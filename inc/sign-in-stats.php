<?php

class MakeSF_SignIn_Stats
{

  public function __construct()
  {
    add_action('admin_menu', [$this, 'register_admin_menu']);
    add_action('wp_ajax_makesf_heatmap', [$this, 'ajax_heatmap']);
  }

  public function register_admin_menu()
  {
    $sign_in = add_menu_page(
      __('Space Sign-in Stats', 'makesantafe'),
      'Sign-in Stats',
      'manage_options',
      'makesf-stats',
      [$this, 'display_stats_page'],
      'dashicons-chart-pie',
      76
    );

    $schedule = add_submenu_page(
      'makesf-stats',
      __('Sign-in Schedule', 'makesantafe'),
      __('Sign-in Schedule', 'makesantafe'),
      'manage_options',
      'sign-in-schedule',
      [$this, 'display_signin_schedule'],
      null
    );

    $leaderboard = add_submenu_page(
      'makesf-stats',
      __('Sign-in Leaderboard', 'makesantafe'),
      __('Sign-in Leaderboard', 'makesantafe'),
      'manage_options',
      'sign-in-leaderboard',
      [$this, 'display_signin_leaderboard'],
      null
    );
    add_action('admin_print_scripts-' . $leaderboard, [$this, 'stats_enqueue_scripts']);
    add_action('admin_print_scripts-' . $sign_in, [$this, 'stats_enqueue_scripts']);
    add_action('admin_print_scripts-' . $schedule, [$this, 'schedule_enqueue_scripts']);
  }

  public function stats_enqueue_scripts()
  {
    wp_enqueue_style('makesf-stats', MAKESF_URL . 'assets/css/stats.css', array(), MAKESF_PLUGIN_VERSION);
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), MAKESF_PLUGIN_VERSION, true);
    wp_enqueue_script('chart-js-matrix', 'https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@2', array('chart-js'), MAKESF_PLUGIN_VERSION, true);

    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-datepicker-style', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
    

    wp_enqueue_script('makesf-stats', MAKESF_URL . 'assets/js/stats.js', array('chart-js-matrix', 'chart-js', 'jquery'), MAKESF_PLUGIN_VERSION, true);
    wp_localize_script('makesf-stats', 'makeMember', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('makesf_stats_nonce'),
      'stats' => array(
        'labels' => self::get_signin_labels(),
        'data' => self::get_signin_data(),
        'matrix' => self::get_signin_matrix_data(),
      ),
    ));

    add_action('admin_footer', function() {
        ?>
        <script>
        jQuery(function($){
            $('#startDate, #endDate').datepicker({ dateFormat: 'yy-mm-dd' });
        });
        </script>
        <?php
    });
  }

  public function schedule_enqueue_scripts()
  {
    wp_enqueue_style('makesf-stats', MAKESF_URL . 'assets/css/stats.css', array(), MAKESF_PLUGIN_VERSION);
  }

public function ajax_heatmap()
{
    if ($_POST['action'] != 'makesf_heatmap')
        return;

    $badge = isset($_POST['badge']) && $_POST['badge'] !== '' ? wp_unslash($_POST['badge']) : null;
    $start_date = isset($_POST['start_date']) && $_POST['start_date'] !== '' ? sanitize_text_field($_POST['start_date']) : null;
    $end_date = isset($_POST['end_date']) && $_POST['end_date'] !== '' ? sanitize_text_field($_POST['end_date']) : null;

    $html = $this->output_heatmap(null, $badge, $start_date, $end_date);
    wp_send_json_success(array('html' => $html));
}

  public static function get_all_badges_for_filter()
  {
    global $wpdb;
    $results = $wpdb->get_col("SELECT `badges` FROM `make_signin`");
    $ids = array();
    $special = ['workshop', 'volunteer', 'other'];
    if ($results) {
      foreach ($results as $serialized) {
        $arr = @unserialize($serialized);
        if ($arr === false && $serialized !== 'b:0;')
          continue;
        if (!is_array($arr))
          $arr = array($arr);
        foreach ($arr as $b) {
          if ($b === null || $b === '')
            continue;
          if (in_array($b, $special, true) || get_the_title($b)) {
            $ids[(string) $b] = true;
          }
        }
      }
    }
    ksort($ids, SORT_NATURAL);
    return array_keys($ids);
  }

  public static function render_heatmap_filters()
  {
    $badges = self::get_all_badges_for_filter();
    $html = '<form id="heatmapFilters">';
    $html .= '<label for="badgeFilter" style="margin-right:8px;">Filter by badge:</label>';
    $html .= '<select id="badgeFilter" name="badge">';
    $html .= '<option value="">All badges</option>';
    foreach ($badges as $b) {
        $label = esc_html(self::get_badge_name_by_id($b));
        $val = esc_attr($b);
        $html .= '<option value="' . $val . '">' . $label . '</option>';
    }
    $html .= '</select>';

    // Add custom date range fields
    $html .= '<label for="startDate" style="margin:0 8px 0 16px;">Start Date:</label>';
    $html .= '<input type="text" id="startDate" name="start_date" autocomplete="off" style="width:110px;" />';
    $html .= '<label for="endDate" style="margin:0 8px 0 8px;">End Date:</label>';
    $html .= '<input type="text" id="endDate" name="end_date" autocomplete="off" style="width:110px;" />';

    $html .= '<button type="submit" style="margin-left:12px;">Filter</button>';
    $html .= '</form>';
    return $html;
  }

  public function display_stats_page()
  {
    echo '<div id="makesfStats">';
    echo '<h1>Sign-in Stats</h1>';
    echo '<div>';
    echo '<canvas id="numberSignIns"></canvas>';
    echo '</div>';

    echo '<h2>Sign-in Heatmap</h2>';
    echo '<div class="heatmap-toolbar">';
    echo self::render_heatmap_filters();
    echo '</div>';
    echo '<div id="signInHeatMap">';
    echo $this->output_heatmap(730, null);
    echo '</div>';
    echo '</div>';
  }
  public function display_signin_leaderboard()
  {
    echo '<div id="makesfLeaderboard">';
    echo '<h1>Sign-in Leaderboard</h1>';
    $this->sign_in_leaderboard();
    echo '</div>';
  }



  public function sign_in_leaderboard()
  {
    $search_term = isset($_GET['search_user']) ? sanitize_text_field($_GET['search_user']) : '';
    echo '<form method="get" class="user-search" style="margin-bottom:1em;">';
      echo '<input type="hidden" name="page" value="sign-in-leaderboard">';
      echo '<input type="text" name="search_user" placeholder="Search by name or email" value="' . esc_attr($search_term) . '" />';
      echo '<button type="submit">Search</button>';
    echo '</form>';

    echo '<div class="sign-ins-by-user">';

    if ($search_term) {
        // Find users by display name or email
        $matched_users = get_users([
            'search'         => '*' . esc_attr($search_term) . '*',
            'search_columns' => ['user_email', 'display_name'],
            'fields'         => ['ID'],
        ]);
        $matched_ids = wp_list_pluck($matched_users, 'ID');
        $all_users = self::get_users_that_sign_in_unique_days();
        $users = [];
        foreach ($matched_ids as $id) {
            if (isset($all_users[$id])) {
                $users[$id] = $all_users[$id];
            }
        }
    } else {
        $users = self::get_users_that_sign_in_unique_days();
    }


    $per_page = 30;
    $page = isset($_GET['leaderboard_page']) ? max(1, intval($_GET['leaderboard_page'])) : 1;
    $total_users = count($users);
    $total_pages = ceil($total_users / $per_page);

    $users_paged = array_slice($users, ($page - 1) * $per_page, $per_page, true);

    if (empty($users)) :
        echo '<p>No users found for that email address.</p>';
    else :

      foreach ($users_paged as $key => $value):
          $user = get_user_by('id', $key);
          if ($user):
              echo '<div class="user">';
              echo '<div class="top-card">';
              echo '<div class="user-avatar">';
              $thumb = get_field('photo', 'user_' . $user->ID);
              if ($thumb):
                  echo wp_get_attachment_image($thumb['ID'], 'small-square', false, array('class' => 'rounded-circle'));
              endif;
              echo '</div>';
              
              echo '<div class="user-meta">';
                echo '<h3 class="name">' . $user->display_name . '</h3>';
                echo '<div class="user-signins">';
                  echo '<span class="value">' . $value . '</span>';
                echo '</div>';
                echo '</div>';
              echo '</div>';

              $sign_ins = self::get_user_signins($key);
              echo '<div class="areas">';
              echo '<ul class="area">';
              foreach ($sign_ins as $key2 => $value2):
                  echo '<li>' . $key2 . ': ' . count($value2) . '</li>';
              endforeach;
              echo '</ul>';
              echo '</div>';

              echo '</div>';
          endif;
      endforeach;
    endif;

    echo '</div>';

    // Pagination links
    if ($total_pages > 1) {
        echo '<div class="leaderboard-pagination" style="margin:1em 0;">';
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $page) {
                echo '<span style="margin:0 4px;font-weight:bold;">' . $i . '</span>';
            } else {
                $url = add_query_arg('leaderboard_page', $i);
                echo '<a href="' . esc_url($url) . '" style="margin:0 4px;">' . $i . '</a>';
            }
        }
        echo '</div>';
    }
  }


  public static function get_users_that_sign_in()
  {
    global $wpdb;
    $results = $wpdb->get_results("SELECT user, count(user) AS 'signins' FROM `make_signin` GROUP BY user ORDER BY 2 DESC;");
    $users = array();
    foreach ($results as $result) {
      $users[$result->user] = $result->signins;
    }
    return $users;
  }

  public static function get_users_that_sign_in_unique_days() {
    global $wpdb;
    $results = $wpdb->get_results("SELECT user, time FROM `make_signin`");
    $user_days = array();
    foreach ($results as $result) {
        $date = date('Y-m-d', strtotime($result->time));
        $user_days[$result->user][$date] = true;
    }
    $users = array();
    foreach ($user_days as $user_id => $days) {
        $users[$user_id] = count($days);
    }
    // Sort descending by unique days
    arsort($users);
    return $users;
}

public function output_heatmap($days = null, $badge = null, $start_date = null, $end_date = null)
{
    $total_counts = 0;
    $html = '';
    $data = self::get_signin_matrix_data($days, $badge, $start_date, $end_date);
    $day_labels = $data['dayLabels'];
    $day_counts = $data['dayCounts'];
    $html .= '<div class="heatmap">';
      $html .= '<div class="hour-label-row">';
      $html .= '<div class="corner-cell"></div>'; // Empty corner for alignment
      for ($hour = 0; $hour < 24; $hour++) {
        $ampm = $hour < 12 ? 'am' : 'pm';
        $display_hour = $hour % 12 === 0 ? 12 : $hour % 12;
        $html .= '<div class="hour-label">' . $display_hour . $ampm . '</div>';
      }
    $html .= '</div>';

    foreach ($data['grid'] as $day => $hours) {
      $html .= '<div class="day-label day">' . $day_labels[$day] . ' (' . $day_counts[$day] . ')</div>';
      $html .= '<div class="day">';
      foreach ($hours as $hour => $count) {
        $html .= '<div class="hour" style="background-color:' . self::get_color($count, $data['max']) . '">';
        $html .= '<span class="count">' . $count . '</span>';
        $html .= '</div>';

        $total_counts += $count;
      }
      $html .= '</div>';
    }


    $html .= '</div>';
    $html .= '<div class="heatmap-footer">';
      $html .= 'Total sign-ins: <strong>' . $total_counts . '.</strong>';
    $html .= '</div>';
    return $html;
  }

  public static function get_color($v, $max)
  {
    if ($max <= 0) {
      return 'rgba(0, 128, 0, 0.05)';
    }
    $ratio = $v / $max;
    return "rgba(0, 128, 0, $ratio)";
  }

  public function display_signin_schedule()
  {
    global $wpdb;
    $timezone = new DateTimeZone('America/Denver');
    $today = new DateTimeImmutable('now', $timezone);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($today->sub(new DateInterval('P29D')), $interval, 30);
    echo '<div id="makesfSchedule">';
    echo '<h1>Sign-in Schedule</h1>';
    foreach (array_reverse(iterator_to_array($period)) as $date):

      $month = $date->format('m');
      $year = $date->format('Y');
      $day = $date->format('d');

      $results = $wpdb->get_results("SELECT * FROM `make_signin` WHERE MONTH(time) = $month AND YEAR(time) = $year AND DAY(time) = $day");
      if (!$results):
        continue;
      endif;

      $first_signin = $results[0]->time;
      $first_signin_time = new DateTime($first_signin, $timezone);
      $first_signinminutes_since_1am = ($first_signin_time->format('H')) * 60 + $first_signin_time->format('i');
      echo '<div class="day-container">';

      echo '<h2>' . $date->format('D, M j') . '</h2>';
      echo '<div class="daily-signins">';
      foreach ($results as $signin):
        $badges = unserialize($signin->badges);
        $user = get_user_by('id', $signin->user);
        if ($user):
          $signin_time = new DateTime($signin->time, $timezone);
          $minutes_since_1am = ($signin_time->format('H')) * 60 + $signin_time->format('i');

          $margin = $minutes_since_1am - $first_signinminutes_since_1am;

          echo '<div class="signin" style="margin-top:' . $margin . 'px">';
          echo '<div class="user-meta">';
          echo '<h3 class="name">' . $user->display_name . '</h3>';
          echo '<div class="time">';
          echo date('g:i a', strtotime($signin->time));
          echo '</div>';

          echo '<ul class="badges">';
          foreach ($badges as $badge):
            echo '<li>' . self::get_badge_name_by_id($badge) . '</li>';
          endforeach;
          echo '</ul>';

          echo '</div>';
          echo '</div>';
        endif;
      endforeach;
      echo '</div>';
      echo '</div>';
    endforeach;
    echo '</div>';
  }

  public static function get_past_year_dates($date = 'now')
  {
    $month = date("m", strtotime($date));
    $year = date("Y", strtotime($date));
    $dates = [];
    for ($i = 0; $i < 24; $i++) {
      if ($month < 1):
        $month = 12;
        $year = $year - 1;
      endif;
      $dates[] = array(
        'month' => $month,
        'year' => $year,
      );
      $month = $month - 1;
    }
    return array_reverse($dates);
  }

  public static function get_signin_labels()
  {
    $dates = self::get_past_year_dates();
    $labels = [];
    foreach ($dates as $date) {
      $labels[] = date('F, Y', mktime(0, 0, 0, $date['month'], 10, $date['year']));
    }
    $labels = array_values($labels);
    return $labels;
  }

  public static function get_signin_data()
  {
    global $wpdb;
    $labels = self::get_signin_labels();
    $number_signins = array();
    $badge_signins = array();
    $dates = self::get_past_year_dates();

    foreach ($dates as $key => $date) {
      $month = $date['month'];
      $year = $date['year'];
      $results = $wpdb->get_results("SELECT * FROM `make_signin` WHERE MONTH(time) = $month AND YEAR(time) = $year");
      foreach ($results as $result) {
        $badges = unserialize($result->badges);
        foreach ($badges as $badge) {
          $badge_signins[$badge][$labels[($key)]] = (isset($badge_signins[$badge][$labels[($key)]]) ? $badge_signins[$badge][$labels[($key)]] + 1 : 1);
        }
      }
      $number_signins[$labels[$key]] = count($results);
    }
    $datasets = array();
    foreach ($badge_signins as $key => $value) {
      $label = (is_int($key) ? get_the_title($key) : $key);
      $exists = post_exists($label);
      if ($exists):
        $datasets[] = array(
          'type' => 'line',
          'label' => html_entity_decode($label),
          'data' => $value,
          'borderWidth' => 3,
        );
      endif;
    }
    $datasets[] = array(
      'type' => 'bar',
      'label' => 'Number of Sign-ins',
      'data' => $number_signins,
      'borderWidth' => 2,
    );
    return $datasets;
  }



  public static function get_user_signins($user_id)
  {
    global $wpdb;
    $results = $wpdb->get_results("SELECT * FROM `make_signin` where user = $user_id;");
    $badge_signins = array();
    foreach ($results as $result) {
      $badges = unserialize($result->badges);
      foreach ($badges as $badge) {
        $badge_signins[self::get_badge_name_by_id($badge)][] = $result->time;
      }
    }
    array_multisort(array_map('count', $badge_signins), SORT_DESC, $badge_signins);
    return $badge_signins;
  }

  public static function get_badge_name_by_id($id)
  {
    if ($id == 'workshop'):
      $title = 'Attended Workshop';
    elseif ($id == 'volunteer'):
      $title = 'Volunteered';
    elseif ($id == 'other'):
      $title = 'Other';
    elseif (get_the_title($id)):
      $title = get_the_title($id);
    else:
      $title = false;
    endif;
    return $title;
  }

 public static function get_signin_matrix_data($days = null, $badge = null, $start_date = null, $end_date = null)
{
    global $wpdb;

    $day_counts = array_fill(0, 7, 0);
    $hour_counts = array_fill(0, 24, 0);
    $grid = array();
    for ($d = 0; $d < 7; $d++) {
        $grid[$d] = array_fill(0, 24, 0);
    }

    // Build WHERE clause for custom date range
    $where = '';
    if ($start_date && $end_date) {
        $where = $wpdb->prepare("WHERE `time` BETWEEN %s AND %s", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
    } elseif ($days > 0) {
        $start_ts = date('Y-m-d H:i:s', strtotime('-' . $days . ' days', current_time('timestamp')));
        $where = $wpdb->prepare("WHERE `time` >= %s", $start_ts);
    }

    $query = "SELECT `time`, `badges` FROM `make_signin` $where ORDER BY `time` ASC";
    $rows = $wpdb->get_results($query);
    if (empty($rows)) {
      return array(
        'dayLabels' => array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'),
        'dayCounts' => $day_counts,
        'hourCounts' => $hour_counts,
        'grid' => $grid,
        'max' => 0,
        'total' => 0,
        'timezone' => wp_timezone_string(),
        'rangeDays' => $days > 0 ? $days : null,
        'badge' => $badge,
      );
    }

    $tz = wp_timezone();
    $max = 0;
    foreach ($rows as $row) {
      $ts = $row->time;

      if ($badge !== null) {
        $badges = @unserialize($row->badges);
        if ($badges === false && $row->badges !== 'b:0;') {
          continue;
        }
        if (!is_array($badges)) {
          $badges = array($badges);
        }
        $needle = (string) $badge;
        $found = false;
        foreach ($badges as $b) {
          if ((string) $b === $needle) {
            $found = true;
            break;
          }
        }
        if (!$found) {
          continue;
        }
      }

      try {
        $dt = new DateTime($ts, $tz);
      } catch (Exception $e) {
        continue;
      }
      $day = intval($dt->format('w'));
      $hour = intval($dt->format('G'));

      $day_counts[$day]++;
      $hour_counts[$hour]++;
      $grid[$day][$hour]++;
      if ($grid[$day][$hour] > $max) {
        $max = $grid[$day][$hour];
      }
    }

    $return = array(
      'dayLabels' => array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'),
      'dayCounts' => $day_counts,
      'hourCounts' => $hour_counts,
      'grid' => $grid,
      'max' => $max,
      'total' => count($rows),
      'timezone' => wp_timezone_string(),
      'rangeDays' => $days > 0 ? $days : null,
      'badge' => $badge,
    );
    return $return;
  }
}

// Instantiate the class
new MakeSF_SignIn_Stats();