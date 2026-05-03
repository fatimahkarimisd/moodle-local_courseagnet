<?php
// This file is part of Course Agent - AI Course Creator Plugin for Moodle.

/**
 * AJAX endpoint for local_courseagent actions.
 *
 * @package   local_courseagent
 * @copyright 2026 Course Agent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/api.php');
require_once(__DIR__ . '/classes/extractor.php');

use local_courseagent\provider;
use local_courseagent\api;

/**
 * Write progress update to a temp file for client polling.
 */
function courseagent_write_progress($step, $percent, $message) {
    global $USER;
    $dir = make_temp_directory('courseagent');
    $file = $dir . '/progress_' . $USER->id . '.json';
    $data = [
        'step'     => $step,
        'percent'  => $percent,
        'message'  => $message,
        'time'     => time(),
    ];
    file_put_contents($file, json_encode($data) . "\n", FILE_APPEND);
}

// Require login and capability.
$context = context_system::instance();
require_login();
$PAGE->set_context($context);
require_capability('local/courseagent:createcourse', $context);
require_sesskey();

// Get action parameter.
$action = required_param('action', PARAM_ALPHAEXT);

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'generate':
            // Generate course outline using AI.
            $topic = optional_param('topic', '', PARAM_TEXT);
            $level = optional_param('level', 'intermediate', PARAM_TEXT);
            $numsections = optional_param('numsections', 4, PARAM_INT);
            $includequiz = optional_param('includequiz', true, PARAM_BOOL);
            $includeassignment = optional_param('includeassignment', false, PARAM_BOOL);
            $useemojis = optional_param('useemojis', false, PARAM_BOOL);
            $usesvg = optional_param('usesvg', false, PARAM_BOOL);
            $providerid = optional_param('provider', 0, PARAM_INT);
            $model = optional_param('model', '', PARAM_TEXT);
            $extractedcontent = optional_param('extracted_content', '', PARAM_RAW);
            $customtitle = optional_param('custom_title', '', PARAM_TEXT);

            // Require at least a topic or uploaded content.
            if (empty(trim($topic)) && empty(trim($extractedcontent))) {
                throw new Exception('Please enter a course topic or upload a document.');
            }

            // Clear previous progress file.
            $progdir = make_temp_directory('courseagent');
            $progfile = $progdir . '/progress_' . $USER->id . '.json';
            @unlink($progfile);

            courseagent_write_progress(1, 10, 'Preparing course outline...');

            // Generate course using AI.
            $api = new api();
            $coursedata = $api->generate_course_outline(
                $topic, $level, $numsections,
                $includequiz, $includeassignment,
                $providerid > 0 ? $providerid : null,
                $model ?: null,
                $extractedcontent ?: null,
                $customtitle ?: null,
                $useemojis,
                $usesvg
            );

            courseagent_write_progress(3, 95, 'Finalizing course...');

            // Store in session for preview page.
            global $SESSION;
            $SESSION->courseagent_preview = $coursedata;

            courseagent_write_progress(3, 100, 'Course generated successfully!');

            echo json_encode([
                'success'       => true,
                'data'          => $coursedata,
                'used_provider' => $coursedata->_used_provider_name ?? null,
                'used_model'    => $coursedata->_used_model ?? null,
                'fallback_log'  => $coursedata->_fallback_log ?? [],
            ]);
            break;

        case 'publish':
            // Publish course to Moodle.
            $jsondata = file_get_contents('php://input');
            $coursedata = json_decode($jsondata);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(get_string('error_invalid_json', 'local_courseagent'));
            }

            $api = new api();
            $courseid = $api->publish_course($coursedata);
            $courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);

            echo json_encode([
                'success' => true,
                'course_id' => $courseid,
                'course_url' => $courseurl->out(false),
            ]);
            break;

        case 'test_provider':
            // Test AI provider connection.
            $providerid = required_param('providerid', PARAM_INT);

            $result = provider::test_connection($providerid);

            echo json_encode([
                'success' => $result->success,
                'message' => $result->message,
                'httpcode' => $result->httpcode,
                'ai_response' => $result->ai_response ?? null,
                'response' => $result->response,
                'debug' => $result->debug ?? null,
            ]);
            break;

        case 'test_provider_raw':
            // Test provider connection with raw parameters (for unsaved forms).
            $baseurl   = required_param('baseurl', PARAM_RAW_TRIMMED);
            $endpoint  = optional_param('endpoint', '', PARAM_RAW_TRIMMED);
            $apikey    = required_param('apikey', PARAM_RAW_TRIMMED);
            $model     = optional_param('model', '', PARAM_TEXT);
            $apiformat = optional_param('api_format', 'openai', PARAM_ALPHA);

            $result = provider::test_connection_raw($baseurl, $endpoint, $apikey, $model ?: null, $apiformat);

            echo json_encode([
                'success' => $result->success,
                'message' => $result->message,
                'httpcode' => $result->httpcode,
                'ai_response' => $result->ai_response ?? null,
                'response' => $result->response,
                'debug' => $result->debug ?? null,
            ]);
            break;

        case 'get_progress':
            // Read progress updates from temp file.
            $progdir = make_temp_directory('courseagent');
            $progfile = $progdir . '/progress_' . $USER->id . '.json';
            $lines = [];
            if (file_exists($progfile)) {
                $content = file_get_contents($progfile);
                $rawlines = array_filter(explode("\n", trim($content)));
                foreach ($rawlines as $line) {
                    $decoded = json_decode($line);
                    if ($decoded) {
                        $lines[] = $decoded;
                    }
                }
            }
            // Return the latest entry only.
            $latest = !empty($lines) ? $lines[count($lines) - 1] : null;
            echo json_encode([
                'success'  => true,
                'progress' => $latest,
            ]);
            break;

        case 'get_models':
            // Get models for a provider.
            $providerid = required_param('providerid', PARAM_INT);

            $provider = provider::get($providerid);
            if (!$provider) {
                throw new Exception('Provider not found');
            }

            $models = json_decode($provider->models, true) ?: [];

            echo json_encode([
                'success' => true,
                'models' => $models,
            ]);
            break;

        case 'extract_content':
            // Extract text from an uploaded file.
            if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $errcodes = [
                    UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize.',
                    UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE.',
                    UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                    UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                    UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
                ];
                $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
                throw new Exception($errcodes[$code] ?? 'File upload failed (code ' . $code . ')');
            }

            $tmppath  = $_FILES['file']['tmp_name'];
            $filename = $_FILES['file']['name'];
            $maxchars = 100000;

            $extractor = new local_courseagent\extractor();
            $text = $extractor->extract($tmppath, $filename, $maxchars);

            echo json_encode([
                'success'  => true,
                'text'     => $text,
                'charcount' => mb_strlen($text),
                'filename' => $filename,
            ]);
            break;

        default:
            throw new Exception(get_string('error_invalid_action', 'local_courseagent'));
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
