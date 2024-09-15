<?php

/**
 * MAKE Upcoming Events
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $r_post_id The post ID this block is saved to.
 */


function write_message_to_log($message) {
    if ( is_array($message) || is_object($message) ) {
        error_log( print_r($message, true) );
    } else {
        error_log( $message );
    }
}


// Create id attribute allowing for custom "anchor" value.
$id = 'make-upcoming-events-' . $block['id'];
if( !empty($block['anchor']) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$className = 'make-upcoming-events';
if( !empty($block['className']) ) {
  $className .= ' ' . $block['className'];

}
if( !empty($block['align']) ) {
  $className .= ' align' . $block['align'];
}



$settings = get_field('make_upcoming_events');
$args = array();
if($settings['event_categories']) {
    $args['tax_query'][] = array(
        'taxonomy' => 'tribe_events_cat',
        'field' => 'term-id',
        'terms' => $settings['event_categories'],
    );
}
$upcoming_events = make_get_upcoming_events($settings['num_events'] ?? 3, true, $args );


if($upcoming_events) :
echo '<div class="' . $className . '" id= "' . $id .'">';
    echo '<div class="row gy-3">';
    foreach($upcoming_events as $event => $title) :
        echo '<div class="col-12 col-md-6 col-lg-4">';
            echo '<div class="card">';
            mapi_write_log(has_post_thumbnail( $event ));
                if(has_post_thumbnail( $event ) && $settings['show_image']) {
                    echo get_the_post_thumbnail( $event, 'horizontal-media-image', array('class' => 'card-img-top w-100') );
                }
                echo '<div class="card-body">';
                    echo '<a href="' . get_the_permalink($event) . '">';
                        echo '<h5 class="card-title text-center fw-bold">' . get_the_title($event) . '</h5>';
                    echo '</a>';    
                    echo '<p class="card-tex text-center strong fw-bold">' . tribe_get_start_date($event, false, 'M j @ g:i a') . '</p>';
                    if($settings['show_excerpt']):
                        echo '<p class="excerpt">' . get_the_excerpt( $event ) . '</h5>';
                    endif;
                    
                    echo '<a href="' . get_the_permalink($event) . '" class="d-block mt-3 btn btn-primary">' . ($settings['button_labels'] ? $settings['button_labels'] : 'Learn More') . '</a>';
                echo '</div>';
            echo '</div>';
        echo '</div>';
    endforeach;
    echo '</div>';
echo '</div>';

endif;