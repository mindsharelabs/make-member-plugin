<?php
/**
 * Member Sign-In Performance Optimizations
 * 
 * This file contains optimized AJAX endpoints and caching mechanisms
 * to significantly improve member sign-in performance.
 */

// Add optimized member search endpoint
add_action('wp_ajax_makeMemberSearch', 'make_member_search_optimized');
add_action('wp_ajax_nopriv_makeMemberSearch', 'make_member_search_optimized');

/**
 * Optimized member search with server-side filtering and caching
 */
function make_member_search_optimized() {
    if (!isset($_REQUEST['action']) || $_REQUEST['action'] !== 'makeMemberSearch') {
        wp_die('Invalid request');
    }
    
    $search_term = sanitize_text_field($_REQUEST['search'] ?? '');
    $limit = intval($_REQUEST['limit'] ?? 20);
    
    // Require minimum 2 characters for search
    if (strlen($search_term) < 2) {
        wp_send_json_success(array(
            'html' => '<div class="text-center text-muted p-4">Type at least 2 characters to search...</div>',
            'count' => 0
        ));
        return;
    }
    
    // Get cached or fresh member data
    $members = make_get_members_cached($search_term, $limit);
    
    if (empty($members)) {
        wp_send_json_success(array(
            'html' => '<div class="text-center text-muted p-4">No members found matching "' . esc_html($search_term) . '"</div>',
            'count' => 0
        ));
        return;
    }
    
    // Generate HTML for search results
    $html = make_generate_member_search_html($members);
    
    wp_send_json_success(array(
        'html' => $html,
        'count' => count($members)
    ));
}

/**
 * Get members with NO caching - direct database query
 */
function make_get_members_cached($search_term = '', $limit = 20) {
    global $wpdb;
    
    // Optimized query with search filtering
    $search_sql = '';
    $search_params = array();
    
    if (!empty($search_term)) {
        $search_sql = "AND (u.display_name LIKE %s OR u.user_email LIKE %s)";
        $search_params = array(
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%'
        );
    }
    
    $query = $wpdb->prepare("
        SELECT DISTINCT
            u.ID,
            u.display_name,
            u.user_email,
            p.post_status as membership_status,
            p2.post_title as membership_plan,
            p2.ID as membership_plan_id
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->posts} p ON p.post_author = u.ID
        INNER JOIN {$wpdb->posts} p2 ON p2.ID = p.post_parent
        WHERE p.post_type = 'wc_user_membership'
        AND p.post_status IN ('wcm-active', 'wcm-complimentary')
        AND p2.post_type = 'wc_membership_plan'
        {$search_sql}
        ORDER BY u.display_name
        LIMIT %d
    ", array_merge($search_params, array($limit)));
    
    $results = $wpdb->get_results($query);
    
    if (empty($results)) {
        return array();
    }
    
    // Enhance results with additional data
    $members = array();
    foreach ($results as $result) {
        $member_data = array(
            'ID' => $result->ID,
            'display_name' => $result->display_name,
            'user_email' => $result->user_email,
            'membership_status' => $result->membership_status,
            'membership_plan' => $result->membership_plan,
            'membership_plan_id' => $result->membership_plan_id,
            'photo' => get_field('photo', 'user_' . $result->ID),
            'has_waiver' => make_check_form_submission_cached($result->ID, 27, 34),
            'has_agreement' => make_check_form_submission_cached($result->ID, 45, 16)
        );
        
        $members[] = $member_data;
    }
    
    return $members;
}

/**
 * Direct form submission check - NO caching
 */
function make_check_form_submission_cached($user_id, $form_id, $field_id) {
    return make_check_form_submission($user_id, $form_id, $field_id);
}

/**
 * Generate HTML for member search results
 */
function make_generate_member_search_html($members) {
    $html = '<div id="member-search-results">';
    $html .= '<div class="row list">';
    
    foreach ($members as $member) {
        $html .= '<div class="col-6 col-md-4 col-lg-3 mb-3">';
        $html .= make_generate_member_card_html($member);
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Generate optimized member card HTML
 */
function make_generate_member_card_html($member) {
    $html = '<div class="profile-container">';
    $html .= '<span class="email hidden d-none">' . esc_html($member['user_email']) . '</span>';
    
    $html .= '<div class="profile-card mb-3" data-user="' . $member['ID'] . '" data-preloaded="true">';
    
    // Profile image
    // Check if user is currently volunteering to add green glow
    $profile_image_class = 'profile-image';
    // Get active volunteer sessions if not already cached
    static $active_volunteers = null;
    if ($active_volunteers === null) {
        $active_volunteers = array();
        if (function_exists('make_get_active_volunteer_sessions')) {
            $active_sessions = make_get_active_volunteer_sessions();
            foreach ($active_sessions as $session) {
                $active_volunteers[$session->user_id] = true;
            }
        }
    }
    
    if (isset($active_volunteers[$member['ID']])) {
        $profile_image_class .= ' volunteer-signed-in';
    }
    
    if ($member['photo']) {
        $html .= wp_get_attachment_image($member['photo']['ID'], 'small-square', false, array('class' => $profile_image_class));
    } else {
        $html .= '<img class="' . $profile_image_class . '" src="' . MAKESF_URL . '/assets/img/nophoto.jpg" alt="No photo"/>';
    }
    
    // Profile info
    $html .= '<div class="profile-info">';
    $html .= '<h3 class="name">' . esc_html($member['display_name']) . '</h3>';
    $html .= '<span class="membership">' . esc_html($member['membership_plan']) . '</span>';
    
    // Status indicators
    if (!$member['has_waiver']) {
        $html .= '<div class="status-indicator text-danger small">⚠️ No waiver</div>';
    }
    if (!$member['has_agreement']) {
        $html .= '<div class="status-indicator text-warning small">⚠️ No agreement</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Optimized member details endpoint with NO caching
 */
add_action('wp_ajax_makeGetMemberOptimized', 'make_get_member_optimized');
add_action('wp_ajax_nopriv_makeGetMemberOptimized', 'make_get_member_optimized');

function make_get_member_optimized() {
    if (!isset($_REQUEST['action']) || $_REQUEST['action'] !== 'makeGetMemberOptimized') {
        wp_die('Invalid request');
    }
    
    $user_id = intval($_REQUEST['userID'] ?? 0);
    
    if (!$user_id) {
        wp_send_json_error(array('message' => 'Invalid user ID'));
        return;
    }
    
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        wp_send_json_error(array('message' => 'User not found'));
        return;
    }
    
    // Check for active volunteer session FIRST
    if (function_exists('make_get_active_volunteer_session')) {
        $active_session = make_get_active_volunteer_session($user_id);
        if ($active_session) {
            // User has active volunteer session - redirect to sign-out interface
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Make Volunteer: Found active session for user ' . $user_id . ', showing sign-out interface (optimized)');
            }
            
            // Use shared renderer instead of buffered AJAX call
            if (function_exists('make_render_volunteer_signout_interface')) {
                $rendered = make_render_volunteer_signout_interface($user_id, $active_session);
                $first_name = get_user_meta($user_id, 'first_name', true);
                if (!$first_name) { $first_name = preg_split('/\s+/', $user->display_name)[0]; }
                $return = array(
                    'status' => 'volunteer_signout',
                    'html' => $rendered['html'],
                    'greeting_name' => $first_name
                );
                wp_send_json_success($return);
                return;
            }
        }
    }
    
    // No active volunteer session - proceed with normal sign-in flow
    // Check requirements
    $has_waiver = make_check_form_submission_cached($user_id, 27, 34);
    $has_agreement = make_check_form_submission_cached($user_id, 45, 16);
    // Get both active and complimentary memberships
    $active_memberships = wc_memberships_get_user_active_memberships($user_id);
    $complimentary_memberships = wc_memberships_get_user_memberships($user_id, array('status' => 'complimentary'));
    $memberships = array_merge($active_memberships, $complimentary_memberships);
    
    // Validation checks
    if (!$has_waiver) {
        $return = array(
            'html' => '<div class="alert alert-danger text-center"><h1>No Safety Waiver!</h1><h2>Please log into your online profile and sign our safety waiver.</h2></div>',
            'status' => 'failed',
            'code' => 'waiver'
        );
        wp_send_json_success($return);
        return;
    }
    
    if (empty($memberships)) {
        $return = array(
            'html' => '<div class="alert alert-danger text-center"><h1>No Active or Complimentary memberships.</h1><h2>Please start or renew your membership to utilize MAKE Santa Fe</h2></div>',
            'status' => 'failed',
            'code' => 'nomembership'
        );
        wp_send_json_success($return);
        return;
    }
    
    // Generate badge selection HTML (no per-user subheader; global heading handles greeting)
    $html = '<div class="badge-header text-center">';
    $html .= '<h4>Which of your badges are you using today?</h4>';
    $html .= '</div>';
    
    $html .= make_list_sign_in_badges_cached($user);
    
    // Add activity options
    $html .= '<div class="badge-item w-100 text-center" data-badge="volunteer">';
    $html .= '<span class="small"><h3 class="my-2">Volunteering</h3></span>';
    $html .= '</div>';
    
    $html .= '<div class="badge-item w-100 text-center" data-badge="workshop">';
    $html .= '<span class="small"><h3 class="my-2">Attending a Class or Workshop</h3></span>';
    $html .= '</div>';
    
    $html .= '<div class="badge-item w-100 text-center" data-badge="other">';
    $html .= '<span class="small"><h3 class="my-2">Computers, general work area, or yard</h3></span>';
    $html .= '</div>';
    
    $html .= '<div class="badge-footer text-center mt-3">';
    $html .= '<button disabled data-user="' . $user_id . '" class="btn btn-primary btn-lg sign-in-done">Done!</button>';
    $html .= '</div>';
    
    $first_name = get_user_meta($user_id, 'first_name', true);
    if (!$first_name) { $first_name = preg_split('/\s+/', $user->display_name)[0]; }
    $return = array(
        'html' => $html,
        'status' => 'userfound',
        'greeting_name' => $first_name
    );
    
    wp_send_json_success($return);
}

/**
 * Direct badge listing - NO caching
 */
function make_list_sign_in_badges_cached($user) {
    return make_list_sign_in_badges($user);
}

/**
 * Clear member-related caches when memberships change
 */
add_action('wc_memberships_user_membership_saved', 'make_clear_member_caches');
add_action('wc_memberships_user_membership_deleted', 'make_clear_member_caches');

function make_clear_member_caches($user_membership) {
    if (!is_a($user_membership, 'WC_Memberships_User_Membership')) {
        return;
    }
    $user_id = $user_membership->get_user_id();
    
    // Clear specific user caches
    $patterns = array(
        "make_member_details_{$user_id}_*",
        "make_user_badges_{$user_id}_*",
        "make_form_check_{$user_id}_*",
        "make_member_membership_{$user_id}_*"
    );
    
    foreach ($patterns as $pattern) {
        make_clear_transients_by_pattern($pattern);
    }
    
    // Clear search and member list caches (they'll rebuild with new data)
    make_clear_transients_by_pattern('make_member_search_*');
    make_clear_transients_by_pattern('make_all_members_optimized_*');
}

/**
 * Helper function to clear transients by pattern
 */
function make_clear_transients_by_pattern($pattern) {
    global $wpdb;
    
    $pattern = str_replace('*', '%', $pattern);
    $transients = $wpdb->get_col($wpdb->prepare("
        SELECT option_name 
        FROM {$wpdb->options} 
        WHERE option_name LIKE %s
    ", '_transient_' . $pattern));
    
    foreach ($transients as $transient) {
        $key = str_replace('_transient_', '', $transient);
        delete_transient($key);
    }
}

/**
 * Add performance monitoring
 */
function make_log_performance($operation, $start_time, $additional_data = array()) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $execution_time = microtime(true) - $start_time;
    $log_data = array_merge(array(
        'operation' => $operation,
        'execution_time' => round($execution_time * 1000, 2) . 'ms',
        'timestamp' => current_time('mysql')
    ), $additional_data);
    
    error_log('MAKE Performance: ' . json_encode($log_data));
}

/**
 * Optimized version of makeAllGetMembers with caching
 */
add_action('wp_ajax_makeAllGetMembersOptimized', 'make_get_all_members_optimized');
add_action('wp_ajax_nopriv_makeAllGetMembersOptimized', 'make_get_all_members_optimized');

function make_get_all_members_optimized() {
    $start_time = microtime(true);
    
    // Create cache key based on time window (5 minutes)
    $time_window = floor(time() / 300) * 300;
    $cache_key = 'make_all_members_optimized_' . $time_window;
    
    // Try to get from cache first
    $cached_response = get_transient($cache_key);
    if ($cached_response !== false) {
        make_log_performance('get_all_members_cached', $start_time, array('cache_hit' => true));
        wp_send_json_success($cached_response);
        return;
    }
    
    // Get members with optimized query
    $members = make_get_active_members_optimized();
    
    if (!$members) {
        $response = array(
            'html' => '<div class="alert alert-warning text-center">No active members found.</div>',
            'count' => 0
        );
        wp_send_json_success($response);
        return;
    }
    
    // Build HTML with enhanced member cards
    $html = '<div id="member-list">';
    $html .= '<div class="search-container w-50 mb-4 mx-auto">';
    $html .= '<input id="memberSearch" type="text" class="form-control form-control-lg member-search" placeholder="Search by Name or Email" />';
    $html .= '</div>';
    
    $all_members = array();
    $html .= '<div class="row list mt-5 pt-5">';
    
    foreach ($members as $member) {
        $member_obj = get_user_by('ID', $member->user_id);
        
        if (!$member_obj) {
            continue;
        }
        
        // Get cached membership data
        $membership_data = make_get_member_membership_cached($member_obj->ID);
        
        $all_members[] = array(
            'ID' => $member_obj->ID,
            'name' => $member_obj->display_name,
            'memberships' => $membership_data['memberships'],
            'image' => get_avatar_url($member_obj->ID, ['size' => '400'])
        );
        
        $html .= '<div class="col-6 col-md-4">';
        $html .= make_output_profile_container_optimized($member_obj, $membership_data);
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    $response = array(
        'member_count' => count($all_members),
        'members' => $all_members,
        'html' => $html,
        'count' => count($all_members)
    );
    
    // Cache the response for 5 minutes
    set_transient($cache_key, $response, 300);
    
    make_log_performance('get_all_members_optimized', $start_time, array(
        'member_count' => count($all_members),
        'cache_hit' => false
    ));
    
    wp_send_json_success($response);
}

/**
 * Optimized member query
 */
function make_get_active_members_optimized() {
    global $wpdb;
    
    // Single optimized query with all needed data
    return $wpdb->get_results("
        SELECT DISTINCT
            um.user_id,
            u.display_name,
            u.user_email
        FROM {$wpdb->prefix}posts AS p
        LEFT JOIN {$wpdb->prefix}posts AS p2 ON p2.ID = p.post_parent
        LEFT JOIN {$wpdb->prefix}users AS u ON u.id = p.post_author
        LEFT JOIN {$wpdb->prefix}usermeta AS um ON u.id = um.user_id
        WHERE p.post_type = 'wc_user_membership'
        AND p.post_status IN ('wcm-active', 'wcm-complimentary')
        AND p2.post_type = 'wc_membership_plan'
        ORDER BY u.display_name
        LIMIT 999
    ");
}

/**
 * Get cached membership data for a member
 */
function make_get_member_membership_cached($user_id) {
    $cache_key = "make_member_membership_{$user_id}_" . floor(time() / 1800) * 1800; // 30-minute cache
    $cached_data = get_transient($cache_key);
    
    if ($cached_data !== false) {
        return $cached_data;
    }
    
    $memberships = '';
    if (function_exists('wc_memberships_get_user_active_memberships')) {
        $active_memberships = wc_memberships_get_user_active_memberships($user_id);
        $complimentary_memberships = wc_memberships_get_user_memberships($user_id, array('status' => 'complimentary'));
        $all_memberships = array_merge($active_memberships, $complimentary_memberships);
        
        // Remove duplicates by plan_id to avoid showing the same membership twice
        $unique_memberships = array();
        $plan_ids_seen = array();
        if ($all_memberships) {
            foreach ($all_memberships as $membership) {
                $plan_id = $membership->plan_id;
                if (!in_array($plan_id, $plan_ids_seen)) {
                    $plan_ids_seen[] = $plan_id;
                    $unique_memberships[] = $membership;
                }
            }
        }
        
        if ($unique_memberships) {
            foreach ($unique_memberships as $index => $membership) {
                $memberships .= $membership->plan->name;
                if ($index < count($unique_memberships) - 1) {
                    $memberships .= ' & ';
                }
            }
        }
    }
    
    $data = array(
        'memberships' => $memberships,
        'has_active' => !empty($memberships)
    );
    
    // Cache for 30 minutes
    set_transient($cache_key, $data, 1800);
    
    return $data;
}

/**
 * Optimized profile container output
 */
function make_output_profile_container_optimized($user, $membership_data) {
    if (!$user) {
        return '';
    }
    
    $user_info = get_userdata($user->ID);
    $html = '<div class="profile-container">';
    $html .= '<span class="email hidden d-none">' . esc_html($user_info->user_email) . '</span>';
    
    $html .= '<div class="profile-card mb-5" data-user="' . $user->ID . '" data-preloaded="true">';
    
    // Profile image with caching consideration
    $image = get_field('photo', 'user_' . $user->ID);
    // Check if user is currently volunteering to add green glow
    $profile_image_class = 'profile-image';
    // Get active volunteer sessions if not already cached
    static $active_volunteers_optimized = null;
    if ($active_volunteers_optimized === null) {
        $active_volunteers_optimized = array();
        if (function_exists('make_get_active_volunteer_sessions')) {
            $active_sessions = make_get_active_volunteer_sessions();
            foreach ($active_sessions as $session) {
                $active_volunteers_optimized[$session->user_id] = true;
            }
        }
    }
    
    if (isset($active_volunteers_optimized[$user->ID])) {
        $profile_image_class .= ' volunteer-signed-in';
    }
    
    if ($image) {
        $html .= wp_get_attachment_image($image['ID'], 'small-square', false, array('class' => $profile_image_class));
    } else {
        $html .= '<img class="' . $profile_image_class . '" src="' . MAKESF_URL . '/assets/img/nophoto.jpg" alt="No photo"/>';
    }
    
    $html .= '<div class="profile-info">';
    $html .= '<h3 class="name">' . esc_html($user->display_name) . '</h3>';
    
    if ($membership_data['has_active']) {
        $html .= '<span class="membership">' . esc_html($membership_data['memberships']) . '</span>';
    } else {
        $html .= '<span class="membership none">No Active Membership</span>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
