<?php

add_action('wp_ajax_nopriv_makeMemberSignIn', 'make_sign_in_member');
add_action('wp_ajax_makeMemberSignIn', 'make_sign_in_member');

function make_sign_in_member() {
	// Verify nonce for security
	if (!wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'makesf_signin_nonce') &&
		!wp_verify_nonce($_REQUEST['volunteer_nonce'] ?? '', 'makesf_volunteer_nonce')) {
		wp_send_json_error(array('message' => 'Security verification failed'));
		return;
	}

	if($_REQUEST['action'] == 'makeMemberSignIn') :
		try {
			global $wpdb;
			
			$user_id = intval($_REQUEST['userID']);
			$badges = isset($_REQUEST['badges']) ? array_map('sanitize_text_field', $_REQUEST['badges']) : array();
			
			// Validate user ID
			if (empty($user_id) || !get_user_by('ID', $user_id)) {
				wp_send_json_error(array('message' => 'Invalid user ID'));
				return;
			}
		
		// Check if volunteering is selected
		$is_volunteering = in_array('volunteer', $badges);
		
		// Handle volunteer session if volunteering is selected
		if ($is_volunteering && function_exists('make_start_volunteer_session')) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('Make Volunteer: Starting volunteer session for user ' . $user_id);
			}
			
			$session_result = make_start_volunteer_session($user_id);
			
			if (is_wp_error($session_result)) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log('Make Volunteer: Error starting session: ' . $session_result->get_error_message());
				}
				wp_send_json_error(array('message' => 'Failed to create volunteer session: ' . $session_result->get_error_message()));
				return;
			}
			
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('Make Volunteer: Successfully started session with ID: ' . $session_result);
			}
		} elseif ($is_volunteering) {
			// Function doesn't exist
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('Make Volunteer: make_start_volunteer_session function not found');
			}
			wp_send_json_error(array('message' => 'Failed to create volunteer session: Function not available'));
			return;
		}
		
			// Record the regular sign-in
			$badges_serialized = serialize($badges);

			$result = $wpdb->insert(
				'make_signin',
				array(
					'time' => current_time( 'mysql' ),
					'badges' => $badges_serialized,
					'user' => $user_id,
				),
				array('%s', '%s', '%d')
			);
			
			if ($result === false) {
				error_log('Make Sign-in: Database insert failed for user ' . $user_id);
				wp_send_json_error(array('message' => 'Failed to record sign-in'));
				return;
			}

		// Prepare response message
		if ($is_volunteering && function_exists('make_get_available_volunteer_tasks')) {
			// Get user info
			$user = get_user_by('ID', $user_id);
			$user_name = $user ? $user->display_name : 'Volunteer';
			
			// Get available tasks to show as preview
			$tasks_html = '';
			$available_tasks = make_get_available_volunteer_tasks($user_id);
			
			if (!empty($available_tasks)) {
				$tasks_html .= '<div class="volunteer-tasks-preview" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">';
				$tasks_html .= '<h3 style="margin-bottom: 15px; color: #495057;">📋 What needs to be done today:</h3>';
				
				// Show up to 8 tasks, prioritized by urgency
				$priority_order = array('urgent' => 4, 'high' => 3, 'medium' => 2, 'low' => 1);
				usort($available_tasks, function($a, $b) use ($priority_order) {
					$a_priority = $priority_order[$a['priority']] ?? 0;
					$b_priority = $priority_order[$b['priority']] ?? 0;
					return $b_priority - $a_priority;
				});
				
				$tasks_to_show = array_slice($available_tasks, 0, 8);
				
				// Display tasks in a list format similar to sign-out view
				$tasks_html .= '<div class="tasks-list" style="max-height: 400px; overflow-y: auto;">';
				
				foreach ($tasks_to_show as $task) {
					$priority_color = '';
					$priority_text = '';
					switch ($task['priority']) {
						case 'urgent':
							$priority_color = '#dc3545';
							$priority_text = 'URGENT';
							break;
						case 'high':
							$priority_color = '#fd7e14';
							$priority_text = 'HIGH';
							break;
						case 'medium':
							$priority_color = '#ffc107';
							$priority_text = 'MEDIUM';
							break;
						case 'low':
							$priority_color = '#28a745';
							$priority_text = 'LOW';
							break;
					}
					
					$tasks_html .= '<div class="task-item" style="background: white; padding: 15px; margin-bottom: 10px; border-radius: 5px; border-left: 4px solid ' . $priority_color . '; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
					
					// Task header with title and priority
					$tasks_html .= '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">';
					$tasks_html .= '<h4 style="margin: 0; font-size: 16px; font-weight: bold; color: #333;">' . esc_html($task['title']) . '</h4>';
					$tasks_html .= '<span style="background: ' . $priority_color . '; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold;">' . $priority_text . '</span>';
					$tasks_html .= '</div>';
					
					// Task details
					if (!empty($task['categories'])) {
						$tasks_html .= '<div style="font-size: 13px; color: #666; margin-bottom: 5px;">📂 ' . esc_html($task['categories'][0]['name']) . '</div>';
					}
					
					if (!empty($task['description'])) {
						$tasks_html .= '<div style="font-size: 14px; color: #555; margin-bottom: 8px; line-height: 1.4;">' . esc_html(wp_trim_words($task['description'], 20)) . '</div>';
					}
					
					// Task metadata
					$tasks_html .= '<div style="display: flex; flex-wrap: wrap; gap: 15px; font-size: 12px; color: #777;">';
					$tasks_html .= '<span>⏱️ ~' . $task['estimated_duration'] . ' min</span>';
					
					if (!empty($task['location'])) {
						$tasks_html .= '<span>📍 ' . esc_html($task['location']) . '</span>';
					}
					
					if ($task['task_type'] === 'recurring') {
						$tasks_html .= '<span style="color: #28a745;">🔄 Recurring</span>';
					}
					$tasks_html .= '</div>';
					
					// Assignment and qualification info
					if (!empty($task['assigned_to']) || !$task['user_qualified']) {
						$tasks_html .= '<div style="margin-top: 8px; font-size: 12px;">';
						
						if (!empty($task['assigned_to'])) {
							if ($task['is_assigned_to_current_user']) {
								$tasks_html .= '<span style="color: #0073aa; font-weight: bold;">👤 Assigned to you</span>';
							} else {
								$tasks_html .= '<span style="color: #666;">👤 Assigned to ' . esc_html($task['assigned_to_name']) . '</span>';
							}
						}
						
						if (!$task['user_qualified']) {
							$tasks_html .= '<span style="color: #dc3545; margin-left: 10px;">🔒 Requires certification</span>';
						}
						
						$tasks_html .= '</div>';
					}
					
					$tasks_html .= '</div>';
				}
				
				$tasks_html .= '</div>';
				
				if (count($available_tasks) > 8) {
					$remaining = count($available_tasks) - 8;
					$tasks_html .= '<div style="text-align: center; margin-top: 15px; font-size: 13px; color: #666; padding: 10px; background: white; border-radius: 5px;">+ ' . $remaining . ' more task' . ($remaining > 1 ? 's' : '') . ' available</div>';
				}
				
				$tasks_html .= '<div style="text-align: center; margin-top: 15px; font-size: 13px; color: #495057; font-style: italic;">';
				$tasks_html .= '💡 You\'ll be able to select completed tasks when you sign out';
				$tasks_html .= '</div>';
				$tasks_html .= '</div>';
			} else {
				$tasks_html .= '<div class="volunteer-tasks-preview" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; text-align: center;">';
				$tasks_html .= '<h3 style="color: #495057;">📋 No specific tasks available right now</h3>';
				$tasks_html .= '<p style="color: #6c757d; margin: 0;">Feel free to help with general maintenance, organization, or ask staff what needs attention!</p>';
				$tasks_html .= '</div>';
			}
			
			$return = array(
				'html' => '<div class="volunteer-signin-success alert alert-success text-center">' .
						  '<h1>Welcome, ' . esc_html($user_name) . '!</h1>' .
						  '<h2>Your volunteer session has started. Don\'t forget to sign out when you\'re done!</h2>' .
						  '<div class="volunteer-signin-time"><strong>Signed in at:</strong> ' . current_time('g:i A') . '</div>' .
						  $tasks_html .
						  '</div>',
				'status' => 'volunteer_signin_complete'
			);
		} else {
			// Regular sign-in response
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
			}
			
			return wp_send_json_success( $return );
			
		} catch (Exception $e) {
			error_log('Make Sign-in Error: ' . $e->getMessage());
			wp_send_json_error(array('message' => 'An error occurred during sign-in'));
			return;
		}
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

		    // Get all active volunteer sessions upfront for efficiency
		    $active_volunteers = array();
		    if (function_exists('make_get_active_volunteer_sessions')) {
		    	$active_sessions = make_get_active_volunteer_sessions();
		    	foreach ($active_sessions as $session) {
		    		$active_volunteers[$session->user_id] = true;
		    	}
		    }

		    $all_members = array();
		    foreach($members as $member) :
		      
		      $member_obj = get_user_by('ID', $member->user_id);

		      
		      if(function_exists('wc_memberships_get_user_active_memberships') && $member_obj) :
		        $active_memberships = wc_memberships_get_user_active_memberships($member_obj->ID);
		        $complimentary_memberships = wc_memberships_get_user_memberships($member_obj->ID, array('status' => 'complimentary'));
		        $all_memberships = array_merge($active_memberships, $complimentary_memberships);
		        
		        // Remove duplicates by plan_id to avoid showing the same membership twice
		        $unique_memberships = array();
		        $plan_ids_seen = array();
		        if($all_memberships) :
		          foreach($all_memberships as $membership) :
		            $plan_id = $membership->plan_id;
		            if(!in_array($plan_id, $plan_ids_seen)) :
		              $plan_ids_seen[] = $plan_id;
		              $unique_memberships[] = $membership;
		            endif;
		          endforeach;
		        endif;
		        
		        $memberships = '';
		        if($unique_memberships) :
		          foreach($unique_memberships as $index => $membership) :
		            $memberships .= $membership->plan->name;
		            if($index < count($unique_memberships) - 1) :
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

			    		// Pass volunteer status to profile container
			    		$is_volunteering = isset($active_volunteers[$user->ID]);
			    		$html .= make_output_profile_container($user, $is_volunteering);

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
		try {
			// Sanitize inputs
			$userEmail = isset($_REQUEST['userEmail']) && $_REQUEST['userEmail'] !== 'false'
				? sanitize_email($_REQUEST['userEmail']) : false;
			$userID = isset($_REQUEST['userID']) && $_REQUEST['userID'] !== 'false'
				? intval($_REQUEST['userID']) : false;

			if (!$userEmail && !$userID) {
				wp_send_json_error(array('message' => 'No user identifier provided'));
				return;
			}

			if($userEmail) :
				$user = get_user_by('email', $userEmail);
			elseif($userID) :
				$user = get_user_by('id', $userID);
			endif;
			
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('Make Member Scan: User ID: ' . $userID);
			}

			$html = '';
			$return = array();
			
			// User Loop
			if ( $user ) :
			// Check for active volunteer session FIRST
			if (function_exists('make_get_active_volunteer_session')) {
				$active_session = make_get_active_volunteer_session($user->ID);
				if ($active_session) {
					// User has active volunteer session - redirect to sign-out interface
					if (defined('WP_DEBUG') && WP_DEBUG) {
						error_log('Make Volunteer: Found active session for user ' . $user->ID . ', showing sign-out interface');
					}
					
					// Get the volunteer sign-out interface HTML
					if (function_exists('make_handle_get_volunteer_session')) {
						// Temporarily set POST data for the volunteer session handler
						$original_post = $_POST;
						$_POST['userID'] = $user->ID;
						$_POST['nonce'] = wp_create_nonce('makesf_volunteer_nonce');
						
						// Capture the volunteer session response
						ob_start();
						make_handle_get_volunteer_session();
						$volunteer_response = ob_get_clean();
						
						// Restore original POST data
						$_POST = $original_post;
						
						// Parse the JSON response
						$volunteer_data = json_decode($volunteer_response, true);
						if ($volunteer_data && $volunteer_data['success'] && $volunteer_data['data']['has_active_session']) {
							$return['status'] = 'volunteer_signout';
							$return['html'] = $volunteer_data['data']['html'];
							wp_send_json_success($return);
							return;
						}
					}
				}
			}
			
			// No active volunteer session - proceed with normal sign-in flow
			$return['status'] = 'userfound';
			$memberships = wc_memberships_get_user_active_memberships($user->ID);
	
			// $html .= make_output_profile_container($user);

				$has_waiver = make_check_form_submission($user->ID, 27, 34);
				$has_member_agreement = make_check_form_submission($user->ID, 45, 16);
				

				//if we still don't have a waiver, send a notice.
				if(!$has_waiver) :
					$return['html'] = '<div class="alert alert-danger text-center"><h1>No Safety Waiver!</h1><h2>Please log into your online profile and sign our safety waiver.</h2></div>';
					$return['status'] = 'failed';
					$return['code'] = 'waiver';
					wp_send_json_success( $return );
				endif;

				// TEMPORARILY DISABLED FOR TESTING - UNCOMMENT TO RE-ENABLE MEMBER AGREEMENT REQUIREMENT
				/*
				if(!$has_member_agreement) :
					$return['html'] = '<div class="alert alert-danger text-center"><h1>No Member Agreement!</h1><h2>Please log into your online profile and sign our member agreement.</h2></div>';
					$return['status'] = 'failed';
					$return['code'] = 'memberagreement';
					wp_send_json_success( $return );
				endif;
				*/

				if(empty($memberships)) :
					$return['html'] = '<div class="alert alert-danger text-center"><h1>No Active memberships.</h1><h2>Please start or renew your membership to utilize MAKE Santa Fe</h2></div>';
					$return['status'] = 'failed';
					$return['code'] = 'nomembership';
					wp_send_json_success( $return );
				endif;


				if(!empty($memberships)) :
					$html .= '<div class="badge-header text-center">';
						$html .= '<h3 class="name">Hi, ' . esc_html($user->data->display_name) . '</h3>';
						$html .= '<h4>Which of your badges are you using today?</h4>';
					$html .= '</div>';

					$html .= make_list_sign_in_badges($user);
					
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
				
					$html .='<div class="badge-footer text-center mt-3"><button disabled data-user="' . intval($user->ID) . '" class="btn btn-primary btn-lg sign-in-done">Done!</button></div>';
				endif;

		
			else :
				$return['status'] = 'nouser';
				$html .= '<div class="no-user alert alert-danger text-center">';
					$html .= '<h2>User not found.</h2><h3>Please try again or contact staff.</h3>';
				$html .= '</div>';
			endif; //no users found
			
			$return['html'] = $html;
		
			wp_send_json_success( $return );
			
		} catch (Exception $e) {
			error_log('Make Get Member Scan Error: ' . $e->getMessage());
			wp_send_json_error(array('message' => 'Failed to load member data'));
		}
	endif;
}


function make_list_sign_in_badges($user) {
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
	$html = '<div class="badge-list d-flex">';
	if($all_badges->have_posts()) :
	
				
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
	endif;

	return $html;
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





function make_output_profile_container($user, $is_volunteering = null) {

	if($user) :
		if(!is_object($user)) {
			$user = get_user_by('id', $user['ID']);
		}
		$user_info = get_userdata($user->ID);
		$html = '<div class="profile-container">';
			$html .= '<span class="email hidden d-none">' . $user_info->user_email  . '</span>';
			// Get both active and complimentary memberships
			$active_memberships = wc_memberships_get_user_active_memberships($user->ID);
			$complimentary_memberships = wc_memberships_get_user_memberships($user->ID, array('status' => 'complimentary'));
			$all_memberships = array_merge($active_memberships, $complimentary_memberships);
			
			// Remove duplicates by plan_id to avoid showing the same membership twice
			$unique_memberships = array();
			$plan_ids_seen = array();
			if($all_memberships) :
				foreach($all_memberships as $membership) :
					$plan_id = $membership->plan_id;
					if(!in_array($plan_id, $plan_ids_seen)) :
						$plan_ids_seen[] = $plan_id;
						$unique_memberships[] = $membership;
					endif;
				endforeach;
			endif;
			
			// Build membership display string
			$membership_display = '';
			if($unique_memberships) :
				foreach($unique_memberships as $index => $membership) :
					$membership_display .= get_the_title($membership->plan_id);
					if($index < count($unique_memberships) - 1) :
						$membership_display .= ' & ';
					endif;
				endforeach;
			endif;

			$html .= '<div class="profile-card mb-5" data-user="' . $user->ID . '">';
				$image = get_field('photo', 'user_' . $user->ID);
				
				// Check if user is currently volunteering to add green glow
				$profile_image_class = 'profile-image';
				if ($is_volunteering !== null) {
					// Use passed volunteer status for efficiency
					if ($is_volunteering) {
						$profile_image_class .= ' volunteer-signed-in';
					}
				} else {
					// Fallback to function check if status not provided
					if (function_exists('make_is_user_volunteering') && make_is_user_volunteering($user->ID)) {
						$profile_image_class .= ' volunteer-signed-in';
					}
				}
				
				if($image) :
					$html .= wp_get_attachment_image($image['ID'], 'small-square', false, array('class' => $profile_image_class) );
				else :
					$html .= '<img class="' . $profile_image_class . '" src="' . MAKESF_URL . '/assets/img/nophoto.jpg"/>';
				endif;
				$html .= '<div class="profile-info">';
					$html .= '<h3 class="name">' . $user->data->display_name . '</h3>';
					if(!empty($membership_display)) :
						$html .= '<span class="membership">' . $membership_display . '</span>';
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

/**
 * Enqueue badge management assets for admin pages
 */
add_action('admin_enqueue_scripts', 'make_enqueue_badge_management_assets');
function make_enqueue_badge_management_assets($hook) {
    // Only load on event edit pages and other relevant admin pages
    global $post_type;
    
    if (($hook == 'post.php' || $hook == 'post-new.php') && $post_type == 'events') {
        // Enqueue badge management CSS
        wp_enqueue_style(
            'make-badge-management',
            MAKESF_URL . 'assets/css/badge-management.css',
            array(),
            MAKESF_PLUGIN_VERSION
        );
        
        // Enqueue badge management JavaScript
        wp_enqueue_script(
            'make-badge-management',
            MAKESF_URL . 'assets/js/badge-management.js',
            array('jquery'),
            MAKESF_PLUGIN_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('make-badge-management', 'make_badge_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('make_badge_toggle_nonce')
        ));
    }
}


function make_check_form_submission($user_id, $form_id, $field_id) {
	
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
	              'key'   => $field_id,
	              'value' => $user_email
	          )
	      )
	    );
	    $entries = $form->get_entries( $form_id, $search_criteria);
	    if(count($entries) > 0) {
	      return true;
	    } else {
	      return false;
	    }
	endif;
	return false;
}

