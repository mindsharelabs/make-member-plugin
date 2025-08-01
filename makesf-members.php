<?php
/**
 * Plugin Name: Make Santa Fe Membership Awesomeness
 * Plugin URI:https://mind.sh/are
 * Description: A plugin that drastically improves the Make Santa Fe membership experience
 * Version: 1.4.0
 * Author: Mindshare Labs, Inc
 * Author URI: https://mind.sh/are
 */


class makeMember {
    /**
     * Single instance of the class
     * @var makeMember
     */
    private static $instance = null;
    
    /**
     * Current user ID
     * @var int
     */
    private $userID = 0;

    /**
     * Plugin version
     * @var string
     */
    const VERSION = '1.4.0';

    /**
     * Constructor - private to enforce singleton pattern
     */
    private function __construct() {
        $this->userID = get_current_user_id();
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
     $this->define( 'MAKE_LOGO', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 508 329.3"><defs><style>.cls-1{fill:#000;stroke-width:0px;}</style></defs><path class="cls-1" d="M126,0H0v98.6h49.4v-49.2h76.6V0Z"/><path class="cls-1" d="M382,329.3h126v-98.6h-49.4v49.2h-76.6v49.4Z"/><path class="cls-1" d="M105.4,79.1c1.5,2.7,3.2,6.1,5.1,10.1s4,8.3,6.1,13,4.2,9.4,6.3,14.3c2.1,4.9,4,9.4,5.8,13.7,1.8-4.3,3.8-8.9,5.8-13.7,2.1-4.9,4.1-9.6,6.3-14.3,2.1-4.7,4.1-9,6.1-13,1.9-4,3.7-7.4,5.1-10.1h17.5c.8,5.7,1.6,12,2.3,19.1.7,7,1.3,14.4,1.9,22,.5,7.6,1,15.3,1.5,22.9.5,7.7.8,14.9,1.2,21.6h-18.8c-.2-8.3-.6-17.4-1-27.2s-1-19.7-1.9-29.7c-1.5,3.5-3.1,7.3-5,11.5-1.8,4.2-3.6,8.4-5.4,12.6-1.8,4.2-3.5,8.2-5.1,12s-3.1,7.1-4.2,9.8h-13.5c-1.2-2.7-2.6-6-4.2-9.8s-3.4-7.8-5.1-12c-1.8-4.2-3.6-8.4-5.4-12.6s-3.5-8-5-11.5c-.8,10-1.4,19.9-1.9,29.7-.4,9.8-.7,18.9-1,27.2h-18.7c.3-6.8.7-14,1.2-21.6.5-7.7.9-15.3,1.5-22.9.5-7.6,1.2-15,1.9-22,.7-7,1.5-13.4,2.3-19.1,0,0,18.3,0,18.3,0Z"/><path class="cls-1" d="M251,164.8c-1-3-2-6-3.1-9.2-1.1-3.1-2.2-6.3-3.3-9.4h-33.4c-1.1,3.1-2.2,6.3-3.3,9.4-1.1,3.1-2.1,6.2-3,9.1h-20c3.2-9.2,6.3-17.8,9.2-25.6s5.7-15.2,8.5-22.1,5.5-13.5,8.2-19.7c2.7-6.2,5.5-12.3,8.4-18.2h18.3c2.8,5.9,5.6,12,8.3,18.2s5.5,12.8,8.3,19.7c2.8,6.9,5.6,14.3,8.5,22.1s6,16.4,9.2,25.6h-20.8v.1ZM227.8,98.5c-.4,1.2-1.1,2.9-1.9,5.1-.8,2.1-1.8,4.6-2.8,7.4-1.1,2.8-2.2,5.9-3.5,9.3s-2.6,6.9-4,10.6h24.4c-1.3-3.7-2.6-7.3-3.8-10.6-1.2-3.4-2.4-6.5-3.5-9.3s-2.1-5.3-2.9-7.4c-.8-2.2-1.5-3.9-2-5.1Z"/><path class="cls-1" d="M333.1,164.8c-1.7-2.8-3.8-5.8-6.1-9-2.4-3.2-4.9-6.5-7.6-9.8s-5.6-6.4-8.5-9.5c-3-3-5.9-5.7-8.9-8v36.3h-19.3v-85.7h19.3v32.2c5-5.2,10-10.6,15.1-16.3,5.1-5.7,9.8-11,14.2-15.8h22.7c-5.8,6.9-11.7,13.6-17.6,20s-12.1,12.9-18.6,19.3c6.8,5.7,13.5,12.5,19.8,20.3,6.4,7.8,12.5,16.5,18.4,25.9h-22.9v.1Z"/><path class="cls-1" d="M367.1,164.8v-85.7h57.9v16.2h-38.6v16.8h34.2v15.8h-34.2v20.6h41.4v16.2h-60.7v.1Z"/><path class="cls-1" d="M130,246.1c2.9,0,5.1-.4,6.5-1.2s2.1-2,2.1-3.7-.7-3.1-2.1-4.2c-1.4-1-3.7-2.2-6.8-3.5-1.5-.6-3-1.2-4.4-1.9-1.4-.6-2.6-1.4-3.7-2.3-1-.9-1.8-1.9-2.5-3.2-.6-1.2-.9-2.7-.9-4.5,0-3.5,1.3-6.3,3.9-8.4,2.6-2.1,6.2-3.1,10.7-3.1,1.1,0,2.3.1,3.4.2,1.1.1,2.2.3,3.2.5s1.8.4,2.6.6c.7.2,1.3.4,1.7.5l-1.3,6.2c-.8-.4-2-.8-3.6-1.3-1.6-.4-3.6-.7-5.9-.7-2,0-3.7.4-5.2,1.2s-2.2,2-2.2,3.7c0,.9.2,1.6.5,2.3s.8,1.3,1.5,1.8,1.5,1,2.6,1.5c1,.5,2.3.9,3.7,1.5,1.9.7,3.6,1.4,5.1,2.1,1.5.7,2.8,1.5,3.8,2.4,1.1.9,1.9,2,2.4,3.3.6,1.3.8,2.9.8,4.8,0,3.7-1.4,6.5-4.1,8.4-2.7,1.9-6.7,2.8-11.7,2.8-3.5,0-6.3-.3-8.3-.9-2-.6-3.4-1-4.1-1.3l1.3-6.2c.8.3,2.1.8,3.9,1.4,1.7.9,4.1,1.2,7.1,1.2Z"/><path class="cls-1" d="M167.9,210.3c2.9,0,5.3.4,7.3,1.1,2,.7,3.6,1.8,4.8,3.2s2.1,3,2.6,4.8c.5,1.9.8,3.9.8,6.2v25c-.6.1-1.5.2-2.6.4s-2.3.3-3.7.5-2.9.3-4.5.4c-1.6.1-3.2.2-4.8.2-2.3,0-4.3-.2-6.2-.7s-3.5-1.2-4.9-2.2-2.5-2.3-3.2-4c-.8-1.6-1.2-3.6-1.2-5.9s.4-4.1,1.3-5.7c.9-1.6,2.1-2.9,3.7-3.8,1.5-1,3.3-1.7,5.4-2.2,2-.5,4.2-.7,6.5-.7.7,0,1.5,0,2.2.1.8.1,1.5.2,2.2.3s1.3.2,1.8.3.9.2,1.1.2v-2c0-1.2-.1-2.3-.4-3.5-.3-1.2-.7-2.2-1.4-3.1-.7-.9-1.6-1.6-2.7-2.2-1.2-.5-2.7-.8-4.5-.8-2.4,0-4.4.2-6.2.5s-3.1.7-4,1l-.8-5.9c.9-.4,2.5-.8,4.6-1.2s4.3-.3,6.8-.3ZM168.5,246.1c1.7,0,3.2,0,4.5-.1s2.4-.2,3.3-.4v-11.9c-.5-.3-1.3-.5-2.5-.7s-2.6-.3-4.2-.3c-1.1,0-2.2.1-3.4.2-1.2.2-2.3.5-3.3,1s-1.8,1.2-2.5,2-1,2-1,3.3c0,2.6.8,4.3,2.5,5.3,1.6,1.1,3.8,1.6,6.6,1.6Z"/><path class="cls-1" d="M195.1,212.4c1.6-.4,3.8-.8,6.5-1.3s5.8-.7,9.4-.7c3.2,0,5.8.4,7.9,1.3,2.1.9,3.8,2.2,5,3.8,1.3,1.6,2.1,3.6,2.7,5.8.5,2.3.8,4.7.8,7.5v22.5h-7.2v-20.9c0-2.5-.2-4.6-.5-6.3s-.9-3.2-1.7-4.2c-.8-1.1-1.8-1.9-3.1-2.3-1.3-.5-2.9-.7-4.8-.7-.8,0-1.6,0-2.4.1-.8.1-1.6.1-2.3.2-.7.1-1.4.2-2,.3s-1,.2-1.3.2v33.8h-7.2v-39.1h.2Z"/><path class="cls-1" d="M245.9,211.3h15.1v6h-15.1v18.5c0,2,.2,3.7.5,5s.8,2.3,1.4,3.1c.6.7,1.4,1.3,2.3,1.6.9.3,2,.5,3.2.5,2.2,0,3.9-.2,5.2-.7,1.3-.5,2.2-.8,2.7-1l1.4,5.9c-.7.4-2,.8-3.8,1.3s-3.8.8-6.2.8c-2.7,0-5-.3-6.7-1-1.8-.7-3.2-1.7-4.3-3.1s-1.8-3.1-2.3-5.1c-.4-2-.7-4.4-.7-7v-35.7l7.2-1.2v12.1h.1Z"/><path class="cls-1" d="M283.1,210.3c2.9,0,5.3.4,7.3,1.1,2,.7,3.6,1.8,4.8,3.2,1.2,1.4,2.1,3,2.6,4.8.5,1.9.8,3.9.8,6.2v25c-.6.1-1.5.2-2.6.4s-2.3.3-3.7.5-2.9.3-4.5.4c-1.6.1-3.2.2-4.8.2-2.3,0-4.3-.2-6.2-.7-1.9-.5-3.5-1.2-4.9-2.2s-2.5-2.3-3.2-4c-.8-1.6-1.2-3.6-1.2-5.9s.4-4.1,1.3-5.7c.9-1.6,2.1-2.9,3.7-3.8,1.5-1,3.3-1.7,5.4-2.2,2-.5,4.2-.7,6.5-.7.7,0,1.5,0,2.2.1.8.1,1.5.2,2.2.3s1.3.2,1.8.3.9.2,1.1.2v-2c0-1.2-.1-2.3-.4-3.5s-.7-2.2-1.4-3.1c-.7-.9-1.6-1.6-2.7-2.2-1.2-.5-2.7-.8-4.5-.8-2.4,0-4.4.2-6.2.5-1.8.3-3.1.7-4,1l-.8-5.9c.9-.4,2.5-.8,4.6-1.2,1.9-.1,4.3-.3,6.8-.3ZM283.7,246.1c1.7,0,3.2,0,4.5-.1s2.4-.2,3.3-.4v-11.9c-.5-.3-1.3-.5-2.5-.7s-2.5-.3-4.2-.3c-1.1,0-2.2.1-3.4.2-1.2.2-2.3.5-3.3,1s-1.8,1.2-2.5,2-1,2-1,3.3c0,2.6.8,4.3,2.5,5.3,1.5,1.1,3.8,1.6,6.6,1.6Z"/><path class="cls-1" d="M343.6,191.6c2.1,0,3.9.2,5.4.5s2.6.6,3.2.8l-1.2,6.1c-.6-.3-1.5-.6-2.6-.9-1.1-.3-2.5-.4-4.2-.4-3.3,0-5.7.9-7,2.7s-2,4.3-2,7.3v3.5h15.4v6h-15.4v34h-7.2v-43.6c0-5.1,1.3-9.1,3.8-11.9,2.5-2.7,6.5-4.1,11.8-4.1Z"/><path class="cls-1" d="M355.5,231.3c0-3.5.5-6.6,1.5-9.3,1-2.6,2.4-4.8,4.1-6.6,1.7-1.7,3.6-3,5.8-3.9,2.2-.9,4.5-1.3,6.8-1.3,5.4,0,9.5,1.7,12.4,5,2.9,3.4,4.3,8.5,4.3,15.3v1.2c0,.5,0,.9-.1,1.3h-27.4c.3,4.2,1.5,7.3,3.6,9.5s5.4,3.2,9.8,3.2c2.5,0,4.6-.2,6.3-.7,1.7-.4,3-.9,3.9-1.3l1,6c-.9.5-2.4.9-4.6,1.5-2.2.5-4.7.8-7.4.8-3.5,0-6.5-.5-9-1.6s-4.6-2.5-6.3-4.3-2.9-4-3.7-6.6c-.6-2.4-1-5.1-1-8.2ZM382.9,227.4c0-3.2-.8-5.9-2.4-8-1.7-2.1-4-3.1-6.9-3.1-1.6,0-3.1.3-4.3,1-1.3.6-2.3,1.5-3.2,2.5-.9,1-1.6,2.2-2,3.5-.5,1.3-.8,2.7-1,4.1h19.8Z"/></svg>');

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
    // Configuration system (load first)
    include_once MAKESF_ABSPATH . 'inc/config.php';
    
    //General
    include_once MAKESF_ABSPATH . 'inc/utilities.php';
    include_once MAKESF_ABSPATH . 'inc/options.php';
    include_once MAKESF_ABSPATH . 'inc/sign-in-stats.php';
    include_once MAKESF_ABSPATH . 'inc/profile.class.php';
    include_once MAKESF_ABSPATH . 'inc/blocks.php';
    include_once MAKESF_ABSPATH . 'inc/scripts.php';
    include_once MAKESF_ABSPATH . 'inc/woocommerce.php';

    include_once MAKESF_ABSPATH . 'inc/api-endpoints.php';

    // Performance Optimizations
    include_once MAKESF_ABSPATH . 'inc/member-search-optimization.php';
    include_once MAKESF_ABSPATH . 'inc/performance-admin.php';

    //Volunteer System
    include_once MAKESF_ABSPATH . 'inc/volunteer/volunteer-database.php';
    include_once MAKESF_ABSPATH . 'inc/volunteer/volunteer-cpt.php';
    include_once MAKESF_ABSPATH . 'inc/volunteer/volunteer-functions.php';
    include_once MAKESF_ABSPATH . 'inc/volunteer/volunteer-ajax.php';
    include_once MAKESF_ABSPATH . 'inc/volunteer/volunteer-admin.php';
    include_once MAKESF_ABSPATH . 'inc/volunteer/volunteer-auto-signout.php';

    //Custom Notification Classes
    include_once MAKESF_ABSPATH . 'inc/notifications.class.php';

    include_once MAKESF_ABSPATH . 'inc/userBadge.class.php';

    include_once MAKESF_ABSPATH . 'inc/newsletter/newsletter-blocks.php';
  }






 }//end of class


// Initialize the plugin using singleton pattern
makeMember::get_instance();





function make_install() {
  global $wpdb;
 
  $charset_collate = $wpdb->get_charset_collate();

  // Original sign-in table
  $sql = "CREATE TABLE make_signin (
    id INT NOT NULL AUTO_INCREMENT,
    time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    badges TEXT CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    user INT NOT NULL,
    PRIMARY KEY  (id)
  ) $charset_collate;";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta( $sql );

  // Initialize volunteer system
  make_init_volunteer_system();

}

register_activation_hook( __FILE__, 'make_install' );
