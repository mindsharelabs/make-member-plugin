# Installation Guide

## Prerequisites

Before installing the Make Santa Fe Membership Awesomeness Plugin, ensure you have:

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Advanced Custom Fields (ACF) plugin installed and activated
- WooCommerce plugin (optional, for enhanced e-commerce features)

## Installation Methods

### Method 1: Manual Installation

1. Download the plugin files from the repository
2. Upload the plugin folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. The plugin will automatically create the necessary database table upon activation

### Method 2: Git Clone (Development)

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/mindsharelabs/make-member-plugin.git
```

Then activate through the WordPress admin panel.

## Post-Installation Setup

### 1. Database Table Creation

The plugin automatically creates a `make_signin` table with the following structure:

```sql
CREATE TABLE make_signin (
  id INT NOT NULL AUTO_INCREMENT,
  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
  badges TEXT CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  user INT NOT NULL,
  PRIMARY KEY (id)
);
```

### 2. Required Plugin Dependencies

**Advanced Custom Fields (ACF)**

- The plugin heavily relies on ACF for custom blocks and field management
- Install ACF before activating this plugin
- All custom blocks are registered using ACF's `acf_register_block_type()` function

**WooCommerce (Optional)**

- Required for membership and badge purchasing features
- Enhances the plugin's e-commerce capabilities

### 3. File Permissions

Ensure proper file permissions for:

- Plugin directory: `755`
- PHP files: `644`
- Asset files (CSS/JS): `644`

### 4. Asset Compilation (Development)

If you're setting up for development:

```bash
# Install Node.js dependencies
npm install

# Compile SASS files
gulp sass

# Watch for changes during development
gulp watch
```

## Verification

After installation, verify the plugin is working:

1. Check that "Make Santa Fe Membership Awesomeness" appears in your active plugins list
2. Navigate to Settings > Member Support Settings to access configuration
3. In the Gutenberg editor, look for the "MAKE Santa Fe Blocks" category
4. Verify that custom blocks are available for use

## Troubleshooting Installation

### Common Issues

**Plugin doesn't activate**

- Ensure ACF is installed and activated first
- Check PHP error logs for specific error messages
- Verify WordPress and PHP version requirements

**Custom blocks don't appear**

- Confirm ACF is properly installed
- Clear any caching plugins
- Check browser console for JavaScript errors

**Database table not created**

- Check database user permissions
- Verify WordPress database connection
- Manually run the installation function if needed

### Manual Database Table Creation

If the table isn't created automatically:

```sql
CREATE TABLE wp_make_signin (
  id INT NOT NULL AUTO_INCREMENT,
  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
  badges TEXT CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  user INT NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Replace `wp_` with your WordPress table prefix.

## Next Steps

After successful installation:

1. [Configure the plugin settings](configuration.md)
2. [Learn about available custom blocks](blocks/README.md)
3. [Set up member sign-in locations](blocks/member-sign-in.md)
4. [Customize the appearance](development.md#styling)

## Uninstallation

To completely remove the plugin:

1. Deactivate the plugin
2. Delete plugin files from `/wp-content/plugins/`
3. Manually remove the `make_signin` database table if desired
4. Clean up any custom options from the `wp_options` table

**Note**: Uninstalling will remove all sign-in data and block configurations. Export any important data before uninstalling.
