<?php
//Unsets some unuseful columns in thbe attendee table
add_filter('tribe_tickets_attendee_table_columns', function($columns) {
	unset($columns['security']);
	return $columns;
});



add_filter('tribe_tickets_attendee_table_columns', function($columns) {
	$columns['is-member'] = 'Membership';
	$columns['make-badges'] = 'Badges';
	$columns['safety-waiver'] = 'Safety Waiver';

	return $columns;
});


add_filter('tribe_events_tickets_attendees_table_column', function($value, $item, $column) {
	if($column == 'is-member') :
		if(function_exists('wc_memberships_get_user_active_memberships')) :
			$active_memberships = wc_memberships_get_user_active_memberships($item['user_id']);
			$value = '';
			if($active_memberships) :
				foreach($active_memberships as $membership) :
					$value .= $membership->plan->name;
					if(next($active_memberships)) :
						$value .= ' & ';
					endif;
				endforeach;
			endif;
		endif;
		return $value;
	endif;

	if($column == 'make-badges') :
		if(function_exists('wc_memberships_get_user_active_memberships')) :
			$user_badges = get_field('certifications', 'user_' . $item['user_id']);
			$value = '';
			if($user_badges) :
				foreach($user_badges as $badge) :
					$value .= '<small class="small">' . get_the_title($badge) . '</small>';
					if(next($user_badges)) :
						$value .= ', ';
					endif;
				endforeach;
			else :
				$value = '<small class="badge badge-danger">No Badges</small>';	
			endif;
		endif;
		return $value;
	endif;

	if($column == 'safety-waiver') :
		$has_waiver = get_user_meta( $item['user_id'], 'waiver_complete', true );
		if($has_waiver != true) :
			$value = '<span class="badge badge-success text-red">Incomplete</span>';
		else :
			$value = '<span class="badge badge-success">Signed</span>';
		endif;	
		return $value;
	endif;

	return $value;

}, 1, 3);



add_action( 'save_post_tribe_events', 'make_create_booking_for_event', 999, 2);
// add_action( 'publish_tribe_events', 'make_create_booking_for_event', 999, 2);
function make_create_booking_for_event( $post_ID, $post) {

	if(isset($_POST['acf'])) :
		if($post->post_type == 'tribe_events') :

			$start_date = Tribe__Events__Timezones::event_start_timestamp($post_ID, 'America/Denver');
	    	$end_date = Tribe__Events__Timezones::event_end_timestamp($post_ID, 'America/Denver');

	    	$has_booking = get_post_meta($post_ID, 'make_has_booking', true);
	    	if($has_booking) :
	    		$has_booking = !$_POST['acf']['field_63134513mas5221c'];
	    	endif;	

			// $bookable_product = 60863;//local
			$bookable_product = 60979;//remote
				
			
			$resources = (get_field('create_booking', $post_ID) ? get_field('create_booking', $post_ID) : $_POST['acf']['field_63a1325d5221c']);




			// mapi_write_log($bookable_product);
			// mapi_write_log($has_booking);
			// mapi_write_log($resources);
			if($resources && !$has_booking) : //if we have resources and DO NOT have a booking already
				// mapi_write_log('WooCommerce Exists?');
				// mapi_write_log(class_exists( 'woocommerce' )); 
				if(class_exists( 'woocommerce' )) :
					foreach($resources as $resource) :

						$defaults = array(
					        'product_id'  => $bookable_product, 
					        'start_date'  => $start_date,
					        'end_date'    => $end_date,
					        'resource_id' => $resource,
					    );
					
						$return = create_wc_booking( $bookable_product, $defaults, 'complete', false );
						
						if($return) :
							update_post_meta($post_ID, 'make_has_booking', true);
						endif;
					endforeach;
				endif;


			endif;

		endif;
	endif;
	

}
// 


if( function_exists('acf_add_local_field_group') ):
	acf_add_local_field_group(array(
		'key' => 'group_63a1325d1933b',
		'title' => 'Additional Event Options',
		'fields' => array(
			array (
	            'key' => 'field_63134513mas5221c',
	            'label' => 'Force Create Booking',
	            'name' => 'force_create_booking',
	            'type' => 'true_false',
	            'prefix' => '',
	            'instructions' => 'If a booking was already created, this will try to recreate it again.',
	            'required' => 0,
	            'conditional_logic' => 0,
	            'wrapper' => array (
	                'width' => '',
	                'class' => '',
	                'id' => '',
	            ),
	            'default_value' => '',
	            'placeholder' => '',
	            'prepend' => '',
	            'append' => '',
	            'maxlength' => '',
	            'readonly' => 0,
	            'disabled' => 0,
	        ),
			array(
				'key' => 'field_63a1325d5221c',
				'label' => 'Create Booking',
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
					'value' => 'tribe_events',
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
