# Course Agent - AI Course Creator Plugin for Moodle

A powerful Moodle plugin that uses AI (Google Gemini or OpenAI) to automatically generate complete courses with lessons, quizzes, and assignments.

## Features

- **AI-Powered Course Generation**: Create complete course outlines using Google Gemini or OpenAI
- **Automatic Content Creation**: Generates lessons, quizzes, and assignments
- **One-Click Publishing**: Publish AI-generated courses directly to Moodle
- **Course History**: Track all your generated courses
- **Flexible Configuration**: Customize number of sections, quiz questions, and assignments
- **Moodle 5.1 Compatible**: Fully compatible with Moodle 5.0 and 5.1

## Installation

1. **Download the Plugin**
   - The plugin files should be in: `C:\xampp\htdocs\moodle\public\local\courseagnet`

2. **Install via Moodle Admin**
   - Log in to Moodle as an administrator
   - Navigate to: **Site Administration > Notifications**
   - Moodle will detect the new plugin and prompt you to upgrade
   - Click **Upgrade Moodle database now**
   - Wait for the installation to complete

3. **Configure the Plugin**
   - Go to: **Site Administration > Plugins > Local plugins > Course Agent - AI Course Creator**
   - Select your AI provider (Google Gemini or OpenAI)
   - Enter your API key:
     - For Gemini: Get a free API key at https://aistudio.google.com/app/apikey
     - For OpenAI: Get an API key at https://platform.openai.com/api-keys
   - Configure other settings as needed:
     - Default AI model
     - Maximum sections
     - Maximum quiz questions per section
     - Enable/disable assignment generation

4. **Set Permissions** (if needed)
   - Go to: **Site Administration > Users > Permissions > Define roles**
   - Ensure the appropriate roles (Teacher, Manager, etc.) have these capabilities:
     - `local/courseagnet:createcourse` - Create AI generated courses
     - `local/courseagnet:viewmycourses` - View my AI generated courses

## Usage

### Creating a Course

1. Navigate to: **Site Administration > Plugins > Local plugins > Course Agent - AI Course Creator**
   - Or access directly at: `http://your-moodle-site/local/courseagnet/index.php`

2. **Fill in the Course Details**:
   - **Course Topic**: Enter the main topic/title of your course
   - **Course Level**: Select Beginner, Intermediate, or Advanced
   - **Number of Sections**: Choose how many sections (2 to max configured)
   - **Include Quiz**: Check to add quizzes to each section
   - **Include Assignment**: Check to add assignments to each section

3. **Generate Course Outline**:
   - Click the **Generate Course Outline** button
   - Wait for the AI to generate your course (this may take 30-60 seconds)
   - Review the generated course outline in the preview panel

4. **Publish or Regenerate**:
   - **Publish to Moodle**: Click to create the actual Moodle course with all modules
   - **Regenerate**: Click to try again with a different outline

### Viewing Your Courses

1. Navigate to: **Site Administration > Plugins > Local plugins > My AI Courses**
   - Or access directly at: `http://your-moodle-site/local/courseagnet/mycourses.php`

2. View all your AI-generated courses with:
   - Creation date
   - Course title
   - Status (draft/published)
   - Direct link to view the course

## Requirements

- **Moodle Version**: 5.0 or higher (tested on 5.1)
- **PHP Version**: 7.4 or higher
- **API Key**: Google Gemini API key OR OpenAI API key
- **Internet Connection**: Required for AI API calls

## Troubleshooting

### Navbar Links Disappearing

This plugin is specifically designed to NOT interfere with Moodle's navigation. If you experience navbar issues:

1. Clear your browser cache
2. Purge all caches in Moodle: **Site Administration > Development > Purge all caches**
3. Check if the issue persists with the plugin disabled

### API Errors

**"Gemini API key not configured"**
- Contact your Moodle administrator
- Ensure the API key is set in plugin settings

**"Invalid response from API"**
- Check your internet connection
- Verify the API key is correct
- Check if you have API quota remaining

### Course Generation Fails

**"Failed to parse AI response"**
- The AI may have returned malformed JSON
- Try regenerating the course
- Check the AI provider settings

**"Course must have sections"**
- Ensure the number of sections is set correctly
- Try with a different course topic

## Technical Details

### Plugin Structure

```
courseagnet/
├── amd/
│   ├── build/
│   │   └── coursecreator.min.js    # Compiled JavaScript
│   └── src/
│       └── coursecreator.js        # Source JavaScript
├── classes/
│   ├── privacy/
│   │   └── provider.php            # GDPR privacy provider
│   └── api.php                     # Main API class
├── db/
│   ├── access.php                  # Capabilities
│   ├── install.php                 # Install script
│   ├── install.xml                 # Database schema
│   └── uninstall.php               # Uninstall script
├── lang/
│   └── en/
│       └── local_courseagnet.php   # Language strings
├── ajax.php                        # AJAX handler
├── index.php                       # Main course creation page
├── lib.php                         # Plugin functions
├── mycourses.php                   # Course history page
├── settings.php                    # Admin settings
├── styles.css                      # Plugin styles
└── version.php                     # Version information
```

### Database Tables

**courseagnet_sessions**
- Stores all AI course generation sessions
- Fields: id, userid, courseid, status, course_json, timecreated, timemodified

### Capabilities

- **local/courseagnet:createcourse**: Permission to create AI courses
- **local/courseagnet:viewmycourses**: Permission to view your generated courses

## Support

For issues, questions, or contributions:
- Check Moodle documentation
- Review error logs in: **Site Administration > Development > Debugging**
- Contact your Moodle administrator

## License

This plugin is released under the GNU GPL v3 or later license.

## Credits

Developed for Moodle 5.1 - AI Course Creator Plugin
Copyright 2026 Course Agent
