<?php
add_action( 'admin_menu', 'makesf_support_settings_page' );
add_action( 'admin_init', 'makesf_api_settings_init' );

function makesf_support_settings_page() {
    add_options_page(
      'Make SF Member Plugin Options',
      'Make SF Member Plugin Optionss',
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
      $forms->update_entry_property( $entry['id'], 'created_by', $user->id );
      update_user_meta( $user->id, 'waiver_complete', true );
    endif;
  endif;


}, 10, 2 );


/**
 * Register a custom menu page.
 */
add_action( 'admin_menu', function () {
	$menu = add_menu_page(
		__( 'Space Sign-in Stats', 'textdomain' ),
		'Sign-in Stats',
		'manage_options',
		'makesf-stats',
		'makesf_display_stats_page',
		'dashicons-chart-pie',
		76
	);

  add_action( 'admin_print_scripts-' . $menu, 'makesf_stats_enqueue_scripts' );


});


function makesf_stats_enqueue_scripts() {
  wp_enqueue_style( 'makesf-stats', MAKESF_URL . 'assets/css/stats.css', array(), MAKESF_PLUGIN_VERSION, 'all' );
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



function makesf_display_stats_page() {
  echo '<div id="makesfStats">';
    echo '<h1>Sign-in Stats</h1>';
    echo '<div>';
      echo '<canvas id="numberSignIns"></canvas>';
    echo '</div>';

    echo '<h1>Sign-in Leaderboard</h1>';
    echo '<div class="sign-ins-by-user">';
      
      $users = get_users_that_sign_in();
      foreach($users as $key => $value) :
        $user = get_user_by('id', $key);
        
        
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
                
                  echo '<li>' . $key . ': ' . count($value) . '</li>';
                  // echo '<ul>';
                  //   foreach($value as $date) :
                  //     echo '<li>' . date('F j, Y', strtotime($date)) . '</li>';
                  //   endforeach;
                  // echo '</ul>';
              
              endforeach;
            echo '</ul>';
          echo '</div>';
          
        echo '</div>';
      endforeach;

    echo '</div>';

  echo '</div>';
  get_users_that_sign_in();
}



function makesf_get_past_year_dates($date = 'now') {
  for ($i = 0; $i < 12; $i++) {
    $dates[] = array(
      'month' => date("m", strtotime( $date . " -$i months")),
      'year' => date("Y", strtotime( $date . " -$i months")),
    );

  }
  return array_reverse($dates);
}


function makesf_get_signin_labels() {
  $dates = makesf_get_past_year_dates();
  foreach($dates as $date) {
    $labels[] = date('F', mktime(0, 0, 0, $date['month'], 10));
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
      'borderWidth' => 1,
    );
    
  }

  //add total counts
  $datasets[] = array(
    'type' => 'bar',
    'label' => 'Number of Sign-ins',
    'data' => $number_signins,
    'borderWidth' => 1,
  );
  // mapi_write_log($datasets);

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
  else :
    $title = get_the_title($id);
  endif;
  return  $title;
}