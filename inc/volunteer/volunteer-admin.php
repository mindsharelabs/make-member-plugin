<?php
/**
 * Volunteer Admin Interface
 * 
 * Handles admin dashboard, reports, and volunteer management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize volunteer admin interface
 */
function make_volunteer_admin_init() {
    add_action('admin_menu', 'make_volunteer_admin_menu');
    add_action('admin_enqueue_scripts', 'make_volunteer_admin_scripts');
    add_action('wp_ajax_make_volunteer_admin_action', 'make_handle_volunteer_admin_ajax');
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
    
    add_submenu_page(
        'volunteer-dashboard',
        'Volunteer Sessions',
        'Sessions',
        'manage_options',
        'volunteer-sessions',
        'make_volunteer_sessions_page'
    );
    
    add_submenu_page(
        'volunteer-dashboard',
        'Volunteer Tasks',
        'Tasks',
        'manage_options',
        'volunteer-tasks',
        'make_volunteer_tasks_page'
    );
    
    add_submenu_page(
        'volunteer-dashboard',
        'Volunteer Schedules',
        'Schedules',
        'manage_options',
        'volunteer-schedules',
        'make_volunteer_schedules_page'
    );
    
    add_submenu_page(
        'volunteer-dashboard',
        'Volunteer Reports',
        'Reports',
        'manage_options',
        'volunteer-reports',
        'make_volunteer_reports_page'
    );
    
    add_submenu_page(
        'volunteer-dashboard',
        'System Test',
        'System Test',
        'manage_options',
        'volunteer-test',
        'make_volunteer_test_page'
    );
    
    add_submenu_page(
        'volunteer-dashboard',
        'Volunteer Settings',
        'Settings',
        'manage_options',
        'volunteer-settings',
        'make_volunteer_settings_page'
    );
}

/**
 * Enqueue admin scripts and styles
 */
function make_volunteer_admin_scripts($hook) {
    if (strpos($hook, 'volunteer-') === false) {
        return;
    }
    
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
    $recent_sessions = make_get_recent_volunteer_sessions(10);
    $leaderboard = make_get_volunteer_leaderboard('month', 5);
    $active_sessions = make_get_active_volunteer_sessions();
    
    ?>
    <div class="wrap volunteer-admin">
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
        
        <div class="volunteer-dashboard-grid">
            <div class="dashboard-section">
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
                                $signin_time = new DateTime($session->signin_time);
                                $current_time = new DateTime();
                                $duration = $signin_time->diff($current_time);
                                $duration_hours = round(($duration->h + ($duration->i / 60)), 1);
                                ?>
                                <tr>
                                    <td><?php echo esc_html($user ? $user->display_name : 'Unknown User'); ?></td>
                                    <td><?php echo $signin_time->format('M j, Y g:i A'); ?></td>
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
            
            <div class="dashboard-section">
                <h2>Top Volunteers This Month</h2>
                <?php if (!empty($leaderboard)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Volunteer</th>
                                <th>Hours</th>
                                <th>Sessions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $volunteer): ?>
                                <tr>
                                    <td><?php echo esc_html($volunteer['name']); ?></td>
                                    <td><?php echo $volunteer['total_hours']; ?></td>
                                    <td><?php echo $volunteer['session_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No volunteer data for this month.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2>Recent Sessions</h2>
            <?php if (!empty($recent_sessions)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Volunteer</th>
                            <th>Date</th>
                            <th>Duration</th>
                            <th>Tasks</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sessions as $session): ?>
                            <?php 
                            $user = get_user_by('ID', $session->user_id);
                            $signin_time = new DateTime($session->signin_time);
                            $tasks = !empty($session->tasks_completed) ? json_decode($session->tasks_completed, true) : array();
                            ?>
                            <tr>
                                <td><?php echo esc_html($user ? $user->display_name : 'Unknown User'); ?></td>
                                <td><?php echo $signin_time->format('M j, Y'); ?></td>
                                <td><?php echo round($session->duration_minutes / 60, 1); ?> hours</td>
                                <td>
                                    <?php if (!empty($tasks)): ?>
                                        <?php echo count($tasks); ?> task<?php echo count($tasks) > 1 ? 's' : ''; ?>
                                    <?php else: ?>
                                        No tasks recorded
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(wp_trim_words($session->notes, 10)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No recent sessions found.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Volunteer Sessions Page
 */
function make_volunteer_sessions_page() {
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $offset = ($current_page - 1) * $per_page;
    
    $sessions = make_get_volunteer_sessions_paginated($per_page, $offset);
    $total_sessions = make_get_volunteer_sessions_count();
    $total_pages = ceil($total_sessions / $per_page);
    
    ?>
    <div class="wrap volunteer-admin">
        <h1>Volunteer Sessions</h1>
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <select id="session-filter-status">
                    <option value="">All Sessions</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                </select>
                <input type="text" id="session-filter-date" placeholder="Filter by date" />
                <button class="button" id="filter-sessions">Filter</button>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Volunteer</th>
                    <th>Sign In</th>
                    <th>Sign Out</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Tasks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                    <?php 
                    $user = get_user_by('ID', $session->user_id);
                    $signin_time = new DateTime($session->signin_time);
                    $signout_time = $session->signout_time ? new DateTime($session->signout_time) : null;
                    $tasks = !empty($session->tasks_completed) ? json_decode($session->tasks_completed, true) : array();
                    ?>
                    <tr>
                        <td><?php echo $session->id; ?></td>
                        <td><?php echo esc_html($user ? $user->display_name : 'Unknown User'); ?></td>
                        <td><?php echo $signin_time->format('M j, Y g:i A'); ?></td>
                        <td>
                            <?php if ($signout_time): ?>
                                <?php echo $signout_time->format('M j, Y g:i A'); ?>
                            <?php else: ?>
                                <span class="active-session">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($session->duration_minutes): ?>
                                <?php echo round($session->duration_minutes / 60, 1); ?> hours
                            <?php else: ?>
                                <span class="calculating">Ongoing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-<?php echo $session->status; ?>">
                                <?php echo ucfirst($session->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($tasks)): ?>
                                <span class="task-count"><?php echo count($tasks); ?> task<?php echo count($tasks) > 1 ? 's' : ''; ?></span>
                            <?php else: ?>
                                No tasks
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="button button-small view-session" data-session="<?php echo $session->id; ?>">
                                View
                            </button>
                            <?php if ($session->status === 'active'): ?>
                                <button class="button button-small end-session" data-session="<?php echo $session->id; ?>">
                                    End
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Volunteer Tasks Page
 */
function make_volunteer_tasks_page() {
    $tasks = get_posts(array(
        'post_type' => 'volunteer_task',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ));
    
    ?>
    <div class="wrap volunteer-admin">
        <h1>Volunteer Tasks</h1>
        
        <div class="page-title-action">
            <a href="<?php echo admin_url('post-new.php?post_type=volunteer_task'); ?>" class="button button-primary">
                Add New Task
            </a>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Duration</th>
                    <th>Completions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <?php
                    $categories = get_the_terms($task->ID, 'volunteer_task_category');
                    $priority = get_field('priority', $task->ID) ?: 'medium';
                    $duration = get_field('estimated_duration', $task->ID) ?: 'N/A';
                    $completions = make_get_task_completion_count($task->ID);
                    $task_status = get_field('task_status', $task->ID) ?: 'available';
                    $task_type = get_field('task_type', $task->ID) ?: 'one_time';
                    $assigned_to = get_field('assigned_to', $task->ID);
                    $completed_by = get_field('completed_by', $task->ID);
                    $completed_date = get_field('completed_date', $task->ID);
                    ?>
                    <tr class="task-status-<?php echo $task_status; ?>">
                        <td>
                            <strong><?php echo esc_html($task->post_title); ?></strong>
                            <div class="task-description">
                                <?php echo esc_html(wp_trim_words($task->post_content, 15)); ?>
                            </div>
                            <div class="task-meta" style="margin-top: 5px; font-size: 12px;">
                                <span class="task-type-badge" style="background: <?php echo $task_type === 'recurring' ? '#28a745' : '#6c757d'; ?>; color: white; padding: 2px 6px; border-radius: 3px; margin-right: 5px;">
                                    <?php echo $task_type === 'recurring' ? '🔄 Recurring' : '📋 One-time'; ?>
                                </span>
                                <span class="task-status-badge" style="background: <?php
                                    switch($task_status) {
                                        case 'completed': echo '#28a745'; break;
                                        case 'in_progress': echo '#ffc107'; break;
                                        case 'on_hold': echo '#6c757d'; break;
                                        default: echo '#007cba'; break;
                                    }
                                ?>; color: white; padding: 2px 6px; border-radius: 3px;">
                                    <?php echo ucfirst(str_replace('_', ' ', $task_status)); ?>
                                </span>
                            </div>
                            <?php if ($assigned_to): ?>
                                <?php $assigned_user = get_user_by('ID', $assigned_to); ?>
                                <div style="margin-top: 3px; font-size: 12px; color: #0073aa;">
                                    👤 Assigned to: <?php echo esc_html($assigned_user ? $assigned_user->display_name : 'Unknown User'); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($completed_by && $completed_date): ?>
                                <?php $completed_user = get_user_by('ID', $completed_by); ?>
                                <div style="margin-top: 3px; font-size: 12px; color: #28a745;">
                                    ✅ Completed by <?php echo esc_html($completed_user ? $completed_user->display_name : 'Unknown User'); ?>
                                    on <?php echo date('M j, Y g:i A', strtotime($completed_date)); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($categories && !is_wp_error($categories)): ?>
                                <?php echo esc_html($categories[0]->name); ?>
                            <?php else: ?>
                                Uncategorized
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="priority-<?php echo $priority; ?>">
                                <?php echo ucfirst($priority); ?>
                            </span>
                        </td>
                        <td><?php echo $duration; ?> min</td>
                        <td><?php echo $completions; ?></td>
                        <td>
                            <a href="<?php echo get_edit_post_link($task->ID); ?>" class="button button-small">
                                Edit
                            </a>
                            <?php if ($task_status !== 'completed' || $task_type === 'recurring'): ?>
                                <button class="button button-small button-primary quick-complete-task" data-task="<?php echo $task->ID; ?>">
                                    <?php echo $task_type === 'recurring' ? 'Log Completion' : 'Mark Complete'; ?>
                                </button>
                            <?php endif; ?>
                            <?php if ($task_status === 'completed' && $task_type === 'one_time'): ?>
                                <button class="button button-small reopen-task" data-task="<?php echo $task->ID; ?>">
                                    Reopen
                                </button>
                            <?php endif; ?>
                            <button class="button button-small view-task-stats" data-task="<?php echo $task->ID; ?>">
                                Stats
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle quick complete task
            $('.quick-complete-task').on('click', function() {
                var taskId = $(this).data('task');
                var taskRow = $(this).closest('tr');
                var notes = prompt('Optional completion notes:');
                
                if (notes === null) return; // User cancelled
                
                $.ajax({
                    url: volunteerAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'make_volunteer_admin_action',
                        admin_action: 'quick_complete_task',
                        task_id: taskId,
                        notes: notes,
                        nonce: volunteerAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data);
                            location.reload(); // Refresh to show updated status
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Error completing task. Please try again.');
                    }
                });
            });
            
            // Handle reopen task
            $('.reopen-task').on('click', function() {
                var taskId = $(this).data('task');
                
                if (!confirm('Are you sure you want to reopen this task?')) {
                    return;
                }
                
                $.ajax({
                    url: volunteerAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'make_volunteer_admin_action',
                        admin_action: 'reopen_task',
                        task_id: taskId,
                        nonce: volunteerAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data);
                            location.reload(); // Refresh to show updated status
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Error reopening task. Please try again.');
                    }
                });
            });
        });
        </script>
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
    
    $timezones = timezone_identifiers_list();
    ?>
    <div class="wrap volunteer-admin">
        <h1>Volunteer Settings</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('save_volunteer_settings', 'settings_nonce'); ?>
            
            <div class="volunteer-settings-grid">
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
                    </table>
                </div>
                
                <div class="settings-section">
                    <h2>Email Template</h2>
                    <table class="form-table">
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
                                $next_run = wp_next_scheduled('make_auto_signout_cron');
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
 * Volunteer Schedules Page
 */
function make_volunteer_schedules_page() {
    // Handle form submissions
    if (isset($_POST['add_schedule']) && wp_verify_nonce($_POST['schedule_nonce'], 'add_volunteer_schedule')) {
        $user_id = intval($_POST['volunteer_user']);
        $day_of_week = intval($_POST['day_of_week']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);
        
        if ($user_id && $day_of_week >= 0 && $day_of_week <= 6 && $start_time && $end_time) {
            $result = make_add_volunteer_schedule($user_id, $day_of_week, $start_time, $end_time);
            if (!is_wp_error($result)) {
                echo '<div class="notice notice-success"><p>Schedule added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Error adding schedule: ' . $result->get_error_message() . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Please fill in all required fields.</p></div>';
        }
    }
    
    if (isset($_POST['remove_schedule']) && wp_verify_nonce($_POST['schedule_nonce'], 'remove_volunteer_schedule')) {
        $schedule_id = intval($_POST['schedule_id']);
        if ($schedule_id) {
            $result = make_remove_volunteer_schedule($schedule_id);
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>Schedule removed successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Error removing schedule.</p></div>';
            }
        }
    }
    
    $schedules = make_get_all_volunteer_schedules();
    $volunteers = make_get_all_volunteers();
    
    ?>
    <div class="wrap volunteer-admin">
        <h1>Volunteer Schedules</h1>
        
        <!-- Add New Schedule Form -->
        <div class="add-schedule-form" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
            <h2>Add New Volunteer Schedule</h2>
            <form method="post" action="">
                <?php wp_nonce_field('add_volunteer_schedule', 'schedule_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="volunteer_user">Volunteer</label></th>
                        <td>
                            <select name="volunteer_user" id="volunteer_user" required>
                                <option value="">Select a volunteer...</option>
                                <?php foreach ($volunteers as $volunteer): ?>
                                    <option value="<?php echo $volunteer['id']; ?>">
                                        <?php echo esc_html($volunteer['name'] . ' (' . $volunteer['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Choose the volunteer for this schedule.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="day_of_week">Day of Week</label></th>
                        <td>
                            <select name="day_of_week" id="day_of_week" required>
                                <option value="">Select a day...</option>
                                <option value="0">Sunday</option>
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="start_time">Start Time</label></th>
                        <td>
                            <input type="time" name="start_time" id="start_time" required>
                            <p class="description">When should this volunteer shift start?</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="end_time">End Time</label></th>
                        <td>
                            <input type="time" name="end_time" id="end_time" required>
                            <p class="description">When should this volunteer shift end?</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="add_schedule" class="button button-primary" value="Add Schedule">
                </p>
            </form>
        </div>
        
        <div class="schedule-overview">
            <h2>Weekly Schedule Overview</h2>
            <div class="schedule-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; margin: 20px 0;">
                <?php
                $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
                for ($day = 0; $day < 7; $day++):
                    $day_schedules = array_filter($schedules, function($s) use ($day) {
                        return $s->day_of_week == $day;
                    });
                ?>
                    <div class="day-schedule" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd;">
                        <h3 style="margin-top: 0;"><?php echo $days[$day]; ?></h3>
                        <?php if (!empty($day_schedules)): ?>
                            <?php foreach ($day_schedules as $schedule): ?>
                                <?php $user = get_user_by('ID', $schedule->user_id); ?>
                                <div class="schedule-item" style="background: #fff; padding: 10px; margin: 5px 0; border-left: 3px solid #0073aa;">
                                    <div class="volunteer-name" style="font-weight: bold;">
                                        <?php echo esc_html($user ? $user->display_name : 'Unknown'); ?>
                                    </div>
                                    <div class="schedule-time" style="font-size: 0.9em; color: #666;">
                                        <?php echo date('g:i A', strtotime($schedule->start_time)); ?> -
                                        <?php echo date('g:i A', strtotime($schedule->end_time)); ?>
                                    </div>
                                    <div class="schedule-actions" style="margin-top: 5px;">
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('remove_volunteer_schedule', 'schedule_nonce'); ?>
                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule->id; ?>">
                                            <button type="submit" name="remove_schedule" class="button button-small button-link-delete"
                                                    onclick="return confirm('Are you sure you want to remove this schedule?')">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-schedules" style="color: #666; font-style: italic;">No scheduled volunteers</div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="all-schedules">
            <h2>All Volunteer Schedules</h2>
            <?php if (!empty($schedules)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Volunteer</th>
                            <th>Day</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <?php
                            $user = get_user_by('ID', $schedule->user_id);
                            $day_name = $days[$schedule->day_of_week];
                            ?>
                            <tr>
                                <td><?php echo esc_html($user ? $user->display_name : 'Unknown User'); ?></td>
                                <td><?php echo $day_name; ?></td>
                                <td><?php echo date('g:i A', strtotime($schedule->start_time)); ?></td>
                                <td><?php echo date('g:i A', strtotime($schedule->end_time)); ?></td>
                                <td>
                                    <span class="status-<?php echo $schedule->is_active ? 'active' : 'inactive'; ?>">
                                        <?php echo $schedule->is_active ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('remove_volunteer_schedule', 'schedule_nonce'); ?>
                                        <input type="hidden" name="schedule_id" value="<?php echo $schedule->id; ?>">
                                        <button type="submit" name="remove_schedule" class="button button-small button-link-delete"
                                                onclick="return confirm('Are you sure you want to remove this schedule?')">
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No volunteer schedules have been created yet. Use the form above to add the first schedule.</p>
            <?php endif; ?>
        </div>
        
        <div class="schedule-help" style="background: #f0f8ff; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;">
            <h3>How Volunteer Schedules Work</h3>
            <ul>
                <li><strong>Schedule Adherence:</strong> When volunteers sign in, the system checks if they're on time, early, late, or unscheduled</li>
                <li><strong>Tolerance:</strong> Volunteers are considered "on time" if they sign in within 15 minutes of their scheduled start time</li>
                <li><strong>Multiple Shifts:</strong> Volunteers can have multiple scheduled shifts throughout the week</li>
                <li><strong>Flexible Volunteers:</strong> Volunteers without schedules can still volunteer anytime (marked as "unscheduled")</li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Volunteer Reports Page
 */
function make_volunteer_reports_page() {
    $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
    $stats = make_get_volunteer_stats($period);
    $monthly_data = make_get_volunteer_monthly_data();
    
    ?>
    <div class="wrap volunteer-admin">
        <h1>Volunteer Reports</h1>
        
        <div class="report-filters">
            <select id="report-period">
                <option value="week" <?php selected($period, 'week'); ?>>This Week</option>
                <option value="month" <?php selected($period, 'month'); ?>>This Month</option>
                <option value="year" <?php selected($period, 'year'); ?>>This Year</option>
                <option value="all" <?php selected($period, 'all'); ?>>All Time</option>
            </select>
            <button class="button" id="update-report">Update Report</button>
            <button class="button button-primary" id="export-report">Export CSV</button>
        </div>
        
        <div class="report-stats">
            <div class="stat-card">
                <h3>Total Sessions</h3>
                <div class="stat-number"><?php echo $stats['total_sessions']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Hours</h3>
                <div class="stat-number"><?php echo $stats['total_hours']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Unique Volunteers</h3>
                <div class="stat-number"><?php echo $stats['unique_volunteers']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Avg Session</h3>
                <div class="stat-number"><?php echo $stats['avg_session_hours']; ?> hrs</div>
            </div>
        </div>
        
        <div class="report-charts">
            <div class="chart-container">
                <h3>Volunteer Hours by Month</h3>
                <canvas id="monthlyHoursChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3>Top Volunteers</h3>
                <canvas id="topVolunteersChart"></canvas>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Monthly hours chart
            const monthlyData = <?php echo json_encode($monthly_data); ?>;
            const ctx1 = document.getElementById('monthlyHoursChart').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: monthlyData.labels,
                    datasets: [{
                        label: 'Volunteer Hours',
                        data: monthlyData.hours,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Top volunteers chart
            const leaderboard = <?php echo json_encode(make_get_volunteer_leaderboard($period, 10)); ?>;
            const ctx2 = document.getElementById('topVolunteersChart').getContext('2d');
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: leaderboard.map(v => v.name),
                    datasets: [{
                        label: 'Hours',
                        data: leaderboard.map(v => v.total_hours),
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
        </script>
    </div>
    <?php
}

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
            
        case 'quick_complete_task':
            $task_id = intval($_POST['task_id'] ?? 0);
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            
            if (!$task_id) {
                wp_send_json_error('Invalid task ID');
                break;
            }
            
            if (function_exists('make_quick_complete_task')) {
                $result = make_quick_complete_task($task_id, get_current_user_id(), $notes);
                if ($result) {
                    $task = get_post($task_id);
                    $task_type = get_field('task_type', $task_id) ?: 'one_time';
                    $message = $task_type === 'recurring'
                        ? 'Completion logged for recurring task: ' . $task->post_title
                        : 'Task marked as completed: ' . $task->post_title;
                    wp_send_json_success($message);
                } else {
                    wp_send_json_error('Failed to complete task');
                }
            } else {
                wp_send_json_error('Task completion function not available');
            }
            break;
            
        case 'reopen_task':
            $task_id = intval($_POST['task_id'] ?? 0);
            
            if (!$task_id) {
                wp_send_json_error('Invalid task ID');
                break;
            }
            
            // Reset task to available status
            update_field('task_status', 'available', $task_id);
            update_field('completed_by', '', $task_id);
            update_field('completed_date', '', $task_id);
            update_field('completion_notes_actual', '', $task_id);
            
            $task = get_post($task_id);
            wp_send_json_success('Task reopened: ' . $task->post_title);
            break;
            
        default:
            wp_send_json_error('Unknown action');
    }
}

/**
 * Volunteer System Test Page
 */
function make_volunteer_test_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    
    ?>
    <div class="wrap volunteer-admin">
        <h1>Volunteer System Test</h1>
        
        <?php
        // Handle manual actions
        if (isset($_POST['create_tables'])) {
            make_create_volunteer_tables();
            echo '<div class="notice notice-success"><p>Volunteer tables created/updated.</p></div>';
        }
        
        if (isset($_POST['verify_tables'])) {
            make_verify_volunteer_tables();
            echo '<div class="notice notice-info"><p>Table verification completed. Check debug logs for details.</p></div>';
        }
        
        if (isset($_POST['create_tasks'])) {
            if (function_exists('make_create_default_volunteer_tasks')) {
                $created_count = make_create_default_volunteer_tasks();
                echo '<div class="notice notice-success"><p>Created ' . $created_count . ' default volunteer tasks.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Task creation function not available.</p></div>';
            }
        }
        
        if (isset($_POST['add_capabilities'])) {
            if (function_exists('make_add_volunteer_task_capabilities')) {
                make_add_volunteer_task_capabilities();
                echo '<div class="notice notice-success"><p>Volunteer task capabilities added to administrator and editor roles.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Capability function not available.</p></div>';
            }
        }
        ?>
        
        <h2>System Status</h2>
        <?php if (function_exists('make_test_volunteer_system')): ?>
            <?php $test_results = make_test_volunteer_system(); ?>
            
            <div class="test-results">
                <h3>Database Tables</h3>
                <ul>
                    <li>Sessions Table: <?php echo $test_results['tables']['sessions_table_exists'] ? '✅ Exists' : '❌ Missing'; ?></li>
                    <li>Schedules Table: <?php echo $test_results['tables']['schedules_table_exists'] ? '✅ Exists' : '❌ Missing'; ?></li>
                </ul>
                
                <h3>Functions</h3>
                <ul>
                    <li>make_start_volunteer_session: <?php echo $test_results['functions']['make_start_volunteer_session'] ? '✅ Available' : '❌ Missing'; ?></li>
                    <li>make_get_active_volunteer_session: <?php echo $test_results['functions']['make_get_active_volunteer_session'] ? '✅ Available' : '❌ Missing'; ?></li>
                    <li>make_get_available_volunteer_tasks: <?php echo $test_results['functions']['make_get_available_volunteer_tasks'] ? '✅ Available' : '❌ Missing'; ?></li>
                </ul>
                
                <h3>AJAX Handlers</h3>
                <ul>
                    <li>makeGetVolunteerSession: <?php echo $test_results['ajax_handlers']['makeGetVolunteerSession'] ? '✅ Registered' : '❌ Not Registered'; ?></li>
                    <li>makeVolunteerSignOut: <?php echo $test_results['ajax_handlers']['makeVolunteerSignOut'] ? '✅ Registered' : '❌ Not Registered'; ?></li>
                </ul>
                
                <h3>Volunteer Tasks</h3>
                <ul>
                    <li>Tasks Available: <?php echo $test_results['volunteer_tasks']['tasks_exist'] ? '✅ Yes' : '❌ No'; ?></li>
                    <li>Task Count: <?php echo $test_results['volunteer_tasks']['task_count']; ?></li>
                </ul>
            </div>
            
            <h3>Raw Test Data</h3>
            <pre style="background: #f1f1f1; padding: 10px; overflow: auto;"><?php echo print_r($test_results, true); ?></pre>
            
        <?php else: ?>
            <p>❌ Test function not available.</p>
        <?php endif; ?>
        
        <h2>Manual Actions</h2>
        <form method="post" style="margin: 20px 0;">
            <input type="submit" name="create_tables" value="Create/Update Volunteer Tables" class="button button-primary" style="margin-right: 10px;">
            <input type="submit" name="verify_tables" value="Verify Tables Exist" class="button button-secondary" style="margin-right: 10px;">
            <input type="submit" name="create_tasks" value="Create Default Volunteer Tasks" class="button button-secondary" style="margin-right: 10px;">
            <input type="submit" name="add_capabilities" value="Add Task Creation Permissions" class="button button-secondary">
        </form>
        
        <h2>Debug Information</h2>
        <p><strong>WordPress Debug:</strong> <?php echo defined('WP_DEBUG') && WP_DEBUG ? '✅ Enabled' : '❌ Disabled'; ?></p>
        <p><strong>Plugin Version:</strong> <?php echo defined('MAKESF_PLUGIN_VERSION') ? MAKESF_PLUGIN_VERSION : 'Unknown'; ?></p>
        <p><strong>Database Prefix:</strong> <?php global $wpdb; echo $wpdb->prefix; ?></p>
        
        <h2>Quick Test Actions</h2>
        <div id="test-actions">
            <button class="button" onclick="testVolunteerAjax()">Test AJAX Endpoints</button>
            <button class="button" onclick="checkConsoleLog()">Check Console for Errors</button>
        </div>
        
        <div id="test-results" style="margin-top: 20px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; display: none;">
            <h3>Test Results</h3>
            <div id="test-output"></div>
        </div>
        
        <script>
        function testVolunteerAjax() {
            console.log('Testing volunteer AJAX endpoints...');
            document.getElementById('test-results').style.display = 'block';
            document.getElementById('test-output').innerHTML = 'Testing AJAX endpoints... Check browser console for details.';
            
            // Test if makeMember object exists
            if (typeof makeMember !== 'undefined') {
                console.log('makeMember object found:', makeMember);
                document.getElementById('test-output').innerHTML += '<br>✅ makeMember object exists';
            } else {
                console.log('makeMember object not found');
                document.getElementById('test-output').innerHTML += '<br>❌ makeMember object missing';
            }
        }
        
        function checkConsoleLog() {
            document.getElementById('test-results').style.display = 'block';
            document.getElementById('test-output').innerHTML = 'Check your browser console (F12) for any JavaScript errors or volunteer system debug messages.';
            console.log('Volunteer System Debug Check - Look for messages starting with "Make Volunteer:"');
        }
        </script>
    </div>
    <?php
}