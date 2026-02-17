<?php


class makeProfile {
  private static $instance = null;

  // My Badges endpoint
  private $badgesEndpoint = 'my-badges';
  private $badgesPageURL = '/my-account/my-badges/';

  private $userID = '';

  //Options
  private $options = '';
  private $waiverURL = '';
  private $membershipURL = '';
  private $agreementURL = '';
  private $badgesURL = '';
  // private $forumURL = '';
  private $workshopsURL = '';
  private $sharingURL = '';
  private $profileURL = '/my-account/make-profile/';

  private $workshopCategory = '';

  private $memberResources = '';

  //User Info
  private $certifications = '';

  public function __construct($userID = false) {
    $this->userID = $userID ? $userID : get_current_user_id();

    $this->options = get_option( 'makesf_support_settings' );

    if($this->userID) :
      $this->waiverURL = (isset($this->options['makesf_waiver_url']) ? $this->options['makesf_waiver_url'] : false);
      $this->agreementURL = (isset($this->options['makesf_agreement_url']) ? $this->options['makesf_agreement_url'] : false);
      $this->membershipURL = (isset($this->options['makesf_membership_url']) ? $this->options['makesf_membership_url'] : false);
      $this->badgesURL = (isset($this->options['makesf_badge_url']) ? $this->options['makesf_badge_url'] : false);

      $this->sharingURL = (isset($this->options['makesf_share_url']) ? $this->options['makesf_share_url'] : false);

      if(function_exists('get_field')) :
        $this->memberResources = get_field('member_resources', 'options');
      endif;

      add_action('woocommerce_before_my_account', array($this, 'display_member_resources'), 30);
      add_action('woocommerce_account_dashboard', array($this, 'profile_progress'));
      // Add volunteer hours progress section on My Account dashboard
      add_action('woocommerce_account_dashboard', array($this, 'volunteer_hours_progress'), 35);

      add_action('wp_footer', array($this, 'enqueueAssets'));
    endif;


    add_rewrite_endpoint($this->badgesEndpoint, EP_ROOT | EP_PAGES);
    add_filter('query_vars', array($this, 'add_my_badges_query_var'), 0);
    add_filter('woocommerce_account_menu_items', array($this, 'add_my_badges_menu_item'));
    add_action('woocommerce_account_' . $this->badgesEndpoint . '_endpoint', array($this, 'render_my_badges_page'));



  }




  /**
   * Add our endpoint to WP query vars.
   */
  public function add_my_badges_query_var($vars) {
    $vars[] = $this->badgesEndpoint;
    return $vars;
  }

  /**
   * Insert "My Badges" into the My Account menu, after Dashboard when possible.
   */
  public function add_my_badges_menu_item($items) {
    $new = array();

    foreach ($items as $key => $label) {
      $new[$key] = $label;
      if ('dashboard' === $key) {
        $new[$this->badgesEndpoint] = 'My Badges';
      }
    }

    if (!isset($new[$this->badgesEndpoint])) {
      $new[$this->badgesEndpoint] = 'My Badges';
    }

    return $new;
  }

  /**
   * Data access (kept flexible via filters so we can wire to real sources later).
   */
  public function get_all_badges($user_id) {
    $badges = new WP_Query(array(
      'post_type' => 'certs',
      'posts_per_page' => -1,
    ));
    return wp_list_pluck($badges->posts, 'ID');
  }

  public function get_user_badges($user_id) {
    $certs = get_user_meta($user_id, 'certifications', true);
    if($certs) :
      return $certs;
    else :
      return array();
    endif;
  }

  public static function get_user_signins($user_id){
    global $wpdb;
    $results = $wpdb->get_results("SELECT * FROM `make_signin` where user = $user_id;");
    $badge_signins = array();
    foreach ($results as $result) {
      $badges = unserialize($result->badges);
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

  public static function get_badge_name_by_id($id){
    if ($id == 'workshop'):
      $title = 'Attended Workshop';
    elseif ($id == 'volunteer'):
      $title = 'Volunteered';
    elseif ($id == 'other'):
      $title = 'Other';
    elseif (get_the_title($id)):
      $title = get_the_title($id);
    else:
      $title = false;
    endif;
    return $title;
  }

  /**
   * Render the My Badges page.
   * Best practice: prepare data in PHP (controller) and keep the template as a view.
   */
  public function render_my_badges_page() {
    $user_id = $this->userID ? (int) $this->userID : (int) get_current_user_id();



    // $user_id = 54291; //temp hardcode for testing, you can put any user ID here to test with that user's data



    //This function only runs once and will generate all of the correct meta for badge expirations. 
    makesf_user_signin_meta_generator($user_id);



    // Allow full override.
    if (has_action('makesf_render_my_badges')) {
      do_action('makesf_render_my_badges', $user_id);
      return;
    }

    // Prepare template context.
    $profile = $this; // expose the instance if the template needs helper methods
    $all_badges = $this->get_all_badges($user_id);
    $earned_badges = $this->get_user_badges($user_id);
    $user_signins = $this->get_user_signins($user_id);

    $template_path = trailingslashit(__DIR__) . 'templates/my-badges.php';

    if (file_exists($template_path)) {
      include $template_path;
      return;
    }

    echo '<div class="woocommerce-MyAccount-content">';
      echo '<h2>My Badges</h2>';
      echo '<p>My Badges template not found.</p>';
    echo '</div>';
  }

  public function enqueueAssets(){

    wp_register_style('member-styles', MAKESF_URL . 'assets/css/style.css', array(), MAKESF_PLUGIN_VERSION);
    wp_enqueue_style('member-styles');

    // Add small, focused styles for volunteer progress UI
    $css = '
    .volunteer-progress-wrapper{margin:20px 0;}
    .volunteer-progress-wrapper h3{margin:0 0 12px;color:#2c3e50;text-align:center}
    .vp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
    .vp-card{background:#fff;border:1px solid #e9ecef;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.06);padding:16px}
    .vp-card-previous{background:#f8f9fa;filter:saturate(.85);}
    .vp-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
    .vp-title{font-weight:700;color:#2c3e50}
    .vp-badge{font-size:12px;padding:4px 8px;border-radius:999px;background:#eef2ff;color:#2b3a67;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
    .vp-progress{margin:10px 0 6px;background:#f1f3f5;border-radius:999px;overflow:hidden;height:14px;position:relative}
    .vp-bar{height:100%;width:0;transition:width .8s ease;box-shadow:inset 0 0 6px rgba(0,0,0,.12)}
    .vp-bar-complete{background:#28a745}
    .vp-bar-incomplete{background:#dc3545}
    .vp-stats{display:flex;justify-content:space-between;font-size:12px;color:#566573}
    .vp-score{font-size:28px;font-weight:800;color:#2c3e50}
    @media (prefers-reduced-motion: reduce){.vp-bar{transition:none}}
    ';
    wp_add_inline_style('member-styles', $css);

  }

  public static function get_instance() {
    if ( null === self::$instance ) {
      self::$instance = new self();
    }
    return self::$instance;
  }
  private function define( $name, $value ) {
    if ( ! defined( $name ) ) {
      define( $name, $value );
    }
  }



  public function display_member_resources() {
    $resources = $this->memberResources;


    if($resources) :
      echo '<div id="memberResources" class="row member-resources">';
        echo '<div class="col-12 col-md">';
          echo '<div class="row">';

            
            if(get_field('enable_member_modal', 'option')) :
              echo '<div class="col-12 text-center mt-2 mb-2">';
                echo '<div class="alert alert-success" role="alert" id="alert">';
                  echo '<h3 class=" text-center mb-1">' . get_field('member_modal_title', 'option') . '</h3>';
                  echo '<p>' . get_field('member_modal_content', 'option') . '</p>';
                  if($link = get_field('member_modal_button', 'option')) :
                    echo '<a href="' . $link['url'] . '" class="btn btn-primary btn-block mt-auto">' . $link['title'] . '</a>';
                  endif;
                echo '</div>';
              echo '</div>';
            endif;


            echo '<div class="col-12 text-center mt-2 mb-2">';
              echo '<h3 class="pt-3">Member Resources</h3>';
            echo '</div>';  

            foreach ($resources as $key => $item) :
              echo '<div class="col-12 col-md-4 mb-3">';
                echo '<div class="card h-100">';
                  echo '<div class="card-body d-flex flex-column">';
                    echo '<h5 class="card-title">' . $item['resource_name'] . '</h5>';
                    echo '<p class="card-text">' . $item['resource_desc'] . '</p>';
                    echo '<a href="' . $item['resource_link'] . '" class="btn btn-primary btn-block mt-auto">Check it out.</a>';
                  echo '</div>';
                echo '</div>';
              echo '</div>';
            endforeach;
          echo '</div>';
        echo '</div>';


      echo '</div>';
    endif;
  }

  private function get_membership_plan_name() {
    if(function_exists('wc_memberships_get_user_active_memberships')) :
      $active_memberships = wc_memberships_get_user_active_memberships($this->userID);
      $memberships = '';
      if($active_memberships) :
                foreach($active_memberships as $membership) :
          $memberships .= $membership->plan->name;
          if(next($active_memberships)) :
            $memberships .= ' & ';
          endif;
        endforeach;
      endif;
      return $memberships;  
    endif;

  }


  function profile_progress() {
      $maker_steps = $this->get_profile_steps();
      if ($maker_steps) :
          $total_steps = count($maker_steps);
          $completed_steps = 0;
          $incomplete_items = [];
          $completed_items = [];

          foreach ($maker_steps as $step) {
              if ($step['complete']) {
                  $completed_steps++;
                  $completed_items[] = $step;
              } else {
                  $incomplete_items[] = $step;
              }
          }

          $percent_complete = round(($completed_steps / $total_steps) * 100);

          echo '<div class="progress-container mt-3 mb-3">';
          echo '<h6>Profile Completion: ' . $percent_complete . '%</h6>';
          echo '<div class="progress bg-white" style="height: 2rem;">';

          // Each completed step gets a segment
          if ($completed_items) {
              $segment_width = 100 / $total_steps;
              foreach ($completed_items as $step) {
                  echo '<div class="progress-bar bg-success me-1" role="progressbar" style="width: ' . $segment_width . '%;" aria-valuenow="' . $segment_width . '" aria-valuemin="0" aria-valuemax="100">';
                  echo '<span class="d-none d-md-inline">' . esc_html($step['label']) . '</span>';
                  echo '</div>';
              }
          }
          // Each incomplete step gets a faded segment
          if ($incomplete_items) {
              $segment_width = 100 / $total_steps;
              foreach ($incomplete_items as $step) {
                  echo '<div class="progress-bar bg-danger text-light me-1" role="progressbar" style="width: ' . $segment_width . '%;" aria-valuenow="' . $segment_width . '" aria-valuemin="0" aria-valuemax="100">';
                  echo '<span class="d-none d-md-inline">' . esc_html($step['label']) . '</span>';
                  echo '</div>';
              }
          }

          echo '</div>';

          // List incomplete items
          if (!empty($incomplete_items)) {
              echo '<div class="mt-3">';
              echo '<h6>Incomplete Steps:</h6>';
              echo '<ul class="list-group">';
              foreach ($incomplete_items as $step) {
                  echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                  echo '<span><i class="fas fa-times-circle text-danger me-2"></i>' . esc_html($step['label']) . '</span>';
                  if ($step['link']) {
                      echo '<a href="' . esc_url($step['link']) . '" class="btn btn-sm btn-primary">Complete Now</a>';
                  }
                  echo '</li>';
              }
              echo '</ul>';
              echo '</div>';
          }
          echo '</div>';
      endif;
  }

  // Volunteer hours progress UI for My Account dashboard
  public function volunteer_hours_progress() {
      $user_id = $this->userID;
      if (!$user_id || !function_exists('make_get_user_volunteer_hours_for_month')) {
          return;
      }

      $target = (int) get_option('makesf_volunteer_target_hours', 12);
      $current_ym = date('Y-m');
      $prev_ym = date('Y-m', strtotime('first day of last month'));

      $current = make_get_user_volunteer_hours_for_month($user_id, $current_ym, true);
      $previous = make_get_user_volunteer_hours_for_month($user_id, $prev_ym, false);

      $cur_pct = $target > 0 ? min(100, round(($current['total_hours'] / $target) * 100)) : 0;
      $pre_pct = $target > 0 ? min(100, round(($previous['total_hours'] / $target) * 100)) : 0;

      // Only show to users who have hours this or last month
      if ((int) $current['total_minutes'] === 0 && (int) $previous['total_minutes'] === 0) {
          return;
      }

      // Gamified badges
      $badge_for = function($pct){
          if ($pct >= 100) return 'Goal Met';
          if ($pct >= 75)  return 'On Track';
          if ($pct >= 40)  return 'Making Progress';
          return 'Getting Started';
      };
      $cur_label = $badge_for($cur_pct);
      $pre_label = $badge_for($pre_pct);

      echo '<div class="volunteer-progress-wrapper">';
      echo '<h3>Volunteer Hours</h3>';
      echo '<div class="vp-grid">';

      // Compute bar classes (red until complete, then green)
      $pre_class = ($pre_pct >= 100) ? 'vp-bar-complete' : 'vp-bar-incomplete';
      $cur_class = ($cur_pct >= 100) ? 'vp-bar-complete' : 'vp-bar-incomplete';

      // Previous month card (left)
      echo '<div class="vp-card vp-card-previous">';
      echo '<div class="vp-header">';
      echo '<div class="vp-title">' . esc_html(date('F Y', strtotime('first day of last month'))) . '</div>';
      echo '<div class="vp-badge">' . esc_html($pre_label) . '</div>';
      echo '</div>';
      echo '<div class="vp-score">' . esc_html($previous['total_hours']) . 'h</div>';
      echo '<div class="vp-progress"><div class="vp-bar ' . esc_attr($pre_class) . '" style="width:' . esc_attr($pre_pct) . '%"></div></div>';
      echo '<div class="vp-stats"><span>' . intval($previous['session_count']) . ' sessions</span><span>Target ' . intval($target) . 'h</span></div>';
      echo '</div>';

      // Current month card (right)
      echo '<div class="vp-card">';
      echo '<div class="vp-header">';
      echo '<div class="vp-title">' . esc_html(date('F Y')) . '</div>';
      echo '<div class="vp-badge">' . esc_html($cur_label) . '</div>';
      echo '</div>';
      echo '<div class="vp-score">' . esc_html($current['total_hours']) . 'h</div>';
      echo '<div class="vp-progress"><div class="vp-bar ' . esc_attr($cur_class) . '" style="width:' . esc_attr($cur_pct) . '%"></div></div>';
      echo '<div class="vp-stats"><span>' . intval($current['session_count']) . ' sessions</span><span>Target ' . intval($target) . 'h</span></div>';
      echo '</div>';

      echo '</div>'; // grid
      echo '</div>'; // wrapper
  }
  private function get_profile_steps() {
    return array(
      'waiver' => array(
        'label' => 'Sign a safety waiver',
        'complete' => $this->has_form_submission(27, 34),
        'link' => $this->waiverURL
      ),
      'membership' => array(
        'label' => 'Start your membership',
        'complete' => $this->has_membership(),
        'link' => $this->membershipURL
      ),
      'membership_agreement' => array(
        'label' => 'Sign the membership agreement',
        // 'complete' => $this->has_form_submission(43, 16), //staging site
        'complete' => $this->has_form_submission(45, 16),//live site
        'link' => $this->agreementURL
      ),
      'badge' => array(
        'label' => 'Get a badge',
        'complete' => $this->has_badge(),
        'link' => $this->badgesPageURL
      ),
     
      'profile' => array(
        'label' => 'Share your profile',
        'complete' => $this->has_profile(),
        'link' => $this->profileURL
      ),

    );
  }


  private function has_membership(){
    // bail if Memberships isn't active
    if ( ! function_exists( 'wc_memberships' ) ) {
      return;
    }
    return wc_memberships_is_user_active_member($this->userID);
  }

  public function get_user_active_memberships() {
    // bail if Memberships isn't active
    if ( ! function_exists( 'wc_memberships' ) ) {
      return;
    }
    return wc_memberships_get_user_active_memberships($this->userID);
  }

  public function get_user_memberships() {
    // bail if Memberships isn't active
    if ( ! function_exists( 'wc_memberships' ) ) {
      return;
    }
    // get all active memberships for a user;
    // returns an array of active user membership objects
    // or null if no memberships are found
    $args = array(
        'status' => array( 'active', 'complimentary', 'pending' ),
    );
    return wc_memberships_get_user_memberships( $this->userID, $args );
  }

  private function has_form_submission($form_id, $field_id) {
    // bail if Gravity Forms isn't active
    if (! class_exists ('GFAPI')) {
      return;
    }
    $form = new GFAPI();
    $user_info = get_userdata($this->userID);
    $user_email = $user_info->user_email;
    $search_criteria = array(
      'status'        => 'active',
      'field_filters' => array(
          'mode' => 'any',
          array(
              'key'   => 'created_by',
              'value' => $this->userID 
          ),
          array(
              'key'   => $field_id, 
              'value' => $user_email
          )
      )
    );
    return (count($form->get_entries( $form_id, $search_criteria)) > 0) ? true : false;
  }


  public function has_waiver() {
    return $this->has_form_submission(27, 34);
  }

  public function has_agreement() {
    return $this->has_form_submission(45, 16);
  }

  private function has_profile() {
    return get_user_meta($this->userID, 'display_profile_publicly', true);
  }

  private function has_badge(){
    $certs = get_user_meta($this->userID, 'certifications', true);
    if($certs) :
      return true;
    else :
      return false;
    endif;
  }






}//end of class

add_action('init', function(){
  makeProfile::get_instance();
});








//if the current user fills out the Waiver and Release of Liability, then we add this user meta
add_action( 'gform_after_submission_27', function ( $entry, $form ) {
  if (! class_exists ('GFAPI')) {
    return;
  }

  if($entry['created_by']) :
    update_user_meta( $entry['created_by'], 'waiver_complete', true );
  else :
    $user = get_user_by('email', $entry['34']);
    if($user) :
      $forms = new GFAPI();
      $forms->update_entry_property( $entry['id'], 'created_by', $user->ID );
      update_user_meta( $user->ID, 'waiver_complete', true );
    endif;
  endif;


}, 10, 2 );



//if the current user fills out the Member Agreement, then we add this user meta
add_action( 'gform_after_submission_45', function ( $entry, $form ) {
  if (! class_exists ('GFAPI')) {
    return;
  }

  if($entry['created_by']) :
    update_user_meta( $entry['created_by'], 'agreement_complete', true );
  else :
    $user = get_user_by('email', $entry['16']);
    if($user) :
      $forms = new GFAPI();
      $forms->update_entry_property( $entry['id'], 'created_by', $user->ID );
      update_user_meta( $user->ID, 'agreement_complete', true );
    endif;
  endif;

}, 10, 2 );



add_action( 'update_user_metadata', function( $check, $object_id, $meta_key, $meta_value, $prev_value) {
  //set badge expiration to current time when badge is manually added to a user. 
  
  if($meta_key == 'certifications') :
    $current_certs = get_user_meta($object_id, 'certifications', true);

    //make sure current certs always returns a array to avoid errors with array_diff when there are no certs
    if(!$current_certs) :
      $current_certs = array();
    endif;

    
    //only make changes if we're adding badges, not removing them
    if(count($meta_value) > count($current_certs)) :
     
      
      $added_badges = array_diff($meta_value, $current_certs);
      if($added_badges) :
        foreach ($added_badges as $key => $value) {
          update_user_meta($object_id, $value . '_last_time',current_time('mysql'));
        }
      endif;
    endif;


  endif;

}, 1, 5 );




class makeProfileAdmin {

  private static $instance = null;

  private $ajax_action = 'makesf_renew_badge';
  private $nonce_action = 'makesf_renew_badge_nonce';

  private $ajax_add_action = 'makesf_add_badge';
  private $nonce_add_action = 'makesf_add_badge_nonce';

  private $ajax_remove_action = 'makesf_remove_badge';
  private $nonce_remove_action = 'makesf_remove_badge_nonce';

  public function __construct() {
    // Render fields on user profile screens
    add_action('show_user_profile', array($this, 'render_badge_expiration_metabox'));
    add_action('edit_user_profile', array($this, 'render_badge_expiration_metabox'));

    // Assets
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

    // Ajax
    add_action('wp_ajax_' . $this->ajax_action, array($this, 'ajax_renew_badge'));
    add_action('wp_ajax_' . $this->ajax_add_action, array($this, 'ajax_add_badge'));
    add_action('wp_ajax_' . $this->ajax_remove_action, array($this, 'ajax_remove_badge'));
  }

  public static function get_instance() {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Only load assets on profile.php and user-edit.php
   */
  public function enqueue_admin_assets($hook) {
    if (!in_array($hook, array('profile.php', 'user-edit.php'), true)) {
      return;
    }

 
    // Minimal inline JS (kept in PHP for now as scaffolding)
    wp_register_script('makesf-badge-admin', MAKESF_URL . 'assets/js/badge-management.js', array('jquery'), MAKESF_PLUGIN_VERSION, true);
    wp_enqueue_script('makesf-badge-admin');

    wp_localize_script('makesf-badge-admin', 'MAKESF_BADGE_ADMIN', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'action'   => $this->ajax_action,
      'nonce'    => wp_create_nonce($this->nonce_action),
      'add_action'    => $this->ajax_add_action,
      'add_nonce'     => wp_create_nonce($this->nonce_add_action),
      'remove_action' => $this->ajax_remove_action,
      'remove_nonce'  => wp_create_nonce($this->nonce_remove_action),
      'i18n'     => array(
        'renewing' => 'Renewing…',
        'renew'    => 'Renew',
        'error'    => 'Something went wrong. Please try again.',
      ),
    ));


    // Tiny admin CSS
    $css_handle = 'makesf-badge-admin-css';
    wp_register_style($css_handle, false, array(), MAKESF_PLUGIN_VERSION);
    wp_enqueue_style($css_handle);
    $css = '
      .makesf-badge-admin-wrap{margin-top:20px}
      .makesf-badge-admin-table{width:100%;max-width:1000px}
      .makesf-badge-admin-table th{white-space:nowrap}
      .makesf-badge-admin-table td{vertical-align:middle}
      .makesf-badge-renew-status{font-weight:600}
    ';
    wp_add_inline_style($css_handle, $css);
  }

  /**
   * "Meta box" style section on the user profile edit screen.
   */
  public function render_badge_expiration_metabox($user) {
    if (!($user instanceof WP_User)) {
      return;
    }

    // Capability check
    if (!current_user_can('edit_user', $user->ID)) {
      return;
    }

    $user_id = (int) $user->ID;
    $badges  = get_user_meta($user_id, 'certifications', true);
    if (!$badges || !is_array($badges)) {
      $badges = array();
    }

    echo '<h2>Badge Expirations</h2>';
    echo '<div class="makesf-badge-admin-wrap">';
      echo '<p>Manage badge expiration dates for this member. Renew will update the badge renewal timestamp and (when supported) the expiration date.</p>';

      // Add badge UI
      $all_certs = new WP_Query(array(
        'post_type'      => 'certs',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
      ));

      $assigned = array();
      if (!empty($badges)) {
        $assigned = array_map('intval', $badges);
      }

      echo '<div class="makesf-badge-admin-add" style="margin:12px 0 16px;">';
      echo '<label for="makesf-add-badge" style="display:inline-block;margin-right:8px;font-weight:600;">Add Badge</label>';
      echo '<select id="makesf-add-badge" style="min-width:280px;">';
      echo '<option value="">Select a badge…</option>';

      if ($all_certs->have_posts()) {
        foreach ($all_certs->posts as $cert_id) {
          $cert_id = (int) $cert_id;
          if (in_array($cert_id, $assigned, true)) {
            continue;
          }
          echo '<option value="' . esc_attr($cert_id) . '">' . esc_html(get_the_title($cert_id)) . '</option>';
        }
      }

      echo '</select> ';
      echo '<button type="button" class="button" id="makesf-add-badge-btn" data-user="' . esc_attr($user_id) . '" data-action="' . esc_attr($this->ajax_add_action) . '" data-nonce="' . esc_attr(wp_create_nonce($this->nonce_add_action)) . '">Add</button>';
      echo '<span id="makesf-add-badge-status" style="margin-left:10px;font-weight:600;"></span>';
      echo '</div>';

      if (empty($badges)) {
        echo '<p><em>No badges found for this user.</em></p>';
        echo '</div>';
        return;
      }

      echo '<table class="widefat striped makesf-badge-admin-table">';
        echo '<thead>';
          echo '<tr>';
            echo '<th>Badge</th>';
            echo '<th>Last Used/Renewed</th>';
            echo '<th>Expires</th>';
            echo '<th>Renew</th>';
            echo '<th>Remove</th>';
            echo '<th>Status</th>';
          echo '</tr>';
        echo '</thead>';


        echo '<tbody>';

          foreach ($badges as $badge_id) {
            $badge_key = sanitize_key((string) $badge_id);
            $badge_name = makeProfile::get_badge_name_by_id($badge_id);
            if (!$badge_name) {
              $badge_name = $badge_key;
            }

            // Common meta keys we might have (kept flexible)
            $last_time = get_user_meta($user_id, $badge_key . '_last_time', true);

            //expires date equals last_time + expiration_time. It is not stored directly in user meta, but we may be able to infer it based on the badge's expiration_time field and the last_time.
            $expires = '—';
            $expiration_time = get_field('expiration_time', $badge_id);
            if ($expiration_time && $last_time) {
              $expires_ts = strtotime($last_time) + ($expiration_time * DAY_IN_SECONDS);
              $expires = date_i18n('M j, Y g:ia', $expires_ts);
            }

            $status_class = '';
            $expire_status = '';
              if ($expires === '—') {
                $status_class = 'notice notice-info';
                $expire_status = 'No Expiration';
              } else {
                $expires_ts = strtotime($expires);
                if ($expires_ts && $expires_ts < current_time('timestamp')) {
                  $status_class = 'notice notice-error';
                  $expire_status = 'Expired';
                } else {
                  $status_class = 'notice notice-success';
                  $expire_status = 'Active';
                }
              }

            echo '<tr>';
              echo '<td><strong>' . esc_html($badge_name) . '</strong><br><code>' . esc_html($badge_key) . '</code></td>';
              echo '<td class="makesf-badge-last-time">' . esc_html($this->format_meta_datetime($last_time)) . '</td>';
              echo '<td class="makesf-badge-expires">' . esc_html($this->format_meta_datetime($expires)) . '</td>';
              echo '<td>';
                echo '<button type="button" class="button button-primary makesf-renew-badge" data-user="' . esc_attr($user_id) . '" data-badge="' . esc_attr($badge_key) . '">Renew</button>';
              echo '</td>';

              echo '<td>';
                echo '<button type="button" class="button button-link-delete makesf-remove-badge" data-user="' . esc_attr($user_id) . '" data-badge-id="' . esc_attr((int) $badge_id) . '" data-action="' . esc_attr($this->ajax_remove_action) . '" data-nonce="' . esc_attr(wp_create_nonce($this->nonce_remove_action)) . '">Remove</button>';
              echo '</td>';

              echo '<td><span class="makesf-badge-renew-status ' . esc_attr($status_class) . '">' . $expire_status . '</span></td>';
            echo '</tr>';
          }

        echo '</tbody>';
      echo '</table>';
    echo '</div>';
  }

  /**
   * Ajax: add a badge (certs post ID) to a user's certifications array.
   */
  public function ajax_add_badge() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, $this->nonce_add_action)) {
      wp_send_json_error(array('message' => 'Invalid request.'), 403);
    }

    $user_id  = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $badge_id = isset($_POST['badge_id']) ? (int) $_POST['badge_id'] : 0;

    if (!$user_id || !$badge_id) {
      wp_send_json_error(array('message' => 'Missing user or badge.'), 400);
    }

    if (!current_user_can('edit_user', $user_id)) {
      wp_send_json_error(array('message' => 'You do not have permission to edit this user.'), 403);
    }

    if (get_post_type($badge_id) !== 'certs') {
      wp_send_json_error(array('message' => 'Invalid badge.'), 400);
    }

    $certs = get_user_meta($user_id, 'certifications', true);
    if (!$certs || !is_array($certs)) {
      $certs = array();
    }

    $certs_int = array_map('intval', $certs);
    if (in_array($badge_id, $certs_int, true)) {
      wp_send_json_error(array('message' => 'User already has this badge.'), 400);
    }

    $certs_int[] = $badge_id;
    $certs_int = array_values(array_unique($certs_int));


  //get expires time for this badge based on current time + expiration_time field from the certs post
    $expiration_time = get_field('expiration_time', $badge_id);
    if ($expiration_time) {
      $expires_ts = current_time('timestamp') + ($expiration_time * DAY_IN_SECONDS);
    }


    update_user_meta($user_id, 'certifications', $certs_int);

    update_user_meta($user_id, $badge_id . '_last_time', current_time('mysql'));

    wp_send_json_success(array(
      'badge_id' => $badge_id,
      'badge_name' => makeProfile::get_badge_name_by_id($badge_id) ? makeProfile::get_badge_name_by_id($badge_id) : get_the_title($badge_id),
      'last_time' => $this->format_meta_datetime(current_time('mysql')),
      'expires' => isset($expires_ts) ? $this->format_meta_datetime($expires_ts) : '—',
      'status' => 'Active',
      'message' => 'Badge added.',
    ));
  }

  /**
   * Ajax: remove a badge (certs post ID) from a user's certifications array.
   */
  public function ajax_remove_badge() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, $this->nonce_remove_action)) {
      wp_send_json_error(array('message' => 'Invalid request.'), 403);
    }

    $user_id  = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $badge_id = isset($_POST['badge_id']) ? (int) $_POST['badge_id'] : 0;

    if (!$user_id || !$badge_id) {
      wp_send_json_error(array('message' => 'Missing user or badge.'), 400);
    }

    if (!current_user_can('edit_user', $user_id)) {
      wp_send_json_error(array('message' => 'You do not have permission to edit this user.'), 403);
    }

    $certs = get_user_meta($user_id, 'certifications', true);
    if (!$certs || !is_array($certs)) {
      $certs = array();
    }

    $certs_int = array_map('intval', $certs);
    if (!in_array($badge_id, $certs_int, true)) {
      wp_send_json_error(array('message' => 'User does not have this badge.'), 400);
    }

    $certs_int = array_values(array_diff($certs_int, array($badge_id)));
    update_user_meta($user_id, 'certifications', $certs_int);

    // cleanup of related meta
    delete_user_meta($user_id, $badge_id . '_last_time');

    wp_send_json_success(array(
      'badge_id' => $badge_id,
      'message'  => 'Badge removed.',
    ));
  }

  /**
   * Ajax: renew a badge.
   */
  public function ajax_renew_badge() {
    // Nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, $this->nonce_action)) {
      wp_send_json_error(array('message' => 'Invalid request.'), 403);
    }

    $user_id  = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $badge_id = isset($_POST['badge_id']) ? sanitize_key((string) $_POST['badge_id']) : '';

    if (!$user_id || empty($badge_id)) {
      wp_send_json_error(array('message' => 'Missing user or badge.'), 400);
    }

    if (!current_user_can('edit_user', $user_id)) {
      wp_send_json_error(array('message' => 'You do not have permission to edit this user.'), 403);
    }

    // Ensure user actually has this badge
    $badges = get_user_meta($user_id, 'certifications', true);
    if (!$badges || !is_array($badges) || !in_array($badge_id, array_map('sanitize_key', $badges), true)) {
      wp_send_json_error(array('message' => 'User does not have this badge.'), 400);
    }

    // Update renewal timestamp
    $now_mysql = current_time('mysql');
    update_user_meta($user_id, $badge_id . '_last_time', $now_mysql);


    $days = get_field('expiration_time', $badge_id);



    wp_send_json_success(array(
      'badge_id'  => $badge_id,
      'last_time' => $this->format_meta_datetime($now_mysql),
      'expires'   => $days ? $this->format_meta_datetime(strtotime($now_mysql) + ($days * DAY_IN_SECONDS)) : '—',
      'message'   => 'Badge renewed.',
    ));
  }

  /**
   * Helper: normalize and format meta values that may be stored as mysql datetime, timestamp, or empty.
   */
  private function format_meta_datetime($value) {
    if (empty($value)) {
      return '—';
    }

    // If numeric timestamp
    if (is_numeric($value)) {
      $ts = (int) $value;
      if ($ts > 0) {
        return date_i18n('M j, Y g:ia', $ts);
      }
    }

    // If mysql datetime string
    $ts = strtotime((string) $value);
    if ($ts) {
      return date_i18n('M j, Y g:ia', $ts);
    }

    return (string) $value;
  }

}

// Boot the admin UI
add_action('init', function(){
  if (is_admin()) {
    makeProfileAdmin::get_instance();
  }
});