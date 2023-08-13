<?php


class makeSkillTree {
  private $userID = '';

  

  public function __construct() {
    $this->userID = get_current_user_id();

    
    add_action('woocommerce_before_my_account', array($this, 'display_skilltree'));

    add_action('save_post', array($this, 'export_badges_to_json'));
    
    add_action('wp_footer', array($this, 'enqueueAssets'));

  }


  public function enqueueAssets(){

    // wp_register_style('member-styles', MAKESF_URL . 'css/style.css', array(), MAKESF_PLUGIN_VERSION);
    // wp_enqueue_style('member-styles');

   


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



  public function export_badges_to_json() {
    $badges = new WP_Query(array(
      'post_type' => 'certs',
      'posts_per_page' => -1,
    ));
    if($badges->have_posts()) :
      $return['nodes'] = array();
      while($badges->have_posts()) :
        $badges->the_post();

        $imageID = get_field('badge_image', get_the_id());
        $prerequisites = (get_field('prerequisites', get_the_id()) ? get_field('prerequisites', get_the_id()) : array());
        $requirements = false;
        if($prerequisites) :
          $requirements = array();
          foreach ($prerequisites as $prerequisite) :
            $requirements[] = 'badge-' . $prerequisite;
          endforeach;
        endif;


        // for all badge display we want ['selected'] to always be true, this highlights all nodes. 
        // for member badge view, we want only the badges they've acehvied to be selected.
        $return['nodes'][] = array(
          'depth' => ($requirements ? 1 : 0),
          'imageUrl' => wp_get_attachment_image_url($imageID, 'full', false ),
          'name' => 'badge-' . get_the_ID(),
          'requirements' => (isset($requirements) ? $requirements : array()),
          'selected' => $this->check_member_badge_acheivemewnt(get_the_ID()) 
        );

      endwhile;
      $data = json_encode($return);
      $file_name = $this->get_file_name();
      $save_path = $this->get_save_path();

      $f = fopen($save_path, "w"); //if json file doesn't gets saved, comment this and uncomment the one below
      //$f = @fopen( $save_path , "w" ) or die(print_r(error_get_last(),true)); //if json file doesn't gets saved, uncomment this to check for errors
      fwrite($f, $data);
      fclose($f);
    endif;

  }

  private function check_member_badge_acheivemewnt($badge_id) {
    
    if(is_user_logged_in()) {
      //TODO: Write a function to determine what to show accoridng to notes above
      return (bool)random_int(0, 1);
    } else {
      return true;
    }
    
  }



  public function get_badges_url() {
    $upload_dir = wp_get_upload_dir();
    return $upload_dir['baseurl'] . '/' . $this->get_file_name();
  }




  private function get_file_name() {
    if(is_user_logged_in()) {
      //TODO: each user should hgave their own acheivements in a json file. 
      return 'skillBadges.json';
    } else {
      return 'skillBadges.json';
    }



    
  }
  private function get_save_path() {
    $upload_dir = wp_get_upload_dir(); // set to save in the /wp-content/uploads folder
    return $upload_dir['basedir'] . '/' . $this->get_file_name();

  }


}//end of class




add_action('init', function (){
  new makeSkillTree();
});


