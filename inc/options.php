<?php
add_action( 'admin_menu', 'makesf_support_settings_page' );
// add_action( 'admin-init', 'makesf_api_settings_init' );


function makesf_support_settings_page() {
    add_options_page(
      'Make SF Member Plugin Options',
      'Make SF Member Plugin Options',
      'manage_options', //permisions
      'makesf-api-settings', //page slug
      'makesf_support_settings' //callback for display
    );
}


function makesf_api_settings_init(  ) {
    register_setting( 'makesfPlugin', 'makesf_support_settings' );
    $options = get_option( 'makesf_support_settings' );

    add_settings_section(
      'makesf_page_links_section', //section id
      'Make SF Page Links', //section title
      'makesf_support_settings_section_callback', //display callback
      'makesfPlugin' //settings page
    );


    add_settings_field(
      'makesf_waiver_url', //setting id
      'Member Waiver URL', //setting title
      'makesf_setting_field', //display callback
      'makesfPlugin', //setting page
      'makesf_page_links_section', //setting section
      array(
        'message' => '',
        'field' => 'makesf_waiver_url',
        'value' => (isset($options['makesf_waiver_url']) ? $options['makesf_waiver_url'] : false)
      ) //args
    );

    add_settings_field(
      'makesf_membership_url', //setting id
      'Membership Purchase URL', //setting title
      'makesf_setting_field', //display callback
      'makesfPlugin', //setting page
      'makesf_page_links_section', //setting section
      array(
        'message' => '',
        'field' => 'makesf_membership_url',
        'value' => (isset($options['makesf_membership_url']) ? $options['makesf_membership_url'] : false)
      ) //args
    );

    add_settings_field(
      'makesf_badges_url', //setting id
      'Badges URL', //setting title
      'makesf_setting_field', //display callback
      'makesfPlugin', //setting page
      'makesf_page_links_section', //setting section
      array(
        'message' => '',
        'field' => 'makesf_badges_url',
        'value' => (isset($options['makesf_badges_url']) ? $options['makesf_badges_url'] : false)
      ) //args
    );

    // add_settings_field(
    //   'makesf_forum_url', //setting id
    //   'Forum URL', //setting title
    //   'makesf_setting_field', //display callback
    //   'makesfPlugin', //setting page
    //   'makesf_page_links_section', //setting section
    //   array(
    //     'message' => '',
    //     'field' => 'makesf_forum_url',
    //     'value' => (isset($options['makesf_forum_url']) ? $options['makesf_forum_url'] : false)
    //   ) //args
    // );

    add_settings_field(
      'makesf_workshops_url', //setting id
      'Workshops URL', //setting title
      'makesf_setting_field', //display callback
      'makesfPlugin', //setting page
      'makesf_page_links_section', //setting section
      array(
        'message' => '',
        'field' => 'makesf_workshops_url',
        'value' => (isset($options['makesf_workshops_url']) ? $options['makesf_workshops_url'] : false)
      ) //args
    );

    add_settings_field(
      'makesf_workshop_category', //setting id
      'Workshop Category', //setting title
      'makesf_setting_select_field', //display callback
      'makesfPlugin', //setting page
      'makesf_page_links_section', //setting section
      array(
        'message' => '',
        'field' => 'makesf_workshop_category',
        'value' => (isset($options['makesf_workshop_category']) ? $options['makesf_workshop_category'] : false)
      ) //args
    );

}





function makesf_enable_sync_field($args) {
  echo '<input type="checkbox" id="' . $args['field'] . '" name="makesf_support_settings[' . $args['field'] . ']" ' . checked($args['value'], 'on', false) . '>';
}


function makesf_setting_field($args) {
  echo '<input class="makesf-text-field" type="text" id="' . $args['field'] . '" name="makesf_support_settings[' . $args['field'] . ']" value="' . $args['value'] . '">';
  if($args['message']) {
    echo '<br><small>' . $args['message'] . '</small>';
  }
}


function makesf_setting_select_field($args) {
  $product_cats = get_terms('product_cat');
  if($product_cats) :
    echo '<select name="makesf_support_settings[' . $args['field'] . ']" id="' . $args['field'] . '">';
      foreach ($product_cats as $key => $cat) :
        echo '<option value="' . $cat->slug . '"' . selected( $args['value'], $cat->slug ) . '>' . $cat->name . '</option>';
      endforeach;
    echo '</select>';
  else :
    echo 'Please install WooCommerce and create some product categories.';
  endif;
}

function makesf_support_settings_section_callback() {
  echo '';
}


function makesf_support_settings() {
  echo '<div id="makesf">';
    echo '<form action="options.php" method="post">';
        settings_fields( 'makesfPlugin' );
        do_settings_sections( 'makesfPlugin' );
        submit_button();
    echo '</form>';
  echo '</div>';

}


//if the current user fills out the Waiver and Release of Liability, then we add this user meta
add_action( 'gform_after_submission_27', function ( $entry, $form ) {
  if (! class_exists ('GFAPI')) {
    return;
  }

  if($entry['created_by']) :
    update_user_meta( $entry['created_by'], 'waiver_complete', true );
  else :
    $user = get_user_by('email', $entry['34']);
    if($user) :
      $forms = new GFAPI();
      $forms->update_entry_property( $entry['id'], 'created_by', $user->ID );
      update_user_meta( $user->ID, 'waiver_complete', true );
    endif;
  endif;


}, 10, 2 );

/**
 * Register a custom menu page.
 */
add_action( 'admin_menu', function () {
	$sign_in = add_menu_page(
		__( 'Space Sign-in Stats', 'makesantafe' ), //page title
		'Sign-in Stats', //menu title
		'manage_options', //capability
		'makesf-stats', //menu slug
		'makesf_display_stats_page', //callback
		'dashicons-chart-pie', //icon
		76 //position
	);

  $schedule = add_submenu_page( 
    'makesf-stats', //string $parent_slug,
    __( 'Sign-in Schedule', 'makesantafe' ), //string $page_title,
    __( 'Sign-in Schedule', 'makesantafe' ), //string $menu_title,
    'manage_options',  //string $capability,
    'sign-in-schedule', //string $menu_slug,
    'makesf_display_signin_schedule', //callable $callback = '',
    null  //int|float $position = null
  );



  add_action( 'admin_print_scripts-' . $sign_in, 'makesf_stats_enqueue_scripts' );
  add_action( 'admin_print_scripts-' . $schedule, 'makesf_schedule_enqueue_scripts' );
});


function makesf_stats_enqueue_scripts() {
  wp_enqueue_style( 'makesf-stats', MAKESF_URL . 'assets/css/stats.css', array(), MAKESF_PLUGIN_VERSION);
  wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), MAKESF_PLUGIN_VERSION, true );
  wp_enqueue_script( 'makesf-stats', MAKESF_URL . 'assets/js/stats.js', array( 'chart-js', 'jquery' ), MAKESF_PLUGIN_VERSION, true );
  wp_localize_script( 'makesf-stats', 'makeMember', array(
    'ajax_url' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'makesf_stats_nonce' ),
    'stats' => array(
      'labels' => makesf_get_signin_labels(),
      'data' => makesf_get_signin_data(),
    ),
  ));
}
function makesf_schedule_enqueue_scripts() {
  wp_enqueue_style( 'makesf-stats', MAKESF_URL . 'assets/css/stats.css', array(), MAKESF_PLUGIN_VERSION);
}



function makesf_display_stats_page() {
  echo '<div id="makesfStats">';
    echo '<h1>Sign-in Stats</h1>';
    echo '<div>';
      echo '<canvas id="numberSignIns"></canvas>';
    echo '</div>';

    echo '<h1>Sign-in Leaderboard</h1>';
    echo '<div class="sign-ins-by-user">';
      
      $users = get_users_that_sign_in();

      $total_signins = array();
      foreach($users as $key => $value) :
        $user = get_user_by('id', $key);
        if($user) :
          echo '<div class="user">';
            echo '<div class="top-card">';
              echo '<div class="user-avatar">';
                $thumb = get_field('photo', 'user_' . $user->ID);
                if($thumb) :
                  echo wp_get_attachment_image( $thumb['ID'], 'small-square', false, array('class' => 'rounded-circle'));
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
                foreach($sign_ins as $key => $value) :
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
      foreach($total_signins as $key => $value) :
        echo '<li><strong>' . $key . '</strong>: ' . $value . '</li>';
      endforeach;
      echo '</ul>';
    echo '</div>';
  echo '</div>';
  // get_users_that_sign_in();
}


/*
This function displays a schedule of sign ins for the past 30 days. 
*/
function makesf_display_signin_schedule() {
  global $wpdb;
  $timezone = new DateTimeZone('America/Denver');
  $today = new DateTimeImmutable('now', $timezone);
  $interval = new DateInterval('P1D');
  $period = new DatePeriod($today->sub(new DateInterval('P29D')), $interval, 30);
    echo '<div id="makesfSchedule">';
    echo '<h1>Sign-in Schedule</h1>';
      foreach (array_reverse(iterator_to_array($period)) as $date) :
      
        $month = $date->format('m');
        $year = $date->format('Y');
        $day = $date->format('d');

        $results = $wpdb->get_results("SELECT * FROM `make_signin` WHERE MONTH(time) = $month AND YEAR(time) = $year AND DAY(time) = $day");
        if(!$results) :
          continue;
        endif;


        $first_signin = $results[0]->time;
        $first_signin_time = new DateTime($first_signin, $timezone);
        $first_signinminutes_since_1am = ($first_signin_time->format('H')) * 60 + $first_signin_time->format('i');
        echo '<div class="day-container">';
        
          echo '<h2>' . $date->format('D, M j') . '</h2>';
          echo '<div class="daily-signins">'; 
            foreach ($results as $signin) :
              $badges = unserialize($signin->badges);
              $user = get_user_by('id', $signin->user);
              if($user) :
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
                    foreach($badges as $badge) :
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

function makesf_get_past_year_dates($date = 'now') {
  
  $month = date("m", strtotime($date));
  $year = date("Y", strtotime($date));
  for ($i = 0; $i < 24; $i++) {
    if($month < 1) :
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


function makesf_get_signin_labels() {
  $dates = makesf_get_past_year_dates();
  foreach($dates as $date) {
    $labels[] = date('F, Y', mktime(0, 0, 0, $date['month'], 10, $date['year']));
  }

  $labels = array_values($labels);
  return $labels;
}


function makesf_get_signin_data() {
  global $wpdb;
  $labels = makesf_get_signin_labels();
  $number_signins = array();
  $badge_signins = array();
  $dates = makesf_get_past_year_dates();
  
  foreach($dates as $key => $date) {
    $month = $date['month'];
    $year = $date['year'];
    $results = $wpdb->get_results("SELECT * FROM `make_signin` WHERE MONTH(time) = $month AND YEAR(time) = $year");
    foreach($results as $result) {
      $badges = unserialize($result->badges);
      foreach($badges as $badge) {
        $badge_signins[$badge][$labels[($key)]] = (isset($badge_signins[$badge][$labels[($key)]]) ? $badge_signins[$badge][$labels[($key)]] + 1 : 1);
      }
    }
    $number_signins[$labels[$key]] = count($results);
  }
  //Badge_signins now has an array of badge signins for each month
  $datasets = array();
  foreach($badge_signins as $key => $value) {
    
    $label = (is_int($key) ? get_the_title($key) : $key);
    if(!$label) :
      $label = 'Badge Removed';
    endif;
    $datasets[] = array(
      'type' => 'line',
      'label' => html_entity_decode($label),
      'data' => $value,
      'borderWidth' => 3,
    );
    
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

function get_users_that_sign_in() {
  global $wpdb;
  $results = $wpdb->get_results("SELECT user, count(user) AS 'signins' FROM `make_signin` GROUP BY user ORDER BY 2 DESC;");
  $users = array();
  foreach($results as $result) {
    $users[$result->user] = $result->signins;
  }
  
  return $users;
}


function get_user_singins($user_id) {
  global $wpdb;
  $results = $wpdb->get_results("SELECT * FROM `make_signin` where user = $user_id;");
  $badge_signins = array();
  foreach($results as $result) {
    $badges = unserialize($result->badges);
    foreach($badges as $badge) {
      $badge_signins[get_badge_name_by_id($badge)][] = $result->time;
    }
  }
  
  array_multisort(array_map('count', $badge_signins), SORT_DESC, $badge_signins);
  return $badge_signins;
}


function get_badge_name_by_id($id) {
  if($id == 'workshop') :
    $title ='Attended Workshop';
  elseif($id == 'volunteer') :
    $title ='Volunteered';
  elseif($id == 'other') :
    $title ='Other';
  else :
    $title = get_the_title($id);
  endif;
  return  $title;
}



//Gets all current members and returns an arrayt of user objects
function make_get_active_members(){
  global $wpdb;
  // Getting all User IDs and data for a membership plan
  return $wpdb->get_results( "
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
}


//Get's all current members and returns them as an array of user ids
function make_get_active_members_array(){
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

  $members_array = array();
  foreach($members as $member) {
    $members_array[] = $member->user_id;
  }

  return $members_array;

}





function make_get_upcoming_events($num = 3, $ticketed = true, $args = array(), $page = 1, $upcoming_events = array()) {


  $default_args = array(
      'post_type' => 'sub_event',
      'posts_per_page' => ($num > 12 ? $num : 12),
      'meta_key'       => 'event_time_stamp', // Meta field for the event start date
      'orderby'        => 'meta_value', // Order by the event start date
      'order'          => 'ASC', // Ascending order (earliest events first)
      'paged'          => $page,
      'meta_query'     => array(
          array(
              'key'     => 'event_time_stamp',
              'value'   => date('Y-m-d H:i:s'),
              'compare' => '>=', // Only get events starting after the current date
              'type'    => 'DATETIME',
          ),
      ),
  );
  $args = wp_parse_args($args, $default_args);
  
  $events = new WP_Query($args);
  
  if($events->have_posts()) :
      while($events->have_posts()) :
          $events->the_post();
          if(count($upcoming_events) < $num) :
              if($ticketed) :
                  if(make_event_has_available_tickets(get_the_id())) :
                      $upcoming_events[get_the_id()] = get_the_title();
                  endif;
              else :
                  $upcoming_events[get_the_id()] = get_the_title();
              endif;  
          endif;
          
      endwhile;
  endif;


  if(count($upcoming_events) < $num) :
      $page = $page + 1;
      if($events->max_num_pages >= $page) :
          $args['paged'] = $page;
          $upcoming_events = make_get_upcoming_events($num, $ticketed, $args, $page, $upcoming_events);
      else :
          return $upcoming_events;    
      endif;    
  endif;

  return $upcoming_events;
}





