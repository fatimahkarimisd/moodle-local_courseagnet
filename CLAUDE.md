# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Course Agent** is a Moodle local plugin that provides AI-powered course generation. It integrates with AI providers (Google Gemini, OpenAI, or custom providers) to generate complete courses with lessons, quizzes, and assignments.

**Key Design Principle**: All AI processing happens server-side via API calls. No browser-based AI libraries are used, keeping the JavaScript lightweight (~50KB) and avoiding navbar interference issues common in other AI plugins.

## Architecture

### Plugin Type
- **Type**: `local` plugin (site-wide functionality, not course-specific)
- **Location**: `local/courseagnet/`
- **Moodle Version**: Requires Moodle 5.0+, tested on 5.1
- **Component Name**: `local_courseagnet`

### Core Architecture

```
User Request → index.php → AMD JS → ajax.php → classes/api.php → AI Provider API
                                    ↓
                              classes/provider.php (encryption, config)
                                    ↓
                              Moodle Course APIs (create_course, add_moduleinfo)
```

### Key Entry Points

| File | Purpose |
|------|---------|
| `index.php` | Main course creation UI with two-column layout (form + preview) |
| `ajax.php` | AJAX endpoint for `generate`, `publish`, `test_provider`, `get_models` actions |
| `providers.php` | Full provider management page (CRUD, test connections, set default) |
| `mycourses.php` | Course history page showing user's generated courses |
| `settings.php` | Admin settings (max sections, quiz questions, assignments toggle) |

### Database Schema

Two custom tables:

1. **`courseagnet_sessions`** - Tracks course generations
   - `userid`, `courseid`, `status` (draft/published/failed)
   - `course_json` - Complete course structure as JSON
   - Timestamps for auditing

2. **`courseagnet_providers`** - AI provider configurations
   - `name`, `apikey` (encrypted), `baseurl`, `endpoint`
   - `models` (JSON array), `isdefault`, `enabled`, `sortorder`

### Security Model

- **Capabilities**: `local/courseagnet:createcourse`, `local/courseagnet:viewmycourses`
- **API Key Encryption**: Uses `aes-256-cbc` with HMAC verification via `classes/provider.php`
- **Encryption Key**: Derived from `get_site_identifier()` - site-specific
- **Context**: All operations use `CONTEXT_SYSTEM`

### AI Provider System

The `classes/provider.php` class manages multiple AI providers:

1. **Supported APIs**:
   - Google Gemini (`generativelanguage.googleapis.com`)
   - OpenAI-compatible (`openai.com` or custom)
   - Generic OpenAI-compatible endpoints

2. **Provider Configuration**:
   - Each provider has: name, encrypted API key, base URL, endpoint path, models array
   - One provider can be marked as default
   - Providers can be enabled/disabled independently

3. **API Request Format**:
   - Gemini: `POST {baseurl}/{endpoint}?key={apikey}` with `contents` array
   - OpenAI: `POST {baseurl}/{endpoint}` with Bearer token, `model` and `messages`

### Course Generation Flow

1. **Generation** (`ajax.php?action=generate`):
   - User submits topic, level, sections, quiz/assignment preferences
   - `api.php::generate_course_outline()` builds AI prompt
   - `provider::call_api()` sends request to configured provider
   - AI returns JSON course structure
   - Response displayed in preview panel

2. **Publishing** (`ajax.php?action=publish`):
   - `api.php::publish_course()` validates course data
   - Creates Moodle course via `create_course()`
   - Creates sections via `course_create_sections_if_missing()`
   - Adds modules: `mod_page` (lessons), `mod_quiz` (quizzes), `mod_assign` (assignments)
   - Saves session record to database

### Frontend Architecture

- **AMD Module**: `amd/src/coursecreator.js` - jQuery-based, uses `core/ajax`, `core/notification`
- **CSS**: `styles.css` - All styles scoped under `#courseagnet-app` to prevent conflicts
- **Page Layout**: Uses `$PAGE->set_pagelayout('standard')` for consistent Moodle theming

### Navigation Integration

Three navigation hooks in `lib.php`:
- `local_courseagnet_extend_navigation()` - Adds to main navigation drawer (for teachers+)
- `local_courseagnet_extend_navigation_user()` - Adds to user profile nav
- `local_courseagnet_extend_settings_navigation()` - Adds under Site Administration

Plus modern hook in `classes/hook/navigation.php` for Moodle 5.x primary navigation.

## File Organization

```
local/courseagnet/
├── amd/src/coursecreator.js          # Frontend JavaScript (AMD module)
├── classes/
│   ├── api.php                       # Core business logic: generate, publish, create modules
│   ├── provider.php                  # AI provider management: CRUD, encryption, API calls
│   ├── form/provider_form.php      # Provider add/edit form with dynamic model list
│   ├── hook/navigation.php           # Moodle 5.x hook for primary nav
│   └── privacy/provider.php          # GDPR compliance implementation
├── db/
│   ├── access.php                    # Capability definitions
│   ├── install.xml                   # Database schema
│   ├── hooks.php                     # Hook callbacks registration
│   └── upgrade.php                   # Version upgrades
├── lang/en/local_courseagnet.php     # All language strings
├── ajax.php                          # AJAX endpoint
├── index.php                         # Main creation page
├── lib.php                           # Navigation callbacks (minimal)
├── mycourses.php                     # Course history
├── providers.php                     # Provider management
├── settings.php                      # Admin settings
├── styles.css                        # Scoped styles
└── version.php                       # Plugin metadata
```

## Common Tasks

### Adding a New AI Provider

Edit `classes/provider.php`:
- `call_api()` - Add detection logic for new API format
- `test_connection()` - Add test logic for new provider

### Modifying Course Structure

Edit `classes/api.php`:
- `build_generation_prompt()` - Change AI prompt structure
- `publish_course()` - Modify course creation logic
- `create_lesson_page()`, `create_quiz()`, `create_assignment()` - Module-specific changes

### Adding AJAX Actions

1. Add case in `ajax.php` switch statement
2. Update `amd/src/coursecreator.js` to call new action

### Database Changes

1. Edit `db/install.xml` for new installations
2. Add upgrade step in `db/upgrade.php` for existing installations
3. Bump version in `version.php`

## Key Implementation Details

### Provider Form Model Widget

The provider form uses a JavaScript-powered dynamic list for models (in `classes/form/provider_form.php`):
- Hidden `models_json` field stores JSON array
- JavaScript widget renders sortable, editable list
- First model in list is the default

### Quiz Creation

Quizzes are created but questions are not populated. The AI generates question data in the course JSON, but the plugin currently only creates the quiz activity shell. Full question population would require `questionlib.php` integration.

### Session Key Validation

All AJAX endpoints require `require_sesskey()` - JavaScript passes `config.sesskey` from PHP via `amd/src/coursecreator.js`.

### Error Handling Pattern

```php
// AJAX pattern used throughout
try {
    // Operation
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
```

## External Dependencies

- **Moodle Core**: `lib/modulerlib.php`, `lib/questionlib.php` (implied)
- **PHP Extensions**: `openssl` (for encryption), `curl` (for API calls)
- **AI APIs**: Google Gemini API or OpenAI API (or compatible)

## Notes for Development

- **No Build Step**: AMD JavaScript is hand-written, not compiled. Minified version in `amd/build/` is manually created or use Moodle's `grunt` if configured.
- **Encryption**: API keys are encrypted at rest. When editing a provider, leaving API key blank preserves existing key.
- **Capability Checks**: Always check `local/courseagnet:createcourse` before allowing course generation.
- **Responsive Design**: Uses Bootstrap 4 classes (Moodle 5.x standard), styles scoped to `#courseagnet-app`.
