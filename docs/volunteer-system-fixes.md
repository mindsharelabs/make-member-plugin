# Volunteer System Fixes and Troubleshooting Guide

## Issues Fixed

### 1. **Added Comprehensive Debugging**

- Added debug logging to all volunteer database operations
- Added debug logging to all AJAX handlers
- Added frontend console logging to volunteer JavaScript
- Added error logging for database table creation

### 2. **Database Table Verification**

- Created `make_verify_volunteer_tables()` function to check if tables exist
- Added automatic table creation if tables are missing
- Added table verification before starting volunteer sessions

### 3. **Enhanced Error Handling**

- Added proper error messages with database error details
- Added nonce verification logging
- Added user ID validation logging

### 4. **Admin Test Interface**

- Added "System Test" page under Volunteering admin menu
- Created comprehensive system status checker
- Added manual table creation buttons
- Added AJAX endpoint testing tools

## How to Troubleshoot

### Step 1: Enable WordPress Debug Mode

Add these lines to your `wp-config.php` file:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Step 2: Check the System Test Page

1. Go to WordPress Admin
2. Navigate to **Volunteering > System Test**
3. Review the system status indicators
4. Use the "Create/Update Volunteer Tables" button if tables are missing

### Step 3: Check Debug Logs

1. Look in `/wp-content/debug.log` for messages starting with "Make Volunteer:"
2. Check for database errors, AJAX call failures, or missing functions

### Step 4: Test the Frontend

1. Go to a page with the member sign-in block
2. Open browser developer tools (F12)
3. Look for console messages starting with "Make Volunteer:"
4. Try clicking on a profile card to see if volunteer session checking works

### Step 5: Test Volunteer Sign-In

1. Select a user and choose "Volunteering" during sign-in
2. Check debug logs for session creation messages
3. After signing in, click the same user's profile card again
4. You should see the volunteer sign-out interface

## Common Issues and Solutions

### Issue: "No active volunteer sessions found"

**Cause**: Database tables don't exist or volunteer session wasn't created
**Solution**:

1. Go to Volunteering > System Test
2. Click "Create/Update Volunteer Tables"
3. Try signing in for volunteering again

### Issue: "Security check failed" in AJAX calls

**Cause**: Nonce verification failing
**Solution**:

1. Check if `makeMember.volunteer_nonce` exists in browser console
2. Verify the nonce is being passed correctly in AJAX calls
3. Check debug logs for nonce verification messages

### Issue: Profile card clicks don't trigger volunteer checking

**Cause**: JavaScript not loading or conflicts
**Solution**:

1. Check browser console for JavaScript errors
2. Verify volunteer.js is loading after make-member-sign-in.js
3. Check if makeMember object exists in console

### Issue: Volunteer tasks not showing during sign-out

**Cause**: No volunteer tasks created or function not working
**Solution**:

1. Create some volunteer tasks in WordPress admin
2. Check if `make_get_available_volunteer_tasks` function exists
3. Verify ACF fields are properly configured for tasks

## Files Modified

### Database Functions (`inc/volunteer/volunteer-database.php`)

- Added `make_verify_volunteer_tables()` function
- Added debug logging to session creation and management
- Added automatic table verification before operations

### AJAX Handlers (`inc/volunteer/volunteer-ajax.php`)

- Added comprehensive debug logging to all AJAX endpoints
- Added detailed error reporting
- Added nonce verification logging

### Frontend JavaScript (`assets/js/volunteer.js`)

- Added console logging for all major operations
- Added debugging for profile card clicks
- Added AJAX response logging

### Sign-in Integration (`inc/scripts.php`)

- Added debug logging for volunteer session creation
- Added error logging for session start failures

### Admin Interface (`inc/volunteer/volunteer-admin.php`)

- Added "System Test" submenu page
- Created comprehensive system status checker
- Added manual troubleshooting tools

### Utility Functions (`inc/volunteer/volunteer-functions.php`)

- Added `make_test_volunteer_system()` function for comprehensive testing

## Testing Checklist

- [ ] Database tables exist (check System Test page)
- [ ] AJAX handlers are registered (check System Test page)
- [ ] Functions are available (check System Test page)
- [ ] Volunteer tasks exist (create at least one test task)
- [ ] Debug logging is enabled in WordPress
- [ ] JavaScript console shows volunteer debug messages
- [ ] Profile card clicks trigger volunteer session checking
- [ ] Volunteer sign-in creates database sessions
- [ ] Volunteer sign-out interface appears for active sessions
- [ ] Task selection works during sign-out
- [ ] Session completion is recorded in database

## Next Steps

1. **Test the fixes**: Use the System Test page to verify all components are working
2. **Create test data**: Add some volunteer tasks to test the complete workflow
3. **Monitor logs**: Watch the debug logs during testing to identify any remaining issues
4. **User testing**: Have actual users test the volunteer sign-in/sign-out process

## Support

If issues persist after following this guide:

1. Check the debug logs for specific error messages
2. Use the System Test page to identify which components are failing
3. Test each step of the volunteer workflow individually
4. Verify all required WordPress plugins (ACF) are active and working
