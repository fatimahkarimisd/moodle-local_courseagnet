// This file is part of Course Agent - AI Course Creator Plugin for Moodle

/**
 * @module local_courseagent/coursecreator
 * @copyright 2026 Course Agent
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    'use strict';

    let config = {};
    let extractedFileText = '';   // Text extracted from the uploaded file (server-side).
    let progressTimer = null;    // Interval timer for progress animation.
    let generateXhr = null;      // Reference to the generate AJAX request for cancellation.

    // Accepted MIME types / extensions.
    const ACCEPTED_EXTS = ['txt','pdf','docx','pptx','odt','rtf','md','csv','epub'];
    const MAX_FILE_BYTES = 50 * 1024 * 1024; // 50 MB

    /**
     * Initialize the course creator.
     * @param {Object} userConfig Configuration object from PHP
     */
    const init = function(userConfig) {
        config = userConfig;
        setupEventListeners();
        setupDropzone();
        updateModelSelector();
    };

    // -------------------------------------------------------------------------
    // Event setup
    // -------------------------------------------------------------------------

    const setupEventListeners = function() {
        $('#btn-generate').on('click', generateCourseOutline);
        $('#upload-remove').on('click', removeUploadedFile);
        $('#ai-provider').on('change', updateModelSelector);
        $('#btn-cancel-generate').on('click', cancelGeneration);

        // Character counter for Course Topic (max 500).
        const $topic = $('#course-topic');
        const $counter = $('#course-topic-counter');
        if ($topic.length && $counter.length) {
            const updateCounter = function() {
                const len = $topic.val().length;
                $counter.text(len + ' / 500');
                $counter.toggleClass('text-danger', len >= 500);
            };
            $topic.on('input', updateCounter);
            updateCounter();
        }
    };

    /**
     * Populate the model selector based on the chosen provider.
     */
    const updateModelSelector = function() {
        const providerId = $('#ai-provider').val();
        const $modelSelect = $('#ai-model');
        $modelSelect.empty();
        $modelSelect.append('<option value="">' + (config.providers[providerId] ? 'Auto-select (first available)' : 'Auto-select') + '</option>');

        if (config.providers[providerId] && config.providers[providerId].models) {
            config.providers[providerId].models.forEach(function(model) {
                $modelSelect.append('<option value="' + model + '">' + model + '</option>');
            });
        }
    };

    // -------------------------------------------------------------------------
    // Dropzone setup
    // -------------------------------------------------------------------------

    const setupDropzone = function() {
        const $zone  = $('#upload-dropzone');
        const $input = $('#upload-file-input');

        // Click on zone -> open file picker.
        $zone.on('click', function(e) {
            if ($(e.target).closest('#upload-remove').length) return; // don't open picker when removing
            $input.trigger('click');
        });

        // File picker change.
        $input.on('change', function() {
            if (this.files && this.files[0]) {
                handleFileSelected(this.files[0]);
            }
        });

        // Drag events.
        $zone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $zone.addClass('drag-over');
        });

        $zone.on('dragleave dragend', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $zone.removeClass('drag-over');
        });

        $zone.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $zone.removeClass('drag-over');
            const files = e.originalEvent.dataTransfer.files;
            if (files && files[0]) {
                handleFileSelected(files[0]);
            }
        });
    };

    // -------------------------------------------------------------------------
    // File handling
    // -------------------------------------------------------------------------

    const handleFileSelected = function(file) {
        // Validate extension.
        const ext = file.name.split('.').pop().toLowerCase();
        if (ACCEPTED_EXTS.indexOf(ext) === -1) {
            Notification.addNotification({
                message: 'Unsupported file type ".' + ext + '". Please upload: TXT, PDF, DOCX, PPTX, ODT, RTF, MD, CSV, or EPUB.',
                type: 'error'
            });
            return;
        }

        // Validate size.
        if (file.size > MAX_FILE_BYTES) {
            Notification.addNotification({
                message: 'File is too large (' + formatBytes(file.size) + '). Maximum allowed size is 50 MB.',
                type: 'error'
            });
            return;
        }

        // Show extracting state.
        showDropzoneState('extracting', file.name);

        // Upload to server for text extraction.
        const formData = new FormData();
        formData.append('action', 'extract_content');
        formData.append('sesskey', config.sesskey);
        formData.append('file', file);

        $.ajax({
            url: config.wwwroot + '/local/courseagent/ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    extractedFileText = response.text;
                    $('#upload-extracted-text').val(response.text);
                    showDropzoneState('done', file.name, response.charcount);
                } else {
                    showDropzoneState('idle');
                    Notification.addNotification({
                        message: response.error || 'Could not extract text from the file.',
                        type: 'error'
                    });
                }
            },
            error: function(xhr) {
                showDropzoneState('idle');
                let msg = 'Failed to upload file for extraction.';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
                Notification.addNotification({ message: msg, type: 'error' });
            }
        });
    };

    const removeUploadedFile = function(e) {
        e.stopPropagation();
        extractedFileText = '';
        $('#upload-extracted-text').val('');
        $('#upload-file-input').val('');
        showDropzoneState('idle');
    };

    /**
     * Update the dropzone's visual state.
     * @param {string} state  'idle' | 'extracting' | 'done'
     * @param {string} [name] File name
     * @param {number} [chars] Character count
     */
    const showDropzoneState = function(state, name, chars) {
        const $inner = $('#upload-dropzone-inner');
        const $info  = $('#upload-file-info');

        if (state === 'idle') {
            $inner.show();
            $info.addClass('d-none');
            $('#upload-dropzone').removeClass('has-file');
        } else if (state === 'extracting') {
            $inner.hide();
            $info.removeClass('d-none');
            $('#upload-filename').text(name);
            $('#upload-charcount').html('<i class="fa fa-spinner fa-spin"></i> Extracting text...');
            $('#upload-dropzone').addClass('has-file');
        } else if (state === 'done') {
            $inner.hide();
            $info.removeClass('d-none');
            $('#upload-filename').text(name);
            $('#upload-charcount').html(
                '<i class="fa fa-check-circle text-success"></i> ' +
                chars.toLocaleString() + ' characters extracted'
            );
            $('#upload-dropzone').addClass('has-file');
        }
    };

    const formatBytes = function(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    // -------------------------------------------------------------------------
    // Course generation
    // -------------------------------------------------------------------------

    const generateCourseOutline = function() {
        const topic       = $('#course-topic').val().trim();
        const customTitle = $('#course-custom-title').val().trim();
        const level       = $('#course-level').val();
        const numSections = parseInt($('#num-sections').val());
        const includeQuiz = $('#include-quiz').is(':checked');
        const includeAssignment = $('#include-assignment').is(':checked');
        const useEmojis   = $('#use-emojis').is(':checked');
        const useSvg      = $('#use-svg').is(':checked');

        // Require topic OR uploaded file.
        if (!topic && !extractedFileText) {
            Notification.addNotification({
                message: 'Please enter a course topic.',
                type: 'error'
            });
            return;
        }

        if (numSections < 2 || numSections > config.maxSections) {
            Notification.addNotification({
                message: 'Number of sections must be between 2 and ' + config.maxSections,
                type: 'error'
            });
            return;
        }

        showProgress(includeQuiz, includeAssignment);
        $('#btn-generate').prop('disabled', true);

        const requestData = {
            action:            'generate',
            topic:             topic,
            level:             level,
            numsections:       numSections,
            includequiz:       includeQuiz ? 1 : 0,
            includeassignment: includeAssignment ? 1 : 0,
            useemojis:         useEmojis ? 1 : 0,
            usesvg:            useSvg ? 1 : 0,
            provider:          $('#ai-provider').val() || 0,
            model:             $('#ai-model').val() || '',
            sesskey:           config.sesskey,
            extracted_content: extractedFileText,
            custom_title:      customTitle
        };

        generateXhr = $.ajax({
            url:      config.wwwroot + '/local/courseagent/ajax.php',
            type:     'POST',
            data:     requestData,
            dataType: 'json',
            success:  function(response) {
                generateXhr = null;

                if (response.success) {
                    window.location.href = config.wwwroot + '/local/courseagent/preview.php';
                    return;
                }

                hideProgress();
                $('#btn-generate').prop('disabled', false);
                Notification.addNotification({
                    message: response.error || 'Failed to generate course',
                    type:    'error'
                });
            },
            error: function(xhr) {
                generateXhr = null;
                // Aborted requests have status 0 — don't show error for user-initiated cancel.
                if (xhr.status === 0 && xhr.statusText === 'abort') {
                    return;
                }
                hideProgress();
                $('#btn-generate').prop('disabled', false);
                let msg = 'An error occurred while generating the course';
                let parsed = null;
                try {
                    parsed = JSON.parse(xhr.responseText);
                    msg = parsed.error || msg;
                } catch (e) {
                    console.error('[Course Agent] Server did not return valid JSON. Raw response text:', xhr.responseText);
                }
                if (parsed) {
                    console.error('[Course Agent] Server returned error object:', parsed);
                    if (parsed.debug) {
                        console.error('[Course Agent] Debug info from server:', parsed.debug);
                    }
                }
                console.error('[Course Agent] Error message shown to user:', msg);
                Notification.addNotification({ message: msg, type: 'error' });
            }
        });
    };

    const cancelGeneration = function() {
        if (generateXhr) {
            generateXhr.abort();
            generateXhr = null;
        }
        hideProgress();
        $('#btn-generate').prop('disabled', false);
        Notification.addNotification({
            message: 'Course generation cancelled.',
            type:    'info'
        });
    };

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    const showProgress = function(includeQuiz, includeAssignment) {
        // Reset progress to 0.
        var $bar = $('#ca-loading-progress');
        var $pct = $('#ca-loading-percent');
        var $extras = $('#ca-step-extras');

        $bar.css('width', '0%');
        $pct.text('0%');

        // Configure extras step visibility and label.
        if (!includeQuiz && !includeAssignment) {
            $extras.hide();
        } else {
            $extras.show();
            var label = '';
            if (includeQuiz && includeAssignment) {
                label = 'Adding quizzes and assignments';
            } else if (includeQuiz) {
                label = 'Adding quizzes';
            } else {
                label = 'Adding assignments';
            }
            $extras.find('.ca-step-label').text(label);
        }

        // Reset step states.
        var $steps = $('.ca-step:visible');
        $steps.removeClass('ca-step-done ca-step-active').addClass('ca-step-pending');
        $steps.find('.ca-step-bubble').html('');
        $steps.eq(0).addClass('ca-step-active').removeClass('ca-step-pending');
        $steps.eq(0).find('.ca-step-bubble').html('<i class="fa fa-hourglass-half" aria-hidden="true"></i>');

        $('#ca-loading-modal').fadeIn(200);

        // Fake step progression with random timing (2-5 seconds per step).
        var currentStep = 0;
        var totalSteps = $steps.length;

        function moveToNextStep() {
            if (currentStep < totalSteps) {
                // Mark current step as done.
                var $currentStep = $steps.eq(currentStep);
                $currentStep.removeClass('ca-step-active ca-step-pending').addClass('ca-step-done');
                $currentStep.find('.ca-step-bubble').html('<i class="fa fa-check" aria-hidden="true"></i>');

                currentStep++;

                // Move to next step if exists.
                if (currentStep < totalSteps) {
                    var $nextStep = $steps.eq(currentStep);
                    $nextStep.removeClass('ca-step-pending ca-step-done').addClass('ca-step-active');
                    $nextStep.find('.ca-step-bubble').html('<i class="fa fa-hourglass-half" aria-hidden="true"></i>');

                    // Schedule next step with random delay (1-3 seconds).
                    var randomDelay = Math.floor(Math.random() * 2000) + 1000;
                    setTimeout(moveToNextStep, randomDelay);
                }
            }
        }

        // Track last known server progress for real progress bar.
        var lastServerPct = 0;
        var displayPct = 0;

        // Poll server for real progress updates (for progress bar only).
        progressTimer = setInterval(function() {
            $.ajax({
                url:  config.wwwroot + '/local/courseagent/ajax.php',
                type: 'POST',
                data: { action: 'get_progress', sesskey: config.sesskey },
                dataType: 'json',
                success: function(resp) {
                    if (resp.success && resp.progress) {
                        var p = resp.progress;
                        lastServerPct = p.percent || 0;

                        // Update message if provided.
                        if (p.message) {
                            $('#ca-loading-modal .text-muted.mb-4').text(p.message);
                        }
                    }
                }
            });

            // Smoothly animate display toward last server percentage.
            var target = Math.max(lastServerPct, displayPct);
            if (displayPct < target) {
                displayPct = Math.min(target, displayPct + Math.max(0.5, (target - displayPct) * 0.15));
            } else if (displayPct < 90) {
                // Slowly creep forward when waiting for next server update.
                displayPct = Math.min(90, displayPct + 0.2);
            }
            var rounded = Math.round(displayPct);
            $bar.css('width', rounded + '%');
            $pct.text(rounded + '%');
        }, 500);

        // Start fake step progression.
        var initialDelay = Math.floor(Math.random() * 2000) + 1000;
        setTimeout(moveToNextStep, initialDelay);
    };

    const hideProgress = function() {
        // Stop the timer.
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
        // Animate to 100% before closing.
        var $bar = $('#ca-loading-progress');
        var $pct = $('#ca-loading-percent');

        $bar.css('width', '100%');
        $pct.text('100%');

        setTimeout(function() {
            $('#ca-loading-modal').fadeOut(200);
        }, 400);
    };

    return { init: init };
});
