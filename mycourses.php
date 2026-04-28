<?php
// This file is part of Course Agent - AI Course Creator Plugin for Moodle

/**
 * @package   local_courseagent
 * @copyright 2026 Course Agent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Require login and check capability.
$context = context_system::instance();
require_login();
require_capability('local/courseagent:viewmycourses', $context);

// Setup page.
$pageurl = new moodle_url('/local/courseagent/mycourses.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('my_courses', 'local_courseagent'));
$PAGE->set_heading(get_string('my_courses', 'local_courseagent'));
$PAGE->set_pagelayout('standard');

global $DB, $USER;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('course_history', 'local_courseagent'), 2);

// Get user's course sessions.
$sessions = $DB->get_records('courseagent_sessions', ['userid' => $USER->id], 'timecreated DESC');

if (empty($sessions)) {
    echo $OUTPUT->notification('You have not generated any courses yet.', 'info');
    echo html_writer::link(new moodle_url('/local/courseagent/index.php'), 
                          get_string('create_course', 'local_courseagent'),
                          ['class' => 'btn btn-primary']);
} else {
    // Display courses in a table.
    $table = new html_table();
    $table->attributes['class'] = 'table table-striped table-hover';
    $table->head = [
        'Date Created',
        'Course Title',
        'Status',
        'Actions'
    ];
    $table->data = [];

    foreach ($sessions as $session) {
        $course_data = json_decode($session->course_json);
        $title = !empty($course_data->title) ? $course_data->title : 'Untitled Course';
        $date = userdate($session->timecreated);
        $status = ucfirst($session->status);
        
        // Status badge styling.
        $status_class = $session->status === 'published' ? 'success' : 
                       ($session->status === 'failed' ? 'danger' : 'secondary');
        $status_badge = html_writer::tag('span', $status, 
                                        ['class' => "badge badge-{$status_class}"]);

        // Action links.
        $actions = '';
        if ($session->courseid) {
            $actions .= html_writer::link(
                new moodle_url('/course/view.php', ['id' => $session->courseid]),
                'View Course',
                ['class' => 'btn btn-sm btn-outline-primary mr-2']
            );
        }
        
        $table->data[] = [$date, $title, $status_badge, $actions];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
