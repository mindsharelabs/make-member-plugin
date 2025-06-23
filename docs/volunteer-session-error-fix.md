# Volunteer Session Creation Error Fix

## Error Identified

Console shows: `"Failed to create volunteer session"`

## Debugging Steps Added

### 1. Enhanced Error Messages

- Added specific error details to help identify the exact cause
- Added validation for user ID and user existence
- Added table verification before session creation

### 2. Check WordPress Debug Logs

Enable WordPress debugging and check `/wp-content/debug.log` for messages starting with "Make Volunteer:"

### 3. Common Causes and Solutions

#### Cause 1: Database Tables Missing

**Symptoms**: Error about tables not existing
**Solution**:

1. Go to Volunteering → System Test
2. Click "Create/Update Volunteer Tables"
3. Try volunteer sign-in again

#### Cause 2: Invalid User ID

**Symptoms**: Error about invalid user or user not found
**Solution**: Verify the user exists and has proper permissions

#### Cause 3: Existing Active Session

**Symptoms**: Error about user already having active session
**Solution**:

1. Go to Volunteering → Sessions in admin
2. End any active sessions for the user
3. Try again

#### Cause 4: Database Permission Issues

**Symptoms**: Database errors in debug log
**Solution**: Check WordPress database permissions

## Testing Steps

### Step 1: Enable Debug Mode

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Step 2: Test Session Creation

1. Try volunteer sign-in
2. Check browser console for detailed error
3. Check `/wp-content/debug.log` for server-side errors

### Step 3: Manual Database Check

Run this in WordPress admin or phpMyAdmin:

```sql
SHOW TABLES LIKE 'wp_volunteer_sessions';
SHOW TABLES LIKE 'wp_volunteer_schedules';
```

(Replace `wp_` with your actual database prefix)

### Step 4: Test Function Availability

Add this temporarily to your theme's `functions.php`:

```php
add_action('wp_footer', function() {
    if (current_user_can('manage_options') && isset($_GET['test_volunteer_functions'])) {
        echo '<script>';
        echo 'console.log("make_start_volunteer_session exists:", ' . (function_exists('make_start_volunteer_session') ? 'true' : 'false') . ');';
        echo 'console.log("make_verify_volunteer_tables exists:", ' . (function_exists('make_verify_volunteer_tables') ? 'true' : 'false') . ');';
        echo '</script>';
    }
});
```

Then visit your site with `?test_volunteer_functions=1`

## Expected Debug Output

When working correctly, you should see these messages in debug.log:

```
Make Volunteer: Starting volunteer session for user 123 (User Name)
Make Volunteer: Sessions table exists: Yes
Make Volunteer: Schedules table exists: Yes
Make Volunteer: Using table wp_volunteer_sessions
Make Volunteer: Created session ID 456 for user 123
Make Volunteer: Successfully started session with ID: 456
```

## Quick Fix Commands

### Force Table Recreation

Go to Volunteering → System Test and click "Create/Update Volunteer Tables"

### Clear Active Sessions (if needed)

```sql
UPDATE wp_volunteer_sessions SET status = 'completed' WHERE status = 'active';
```

### Check Table Structure

```sql
DESCRIBE wp_volunteer_sessions;
DESCRIBE wp_volunteer_schedules;
```

## Next Steps After Fixing

1. Try volunteer sign-in again
2. Check that session appears in Volunteering → Sessions admin page
3. Test the complete workflow: sign-in → sign-out with tasks
4. Verify session data is properly recorded

## If Error Persists

1. Copy the exact error message from debug.log
2. Check database permissions
3. Verify all volunteer system files are properly uploaded
4. Test with a different user account
5. Check for plugin conflicts by temporarily deactivating other plugins
