# Badge Management Integration - Make Member Plugin

## Overview

This document describes the complete badge management integration between the Make Member Plugin and the Mindshare Simple Events Plugin. The system allows administrators to award badges/certificates to event attendees directly from the event admin interface.

## Key Features

### 1. Automatic Badge Class Detection

- Events are automatically identified as "Badge Classes" if they have a category containing the word "badge"
- Only Badge Class events show badge management functionality

### 2. Event-Badge Association

- Each Badge Class event can be associated with a specific certificate/badge
- Badge selection is done via the "Badge Management" metabox in the event admin
- Only events with selected badges show the award/remove buttons

### 3. Unified Badge Display and Management

- Shows attendee's current badges in a readable format
- Provides award/remove functionality via AJAX buttons
- Only shows management buttons for completed orders

### 4. Real-time Updates

- AJAX-powered badge toggling without page refresh
- Immediate visual feedback on button state changes
- Automatic page refresh to show updated badge lists

## Technical Implementation

### File Structure

#### Make Member Plugin Files

- `inc/woocommerce.php` - Main badge management logic
- `assets/js/badge-management.js` - Frontend JavaScript
- `assets/css/badge-management.css` - Styling
- `inc/scripts.php` - Asset enqueuing

#### Events Plugin Integration

- Badge Management metabox for event configuration
- Attendee table column integration via filters
- Event category detection for Badge Classes

### Data Flow

1. **Event Setup**: Admin selects badge/certificate in event metabox
2. **Attendee Display**: Make Member Plugin adds badge column via filter
3. **Badge Toggle**: JavaScript sends AJAX request to toggle badge
4. **Database Update**: ACF field updated with new badge list
5. **UI Update**: Button state changes and page refreshes

### Database Schema

#### Events

- `badge_cert_id` meta field stores selected certificate ID

#### Users

- ACF `certs` field (or `certifications` for compatibility) stores array of certificate IDs

#### Certificates

- `certs` custom post type for available badges

## Configuration

### Event Setup

1. **Create Badge Class Event**

   - Add event with category containing "badge" (e.g., "Badge Class")
   - Configure event details normally

2. **Select Badge/Certificate**

   - Use "Badge Management" metabox in event admin
   - Select which certificate will be awarded
   - Save event

3. **Attendee Management**
   - Badge management appears in attendee tables
   - Only for completed orders
   - Shows current badges and award/remove buttons

### ACF Field Configuration

The system works with either ACF field name:

- `certs` (preferred)
- `certifications` (legacy compatibility)

Field should be configured as:

- Field Type: Post Object
- Post Type: certs
- Return Format: Post ID or Post Object
- Multiple: Yes

## User Interface

### Badge Display

- Current badges shown as comma-separated list
- "No badges" indicator for users without certificates
- Clean, readable format

### Management Buttons

- **Award Button**: Blue background, "Award [Badge Name]"
- **Remove Button**: Red background, "Remove [Badge Name]"
- **Processing State**: Gray background, "Processing..."
- **Disabled State**: Gray background, not clickable

### Visual States

```css
.make-attendee-badge-toggle {
  /* Normal state - blue */
  background: #0073aa;
}

.make-attendee-badge-toggle.badged {
  /* User has badge - red */
  background: #dc3232;
}

.make-attendee-badge-toggle.processing {
  /* Processing - gray */
  background: #666;
}
```

## Security Features

### AJAX Security

- Nonce verification for all requests
- User capability checks (`edit_users`)
- Input sanitization and validation

### Data Validation

- Certificate ID validation
- User ID validation
- Event context verification

## Action Hooks

### Available Hooks

```php
// Fired after badge is awarded or removed
do_action('make_after_badge_toggled', $user_id, $cert_id, $new_status, $badge_name, $event_id);
```

### Hook Parameters

- `$user_id`: ID of the user receiving/losing the badge
- `$cert_id`: ID of the certificate/badge
- `$new_status`: Boolean (true = awarded, false = removed)
- `$badge_name`: Display name of the badge
- `$event_id`: ID of the event context

## Filter Integration

### Events Plugin Filters

```php
// Add badge column to attendee tables
add_filter('mindevents_attendee_columns', function($columns) {
    $columns['make-badges'] = 'Badges';
    return $columns;
});

// Add badge data to attendee rows
add_filter('mindevents_attendee_data', function($data) {
    $data['make-badges'] = make_get_badge_management_html($data);
    return $data;
});
```

## Troubleshooting

### Common Issues

1. **Badge Buttons Not Appearing**

   - Check event category contains "badge"
   - Verify certificate is selected in Badge Management metabox
   - Ensure order status is "completed"

2. **AJAX Errors**

   - Check browser console for JavaScript errors
   - Verify nonce is being passed correctly
   - Confirm user has `edit_users` capability

3. **Badges Not Saving**

   - Verify ACF `certs` or `certifications` field exists
   - Check field is configured for `certs` post type
   - Ensure field allows multiple values

4. **Styling Issues**
   - Confirm CSS file is properly enqueued
   - Check for theme conflicts
   - Verify admin page is loading assets

### Debug Steps

1. **Check JavaScript Console**

   ```javascript
   // Look for errors in browser console
   console.log("Badge management loaded");
   ```

2. **Verify AJAX Requests**

   - Open Network tab in browser dev tools
   - Click badge button and check AJAX request
   - Verify response data

3. **Test User Capabilities**

   ```php
   // Check if current user can edit users
   if (current_user_can('edit_users')) {
       echo 'User has permission';
   }
   ```

4. **Validate ACF Fields**
   ```php
   // Check user's current certificates
   $user_certs = get_field('certs', 'user_' . $user_id);
   var_dump($user_certs);
   ```

## Performance Considerations

### Optimization Features

- Efficient AJAX operations with minimal data transfer
- Targeted page refresh instead of full reload
- Optimized database queries for badge lookup
- CSS and JS minification ready

### Caching Compatibility

- Works with standard WordPress caching
- AJAX operations bypass cache appropriately
- Database updates invalidate relevant caches

## Future Enhancements

### Planned Features

1. **Bulk Badge Operations** - Award badges to multiple users
2. **Badge History Tracking** - Log when badges were awarded/removed
3. **Email Notifications** - Notify users when badges are awarded
4. **Badge Prerequisites** - Require certain badges before others
5. **Expiration Dates** - Time-limited badges

### Integration Opportunities

1. **Reporting Dashboard** - Badge analytics and statistics
2. **Public Badge Display** - Show badges on user profiles
3. **Export Functionality** - Generate badge reports
4. **Gamification Features** - Badge achievement system

## Status

- ✅ **Complete**: Badge management fully integrated
- ✅ **Complete**: AJAX functionality working
- ✅ **Complete**: Security measures implemented
- ✅ **Complete**: Cross-plugin compatibility
- ✅ **Complete**: Documentation updated

The badge management system is now fully operational and provides a seamless experience for awarding certificates to event attendees.
