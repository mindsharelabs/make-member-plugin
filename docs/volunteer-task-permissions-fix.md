# Volunteer Task Creation Permissions Fix

## Issue Identified ✅

Error: "Sorry, you are not allowed to edit this post" when trying to create volunteer tasks.

## Root Cause

The volunteer task custom post type was configured with overly restrictive permissions that only allowed users with `manage_options` capability (administrators only) to create tasks.

## Fix Applied

### 1. Updated Custom Post Type Capabilities

Changed from hardcoded `manage_options` requirement to proper WordPress capability mapping:

**Before:**

```php
'capabilities' => array(
    'create_posts' => 'manage_options',
    'edit_posts' => 'manage_options',
    // ... all requiring manage_options
),
```

**After:**

```php
'capability_type' => 'volunteer_task',
'map_meta_cap' => true,
'capabilities' => array(
    'edit_post' => 'edit_volunteer_task',
    'create_posts' => 'edit_volunteer_tasks',
    // ... proper capability mapping
),
```

### 2. Added Capability Assignment Function

Created `make_add_volunteer_task_capabilities()` that grants volunteer task permissions to:

- **Administrators** - Full access to create, edit, delete volunteer tasks
- **Editors** - Full access to create, edit, delete volunteer tasks

### 3. Automatic Capability Assignment

- Capabilities are added during plugin activation
- Capabilities are also added on `init` hook to ensure they exist
- Manual button added to System Test page for troubleshooting

## How to Fix

### Option 1: Use System Test Page (Recommended)

1. Go to **Volunteering → System Test** in WordPress admin
2. Click **"Add Task Creation Permissions"** button
3. Try creating a volunteer task again

### Option 2: Manual Role Assignment (if needed)

If you need to grant permissions to other roles:

```php
// Add to your theme's functions.php temporarily
add_action('init', function() {
    $role = get_role('your_role_name'); // e.g., 'author', 'contributor'
    if ($role) {
        $role->add_cap('edit_volunteer_tasks');
        $role->add_cap('edit_volunteer_task');
        $role->add_cap('publish_volunteer_tasks');
        $role->add_cap('delete_volunteer_tasks');
    }
});
```

### Option 3: Database Direct Fix (Advanced)

If the above doesn't work, you can directly update user capabilities in the database, but this is not recommended unless you're experienced with WordPress database structure.

## Verification

After applying the fix:

1. **Test Task Creation:**

   - Go to **Volunteering → Tasks**
   - Click **"Add New Task"**
   - Fill out the form and click **"Publish"**
   - Task should be created successfully

2. **Check User Capabilities:**
   ```php
   // Add this to test current user capabilities
   if (current_user_can('edit_volunteer_tasks')) {
       echo "✅ User can create volunteer tasks";
   } else {
       echo "❌ User cannot create volunteer tasks";
   }
   ```

## User Roles That Can Create Tasks

After the fix, these roles can create volunteer tasks:

- **Administrator** ✅ (full access)
- **Editor** ✅ (full access)
- **Author** ❌ (no access by default)
- **Contributor** ❌ (no access by default)
- **Subscriber** ❌ (no access by default)

## Troubleshooting

### If task creation still fails:

1. **Check user role:**

   ```php
   $user = wp_get_current_user();
   echo "Current user role: " . implode(', ', $user->roles);
   ```

2. **Check specific capabilities:**

   ```php
   $caps_to_check = ['edit_volunteer_tasks', 'publish_volunteer_tasks', 'edit_volunteer_task'];
   foreach ($caps_to_check as $cap) {
       echo $cap . ': ' . (current_user_can($cap) ? 'Yes' : 'No') . "\n";
   }
   ```

3. **Reset capabilities:**

   - Go to Volunteering → System Test
   - Click "Add Task Creation Permissions" again

4. **Check for plugin conflicts:**
   - Temporarily deactivate other permission/role management plugins
   - Test task creation
   - Reactivate plugins one by one to identify conflicts

## Prevention

This fix ensures that:

- Proper WordPress capability system is used
- Capabilities are automatically assigned during plugin activation
- Manual fix option is available through admin interface
- Future plugin updates won't break task creation permissions

The volunteer system now follows WordPress best practices for custom post type permissions.
