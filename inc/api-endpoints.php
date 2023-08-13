<?php


add_action( 'rest_api_init', 'make_content_routes_v2');
function make_content_routes_v2() {

  if(function_exists('tribe_get_events')) :

    register_rest_route('make', '/events', array(
      'methods' => WP_REST_Server::ALLMETHODS,
      'callback' => 'make_events',
      'permission_callback' => '__return_true',
    ));


    register_rest_route('make', '/events/(?P<id>\d+)', array(
      'methods' => WP_REST_Server::ALLMETHODS,
      'callback' => 'make_event',
      'permission_callback' => '__return_true',
    ));

    register_rest_route('make', '/members', array(
      'methods' => WP_REST_Server::ALLMETHODS,
      'callback' => 'make_members',
      'permission_callback' => '__return_true',
    ));

  endif;


}


function make_members($request) {
  $members = make_get_active_members();
  if($members) :
    $all_members = array();
    foreach($members as $member) :
      
      $member_obj = get_user_by('ID', $member->user_id);
      if(function_exists('wcs_get_users_subscriptions')) :
        $user_subscriptions = '';
        $total = '';
        $current_subscriptions = wcs_get_users_subscriptions($member_obj->ID);
        foreach ($current_subscriptions as $subscription):
          if ($subscription->has_status(array('active'))) :
            foreach($subscription->get_items() as $item) :
              $user_subscriptions .= $item->get_name() . ' | ';
              $total = $item->get_total();
            
            endforeach;
            
          endif;
        endforeach;
      endif;
      

      if(function_exists('wc_memberships_get_user_active_memberships')) :
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
        
      endif;

      
      $all_members[] = array(
        'ID' => $member_obj->ID,
        'name' => $member_obj->data->display_name,
        'memberships' => (isset($memberships) ? $memberships : false),
        'subscriptions' => (isset($user_subscriptions) ? $user_subscriptions : false),
        'total' => (isset($total) ? $total : false),
        'image' => get_avatar_url($member_obj->ID, ['size' => '400'])
      );
    endforeach;
  endif;




  return wp_send_json_success($all_members);
}

function make_events($request) {
  $events = tribe_get_events( array(
   'posts_per_page' => -1,
   'start_date'     => 'now',
   // 'tax_query'=> array(
   //    array(
   //    'taxonomy' => 'tribe_events_cat',
   //    'field' => 'slug',
   //    'terms' => 'badge-classes'
   //    ))
  ));
  if($events) :
    $all_events = array();
    foreach($events as $event) :
      $attendees = tribe_tickets_get_attendees( $event->ID );
      $all_events[] = array(
        'ID' => $event->ID,
        'start_date' => tribe_get_start_date($event->ID,false, 'M j, y'),
        'title' => get_the_title($event->ID),
        'attendee_count' => count($attendees),
        'image' => get_the_post_thumbnail_url($event->ID, 'full' ),
        'excerpt' => get_the_excerpt($event->ID),
        'permalink' => get_permalink($event->ID)
      );
    endforeach;
  endif;




  return wp_send_json_success($all_events);
}

function make_event($request) {

  return wp_send_json_success($return);
}










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

