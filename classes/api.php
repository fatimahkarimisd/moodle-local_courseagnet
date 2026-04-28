<?php
// This file is part of Course Agent - AI Course Creator Plugin for Moodle

/**
 * @package   local_courseagent
 * @copyright 2026 Course Agent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseagent;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/externallib.php');


/**
 * Course Agent API class - handles course generation and publishing.
 *
 * @package   local_courseagent
 * @copyright 2026 Course Agent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Generate course outline using AI.
     *
     * @param string $topic Course topic
     * @param string $level Course level (beginner, intermediate, advanced)
     * @param int $numsections Number of sections
     * @param bool $includequiz Include quizzes
     * @param bool $includeassignment Include assignments
     * @param int|null $providerid Provider ID or null for default
     * @param string|null $model AI model name or null for first available
     * @return \stdClass Course data
     */
    public function generate_course_outline($topic, $level, $numsections,
                                            $includequiz = true, $includeassignment = false,
                                            $providerid = null, $model = null,
                                            $uploadedcontent = null, $customtitle = null,
                                            $useemojis = false, $usesvg = false) {
        global $USER;

        // Build prompt for AI.
        $prompt = $this->build_generation_prompt($topic, $level, $numsections,
                                                  $includequiz, $includeassignment,
                                                  $uploadedcontent, $customtitle,
                                                  $useemojis, $usesvg);

        $this->write_progress(1, 15, 'Building course outline...');

        // Build an ordered list of (providerid, model) pairs to try.
        // Order: requested provider+model first, then all other enabled providers in order.
        $attempts = $this->build_fallback_attempts($providerid, $model);

        $lasterror  = null;
        $fallbacklog = [];  // Each entry: ['provider' => name, 'model' => id, 'reason' => string]

        $attemptindex = 0;
        foreach ($attempts as $attempt) {
            try {
                $attemptindex++;
                $response = provider::call_api($prompt, $attempt['providerid'], $attempt['model']);

                $this->write_progress(2, 65, 'AI response received, processing...');

                // Strip markdown code fences if present.
                $rawresponse = trim($response);
                if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/s', $rawresponse, $matches)) {
                    $rawresponse = trim($matches[1]);
                }

                // DEBUG: log raw response (first 3000 chars) to server error log.
                error_log('[CourseAgent] Raw AI response before sanitize (attempt ' . $attemptindex . '): ' . substr($rawresponse, 0, 3000));

                // Sanitize control characters that break json_decode().
                // AI models (especially via OpenRouter) may inject raw control
                // chars (0x00-0x1F except allowed whitespace) inside JSON
                // string values, causing "Control character error".
                // Step 1: Remove illegal control chars (0x00-0x08, 0x0B, 0x0C, 0x0E-0x1F).
                $response = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $rawresponse);
                // Step 2: Escape literal bare CR/LF/TAB inside JSON string values.
                // A bare \n or \r inside a JSON string value is invalid; it must be \\n.
                // We use a callback so we only touch content inside double-quoted strings.
                $response = preg_replace_callback('/"((?:[^"\\\\]|\\\\.)*)"/s', function($m) {
                    $inner = $m[1];
                    // Replace unescaped literal newlines/tabs with their escaped forms.
                    $inner = preg_replace('/(?<!\\\\)\r/', '\\r', $inner);
                    $inner = preg_replace('/(?<!\\\\)\n/', '\\n', $inner);
                    $inner = preg_replace('/(?<!\\\\)\t/', '\\t', $inner);
                    return '"' . $inner . '"';
                }, $response);

                // DEBUG: log sanitized response (first 3000 chars) to server error log.
                error_log('[CourseAgent] Sanitized AI response before json_decode (attempt ' . $attemptindex . '): ' . substr($response, 0, 3000));

                $course_data = json_decode($response);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Last resort: try with deeper error recovery.
                    $course_data = json_decode($response, false, 512, JSON_INVALID_UTF8_IGNORE);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $debugpreview = substr($response, 0, 2000);
                        error_log('[CourseAgent] JSON parse FAILED (attempt ' . $attemptindex . '). Error: ' . json_last_error_msg() . ' | Preview: ' . $debugpreview);
                        throw new \Exception(
                            'Failed to parse AI response as JSON: ' . json_last_error_msg() .
                            "\n\n--- RAW AI RESPONSE (first 2000 chars) ---\n" . $debugpreview .
                            "\n--- END RAW AI RESPONSE ---"
                        );
                    }
                }
                if (empty($course_data->title) || empty($course_data->sections)) {
                    throw new \Exception('AI returned incomplete course structure. Missing title or sections.');
                }
                $returnedsectioncount = count($course_data->sections);
                if ($returnedsectioncount != $numsections) {
                    throw new \Exception(
                        "AI returned {$returnedsectioncount} sections but {$numsections} were requested. " .
                        "Rejecting and trying fallback."
                    );
                }

                // Attach which provider/model was actually used + fallback log.
                $course_data->_used_provider_id   = $attempt['providerid'];
                $course_data->_used_provider_name = $attempt['providername'];
                $course_data->_used_model         = $attempt['model'];
                $course_data->_fallback_log        = $fallbacklog;

                return $course_data;

            } catch (\Exception $e) {
                $lasterror = $e->getMessage();
                $isratelimit = $this->is_rate_limit_error($lasterror);

                $fallbacklog[] = [
                    'provider' => $attempt['providername'],
                    'model'    => $attempt['model'] ?: '(default)',
                    'reason'   => $isratelimit ? 'rate_limit' : 'error',
                    'message'  => $lasterror,
                ];

                // Only continue fallback chain on rate-limit errors.
                if (!$isratelimit) {
                    throw $e;
                }
                // Rate limited — try next.
            }
        }

        // All attempts exhausted.
        throw new \Exception('All AI providers and models hit rate limits. Last error: ' . $lasterror);
    }

    /**
     * Build an ordered list of (providerid, model) attempts for fallback.
     * First: requested provider with requested model.
     * Then: same provider's other models.
     * Then: other enabled providers each with their first model.
     *
     * @param int|null    $providerid Requested provider ID
     * @param string|null $model      Requested model
     * @return array Array of ['providerid', 'model', 'providername']
     */
    private function build_fallback_attempts(?int $providerid, ?string $model): array {
        $allproviders  = provider::get_all(true); // enabled only, sorted
        $attempts      = [];
        $seen          = [];

        // Helper to add without duplication.
        $add = function($pid, $mod, $name) use (&$attempts, &$seen) {
            $key = $pid . '|' . $mod;
            if (!isset($seen[$key])) {
                $seen[$key]  = true;
                $attempts[]  = ['providerid' => $pid, 'model' => $mod, 'providername' => $name];
            }
        };

        // 1. Requested provider + requested model first.
        if ($providerid) {
            $reqprovider = provider::get($providerid);
            if ($reqprovider) {
                $models = json_decode($reqprovider->models, true) ?: [];
                $firstmodel = $model ?: ($models[0] ?? '');
                $add($providerid, $firstmodel, $reqprovider->name);
                // Add remaining models of same provider.
                foreach ($models as $m) {
                    if ($m !== $firstmodel) {
                        $add($providerid, $m, $reqprovider->name);
                    }
                }
            }
        } else {
            // No specific provider requested — use default first.
            $def = provider::get_default();
            if ($def) {
                $models     = json_decode($def->models, true) ?: [];
                $firstmodel = $model ?: ($models[0] ?? '');
                $add($def->id, $firstmodel, $def->name);
                foreach ($models as $m) {
                    if ($m !== $firstmodel) {
                        $add($def->id, $m, $def->name);
                    }
                }
            }
        }

        // 2. All other enabled providers, each with their full model list.
        foreach ($allproviders as $p) {
            $models = json_decode($p->models, true) ?: [];
            $first  = $models[0] ?? '';
            $add($p->id, $first, $p->name);
            foreach ($models as $m) {
                if ($m !== $first) {
                    $add($p->id, $m, $p->name);
                }
            }
        }

        return $attempts;
    }

    /**
     * Determine whether an exception message indicates a rate limit.
     *
     * @param string $message Error message
     * @return bool
     */
    private function is_rate_limit_error(string $message): bool {
        $patterns = [
            'rate limit', 'rate_limit', 'ratelimit',
            '429', 'too many requests',
            'quota exceeded', 'quota_exceeded',
            'resource_exhausted', 'resource exhausted',
            'tokens per', 'requests per',
            'model_rate_limit',
        ];
        $lower = strtolower($message);
        foreach ($patterns as $p) {
            if (strpos($lower, $p) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build prompt for course generation.
     */
    private function build_generation_prompt($topic, $level, $numsections, $includequiz, $includeassignment,
                                             $uploadedcontent = null, $customtitle = null,
                                             $useemojis = false, $usesvg = false) {
        $maxsections = get_config('local_courseagent', 'max_sections') ?: 8;
        $maxquiz     = get_config('local_courseagent', 'max_quiz_questions') ?: 7;

        // Build context section.
        $contextsection = '';
        if (!empty($uploadedcontent)) {
            $snippet = mb_substr($uploadedcontent, 0, 8000);
            $contextsection = "\n\nSOURCE DOCUMENT CONTENT (use this as the primary knowledge base for the course):\n"
                . "--- BEGIN DOCUMENT ---\n"
                . $snippet
                . (mb_strlen($uploadedcontent) > 8000 ? '\n[... document continues ...]' : '')
                . "\n--- END DOCUMENT ---\n";
        }

        // Title instruction.
        $titleinstruction = !empty($customtitle)
            ? '"title": "' . addslashes($customtitle) . '"'
            : '"title": "A descriptive, engaging course title derived from the topic/content"';

        // Topic line.
        $topicline = !empty($topic) ? "Topic: {$topic}" : 'Topic: (derive from the source document above)';

        $quizcount    = min($maxquiz, 5); // default 5 questions per section
        $quizinstruct = $includequiz
            ? "Yes — generate exactly {$quizcount} MCQ questions per section"
            : 'No';

        $prompt  = "You are a senior instructional designer with expertise in creating comprehensive, university-level online courses.\n";
        $prompt .= "Your task is to create a COMPLETE, DETAILED course — not a brief outline.\n\n";
        $prompt .= "{$topicline}\n";
        $prompt .= "Level: {$level}\n";
        $prompt .= "Number of Sections: {$numsections} (maximum {$maxsections})\n";
        $prompt .= "Include Quizzes: {$quizinstruct}\n";
        $prompt .= "Include Assignments: " . ($includeassignment ? 'Yes' : 'No') . "\n";
        $prompt .= $contextsection;

        $prompt .= "\n\n== CRITICAL CONTENT REQUIREMENTS ==\n";
        $prompt .= "For EVERY section, you MUST write a COMPLETE, DETAILED lesson with ALL of the following:\n";
        $prompt .= "  1. An introduction paragraph (2-3 sentences) explaining what learners will discover.\n";
        $prompt .= "  2. At least 3-5 main concept headings, each with 2-4 paragraphs of explanation.\n";
        $prompt .= "  3. Concrete real-world examples and use-cases for each concept.\n";
        $prompt .= "  4. A 'Key Takeaways' section with 5-7 bullet points.\n";
        $prompt .= "  5. A 'Further Reading / Practice' section with suggestions.\n";
        $prompt .= "The content_html field MUST contain well-structured HTML with <h2>, <h3>, <p>, <ul>, <ol>, <strong>, <em>, <blockquote>, and <pre><code> tags as appropriate.\n";
        $prompt .= "Each lesson MUST be at minimum 800 words — comprehensive enough for a student to learn the topic without any other resources.\n\n";

        // Emoji styling instructions.
        if ($useemojis) {
            $prompt .= "== EMOJI ENHANCEMENTS ==\n";
            $prompt .= "Sprinkle relevant emojis throughout the content to make it engaging and visually appealing.\n";
            $prompt .= "Use emojis in headings, bullet points, and key concepts where appropriate.\n";
            $prompt .= "Examples: 📚 for learning, 💡 for tips, 🎯 for objectives, ⚠️ for warnings, ✅ for checklists, 🔍 for examples.\n\n";
        }

        // SVG diagram instructions.
        if ($usesvg) {
            $prompt .= "== SVG DIAGRAMS ==\n";
            $prompt .= "Where visual explanations would help understanding, include simple inline SVG diagrams.\n";
            $prompt .= "Embed SVG code directly in the HTML content using <svg> tags.\n";
            $prompt .= "Create simple diagrams like: flowcharts, process diagrams, concept maps, comparison charts, or illustrative icons.\n";
            $prompt .= "Keep SVGs clean, minimalist, and directly relevant to the concept being explained.\n";
            $prompt .= "Use proper viewBox and reasonable dimensions (width='300-600' height='200-400').\n\n";
        }

        if ($includequiz) {
            $prompt .= "== MCQ QUIZ REQUIREMENTS ==\n";
            $prompt .= "For EVERY section generate exactly {$quizcount} multiple-choice questions that test deep understanding.\n";
            $prompt .= "Each question MUST have exactly 4 answer options (A, B, C, D).\n";
            $prompt .= "correct_answer is the 0-based index of the correct option (0=A, 1=B, 2=C, 3=D).\n";
            $prompt .= "Mix question types: factual recall, conceptual understanding, and application.\n\n";
        }

        if ($includeassignment) {
            $prompt .= "== ASSIGNMENT REQUIREMENTS ==\n";
            $prompt .= "For EVERY section, include a practical assignment that reinforces the lesson content.\n";
            $prompt .= "The assignment should have clear instructions, a descriptive title, and an estimated word count.\n\n";
        }

        $prompt .= "== CRITICAL SECTION COUNT REQUIREMENT ==\n";
        $prompt .= "You MUST generate EXACTLY {$numsections} sections.\n";
        $prompt .= "The 'sections' array in your JSON response MUST contain precisely {$numsections} section objects — no more, no less.\n";
        $prompt .= "Do not stop early. Do not return fewer sections than requested.\n\n";

        $prompt .= "Return ONLY a valid JSON object — no markdown fences, no extra text before or after.\n";
        $prompt .= "Use this EXACT JSON structure (repeat the section template exactly {$numsections} times):\n";
        $prompt .= "{\n";
        $prompt .= "  {$titleinstruction},\n";
        $prompt .= '  "summary": "A rich 2-3 sentence course description explaining what students will learn and why it matters",' . "\n";
        $prompt .= '  "sections": [' . "\n";
        $prompt .= "    {\n";
        $prompt .= '      "name": "Section title (clear and descriptive)",' . "\n";
        $prompt .= '      "description": "2-3 sentence section overview",' . "\n";
        $prompt .= "      \"lesson\": {\n";
        $prompt .= '        "summary": "1-2 sentence lesson intro shown to students before they open the lesson",' . "\n";
        $prompt .= '        "content_html": "<h2>Introduction</h2><p>...</p><h2>Core Concept 1</h2><p>...</p><h3>Example</h3><p>...</p><h2>Core Concept 2</h2><p>...</p><h2>Key Takeaways</h2><ul><li>...</li></ul><h2>Further Reading</h2><p>...</p>"' . "\n";
        $prompt .= "      }";

        if ($includequiz) {
            $prompt .= ",\n      \"quiz\": {\n";
            $prompt .= "        \"name\": \"Section Quiz\",\n";
            $prompt .= '        "questions": [' . "\n";
            $prompt .= "          {\n";
            $prompt .= '            "question": "Full question text ending with a question mark?",' . "\n";
            $prompt .= '            "options": ["Option A text", "Option B text", "Option C text", "Option D text"],' . "\n";
            $prompt .= '            "correct_answer": 0,' . "  // 0-based index\n";
            $prompt .= '            "explanation": "Brief explanation of why this answer is correct",' . "\n";
            $prompt .= '            "points": 1' . "\n";
            $prompt .= "          }\n";
            $prompt .= "        ]\n";
            $prompt .= "      }";
        }

        if ($includeassignment) {
            $prompt .= ",\n      \"assignment\": {\n";
            $prompt .= '        "title": "Descriptive assignment title (e.g., \'Research and Analysis Essay\' or \'Code Implementation Task\')",' . "\n";
            $prompt .= '        "description": "2-3 sentence description of what the student needs to do and the learning objective",' . "\n";
            $prompt .= '        "instructions": ["Clear step 1 the student should follow", "Step 2 with specific requirements", "Step 3 with submission guidelines"],' . "\n";
            $prompt .= '        "word_count": 500' . "\n";
            $prompt .= "      }";
        }

        $prompt .= "\n    }\n  ]\n}";

        $prompt .= "\n\nREMEMBER: Every lesson content_html must be thorough — at least 800 words of educational content.";
        $prompt .= " Every quiz must have exactly {$quizcount} MCQ questions if quizzes are enabled.";
        if ($includeassignment) {
            $prompt .= " Every section must include a complete assignment with title, description, and step-by-step instructions.";
        }
        $prompt .= " Return valid JSON only — no surrounding markdown.";

        return $prompt;
    }

    /**
     * Publish course to Moodle.
     *
     * @param \stdClass $course_data Course data
     * @return int Moodle course ID
     */
    public function publish_course($course_data) {
        global $DB, $USER;

        // Validate course data.
        if (empty($course_data->title)) {
            throw new \Exception('Course title is required');
        }
        if (empty($course_data->sections) || !is_array($course_data->sections)) {
            throw new \Exception('Course must have sections');
        }

        $coursename  = $course_data->title;
        $numsections = count($course_data->sections);
        $shortname   = substr(
            preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', strtolower($coursename))),
            0, 50
        ) . '_' . time();

        // Create course via core external API (recommended for local plugins).
        // Note: numsections courseformatoption was removed in Moodle 4.0+.
        // We create sections explicitly after course creation.
        $newcourses = \core_course_external::create_courses([[
            'fullname'      => $coursename,
            'shortname'     => $shortname,
            'categoryid'    => 1,
            'summary'       => !empty($course_data->summary) ? $course_data->summary : '',
            'summaryformat' => FORMAT_HTML,
            'format'        => 'topics',
            'visible'       => 0,
        ]]);
        $courseid = $newcourses[0]['id'];
        $course   = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Create ALL required sections upfront (Moodle 4.0+ no longer uses
        // the numsections courseformatoption — sections must be created explicitly).
        \course_create_sections_if_missing($course, range(0, $numsections));

        // Populate sections and add modules.
        foreach ($course_data->sections as $index => $section) {
            $sectionnum = $index + 1;

            // Update section name and summary directly in DB.
            $DB->set_field('course_sections', 'name', $section->name,
                           ['course' => $courseid, 'section' => $sectionnum]);
            if (!empty($section->description)) {
                $DB->set_field('course_sections', 'summary', $section->description,
                               ['course' => $courseid, 'section' => $sectionnum]);
            }

            // Create lesson page.
            if (!empty($section->lesson)) {
                $this->create_lesson_page($course, $sectionnum, $section);
            }

            // Create quiz.
            if (!empty($section->quiz) && !empty($section->quiz->questions)) {
                $this->create_quiz($course, $sectionnum, $section);
            }

            // Create assignment.
            if (!empty($section->assignment)) {
                debugging('Course Agent: Creating assignment for section ' . $sectionnum .
                          ' data: ' . json_encode($section->assignment), DEBUG_DEVELOPER);
                $this->create_assignment($course, $sectionnum, $section->assignment);
            } else {
                debugging('Course Agent: No assignment data for section ' . $sectionnum .
                          ' section data: ' . json_encode($section), DEBUG_DEVELOPER);
            }
        }

        // Rebuild course cache.
        \rebuild_course_cache($courseid, true);

        // Save session record.
        $session               = new \stdClass();
        $session->userid       = $USER->id;
        $session->courseid     = $courseid;
        $session->status       = 'published';
        $session->course_json  = json_encode($course_data);
        $session->timecreated  = time();
        $session->timemodified = time();
        $DB->insert_record('courseagent_sessions', $session);

        return $courseid;
    }

    /**
     * Create a stub course_modules row so we have a cmid BEFORE calling
     * {module}_add_instance() — Moodle's own module libs (e.g. page_add_instance)
     * expect $data->coursemodule to already exist and update it themselves.
     *
     * @param  stdClass $course
     * @param  string   $modulename  e.g. 'page', 'quiz', 'assign'
     * @return int      The new course_modules.id (cmid)
     */
    private function create_cm_stub($course, $modulename) {
        global $DB;

        $moduleid = $DB->get_field('modules', 'id', ['name' => $modulename], MUST_EXIST);

        $cm                      = new \stdClass();
        $cm->course              = $course->id;
        $cm->module              = $moduleid;
        $cm->instance            = 0;   // updated by _add_instance()
        $cm->section             = 0;   // moved by course_add_cm_to_section()
        $cm->visible             = 1;
        $cm->visibleold          = 1;
        $cm->visibleoncoursepage = 1;
        $cm->groupmode           = 0;
        $cm->groupingid          = 0;
        $cm->added               = time();
        $cm->id = $DB->insert_record('course_modules', $cm);

        return $cm->id;
    }

    /**
     * Move a cm to its target section after _add_instance() has set the instance id.
     */
    private function place_cm_in_section($course, $cmid, $sectionnum) {
        \course_add_cm_to_section($course, $cmid, $sectionnum);
    }

    /**
     * Create a lesson page (mod_page).
     */
    private function create_lesson_page($course, $sectionnum, $section) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/page/lib.php');
        require_once($CFG->dirroot . '/lib/resourcelib.php');

        // Must create the CM stub first — page_add_instance() uses $data->coursemodule
        // to update course_modules.instance after inserting into mdl_page.
        $cmid = $this->create_cm_stub($course, 'page');

        $content = !empty($section->lesson->content_html) ? $section->lesson->content_html : '';
        if (!empty($section->lesson->key_points) && is_array($section->lesson->key_points)) {
            $content .= '<h3>Key Points</h3><ul>';
            foreach ($section->lesson->key_points as $kp) {
                $content .= '<li>' . $kp . '</li>';
            }
            $content .= '</ul>';
        }

        $moduleinfo                   = new \stdClass();
        $moduleinfo->coursemodule     = $cmid;   // required by page_add_instance()
        $moduleinfo->course           = $course->id;
        $moduleinfo->name             = $section->name . ' - Lesson';
        $moduleinfo->intro            = !empty($section->lesson->summary) ? $section->lesson->summary : '';
        $moduleinfo->introformat      = FORMAT_HTML;
        $moduleinfo->content          = $content;
        $moduleinfo->contentformat    = FORMAT_HTML;
        $moduleinfo->trusttext        = 1;  // Prevent Moodle from stripping SVG/emoji HTML.
        $moduleinfo->display          = RESOURCELIB_DISPLAY_OPEN;
        $moduleinfo->printintro       = 0;
        $moduleinfo->printlastmodified = 1;
        $moduleinfo->timemodified     = time();

        \page_add_instance($moduleinfo, null);
        $this->place_cm_in_section($course, $cmid, $sectionnum);
    }

    /**
     * Create a quiz (mod_quiz) and populate it with AI-generated MCQ questions.
     */
    private function create_quiz($course, $sectionnum, $section) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/lib/questionlib.php');
        require_once($CFG->dirroot . '/question/engine/lib.php');
        require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');

        $cmid = $this->create_cm_stub($course, 'quiz');

        $questions    = $section->quiz->questions ?? [];
        $numquestions = count($questions);
        $totalpoints  = max($numquestions, 1);

        // Generate descriptive quiz name based on section content.
        $sectionnum = (int) $sectionnum;
        $quiznametopic = !empty($section->name) ? $section->name : 'Section ' . $sectionnum;
        // Clean up the topic for use in the quiz name.
        $quiznametopic = preg_replace('/\s*-\s*Lesson$/i', '', $quiznametopic);
        $quiznametopic = preg_replace('/\s*-\s*Quiz$/i', '', $quiznametopic);
        $quizname = 'Section ' . $sectionnum . ': ' . $quiznametopic . ' - Knowledge Check';

        $moduleinfo                              = new \stdClass();
        $moduleinfo->coursemodule               = $cmid;
        $moduleinfo->course                     = $course->id;
        $moduleinfo->name                       = $quizname;
        $moduleinfo->intro                      = 'Test your understanding of this section.';
        $moduleinfo->introformat                = FORMAT_HTML;
        $moduleinfo->timeopen                   = 0;
        $moduleinfo->timeclose                  = 0;
        $moduleinfo->timelimit                  = 0;
        $moduleinfo->overduehandling            = 'autoabandon';
        $moduleinfo->graceperiod                = 0;
        $moduleinfo->attempts                   = 3;
        $moduleinfo->grademethod                = 1;
        $moduleinfo->decimalpoints              = 2;
        $moduleinfo->questiondecimalpoints      = -1;
        $moduleinfo->shuffleanswers             = 1;
        $moduleinfo->sumgrades                  = (float) $totalpoints;
        $moduleinfo->grade                      = 100;
        $moduleinfo->timecreated                = time();
        $moduleinfo->timemodified               = time();
        $moduleinfo->preferredbehaviour       = 'deferredfeedback';
        $moduleinfo->browsersecurity            = '-';
        $moduleinfo->delay1                     = 0;
        $moduleinfo->delay2                     = 0;
        $moduleinfo->showuserpicture            = 0;
        $moduleinfo->showblocks                 = 0;
        $moduleinfo->completionattemptsexhausted = 0;
        $moduleinfo->completionpass             = 0;
        $moduleinfo->allowofflineattempts       = 0;
        $moduleinfo->quizpassword               = '';
        $moduleinfo->reviewattempt              = 0x11110;
        $moduleinfo->reviewcorrectness          = 0x11110;
        $moduleinfo->reviewmarks                = 0x11110;
        $moduleinfo->reviewspecificfeedback     = 0x11110;
        $moduleinfo->reviewgeneralfeedback      = 0x11110;
        $moduleinfo->reviewrightanswer          = 0x11110;
        $moduleinfo->reviewoverallfeedback      = 0x11110;

        $quizid = \quiz_add_instance($moduleinfo, null);
        $this->place_cm_in_section($course, $cmid, $sectionnum);

        // ── Add AI-generated MCQ questions to the quiz ─────────────────────
        if (!empty($questions)) {
            // Get or create the course question category.
            $coursecontext = \context_course::instance($course->id);
            $categoryid    = $this->get_or_create_question_category($coursecontext, $course->fullname);

            $slot = 1;
            foreach ($questions as $q) {
                if (empty($q->question) || empty($q->options) || !is_array($q->options) || count($q->options) < 2) {
                    continue; // skip malformed questions
                }

                $questionid = $this->create_multichoice_question(
                    $categoryid,
                    $q,
                    $coursecontext
                );

                if ($questionid) {
                    $points = !empty($q->points) ? (float) $q->points : 1.0;
                    \quiz_add_quiz_question($questionid, (object)['id' => $quizid], $slot, $points);
                    $slot++;
                }
            }

            // Recalculate quiz sumgrades based on actual questions added.
            // Use Moodle 5.x grade_calculator API (quiz_update_sumgrades is deprecated).
            \mod_quiz\quiz_settings::create($quizid)->get_grade_calculator()->recompute_quiz_sumgrades();
        }
    }

    /**
     * Get or create the default question category for a course context.
     */
    private function get_or_create_question_category($context, $coursename) {
        global $DB;

        $existing = $DB->get_record('question_categories', [
            'contextid' => $context->id,
            'parent'    => 0,
        ]);
        if ($existing) {
            return $existing->id;
        }

        // Create a top-level category.
        $cat              = new \stdClass();
        $cat->name        = get_string('defaultfor', 'question', $coursename);
        $cat->info        = '';
        $cat->infoformat  = FORMAT_HTML;
        $cat->contextid   = $context->id;
        $cat->parent      = 0;
        $cat->sortorder   = 999;
        $cat->stamp       = make_unique_id_code();
        return $DB->insert_record('question_categories', $cat);
    }

    /**
     * Create a multichoice question in the question bank.
     *
     * @param  int       $categoryid
     * @param  stdClass  $q  Question data from AI JSON
     * @param  context   $context
     * @return int|false  Question ID or false on failure
     */
    private function create_multichoice_question($categoryid, $q, $context) {
        global $DB, $USER;

        $options   = array_values((array) $q->options);
        $correct   = isset($q->correct_answer) ? (int) $q->correct_answer : 0;
        $correct   = max(0, min($correct, count($options) - 1));
        $points    = !empty($q->points) ? (float) $q->points : 1.0;
        $explanation = !empty($q->explanation) ? (string) $q->explanation : '';

        try {
            // ── question_bank_entries (Moodle 5.x requirement) ───────────────
            $questionbankentry = new \stdClass();
            $questionbankentry->questioncategoryid = $categoryid;
            $questionbankentry->idnumber = null;
            $questionbankentry->ownerid = $USER->id ?? 0;
            $bankentryid = $DB->insert_record('question_bank_entries', $questionbankentry);

            // ── question base record ────────────────────────────────────────
            $question              = new \stdClass();
            $question->category   = $categoryid;
            $question->qtype      = 'multichoice';
            $question->name       = shorten_text(strip_tags($q->question), 255);
            $question->questiontext        = '<p>' . s($q->question) . '</p>';
            $question->questiontextformat  = FORMAT_HTML;
            $question->generalfeedback     = $explanation ? '<p>' . s($explanation) . '</p>' : '';
            $question->generalfeedbackformat = FORMAT_HTML;
            $question->defaultmark  = $points;
            $question->penalty      = 0.3333333;
            $question->hidden       = 0;
            $question->timecreated  = time();
            $question->timemodified = time();
            $question->createdby    = $USER->id ?? 0;
            $question->modifiedby   = $USER->id ?? 0;
            $question->stamp        = make_unique_id_code();
            $question->version      = make_unique_id_code();
            $question->contextid    = $context->id;

            $questionid = $DB->insert_record('question', $question);

            // ── question_versions (Moodle 5.x requirement) ───────────────────
            $questionversion = new \stdClass();
            $questionversion->questionbankentryid = $bankentryid;
            $questionversion->questionid = $questionid;
            $questionversion->version = 1;
            $questionversion->status = 'ready';
            $DB->insert_record('question_versions', $questionversion);

            // ── qtype_multichoice_options ────────────────────────────────────
            $mcoptions                      = new \stdClass();
            $mcoptions->questionid          = $questionid;
            $mcoptions->layout              = 0; // vertical
            $mcoptions->single              = 1; // single correct answer
            $mcoptions->shuffleanswers      = 1;
            $mcoptions->correctfeedback     = 'Correct!';
            $mcoptions->correctfeedbackformat      = FORMAT_HTML;
            $mcoptions->partiallycorrectfeedback   = 'Partially correct.';
            $mcoptions->partiallycorrectfeedbackformat = FORMAT_HTML;
            $mcoptions->incorrectfeedback          = 'Incorrect. The correct answer was: ' . s($options[$correct]);
            $mcoptions->incorrectfeedbackformat    = FORMAT_HTML;
            $mcoptions->answernumbering     = 'abc';
            $mcoptions->shownumcorrect      = 0;
            $DB->insert_record('qtype_multichoice_options', $mcoptions);

            // ── question_answers (one per option) ───────────────────────────
            foreach ($options as $i => $opttext) {
                $answer                  = new \stdClass();
                $answer->question        = $questionid;
                $answer->answer          = s($opttext);
                $answer->answerformat    = FORMAT_HTML;
                $answer->fraction        = ($i === $correct) ? 1.0 : 0.0;
                $answer->feedback        = ($i === $correct)
                    ? ($explanation ? s($explanation) : 'Correct!')
                    : 'Incorrect.';
                $answer->feedbackformat  = FORMAT_HTML;
                $DB->insert_record('question_answers', $answer);
            }

            return $questionid;

        } catch (\Exception $e) {
            // Log the error but don't abort the whole course creation.
            debugging('Course Agent: Failed to create question: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Create an assignment (mod_assign).
     */
    private function create_assignment($course, $sectionnum, $assignment_data) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/lib.php');

        $cmid = $this->create_cm_stub($course, 'assign');

        // Debug log the incoming assignment data.
        debugging('Course Agent: Creating assignment in section ' . $sectionnum .
                  ' with data: ' . json_encode($assignment_data), DEBUG_DEVELOPER);

        $intro = !empty($assignment_data->description) ? $assignment_data->description : '';
        if (!empty($assignment_data->instructions) && is_array($assignment_data->instructions)) {
            $intro .= '<h4>Instructions</h4><ul>';
            foreach ($assignment_data->instructions as $inst) {
                $intro .= '<li>' . $inst . '</li>';
            }
            $intro .= '</ul>';
        }
        if (!empty($assignment_data->word_count)) {
            $intro .= '<p><strong>Word count:</strong> ' . $assignment_data->word_count . ' words.</p>';
        }

        $moduleinfo                          = new \stdClass();
        $moduleinfo->coursemodule            = $cmid;
        $moduleinfo->course                  = $course->id;
        $moduleinfo->name                    = !empty($assignment_data->title) ? $assignment_data->title : 'Assignment';
        $moduleinfo->intro                   = $intro;
        $moduleinfo->introformat             = FORMAT_HTML;
        $moduleinfo->alwaysshowdescription   = 1;
        $moduleinfo->submissiondrafts        = 0;
        $moduleinfo->requiresubmissionstatement = 0;
        $moduleinfo->sendnotifications       = 0;
        $moduleinfo->sendlatenotifications   = 0;
        $moduleinfo->duedate                 = 0;
        $moduleinfo->allowsubmissionsfromdate = 0;
        $moduleinfo->grade                   = 100;
        $moduleinfo->cutoffdate              = 0;
        $moduleinfo->gradingduedate          = 0;
        $moduleinfo->teamsubmission          = 0;
        $moduleinfo->requireallteammemberssubmit = 0;
        $moduleinfo->teamsubmissiongroupingid = 0;
        $moduleinfo->blindmarking            = 0;
        $moduleinfo->attemptreopenmethod     = 'none';
        $moduleinfo->maxattempts             = -1;
        $moduleinfo->markingworkflow         = 0;
        $moduleinfo->markingallocation       = 0;
        $moduleinfo->assignsubmission_onlinetext_enabled = 1;
        $moduleinfo->assignsubmission_file_enabled       = 1;
        $moduleinfo->assignsubmission_file_maxfiles      = 5;
        $moduleinfo->assignsubmission_file_maxsizebytes  = 10485760; // 10 MB

        // Add required properties that assign_add_instance expects.
        $moduleinfo->courseid   = $course->id;  // Required by Moodle 5.x assign_add_instance.
        $moduleinfo->maxbytes  = $course->maxbytes ?? 10485760;  // Course upload limit.
        $moduleinfo->section   = $sectionnum;
        $moduleinfo->visible   = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->cmidnumber = '';  // ID number (optional, must be set).

        try {
            $instanceid = \assign_add_instance($moduleinfo, null);
            if (!$instanceid) {
                debugging('Course Agent: assign_add_instance returned false for section ' . $sectionnum, DEBUG_DEVELOPER);
                return;
            }
            debugging('Course Agent: Assignment created with instance ID ' . $instanceid . ' in section ' . $sectionnum, DEBUG_DEVELOPER);

            // assign_add_instance does NOT update course_modules.instance
            // (unlike page_add_instance / quiz_after_add_or_update which do).
            // Without this, the CM has instance=0 and the assignment is invisible.
            $DB->set_field('course_modules', 'instance', $instanceid, ['id' => $cmid]);

            $this->place_cm_in_section($course, $cmid, $sectionnum);
        } catch (\Exception $e) {
            error_log('Course Agent: Failed to create assignment in section ' . $sectionnum .
                      ': ' . $e->getMessage());
            debugging('Course Agent: Failed to create assignment in section ' . $sectionnum .
                      ': ' . $e->getMessage() . '\n' . $e->getTraceAsString(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Write progress update to temp file for client polling.
     *
     * @param int    $step    Step number (1=outline, 2=generating, 3=finalizing)
     * @param int    $percent Progress percentage 0-100
     * @param string $message Status message
     */
    private function write_progress($step, $percent, $message) {
        global $USER;
        $dir = make_temp_directory('courseagent');
        $file = $dir . '/progress_' . $USER->id . '.json';
        $data = [
            'step'    => $step,
            'percent' => $percent,
            'message' => $message,
            'time'    => time(),
        ];
        file_put_contents($file, json_encode($data) . "\n", FILE_APPEND);
    }
}