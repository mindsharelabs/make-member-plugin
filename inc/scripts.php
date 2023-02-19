<?php

	add_action('wp_ajax_nopriv_makeMemberSignIn', 'make_sign_in_member');
	add_action('wp_ajax_makeMemberSignIn', 'make_sign_in_member');

	function make_sign_in_member() {

		if($_REQUEST['action'] == 'makeMemberSignIn') :
	
			global $wpdb;
				
			$badges = serialize($_REQUEST['badges']);
			
			$wpdb->insert( 
				SIGNIN_TABLENAME, 
				array( 
					'time' => current_time( 'mysql' ), 
					'badges' => $badges, 
					'user' => $_REQUEST['userID'], 
				) 
			);
		endif;

	}



	add_action('wp_ajax_nopriv_makeGetMember', 'make_get_member_scan');
	add_action('wp_ajax_makeGetMember', 'make_get_member_scan');
	function make_get_member_scan() {
		if($_REQUEST['action'] == 'makeGetMember') :
			mapi_write_log($_REQUEST);

			$userEmail = ($_REQUEST['userEmail'] === 'false' ? false : $_REQUEST['userEmail']);
			$userID = ($_REQUEST['userID'] === 'false' ? false : $_REQUEST['userID']);

			mapi_write_log($userEmail);
			mapi_write_log($userID);

			if($userEmail) :
				$user = get_user_by('email', $userEmail);
				mapi_write_log('email');
			elseif($userID) :
				$user = get_user_by('id', $userID);
				mapi_write_log('userid');
			endif;

			mapi_write_log($user);


			$html = '';
			$return = array();
			// User Loop
			if ( $user ) :
				$return['status'] = 'userfound';
				$html .= '<div class="profile-container">';
					
					$memberships = wc_memberships_get_user_active_memberships($user->ID);
					
					
				
					$html .= '<div class="profile-card mb-5" data-user="' . $user->ID . '">';
						$image = get_field('photo', 'user_' . $user->ID);
						
						if($image) :
							$html .= wp_get_attachment_image($image['ID'], 'medium', false, array('class' => 'profile-image') );
						endif;
						$html .= '<div class="profile-info">';
							$html .= '<h3>' . $user->data->display_name . '</h3>';
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

				if(!empty($memberships)) :

					$all_badges = new WP_Query(array(
						'post_type' => 'certs',
						'posts_per_page' => -1,
					));
					if($all_badges->have_posts()) :
						$html .= '<div class="badge-list d-flex">';
						$html .='<div class="badge-header text-center"><h4 class="mt-5">Which of your badges are you using today?</h3></div>';
									
							while($all_badges->have_posts()) :
								$all_badges->the_post();

								$user_badges = get_field('certifications', 'user_' . $user->ID);
								
								$html .= '<div class="badge-item ' . (in_array(get_the_id(), $user_badges) ? '' : 'not-allowed') . ' text-center" data-badge="' . get_the_id() . '">';
									$badge_image = get_field('badge_image', get_the_id());
									$html .= wp_get_attachment_image( $badge_image,'thumbnail', false);
									$html .= '<span class="small">' . get_the_title(get_the_id()) . '</span>';
								$html .= '</div>';



							endwhile;
							$html .= '</div>';
						$html .='<div class="badge-footer text-center"><button disabled class="btn btn-primary btn-lg sign-in-done">Done!</button></div>';
					endif;
				else :
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
