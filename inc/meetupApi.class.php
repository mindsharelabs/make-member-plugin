<?php
class makeMeetupAPI {

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
		$this->eventDetails = $this->getEventDetails($this->eventID);
		$this->meetup_code = get_option('meetup_code');
		$this->refreshToken = get_option('meetup_refresh_token');
		

		//if we have not authorized in an hour (transient expiration), then refresh authorization
		if($access_cred = get_transient('meetup_access')) :
			$this->access_cred = $access_cred;
			$this->accessToken = $access_cred['access_token'];
			$this->refreshToken = $access_cred['refresh_token'];
		else :
			$this->authorize();
		endif;


		//add appropriate metaboxes
		
		// add_meta_box( string $id, string $title, callable $callback, string|array|WP_Screen $screen = null, string $context = 'advanced', string $priority = 'default', array $callback_args = null )
		add_meta_box(
            'meetup-push',
            'Publish Event',
            array( $this, 'add_metabox'),
            'tribe_events',
            'side',
            'high'
        );


	}
	public static function get_instance() {
		if ( null === self::$instance ) {
		self::$instance = new self;
		}
		return self::$instance;
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
			mapi_write_log($response);
		else :
			mapi_write_log($response);
			set_transient('meetup_access', $response, $response['expires_in']);
			add_option('meetup_refresh_token', $response['refresh_token']);
		endif;

	}

	public function add_metabox() {
		echo '<button class="button">Publish event on Meetup.com</button>';
	}
	public function createMeetupEvent() {
		// Initialize cURL session
		$groupUrlName = $this->groupUrlName;
		$url = 'https://api.meetup.com/' . $groupUrlName . '/events';


		mapi_write_log($url);
		$ch = curl_init($url);

		// Set cURL options
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
		    'Authorization: Bearer ' . $this->accessToken,
		    'Content-Type: application/json',
		]);


		$eventDetails = $this->getEventDetails();
		mapi_write_log($eventDetails);


		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($eventDetails));

		// Execute cURL session and get the response
		$response = curl_exec($ch);

		mapi_write_log($response);

		// Check for errors
		if (curl_errno($ch)) {
		    throw new Exception(curl_error($ch));
		}

		// Close cURL session
		curl_close($ch);

		// Decode the response
		$responseData = json_decode($response, true);
		mapi_write_log($responseData);

		// // Handle the response
		// if (isset($responseData['errors'])) {
		//     // Handle errors
		//     print_r($responseData['errors']);
		// } else {
		//     // Success
		//     echo "Event created successfully!";
		//     print_r($responseData);
		// }

	}



	private function getEventDetails() {
		$eventDetails = array(
		    'name' => 'This is a test event',
		    'description' => 'A description of the event',
		    'time' => strtotime('+1 week') * 1000, // Event time in milliseconds since the epoch
		    // Add other event details as needed
		);
		return $eventDetails;
	}
}


if(isset($_GET['code'])) :
	delete_transient('meetup_access');
	delete_option('meetup_code');
	delete_option('meetup_refresh_token');
	add_option('meetup_code', $_GET['code']);
endif;



function init_meetup_metabox() {
	new makeMeetupAPI();
}

// if ( is_admin() ) {
// 	add_action('add_meta_boxes', 'init_meetup_metabox');
// }

