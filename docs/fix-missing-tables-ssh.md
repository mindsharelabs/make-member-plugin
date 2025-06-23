# Fix Missing Volunteer Tables via SSH

## Issue Identified ✅

Debug log shows: `Table 'rzknzaagjv.wp_volunteer_sessions' doesn't exist`

The volunteer database tables were never created during plugin activation.

## Quick Fix via SSH

### Option 1: Use WordPress Admin (Easiest)

```bash
# Go to your WordPress admin in browser:
# https://staging.makesantafe.org/wp-admin/admin.php?page=volunteer-test
# Click "Create/Update Volunteer Tables" button
```

### Option 2: Direct Database Creation via SSH

**Step 1: Connect to MySQL**

```bash
# Find your database credentials in wp-config.php
grep -E "DB_NAME|DB_USER|DB_PASSWORD|DB_HOST" /home/1151261.cloudwaysapps.com/rzknzaagjv/public_html/wp-config.php

# Connect to MySQL (replace with your actual credentials)
mysql -h localhost -u your_db_user -p your_db_name
```

**Step 2: Create the Tables**

```sql
-- Create volunteer_sessions table
CREATE TABLE wp_volunteer_sessions (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    signin_time DATETIME NOT NULL,
    signout_time DATETIME NULL,
    duration_minutes INT NULL,
    tasks_completed TEXT NULL,
    notes TEXT NULL,
    status ENUM('active', 'completed') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_id (user_id),
    INDEX idx_signin_time (signin_time),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Create volunteer_schedules table
CREATE TABLE wp_volunteer_schedules (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_id (user_id),
    INDEX idx_day_of_week (day_of_week),
    INDEX idx_is_active (is_active),
    UNIQUE KEY unique_user_day_time (user_id, day_of_week, start_time)
);

-- Verify tables were created
SHOW TABLES LIKE 'wp_volunteer_%';

-- Exit MySQL
EXIT;
```

### Option 3: Force Plugin Reactivation

```bash
# Navigate to WordPress directory
cd /home/1151261.cloudwaysapps.com/rzknzaagjv/public_html

# Use WP-CLI to reactivate the plugin (if available)
wp plugin deactivate makesf-members
wp plugin activate makesf-members

# OR manually trigger the activation hook via PHP
php -r "
define('ABSPATH', '/home/1151261.cloudwaysapps.com/rzknzaagjv/public_html/');
require_once ABSPATH . 'wp-config.php';
require_once ABSPATH . 'wp-includes/wp-db.php';
require_once ABSPATH . 'wp-content/plugins/makesf-members/makesf-members.php';
make_install();
echo 'Plugin activation function executed\n';
"
```

## Verify the Fix

**Check if tables exist:**

```bash
mysql -h localhost -u your_db_user -p your_db_name -e "SHOW TABLES LIKE 'wp_volunteer_%';"
```

**Expected output:**

```
+----------------------------------+
| Tables_in_rzknzaagjv (wp_volunteer_%) |
+----------------------------------+
| wp_volunteer_schedules           |
| wp_volunteer_sessions            |
+----------------------------------+
```

## Test the Volunteer System

1. **Try volunteer sign-in again** on your website
2. **Check debug log** for success messages:

   ```bash
   tail -f /home/1151261.cloudwaysapps.com/rzknzaagjv/public_html/wp-content/debug.log | grep "Make Volunteer"
   ```

3. **Expected success messages:**
   ```
   Make Volunteer: Starting volunteer session for user 54646
   Make Volunteer: Sessions table exists: Yes
   Make Volunteer: Schedules table exists: Yes
   Make Volunteer: Created session ID 1 for user 54646
   ```

## Why This Happened

The plugin activation hook `make_install()` didn't run properly when the plugin was first activated, so the database tables were never created. This is common when:

- Plugin was uploaded manually instead of activated through WordPress admin
- Database permissions were insufficient during activation
- PHP errors occurred during activation
- Plugin was activated before all files were uploaded

## Prevention

To prevent this in the future, the volunteer system now includes automatic table verification and creation, so this should not happen again.
