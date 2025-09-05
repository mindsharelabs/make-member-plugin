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
 * Benefits status helpers (per user per month)
 */
function makesf_get_benefits_status($user_id, $ym) {
    $key = 'makesf_volunteer_benefits_' . $ym; // e.g., 2025-09
    $val = get_user_meta($user_id, $key, true);
    return $val ?: 'pending';
}

function makesf_set_benefits_status($user_id, $ym, $status) {
    $allowed = array('approved','denied','pending');
    if (!in_array($status, $allowed, true)) return false;
    $key = 'makesf_volunteer_benefits_' . $ym;
    return update_user_meta($user_id, $key, $status);
}

/**
 * Sync WooCommerce Memberships status when benefits are approved/denied.
 * - Plan slug: make-member
 * - Approved: set status to complimentary and end-date to last day of next month
 * - Denied: set end-date to last day of current month and set status to expired
 *
 * This is resilient: if WC Memberships is not available, it silently returns.
 */
function makesf_sync_benefits_membership($user_id, $ym, $status) {
    // Only handle approved/denied transitions
    if (!in_array($status, array('approved','denied'), true)) {
        return;
    }
    // Require WooCommerce Memberships runtime
    if (!function_exists('wc_memberships_get_user_memberships')) {
        return;
    }

    // Resolve plan id for slug "make-member"
    $plan_id = 0;
    if (function_exists('wc_memberships_get_membership_plan')) {
        $plan_obj = wc_memberships_get_membership_plan('make-member');
        if ($plan_obj && is_object($plan_obj)) {
            $plan_id = method_exists($plan_obj, 'get_id') ? (int) $plan_obj->get_id() : (int) $plan_obj->id;
        }
    }
    if (!$plan_id) {
        $plan_post = get_page_by_path('make-member', OBJECT, 'wc_membership_plan');
        if ($plan_post) $plan_id = (int) $plan_post->ID;
    }
    if (!$plan_id) {
        return; // Can't find the plan, bail quietly
    }

    // Find existing user membership for that plan (any status)
    $memberships = wc_memberships_get_user_memberships($user_id);
    $user_membership = null;
    $created = false;
    if (!empty($memberships)) {
        foreach ($memberships as $m) {
            $pid = method_exists($m, 'get_plan_id') ? (int) $m->get_plan_id() : (int) $m->plan->get_id();
            if ($pid === $plan_id) { $user_membership = $m; break; }
        }
    }
    // If none, create one
    if (!$user_membership && function_exists('wc_memberships_create_user_membership')) {
        $args = array(
            'plan_id'   => $plan_id,
            'user_id'   => $user_id,
            'is_manual' => true,
            'status'    => 'complimentary',
        );
        $user_membership = wc_memberships_create_user_membership($args);
        if (is_wp_error($user_membership)) {
            return false; // creation failed
        }
        $created = true;
    }
    if (!$user_membership) {
        return false;
    }

    // Compute end date per rules
    // $ym is YYYY-MM representing the month reviewed
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        return false;
    }
    try {
        $tz = wp_timezone();
    } catch (Exception $e) {
        $tz = new DateTimeZone('UTC');
    }
    $month_start = DateTime::createFromFormat('Y-m-d H:i:s', $ym.'-01 00:00:00', $tz);
    if (!$month_start) {
        return false;
    }
    if ($status === 'approved') {
        // Benefits for reviewed month grant through end of the following month
        $end = (clone $month_start)->modify('first day of next month')->modify('last day of this month')->setTime(23,59,59);
        $new_status = 'complimentary';
    } else { // denied
        // End at last day of reviewed month
        $end = (clone $month_start)->modify('last day of this month')->setTime(23,59,59);
        // If end is in the past, mark expired; otherwise keep complimentary until then
        $now = new DateTime('now', $tz);
        $new_status = ($end < $now) ? 'expired' : 'complimentary';
    }

    // Apply changes
    if (method_exists($user_membership, 'set_end_date')) {
        $user_membership->set_end_date($end->format('Y-m-d H:i:s'));
    }
    if (method_exists($user_membership, 'update_status')) {
        $user_membership->update_status($new_status);
    } elseif (method_exists($user_membership, 'set_status')) {
        $user_membership->set_status($new_status);
    }
    if (method_exists($user_membership, 'save')) {
        $user_membership->save();
    }

    return array(
        'end' => $end->format('Y-m-d H:i:s'),
        'applied_status' => $new_status,
        'created' => $created,
        'plan_id' => $plan_id,
    );
}

/**
 * Render volunteer sign-out interface HTML and summary.
 * Shared renderer for optimized member flow and volunteer session UI.
 *
 * @param int   $user_id
 * @param object $active_session  Object from make_get_active_volunteer_session()
 * @return array { html: string, session_data: array }
 */
function make_render_volunteer_signout_interface($user_id, $active_session) {
    // Compute current duration using WordPress timezone
    $timezone = wp_timezone();
    $signin_time = new DateTime($active_session->signin_time, $timezone);
    $current_time = new DateTime('now', $timezone);
    $duration = $signin_time->diff($current_time);
    $duration_minutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;

    // Format duration as hours/mins
    if ($duration_minutes >= 60) {
        $hours = floor($duration_minutes / 60);
        $minutes = $duration_minutes % 60;
        $duration_display = $hours . 'h ' . $minutes . 'm';
    } else {
        $duration_display = $duration_minutes . 'm';
    }

    // User info
    $user = get_user_by('ID', $user_id);
    $user_name = $user ? $user->display_name : 'Volunteer';

    // Monthly totals (current and previous calendar months)
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
        foreach ($q->posts as $pid) {
            $total += (int) get_post_meta($pid, 'duration_minutes', true);
        }
        return $total;
    };

    $current_minutes = $sum_minutes($user_id, $current_start, $current_end);
    $previous_minutes = $sum_minutes($user_id, $prev_start, $prev_end);

    // Build HTML
    $html = '<div class="volunteer-signout-interface">';
    $html .= '<div class="volunteer-session-info">';
    // Live timer (matches sign-in styling) using WP timezone timestamp
    $html .= '<div class="makesf-session-timer" id="volunteer-live-timer" data-start="' . esc_attr($signin_time->getTimestamp() * 1000) . '"></div>';
    $html .= '<div class="current-session">';
    $html .= '<p><strong>Signed in:</strong> ' . $signin_time->format('g:i A') . '</p>';
    $html .= '</div>';
    $html .= '</div>';
    // Include the current live session minutes in this month's total
    $combined_current_minutes = $current_minutes + $duration_minutes;
    $html .= '<div class="volunteer-monthly-totals" style="margin-top:10px;">';
    $html .= '<div><strong>This month (incl. current):</strong> ' . round($combined_current_minutes / 60, 2) . ' hours</div>';
    $html .= '<div><strong>Last month:</strong> ' . round($previous_minutes / 60, 2) . ' hours</div>';
    $html .= '</div>';
    // Live timer script
    $html .= '<script>(function(){
      var el = document.getElementById("volunteer-live-timer");
      if(!el) return;
      var start = parseInt(el.getAttribute("data-start"),10);
      function pad(n){return (n<10?"0":"")+n;}
      function tick(){
        var diff = Math.floor((Date.now() - start)/1000);
        var h = Math.floor(diff/3600);
        var m = Math.floor((diff%3600)/60);
        var s = diff%60;
        el.innerHTML = "<div class=\\"timer-display\\"><span>"+pad(h)+"</span>:<span>"+pad(m)+"</span>:<span>"+pad(s)+"</span></div><div class=\\"timer-label\\">Session Running</div>";
      }
      tick();
      window.makesfVolunteerLiveTimer = setInterval(tick,1000);
    })();</script>';
    // Footer actions (fixed, like badge selection)
    $html .= '<div class="badge-footer">';
    $html .= '  <div class="d-flex justify-content-center" style="gap:12px;">';
    $html .= '    <button type="button" class="btn btn-outline-secondary btn-lg volunteer-back-btn">Back</button>';
    $html .= '    <button type="button" class="btn btn-primary btn-lg volunteer-sign-out-btn" data-user="' . intval($user_id) . '" data-session="' . intval($active_session->id) . '">Sign Out</button>';
    $html .= '  </div>';
    $html .= '</div>';
    $html .= '</div>';

    return array(
        'html' => $html,
        'session_data' => array(
            'id' => intval($active_session->id),
            'signin_time' => $active_session->signin_time,
            'duration_minutes' => $duration_minutes,
            'schedule_status' => 'unscheduled'
        )
    );
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
 * Export volunteer data
 */
function make_export_volunteer_data($filters = array()) {
    // Build meta query
    $meta_query = array(
        'relation' => 'AND',
        array('key' => 'status', 'value' => 'completed', 'compare' => '='),
    );
    if (!empty($filters['user_id'])) {
        $meta_query[] = array('key' => 'user_id', 'value' => intval($filters['user_id']), 'compare' => '=');
    }
    if (!empty($filters['start_date'])) {
        $meta_query[] = array('key' => 'signin_time', 'value' => $filters['start_date'], 'compare' => '>=', 'type' => 'DATETIME');
    }
    if (!empty($filters['end_date'])) {
        $meta_query[] = array('key' => 'signin_time', 'value' => $filters['end_date'], 'compare' => '<=', 'type' => 'DATETIME');
    }

    $q = new WP_Query(array(
        'post_type' => 'volunteer_session',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'meta_value',
        'meta_key' => 'signin_time',
        'order' => 'DESC',
        'meta_query' => $meta_query,
        'fields' => 'ids',
    ));

    $export_data = array();
    foreach ($q->posts as $post_id) {
        $user_id = intval(get_post_meta($post_id, 'user_id', true));
        $user = get_user_by('ID', $user_id);
        $signin_time = get_post_meta($post_id, 'signin_time', true);
        $signout_time = get_post_meta($post_id, 'signout_time', true);
        $duration_minutes = (int) get_post_meta($post_id, 'duration_minutes', true);
        $notes = (string) get_post_meta($post_id, 'notes', true);

        $export_data[] = array(
            'volunteer_name' => $user ? $user->display_name : 'Unknown',
            'volunteer_email' => $user ? $user->user_email : '',
            'signin_time' => $signin_time,
            'signout_time' => $signout_time,
            'duration_hours' => round($duration_minutes / 60, 2),
            'notes' => $notes
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
 * Get a user's volunteer hours for a specific calendar month.
 *
 * @param int         $user_id User ID
 * @param string|null $ym      Year-month in 'Y-m' (e.g. '2025-09'). Defaults to current month.
 * @param bool        $include_active Whether to include the currently active session minutes if month is current.
 * @return array { total_minutes, total_hours, session_count, start, end }
 */
function make_get_user_volunteer_hours_for_month($user_id, $ym = null, $include_active = false) {
    $user_id = (int) $user_id;
    if (!$user_id) {
        return array(
            'total_minutes' => 0,
            'total_hours' => 0,
            'session_count' => 0,
            'start' => null,
            'end' => null,
        );
    }

    if (!$ym) {
        $ym = date('Y-m');
    }

    // Compute calendar month boundaries in site timezone
    try { $tz = wp_timezone(); } catch (Exception $e) { $tz = new DateTimeZone('UTC'); }
    $month_start = DateTime::createFromFormat('Y-m-d H:i:s', $ym.'-01 00:00:00', $tz);
    if (!$month_start) {
        $month_start = new DateTime(date('Y-m-01 00:00:00'), $tz);
    }
    $month_end = (clone $month_start)->modify('last day of this month')->setTime(23,59,59);

    $start_str = $month_start->format('Y-m-d H:i:s');
    $end_str   = $month_end->format('Y-m-d H:i:s');

    // Query completed sessions within the month for this user
    $q = new WP_Query(array(
        'post_type'      => 'volunteer_session',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            'relation' => 'AND',
            array('key' => 'user_id', 'value' => $user_id, 'compare' => '='),
            array('key' => 'status', 'value' => 'completed', 'compare' => '='),
            array('key' => 'signin_time', 'value' => array($start_str, $end_str), 'compare' => 'BETWEEN', 'type' => 'DATETIME'),
        ),
    ));

    $total_minutes = 0;
    $count = 0;
    foreach ($q->posts as $post_id) {
        $mins = (int) get_post_meta($post_id, 'duration_minutes', true);
        $total_minutes += max(0, $mins);
        $count++;
    }

    // Optionally include active session minutes if this is current month
    if ($include_active && $ym === date('Y-m')) {
        if (function_exists('make_get_active_volunteer_session')) {
            $active = make_get_active_volunteer_session($user_id);
            if ($active && !empty($active->signin_time)) {
                try { $signin = new DateTime($active->signin_time, $tz); } catch (Exception $e) { $signin = null; }
                if ($signin) {
                    $now = new DateTime('now', $tz);
                    $diff = $signin->diff($now);
                    $active_minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
                    $total_minutes += max(0, (int) $active_minutes);
                }
            }
        }
    }

    return array(
        'total_minutes' => $total_minutes,
        'total_hours'   => round($total_minutes / 60, 2),
        'session_count' => $count,
        'start'         => $start_str,
        'end'           => $end_str,
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
    // Users who either have sessions (CPT) or have orientation completed
    global $wpdb;
    $umeta = $wpdb->usermeta;

    // Gather user IDs from CPT sessions
    $q = new WP_Query(array(
        'post_type' => 'volunteer_session',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            array('key' => 'user_id', 'compare' => 'EXISTS'),
        ),
    ));
    $session_user_ids = array();
    foreach ($q->posts as $post_id) {
        $uid = intval(get_post_meta($post_id, 'user_id', true));
        if ($uid) $session_user_ids[$uid] = true;
    }

    // Gather users with orientation meta
    $oriented_user_ids = $wpdb->get_col("
        SELECT DISTINCT user_id FROM {$umeta}
        WHERE meta_key = 'volunteer_orientation_completed' AND meta_value = '1'
    ");

    $all_ids = array_unique(array_merge(array_keys($session_user_ids), array_map('intval', $oriented_user_ids)));

    $volunteers = array();
    foreach ($all_ids as $user_id) {
        $user = get_user_by('ID', $user_id);
        if ($user) {
            $volunteers[] = array(
                'id' => $user_id,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'orientation_completed' => make_volunteer_has_orientation($user_id),
                'orientation_date' => make_get_volunteer_orientation_date($user_id),
                'start_date' => get_user_meta($user_id, 'volunteer_start_date', true),
                'hours_this_month' => make_get_volunteer_hours($user_id, 'month')['total_hours'],
                'total_hours' => make_get_volunteer_hours($user_id, 'all')['total_hours']
            );
        }
    }

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
    $q = new WP_Query(array(
        'post_type' => 'volunteer_session',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'meta_value',
        'meta_key' => 'signin_time',
        'order' => 'DESC',
        'meta_query' => array(
            'relation' => 'AND',
            array('key' => 'user_id', 'value' => intval($user_id), 'compare' => '='),
            array('key' => 'status', 'value' => 'completed', 'compare' => '='),
        ),
        'fields' => 'ids',
    ));
    
    $history = array();
    foreach ($q->posts as $post_id) {
        $signin_time = get_post_meta($post_id, 'signin_time', true);
        $signout_time = get_post_meta($post_id, 'signout_time', true);
        $duration_minutes = (int) get_post_meta($post_id, 'duration_minutes', true);
        $notes = (string) get_post_meta($post_id, 'notes', true);
        $history[] = array(
            'id' => $post_id,
            'signin_time' => $signin_time,
            'signout_time' => $signout_time,
            'duration_hours' => round($duration_minutes / 60, 2),
            'notes' => $notes
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
    $q = new WP_Query(array(
        'post_type' => 'volunteer_session',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'meta_value',
        'meta_key' => 'signout_time',
        'order' => 'DESC',
        'meta_query' => array(
            array('key' => 'status', 'value' => 'completed', 'compare' => '='),
        ),
        'fields' => 'ids',
    ));
    $results = array();
    foreach ($q->posts as $post_id) {
        $obj = (object) array(
            'id' => $post_id,
            'user_id' => intval(get_post_meta($post_id, 'user_id', true)),
            'signin_time' => get_post_meta($post_id, 'signin_time', true),
            'signout_time' => get_post_meta($post_id, 'signout_time', true),
            'duration_minutes' => (int) get_post_meta($post_id, 'duration_minutes', true),
            'notes' => (string) get_post_meta($post_id, 'notes', true),
            'status' => get_post_meta($post_id, 'status', true),
        );
        $results[] = $obj;
    }
    return $results;
}

/**
 * Get all active volunteer sessions
 */
function make_get_active_volunteer_sessions() {
    $q = new WP_Query(array(
        'post_type' => 'volunteer_session',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'meta_value',
        'meta_key' => 'signin_time',
        'order' => 'ASC',
        'meta_query' => array(
            array('key' => 'status', 'value' => 'active', 'compare' => '='),
        ),
        'fields' => 'ids',
    ));
    $results = array();
    foreach ($q->posts as $post_id) {
        $obj = (object) array(
            'id' => $post_id,
            'user_id' => intval(get_post_meta($post_id, 'user_id', true)),
            'signin_time' => get_post_meta($post_id, 'signin_time', true),
            'signout_time' => get_post_meta($post_id, 'signout_time', true),
            'duration_minutes' => (int) get_post_meta($post_id, 'duration_minutes', true),
            'notes' => (string) get_post_meta($post_id, 'notes', true),
            'status' => get_post_meta($post_id, 'status', true),
        );
        $results[] = $obj;
    }
    return $results;
}

/**
 * Get volunteer sessions with pagination
 */
function make_get_volunteer_sessions_paginated($per_page = 20, $offset = 0) {
    $q = new WP_Query(array(
        'post_type' => 'volunteer_session',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'offset' => $offset,
        'orderby' => 'meta_value',
        'meta_key' => 'signin_time',
        'order' => 'DESC',
        'fields' => 'ids',
    ));
    $results = array();
    foreach ($q->posts as $post_id) {
        $results[] = (object) array(
            'id' => $post_id,
            'user_id' => intval(get_post_meta($post_id, 'user_id', true)),
            'signin_time' => get_post_meta($post_id, 'signin_time', true),
            'signout_time' => get_post_meta($post_id, 'signout_time', true),
            'duration_minutes' => (int) get_post_meta($post_id, 'duration_minutes', true),
            'notes' => (string) get_post_meta($post_id, 'notes', true),
            'status' => get_post_meta($post_id, 'status', true),
        );
    }
    return $results;
}

/**
 * Get total volunteer sessions count
 */
function make_get_volunteer_sessions_count() {
    $q = new WP_Query(array(
        'post_type' => 'volunteer_session',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
    ));
    return intval($q->found_posts);
}

/**
 * Get volunteer monthly data for charts
 */
function make_get_volunteer_monthly_data($months = 12) {
    // Load completed sessions in the last $months months and aggregate client-side
    $cutoff = date('Y-m-d H:i:s', strtotime("-$months months"));
    $q = new WP_Query(array(
        'post_type' => 'volunteer_session',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            'relation' => 'AND',
            array('key' => 'status', 'value' => 'completed', 'compare' => '='),
            array('key' => 'signin_time', 'value' => $cutoff, 'compare' => '>=', 'type' => 'DATETIME'),
        ),
    ));

    $buckets = array(); // 'Y-m' => minutes
    foreach ($q->posts as $post_id) {
        $signin = get_post_meta($post_id, 'signin_time', true);
        $mins = (int) get_post_meta($post_id, 'duration_minutes', true);
        if (!$signin) { continue; }
        $ym = date('Y-m', strtotime($signin));
        if (!isset($buckets[$ym])) { $buckets[$ym] = 0; }
        $buckets[$ym] += $mins;
    }

    ksort($buckets);
    $labels = array();
    $hours = array();
    foreach ($buckets as $ym => $mins) {
        $labels[] = date('M Y', strtotime($ym . '-01'));
        $hours[] = round($mins / 60, 1);
    }

    return array('labels' => $labels, 'hours' => $hours);
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
    
    $sessions_exists = $wpdb->get_var("SHOW TABLES LIKE '$sessions_table'") == $sessions_table;
    
    $results['tables'] = array(
        'sessions_table_exists' => $sessions_exists
    );
    
    // Test 2: Check if core session functions exist
    $results['functions'] = array(
        'make_start_volunteer_session' => function_exists('make_start_volunteer_session'),
        'make_get_active_volunteer_session' => function_exists('make_get_active_volunteer_session')
    );
    
    // Test 3: Check AJAX handlers
    $results['ajax_handlers'] = array(
        'makeGetVolunteerSession' => has_action('wp_ajax_makeGetVolunteerSession'),
        'makeVolunteerSignOut' => has_action('wp_ajax_makeVolunteerSignOut')
    );
    
    return $results;
}

/**
 * Get session details for modal display
 */
function make_get_session_details_for_modal($session_id) {
    $post = get_post($session_id);
    if (!$post || $post->post_type !== 'volunteer_session') {
        return false;
    }

    $user_id = intval(get_post_meta($post->ID, 'user_id', true));
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }

    $signin_time = get_post_meta($post->ID, 'signin_time', true);
    $signout_time = get_post_meta($post->ID, 'signout_time', true);
    $duration_minutes = (int) get_post_meta($post->ID, 'duration_minutes', true);

    $signin_time_input = '';
    $signout_time_input = '';
    $duration_display = 'Ongoing';

    if ($signin_time) {
        $signin_dt = new DateTime($signin_time);
        $signin_time_input = $signin_dt->format('Y-m-d\TH:i');
    }
    if ($signout_time) {
        $signout_dt = new DateTime($signout_time);
        $signout_time_input = $signout_dt->format('Y-m-d\TH:i');
        if ($duration_minutes) {
            $hours = floor($duration_minutes / 60);
            $minutes = $duration_minutes % 60;
            $duration_display = $hours . 'h ' . $minutes . 'm';
        }
    }

    $status = get_post_meta($post->ID, 'status', true) ?: 'active';

    return array(
        'id' => $post->ID,
        'volunteer_name' => $user->display_name,
        'volunteer_email' => $user->user_email,
        'status' => $status,
        'status_label' => ucfirst($status),
        'signin_time_input' => $signin_time_input,
        'signout_time_input' => $signout_time_input,
        'duration_display' => $duration_display,
        'notes' => (string) get_post_meta($post->ID, 'notes', true),
    );
}

/**
 * Update volunteer session times and notes
 */
function make_update_volunteer_session($session_id, $signin_time, $signout_time, $notes = '') {
    $post = get_post($session_id);
    if (!$post || $post->post_type !== 'volunteer_session') {
        return new WP_Error('session_not_found', 'Session not found');
    }

    $signin_dt = new DateTime($signin_time);
    $signout_dt = new DateTime($signout_time);
    if ($signout_dt <= $signin_dt) {
        return new WP_Error('invalid_times', 'Sign-out time must be after sign-in time');
    }

    $duration_minutes = round(($signout_dt->getTimestamp() - $signin_dt->getTimestamp()) / 60);

    update_post_meta($post->ID, 'signin_time', $signin_dt->format('Y-m-d H:i:s'));
    update_post_meta($post->ID, 'signout_time', $signout_dt->format('Y-m-d H:i:s'));
    update_post_meta($post->ID, 'duration_minutes', $duration_minutes);
    update_post_meta($post->ID, 'notes', (string) $notes);
    update_post_meta($post->ID, 'status', 'completed');
    update_post_meta($post->ID, 'updated_at', current_time('mysql'));

    return true;
}
