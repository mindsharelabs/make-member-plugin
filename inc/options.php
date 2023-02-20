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
  mapi_write_log($entry);
  $update = update_user_meta( $entry['created_by'], 'waiver_complete', true );
  mapi_write_log($update);
}, 10, 2 );






