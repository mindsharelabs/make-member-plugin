<?php
class Meetup_Event_Sync_Options {
    private $options;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
    }

    public function add_plugin_page() {
        add_options_page(
            'Meetup Event Sync Settings', 
            'Meetup Event Sync', 
            'manage_options', 
            'meetup-event-sync', 
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        $this->options = get_option('meetup_event_sync_options');
        ?>
        <div class="wrap">
            <h1>Meetup Event Sync Settings</h1>
            <form method="post" action="options.php">
            <?php
                settings_fields('meetup_event_sync_option_group');
                do_settings_sections('meetup-event-sync-admin');
                submit_button();
            ?>
            </form>
        </div>
        <?php
        $code = get_option('meetup_code');
        if(!$code) :
            $meetupAPI = new makeMeetupAPI();
        endif;


    }

    public function page_init() {
        register_setting(
            'meetup_event_sync_option_group',
            'meetup_event_sync_options',
            array($this, 'sanitize')
        );

        add_settings_section(
            'meetup_event_sync_setting_section',
            'Meetup API Settings',
            array($this, 'section_info'),
            'meetup-event-sync-admin'
        );

        add_settings_field(
            'client_id', 
            'Client ID', 
            array($this, 'client_id_callback'), 
            'meetup-event-sync-admin', 
            'meetup_event_sync_setting_section'
        );

        add_settings_field(
            'client_secret', 
            'Client Secret', 
            array($this, 'client_secret_callback'), 
            'meetup-event-sync-admin', 
            'meetup_event_sync_setting_section'
        );

        add_settings_field(
            'group_urlname', 
            'Group URL Name', 
            array($this, 'group_urlname_callback'), 
            'meetup-event-sync-admin', 
            'meetup_event_sync_setting_section'
        );
    }

    public function sanitize($input) {
        $sanitary_values = array();
        if (isset($input['client_id'])) {
            $sanitary_values['client_id'] = sanitize_text_field($input['client_id']);
        }
        if (isset($input['client_secret'])) {
            $sanitary_values['client_secret'] = sanitize_text_field($input['client_secret']);
        }
        if (isset($input['group_urlname'])) {
            $sanitary_values['group_urlname'] = sanitize_text_field($input['group_urlname']);
        }
        return $sanitary_values;
    }

    public function section_info() {
        echo 'Enter your Meetup API settings below:';
    }

    public function client_id_callback() {
        printf(
            '<input type="text" id="client_id" name="meetup_event_sync_options[client_id]" value="%s" />',
            isset($this->options['client_id']) ? esc_attr($this->options['client_id']) : ''
        );
    }

    public function client_secret_callback() {
        printf(
            '<input type="password" id="client_secret" name="meetup_event_sync_options[client_secret]" value="%s" />',
            isset($this->options['client_secret']) ? esc_attr($this->options['client_secret']) : ''
        );
    }

    public function group_urlname_callback() {
        printf(
            '<input type="text" id="group_urlname" name="meetup_event_sync_options[group_urlname]" value="%s" />',
            isset($this->options['group_urlname']) ? esc_attr($this->options['group_urlname']) : ''
        );
    }
}

if (is_admin())
    $meetup_event_sync_options = new Meetup_Event_Sync_Options();