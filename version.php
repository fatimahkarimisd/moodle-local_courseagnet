<?php
// This file is part of Course Agent - AI Course Creator Plugin for Moodle

/**
 * @package   local_courseagent
 * @copyright 2026 Course Agent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026042112; // Primary navbar hook fix - use primary_extend instead of before_http_headers.
$plugin->requires  = 2024100805; // Requires Moodle 5.0+ (compatible with 5.1).
$plugin->component = 'local_courseagent'; // Full name of the plugin.
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.2';
$plugin->supported = [500, 501]; // Supported from Moodle 5.0 to 5.1.
