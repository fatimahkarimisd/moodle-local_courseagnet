<?php
// This file is part of Course Agent - AI Course Creator Plugin for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * @package   local_courseagent
 * @copyright 2026 Course Agent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_courseagent\provider;

// Require login and check capability.
$context = context_system::instance();
require_login();
require_capability('local/courseagent:createcourse', $context);

// Setup page.
$pageurl = new moodle_url('/local/courseagent/index.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('create_course', 'local_courseagent'));
$PAGE->set_heading(get_string('create_course', 'local_courseagent'));
$PAGE->set_pagelayout('base');

// Get plugin configuration.
$maxsections      = get_config('local_courseagent', 'max_sections') ?: 8;
$maxquiz          = get_config('local_courseagent', 'max_quiz_questions') ?: 7;
$enableassignments = get_config('local_courseagent', 'enable_assignments') ?: 1;

// Get available providers.
$providers       = provider::get_all(true);
$defaultprovider = provider::get_default();

// Check if any provider is configured.
if (empty($providers)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('error_no_provider', 'local_courseagent'), 'notifyproblem');
    echo $OUTPUT->single_button(
        new moodle_url('/local/courseagent/providers.php'),
        get_string('provider_manage_link', 'local_courseagent'),
        'get'
    );
    echo $OUTPUT->footer();
    exit;
}

// Build provider config for JavaScript.
$providerconfig = [];
foreach ($providers as $p) {
    $models = json_decode($p->models, true) ?: [];
    $providerconfig[$p->id] = [
        'name'      => $p->name,
        'models'    => $models,
        'isdefault' => $p->isdefault,
    ];
}

// Pass configuration to JavaScript.
$jsconfig = [
    'maxSections'      => (int)  $maxsections,
    'maxQuizQuestions' => (int)  $maxquiz,
    'enableAssignments' => (bool) $enableassignments,
    'providers'        => $providerconfig,
    'defaultProviderId' => $defaultprovider ? $defaultprovider->id : 0,
    'wwwroot'          => $CFG->wwwroot,
    'sesskey'          => sesskey(),
];
$PAGE->requires->css(new moodle_url('/local/courseagent/styles.css'));
$PAGE->requires->js_call_amd('local_courseagent/coursecreator', 'init', [$jsconfig]);

echo $OUTPUT->header();
?>

<div id="courseagent-app">
    <div class="row">
        <div class="col-lg-8">
            <p class="text-muted mb-3">Configure your AI-generated curriculum settings.</p>
            <div class="card mb-4">
                <div class="card-body">
                    <form id="courseagent-form">
                        <!-- Course Title -->
                        <div class="form-group">
                            <label for="course-custom-title" class="font-weight-bold">
                                Course Title
                                <span class="text-muted font-weight-normal small ml-1">(Optional override)</span>
                            </label>
                            <input type="text" id="course-custom-title" class="form-control"
                                   placeholder="Leave blank to let the AI choose a title">
                        </div>

                        <!-- Course Topic -->
                        <div class="form-group">
                            <label for="course-topic" class="font-weight-bold">
                                <?php print_string('coursetopic', 'local_courseagent'); ?> <span class="text-danger">*</span>
                            </label>
                            <textarea id="course-topic" class="form-control" rows="4" maxlength="500"
                                      placeholder="Describe the main topics, learning objectives, or paste an existing syllabus outline..."></textarea>
                            <small class="form-text text-muted d-flex justify-content-between">
                                <span>Describe the topic the AI should build the course around.</span>
                                <span id="course-topic-counter" class="text-muted">0 / 500</span>
                            </small>
                        </div>

                        <!-- Upload content — PRO lock -->
                        <div class="form-group mt-3">
                            <label class="font-weight-bold d-flex align-items-center">
                                Upload Your Content
                                <span class="badge badge-warning ml-2" style="font-size:0.7em;">
                                    <i class="fa fa-lock" aria-hidden="true"></i>&nbsp;PRO
                                </span>
                            </label>
                            <p class="text-muted small mb-2">
                                Upload a document and let the AI build the course directly from your material.
                            </p>
                            <div class="courseagent-pro-wrapper">
                                <div class="courseagent-dropzone courseagent-dropzone--locked" aria-hidden="true">
                                    <i class="fa fa-cloud-upload fa-2x text-muted" aria-hidden="true"></i>
                                    <p class="mb-1 mt-2"><strong>Click to upload</strong> or drag &amp; drop</p>
                                    <p class="small text-muted mb-0">
                                        TXT, PDF, DOCX, PPTX, ODT, RTF, MD, CSV, EPUB &mdash; max&nbsp;50&nbsp;MB
                                    </p>
                                </div>
                                <div class="courseagent-pro-overlay">
                                    <div class="text-center px-4">
                                        <i class="fa fa-lock fa-2x text-warning mb-2" aria-hidden="true"></i>
                                        <p class="font-weight-bold mb-1">Pro Feature</p>
                                        <p class="small text-muted mb-0">
                                            Document upload is available in the <strong>Pro version</strong>.<br>
                                            Upgrade to unlock this and other advanced features.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <input type="file" id="upload-file-input" class="d-none" disabled
                                   accept=".txt,.pdf,.docx,.pptx,.odt,.rtf,.md,.csv,.epub">
                            <input type="hidden" id="upload-extracted-text" name="extracted_content">
                        </div>

                        <div class="row">
                            <!-- Level -->
                            <div class="form-group col-md-6">
                                <label for="course-level" class="font-weight-bold">Difficulty Level</label>
                                <select id="course-level" class="custom-select">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate" selected>Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                            <!-- Number of Sections -->
                            <div class="form-group col-md-6">
                                <label for="num-sections" class="font-weight-bold">Number of Sections</label>
                                <input type="number" id="num-sections" class="form-control"
                                       min="2" max="<?php echo $maxsections; ?>" value="4">
                                <small class="form-text text-muted">Between 2 and <?php echo $maxsections; ?> sections.</small>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Included Components -->
                        <div class="form-group">
                            <label class="font-weight-bold mb-3">Included Components</label>

                            <!-- Include Quizzes -->
                            <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded border mb-2">
                                <div class="d-flex align-items-center">
                                    <div class="mr-3">
                                        <span class="badge badge-primary rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;">
                                            <i class="fa fa-question-circle"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold">Include Quizzes</div>
                                        <div class="small text-muted">Generate MCQs at the end of each section</div>
                                    </div>
                                </div>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="include-quiz" checked>
                                    <label class="custom-control-label" for="include-quiz"></label>
                                </div>
                            </div>

                            <?php if ($enableassignments) : ?>
                            <!-- Include Assignments -->
                            <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded border mb-2">
                                <div class="d-flex align-items-center">
                                    <div class="mr-3">
                                        <span class="badge badge-secondary rounded-circle p-2 d-inline-flex align-items-center justify-content-center"
                                              style="width:2.5rem;height:2.5rem;">
                                            <i class="fa fa-pencil-square-o"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold">Include Assignments</div>
                                        <div class="small text-muted">Create practical tasks for learners</div>
                                    </div>
                                </div>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="include-assignment" checked>
                                    <label class="custom-control-label" for="include-assignment"></label>
                                </div>
                            </div>
                            <?php } ?>

                            <!-- Use Emojis -->
                            <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded border mb-2">
                                <div class="d-flex align-items-center">
                                    <div class="mr-3">
                                        <span class="badge badge-info rounded-circle p-2 d-inline-flex align-items-center justify-content-center"
                                              style="width:2.5rem;height:2.5rem;">
                                            <i class="fa fa-smile-o"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold">Use Emojis</div>
                                        <div class="small text-muted">Add relevant emojis to make content more engaging</div>
                                    </div>
                                </div>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="use-emojis">
                                    <label class="custom-control-label" for="use-emojis"></label>
                                </div>
                            </div>

                            <!-- Include SVG -->
                            <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded border">
                                <div class="d-flex align-items-center">
                                    <div class="mr-3">
                                        <span class="badge badge-info rounded-circle p-2 d-inline-flex align-items-center justify-content-center"
                                              style="width:2.5rem;height:2.5rem;">
                                            <i class="fa fa-picture-o"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold">Include SVG Diagrams</div>
                                        <div class="small text-muted">Generate simple SVG illustrations where helpful</div>
                                    </div>
                                </div>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="use-svg">
                                    <label class="custom-control-label" for="use-svg"></label>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row">
                            <!-- AI Provider -->
                            <div class="form-group col-md-6">
                                <label for="ai-provider" class="font-weight-bold">AI Provider</label>
                                <select id="ai-provider" class="custom-select">
                                    <?php foreach ($providers as $p) : ?>
                                        <option value="<?php echo $p->id; ?>"
                                            <?php echo $p->isdefault ? 'selected' : ''; ?>>
                                            <?php echo format_string($p->name);
                                                  echo $p->isdefault ? ' (' . get_string('provider_default', 'local_courseagent') . ')' : ''; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <!-- AI Model -->
                            <div class="form-group col-md-6">
                                <label for="ai-model" class="font-weight-bold">Model Selection</label>
                                <select id="ai-model" class="custom-select">
                                    <option value=""><?php print_string('provider_autoselect', 'local_courseagent'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="button" id="btn-generate" class="btn btn-primary btn-lg">
                                <i class="fa fa-magic fa-fw" aria-hidden="true"></i>
                                Generate Course
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: How it works -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4 d-flex align-items-center">
                        <i class="fa fa-info-circle text-primary mr-2"></i>
                        How it works
                    </h4>
                    <p class="text-muted small mb-4">
                        CourseAgent uses advanced AI to instantly draft a comprehensive Moodle course structure based on your topic and parameters.
                    </p>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item px-0 d-flex align-items-start">
                            <span class="badge badge-light rounded-circle p-2 mr-3 border d-inline-flex align-items-center justify-content-center"
                                  style="width:2.5rem;height:2.5rem;">
                                <i class="fa fa-list-ol text-primary"></i>
                            </span>
                            <div>
                                <h6 class="mb-1">Structuring</h6>
                                <p class="small text-muted mb-0">We analyze your topic and break it down into logical modules and lessons.</p>
                            </div>
                        </div>
                        <div class="list-group-item px-0 d-flex align-items-start">
                            <span class="badge badge-light rounded-circle p-2 mr-3 border d-inline-flex align-items-center justify-content-center"
                                  style="width:2.5rem;height:2.5rem;">
                                <i class="fa fa-file-text-o text-primary"></i>
                            </span>
                            <div>
                                <h6 class="mb-1">Content Generation</h6>
                                <p class="small text-muted mb-0">Detailed lesson content, readings, and summaries are drafted for each section.</p>
                            </div>
                        </div>
                        <div class="list-group-item px-0 d-flex align-items-start">
                            <span class="badge badge-light rounded-circle p-2 mr-3 border d-inline-flex align-items-center justify-content-center"
                                  style="width:2.5rem;height:2.5rem;">
                                <i class="fa fa-check-circle text-primary"></i>
                            </span>
                            <div>
                                <h6 class="mb-1">Review &amp; Refine</h6>
                                <p class="small text-muted mb-0">You can edit everything before finalizing and publishing to Moodle.</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 p-3 bg-light rounded border">
                        <div class="d-flex align-items-start">
                            <i class="fa fa-lightbulb-o text-warning mr-2 mt-1"></i>
                            <p class="small text-muted mb-0">
                                <strong>Pro Tip:</strong> Be as specific as possible in the Topic field. Pasting a syllabus outline yields the best results.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading modal overlay -->
<div id="ca-loading-modal" class="ca-loading-modal" style="display:none;" role="dialog" aria-modal="true">
    <div class="ca-loading-modal-content">
        <!-- Spinner -->
        <div class="ca-loading-ring mb-4">
            <div class="ca-loading-ring-track"></div>
            <div class="ca-loading-ring-fill"></div>
            <i class="fa fa-magic ca-loading-ring-icon" aria-hidden="true"></i>
        </div>

        <!-- Header -->
        <h4 class="mb-2">Generating Your Course...</h4>
        <p class="text-muted mb-4">This may take a few seconds. We're crafting high-quality content for you.</p>

        <!-- Progress bar -->
        <div class="progress mb-1" style="height:6px;">
            <div id="ca-loading-progress" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
        </div>
        <div class="text-right mb-4">
            <small class="text-muted" id="ca-loading-percent">0%</small>
        </div>

        <!-- Steps -->
        <div class="ca-steps-list">
            <div id="ca-step-outline" class="ca-step ca-step-active">
                <div class="ca-step-bubble">
                    <i class="fa fa-hourglass-half" aria-hidden="true"></i>
                </div>
                <span class="ca-step-label">Creating course outline</span>
            </div>
            <div id="ca-step-lessons" class="ca-step ca-step-pending">
                <div class="ca-step-bubble">2</div>
                <span class="ca-step-label">Generating lessons</span>
            </div>
            <div id="ca-step-extras" class="ca-step ca-step-pending">
                <div class="ca-step-bubble">3</div>
                <span class="ca-step-label">Adding quizzes and assignments</span>
            </div>
        </div>

        <!-- Cancel button -->
        <div class="text-center mt-4">
            <button type="button" id="btn-cancel-generate" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-times fa-fw" aria-hidden="true"></i> Cancel
            </button>
        </div>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?>
