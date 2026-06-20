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


// ===========================
// Membership Account Actions
// ===========================

function make_membership_action_url( $action, $membership_id ) {
    return wp_nonce_url(
        add_query_arg(
            array(
                'make_membership_action' => $action,
                'membership_id'          => absint( $membership_id ),
            ),
            wc_get_account_endpoint_url( 'my-membership' )
        ),
        'make_membership_' . $action . '_' . $membership_id
    );
}

/**
 * Process Pause, Resume, and Cancel at Renewal requests.
 * Uses the same query-param + nonce pattern as WC Memberships' own cancel action.
 */
add_action( 'template_redirect', 'make_handle_membership_actions' );
function make_handle_membership_actions() {
    if ( ! isset( $_REQUEST['make_membership_action'], $_REQUEST['membership_id'], $_REQUEST['_wpnonce'] ) ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
        exit;
    }

    $action        = sanitize_key( $_REQUEST['make_membership_action'] );
    $membership_id = absint( $_REQUEST['membership_id'] );
    $nonce         = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
    $redirect_to = wc_get_account_endpoint_url( 'my-membership' );

    if ( ! wp_verify_nonce( $nonce, 'make_membership_' . $action . '_' . $membership_id ) ) {
        wc_add_notice( __( 'Invalid request. Please try again.', 'make-member-plugin' ), 'error' );
        wp_safe_redirect( $redirect_to );
        exit;
    }

    $user_membership = wc_memberships_get_user_membership( $membership_id );

    if ( ! $user_membership || $user_membership->get_user_id() !== get_current_user_id() ) {
        wc_add_notice( __( 'Membership not found.', 'make-member-plugin' ), 'error' );
        wp_safe_redirect( $redirect_to );
        exit;
    }

    $subscription = null;
    if ( method_exists( $user_membership, 'get_subscription' ) ) {
        $subscription = $user_membership->get_subscription();
    } elseif ( function_exists( 'wcs_get_subscriptions_for_order' ) && $user_membership->get_order_id() ) {
        $subs = wcs_get_subscriptions_for_order( $user_membership->get_order_id() );
        $subscription = ! empty( $subs ) ? reset( $subs ) : null;
    }

    switch ( $action ) {

        case 'pause':
            if ( ! $user_membership->has_status( 'active' ) ) {
                wc_add_notice( __( 'Only an active membership can be paused.', 'make-member-plugin' ), 'error' );
                break;
            }
            if ( $subscription && $subscription->has_status( 'active' ) ) {
                $subscription->update_status( 'on-hold', __( 'Paused by member via account page.', 'make-member-plugin' ) );
            }
            $user_membership->pause_membership( __( 'Paused by member via account page.', 'make-member-plugin' ) );
            wc_add_notice( __( 'Your membership has been paused.', 'make-member-plugin' ), 'success' );
            break;

        case 'resume':
            if ( ! $user_membership->has_status( 'paused' ) ) {
                wc_add_notice( __( 'Only a paused membership can be resumed.', 'make-member-plugin' ), 'error' );
                break;
            }
            if ( $subscription && $subscription->has_status( 'on-hold' ) ) {
                $subscription->update_status( 'active', __( 'Resumed by member via account page.', 'make-member-plugin' ) );
            }
            $user_membership->activate_membership( __( 'Resumed by member via account page.', 'make-member-plugin' ) );
            wc_add_notice( __( 'Your membership has been resumed.', 'make-member-plugin' ), 'success' );
            break;

        case 'do-not-renew':
            if ( ! $user_membership->has_status( 'active' ) ) {
                wc_add_notice( __( 'Only an active membership can be set to not renew.', 'make-member-plugin' ), 'error' );
                break;
            }
            if ( $subscription && $subscription->has_status( 'active' ) ) {
                $subscription->update_status( 'pending-cancel', __( 'Set to not renew by member via account page.', 'make-member-plugin' ) );
            }
            wc_add_notice( __( 'Your membership will remain active until the end of the current billing period, then cancel automatically.', 'make-member-plugin' ), 'success' );
            break;

        case 'cancel':
            if ( ! $user_membership->has_status( array( 'active', 'paused', 'complimentary' ) ) ) {
                wc_add_notice( __( 'This membership cannot be cancelled.', 'make-member-plugin' ), 'error' );
                break;
            }
            $user_membership->cancel_membership( __( 'Cancelled by member via account page.', 'make-member-plugin' ) );
            wc_add_notice( __( 'Your membership has been cancelled.', 'make-member-plugin' ), 'success' );
            break;

        default:
            wc_add_notice( __( 'Unknown action.', 'make-member-plugin' ), 'error' );
    }

    wp_safe_redirect( $redirect_to );
    exit;
}

/**
 * When a member uses WC Memberships' built-in Cancel button, also cancel the linked subscription
 * so billing stops immediately rather than continuing to the next renewal date.
 */
add_action( 'wc_memberships_cancelled_user_membership', 'make_cancel_linked_subscription_on_membership_cancel' );
function make_cancel_linked_subscription_on_membership_cancel( $membership_id ) {
    $user_membership = wc_memberships_get_user_membership( $membership_id );
    if ( ! $user_membership ) {
        return;
    }

    $subscription = null;
    if ( method_exists( $user_membership, 'get_subscription' ) ) {
        $subscription = $user_membership->get_subscription();
    } elseif ( function_exists( 'wcs_get_subscriptions_for_order' ) && $user_membership->get_order_id() ) {
        $subs = wcs_get_subscriptions_for_order( $user_membership->get_order_id() );
        $subscription = ! empty( $subs ) ? reset( $subs ) : null;
    }

    if ( $subscription && ! $subscription->has_status( array( 'cancelled', 'expired', 'trash', 'pending-cancel' ) ) ) {
        $subscription->update_status( 'cancelled', __( 'Cancelled when member cancelled their membership.', 'make-member-plugin' ) );
    }
}




// ===========================
// My Account Menu + Profile Endpoint
// ===========================

add_action( 'init', 'make_add_make_profile_endpoint' );
function make_add_make_profile_endpoint() {
    add_rewrite_endpoint( 'make-profile', EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'my-membership', EP_ROOT | EP_PAGES );
}

add_filter( 'query_vars', 'make_profile_query_vars', 0 );
function make_profile_query_vars( $vars ) {
    $vars[] = 'make-profile';
    $vars[] = 'my-membership';
    return $vars;
}

add_filter( 'woocommerce_account_menu_items', 'make_add_make_profile_link_my_account', 99 );
function make_add_make_profile_link_my_account( $items ) {
    // Remove unwanted items.
    foreach ( array( 'subscriptions', 'members-area', 'edit-account', 'bookings' ) as $key ) {
        unset( $items[ $key ] );
    }

    // Ensure our custom endpoints have labels.
    $items['my-membership'] = 'My Membership';
    $items['make-profile']  = 'My Public Profile';

    // Canonical order — keys absent from $items are skipped silently.
    $order = array(
        'dashboard',
        'tool-reservations',
        'my-membership',
        'member-calendar',
        'my-badges',
        'make-profile',
        'store-credit',
        'giftcards',
        'orders',
        'edit-address',
        'payment-methods',
        'customer-logout',
    );

    $ordered = array();
    foreach ( $order as $key ) {
        if ( isset( $items[ $key ] ) ) {
            $ordered[ $key ] = $items[ $key ];
        }
    }

    // Append anything not in the order list so no tab goes missing.
    foreach ( $items as $key => $label ) {
        if ( ! isset( $ordered[ $key ] ) ) {
            $ordered[ $key ] = $label;
        }
    }

    return $ordered;
}

function make_subscription_status_label( $status ) {
    $labels = array(
        'active'         => 'Active',
        'on-hold'        => 'Paused',
        'pending-cancel' => 'Cancels at renewal',
        'cancelled'      => 'Cancelled',
        'expired'        => 'Expired',
        'pending'        => 'Pending',
    );
    return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
}

function make_render_subscription_billing( $sub ) {
    $next_payment = $sub->get_date( 'next_payment' );
    $end_date     = $sub->get_date( 'end' );
    $sub_status   = $sub->get_status();
    $total        = $sub->get_total();
    $period       = $sub->get_billing_period();
    $interval     = (int) $sub->get_billing_interval();

    if ( 'pending-cancel' === $sub_status && $end_date ) {
        echo '<p><strong>Access until:</strong> '
            . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $end_date ) ) ) . '</p>';
    } elseif ( $next_payment ) {
        echo '<p><strong>Next payment:</strong> '
            . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $next_payment ) ) ) . '</p>';
    }

    if ( $total && $period ) {
        $interval_label = 1 === $interval ? $period : $interval . ' ' . $period . 's';
        echo '<p><strong>Billing:</strong> ' . wp_kses_post( wc_price( $total ) ) . ' / ' . esc_html( $interval_label ) . '</p>';
    }
}

// Renders a single unified card. $actions is an array of ['label' => '', 'url' => ''] entries.
function make_render_membership_card( $title, $status_label, $start, $subscription, $actions = array() ) {
    $view_url = $subscription
        ? wc_get_endpoint_url( 'view-subscription', $subscription->get_id(), wc_get_page_permalink( 'myaccount' ) )
        : null;

    echo '<div class="make-membership-card">';
    echo '<h3>' . esc_html( $title ) . '</h3>';
    echo '<p><strong>Status:</strong> ' . esc_html( $status_label ) . '</p>';

    if ( $start ) {
        echo '<p><strong>Since:</strong> '
            . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start ) ) ) . '</p>';
    }

    if ( $subscription ) {
        make_render_subscription_billing( $subscription );
    }

    $has_actions = ! empty( $actions ) || $view_url;
    if ( $has_actions ) {
        echo '<div class="make-membership-actions">';
        foreach ( $actions as $action ) {
            echo '<a href="' . esc_url( $action['url'] ) . '" class="button">' . esc_html( $action['label'] ) . '</a> ';
        }
        if ( $view_url ) {
            echo '<a href="' . esc_url( $view_url ) . '" class="button button-secondary">View Subscription</a>';
        }
        echo '</div>';
    }

    echo '</div>';
}

add_action( 'woocommerce_account_my-membership_endpoint', 'make_my_membership_content' );
function make_my_membership_content() {
    $user_id       = get_current_user_id();
    $shown_sub_ids = array();
    $has_content   = false;

    echo '<div class="make-membership-cards">';

    // ── Memberships ───────────────────────────────────────────────────
    $memberships = function_exists( 'wc_memberships_get_user_memberships' )
        ? wc_memberships_get_user_memberships( $user_id )
        : array();

    foreach ( $memberships as $membership ) {
        $has_content = true;
        $plan        = $membership->get_plan();
        $plan_name   = $plan ? $plan->get_name() : 'Membership';
        $status      = $membership->get_status();
        $start       = $membership->get_start_date();
        $id          = $membership->get_id();

        $subscription = null;
        if ( method_exists( $membership, 'get_subscription' ) ) {
            $subscription = $membership->get_subscription();
        } elseif ( function_exists( 'wcs_get_subscriptions_for_order' ) && $membership->get_order_id() ) {
            $subs         = wcs_get_subscriptions_for_order( $membership->get_order_id() );
            $subscription = ! empty( $subs ) ? reset( $subs ) : null;
        }
        if ( $subscription ) {
            $shown_sub_ids[] = $subscription->get_id();
        }

        $sub_is_pending_cancel = $subscription && $subscription->has_status( 'pending-cancel' );

        if ( 'active' === $status && $sub_is_pending_cancel ) {
            $status_label = 'Active (cancels at renewal)';
        } else {
            $status_label = make_subscription_status_label( $status );
        }

        $actions = array();
        if ( $subscription ) {
            // Subscription-backed membership: full suite of actions
            if ( $membership->has_status( 'active' ) && ! $sub_is_pending_cancel ) {
                $actions[] = array( 'label' => 'Pause',             'url' => make_membership_action_url( 'pause',        $id ) );
                $actions[] = array( 'label' => 'Cancel at Renewal', 'url' => make_membership_action_url( 'do-not-renew', $id ) );
            }
            if ( $membership->has_status( 'paused' ) ) {
                $actions[] = array( 'label' => 'Resume', 'url' => make_membership_action_url( 'resume', $id ) );
            }
        } else {
            // No linked subscription: only allow immediate cancellation
            if ( $membership->has_status( array( 'active', 'paused' ) ) ) {
                $actions[] = array( 'label' => 'Cancel', 'url' => make_membership_action_url( 'cancel', $id ) );
            }
        }

        make_render_membership_card( $plan_name, $status_label, $start, $subscription, $actions );
    }

    // ── Standalone subscriptions ──────────────────────────────────────
    if ( function_exists( 'wcs_get_users_subscriptions' ) ) {
        $all_subs   = wcs_get_users_subscriptions( $user_id );
        $standalone = array_filter( $all_subs, function( $sub ) use ( $shown_sub_ids ) {
            return ! in_array( $sub->get_id(), $shown_sub_ids, true );
        } );

        foreach ( $standalone as $sub ) {
            $has_content  = true;
            $names        = array();
            foreach ( $sub->get_items() as $item ) {
                $names[] = $item->get_name();
            }
            $sub_name     = ! empty( $names ) ? implode( ', ', $names ) : 'Subscription';
            $status_label = make_subscription_status_label( $sub->get_status() );
            $start        = $sub->get_date( 'date_created' );

            make_render_membership_card( $sub_name, $status_label, $start, $sub );
        }
    }

    echo '</div>'; // .make-membership-cards

    if ( ! $has_content ) {
        echo '<p>You do not have any active memberships or subscriptions.</p>';
    }
}

add_action( 'woocommerce_account_make-profile_endpoint', 'make_user_edit_form' );
function make_user_edit_form() {
    $current_user_id = get_current_user_id();
    $author_url      = get_author_posts_url( $current_user_id );
    include get_template_directory() . '/inc/user-edit-form.php';
}

add_filter( 'woocommerce_product_subcategories_args', function( $args ) {
    $exclude = array();
    $slugs   = array( 'track-products' );
    foreach ( $slugs as $slug ) {
        $term = get_term_by( 'slug', $slug, 'product_cat' );
        if ( $term && ! is_wp_error( $term ) ) {
            $exclude[] = $term->term_id;
        }
    }
    if ( ! empty( $exclude ) ) {
        $args['exclude'] = $exclude;
    }
    return $args;
} );