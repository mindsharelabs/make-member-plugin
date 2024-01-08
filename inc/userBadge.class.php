<?php
class makeUserBadges {

	// Your OAuth access token
	private $badgeID = false;
	


	public function __construct() {
		//Lets set some variables to use throughout this class
		$this->badgeID = get_the_ID();
		

		//add appropriate metaboxes
		mapi_write_log('//add appropriate metaboxes');
		// add_meta_box( string $id, string $title, callable $callback, string|array|WP_Screen $screen = null, string $context = 'advanced', string $priority = 'default', array $callback_args = null )
		add_meta_box(
            'users-with-badge',
            'Users with this Badge',
            array( $this, 'add_metabox'),
            'certs',
        );


	}
	public static function get_instance() {
		if ( null === self::$instance ) {
		self::$instance = new self;
		}
		return self::$instance;
	}



	

	public function add_metabox() {
		$member_ids = $this->make_get_active_member_ids();
		$args = array( 
			'meta_key' => 'certifications', 
			'meta_value' => '"' . $this->badgeID . '"', 
			'meta_compare' => 'LIKE',
			'include' => $member_ids
		);
		$users = new WP_User_Query( $args );
		if ( !empty( $users->get_results() ) ) {
			echo '<div class="user-list-box">';
			echo '<div class="column col-1">';
				echo '<h3>Active users with Badge</h3>';
				echo '<ul>';
				foreach ( $users->get_results() as $user ) {
					echo '<li>';
						echo '<strong>' . $user->display_name . '</strong>: ' . $user->user_email;
					echo '</li>';
				}
				echo '</ul>';
			echo '</div>';	
			echo '<div class="column col-2">';
				echo '<h3>Comma Separated Email Addresses</h3>';
				
				foreach ( $users->get_results() as $user ) {
					echo $user->user_email . ',';
				}
				
			echo '</div>';	
			echo '</div>';	
		} else {
			echo 'No users found.';
		}

	}






	private function make_get_active_member_ids(){
	    global $wpdb;
	    // Getting all User IDs and data for a membership plan
	    $members = $wpdb->get_results( "
	        SELECT DISTINCT um.user_id
	        FROM {$wpdb->prefix}posts AS p
	        LEFT JOIN {$wpdb->prefix}posts AS p2 ON p2.ID = p.post_parent
	        LEFT JOIN {$wpdb->prefix}users AS u ON u.id = p.post_author
	        LEFT JOIN {$wpdb->prefix}usermeta AS um ON u.id = um.user_id
	        WHERE p.post_type = 'wc_user_membership'
	        AND p.post_status IN ('wcm-active')
	        AND p2.post_type = 'wc_membership_plan'
	        LIMIT 999
	    ");

	    if($members) :
	    	$member_ids = array();
	      foreach($members as $member) :
	      	if($member->user_id) :
		        $member_ids[] = $member->user_id;
		    endif;
	      endforeach;
	      return $member_ids;
	    endif;
	   
	}


	



	
}


function init_user_badge_metabox() {
	new makeUserBadges();
}


add_action('wp_loaded', function() {
	if ( is_admin() ) {
		add_action('add_meta_boxes', 'init_user_badge_metabox');
	}
});


