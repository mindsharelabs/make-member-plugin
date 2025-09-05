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
        $complimentary_memberships = wc_memberships_get_user_memberships($member_obj->ID, array('status' => 'complimentary'));
        $all_memberships = array_merge($active_memberships, $complimentary_memberships);
        $memberships = '';
        if($all_memberships) :
          
          foreach($all_memberships as $membership) :
            $memberships .= $membership->plan->name;
            if(next($all_memberships)) :
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

add_action('wc_memberships_user_membership_status_changed', 'make_notify_zapier_on_status_change', 10, 3);

function make_notify_zapier_on_status_change($membership, $old_status, $new_status) {

  $user = get_userdata($membership->get_user_id());
  $plan = $membership->get_plan();
  $profile = new makeProfile($user->ID);
  $start_date = $membership->get_start_date();
  $end_date = $membership->get_end_date();

  $payload = array(
    'user_id' => $user->ID,
    'name' => $user->display_name,
    'email' => $user->user_email,
    'new_status' => $new_status,
    'old_status' => $old_status,
    'membership_plan' => $plan ? $plan->get_name() : '',
    'membership_id' => $membership->get_id(),
    'start_date' => $start_date,
    'end_date' => $end_date,
    'roles' => $user->roles,
    'has_waiver' => $profile->has_waiver(),
    'has_agreement' => $profile->has_agreement(),
  );
  $zapier_webhook_url = 'https://hooks.zapier.com/hooks/catch/20748362/u4i3vnc/';

  wp_remote_post($zapier_webhook_url, array(
    'method' => 'POST',
    'headers' => array('Content-Type' => 'application/json'),
    'body' => json_encode($payload),
    'timeout' => 20,
  ));
}



add_filter('oidc_registered_clients', function () {
  return [
    'wikijs-client' => [
      'name'         => 'Make Santa Fe Wiki',
      'secret'       => 'l2AGBC*Ee@n2FNrtlP!1KC7orG9naPvYvSlQm#VMkoshntRNtT1U74QY@j5lF1d',
      'redirect_uri' => 'https://wiki.makesantafe.org/login/8f64200d-cb85-47cd-9169-0feb309d714f/callback',
      'grant_types'  => ['authorization_code'],
      'scope'        => 'openid profile email',
    ],
  ];
});