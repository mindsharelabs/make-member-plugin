<?php
/**
 * Volunteer Admin Interface
 * 
 * Handles admin dashboard, reports, and volunteer management
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add WP_List_Table implementations for Sessions to use native WordPress tables
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Volunteer Sessions List Table (WP_List_Table) - CPT only
 */
class Make_Volunteer_Sessions_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular' => 'volunteer_session',
            'plural'   => 'volunteer_sessions',
            'ajax'     => false,
        ));
    }

    public function get_columns() {
        return array(
            'cb'            => '<input type="checkbox" />',
            'id'            => 'ID',
            'volunteer'     => 'Volunteer',
            'signin_time'   => 'Sign In',
            'signout_time'  => 'Sign Out',
            'duration'      => 'Duration',
            'status'        => 'Status',
        );
    }

    protected function get_sortable_columns() {
        return array(
            'id'          => array('id', false),
            'signin_time' => array('signin_time', true),
            'signout_time'=> array('signout_time', false),
            'status'      => array('status', false),
            'duration'    => array('duration_minutes', false),
        );
    }

    protected function column_cb($item) {
        return sprintf('<label class="screen-reader-text" for="session_%1$d">Select session %1$d</label><input type="checkbox" id="session_%1$d" name="session_ids[]" value="%1$d" />', $item->id);
    }

    protected function column_id($item) {
        $view_url = add_query_arg(array('page' => 'volunteer-sessions', 'view' => (int)$item->id), admin_url('admin.php'));
        $actions = array();

        if ($item->status === 'active') {
            $end_url = wp_nonce_url(
                add_query_arg(array(
                    'page' => 'volunteer-sessions',
                    'action' => 'end',
                    'session_id' => (int)$item->id,
                ), admin_url('admin.php')),
                'make_end_session_' . (int)$item->id
            );
            $actions['end'] = sprintf('<a href="%s">End</a>', esc_url($end_url));
        }

        $details_url = wp_nonce_url(
            add_query_arg(array(
                'page' => 'volunteer-sessions',
                'action' => 'details',
                'session_id' => (int)$item->id,
            ), admin_url('admin.php')),
            'make_session_details_' . (int)$item->id
        );
        $actions['view'] = sprintf('<a href="%s">View</a>', esc_url($details_url));

        return sprintf('<strong>%d</strong> %s', (int)$item->id, $this->row_actions($actions));
    }

    protected function column_volunteer($item) {
        $user = get_user_by('ID', $item->user_id);
        if (!$user) return 'Unknown User';
        $profile_url = get_edit_user_link($item->user_id);
        return sprintf('<a href="%s">%s</a><br><span class="description">%s</span>',
            esc_url($profile_url),
            esc_html($user->display_name),
            esc_html($user->user_email)
        );
    }

    protected function column_signin_time($item) {
        $dt = $item->signin_time ? new DateTime($item->signin_time) : null;
        return $dt ? esc_html($dt->format('M j, Y g:i A')) : '-';
    }

    protected function column_signout_time($item) {
        if (!$item->signout_time) {
            return '<span class="active-session">Active</span>';
        }
        $dt = new DateTime($item->signout_time);
        return esc_html($dt->format('M j, Y g:i A'));
    }

    protected function column_duration($item) {
        if (!empty($item->duration_minutes)) {
            $mins = (int) $item->duration_minutes;
            $hours = floor($mins / 60);
            $minutes = $mins % 60;
            if ($hours > 0 && $minutes > 0) {
                return esc_html($hours . 'h ' . $minutes . 'm');
            } elseif ($hours > 0) {
                return esc_html($hours . 'h');
            } else {
                return esc_html($minutes . 'm');
            }
        }
        return '<span class="calculating">Ongoing</span>';
    }

    protected function column_status($item) {
        return sprintf(
            '<span class="status-%s">%s</span>',
            esc_attr($item->status),
            esc_html(ucfirst($item->status))
        );
    }

    public function get_bulk_actions() {
        return array(
            'bulk_end' => 'End Sessions',
            'bulk_delete' => 'Delete Sessions',
        );
    }

    public function process_bulk_action() {
        if ('bulk_end' === $this->current_action() && !empty($_POST['session_ids'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = array_map('intval', (array) $_POST['session_ids']);
            foreach ($ids as $id) {
                if (gettype($id) === 'integer' && $id > 0) {
                    if (function_exists('make_end_volunteer_session')) {
                        make_end_volunteer_session($id, array(), 'Ended via bulk action');
                    }
                }
            }
            add_settings_error('make_volunteer_sessions', 'bulk_end_success', 'Selected sessions ended (where applicable).', 'updated');
        }

        if ('bulk_delete' === $this->current_action() && !empty($_POST['session_ids'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = array_map('intval', (array) $_POST['session_ids']);
            foreach ($ids as $id) {
                if (get_post_type($id) === 'volunteer_session') {
                    wp_trash_post($id);
                }
            }
            add_settings_error('make_volunteer_sessions', 'bulk_delete_success', 'Selected sessions moved to Trash.', 'updated');
        }
    }

    public function extra_tablenav($which) {
        if ($which === 'top') {
            $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
            $date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
            echo '<div class="alignleft actions">';
            echo '<select name="status">';
            echo '<option value="">All Sessions</option>';
            echo '<option value="active"' . selected($status, 'active', false) . '>Active</option>';
            echo '<option value="completed"' . selected($status, 'completed', false) . '>Completed</option>';
            echo '</select>';
            echo '<input type="date" name="date" value="' . esc_attr($date) . '" />';
            submit_button('Filter', '', 'filter_action', false);
            echo '</div>';
        }
    }

    public function prepare_items() {
        $per_page = $this->get_items_per_page('make_volunteer_sessions_per_page', 20);
        $current_page = $this->get_pagenum();

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'signin_time';
        $order   = isset($_GET['order']) && in_array(strtoupper($_GET['order']), array('ASC','DESC')) ? strtoupper($_GET['order']) : 'DESC';

        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $date_filter   = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';

        $meta_query = array('relation' => 'AND');
        if ($status_filter && in_array($status_filter, array('active','completed'), true)) {
            $meta_query[] = array('key' => 'status', 'value' => $status_filter, 'compare' => '=');
        }
        if ($date_filter) {
            $meta_query[] = array(
                'key' => 'signin_time',
                'value' => array($date_filter . ' 00:00:00', $date_filter . ' 23:59:59'),
                'compare' => 'BETWEEN',
                'type' => 'DATETIME',
            );
        }

        $args = array(
            'post_type'      => 'volunteer_session',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
            'meta_query'     => $meta_query,
            'fields'         => 'ids',
        );

        $orderby_map = array(
            'id' => 'ID',
            'signin_time' => 'meta_value',
            'signout_time' => 'meta_value',
            'status' => 'meta_value',
            'duration_minutes' => 'meta_value_num',
        );
        if (isset($orderby_map[$orderby])) {
            if ($orderby === 'id') {
                $args['orderby'] = 'ID';
            } else {
                $args['orderby'] = $orderby_map[$orderby];
                $args['meta_key'] = $orderby;
            }
            $args['order'] = $order;
        } else {
            $args['orderby'] = 'date';
            $args['order'] = $order;
        }

        $q = new WP_Query($args);

        $items = array();
        foreach ($q->posts as $post_id) {
            $items[] = (object) array(
                'id' => $post_id,
                'user_id' => intval(get_post_meta($post_id, 'user_id', true)),
                'signin_time' => get_post_meta($post_id, 'signin_time', true),
                'signout_time' => get_post_meta($post_id, 'signout_time', true),
                'duration_minutes' => (int) get_post_meta($post_id, 'duration_minutes', true),
                'tasks_completed' => get_post_meta($post_id, 'tasks_completed', true),
                'status' => get_post_meta($post_id, 'status', true),
            );
        }

        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());
        $this->items = $items;

        $this->set_pagination_args(array(
            'total_items' => (int) $q->found_posts,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($q->found_posts / $per_page),
        ));

        $this->process_bulk_action();
    }
}

/**
 * Initialize volunteer admin interface
 */
function make_volunteer_admin_init() {
    add_action('admin_menu', 'make_volunteer_admin_menu');
    add_action('admin_enqueue_scripts', 'make_volunteer_admin_scripts');
    add_action('wp_ajax_make_volunteer_admin_action', 'make_handle_volunteer_admin_ajax');
    
    // No longer adding volunteer hours to main users table
    // We'll create a separate volunteers page instead
}
add_action('init', 'make_volunteer_admin_init');

/**
 * Add volunteer admin menu
 */
function make_volunteer_admin_menu() {
    add_menu_page(
        'Volunteer Management',
        'Volunteering',
        'manage_options',
        'volunteer-dashboard',
        'make_volunteer_dashboard_page',
        'dashicons-groups',
        30
    );
    
    add_submenu_page(
        'volunteer-dashboard',
        'Volunteer Dashboard',
        'Dashboard',
        'manage_options',
        'volunteer-dashboard',
        'make_volunteer_dashboard_page'
    );
    
    // Sessions submenu points to native CPT list screen for volunteer_session
    add_submenu_page(
        'volunteer-dashboard',
        'Volunteer Sessions',
        'Sessions',
        'manage_options',
        'edit.php?post_type=volunteer_session',
        ''
    );
    
    add_submenu_page(
        'volunteer-dashboard',
        'Volunteers',
        'Volunteers',
        'manage_options',
        'volunteer-volunteers',
        'make_volunteer_volunteers_page'
    );
    
    // Move Settings to Settings menu (Options) for cleaner UX
    add_options_page(
        'Volunteer Settings',
        'Volunteer Settings',
        'manage_options',
        'volunteer-settings',
        'make_volunteer_settings_page'
    );
}

/**
 * Enqueue admin scripts and styles
 */
function make_volunteer_admin_scripts($hook) {
    if (strpos($hook, 'volunteer-') === false && strpos($hook, 'settings_page_volunteer-settings') === false) {
        return;
    }
    
    // Ensure full-width layout for volunteer admin pages and fix duplicate checkbox column spacing
    $custom_css = '
    .wrap.volunteer-admin.full-width, .wrap.volunteer-admin { max-width: none !important; }
    .wrap.volunteer-admin.full-width .wp-list-table { table-layout: auto; width: 100%; }
    /* Remove any accidental second checkbox column spacing by ensuring only first column is checkbox-sized */
    .wrap.volunteer-admin .wp-list-table th.check-column,
    .wrap.volunteer-admin .wp-list-table td.check-column { width: 2.2em; }
    ';
    // Add small polish for settings page
    if (strpos($hook, 'settings_page_volunteer-settings') !== false) {
        $custom_css .= "\n.wrap.volunteer-admin .form-table th{width:240px;}\n.wrap.volunteer-admin .search-box{display:none!important;}\n";
    }
    wp_register_style('makesf-volunteer-admin-inline', false);
    wp_enqueue_style('makesf-volunteer-admin-inline');
    wp_add_inline_style('makesf-volunteer-admin-inline', $custom_css);

    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
    
    wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
    
    wp_register_script('volunteer-admin', MAKESF_URL . 'assets/js/volunteer-admin.js', array('jquery', 'chart-js'), MAKESF_PLUGIN_VERSION, true);
    wp_enqueue_script('volunteer-admin');
    
    wp_localize_script('volunteer-admin', 'volunteerAdmin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('volunteer_admin_nonce')
    ));
    
    wp_register_style('volunteer-admin', MAKESF_URL . 'assets/css/volunteer-admin.css', array(), MAKESF_PLUGIN_VERSION);
    wp_enqueue_style('volunteer-admin');
}

/**
 * Volunteer Dashboard Page
 */
function make_volunteer_dashboard_page() {
    $stats = make_get_volunteer_stats('month');
    $active_sessions = make_get_active_volunteer_sessions();
    
    // Get current month for display
    $current_month = date('F Y');
    
    ?>
    <div class="wrap volunteer-admin full-width">
        <h1>Volunteer Dashboard</h1>
        
        <div class="volunteer-stats-grid">
            <div class="stat-card">
                <h3>Active Sessions</h3>
                <div class="stat-number"><?php echo count($active_sessions); ?></div>
                <div class="stat-label">Currently volunteering</div>
            </div>
            
            <div class="stat-card">
                <h3>This Month</h3>
                <div class="stat-number"><?php echo $stats['total_sessions']; ?></div>
                <div class="stat-label">Total sessions</div>
            </div>
            
            <div class="stat-card">
                <h3>Volunteer Hours</h3>
                <div class="stat-number"><?php echo $stats['total_hours']; ?></div>
                <div class="stat-label">Hours this month</div>
            </div>
            
            <div class="stat-card">
                <h3>Unique Volunteers</h3>
                <div class="stat-number"><?php echo $stats['unique_volunteers']; ?></div>
                <div class="stat-label">This month</div>
            </div>
        </div>
        
        <div class="dashboard-section full-width">
            <h2>Quick Actions</h2>
            <div class="quick-actions-grid">
                <a href="<?php echo admin_url('edit.php?post_type=volunteer_session'); ?>" class="button button-primary button-hero">
                    View All Sessions
                </a>
                <a href="<?php echo admin_url('admin.php?page=volunteer-volunteers'); ?>" class="button button-primary button-hero">
                    View Volunteers
                </a>
                <a href="<?php echo admin_url('post-new.php?post_type=volunteer_session'); ?>" class="button button-secondary">
                    Add New Session
                </a>
                <a href="<?php echo admin_url('options-general.php?page=volunteer-settings'); ?>" class="button button-secondary">
                    Settings
                </a>
            </div>
        </div>
        
        <div class="dashboard-section full-width">
            <h2>Active Volunteer Sessions</h2>
            <?php if (!empty($active_sessions)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Volunteer</th>
                            <th>Started</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_sessions as $session): ?>
                            <?php
                            $user = get_user_by('ID', $session->user_id);
                            try {
                                $tz = wp_timezone();
                            } catch (Exception $e) {
                                $tz = new DateTimeZone('UTC');
                            }
                            try {
                                $signin_time = new DateTime($session->signin_time, $tz);
                            } catch (Exception $e) {
                                $signin_time = new DateTime('now', $tz);
                            }
                            $current_time = new DateTime('now', $tz);
                            $duration = $signin_time->diff($current_time);
                            $duration_minutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
                            $duration_hours = round($duration_minutes / 60, 1);
                            ?>
                            <tr>
                                <td><?php echo esc_html($user ? $user->display_name : 'Unknown User'); ?></td>
                                <td><?php echo esc_html($signin_time->format('M j, Y g:i A')); ?></td>
                                <td><?php echo $duration_hours; ?> hours</td>
                                <td>
                                    <button class="button button-small end-session" data-session="<?php echo $session->id; ?>">
                                        End Session
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No active volunteer sessions.</p>
            <?php endif; ?>
        </div>
        
        <div class="dashboard-section full-width">
            <h2>Monthly Summary - <?php echo esc_html($current_month); ?></h2>
            <div class="monthly-summary">
                <p>
                    <strong>Total Volunteer Hours:</strong> <?php echo $stats['total_hours']; ?> hours<br>
                    <strong>Total Sessions:</strong> <?php echo $stats['total_sessions']; ?><br>
                    <strong>Unique Volunteers:</strong> <?php echo $stats['unique_volunteers']; ?><br>
                    <strong>Average Session Duration:</strong> <?php echo $stats['total_sessions'] > 0 ? round($stats['total_hours'] / $stats['total_sessions'], 1) : 0; ?> hours
                </p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=volunteer-volunteers'); ?>" class="button">
                        View Detailed Volunteer Hours
                    </a>
                </p>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Volunteer Sessions Page
 */
function make_volunteer_sessions_page() {
    if (!class_exists('Make_Volunteer_Sessions_List_Table')) {
        echo '<div class="notice notice-error"><p>Sessions table class not available.</p></div>';
        return;
    }

    // Handle row actions via GET (end, details)
    if (isset($_GET['page']) && $_GET['page'] === 'volunteer-sessions' && isset($_GET['action'])) {
        $action = sanitize_text_field($_GET['action']);
        $session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

        if ($action === 'end' && $session_id && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'make_end_session_' . $session_id)) {
            if (function_exists('make_end_volunteer_session')) {
                $result = make_end_volunteer_session($session_id, array(), 'Ended from sessions table');
                if (is_wp_error($result)) {
                    add_settings_error('make_volunteer_sessions', 'end_error', esc_html($result->get_error_message()), 'error');
                } else {
                    add_settings_error('make_volunteer_sessions', 'end_success', 'Session ended successfully.', 'updated');
                }
            }
        }
        if ($action === 'details' && $session_id && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'make_session_details_' . $session_id)) {
            // For now we just show admin notice; the modal continues to use AJAX
            add_settings_error('make_volunteer_sessions', 'details_info', 'Use the "View" button to open details modal.', 'info');
        }
    }

    echo '<div class="wrap volunteer-admin full-width">';
    echo '<h1>Volunteer Sessions</h1>';

    settings_errors('make_volunteer_sessions');

    echo '<form method="get">';
    // Preserve required params for pagination/sorting
    echo '<input type="hidden" name="page" value="volunteer-sessions" />';

    $table = new Make_Volunteer_Sessions_List_Table();
    $table->prepare_items();
    $table->display();

    echo '</form>';
    echo '</div>';
}

/**
 * Volunteer Volunteers Page
 */
function make_volunteer_volunteers_page() {
    // Get selected month from query parameters, default to current month
    $selected_month = isset($_GET['volunteer_month']) ? sanitize_text_field($_GET['volunteer_month']) : date('Y-m');
    
    // Parse the selected month
    $year = date('Y', strtotime($selected_month . '-01'));
    $month = date('m', strtotime($selected_month . '-01'));
    
    // Calculate start and end dates for the selected month
    $start_date = date('Y-m-01 00:00:00', strtotime($selected_month . '-01'));
    $end_date = date('Y-m-t 23:59:59', strtotime($selected_month . '-01'));
    
    ?>
    <div class="wrap volunteer-admin full-width">
        <h1>Volunteers</h1>
        <p class="description">This table shows only users who have recorded volunteer hours in the selected month.</p>
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" action="" class="volunteer-month-filter">
                    <input type="hidden" name="page" value="volunteer-volunteers" />
                    <select name="volunteer_month" id="volunteer_month">
                        <?php
                        // Generate options for the last 12 months
                        for ($i = 0; $i < 12; $i++) {
                            $month_date = date('Y-m', strtotime("-$i months"));
                            $month_name = date('F Y', strtotime($month_date . '-01'));
                            $selected = ($month_date === $selected_month) ? 'selected="selected"' : '';
                            echo '<option value="' . esc_attr($month_date) . '" ' . $selected . '>' . esc_html($month_name) . '</option>';
                        }
                        ?>
                    </select>
                    <input type="submit" class="button" value="Filter" />
                </form>
                <div class="volunteer-bulk-benefits-actions">
                    <button type="button" class="button button-primary" id="benefits-approve-selected" disabled>Approve Selected</button>
                    <button type="button" class="button" id="benefits-approve-if-meets" disabled title="Approve only those selected who meet the target hours">Approve If Meets Target</button>
                    <button type="button" class="button" id="benefits-deny-selected" disabled>Deny Selected</button>
                </div>
            </div>
            <div class="alignright">
                <span class="displaying-num">
                    Showing volunteer hours for <?php echo esc_html(date('F Y', strtotime($selected_month . '-01'))); ?>
                </span>
            </div>
            <br class="clear" />
        </div>
        
        <?php
        // First, get all users who have volunteer sessions in the selected month
        // This is more efficient than getting all users and checking each one
        $session_args = array(
            'post_type' => 'volunteer_session',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'signin_time',
                    'value' => array($start_date, $end_date),
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME'
                ),
                array(
                    'key' => 'status',
                    'value' => 'completed',
                    'compare' => '='
                )
            ),
            'fields' => 'ids'
        );
        
        $sessions_query = new WP_Query($session_args);
        $user_ids_with_sessions = array();
        
        if ($sessions_query->have_posts()) {
            foreach ($sessions_query->posts as $session_id) {
                $user_id = get_post_meta($session_id, 'user_id', true);
                if ($user_id && !in_array($user_id, $user_ids_with_sessions)) {
                    $user_ids_with_sessions[] = $user_id;
                }
            }
        }
        
        // Resolve MAKE plan id once (if WC Memberships is installed)
        $make_plan_id = 0;
        if (function_exists('wc_memberships_get_membership_plan')) {
            $plan_obj = wc_memberships_get_membership_plan('make-member');
            if ($plan_obj) {
                $make_plan_id = method_exists($plan_obj, 'get_id') ? (int) $plan_obj->get_id() : (int) $plan_obj->id;
            }
        }
        if (!$make_plan_id) {
            $plan_post = get_page_by_path('make-member', OBJECT, 'wc_membership_plan');
            if ($plan_post) $make_plan_id = (int) $plan_post->ID;
        }

        // Prepare table data
        $volunteers_data = array();
        $target_hours = (float) get_option('makesf_volunteer_target_hours', 12);
        
        foreach ($user_ids_with_sessions as $user_id) {
            $user = get_user_by('ID', $user_id);
            if (!$user) continue;
            
            // Get volunteer sessions for this user in the selected month
            $session_args = array(
                'post_type' => 'volunteer_session',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'user_id',
                        'value' => $user_id,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'signin_time',
                        'value' => array($start_date, $end_date),
                        'compare' => 'BETWEEN',
                        'type' => 'DATETIME'
                    ),
                    array(
                        'key' => 'status',
                        'value' => 'completed',
                        'compare' => '='
                    )
                )
            );
            
            $user_sessions_query = new WP_Query($session_args);
            $total_minutes = 0;
            $session_count = 0;
            
            if ($user_sessions_query->have_posts()) {
                while ($user_sessions_query->have_posts()) {
                    $user_sessions_query->the_post();
                    $duration = get_post_meta(get_the_ID(), 'duration_minutes', true);
                    $total_minutes += intval($duration);
                    $session_count++;
                }
            }
            
            wp_reset_postdata();
            
            $meets_target = round($total_minutes / 60, 2) >= $target_hours;
            $benefits_status = makesf_get_benefits_status($user_id, $selected_month);

            // Membership info (status + expiration)
            $membership_status_label = '—';
            $membership_end_label = '—';
            if (function_exists('wc_memberships_get_user_memberships')) {
                $user_memberships = wc_memberships_get_user_memberships($user_id);
                if (!empty($user_memberships)) {
                    $chosen = null;
                    foreach ($user_memberships as $mem) {
                        $pid = method_exists($mem, 'get_plan_id') ? (int) $mem->get_plan_id() : (int) $mem->plan->get_id();
                        if ($make_plan_id && $pid === $make_plan_id) { $chosen = $mem; break; }
                        // fallback pick the first if no plan match
                        if (!$chosen) { $chosen = $mem; }
                    }
                    if ($chosen) {
                        $status_slug = method_exists($chosen, 'get_status') ? $chosen->get_status() : '';
                        $status_slug = is_string($status_slug) ? preg_replace('/^wcm-/', '', $status_slug) : '';
                        $membership_status_label = $status_slug ? ucfirst($status_slug) : '—';
                        $end = method_exists($chosen, 'get_end_date') ? $chosen->get_end_date() : '';
                        if ($end) {
                            $membership_end_label = date_i18n('M j, Y', strtotime($end));
                        }
                    }
                }
            }

            $volunteers_data[] = array(
                'ID' => $user_id,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'hours' => round($total_minutes / 60, 2),
                'sessions' => $session_count,
                'minutes' => $total_minutes,
                'meets_target' => $meets_target,
                'benefits_status' => $benefits_status,
                'membership_status' => $membership_status_label,
                'membership_end' => $membership_end_label,
            );
        }
        
        // Sort by hours descending
        usort($volunteers_data, function($a, $b) {
            return $b['hours'] <=> $a['hours'];
        });
        
        // Calculate totals
        $total_hours = 0;
        $total_sessions = 0;
        foreach ($volunteers_data as $volunteer) {
            $total_hours += $volunteer['hours'];
            $total_sessions += $volunteer['sessions'];
        }
        
        // Display the table
        if (!empty($volunteers_data)) {
            ?>
            <table class="wp-list-table widefat fixed striped users" id="volunteers-benefits-table">
                <thead>
                    <tr>
                        <th scope="col" id="cb" class="manage-column column-cb check-column">
                            <input id="benefits-select-all" type="checkbox" />
                        </th>
                        <th scope="col" class="manage-column column-name column-primary">Name</th>
                        <th scope="col" class="manage-column column-email">Email</th>
                        <th scope="col" class="manage-column column-hours">Hours</th>
                        <th scope="col" class="manage-column column-sessions">Sessions</th>
                        <th scope="col" class="manage-column column-membership">Membership</th>
                        <th scope="col" class="manage-column column-expires">Expires</th>
                        <th scope="col" class="manage-column column-benefits">Benefits</th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php foreach ($volunteers_data as $volunteer) : ?>
                        <tr data-user-id="<?php echo esc_attr($volunteer['ID']); ?>">
                            <th scope="row" class="check-column">
                                <input type="checkbox" class="benefits-user-checkbox" value="<?php echo esc_attr($volunteer['ID']); ?>" />
                            </th>
                            <td class="name column-name column-primary">
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_user_link($volunteer['ID'])); ?>">
                                        <?php echo esc_html($volunteer['name']); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=volunteer_session&meta_key=user_id&meta_value=' . $volunteer['ID'])); ?>">
                                            View Sessions
                                        </a> |
                                    </span>
                                    <span class="edit">
                                        <a href="<?php echo esc_url(get_edit_user_link($volunteer['ID'])); ?>">
                                            Edit User
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td class="email column-email">
                                <a href="mailto:<?php echo esc_attr($volunteer['email']); ?>">
                                    <?php echo esc_html($volunteer['email']); ?>
                                </a>
                            </td>
                            <td class="hours column-hours">
                                <?php echo esc_html($volunteer['hours']); ?>
                            </td>
                            <td class="sessions column-sessions">
                                <?php echo esc_html($volunteer['sessions']); ?>
                            </td>
                            <td class="membership column-membership">
                                <?php echo esc_html($volunteer['membership_status']); ?>
                            </td>
                            <td class="expires column-expires">
                                <?php echo esc_html($volunteer['membership_end']); ?>
                            </td>
                            <td class="benefits column-benefits">
                                <?php if ($volunteer['meets_target']) : ?>
                                    <span class="badge meets-target" title="Meets target of <?php echo esc_attr($target_hours); ?>h">Meets target</span>
                                <?php else: ?>
                                    <span class="badge below-target" title="Target <?php echo esc_attr($target_hours); ?>h">Below target</span>
                                <?php endif; ?>
                                <?php
                                $status = $volunteer['benefits_status'];
                                $label = ucfirst($status);
                                echo '<span class="badge benefits-status status-'.esc_attr($status).'">'.$label.'</span> ';
                                ?>
                                <button type="button" class="button button-small benefits-review" data-user="<?php echo $volunteer['ID']; ?>" data-month="<?php echo esc_attr($selected_month); ?>">Review</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="col" class="manage-column column-cb check-column"></th>
                        <td class="column-name column-primary"><strong>Total</strong></td>
                        <td class="column-email"><?php echo count($volunteers_data); ?> volunteers</td>
                        <td class="column-hours"><?php echo round($total_hours, 2); ?></td>
                        <td class="column-sessions"><?php echo $total_sessions; ?></td>
                        <td class="column-membership"></td>
                        <td class="column-expires"></td>
                        <td class="column-benefits"></td>
                    </tr>
                </tfoot>
            </table>
            <?php
        } else {
            echo '<div class="notice notice-warning"><p>No volunteer sessions found for ' . esc_html(date('F Y', strtotime($selected_month . '-01'))) . '.</p></div>';
        }
        ?>
    </div>
    <?php
}

/**
 * Volunteer Settings Page
 */
function make_volunteer_settings_page() {
    // Handle form submissions
    if (isset($_POST['save_volunteer_settings']) && wp_verify_nonce($_POST['settings_nonce'], 'save_volunteer_settings')) {
        $settings = array(
            'auto_signout_enabled' => isset($_POST['auto_signout_enabled']) ? 1 : 0,
            'auto_signout_time' => sanitize_text_field($_POST['auto_signout_time']),
            'auto_signout_timezone' => sanitize_text_field($_POST['auto_signout_timezone']),
            'auto_signout_notification' => isset($_POST['auto_signout_notification']) ? 1 : 0,
            'auto_signout_email_template' => wp_kses_post($_POST['auto_signout_email_template']),
            'auto_signout_log_enabled' => isset($_POST['auto_signout_log_enabled']) ? 1 : 0
        );
        
        update_option('make_volunteer_settings', $settings);
        // Save monthly target hours
        if (isset($_POST['monthly_target_hours'])) {
            update_option('makesf_volunteer_target_hours', max(0, intval($_POST['monthly_target_hours'])));
        }
        
        // Reschedule cron job if settings changed
        if ($settings['auto_signout_enabled']) {
            make_schedule_auto_signout_cron();
        } else {
            wp_clear_scheduled_hook('make_auto_signout_cron');
        }
        
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
    
    // Get current settings
    $settings = get_option('make_volunteer_settings', array(
        'auto_signout_enabled' => 1,
        'auto_signout_time' => '20:00',
        'auto_signout_timezone' => wp_timezone_string(),
        'auto_signout_notification' => 1,
        'auto_signout_email_template' => "Hi {name},\n\nYour volunteer session has been automatically ended at {time}.\n\nThank you for your contribution to MakeSF!\n\nBest regards,\nMakeSF Team",
        'auto_signout_log_enabled' => 1
    ));
    $target_hours = get_option('makesf_volunteer_target_hours', 12);
    
    $timezones = timezone_identifiers_list();
    ?>
    <div class="wrap volunteer-admin full-width">
        <h1>Volunteer Settings</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('save_volunteer_settings', 'settings_nonce'); ?>
            
            <div class="volunteer-settings-grid">
                <div class="settings-section">
                    <h2>Program Goals</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="monthly_target_hours">Monthly Target Hours</label>
                            </th>
                            <td>
                                <input type="number" id="monthly_target_hours" name="monthly_target_hours" min="0" step="1" value="<?php echo esc_attr($target_hours); ?>" class="small-text"> hours
                                <p class="description">Minimum volunteer hours per month to receive benefits (default 12).</p>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="settings-section">
                    <h2>Auto Sign-Out Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="auto_signout_enabled">Enable Auto Sign-Out</label>
                            </th>
                            <td>
                                <input type="checkbox" id="auto_signout_enabled" name="auto_signout_enabled" value="1"
                                       <?php checked($settings['auto_signout_enabled'], 1); ?>>
                                <p class="description">Automatically sign out all active volunteers at the specified time.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="auto_signout_time">Sign-Out Time</label>
                            </th>
                            <td>
                                <input type="time" id="auto_signout_time" name="auto_signout_time"
                                       value="<?php echo esc_attr($settings['auto_signout_time']); ?>" required>
                                <p class="description">Time when all active volunteers will be automatically signed out (24-hour format).</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="auto_signout_timezone">Timezone</label>
                            </th>
                            <td>
                                <select id="auto_signout_timezone" name="auto_signout_timezone">
                                    <?php foreach ($timezones as $timezone): ?>
                                        <option value="<?php echo esc_attr($timezone); ?>"
                                                <?php selected($settings['auto_signout_timezone'], $timezone); ?>>
                                            <?php echo esc_html($timezone); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Timezone used for the sign-out time.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="auto_signout_notification">Send Email Notifications</label>
                            </th>
                            <td>
                                <input type="checkbox" id="auto_signout_notification" name="auto_signout_notification" value="1"
                                       <?php checked($settings['auto_signout_notification'], 1); ?>>
                                <p class="description">Send email notifications to volunteers when they are automatically signed out.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="auto_signout_log_enabled">Enable Logging</label>
                            </th>
                            <td>
                                <input type="checkbox" id="auto_signout_log_enabled" name="auto_signout_log_enabled" value="1"
                                       <?php checked($settings['auto_signout_log_enabled'], 1); ?>>
                                <p class="description">Log all auto sign-out activities for review.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="auto_signout_email_template">Auto Sign-Out Email</label>
                            </th>
                            <td>
                                <textarea id="auto_signout_email_template" name="auto_signout_email_template"
                                          rows="8" cols="50" class="large-text"><?php
                                    echo esc_textarea($settings['auto_signout_email_template']);
                                ?></textarea>
                                <p class="description">
                                    Available placeholders: {name}, {time}, {date}, {duration}, {organization}
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="settings-section">
                    <h2>System Information</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Next Scheduled Run</th>
                            <td>
                                <?php
                                // Correct hook for the auto sign-out cron event
                                $next_run = wp_next_scheduled('make_auto_signout_volunteers');
                                if ($next_run) {
                                    echo date('Y-m-d H:i:s T', $next_run);
                                } else {
                                    echo 'Not scheduled';
                                }
                                ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Current Time</th>
                            <td>
                                <?php echo date('Y-m-d H:i:s T'); ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">WordPress Timezone</th>
                            <td>
                                <?php echo wp_timezone_string(); ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Active Sessions</th>
                            <td>
                                <?php
                                $active_sessions = make_get_active_volunteer_sessions();
                                echo count($active_sessions) . ' active volunteer session(s)';
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <p class="submit">
                <input type="submit" name="save_volunteer_settings" class="button button-primary" value="Save Settings">
            </p>
        </form>
        
        <div class="settings-actions" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">
            <h3>Manual Actions</h3>
            <p>
                <button type="button" class="button" onclick="if(confirm('Are you sure you want to run auto sign-out now?')) { window.location.href='<?php echo wp_nonce_url(admin_url('admin.php?page=volunteer-settings&action=run_auto_signout'), 'run_auto_signout'); ?>'; }">
                    Run Auto Sign-Out Now
                </button>
                <button type="button" class="button" onclick="if(confirm('Are you sure you want to clear the log?')) { window.location.href='<?php echo wp_nonce_url(admin_url('admin.php?page=volunteer-settings&action=clear_log'), 'clear_log'); ?>'; }">
                    Clear Auto Sign-Out Log
                </button>
            </p>
        </div>
        
        <?php
        // Handle manual actions
        if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
            if ($_GET['action'] === 'run_auto_signout' && wp_verify_nonce($_GET['_wpnonce'], 'run_auto_signout')) {
                $result = make_auto_signout_all_volunteers();
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>Error: ' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>Auto sign-out completed. ' . esc_html($result) . '</p></div>';
                }
            }
            
            if ($_GET['action'] === 'clear_log' && wp_verify_nonce($_GET['_wpnonce'], 'clear_log')) {
                delete_option('make_auto_signout_log');
                echo '<div class="notice notice-success"><p>Auto sign-out log cleared.</p></div>';
            }
        }
        ?>
    </div>
    <?php
}

/**
* Sessions migration helpers (removed; sessions are CPT-only)
*/
/**
 * Handle admin AJAX requests
 */
function make_handle_volunteer_admin_ajax() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'volunteer_admin_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $action = sanitize_text_field($_POST['admin_action'] ?? '');
    
    switch ($action) {
        case 'get_month_sessions':
            $user_id = intval($_POST['user_id'] ?? 0);
            $ym = sanitize_text_field($_POST['month'] ?? '');
            if (!$user_id || !preg_match('/^\d{4}-\d{2}$/', $ym)) {
                wp_send_json_error('Invalid params'); break;
            }
            $start = $ym . '-01 00:00:00';
            $end = date('Y-m-t 23:59:59', strtotime($ym . '-01'));
            $q = new WP_Query(array(
                'post_type' => 'volunteer_session',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => array(
                    'relation' => 'AND',
                    array('key' => 'user_id', 'value' => $user_id, 'compare' => '='),
                    array('key' => 'status', 'value' => 'completed', 'compare' => '='),
                    array('key' => 'signin_time', 'value' => array($start, $end), 'compare' => 'BETWEEN', 'type' => 'DATETIME'),
                ),
            ));
            $rows = array(); $mins = 0;
            foreach ($q->posts as $pid) {
                $signin = get_post_meta($pid, 'signin_time', true);
                $signout = get_post_meta($pid, 'signout_time', true);
                $dur = (int) get_post_meta($pid, 'duration_minutes', true);
                $rows[] = array('id'=>$pid,'signin'=>$signin,'signout'=>$signout,'minutes'=>$dur);
                $mins += $dur;
            }
            $target = (float) get_option('makesf_volunteer_target_hours', 12);
            $status = makesf_get_benefits_status($user_id, $ym);
            wp_send_json_success(array(
                'sessions'=>$rows,
                'total_minutes'=>$mins,
                'total_hours'=>round($mins/60,2),
                'meets_target'=> (round($mins/60,2) >= $target),
                'target_hours'=>$target,
                'status'=>$status,
            ));
            break;
        case 'set_benefits_status':
            $user_id = intval($_POST['user_id'] ?? 0);
            $ym = sanitize_text_field($_POST['month'] ?? '');
            $status = sanitize_text_field($_POST['status'] ?? '');
            if (!$user_id || !preg_match('/^\d{4}-\d{2}$/', $ym)) { wp_send_json_error('Invalid params'); break; }
            if (!in_array($status, array('approved','denied','pending'), true)) { wp_send_json_error('Invalid status'); break; }
            makesf_set_benefits_status($user_id, $ym, $status);
            $message = 'Benefits updated.';
            $membership_payload = null;
            if (in_array($status, array('approved','denied'), true) && function_exists('makesf_sync_benefits_membership')) {
                $info = makesf_sync_benefits_membership($user_id, $ym, $status);
                if ($info && is_array($info)) {
                    $end_disp = date_i18n('F j, Y', strtotime($info['end']));
                    $status_label = ucfirst($info['applied_status']);
                    $membership_payload = array('status_label' => $status_label, 'end_label' => $end_disp);
                    if ($status === 'approved') {
                        $message = sprintf('Membership set to Complimentary; expiration set to %s.', $end_disp);
                    } else {
                        if (($info['applied_status'] ?? '') === 'expired') {
                            $message = sprintf('Membership expiration set to %s and status set to Expired.', $end_disp);
                        } else {
                            $message = sprintf('Membership expiration set to %s.', $end_disp);
                        }
                    }
                } else {
                    $message = 'Benefits updated; membership unchanged.';
                }
            }
            wp_send_json_success(array('ok' => true, 'message' => $message, 'membership' => $membership_payload));
            break;
        case 'bulk_set_benefits_status':
            $ids = isset($_POST['user_ids']) ? (array) $_POST['user_ids'] : array();
            $ym = sanitize_text_field($_POST['month'] ?? '');
            $status = sanitize_text_field($_POST['status'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}$/', $ym)) { wp_send_json_error('Invalid month'); break; }
            if (!in_array($status, array('approved','denied','pending'), true)) { wp_send_json_error('Invalid status'); break; }
            $updated = array();
            $last_info = null;
            foreach ($ids as $id) {
                $uid = intval($id);
                if ($uid > 0) {
                    makesf_set_benefits_status($uid, $ym, $status);
                    if (in_array($status, array('approved','denied'), true) && function_exists('makesf_sync_benefits_membership')) {
                        $last_info = makesf_sync_benefits_membership($uid, $ym, $status);
                    }
                    $updated[] = $uid;
                }
            }
            // Build a concise message for the bulk action
            $message = sprintf('Updated %d volunteer(s).', count($updated));
            if ($last_info && is_array($last_info) && in_array($status, array('approved','denied'), true)) {
                $end_disp = date_i18n('F j, Y', strtotime($last_info['end']));
                if ($status === 'approved') {
                    $message .= sprintf(' Set membership to Complimentary; expiration set to %s.', $end_disp);
                } else {
                    if (($last_info['applied_status'] ?? '') === 'expired') {
                        $message .= sprintf(' Expiration set to %s and status set to Expired.', $end_disp);
                    } else {
                        $message .= sprintf(' Expiration set to %s.', $end_disp);
                    }
                }
            }
            wp_send_json_success(array('updated' => $updated, 'status' => $status, 'message' => $message));
            break;
        case 'get_session_details':
            $session_id = intval($_POST['session_id'] ?? 0);
            if (!$session_id) {
                wp_send_json_error('Invalid session ID');
                break;
            }
            
            $session_data = make_get_session_details_for_modal($session_id);
            if (!$session_data) {
                wp_send_json_error('Session not found');
                break;
            }
            
            wp_send_json_success($session_data);
            break;
            
        case 'update_session':
            $session_id = intval($_POST['session_id'] ?? 0);
            $signin_time = sanitize_text_field($_POST['signin_time'] ?? '');
            $signout_time = sanitize_text_field($_POST['signout_time'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            
            if (!$session_id || !$signin_time || !$signout_time) {
                wp_send_json_error('Missing required fields');
                break;
            }
            
            $result = make_update_volunteer_session($session_id, $signin_time, $signout_time, $notes);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success('Session updated successfully');
            }
            break;
            
        case 'end_session':
            $session_id = intval($_POST['session_id'] ?? 0);
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            $result = make_end_volunteer_session($session_id, array(), $notes ?: 'Ended by admin');
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success('Session ended successfully');
            }
            break;
            
        case 'export_report':
            $period = sanitize_text_field($_POST['period'] ?? 'month');
            $csv_data = make_generate_volunteer_report_csv($period);
            wp_send_json_success(array('csv' => $csv_data));
            break;
            
        case 'get_active_count':
            $active_sessions = make_get_active_volunteer_sessions();
            wp_send_json_success(array('count' => count($active_sessions)));
            break;
            
        default:
            wp_send_json_error('Unknown action');
    }
}
