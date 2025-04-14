<?php

add_filter('mindevents_attendee_columns', function($columns) {
	$columns['is-member'] = 'Membership';
	$columns['make-badges'] = 'Badges';
	$columns['safety-waiver'] = 'Safety Waiver';

	return $columns;
});

add_filter('mindevents_attendee_data', function($data) {
	$membership_text = '';
	$badges = '';
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


	$user_badges = get_field('certifications', 'user_' . $data['user_id']);	
	if($user_badges) :
		foreach($user_badges as $badge) :
			$badges .= '<small class="small">' . get_the_title($badge) . '</small>';
			if(next($user_badges)) :
				$badges .= ', ';
			endif;
		endforeach;
	else :
		$badges = '<small class="badge badge-danger">No Badges</small>';	
	endif;
	$data['make-badges'] = $badges;


	
	if($has_waiver != true) :
		$waiver_value = '<span class="badge badge-success text-red">Incomplete</span>';
	else :
		$waiver_value = '<span class="badge badge-success">Signed</span>';
	endif;	

	$data['safety-waiver'] = $waiver_value;

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
				'key' => 'starttime',
				'compare' => 'EXISTS',
			  ),
			  'date_clause' => array(
				'key' => 'event_date',
				'compare' => 'EXISTS',
			  ),
			),
			'orderby' => 'meta_value',
			'meta_key' => 'event_time_stamp',
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