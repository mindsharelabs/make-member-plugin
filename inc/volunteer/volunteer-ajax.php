<?php
/**
 * Volunteer AJAX Handlers
 * 
 * Handles AJAX requests for volunteer functionality
 */

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
    $tasks = $_POST['tasks'] ?? array();
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
    
    // Mark completed tasks as done (for one-time tasks)
    if (!empty($tasks) && function_exists('make_mark_task_completed')) {
        foreach ($tasks as $task_id) {
            make_mark_task_completed($task_id, $user_id, $notes);
        }
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

    // Get task names
    $task_names = array();
    if (!empty($tasks)) {
        foreach ($tasks as $task_id) {
            $task = get_post($task_id);
            if ($task) {
                $task_names[] = $task->post_title;
            }
        }
    }

    // Schedule adherence check removed per user request
    $schedule_status = 'unscheduled'; // Default status for response compatibility
    $schedule_message = '';

    // Build response HTML
    $html = '<div class="volunteer-signout-success">';
    $html .= '<div class="success-header">';
    $html .= '<h3>Thank you, ' . esc_html($user_name) . '!</h3>';
    $html .= '<p class="volunteer-signout-message">Your volunteer session has been recorded.</p>';
    $html .= '</div>';
    
    $html .= '<div class="session-summary">';
    $html .= '<div class="session-time">';
    $html .= '<strong>Session Time:</strong> ' . $signin_time->format('g:i A') . ' - ' . $signout_time->format('g:i A');
    $html .= '<br><strong>Duration:</strong> ' . $duration_display;
    $html .= '</div>';
    
    // Schedule message display removed per user request
    
    if (!empty($task_names)) {
        $html .= '<div class="tasks-completed">';
        $html .= '<strong>Tasks Completed:</strong>';
        $html .= '<ul>';
        foreach ($task_names as $task_name) {
            $html .= '<li>' . esc_html($task_name) . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }
    
    if (!empty($notes)) {
        $html .= '<div class="session-notes">';
        $html .= '<strong>Notes:</strong> ' . esc_html($notes);
        $html .= '</div>';
    }
    
    $html .= '</div>';
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

    // Verify tables exist before checking for sessions
    if (!function_exists('make_verify_volunteer_tables') || !make_verify_volunteer_tables()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Make Volunteer: Database tables not available');
        }
        wp_send_json_success(array(
            'has_active_session' => false,
            'html' => '',
            'error' => 'Database tables not available'
        ));
        return;
    }

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

    // Calculate current duration - use WordPress timezone
    $timezone = wp_timezone();
    $signin_time = new DateTime($active_session->signin_time, $timezone);
    $current_time = new DateTime('now', $timezone);
    $duration = $signin_time->diff($current_time);
    $duration_minutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
    
    // Format duration as hours and minutes, or just minutes if less than an hour
    if ($duration_minutes >= 60) {
        $hours = floor($duration_minutes / 60);
        $minutes = $duration_minutes % 60;
        $duration_display = $hours . 'h ' . $minutes . 'm';
    } else {
        $duration_display = $duration_minutes . 'm';
    }

    // Get user info
    $user = get_user_by('ID', $user_id);
    $user_name = $user ? $user->display_name : 'Volunteer';

    // Get available tasks
    $available_tasks = make_get_available_volunteer_tasks($user_id);

    // Schedule info check removed per user request
    $schedule_status = 'unscheduled'; // Default status for response compatibility
    $schedule_message = '';

    // Build signout interface HTML
    $html = '<div class="volunteer-signout-interface">';
    $html .= '<div class="volunteer-session-info">';
    $html .= '<h3>Welcome back, ' . esc_html($user_name) . '!</h3>';
    $html .= '<div class="current-session">';
    $html .= '<p><strong>Signed in:</strong> ' . $signin_time->format('g:i A') . '</p>';
    $html .= '<p><strong>Current duration:</strong> ' . $duration_display . '</p>';
    $html .= '</div>';
    $html .= '</div>';

    // Tasks selection
    if (!empty($available_tasks)) {
        $html .= '<div class="volunteer-tasks-selection">';
        $html .= '<h4>What did you work on? (Select all that apply)</h4>';
        $html .= '<div class="tasks-grid">';
        
        foreach ($available_tasks as $task) {
            $disabled_class = !$task['user_qualified'] ? ' not-allowed' : '';
            $disabled_attr = !$task['user_qualified'] ? ' disabled' : '';
            
            $html .= '<div class="task-item' . $disabled_class . '"' . $disabled_attr . ' data-task="' . $task['id'] . '">';
            $html .= '<div class="task-header">';
            $html .= '<h5>' . esc_html($task['title']) . '</h5>';
            if (!empty($task['categories'])) {
                $html .= '<span class="task-category">' . esc_html($task['categories'][0]['name']) . '</span>';
            }
            $html .= '</div>';
            
            if (!empty($task['description'])) {
                $html .= '<p class="task-description">' . esc_html(wp_trim_words($task['description'], 15)) . '</p>';
            }
            
            $html .= '<div class="task-meta">';
            $html .= '<span class="task-duration">~' . $task['estimated_duration'] . ' min</span>';
            $html .= '<span class="task-priority priority-' . $task['priority'] . '">' . ucfirst($task['priority']) . '</span>';
            $html .= '</div>';
            
            if (!$task['user_qualified']) {
                $html .= '<div class="task-requirements">Requires additional certification</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
    }

    // Notes section
    $html .= '<div class="volunteer-notes-section">';
    $html .= '<h4>Session Notes (Optional)</h4>';
    $html .= '<textarea id="volunteerNotes" placeholder="Any notes about your volunteer session..."></textarea>';
    $html .= '</div>';

    // Sign out button
    $html .= '<div class="volunteer-signout-actions">';
    $html .= '<button class="volunteer-sign-out-btn" data-user="' . $user_id . '" data-session="' . $active_session->id . '">Sign Out</button>';
    $html .= '</div>';

    $html .= '</div>';

    wp_send_json_success(array(
        'has_active_session' => true,
        'html' => $html,
        'session_data' => array(
            'id' => $active_session->id,
            'signin_time' => $active_session->signin_time,
            'duration_minutes' => $duration_minutes,
            'schedule_status' => $schedule_status
        )
    ));
}
add_action('wp_ajax_makeGetVolunteerSession', 'make_handle_get_volunteer_session');
add_action('wp_ajax_nopriv_makeGetVolunteerSession', 'make_handle_get_volunteer_session');

/**
 * Get available volunteer tasks
 */
function make_handle_get_volunteer_tasks() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'makesf_volunteer_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    $user_id = intval($_POST['userID'] ?? 0);
    $tasks = make_get_available_volunteer_tasks($user_id);

    wp_send_json_success(array(
        'tasks' => $tasks
    ));
}
add_action('wp_ajax_makeGetVolunteerTasks', 'make_handle_get_volunteer_tasks');
add_action('wp_ajax_nopriv_makeGetVolunteerTasks', 'make_handle_get_volunteer_tasks');

/**
 * Get volunteer schedule for today
 */
function make_handle_get_volunteer_schedule() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'makesf_volunteer_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    $user_id = intval($_POST['userID'] ?? 0);

    if (!$user_id) {
        wp_send_json_error(array('message' => 'Invalid user ID'));
        return;
    }

    $schedules = make_get_volunteer_schedule($user_id);
    $today_schedule = null;
    $today_day_of_week = date('w'); // 0 = Sunday

    foreach ($schedules as $schedule) {
        if ($schedule->day_of_week == $today_day_of_week) {
            $today_schedule = $schedule;
            break;
        }
    }

    wp_send_json_success(array(
        'has_schedule_today' => !is_null($today_schedule),
        'schedule' => $today_schedule,
        'all_schedules' => $schedules
    ));
}
add_action('wp_ajax_makeGetVolunteerSchedule', 'make_handle_get_volunteer_schedule');
add_action('wp_ajax_nopriv_makeGetVolunteerSchedule', 'make_handle_get_volunteer_schedule');

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
        // Start volunteer session
        $session_result = make_start_volunteer_session($user_id);
        
        if (is_wp_error($session_result)) {
            wp_send_json_error(array('message' => $session_result->get_error_message()));
            return;
        }

        // Get user info
        $user = get_user_by('ID', $user_id);
        $user_name = $user ? $user->display_name : 'Volunteer';

        // Schedule check removed per user request
        $schedule_status = 'unscheduled'; // Default status for response compatibility
        $schedule_message = '';

        $html = '<div class="volunteer-signin-success">';
        $html .= '<h3>Welcome, ' . esc_html($user_name) . '!</h3>';
        $html .= '<p>Your volunteer session has started. Don\'t forget to sign out when you\'re done!</p>';
        $html .= '<div class="volunteer-signin-time">';
        $html .= '<strong>Signed in at:</strong> ' . current_time('g:i A');
        $html .= '</div>';
        $html .= '</div>';

        wp_send_json_success(array(
            'html' => $html,
            'status' => 'volunteer_signin_complete',
            'session_id' => $session_result,
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
// COMMENTED OUT - This conflicts with the original makeMemberSignIn handler in scripts.php
// The original handler should be used to maintain member agreement and waiver validation
// add_action('wp_ajax_makeMemberSignIn', 'make_handle_enhanced_member_signin');
// add_action('wp_ajax_nopriv_makeMemberSignIn', 'make_handle_enhanced_member_signin');