<?php
/**
 * Volunteer Custom Post Type and Taxonomy
 * 
 * Registers volunteer_task custom post type and related taxonomy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register volunteer task custom post type
 */
function make_register_volunteer_task_cpt() {
    $labels = array(
        'name'                  => _x('Volunteer Tasks', 'Post Type General Name', 'makesf'),
        'singular_name'         => _x('Volunteer Task', 'Post Type Singular Name', 'makesf'),
        'menu_name'             => __('Volunteer Tasks', 'makesf'),
        'name_admin_bar'        => __('Volunteer Task', 'makesf'),
        'archives'              => __('Task Archives', 'makesf'),
        'attributes'            => __('Task Attributes', 'makesf'),
        'parent_item_colon'     => __('Parent Task:', 'makesf'),
        'all_items'             => __('All Tasks', 'makesf'),
        'add_new_item'          => __('Add New Task', 'makesf'),
        'add_new'               => __('Add New', 'makesf'),
        'new_item'              => __('New Task', 'makesf'),
        'edit_item'             => __('Edit Task', 'makesf'),
        'update_item'           => __('Update Task', 'makesf'),
        'view_item'             => __('View Task', 'makesf'),
        'view_items'            => __('View Tasks', 'makesf'),
        'search_items'          => __('Search Tasks', 'makesf'),
        'not_found'             => __('Not found', 'makesf'),
        'not_found_in_trash'    => __('Not found in Trash', 'makesf'),
        'featured_image'        => __('Featured Image', 'makesf'),
        'set_featured_image'    => __('Set featured image', 'makesf'),
        'remove_featured_image' => __('Remove featured image', 'makesf'),
        'use_featured_image'    => __('Use as featured image', 'makesf'),
        'insert_into_item'      => __('Insert into task', 'makesf'),
        'uploaded_to_this_item' => __('Uploaded to this task', 'makesf'),
        'items_list'            => __('Tasks list', 'makesf'),
        'items_list_navigation' => __('Tasks list navigation', 'makesf'),
        'filter_items_list'     => __('Filter tasks list', 'makesf'),
    );

    $args = array(
        'label'                 => __('Volunteer Task', 'makesf'),
        'description'           => __('Tasks that volunteers can work on', 'makesf'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor', 'thumbnail', 'revisions'),
        'taxonomies'            => array('volunteer_task_category'),
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => false, // We'll add it to our custom menu
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-clipboard',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'volunteer_task',
        'map_meta_cap'          => true,
        'capabilities'          => array(
            'edit_post'              => 'edit_volunteer_task',
            'read_post'              => 'read_volunteer_task',
            'delete_post'            => 'delete_volunteer_task',
            'edit_posts'             => 'edit_volunteer_tasks',
            'edit_others_posts'      => 'edit_others_volunteer_tasks',
            'publish_posts'          => 'publish_volunteer_tasks',
            'read_private_posts'     => 'read_private_volunteer_tasks',
            'delete_posts'           => 'delete_volunteer_tasks',
            'delete_private_posts'   => 'delete_private_volunteer_tasks',
            'delete_published_posts' => 'delete_published_volunteer_tasks',
            'delete_others_posts'    => 'delete_others_volunteer_tasks',
            'edit_private_posts'     => 'edit_private_volunteer_tasks',
            'edit_published_posts'   => 'edit_published_volunteer_tasks',
            'create_posts'           => 'edit_volunteer_tasks',
        ),
        'show_in_rest'          => false,
    );

    register_post_type('volunteer_task', $args);
}
add_action('init', 'make_register_volunteer_task_cpt', 0);

/**
 * Register volunteer task category taxonomy
 */
function make_register_volunteer_task_taxonomy() {
    $labels = array(
        'name'                       => _x('Task Categories', 'Taxonomy General Name', 'makesf'),
        'singular_name'              => _x('Task Category', 'Taxonomy Singular Name', 'makesf'),
        'menu_name'                  => __('Task Categories', 'makesf'),
        'all_items'                  => __('All Categories', 'makesf'),
        'parent_item'                => __('Parent Category', 'makesf'),
        'parent_item_colon'          => __('Parent Category:', 'makesf'),
        'new_item_name'              => __('New Category Name', 'makesf'),
        'add_new_item'               => __('Add New Category', 'makesf'),
        'edit_item'                  => __('Edit Category', 'makesf'),
        'update_item'                => __('Update Category', 'makesf'),
        'view_item'                  => __('View Category', 'makesf'),
        'separate_items_with_commas' => __('Separate categories with commas', 'makesf'),
        'add_or_remove_items'        => __('Add or remove categories', 'makesf'),
        'choose_from_most_used'      => __('Choose from the most used', 'makesf'),
        'popular_items'              => __('Popular Categories', 'makesf'),
        'search_items'               => __('Search Categories', 'makesf'),
        'not_found'                  => __('Not Found', 'makesf'),
        'no_terms'                   => __('No categories', 'makesf'),
        'items_list'                 => __('Categories list', 'makesf'),
        'items_list_navigation'      => __('Categories list navigation', 'makesf'),
    );

    $args = array(
        'labels'                     => $labels,
        'hierarchical'               => true,
        'public'                     => false,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => false,
        'show_tagcloud'              => false,
        'capabilities'               => array(
            'manage_terms' => 'manage_options',
            'edit_terms' => 'manage_options',
            'delete_terms' => 'manage_options',
            'assign_terms' => 'manage_options',
        ),
        'show_in_rest'               => false,
    );

    register_taxonomy('volunteer_task_category', array('volunteer_task'), $args);
}
add_action('init', 'make_register_volunteer_task_taxonomy', 0);

/**
 * Add ACF fields for volunteer tasks
 */
function make_register_volunteer_task_fields() {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(array(
        'key' => 'group_volunteer_task_fields',
        'title' => 'Volunteer Task Details',
        'fields' => array(
            array(
                'key' => 'field_volunteer_task_estimated_duration',
                'label' => 'Estimated Duration (minutes)',
                'name' => 'estimated_duration',
                'type' => 'number',
                'instructions' => 'How long should this task take to complete?',
                'required' => 1,
                'default_value' => 60,
                'min' => 5,
                'max' => 480, // 8 hours max
                'step' => 5,
                'prepend' => '',
                'append' => 'minutes',
            ),
            array(
                'key' => 'field_volunteer_task_priority',
                'label' => 'Priority',
                'name' => 'priority',
                'type' => 'select',
                'instructions' => 'How urgent is this task?',
                'required' => 1,
                'choices' => array(
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'urgent' => 'Urgent',
                ),
                'default_value' => 'medium',
                'allow_null' => 0,
                'multiple' => 0,
                'ui' => 1,
                'return_format' => 'value',
            ),
            array(
                'key' => 'field_volunteer_task_instructions',
                'label' => 'Detailed Instructions',
                'name' => 'instructions',
                'type' => 'textarea',
                'instructions' => 'Provide step-by-step instructions for completing this task',
                'required' => 0,
                'rows' => 5,
                'new_lines' => 'wpautop',
            ),
            array(
                'key' => 'field_volunteer_task_required_skills',
                'label' => 'Required Skills/Badges',
                'name' => 'required_skills',
                'type' => 'relationship',
                'instructions' => 'Select any badges/certifications required for this task',
                'required' => 0,
                'post_type' => array('certs'),
                'taxonomy' => '',
                'filters' => array('search'),
                'elements' => '',
                'min' => '',
                'max' => '',
                'return_format' => 'id',
            ),
            array(
                'key' => 'field_volunteer_task_location',
                'label' => 'Location/Area',
                'name' => 'location',
                'type' => 'text',
                'instructions' => 'Where in the makerspace should this task be completed?',
                'required' => 0,
                'placeholder' => 'e.g., Wood Shop, Front Desk, Storage Room',
            ),
            array(
                'key' => 'field_volunteer_task_tools_needed',
                'label' => 'Tools/Materials Needed',
                'name' => 'tools_needed',
                'type' => 'textarea',
                'instructions' => 'List any tools or materials needed for this task',
                'required' => 0,
                'rows' => 3,
            ),
            array(
                'key' => 'field_volunteer_task_completion_notes',
                'label' => 'Completion Notes',
                'name' => 'completion_notes',
                'type' => 'textarea',
                'instructions' => 'What should volunteers note when they complete this task?',
                'required' => 0,
                'rows' => 3,
                'placeholder' => 'e.g., Take photos, record quantities, note any issues',
            ),
            array(
                'key' => 'field_volunteer_task_type',
                'label' => 'Task Type',
                'name' => 'task_type',
                'type' => 'select',
                'instructions' => 'Is this a one-time task or does it repeat?',
                'required' => 1,
                'choices' => array(
                    'one_time' => 'One-Time Task (disappears when completed)',
                    'recurring' => 'Recurring Task (always available)',
                ),
                'default_value' => 'one_time',
                'allow_null' => 0,
                'multiple' => 0,
                'ui' => 1,
                'return_format' => 'value',
            ),
            array(
                'key' => 'field_volunteer_task_assigned_to',
                'label' => 'Assigned To',
                'name' => 'assigned_to',
                'type' => 'user',
                'instructions' => 'Assign this task to a specific volunteer (optional)',
                'required' => 0,
                'multiple' => 0,
                'allow_null' => 1,
                'return_format' => 'id',
            ),
            array(
                'key' => 'field_volunteer_task_status',
                'label' => 'Task Status',
                'name' => 'task_status',
                'type' => 'select',
                'instructions' => 'Current status of this task',
                'required' => 1,
                'choices' => array(
                    'available' => 'Available',
                    'in_progress' => 'In Progress',
                    'completed' => 'Completed',
                    'on_hold' => 'On Hold',
                ),
                'default_value' => 'available',
                'allow_null' => 0,
                'multiple' => 0,
                'ui' => 1,
                'return_format' => 'value',
            ),
            array(
                'key' => 'field_volunteer_task_completed_by',
                'label' => 'Completed By',
                'name' => 'completed_by',
                'type' => 'user',
                'instructions' => 'Who completed this task?',
                'required' => 0,
                'multiple' => 0,
                'allow_null' => 1,
                'return_format' => 'id',
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_volunteer_task_status',
                            'operator' => '==',
                            'value' => 'completed',
                        ),
                    ),
                ),
            ),
            array(
                'key' => 'field_volunteer_task_completed_date',
                'label' => 'Completion Date',
                'name' => 'completed_date',
                'type' => 'date_time_picker',
                'instructions' => 'When was this task completed?',
                'required' => 0,
                'display_format' => 'M j, Y g:i a',
                'return_format' => 'Y-m-d H:i:s',
                'first_day' => 1,
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_volunteer_task_status',
                            'operator' => '==',
                            'value' => 'completed',
                        ),
                    ),
                ),
            ),
            array(
                'key' => 'field_volunteer_task_completion_notes_actual',
                'label' => 'Actual Completion Notes',
                'name' => 'completion_notes_actual',
                'type' => 'textarea',
                'instructions' => 'Notes from the volunteer who completed this task',
                'required' => 0,
                'rows' => 3,
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_volunteer_task_status',
                            'operator' => '==',
                            'value' => 'completed',
                        ),
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'volunteer_task',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
    ));
}
add_action('acf/init', 'make_register_volunteer_task_fields');

/**
 * Get available volunteer tasks
 */
function make_get_available_volunteer_tasks($user_id = null, $include_completed = false) {
    $meta_query = array(
        'relation' => 'AND',
        array(
            'key' => 'priority',
            'value' => array('urgent', 'high', 'medium', 'low'),
            'compare' => 'IN',
        ),
    );
    
    // Only show available tasks unless specifically requesting completed ones
    if (!$include_completed) {
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key' => 'task_status',
                'value' => array('available', 'in_progress'),
                'compare' => 'IN',
            ),
            array(
                'key' => 'task_status',
                'compare' => 'NOT EXISTS',
            ),
        );
    }

    $args = array(
        'post_type' => 'volunteer_task',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
        'meta_query' => $meta_query,
    );

    $tasks = get_posts($args);
    $available_tasks = array();

    foreach ($tasks as $task) {
        $task_status = get_field('task_status', $task->ID) ?: 'available';
        $task_type = get_field('task_type', $task->ID) ?: 'one_time';
        $assigned_to = get_field('assigned_to', $task->ID);
        $completed_by = get_field('completed_by', $task->ID);
        $completed_date = get_field('completed_date', $task->ID);
        
        // Skip completed one-time tasks unless specifically requested
        if (!$include_completed && $task_status === 'completed' && $task_type === 'one_time') {
            continue;
        }
        
        // If task is assigned to someone specific, only show it to that person (unless admin viewing)
        if ($assigned_to && $user_id && $assigned_to != $user_id && !current_user_can('manage_options')) {
            continue;
        }

        $task_data = array(
            'id' => $task->ID,
            'title' => $task->post_title,
            'description' => $task->post_content,
            'estimated_duration' => get_field('estimated_duration', $task->ID),
            'priority' => get_field('priority', $task->ID),
            'instructions' => get_field('instructions', $task->ID),
            'location' => get_field('location', $task->ID),
            'tools_needed' => get_field('tools_needed', $task->ID),
            'required_skills' => get_field('required_skills', $task->ID),
            'task_type' => $task_type,
            'task_status' => $task_status,
            'assigned_to' => $assigned_to,
            'completed_by' => $completed_by,
            'completed_date' => $completed_date,
            'completion_notes_actual' => get_field('completion_notes_actual', $task->ID),
        );

        // Get categories
        $categories = wp_get_post_terms($task->ID, 'volunteer_task_category');
        $task_data['categories'] = array();
        foreach ($categories as $category) {
            $task_data['categories'][] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
            );
        }

        // Check if user has required skills (if user_id provided)
        $task_data['user_qualified'] = true;
        if ($user_id && !empty($task_data['required_skills'])) {
            $user_badges = get_field('certifications', 'user_' . $user_id);
            $user_badge_ids = array();
            if ($user_badges) {
                foreach ($user_badges as $badge) {
                    $user_badge_ids[] = $badge->ID;
                }
            }

            foreach ($task_data['required_skills'] as $required_skill) {
                if (!in_array($required_skill, $user_badge_ids)) {
                    $task_data['user_qualified'] = false;
                    break;
                }
            }
        }
        
        // Add assignment info for display
        if ($assigned_to) {
            $assigned_user = get_user_by('ID', $assigned_to);
            $task_data['assigned_to_name'] = $assigned_user ? $assigned_user->display_name : 'Unknown User';
            $task_data['is_assigned_to_current_user'] = ($user_id && $assigned_to == $user_id);
        }
        
        // Add completion info for display
        if ($completed_by) {
            $completed_user = get_user_by('ID', $completed_by);
            $task_data['completed_by_name'] = $completed_user ? $completed_user->display_name : 'Unknown User';
        }

        $available_tasks[] = $task_data;
    }

    return $available_tasks;
}

/**
 * Record task completion
 */
function make_record_task_completion($session_id, $task_ids) {
    global $wpdb;
    
    if (empty($task_ids) || !is_array($task_ids)) {
        return false;
    }

    $table_name = $wpdb->prefix . 'volunteer_sessions';
    
    // Get current tasks
    $current_tasks = $wpdb->get_var($wpdb->prepare(
        "SELECT tasks_completed FROM $table_name WHERE id = %d",
        $session_id
    ));

    $tasks_array = array();
    if (!empty($current_tasks)) {
        $tasks_array = json_decode($current_tasks, true);
        if (!is_array($tasks_array)) {
            $tasks_array = array();
        }
    }

    // Add new tasks
    foreach ($task_ids as $task_id) {
        if (!in_array($task_id, $tasks_array)) {
            $tasks_array[] = (int) $task_id;
        }
    }

    // Update database
    $result = $wpdb->update(
        $table_name,
        array('tasks_completed' => json_encode($tasks_array)),
        array('id' => $session_id),
        array('%s'),
        array('%d')
    );

    return $result !== false;
}

/**
 * Mark task as completed
 */
function make_mark_task_completed($task_id, $user_id, $notes = '') {
    if (!$task_id || !$user_id) {
        return false;
    }
    
    $task_type = get_field('task_type', $task_id) ?: 'one_time';
    
    // For one-time tasks, mark as completed
    if ($task_type === 'one_time') {
        update_field('task_status', 'completed', $task_id);
        update_field('completed_by', $user_id, $task_id);
        update_field('completed_date', current_time('mysql'), $task_id);
        
        if (!empty($notes)) {
            update_field('completion_notes_actual', $notes, $task_id);
        }
    }
    // For recurring tasks, we don't change the status - they remain available
    
    return true;
}

/**
 * Get tasks assigned to a specific user
 */
function make_get_assigned_tasks($user_id) {
    $args = array(
        'post_type' => 'volunteer_task',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'assigned_to',
                'value' => $user_id,
                'compare' => '=',
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => 'task_status',
                    'value' => array('available', 'in_progress'),
                    'compare' => 'IN',
                ),
                array(
                    'key' => 'task_status',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        ),
    );
    
    return get_posts($args);
}

/**
 * Quick complete task function for admin
 */
function make_quick_complete_task($task_id, $user_id = null, $notes = '') {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    return make_mark_task_completed($task_id, $user_id, $notes);
}

/**
 * Get task statistics
 */
function make_get_task_statistics($task_id = null) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'volunteer_sessions';
    
    if ($task_id) {
        // Statistics for a specific task
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, duration_minutes, signin_time 
            FROM $table_name 
            WHERE status = 'completed' 
            AND tasks_completed LIKE %s",
            '%"' . $task_id . '"%'
        ));

        return array(
            'task_id' => $task_id,
            'completion_count' => count($results),
            'unique_volunteers' => count(array_unique(wp_list_pluck($results, 'user_id'))),
            'sessions' => $results,
        );
    } else {
        // Overall task statistics
        $all_sessions = $wpdb->get_results(
            "SELECT tasks_completed FROM $table_name WHERE status = 'completed' AND tasks_completed IS NOT NULL"
        );

        $task_counts = array();
        foreach ($all_sessions as $session) {
            $tasks = json_decode($session->tasks_completed, true);
            if (is_array($tasks)) {
                foreach ($tasks as $task_id) {
                    $task_counts[$task_id] = isset($task_counts[$task_id]) ? $task_counts[$task_id] + 1 : 1;
                }
            }
        }

        arsort($task_counts);
        return $task_counts;
    }
}

/**
 * Create default volunteer task categories
 */
function make_create_default_volunteer_categories() {
    $default_categories = array(
        'Maintenance' => 'General maintenance and upkeep tasks',
        'Organization' => 'Organizing and cleaning tasks',
        'Events' => 'Event setup and support tasks',
        'Teaching' => 'Assisting with classes and workshops',
        'Administrative' => 'Office and administrative tasks',
        'Safety' => 'Safety inspections and improvements',
    );

    foreach ($default_categories as $name => $description) {
        if (!term_exists($name, 'volunteer_task_category')) {
            wp_insert_term($name, 'volunteer_task_category', array(
                'description' => $description,
                'slug' => sanitize_title($name),
            ));
        }
    }
}

// Create default categories on activation
register_activation_hook(MAKESF_PLUGIN_FILE, 'make_create_default_volunteer_categories');

/**
 * Add volunteer task capabilities to user roles
 */
function make_add_volunteer_task_capabilities() {
    // Get administrator and editor roles
    $admin_role = get_role('administrator');
    $editor_role = get_role('editor');
    
    // Capabilities to add
    $capabilities = array(
        'edit_volunteer_task',
        'read_volunteer_task',
        'delete_volunteer_task',
        'edit_volunteer_tasks',
        'edit_others_volunteer_tasks',
        'publish_volunteer_tasks',
        'read_private_volunteer_tasks',
        'delete_volunteer_tasks',
        'delete_private_volunteer_tasks',
        'delete_published_volunteer_tasks',
        'delete_others_volunteer_tasks',
        'edit_private_volunteer_tasks',
        'edit_published_volunteer_tasks',
    );
    
    // Add capabilities to administrator
    if ($admin_role) {
        foreach ($capabilities as $cap) {
            $admin_role->add_cap($cap);
        }
    }
    
    // Add capabilities to editor
    if ($editor_role) {
        foreach ($capabilities as $cap) {
            $editor_role->add_cap($cap);
        }
    }
}

// Add capabilities on plugin activation
register_activation_hook(MAKESF_PLUGIN_FILE, 'make_add_volunteer_task_capabilities');

// Also add capabilities on init to ensure they exist
add_action('init', function() {
    static $capabilities_added = false;
    if (!$capabilities_added) {
        make_add_volunteer_task_capabilities();
        $capabilities_added = true;
    }
}, 11);