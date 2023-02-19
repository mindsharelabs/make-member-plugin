<?php

	add_action('wp_ajax_nopriv_makeMemberSignIn', 'make_sign_in_member');
	add_action('wp_ajax_makeMemberSignIn', 'make_sign_in_member');

	function make_sign_in_member() {
		mapi_write_log($_REQUEST);

	}



	add_action('wp_ajax_nopriv_makeGetMember', 'make_get_member_scan');
	add_action('wp_ajax_makeGetMember', 'make_get_member_scan');
	function make_get_member_scan() {
	

		if($_REQUEST['action'] == 'makeGetMember') :
			$user = get_user_by('id', $_REQUEST['userID']);
			$html = '';
			
			// User Loop
			if ( $user ) :
				$html .= '<div class="profile-container">';
					
					$memberships = wc_memberships_get_user_active_memberships($user->ID);
					
					$badges = get_field('certifications', 'user_' . $user->ID);

				
					$html .= '<div class="profile-card" data-user="' . $user->ID . '">';
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
								$html .= '<span class="membership">No Active Membership</span>';
							endif;

						$html .= '</div>';
						
					$html .= '</div>';
						


				$html .= '</div>';



				if($badges) :
					$html .= '<div class="badge-list d-flex">';
						$html .='<div class="badge-header text-center"><h2 class="mb-0">Your badges.</h2><h4>Which are you using today?</h3></div>';
						foreach ($badges as $badge) :
							$html .= '<div class="badge-item text-center" data-badge="' . $badge . '">';
								$badge_image = get_field('badge_image', $badge);
								$html .= wp_get_attachment_image( $badge_image,'thumbnail', false);
								$html .= '<span class="small">' . get_the_title($badge) . '</span>';
							$html .= '</div>';

						endforeach;
						$html .= '</div>';
						$html .='<div class="badge-footer text-center"><button class="btn btn-primary btn-lg sign-in-done">Done!</button></div>';
				endif;


			else :
				$html .= 'No users found.';
			endif; //no users found
			
			
			wp_send_json_success( $html );
		endif;
		

	}