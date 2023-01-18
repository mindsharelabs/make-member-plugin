<?php
	add_action('wp_enqueue_scripts', function() {
		wp_enqueue_script('make-member-sign-in', MAKESF_URL . '/assets/js/make-member-sign-in.js', array('jquery'), null, true);
		wp_localize_script('make-member-sign-in', 'makeMemberSignIn', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'ajax_nonce' => wp_create_nonce('autocompleteSearchNonce')
		]);


		wp_register_style('make-plugin-css', MAKESF_URL . '/css/style.css', array(), THEME_VERSION);
    	wp_enqueue_style('make-plugin-css');

	});




	add_action('wp_ajax_nopriv_makeMemberSignIn', 'make_autocomplete_search');
	add_action('wp_ajax_makeMemberSignIn', 'make_autocomplete_search');
	function make_autocomplete_search($request) {
	

		if($_REQUEST['action'] == 'makeMemberSignIn') :
			$user = get_user_by('email', $_REQUEST['makeEmail']);
			$html = '';
			$user_query = new WP_User_Query(array(
				'search'         => $_REQUEST['makeEmail'],
				'search_columns' => array( 'user_login', 'user_email' )
			));
			mapi_write_log($user_query->get_results() );
			// User Loop
			if ( !empty( $user_query->get_results() ) ) :
				$html .= '<div class="profile-container">';
				foreach ( $user_query->get_results() as $user ) :
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
							if($badges) :
								$html .= '<div class="badge-list d-flex">';
									foreach ($badges as $badge) :
										$html .= '<div class="badge-item text-center">';
											$html .= get_the_post_thumbnail( $badge, 'full', array('class' => 'w-100 badge-image') );
											
										$html .= '</div>';

									endforeach;
								$html .= '</div>';
							endif;
						$html .= '</div>';
						
						


					



				endforeach;
				$html .= '</div>';
			else :
				$html .= 'No users found.';
			endif; //no users found
			
			
			wp_send_json_success( $html );
		endif;
		

	}