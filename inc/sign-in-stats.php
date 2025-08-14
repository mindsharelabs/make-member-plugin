<?php
/**
 * Register a custom menu page.
 */
add_action('admin_menu', function () {
  $sign_in = add_menu_page(
    __('Space Sign-in Stats', 'makesantafe'), //page title
    'Sign-in Stats', //menu title
    'manage_options', //capability
    'makesf-stats', //menu slug
    'makesf_display_stats_page', //callback
    'dashicons-chart-pie', //icon
    76 //position
  );

  $schedule = add_submenu_page(
    'makesf-stats', //string $parent_slug,
    __('Sign-in Schedule', 'makesantafe'), //string $page_title,
    __('Sign-in Schedule', 'makesantafe'), //string $menu_title,
    'manage_options',  //string $capability,
    'sign-in-schedule', //string $menu_slug,
    'makesf_display_signin_schedule', //callable $callback = '',
    null  //int|float $position = null
  );



  add_action('admin_print_scripts-' . $sign_in, 'makesf_stats_enqueue_scripts');
  add_action('admin_print_scripts-' . $schedule, 'makesf_schedule_enqueue_scripts');
});


function makesf_stats_enqueue_scripts()
{
  wp_enqueue_style('makesf-stats', MAKESF_URL . 'assets/css/stats.css', array(), MAKESF_PLUGIN_VERSION);
  wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), MAKESF_PLUGIN_VERSION, true);
  wp_enqueue_script('chart-js-matrix', 'https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@2', array('chart-js'), MAKESF_PLUGIN_VERSION, true);


  wp_enqueue_script('makesf-stats', MAKESF_URL . 'assets/js/stats.js', array('chart-js-matrix', 'chart-js', 'jquery'), MAKESF_PLUGIN_VERSION, true);
  wp_localize_script('makesf-stats', 'makeMember', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('makesf_stats_nonce'),
    'stats' => array(
      'labels' => makesf_get_signin_labels(),
      'data' => makesf_get_signin_data(),
      'matrix' => makesf_get_signin_matrix_data(),  // precomputed day×hour matrix + counts
    ),
  ));
}
function makesf_schedule_enqueue_scripts()
{
  wp_enqueue_style('makesf-stats', MAKESF_URL . 'assets/css/stats.css', array(), MAKESF_PLUGIN_VERSION);
}


function makesf_display_stats_page()
{
  echo '<div id="makesfStats">';
  echo '<h1>Sign-in Stats</h1>';
  echo '<div>';
  echo '<canvas id="numberSignIns"></canvas>';
  echo '</div>';

  echo '<h1>Popular Days and Times</h1>';
  // echo '<div>';
  //   echo '<canvas id="signinsHeatmap"></canvas>';
  // echo '</div>';


  $data = makesf_get_signin_matrix_data(730);
  $day_labels = $data['dayLabels'];
  $day_counts = $data['dayCounts'];
  echo '<div class="heatmap">';
    echo '<div class="hour-label-row">';
      echo '<div class="corner-cell"></div>'; // Empty corner for alignment
        for ($hour = 0; $hour < 24; $hour++) {
            $ampm = $hour < 12 ? 'am' : 'pm';
            $display_hour = $hour % 12 === 0 ? 12 : $hour % 12;
            echo '<div class="hour-label">' . $display_hour . $ampm . '</div>';
        }
      echo '</div>';
    foreach($data['grid'] as $day => $hours) {
      echo '<div class="day-label day">' . $day_labels[$day] . ' (' . $day_counts[$day] . ')</div>';
      echo '<div class="day">';
        foreach($hours as $hour => $count) {
          echo '<div class="hour" style="background-color:' . makesf_get_color($count, $data['max']) . '">';
            echo '<span class="count">' . $count . '</span>';
          echo '</div>';
        }
      echo '</div>';
    }
  echo '</div>';

  echo '<h1>Sign-in Leaderboard</h1>';
  echo '<div class="sign-ins-by-user">';

  $users = get_users_that_sign_in();

  $total_signins = array();
  foreach ($users as $key => $value):
    $user = get_user_by('id', $key);
    if ($user):
      echo '<div class="user">';
      echo '<div class="top-card">';
      echo '<div class="user-avatar">';
      $thumb = get_field('photo', 'user_' . $user->ID);
      if ($thumb):
        //echo wp_get_attachment_image( $thumb['ID'], 'small-square', false, array('class' => 'rounded-circle'));
      endif;
      echo '</div>';
      echo '<div class="user-meta">';
      echo '<h3 class="name">' . $user->display_name . '</h3>';
      echo '<div class="user-signins">';
      echo $value;
      echo '</div>';
      echo '</div>';
      echo '</div>';

      $sign_ins = get_user_singins($key);
      echo '<div class="areas">';
      echo '<ul class="area">';
      foreach ($sign_ins as $key => $value):
        $total_signins[$key] = (isset($total_signins[$key]) ? $total_signins[$key] + count($value) : count($value));
        echo '<li>' . $key . ': ' . count($value) . '</li>';
      endforeach;
      echo '</ul>';
      echo '</div>';

      echo '</div>';

    endif;
  endforeach;

  echo '</div>';
  echo '<div class="total-signins">';
  echo '<h2>Total Sign-ins</h2>';
  echo '<ul>';
  arsort($total_signins);
  foreach ($total_signins as $key => $value):
    echo '<li><strong>' . $key . '</strong>: ' . $value . '</li>';
  endforeach;
  echo '</ul>';
  echo '</div>';
  echo '</div>';
  // get_users_that_sign_in();
}



function makesf_get_color($v, $max) {
  if ($max <= 0) {
    return 'rgba(0, 128, 0, 0.05)'; // No sign-ins, almost transparent green
  }
  $ratio = $v / $max;
  return "rgba(0, 128, 0, $ratio)"; // Transparent to green
}
/*
This function displays a schedule of sign ins for the past 30 days. 
*/
function makesf_display_signin_schedule()
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
          echo '<li>' . get_badge_name_by_id($badge) . '</li>';
        endforeach;
        echo '</ul>';

        echo '</div>';
        echo '</div>';
      endif;
    endforeach; //daily results
    echo '</div>';
    echo '</div>';
  endforeach; //day
  echo '</div>';
}

function makesf_get_past_year_dates($date = 'now')
{

  $month = date("m", strtotime($date));
  $year = date("Y", strtotime($date));
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


function makesf_get_signin_labels()
{
  $dates = makesf_get_past_year_dates();
  foreach ($dates as $date) {
    $labels[] = date('F, Y', mktime(0, 0, 0, $date['month'], 10, $date['year']));
  }

  $labels = array_values($labels);
  return $labels;
}


function makesf_get_signin_data()
{
  global $wpdb;
  $labels = makesf_get_signin_labels();
  $number_signins = array();
  $badge_signins = array();
  $dates = makesf_get_past_year_dates();

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
  //Badge_signins now has an array of badge signins for each month
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

  //add total counts
  $datasets[] = array(
    'type' => 'bar',
    'label' => 'Number of Sign-ins',
    'data' => $number_signins,
    'borderWidth' => 2,
  );


  return $datasets;

}

function get_users_that_sign_in()
{
  global $wpdb;
  $results = $wpdb->get_results("SELECT user, count(user) AS 'signins' FROM `make_signin` GROUP BY user ORDER BY 2 DESC;");
  $users = array();
  foreach ($results as $result) {
    $users[$result->user] = $result->signins;
  }

  return $users;
}


function get_user_singins($user_id)
{
  global $wpdb;
  $results = $wpdb->get_results("SELECT * FROM `make_signin` where user = $user_id;");
  $badge_signins = array();
  foreach ($results as $result) {
    $badges = unserialize($result->badges);
    foreach ($badges as $badge) {
      $badge_signins[get_badge_name_by_id($badge)][] = $result->time;
    }
  }

  array_multisort(array_map('count', $badge_signins), SORT_DESC, $badge_signins);
  return $badge_signins;
}


function get_badge_name_by_id($id)
{
  if ($id == 'workshop'):
    $title = 'Attended Workshop';
  elseif ($id == 'volunteer'):
    $title = 'Volunteered';
  elseif ($id == 'other'):
    $title = 'Other';
  else:
    $title = get_the_title($id);
  endif;
  return $title;
}


function makesf_get_signin_matrix_data($days = 365)
{
  global $wpdb;

  // Initialize structures
  $day_counts = array_fill(0, 7, 0);
  $hour_counts = array_fill(0, 24, 0);
  $grid = array();
  for ($d = 0; $d < 7; $d++) {
    $grid[$d] = array_fill(0, 24, 0);
  }

  // Build query window
  $days = intval($days);
  if ($days > 0) {
    $start_ts = date('Y-m-d H:i:s', strtotime('-' . $days . ' days', current_time('timestamp')));
    $query = $wpdb->prepare("SELECT `time` FROM `make_signin` WHERE `time` >= %s ORDER BY `time` ASC", $start_ts);
  } else {
    $query = "SELECT `time` FROM `make_signin` ORDER BY `time` ASC";
  }

  $rows = $wpdb->get_col($query);
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
    );
  }

  $tz = wp_timezone(); // Uses site timezone setting
  $max = 0;
  foreach ($rows as $ts) {
    try {
      $dt = new DateTime($ts, $tz);
    } catch (Exception $e) {
      continue;
    }
    $day = intval($dt->format('w')); // 0=Sun..6=Sat
    $hour = intval($dt->format('G')); // 0..23

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
  );
  return $return;
}