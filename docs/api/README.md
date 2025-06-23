# API Reference

The Make Santa Fe Membership Plugin provides both REST API endpoints and AJAX endpoints for various functionality. This document covers all available APIs and their usage.

## REST API Endpoints

### Members Endpoint

**Endpoint**: `/wp-json/make/members`  
**Methods**: `GET`, `POST`, `PUT`, `DELETE` (all methods allowed)  
**Permission**: Public (no authentication required)

#### Description

Retrieves a list of all active members with their membership and subscription information.

#### Response Format

```json
{
  "success": true,
  "data": [
    {
      "ID": 123,
      "name": "John Doe",
      "memberships": "Premium Member",
      "subscriptions": "Monthly Membership | ",
      "total": "50.00",
      "image": "https://example.com/avatar.jpg"
    }
  ]
}
```

#### Response Fields

| Field           | Type           | Description                                  |
| --------------- | -------------- | -------------------------------------------- |
| `ID`            | Integer        | WordPress user ID                            |
| `name`          | String         | User's display name                          |
| `memberships`   | String/Boolean | Active membership plan names (false if none) |
| `subscriptions` | String/Boolean | Active subscription names (false if none)    |
| `total`         | String/Boolean | Subscription total amount (false if none)    |
| `image`         | String         | Avatar URL (400px size)                      |

#### Example Usage

```javascript
// Fetch all active members
fetch("/wp-json/make/members")
  .then((response) => response.json())
  .then((data) => {
    console.log("Active members:", data.data);
  });
```

```php
// PHP example using WordPress HTTP API
$response = wp_remote_get(home_url('/wp-json/make/members'));
$members = json_decode(wp_remote_retrieve_body($response), true);
```

## AJAX Endpoints

All AJAX endpoints use the WordPress AJAX system with the action prefix `makesantafe_`.

### 1. Get All Members

**Action**: `makeAllGetMembers`  
**Method**: POST  
**Authentication**: WordPress nonce required

#### Description

Loads all active members for the sign-in interface.

#### Request Parameters

None required.

#### Response

```json
{
  "success": true,
  "data": {
    "html": "<div class='member-list'>...</div>",
    "status": "success"
  }
}
```

#### JavaScript Usage

```javascript
$.ajax({
  url: makeMember.ajax_url,
  type: "post",
  data: {
    action: "makeAllGetMembers",
  },
  success: function (response) {
    $("#memberList").html(response.data.html);
  },
});
```

### 2. Get Member Details

**Action**: `makeGetMember`  
**Method**: POST  
**Authentication**: WordPress nonce required

#### Description

Retrieves detailed information for a specific member including available badges.

#### Request Parameters

| Parameter   | Type    | Required | Description        |
| ----------- | ------- | -------- | ------------------ |
| `userID`    | Integer | Yes\*    | WordPress user ID  |
| `userEmail` | String  | Yes\*    | User email address |

\*Either `userID` or `userEmail` is required.

#### Response

```json
{
  "success": true,
  "data": {
    "status": "userfound",
    "html": "<div class='member-profile'>...</div>"
  }
}
```

#### JavaScript Usage

```javascript
$.ajax({
  url: makeMember.ajax_url,
  type: "post",
  data: {
    action: "makeGetMember",
    userID: 123,
  },
  success: function (response) {
    if (response.data.status === "userfound") {
      $("#result").html(response.data.html);
    }
  },
});
```

### 3. Member Sign-In

**Action**: `makeMemberSignIn`  
**Method**: POST  
**Authentication**: WordPress nonce required

#### Description

Records a member sign-in with selected badges/activities.

#### Request Parameters

| Parameter | Type    | Required | Description                 |
| --------- | ------- | -------- | --------------------------- |
| `userID`  | Integer | Yes      | WordPress user ID           |
| `badges`  | Array   | Yes      | Array of badge/activity IDs |

#### Response

```json
{
  "success": true,
  "data": {
    "status": "signin_complete",
    "html": "<div class='success-message'>...</div>"
  }
}
```

#### JavaScript Usage

```javascript
var badges = [123, 456, 789]; // Selected badge IDs
$.ajax({
  url: makeMember.ajax_url,
  type: "post",
  data: {
    action: "makeMemberSignIn",
    userID: 123,
    badges: badges,
  },
  success: function (response) {
    console.log("Sign-in recorded:", response.data);
  },
});
```

## Utility Functions

### Member Functions

#### `make_get_active_members()`

Returns all active members as database result objects.

```php
$members = make_get_active_members();
foreach($members as $member) {
  $user = get_user_by('ID', $member->user_id);
  echo $user->display_name;
}
```

#### `make_get_active_members_array()`

Returns active member user IDs as a simple array.

```php
$member_ids = make_get_active_members_array();
// Returns: [123, 456, 789, ...]
```

#### `make_output_member_card($maker, $echo, $args)`

Generates HTML for a member card display.

**Parameters**:

- `$maker` (int|object): User ID or user object
- `$echo` (bool): Whether to echo output (default: false)
- `$args` (array): Display options

**Arguments**:

```php
$args = array(
  'show_badges' => true,    // Show member badges
  'show_title' => true,     // Show member title
  'show_bio' => false,      // Show member bio
  'show_gallery' => false,  // Show image gallery
  'show_photo' => true,     // Show profile photo
);
```

**Usage**:

```php
// Return HTML
$html = make_output_member_card(123, false, $args);

// Echo directly
make_output_member_card(123, true, $args);
```

### Event Functions

#### `make_get_upcoming_events($num, $ticketed, $args, $page, $upcoming_events)`

Retrieves upcoming events with optional ticket filtering.

**Parameters**:

- `$num` (int): Number of events to return (default: 3)
- `$ticketed` (bool): Only return events with available tickets (default: true)
- `$args` (array): WP_Query arguments
- `$page` (int): Page number for pagination (default: 1)
- `$upcoming_events` (array): Existing events array for recursion

**Returns**: Array of event IDs and titles

```php
// Get 5 upcoming events with tickets
$events = make_get_upcoming_events(5, true);

// Get all upcoming events regardless of tickets
$all_events = make_get_upcoming_events(10, false);
```

### Debug Functions

#### `mapi_var_dump($var)`

Debug function for administrators only.

```php
// Only displays for administrators
mapi_var_dump($some_variable);
```

#### `mapi_write_log($message)`

Writes to WordPress debug log when WP_DEBUG is enabled.

```php
mapi_write_log('Debug message');
mapi_write_log($array_or_object);
```

## Authentication and Security

### AJAX Security

All AJAX endpoints should include WordPress nonces for security:

```javascript
// Include nonce in AJAX requests
$.ajax({
  url: makeMember.ajax_url,
  type: "post",
  data: {
    action: "makeGetMember",
    userID: 123,
    _wpnonce: makeMember.nonce, // Include nonce
  },
});
```

### Permission Checks

- REST API endpoints are currently public
- AJAX endpoints require valid WordPress session
- Admin functions require appropriate capabilities

### Data Sanitization

All input data is sanitized using WordPress functions:

- `sanitize_text_field()`
- `absint()` for integers
- `sanitize_email()` for email addresses

## Error Handling

### Common Error Responses

#### User Not Found

```json
{
  "success": false,
  "data": {
    "status": "user_not_found",
    "message": "Member not found"
  }
}
```

#### Invalid Parameters

```json
{
  "success": false,
  "data": {
    "status": "invalid_params",
    "message": "Required parameters missing"
  }
}
```

#### Database Error

```json
{
  "success": false,
  "data": {
    "status": "database_error",
    "message": "Unable to save sign-in data"
  }
}
```

### Error Handling Best Practices

```javascript
$.ajax({
  url: makeMember.ajax_url,
  type: "post",
  data: {
    action: "makeGetMember",
    userID: 123,
  },
  success: function (response) {
    if (response.success) {
      // Handle success
      console.log(response.data);
    } else {
      // Handle API-level errors
      console.error("API Error:", response.data.message);
    }
  },
  error: function (xhr, status, error) {
    // Handle HTTP-level errors
    console.error("HTTP Error:", error);
  },
});
```

## Rate Limiting and Performance

### Caching Strategies

- Member lists are cached for performance
- Database queries use proper indexing
- AJAX responses are optimized for size

### Performance Considerations

- Limit member list queries with LIMIT clauses
- Use pagination for large datasets
- Implement client-side caching where appropriate

## Integration Examples

### Custom Theme Integration

```php
// In your theme's functions.php
function custom_member_display() {
  $members = make_get_active_members_array();

  foreach($members as $member_id) {
    echo make_output_member_card($member_id, false, array(
      'show_badges' => true,
      'show_bio' => true
    ));
  }
}
```

### JavaScript Integration

```javascript
// Custom member search functionality
function searchMembers(query) {
  return fetch("/wp-json/make/members")
    .then((response) => response.json())
    .then((data) => {
      return data.data.filter((member) =>
        member.name.toLowerCase().includes(query.toLowerCase())
      );
    });
}
```

## Extending the API

### Adding Custom Endpoints

```php
// Add custom REST endpoint
add_action('rest_api_init', function() {
  register_rest_route('make', '/custom-endpoint', array(
    'methods' => 'GET',
    'callback' => 'custom_endpoint_callback',
    'permission_callback' => '__return_true'
  ));
});

function custom_endpoint_callback($request) {
  // Custom logic here
  return wp_send_json_success($data);
}
```

### Adding Custom AJAX Actions

```php
// Add custom AJAX action
add_action('wp_ajax_custom_make_action', 'handle_custom_action');
add_action('wp_ajax_nopriv_custom_make_action', 'handle_custom_action');

function handle_custom_action() {
  // Verify nonce
  check_ajax_referer('make_nonce', 'nonce');

  // Custom logic here
  wp_send_json_success($response_data);
}
```

## Next Steps

- [Database Schema](../database.md)
- [Custom Blocks](../blocks/README.md)
- [Development Guide](../development.md)
- [Troubleshooting](../troubleshooting.md)
