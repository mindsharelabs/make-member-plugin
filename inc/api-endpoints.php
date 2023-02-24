<?php


add_action( 'rest_api_init', 'experience_content_routes_v2');
function experience_content_routes_v2() {

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

  endif;


}



function make_events($request) {
  $events = tribe_get_events( array(
   'posts_per_page' => -1,
   'start_date'     => 'now',
   'tax_query'=> array(
      array(
      'taxonomy' => 'tribe_events_cat',
      'field' => 'slug',
      'terms' => 'badge-classes'
      ))
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









function experience($request) {

  $params = $request->get_params();
  $post = get_post($params['id']);
  if($post) :
    $attendees = tribe_tickets_get_attendees( $event->ID );
    wp_send_json_success($attendees);
  endif;

  
}