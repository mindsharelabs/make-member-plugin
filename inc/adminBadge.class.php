

<?php

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Weekly badge expiration reminder emails
 *
 * Scaffolding notes:
 * - Runs via WP-Cron once per week.
 * - Iterates active members (make_get_active_members_array()) to avoid expensive global user scans.
 * - Determines expiring/expired status using the same data assumptions as the admin overview.
 * - Stores per-badge notice meta to avoid duplicate reminders.
 *
 * Filters:
 * - makesf_badge_reminder_days_window (int) default 30
 * - makesf_badge_reminder_subject (string)
 * - makesf_badge_reminder_message (string, receives args)
 * - makesf_badge_reminder_should_send (bool, receives args)
 */
class makeBadgeExpirationReminders {

  private static $instance = null;

  const CRON_HOOK = 'makesf_badge_expiration_reminder_cron';
  const CRON_SCHEDULE = 'makesf_weekly';

  // Default: remind when <= 30 days remaining
  const DEFAULT_WINDOW_DAYS = 30;

  public static function get_instance() {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function __construct() {
    // Ensure our weekly schedule exists.
    add_filter('cron_schedules', array($this, 'register_weekly_schedule'));

    // Cron hook.
    add_action(self::CRON_HOOK, array($this, 'run_weekly'));

    // Schedule event if needed.
    add_action('init', array($this, 'maybe_schedule_event'));
  }

  /**
   * Adds a weekly schedule (WP core does not include a default "weekly" interval).
   */
  public function register_weekly_schedule($schedules) {
    if (!isset($schedules[self::CRON_SCHEDULE])) {
      $schedules[self::CRON_SCHEDULE] = array(
        'interval' => WEEK_IN_SECONDS,
        'display'  => __('Once Weekly (Make Santa Fe)', 'makesf'),
      );
    }
    return $schedules;
  }

  /**
   * Schedule the weekly cron event.
   *
   * Best practice: schedule on activation. If you have an activation hook in your main plugin file,
   * call makeBadgeExpirationReminders::schedule(); and call ::unschedule() on deactivation.
   *
   * This scaffolding also self-heals by scheduling on init if missing.
   */
  public function maybe_schedule_event() {
    if (wp_next_scheduled(self::CRON_HOOK)) {
      return;
    }

    // Align start time to the next hour to avoid immediate runs.
    $start = time() + HOUR_IN_SECONDS;
    wp_schedule_event($start, self::CRON_SCHEDULE, self::CRON_HOOK);
  }

  /**
   * Optional helpers for main plugin activation/deactivation hooks.
   */
  public static function schedule() {
    if (!wp_next_scheduled(self::CRON_HOOK)) {
      $start = time() + HOUR_IN_SECONDS;
      wp_schedule_event($start, self::CRON_SCHEDULE, self::CRON_HOOK);
    }
  }

  public static function unschedule() {
    $ts = wp_next_scheduled(self::CRON_HOOK);
    while ($ts) {
      wp_unschedule_event($ts, self::CRON_HOOK);
      $ts = wp_next_scheduled(self::CRON_HOOK);
    }
  }

  /**
   * Cron callback.
   */
  public function run_weekly() {
    $window_days = (int) apply_filters('makesf_badge_reminder_days_window', self::DEFAULT_WINDOW_DAYS);
    if ($window_days < 1) {
      $window_days = self::DEFAULT_WINDOW_DAYS;
    }

    // Get active members.
    if (!function_exists('make_get_active_members_array')) {
      $this->log('make_get_active_members_array() not available; skipping reminders.');
      return;
    }

    $user_ids = make_get_active_members_array();
    if (empty($user_ids) || !is_array($user_ids)) {
      $this->log('No active members returned; skipping reminders.');
      return;
    }

    foreach ($user_ids as $uid) {
      $uid = (int) $uid;
      if (!$uid) {
        continue;
      }

      $user = get_user_by('id', $uid);
      if (!$user || !($user instanceof WP_User)) {
        continue;
      }

      // Basic safety: ensure an email exists.
      if (empty($user->user_email) || !is_email($user->user_email)) {
        continue;
      }

      $certs = get_user_meta($uid, 'certifications', true);
      if (empty($certs) || !is_array($certs)) {
        continue;
      }

      foreach ($certs as $badge_id) {
        $badge_id = (int) $badge_id;
        if (!$badge_id) {
          continue;
        }

        // Skip if badge no longer exists.
        $badge_post = get_post($badge_id);
        if (!$badge_post || $badge_post->post_type !== 'certs') {
          continue;
        }

        // Last use/renewal.
        $last_time = get_user_meta($uid, $badge_id . '_last_time', true);
        if (empty($last_time)) {
          // Attempt to generate meta if the helper exists.
          if (function_exists('makesf_user_signin_meta_generator')) {
            makesf_user_signin_meta_generator($uid);
            $last_time = get_user_meta($uid, $badge_id . '_last_time', true);
          }
        }

        $computed = $this->compute_badge_expiration($badge_id, $last_time);

        // We only email for "expiring soon". (You can extend to "expired" if desired.)
        if ($computed['status'] !== 'expiring') {
          continue;
        }

        // Gate by actual days remaining.
        $days_left = isset($computed['days_left']) ? (int) $computed['days_left'] : 0;
        if ($days_left < 0 || $days_left > $window_days) {
          continue;
        }

        // Avoid duplicate reminders: store a sent timestamp per badge.
        $notice_key = $badge_id . '_expiry_notice_sent';
        $last_notice = get_user_meta($uid, $notice_key, true);
        $last_notice_ts = $this->parse_time_to_timestamp($last_notice);
        if ($last_notice_ts && (time() - $last_notice_ts) < WEEK_IN_SECONDS) {
          continue;
        }

        $badge_name = get_the_title($badge_id);
        if (empty($badge_name)) {
          $badge_name = 'Badge #' . $badge_id;
        }

        $expires_label = isset($computed['expires_label']) ? (string) $computed['expires_label'] : '';

        $args = array(
          'user'         => $user,
          'user_id'      => $uid,
          'badge_id'     => $badge_id,
          'badge_name'   => $badge_name,
          'days_left'    => $days_left,
          'expires_date' => $expires_label,
        );

        $should_send = (bool) apply_filters('makesf_badge_reminder_should_send', true, $args);
        if (!$should_send) {
          continue;
        }

        $subject = sprintf('Your %s badge is expiring soon', $badge_name);
        $subject = (string) apply_filters('makesf_badge_reminder_subject', $subject, $args);

        $message = $this->default_message($args);
        $message = (string) apply_filters('makesf_badge_reminder_message', $message, $args);

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        mapi_write_log('Sending badge expiration reminder to user ' . $uid . ' for badge ' . $badge_id . ' with ' . $days_left . ' days left.');

        $sent = wp_mail($user->user_email, $subject, $message, $headers);

        if ($sent) {
          update_user_meta($uid, $notice_key, time());
        } else {
          $this->log('wp_mail failed for user ' . $uid . ' / badge ' . $badge_id);
        }
      }
    }
  }

  private function default_message($args) {
    $display_name = !empty($args['user']->display_name) ? $args['user']->display_name : 'there';
    $badge_name   = (string) $args['badge_name'];
    $days_left    = (int) $args['days_left'];
    $expires_date = (string) $args['expires_date'];

    // Keep this plain text; you can swap to HTML email later.
    $lines = array();
    $lines[] = 'Hi ' . $display_name . ',';
    $lines[] = '';
    $lines[] = 'Quick heads up from Make Santa Fe: your "' . $badge_name . '" badge is set to expire soon.';
    $lines[] = 'Expiration date: ' . $expires_date;
    $lines[] = 'Time remaining: ' . $days_left . ' day' . ($days_left === 1 ? '' : 's');
    $lines[] = '';
    $lines[] = 'To renew, schedule time in the shop and be sure to sign in per our studio policy.';
    $lines[] = '';
    $lines[] = 'Questions? Reply to this email or visit makesantafe.org.';
    $lines[] = '';
    $lines[] = '— The Make Team';

    return implode("\n", $lines);
  }

  /**
   * Compute expiration status.
   * Mirrors the logic used in the badge overview page, but includes days_left for reminder gating.
   */
  private function compute_badge_expiration($badge_id, $last_time) {
    $now = current_time('timestamp');

    $expiration_days = 0;
    if (function_exists('get_field')) {
      $expiration_days = (int) get_field('expiration_time', $badge_id);
    }

    if ($expiration_days <= 0) {
      return array(
        'status'       => 'no_expiration',
        'status_label' => 'No Expiration',
        'expires_label'=> '—',
        'detail'       => $this->format_last_time_detail($last_time),
        'days_left'    => null,
      );
    }

    if (empty($last_time)) {
      return array(
        'status'       => 'unknown',
        'status_label' => 'Unknown',
        'expires_label'=> '—',
        'detail'       => 'No last use/renewal recorded',
        'days_left'    => null,
      );
    }

    $last_ts = $this->parse_time_to_timestamp($last_time);
    if (!$last_ts) {
      return array(
        'status'       => 'unknown',
        'status_label' => 'Unknown',
        'expires_label'=> '—',
        'detail'       => 'Unrecognized last_time format',
        'days_left'    => null,
      );
    }

    $expires_ts = $last_ts + ($expiration_days * DAY_IN_SECONDS);

    if ($expires_ts < $now) {
      $days_ago = (int) floor(($now - $expires_ts) / DAY_IN_SECONDS);
      return array(
        'status'       => 'expired',
        'status_label' => 'Expired',
        'expires_label'=> date_i18n('M j, Y', $expires_ts),
        'detail'       => 'Expired ' . $days_ago . ' day' . ($days_ago === 1 ? '' : 's') . ' ago',
        'days_left'    => 0,
      );
    }

    $days_left = (int) ceil(($expires_ts - $now) / DAY_IN_SECONDS);

    if ($days_left <= self::DEFAULT_WINDOW_DAYS) {
      return array(
        'status'       => 'expiring',
        'status_label' => 'Expiring Soon',
        'expires_label'=> date_i18n('M j, Y', $expires_ts),
        'detail'       => $days_left . ' day' . ($days_left === 1 ? '' : 's') . ' remaining',
        'days_left'    => $days_left,
      );
    }

    return array(
      'status'       => 'active',
      'status_label' => 'Active',
      'expires_label'=> date_i18n('M j, Y', $expires_ts),
      'detail'       => $days_left . ' day' . ($days_left === 1 ? '' : 's') . ' remaining',
      'days_left'    => $days_left,
    );
  }

  private function parse_time_to_timestamp($value) {
    if (empty($value)) {
      return 0;
    }

    if (is_numeric($value)) {
      $ts = (int) $value;
      return $ts > 0 ? $ts : 0;
    }

    $ts = strtotime((string) $value);
    return $ts ? $ts : 0;
  }

  private function format_last_time_detail($last_time) {
    if (empty($last_time)) {
      return 'No last use/renewal recorded';
    }
    $ts = $this->parse_time_to_timestamp($last_time);
    if (!$ts) {
      return 'Last use: ' . (string) $last_time;
    }
    return 'Last use: ' . date_i18n('M j, Y', $ts);
  }

  private function log($msg) {
    if (function_exists('mapi_write_log')) {
      mapi_write_log('makeBadgeExpirationReminders: ' . $msg);
    }
  }
}

/**
 * Admin badge overview page
 *
 * Shows active WooCommerce Memberships users and their badge status.
 *
 * Data assumptions:
 * - User meta `certifications` is an array of cert post IDs (post type: `certs`).
 * - Badge last-use/renewal is stored at meta key `{cert_id}_last_time`.
 * - Badge expiration timeline is stored on the cert post as ACF field `expiration_time` (days).
 */
class makeAdminBadgeOverview {

  private static $instance = null;

  private $page_slug = 'makesf-badge-overview';

  public static function get_instance() {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function __construct() {
    add_action('admin_menu', array($this, 'register_admin_page'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
  }

  public function register_admin_page() {
    // Place under the core "Users" menu in wp-admin.
    // https://developer.wordpress.org/reference/functions/add_users_page/

    add_users_page(
      'Badge Overview',
      'Badge Overview',
      'list_users',
      $this->page_slug,
      array($this, 'render_page')
    );
  }

  public function enqueue_assets($hook) {
    // Only load on our page
    if (empty($_GET['page']) || $_GET['page'] !== $this->page_slug) {
      return;
    }

    // Minimal admin styles (scaffold)
    $css_handle = 'makesf-badge-overview-css';
    wp_register_style($css_handle, false, array(), defined('MAKESF_PLUGIN_VERSION') ? MAKESF_PLUGIN_VERSION : null);
    wp_enqueue_style($css_handle);

    $css = '
      .makesf-badge-overview-wrap{max-width:1200px}
      .makesf-badge-overview-toolbar{display:flex;gap:8px;align-items:center;margin:10px 0 12px;flex-wrap:wrap}

      /* Denser grid and cards */
      .makesf-member-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:10px;margin-top:12px}
      .makesf-member-card{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:10px}

      /* Smaller typography */
      .makesf-member-card h3{margin:0 0 4px;font-size:14px;line-height:1.2}
      .makesf-member-card h3 a{text-decoration:none}
      .makesf-member-meta{color:#646970;font-size:12px;margin:0 0 6px;line-height:1.2}

      /* Tight table */
      .makesf-badge-table{width:100%;border-collapse:collapse;font-size:12px;line-height:1.25}
      .makesf-badge-table thead th{padding:6px 6px 5px;border-top:0;color:#1d2327;font-weight:600}
      .makesf-badge-table tbody td{padding:6px;border-top:1px solid #f0f0f1;vertical-align:top}
      .makesf-badge-table td strong{font-weight:600}

      /* Compact status pills */
      .makesf-badge-pill{display:inline-block;padding:1px 6px;border-radius:999px;font-weight:600;font-size:11px;line-height:16px;white-space:nowrap}
      .makesf-pill-success{background:#d1e7dd;color:#0f5132}
      .makesf-pill-warning{background:#fff3cd;color:#664d03}
      .makesf-pill-error{background:#f8d7da;color:#842029}
      .makesf-pill-info{background:#cff4fc;color:#055160}

      .makesf-small{color:#646970;font-size:11px;line-height:1.2}

      /* Reduce vertical noise in badge rows */
      .makesf-badge-table td div{margin:0}
      .makesf-badge-table td .makesf-small{margin-top:2px}
    ';
    wp_add_inline_style($css_handle, $css);
  }

  public function render_page() {
    if (!current_user_can('list_users')) {
      wp_die('You do not have permission to view this page.');
    }

    echo '<div class="wrap makesf-badge-overview-wrap">';
    echo '<h1>Badge Overview</h1>';

    // Toolbar (scaffold)
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';


    echo '<form method="get" class="makesf-badge-overview-toolbar">';
    echo '<input type="hidden" name="page" value="' . esc_attr($this->page_slug) . '">';
    echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Search members (name or email)" class="regular-text">';
    
    submit_button('Filter', 'secondary', '', false);
    echo '</form>';

    // Fetch active members (scaffold)
    $memberships = $this->get_active_memberships();

    if (empty($memberships)) {
      echo '<p><em>No active memberships found. If WooCommerce Memberships is disabled, this view will be empty.</em></p>';
      echo '</div>';
      return;
    }

    // Group by user
    $users_map = array();
    foreach ($memberships as $uid) {
      if (!$uid) {
        continue;
      }
      if (!isset($users_map[$uid])) {
        $users_map[$uid] = array(
          'user' => get_user_by('id', $uid),
          'memberships' => array(),
        );
      }
      $users_map[$uid]['memberships'][] = get_user_by('id', $uid);;
    }

    // Optional search filter (name/email)
    if (!empty($search)) {
      $search_l = strtolower($search);
      $users_map = array_filter($users_map, function($entry) use ($search_l) {
        if (empty($entry['user']) || !($entry['user'] instanceof WP_User)) {
          return false;
        }
        $name = strtolower(trim($entry['user']->display_name));
        $email = strtolower(trim($entry['user']->user_email));
        return (false !== strpos($name, $search_l)) || (false !== strpos($email, $search_l));
      });
    }

    echo '<div class="makesf-member-grid">';

    foreach ($users_map as $uid => $entry) {
      $user = $entry['user'];
      if (!$user) {
        continue;
      }
      echo $this->render_member_card($user);
    }

    echo '</div>'; // grid
    echo '</div>'; // wrap
  }

  /**
   * Get active WooCommerce Memberships user memberships.
   *
   * Returns an array of WC_Memberships_User_Membership objects when available.
   */
  private function get_active_memberships() {
    if (!class_exists('WC_Memberships_User_Membership')) {
        mapi_write_log('makeAdminBadgeOverview: WooCommerce Memberships not active or class not found.');
        return array();
    }
    return make_get_active_members_array();
  }

  private function render_member_card(WP_User $user) {
    $uid = (int) $user->ID;

  

    // Badges from user meta
    $badges = get_user_meta($uid, 'certifications', true);
    if (!$badges || !is_array($badges)) {
      $badges = array();
    }

    // Build HTML
    ob_start();

    echo '<div class="makesf-member-card">';
    $user_edit_url = esc_url( admin_url( 'user-edit.php?user_id=' . $user->ID ) );
    echo '<h3><a href="' . $user_edit_url . '">' . esc_html($user->display_name) . '</a></h3>';
    echo '<p class="makesf-member-meta">' . esc_html($user->user_email) . '</p>';


    if (empty($badges)) {
      echo '<p class="makesf-small"><em>No badges assigned.</em></p>';
      echo '</div>';
      return ob_get_clean();
    }

    echo '<table class="makesf-badge-table">';
    echo '<thead><tr>';
    echo '<th>Badge</th>';
    echo '<th>Status</th>';
    echo '<th>Expiration</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($badges as $badge_id) {
        $badge_id = (int) $badge_id;
        if (!$badge_id) {
            continue;
        }

        //check if badge exists before trying to get name or expiration - if the badge was deleted we want to skip it gracefully because we no longer support it at Make
            $badge_post = get_post($badge_id);
            if (!$badge_post || $badge_post->post_type !== 'certs') {
                continue;
            }

        $badge_name = $this->get_badge_name($badge_id);
        $last_time  = get_user_meta($uid, $badge_id . '_last_time', true);


        //if there is no last_time for this badge, let's look it up by user signins and add the meta for future use
        if (empty($last_time)) {
            makesf_user_signin_meta_generator($uid);
            $last_time  = get_user_meta($uid, $badge_id . '_last_time', true);
        }


        $computed = $this->compute_badge_expiration($badge_id, $last_time);

        echo '<tr>';
        echo '<td><strong>' . esc_html($badge_name) . '</strong><br></td>';
        echo '<td>' . $this->render_status_pill($computed['status'], $computed['status_label']) . '</td>';
        echo '<td>';
            echo '<div>' . esc_html($computed['expires_label']) . '</div>';
            if (!empty($computed['detail'])) {
            echo '<div class="makesf-small">' . esc_html($computed['detail']) . '</div>';
            }
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    echo '</div>';

    return ob_get_clean();
  }

  private function get_badge_name($badge_id) {
    // Prefer the existing helper if available.
    if (class_exists('makeProfile') && method_exists('makeProfile', 'get_badge_name_by_id')) {
      $name = makeProfile::get_badge_name_by_id($badge_id);
      if (!empty($name)) {
        return $name;
      }
    }

    $title = get_the_title($badge_id);
    return $title ? $title : ('Badge #' . (int) $badge_id);
  }

  /**
   * Compute expiration based on:
   * - expiration_time (days) field on cert post
   * - last_time (user meta)
   */
  private function compute_badge_expiration($badge_id, $last_time) {
    $now = current_time('timestamp');

 
    if (function_exists('get_field')) {
      $expiration_days = (int) get_field('expiration_time', $badge_id);
    }

    // If no expiration timeline
    if ($expiration_days <= 0) {
      return array(
        'status' => 'no_expiration',
        'status_label' => 'No Expiration',
        'expires_label' => '—',
        'detail' => $this->format_last_time_detail($last_time),
      );
    }

    // If never used/renewed
    if (empty($last_time)) {
      return array(
        'status' => 'unknown',
        'status_label' => 'Unknown',
        'expires_label' => '—',
        'detail' => 'No last use/renewal recorded',
      );
    }

    $last_ts = $this->parse_time_to_timestamp($last_time);
    if (!$last_ts) {
      return array(
        'status' => 'unknown',
        'status_label' => 'Unknown',
        'expires_label' => '—',
        'detail' => 'Unrecognized last_time format',
      );
    }

    $expires_ts = $last_ts + ($expiration_days * DAY_IN_SECONDS);

    if ($expires_ts < $now) {
      $days_ago = (int) floor(($now - $expires_ts) / DAY_IN_SECONDS);
      return array(
        'status' => 'expired',
        'status_label' => 'Expired',
        'expires_label' => date_i18n('M j, Y', $expires_ts),
        'detail' => 'Expired ' . $days_ago . ' day' . ($days_ago === 1 ? '' : 's') . ' ago',
      );
    }

    $days_left = (int) ceil(($expires_ts - $now) / DAY_IN_SECONDS);

    if ($days_left <= 30) {
      return array(
        'status' => 'expiring',
        'status_label' => 'Expiring Soon',
        'expires_label' => date_i18n('M j, Y', $expires_ts),
        'detail' => $days_left . ' day' . ($days_left === 1 ? '' : 's') . ' remaining',
      );
    }

    return array(
      'status' => 'active',
      'status_label' => 'Active',
      'expires_label' => date_i18n('M j, Y', $expires_ts),
      'detail' => $days_left . ' day' . ($days_left === 1 ? '' : 's') . ' remaining',
    );
  }

  private function render_status_pill($status, $label) {
    $cls = 'makesf-badge-pill makesf-pill-info';

    switch ($status) {
      case 'active':
        $cls = 'makesf-badge-pill makesf-pill-success';
        break;
      case 'expiring':
        $cls = 'makesf-badge-pill makesf-pill-warning';
        break;
      case 'expired':
        $cls = 'makesf-badge-pill makesf-pill-error';
        break;
      case 'no_expiration':
        $cls = 'makesf-badge-pill makesf-pill-info';
        break;
      case 'unknown':
      default:
        $cls = 'makesf-badge-pill makesf-pill-info';
        break;
    }

    return '<span class="' . esc_attr($cls) . '">' . esc_html($label) . '</span>';
  }

  private function parse_time_to_timestamp($value) {
    if (empty($value)) {
      return 0;
    }

    if (is_numeric($value)) {
      $ts = (int) $value;
      return $ts > 0 ? $ts : 0;
    }

    $ts = strtotime((string) $value);
    return $ts ? $ts : 0;
  }

  private function format_last_time_detail($last_time) {
    if (empty($last_time)) {
      return 'No last use/renewal recorded';
    }
    $ts = $this->parse_time_to_timestamp($last_time);
    if (!$ts) {
      return 'Last use: ' . (string) $last_time;
    }
    return 'Last use: ' . date_i18n('M j, Y', $ts);
  }
}












// Boot
add_action('init', function() {
  if (is_admin()) {
    makeAdminBadgeOverview::get_instance();
  }

  // Cron can run without an admin context.
  makeBadgeExpirationReminders::get_instance();
});
