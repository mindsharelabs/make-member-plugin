<?php

add_action ('make_after_badge_toggled', 'make_after_badge_toggled', 10, 5);
function make_after_badge_toggled($user_id, $cert_id, $new_status, $badge_name, $event_id) {
	if (defined('WP_DEBUG') && WP_DEBUG) {
		error_log('Make Badge Toggle: User ' . $user_id . ' badge ' . $cert_id . ' new status: ' . $new_status . ' badge name: ' . $badge_name . ' event ID: ' . $event_id);
	}

	//create user meta for the badge with the most recent toggle time and total count of toggles
	$meta_key_time = $cert_id . '_last_time';
	$meta_key_count = $cert_id . '_total_count';
	$previous_count = get_user_meta($user_id, $meta_key_count, true);
	update_user_meta($user_id, $meta_key_time, current_time('mysql'));
	update_user_meta($user_id, $meta_key_count, ($previous_count ? intval($previous_count) : 0) + 1);


}



add_action('make_after_signin_recorded', 'make_after_signin_log', 10, 2);
function make_after_signin_log($user_id, $badges) {
	if (defined('WP_DEBUG') && WP_DEBUG) {
		error_log('Make Sign-in: Recording sign-in for user ' . $user_id . ' with badges: ' . implode(', ', $badges));
	}

	$user_signins = make_get_user_signins($user_id);
	// mapi_write_log($user_signins);

	// save meta for each badge with the most recent signin time and total count
	foreach ($user_signins as $badge => $times) {
		$meta_key_time = $badge . '_last_time';
		$meta_key_count = $badge . '_total_count';
		update_user_meta($user_id, $meta_key_time, max($times));
		update_user_meta($user_id, $meta_key_count, count($times));
	}


}

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
			
			do_action('make_before_signin_recorded', $user_id, $badges);
			$result = $wpdb->insert(
				'make_signin',
				array(
					'time' => current_time( 'mysql' ),
					'badges' => $badges_serialized,
					'user' => $user_id,
				),
				array('%s', '%s', '%d')
			);
			do_action('make_after_signin_recorded', $user_id, $badges, $result);
			
			if ($result === false) {
				error_log('Make Sign-in: Database insert failed for user ' . $user_id);
				wp_send_json_error(array('message' => 'Failed to record sign-in'));
				return;
			}

		// Prepare response message
        if ($is_volunteering) {
            // Get user info
            $user = get_user_by('ID', $user_id);
            $user_name = $user ? $user->display_name : 'Volunteer';
            // Compute monthly totals
            $tz = wp_timezone();
            $now = new DateTime('now', $tz);
            $current_start = new DateTime($now->format('Y-m-01 00:00:00'), $tz);
            $current_end = new DateTime($now->format('Y-m-t 23:59:59'), $tz);
            $prev = (clone $current_start)->modify('-1 month');
            $prev_start = new DateTime($prev->format('Y-m-01 00:00:00'), $tz);
            $prev_end = new DateTime($prev->format('Y-m-t 23:59:59'), $tz);
            $sum_minutes = function($uid, $start, $end) {
                $q = new WP_Query(array(
                    'post_type' => 'volunteer_session',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'meta_query' => array(
                        'relation' => 'AND',
                        array('key' => 'user_id', 'value' => intval($uid), 'compare' => '='),
                        array('key' => 'status', 'value' => 'completed', 'compare' => '='),
                        array('key' => 'signin_time', 'value' => array($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')), 'compare' => 'BETWEEN', 'type' => 'DATETIME'),
                    ),
                ));
                $total = 0;
                foreach ($q->posts as $pid) { $total += (int) get_post_meta($pid, 'duration_minutes', true); }
                return $total;
            };
            $current_minutes = $sum_minutes($user_id, $current_start, $current_end);
            $previous_minutes = $sum_minutes($user_id, $prev_start, $prev_end);
            $first_name = get_user_meta($user_id, 'first_name', true);
            if (!$first_name && $user) { $first_name = preg_split('/\s+/', $user->display_name)[0]; }
            $return = array(
                'html' => '<div class="volunteer-signin-success">' .
                          '<div class="sign-in-confirm text-center" style="font-size:1.25rem;font-weight:600;margin-bottom:10px;">You\'re signed in, ' . esc_html($first_name ?: $user_name) . '!</div>' .
                          '<div class="makesf-session-timer" id="volunteer-session-timer"></div>' .
                          '<div class="volunteer-signin-time text-center"><strong>Signed in at:</strong> ' . current_time('g:i A') . '</div>' .
                          '<div class="volunteer-monthly-totals" style="margin-top:10px;"><div><strong>This month (incl. current):</strong> ' . round($current_minutes/60, 2) . ' hours</div><div><strong>Last month:</strong> ' . round($previous_minutes/60, 2) . ' hours</div></div>' .
                          '<script>(function(){
                            try { if (window.makesfVolunteerTimer) { clearInterval(window.makesfVolunteerTimer); window.makesfVolunteerTimer = null; } } catch(e) {}
                            var start = Date.now();
                            function pad(n){return (n<10?"0":"")+n;}
                            function tick(){
                              var diff = Math.floor((Date.now() - start)/1000);
                              var h = Math.floor(diff/3600);
                              var m = Math.floor((diff%3600)/60);
                              var s = diff%60;
                              var el = document.getElementById("volunteer-session-timer");
                              if(el){ el.innerHTML = "<div class=\\"timer-display\\"><span>"+pad(h)+"</span>:<span>"+pad(m)+"</span>:<span>"+pad(s)+"</span></div><div class=\\"timer-label\\">Session Running</div>"; }
                            }
                            tick();
                            window.makesfVolunteerTimer = setInterval(tick, 1000);
                          })();</script>' .
                          '</div>',
                'status' => 'volunteer_signin_complete',
                'greeting_name' => $first_name
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
					
                // Use shared renderer for the volunteer sign-out interface
                if (function_exists('make_render_volunteer_signout_interface')) {
                    $rendered = make_render_volunteer_signout_interface($user->ID, $active_session);
                    $first_name = get_user_meta($user->ID, 'first_name', true);
                    if (!$first_name) { $first_name = preg_split('/\s+/', $user->display_name)[0]; }
                    $return['status'] = 'volunteer_signout';
                    $return['html'] = $rendered['html'];
                    $return['greeting_name'] = $first_name;
                    wp_send_json_success($return);
                    return;
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


				if(!$has_member_agreement) :
					$return['html'] = '<div class="alert alert-danger text-center"><h1>No Member Agreement!</h1><h2>Please log into your online profile and sign our member agreement.</h2></div>';
					$return['status'] = 'failed';
					$return['code'] = 'memberagreement';
					wp_send_json_success( $return );
				endif;
				

				if(empty($memberships)) :
					$return['html'] = '<div class="alert alert-danger text-center"><h1>No Active memberships.</h1><h2>Please start or renew your membership to utilize MAKE Santa Fe</h2></div>';
					$return['status'] = 'failed';
					$return['code'] = 'nomembership';
					wp_send_json_success( $return );
				endif;


				if(!empty($memberships)) :
                    $html .= '<div class="badge-header text-center">';
                        $html .= '<h4>Which of your badges are you using today?</h4>';
                    $html .= '</div>';

					$html .= make_list_sign_in_badges($user);
					
					// Add additional activities that are not badges (full width below badges)
					$html .= '<div class="badge-item activity-item w-100 text-center" data-badge="volunteer">';
					$html .= '  <span class="small"><h3 class="my-2">Volunteering</h3></span>';
					$html .= '</div>';
					$html .= '<div class="badge-item activity-item w-100 text-center" data-badge="workshop">';
					$html .= '  <span class="small"><h3 class="my-2">Attending a Class or Workshop</h3></span>';
					$html .= '</div>';
					$html .= '<div class="badge-item activity-item w-100 text-center" data-badge="other">';
					$html .= '  <span class="small"><h3 class="my-2">Computers, general work area, or yard</h3></span>';
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
			
            $first_name = get_user_meta($user->ID, 'first_name', true);
            if (!$first_name) { $first_name = preg_split('/\s+/', $user->display_name)[0]; }
            $return['html'] = $html;
            $return['greeting_name'] = $first_name;
		
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
	$html = '';

	$html .= '<div class="alert alert-warning text-center mb-3" style="font-size:1rem;">';
		$html .= 'Each time you sign in with a badge, its expiration timer resets. If you do not use a badge before it expires, you will need to retake it.';
	$html .= '</div>';

	$html .= '<div class="badge-list d-flex">';

	$allowed_items = array();
	$other_items = array();
	if($all_badges->have_posts()) :
		while($all_badges->have_posts()) :
			$all_badges->the_post();

			$user_badges = get_field('certifications', 'user_' . $user->ID);
			$is_earned = is_array($user_badges) && in_array(get_the_id(), $user_badges);
			$badge_expiration_length = get_field('expiration_time', get_the_id()); //this is stored in days
			$badge_last_signin_time = get_user_meta($user->ID, get_the_id() . '_last_time', true);
			$expiration_time = strtotime($badge_last_signin_time) + ($badge_expiration_length * DAY_IN_SECONDS);
			//days until expiration
			$time_remaining = $expiration_time - time();


			if ($is_earned && !empty($badge_expiration_length) && !empty($badge_last_signin_time)) {
				$last_ts = strtotime($badge_last_signin_time);

				if ($last_ts) {
				$expiration_time = $last_ts + ((int) $badge_expiration_length * DAY_IN_SECONDS);
				$seconds_remaining = $expiration_time - time();

				if ($seconds_remaining >= 0) {
					$time_remaining = (int) ceil($seconds_remaining / DAY_IN_SECONDS);
				} else {
					$time_remaining = (int) floor($seconds_remaining / DAY_IN_SECONDS);
				}
				}
			}



			$classes = [];

			$expired = false;
			if($user_badges) :
				if(!$is_earned) :
					$classes[] = 'not-allowed';
				endif;
			endif;
			if($time_remaining <= 0) :
				$classes[] = 'not-allowed';
				$classes[] = 'expired';
				$expired = true;
			endif;


			// Optional helper text under the status
				$helper = '';
				if ($time_remaining !== null) {
					if ($time_remaining < 0) {
						$helper = 'Expired ' . abs((int) $time_remaining) . ' days ago. Please re-take this badge class to earn it again.';
					} elseif ($time_remaining == 0 || $time_remaining == -0) {
						$helper = 'Expires today. Practice this badge to reset the timer.';
					} elseif ($time_remaining <= 20) {
						$helper = 'Expires soon. Practice this badge to reset the timer.';
					} else {
						$helper = 'Expires in ' . (int) $time_remaining . ' days.';
					}
				}


			$item_html = '<div class="badge-item ' . implode(' ', $classes) . ' text-center" data-badge="' . get_the_id() . '">';
				$badge_image = get_field('badge_image', get_the_id());
				$item_html .= wp_get_attachment_image( $badge_image,'thumbnail', false);
				$item_html .= '<span class="small">' . get_the_title(get_the_id()) . '</span>';

				if($is_earned && $badge_expiration_length) :
					if($expired) :
						$item_html .= '<span class="badge-expiration text-danger">Badge Expired</span>';
					elseif($time_remaining <= 20) :
						$item_html .= '<span class="badge-expiration text-warning">' . $helper . '</span>';
					elseif($time_remaining) :
						$item_html .= '<span class="badge-expiration text-success">' . $helper . '</span>';
					endif;
				endif;

			$item_html .= '</div>';

			if ($is_earned) {
				$allowed_items[] = $item_html;
			} else {
				$other_items[] = $item_html;
			}
		endwhile;
	endif;

	// Output allowed first, then the rest
	$html .= implode('', $allowed_items) . implode('', $other_items);

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






function make_get_user_signins($user_id){
    global $wpdb;
    $results = $wpdb->get_results("SELECT * FROM `make_signin` where user = $user_id;");
    $badge_signins = array();
    foreach ($results as $result) {
      $badges = unserialize($result->badges);
	//   mapi_write_log($badges);
      foreach ($badges as $badge) {
        $badge_signins[$badge][] = $result->time;
      }
    }

	//array multisort will re-order the badge_signins array based on the count of sign-ins for each badge, but it will reset the keys to numeric indexes.
	// To preserve the badge IDs as keys, we can store the keys before sorting and then reassign them after sorting.
	$keys = array_keys($badge_signins);

    array_multisort(array_map('count', $badge_signins), SORT_DESC, $badge_signins);
	$badge_signins = array_combine($keys, $badge_signins);

    return $badge_signins;
}



//include sign-in css on make-member-sign-in template
add_action('wp_enqueue_scripts', 'make_enqueue_signin_styles');
function make_enqueue_signin_styles() {
	if (is_page_template('make-member-sign-in.php')) {
		mapi_write_log('Enqueueing sign-in styles for make-member-sign-in template');
		wp_enqueue_style(
			'make-signin-styles',
			MAKESF_URL . 'assets/css/sign-in.css',
			array(),
			MAKESF_PLUGIN_VERSION
		);
	}
}