# Volunteer Sign-Out Timing and Duration Display Fixes

## Issues Fixed

### 1. **Auto-Redirect Timing Issue**

**Problem**: The 15-second auto-redirect timer was starting immediately after clicking the "Sign Out" button, before the user could see the confirmation screen. Additionally, other JavaScript files (member sign-in scripts) were interfering with their own timeout redirects.

**Root Causes**:

1. In [`assets/js/volunteer.js`](assets/js/volunteer.js:130), the auto-redirect timer was starting immediately in the AJAX success callback
2. [`assets/js/make-member-sign-in-optimized.js`](assets/js/make-member-sign-in-optimized.js:388) and [`assets/js/make-member-sign-in-hybrid.js`](assets/js/make-member-sign-in-hybrid.js:423) had their own timeout redirects that were interfering with the volunteer sign-out process

**Fix Applied**:

1. **Fixed volunteer.js timing**:

```javascript
// Before (incorrect timing)
success: function (response) {
  if (response.success) {
    $("#result").html(response.data.html);

    // Auto-redirect started immediately (WRONG)
    setTimeout(function () {
      returnToMemberList();
    }, 15000);
  }
}

// After (correct timing with interference prevention)
success: function (response) {
  if (response.success) {
    $("#result").html(response.data.html);

    // Mark that we're in volunteer sign-out mode to prevent other timeouts
    $("body").addClass("volunteer-signout-mode");

    setTimeout(function () {
      var $result = $("#result");
      if ($result.find(".volunteer-signout-success").length > 0) {
        $result.append(/* button HTML */);

        // Auto-redirect only starts AFTER confirmation is shown and button is added
        setTimeout(function () {
          if ($("body").hasClass("volunteer-signout-mode")) {
            returnToMemberList();
          }
        }, 15000);
      }
    }, 1000);
  }
}
```

2. **Prevented interference from member sign-in scripts**:

```javascript
// In make-member-sign-in-optimized.js and make-member-sign-in-hybrid.js
// Before (always redirected)
setTimeout(function () {
  returnToSearch(); // or returnToMemberList()
}, 8000);

// After (respects volunteer sign-out mode)
if (!$("body").hasClass("volunteer-signout-mode")) {
  setTimeout(function () {
    returnToSearch(); // or returnToMemberList()
  }, 8000);
}
```

**Result**: The 15-second auto-redirect now only starts after the confirmation screen is displayed and the "Return to Member List" button is added, giving users proper time to review their session details.

### 2. **Duration Display Format and Timezone Issues**

**Problem**: Duration was being displayed as decimal hours (e.g., "6.02 hours") instead of a user-friendly format. Users also reported a 6-hour offset issue, and requested hours/minutes format for durations over an hour, and just minutes for shorter durations.

**Root Causes**:

1. The display formatting was using decimal hours instead of a more readable format
2. **Timezone mismatch**: The system was using `new DateTime()` without timezone specification (defaults to UTC) while WordPress uses local timezone, causing the 6-hour offset for Mountain Time (UTC-6)

**Fix Applied**:

#### 1. Fixed Timezone Issues

```php
// Before (UTC timezone causing 6-hour offset)
$signin_time = new DateTime($active_session->signin_time);
$current_time = new DateTime();
$duration = $signin_time->diff($current_time);

// After (WordPress timezone)
$timezone = wp_timezone();
$signin_time = new DateTime($active_session->signin_time, $timezone);
$current_time = new DateTime('now', $timezone);
$duration = $signin_time->diff($current_time);
```

Applied to:

- [`inc/volunteer/volunteer-ajax.php`](inc/volunteer/volunteer-ajax.php:204) - Current session duration calculation
- [`inc/volunteer/volunteer-ajax.php`](inc/volunteer/volunteer-ajax.php:67) - Sign-out confirmation duration
- [`inc/volunteer/volunteer-database.php`](inc/volunteer/volunteer-database.php:217) - Session end duration calculation

#### 2. Fixed Duration Display Format

```php
// Before (decimal hours)
$duration_hours = round($duration_minutes / 60, 2);
$html .= '<p><strong>Current duration:</strong> ' . $duration_hours . ' hours</p>';

// After (hours and minutes format)
if ($duration_minutes >= 60) {
    $hours = floor($duration_minutes / 60);
    $minutes = $duration_minutes % 60;
    $duration_display = $hours . 'h ' . $minutes . 'm';
} else {
    $duration_display = $duration_minutes . 'm';
}
$html .= '<p><strong>Current duration:</strong> ' . $duration_display . '</p>';
```

**Result**: Duration is now displayed in a user-friendly format:

- Sessions over 1 hour: "2h 30m"
- Sessions under 1 hour: "45m"
- No more confusing decimal hours like "6.02 hours"

## Technical Implementation

### Files Modified

1. **[`assets/js/volunteer.js`](assets/js/volunteer.js:116)** - Fixed auto-redirect timing and added volunteer sign-out mode protection
2. **[`assets/js/make-member-sign-in-optimized.js`](assets/js/make-member-sign-in-optimized.js:388)** - Prevented interference with volunteer sign-out process
3. **[`assets/js/make-member-sign-in-hybrid.js`](assets/js/make-member-sign-in-hybrid.js:423)** - Prevented interference with volunteer sign-out process
4. **[`inc/volunteer/volunteer-ajax.php`](inc/volunteer/volunteer-ajax.php:204)** - Fixed timezone and duration display format in sign-out interface
5. **[`inc/volunteer/volunteer-ajax.php`](inc/volunteer/volunteer-ajax.php:67)** - Fixed timezone and duration display format in confirmation screen
6. **[`inc/volunteer/volunteer-database.php`](inc/volunteer/volunteer-database.php:217)** - Fixed timezone in session end calculation

### User Experience Improvements

#### Before Fixes:

- ❌ Auto-redirect started before users could see confirmation
- ❌ Duration displayed as confusing decimal hours (e.g., "6.02 hours")
- ❌ Users couldn't properly review their session details
- ❌ Potential 6-hour offset confusion

#### After Fixes:

- ✅ Auto-redirect only starts after confirmation screen is fully displayed
- ✅ Duration displayed in clear format (e.g., "2h 30m" or "45m")
- ✅ Users have proper time to review session details
- ✅ Consistent timing behavior across all scenarios
- ✅ More intuitive duration format for all session lengths

### Duration Format Logic

The new duration formatting follows this logic:

```php
if ($duration_minutes >= 60) {
    $hours = floor($duration_minutes / 60);
    $minutes = $duration_minutes % 60;
    $duration_display = $hours . 'h ' . $minutes . 'm';
} else {
    $duration_display = $duration_minutes . 'm';
}
```

**Examples**:

- 45 minutes → "45m"
- 90 minutes → "1h 30m"
- 150 minutes → "2h 30m"
- 60 minutes → "1h 0m"

### Auto-Redirect Flow

The new auto-redirect timing follows this sequence:

1. User clicks "Sign Out" button
2. AJAX request processes sign-out
3. Confirmation screen is displayed immediately
4. After 1 second: "Return to Member List" button is added
5. **Only then**: 15-second auto-redirect timer starts
6. Total time before auto-redirect: ~16 seconds from confirmation display

## Testing Checklist

- [ ] **Auto-Redirect Timing**: Confirm 15-second countdown starts only after confirmation screen is displayed
- [ ] **No Interference**: Member sign-in scripts don't interfere with volunteer sign-out process
- [ ] **Duration Format - Short Sessions**: Sessions under 1 hour show as "XXm" (e.g., "45m")
- [ ] **Duration Format - Long Sessions**: Sessions over 1 hour show as "Xh Ym" (e.g., "2h 30m")
- [ ] **Duration Accuracy**: Duration calculations are accurate (no 6-hour offset from timezone issues)
- [ ] **Timezone Consistency**: All duration calculations use WordPress timezone consistently
- [ ] **Manual Return Button**: "Return to Member List" button appears and functions correctly
- [ ] **User Review Time**: Users have adequate time to review session details before auto-redirect
- [ ] **Confirmation Display**: Sign-out confirmation appears immediately after clicking "Sign Out"
- [ ] **Volunteer Mode Protection**: Body class "volunteer-signout-mode" prevents other script interference

## Backward Compatibility

All changes maintain backward compatibility:

- No changes to database structure or session data
- No changes to sign-out process flow
- Works with existing volunteer sessions
- Compatible with both original and optimized member search systems

## Performance Impact

- **Minimal**: Only affects UI timing and display formatting
- **Improved UX**: Better user control and clearer information display
- **No Additional Requests**: Same number of AJAX calls as before
- **Client-side Only**: Duration formatting happens server-side, no additional JavaScript processing
