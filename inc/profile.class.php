<?php


class makeProfile {
  private $userID = '';

  //Options
  private $options = '';
  private $waiverURL = '';
  private $membershipURL = '';
  private $badgesURL = '';
  // private $forumURL = '';
  private $workshopsURL = '';
  private $sharingURL = '';
  private $profileURL = '/my-account/make-profile/';

  private $workshopCategory = '';

  private $memberResources = '';

  //User Info
  private $certifications = '';

  public function __construct() {
    $this->userID = get_current_user_id();

    $this->options = get_option( 'makesf_support_settings' );

    $this->waiverURL = (isset($this->options['makesf_waiver_url']) ? $this->options['makesf_waiver_url'] : false);
    $this->membershipURL = (isset($this->options['makesf_membership_url']) ? $this->options['makesf_membership_url'] : false);
    $this->badgesURL = (isset($this->options['makesf_badges_url']) ? $this->options['makesf_badges_url'] : false);

    $this->workshopsURL = (isset($this->options['makesf_workshops_url']) ? $this->options['makesf_workshops_url'] : false);
    $this->sharingURL = (isset($this->options['makesf_share_url']) ? $this->options['makesf_share_url'] : false);

    $this->workshopCategory = (isset($this->options['makesf_workshop_category']) ? $this->options['makesf_workshop_category'] : false);

    if(function_exists('get_field')) :
      $this->memberResources = get_field('member_resources', 'options');
    endif;


    add_action('woocommerce_before_my_account', array($this, 'display_member_resources'));
    add_action('woocommerce_account_dashboard', array($this, 'profile_progress'));

    add_action('wp_footer', array($this, 'enqueueAssets'));

  }


  public function enqueueAssets(){

    wp_register_style('member-styles', MAKESF_URL . 'css/style.css', array(), MAKESF_PLUGIN_VERSION);
    wp_enqueue_style('member-styles');

    //  wp_register_style('mindblankcssmin', get_template_directory_uri() . '/css/style.css', array(), THEME_VERSION);
    // wp_enqueue_style('mindblankcssmin');


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




  private function generate_member_badge() {
    $user = get_user_by('ID', $this->userID);

    $user_badge_image = get_transient( $this->userID . '_badge_image_url' );
    // $user_badge_image = false;
    if(!$user_badge_image) :
      $css = "
        .member-badge{border:1px dashed #000}
        .member-badge .badge-header{background:#be202e;text-align:center;padding:15px}
        .member-badge .badge-header img{width:33%;margin:0 auto;height:auto}
        .member-badge .badge-body{text-align:center}
        .member-badge .badge-body h2{font-size:1.7em}
        .member-badge .badge-body h3{font-size:1.2em;font-style:italic}
        .member-badge .badge-body .qr-code{padding:30px}
      ";


      $html = '<div class="member-badge p-4">';
        $html .= '<div class="badge-header text-white h3">Make Santa Fe</div>';
        $html .= '<div class="badge-body">';
          $html .= '<h2 class="mb-0 mt-4">' . $user->data->display_name . '</h2>';
          $html .= '<h3 class="mt-1">' . $this->get_membership_plan_name() . '</h3>';
          $html .= '<div class="qr-code px-5">';
            $html .= '<img class="w-100" src="https://api.qrserver.com/v1/create-qr-code/?data=' . $this->userID . '&size=400x400" alt="" title="' . $user->data->display_name . '" />';

          $html .= '</div>';  
        $html .= '</div>';  
      $html .= '</div>';

      // return $html;

        $google_fonts = "Courier Prime ";

        $data = array('html'=>$html,
                      'css'=>$css,
                      'google_fonts'=>$google_fonts,

                    );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://hcti.io/v1/image");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        curl_setopt($ch, CURLOPT_POST, 1);
        

        // Retrieve your user_id and api_key from https://htmlcsstoimage.com/dashboard
        curl_setopt($ch, CURLOPT_USERPWD, HTMLTOIMAGE_USERID . ":" . HTMLTOIMAGE_APIKEY);

        $headers = array();
        $headers[] = "Content-Type: application/x-www-form-urlencoded";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
          echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        $res = json_decode($result,true);

        set_transient( $this->userID . '_badge_image_url', $res['url'], MONTH_IN_SECONDS );
        return '<a href="' . $res['url'] . '" download="MakeSignInBadge" target="_blank"><img src="' . $res['url'] . '"/></a>';
      else :
        return '<a href="' . $user_badge_image . '" download="download" target="_blank"><img src="' . $user_badge_image . '"/></a>';
      endif;
    
  }


  public function display_member_resources() {
    $resources = $this->memberResources;


    if($resources) :
      echo '<div id="memberResources" class="row member-resources">';


        // if($this->has_membership()) :
        //   echo '<div class="col-12 col-md-4 mt-5">';
        //     echo '<p class="small text-center mx-5 mb-1">Your member badge. Click to download.</p>';
        //     echo $this->generate_member_badge();
        //   echo '</div>';
        // endif;


        echo '<div class="col-12 col-md">';
          echo '<div class="row">';

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
    if($maker_steps) :
      $count = count($maker_steps);
      $max = 100/$count;
      echo '<div class="progress-container d-flex flex-column flex-md-row mt-3 mb-3">';
      foreach ($maker_steps as $key => $step) :
        echo $this->get_progress_bar($step['label'], $max, 0, $step['complete'], $step['link']);
      endforeach;
      echo '</div>';
    endif;
  }

  private function get_progress_bar($label, $max, $min, $complete, $link) {
    $color = ($complete) ? 'bg-success' : 'bg-danger';
    $icon = ($complete) ? 'fas fa-check-circle' : 'fas fa-times-circle';

    $return = '<div class="progress-item ' . $color . ' flex-grow-1 p-2 text-center border border-white">';
      $return .= ($link) ? '<a class="text-white" href="' . $link . '">' : '';
        $return .= '<span><i class="' . $icon . '"></i> ' . $label . '</span>';
      $return .= ($link) ? '</a>' : '';
    $return .= '</div>';
    return $return;
  }

  private function get_profile_steps() {
    return array(
      'membership' => array(
        'label' => 'Start your membership',
        'complete' => $this->has_membership(),
        'link' => $this->membershipURL
      ),
      'waiver' => array(
        'label' => 'Sign a safety waiver',
        'complete' => $this->has_waiver(),
        'link' => $this->waiverURL
      ),
      'profile' => array(
        'label' => 'Share your profile',
        'complete' => $this->has_profile(),
        'link' => $this->profileURL
      ),
      'badge' => array(
        'label' => 'Get a badge',
        'complete' => $this->has_badge(),
        'link' => $this->badgesURL
      ),
      // 'forum' => array(
      //   'label' => 'Post to the forum',
      //   'complete' => $this->has_forum(),
      //   'link' => $this->forumURL
      // ),
      'workshop' => array(
        'label' => 'Take a workshop',
        'complete' => $this->has_workshop(),
        'link' => $this->workshopsURL
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


  private function has_waiver(){
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
              'key'   => '34',
              'value' => $user_email
          )
      )
    );
    $entries = $form->get_entries( 27, $search_criteria);

    if(count($entries) > 0) {
      update_user_meta( $this->userID, 'waiver_complete', true );
      return true;
    } else {
      update_user_meta( $this->userID, 'waiver_complete', false );
      return false;
    }
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


  private function has_forum(){
    $posts = get_posts(array(
      'post_type' => array('reply', 'topic'),
      'author' => $this->userID
    ));
    if(count($posts) > 0) :
      return true;
    else :
      return false;
    endif;
  }


  function has_workshop() {
    $bought = false;

    // Get all customer orders
    $customer_orders = get_posts( array(
        'numberposts' => -1,
        'meta_key'    => '_customer_user',
        'meta_value'  => $this->userID,
        'post_type'   => 'shop_order', // WC orders post type
        'post_status' => 'wc-completed' // Only orders with status "completed"
    ));
    foreach ( $customer_orders as $customer_order ) {
      // Updated compatibility with WooCommerce 3+
      $order_id = method_exists( $customer_order, 'get_id' ) ? $customer_order->get_id() : $customer_order->id;
      $order = wc_get_order( $customer_order );

      // Iterating through each current customer products bought in the order
      foreach ($order->get_items() as $item) {
          // WC 3+ compatibility
          if ( version_compare( WC_VERSION, '3.0', '<' ) ) :
              $product_id = $item['product_id'];
          else :
              $product_id = $item->get_product_id();
          endif;

          $terms = get_the_terms( $product_id, 'product_cat' );
          if($terms) :
            foreach ($terms as $term) :
              if($this->workshopCategory == $term->slug) :
                $bought = true;
                break;
              endif;
            endforeach;
          endif;
      }
    }
    // return "true" if one the specifics products have been bought before by customer
    return $bought;
  }






}//end of class


add_action('init', 'makesf_start_er_up');
function makesf_start_er_up(){
  new makeProfile();
}
