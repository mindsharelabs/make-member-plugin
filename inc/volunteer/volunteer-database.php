<?php
/**
 * Volunteer Database Operations
 * 
 * Handles database table creation and core database operations for volunteer system
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create volunteer database tables
 */
function make_create_volunteer_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Volunteer sessions table
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    $sessions_sql = "CREATE TABLE $sessions_table (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        signin_time DATETIME NOT NULL,
        signout_time DATETIME NULL,
        duration_minutes INT NULL,
        tasks_completed TEXT NULL,
        notes TEXT NULL,
        status ENUM('active', 'completed') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_user_id (user_id),
        INDEX idx_signin_time (signin_time),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    ) $charset_collate;";
    
    // Volunteer schedules table
    $schedules_table = $wpdb->prefix . 'volunteer_schedules';
    $schedules_sql = "CREATE TABLE $schedules_table (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        day_of_week TINYINT NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_active BOOLEAN DEFAULT 1,
        created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_user_id (user_id),
        INDEX idx_day_of_week (day_of_week),
        INDEX idx_is_active (is_active),
        UNIQUE KEY unique_user_day_time (user_id, day_of_week, start_time)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sessions_sql);
    dbDelta($schedules_sql);
    
    // Log table creation
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Make Volunteer: Database tables created');
    }
}

/**
 * Initialize volunteer system on plugin activation
 */
function make_init_volunteer_system() {
    // Create tables
    make_create_volunteer_tables();
    
    // Set default options
    if (!get_option('makesf_volunteer_target_hours')) {
        update_option('makesf_volunteer_target_hours', 12);
    }
    
    // Log initialization
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Make Volunteer: System initialized');
    }
}

/**
 * Verify volunteer database tables exist
 */
function make_verify_volunteer_tables() {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    $schedules_table = $wpdb->prefix . 'volunteer_schedules';
    
    $sessions_exists = $wpdb->get_var("SHOW TABLES LIKE '$sessions_table'") == $sessions_table;
    $schedules_exists = $wpdb->get_var("SHOW TABLES LIKE '$schedules_table'") == $schedules_table;
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Make Volunteer: Sessions table exists: ' . ($sessions_exists ? 'Yes' : 'No'));
        error_log('Make Volunteer: Schedules table exists: ' . ($schedules_exists ? 'Yes' : 'No'));
    }
    
    if (!$sessions_exists || !$schedules_exists) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Make Volunteer: Tables missing, creating them now');
        }
        make_create_volunteer_tables();
        
        // Re-check after creation
        $sessions_exists = $wpdb->get_var("SHOW TABLES LIKE '$sessions_table'") == $sessions_table;
        $schedules_exists = $wpdb->get_var("SHOW TABLES LIKE '$schedules_table'") == $schedules_table;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Make Volunteer: After creation - Sessions table: ' . ($sessions_exists ? 'Yes' : 'No'));
            error_log('Make Volunteer: After creation - Schedules table: ' . ($schedules_exists ? 'Yes' : 'No'));
        }
    }
    
    return $sessions_exists && $schedules_exists;
}

/**
 * Start a new volunteer session
 */
function make_start_volunteer_session($user_id) {
    global $wpdb;
    
    // Verify tables exist first
    $tables_verified = make_verify_volunteer_tables();
    if (!$tables_verified) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Make Volunteer: Failed to verify/create tables');
        }
        return new WP_Error('table_error', 'Database tables could not be created or verified');
    }
    
    // Validate user ID
    if (!$user_id || !is_numeric($user_id)) {
        return new WP_Error('invalid_user', 'Invalid user ID provided');
    }
    
    // Check if user exists
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found');
    }
    
    // Check if user already has an active session
    $active_session = make_get_active_volunteer_session($user_id);
    if ($active_session) {
        return new WP_Error('session_exists', 'User already has an active volunteer session');
    }
    
    $table_name = $wpdb->prefix . 'volunteer_sessions';
    
    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Make Volunteer: Starting session for user ' . $user_id . ' (' . $user->display_name . ')');
        error_log('Make Volunteer: Using table ' . $table_name);
    }
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'signin_time' => current_time('mysql'),
            'status' => 'active'
        ),
        array('%d', '%s', '%s')
    );
    
    if ($result === false) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Make Volunteer: Database error creating session: ' . $wpdb->last_error);
        }
        return new WP_Error('db_error', 'Failed to create volunteer session: ' . $wpdb->last_error);
    }
    
    $session_id = $wpdb->insert_id;
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Make Volunteer: Created session ID ' . $session_id . ' for user ' . $user_id);
    }
    
    // Set volunteer start date if this is their first session
    $start_date = get_user_meta($user_id, 'volunteer_start_date', true);
    if (empty($start_date)) {
        update_user_meta($user_id, 'volunteer_start_date', current_time('mysql'));
    }
    
    // Clear member-related caches so the system knows about the new volunteer session
    make_clear_volunteer_member_caches($user_id);
    
    return $session_id;
}

/**
 * End a volunteer session
 */
function make_end_volunteer_session($session_id, $tasks = array(), $notes = '') {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'volunteer_sessions';
    
    // Get the session
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d AND status = 'active'",
        $session_id
    ));
    
    if (!$session) {
        return new WP_Error('session_not_found', 'Active session not found');
    }
    
    // Calculate duration - use WordPress timezone
    $timezone = wp_timezone();
    $signin_time = new DateTime($session->signin_time, $timezone);
    $signout_time = new DateTime(current_time('mysql'), $timezone);
    $duration_diff = $signin_time->diff($signout_time);
    $duration_minutes = ($duration_diff->days * 24 * 60) + ($duration_diff->h * 60) + $duration_diff->i;
    
    // Prepare tasks data
    $tasks_json = !empty($tasks) ? json_encode($tasks) : null;
    
    $result = $wpdb->update(
        $table_name,
        array(
            'signout_time' => current_time('mysql'),
            'duration_minutes' => $duration_minutes,
            'tasks_completed' => $tasks_json,
            'notes' => sanitize_textarea_field($notes),
            'status' => 'completed'
        ),
        array('id' => $session_id),
        array('%s', '%d', '%s', '%s', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        return new WP_Error('db_error', 'Failed to update volunteer session');
    }
    
    // Clear member-related caches so the system knows the volunteer session has ended
    make_clear_volunteer_member_caches($session->user_id);
    
    return array(
        'session_id' => $session_id,
        'duration_minutes' => $duration_minutes,
        'signin_time' => $session->signin_time,
        'signout_time' => current_time('mysql')
    );
}

/**
 * Get active volunteer session for a user
 */
function make_get_active_volunteer_session($user_id) {
    global $wpdb;
    
    if (!$user_id || !is_numeric($user_id)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Make Volunteer: Invalid user_id provided to get_active_volunteer_session: ' . print_r($user_id, true));
        }
        return null;
    }
    
    $table_name = $wpdb->prefix . 'volunteer_sessions';
    
    // Check if table exists first
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    if (!$table_exists) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Make Volunteer: Sessions table does not exist: ' . $table_name);
        }
        return null;
    }
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d AND status = 'active' ORDER BY signin_time DESC LIMIT 1",
        $user_id
    ));
    
    if ($wpdb->last_error) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Make Volunteer: Database error in get_active_volunteer_session: ' . $wpdb->last_error);
        }
        return null;
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG && $result) {
        error_log('Make Volunteer: Found active session for user ' . $user_id . ': Session ID ' . $result->id . ', signed in at ' . $result->signin_time);
    }
    
    return $result;
}

/**
 * Get volunteer hours for a user in a specific period
 */
function make_get_volunteer_hours($user_id, $period = 'month') {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'volunteer_sessions';
    
    // Determine date range based on period
    switch ($period) {
        case 'week':
            $date_condition = "signin_time >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $date_condition = "signin_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $date_condition = "signin_time >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        case 'all':
        default:
            $date_condition = "1=1";
            break;
    }
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as session_count,
            SUM(duration_minutes) as total_minutes,
            AVG(duration_minutes) as avg_minutes,
            MIN(signin_time) as first_session,
            MAX(signin_time) as last_session
        FROM $table_name 
        WHERE user_id = %d AND status = 'completed' AND $date_condition",
        $user_id
    ));
    
    return array(
        'session_count' => (int) $result->session_count,
        'total_hours' => round($result->total_minutes / 60, 2),
        'total_minutes' => (int) $result->total_minutes,
        'avg_hours' => round($result->avg_minutes / 60, 2),
        'first_session' => $result->first_session,
        'last_session' => $result->last_session
    );
}

/**
 * Get volunteer statistics for dashboard
 */
function make_get_volunteer_stats($period = 'month') {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'volunteer_sessions';
    
    // Determine date range
    switch ($period) {
        case 'week':
            $date_condition = "signin_time >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $date_condition = "signin_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $date_condition = "signin_time >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        default:
            $date_condition = "1=1";
            break;
    }
    
    // Get overall stats
    $overall_stats = $wpdb->get_row(
        "SELECT 
            COUNT(*) as total_sessions,
            COUNT(DISTINCT user_id) as unique_volunteers,
            SUM(duration_minutes) as total_minutes,
            AVG(duration_minutes) as avg_session_minutes
        FROM $table_name 
        WHERE status = 'completed' AND $date_condition"
    );
    
    // Get active sessions count
    $active_sessions = $wpdb->get_var(
        "SELECT COUNT(*) FROM $table_name WHERE status = 'active'"
    );
    
    return array(
        'total_sessions' => (int) $overall_stats->total_sessions,
        'unique_volunteers' => (int) $overall_stats->unique_volunteers,
        'total_hours' => round($overall_stats->total_minutes / 60, 2),
        'avg_session_hours' => round($overall_stats->avg_session_minutes / 60, 2),
        'active_sessions' => (int) $active_sessions
    );
}

/**
 * Get volunteer leaderboard
 */
function make_get_volunteer_leaderboard($period = 'month', $limit = 10) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'volunteer_sessions';
    
    // Determine date range
    switch ($period) {
        case 'week':
            $date_condition = "signin_time >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $date_condition = "signin_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $date_condition = "signin_time >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        default:
            $date_condition = "1=1";
            break;
    }
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            user_id,
            COUNT(*) as session_count,
            SUM(duration_minutes) as total_minutes,
            AVG(duration_minutes) as avg_minutes
        FROM $table_name 
        WHERE status = 'completed' AND $date_condition
        GROUP BY user_id 
        ORDER BY total_minutes DESC 
        LIMIT %d",
        $limit
    ));
    
    $leaderboard = array();
    foreach ($results as $result) {
        $user = get_user_by('ID', $result->user_id);
        if ($user) {
            $leaderboard[] = array(
                'user_id' => $result->user_id,
                'name' => $user->display_name,
                'session_count' => (int) $result->session_count,
                'total_hours' => round($result->total_minutes / 60, 2),
                'avg_hours' => round($result->avg_minutes / 60, 2)
            );
        }
    }
    
    return $leaderboard;
}

/**
 * Add volunteer schedule
 */
function make_add_volunteer_schedule($user_id, $day_of_week, $start_time, $end_time) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'volunteer_schedules';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'day_of_week' => $day_of_week,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'is_active' => 1
        ),
        array('%d', '%d', '%s', '%s', '%d')
    );
    
    if ($result === false) {
        return new WP_Error('db_error', 'Failed to add volunteer schedule');
    }
    
    return $wpdb->insert_id;
}

/**
 * Get volunteer schedule
 */
function make_get_volunteer_schedule($user_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'volunteer_schedules';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d AND is_active = 1 ORDER BY day_of_week, start_time",
        $user_id
    ));
}

/**
 * Remove volunteer schedule
 */
function make_remove_volunteer_schedule($schedule_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'volunteer_schedules';
    
    return $wpdb->update(
        $table_name,
        array('is_active' => 0),
        array('id' => $schedule_id),
        array('%d'),
        array('%d')
    );
}

/**
 * Check schedule adherence for a sign-in
 */
function make_check_schedule_adherence($user_id, $signin_time) {
    $schedules = make_get_volunteer_schedule($user_id);
    
    if (empty($schedules)) {
        return 'unscheduled'; // No schedule set
    }
    
    $signin_datetime = new DateTime($signin_time);
    $day_of_week = $signin_datetime->format('w'); // 0 = Sunday
    $signin_time_only = $signin_datetime->format('H:i:s');
    
    foreach ($schedules as $schedule) {
        if ($schedule->day_of_week == $day_of_week) {
            $start_time = new DateTime($schedule->start_time);
            $end_time = new DateTime($schedule->end_time);
            $signin_time_obj = new DateTime($signin_time_only);
            
            // Check if within 15 minutes of start time
            $tolerance = 15 * 60; // 15 minutes in seconds
            $start_timestamp = $start_time->getTimestamp();
            $signin_timestamp = $signin_time_obj->getTimestamp();
            
            if (abs($signin_timestamp - $start_timestamp) <= $tolerance) {
                return 'on_time';
            } elseif ($signin_timestamp < $start_timestamp - $tolerance) {
                return 'early';
            } elseif ($signin_timestamp > $start_timestamp + $tolerance) {
                return 'late';
            }
        }
    }
    
    return 'unscheduled';
}

/**
 * Clear member-related caches when volunteer sessions change
 */
function make_clear_volunteer_member_caches($user_id) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Make Volunteer: Clearing member caches for user ' . $user_id);
    }
    
    // Clear specific user caches from the optimized member search system
    $patterns = array(
        "make_member_details_{$user_id}_*",
        "make_user_badges_{$user_id}_*",
        "make_form_check_{$user_id}_*",
        "make_member_membership_{$user_id}_*"
    );
    
    foreach ($patterns as $pattern) {
        if (function_exists('make_clear_transients_by_pattern')) {
            make_clear_transients_by_pattern($pattern);
        }
    }
    
    // Clear search and member list caches (they'll rebuild with new data)
    if (function_exists('make_clear_transients_by_pattern')) {
        make_clear_transients_by_pattern('make_member_search_*');
        make_clear_transients_by_pattern('make_all_members_optimized_*');
    }
    
    // Also clear any WordPress object cache if available
    if (function_exists('wp_cache_delete_group')) {
        wp_cache_delete_group('make_member_' . $user_id);
    }
    
    // Clear individual transients that might exist
    $time_windows = array(
        floor(time() / 300) * 300,     // Current 5-minute window
        floor((time() - 300) / 300) * 300, // Previous 5-minute window
        floor((time() + 300) / 300) * 300  // Next 5-minute window
    );
    
    foreach ($time_windows as $window) {
        delete_transient("make_member_details_{$user_id}_{$window}");
        delete_transient("make_user_badges_{$user_id}_" . floor($window / 3600) * 3600);
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Make Volunteer: Completed clearing member caches for user ' . $user_id);
    }
}