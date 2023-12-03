<?php


use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;

class makeSocialAPI {

	// Your OAuth access token
	private $accessToken = false;
	private $refreshToken = false;

	//this variable holds an array of access credentials
	// {"access_token":"bedb3c90f546c56a60c6a580f9e08c6d","refresh_token":"d617fece842b20e0a1060a29a2125fd3","token_type":"bearer","expires_in":3600}
	private $access_cred = false;
	
	//https://www.meetup.com/api/oauth/list/


	private $clientID = '4ugp5t6sd4p0j6d15m11mahq5q';
	private $clientSecret = '638cmvjjtdh7dqrheu4rmf7cn1';
 	private $meetup_code = false;
	private $redirect_url = 'http://makesantafe.org/';
	



	// The group URL name you are creating an event for
	private $groupUrlName = 'santa-fe-makers';
	private $eventDetails = array();
	private $eventID = '';





	public function __construct() {
		//Lets set some variables to use throughout this class
		$this->meetup_code = get_option('meetup_code');
		$this->refreshToken = get_option('meetup_refresh_token');
		

		add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
		//AJAX Actions
		add_action( 'wp_ajax_' . MAKE_AJAX_PREPEND . 'publishEvent', array( $this, 'publishEvent' ) );

		// add_action( 'wp_ajax_nopriv_' . MAKE_AJAX_PREPEND . 'move_pub_calendar', array( $this, 'move_pub_calendar' ) );
		// add_action( 'wp_ajax_' . MAKE_AJAX_PREPEND . 'move_pub_calendar', array( $this, 'move_pub_calendar' ) );




		//if we have not authorized in an hour (transient expiration), then refresh authorization
		if($access_cred = get_transient('meetup_access')) :
			$this->access_cred = $access_cred;
			$this->accessToken = $access_cred['access_token'];
			$this->refreshToken = $access_cred['refresh_token'];
		else :
			$this->authorize();
		endif;



		//add appropriate scripts
		add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
		

	}
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	private function getAccessToken() {
		return $this->accessToken;
	}
	public static function enqueueAssets() {
	  wp_register_script('social-admin-js', MAKESF_URL . 'assets/js/social-admin.js', array('jquery'), '', true);
	  wp_enqueue_script('social-admin-js');
	  wp_localize_script( 'social-admin-js', 'makeSocialSettings', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
	    'nonce' => wp_create_nonce( 'social-nonce' ),
	  ));
	}


	static function add_meta_boxes() {
		// add_meta_box( string $id, string $title, callable $callback, string|array|WP_Screen $screen = null, string $context = 'advanced', string $priority = 'default', array $callback_args = null )
		
		add_meta_box(
			'social-push',
			'Push Event to Socials',
			array( 'makeSocialAPI', 'add_metabox'),
			'tribe_events',
			'side',
			'high'
		);
	}

	private function authorize() {

		$url = 'https://secure.meetup.com/oauth2/access';
		$params = [
		    'client_id' => $this->clientID,
		    'client_secret' => $this->clientSecret,
		];
		if($this->refreshToken) :
			$params['grant_type'] = 'refresh_token';
			$params['refresh_token'] = $this->refreshToken;
		else :
			$params['grant_type'] = 'authorization_code';
			$params['code'] = $this->meetup_code;
			$params['redirect_uri'] = $this->redirect_url;
		endif;

		// Initialize cURL
		$ch = curl_init($url);

		// Set cURL options
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

		// Execute the request
		$response = curl_exec($ch);
		$response = json_decode($response, true);
		
		// Check for errors
		if (curl_errno($ch)) {
		    mapi_write_log($ch);
		}

		// Close the cURL session
		curl_close($ch);

		if(isset($response['error'])) :
		else :
			set_transient('meetup_access', $response, $response['expires_in']);
			add_option('meetup_refresh_token', $response['refresh_token']);
		endif;

	}

	public static function add_metabox() {
		echo '<div id="socialPushContainer" class="metabox-content">';
			echo '<div class="social-notice"></div>';
		    echo self::buildButton(array(
				'action' => 'publishEvent',
				'destination' => 'meetup',
				'buttonText' => 'Publish event on Meetup.com', 
				'buttonClass' => 'push-event button',
				'buttonID' => 'publishMeetup'
			));
			
		
			echo '<div class="loading"></div>';
		echo '</div>';
	
	
	}

	private static function buildButton($data) {
		$defaults = array(
			'action' => 'publishEvent',
			'destination' => 'meetup',
			'buttonText' => 'Publish event on Meetup.com',
			'buttonClass' => 'push-event button',
			'buttonID' => 'publishMeetup'
		);
		$data = wp_parse_args( $data, $defaults);
		return '<button id="' . $data['buttonID'] . '" data-EventID="' . get_the_id() . '" data-action="' . $data['action'] . '" data-destination="' . $data['destination'] . '" class="' . $data['buttonClass'] . '">' . $data['buttonText'] . '</button>';
		
	}
	function publishEvent() {
		if($_POST['action'] == MAKE_AJAX_PREPEND . 'publishEvent') :
			if(get_post_status($_POST['eventID']) == 'publish') :
				$post = $_POST['eventID'];


				
				$eventDetails = self::getEventDetails($post);
				$accessToken = $this->getAccessToken();
				
				$graphqlMutation = '
					mutation {
					createEvent(input: {
						groupUrlname: "' . $this->groupUrlName . '", 
						title: "' . $eventDetails['title'] . '",
						description: "' . $eventDetails['description'] . '",
						startDateTime: "' . $eventDetails['startTime'] . '",
						duration : "' . $eventDetails['duration'] . '",
						PublishStatus : "DRAFT"
					}) {
						event {
						id
						title
						}
					}
				}';
				$ch = curl_init();
				curl_setopt_array($ch, [
					CURLOPT_URL => 'https://api.meetup.com/gql',
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => json_encode(['query' => $graphqlMutation]),
					CURLOPT_HTTPHEADER => [
						'Authorization: Bearer ' . $accessToken,
						'Content-Type: application/json',
					],
				]);

				try {
					$response = curl_exec($ch);
				
					if (curl_errno($ch)) {
						throw new Exception(curl_error($ch));
					}
				
					$data = json_decode($response, true);
					mapi_write_log('Here is the data:=====================');
					mapi_write_log($data);
					$return = array(
						'success' => true,
						'data' => array(
							'message' => 'Successfully posted a draft to Meetup.com. Please finalize event details on Meetup.com.'
						),
						
					);
					wp_send_json($return);
				
				} catch (Exception $e) {
					mapi_write_log($e);
				} finally {
					curl_close($ch);
				}








			else :
				$return = array(
					'success' => false,
					'data' => array(
						'message' => 'This event is not published. Please publish it before pushing to socials.'
					),
					
				);
				wp_send_json($return);
			endif;	


		endif;
	}



	private static function getEventDetails($eventID) {
		$event = tribe_get_event($eventID, 'ARRAY_A', 'raw', false);

		// mapi_write_log($event);

		$start = $event['dates']->start;
		$end = $event['dates']->end;
		$p = $end->diff($start);
		mapi_write_log($p);
		$duration = 'P';
		$duration .= ($p->y > 0) ? $p->y . 'Y' : '';
		$duration .= ($p->m > 0) ? $p->m . 'M' : '';
		$duration .= ($p->d > 0) ? $p->d . 'D' : '';
		$duration .= 'T';
		$duration .= ($p->h > 0) ? $p->h . 'H' : '';
		$duration .= ($p->i > 0) ? $p->i . 'M' : '';
		$duration .= ($p->s > 0) ? $p->s . 'S' : '';
		mapi_write_log($duration);
		$eventDetails = array(
		    'title' => $event['post_title'],
		    'description' => $event['post_excerpt'],
			'startTime' => $start->format('Y-m-d\TH:i:s'),
		    'duration' => $duration// Add other event details as needed
		);
		mapi_write_log($eventDetails);
		return $eventDetails;
	}


}


if(isset($_GET['code'])) :
	delete_transient('meetup_access');
	delete_option('meetup_code');
	delete_option('meetup_refresh_token');
	add_option('meetup_code', $_GET['code']);
endif;



new makeSocialAPI();

