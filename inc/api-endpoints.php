<?php


add_action( 'rest_api_init', 'make_content_routes_v2');
function make_content_routes_v2() {

  register_rest_route('make', '/members', array(
    'methods' => WP_REST_Server::ALLMETHODS,
    'callback' => 'make_members',
    'permission_callback' => '__return_true',
  ));

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
        // 'email' => $member_obj->user_email,
        'memberships' => (isset($memberships) ? $memberships : false),
        'subscriptions' => (isset($user_subscriptions) ? $user_subscriptions : false),
        'total' => (isset($total) ? $total : false),
        'image' => get_avatar_url($member_obj->ID, ['size' => '400'])
      );
    endforeach;
  endif;




  return wp_send_json_success($all_members);
}

