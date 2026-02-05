

<?php
/**
 * ClickUp Sync (OAuth + Settings UI)
 *
 * One-way sync scaffolding for:
 * - WooCommerce Memberships
 * - Donors (Gravity Forms)
 * - Volunteers (custom)
 * - Class Attendees (Event check-in)
 *
 * This file focuses on OAuth + a basic settings page with Connect / Disconnect / Sync buttons.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Make_ClickUp_Sync' ) ) :

class Make_ClickUp_Sync {
	/**
	 * Option keys.
	 */
	const OPT = 'make_clickup_sync_options';

	/**
	 * Admin page slug.
	 */
	const PAGE_SLUG = 'make-clickup-sync';

	/**
	 * Nonces.
	 */
	const NONCE_CONNECT    = 'make_clickup_connect';
	const NONCE_DISCONNECT = 'make_clickup_disconnect';
	const NONCE_SYNC       = 'make_clickup_sync';
	const NONCE_TEST       = 'make_clickup_test';
	const NONCE_DEBUG      = 'make_clickup_debug';

	/**
	 * Init hooks.
	 */
	public static function init() : void {
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle_oauth_callback' ) );

		// Form handlers.
		add_action( 'admin_post_make_clickup_disconnect', array( __CLASS__, 'handle_disconnect' ) );
		add_action( 'admin_post_make_clickup_sync', array( __CLASS__, 'handle_manual_sync' ) );
		add_action( 'admin_post_make_clickup_test_person', array( __CLASS__, 'handle_test_person' ) );
		add_action( 'admin_post_make_clickup_debug_fields', array( __CLASS__, 'handle_debug_fields' ) );

		// Register option.
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

    private static function defaults() : array {
        return array(
            'people_list_id'                  => '901710628846',
            'donations_list_id'               => '901710651726',
            'event_interactions_list_id'      => '901710651771',
            'volunteer_interactions_list_id'  => '901710651739',
            'instructor_interactions_list_id' => '901710651780',
            'membership_interactions_list_id' => '901710651758',
            'people_email_field_id'           => '8be1e725-2d49-4ac7-a351-3797eabb53d0',
        );
    }

	/**
	 * ClickUp UI list IDs often look like "6-901710628846-1".
	 * The API expects the numeric list id, e.g. "901710628846".
	 */
	private static function normalize_list_id( string $maybe ) : string {
		$maybe = trim( $maybe );
		if ( $maybe === '' ) {
			return '';
		}

		// If it's already all digits, keep it.
		if ( preg_match( '/^\d+$/', $maybe ) ) {
			return $maybe;
		}

		// Extract the longest run of digits; in "6-9017...-1" this yields the list id.
		preg_match_all( '/\d+/', $maybe, $m );
		if ( empty( $m[0] ) ) {
			return '';
		}

		$longest = '';
		foreach ( $m[0] as $candidate ) {
			if ( strlen( $candidate ) > strlen( $longest ) ) {
				$longest = $candidate;
			}
		}
		return $longest;
	}

	/**
	 * Register the option.
	 */
	public static function register_settings() : void {
		register_setting(
			'make_clickup_sync_group',
			self::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_options' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize options.
	 */
	public static function sanitize_options( $raw ) : array {
		$raw = is_array( $raw ) ? $raw : array();

		$opts = self::get_options();
		$opts['client_id']     = isset( $raw['client_id'] ) ? sanitize_text_field( (string) $raw['client_id'] ) : ( $opts['client_id'] ?? '' );
		$opts['client_secret'] = isset( $raw['client_secret'] ) ? sanitize_text_field( (string) $raw['client_secret'] ) : ( $opts['client_secret'] ?? '' );

        $keys = array(
            'people_list_id',
            'donations_list_id',
            'event_interactions_list_id',
            'volunteer_interactions_list_id',
            'instructor_interactions_list_id',
            'membership_interactions_list_id',
            'people_email_field_id',
        );

        foreach ( $keys as $k ) {
            if ( isset( $raw[ $k ] ) ) {
                $val = sanitize_text_field( (string) $raw[ $k ] );
                if ( str_ends_with( $k, '_list_id' ) ) {
                    $val = self::normalize_list_id( $val );
                }
                $opts[ $k ] = $val;
            }
        }

		// Never accept tokens via settings save.
		return $opts;
	}

	/**
	 * Add settings page.
	 */
	public static function register_settings_page() : void {
		add_options_page(
			'ClickUp Sync',
			'ClickUp Sync',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Render settings page.
	 */
	public static function render_settings_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$opts          = self::get_options();
		$is_connected  = self::is_connected();
		$redirect_uri  = esc_url( self::get_redirect_uri() );
		$connect_url   = esc_url( self::get_connect_url() );
		$disconnect_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=make_clickup_disconnect' ),
			self::NONCE_DISCONNECT
		);
		$sync_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=make_clickup_sync' ),
			self::NONCE_SYNC
		);
		$test_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=make_clickup_test_person' ),
			self::NONCE_TEST
		);

		?>
		<div class="wrap">
			<h1>ClickUp Sync</h1>

			<p style="max-width: 760px;">
				This is the authentication + manual sync control panel for your one-way ClickUp CRM sync.
				Field/task mapping and event-driven pushes can be layered in after OAuth is working.
			</p>

			<?php if ( isset( $_GET['make_clickup_notice'] ) ) : ?>
				<?php
					$notice = sanitize_text_field( (string) wp_unslash( $_GET['make_clickup_notice'] ) );
					$level  = ( $notice === 'connected' || $notice === 'synced' || $notice === 'test_person_ok' ) ? 'updated' : 'error';
				?>
				<div class="notice <?php echo esc_attr( $level ); ?>"><p>
					<?php
					switch ( $notice ) {
						case 'connected':
							echo 'ClickUp connected successfully.';
							break;
						case 'disconnected':
							echo 'ClickUp connection removed.';
							break;
						case 'synced':
							echo 'Manual sync triggered.';
							break;
						case 'oauth_error':
							echo 'OAuth error. Please try connecting again.';
							break;
						case 'missing_creds':
							echo 'Please save your ClickUp Client ID and Client Secret first.';
							break;
						case 'test_person_ok':
							echo 'Test succeeded: Person record upserted in ClickUp.';
							break;
						case 'test_person_fail':
							echo 'Test failed: Could not upsert person record. Check error logs.';
							break;
						case 'debug_fail':
							echo 'Debug failed: could not fetch fields. Check error logs.';
							break;
						default:
							echo 'Notice: ' . esc_html( $notice );
							break;
					}
					?>
				</p></div>
			<?php endif; ?>

			<hr />

			<h2>OAuth App Credentials</h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'make_clickup_sync_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="make_clickup_client_id">Client ID</label></th>
						<td>
							<input id="make_clickup_client_id" name="<?php echo esc_attr( self::OPT ); ?>[client_id]" type="text" class="regular-text" value="<?php echo esc_attr( $opts['client_id'] ?? '' ); ?>" autocomplete="off" />
							<p class="description">From your ClickUp OAuth app.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="make_clickup_client_secret">Client Secret</label></th>
						<td>
							<input id="make_clickup_client_secret" name="<?php echo esc_attr( self::OPT ); ?>[client_secret]" type="password" class="regular-text" value="<?php echo esc_attr( $opts['client_secret'] ?? '' ); ?>" autocomplete="new-password" />
							<p class="description">Keep this secret. Stored in WordPress options.</p>
						</td>
					</tr>
				</table>

                <h2>ClickUp IDs</h2>
                <p class="description">Tip: you can paste the full ClickUp UI list id (e.g. <code>6-901710628846-1</code>) and it will be normalized automatically.</p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="make_clickup_people_list_id">People List ID</label></th>
                        <td><input id="make_clickup_people_list_id" name="<?php echo esc_attr( self::OPT ); ?>[people_list_id]" type="text" class="regular-text" value="<?php echo esc_attr( $opts['people_list_id'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="make_clickup_people_email_field_id">People Email Field ID</label></th>
                        <td><input id="make_clickup_people_email_field_id" name="<?php echo esc_attr( self::OPT ); ?>[people_email_field_id]" type="text" class="regular-text" value="<?php echo esc_attr( $opts['people_email_field_id'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="make_clickup_donations_list_id">Donations List ID</label></th>
                        <td><input id="make_clickup_donations_list_id" name="<?php echo esc_attr( self::OPT ); ?>[donations_list_id]" type="text" class="regular-text" value="<?php echo esc_attr( $opts['donations_list_id'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="make_clickup_event_interactions_list_id">Event Interactions List ID</label></th>
                        <td><input id="make_clickup_event_interactions_list_id" name="<?php echo esc_attr( self::OPT ); ?>[event_interactions_list_id]" type="text" class="regular-text" value="<?php echo esc_attr( $opts['event_interactions_list_id'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="make_clickup_volunteer_interactions_list_id">Volunteer Interactions List ID</label></th>
                        <td><input id="make_clickup_volunteer_interactions_list_id" name="<?php echo esc_attr( self::OPT ); ?>[volunteer_interactions_list_id]" type="text" class="regular-text" value="<?php echo esc_attr( $opts['volunteer_interactions_list_id'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="make_clickup_instructor_interactions_list_id">Instructor Interactions List ID</label></th>
                        <td><input id="make_clickup_instructor_interactions_list_id" name="<?php echo esc_attr( self::OPT ); ?>[instructor_interactions_list_id]" type="text" class="regular-text" value="<?php echo esc_attr( $opts['instructor_interactions_list_id'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="make_clickup_membership_interactions_list_id">Membership Interactions List ID</label></th>
                        <td><input id="make_clickup_membership_interactions_list_id" name="<?php echo esc_attr( self::OPT ); ?>[membership_interactions_list_id]" type="text" class="regular-text" value="<?php echo esc_attr( $opts['membership_interactions_list_id'] ?? '' ); ?>" /></td>
                    </tr>
                </table>

				<?php submit_button( 'Save Credentials' ); ?>
			</form>

			<hr />

			<h2>Connection</h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Redirect URI</th>
					<td>
						<code><?php echo $redirect_uri; ?></code>
						<p class="description">Add this exact URL to your ClickUp OAuth app’s redirect URLs.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Status</th>
					<td>
						<?php if ( $is_connected ) : ?>
							<strong style="color: #1d7f1d;">Connected</strong>
						<?php else : ?>
							<strong style="color: #a00;">Not connected</strong>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p>
				<?php if ( ! $is_connected ) : ?>
					<a class="button button-primary" href="<?php echo $connect_url; ?>">Connect to ClickUp</a>
				<?php else : ?>
					<a class="button" href="<?php echo esc_url( $disconnect_url ); ?>" onclick="return confirm('Disconnect ClickUp? This will remove the stored access token.');">Disconnect</a>
				<?php endif; ?>
			</p>

			<hr />

			<h2>Manual Sync</h2>
			<p>
				<a class="button" href="<?php echo esc_url( $sync_url ); ?>" <?php echo $is_connected ? '' : 'disabled="disabled"'; ?>>Sync Now</a>
				<?php if ( ! $is_connected ) : ?>
					<span class="description" style="margin-left: 10px;">Connect ClickUp to enable sync.</span>
				<?php endif; ?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( $test_url ); ?>" <?php echo $is_connected ? '' : 'disabled="disabled"'; ?>>Test: Upsert Person</a>
				<span class="description" style="margin-left: 10px;">Creates/updates a test person in your People list.</span>
			</p>
			<p class="description" style="max-width: 760px;">
				For now this just triggers a placeholder hook you can wire up to your membership / donor / volunteer / attendee pushes.
			</p>

			<hr />

			<h2>Debug</h2>
			<p class="description" style="max-width: 760px;">
				Use this to dump the custom fields for a configured list. This helps you capture field IDs and dropdown option IDs for mapping.
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="make_clickup_debug_fields" />
				<?php wp_nonce_field( self::NONCE_DEBUG ); ?>

				<label for="make_clickup_debug_list" style="display:block; margin-bottom:6px; font-weight:600;">Select List</label>
				<select id="make_clickup_debug_list" name="debug_list" style="min-width: 320px;">
					<option value="people">People</option>
					<option value="donations">Donations</option>
					<option value="event_interactions">Event Interactions</option>
					<option value="volunteer_interactions">Volunteer Interactions</option>
					<option value="instructor_interactions">Instructor Interactions</option>
					<option value="membership_interactions">Membership Interactions</option>
				</select>

				<?php submit_button( 'Dump Custom Fields', 'secondary', 'submit', false ); ?>
			</form>

			<?php if ( isset( $_GET['make_clickup_debug'] ) ) : ?>
				<?php
					$debug = base64_decode( (string) wp_unslash( $_GET['make_clickup_debug'] ) );
					$debug = is_string( $debug ) ? $debug : '';
				?>
				<h3 style="margin-top: 20px;">Debug Output</h3>
				<p class="description">Copy/paste this JSON somewhere safe. It includes field IDs and (for dropdowns) option IDs.</p>
				<textarea readonly rows="16" style="width:100%; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;"><?php echo esc_textarea( $debug ); ?></textarea>
			<?php endif; ?>

		</div>
		<?php
	}

    /**
	 * Test handler: upsert a Person record end-to-end.
	 */
	public static function handle_test_person() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}

		check_admin_referer( self::NONCE_TEST );

		if ( ! self::is_connected() ) {
			self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'oauth_error' ) );
		}

		// Local dev domains may not have a TLD; ClickUp validates emails strictly.
		// Use a fixed valid email for the test record.
		$email = 'test@test.com';

		$people = make_clickup_people();
		if ( is_wp_error( $people ) ) {
			error_log( 'ClickUp test: people factory error: ' . $people->get_error_message() );
			self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'test_person_fail' ) );
		}

		$res = $people->upsert(
			array(
				'email'       => $email,
				'first_name'  => 'ClickUp',
				'last_name'   => 'Test',
				'description' => 'Automated test record created by Make ClickUp Sync on ' . gmdate( 'c' ),
			)
		);

		if ( is_wp_error( $res ) ) {
			error_log( 'ClickUp test: upsert error: ' . $res->get_error_message() );
			self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'test_person_fail' ) );
		}

		self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'test_person_ok' ) );
	}

	/**
	 * Build redirect URI (this page).
	 */
	public static function get_redirect_uri() : string {
		return admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Build ClickUp authorization URL.
	 */
	public static function get_connect_url() : string {
		$opts = self::get_options();
		$client_id = isset( $opts['client_id'] ) ? (string) $opts['client_id'] : '';
		if ( $client_id === '' ) {
			// Fall back to settings page; notice handled there.
			return add_query_arg( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'missing_creds' ), admin_url( 'options-general.php' ) );
		}

		$state = wp_create_nonce( self::NONCE_CONNECT );

		return add_query_arg(
			array(
				'client_id'    => rawurlencode( $client_id ),
				'redirect_uri' => rawurlencode( self::get_redirect_uri() ),
				'state'        => rawurlencode( $state ),
			),
			'https://app.clickup.com/api'
		);
	}

	/**
	 * OAuth callback handler.
	 *
	 * ClickUp redirects to redirect_uri with: ?code=...&state=...
	 */
	public static function maybe_handle_oauth_callback() : void {
		if ( ! is_admin() ) {
			return;
		}

		// Only handle on our page.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== self::PAGE_SLUG ) {
			return;
		}

		// Only when OAuth params are present.
		if ( empty( $_GET['code'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$code  = sanitize_text_field( (string) wp_unslash( $_GET['code'] ) );
		$state = isset( $_GET['state'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['state'] ) ) : '';

		if ( ! wp_verify_nonce( $state, self::NONCE_CONNECT ) ) {
			self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'oauth_error' ) );
		}

		$token = self::exchange_code_for_token( $code );
		if ( is_wp_error( $token ) ) {
			self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'oauth_error' ) );
		}

		$opts = self::get_options();
		$opts['access_token'] = $token;
		$opts['connected_at'] = time();

		update_option( self::OPT, $opts, false );

		self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'connected' ) );
	}

	/**
	 * Exchange authorization code for an access token.
	 */
	private static function exchange_code_for_token( string $code ) {
		$opts = self::get_options();
		$client_id     = isset( $opts['client_id'] ) ? (string) $opts['client_id'] : '';
		$client_secret = isset( $opts['client_secret'] ) ? (string) $opts['client_secret'] : '';

		if ( $client_id === '' || $client_secret === '' ) {
			return new WP_Error( 'make_clickup_missing_creds', 'Missing ClickUp client credentials.' );
		}

		$endpoint = 'https://api.clickup.com/api/v2/oauth/token';

		// ClickUp docs specify client_id, client_secret, and code.
		// We send JSON (wp_remote_post will set appropriate headers); ClickUp accepts this in many SDK examples.
		$args = array(
			'timeout' => 20,
			'headers' => array(
				'Content-Type' => 'application/json; charset=utf-8',
			),
			'body'    => wp_json_encode(
				array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
				)
			),
		);

		$response = wp_remote_post( $endpoint, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code_http = (int) wp_remote_retrieve_response_code( $response );
		$body      = (string) wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( $code_http < 200 || $code_http >= 300 ) {
			return new WP_Error( 'make_clickup_token_http_error', 'Token request failed: ' . $code_http );
		}

		if ( ! is_array( $data ) || empty( $data['access_token'] ) ) {
			return new WP_Error( 'make_clickup_token_parse_error', 'Token response missing access_token.' );
		}

		return sanitize_text_field( (string) $data['access_token'] );
	}

	/**
	 * Disconnect (delete token).
	 */
	public static function handle_disconnect() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}

		check_admin_referer( self::NONCE_DISCONNECT );

		$opts = self::get_options();
		unset( $opts['access_token'], $opts['connected_at'] );
		update_option( self::OPT, $opts, false );

		self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'disconnected' ) );
	}

	/**
	 * Manual sync handler (placeholder).
	 */
	public static function handle_manual_sync() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}

		check_admin_referer( self::NONCE_SYNC );

		if ( ! self::is_connected() ) {
			self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'oauth_error' ) );
		}

		/**
		 * Hook for your actual sync implementation.
		 *
		 * You can attach your membership/donor/volunteer/attendee push logic here.
		 */
		do_action( 'make_clickup_sync_now', self::get_access_token() );

		self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'synced' ) );
	}

	/**
	 * Options.
	 */
	public static function get_options() : array {
        $opts = get_option( self::OPT, array() );
        $opts = is_array( $opts ) ? $opts : array();
        return array_merge( self::defaults(), $opts );
    }

	/**
	 * Access token.
	 */
	public static function get_access_token() : string {
		$opts = self::get_options();
		return isset( $opts['access_token'] ) ? (string) $opts['access_token'] : '';
	}

	/**
	 * Connected?
	 */
	public static function is_connected() : bool {
		$token = self::get_access_token();
		return $token !== '';
	}

	/**
	 * Safe redirect back to settings page.
	 */
	private static function safe_redirect( array $args ) : void {
		$url = add_query_arg( $args, admin_url( 'options-general.php' ) );
		wp_safe_redirect( $url );
		exit;
	}


/**
	 * Debug handler: Dump custom fields for a selected list.
	 */
	public static function handle_debug_fields() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}

		check_admin_referer( self::NONCE_DEBUG );

		if ( ! self::is_connected() ) {
			self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'oauth_error' ) );
		}

		$which = isset( $_POST['debug_list'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['debug_list'] ) ) : 'people';
		$opts  = self::get_options();

		$map = array(
			'people'                 => $opts['people_list_id'] ?? '',
			'donations'              => $opts['donations_list_id'] ?? '',
			'event_interactions'     => $opts['event_interactions_list_id'] ?? '',
			'volunteer_interactions' => $opts['volunteer_interactions_list_id'] ?? '',
			'instructor_interactions'=> $opts['instructor_interactions_list_id'] ?? '',
			'membership_interactions'=> $opts['membership_interactions_list_id'] ?? '',
		);

		$list_id = isset( $map[ $which ] ) ? (string) $map[ $which ] : '';
		$list_id = self::normalize_list_id( $list_id );

		if ( $list_id === '' ) {
			error_log( 'ClickUp debug: missing list id for ' . $which );
			self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'debug_fail' ) );
		}

		$client = make_clickup_client();
		if ( is_wp_error( $client ) ) {
			error_log( 'ClickUp debug: client error: ' . $client->get_error_message() );
			self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'debug_fail' ) );
		}

		$res = $client->request( 'GET', '/list/' . rawurlencode( $list_id ) . '/field' );
		if ( is_wp_error( $res ) ) {
			error_log( 'ClickUp debug: fetch error: ' . $res->get_error_message() );
			self::safe_redirect( array( 'page' => self::PAGE_SLUG, 'make_clickup_notice' => 'debug_fail' ) );
		}

		$out = array(
			'list'   => $which,
			'list_id'=> $list_id,
			'fields' => $res,
		);

		$json = wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$enc  = base64_encode( (string) $json );

		// Redirect back with payload.
		$url = add_query_arg(
			array(
				'page'               => self::PAGE_SLUG,
				'make_clickup_debug' => rawurlencode( $enc ),
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

}

endif;

if ( ! class_exists( 'Make_ClickUp_Donations' ) ) :

class Make_ClickUp_Donations {
	/** @var Make_ClickUp_Client */
	private $client;

	/** @var string */
	private $donations_list_id;

	/**
	 * Field IDs (from your Donations list dump).
	 */
	private $field_person_id           = '1650255d-c067-45b3-b61a-7844fee0ca00';
	private $field_board_attr_id       = '1952ee0e-5c7f-441f-83bf-de2a9b148b45';
	private $field_type_id             = '1def4680-a720-467a-9420-dcbaaea00275';
	private $field_source_id           = '2fd48063-da90-490a-95ff-9e7422c5c219';
	private $field_donation_date_id    = 'a98dc006-dd16-411e-8c81-c33c5029c100';
	private $field_amount_id           = 'ab0fe21c-c365-4fac-8b96-8292a5df423d';
	private $field_program_id          = 'd71bce74-ce29-4325-8da7-5d825520c631';

	/**
	 * Dropdown option IDs (name => option_id).
	 */
	private $type_options = array(
		'Operating'             => 'd651a18e-3ae8-4211-b3b1-4a497ef6db5c',
		'In-Kind'               => 'a303eb00-dba1-4acc-86ed-a735a2ec24c3',
		'Sponsorship'           => '91f3fb8b-7473-40b7-898a-2a5669efce89',
		'Board - Personal Gift' => 'd8b7eaee-96c3-495d-b37d-4a0a87a8ed04',
		'Board - Solicited Gift'=> '47b9a1e2-d8a9-4a42-bffa-4eac4d798c13',
	);

	private $program_options = array(
		'Sponsorship'         => 'e47e0bf3-8344-4f8d-b787-1c80ad1ec2db',
		'Classes & Workshops' => '3a7542df-2ca5-4440-a92f-3ed6b6deaaf3',
		'Tools & Equipment'   => '9a191c29-c34d-475b-b2f5-bf45a60376b2',
		'General Operation'   => 'f9d1c048-d035-4758-bda2-dce3adfafb48',
	);

	public function __construct( Make_ClickUp_Client $client, string $donations_list_id ) {
		$this->client            = $client;
		$this->donations_list_id  = preg_replace( '/\D+/', '', $donations_list_id );
		if ( $this->donations_list_id === '' ) {
			$this->donations_list_id = $donations_list_id;
		}
	}

	/**
	 * Record a donation interaction.
	 *
	 * Creates a task in the Donations list and sets custom fields.
	 *
	 * @param string $person_task_id ClickUp task ID from the People list.
	 * @param array $donation {
	 *   @type float|int|string $amount (required)
	 *   @type int|string $date Unix seconds, ms, or a date string parseable by strtotime(). (optional)
	 *   @type string $program Program name or option id (optional)
	 *   @type string $type Type of Donation name or option id (optional)
	 *   @type string $source Source text (optional)
	 *   @type string $board_attribution_task_id ClickUp People task id (optional)
	 *   @type string $name Task name override (optional)
	 * }
	 *
	 * @return array|WP_Error
	 */
	public function record( string $person_task_id, array $donation ) {
		$person_task_id = trim( $person_task_id );
		if ( $person_task_id === '' ) {
			return new WP_Error( 'make_clickup_missing_person_task_id', 'Donation record requires a People task ID.' );
		}

		$amount = isset( $donation['amount'] ) ? $donation['amount'] : null;
		if ( $amount === null || $amount === '' ) {
			return new WP_Error( 'make_clickup_missing_amount', 'Donation record requires an amount.' );
		}
		$amount_num = (float) $amount;

		$date_ms = $this->to_unix_ms( $donation['date'] ?? null );

		$name = isset( $donation['name'] ) ? trim( (string) $donation['name'] ) : '';
		if ( $name === '' ) {
			// Example: "Donation $50.00" (date kept in custom field)
			$name = 'Donation $' . number_format( $amount_num, 2, '.', '' );
		}

		$created = $this->client->create_task(
			$this->donations_list_id,
			array(
				'name'        => $name,
				'description' => '',
			)
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$task_id = isset( $created['id'] ) ? (string) $created['id'] : '';
		if ( $task_id === '' ) {
			return new WP_Error( 'make_clickup_create_missing_id', 'Created donation task response missing ID.' );
		}

		// Person relationship.
		$set = $this->client->set_custom_field_value(
			$task_id,
			$this->field_person_id,
			array( 'add' => array( $person_task_id ), 'rem' => array() )
		);
		if ( is_wp_error( $set ) ) {
			return $set;
		}

		// Board Attribution relationship (optional).
		if ( ! empty( $donation['board_attribution_task_id'] ) ) {
			$board_id = trim( (string) $donation['board_attribution_task_id'] );
			if ( $board_id !== '' ) {
				$set = $this->client->set_custom_field_value(
					$task_id,
					$this->field_board_attr_id,
					array( 'add' => array( $board_id ), 'rem' => array() )
				);
				if ( is_wp_error( $set ) ) {
					return $set;
				}
			}
		}

		// Donation Amount (currency) – ClickUp expects a number.
		$set = $this->client->set_custom_field_value( $task_id, $this->field_amount_id, $amount_num );
		if ( is_wp_error( $set ) ) {
			return $set;
		}

		// Donation Date (date) – Unix ms.
		if ( $date_ms !== null ) {
			$set = $this->client->set_custom_field_value( $task_id, $this->field_donation_date_id, $date_ms );
			if ( is_wp_error( $set ) ) {
				return $set;
			}
		}

		// Source (short text).
		if ( isset( $donation['source'] ) && (string) $donation['source'] !== '' ) {
			$set = $this->client->set_custom_field_value( $task_id, $this->field_source_id, (string) $donation['source'] );
			if ( is_wp_error( $set ) ) {
				return $set;
			}
		}

		// Program (dropdown).
		if ( ! empty( $donation['program'] ) ) {
			$program_opt = $this->resolve_dropdown_option_id( (string) $donation['program'], $this->program_options );
			if ( $program_opt !== '' ) {
				$set = $this->client->set_custom_field_value( $task_id, $this->field_program_id, $program_opt );
				if ( is_wp_error( $set ) ) {
					return $set;
				}
			}
		}

		// Type of Donation (dropdown).
		if ( ! empty( $donation['type'] ) ) {
			$type_opt = $this->resolve_dropdown_option_id( (string) $donation['type'], $this->type_options );
			if ( $type_opt !== '' ) {
				$set = $this->client->set_custom_field_value( $task_id, $this->field_type_id, $type_opt );
				if ( is_wp_error( $set ) ) {
					return $set;
				}
			}
		}

		return $created;
	}

	/**
	 * Convert various date inputs to unix milliseconds.
	 *
	 * @param mixed $date
	 * @return int|null
	 */
	private function to_unix_ms( $date ) {
		if ( $date === null || $date === '' ) {
			return null;
		}

		// If already numeric: treat >= 10^12 as ms, else seconds.
		if ( is_numeric( $date ) ) {
			$val = (int) $date;
			if ( $val > 100000000000 ) { // ~ 1973 in ms
				return $val;
			}
			return $val * 1000;
		}

		$ts = strtotime( (string) $date );
		if ( ! $ts ) {
			return null;
		}
		return (int) $ts * 1000;
	}

	/**
	 * Accept either an option id or a human-readable name.
	 */
	private function resolve_dropdown_option_id( string $value, array $map ) : string {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}

		// If they passed an ID directly, accept it.
		if ( preg_match( '/^[0-9a-fA-F\-]{16,}$/', $value ) ) {
			return $value;
		}

		// Exact match.
		if ( isset( $map[ $value ] ) ) {
			return (string) $map[ $value ];
		}

		// Case-insensitive match.
		foreach ( $map as $k => $id ) {
			if ( strtolower( (string) $k ) === strtolower( $value ) ) {
				return (string) $id;
			}
		}

		return '';
	}
}

endif;

// Boot.
Make_ClickUp_Sync::init();



/**
 * -----------------------------------------------------------------------------
 * ClickUp API Abstractions (Client + People Repository)
 * -----------------------------------------------------------------------------
 *
 * These are intentionally small, composable building blocks:
 * - Make_ClickUp_Client: low-level HTTP wrapper
 * - Make_ClickUp_People: upsert/find logic for your People list
 *
 * Next abstractions you’ll likely add:
 * - Make_ClickUp_Interactions (Donations / Events / Volunteers / Membership)
 */

if ( ! class_exists( 'Make_ClickUp_Client' ) ) :

class Make_ClickUp_Client {
	/** @var string */
	private $access_token;

	/** @var string */
	private $base_url = 'https://api.clickup.com/api/v2';

	public function __construct( string $access_token ) {
		$this->access_token = $access_token;
	}

	/**
	 * Perform an HTTP request to ClickUp.
	 *
	 * @param string $method GET|POST|PUT|DELETE
	 * @param string $path   Path starting with '/'
	 * @param array  $query  Query params
	 * @param array|null $body JSON body
	 *
	 * @return array|WP_Error Decoded JSON array
	 */
	public function request( string $method, string $path, array $query = array(), $body = null ) {
		$method = strtoupper( $method );
		$url    = $this->base_url . $path;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Authorization' => $this->access_token,
				'Content-Type'  => 'application/json; charset=utf-8',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$err_msg = 'ClickUp API error (' . $code . ').';
			if ( is_array( $data ) ) {
				// ClickUp often returns {"err":"...","ECODE":"..."}
				if ( ! empty( $data['err'] ) ) {
					$err_msg .= ' ' . (string) $data['err'];
				} elseif ( ! empty( $data['error'] ) ) {
					$err_msg .= ' ' . (string) $data['error'];
				}
			}
			return new WP_Error( 'make_clickup_http_' . $code, $err_msg, array( 'status' => $code, 'body' => $raw ) );
		}

		if ( null === $data && $raw !== '' ) {
			return new WP_Error( 'make_clickup_bad_json', 'ClickUp API returned invalid JSON.', array( 'body' => $raw ) );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Create a task in a list.
	 *
	 * https://developer.clickup.com/reference/createtask
	 */
	public function create_task( string $list_id, array $payload ) {
		return $this->request( 'POST', '/list/' . rawurlencode( $list_id ) . '/task', array(), $payload );
	}

	/**
	 * Update a task.
	 *
	 * Note: Custom Fields must be updated via the Set Custom Field Value endpoint.
	 * https://developer.clickup.com/reference/updatetask
	 */
	public function update_task( string $task_id, array $payload ) {
		return $this->request( 'PUT', '/task/' . rawurlencode( $task_id ), array(), $payload );
	}

	/**
	 * Get a task.
	 * https://developer.clickup.com/reference/gettask
	 */
	public function get_task( string $task_id, array $query = array() ) {
		return $this->request( 'GET', '/task/' . rawurlencode( $task_id ), $query );
	}

	/**
	 * Set a Custom Field Value.
	 * https://developer.clickup.com/reference/setcustomfieldvalue
	 */
	public function set_custom_field_value( string $task_id, string $field_id, $value ) {
		return $this->request(
			'POST',
			'/task/' . rawurlencode( $task_id ) . '/field/' . rawurlencode( $field_id ),
			array(),
			array( 'value' => $value )
		);
	}

	/**
	 * Get tasks in a list (paged).
	 * https://developer.clickup.com/reference/gettasks
	 */
	public function get_tasks( string $list_id, array $query = array() ) {
		return $this->request( 'GET', '/list/' . rawurlencode( $list_id ) . '/task', $query );
	}
}

endif;





if ( ! class_exists( 'Make_ClickUp_People' ) ) :

class Make_ClickUp_People {
	/**
	 * Lightweight local index for email -> ClickUp task ID.
	 *
	 * This avoids expensive list scans and ClickUp API limitations around searching by title.
	 * You can rebuild this later or switch to a custom_field filter once you decide your exact field IDs.
	 */
	const OPT_INDEX = 'make_clickup_people_index';

	/** @var Make_ClickUp_Client */
	private $client;

	/** @var string */
	private $people_list_id;

	/** @var string */
	private $email_field_id;

	/**
	 * @param Make_ClickUp_Client $client
	 * @param string $people_list_id ClickUp List ID for People
	 * @param string $email_field_id Custom Field UUID for the People list's Email field
	 */
	public function __construct( Make_ClickUp_Client $client, string $people_list_id, string $email_field_id ) {
		$this->client         = $client;
		$this->people_list_id = preg_replace( '/\D+/', '', $people_list_id );
		if ( $this->people_list_id === '' ) {
			$this->people_list_id = $people_list_id;
		}
		$this->email_field_id = $email_field_id;
	}

	/**
	 * Upsert a person record.
	 *
	 * Minimal v1 behavior:
	 * - If clickup_task_id is provided, update that task.
	 * - Else, try local email index.
	 * - Else, create a new People task and set Email custom field.
	 *
	 * @param array $person {
	 *   @type string $email (required)
	 *   @type string $first_name
	 *   @type string $last_name
	 *   @type string $clickup_task_id
	 *   @type string $description
	 * }
	 *
	 * @return array|WP_Error The ClickUp task payload
	 */
	public function upsert( array $person ) {
		$email = isset( $person['email'] ) ? strtolower( trim( (string) $person['email'] ) ) : '';
		if ( $email === '' ) {
			return new WP_Error( 'make_clickup_missing_email', 'Person upsert requires an email.' );
		}

		$task_id = isset( $person['clickup_task_id'] ) ? trim( (string) $person['clickup_task_id'] ) : '';
		if ( $task_id === '' ) {
			$task_id = $this->lookup_task_id_by_email( $email );
		}

		$name = $this->build_person_task_name( $person, $email );

		if ( $task_id !== '' ) {
			$payload = array(
				'name'        => $name,
				'description' => isset( $person['description'] ) ? (string) $person['description'] : '',
			);

			$updated = $this->client->update_task( $task_id, $payload );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}

			// Ensure the email field stays correct.
			$set = $this->client->set_custom_field_value( (string) $task_id, $this->email_field_id, $email );
			if ( is_wp_error( $set ) ) {
				return $set;
			}

			$this->store_email_index( $email, (string) $task_id );
			return $updated;
		}
       
		// Create new person task.
		$created = $this->client->create_task(
			$this->people_list_id,
			array(
				'name'        => $name,
				'description' => isset( $person['description'] ) ? (string) $person['description'] : '',
			)
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$new_task_id = isset( $created['id'] ) ? (string) $created['id'] : '';
		if ( $new_task_id === '' ) {
			return new WP_Error( 'make_clickup_create_missing_id', 'Created task response missing ID.' );
		}

		$set = $this->client->set_custom_field_value( $new_task_id, $this->email_field_id, $email );
		if ( is_wp_error( $set ) ) {
			return $set;
		}

		$this->store_email_index( $email, $new_task_id );
		return $created;
	}

	/**
	 * Build the ClickUp task name for a person.
	 */
	private function build_person_task_name( array $person, string $email ) : string {
		$first = isset( $person['first_name'] ) ? trim( (string) $person['first_name'] ) : '';
		$last  = isset( $person['last_name'] ) ? trim( (string) $person['last_name'] ) : '';

		if ( $first !== '' && $last !== '' ) {
			return $last . ', ' . $first;
		}
		if ( $last !== '' ) {
			return $last;
		}
		if ( $first !== '' ) {
			return $first;
		}

		return $email;
	}

	private function lookup_task_id_by_email( string $email ) : string {
        $index = get_option( self::OPT_INDEX, array() );
        $index = is_array( $index ) ? $index : array();

        if ( isset( $index[ $email ] ) ) {
            return (string) $index[ $email ];
        }

        // Fallback: query ClickUp by custom field filter.
        $found = $this->find_task_id_by_email_remote( $email );
        if ( $found !== '' ) {
            $this->store_email_index( $email, $found );
        }
        return $found;
    }

    private function find_task_id_by_email_remote( string $email ) : string {
        $custom_fields = wp_json_encode( array(
            array(
                'field_id' => $this->email_field_id,
                'operator' => '==',
                'value'    => $email,
            ),
        ) );

        $res = $this->client->get_tasks( $this->people_list_id, array(
            'include_closed' => 'true',
            'custom_fields'  => rawurlencode( $custom_fields ),
            'page'           => 0,
        ) );

        if ( is_wp_error( $res ) ) {
            return '';
        }

        if ( empty( $res['tasks'] ) || ! is_array( $res['tasks'] ) ) {
            return '';
        }

        $first = $res['tasks'][0];
        return ! empty( $first['id'] ) ? (string) $first['id'] : '';
    }

	/**
	 * Store a mapping email -> ClickUp task ID.
	 */
	private function store_email_index( string $email, string $task_id ) : void {
		$index = get_option( self::OPT_INDEX, array() );
		$index = is_array( $index ) ? $index : array();
		$index[ $email ] = $task_id;
		update_option( self::OPT_INDEX, $index, false );
	}

    
}

endif;

/**
 * Convenience factory for a ClickUp client.
 *
 * Usage:
 *   $client = make_clickup_client();
 */
function make_clickup_client() {
	$token = Make_ClickUp_Sync::get_access_token();
	if ( ! is_string( $token ) || $token === '' ) {
		return new WP_Error( 'make_clickup_not_connected', 'ClickUp is not connected.' );
	}
	return new Make_ClickUp_Client( $token );
}



    function make_clickup_people() {
        $client = make_clickup_client();
        if ( is_wp_error( $client ) ) {
            return $client;
        }

        $opts = Make_ClickUp_Sync::get_options();

        if ( empty( $opts['people_list_id'] ) || empty( $opts['people_email_field_id'] ) ) {
            return new WP_Error( 'make_clickup_missing_people_config', 'Missing People List ID or People Email Field ID.' );
        }

        return new Make_ClickUp_People(
            $client,
            (string) $opts['people_list_id'],
            (string) $opts['people_email_field_id']
        );
    }


function make_clickup_donations() {
	$client = make_clickup_client();
	if ( is_wp_error( $client ) ) {
		return $client;
	}

	$opts = Make_ClickUp_Sync::get_options();
	if ( empty( $opts['donations_list_id'] ) ) {
		return new WP_Error( 'make_clickup_missing_donations_config', 'Missing Donations List ID.' );
	}

	return new Make_ClickUp_Donations( $client, (string) $opts['donations_list_id'] );
}
	