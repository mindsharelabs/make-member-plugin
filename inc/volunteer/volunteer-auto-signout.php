<?php
/**
 * Auto Sign-out Functionality for Volunteers
 * 
 * Automatically signs out all active volunteers at 8pm daily
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schedule the auto sign-out cron job
 */
function make_schedule_auto_signout_cron() {
    $settings = get_option('make_volunteer_settings', array());
    
    // Check if auto sign-out is enabled
    if (!isset($settings['auto_signout_enabled']) || !$settings['auto_signout_enabled']) {
        // Clear any existing schedule if disabled
        make_unschedule_auto_signout_cron();
        return;
    }
    
    $signout_time = isset($settings['auto_signout_time']) ? $settings['auto_signout_time'] : '20:00';
    $timezone = isset($settings['auto_signout_timezone']) ? $settings['auto_signout_timezone'] : wp_timezone_string();
    
    // Clear existing schedule
    make_unschedule_auto_signout_cron();
    
    // Schedule new event with configured time
    try {
        $date = new DateTime('today ' . $signout_time, new DateTimeZone($timezone));
        $timestamp = $date->getTimestamp();
        
        // Ensure the time is in the future
        if ($timestamp < time()) {
            $timestamp = strtotime('tomorrow ' . $signout_time, $timestamp);
        }
        
        wp_schedule_event($timestamp, 'daily', 'make_auto_signout_volunteers');
    } catch (Exception $e) {
        error_log('Failed to schedule auto sign-out: ' . $e->getMessage());
    }
}

/**
 * Unschedule the auto sign-out cron job
 */
function make_unschedule_auto_signout_cron() {
    $timestamp = wp_next_scheduled('make_auto_signout_volunteers');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'make_auto_signout_volunteers');
    }
}

/**
 * Auto sign-out all active volunteers
 *
 * This function is triggered by the cron job daily at configured time
 */
function make_auto_signout_all_volunteers() {
    global $wpdb;
    
    // Get settings
    $settings = get_option('make_volunteer_settings', array());
    
    // Check if auto sign-out is enabled
    if (!isset($settings['auto_signout_enabled']) || !$settings['auto_signout_enabled']) {
        make_log_auto_signout_activity('Auto sign-out skipped - disabled in settings');
        return new WP_Error('disabled', 'Auto sign-out is disabled');
    }
    
    // Get all active volunteer sessions
    $active_sessions = make_get_active_volunteer_sessions();
    
    if (empty($active_sessions)) {
        // Log that no active sessions were found
        make_log_auto_signout_activity('No active volunteer sessions found for auto sign-out');
        return 'No active sessions to sign out';
    }
    
    $signout_count = 0;
    $errors = array();
    
    foreach ($active_sessions as $session) {
        try {
            // Use existing function to properly end the session
            $result = make_end_volunteer_session($session->id, array(), 'Auto-completed at end of day');
            
            if ($result) {
                $signout_count++;
                
                // Log the auto sign-out
                make_log_auto_signout_activity(sprintf(
                    'Auto signed out volunteer ID %d (Session ID: %d)',
                    $session->user_id,
                    $session->id
                ));
                
                // Send notification to volunteer if enabled
                if (isset($settings['auto_signout_notification']) && $settings['auto_signout_notification']) {
                    make_notify_volunteer_auto_signout($session->user_id, $session);
                }
                
            } else {
                $errors[] = sprintf('Failed to sign out volunteer ID %d', $session->user_id);
            }
            
        } catch (Exception $e) {
            $errors[] = sprintf('Error signing out volunteer ID %d: %s', $session->user_id, $e->getMessage());
        }
    }
    
    // Log summary
    make_log_auto_signout_activity(sprintf(
        'Auto sign-out completed: %d volunteers signed out, %d errors',
        $signout_count,
        count($errors)
    ));
    
    // Log any errors
    if (!empty($errors)) {
        foreach ($errors as $error) {
            make_log_auto_signout_activity($error, 'error');
        }
    }
    
    return sprintf('%d volunteers signed out successfully', $signout_count);
}

/**
 * Log auto sign-out activity
 *
 * @param string $message The log message
 * @param string $level Log level (info, warning, error)
 */
function make_log_auto_signout_activity($message, $level = 'info') {
    $settings = get_option('make_volunteer_settings', array());
    
    // Check if logging is enabled
    if (!isset($settings['auto_signout_log_enabled']) || !$settings['auto_signout_log_enabled']) {
        return;
    }
    
    $log_entry = sprintf(
        '[%s] [%s] %s',
        current_time('mysql'),
        strtoupper($level),
        $message
    );
    
    // Log to WordPress debug log if enabled
    if (WP_DEBUG_LOG) {
        error_log($log_entry);
    }
    
    // Store in database for admin viewing
    $logs = get_option('make_auto_signout_log', array());
    
    // Limit logs to last 100 entries
    array_unshift($logs, $log_entry);
    $logs = array_slice($logs, 0, 100);
    
    update_option('make_auto_signout_log', $logs);
}

/**
 * Get auto sign-out logs
 *
 * @param int $limit Maximum number of log entries to return
 * @return array Array of log entries
 */
function make_get_auto_signout_logs($limit = 50) {
    $logs = get_option('make_auto_signout_log', array());
    return array_slice($logs, 0, $limit);
}

/**
 * Notify volunteer about auto sign-out
 *
 * @param int $user_id Volunteer user ID
 * @param object $session Session data
 */
function make_notify_volunteer_auto_signout($user_id, $session) {
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }
    
    $settings = get_option('make_volunteer_settings', array());
    
    // Check if notifications are enabled
    if (!isset($settings['auto_signout_notification']) || !$settings['auto_signout_notification']) {
        return false;
    }
    
    $template = isset($settings['auto_signout_email_template']) ? $settings['auto_signout_email_template'] :
        "Hi {name},\n\nYour volunteer session has been automatically ended at {time}.\n\nThank you for your contribution to MakeSF!\n\nBest regards,\nMakeSF Team";
    
    // Calculate duration
    $signin_time = new DateTime($session->signin_time);
    $signout_time = new DateTime();
    $duration = $signin_time->diff($signout_time);
    $duration_minutes = $duration->h * 60 + $duration->i;
    
    // Replace placeholders
    $replacements = array(
        '{name}' => $user->display_name,
        '{time}' => $signout_time->format('g:i A'),
        '{date}' => $signout_time->format('F j, Y'),
        '{duration}' => $duration_minutes . ' minutes',
        '{organization}' => get_bloginfo('name')
    );
    
    $message = str_replace(array_keys($replacements), array_values($replacements), $template);
    
    $subject = sprintf('Your %s volunteer session has been completed', get_bloginfo('name'));
    
    // Send email
    wp_mail($user->user_email, $subject, $message);
    
    return true;
}

/**
 * Get auto sign-out settings
 *
 * @return array Settings array
 */
function make_get_auto_signout_settings() {
    $settings = get_option('make_volunteer_settings', array());
    return array(
        'enabled' => isset($settings['auto_signout_enabled']) ? $settings['auto_signout_enabled'] : true,
        'signout_time' => isset($settings['auto_signout_time']) ? $settings['auto_signout_time'] : '20:00',
        'send_notifications' => isset($settings['auto_signout_notification']) ? $settings['auto_signout_notification'] : true,
        'log_activity' => isset($settings['auto_signout_log_enabled']) ? $settings['auto_signout_log_enabled'] : true
    );
}

/**
 * Update auto sign-out settings
 *
 * @param array $settings Settings to update
 * @return bool Success status
 */
function make_update_auto_signout_settings($settings) {
    $current_settings = get_option('make_volunteer_settings', array());
    
    if (isset($settings['enabled'])) {
        $current_settings['auto_signout_enabled'] = (bool)$settings['enabled'];
    }
    
    if (isset($settings['signout_time'])) {
        $current_settings['auto_signout_time'] = sanitize_text_field($settings['signout_time']);
    }
    
    if (isset($settings['send_notifications'])) {
        $current_settings['auto_signout_notification'] = (bool)$settings['send_notifications'];
    }
    
    if (isset($settings['log_activity'])) {
        $current_settings['auto_signout_log_enabled'] = (bool)$settings['log_activity'];
    }
    
    return update_option('make_volunteer_settings', $current_settings);
}

/**
 * Manual trigger for auto sign-out (for testing)
 * 
 * @return array Results of the sign-out process
 */
function make_trigger_manual_auto_signout() {
    if (!current_user_can('manage_options')) {
        return array('error' => 'Insufficient permissions');
    }
    
    return make_auto_signout_all_volunteers();
}

// Hook up the cron job
add_action('make_auto_signout_volunteers', 'make_auto_signout_all_volunteers');

// Schedule cron on plugin activation
register_activation_hook(MAKESF_PLUGIN_FILE, 'make_schedule_auto_signout_cron');

// Unschedule cron on plugin deactivation
register_deactivation_hook(MAKESF_PLUGIN_FILE, 'make_unschedule_auto_signout_cron');

// Add AJAX handler for manual trigger
add_action('wp_ajax_make_trigger_auto_signout', 'make_handle_manual_auto_signout');

/**
 * Handle manual auto sign-out trigger via AJAX
 */
function make_handle_manual_auto_signout() {
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Check nonce
    if (!wp_verify_nonce($_POST['nonce'], 'make_auto_signout_nonce')) {
        wp_die('Invalid nonce');
    }
    
    // Execute auto sign-out
    $result = make_auto_signout_all_volunteers();
    
    if (!is_wp_error($result)) {
        wp_send_json_success(array(
            'message' => sprintf('Successfully signed out %d volunteer(s)', $result),
            'details' => $result
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'Failed to sign out volunteers',
            'error' => $result->get_error_message()
        ));
    }
}