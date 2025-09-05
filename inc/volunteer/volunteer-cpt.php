<?php
/**
 * Volunteer Custom Post Type and Taxonomy
 * 
 * Registers volunteer_task custom post type and related taxonomy
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_head', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen) {
        return;
    }

    if ($screen->id === 'edit-volunteer_session') {
        echo '<style>
        .column-duration_minutes { width: 110px; }
        </style>';
    }
});

/**
 * Volunteer Sessions Repository (CPT-first with legacy DB fallback)
 * Centralizes all reads/writes for sessions so we can migrate incrementally.
 */
if (!class_exists('Make_Volunteer_Session_Repository')) {
    class Make_Volunteer_Session_Repository {
        // Meta keys used on volunteer_session posts
        const META_USER_ID = 'user_id';
        const META_SIGNIN = 'signin_time';
        const META_SIGNOUT = 'signout_time';
        const META_DURATION = 'duration_minutes';
        const META_NOTES = 'notes';
        const META_STATUS = 'status';
        const META_CREATED = 'created_at';
        const META_UPDATED = 'updated_at';
        const META_LEGACY_ID = '_legacy_session_id';

        // Feature flag: read/write CPT only. Default false to keep fallback during staging.
        public static function use_cpt_only(): bool {
            // Force CPT-only mode: do not read/write legacy DB
            return true;
        }

        // Start a session: creates CPT entry if CPT-only or preferred; otherwise writes legacy row and mirrors to CPT.
        public static function start_session($user_id) {
            $user_id = intval($user_id);
            if (!$user_id) {
                return new WP_Error('invalid_user', 'Invalid user ID');
            }
            // Prevent duplicates
            $active = self::find_active_session_for_user($user_id);
            if ($active) {
                return new WP_Error('session_exists', 'User already has an active volunteer session');
            }

            $now = current_time('mysql');
            $title = self::compose_session_title($user_id, $now);

            // Primary write: CPT
            $post_id = wp_insert_post(array(
                'post_type' => 'volunteer_session',
                'post_status' => 'publish',
                'post_title' => $title,
                'post_author' => $user_id,
            ), true);
            if (is_wp_error($post_id)) {
                return $post_id;
            }

            update_post_meta($post_id, self::META_USER_ID, $user_id);
            update_post_meta($post_id, self::META_SIGNIN, $now);
            update_post_meta($post_id, self::META_STATUS, 'active');
            update_post_meta($post_id, self::META_CREATED, $now);
            update_post_meta($post_id, self::META_UPDATED, $now);

            // Mirror to legacy DB if not CPT-only to keep tools/reports working during migration
            if (!self::use_cpt_only() && function_exists('make_verify_volunteer_tables') && make_verify_volunteer_tables()) {
                global $wpdb;
                $table = $wpdb->prefix . 'volunteer_sessions';
                $ok = $wpdb->insert($table, array(
                    'user_id' => $user_id,
                    'signin_time' => $now,
                    'status' => 'active',
                ), array('%d','%s','%s'));
                if ($ok !== false) {
                    $legacy_id = intval($wpdb->insert_id);
                    update_post_meta($post_id, self::META_LEGACY_ID, $legacy_id);
                }
            }

            return $post_id;
        }

        // End a session by ID (supports legacy id or CPT id). Returns summary array compatible with existing AJAX responses.
        public static function end_session($session_identifier, $tasks = array(), $notes = '') {
            $session = self::get_session_by_identifier($session_identifier);
            if (!$session) {
                return new WP_Error('session_not_found', 'Active session not found');
            }
            if ($session['status'] !== 'active') {
                return new WP_Error('session_not_active', 'Session is not active');
            }

            $now = current_time('mysql');
            // Compute duration using WP timezone, but store integer minutes
            $tz = wp_timezone();
            $signin = new DateTime($session['signin_time'], $tz);
            $signout = new DateTime($now, $tz);
            $diff = $signin->diff($signout);
            $duration_minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

            // Update CPT
            update_post_meta($session['post_id'], self::META_SIGNOUT, $now);
            update_post_meta($session['post_id'], self::META_DURATION, $duration_minutes);
            update_post_meta($session['post_id'], self::META_NOTES, sanitize_textarea_field($notes));
            update_post_meta($session['post_id'], self::META_STATUS, 'completed');
            update_post_meta($session['post_id'], self::META_UPDATED, $now);

            // Mirror to legacy DB if linked or if not CPT-only
            if (!self::use_cpt_only() && function_exists('make_verify_volunteer_tables') && make_verify_volunteer_tables()) {
                global $wpdb;
                $table = $wpdb->prefix . 'volunteer_sessions';
                $legacy_id = isset($session['legacy_id']) ? intval($session['legacy_id']) : 0;
                if ($legacy_id > 0) {
                    $wpdb->update($table, array(
                        'signout_time' => $now,
                        'duration_minutes' => $duration_minutes,
                        'notes' => sanitize_textarea_field($notes),
                        'status' => 'completed',
                    ), array('id' => $legacy_id), array('%s','%d','%s','%s','%s'), array('%d'));
                } else {
                    // Try to find matching legacy row by user + signin_time if no mapping, then update
                    $maybe_legacy = self::find_matching_legacy_row($session['user_id'], $session['signin_time']);
                    if ($maybe_legacy) {
                        $wpdb->update($table, array(
                            'signout_time' => $now,
                            'duration_minutes' => $duration_minutes,
                            'notes' => sanitize_textarea_field($notes),
                            'status' => 'completed',
                        ), array('id' => $maybe_legacy->id), array('%s','%d','%s','%s','%s'), array('%d'));
                        update_post_meta($session['post_id'], self::META_LEGACY_ID, intval($maybe_legacy->id));
                    }
                }
            }

            return array(
                'session_id' => $session['post_id'], // maintain shape; original returned legacy id, but consumers use fields below
                'duration_minutes' => $duration_minutes,
                'signin_time' => $session['signin_time'],
                'signout_time' => $now,
            );
        }

        // Find active session for a user; returns normalized associative array or null.
        public static function find_active_session_for_user($user_id) {
            $user_id = intval($user_id);
            // Query CPT first
            $q = new WP_Query(array(
                'post_type' => 'volunteer_session',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(
                    'relation' => 'AND',
                    array('key' => self::META_USER_ID, 'value' => $user_id, 'compare' => '='),
                    array('key' => self::META_STATUS, 'value' => 'active', 'compare' => '='),
                ),
                'fields' => 'ids',
            ));
            if (!empty($q->posts)) {
                $post_id = $q->posts[0];
                return self::normalize_cpt_session($post_id);
            }

            // Fallback to legacy DB only if CPT-only flag is off
            if (!self::use_cpt_only() && function_exists('make_get_active_volunteer_session')) {
                $legacy = make_get_active_volunteer_session($user_id);
                if ($legacy) {
                    // Ensure there is a CPT shadow record for this legacy row to keep admin consistent
                    $shadow = self::ensure_cpt_shadow_from_legacy($legacy);
                    return $shadow ?: self::normalize_legacy_row($legacy);
                }
            }

            return null;
        }

        // Helpers

        private static function compose_session_title($user_id, $signin_mysql) {
            $user = get_user_by('ID', $user_id);
            $user_name = $user ? $user->display_name : 'User ' . $user_id;
            return sprintf('%s — %s', $user_name, date('M j, Y g:i a', strtotime($signin_mysql)));
        }

        private static function normalize_cpt_session($post_id) {
            $user_id = intval(get_post_meta($post_id, self::META_USER_ID, true));
            $signin_time = get_post_meta($post_id, self::META_SIGNIN, true);
            $signout_time = get_post_meta($post_id, self::META_SIGNOUT, true);
            $status = get_post_meta($post_id, self::META_STATUS, true) ?: 'active';
            $duration = get_post_meta($post_id, self::META_DURATION, true);
            $legacy_id = get_post_meta($post_id, self::META_LEGACY_ID, true);

            return array(
                'post_id' => $post_id,
                'legacy_id' => $legacy_id ? intval($legacy_id) : null,
                'user_id' => $user_id,
                'signin_time' => $signin_time,
                'signout_time' => $signout_time,
                'duration_minutes' => $duration !== '' ? intval($duration) : null,
                'status' => $status,
            );
        }

        private static function normalize_legacy_row($row) {
            return array(
                'post_id' => null,
                'legacy_id' => intval($row->id),
                'user_id' => intval($row->user_id),
                'signin_time' => $row->signin_time,
                'signout_time' => $row->signout_time,
                'duration_minutes' => isset($row->duration_minutes) ? intval($row->duration_minutes) : null,
                'status' => $row->status,
            );
        }

        private static function get_session_by_identifier($identifier) {
            // If numeric, it could be a CPT post ID or legacy ID. Prefer CPT post if exists.
            $id = intval($identifier);
            if ($id > 0) {
                // CPT check
                $post = get_post($id);
                if ($post && $post->post_type === 'volunteer_session') {
                    return self::normalize_cpt_session($post->ID);
                }
                // Legacy check by ID, try to map to CPT via meta
                $by_meta = new WP_Query(array(
                    'post_type' => 'volunteer_session',
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'meta_query' => array(
                        array('key' => self::META_LEGACY_ID, 'value' => $id, 'compare' => '='),
                    ),
                ));
                if (!empty($by_meta->posts)) {
                    return self::normalize_cpt_session($by_meta->posts[0]);
                }
                // As last resort, legacy row
                if (!self::use_cpt_only()) {
                    global $wpdb;
                    $table = $wpdb->prefix . 'volunteer_sessions';
                    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
                    if ($row) {
                        // Create CPT shadow so next time it's found via CPT
                        $shadow = self::ensure_cpt_shadow_from_legacy($row);
                        return $shadow ?: self::normalize_legacy_row($row);
                    }
                }
            }
            return null;
        }

        private static function ensure_cpt_shadow_from_legacy($legacy_row) {
            // Look for existing CPT mapped by legacy id
            $existing = new WP_Query(array(
                'post_type' => 'volunteer_session',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => array(
                    array('key' => self::META_LEGACY_ID, 'value' => intval($legacy_row->id), 'compare' => '='),
                ),
            ));
            if (!empty($existing->posts)) {
                return self::normalize_cpt_session($existing->posts[0]);
            }

            // Create CPT shadow
            $user_id = intval($legacy_row->user_id);
            $title = self::compose_session_title($user_id, $legacy_row->signin_time);
            $post_id = wp_insert_post(array(
                'post_type' => 'volunteer_session',
                'post_status' => 'publish',
                'post_title' => $title,
                'post_author' => $user_id,
            ), true);
            if (is_wp_error($post_id)) {
                return null;
            }
            update_post_meta($post_id, self::META_USER_ID, $user_id);
            update_post_meta($post_id, self::META_SIGNIN, $legacy_row->signin_time);
            if (!empty($legacy_row->signout_time)) {
                update_post_meta($post_id, self::META_SIGNOUT, $legacy_row->signout_time);
            }
            if (!is_null($legacy_row->duration_minutes)) {
                update_post_meta($post_id, self::META_DURATION, intval($legacy_row->duration_minutes));
            }
            if (!empty($legacy_row->notes)) {
                update_post_meta($post_id, self::META_NOTES, $legacy_row->notes);
            }
            update_post_meta($post_id, self::META_STATUS, $legacy_row->status === 'completed' ? 'completed' : 'active');
            update_post_meta($post_id, self::META_CREATED, $legacy_row->created_at ?: $legacy_row->signin_time);
            update_post_meta($post_id, self::META_UPDATED, $legacy_row->updated_at ?: current_time('mysql'));
            update_post_meta($post_id, self::META_LEGACY_ID, intval($legacy_row->id));

            return self::normalize_cpt_session($post_id);
        }

        private static function find_matching_legacy_row($user_id, $signin_time_mysql) {
            global $wpdb;
            $table = $wpdb->prefix . 'volunteer_sessions';
            // Exact match first
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d AND signin_time = %s LIMIT 1",
                $user_id, $signin_time_mysql
            ));
            if ($row) return $row;
            // Fuzzy within 2 minutes for edge cases
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d AND ABS(TIMESTAMPDIFF(SECOND, signin_time, %s)) <= 120 ORDER BY ABS(TIMESTAMPDIFF(SECOND, signin_time, %s)) ASC LIMIT 1",
                $user_id, $signin_time_mysql, $signin_time_mysql
            ));
            return $row ?: null;
        }

        /**
         * Compute volunteer hours summary for a user over a period.
         * Period: 'week' | 'month' | 'year' | 'all'
         */
        public static function get_hours(int $user_id, string $period = 'month'): array {
            if (!$user_id) {
                return array(
                    'session_count' => 0,
                    'total_hours' => 0,
                    'total_minutes' => 0,
                    'avg_hours' => 0,
                    'first_session' => null,
                    'last_session' => null
                );
            }
            $cutoff = null;
            switch ($period) {
                case 'week':  $cutoff = date('Y-m-d H:i:s', strtotime('-1 week')); break;
                case 'month': $cutoff = date('Y-m-d H:i:s', strtotime('-1 month')); break;
                case 'year':  $cutoff = date('Y-m-d H:i:s', strtotime('-1 year')); break;
                case 'all':
                default: $cutoff = null; break;
            }

            $meta_query = array(
                'relation' => 'AND',
                array('key' => self::META_USER_ID, 'value' => $user_id, 'compare' => '=')
            );
            if ($cutoff) {
                $meta_query[] = array('key' => self::META_SIGNIN, 'value' => $cutoff, 'compare' => '>=', 'type' => 'DATETIME');
            }
            $q = new WP_Query(array(
                'post_type' => 'volunteer_session',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => $meta_query,
            ));

            $total_minutes = 0;
            $count = 0;
            $first = null;
            $last = null;
            foreach ($q->posts as $pid) {
                $status = get_post_meta($pid, self::META_STATUS, true);
                if ($status !== 'completed') continue;
                $count++;
                $mins = (int) get_post_meta($pid, self::META_DURATION, true);
                $total_minutes += $mins;
                $sign_in = get_post_meta($pid, self::META_SIGNIN, true);
                if ($sign_in) {
                    if (!$first || $sign_in < $first) $first = $sign_in;
                    if (!$last || $sign_in > $last) $last = $sign_in;
                }
            }
            $avg_minutes = $count > 0 ? $total_minutes / $count : 0;
            return array(
                'session_count' => $count,
                'total_hours' => round($total_minutes / 60, 2),
                'total_minutes' => $total_minutes,
                'avg_hours' => round($avg_minutes / 60, 2),
                'first_session' => $first,
                'last_session' => $last
            );
        }

        /**
         * Overall volunteer statistics within a period.
         */
        public static function get_stats(string $period = 'month'): array {
            $cutoff = null;
            switch ($period) {
                case 'week':  $cutoff = date('Y-m-d H:i:s', strtotime('-1 week')); break;
                case 'month': $cutoff = date('Y-m-d H:i:s', strtotime('-1 month')); break;
                case 'year':  $cutoff = date('Y-m-d H:i:s', strtotime('-1 year')); break;
                default: $cutoff = null; break;
            }

            $meta_query = array(
                array('key' => self::META_STATUS, 'value' => 'completed', 'compare' => '=')
            );
            if ($cutoff) {
                $meta_query[] = array('key' => self::META_SIGNIN, 'value' => $cutoff, 'compare' => '>=', 'type' => 'DATETIME');
            }
            $q = new WP_Query(array(
                'post_type' => 'volunteer_session',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => $meta_query,
            ));

            $total_minutes = 0;
            $unique_users = array();
            foreach ($q->posts as $pid) {
                $total_minutes += (int) get_post_meta($pid, self::META_DURATION, true);
                $uid = (int) get_post_meta($pid, self::META_USER_ID, true);
                if ($uid) { $unique_users[$uid] = true; }
            }

            $active_q = new WP_Query(array(
                'post_type' => 'volunteer_session',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => array(
                    array('key' => self::META_STATUS, 'value' => 'active', 'compare' => '=')
                ),
            ));

            return array(
                'total_sessions' => count($q->posts),
                'unique_volunteers' => count($unique_users),
                'total_hours' => round($total_minutes / 60, 2),
                'avg_session_hours' => count($q->posts) ? round(($total_minutes / count($q->posts)) / 60, 2) : 0,
                'active_sessions' => (int) $active_q->found_posts,
            );
        }

        /**
         * Leaderboard: top volunteers by total minutes within a period.
         */
        public static function get_leaderboard(string $period = 'month', int $limit = 10): array {
            $cutoff = null;
            switch ($period) {
                case 'week':  $cutoff = date('Y-m-d H:i:s', strtotime('-1 week')); break;
                case 'month': $cutoff = date('Y-m-d H:i:s', strtotime('-1 month')); break;
                case 'year':  $cutoff = date('Y-m-d H:i:s', strtotime('-1 year')); break;
                default: $cutoff = null; break;
            }
            $meta_query = array(
                array('key' => self::META_STATUS, 'value' => 'completed', 'compare' => '=')
            );
            if ($cutoff) {
                $meta_query[] = array('key' => self::META_SIGNIN, 'value' => $cutoff, 'compare' => '>=', 'type' => 'DATETIME');
            }

            $q = new WP_Query(array(
                'post_type' => 'volunteer_session',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => $meta_query,
            ));

            $by_user = array();
            foreach ($q->posts as $pid) {
                $uid = (int) get_post_meta($pid, self::META_USER_ID, true);
                if (!$uid) continue;
                if (!isset($by_user[$uid])) {
                    $by_user[$uid] = array('session_count' => 0, 'total_minutes' => 0);
                }
                $by_user[$uid]['session_count']++;
                $by_user[$uid]['total_minutes'] += (int) get_post_meta($pid, self::META_DURATION, true);
            }

            uasort($by_user, function($a, $b) { return $b['total_minutes'] <=> $a['total_minutes']; });
            $leaderboard = array();
            $i = 0;
            foreach ($by_user as $uid => $stats) {
                $user = get_user_by('ID', $uid);
                if (!$user) continue;
                $leaderboard[] = array(
                    'user_id' => $uid,
                    'name' => $user->display_name,
                    'session_count' => (int) $stats['session_count'],
                    'total_hours' => round($stats['total_minutes'] / 60, 2),
                    'avg_hours' => $stats['session_count'] ? round(($stats['total_minutes'] / $stats['session_count']) / 60, 2) : 0,
                );
                $i++;
                if ($i >= $limit) break;
            }
            return $leaderboard;
        }
    }
}

// Admin setting to toggle CPT-only mode after validation on staging
add_action('admin_init', function () {
    register_setting('make_volunteer_settings_group', 'make_volunteer_cpt_only_mode');
});
/**
 * Register volunteer_session custom post type (hidden, admin-only)
 */
function make_register_volunteer_session_cpt() {
    $labels = array(
        'name'               => _x('Volunteer Sessions', 'Post Type General Name', 'makesf'),
        'singular_name'      => _x('Volunteer Session', 'Post Type Singular Name', 'makesf'),
        'menu_name'          => __('Volunteer Sessions', 'makesf'),
        'name_admin_bar'     => __('Volunteer Session', 'makesf'),
        'add_new'            => __('Add New', 'makesf'),
        'add_new_item'       => __('Add New Session', 'makesf'),
        'new_item'           => __('New Session', 'makesf'),
        'edit_item'          => __('Edit Session', 'makesf'),
        'view_item'          => __('View Session', 'makesf'),
        'all_items'          => __('All Sessions', 'makesf'),
        'search_items'       => __('Search Sessions', 'makesf'),
        'not_found'          => __('No sessions found', 'makesf'),
        'not_found_in_trash' => __('No sessions found in Trash', 'makesf'),
    );

    $args = array(
        'label'               => __('Volunteer Session', 'makesf'),
        'description'         => __('Volunteer sign-in sessions', 'makesf'),
        'labels'              => $labels,
        'supports'            => array('title', 'custom-fields', 'revisions'),
        'hierarchical'        => false,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => false, // We will link it under Volunteering menu
        'menu_icon'           => 'dashicons-clock',
        'show_in_admin_bar'   => false,
        'show_in_nav_menus'   => false,
        'can_export'          => true,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'capability_type'     => 'volunteer_session',
        'map_meta_cap'        => true,
        'capabilities'        => array(
            'edit_post'              => 'edit_volunteer_session',
            'read_post'              => 'read_volunteer_session',
            'delete_post'            => 'delete_volunteer_session',
            'edit_posts'             => 'edit_volunteer_sessions',
            'edit_others_posts'      => 'edit_others_volunteer_sessions',
            'publish_posts'          => 'publish_volunteer_sessions',
            'read_private_posts'     => 'read_private_volunteer_sessions',
            'delete_posts'           => 'delete_volunteer_sessions',
            'delete_private_posts'   => 'delete_private_volunteer_sessions',
            'delete_published_posts' => 'delete_published_volunteer_sessions',
            'delete_others_posts'    => 'delete_others_volunteer_sessions',
            'edit_private_posts'     => 'edit_private_volunteer_sessions',
            'edit_published_posts'   => 'edit_published_volunteer_sessions',
            'create_posts'           => 'edit_volunteer_sessions',
        ),
        'show_in_rest'        => false,
    );

    register_post_type('volunteer_session', $args);
}
add_action('init', 'make_register_volunteer_session_cpt', 0);

/**
 * Admin Columns: render duration_minutes (stored as minutes) as Hh Mm in list table,
 * without changing stored value. Also provide a readable Status.
 */
add_filter('manage_volunteer_session_posts_columns', function($columns) {
    // Ensure duration column is visible and labeled nicely
    if (isset($columns['date'])) {
        // Insert before 'date'
        $new = array();
        foreach ($columns as $key => $label) {
            if ($key === 'duration_minutes') {
                // If Admin Columns already added it, keep position
                $new[$key] = $label;
            } elseif ($key === 'date') {
                // If duration not present, add it right before date
                if (!isset($columns['duration_minutes'])) {
                    $new['duration_minutes'] = __('Duration', 'makesf');
                }
                $new[$key] = $label;
            } else {
                $new[$key] = $label;
            }
        }
        return $new;
    }
    // Fallback: add if missing
    if (!isset($columns['duration_minutes'])) {
        $columns['duration_minutes'] = __('Duration', 'makesf');
    }
    return $columns;
}, 20);

add_action('manage_volunteer_session_posts_custom_column', function($column, $post_id) {
    if ($column === 'duration_minutes') {
        $mins = (int) get_post_meta($post_id, 'duration_minutes', true);
        if ($mins > 0) {
            $h = floor($mins / 60);
            $m = $mins % 60;
            if ($h > 0 && $m > 0) {
                echo esc_html($h . 'h ' . $m . 'm');
            } elseif ($h > 0) {
                echo esc_html($h . 'h');
            } else {
                echo esc_html($m . 'm');
            }
        } else {
            // If active or no duration yet
            $status = get_post_meta($post_id, 'status', true);
            echo $status === 'active' ? esc_html__('Ongoing', 'makesf') : esc_html__('0m', 'makesf');
        }
    }
}, 10, 2);

// Make duration column sortable by the underlying integer meta
add_filter('manage_edit-volunteer_session_sortable_columns', function($columns) {
    $columns['duration_minutes'] = 'duration_minutes';
    return $columns;
});

add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'volunteer_session') return;
    $orderby = $query->get('orderby');
    if ($orderby === 'duration_minutes') {
        $query->set('meta_key', 'duration_minutes');
        $query->set('orderby', 'meta_value_num');
    }
}, 10);

/**
 * Add volunteer_session capabilities to admin/editor
 */
function make_add_volunteer_session_capabilities() {
    $admin = get_role('administrator');
    $editor = get_role('editor');
    $caps = array(
        'edit_volunteer_session',
        'read_volunteer_session',
        'delete_volunteer_session',
        'edit_volunteer_sessions',
        'edit_others_volunteer_sessions',
        'publish_volunteer_sessions',
        'read_private_volunteer_sessions',
        'delete_volunteer_sessions',
        'delete_private_volunteer_sessions',
        'delete_published_volunteer_sessions',
        'delete_others_volunteer_sessions',
        'edit_private_volunteer_sessions',
        'edit_published_volunteer_sessions',
    );
    foreach (array($admin, $editor) as $role) {
        if ($role) {
            foreach ($caps as $cap) {
                $role->add_cap($cap);
            }
        }
    }
}
register_activation_hook(MAKESF_PLUGIN_FILE, 'make_add_volunteer_session_capabilities');
add_action('init', function() {
    static $session_caps_added = false;
    if (!$session_caps_added) {
        make_add_volunteer_session_capabilities();
        $session_caps_added = true;
    }
}, 12);

/**
 * Optional: ACF field group for volunteer_session to aid Admin Columns mapping
 * Uses basic field keys and allows Admin Columns to expose/edit meta.
 */
function make_register_volunteer_session_fields() {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }
    acf_add_local_field_group(array(
        'key' => 'group_volunteer_session_fields',
        'title' => 'Volunteer Session Details',
        'fields' => array(
            array('key' => 'field_vs_user_id','label' => 'User ID','name' => 'user_id','type' => 'number','required' => 1),
            array('key' => 'field_vs_signin_time','label' => 'Sign In','name' => 'signin_time','type' => 'date_time_picker','display_format' => 'M j, Y g:i a','return_format' => 'Y-m-d H:i:s','first_day' => 1),
            array('key' => 'field_vs_signout_time','label' => 'Sign Out','name' => 'signout_time','type' => 'date_time_picker','display_format' => 'M j, Y g:i a','return_format' => 'Y-m-d H:i:s','first_day' => 1),
            array('key' => 'field_vs_duration_minutes','label' => 'Duration (minutes)','name' => 'duration_minutes','type' => 'number'),
            array('key' => 'field_vs_notes','label' => 'Notes','name' => 'notes','type' => 'textarea'),
            array('key' => 'field_vs_status','label' => 'Status','name' => 'status','type' => 'select','choices' => array('active' => 'Active','completed' => 'Completed'),'default_value' => 'active','ui' => 1),
            array('key' => 'field_vs_created_at','label' => 'Created At','name' => 'created_at','type' => 'date_time_picker','display_format' => 'M j, Y g:i a','return_format' => 'Y-m-d H:i:s','first_day' => 1),
            array('key' => 'field_vs_updated_at','label' => 'Updated At','name' => 'updated_at','type' => 'date_time_picker','display_format' => 'M j, Y g:i a','return_format' => 'Y-m-d H:i:s','first_day' => 1),
            array('key' => 'field_vs_legacy_id','label' => 'Legacy Session ID','name' => '_legacy_session_id','type' => 'number','instructions' => 'Original ID from wp_volunteer_sessions'),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'volunteer_session',
                ),
            ),
        ),
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'active' => true,
    ));
}
add_action('acf/init', 'make_register_volunteer_session_fields');
