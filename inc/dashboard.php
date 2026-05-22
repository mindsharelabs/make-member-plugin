<?php
/**
 * MAKE Dashboard
 *
 * Cross-plugin reporting surface for memberships, classes, and tool usage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MakeSF_Dashboard {

	const OPTION_KEY = 'makesf_dashboard_settings';
	const CACHE_VERSION_OPTION = 'makesf_dashboard_cache_version';
	const CACHE_TTL = 300;
	const DEFAULT_PLAN_SLUG = 'make-member';
	const CACHE_SCHEMA_VERSION = 3;

	/**
	 * Bootstrap hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_post_makesf_dashboard_save_settings', array( $this, 'handle_save_settings' ) );

		add_action( 'wc_memberships_user_membership_saved', array( $this, 'invalidate_cache' ) );
		add_action( 'wc_memberships_user_membership_deleted', array( $this, 'invalidate_cache' ) );
		add_action( 'wc_memberships_user_membership_status_changed', array( $this, 'invalidate_cache' ) );

		add_action( 'mtr_reservation_created', array( $this, 'invalidate_cache' ) );
		add_action( 'mtr_reservation_updated', array( $this, 'invalidate_cache' ) );
		add_action( 'mtr_reservation_cancelled', array( $this, 'invalidate_cache' ) );
		add_action( 'mtr_reservation_deleted', array( $this, 'invalidate_cache' ) );

		add_action( 'woocommerce_order_status_changed', array( $this, 'invalidate_cache' ) );
		add_action( 'woocommerce_order_refunded', array( $this, 'invalidate_cache' ) );

		add_action( 'save_post_events', array( $this, 'invalidate_cache' ) );
		add_action( 'save_post_sub_event', array( $this, 'invalidate_cache' ) );
		add_action( 'updated_post_meta', array( $this, 'invalidate_class_metric_meta_changes' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'invalidate_class_metric_meta_changes' ), 10, 4 );
	}

	/**
	 * Register admin pages.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'MAKE Dashboard', 'makesantafe' ),
			__( 'MAKE Dashboard', 'makesantafe' ),
			'manage_options',
			'makesf-dashboard',
			array( $this, 'render_overview_page' ),
			'dashicons-chart-area',
			58
		);

		add_submenu_page(
			'makesf-dashboard',
			__( 'MAKE Dashboard Overview', 'makesantafe' ),
			__( 'Overview', 'makesantafe' ),
			'manage_options',
			'makesf-dashboard',
			array( $this, 'render_overview_page' )
		);

		add_submenu_page(
			'makesf-dashboard',
			__( 'MAKE Dashboard Settings', 'makesantafe' ),
			__( 'Settings', 'makesantafe' ),
			'manage_options',
			'makesf-dashboard-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the overview screen.
	 *
	 * @return void
	 */
	public function render_overview_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'makesantafe' ) );
		}

		$dates             = $this->get_requested_dates();
		$settings          = $this->get_settings();
		$dashboard         = $this->get_dashboard_data( $dates['date_from'], $dates['date_to'] );
		$needs_revenue_map = empty( $settings['membership_product_ids'] ) || empty( $settings['day_access_product_ids'] );
		$tool_max          = ! empty( $dashboard['tool_usage']['categories'] ) ? max( $dashboard['tool_usage']['categories'] ) : 0;

		$this->render_admin_styles();
		?>
		<div class="wrap makesf-dashboard">
			<h1><?php esc_html_e( 'MAKE Dashboard', 'makesantafe' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Cross-plugin reporting for memberships, access revenue, classes, and tool usage.', 'makesantafe' ); ?></p>

			<?php if ( $needs_revenue_map ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: linked settings page label */
								__( 'Revenue mappings are incomplete. Configure membership and 24-hour access products on the %s page to populate revenue cards.', 'makesantafe' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=makesf-dashboard-settings' ) ) . '">' . esc_html__( 'Settings', 'makesantafe' ) . '</a>'
							)
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="get" class="makesf-dashboard__filters">
				<input type="hidden" name="page" value="makesf-dashboard">
				<div class="makesf-dashboard__filters-grid">
					<label>
						<span><?php esc_html_e( 'From', 'makesantafe' ); ?></span>
						<input type="date" name="date_from" value="<?php echo esc_attr( $dates['date_from'] ); ?>">
					</label>
					<label>
						<span><?php esc_html_e( 'To', 'makesantafe' ); ?></span>
						<input type="date" name="date_to" value="<?php echo esc_attr( $dates['date_to'] ); ?>">
					</label>
					<div class="makesf-dashboard__filters-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filters', 'makesantafe' ); ?></button>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=makesf-dashboard' ) ); ?>"><?php esc_html_e( 'Reset to Month-to-Date', 'makesantafe' ); ?></a>
					</div>
				</div>
			</form>

			<div class="makesf-dashboard__meta">
				<div>
					<strong><?php esc_html_e( 'Reporting Period:', 'makesantafe' ); ?></strong>
					<?php echo esc_html( $dashboard['range_label'] ); ?>
				</div>
				<div>
					<strong><?php esc_html_e( 'Last Updated:', 'makesantafe' ); ?></strong>
					<?php echo esc_html( $dashboard['generated_at'] ); ?>
				</div>
			</div>

			<div class="makesf-dashboard__section">
				<h2><?php esc_html_e( 'Membership Snapshot', 'makesantafe' ); ?></h2>
				<div class="makesf-dashboard__cards">
					<?php $this->render_stat_card( __( 'Active-status members', 'makesantafe' ), $dashboard['membership']['active_status_members'] ); ?>
					<?php $this->render_stat_card( __( 'Complimentary access holders', 'makesantafe' ), $dashboard['membership']['complimentary_access_holders'] ); ?>
					<?php $this->render_stat_card( __( 'Total active access holders', 'makesantafe' ), $dashboard['membership']['total_active_access_holders'] ); ?>
				</div>
			</div>

			<div class="makesf-dashboard__section">
				<h2><?php esc_html_e( 'Membership Growth', 'makesantafe' ); ?></h2>
				<div class="makesf-dashboard__cards">
					<?php $this->render_stat_card( __( 'New joins', 'makesantafe' ), $dashboard['membership']['new_joins'] ); ?>
					<?php $this->render_stat_card( __( 'Cancellations', 'makesantafe' ), $dashboard['membership']['cancellations'] ); ?>
					<?php $this->render_stat_card( __( 'Net member growth', 'makesantafe' ), $dashboard['membership']['net_member_growth'], false, true ); ?>
				</div>
			</div>

			<div class="makesf-dashboard__section">
				<h2><?php esc_html_e( 'Access Revenue', 'makesantafe' ); ?></h2>
				<div class="makesf-dashboard__cards">
					<?php $this->render_stat_card( __( 'Realized monthly membership revenue', 'makesantafe' ), $dashboard['revenue']['membership_revenue'], true ); ?>
					<?php $this->render_stat_card( __( '24-hour access revenue', 'makesantafe' ), $dashboard['revenue']['day_access_revenue'], true ); ?>
				</div>
			</div>

			<div class="makesf-dashboard__section">
				<h2><?php esc_html_e( 'Classes', 'makesantafe' ); ?></h2>
				<div class="makesf-dashboard__cards">
					<?php $this->render_stat_card( __( 'Class seats offered', 'makesantafe' ), $dashboard['classes']['class_seats_offered'] ); ?>
					<?php $this->render_stat_card( __( 'Class enrollments', 'makesantafe' ), $dashboard['classes']['class_enrollments'] ); ?>
					<?php $this->render_stat_card( __( 'Class fill rate', 'makesantafe' ), $dashboard['classes']['class_fill_rate'], false, false, true ); ?>
					<?php $this->render_stat_card( __( 'Class contribution margin', 'makesantafe' ), $dashboard['classes']['class_contribution_margin'], false, false, true, true ); ?>
				</div>
			</div>

			<div class="makesf-dashboard__section">
				<h2><?php esc_html_e( 'Tool Sign-ins by Category', 'makesantafe' ); ?></h2>
				<?php if ( empty( $dashboard['tool_usage']['categories'] ) ) : ?>
					<p class="makesf-dashboard__empty"><?php esc_html_e( 'No tool reservation starts were found in the selected range.', 'makesantafe' ); ?></p>
				<?php else : ?>
					<div class="makesf-dashboard__chart-list">
						<?php foreach ( $dashboard['tool_usage']['categories'] as $label => $count ) : ?>
							<div class="makesf-dashboard__chart-row">
								<div class="makesf-dashboard__chart-label"><?php echo esc_html( $label ); ?></div>
								<div class="makesf-dashboard__chart-bar-wrap">
									<div class="makesf-dashboard__chart-bar" style="width: <?php echo esc_attr( $tool_max > 0 ? round( ( $count / $tool_max ) * 100, 2 ) : 0 ); ?>%;">
										<span><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings screen.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'makesantafe' ) );
		}

		$settings = $this->get_settings();
		$plans    = $this->get_membership_plan_options();
		$products = $this->get_product_options();
		$product_categories = $this->get_product_category_options();

		$this->render_admin_styles();
		?>
		<div class="wrap makesf-dashboard">
			<h1><?php esc_html_e( 'MAKE Dashboard Settings', 'makesantafe' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Map the membership plan and WooCommerce products used by the dashboard.', 'makesantafe' ); ?></p>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Dashboard settings saved.', 'makesantafe' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="makesf-dashboard__settings-form">
				<input type="hidden" name="action" value="makesf_dashboard_save_settings">
				<?php wp_nonce_field( 'makesf_dashboard_save_settings', 'makesf_dashboard_nonce' ); ?>

				<div class="makesf-dashboard__settings-section">
					<h2><?php esc_html_e( 'Membership Plans', 'makesantafe' ); ?></h2>
					<p><?php esc_html_e( 'Select the WooCommerce Membership plans counted as access holders.', 'makesantafe' ); ?></p>

					<?php if ( empty( $plans ) ) : ?>
						<p class="makesf-dashboard__empty"><?php esc_html_e( 'No membership plans were found.', 'makesantafe' ); ?></p>
					<?php else : ?>
						<div class="makesf-dashboard__checklist">
							<?php foreach ( $plans as $plan_id => $plan_label ) : ?>
								<label>
									<input type="checkbox" name="membership_plan_ids[]" value="<?php echo esc_attr( $plan_id ); ?>" <?php checked( in_array( (int) $plan_id, $settings['membership_plan_ids'], true ) ); ?>>
									<span><?php echo esc_html( $plan_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="makesf-dashboard__settings-section">
					<h2><?php esc_html_e( 'Revenue Mapping', 'makesantafe' ); ?></h2>
					<p><?php esc_html_e( 'Choose the exact WooCommerce products that count toward each revenue card.', 'makesantafe' ); ?></p>
					<div class="makesf-dashboard__product-filter">
						<label for="makesf-dashboard-product-category-filter">
							<span><?php esc_html_e( 'Filter products by category', 'makesantafe' ); ?></span>
							<select id="makesf-dashboard-product-category-filter">
								<option value="all"><?php esc_html_e( 'All categories', 'makesantafe' ); ?></option>
								<?php foreach ( $product_categories as $category_value => $category_label ) : ?>
									<option value="<?php echo esc_attr( $category_value ); ?>"><?php echo esc_html( $category_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<p class="description"><?php esc_html_e( 'Selected products stay visible even when they fall outside the active category filter.', 'makesantafe' ); ?></p>
					</div>
					<div class="makesf-dashboard__settings-grid">
						<label>
							<span><?php esc_html_e( 'Membership products', 'makesantafe' ); ?></span>
							<select name="membership_product_ids[]" multiple size="12" data-makesf-product-select="1">
								<?php foreach ( $products as $product_id => $product_data ) : ?>
									<option
										value="<?php echo esc_attr( $product_id ); ?>"
										data-category-ids="<?php echo esc_attr( implode( ',', $product_data['category_ids'] ) ); ?>"
										<?php selected( in_array( (int) $product_id, $settings['membership_product_ids'], true ) ); ?>
									><?php echo esc_html( $product_data['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label>
							<span><?php esc_html_e( '24-hour access products', 'makesantafe' ); ?></span>
							<select name="day_access_product_ids[]" multiple size="12" data-makesf-product-select="1">
								<?php foreach ( $products as $product_id => $product_data ) : ?>
									<option
										value="<?php echo esc_attr( $product_id ); ?>"
										data-category-ids="<?php echo esc_attr( implode( ',', $product_data['category_ids'] ) ); ?>"
										<?php selected( in_array( (int) $product_id, $settings['day_access_product_ids'], true ) ); ?>
									><?php echo esc_html( $product_data['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'Hold Command on macOS or Ctrl on Windows/Linux to select multiple products.', 'makesantafe' ); ?></p>
				</div>

				<?php submit_button( __( 'Save Dashboard Settings', 'makesantafe' ) ); ?>
			</form>
		</div>
		<?php $this->render_settings_page_script(); ?>
		<?php
	}

	/**
	 * Save dashboard settings.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to update dashboard settings.', 'makesantafe' ) );
		}

		check_admin_referer( 'makesf_dashboard_save_settings', 'makesf_dashboard_nonce' );

		$settings = array(
			'membership_plan_ids'   => $this->sanitize_id_list( isset( $_POST['membership_plan_ids'] ) ? wp_unslash( $_POST['membership_plan_ids'] ) : array() ),
			'membership_product_ids'=> $this->sanitize_id_list( isset( $_POST['membership_product_ids'] ) ? wp_unslash( $_POST['membership_product_ids'] ) : array() ),
			'day_access_product_ids'=> $this->sanitize_id_list( isset( $_POST['day_access_product_ids'] ) ? wp_unslash( $_POST['day_access_product_ids'] ) : array() ),
		);

		if ( empty( $settings['membership_plan_ids'] ) ) {
			$default_plan_id = $this->resolve_default_membership_plan_id();
			if ( $default_plan_id ) {
				$settings['membership_plan_ids'] = array( $default_plan_id );
			}
		}

		update_option( self::OPTION_KEY, $settings );
		$this->bump_cache_version();

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=makesf-dashboard-settings' ) ) );
		exit;
	}

	/**
	 * Invalidate cache when relevant class meta changes.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $object_id Object ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function invalidate_class_metric_meta_changes( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );

		$tracked_keys = array(
			'total_revenue',
			'sub_event_profit',
			'instructor_expense',
			'materials_expense',
			'related_orders_count',
			'ticket_stock',
			'linked_product',
			'attendees',
		);

		if ( in_array( $meta_key, $tracked_keys, true ) ) {
			$post_type = get_post_type( $object_id );
			if ( in_array( $post_type, array( 'events', 'sub_event' ), true ) ) {
				$this->bump_cache_version();
			}
		}
	}

	/**
	 * Generic cache invalidation callback.
	 *
	 * @param mixed ...$args Unused hook arguments.
	 * @return void
	 */
	public function invalidate_cache( ...$args ) {
		unset( $args );
		$this->bump_cache_version();
	}

	/**
	 * Get merged settings.
	 *
	 * @return array<string, array<int, int>>
	 */
	private function get_settings() {
		$saved    = get_option( self::OPTION_KEY, array() );
		$defaults = $this->get_default_settings();

		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );

		$settings['membership_plan_ids']    = $this->sanitize_id_list( $settings['membership_plan_ids'] );
		$settings['membership_product_ids'] = $this->sanitize_id_list( $settings['membership_product_ids'] );
		$settings['day_access_product_ids'] = $this->sanitize_id_list( $settings['day_access_product_ids'] );

		if ( empty( $settings['membership_plan_ids'] ) && ! empty( $defaults['membership_plan_ids'] ) ) {
			$settings['membership_plan_ids'] = $defaults['membership_plan_ids'];
		}

		return $settings;
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, array<int, int>>
	 */
	private function get_default_settings() {
		$default_plan_id = $this->resolve_default_membership_plan_id();

		return array(
			'membership_plan_ids'    => $default_plan_id ? array( $default_plan_id ) : array(),
			'membership_product_ids' => array(),
			'day_access_product_ids' => array(),
		);
	}

	/**
	 * Resolve the default membership plan.
	 *
	 * @return int
	 */
	private function resolve_default_membership_plan_id() {
		if ( function_exists( 'wc_memberships_get_membership_plan' ) ) {
			$plan = wc_memberships_get_membership_plan( self::DEFAULT_PLAN_SLUG );
			if ( $plan && is_object( $plan ) && method_exists( $plan, 'get_id' ) ) {
				return (int) $plan->get_id();
			}
		}

		$plan_post = get_page_by_path( self::DEFAULT_PLAN_SLUG, OBJECT, 'wc_membership_plan' );

		return $plan_post ? (int) $plan_post->ID : 0;
	}

	/**
	 * Get cached dashboard data.
	 *
	 * @param string $date_from Range start.
	 * @param string $date_to Range end.
	 * @return array<string, mixed>
	 */
	private function get_dashboard_data( $date_from, $date_to ) {
		$settings = $this->get_settings();
		$range    = $this->build_date_range( $date_from, $date_to );
		$version  = (int) get_option( self::CACHE_VERSION_OPTION, 1 );
		$key_data = array(
			'schema'   => self::CACHE_SCHEMA_VERSION,
			'version'  => $version,
			'date_from'=> $date_from,
			'date_to'  => $date_to,
			'settings' => $settings,
		);
		$cache_key = 'makesf_dashboard_' . md5( wp_json_encode( $key_data ) );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$data = array(
			'range_label' => sprintf(
				/* translators: 1: start date, 2: end date */
				__( '%1$s to %2$s', 'makesantafe' ),
				wp_date( get_option( 'date_format' ), $range['start_local']->getTimestamp(), wp_timezone() ),
				wp_date( get_option( 'date_format' ), $range['inclusive_end_local']->getTimestamp(), wp_timezone() )
			),
			'generated_at' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) ),
			'membership'   => $this->get_membership_metrics( $settings['membership_plan_ids'], $range ),
			'revenue'      => array(
				'membership_revenue' => $this->get_product_revenue( $settings['membership_product_ids'], $range ),
				'day_access_revenue' => $this->get_product_revenue( $settings['day_access_product_ids'], $range ),
			),
			'classes'      => $this->get_class_metrics( $range ),
			'tool_usage'   => $this->get_tool_usage_metrics( $range ),
		);

		set_transient( $cache_key, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Build date range metadata.
	 *
	 * @param string $date_from Range start.
	 * @param string $date_to Range end.
	 * @return array<string, mixed>
	 */
	private function build_date_range( $date_from, $date_to ) {
		$timezone            = wp_timezone();
		$start_local         = new DateTimeImmutable( $date_from . ' 00:00:00', $timezone );
		$inclusive_end_local = new DateTimeImmutable( $date_to . ' 23:59:59', $timezone );
		$end_local_exclusive = new DateTimeImmutable( $date_to . ' 00:00:00', $timezone );
		$end_local_exclusive = $end_local_exclusive->modify( '+1 day' );
		$start_utc           = $start_local->setTimezone( new DateTimeZone( 'UTC' ) );
		$end_utc             = $end_local_exclusive->setTimezone( new DateTimeZone( 'UTC' ) );

		return array(
			'start_local'         => $start_local,
			'inclusive_end_local' => $inclusive_end_local,
			'end_local_exclusive' => $end_local_exclusive,
			'start_utc_timestamp' => $start_utc->getTimestamp(),
			'end_utc_timestamp'   => $end_utc->getTimestamp(),
			'start_utc_mysql'     => $start_utc->format( 'Y-m-d H:i:s' ),
			'end_utc_mysql'       => $end_utc->format( 'Y-m-d H:i:s' ),
			'start_query'         => $start_local->format( 'Y-m-d H:i:s' ),
			'end_query'           => $inclusive_end_local->format( 'Y-m-d H:i:s' ),
			'date_from'           => $date_from,
			'date_to'             => $date_to,
		);
	}

	/**
	 * Membership metrics.
	 *
	 * @param array<int, int>    $plan_ids Plan IDs.
	 * @param array<string,mixed> $range Range data.
	 * @return array<string, int>
	 */
	private function get_membership_metrics( $plan_ids, $range ) {
		$metrics = array(
			'active_status_members'         => 0,
			'complimentary_access_holders'  => 0,
			'total_active_access_holders'   => 0,
			'new_joins'                     => 0,
			'cancellations'                 => 0,
			'net_member_growth'             => 0,
		);

		if ( empty( $plan_ids ) || ! function_exists( 'wc_memberships_get_user_membership' ) ) {
			return $metrics;
		}

		$membership_posts = get_posts(
			array(
				'post_type'      => 'wc_user_membership',
				'post_status'    => 'any',
				'post_parent__in'=> $plan_ids,
				'posts_per_page' => -1,
			)
		);

		if ( empty( $membership_posts ) ) {
			return $metrics;
		}

		$active_users         = array();
		$complimentary_users  = array();
		$start_boundary       = (int) $range['start_utc_timestamp'];
		$end_boundary         = (int) $range['end_utc_timestamp'];

		foreach ( $membership_posts as $membership_post ) {
			$membership = wc_memberships_get_user_membership( $membership_post );
			if ( ! $membership ) {
				continue;
			}

			$user_id = method_exists( $membership, 'get_user_id' ) ? (int) $membership->get_user_id() : (int) $membership_post->post_author;
			$status  = method_exists( $membership, 'get_status' ) ? sanitize_key( (string) $membership->get_status() ) : sanitize_key( str_replace( 'wcm-', '', (string) $membership_post->post_status ) );

			if ( 'active' === $status ) {
				$active_users[ $user_id ] = true;
			} elseif ( 'complimentary' === $status ) {
				$complimentary_users[ $user_id ] = true;
			}

			$start_date = $this->get_membership_utc_timestamp( $membership, 'start' );
			if ( $start_date >= $start_boundary && $start_date < $end_boundary ) {
				$metrics['new_joins']++;
			}

			$cancelled_date = $this->get_membership_utc_timestamp( $membership, 'cancelled' );
			$end_date       = $this->get_membership_utc_timestamp( $membership, 'end' );

			if ( $cancelled_date >= $start_boundary && $cancelled_date < $end_boundary ) {
				$metrics['cancellations']++;
			} elseif ( 'expired' === $status && $end_date >= $start_boundary && $end_date < $end_boundary ) {
				$metrics['cancellations']++;
			}
		}

		$total_users = array_keys( $active_users + $complimentary_users );

		$metrics['active_status_members']        = count( $active_users );
		$metrics['complimentary_access_holders'] = count( $complimentary_users );
		$metrics['total_active_access_holders']  = count( $total_users );
		$metrics['net_member_growth']            = $metrics['new_joins'] - $metrics['cancellations'];

		return $metrics;
	}

	/**
	 * Revenue for mapped products.
	 *
	 * @param array<int, int>    $product_ids Product IDs.
	 * @param array<string,mixed> $range Range data.
	 * @return float
	 */
	private function get_product_revenue( $product_ids, $range ) {
		if ( empty( $product_ids ) || ! function_exists( 'wc_get_orders' ) ) {
			return 0.0;
		}

		$order_ids = wc_get_orders(
			array(
				'limit'        => -1,
				'type'         => 'shop_order',
				'status'       => array( 'processing', 'completed', 'refunded' ),
				'return'       => 'ids',
				'date_created' => $range['start_query'] . '...' . $range['end_query'],
			)
		);

		if ( empty( $order_ids ) ) {
			return 0.0;
		}

		$total_revenue = 0.0;
		$start_ts      = (int) $range['start_utc_timestamp'];
		$end_ts        = (int) $range['end_utc_timestamp'];

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order || ! method_exists( $order, 'get_items' ) ) {
				continue;
			}

			$order_moment = $order->get_date_paid();
			if ( ! $order_moment ) {
				$order_moment = $order->get_date_created();
			}

			if ( ! $order_moment ) {
				continue;
			}

			$order_timestamp = $order_moment->getTimestamp();
			if ( $order_timestamp < $start_ts || $order_timestamp >= $end_ts ) {
				continue;
			}

			$order_line_total = 0.0;

			foreach ( $order->get_items() as $line_item ) {
				$product_match = in_array( (int) $line_item->get_product_id(), $product_ids, true ) || in_array( (int) $line_item->get_variation_id(), $product_ids, true );

				if ( ! $product_match ) {
					continue;
				}

				$order_line_total += (float) $line_item->get_total();
			}

			if ( 0.0 === $order_line_total ) {
				continue;
			}

			$refunded_amount = $this->get_refunded_amount_for_products( $order, $product_ids );
			$total_revenue  += $order_line_total + $refunded_amount;
		}

		return round( $total_revenue, 2 );
	}

	/**
	 * Class metrics.
	 *
	 * @param array<string,mixed> $range Range data.
	 * @return array<string, float|int|null>
	 */
	private function get_class_metrics( $range ) {
		$metrics = array(
			'class_enrollments'         => 0,
			'class_seats_offered'       => 0,
			'class_fill_rate'           => null,
			'class_contribution_margin' => null,
		);

		if ( ! post_type_exists( 'sub_event' ) ) {
			return $metrics;
		}

		$timezone          = wp_timezone();
		$now               = new DateTimeImmutable( 'now', $timezone );
		$today             = $now->format( 'Y-m-d' );
		$class_range_end   = min( $range['date_to'], $today );
		$class_range_start = $range['date_from'];
		$window_start      = new DateTimeImmutable( $class_range_start . ' 00:00:00', $timezone );
		$requested_end     = new DateTimeImmutable( $class_range_end . ' 23:59:59', $timezone );
		$window_end        = $requested_end < $now ? $requested_end : $now;

		if ( $class_range_start > $class_range_end ) {
			return $metrics;
		}

		$sub_events = get_posts(
			array(
				'post_type'      => 'sub_event',
				'post_status'    => array( 'publish', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'event_start_time_stamp',
						'value'   => array( $class_range_start, $class_range_end ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
					array(
						'key'     => 'event_date',
						'value'   => array( $class_range_start, $class_range_end ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
			)
		);

		if ( empty( $sub_events ) ) {
			return $metrics;
		}

		$total_revenue      = 0.0;
		$total_direct_costs = 0.0;
		$ticketed_enrollments = 0;

		foreach ( $sub_events as $sub_event_id ) {
			$event_datetime = $this->get_sub_event_datetime( $sub_event_id, $timezone );
			if ( ! $event_datetime || $event_datetime < $window_start || $event_datetime > $window_end ) {
				continue;
			}

			$attendees_count          = $this->get_sub_event_attendee_count( $sub_event_id );
			$metrics['class_enrollments'] += $attendees_count;

			$parent_id                = wp_get_post_parent_id( $sub_event_id );
			$linked_product_id        = absint( get_post_meta( $sub_event_id, 'linked_product', true ) );
			$class_revenue            = $this->get_sub_event_total_revenue( $sub_event_id );
			$instructor_expense       = $parent_id ? (float) get_post_meta( $parent_id, 'instructor_expense', true ) : 0.0;
			$materials_expense        = $parent_id ? (float) get_post_meta( $parent_id, 'materials_expense', true ) : 0.0;
			$total_direct_cost        = $instructor_expense + ( $materials_expense * $attendees_count );

			$total_revenue      += $class_revenue;
			$total_direct_costs += $total_direct_cost;

			if ( $this->is_ticketed_sub_event( $sub_event_id, $parent_id, $linked_product_id ) ) {
				$ticketed_enrollments += $attendees_count;

				$seat_count = $this->get_sub_event_seat_count( $sub_event_id, $linked_product_id, $attendees_count, $parent_id );
				if ( null !== $seat_count ) {
					$metrics['class_seats_offered'] += $seat_count;
				}
			}
		}

		if ( $metrics['class_seats_offered'] > 0 ) {
			$metrics['class_fill_rate'] = $ticketed_enrollments / $metrics['class_seats_offered'];
		}

		if ( $total_revenue > 0 ) {
			$metrics['class_contribution_margin'] = ( $total_revenue - $total_direct_costs ) / $total_revenue;
		}

		return $metrics;
	}

	/**
	 * Tool usage metrics by studio.
	 *
	 * @param array<string,mixed> $range Range data.
	 * @return array<string,mixed>
	 */
	private function get_tool_usage_metrics( $range ) {
		$metrics = array(
			'total'      => 0,
			'categories' => array(),
		);

		global $wpdb;

		$table = class_exists( 'MTR_Reservation_Repository' ) ? MTR_Reservation_Repository::table_name() : $wpdb->prefix . 'mtr_reservations';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tool_id, COUNT(*) AS reservation_count
				FROM {$table}
				WHERE status = %s
				AND start_utc >= %s
				AND start_utc < %s
				GROUP BY tool_id",
				'active',
				$range['start_utc_mysql'],
				$range['end_utc_mysql']
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return $metrics;
		}

		$categories = array();

		foreach ( $rows as $row ) {
			$tool_id = (int) $row['tool_id'];
			$count   = (int) $row['reservation_count'];
			$label   = __( 'Unassigned', 'makesantafe' );
			$terms   = wp_get_post_terms(
				$tool_id,
				'studio',
				array(
					'orderby' => 'name',
					'order'   => 'ASC',
				)
			);

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$label = $terms[0]->name;
			}

			if ( ! isset( $categories[ $label ] ) ) {
				$categories[ $label ] = 0;
			}

			$categories[ $label ] += $count;
			$metrics['total']     += $count;
		}

		arsort( $categories );
		$metrics['categories'] = $categories;

		return $metrics;
	}

	/**
	 * Count attendee rows for a sub event.
	 *
	 * @param int $sub_event_id Sub event ID.
	 * @return int
	 */
	private function get_sub_event_attendee_count( $sub_event_id ) {
		$cached = get_post_meta( $sub_event_id, 'related_orders_count', true );
		if ( '' !== $cached && null !== $cached ) {
			return max( 0, (int) $cached );
		}

		$parent_id = wp_get_post_parent_id( $sub_event_id );
		if ( ! $parent_id ) {
			return 0;
		}

		$attendees = get_post_meta( $parent_id, 'attendees', true );
		if ( ! is_array( $attendees ) || empty( $attendees[ $sub_event_id ] ) || ! is_array( $attendees[ $sub_event_id ] ) ) {
			return 0;
		}

		return count( $attendees[ $sub_event_id ] );
	}

	/**
	 * Calculate sub-event revenue using stored meta with a fallback.
	 *
	 * @param int $sub_event_id Sub event ID.
	 * @return float
	 */
	private function get_sub_event_total_revenue( $sub_event_id ) {
		$stored = get_post_meta( $sub_event_id, 'total_revenue', true );
		if ( '' !== $stored && null !== $stored ) {
			return round( (float) $stored, 2 );
		}

		$parent_id = wp_get_post_parent_id( $sub_event_id );
		if ( ! $parent_id ) {
			return 0.0;
		}

		$attendees = get_post_meta( $parent_id, 'attendees', true );
		if ( ! is_array( $attendees ) || empty( $attendees[ $sub_event_id ] ) || ! is_array( $attendees[ $sub_event_id ] ) ) {
			return 0.0;
		}

		$linked_product_id = absint( get_post_meta( $sub_event_id, 'linked_product', true ) );
		if ( ! $linked_product_id || ! function_exists( 'wc_get_order' ) ) {
			return 0.0;
		}

		$order_ids = array();
		foreach ( $attendees[ $sub_event_id ] as $entry ) {
			$order_id = isset( $entry['order_id'] ) ? absint( $entry['order_id'] ) : 0;
			if ( ! $order_id || in_array( $order_id, $order_ids, true ) ) {
				continue;
			}

			$order = wc_get_order( $order_id );
			if ( $order && in_array( $order->get_status(), array( 'completed', 'processing' ), true ) ) {
				$order_ids[] = $order_id;
			}
		}

		$total_revenue = 0.0;

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$order_line_total = 0.0;

			foreach ( $order->get_items() as $line_item ) {
				if ( (int) $line_item->get_product_id() !== $linked_product_id ) {
					continue;
				}

				$order_line_total += (float) $line_item->get_total();
			}

			if ( 0.0 === $order_line_total ) {
				continue;
			}

			$refunded_amount = $this->get_refunded_amount_for_products( $order, array( $linked_product_id ) );
			$total_revenue  += $order_line_total + $refunded_amount;
		}

		return round( $total_revenue, 2 );
	}

	/**
	 * Determine seat count for a ticketed class.
	 *
	 * @param int $sub_event_id Sub event ID.
	 * @param int $linked_product_id Linked product ID.
	 * @param int $attendees_count Enrollment count.
	 * @param int $parent_id Parent event ID.
	 * @return int|null
	 */
	private function get_sub_event_seat_count( $sub_event_id, $linked_product_id, $attendees_count, $parent_id ) {
		$ticket_stock = get_post_meta( $sub_event_id, 'ticket_stock', true );
		if ( '' === $ticket_stock && $parent_id ) {
			$ticket_stock = get_post_meta( $parent_id, 'ticket_stock', true );
		}

		if ( '' !== $ticket_stock && null !== $ticket_stock ) {
			return max( $attendees_count, (int) $ticket_stock );
		}

		if ( $linked_product_id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $linked_product_id );
			if ( $product && $product->managing_stock() ) {
				$remaining_stock = $product->get_stock_quantity();
				if ( null !== $remaining_stock ) {
					return max( 0, $attendees_count + (int) $remaining_stock );
				}
			}
		}

		return null;
	}

	/**
	 * Determine whether a sub-event should be treated as ticketed.
	 *
	 * Mirrors the Make Simple Events reporting logic by using the parent
	 * event's `has_tickets` flag, with a linked-product fallback for older data.
	 *
	 * @param int $sub_event_id Sub event ID.
	 * @param int $parent_id Parent event ID.
	 * @param int $linked_product_id Linked product ID.
	 * @return bool
	 */
	private function is_ticketed_sub_event( $sub_event_id, $parent_id, $linked_product_id ) {
		if ( $parent_id ) {
			$has_tickets = get_post_meta( $parent_id, 'has_tickets', true );
			if ( ! empty( $has_tickets ) ) {
				return true;
			}
		}

		if ( $linked_product_id > 0 ) {
			return true;
		}

		$sub_event_product_id = absint( get_post_meta( $sub_event_id, 'linked_product', true ) );
		return $sub_event_product_id > 0;
	}

	/**
	 * Get the sub-event datetime in the site timezone.
	 *
	 * @param int              $sub_event_id Sub event ID.
	 * @param DateTimeZone|null $timezone Site timezone.
	 * @return DateTimeImmutable|null
	 */
	private function get_sub_event_datetime( $sub_event_id, $timezone = null ) {
		$timezone   = $timezone ?: wp_timezone();
		$event_date = get_post_meta( $sub_event_id, 'event_start_time_stamp', true );

		if ( ! $event_date ) {
			$event_date = get_post_meta( $sub_event_id, 'event_date', true );
		}

		if ( ! is_string( $event_date ) || '' === $event_date ) {
			return null;
		}

		try {
			return new DateTimeImmutable( $event_date, $timezone );
		} catch ( Exception $exception ) {
			return null;
		}
	}

	/**
	 * Get refunded line-item totals for a set of products on an order.
	 *
	 * Refund line totals are negative in WooCommerce, so callers can add the
	 * returned value to the original line total to get net realized revenue.
	 *
	 * @param object        $order WooCommerce order-like object.
	 * @param array<int,int> $product_ids Product IDs to match.
	 * @return float
	 */
	private function get_refunded_amount_for_products( $order, $product_ids ) {
		$refunded_amount = 0.0;

		if ( ! $order || ! method_exists( $order, 'get_refunds' ) ) {
			return $refunded_amount;
		}

		foreach ( $order->get_refunds() as $refund ) {
			if ( ! $refund || ! method_exists( $refund, 'get_items' ) ) {
				continue;
			}

			foreach ( $refund->get_items() as $refund_item ) {
				$refund_match = in_array( (int) $refund_item->get_product_id(), $product_ids, true ) || in_array( (int) $refund_item->get_variation_id(), $product_ids, true );
				if ( $refund_match ) {
					$refunded_amount += (float) $refund_item->get_total();
				}
			}
		}

		return $refunded_amount;
	}

	/**
	 * Get membership timestamp values as UTC timestamps.
	 *
	 * @param object $membership Membership object.
	 * @param string $field Field key.
	 * @return int
	 */
	private function get_membership_utc_timestamp( $membership, $field ) {
		$method_map = array(
			'start'     => 'get_start_date',
			'end'       => 'get_end_date',
			'cancelled' => 'get_cancelled_date',
		);

		if ( empty( $method_map[ $field ] ) || ! method_exists( $membership, $method_map[ $field ] ) ) {
			return 0;
		}

		$value = $membership->{$method_map[ $field ]}( 'timestamp' );

		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		if ( is_string( $value ) && '' !== $value ) {
			$timestamp = strtotime( $value );
			return $timestamp ? (int) $timestamp : 0;
		}

		return 0;
	}

	/**
	 * Get requested date filters or defaults.
	 *
	 * @return array<string, string>
	 */
	private function get_requested_dates() {
		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );
		$defaults = array(
			'date_from' => $now->modify( 'first day of this month' )->format( 'Y-m-d' ),
			'date_to'   => $now->format( 'Y-m-d' ),
		);

		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : $defaults['date_from'];
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : $defaults['date_to'];

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$date_from = $defaults['date_from'];
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$date_to = $defaults['date_to'];
		}

		if ( $date_from > $date_to ) {
			$date_from = $date_to;
		}

		return array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
		);
	}

	/**
	 * Membership plans for settings.
	 *
	 * @return array<int, string>
	 */
	private function get_membership_plan_options() {
		$options = array();

		if ( ! function_exists( 'wc_memberships_get_membership_plans' ) ) {
			return $options;
		}

		$plans = wc_memberships_get_membership_plans( array( 'post_status' => 'any' ) );
		foreach ( $plans as $plan ) {
			if ( ! is_object( $plan ) || ! method_exists( $plan, 'get_id' ) || ! method_exists( $plan, 'get_name' ) ) {
				continue;
			}

			$options[ (int) $plan->get_id() ] = sprintf( '%1$s (#%2$d)', $plan->get_name(), (int) $plan->get_id() );
		}

		asort( $options );

		return $options;
	}

	/**
	 * Products for settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_product_options() {
		$options  = array();
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		foreach ( $products as $product_id ) {
			$product_terms = wp_get_post_terms(
				$product_id,
				'product_cat',
				array(
					'orderby' => 'name',
					'order'   => 'ASC',
				)
			);
			$category_ids = array();

			if ( ! is_wp_error( $product_terms ) && ! empty( $product_terms ) ) {
				foreach ( $product_terms as $product_term ) {
					$category_ids[] = (int) $product_term->term_id;
				}
			}

			if ( empty( $category_ids ) ) {
				$category_ids[] = 'uncategorized';
			}

			$options[ (int) $product_id ] = array(
				'label'        => sprintf( '%1$s (#%2$d)', get_the_title( $product_id ), (int) $product_id ),
				'category_ids' => $category_ids,
			);
		}

		return $options;
	}

	/**
	 * Product categories for settings filtering.
	 *
	 * @return array<string, string>
	 */
	private function get_product_category_options() {
		$options = array(
			'uncategorized' => __( 'Uncategorized', 'makesantafe' ),
		);

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $options;
		}

		$options = array();

		foreach ( $terms as $term ) {
			$options[ (string) $term->term_id ] = $term->name;
		}

		$options['uncategorized'] = __( 'Uncategorized', 'makesantafe' );

		return $options;
	}

	/**
	 * Render stat card markup.
	 *
	 * @param string       $label Card label.
	 * @param int|float|null $value Card value.
	 * @param bool         $currency Format as currency.
	 * @param bool         $signed Show sign on positive numbers.
	 * @param bool         $percentage Format as percentage.
	 * @param bool         $margin_style Style positive/negative.
	 * @return void
	 */
	private function render_stat_card( $label, $value, $currency = false, $signed = false, $percentage = false, $margin_style = false ) {
		$class_name = 'makesf-dashboard__card-value';

		if ( $margin_style && is_numeric( $value ) ) {
			if ( $value > 0 ) {
				$class_name .= ' is-positive';
			} elseif ( $value < 0 ) {
				$class_name .= ' is-negative';
			}
		}

		?>
		<div class="makesf-dashboard__card">
			<div class="makesf-dashboard__card-label"><?php echo esc_html( $label ); ?></div>
			<div class="<?php echo esc_attr( $class_name ); ?>"><?php echo esc_html( $this->format_metric_value( $value, $currency, $signed, $percentage ) ); ?></div>
		</div>
		<?php
	}

	/**
	 * Format card values.
	 *
	 * @param int|float|null $value Value.
	 * @param bool           $currency Currency flag.
	 * @param bool           $signed Signed flag.
	 * @param bool           $percentage Percentage flag.
	 * @return string
	 */
	private function format_metric_value( $value, $currency = false, $signed = false, $percentage = false ) {
		if ( null === $value ) {
			return __( 'N/A', 'makesantafe' );
		}

		if ( $percentage ) {
			return number_format_i18n( (float) $value * 100, 1 ) . '%';
		}

		if ( $currency ) {
			return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( (float) $value ) ) : '$' . number_format_i18n( (float) $value, 2 );
		}

		if ( is_numeric( $value ) ) {
			$prefix = '';
			if ( $signed && (float) $value > 0 ) {
				$prefix = '+';
			}

			if ( (int) $value === (float) $value ) {
				return $prefix . number_format_i18n( (int) $value );
			}

			return $prefix . number_format_i18n( (float) $value, 2 );
		}

		return (string) $value;
	}

	/**
	 * Render shared admin styles.
	 *
	 * @return void
	 */
	private function render_admin_styles() {
		static $rendered = false;

		if ( $rendered ) {
			return;
		}

		$rendered = true;
		?>
		<style>
			.makesf-dashboard__filters,
			.makesf-dashboard__settings-section,
			.makesf-dashboard__section {
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 10px;
				padding: 20px;
				margin: 20px 0;
				box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
			}
			.makesf-dashboard__filters-grid,
			.makesf-dashboard__settings-grid {
				display: grid;
				gap: 16px;
				grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
				align-items: end;
			}
			.makesf-dashboard__filters label,
			.makesf-dashboard__settings-grid label {
				display: flex;
				flex-direction: column;
				gap: 6px;
				font-weight: 600;
			}
			.makesf-dashboard__filters input,
			.makesf-dashboard__settings-grid select {
				font-weight: 400;
			}
			.makesf-dashboard__product-filter {
				margin-bottom: 16px;
			}
			.makesf-dashboard__product-filter label {
				display: flex;
				flex-direction: column;
				gap: 6px;
				max-width: 360px;
				font-weight: 600;
			}
			.makesf-dashboard__filters-actions {
				display: flex;
				gap: 10px;
				flex-wrap: wrap;
			}
			.makesf-dashboard__meta {
				display: flex;
				gap: 16px;
				flex-wrap: wrap;
				color: #50575e;
				margin: 14px 0 0;
			}
			.makesf-dashboard__cards {
				display: grid;
				gap: 16px;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			}
			.makesf-dashboard__card {
				border: 1px solid #e5e7eb;
				border-radius: 12px;
				padding: 18px;
				background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
			}
			.makesf-dashboard__card-label {
				font-size: 13px;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.04em;
				color: #6b7280;
				margin-bottom: 8px;
			}
			.makesf-dashboard__card-value {
				font-size: 30px;
				line-height: 1.1;
				font-weight: 700;
				color: #111827;
			}
			.makesf-dashboard__card-value.is-positive {
				color: #1f7a1f;
			}
			.makesf-dashboard__card-value.is-negative {
				color: #b42318;
			}
			.makesf-dashboard__checklist {
				display: grid;
				gap: 10px;
				grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
			}
			.makesf-dashboard__checklist label {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 10px 12px;
				border: 1px solid #e5e7eb;
				border-radius: 10px;
			}
			.makesf-dashboard__chart-list {
				display: grid;
				gap: 12px;
			}
			.makesf-dashboard__chart-row {
				display: grid;
				grid-template-columns: minmax(180px, 220px) 1fr;
				gap: 12px;
				align-items: center;
			}
			.makesf-dashboard__chart-label {
				font-weight: 600;
			}
			.makesf-dashboard__chart-bar-wrap {
				background: #eef2f7;
				border-radius: 999px;
				overflow: hidden;
			}
			.makesf-dashboard__chart-bar {
				background: linear-gradient(90deg, #be202e 0%, #d44d57 100%);
				color: #fff;
				min-width: 56px;
				padding: 8px 12px;
				border-radius: 999px;
				font-weight: 700;
				text-align: right;
			}
			.makesf-dashboard__empty {
				color: #6b7280;
				margin: 0;
			}
			.makesf-dashboard__settings-grid select[multiple] {
				min-height: 280px;
			}
			@media (max-width: 782px) {
				.makesf-dashboard__chart-row {
					grid-template-columns: 1fr;
				}
			}
		</style>
		<?php
	}

	/**
	 * Sanitize ID lists.
	 *
	 * @param mixed $values Raw values.
	 * @return array<int, int>
	 */
	private function sanitize_id_list( $values ) {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$values = array_map( 'absint', $values );
		$values = array_values( array_filter( array_unique( $values ) ) );
		sort( $values );

		return $values;
	}

	/**
	 * Bump cache version.
	 *
	 * @return void
	 */
	private function bump_cache_version() {
		$current_version = (int) get_option( self::CACHE_VERSION_OPTION, 1 );
		update_option( self::CACHE_VERSION_OPTION, $current_version + 1, false );
	}

	/**
	 * Render the settings page filtering script.
	 *
	 * @return void
	 */
	private function render_settings_page_script() {
		?>
		<script>
			(function() {
				var filter = document.getElementById('makesf-dashboard-product-category-filter');
				if (!filter) {
					return;
				}

				var selects = document.querySelectorAll('[data-makesf-product-select="1"]');
				if (!selects.length) {
					return;
				}

				function optionMatches(option, categoryValue) {
					if (categoryValue === 'all') {
						return true;
					}

					var categoryIds = String(option.getAttribute('data-category-ids') || '')
						.split(',')
						.map(function(value) { return value.trim(); })
						.filter(Boolean);

					return categoryIds.indexOf(categoryValue) !== -1;
				}

				function updateSelects() {
					var categoryValue = filter.value;

					selects.forEach(function(select) {
						Array.prototype.forEach.call(select.options, function(option) {
							var shouldShow = option.selected || optionMatches(option, categoryValue);
							option.hidden = !shouldShow;
						});
					});
				}

				filter.addEventListener('change', updateSelects);
				updateSelects();
			})();
		</script>
		<?php
	}
}

new MakeSF_Dashboard();
