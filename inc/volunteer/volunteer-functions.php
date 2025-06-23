<?php
/**
 * Volunteer Utility Functions
 * 
 * Helper functions for volunteer system
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mark volunteer orientation as complete
 */
function make_mark_orientation_complete($user_id, $date = null) {
    if (!$date) {
        $date = current_time('mysql');
    }
    
    update_user_meta($user_id, 'volunteer_orientation_completed', true);
    update_user_meta($user_id, 'volunteer_orientation_date', $date);
    
    return true;
}

/**
 * Check if volunteer has completed orientation
 */
function make_volunteer_has_orientation($user_id) {
    return get_user_meta($user_id, 'volunteer_orientation_completed', true);
}

/**
 * Get volunteer orientation date
 */
function make_get_volunteer_orientation_date($user_id) {
    return get_user_meta($user_id, 'volunteer_orientation_date', true);
}

/**
 * Set volunteer schedule type
 */
function make_set_volunteer_schedule_type($user_id, $type) {
    $valid_types = array('scheduled', 'flexible', 'none');
    
    if (!in_array($type, $valid_types)) {
        return false;
    }
    
    update_user_meta($user_id, 'volunteer_schedule_type', $type);
    return true;
}

/**
 * Get volunteer schedule type
 */
function make_get_volunteer_schedule_type($user_id) {
    return get_user_meta($user_id, 'volunteer_schedule_type', true) ?: 'none';
}

/**
 * Get expected volunteers for today
 */
function make_get_expected_volunteers_today() {
    global $wpdb;
    
    $today_day_of_week = date('w'); // 0 = Sunday
    $current_time = date('H:i:s');
    
    $schedules_table = $wpdb->prefix . 'volunteer_schedules';
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT user_id, start_time, end_time 
        FROM $schedules_table 
        WHERE day_of_week = %d 
        AND is_active = 1 
        AND start_time <= %s 
        AND end_time >= %s",
        $today_day_of_week,
        $current_time,
        $current_time
    ));
    
    $expected_volunteers = array();
    foreach ($results as $result) {
        $user = get_user_by('ID', $result->user_id);
        if ($user) {
            $expected_volunteers[] = array(
                'user_id' => $result->user_id,
                'name' => $user->display_name,
                'start_time' => $result->start_time,
                'end_time' => $result->end_time,
                'is_signed_in' => !is_null(make_get_active_volunteer_session($result->user_id))
            );
        }
    }
    
    return $expected_volunteers;
}

/**
 * Get schedule adherence rate for a volunteer
 */
function make_get_schedule_adherence_rate($user_id, $period = 'month') {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
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
    
    $sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT signin_time FROM $sessions_table 
        WHERE user_id = %d AND status = 'completed' AND $date_condition",
        $user_id
    ));
    
    if (empty($sessions)) {
        return array(
            'total_sessions' => 0,
            'on_time' => 0,
            'late' => 0,
            'early' => 0,
            'unscheduled' => 0,
            'adherence_rate' => 0
        );
    }
    
    $stats = array(
        'on_time' => 0,
        'late' => 0,
        'early' => 0,
        'unscheduled' => 0
    );
    
    foreach ($sessions as $session) {
        $adherence = make_check_schedule_adherence($user_id, $session->signin_time);
        $stats[$adherence]++;
    }
    
    $total_sessions = count($sessions);
    $adherence_rate = $total_sessions > 0 ? round(($stats['on_time'] / $total_sessions) * 100, 1) : 0;
    
    return array(
        'total_sessions' => $total_sessions,
        'on_time' => $stats['on_time'],
        'late' => $stats['late'],
        'early' => $stats['early'],
        'unscheduled' => $stats['unscheduled'],
        'adherence_rate' => $adherence_rate
    );
}

/**
 * Export volunteer data
 */
function make_export_volunteer_data($filters = array()) {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
    // Build WHERE clause based on filters
    $where_conditions = array("status = 'completed'");
    $where_values = array();
    
    if (!empty($filters['user_id'])) {
        $where_conditions[] = "user_id = %d";
        $where_values[] = $filters['user_id'];
    }
    
    if (!empty($filters['start_date'])) {
        $where_conditions[] = "signin_time >= %s";
        $where_values[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $where_conditions[] = "signin_time <= %s";
        $where_values[] = $filters['end_date'];
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $query = "SELECT 
        s.user_id,
        s.signin_time,
        s.signout_time,
        s.duration_minutes,
        s.tasks_completed,
        s.notes,
        u.display_name,
        u.user_email
    FROM $sessions_table s
    LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
    WHERE $where_clause
    ORDER BY s.signin_time DESC";
    
    if (!empty($where_values)) {
        $results = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $results = $wpdb->get_results($query);
    }
    
    // Process results for export
    $export_data = array();
    foreach ($results as $result) {
        $tasks = array();
        if (!empty($result->tasks_completed)) {
            $task_ids = json_decode($result->tasks_completed, true);
            if (is_array($task_ids)) {
                foreach ($task_ids as $task_id) {
                    $task = get_post($task_id);
                    if ($task) {
                        $tasks[] = $task->post_title;
                    }
                }
            }
        }
        
        $export_data[] = array(
            'volunteer_name' => $result->display_name,
            'volunteer_email' => $result->user_email,
            'signin_time' => $result->signin_time,
            'signout_time' => $result->signout_time,
            'duration_hours' => round($result->duration_minutes / 60, 2),
            'tasks_completed' => implode(', ', $tasks),
            'notes' => $result->notes
        );
    }
    
    return $export_data;
}

/**
 * Get volunteer monthly progress
 */
function make_get_volunteer_monthly_progress($user_id, $target_hours = 12) {
    $current_month_hours = make_get_volunteer_hours($user_id, 'month');
    $total_hours = $current_month_hours['total_hours'];
    
    $progress_percentage = ($total_hours / $target_hours) * 100;
    $progress_percentage = min($progress_percentage, 100); // Cap at 100%
    
    $remaining_hours = max(0, $target_hours - $total_hours);
    
    return array(
        'total_hours' => $total_hours,
        'target_hours' => $target_hours,
        'remaining_hours' => $remaining_hours,
        'progress_percentage' => round($progress_percentage, 1),
        'is_complete' => $total_hours >= $target_hours,
        'session_count' => $current_month_hours['session_count']
    );
}

/**
 * Get volunteer badges/certifications
 */
function make_get_volunteer_badges($user_id) {
    $badges = get_field('certifications', 'user_' . $user_id);
    
    if (!$badges) {
        return array();
    }
    
    $badge_data = array();
    foreach ($badges as $badge) {
        $badge_image = get_field('badge_image', $badge->ID);
        
        $badge_data[] = array(
            'id' => $badge->ID,
            'title' => $badge->post_title,
            'image' => $badge_image ? wp_get_attachment_image_url($badge_image, 'thumbnail') : null,
            'description' => $badge->post_content
        );
    }
    
    return $badge_data;
}

/**
 * Get all volunteers with basic info
 */
function make_get_all_volunteers() {
    // Get all users who have volunteer sessions or orientation
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
    $volunteer_user_ids = $wpdb->get_col(
        "SELECT DISTINCT user_id FROM $sessions_table 
        UNION 
        SELECT DISTINCT user_id FROM {$wpdb->usermeta} 
        WHERE meta_key = 'volunteer_orientation_completed' AND meta_value = '1'"
    );
    
    $volunteers = array();
    foreach ($volunteer_user_ids as $user_id) {
        $user = get_user_by('ID', $user_id);
        if ($user) {
            $volunteers[] = array(
                'id' => $user_id,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'orientation_completed' => make_volunteer_has_orientation($user_id),
                'orientation_date' => make_get_volunteer_orientation_date($user_id),
                'schedule_type' => make_get_volunteer_schedule_type($user_id),
                'start_date' => get_user_meta($user_id, 'volunteer_start_date', true),
                'hours_this_month' => make_get_volunteer_hours($user_id, 'month')['total_hours'],
                'total_hours' => make_get_volunteer_hours($user_id, 'all')['total_hours']
            );
        }
    }
    
    // Sort by total hours descending
    usort($volunteers, function($a, $b) {
        return $b['total_hours'] <=> $a['total_hours'];
    });
    
    return $volunteers;
}

/**
 * Get day name from day of week number
 */
function make_get_day_name($day_of_week) {
    $days = array(
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday'
    );
    
    return $days[$day_of_week] ?? 'Unknown';
}

/**
 * Format time for display
 */
function make_format_time($time) {
    return date('g:i A', strtotime($time));
}

/**
 * Get volunteer session history
 */
function make_get_volunteer_session_history($user_id, $limit = 10) {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
    $sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $sessions_table 
        WHERE user_id = %d AND status = 'completed' 
        ORDER BY signin_time DESC 
        LIMIT %d",
        $user_id,
        $limit
    ));
    
    $history = array();
    foreach ($sessions as $session) {
        $tasks = array();
        if (!empty($session->tasks_completed)) {
            $task_ids = json_decode($session->tasks_completed, true);
            if (is_array($task_ids)) {
                foreach ($task_ids as $task_id) {
                    $task = get_post($task_id);
                    if ($task) {
                        $tasks[] = array(
                            'id' => $task_id,
                            'title' => $task->post_title
                        );
                    }
                }
            }
        }
        
        $history[] = array(
            'id' => $session->id,
            'signin_time' => $session->signin_time,
            'signout_time' => $session->signout_time,
            'duration_hours' => round($session->duration_minutes / 60, 2),
            'tasks' => $tasks,
            'notes' => $session->notes,
            'schedule_adherence' => make_check_schedule_adherence($user_id, $session->signin_time)
        );
    }
    
    return $history;
}

/**
 * Check if user is currently volunteering
 */
function make_is_user_volunteering($user_id) {
    $active_session = make_get_active_volunteer_session($user_id);
    return !is_null($active_session);
}

/**
 * Get volunteer target hours setting
 */
function make_get_volunteer_target_hours() {
    return get_option('makesf_volunteer_target_hours', 12);
}

/**
 * Set volunteer target hours setting
 */
function make_set_volunteer_target_hours($hours) {
    return update_option('makesf_volunteer_target_hours', intval($hours));
}

/**
 * Get recent volunteer sessions for admin dashboard
 */
function make_get_recent_volunteer_sessions($limit = 10) {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $sessions_table
        WHERE status = 'completed'
        ORDER BY signout_time DESC
        LIMIT %d",
        $limit
    ));
}

/**
 * Get all active volunteer sessions
 */
function make_get_active_volunteer_sessions() {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
    return $wpdb->get_results(
        "SELECT * FROM $sessions_table
        WHERE status = 'active'
        ORDER BY signin_time ASC"
    );
}

/**
 * Get volunteer sessions with pagination
 */
function make_get_volunteer_sessions_paginated($per_page = 20, $offset = 0) {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $sessions_table
        ORDER BY signin_time DESC
        LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
}

/**
 * Get total volunteer sessions count
 */
function make_get_volunteer_sessions_count() {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
    return $wpdb->get_var("SELECT COUNT(*) FROM $sessions_table");
}

/**
 * Get task completion count
 */
function make_get_task_completion_count($task_id) {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $sessions_table
        WHERE status = 'completed'
        AND tasks_completed LIKE %s",
        '%"' . $task_id . '"%'
    ));
    
    return intval($count);
}

/**
 * Get all volunteer schedules
 */
function make_get_all_volunteer_schedules() {
    global $wpdb;
    
    $schedules_table = $wpdb->prefix . 'volunteer_schedules';
    
    return $wpdb->get_results(
        "SELECT * FROM $schedules_table
        WHERE is_active = 1
        ORDER BY day_of_week, start_time"
    );
}

/**
 * Get volunteer monthly data for charts
 */
function make_get_volunteer_monthly_data($months = 12) {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT
            DATE_FORMAT(signin_time, '%%Y-%%m') as month,
            SUM(duration_minutes) as total_minutes,
            COUNT(*) as session_count
        FROM $sessions_table
        WHERE status = 'completed'
        AND signin_time >= DATE_SUB(NOW(), INTERVAL %d MONTH)
        GROUP BY DATE_FORMAT(signin_time, '%%Y-%%m')
        ORDER BY month",
        $months
    ));
    
    $labels = array();
    $hours = array();
    
    foreach ($results as $result) {
        $labels[] = date('M Y', strtotime($result->month . '-01'));
        $hours[] = round($result->total_minutes / 60, 1);
    }
    
    return array(
        'labels' => $labels,
        'hours' => $hours
    );
}

/**
 * Generate CSV report data
 */
function make_generate_volunteer_report_csv($period = 'month') {
    $filters = array();
    
    switch ($period) {
        case 'week':
            $filters['start_date'] = date('Y-m-d', strtotime('-1 week'));
            break;
        case 'month':
            $filters['start_date'] = date('Y-m-d', strtotime('-1 month'));
            break;
        case 'year':
            $filters['start_date'] = date('Y-m-d', strtotime('-1 year'));
            break;
    }
    
    $data = make_export_volunteer_data($filters);
    
    if (empty($data)) {
        return '';
    }
    
    // Create CSV content
    $csv = '';
    
    // Headers
    $headers = array_keys($data[0]);
    $csv .= implode(',', $headers) . "\n";
    
    // Data rows
    foreach ($data as $row) {
        $csv_row = array();
        foreach ($row as $value) {
            // Escape quotes and wrap in quotes if contains comma
            $value = str_replace('"', '""', $value);
            if (strpos($value, ',') !== false || strpos($value, '"') !== false) {
                $value = '"' . $value . '"';
            }
            $csv_row[] = $value;
        }
        $csv .= implode(',', $csv_row) . "\n";
    }
    
    return $csv;
}

/**
 * Test volunteer system functionality
 */
function make_test_volunteer_system() {
    if (!current_user_can('manage_options')) {
        return 'Access denied';
    }
    
    $results = array();
    
    // Test 1: Check if tables exist
    global $wpdb;
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    $schedules_table = $wpdb->prefix . 'volunteer_schedules';
    
    $sessions_exists = $wpdb->get_var("SHOW TABLES LIKE '$sessions_table'") == $sessions_table;
    $schedules_exists = $wpdb->get_var("SHOW TABLES LIKE '$schedules_table'") == $schedules_table;
    
    $results['tables'] = array(
        'sessions_table_exists' => $sessions_exists,
        'schedules_table_exists' => $schedules_exists
    );
    
    // Test 2: Check if functions exist
    $results['functions'] = array(
        'make_start_volunteer_session' => function_exists('make_start_volunteer_session'),
        'make_get_active_volunteer_session' => function_exists('make_get_active_volunteer_session'),
        'make_get_available_volunteer_tasks' => function_exists('make_get_available_volunteer_tasks')
    );
    
    // Test 3: Check AJAX handlers
    $results['ajax_handlers'] = array(
        'makeGetVolunteerSession' => has_action('wp_ajax_makeGetVolunteerSession'),
        'makeVolunteerSignOut' => has_action('wp_ajax_makeVolunteerSignOut')
    );
    
    // Test 4: Check if volunteer tasks exist
    $tasks = get_posts(array(
        'post_type' => 'volunteer_task',
        'post_status' => 'publish',
        'numberposts' => 1
    ));
    
    $results['volunteer_tasks'] = array(
        'tasks_exist' => !empty($tasks),
        'task_count' => count($tasks)
    );
    
    return $results;
}

/**
 * Create default volunteer tasks for testing
 */
function make_create_default_volunteer_tasks() {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    $default_tasks = array(
        array(
            'title' => 'Clean Workshop Area',
            'content' => 'Sweep floors, organize tools, and wipe down work surfaces in the main workshop area.',
            'category' => 'Maintenance',
            'priority' => 'medium',
            'duration' => 30,
            'location' => 'Main Workshop',
            'tools' => 'Broom, cleaning supplies, rags',
            'instructions' => '1. Clear all tools from work surfaces\n2. Sweep floor thoroughly\n3. Wipe down all work surfaces\n4. Organize tools back to proper locations'
        ),
        array(
            'title' => 'Organize Storage Room',
            'content' => 'Sort materials, label shelves, and inventory supplies in the storage areas.',
            'category' => 'Organization',
            'priority' => 'low',
            'duration' => 60,
            'location' => 'Storage Room',
            'tools' => 'Labels, marker, inventory sheets',
            'instructions' => '1. Sort materials by type\n2. Label all shelves clearly\n3. Update inventory sheets\n4. Remove any damaged items'
        ),
        array(
            'title' => 'Front Desk Support',
            'content' => 'Greet visitors, answer questions, and assist with member sign-ins.',
            'category' => 'Administrative',
            'priority' => 'high',
            'duration' => 120,
            'location' => 'Front Desk',
            'tools' => 'Computer, phone, visitor log',
            'instructions' => '1. Greet all visitors warmly\n2. Help with sign-in process\n3. Answer basic questions about the space\n4. Direct visitors to appropriate areas'
        ),
        array(
            'title' => 'Safety Equipment Check',
            'content' => 'Inspect and test safety equipment throughout the facility.',
            'category' => 'Safety',
            'priority' => 'urgent',
            'duration' => 45,
            'location' => 'Entire Facility',
            'tools' => 'Checklist, flashlight, testing equipment',
            'instructions' => '1. Check all fire extinguishers\n2. Test emergency lighting\n3. Verify first aid kit supplies\n4. Report any issues immediately'
        )
    );
    
    $created_count = 0;
    
    foreach ($default_tasks as $task_data) {
        // Check if task already exists
        $existing = get_posts(array(
            'post_type' => 'volunteer_task',
            'title' => $task_data['title'],
            'post_status' => 'any',
            'numberposts' => 1
        ));
        
        if (!empty($existing)) {
            continue; // Skip if already exists
        }
        
        // Create the task post
        $post_id = wp_insert_post(array(
            'post_title' => $task_data['title'],
            'post_content' => $task_data['content'],
            'post_type' => 'volunteer_task',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ));
        
        if ($post_id && !is_wp_error($post_id)) {
            // Set the category
            $category_term = get_term_by('name', $task_data['category'], 'volunteer_task_category');
            if ($category_term) {
                wp_set_post_terms($post_id, array($category_term->term_id), 'volunteer_task_category');
            }
            
            // Set ACF fields
            update_field('priority', $task_data['priority'], $post_id);
            update_field('estimated_duration', $task_data['duration'], $post_id);
            update_field('location', $task_data['location'], $post_id);
            update_field('tools_needed', $task_data['tools'], $post_id);
            update_field('instructions', $task_data['instructions'], $post_id);
            
            $created_count++;
        }
    }
    
    return $created_count;
}

/**
 * Get session details for modal display
 */
function make_get_session_details_for_modal($session_id) {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sessions_table WHERE id = %d",
        $session_id
    ));
    
    if (!$session) {
        return false;
    }
    
    // Get user info
    $user = get_user_by('ID', $session->user_id);
    if (!$user) {
        return false;
    }
    
    // Get tasks
    $tasks = array();
    $tasks_html = '<div class="no-tasks">No tasks recorded</div>';
    
    if (!empty($session->tasks_completed)) {
        $task_ids = json_decode($session->tasks_completed, true);
        if (is_array($task_ids) && !empty($task_ids)) {
            $tasks_list = array();
            foreach ($task_ids as $task_id) {
                $task = get_post($task_id);
                if ($task) {
                    $category = get_the_terms($task_id, 'volunteer_task_category');
                    $category_name = $category && !is_wp_error($category) ? $category[0]->name : 'General';
                    
                    $tasks[] = array(
                        'id' => $task_id,
                        'title' => $task->post_title,
                        'category' => $category_name
                    );
                    
                    $tasks_list[] = '<div class="task-item">
                        <span class="task-name">' . esc_html($task->post_title) . '</span>
                        <span class="task-category">' . esc_html($category_name) . '</span>
                    </div>';
                }
            }
            
            if (!empty($tasks_list)) {
                $tasks_html = implode('', $tasks_list);
            }
        }
    }
    
    // Format times for datetime-local inputs
    $signin_time_input = '';
    $signout_time_input = '';
    $duration_display = 'Ongoing';
    
    if ($session->signin_time) {
        $signin_dt = new DateTime($session->signin_time);
        $signin_time_input = $signin_dt->format('Y-m-d\TH:i');
    }
    
    if ($session->signout_time) {
        $signout_dt = new DateTime($session->signout_time);
        $signout_time_input = $signout_dt->format('Y-m-d\TH:i');
        
        if ($session->duration_minutes) {
            $hours = floor($session->duration_minutes / 60);
            $minutes = $session->duration_minutes % 60;
            $duration_display = $hours . 'h ' . $minutes . 'm';
        }
    }
    
    return array(
        'id' => $session->id,
        'volunteer_name' => $user->display_name,
        'volunteer_email' => $user->user_email,
        'status' => $session->status,
        'status_label' => ucfirst($session->status),
        'signin_time_input' => $signin_time_input,
        'signout_time_input' => $signout_time_input,
        'duration_display' => $duration_display,
        'tasks' => $tasks,
        'tasks_html' => $tasks_html,
        'notes' => $session->notes ?: ''
    );
}

/**
 * Update volunteer session times and notes
 */
function make_update_volunteer_session($session_id, $signin_time, $signout_time, $notes = '') {
    global $wpdb;
    
    $sessions_table = $wpdb->prefix . 'volunteer_sessions';
    
    // Validate session exists
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sessions_table WHERE id = %d",
        $session_id
    ));
    
    if (!$session) {
        return new WP_Error('session_not_found', 'Session not found');
    }
    
    // Validate times
    $signin_dt = new DateTime($signin_time);
    $signout_dt = new DateTime($signout_time);
    
    if ($signout_dt <= $signin_dt) {
        return new WP_Error('invalid_times', 'Sign-out time must be after sign-in time');
    }
    
    // Calculate duration
    $duration_minutes = round(($signout_dt->getTimestamp() - $signin_dt->getTimestamp()) / 60);
    
    // Update session
    $result = $wpdb->update(
        $sessions_table,
        array(
            'signin_time' => $signin_dt->format('Y-m-d H:i:s'),
            'signout_time' => $signout_dt->format('Y-m-d H:i:s'),
            'duration_minutes' => $duration_minutes,
            'notes' => $notes,
            'status' => 'completed'
        ),
        array('id' => $session_id),
        array('%s', '%s', '%d', '%s', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        return new WP_Error('update_failed', 'Failed to update session');
    }
    
    return true;
}