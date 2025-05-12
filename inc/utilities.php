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






//Gets all current members and returns an arrayt of user objects
function make_get_active_members(){
  global $wpdb;
  // Getting all User IDs and data for a membership plan
  return $wpdb->get_results( "
      SELECT DISTINCT um.user_id
      FROM {$wpdb->prefix}posts AS p
      LEFT JOIN {$wpdb->prefix}posts AS p2 ON p2.ID = p.post_parent
      LEFT JOIN {$wpdb->prefix}users AS u ON u.id = p.post_author
      LEFT JOIN {$wpdb->prefix}usermeta AS um ON u.id = um.user_id
      WHERE p.post_type = 'wc_user_membership'
      AND p.post_status IN ('wcm-active')
      AND p2.post_type = 'wc_membership_plan'
      LIMIT 999
  ");
}


//Get's all current members and returns them as an array of user ids
function make_get_active_members_array(){
  global $wpdb;
  // Getting all User IDs and data for a membership plan
  $members = $wpdb->get_results( "
      SELECT DISTINCT um.user_id
      FROM {$wpdb->prefix}posts AS p
      LEFT JOIN {$wpdb->prefix}posts AS p2 ON p2.ID = p.post_parent
      LEFT JOIN {$wpdb->prefix}users AS u ON u.id = p.post_author
      LEFT JOIN {$wpdb->prefix}usermeta AS um ON u.id = um.user_id
      WHERE p.post_type = 'wc_user_membership'
      AND p.post_status IN ('wcm-active')
      AND p2.post_type = 'wc_membership_plan'
      LIMIT 999
  ");

  $members_array = array();
  foreach($members as $member) {
    $members_array[] = $member->user_id;
  }

  return $members_array;

}





function make_get_upcoming_events($num = 3, $ticketed = true, $args = array(), $page = 1, $upcoming_events = array()) {


  $default_args = array(
      'post_type' => 'sub_event',
      'posts_per_page' => ($num > 12 ? $num : 12),
      'meta_key'       => 'event_time_stamp', // Meta field for the event start date
      'orderby'        => 'meta_value', // Order by the event start date
      'order'          => 'ASC', // Ascending order (earliest events first)
      'paged'          => $page,
      'meta_query'     => array(
          array(
              'key'     => 'event_time_stamp',
              'value'   => date('Y-m-d H:i:s'),
              'compare' => '>=', // Only get events starting after the current date
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
                  if(make_event_has_available_tickets(get_the_id())) :
                      $upcoming_events[get_the_id()] = get_the_title();
                  endif;
              else :
                  $upcoming_events[get_the_id()] = get_the_title();
              endif;  
          endif;
          
      endwhile;
  endif;


  if(count($upcoming_events) < $num) :
      $page = $page + 1;
      if($events->max_num_pages >= $page) :
          $args['paged'] = $page;
          $upcoming_events = make_get_upcoming_events($num, $ticketed, $args, $page, $upcoming_events);
      else :
          return $upcoming_events;    
      endif;    
  endif;

  return $upcoming_events;
}

