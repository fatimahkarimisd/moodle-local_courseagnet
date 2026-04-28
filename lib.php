<?php
// This file is part of Course Agent - AI Course Creator Plugin for Moodle

/**
 * @package   local_courseagent
 * @copyright 2026 Course Agent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the main navigation (side drawer in Boost theme).
 * Only visible to users with course creation capability (teachers, managers, admins).
 *
 * @param global_navigation $navigation The navigation object
 */
function local_courseagent_extend_navigation(global_navigation $navigation): void {
    global $PAGE;

    // Check capability - only show for teachers, managers, admins.
    $context = context_system::instance();
    if (!has_capability('local/courseagent:createcourse', $context)) {
        return;
    }

    // Create the navigation node for the side drawer.
    $url = new moodle_url('/local/courseagent/index.php');
    $node = navigation_node::create(
        get_string('pluginname', 'local_courseagent'),
        $url,
        navigation_node::TYPE_CUSTOM,
        'local_courseagent',
        'local_courseagent',
        new pix_icon('i/course', '')
    );

    // Add to main navigation (visible in Boost drawer/sidebar).
    $navigation->add_node($node);

    // Mark as active if on our plugin page.
    if ($PAGE->url && strpos($PAGE->url->out(), '/local/courseagent/') !== false) {
        $node->make_active();
    }
}

/**
 * Extends the user profile navigation.
 * Adds link to user's profile page for quick access.
 *
 * @param navigation_node $parentnode The parent navigation node
 * @param stdClass $user The user object
 * @param context_user $context The user context
 * @param stdClass $course The course object
 * @param context_course $coursecontext The course context
 */
function local_courseagent_extend_navigation_user(
    navigation_node $parentnode,
    stdClass $user,
    context_user $context,
    stdClass $course,
    context_course $coursecontext
): void {
    // Check capability - only show for teachers, managers, admins.
    $systemcontext = context_system::instance();
    if (!has_capability('local/courseagent:createcourse', $systemcontext)) {
        return;
    }

    // Add link to user profile navigation.
    $url = new moodle_url('/local/courseagent/index.php');
    $node = navigation_node::create(
        get_string('pluginname', 'local_courseagent'),
        $url,
        navigation_node::TYPE_SETTING,
        'local_courseagent',
        'local_courseagent',
        new pix_icon('i/course', '')
    );
    $parentnode->add_node($node);
}

/**
 * Extends the settings navigation.
 * Adds link under Site administration for admins.
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param context $context The current context
 */
function local_courseagent_extend_settings_navigation(settings_navigation $settingsnav, context $context): void {
    global $PAGE;

    // Only add on site context (front page/admin pages).
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return;
    }

    // Check capability.
    if (!has_capability('local/courseagent:createcourse', $context)) {
        return;
    }

    // Try to add to site administration menu if available.
    $adminnode = $settingsnav->find('root', navigation_node::TYPE_SITE_ADMIN);
    if ($adminnode) {
        $url = new moodle_url('/local/courseagent/index.php');
        $node = navigation_node::create(
            get_string('pluginname', 'local_courseagent'),
            $url,
            navigation_node::TYPE_SETTING,
            'local_courseagent',
            'local_courseagent',
            new pix_icon('i/course', '')
        );
        $adminnode->add_node($node);
    }
}