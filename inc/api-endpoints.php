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

        $current_subscriptions = wcs_get_users_subscriptions($member_obj->ID);
        foreach ($current_subscriptions as $subscription):
          if ($subscription->has_status(array('active'))) :
            foreach($subscription->get_items() as $item) :
              $user_subscriptions .= $item->get_name() . ' | ';
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
        'image' => get_avatar_url($member_obj->ID, ['size' => '400'])
      );
    endforeach;
  endif;




  return wp_send_json_success($all_members);
}







add_action('wc_memberships_user_membership_status_changed', 'make_notify_zapier_on_status_change', 10, 3);
function make_notify_zapier_on_status_change($membership, $old_status, $new_status) {
  mapi_write_log("Membership status changed for user ID " . $membership->get_user_id() . " from $old_status to $new_status");

  $user = get_userdata($membership->get_user_id());

  $args = array(
    'user_id' => $user->ID,
    'name' => $user->display_name,
    'email' => $user->user_email,
    'membership_status' => $new_status,
    'membership_plan' => ($membership ? $membership->get_plan()->name : null),
    'membership_id' => ($membership ? $membership->get_id() : null),
    'start_date' => ($membership ? $membership->get_start_date() : null),
    'end_date' => ($membership ? $membership->get_end_date() : null),

  );
  mapi_write_log($args);
  make_send_user_update_to_zapier($user->ID, $args);

}




add_action('makesf_after_set_benefits_status', function($user_id, $status) {
  $args['volunteer_status'] = $status;

  make_send_user_update_to_zapier($user_id, $args);

}, 10, 2);


function make_send_user_update_to_zapier($user_id, $args = array()) {
  $profile = new makeProfile($user_id);

  $membership = $profile->get_user_active_memberships()[0] ?? null; //TODO: handle multiple memberships
  $user = get_userdata($membership->get_user_id());
  $status = ($membership ? $membership->get_status() : 'cancelled');
  $plan = ($membership ? $membership->get_plan()->name : null);
  $start_date = ($membership ? $membership->get_start_date() : null);
  $end_date = ($membership ? $membership->get_end_date() : null);


  $payload_default = array(
    'user_id' => $user->ID,
    'name' => $user->display_name,
    'email' => $user->user_email,
    'membership_status' => $status,
    'membership_plan' => $plan,
    'membership_id' => $membership->get_id(),
    'start_date' => $start_date,
    'end_date' => $end_date,
    'roles' => $user->roles,
    'has_waiver' => ($profile->has_waiver() ? 'Signed' : 'Not Signed'),
    'has_agreement' => ($profile->has_agreement() ? 'Signed' : 'Not Signed'),
    'volunteer_status' => makesf_get_benefits_status($user->ID, date('Y-m')),
  );

  $payload = wp_parse_args($args, $payload_default);

  mapi_write_log($payload);

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
      'secret'       => 'AoIKthwDsQKmb1w8QVb1dWVwyB5Tjk3g0j8aLdPg4OZxiw8ZHD8NM6iQpRnNVLe',
      'redirect_uri' => 'https://wiki.makesantafe.org/login/8f64200d-cb85-47cd-9169-0feb309d714f/callback',
      'grant_types'  => ['authorization_code'],
      'scope'        => 'openid profile email',
    ],
  ];
});

add_filter('oidc_user_claims', function($claims, $user){
  $claims['email'] = $user->user_email;
  $claims['name']  = trim($user->first_name.' '.$user->last_name) ?: $user->display_name;
  return $claims;
}, 10, 2);

add_filter('oidc_userinfo', function($userinfo, $user){
  $userinfo['email'] = $user->user_email;
  $userinfo['name']  = trim($user->first_name.' '.$user->last_name) ?: $user->display_name;
  return $userinfo;
}, 10, 2);

add_filter('oidc_registered_clients', function ($clients) {
  $clients['wikijs-client']['scope'] = 'openid email profile';
  return $clients;
});