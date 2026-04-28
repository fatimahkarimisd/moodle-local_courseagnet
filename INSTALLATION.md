# Course Agent Installation Guide for Moodle 5.1

## Quick Installation Steps

### Step 1: Verify Plugin Location
The plugin is already created at:
```
C:\xampp\htdocs\moodle\public\local\courseagnet\
```

### Step 2: Install the Plugin

1. **Log in to Moodle as Administrator**
   - URL: `http://localhost/moodle/public/admin/`
   - Use your admin credentials

2. **Navigate to Notifications**
   - Go to: **Site Administration** (from the top navbar)
   - Click on: **Notifications**
   - Moodle will automatically detect the new plugin

3. **Complete Installation**
   - You'll see: "Local plugin - Course Agent" needs to be installed
   - Click: **"Upgrade Moodle database now"**
   - Wait for the installation to complete
   - You should see success messages for:
     - Creating database table: `courseagnet_sessions`
     - Installing capabilities
     - Installing language strings

4. **Verify Installation**
   - Go to: **Site Administration > Plugins > Local plugins**
   - You should see: **"Course Agent - AI Course Creator"**

### Step 3: Configure the Plugin

1. **Access Plugin Settings**
   - Go to: **Site Administration > Plugins > Local plugins > Course Agent - AI Course Creator**
   - OR click on "Course Agent" in the Local plugins list

2. **Configure AI Provider**
   - **AI Provider**: Choose between:
     - Google Gemini API (Recommended - Free tier available)
     - OpenAI API
     - Local WebGPU (Browser-based, experimental)

3. **Enter API Key**
   
   **For Google Gemini:**
   - Visit: https://aistudio.google.com/app/apikey
   - Sign in with Google account
   - Create a new API key
   - Copy and paste it into the "Gemini API Key" field
   
   **For OpenAI:**
   - Visit: https://platform.openai.com/api-keys
   - Create an API key
   - Copy and paste it into the "OpenAI API Key" field

4. **Configure Other Settings**
   - **Default AI Model**: Leave as default or change
   - **Maximum Sections**: 4-8 (recommended)
   - **Maximum Quiz Questions**: 5-10 (recommended)
   - **Enable Assignment Generation**: Check/uncheck as needed

5. **Save Changes**
   - Click: **"Save changes"** at the bottom

### Step 4: Set User Permissions

1. **Navigate to Role Definitions**
   - Go to: **Site Administration > Users > Permissions > Define roles**

2. **Configure for Teachers**
   - Click on: **"Editing teacher"** (or your teacher role)
   - Find these capabilities and set to **Allow**:
     - `local/courseagnet:createcourse`
     - `local/courseagnet:viewmycourses`
   - Click: **"Save changes"**

3. **Configure for Managers** (if not already set)
   - Click on: **"Manager"**
   - Ensure these are set to **Allow**:
     - `local/courseagnet:createcourse`
     - `local/courseagnet:viewmycourses`

### Step 5: Test the Plugin

1. **Access Course Creator**
   - Go to: **Site Administration > Plugins > Local plugins > Course Agent - AI Course Creator**
   - OR visit: `http://localhost/moodle/public/local/courseagnet/index.php`

2. **Generate a Test Course**
   - Enter a topic: "Introduction to Web Development"
   - Select level: "Beginner"
   - Set sections: 3
   - Check: "Include Quiz per Section"
   - Click: **"Generate Course Outline"**
   - Wait 30-60 seconds for AI to generate

3. **Review and Publish**
   - Review the generated outline
   - Click: **"Publish to Moodle"**
   - You'll be redirected to the created course

4. **View Course History**
   - Go to: **Site Administration > Plugins > Local plugins > My AI Courses**
   - OR visit: `http://localhost/moodle/public/local/courseagnet/mycourses.php`

## Troubleshooting

### Issue: "Plugin not detected by Moodle"

**Solution:**
1. Verify folder name is exactly: `courseagnet`
2. Check that `version.php` exists in the root folder
3. Purge caches: **Site Administration > Development > Purge all caches**
4. Refresh the Notifications page

### Issue: "Database installation failed"

**Solution:**
1. Check Moodle error logs
2. Verify database permissions
3. Try again after purging caches
4. Check that `db/install.xml` exists and is valid XML

### Issue: "Navbar links hidden after installation"

**This plugin is specifically designed NOT to interfere with Moodle's navigation.**

If you experience navbar issues:

1. **Clear Browser Cache**
   - Press `Ctrl + Shift + Delete`
   - Clear all cached data
   - Restart browser

2. **Purge Moodle Caches**
   - Go to: **Site Administration > Development > Purge all caches**
   - Click: **"Purge all caches"**

3. **Check Theme Compatibility**
   - Go to: **Site Administration > Appearance > Themes**
   - Try switching to a default theme (Boost, Classic)
   - Check if navbar issue persists

4. **Disable and Re-enable**
   - Go to: **Site Administration > Plugins > Manage plugins**
   - Find "Course Agent" in Local plugins
   - Disable it, then enable again

### Issue: "API errors when generating courses"

**For Gemini API:**
1. Verify API key is correct
2. Check you have quota remaining
3. Ensure model name is valid (e.g., `gemini-2.5-flash`)
4. Check internet connection

**For OpenAI API:**
1. Verify API key is correct
2. Check account balance/credits
3. Ensure model name is valid (e.g., `gpt-4`)
4. Check internet connection

### Issue: "Course generation fails"

**Solution:**
1. Check API key is configured
2. Verify internet connection
3. Try with fewer sections (2-3)
4. Check PHP error logs
5. Increase PHP timeout in `php.ini`:
   ```ini
   max_execution_time = 300
   ```

### Issue: "Permission denied"

**Solution:**
1. Verify user has required capabilities
2. Check role definitions (Step 4 above)
3. Ensure user is logged in
4. Check context level is SYSTEM

## Post-Installation Checklist

- [ ] Plugin installed successfully
- [ ] Database table `courseagnet_sessions` created
- [ ] API key configured (Gemini or OpenAI)
- [ ] Teacher/Manager roles have permissions
- [ ] Test course generated successfully
- [ ] Test course published successfully
- [ ] No navbar issues
- [ ] Can view course history

## File Structure Verification

After installation, verify these files exist:

```
C:\xampp\htdocs\moodle\public\local\courseagnet\
├── amd/
│   ├── build/
│   │   └── coursecreator.min.js ✓
│   └── src/
│       └── coursecreator.js ✓
├── classes/
│   ├── privacy/
│   │   └── provider.php ✓
│   └── api.php ✓
├── db/
│   ├── access.php ✓
│   ├── install.php ✓
│   ├── install.xml ✓
│   └── uninstall.php ✓
├── lang/
│   └── en/
│       └── local_Course Agent.php ✓
├── ajax.php ✓
├── index.php ✓
├── lib.php ✓
├── mycourses.php ✓
├── settings.php ✓
├── styles.css ✓
├── version.php ✓
└── README.md ✓
```

## Next Steps

1. **Create Your First Course**: Follow the test steps above
2. **Explore Settings**: Try different AI models and configurations
3. **Train Your Teachers**: Show teachers how to access and use the plugin
4. **Monitor Usage**: Check course history regularly

## Support

If you encounter issues not covered here:

1. **Check Debugging**: Enable debugging in Moodle
   - **Site Administration > Development > Debugging**
   - Set to: DEVELOPER level
   
2. **Check Logs**: Review Moodle logs for errors

3. **Review Documentation**: Check the README.md file

4. **Test API Connection**: Verify your API key works outside Moodle

## Uninstallation

If you need to remove the plugin:

1. **Disable the Plugin**
   - Go to: **Site Administration > Plugins > Manage plugins**
   - Find Course Agent
   - Click: **Disable**

2. **Uninstall**
   - Click: **Uninstall** on the plugin page
   - Confirm uninstallation

3. **Delete Files** (optional)
   - Delete the folder: `C:\xampp\htdocs\moodle\public\local\courseagnet\`

4. **Clean Database** (optional)
   - The uninstall script will remove the `courseagnet_sessions` table
   - Or manually drop the table if needed

---

**Congratulations!** Your Course Agent plugin is now installed and ready to use! 🎉
