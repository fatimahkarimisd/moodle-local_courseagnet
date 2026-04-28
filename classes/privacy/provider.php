<?php
// This file is part of Course Agent - AI Course Creator Plugin for Moodle

/**
 * @package   local_courseagent
 * @copyright 2026 Course Agent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseagent\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for Course Agent plugin.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userdata_provider
{

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of personal data locations through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'courseagent_sessions',
            [
                'userid' => 'privacy:metadata:courseagent_sessions:userid',
                'courseid' => 'privacy:metadata:courseagent_sessions:courseid',
                'status' => 'privacy:metadata:courseagent_sessions:status',
                'course_json' => 'privacy:metadata:courseagent_sessions:course_json',
                'timecreated' => 'privacy:metadata:courseagent_sessions:timecreated',
                'timemodified' => 'privacy:metadata:courseagent_sessions:timemodified',
            ],
            'privacy:metadata:courseagent_sessions'
        );
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_user_context($userid);
        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        $sessions = $DB->get_records('courseagent_sessions', ['userid' => $user->id]);

        foreach ($sessions as $session) {
            $context = \context_user::instance($user->id);
            writer::with_context($context)->export_data(
                ['local_courseagent', 'session_' . $session->id],
                $session
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_USER) {
            $DB->delete_records('courseagent_sessions');
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved context and user to delete data for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $DB->delete_records('courseagent_sessions', ['userid' => $userid]);
    }
}
