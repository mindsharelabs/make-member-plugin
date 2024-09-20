<?php
class makeMeetupAPI {

	// Your OAuth access token
	private $accessToken = false;
	private $refreshToken = false;

	//this variable holds an array of access credentials
	// {"access_token":"bedb3c90f546c56a60c6a580f9e08c6d","refresh_token":"d617fece842b20e0a1060a29a2125fd3","token_type":"bearer","expires_in":3600}
	private $access_cred = false;
	
	//https://www.meetup.com/api/oauth/list/


	private $clientID = '';
	private $clientSecret = '';

	// The group URL name you are creating an event for
	private $groupURL = '';
 	private $meetup_code = false;
	private $redirect_url = 'http://makesantafe/wp-admin/options-general.php?page=meetup-event-sync';
	



	
	private $eventDetails = array();
	private $eventID = false;


	public function __construct() {

		$options = get_option('meetup_event_sync_options');
		
		$this->clientID = $options['client_id'];
		$this->clientSecret = $options['client_secret'];
		$this->groupUrl = $options['group_urlname'];


		$this->accessToken = get_option('meetup_access_token');
		$this->refreshToken = get_option('meetup_refresh_token');
		$token_expiry = get_option('meetup_token_expiry');

		//if we have a code in the URL, then we need to handle the callback
		if(isset($_GET['code'])) :
			$this->meetup_code = $_GET['code'];
			$this->handle_meetup_callback();
		endif;


		mapi_write_log('Token Expiration:' . ($token_expiry - time()));

		//if we have an access token but the expiration is less than the current time, then we need to refresh the token
		if(isset($_GET['code'])) :
			$this->meetup_code = $_GET['code'];
			$this->handle_meetup_callback();
		elseif($this->refreshToken && ($token_expiry < time())) :
			mapi_write_log('====================REFRESHING TOKEN====================');
			$this->refresh_meetup_token();
		elseif($this->clientID && $this->clientSecret && !$this->refreshToken) :
			mapi_write_log('====================AUTHORIZATION====================');
			$this->get_authorization_code();
		endif;


		//if this is the tribe_events post type, then we want to sync the event to meetup
		if (get_post_type() == 'tribe_events') :
			$this->eventID = get_the_ID();
		else : 
			false;	
		endif;


	}


	public static function get_instance() {
		if ( null === self::$instance ) {
		self::$instance = new self;
		}
		return self::$instance;
	}



	private function get_authorization_code() {
		mapi_write_log('====================AUTHORIZATION====================');
		$authorization_url = 'https://secure.meetup.com/oauth2/authorize';
		$options = get_option('meetup_event_sync_options');
		$params = [
			'client_id' => $options['client_id'],
			'response_type' => 'code',
			'redirect_uri' => $this->redirect_url,
		];
		$authorization_url = add_query_arg($params, $authorization_url);
		mapi_write_log($authorization_url);
		// wp_redirect($authorization_url);
		// exit;
	}

	private function handle_meetup_callback() {
		mapi_write_log('====================HANDLE MEETUP CALLBACK====================');
		delete_option('meetup_code');
		delete_option('meetup_refresh_token');
		add_option('meetup_code', $_GET['code']);
		$token_url = 'https://secure.meetup.com/oauth2/access';
		$body = array(
			'client_id' => $this->clientID,
			'client_secret' => $this->clientSecret,
			'grant_type' => 'authorization_code',
			// 'redirect_uri' => $this->redirect_url,
			'redirect_uri' => 'https://makesantafe.org/',
			'code' => $_GET['code'],
		);

		$response = wp_remote_post($token_url, [
			'body' => $body,
		]);



		if (!is_wp_error($response)) {
			$body = json_decode(wp_remote_retrieve_body($response), true);

			if (isset($body['access_token'])) {
				update_option('meetup_access_token', $body['access_token']);
				update_option('meetup_token_expiry', time() + $body['expires_in']);
				update_option('meetup_refresh_token', $body['refresh_token']);
			}
		}
		
	}

	private function refresh_meetup_token() {
	
		$token_url = 'https://secure.meetup.com/oauth2/access';
		$response = wp_remote_post($token_url, [
			'body' => [
			'client_id' => get_option('meetup_client_id'),
			'client_secret' => get_option('meetup_client_secret'),
			'grant_type' => 'refresh_token',
			'refresh_token' => get_option('meetup_refresh_token'),
			],
		]);

		if (!is_wp_error($response)) {
			$body = json_decode(wp_remote_retrieve_body($response), true);
			mapi_write_log($body);
			if (isset($body['access_token'])) {
				update_option('meetup_access_token', $body['access_token']);
				update_option('meetup_token_expiry', time() + $body['expires_in']);
				update_option('meetup_refresh_token', $body['refresh_token']);
			}
		}
		
	}
	  


	public function  sync_event_to_meetup($post_id) {
		if (get_post_type($post_id) != 'event') return;

		$event_details = $this->get_event_details($post_id);
		$meetup_event_id = get_post_meta($post_id, 'meetup_event_id', true);
		$has_event = $this->check_meetup_event_exists($meetup_event_id);
		$input = array(
			'groupUrlname' => $this->groupUrl,
			'title' => $event_details['title'],
			'description' => $event_details['description'],
			'startDateTime' => $event_details['startDateTime'],
			'duration' => $event_details['duration'],
			'publishStatus' => 'DRAFT'
		);
		if ($meetup_event_id && $has_event) :
			//if the event is on meetup, then update the event
			$query = '
			mutation($input: UpdateEventInput!) {
				updateEvent(input: $input) {
					event {
						id
					}
				}
			}';

			$input['id'] = $meetup_event_id;
		else :
			$query = '
				mutation($input: CreateEventInput!) {
					createEvent(input: $input) {
						event {
							id
						}
					}
				}';
		endif;
	
		$variables = ['input' => $input];
	
		$result = $this->meetup_graphql_request($query, $variables);
		
		mapi_write_log($result);
		if ($result && isset($result['data'])) {
			if (isset($result['data']['createEvent'])) {
				$new_meetup_event_id = $result['data']['createEvent']['event']['id'];
				update_post_meta($post_id, 'meetup_event_id', $new_meetup_event_id);
			} elseif (isset($result['data']['updateEvent'])) {
				// Event was successfully updated
			}
		} else {
			// Handle error
			error_log('Failed to sync event to Meetup: ' . print_r($result, true));
		}
	}



	private function delete_event_from_meetup() {
		if(!$this->eventID) :
			return;
		endif;
		if (get_post_type($this->eventID) != 'tribe_events') :
			return;
		endif;

		$meetup_event_id = get_post_meta($this->eventID, 'meetup_event_id', true);
		if (!$meetup_event_id) return;

		$query = '
		mutation CancelEvent($input: CancelEventInput!) {
			cancelEvent(input: $input) {
				success
			}
		}';

		$variables = [
			'input' => [
				'eventId' => $meetup_event_id,
			]
		];

		$this->meetup_graphql_request($query, $variables);
	}


	private function check_meetup_event_exists($meetup_event_id) {
		$query = '
		query($eventId: ID!) {
			event(id: $eventId) {
				id
				title
				eventUrl
			}
		}';
	
		$variables = [
			'eventId' => $meetup_event_id
		];
	
		$result = meetup_graphql_request($query, $variables);
	
		if (is_wp_error($result)) {
			error_log('Error checking Meetup event: ' . $result->get_error_message());
			return false;
		}
	
		if (isset($result['data']['event']) && $result['data']['event'] !== null) {
			return true; // Event exists
		}
	
		return false; // Event doesn't exist
	}



	private function get_event_details($event_id) {
		$eventDetails = array(
		    'title' => html_entity_decode(get_the_title($event_id)),
		    'description' => get_the_excerpt($event_id),
		    'startDateTime' => $this->get_tribe_event_start_iso8601($event_id), 
			'duration' => $this->get_tribe_event_duration_iso8601($event_id), 
		    // Add other event details as needed
		);
		return $eventDetails;
	}



	private function get_meetup_venues() {
		$query = '
		query($groupUrlname: String!) {
			groupByUrlname(urlname: $groupUrlname) {
				venues {
					id
					name
					address
					city
					state
					country
				}
			}
		}';
	
		$variables = [
			'groupUrlname' => $this->groupUrl,
		];
	
		$result = $this->meetup_graphql_request($query, $variables);
	
		if (is_wp_error($result)) {
			return $result; // Return the WP_Error object if the request failed
		}
	
		if (isset($result['data']['groupByUrlname']['venues'])) {
			return $result['data']['groupByUrlname']['venues'];
		}
	
		return new WP_Error('no_venues', 'No venues found for this group');
	}



	private function meetup_graphql_request($query, $variables = []) {
		$url = 'https://api.meetup.com/gql';
		
		
		$headers = [
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $this->accessToken,
		];
	
		$body = json_encode([
			'query' => $query,
			'variables' => $variables,
		]);
	
		$response = wp_remote_post($url, [
			'headers' => $headers,
			'body' => $body,
		]);
	
		if (is_wp_error($response)) {
			return null;
		}
	
		$body = json_decode(wp_remote_retrieve_body($response), true);
		return $body;
	}



	private function get_tribe_event_start_iso8601($event_id) {
		// Get the event start date and time
		$start_date = tribe_get_start_date($event_id, true, 'Y-m-d H:i:s');
	
		// Convert to DateTime object
		$start = new DateTime($start_date);
	
		// Format the date in ISO 8601
		return $start->format('Y-m-d\TH:i:s');
	}
	private function get_tribe_event_duration_iso8601($event_id) {
		// Get the event start and end dates
		$start_date = tribe_get_start_date($event_id, true, 'Y-m-d H:i:s');
		$end_date = tribe_get_end_date($event_id, true, 'Y-m-d H:i:s');
	
		// Convert to DateTime objects
		$start = new DateTime($start_date);
		$end = new DateTime($end_date);
	
		// Calculate the difference
		$interval = $start->diff($end);
	
		// Build the ISO 8601 duration string
		$duration = 'P';
	
		// Add years, months, days
		if ($interval->y > 0) $duration .= $interval->y . 'Y';
		if ($interval->m > 0) $duration .= $interval->m . 'M';
		if ($interval->d > 0) $duration .= $interval->d . 'D';
	
		// Add time components if there are any
		if ($interval->h > 0 || $interval->i > 0 || $interval->s > 0) {
			$duration .= 'T';
			if ($interval->h > 0) $duration .= $interval->h . 'H';
			if ($interval->i > 0) $duration .= $interval->i . 'M';
			if ($interval->s > 0) $duration .= $interval->s . 'S';
		}
	
		// If the duration is just 'P', it means 0 duration, so return PT0S
		if ($duration === 'P') {
			return 'PT0S';
		}
	
		return $duration;
	}
}





add_action('admin_init', function() {
	$makeMeetupAPI = new makeMeetupAPI();
});

add_action('save_post_tribe_events', function($post_id) {
	$makeMeetupAPI = new makeMeetupAPI();
	$makeMeetupAPI->sync_event_to_meetup($post_id);
});

add_action('before_delete_post', function($post_id, $post) {
	if ( 'tribe_events' !== $post->post_type ) {
        return;
    }
	$makeMeetupAPI = new makeMeetupAPI();
	$makeMeetupAPI->delete_event_from_meetup($post_id);
}, 99, 2);