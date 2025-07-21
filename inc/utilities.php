<?php
if(!function_exists('mapi_var_dump')) {
    function mapi_var_dump($var) {
      if (current_user_can('administrator') && isset($var)) {
          echo '<pre>';
          var_dump($var);
          echo '</pre>'; 
      }
    }
  }
  
if(!function_exists('mapi_write_log')) {
    function mapi_write_log($message) {
        if ( WP_DEBUG === true ) {
            if ( is_array($message) || is_object($message) ) {
                error_log( print_r($message, true) );
            } else {
                error_log( $message );
            }
        }
    }
}




if(!function_exists('make_output_member_card')) :
    function make_output_member_card($maker, $echo = false, $args = array()) {
        if(!is_object($maker)) :
          $maker = get_user_by('ID', $maker);
        endif;
  
        $args = wp_parse_args($args, array(
          'show_badges' => true,
          'show_title' => true,
          'show_bio' => false,
          'show_gallery' => false,
          'show_photo' => true,
        ));
  
  
        $html = '';
          $name = (get_field('display_name', 'user_' . $maker->ID ) ? get_field('display_name', 'user_' . $maker->ID ) : $maker->display_name);
          $badges = ($args['show_badges'] ? get_field('certifications', 'user_' . $maker->ID ) : false);
          $title = ($args['show_title'] ? get_field('title', 'user_' . $maker->ID) : false);
          $bio = ($args['show_bio'] ? get_field('bio', 'user_' . $maker->ID) : false);
          $gallery = ($args['show_gallery'] ? get_field('image_gallery', 'user_' . $maker->ID) : false);
          $photo = ($args['show_photo'] ? get_field('photo', 'user_' . $maker->ID) : false);
          
          $link = get_author_posts_url($maker->ID);
          
  
          if(!$photo) {
            $image_url = get_template_directory_uri() . '/img/nophoto.svg';
            $image = '<img src="' . $image_url . '" class="rounded-circle">';
          } else {
            $image = wp_get_attachment_image( $photo['ID'], 'small-square', false, array('alt' => $name, 'class' => 'rounded-circle'));
          }
          $html .='<div class="col-6 col-md-4 col-lg-3 text-center mb-2">';
            $html .='<div class="mb-4 text-center card make-member-card d-flex flex-column justify-content-start h-100">';
              if($image) :
                $html .='<div class="image profile-image p-3 w-75 mx-auto">';
                  $html .='<a href="' . $link . '">';
                    $html .= $image;
                  $html .='</a>';
                $html .='</div>';
              endif;
  
              $html .='<div class="content">';
                $html .='<a href="' . $link . '">';
                  $html .='<h5 class="text-center">' . $name . '</h5>';
                $html .='</a>';
                $html .= ($title ? '<p class="text-center small mb-0">' . $title . '</p>' : '');
              $html .='</div>';
  
              
              if($badges) :
                $html .= '<div class="maker-badges d-flex justify-content-center flex-wrap">';
                foreach($badges as $badge) :
                  if($image = get_field('badge_image', $badge)) :
                    $html .= '<a class="badge-image-holder m-1" href="' . get_permalink($badge) . '" class="badge-name d-block text-center" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="' . get_the_title($badge) . '" data-bs-placement="top">';
                      $html .= wp_get_attachment_image($image);
                    $html .= '</a>';
                  endif;
                endforeach;
                $html .= '</div>';
              endif;
  
  
              if($gallery) :
                $html .= '<div class="maker-gallery d-flex justify-content-center flex-wrap w-100">';
                  $html .= '<div class="row w-100 my-3 gy-2">';
                  foreach ($gallery as $image) :
                    if($image['image'] != '') :
                      $image_elem = wp_get_attachment_image( $image['image']['ID'], array(100,100), false, array('class' => 'gallery-image'));
                      if($image_elem):
                        $html .= '<div class="col-3 col-md-2">' . $image_elem . '</div>';
                      endif;
                    endif;
                  endforeach;
  
                  $html .= '</div>';
                $html .= '</div>';
              endif;
            
  
  
            $html .='</div>';
          $html .='</div>';
  
      if($echo) :
        echo $html;
      else :
        return $html; 
      endif;
    }
endif;






/**
 * Gets all current members and returns an array of user objects
 *
 * @return array|null Array of user objects or null on failure
 */
function make_get_active_members(){
 try {
  global $wpdb;
  
  // Getting all User IDs and data for a membership plan
  $results = $wpdb->get_results( $wpdb->prepare("
  	SELECT DISTINCT um.user_id
  	FROM {$wpdb->prefix}posts AS p
  	LEFT JOIN {$wpdb->prefix}posts AS p2 ON p2.ID = p.post_parent
  	LEFT JOIN {$wpdb->prefix}users AS u ON u.id = p.post_author
  	LEFT JOIN {$wpdb->prefix}usermeta AS um ON u.id = um.user_id
  	WHERE p.post_type = %s
  	AND p.post_status IN (%s)
  	AND p2.post_type = %s
  	LIMIT 999
  ", 'wc_user_membership', 'wcm-active', 'wc_membership_plan'));
  
  return $results;
  
 } catch (Exception $e) {
  error_log('Make Get Active Members Error: ' . $e->getMessage());
  return null;
 }
}


/**
 * Gets all current members and returns them as an array of user IDs
 *
 * @return array Array of user IDs
 */
function make_get_active_members_array(){
	try {
		global $wpdb;
		
		// Getting all User IDs and data for a membership plan
		$members = $wpdb->get_results( $wpdb->prepare("
			SELECT DISTINCT um.user_id
			FROM {$wpdb->prefix}posts AS p
			LEFT JOIN {$wpdb->prefix}posts AS p2 ON p2.ID = p.post_parent
			LEFT JOIN {$wpdb->prefix}users AS u ON u.id = p.post_author
			LEFT JOIN {$wpdb->prefix}usermeta AS um ON u.id = um.user_id
			WHERE p.post_type = %s
			AND p.post_status IN (%s)
			AND p2.post_type = %s
			LIMIT 999
		", 'wc_user_membership', 'wcm-active', 'wc_membership_plan'));

		if (!$members) {
			return array();
		}

		$members_array = array();
		foreach($members as $member) {
			$members_array[] = intval($member->user_id);
		}

		return $members_array;
		
	} catch (Exception $e) {
		error_log('Make Get Active Members Array Error: ' . $e->getMessage());
		return array();
	}
}





/**
 * Get upcoming events with optional ticket filtering
 *
 * @param int $num Number of events to return
 * @param bool $ticketed Whether to filter by available tickets
 * @param array $args Additional WP_Query arguments
 * @param int $page Current page for pagination
 * @param array $upcoming_events Accumulated events (for recursion)
 * @return array Array of upcoming events
 */
function make_get_upcoming_events($num = 3, $ticketed = true, $args = array(), $page = 1, $upcoming_events = array()) {
	try {
		$default_args = array(
			'post_type' => 'sub_event',
			'posts_per_page' => ($num > 12 ? $num : 12),
			'meta_key'       => 'event_time_stamp',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'paged'          => $page,
			'meta_query'     => array(
				array(
					'key'     => 'event_time_stamp',
					'value'   => current_time('Y-m-d H:i:s'),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
		);
		$args = wp_parse_args($args, $default_args);
		
		$events = new WP_Query($args);
		
		if($events->have_posts()) :
			while($events->have_posts()) :
				$events->the_post();
				if(count($upcoming_events) < $num) :
					if($ticketed) :
						if(function_exists('make_event_has_available_tickets') && make_event_has_available_tickets(get_the_id())) :
							$upcoming_events[get_the_id()] = esc_html(get_the_title());
						endif;
					else :
						$upcoming_events[get_the_id()] = esc_html(get_the_title());
					endif;
				endif;
			endwhile;
			wp_reset_postdata();
		endif;

		// Recursive pagination if we need more events
		if(count($upcoming_events) < $num && $events->max_num_pages >= ($page + 1)) :
			$args['paged'] = $page + 1;
			$upcoming_events = make_get_upcoming_events($num, $ticketed, $args, $page + 1, $upcoming_events);
		endif;

		return $upcoming_events;
		
	} catch (Exception $e) {
		error_log('Make Get Upcoming Events Error: ' . $e->getMessage());
		return $upcoming_events; // Return what we have so far
	}
}



function make_get_event_instructors($parent_id) {
  $instructors = array();
  $sub_events = get_posts(array(
    'post_type' => 'sub_event',
    'posts_per_page' => -1,
    'post_parent' => $parent_id,
    'fields' => 'ids',
  ));
  if ($sub_events) {
    foreach ($sub_events as $sub_event_id) {
      $instructorID = get_post_meta($sub_event_id, 'instructorID', true);
      if ($instructorID) {
        $user = get_user_by('id', $instructorID);
        $instructors[$user->ID] = $user;
      }
    }
  }
  return $instructors;
}