<?php

add_action('wp_ajax_nopriv_makeMemberSignIn', 'make_sign_in_member');
add_action('wp_ajax_makeMemberSignIn', 'make_sign_in_member');

function make_sign_in_member() {

	if($_REQUEST['action'] == 'makeMemberSignIn') :

		global $wpdb;
			
		$badges = serialize($_REQUEST['badges']);
		// mapi_write_log($_REQUEST['badges']);
		// mapi_write_log($_REQUEST['userID']);
		// $activity = serialize($_REQUEST['activity']);
		
		$wpdb->insert( 
			'make_signin', 
			array( 
				'time' => current_time( 'mysql' ), 
				'badges' => $badges, 
				'user' => $_REQUEST['userID'], 
			) 
		);

		$tips = get_field('member_tips', 'options');
		if($tip = $tips[array_rand($tips)]) :
			$return = array(
				'html' => '<div class="alert alert-info text-center"><h1>Did you know...?</h1><h2>' . $tip['tip'] . '</h2></div>',
			);
		else : 
			$return = array(
				'html' => '<div class="alert alert-success text-center"><h1>Success!</h1><h2>Thank you!</h2></div>',
			);
		endif;	
		
		return wp_send_json_success( $return );

	endif;

}




add_action('wp_ajax_nopriv_makeAllGetMembers', 'make_get_all_members');
add_action('wp_ajax_makeAllGetMembers', 'make_get_all_members');
function make_get_all_members() {

	$members = make_get_active_members();
	 if($members) :
	 	$html = '<div id="member-list">';

	 		$html .= '<div class="search-container w-50 mb-4 mx-auto">';
		 		$html .= '<input id="memberSearch" type="text" class="form-control form-control-lg member-search" placeholder="Search by Name or Email" />';
		    $html .= '</div>';

		    $all_members = array();
		    foreach($members as $member) :
		      
		      $member_obj = get_user_by('ID', $member->user_id);

		      
		      if(function_exists('wc_memberships_get_user_active_memberships') && $member_obj) :
		        $active_memberships = wc_memberships_get_user_active_memberships($member_obj->ID);
		        $memberships = '';
		        if($active_memberships) :
		          foreach($active_memberships as $membership) :
		            $memberships .= $membership->plan->name;
		            if(next($active_memberships)) :
		              $memberships .= ' & ';
		            endif;
		          endforeach;
		        endif;
		        $all_members[] = array(
			        'ID' => $member_obj->ID,
			        'name' => $member_obj->data->display_name,
			        'memberships' => (isset($memberships) ? $memberships : false),
			        // 'subscriptions' => (isset($user_subscriptions) ? $user_subscriptions : false),
			        'image' => get_avatar_url($member_obj->ID, ['size' => '400'])
			      );
		      endif;

		      
		      
		    endforeach;

		    $html .= '<div class="row list mt-5 pt-5">';
			    foreach ($all_members as $member) :
			    	$user = get_user_by('id', $member['ID']);
			    	
			    	$html .= '<div class="col-6 col-md-4">';

			    		$html .= make_output_profile_container($user);

			    	$html .= '</div>';

			    endforeach;
			$html .= '</div>';
	    $html .= '</div>';

	 endif;





	 wp_send_json_success(array(
	 	'member_count' => count($all_members),
	 	'members' => $all_members,
	 	'html' => $html
	 ));
	

}









add_action('wp_ajax_nopriv_makeGetMember', 'make_get_member_scan');
add_action('wp_ajax_makeGetMember', 'make_get_member_scan');
function make_get_member_scan() {
	if($_REQUEST['action'] == 'makeGetMember') :
		// mapi_write_log($_REQUEST);

		$userEmail = ($_REQUEST['userEmail'] === 'false' ? false : $_REQUEST['userEmail']);
		$userID = ($_REQUEST['userID'] === 'false' ? false : $_REQUEST['userID']);

		// mapi_write_log($userID);
		if($userEmail) :
			$user = get_user_by('email', $userEmail);
			// mapi_write_log('user by email');
		elseif($userID) :
			$user = get_user_by('id', $userID);
			// mapi_write_log('user by id');
		endif;



		// mapi_write_log($user);
		$html = '';
		$return = array();
		// User Loop
		if ( $user ) :
			$return['status'] = 'userfound';
			$memberships = wc_memberships_get_user_active_memberships($user->ID);
	
			// $html .= make_output_profile_container($user);

			$has_waiver = make_check_user_waiver($user->ID);
			
			//if we still don't have a waiver, send a notice. 
			if(!$has_waiver) :	
				$html .= '<div class="alert alert-danger text-center"><h1>No Safety Waiver!</h1><h2>Please log into your online profile and sign our safety waiver.</h2></div>';	
				$return['status'] = 'nosafety';
			elseif(!empty($memberships)) :
				$all_badges = new WP_Query(array(
					'post_type' => 'certs',
					'posts_per_page' => -1,
					'meta_query'    => array(
						'relation'      => 'AND',
						array(
							'key'       => 'use_for_sign_in',
							'value'     => '1',
							'compare'   => '=',
						),
					)
				));
				if($all_badges->have_posts()) :
					$html .= '<div class="badge-list d-flex">';
					$html .=	'<div class="badge-header text-center">';
						$html .= '<h3 class="name">Hi, ' . $user->data->display_name . '</h3>';
						$html .= '<h4>Which of your badges are you using today?</h3>';
					$html .= '</div>';
								
						while($all_badges->have_posts()) :
							$all_badges->the_post();

							$user_badges = get_field('certifications', 'user_' . $user->ID);
							
							$class = 'not-allowed';
							if($user_badges) :
								if(in_array(get_the_id(), $user_badges)) :
									$class = '';
								endif;
							endif;

							$html .= '<div class="badge-item ' . $class . ' text-center" data-badge="' . get_the_id() . '">';
								$badge_image = get_field('badge_image', get_the_id());
								$html .= wp_get_attachment_image( $badge_image,'thumbnail', false);
								$html .= '<span class="small">' . get_the_title(get_the_id()) . '</span>';
							$html .= '</div>';

						endwhile;


						// Add additional activities that are not badges
						$html .= '<div class="badge-item w-100 text-center" data-badge="volunteer">';
							$html .= '<span class="small"><h3 class="my-2">Volunteering</h3></span>';
						$html .= '</div>';

						$html .= '<div class="badge-item w-100 text-center" data-badge="workshop">';
							$html .= '<span class="small"><h3 class="my-2">Attending a Class or Workshop</h3></span>';
						$html .= '</div>';

						$html .= '<div class="badge-item w-100 text-center" data-badge="other">';
							$html .= '<span class="small"><h3 class="my-2">Computers, general work area, or yard</h3></span>';
						$html .= '</div>';



					$html .= '</div>';
				endif;
			
				$html .='<div class="badge-footer text-center mt-3"><button disabled data-user="' . $user->ID . '" class="btn btn-primary btn-lg sign-in-done">Done!</button></div>';
			else :
				$return['status'] = 'nomembership';
				$html .= '<div class="alert alert-danger text-center"><h1>No Active memberships.</h1><h2>Please start or renew your membership to utilize MAKE Santa Fe</h2></div>';		
			endif;

	
		else :
			$return['status'] = 'nouser';
			$html .= '<div class="no-user alert alert-danger text-center">';
				$html .= '<h2>User not found.</h2><h3>Please try again or contact staff.</h3>';
			$html .= '</div>';
		endif; //no users found
		$return['html'] = $html;
	
		wp_send_json_success( $return );
	endif;
	

}




add_action('wp_ajax_nopriv_makeGetEmailForm', 'make_get_email_form');
add_action('wp_ajax_makeGetEmailForm', 'make_get_email_form');

function make_get_email_form() {

	if($_REQUEST['action'] == 'makeGetEmailForm') :
		$return = array();
		$html = '<div class="email-form text-center">';
			$html .= '<form id="emailSubmit">';
			  $html .= '<div class="mb-3 input-group input-group-lg text-center">';
			    $html .= '<span class="input-group-text">Email</span>';
			    $html .= '<input type="email" name="userEmail" class="form-control">';
			  $html .= '</div>';
			  $html .= '<button type="submit" class="btn btn-primary btn-lg emailSubmit">Submit</button>';
			$html .= '</form>';
		$html .= '</div>';
		$return['html'] = $html;
		wp_send_json_success( $return );
	endif;

}





function make_output_profile_container($user) {

	if($user) :
		if(!is_object($user)) {
			$user = get_user_by('id', $user['ID']);
		}
		$user_info = get_userdata($user->ID);
		$html = '<div class="profile-container">';
			$html .= '<span class="email hidden d-none">' . $user_info->user_email  . '</span>';
			$memberships = wc_memberships_get_user_active_memberships($user->ID);
			

			$html .= '<div class="profile-card mb-5" data-user="' . $user->ID . '">';
				$image = get_field('photo', 'user_' . $user->ID);
				
				if($image) :
					$html .= wp_get_attachment_image($image['ID'], 'small-square', false, array('class' => 'profile-image') );
				else :
					$html .= '<img class="profile-image" src="' . MAKESF_URL . '/assets/img/nophoto.jpg"/>';
				endif;
				$html .= '<div class="profile-info">';
					$html .= '<h3 class="name">' . $user->data->display_name . '</h3>';
					if(!empty($memberships)) :
						foreach($memberships as $membership) :
							$html .= '<span class="membership">' . get_the_title($membership->plan_id) . '</span>';
						endforeach;
					else :
						$return['status'] = 'nomembership';
						$html .= '<span class="membership none">No Active Membership</span>';
					endif;

				$html .= '</div>';
				
			$html .= '</div>';
				


		$html .= '</div>';

		return $html;
	endif;
	

}


function make_check_user_waiver($user_id) {
	
	if(class_exists('GFAPI')) :
		$user_info = get_userdata($user_id);
    	$user_email = $user_info->user_email;

		$form = new GFAPI();
	    $search_criteria = array(
	      'status'        => 'active',
	      'field_filters' => array(
	          'mode' => 'any',
	          array(
	              'key'   => 'created_by',
	              'value' => $user_id
	          ),
	          array(
	              'key'   => '34',
	              'value' => $user_email
	          )
	      )
	    );
	    $entries = $form->get_entries( 27, $search_criteria);
	    if(count($entries) > 0) {
	      update_user_meta( $user_id, 'waiver_complete', true );
	      return true;
	    } else {
	      update_user_meta( $user_id, 'waiver_complete', false );
	      return false;
	    }
	endif;
	return false;
}

