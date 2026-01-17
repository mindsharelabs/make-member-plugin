<?php

add_filter('mindevents_attendee_columns', function($columns) {
	// Insert columns in the correct order after 'check_in'
	$new_columns = array();
	
	foreach ($columns as $key => $value) {
		$new_columns[$key] = $value;
		
		// After 'check_in', add our custom columns in the correct order
		if ($key === 'check_in') {
			$new_columns['is-member'] = 'Membership';
			$new_columns['safety-waiver'] = 'Safety Waiver';
			$new_columns['make-badges'] = 'Badges';
			$new_columns['badge-attendee'] = 'Badge Attendee';
		}
	}
	
	return $new_columns;
});

add_filter('mindevents_attendee_data', function($data) {
	$membership_text = '';
	$has_waiver = get_user_meta( $data['user_id'], 'waiver_complete', true );

	if(function_exists('wc_memberships_get_user_active_memberships')) :
		$active_memberships = wc_memberships_get_user_active_memberships($data['user_id']);
		if($active_memberships) :
			foreach($active_memberships as $membership) :
				$membership_text .= $membership->plan->name;
				if(next($active_memberships)) :
					$membership_text .= ' & ';
				endif;
			endforeach;
		endif;
	endif;
	$data['is-member'] = $membership_text;

	if($has_waiver != true) :
		$waiver_value = '<span class="badge badge-success text-red">Incomplete</span>';
	else :
		$waiver_value = '<span class="badge badge-success">Signed</span>';
	endif;
	$data['safety-waiver'] = $waiver_value;

	// Get user's badges for display (without action button)
	$data['make-badges'] = make_get_user_badges_display($data);
	
	// Badge management action button (separate column)
	$data['badge-attendee'] = make_get_badge_action_button($data);

	return $data;
}, 1, 3);



add_action( 'save_post', 'make_create_booking_for_event', 999, 2);
// add_action( 'publish_tribe_events', 'make_create_booking_for_event', 999, 2);
function make_create_booking_for_event( $post_ID, $post) {

	if(!class_exists( 'WC_Bookings' )) :
		return;
	endif;
	
	if($post->post_type == 'events') :
		


		$defaults = array(
			'meta_query' => array(
			  'relation' => 'AND',
			  'start_clause' => array(
				'key' => 'event_start_time_stamp',
				'compare' => 'EXISTS',
			  ),
			  'date_clause' => array(
				'key' => 'event_end_time_stamp',
				'compare' => 'EXISTS',
			  ),
			),
			'orderby' => 'meta_value',
			'meta_key' => 'event_start_time_stamp',
			'meta_type' => 'DATETIME',
			'order'            => 'ASC',
			'post_type'        => 'sub_event',
			'post_parent'      => $post_ID,
			'suppress_filters' => true,
			'posts_per_page'   => -1
		);
		$sub_events = get_posts($defaults);

	
		if(count($sub_events) > 0) :
			foreach($sub_events as $post) :
				$start_date = get_post_meta($post->ID, 'event_start_time_stamp', true);
				$end_date = get_post_meta($post->ID, 'event_end_time_stamp', true);
				$start_date_obj = new DateTimeImmutable($start_date);
				$end_date_obj = new DateTimeImmutable($end_date);
				$today = new DateTimeImmutable();

				//if the event is in the past, don't do anything
				if($today->getTimestamp() > $end_date_obj->getTimestamp()) :
					continue;
				endif;

				if(isset($_POST['acf'])) :
					$resources = (get_field('create_booking', $post->ID) ? get_field('create_booking', $post->ID) : $_POST['acf']['field_63a1325d5221c']);
				else : 
					$resources = false;
				endif;
				//if we have resources 
				if($resources) :
					foreach($resources as $resource) :
						//remove all dates that are in the past
						make_clean_bookable_resource($resource);

						$data = array(
							'start_date' => $start_date_obj->getTimestamp(),
							'end_date' => $end_date_obj->getTimestamp(),
							'post_id' => $post->ID
						);
			
						$has_booking = make_check_for_booking($resource, $data);
						if($has_booking) :
							continue;
						endif;	

					
						$booking_availability = get_post_meta($resource, '_wc_booking_availability', true);
						$booking_availability[] = array(
							'type' => 'custom:daterange',
							'bookable' => 'no',
							'priority' => 1,
							'from' => $start_date_obj->format('H:s'),
							'to' => $end_date_obj->format('H:s'),
							'from_date' => $start_date_obj->format('Y-m-d'),
							'to_date' => $end_date_obj->format('Y-m-d')
						);
						
						update_post_meta($resource, '_wc_booking_availability', $booking_availability);
					endforeach;
				endif; //end if we have resources
			endforeach; //end foreach event
		endif; //end if we have sub events
	endif; //end if post type is tribe_events


}



/*
check if a booking already exists for a resource
returns true if a booking already exists
returns false if a booking does not exist
$resource = the resource post ID
#$data = array(
	'start_date' => $start_date,
	'end_date' => $end_date,
	'post_id' => $post_ID
);
*/
function make_check_for_booking($resource, $data) {
	$has_booking = false;
	$booking_availability = get_post_meta($resource, '_wc_booking_availability', true);
	if(!$booking_availability) :
		return false;
	
	else :
		foreach($booking_availability as $availability) :
			$avail_from_date = strtotime($availability['from_date'] . ' ' . $availability['from']);
			$avail_to_date = strtotime($availability['to_date'] . ' ' . $availability['to']);

			if($avail_from_date == $data['start_date'] && $avail_to_date == $data['end_date']) :
				$has_booking = true;
				break;
			endif;
		endforeach;	
	endif;
	return $has_booking;
}



function make_clean_bookable_resource($resource) {
	$booking_availability = get_post_meta($resource, '_wc_booking_availability', true);
	$today = new DateTime();
	$today_formatted = $today->format('Y-m-d');
	if($booking_availability) :
		foreach($booking_availability as $key => $availability) :
			if($availability['to_date'] < $today_formatted) :
				unset($booking_availability[$key]);
			endif;	
		endforeach;
	endif;
	update_post_meta($resource, '_wc_booking_availability', $booking_availability);
};

if( function_exists('acf_add_local_field_group') ):
	acf_add_local_field_group(array(
		'key' => 'group_63a1325d1933b',
		'title' => 'Additional Event Options',
		'fields' => array(
			array(
				'key' => 'field_63a1325d5221c',
				'label' => 'Block Availability',
				'name' => 'create_booking',
				'aria-label' => '',
				'type' => 'relationship',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'post_type' => array(
					0 => 'bookable_resource',
				),
				'taxonomy' => '',
				'filters' => array(
					0 => 'search',
				),
				'return_format' => 'id',
				'min' => '',
				'max' => '',
				'elements' => '',
			),
			
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'events',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'side',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	));
endif;		




function make_event_has_available_tickets($event_id) {
	if(!function_exists('wc_get_product')) :
		return false;
	endif;
	//get_linked_product
	$product_id = get_post_meta($event_id, 'linked_product', true);

	if($product_id) :
		$product = wc_get_product($product_id);
		if(!$product) :
			return false;
		else :
			return $product->is_in_stock();
		endif;
	endif;
    return false;
}

/**
 * Get user's badges for display only (no action button)
 */
function make_get_user_badges_display($data) {
    $user_id = $data['user_id'];
    
    // Get user's current certs using ACF - try both field names for compatibility
    $user_certs = get_field('certs', 'user_' . $user_id);
    if (!$user_certs) {
        $user_certs = get_field('certifications', 'user_' . $user_id);
    }
    
    $cert_names = array();
    
    if ($user_certs && is_array($user_certs)) {
        foreach ($user_certs as $cert) {
            $cert_id = null;
            $cert_title = null;
            
            if (is_object($cert) && isset($cert->post_title)) {
                // ACF Post Object
                $cert_id = $cert->ID;
                $cert_title = $cert->post_title;
            } elseif (is_array($cert) && isset($cert['ID'])) {
                // ACF Post Object as array
                $cert_id = $cert['ID'];
                $cert_post = get_post($cert['ID']);
                if ($cert_post) {
                    $cert_title = $cert_post->post_title;
                }
            } elseif (is_numeric($cert)) {
                // Post ID
                $cert_id = intval($cert);
                $cert_post = get_post($cert);
                if ($cert_post) {
                    $cert_title = $cert_post->post_title;
                }
            }
            
            if ($cert_id && $cert_title) {
                $cert_names[] = esc_html($cert_title);
            }
        }
    }
    
    return !empty($cert_names) ? implode(', ', $cert_names) : '<em>No badges</em>';
}

/**
 * Generate badge action button for attendee table
 */
function make_get_badge_action_button($data) {
    $user_id = $data['user_id'];
    
    // Get user's current certs using ACF - try both field names for compatibility
    $user_certs = get_field('certs', 'user_' . $user_id);
    if (!$user_certs) {
        $user_certs = get_field('certifications', 'user_' . $user_id);
    }
    
    $user_cert_ids = array(); // Keep track of cert IDs for comparison
    
    if ($user_certs && is_array($user_certs)) {
        foreach ($user_certs as $cert) {
            $cert_id = null;
            
            if (is_object($cert) && isset($cert->ID)) {
                $cert_id = $cert->ID;
            } elseif (is_array($cert) && isset($cert['ID'])) {
                $cert_id = $cert['ID'];
            } elseif (is_numeric($cert)) {
                $cert_id = intval($cert);
            }
            
            if ($cert_id) {
                $user_cert_ids[] = $cert_id;
            }
        }
    }
    
    // Check if this is a Badge Class event and user has completed order
    $is_badge_class = false;
    $order = null;
    $event_id = null;
    
    // Try to get event context from various sources
    if (isset($data['event_id'])) {
        $event_id = $data['event_id'];
    } elseif (isset($_GET['post']) && get_post_type($_GET['post']) === 'events') {
        $event_id = $_GET['post'];
    } elseif (isset($data['sub_event'])) {
        // Get parent event from sub_event
        $parent_id = wp_get_post_parent_id($data['sub_event']);
        if ($parent_id) {
            $event_id = $parent_id;
        }
    }
    
    if ($event_id) {
        // Check if this event has the "Badge Class" category
        $event_categories = get_the_terms($event_id, 'event_category');
        if ($event_categories && !is_wp_error($event_categories)) {
            foreach ($event_categories as $category) {
                if (stripos($category->name, 'badge') !== false) {
                    $is_badge_class = true;
                    break;
                }
            }
        }
        
        // Get order if available
        if (isset($data['order_id'])) {
            $order = wc_get_order($data['order_id']);
        }
    }
    
    // Add badge management interface for Badge Class events
    if ($is_badge_class && $order && $order->get_status() == 'completed') {
        $selected_cert_id = get_post_meta($event_id, 'badge_cert_id', true);
        if ($selected_cert_id) {
            // Check if user already has this cert
            $has_cert = in_array(intval($selected_cert_id), $user_cert_ids);
            
            $selected_cert = get_post($selected_cert_id);
            $cert_name = $selected_cert ? $selected_cert->post_title : 'Badge';
            
            return '<button
                class="make-attendee-badge-toggle ' . ($has_cert ? 'badged' : '') . '"
                data-user_id="' . $user_id . '"
                data-cert_id="' . $selected_cert_id . '"
                data-event_id="' . $event_id . '"
                data-akey="' . (isset($data['akey']) ? $data['akey'] : '') . '"
                data-sub_event="' . (isset($data['sub_event']) ? $data['sub_event'] : '') . '">
                <span class="badge-status">' . ($has_cert ? 'Remove ' . $cert_name : 'Award ' . $cert_name) . '</span>
            </button>';
        } else {
            return '<em>No certificate selected</em>';
        }
    }
    
    return ''; // No action button for non-badge events
}

/**
 * Add AJAX handler for badge toggle
 */
add_action('wp_ajax_make_badge_toggle', 'make_handle_badge_toggle');
function make_handle_badge_toggle() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'make_badge_toggle_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $user_id = intval($_POST['user_id']);
    $cert_id = intval($_POST['cert_id']);
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : null;
    
    if (!$user_id || !$cert_id) {
        wp_send_json_error('Missing user ID or certificate ID');
        return;
    }
    
    // Check user capabilities
    if (!current_user_can('edit_users')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Get user's current certs - try both field names for compatibility
    $user_certs = get_field('certs', 'user_' . $user_id);
    $field_name = 'certs';
    
    if (!$user_certs) {
        $user_certs = get_field('certifications', 'user_' . $user_id);
        $field_name = 'certifications';
    }
    
    if (!is_array($user_certs)) {
        $user_certs = array();
    }
    
    // Check if user already has this cert
    $has_cert = false;
    $cert_index = null;
    foreach ($user_certs as $index => $cert) {
        $existing_cert_id = null;
        if (is_object($cert) && isset($cert->ID)) {
            $existing_cert_id = $cert->ID;
        } elseif (is_array($cert) && isset($cert['ID'])) {
            $existing_cert_id = $cert['ID'];
        } elseif (is_numeric($cert)) {
            $existing_cert_id = intval($cert);
        }
        
        if ($existing_cert_id == $cert_id) {
            $has_cert = true;
            $cert_index = $index;
            break;
        }
    }
    
    if ($has_cert) {
        // Remove cert from user profile
        unset($user_certs[$cert_index]);
        $user_certs = array_values($user_certs); // Re-index array
        $new_status = false;
    } else {
        // Add cert to user profile
        $user_certs[] = intval($cert_id);
        $new_status = true;
    }
    
    // Update user's certs using ACF with the correct field name
    update_field($field_name, $user_certs, 'user_' . $user_id);
    
    // Get badge name for response
    $cert_post = get_post($cert_id);
    $badge_name = $cert_post ? $cert_post->post_title : 'Badge';
    
    // Fire action hook for other plugins to use
    do_action('make_after_badge_toggled', $user_id, $cert_id, $new_status, $badge_name, $event_id);
    
    // Get updated badge display for the user
    $updated_badges_display = make_get_user_badges_display(array('user_id' => $user_id));
    
    wp_send_json_success(array(
        'new_status' => $new_status,
        'html' => ($new_status ? 'Remove ' . $badge_name : 'Award ' . $badge_name),
        'message' => ($new_status ? 'Badge awarded successfully!' : 'Badge removed successfully!'),
        'updated_badges' => $updated_badges_display
    ));
}