<?php

/**
 * MAKE Upcoming Events
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $r_post_id The post ID this block is saved to.
 */


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


if(!$is_preview) :
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


    
    echo '<div class="' . $className . '" id= "' . $id .'">';
        echo '<div class="row gy-3">';
        if($upcoming_events) :
            foreach($upcoming_events as $event => $title) :
                $all_tickets = Tribe__Tickets__Tickets::get_all_event_tickets( $event );
                echo '<div class="col-12 col-md-6 col-lg-3">';
                    echo '<div class="card d-flex flex-column h-100 justify-content-between">';
                        if(has_post_thumbnail( $event ) && $settings['show_image']) {
                            echo get_the_post_thumbnail( $event, 'horizontal-media-image', array('class' => 'card-img-top w-100') );
                        }
                        echo '<div class="card-body">';
                            echo '<a href="' . get_the_permalink($event) . '">';
                                echo '<h5 class="card-title text-center fw-bold">' . get_the_title($event) . '</h5>';
                            echo '</a>';    
                            echo '<p class="card-tex text-center strong fw-bold">' . tribe_get_start_date($event, false, 'M j @ g:i a') . '</p>';
                            
                            if($settings['show_price']) :
                                echo '<div class="price text-center mt-2 small">';
                                    echo '<span class="label fw-bold">Cost: </span class="label">' . tribe_get_cost($event, true) . '</span>';
                                echo '</div>';
                            endif;
                            
                            
                            if($settings['show_excerpt']):
                                echo '<p class="excerpt">' . get_the_excerpt( $event ) . '</h5>';
                            endif;
                            
                            
                        echo '</div>';
                        echo '<div class="card-footer">';
                            echo '<a href="' . get_the_permalink($event) . '" class="d-block my-2 btn btn-primary">' . ($settings['button_labels'] ? $settings['button_labels'] : 'Learn More') . '</a>';
                        echo '</div>';
                    echo '</div>';
                echo '</div>';
            endforeach;
        else :
            echo '<div class="col-12 my-03">';
                echo '<h3 class="text-center">No upcoming events found.</h3>';
            echo '</div>';
        endif;
        echo '</div>';
    echo '</div>';

    
endif;