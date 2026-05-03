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
 * AI Provider management page.
 *
 * @package   local_courseagent
 * @copyright 2026 Course Agent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/provider_form.php');

use local_courseagent\provider;
use local_courseagent\form\provider_form;

// Require admin login.
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Page setup.
$PAGE->set_url('/local/courseagent/providers.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('provider_management', 'local_courseagent'));
$PAGE->set_heading(get_string('pluginname', 'local_courseagent'));
$PAGE->set_pagelayout('admin');

// Parameters.
$action  = optional_param('action', '', PARAM_ALPHA);
$id      = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$editid  = optional_param('edit', 0, PARAM_INT);

// ------------------------------------------------------------------ //
// Action: Delete                                                       //
// ------------------------------------------------------------------ //
if ($action === 'delete' && $id && confirm_sesskey()) {
    $rec = provider::get($id);
    if ($rec) {
        if ($confirm) {
            provider::delete($id);
            redirect(
                new moodle_url('/local/courseagent/providers.php'),
                get_string('provider_deleted', 'local_courseagent'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('provider_delete_confirm', 'local_courseagent', $rec->name),
                new moodle_url('/local/courseagent/providers.php', [
                    'action'  => 'delete',
                    'id'      => $id,
                    'confirm' => 1,
                    'sesskey' => sesskey(),
                ]),
                new moodle_url('/local/courseagent/providers.php')
            );
            echo $OUTPUT->footer();
            exit;
        }
    }
}

// ------------------------------------------------------------------ //
// Action: Set Default                                                  //
// ------------------------------------------------------------------ //
if ($action === 'setdefault' && $id && confirm_sesskey()) {
    provider::set_default($id);
    redirect(
        new moodle_url('/local/courseagent/providers.php'),
        get_string('provider_set_default', 'local_courseagent'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ------------------------------------------------------------------ //
// Action: Toggle Enabled                                               //
// ------------------------------------------------------------------ //
if ($action === 'toggle' && $id && confirm_sesskey()) {
    $rec = provider::get($id);
    if ($rec) {
        provider::set_enabled($id, !$rec->enabled);
        redirect(new moodle_url('/local/courseagent/providers.php'));
    }
}

// ------------------------------------------------------------------ //
// Action: Test Connection (AJAX)                                       //
// ------------------------------------------------------------------ //
if ($action === 'test' && $id) {
    header('Content-Type: application/json');
    try {
        require_sesskey();
        $result = provider::test_connection($id);
        echo json_encode([
            'success'     => $result->success,
            'message'     => $result->message,
            'httpcode'    => $result->httpcode,
            'ai_response' => $result->ai_response ?? null,
            'response'    => $result->response,
            'debug'       => $result->debug ?? null,
        ]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => ['exception' => $e->getMessage()]]);
    }
    exit;
}

// ------------------------------------------------------------------ //
// Add / Edit form                                                      //
// ------------------------------------------------------------------ //
$isediting   = ($editid > 0);
$isadding    = ($action === 'add');
$form        = null;  // Initialize to avoid undefined variable warning.

// Check if form was submitted - Moodle forms send a _qf__<formclass> marker in POST.
// This must be detected BEFORE we decide whether to create the form object,
// because get_data() requires the form object to exist.
$qfmarker = '_qf__local_courseagent_form_provider_form';
$formsubmitted = optional_param($qfmarker, null, PARAM_RAW) !== null;

if ($isediting) {
    $rec = provider::get($editid);
    if ($rec) {
        $form = new provider_form(new moodle_url('/local/courseagent/providers.php', ['edit' => $editid]), ['provider' => $rec]);
        $form->set_data([
            'id'          => $rec->id,
            'name'        => $rec->name,
            'baseurl'     => $rec->baseurl,
            'endpoint'    => $rec->endpoint,
            'api_format'  => $rec->api_format ?? 'openai',
            'isdefault'   => $rec->isdefault,
            'enabled'     => $rec->enabled,
            'models_json' => $rec->models,
        ]);
    }
} else if ($isadding || $formsubmitted) {
    // Create form for both "add" display AND form submission processing.
    $form = new provider_form(new moodle_url('/local/courseagent/providers.php'), ['provider' => null]);
}

if ($form) {
    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/courseagent/providers.php'));
    }

    if ($data = $form->get_data()) {
        // Debug: Uncomment to see what data is received
        // echo $OUTPUT->header(); echo '<pre>'; print_r($data); echo '</pre>'; echo $OUTPUT->footer(); exit;

        // Parse models from the hidden JSON field (populated by JS widget).
        $modelsraw = $data->models_json ?? '[]';
        $models    = json_decode($modelsraw, true);
        if (!is_array($models)) {
            $models = [];
        }
        // Sanitise: trim & remove blanks.
        $models = array_values(array_filter(array_map('trim', $models)));
        $data->models = $models;

        // If editing and API key left blank, keep the existing key.
        if (!empty($data->id) && empty(trim($data->apikey ?? ''))) {
            $existing         = provider::get($data->id);
            $data->apikey     = provider::decrypt_apikey($existing->apikey);
        }

        if (!empty($data->id)) {
            provider::update($data->id, $data);
            redirect(
                new moodle_url('/local/courseagent/providers.php'),
                get_string('provider_updated', 'local_courseagent'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            try {
                $newid = provider::create($data);
                // Debug: uncomment the next line to see if provider was created
                // redirect(new moodle_url('/local/courseagent/providers.php'), "Created provider ID: " . $newid, null, \core\output\notification::NOTIFY_SUCCESS);
                redirect(
                    new moodle_url('/local/courseagent/providers.php'),
                    get_string('provider_created', 'local_courseagent'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } catch (\Exception $e) {
                // Show error if create failed
                echo $OUTPUT->header();
                echo $OUTPUT->notification('Error creating provider: ' . $e->getMessage(), 'notifyproblem');
                echo html_writer::div(
                    html_writer::link(
                        new moodle_url('/local/courseagent/providers.php'),
                        get_string('back', 'core')
                    ),
                    'mt-3'
                );
                echo $OUTPUT->footer();
                exit;
            }
        }
    }

    // Render form page.
    // Heading: "Add Provider" when adding, "Edit Provider: <name>" when editing.
    if ($isediting && !empty($rec)) {
        $pageheading = get_string('provider_edit', 'local_courseagent') . ': ' . format_string($rec->name);
    } else {
        $pageheading = get_string('provider_add_heading', 'local_courseagent');
    }

    echo $OUTPUT->header();

    // Breadcrumb-style back link.
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/courseagent/providers.php'),
            html_writer::tag('i', '', ['class' => 'fa fa-arrow-left mr-1']) .
            get_string('provider_management', 'local_courseagent')
        ),
        'mb-3'
    );

    echo $OUTPUT->heading($pageheading);

    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// ------------------------------------------------------------------ //
// Provider list page                                                   //
// ------------------------------------------------------------------ //
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('provider_management', 'local_courseagent'));

// "Add New Provider" button.
$addurl = new moodle_url('/local/courseagent/providers.php', ['action' => 'add']);
echo html_writer::div(
    html_writer::link(
        $addurl->out(false),
        get_string('provider_add', 'local_courseagent'),
        ['class' => 'btn btn-primary']
    ),
    'mb-3'
);

$providers = provider::get_all();

if (empty($providers)) {
    echo $OUTPUT->notification(get_string('provider_no_providers', 'local_courseagent'), 'notifymessage');
    echo html_writer::tag('p', get_string('provider_no_providers_help', 'local_courseagent'));
} else {
    $table             = new html_table();
    $table->head       = [
        get_string('provider_name', 'local_courseagent'),
        get_string('provider_baseurl', 'local_courseagent'),
        get_string('provider_models', 'local_courseagent'),
        get_string('provider_status', 'local_courseagent'),
        get_string('actions', 'local_courseagent'),
    ];
    $table->attributes['class'] = 'generaltable table-striped';
    $table->id = 'provider-table';

    foreach ($providers as $p) {
        $models     = json_decode($p->models, true) ?: [];
        $modelcount = count($models);
        $modelshtml = '';
        if ($modelcount > 0) {
            // Show first model + count badge.
            $first     = htmlspecialchars($models[0], ENT_QUOTES);
            $modelshtml = html_writer::tag('code', $first, ['class' => 'small']);
            if ($modelcount > 1) {
                $modelshtml .= ' ' . html_writer::tag('span', '+' . ($modelcount - 1) . ' more', ['class' => 'badge badge-light border text-muted']);
            }
        } else {
            $modelshtml = html_writer::tag('span', '—', ['class' => 'text-muted']);
        }

        // Status badges.
        $status = '';
        if ($p->isdefault) {
            $status .= html_writer::tag(
                'span',
                html_writer::tag('i', '', ['class' => 'fa fa-star mr-1']) . get_string('provider_default', 'local_courseagent'),
                ['class' => 'badge badge-primary mr-1']
            );
        }
        $status .= $p->enabled
            ? html_writer::tag('span', get_string('provider_enabled', 'local_courseagent'), ['class' => 'badge badge-success'])
            : html_writer::tag('span', get_string('provider_disabled', 'local_courseagent'), ['class' => 'badge badge-secondary']);

        // Actions.
        $actions = [];

        // Edit.
        $actions[] = html_writer::link(
            new moodle_url('/local/courseagent/providers.php', ['edit' => $p->id]),
            $OUTPUT->pix_icon('i/edit', get_string('edit')),
            ['class' => 'action-icon', 'title' => get_string('edit')]
        );

        // Test connection.
        $actions[] = html_writer::tag(
            'a',
            $OUTPUT->pix_icon('i/valid', get_string('provider_test', 'local_courseagent')),
            [
                'href'            => 'javascript:void(0)',
                'class'           => 'action-icon ca-test-provider',
                'data-id'         => $p->id,
                'data-sesskey'    => sesskey(),
                'title'           => get_string('provider_test', 'local_courseagent'),
            ]
        );

        // Set as default (only shown if not already default and is enabled).
        if (!$p->isdefault && $p->enabled) {
            $actions[] = html_writer::link(
                new moodle_url('/local/courseagent/providers.php', ['action' => 'setdefault', 'id' => $p->id, 'sesskey' => sesskey()]),
                $OUTPUT->pix_icon('i/star', get_string('provider_set_default', 'local_courseagent')),
                ['class' => 'action-icon', 'title' => get_string('provider_set_default', 'local_courseagent')]
            );
        }

        // Toggle enable/disable.
        $toggleicon  = $p->enabled ? 'i/hide' : 'i/show';
        $toggletitle = $p->enabled ? 'Disable provider' : 'Enable provider';
        $actions[] = html_writer::link(
            new moodle_url('/local/courseagent/providers.php', ['action' => 'toggle', 'id' => $p->id, 'sesskey' => sesskey()]),
            $OUTPUT->pix_icon($toggleicon, $toggletitle),
            ['class' => 'action-icon', 'title' => $toggletitle]
        );

        // Delete.
        $actions[] = html_writer::link(
            new moodle_url('/local/courseagent/providers.php', ['action' => 'delete', 'id' => $p->id, 'sesskey' => sesskey()]),
            $OUTPUT->pix_icon('i/delete', get_string('delete')),
            ['class' => 'action-icon', 'title' => get_string('delete')]
        );

        $table->data[] = [
            format_string($p->name),
            html_writer::link(
                $p->baseurl,
                html_writer::tag('code', strlen($p->baseurl) > 45 ? substr($p->baseurl, 0, 45) . '…' : $p->baseurl, ['class' => 'small']),
                ['target' => '_blank', 'rel' => 'noopener', 'title' => $p->baseurl]
            ),
            $modelshtml,
            $status,
            html_writer::div(implode(' ', $actions), 'nowrap'),
        ];
    }

    echo html_writer::table($table);
}

// ------------------------------------------------------------------ //
// Test Connection inline result modal (Bootstrap alert, no popup)     //
// ------------------------------------------------------------------ //
echo html_writer::div(
    html_writer::div('', 'alert mb-0', ['id' => 'ca-test-result-inner']),
    'ca-test-result-wrap mt-3',
    ['id' => 'ca-test-result', 'style' => 'display:none; max-width:600px;']
);

// ------------------------------------------------------------------ //
// JavaScript for test-connection                                       //
// ------------------------------------------------------------------ //
$js = <<<JS
console.log('[Course Agent] Providers list page script loaded');
console.log('[Course Agent] Found test buttons:', document.querySelectorAll('.ca-test-provider').length);

document.querySelectorAll('.ca-test-provider').forEach(function(btn) {
    console.log('[Course Agent] Attaching click handler to button:', btn.getAttribute('data-id'));
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var id      = this.getAttribute('data-id');
        var sesskey = this.getAttribute('data-sesskey');
        var wrap    = document.getElementById('ca-test-result');
        var inner   = document.getElementById('ca-test-result-inner');

        console.log('[Course Agent] Test button clicked for Provider ID:', id);

        var requestUrl = 'providers.php?action=test&id=' + id + '&sesskey=' + sesskey;

        inner.className   = 'alert mb-0 alert-info';
        inner.textContent = 'Testing connection…';
        wrap.style.display = 'block';

        fetch(requestUrl)
            .then(function(r) {
                console.log('[Course Agent] HTTP Status:', r.status);
                return r.json();
            })
            .then(function(data) {
                // ===== TOP LEVEL GROUP =====
                console.group('%c[Course Agent] Provider Test Connection Debug', 'font-size: 14px; font-weight: bold; color: #0066cc;');

                // Basic Result
                console.log('Success:', data.success);
                console.log('Message:', data.message);
                console.log('HTTP Code:', data.httpcode);

                if (data.debug) {
                    // ===== REQUEST CONSTRUCTION =====
                    console.group('%c1. Request Construction', 'color: #009900; font-weight: bold;');
                    console.log('API Type:', data.debug.api_type);
                    console.log('Base URL:', data.debug.base_url);
                    console.log('Endpoint:', data.debug.endpoint);
                    console.log('Full URL Used:', data.debug.full_url);
                    if (data.debug.request_construction) {
                        console.group('URL Building Steps');
                        console.log('Base URL Input:', data.debug.request_construction.url_building.base_url_input);
                        console.log('Endpoint Input:', data.debug.request_construction.url_building.endpoint_input);
                        console.log('URL Before Key:', data.debug.request_construction.url_building.url_before_key);
                        console.log('Final URL Used:', data.debug.request_construction.url_building.final_url_used);
                        console.groupEnd();
                        console.log('Is Gemini:', data.debug.request_construction.is_gemini);
                        console.log('Is OpenAI:', data.debug.request_construction.is_openai);
                        console.log('API Key Present:', data.debug.request_construction.api_key_present);
                        console.log('API Key Length:', data.debug.request_construction.api_key_length);
                        console.log('API Key Preview:', data.debug.request_construction.api_key_preview);
                    }
                    console.groupEnd();

                    // ===== REQUEST DETAILS =====
                    console.group('%c2. Request Details', 'color: #009900; font-weight: bold;');
                    console.log('Method:', data.debug.request_method);
                    console.log('Headers (for display):', data.debug.request_headers);
                    if (data.debug.request_construction && data.debug.request_construction.headers_sent) {
                        console.log('Actual Headers Sent:', data.debug.request_construction.headers_sent);
                    }
                    if (data.debug.curl_config) {
                        console.log('cURL Config:', data.debug.curl_config);
                    }
                    console.log('Payload:');
                    console.log(JSON.stringify(data.debug.request_payload, null, 2));
                    console.groupEnd();

                    // ===== RESPONSE DETAILS =====
                    console.group('%c3. Response Details', 'color: #cc6600; font-weight: bold;');
                    if (data.debug.response_processing) {
                        console.log('HTTP Code Received:', data.debug.response_processing.http_code_received);
                        console.log('Raw Response Length:', data.debug.response_processing.raw_response_length);
                        console.log('JSON Decode Success:', data.debug.response_processing.json_decode_success);
                        if (data.debug.response_processing.json_decode_error) {
                            console.error('JSON Decode Error:', data.debug.response_processing.json_decode_error);
                        }
                        if (data.debug.response_processing.curl_error) {
                            console.error('cURL Error:', data.debug.response_processing.curl_error);
                        }
                        if (data.debug.response_processing.response_structure) {
                            console.group('Response Structure Analysis');
                            console.log('Type:', data.debug.response_processing.response_structure.type);
                            console.log('Top Level Keys:', data.debug.response_processing.response_structure.top_level_keys);
                            if (data.debug.response_processing.response_structure.candidates_type) {
                                console.log('Candidates Type:', data.debug.response_processing.response_structure.candidates_type);
                                console.log('Candidates Count:', data.debug.response_processing.response_structure.candidates_count);
                                console.log('Candidate[0] Keys:', data.debug.response_processing.response_structure.candidate_0_keys);
                                console.log('Candidate[0] Content Keys:', data.debug.response_processing.response_structure.candidate_0_content_keys);
                                console.log('Candidate[0] Parts Type:', data.debug.response_processing.response_structure.candidate_0_parts_type);
                                console.log('Candidate[0] Parts Count:', data.debug.response_processing.response_structure.candidate_0_parts_count);
                            }
                            if (data.debug.response_processing.response_structure.choices_type) {
                                console.log('Choices Type:', data.debug.response_processing.response_structure.choices_type);
                                console.log('Choices Count:', data.debug.response_processing.response_structure.choices_count);
                                console.log('Choice[0] Keys:', data.debug.response_processing.response_structure.choice_0_keys);
                                console.log('Choice[0] Message Keys:', data.debug.response_processing.response_structure.choice_0_message_keys);
                            }
                            if (data.debug.response_processing.response_structure.error_structure) {
                                console.log('Error Structure:', data.debug.response_processing.response_structure.error_structure);
                            }
                            console.groupEnd();
                        }
                    }
                    console.log('Raw Response (first 500 chars):', data.debug.response_raw ? data.debug.response_raw.substring(0, 500) : '(none)');
                    console.log('Parsed Response:', data.debug.response_parsed);
                    console.groupEnd();

                    // ===== EXTRACTION ATTEMPTS (CRITICAL FOR DEBUGGING) =====
                    console.group('%c4. AI Response Extraction', 'color: #cc0000; font-weight: bold;');
                    console.log('AI Response Extracted:', data.ai_response || 'NONE - EXTRACTION FAILED');
                    if (data.debug.extraction_attempts && data.debug.extraction_attempts.length > 0) {
                        console.log('Extraction Steps:');
                        data.debug.extraction_attempts.forEach(function(attempt) {
                            var details = {
                                exists: attempt.exists,
                                type: attempt.type,
                                count: attempt.count,
                                value_preview: attempt.value_preview
                            };
                            if (attempt.exists) {
                                console.log('  Step ' + attempt.step + ': Path "' + attempt.path + '"', details);
                            } else {
                                console.warn('  Step ' + attempt.step + ': Path "' + attempt.path + '" NOT FOUND', details);
                            }
                        });
                    } else {
                        console.warn('No extraction attempts logged');
                    }
                    console.groupEnd();

                    // ===== TIMING =====
                    if (data.debug.timing) {
                        console.log('%c5. Request Duration: ' + data.debug.timing.request_duration_ms + 'ms', 'color: #9900cc; font-weight: bold;');
                    }

                    // ===== ERRORS =====
                    if (data.debug.curl_error) {
                        console.error('%ccURL Error:', 'font-weight: bold;', data.debug.curl_error);
                    }
                    if (data.debug.exception) {
                        console.error('%cException:', 'font-weight: bold;', data.debug.exception);
                    }
                }

                console.groupEnd(); // Close main group

                // Update UI
                if (data.success) {
                    console.log('%c✓ SUCCESS: Connection test passed!', 'color: #009900; font-weight: bold;');
                    inner.className   = 'alert mb-0 alert-success';
                    inner.innerHTML   = '<strong>✓ Connection successful</strong> (HTTP ' + data.httpcode + ')' +
                        (data.ai_response ? '<br><em>AI replied: ' + data.ai_response + '</em>' : '');
                } else {
                    console.error('%c✗ FAILED: Connection test failed!', 'color: #cc0000; font-weight: bold;');
                    inner.className   = 'alert mb-0 alert-danger';
                    inner.innerHTML   = '<strong>✗ Connection failed</strong><br>' + data.message +
                        '<br><small class="text-muted">Check browser console for detailed debug info</small>';
                }
            })
            .catch(function(err) {
                console.error('[Course Agent] ========================================');
                console.error('[Course Agent] ✗ AJAX/FETCH ERROR');
                console.error('[Course Agent] ========================================');
                console.error('[Course Agent] Error:', err);
                console.error('[Course Agent] Error Message:', err.message);
                inner.className   = 'alert mb-0 alert-danger';
                inner.textContent = 'Request error: ' + err.message;
            });
    });
});
JS;

echo html_writer::script($js);
echo $OUTPUT->footer();
