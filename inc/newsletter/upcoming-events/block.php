<?php
/*
 * Name: Tribe Events
 * Section: content
 * Description: Extract the latest event from Tribe Events Calendar
 * Type: dynamic
 *
 */

/* @var $controls NewsletterControls */
/* @var $options array */
/* @var $wpdb wpdb */

if (!class_exists('Tribe__Events__Main')) {
    echo 'The Events Calendar plugin seems not active';
    return;
}

if (!function_exists('tribe_get_events')) {
    echo 'The tribe_get_events function is missing';
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
$filters['orderby'] = 'event_date';
$filters['order'] = empty($options['order']) ? 'DESC' : 'ASC';
$filters['start_date'] = 'now';

if (isset($options['tax_tribe_events_cat'])) {
    $filters['tax_query'] = array(
        array(
            'taxonomy' => 'tribe_events_cat',
            'field' => 'term_id',
            'terms' => $options['tax_tribe_events_cat']
        )
    );
}


if ($options['language']) {
    do_action('wpml_switch_language', $options['language']);
}

// https://theeventscalendar.com/knowledgebase/k/using-tribe_get_events/

$events = make_get_upcoming_events($options['max'], ($options['tickets'] == 'all' ? false : true), $filters );
// $events = tribe_get_events(array(
//     'post__in' => array_keys($event_list),
// ));

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

// This is only for Automated Addon
if (!empty($events)) {
    $out['subject'] = $events[0]->post_title;
}
?>


<?php if (!$events) { ?>

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
    $item = new stdClass();

    $item->show_date = !empty($options['show_date']);
    $item->show_image = !empty($options['image']);
    $item->show_location = !empty($options['show_location']);
    $item->show_time = !empty($options['show_time']);

    $item->url = get_permalink($event->ID);
    $item->excerpt = tnp_post_excerpt($event, $options['excerpt_length'], true);
    $item->title = get_the_title($event->ID);

    $item->all_day = $event->_EventAllDay == 'yes';

    // Date and Time
    $item->date = '';
    $item->time = '';
    if ($item->show_date) {
        $item->start_date = date_i18n($options['date_format'], strtotime($event->_EventStartDate));
        $item->end_date = date_i18n($options['date_format'], strtotime($event->_EventEndDate));
        $item->start_time = date_i18n($options['time_format'], strtotime($event->_EventStartDate));
        $item->end_time = date_i18n($options['time_format'], strtotime($event->_EventEndDate));

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
    if ($item->show_location) {
        $location = get_post($event->_EventVenueID);
        if ($location) {
            $item->location = new stdClass();
            $item->location->name = $location->post_title;
            $item->location->city = $location->_VenueCity;
            $item->location->address = $location->_VenueAddress;
        }
    }

    // Image
    $item->image = null;
    if ($item->show_image) {
        $item->image = tnp_resize_2x(TNP_Composer::get_post_thumbnail_id($event->ID), [600, 250, true]);
    }

    $items[] = $item;
}


include __DIR__ . '/layout.php';