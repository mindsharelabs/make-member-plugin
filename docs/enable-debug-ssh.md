# Enable WordPress Debug Mode via SSH

## Step 1: Locate wp-config.php

```bash
# Find your WordPress installation directory
find /var/www -name "wp-config.php" 2>/dev/null
# OR if you know the path:
ls -la /var/www/html/wp-config.php
# OR for staging sites:
ls -la /var/www/staging/wp-config.php
```

## Step 2: Backup wp-config.php

```bash
# Replace with your actual path
cp /var/www/html/wp-config.php /var/www/html/wp-config.php.backup
```

## Step 3: Edit wp-config.php

```bash
# Use nano editor (easier for beginners)
nano /var/www/html/wp-config.php

# OR use vim if you prefer
vim /var/www/html/wp-config.php
```

## Step 4: Add Debug Lines

Look for this line in wp-config.php:

```php
define( 'WP_DEBUG', false );
```

**Replace it with these lines:**

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

**If WP_DEBUG doesn't exist, add these lines before the line that says:**

```php
/* That's all, stop editing! Happy publishing. */
```

## Step 5: Save and Exit

**For nano:**

- Press `Ctrl + X`
- Press `Y` to confirm
- Press `Enter` to save

**For vim:**

- Press `Esc`
- Type `:wq`
- Press `Enter`

## Step 6: Check Debug Log Location

```bash
# Debug log will be created at:
ls -la /var/www/html/wp-content/debug.log

# If it doesn't exist yet, create the directory with proper permissions:
mkdir -p /var/www/html/wp-content
chmod 755 /var/www/html/wp-content
```

## Step 7: Test and Monitor

```bash
# Try the volunteer sign-in on your website, then check the log:
tail -f /var/www/html/wp-content/debug.log

# OR view the last 50 lines:
tail -50 /var/www/html/wp-content/debug.log

# OR search for volunteer-specific messages:
grep "Make Volunteer" /var/www/html/wp-content/debug.log
```

## Quick One-Liner Commands

**Enable debug (replace path as needed):**

```bash
# Backup and enable debug in one go:
cp /var/www/html/wp-config.php /var/www/html/wp-config.php.backup && \
sed -i "s/define( 'WP_DEBUG', false );/define( 'WP_DEBUG', true );\ndefine( 'WP_DEBUG_LOG', true );\ndefine( 'WP_DEBUG_DISPLAY', false );/" /var/www/html/wp-config.php
```

**Monitor debug log in real-time:**

```bash
tail -f /var/www/html/wp-content/debug.log | grep "Make Volunteer"
```

## After Testing

**To disable debug later:**

```bash
# Restore backup
cp /var/www/html/wp-config.php.backup /var/www/html/wp-config.php

# OR manually change back to:
# define( 'WP_DEBUG', false );
```

## Common Paths by Server Setup

**Standard Apache/Nginx:**

- `/var/www/html/wp-config.php`

**cPanel/Shared Hosting:**

- `/home/username/public_html/wp-config.php`

**WordPress Multisite:**

- Same as above, but check for network-specific configs

**Staging Sites:**

- `/var/www/staging/wp-config.php`
- `/var/www/staging.domain.com/wp-config.php`

## Troubleshooting

**If you can't find wp-config.php:**

```bash
find / -name "wp-config.php" 2>/dev/null | head -5
```

**If you get permission denied:**

```bash
sudo nano /var/www/html/wp-config.php
```

**If debug.log doesn't appear:**

```bash
# Check WordPress can write to wp-content:
ls -la /var/www/html/wp-content/
# Should show www-data or apache as owner

# Fix permissions if needed:
sudo chown -R www-data:www-data /var/www/html/wp-content/
sudo chmod 755 /var/www/html/wp-content/
```
