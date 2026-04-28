// This file is part of Course Agent - AI Course Creator Plugin for Moodle

/**
 * @module local_courseagent/preview
 * @copyright 2026 Course Agent
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    'use strict';

    let config = {};
    let state = {
        currentSectionIndex: 0,
        currentTab: 'content',
        expandedSections: new Set([0]),
        chatMessages: [
            {
                type: 'ai',
                text: "I've drafted the initial content for the course. Review the sections in the sidebar and let me know if you'd like any changes."
            }
        ],
        quickActions: [
            'Add 10 more questions to section 2 quiz',
            'Remove assignment from section 3',
            'Make section 1 more advanced'
        ]
    };

    const init = function(userConfig) {
        config = userConfig;
        var dataEl = document.getElementById('ca-preview-data');
        if (dataEl) {
            try {
                config.courseData = JSON.parse(dataEl.textContent);
            } catch (e) {
                config.courseData = null;
            }
        }
        if (config.courseData) {
            $('#ca-course-title').text(config.courseData.title || 'Untitled Course');
            renderAll();
        }
        setupEventListeners();
    };

    const renderAll = function() {
        renderSidebar();
        renderMain();
        renderChat();
    };

    /* ── Sidebar Tree ── */
    const renderSidebar = function() {
        var sections = config.courseData.sections || [];
        var html = '';

        sections.forEach(function(section, index) {
            var isExpanded = state.expandedSections.has(index);
            var sectionNum = index + 1;

            html += '<div class="ca-tree-section">';
            html += '<button class="ca-tree-section-header' + (isExpanded ? ' is-expanded' : '') + '" data-index="' + index + '">';
            html += '<i class="fa fa-chevron-' + (isExpanded ? 'down' : 'right') + ' ca-tree-chevron"></i>';
            html += '<span class="ca-tree-section-title">Section ' + sectionNum + ': ' + escapeHtml(section.name) + '</span>';
            html += '</button>';

            if (isExpanded) {
                html += '<div class="ca-tree-children">';

                // Lesson item.
                if (section.lesson) {
                    var isActive = state.currentSectionIndex === index && state.currentTab === 'content';
                    html += '<a href="#" class="ca-tree-item' + (isActive ? ' is-active' : '') + '" ' +
                            'data-section="' + index + '" data-tab="content">';
                    html += '<i class="fa fa-file-alt ca-tree-item-icon"></i>';
                    html += '<span class="ca-tree-item-label">Lesson: ' + escapeHtml(section.name) + '</span>';
                    html += '</a>';
                }

                // Quiz item.
                if (section.quiz && section.quiz.questions && section.quiz.questions.length > 0) {
                    var isQuizActive = state.currentSectionIndex === index && state.currentTab === 'quiz';
                    html += '<a href="#" class="ca-tree-item' + (isQuizActive ? ' is-active' : '') + '" ' +
                            'data-section="' + index + '" data-tab="quiz">';
                    html += '<i class="fa fa-question-circle ca-tree-item-icon"></i>';
                    html += '<span class="ca-tree-item-label">Quiz (' + section.quiz.questions.length + ')</span>';
                    html += '</a>';
                }

                // Assignment item.
                if (section.assignment) {
                    var isAssignActive = state.currentSectionIndex === index && state.currentTab === 'assignment';
                    html += '<a href="#" class="ca-tree-item' + (isAssignActive ? ' is-active' : '') + '" ' +
                            'data-section="' + index + '" data-tab="assignment">';
                    html += '<i class="fa fa-tasks ca-tree-item-icon"></i>';
                    html += '<span class="ca-tree-item-label">Assignment</span>';
                    html += '</a>';
                }

                html += '</div>';
            }

            html += '</div>';
        });

        $('#ca-sidebar-tree').html(html);
    };

    /* ── Main Content ── */
    const renderMain = function() {
        var sections = config.courseData.sections || [];
        var section = sections[state.currentSectionIndex];
        if (!section) { return; }

        var hasLesson = !!section.lesson;
        var hasQuiz = !!(section.quiz && section.quiz.questions && section.quiz.questions.length > 0);
        var hasAssignment = !!section.assignment;

        // Auto-switch tab if current one isn't available for this section.
        if (state.currentTab === 'content' && !hasLesson) {
            state.currentTab = hasQuiz ? 'quiz' : (hasAssignment ? 'assignment' : 'content');
        } else if (state.currentTab === 'quiz' && !hasQuiz) {
            state.currentTab = hasLesson ? 'content' : (hasAssignment ? 'assignment' : 'content');
        } else if (state.currentTab === 'assignment' && !hasAssignment) {
            state.currentTab = hasLesson ? 'content' : (hasQuiz ? 'quiz' : 'content');
        }

        var html = '';

        // Tabs.
        html += '<div class="ca-main-tabs">';
        html += '<button class="ca-main-tab' + (state.currentTab === 'content' ? ' is-active' : '') + '" data-tab="content">';
        html += '<i class="fa fa-edit"></i> Content';
        html += '</button>';
        if (hasQuiz) {
            html += '<button class="ca-main-tab' + (state.currentTab === 'quiz' ? ' is-active' : '') + '" data-tab="quiz">';
            html += '<i class="fa fa-question-circle"></i> Quiz Questions';
            html += '</button>';
        }
        if (hasAssignment) {
            html += '<button class="ca-main-tab' + (state.currentTab === 'assignment' ? ' is-active' : '') + '" data-tab="assignment">';
            html += '<i class="fa fa-tasks"></i> Assignment Details';
            html += '</button>';
        }
        html += '</div>';

        // Content area.
        html += '<div class="ca-main-content">';
        html += '<div class="ca-main-card">';
        html += '<div class="ca-ai-badge"><i class="fa fa-magic"></i> AI Generated</div>';

        if (state.currentTab === 'content') {
            html += renderLessonContent(section);
        } else if (state.currentTab === 'quiz') {
            html += renderQuizContent(section);
        } else if (state.currentTab === 'assignment') {
            html += renderAssignmentContent(section);
        }

        html += '</div>';
        html += '</div>';

        $('#ca-main').html(html);
    };

    const renderLessonContent = function(section) {
        var html = '';
        if (!section.lesson) {
            html += '<p class="text-muted">No lesson content for this section.</p>';
            return html;
        }

        var lesson = section.lesson;
        html += '<h1 class="ca-content-title">' + escapeHtml(lesson.title || section.name) + '</h1>';

        if (lesson.summary) {
            html += '<p class="ca-content-lead">' + escapeHtml(lesson.summary) + '</p>';
        }

        if (lesson.content_html) {
            html += '<div class="ca-content-body">' + lesson.content_html + '</div>';
        } else {
            html += '<p class="text-muted">No content available.</p>';
        }

        return html;
    };

    const renderQuizContent = function(section) {
        var html = '';
        if (!section.quiz || !section.quiz.questions || section.quiz.questions.length === 0) {
            html += '<p class="text-muted">No quiz for this section.</p>';
            return html;
        }

        html += '<h1 class="ca-content-title">Quiz: ' + escapeHtml(section.name) + '</h1>';

        section.quiz.questions.forEach(function(q, qi) {
            var correctIdx = (typeof q.correct_answer === 'number') ? q.correct_answer : -1;
            html += '<div class="ca-quiz-question">';
            html += '<div class="ca-quiz-question-header">';
            html += '<span class="ca-quiz-number">Q' + (qi + 1) + '</span>';
            html += '<span class="ca-quiz-qtext">' + escapeHtml(q.question) + '</span>';
            html += '</div>';
            html += '<ul class="ca-quiz-options">';
            if (q.options && q.options.length > 0) {
                q.options.forEach(function(opt, oi) {
                    var isCorrect = (oi === correctIdx);
                    html += '<li class="ca-quiz-option' + (isCorrect ? ' is-correct' : '') + '">';
                    html += '<span class="ca-quiz-opt-marker">' + String.fromCharCode(65 + oi) + '.</span>';
                    html += '<span class="ca-quiz-opt-text">' + escapeHtml(opt) + '</span>';
                    if (isCorrect) {
                        html += '<span class="ca-quiz-correct-badge"><i class="fa fa-check"></i> Correct</span>';
                    }
                    html += '</li>';
                });
            }
            html += '</ul>';
            if (q.explanation) {
                html += '<div class="ca-quiz-explanation"><i class="fa fa-info-circle"></i> ' + escapeHtml(q.explanation) + '</div>';
            }
            html += '</div>';
        });

        return html;
    };

    const renderAssignmentContent = function(section) {
        var html = '';
        if (!section.assignment) {
            html += '<p class="text-muted">No assignment for this section.</p>';
            return html;
        }

        var a = section.assignment;
        html += '<h1 class="ca-content-title">Assignment: ' + escapeHtml(a.title || section.name) + '</h1>';

        if (a.description) {
            html += '<p class="ca-content-lead">' + escapeHtml(a.description) + '</p>';
        }

        if (a.instructions && a.instructions.length > 0) {
            html += '<div class="ca-info-box">';
            html += '<h3 class="ca-info-box-title"><i class="fa fa-lightbulb"></i> Instructions</h3>';
            html += '<ol class="ca-assignment-instructions">';
            a.instructions.forEach(function(inst) {
                html += '<li>' + escapeHtml(inst) + '</li>';
            });
            html += '</ol>';
            html += '</div>';
        }

        if (a.word_count) {
            html += '<p class="ca-meta"><i class="fa fa-file-text-o"></i> Expected length: <strong>' + a.word_count + ' words</strong></p>';
        }

        return html;
    };

    /* ── Chat Panel ── */
    const renderChat = function() {
        var messagesHtml = '';
        state.chatMessages.forEach(function(msg) {
            if (msg.type === 'ai') {
                messagesHtml += '<div class="ca-chat-message ca-chat-message--ai">';
                messagesHtml += '<div class="ca-chat-avatar ca-chat-avatar--ai"><i class="fa fa-robot"></i></div>';
                messagesHtml += '<div class="ca-chat-bubble ca-chat-bubble--ai">' + escapeHtml(msg.text) + '</div>';
                messagesHtml += '</div>';
            } else {
                messagesHtml += '<div class="ca-chat-message ca-chat-message--user">';
                messagesHtml += '<div class="ca-chat-avatar ca-chat-avatar--user"><i class="fa fa-user"></i></div>';
                messagesHtml += '<div class="ca-chat-bubble ca-chat-bubble--user">' + escapeHtml(msg.text) + '</div>';
                messagesHtml += '</div>';
            }
        });
        $('#ca-chat-messages').html(messagesHtml);
        scrollChatToBottom();

        var quickHtml = '';
        state.quickActions.forEach(function(action) {
            quickHtml += '<button class="ca-quick-chip">' + escapeHtml(action) + '</button>';
        });
        $('#ca-chat-quickactions').html(quickHtml);
    };

    const scrollChatToBottom = function() {
        var container = document.getElementById('ca-chat-messages');
        if (container) { container.scrollTop = container.scrollHeight; }
    };

    const sendChatMessage = function(text) {
        if (!text.trim()) { return; }
        state.chatMessages.push({ type: 'user', text: text.trim() });
        renderChat();

        // Simulate AI response.
        setTimeout(function() {
            state.chatMessages.push({
                type: 'ai',
                text: "I've noted your request: \"" + text.trim() + "\". I'll update the course content accordingly. (This is a placeholder — connect to the AI agent backend for real modifications.)"
            });
            renderChat();
        }, 800);
    };

    /* ── Event Listeners ── */
    const setupEventListeners = function() {
        $('#btn-publish').on('click', publishCourse);

        // Sidebar toggles & item clicks (delegated).
        $('#ca-sidebar-tree')
            .off('click.courseagent')
            .on('click.courseagent', '.ca-tree-section-header', function(e) {
                e.preventDefault();
                var index = parseInt($(this).data('index'), 10);
                if (state.expandedSections.has(index)) {
                    state.expandedSections.delete(index);
                } else {
                    state.expandedSections.add(index);
                }
                renderSidebar();
            })
            .on('click.courseagent', '.ca-tree-item', function(e) {
                e.preventDefault();
                var sectionIdx = parseInt($(this).data('section'), 10);
                var tab = $(this).data('tab');
                state.currentSectionIndex = sectionIdx;
                state.currentTab = tab;
                renderSidebar();
                renderMain();
            });

        // Main tab clicks (delegated).
        $('#ca-main')
            .off('click.courseagent')
            .on('click.courseagent', '.ca-main-tab', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                state.currentTab = tab;
                renderMain();
            });

        // Chat send.
        $('#ca-chat-send').on('click', function() {
            var $input = $('#ca-chat-input');
            sendChatMessage($input.val());
            $input.val('');
        });

        $('#ca-chat-input').on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                var $input = $(this);
                sendChatMessage($input.val());
                $input.val('');
            }
        });

        // Quick action chips.
        $('#ca-chat-quickactions')
            .off('click.courseagent')
            .on('click.courseagent', '.ca-quick-chip', function() {
                sendChatMessage($(this).text());
            });
    };

    /* ── Publish ── */
    const publishCourse = function() {
        if (!config.courseData) {
            Notification.addNotification({
                message: 'No course data found. Please generate a course first.',
                type:    'error'
            });
            return;
        }

        $('#btn-publish').prop('disabled', true).html('<i class="fa fa-spinner fa-spin fa-fw"></i> Publishing...');

        $.ajax({
            url:         config.wwwroot + '/local/courseagent/ajax.php?action=publish&sesskey=' + config.sesskey,
            type:        'POST',
            data:        JSON.stringify(config.courseData),
            contentType: 'application/json',
            dataType:    'json',
            success:     function(response) {
                $('#btn-publish').prop('disabled', false).html('<i class="fa fa-upload fa-fw"></i> Publish to Moodle');
                if (response.success) {
                    Notification.addNotification({ message: 'Course published successfully!', type: 'success' });
                    setTimeout(function() { window.location.href = response.course_url; }, 1500);
                } else {
                    Notification.addNotification({
                        message: response.error || 'Failed to publish course',
                        type:    'error'
                    });
                }
            },
            error: function(xhr) {
                $('#btn-publish').prop('disabled', false).html('<i class="fa fa-upload fa-fw"></i> Publish to Moodle');
                let msg = 'An error occurred while publishing the course';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
                Notification.addNotification({ message: msg, type: 'error' });
            }
        });
    };

    const escapeHtml = function(text) {
        if (typeof text !== 'string') { return String(text || ''); }
        const map = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    };

    return { init: init };
});
