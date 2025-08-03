<?php


class makeProfile {
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

      add_action('wp_footer', array($this, 'enqueueAssets'));
    endif;

  }


  public function enqueueAssets(){

    wp_register_style('member-styles', MAKESF_URL . 'assets/css/style.css', array(), MAKESF_PLUGIN_VERSION);
    wp_enqueue_style('member-styles');

  }

  public static function get_instance() {
    if ( null === self::$instance ) {
      self::$instance = new self;
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
        'link' => $this->badgesURL
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


add_action('init', 'makesf_start_er_up');
function makesf_start_er_up(){
  new makeProfile();
}








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



