# Make Member Sign In Block

The Make Member Sign In block is the core functionality of the plugin, providing a comprehensive member check-in system for Make Santa Fe. This block allows members to sign in, select badges/activities they're using, and track their visits.

## Overview

**Block Name**: `make-member-sign-in`  
**Template**: `inc/templates/make-member-sign-in.php`  
**JavaScript**: `assets/js/make-member-sign-in.js`  
**Dependencies**: jQuery, List.js

## Features

- **Member Search**: Real-time search through member list
- **Badge Selection**: Members can select badges/certifications they're using
- **Activity Tracking**: Track specific activities during visits
- **Visit Logging**: Automatic logging of sign-ins to database
- **User Profiles**: Display member information and certifications
- **AJAX Interface**: Smooth, no-reload user experience

## Block Configuration

### Basic Settings

The block has minimal configuration options and works primarily through its interactive interface:

- **Block Alignment**: Full width by default
- **Multiple Instances**: Only one instance per page allowed
- **Edit Mode**: Default editing mode in Gutenberg

### CSS Classes

- **Container**: `.make-member-sign-in`
- **Main Interface**: `#MAKEMemberSignIn`
- **Member List**: `#memberList`
- **Results Area**: `#result`

## User Interface Components

### 1. Member List Display

The block loads all members in a searchable list format:

```javascript
// Member list initialization
const memberList = new List("member-list", {
  valueNames: ["email", "name"],
  searchClass: "member-search",
});
```

**Features**:

- Real-time search by name or email
- Profile cards for each member
- Click-to-select functionality

### 2. Member Profile View

When a member is selected, the interface displays:

- Member name and information
- Available badges/certifications
- Activity options
- Sign-in button

### 3. Badge/Activity Selection

Members can select multiple badges or activities:

- Visual selection indicators
- Disabled state for unavailable badges
- Dynamic button enabling based on selections

## AJAX Endpoints

The block uses three main AJAX actions:

### 1. Load All Members

**Action**: `makeAllGetMembers`

- **Purpose**: Retrieve and display all members
- **Response**: HTML for member list interface

### 2. Get Member Details

**Action**: `makeGetMember`

- **Parameters**:
  - `userID`: WordPress user ID
  - `userEmail`: User email (alternative identifier)
- **Purpose**: Load individual member profile and badges
- **Response**: Member profile HTML with badge options

### 3. Process Sign-In

**Action**: `makeMemberSignIn`

- **Parameters**:
  - `userID`: WordPress user ID
  - `badges`: Array of selected badge IDs
- **Purpose**: Record member sign-in with selected badges
- **Response**: Confirmation message and success status

## Database Integration

### Sign-In Records

Each sign-in creates a record in the `make_signin` table:

```sql
INSERT INTO make_signin (time, badges, user)
VALUES (NOW(), '[badge_ids]', user_id)
```

**Fields**:

- `time`: Timestamp of sign-in
- `badges`: JSON/serialized array of badge IDs
- `user`: WordPress user ID

### Data Flow

1. Member selects their profile
2. System loads their available badges
3. Member selects badges/activities
4. Sign-in is recorded with timestamp and badge data
5. Confirmation is displayed
6. Interface resets for next member

## JavaScript Functionality

### Core Functions

#### `loadMembers(callback)`

- Loads all members via AJAX
- Initializes List.js for search functionality
- Populates member list interface

#### `submitUser(userID, userEmail)`

- Retrieves individual member data
- Displays member profile and badge options
- Handles loading states and error conditions

#### Event Handlers

**Profile Card Click**:

```javascript
$(document).on("click", ".profile-card", function () {
  var userID = $(this).data("user");
  submitUser(userID);
});
```

**Badge Selection**:

```javascript
$(document).on(
  "click",
  ".badge-item:not(.not-allowed), .activity-item",
  function () {
    $(this).toggleClass("selected");
    // Enable/disable sign-in button based on selections
  }
);
```

**Sign-In Completion**:

```javascript
$(document).on("click", ".sign-in-done", function () {
  // Collect selected badges and submit sign-in
});
```

## Styling and Layout

### CSS Structure

```css
.make-member-sign-in {
  /* Main container styles */
}

#MAKEMemberSignIn {
  /* Interface container */
}

.profile-card {
  /* Member profile card styling */
}

.badge-item {
  /* Badge selection styling */
}

.badge-item.selected {
  /* Selected badge styling */
}

.badge-item.not-allowed {
  /* Disabled badge styling */
}
```

### Responsive Design

The block is designed to work across all device sizes:

- Mobile-first approach
- Touch-friendly interface elements
- Responsive grid layouts for member cards

## Integration Points

### WordPress Users

- Integrates with WordPress user system
- Respects user roles and capabilities
- Uses WordPress user meta for additional data

### Custom Post Types

- **Badges/Certifications**: Links to `certs` post type
- **Activities**: May integrate with custom activity types

### WooCommerce

If WooCommerce is active:

- Links to membership products
- Integrates with badge purchase history
- Enhanced user profile data

## Security Considerations

### AJAX Security

- All AJAX requests include WordPress nonces
- User capability checks on server side
- Input sanitization and validation

### Data Protection

- Member data is handled according to WordPress standards
- No sensitive information exposed in frontend
- Proper escaping of output data

## Customization Options

### Template Override

Copy template to theme for customization:

```
/wp-content/themes/your-theme/make-member-plugin/templates/make-member-sign-in.php
```

### JavaScript Hooks

Extend functionality with custom JavaScript:

```javascript
// After member selection
$(document).on("member_selected", function (event, userID) {
  // Custom functionality
});

// After sign-in completion
$(document).on("signin_complete", function (event, data) {
  // Custom post-signin actions
});
```

### CSS Customization

Override default styles:

```css
.make-member-sign-in .profile-card {
  /* Custom profile card styling */
}

.make-member-sign-in .badge-item.selected {
  /* Custom selected badge styling */
}
```

## Troubleshooting

### Common Issues

**Members not loading**:

- Check AJAX endpoint configuration
- Verify user permissions
- Review browser console for errors

**Badge selection not working**:

- Ensure JavaScript files are loaded
- Check for jQuery conflicts
- Verify badge data structure

**Sign-in not recording**:

- Check database table structure
- Verify AJAX action handlers
- Review server error logs

### Debug Mode

Enable debug logging:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Plugin-specific debugging
define('MAKESF_DEBUG', true);
```

## Performance Considerations

### Optimization Tips

- Member list is cached for performance
- AJAX responses are optimized for size
- Database queries use proper indexing
- Assets are minified in production

### Monitoring

- Track sign-in frequency and patterns
- Monitor AJAX response times
- Review database performance
- Analyze user interaction patterns

## Next Steps

- [Configure badge management](badge-list.md)
- [Set up member profiles](../api/member-profiles.md)
- [Customize appearance](../development.md#styling)
- [Monitor usage statistics](../api/statistics.md)
