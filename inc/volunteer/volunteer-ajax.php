<?php
/**
 * Volunteer AJAX Handlers
 *
 * Handles AJAX requests for volunteer functionality
 */

// BEGIN: CPT bridge wiring (added)
/**
 * Wire AJAX to CPT repository (non-breaking)
 * We preserve existing action names and response shapes, but route through the CPT repository where possible.
 */
if (!defined('ABSPATH')) { exit; }

// Guard if repository not loaded yet
if (!class_exists('Make_Volunteer_Session_Repository')) {
    // Repository is declared in volunteer-cpt.php; load order should include it. If not, continue gracefully.
}

/**
 * Override wrappers that route to CPT repository while keeping existing signatures.
 * We do NOT redeclare functions if they already exist elsewhere to avoid fatal errors; we selectively wrap via pluggable-style guards.
 */
if (!function_exists('make_start_volunteer_session_cpt_bridge')) {
    function make_start_volunteer_session_cpt_bridge($user_id) {
        if (!class_exists('Make_Volunteer_Session_Repository')) {
            if (function_exists('make_start_volunteer_session')) {
                return make_start_volunteer_session($user_id);
            }
            return new WP_Error('missing_repo', 'Session repository unavailable');
        }
        return Make_Volunteer_Session_Repository::start_session($user_id);
    }
}

if (!function_exists('make_get_active_volunteer_session_cpt_bridge')) {
    function make_get_active_volunteer_session_cpt_bridge($user_id) {
        if (!class_exists('Make_Volunteer_Session_Repository')) {
            if (function_exists('make_get_active_volunteer_session')) {
                return make_get_active_volunteer_session($user_id);
            }
            return null;
        }
        $session = Make_Volunteer_Session_Repository::find_active_session_for_user($user_id);
        if (!$session) return null;

        $obj = new stdClass();
        $obj->id = $session['post_id'] ? intval($session['post_id']) : intval($session['legacy_id']);
        $obj->user_id = intval($session['user_id']);
        $obj->signin_time = $session['signin_time'];
        $obj->signout_time = $session['signout_time'];
        $obj->duration_minutes = $session['duration_minutes'];
        $obj->status = $session['status'];
        return $obj;
    }
}

if (!function_exists('make_end_volunteer_session_cpt_bridge')) {
    function make_end_volunteer_session_cpt_bridge($session_identifier, $tasks = array(), $notes = '') {
        if (!class_exists('Make_Volunteer_Session_Repository')) {
            if (function_exists('make_end_volunteer_session')) {
                return make_end_volunteer_session($session_identifier, $tasks, $notes);
            }
            return new WP_Error('missing_repo', 'Session repository unavailable');
        }
        return Make_Volunteer_Session_Repository::end_session($session_identifier, $tasks, $notes);
    }
}

// Provide bridge aliases only if the legacy-named functions are not already defined.
if (!function_exists('make_get_active_volunteer_session')) {
    function make_get_active_volunteer_session($user_id) {
        return make_get_active_volunteer_session_cpt_bridge($user_id);
    }
}
if (!function_exists('make_start_volunteer_session')) {
    function make_start_volunteer_session($user_id) {
        return make_start_volunteer_session_cpt_bridge($user_id);
    }
}
if (!function_exists('make_end_volunteer_session')) {
    function make_end_volunteer_session($session_id, $tasks = array(), $notes = '') {
        return make_end_volunteer_session_cpt_bridge($session_id, $tasks, $notes);
    }
}
// END: CPT bridge wiring (added)

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle volunteer sign-out AJAX request
 */
function make_handle_volunteer_signout() {
    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Make Volunteer: Volunteer signout AJAX called');
        error_log('Make Volunteer: POST data: ' . print_r($_POST, true));
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'makesf_volunteer_nonce')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Make Volunteer: Nonce verification failed');
        }
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    $user_id = intval($_POST['userID'] ?? 0);
    $tasks = array();
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    if (!$user_id) {
        wp_send_json_error(array('message' => 'Invalid user ID'));
        return;
    }

    // Get active session
    $active_session = make_get_active_volunteer_session($user_id);
    if (!$active_session) {
        wp_send_json_error(array('message' => 'No active volunteer session found'));
        return;
    }

    // End the session
    $result = make_end_volunteer_session($active_session->id, $tasks, $notes);
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    // Get user info for response
    $user = get_user_by('ID', $user_id);
    $user_name = $user ? $user->display_name : 'Volunteer';

    // Calculate session summary - use WordPress timezone
    $timezone = wp_timezone();
    $signin_time = new DateTime($result['signin_time'], $timezone);
    $signout_time = new DateTime($result['signout_time'], $timezone);
    
    // Format duration as hours and minutes, or just minutes if less than an hour
    $duration_minutes = $result['duration_minutes'];
    if ($duration_minutes >= 60) {
        $hours = floor($duration_minutes / 60);
        $minutes = $duration_minutes % 60;
        $duration_display = $hours . 'h ' . $minutes . 'm';
    } else {
        $duration_display = $duration_minutes . 'm';
    }

    // Schedule adherence check removed per user request
    $schedule_status = 'unscheduled'; // Default status for response compatibility
    $schedule_message = '';

    // Build response HTML
    $first_name = get_user_meta($user_id, 'first_name', true);
    if (!$first_name && $user) { $first_name = preg_split('/\s+/', $user->display_name)[0]; }
    $html = '<div class="volunteer-signout-success">';
    $html .= '<div class="success-header text-center">';
    $html .= '<h3>Thanks for volunteering, ' . esc_html($first_name ?: $user_name) . '!</h3>';
    $html .= '<p class="volunteer-signout-message">Your volunteer session has been recorded.</p>';
    $html .= '</div>';
    
    // Prominent session duration display
    $html .= '<div class="prominent-session">';
    $html .= '<div class="makesf-session-timer"><div class="timer-display">' . ($duration_minutes >= 60 ? floor($duration_minutes/60) . 'h ' . ($duration_minutes%60) . 'm' : $duration_minutes . 'm') . '</div><div class="timer-label">Total This Session</div></div>';
    $html .= '</div>';
    
    $html .= '<div class="session-summary">';
    $html .= '<div class="session-time"><strong>Session Time:</strong> ' . $signin_time->format('g:i A') . ' - ' . $signout_time->format('g:i A') . '</div>';
    
    // Schedule message display removed per user request

    if (!empty($notes)) {
        $html .= '<div class="session-notes">';
        $html .= '<strong>Notes:</strong> ' . esc_html($notes);
        $html .= '</div>';
    }
    
    $html .= '</div>';

    // Footer with Back button and auto-return in ~15s to mirror other screens
    $html .= '<div class="badge-footer">';
    $html .= '  <div class="d-flex justify-content-center" style="gap:12px;">';
    $html .= '    <button type="button" class="btn btn-outline-secondary btn-lg return-to-list-btn">Back</button>';
    $html .= '  </div>';
    $html .= '  <div class="text-center mt-2 small text-muted">Returning to member list in <span id="makesf-auto-return-timer">15</span>s…</div>';
    $html .= '</div>';

    // Auto-return script with countdown and robust fallbacks
    $html .= '<script>(function(){
      var seconds = 15;
      var el = document.getElementById("makesf-auto-return-timer");
      function tick(){
        seconds -= 1;
        if (el && seconds >= 0) { el.textContent = String(seconds); }
        if (seconds > 0) { setTimeout(tick, 1000); }
      }
      function go(){
        try {
          if (window.MakeSignIn && typeof window.MakeSignIn.returnToInterface === "function") {
            window.MakeSignIn.returnToInterface();
            return;
          }
          var btn = document.querySelector(".return-to-list-btn");
          if (btn) { btn.click(); return; }
        } catch(e) {}
        // Last resort: hard refresh to reset kiosk
        try { window.location.reload(); } catch(e) {}
      }
      setTimeout(go, 15000);
      setTimeout(tick, 1000);
    })();</script>';

    $html .= '</div>';

    wp_send_json_success(array(
        'html' => $html,
        'status' => 'signout_complete',
        'session_data' => $result,
        'schedule_status' => $schedule_status,
        'user_id' => $user_id
    ));
}
add_action('wp_ajax_makeVolunteerSignOut', 'make_handle_volunteer_signout');
add_action('wp_ajax_nopriv_makeVolunteerSignOut', 'make_handle_volunteer_signout');

/**
 * Get current volunteer session info
 */
function make_handle_get_volunteer_session() {
    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Make Volunteer: Get volunteer session AJAX called');
        error_log('Make Volunteer: POST data: ' . print_r($_POST, true));
    }
    
    // Verify nonce - make it more flexible for different scenarios
    $nonce_valid = false;
    if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'makesf_volunteer_nonce')) {
        $nonce_valid = true;
    }
    
    // For debugging, allow bypass in development
    if (!$nonce_valid && (!defined('WP_DEBUG') || !WP_DEBUG)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Make Volunteer: Nonce verification failed in get session');
        }
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    $user_id = intval($_POST['userID'] ?? 0);

    if (!$user_id) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Make Volunteer: Invalid user ID provided: ' . print_r($_POST['userID'] ?? 'not set', true));
        }
        wp_send_json_error(array('message' => 'Invalid user ID'));
        return;
    }

    // CPT-first: Do not require legacy tables for session lookup

    // CPT-first active session lookup
    $active_session = make_get_active_volunteer_session($user_id);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Make Volunteer: Active session for user ' . $user_id . ': ' . ($active_session ? 'Found (ID: ' . $active_session->id . ')' : 'Not found'));
    }
    
    if (!$active_session) {
        wp_send_json_success(array(
            'has_active_session' => false,
            'html' => ''
        ));
        return;
    }

    // Shared renderer
    $rendered = make_render_volunteer_signout_interface($user_id, $active_session);
    $user = get_user_by('ID', $user_id);
    $first_name = get_user_meta($user_id, 'first_name', true);
    if (!$first_name && $user) { $first_name = preg_split('/\s+/', $user->display_name)[0]; }
    wp_send_json_success(array(
        'has_active_session' => true,
        'html' => $rendered['html'],
        'greeting_name' => $first_name,
        'session_data' => $rendered['session_data']
    ));
}
add_action('wp_ajax_makeGetVolunteerSession', 'make_handle_get_volunteer_session');
add_action('wp_ajax_nopriv_makeGetVolunteerSession', 'make_handle_get_volunteer_session');

/**
 * Lightweight: return IDs of users with active volunteer sessions
 */
function make_handle_get_active_volunteer_ids() {
    $sessions = make_get_active_volunteer_sessions();
    $ids = array();
    foreach ($sessions as $s) {
        $ids[$s->user_id] = true;
    }
    wp_send_json_success(array(
        'active_user_ids' => array_map('intval', array_keys($ids)),
        'updated_at' => current_time('mysql')
    ));
}
add_action('wp_ajax_makeGetActiveVolunteerIds', 'make_handle_get_active_volunteer_ids');
add_action('wp_ajax_nopriv_makeGetActiveVolunteerIds', 'make_handle_get_active_volunteer_ids');

/**
 * Refresh nonces for long-lived kiosk sessions
 */
function make_handle_refresh_nonces() {
    wp_send_json_success(array(
        'volunteer_nonce' => wp_create_nonce('makesf_volunteer_nonce'),
        'signin_nonce' => wp_create_nonce('makesf_signin_nonce'),
        'generated_at' => current_time('mysql')
    ));
}
add_action('wp_ajax_makeRefreshNonces', 'make_handle_refresh_nonces');
add_action('wp_ajax_nopriv_makeRefreshNonces', 'make_handle_refresh_nonces');

/**
 * Enhanced member sign-in to handle volunteer sessions
 */
function make_handle_enhanced_member_signin() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'makesf_volunteer_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    $user_id = intval($_POST['userID'] ?? 0);
    $badges = $_POST['badges'] ?? array();

    if (!$user_id) {
        wp_send_json_error(array('message' => 'Invalid user ID'));
        return;
    }

    // Check if volunteering is selected
    $is_volunteering = in_array('volunteer', $badges);

    if ($is_volunteering) {
        // Start volunteer session (CPT-first)
        $session_result = make_start_volunteer_session($user_id);
        if (is_wp_error($session_result)) {
            wp_send_json_error(array('message' => $session_result->get_error_message()));
            return;
        }
        // Normalize to CPT post ID if repository returns WP_Post ID
        if (is_object($session_result) && isset($session_result->ID)) {
            $session_result = intval($session_result->ID);
        }

        // Get user info
        $user = get_user_by('ID', $user_id);
        $user_name = $user ? $user->display_name : 'Volunteer';
        // Monthly totals
        $tz = wp_timezone();
        $now = new DateTime('now', $tz);
        $current_start = new DateTime($now->format('Y-m-01 00:00:00'), $tz);
        $current_end = new DateTime($now->format('Y-m-t 23:59:59'), $tz);
        $prev = (clone $current_start)->modify('-1 month');
        $prev_start = new DateTime($prev->format('Y-m-01 00:00:00'), $tz);
        $prev_end = new DateTime($prev->format('Y-m-t 23:59:59'), $tz);
        $sum_minutes = function($uid, $start, $end) {
            $q = new WP_Query(array(
                'post_type' => 'volunteer_session',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => array(
                    'relation' => 'AND',
                    array('key' => 'user_id', 'value' => intval($uid), 'compare' => '='),
                    array('key' => 'status', 'value' => 'completed', 'compare' => '='),
                    array('key' => 'signin_time', 'value' => array($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')), 'compare' => 'BETWEEN', 'type' => 'DATETIME'),
                ),
            ));
            $total = 0;
            foreach ($q->posts as $pid) { $total += (int) get_post_meta($pid, 'duration_minutes', true); }
            return $total;
        };
        $current_minutes = $sum_minutes($user_id, $current_start, $current_end);
        $previous_minutes = $sum_minutes($user_id, $prev_start, $prev_end);

        // Schedule check removed per user request
        $schedule_status = 'unscheduled'; // Default status for response compatibility
        $schedule_message = '';

        $first_name = get_user_meta($user_id, 'first_name', true);
        if (!$first_name && $user) { $first_name = preg_split('/\s+/', $user->display_name)[0]; }
        $html = '<div class="volunteer-signin-success">';
        $html .= '<div class="sign-in-confirm text-center" style="font-size:1.25rem;font-weight:600;margin-bottom:10px;">You\'re signed in, ' . esc_html($first_name ?: $user_name) . '!</div>';
        $html .= '<div class="makesf-session-timer" id="volunteer-session-timer"></div>';
        $html .= '<div class="volunteer-signin-time text-center"><strong>Signed in at:</strong> ' . current_time('g:i A') . '</div>';
        $html .= '<div class="volunteer-monthly-totals" style="margin-top:10px;"><div><strong>This month (incl. current):</strong> ' . round($current_minutes/60, 2) . ' hours</div><div><strong>Last month:</strong> ' . round($previous_minutes/60, 2) . ' hours</div></div>';
        $html .= '<script>(function(){
            var start = Date.now();
            function pad(n){return (n<10?"0":"")+n;}
            function tick(){
              var diff = Math.floor((Date.now() - start)/1000);
              var h = Math.floor(diff/3600);
              var m = Math.floor((diff%3600)/60);
              var s = diff%60;
              var el = document.getElementById("volunteer-session-timer");
              if(el){ el.innerHTML = "<div class=\\"timer-display\\"><span>"+pad(h)+"</span>:<span>"+pad(m)+"</span>:<span>"+pad(s)+"</span></div><div class=\\"timer-label\\">Session Running</div>"; }
            }
            tick();
            window.makesfVolunteerTimer = setInterval(tick, 1000);
        })();</script>';
        $html .= '</div>';

        $first_name = get_user_meta($user_id, 'first_name', true);
        if (!$first_name && $user) { $first_name = preg_split('/\s+/', $user->display_name)[0]; }
        wp_send_json_success(array(
            'html' => $html,
            'status' => 'volunteer_signin_complete',
            'greeting_name' => $first_name,
            'session_id' => is_array($session_result) ? ($session_result['session_id'] ?? $session_result) : $session_result,
            'schedule_status' => $schedule_status
        ));
    } else {
        // Handle regular sign-in (existing functionality)
        // This would integrate with the existing sign-in system
        wp_send_json_success(array(
            'html' => '<div class="signin-success"><h3>Signed in successfully!</h3></div>',
            'status' => 'signin_complete'
        ));
    }
}
