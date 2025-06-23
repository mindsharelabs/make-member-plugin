# Configuration Guide

## Admin Settings Panel

The plugin provides a comprehensive settings panel accessible through **Settings > Member Support Settings** in the WordPress admin.

### Available Settings

The plugin includes the following configurable options:

#### 1. Waiver URL

- **Field ID**: `makesf_waiver_url`
- **Type**: URL
- **Description**: The link to the waiver and release of liability
- **Usage**: Used in member sign-in processes and legal documentation

#### 2. Member Agreement URL

- **Field ID**: `makesf_agreement_url`
- **Type**: URL
- **Description**: The link to the member agreement URL
- **Usage**: Referenced during membership signup and renewal processes

#### 3. Membership Purchase URL

- **Field ID**: `makesf_membership_url`
- **Type**: URL
- **Description**: The link to purchase a membership
- **Usage**: Directs users to membership purchase page

#### 4. Badges URL

- **Field ID**: `makesf_badge_url`
- **Type**: URL
- **Description**: The link to purchase a badge class
- **Usage**: Links to badge/certification class purchasing

#### 5. Share Profile URL

- **Field ID**: `makesf_profile_share_url`
- **Type**: URL
- **Description**: The link to share a profile
- **Usage**: Enables member profile sharing functionality

## Configuration Steps

### 1. Access Settings Panel

1. Log in to WordPress admin
2. Navigate to **Settings > Member Support Settings**
3. Configure the required URLs for your Make Santa Fe installation

### 2. Required URLs Setup

**Waiver URL Configuration**

```
Example: https://yourdomain.com/waiver-form
Purpose: Legal liability waiver for members
```

**Member Agreement URL**

```
Example: https://yourdomain.com/member-agreement
Purpose: Terms and conditions for membership
```

**Membership Purchase URL**

```
Example: https://yourdomain.com/shop/membership
Purpose: WooCommerce or external membership purchase page
```

**Badges URL**

```
Example: https://yourdomain.com/shop/badges
Purpose: Badge/certification class purchase page
```

**Profile Share URL**

```
Example: https://yourdomain.com/members/profile-share
Purpose: Member profile sharing functionality
```

### 3. Save Configuration

After entering all URLs, click **Save Settings** to store the configuration.

## Plugin Constants

The plugin defines several important constants during initialization:

### File System Constants

- `MAKESF_PLUGIN_FILE`: Main plugin file path
- `MAKESF_ABSPATH`: Plugin directory absolute path
- `MAKESF_URL`: Plugin directory URL
- `PLUGIN_DIR`: Plugin directory URL (duplicate)

### Database Constants

- `SIGNIN_TABLENAME`: Database table name for sign-ins (`wp_makesignin`)

### Version Constants

- `MAKESF_PLUGIN_VERSION`: Current plugin version (1.4.0)

### AJAX Constants

- `MAKE_AJAX_PREPEND`: AJAX action prefix (`makesantafe_`)

### Branding Constants

- `MAKE_LOGO`: SVG logo markup for Make Santa Fe

## Advanced Configuration

### Custom Post Types

The plugin works with several custom post types:

**Certificates/Badges (`certs`)**

- Used for badge management and display
- Integrated with the Badge List block

**Events (`tribe_events`)**

- Integration with The Events Calendar plugin
- Used in Upcoming Events block

### WooCommerce Integration

If WooCommerce is active, the plugin provides enhanced functionality:

- Membership product integration
- Badge/certification purchasing
- Enhanced user profile features

### Database Configuration

**Sign-in Table Structure**

```sql
Table: {prefix}makesignin
- id: Auto-increment primary key
- time: Sign-in timestamp
- badges: JSON/serialized badge data
- user: WordPress user ID
```

## Environment-Specific Settings

### Development Environment

```php
// Enable debug mode
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Plugin-specific debugging
define('MAKESF_DEBUG', true);
```

### Production Environment

```php
// Optimize for production
define('WP_DEBUG', false);
define('SCRIPT_DEBUG', false);

// Enable caching
define('WP_CACHE', true);
```

## Security Considerations

### Capability Requirements

- Admin settings require `manage_options` capability
- Member sign-in features respect WordPress user roles
- AJAX endpoints include proper nonce verification

### Data Sanitization

- All URL inputs are sanitized using WordPress functions
- User input is escaped before database storage
- Output is escaped before display

## Troubleshooting Configuration

### Settings Not Saving

1. Check user permissions (`manage_options` capability)
2. Verify WordPress nonce validation
3. Check for plugin conflicts
4. Review PHP error logs

### URLs Not Working

1. Verify URL format (include http:// or https://)
2. Test URLs manually in browser
3. Check for redirect loops
4. Validate SSL certificates for HTTPS URLs

### Database Issues

1. Verify database table creation
2. Check table permissions
3. Review database error logs
4. Ensure proper character encoding (UTF-8)

## Next Steps

After configuration:

1. [Set up custom blocks](blocks/README.md)
2. [Configure member sign-in locations](blocks/member-sign-in.md)
3. [Customize styling and appearance](development.md#styling)
4. [Test functionality](troubleshooting.md#testing)
