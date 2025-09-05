<?php
/**
 * Volunteer Sessions API (CPT-backed)
 *
 * Thin functional API for starting/ending sessions and computing
 * volunteer hours/stats/leaderboards using the volunteer_session CPT.
 */

if (!defined('ABSPATH')) { exit; }

function make_create_volunteer_tables() { return true; }

/**
 * Initialize volunteer system on plugin activation
 */
function make_init_volunteer_system() {
    if (!get_option('makesf_volunteer_target_hours')) {
        update_option('makesf_volunteer_target_hours', 12);
    }
    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Make Volunteer: System initialized'); }
}

/**
 * Verify volunteer database tables exist
 */
function make_verify_volunteer_tables() { return true; }

/**
 * Start a new volunteer session
 */
function make_start_volunteer_session($user_id) {
    // Validate user ID
    if (!$user_id || !is_numeric($user_id)) {
        return new WP_Error('invalid_user', 'Invalid user ID provided');
    }
    
    // Check if user exists
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found');
    }
    
    // Prevent duplicates
    $active_session = make_get_active_volunteer_session($user_id);
    if ($active_session) {
        return new WP_Error('session_exists', 'User already has an active volunteer session');
    }

    // Create CPT-based session
    if (class_exists('Make_Volunteer_Session_Repository')) {
        $session_post_id = Make_Volunteer_Session_Repository::start_session($user_id);
        if (is_wp_error($session_post_id)) {
            return $session_post_id;
        }
    } else {
        return new WP_Error('missing_repo', 'Session repository unavailable');
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Make Volunteer: Created CPT session ID ' . $session_post_id . ' for user ' . $user_id);
    }
    
    // Set volunteer start date if this is their first session
    $start_date = get_user_meta($user_id, 'volunteer_start_date', true);
    if (empty($start_date)) {
        update_user_meta($user_id, 'volunteer_start_date', current_time('mysql'));
    }
    
    // Clear member-related caches so the system knows about the new volunteer session
    make_clear_volunteer_member_caches($user_id);
    
    return $session_post_id;
}

/**
 * End a volunteer session
 */
function make_end_volunteer_session($session_id, $tasks = array(), $notes = '') {
    if (class_exists('Make_Volunteer_Session_Repository')) {
        $result = Make_Volunteer_Session_Repository::end_session($session_id, array(), $notes);
        if (is_wp_error($result)) {
            return $result;
        }
        // Clear caches
        $uid = (int) get_post_meta(is_array($result) && isset($result['session_id']) ? $result['session_id'] : $session_id, 'user_id', true);
        if ($uid) {
            make_clear_volunteer_member_caches($uid);
        }
        return $result;
    }
    return new WP_Error('missing_repo', 'Session repository unavailable');
}

/**
 * Get active volunteer session for a user
 */
function make_get_active_volunteer_session($user_id) {
    if (!$user_id || !is_numeric($user_id)) {
        return null;
    }
    if (class_exists('Make_Volunteer_Session_Repository')) {
        $session = Make_Volunteer_Session_Repository::find_active_session_for_user(intval($user_id));
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
    return null;
}

/**
 * Get volunteer hours for a user in a specific period
 */
function make_get_volunteer_hours($user_id, $period = 'month') {
    if (!class_exists('Make_Volunteer_Session_Repository')) return array();
    return Make_Volunteer_Session_Repository::get_hours((int) $user_id, (string) $period);
}

/**
 * Get volunteer statistics for dashboard
 */
function make_get_volunteer_stats($period = 'month') {
    if (!class_exists('Make_Volunteer_Session_Repository')) return array();
    return Make_Volunteer_Session_Repository::get_stats((string) $period);
}

/**
 * Get volunteer leaderboard
 */
function make_get_volunteer_leaderboard($period = 'month', $limit = 10) {
    if (!class_exists('Make_Volunteer_Session_Repository')) return array();
    return Make_Volunteer_Session_Repository::get_leaderboard((string) $period, (int) $limit);
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
