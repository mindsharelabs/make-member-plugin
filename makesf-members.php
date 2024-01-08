<?php
/**
 * Plugin Name: Make Santa Fe Membership Awesomeness
 * Plugin URI:https://mind.sh/are
 * Description: A plugin that drastically improves the Make Santa Fe membership experience
 * Version: 1.2.0
 * Author: Mindshare Labs, Inc
 * Author URI: https://mind.sh/are
 */


 class makeMember {
   private $userID = '';


   public function __construct() {
     $this->userId = get_current_user_id();
     global $wpdb;
     if ( !defined( 'MAKESF_PLUGIN_FILE' ) ) {
     	define( 'MAKESF_PLUGIN_FILE', __FILE__ );
     }
     //Define all the constants
     $this->define( 'MAKESF_ABSPATH', dirname( MAKESF_PLUGIN_FILE ) . '/' );
     $this->define( 'MAKESF_URL', plugin_dir_url( __FILE__ ));
     $this->define( 'MAKESF_PLUGIN_VERSION', '1.4.0');
     $this->define( 'PLUGIN_DIR', plugin_dir_url( __FILE__ ));
     $this->define( 'SIGNIN_TABLENAME', $wpdb->prefix . 'makesignin');
     $this->define( 'MAKE_AJAX_PREPEND', 'makesantafe_');

     $this->includes();


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
  private function includes() {
    //General
    include_once MAKESF_ABSPATH . 'inc/options.php';
    include_once MAKESF_ABSPATH . 'inc/profile.class.php';
    include_once MAKESF_ABSPATH . 'inc/blocks.php';
    include_once MAKESF_ABSPATH . 'inc/scripts.php';
    include_once MAKESF_ABSPATH . 'inc/tribe-woocommerce.php';
    include_once MAKESF_ABSPATH . 'inc/api-endpoints.php';
    // include_once MAKESF_ABSPATH . 'inc/meetupAPI.class.php';
    include_once MAKESF_ABSPATH . 'inc/userBadge.class.php';
  }






 }//end of class


new makeMember();





function make_install() {
  global $wpdb;
 
  $charset_collate = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE make_signin (
    id INT NOT NULL AUTO_INCREMENT,
    time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    badges TEXT CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    user INT NOT NULL,
    PRIMARY KEY  (id)
  ) $charset_collate;";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta( $sql );

}

register_activation_hook( __FILE__, 'make_install' );
