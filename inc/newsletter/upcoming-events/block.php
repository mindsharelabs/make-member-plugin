<?php
/*
 * Name: Mindshare Single Events
 * Section: content
 * Description: Extract the latest events from Mindshare Events plugin.
 * Type: dynamic
 *
 */

/* @var $controls NewsletterControls */
/* @var $options array */
/* @var $wpdb wpdb */

if (!class_exists('mindEventCalendar')) {
    echo 'The Events Calendar plugin seems not active';
    return;
}
$default_options = array(
    'image' => 1,
    'max' => 5,
    'show_excerpt' => 1,
    'show_location' => 1,
    'show_date' => 1,
    'show_time' => 1,
    'image_size' => 'medium',
    'font_family' => '',
    'font_size' => '',
    'font_weight' => '',
    'font_color' => '',
    'title_font_family' => '',
    'title_font_size' => '',
    'title_font_weight' => '',
    'title_font_color' => '',
    'width' => 600,
    'image_width' => 600,
    'image_height' => 400,
    'image_crop' => 0,
    'language' => '',
    'order' => 1,
    'button_label' => 'Read more or book now!',
    'button_background' => '',
    'button_font_color' => '',
    'button_font_family' => '',
    'button_font_size' => '',
    'block_padding_left' => 15,
    'block_padding_right' => 15,
    'block_padding_top' => 15,
    'block_padding_bottom' => 15,
    'date_format' => get_option('date_format'),
    'time_format' => get_option('time_format'),
    'automated_no_events' => 'No new events by now!',
    'automated' => '',
    'excerpt_length' => 50,
    'tickets' => 'all',
);

// Backward compatibility
if (isset($options['automated_required'])) {
    $defaults['automated'] = '1';
}

$options = array_merge($default_options, $options);

$title_style = TNP_Composer::get_title_style($options, 'title', $composer);
$text_style = TNP_Composer::get_text_style($options, '', $composer);
$button_options = TNP_Composer::get_button_options($options, 'button', $composer);

// Filters to extract the events

$filters = [];
$filters['showposts'] = (int) $options['max'];
//$filters['post_type'] = 'tribe_events';
$filters['orderby'] = 'event_start_time_stamp';
$filters['order'] = empty($options['order']) ? 'DESC' : 'ASC';
$filters['start_date'] = 'now';

if (isset($options['tax_event_category'])) {
    $filters['tax_query'] = array(
        array(
            'taxonomy' => 'event_category',
            'field' => 'term_id',
            'terms' => $options['tax_event_category']
        )
    );
}


if ($options['language']) {
    do_action('wpml_switch_language', $options['language']);
}

$events = make_get_upcoming_events($options['max'], ($options['tickets'] == 'all' ? false : true), $filters );


// This is only for Automated Addon: if there are no events after the last sent newsletter
// block the generation or skip this block
if ($context['type'] === 'automated' && !empty($context['last_run'])) {
    if (empty($events)) {
        if ($options['automated'] == '1') {
            $out['stop'] = true;
            return;
        } elseif ($options['automated'] == '2') {
            $out['skip'] = true;
            return;
        }
    }
}

if (!$events) { ?>

    <style>
        .noevents {
            font-size: 14px;
            font-style: italic;
            color: #444444;
        }
    </style>


    <div inline-class="noevents"><?php echo wp_kses_post($options['automated_no_events']) ?></div>

<?php } ?>


<?php
// Build a list of items later rendered with different layouts
$items = [];
foreach ($events as $event_id => $event_title) {
    $event = get_post($event_id);
    $start_date = get_post_meta($event->ID, 'event_start_time_stamp', true);
    $end_date = get_post_meta($event->ID, 'event_start_time_stamp', true);
    $start_date_obj = new DateTime($start_date);
    $end_date_obj = new DateTime($end_date);

    $parent = $event->post_parent;

    

    $item = new stdClass();


    $item->start_date = $start_date_obj->format($options['date_format']);
    $item->end_date = $end_date_obj->format($options['date_format']);
    $item->start_time = $start_date_obj->format($options['time_format']);
    $item->end_time = $end_date_obj->format($options['time_format']);

    // $item->id = $event->ID;
    $item->show_date = !empty($options['show_date']);
    $item->show_image = !empty($options['image']);
    $item->show_location = !empty($options['show_location']);
    $item->show_time = !empty($options['show_time']);
    
    $item->url = get_permalink($parent);
    
    $item->excerpt = tnp_post_excerpt(get_post($parent), $options['excerpt_length'], true);
    $item->title = get_the_title($parent);

    $item->all_day = $event->_EventAllDay == 'yes';

    // Date and Time
    if ($item->show_date) {

        if ($item->start_date == $item->end_date) {
            $item->date = $item->start_date;
        } else {
            $item->date = $item->start_date . '&nbsp;-&nbsp;' . $item->end_date;
        }

        if ($item->show_time) {
            
            if ($item->start_time == $item->end_time) {
                $item->time = $item->start_time;
            } else {
                
                $item->time = $item->start_time . '&nbsp;-&nbsp;' . $item->end_time;
            }
        }
    }


    // Location
    $item->location = null;
    // if ($item->show_location) {
    //     $location = get_post($event->_EventVenueID);
    //     if ($location) {
    //         $item->location = new stdClass();
    //         $item->location->name = $location->post_title;
    //         $item->location->city = $location->_VenueCity;
    //         $item->location->address = $location->_VenueAddress;
    //     }
    // }

    // Image
    $item->image = null;
    if ($item->show_image) {
        $item->image = tnp_resize_2x(TNP_Composer::get_post_thumbnail_id($parent), [600, 250, true]);
    }

    $items[] = $item;
}


include __DIR__ . '/layout.php';