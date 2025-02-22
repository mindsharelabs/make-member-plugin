<?php

add_filter('mindevents_attendee_columns', function($columns) {
	$columns['is-member'] = 'Membership';
	$columns['make-badges'] = 'Badges';
	$columns['safety-waiver'] = 'Safety Waiver';

	return $columns;
});


add_filter('mindevents_attendee_data', function($data) {
	$membership = '';
	$badges = '';
	$has_waiver = get_user_meta( $data['user_id'], 'waiver_complete', true );

	if(function_exists('wc_memberships_get_user_active_memberships')) :
		$active_memberships = wc_memberships_get_user_active_memberships($data['user_id']);
		if($active_memberships) :
			foreach($active_memberships as $membership) :
				$membership .= $membership->plan->name;
				if(next($active_memberships)) :
					$membership .= ' & ';
				endif;
			endforeach;
		endif;
	endif;
	$data['is-member'] = $membership;


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

		$sub_events = get_post_meta('sub_events', $post_ID);

		foreach($sub_events as $post_ID) :
			$start_date = get_post_meta($post_ID, 'event_start_time_stamp', true);
			$end_date = get_post_meta($post_ID, 'event_end_time_stamp', true);

			if(isset($_POST['acf'])) :
				$resources = (get_field('create_booking', $post_ID) ? get_field('create_booking', $post_ID) : $_POST['acf']['field_63a1325d5221c']);
			else : 
				$resources = false;
			endif;
			//if we have resources 
			if($resources) :
				foreach($resources as $resource) :
					//remove all dates that are in the past
					make_clean_bookable_resource($resource);

					$data = array(
						'start_date' => $start_date,
						'end_date' => $end_date,
						'post_id' => $post_ID
					);

					$has_booking = make_check_for_booking($resource, $data);
					if($has_booking) :
						return;
					endif;	

					$start_date_obj = new DateTime();
					$end_date_obj = new DateTime();

					$start_date_obj->setTimestamp($start_date);
					$end_date_obj->setTimestamp($end_date);

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
			$avail_from_date = new DateTime();
			$avail_to_date = new DateTime();
			$avail_from_date->setTimestamp(strtotime($availability['from_date'] . ' ' . $availability['from']));
			$avail_to_date->setTimestamp(strtotime($availability['to_date'] . ' ' . $availability['to']));
			
			
			$event_start_date = new DateTime();
			$event_end_date = new DateTime();
			$event_start_date->setTimestamp($data['start_date']);
			$event_end_date->setTimestamp($data['end_date']);

			if($avail_from_date == $event_start_date && $avail_to_date == $event_end_date) :
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
    $tickets = Tribe__Tickets__Tickets::get_all_event_tickets($event_id);
    foreach ($tickets as $ticket) {
		
        if ($ticket->available() > 0) {
            return true;
        }
    }
    return false;
}