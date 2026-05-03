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

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Course Agent - AI Course Creator';
$string['courseagent:createcourse'] = 'Create AI generated courses';
$string['courseagent:viewmycourses'] = 'View my AI generated courses';

// Page strings.
$string['create_course'] = 'Create AI Course';
$string['my_courses'] = 'My AI Courses';
$string['course_history'] = 'Course Generation History';
$string['coursetopic'] = 'Course Topic';

// Settings strings.
$string['settings'] = 'Course Agent Settings';
$string['generation_settings'] = 'Course Generation Settings';
$string['max_sections'] = 'Maximum Sections';
$string['max_sections_desc'] = 'Maximum number of course sections to generate.';
$string['max_quiz_questions'] = 'Maximum Quiz Questions';
$string['max_quiz_questions_desc'] = 'Maximum number of quiz questions per section.';
$string['enable_assignments'] = 'Enable Assignment Generation';
$string['enable_assignments_desc'] = 'When enabled, AI will generate assignments for each section.';
$string['use_emojis'] = 'Use Emojis';
$string['use_emojis_desc'] = 'Add relevant emojis throughout the content to make it more engaging.';
$string['use_svg'] = 'Include SVG Diagrams';
$string['use_svg_desc'] = 'Generate simple SVG illustrations and diagrams where helpful for explanations.';

// Provider management strings.
$string['provider_management'] = 'AI Provider Management';
$string['provider_manage_link'] = 'Manage AI Providers';
$string['provider_add'] = 'Add New Provider';
$string['provider_add_heading'] = 'Add Provider';
$string['provider_edit'] = 'Edit Provider';
$string['provider_name'] = 'Provider Name';
$string['provider_name_desc'] = 'A short, recognisable label for this connection — for example <strong>OpenAI GPT-4o</strong> or <strong>Gemini 2.5 Flash</strong>. Shown in the provider list and model selector.';
$string['provider_name_help'] = 'A friendly name for this AI provider (e.g., "OpenAI GPT-4", "Google Gemini").';
$string['provider_apikey'] = 'API Key';
$string['provider_apikey_desc'] = 'The secret key issued by your AI provider. It is encrypted with AES-256 before being stored in the database — it is never saved in plain text. You can find your key in your provider\'s developer console.';
$string['provider_apikey_help'] = 'Your secret API key for authentication. This is encrypted with AES-256 before being saved and is never stored in plain text.';
$string['provider_apikey_note'] = 'Leave this field as-is to keep the existing API key. Only type a new value if you want to replace it.';
$string['provider_baseurl'] = 'Base URL';
$string['provider_baseurl_desc'] = 'The root URL of the API — without a trailing slash. Everything else is appended to this.<br>Examples:<br>&nbsp;• <code>https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-04-17:generateContent</code> (Gemini)<br>&nbsp;• <code>https://api.openai.com/v1</code> (OpenAI)<br>&nbsp;• <code>https://api.groq.com/openai/v1</code> (Groq)';
$string['provider_baseurl_help'] = 'The root URL of the AI API. Do not include a trailing slash.';
$string['provider_baseurl_invalid'] = 'Please enter a valid URL starting with http:// or https://.';
$string['provider_endpoint'] = 'Chat Endpoint Path';
$string['provider_endpoint_desc'] = 'The path that is appended to the Base URL to reach the chat/completion endpoint.<br>Examples:<br>&nbsp;• Leave <strong>empty</strong> if the Base URL already points directly to the endpoint (Gemini)<br>&nbsp;• <code>chat/completions</code> for OpenAI-compatible APIs<br>&nbsp;• <code>chat/completions</code> for Groq, Together AI, Mistral, etc.';
$string['provider_endpoint_help'] = 'The endpoint path appended to the base URL for chat/completion requests. Leave blank if the base URL is the full endpoint.';
$string['provider_api_format'] = 'API Format';
$string['provider_api_format_help'] = 'Select the request format this provider expects. Use <strong>OpenAI-compatible</strong> for OpenAI, OpenRouter, NVIDIA NIM, Groq, Mistral, Together AI, Ollama, LM Studio, and any other OpenAI-style endpoint. Use <strong>Google Gemini</strong> for Google AI Studio or Vertex AI endpoints — these use a different authentication and request body format.';
$string['provider_api_format_openai'] = 'OpenAI-compatible (OpenAI, OpenRouter, NVIDIA, Groq, Mistral, Ollama...)';
$string['provider_api_format_gemini'] = 'Google Gemini (AI Studio / Vertex AI)';
$string['provider_models'] = 'Available Models';
$string['provider_models_desc'] = 'The model identifiers that this provider supports. The <strong>first model</strong> in the list is used as the default when generating courses. Add at least one model. Use the exact model ID from your provider\'s documentation.';
$string['provider_models_help'] = 'Add one or more model identifiers. The first model in the list is the default. Use exact IDs from the provider documentation, e.g. <code>gpt-4o</code>, <code>gemini-2.5-flash-preview-04-17</code>.';
$string['provider_model_add'] = 'Add Model';
$string['provider_model_placeholder'] = 'e.g. gpt-4o or gemini-2.5-flash-preview-04-17';
$string['provider_model_remove'] = 'Remove';
$string['provider_model_empty_error'] = 'Please enter a model ID before adding.';
$string['provider_model_duplicate_error'] = 'This model ID has already been added.';
$string['provider_isdefault'] = 'Set as Default Provider';
$string['provider_isdefault_desc'] = 'When checked, this provider is automatically used for all course generation tasks unless overridden per-request. Only one provider can be the default at a time — enabling this will unset any existing default.';
$string['provider_isdefault_help'] = 'Make this the default provider for course generation. Only one provider can be the default — setting this will remove the default flag from any other provider.';
$string['provider_enabled'] = 'Enabled';
$string['provider_enabled_desc'] = 'Enable or disable this provider. Disabled providers remain saved but cannot be used for course generation. Useful for temporarily switching between providers without deleting configuration.';
$string['provider_enabled_help'] = 'Only enabled providers can be used for course generation. Disable a provider to pause it without deleting its configuration.';
$string['provider_status'] = 'Status';
$string['provider_default'] = 'Default';
$string['provider_disabled'] = 'Disabled';
$string['provider_test'] = 'Test Connection';
$string['provider_test_connection'] = 'Test Connection';
$string['provider_test_loading'] = 'Testing connection...';
$string['provider_test_success'] = 'Connection successful!';
$string['provider_test_failed'] = 'Connection failed';
$string['provider_test_noapikey'] = 'Please enter an API key to test.';
$string['provider_test_nobaseurl'] = 'Please enter a Base URL to test.';
$string['provider_test_validating'] = 'Validating credentials...';
$string['provider_test_show_response'] = 'Show response body';
$string['provider_test_hide_response'] = 'Hide response body';
$string['provider_test_response_body'] = 'Response Body';
$string['provider_toggle'] = 'Toggle Enable/Disable';
$string['provider_set_default'] = 'Set as default provider';
$string['provider_autoselect'] = 'Auto-select (first available)';
$string['default_provider'] = 'Default Provider';
$string['default_provider_desc'] = 'Select the default AI provider for course generation.';

// Provider CRUD messages.
$string['provider_created'] = 'Provider created successfully.';
$string['provider_updated'] = 'Provider updated successfully.';
$string['provider_deleted'] = 'Provider deleted successfully.';
$string['provider_name_exists'] = 'A provider with this name already exists. Please choose a different name.';
$string['provider_delete_confirm'] = 'Are you sure you want to delete the provider "{$a}"? This action cannot be undone.';
$string['provider_no_providers'] = 'No AI providers configured yet.';
$string['provider_no_providers_help'] = 'Add an AI provider to enable course generation. You can add multiple providers (e.g. OpenAI and Gemini) and switch between them.';

// Error strings.
$string['error_nopermission'] = 'You do not have permission to use Course Agent.';
$string['error_invalid_action'] = 'Invalid action requested.';
$string['error_invalid_json'] = 'Invalid JSON data received.';
$string['error_course_creation_failed'] = 'Failed to create course.';
$string['error_no_provider'] = 'No AI provider configured. Please contact your administrator.';

// Privacy strings.
$string['privacy:metadata:courseagent_sessions'] = 'Stores information about AI-generated course sessions.';
$string['privacy:metadata:courseagent_sessions:userid'] = 'The ID of the user who generated the course.';
$string['privacy:metadata:courseagent_sessions:courseid'] = 'The ID of the created Moodle course.';
$string['privacy:metadata:courseagent_sessions:status'] = 'The status of the generation session.';
$string['privacy:metadata:courseagent_sessions:course_json'] = 'The JSON data containing the course structure.';
$string['privacy:metadata:courseagent_sessions:timecreated'] = 'The time when the session was created.';
$string['privacy:metadata:courseagent_sessions:timemodified'] = 'The time when the session was last modified.';
$string['privacy:metadata:courseagent_providers'] = 'Stores AI provider configurations.';
$string['privacy:metadata:courseagent_providers:apikey'] = 'Encrypted API key for the AI provider.';

// Preview page.
$string['preview_course'] = 'Preview Course';
$string['preview_description'] = 'Review your AI-generated course before publishing.';
$string['back_to_create'] = 'Back to Create';
$string['no_preview_data'] = 'No course data found. Please generate a course first.';
$string['publish_to_moodle'] = 'Publish to Moodle';

// Actions.
$string['actions'] = 'Actions';
$string['provider_models_list_label'] = 'Model ID';

// Preview page UI strings.
$string['draft_mode'] = 'Draft Mode';
$string['course_builder'] = 'Course Builder';
$string['ai_assistant_active'] = 'AI Assistant Active';
$string['agent_assistant'] = 'Agent Assistant';
$string['chat_placeholder'] = 'Ask the agent to modify content...';
$string['chat_disclaimer'] = 'Agent can make mistakes. Consider verifying changes.';
