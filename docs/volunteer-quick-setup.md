# Volunteer System Quick Setup Guide

## Current Status ✅

Based on your test results, the volunteer system is properly installed:

- ✅ Database tables exist
- ✅ All functions are available
- ✅ AJAX handlers are registered
- ❌ No volunteer tasks created yet
- ❌ makeMember object not available (expected on admin pages)

## Next Steps to Complete Setup

### 1. Create Volunteer Tasks (Required)

The system needs volunteer tasks for users to select during sign-out:

1. **Go to WordPress Admin → Volunteering → Tasks**
2. **Click "Add New Task"**
3. **Create a few sample tasks:**

**Sample Task 1:**

- Title: "Clean Workshop Area"
- Content: "Sweep floors, organize tools, and wipe down work surfaces"
- Category: "Maintenance"
- Priority: "Medium"
- Estimated Duration: "30" minutes
- Location: "Main Workshop"

**Sample Task 2:**

- Title: "Organize Storage Room"
- Content: "Sort materials, label shelves, and inventory supplies"
- Category: "Organization"
- Priority: "Low"
- Estimated Duration: "60" minutes
- Location: "Storage Room"

**Sample Task 3:**

- Title: "Front Desk Support"
- Content: "Greet visitors, answer questions, and assist with sign-ins"
- Category: "Administrative"
- Priority: "High"
- Estimated Duration: "120" minutes
- Location: "Front Desk"

### 2. Test the Frontend (Where it Actually Works)

The volunteer system works on frontend pages with the member sign-in block, not in admin:

1. **Go to your member sign-in page** (frontend, not admin)
2. **Open browser developer tools (F12)**
3. **Look for "Make Volunteer:" messages in console**
4. **Test the workflow:**
   - Click a user profile card
   - Select "Volunteering"
   - Click "Done!"
   - Wait a few seconds, then click the same user's profile card again
   - You should see the volunteer sign-out interface with your tasks

### 3. Expected Workflow

Once tasks are created, here's how it should work:

**Sign-In Process:**

1. User clicks their profile card
2. Selects "Volunteering" from options
3. Clicks "Done!"
4. Sees volunteer-specific success message
5. Volunteer session is created in database

**Sign-Out Process:**

1. User clicks their profile card again (while volunteering)
2. System detects active volunteer session
3. Shows sign-out interface with:
   - Current session info
   - Available tasks to select
   - Notes field
4. User selects completed tasks and adds notes
5. Clicks "Sign Out"
6. Session is completed and recorded

## Troubleshooting

### If volunteer sign-out interface doesn't appear:

1. Check browser console for "Make Volunteer:" debug messages
2. Verify the user actually has an active volunteer session
3. Check WordPress debug logs for any errors

### If tasks don't show during sign-out:

1. Make sure you created and published volunteer tasks
2. Verify tasks have all required fields filled out
3. Check that ACF (Advanced Custom Fields) plugin is active

### If JavaScript errors occur:

1. Make sure you're testing on the frontend member sign-in page
2. Verify all scripts are loading properly
3. Check for conflicts with other plugins

## Quick Test Commands

You can also test the system programmatically. Add this to your theme's functions.php temporarily:

```php
// Test volunteer session creation (remove after testing)
add_action('wp_footer', function() {
    if (current_user_can('manage_options') && isset($_GET['test_volunteer'])) {
        $user_id = 1; // Change to a real user ID
        $session_id = make_start_volunteer_session($user_id);
        if (is_wp_error($session_id)) {
            echo '<script>console.log("Session Error: ' . $session_id->get_error_message() . '");</script>';
        } else {
            echo '<script>console.log("Session Created: ' . $session_id . '");</script>';
        }
    }
});
```

Then visit your site with `?test_volunteer=1` in the URL to test session creation.

## Summary

Your volunteer system is properly installed and configured. You just need to:

1. **Create some volunteer tasks** (most important)
2. **Test on the frontend member sign-in page** (not admin)
3. **Follow the sign-in → sign-out workflow**

The system should work perfectly once you have volunteer tasks created!
