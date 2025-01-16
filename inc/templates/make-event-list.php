<?php

/**
 * MAKE Events List
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $r_post_id The post ID this block is saved to.
 */

// Create id attribute allowing for custom "anchor" value.
$id = 'make-event-list-' . $block['id'];
if( !empty($block['anchor']) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$className = 'make-event-list';
if( !empty($block['className']) ) {
  $className .= ' ' . $block['className'];

}
if( !empty($block['align']) ) {
  $className .= ' align' . $block['align'];
}

if(function_exists('tribe_get_events')) :
    $make_event_list = get_field('make_events_list');
    $cats = $make_event_list['categories'];

    $args = array(
        'posts_per_page' => ($make_event_list['events_per_page'] ? $make_event_list['events_per_page'] : 6),
        'start_date'     => 'now',
    );
    if(! empty($cats)) :
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'tribe_events_cat',
                'field' => 'term_id',
                'terms' => $cats,
            )
        );
    endif;


    $events = tribe_get_events($args);
    if($events) :

    echo '<div class="' . $className . '" id= "' . $id .'">';
        
            echo '<div class="row gy-2 mb-3">';
            foreach($events as $event) :
                $all_tickets = Tribe__Tickets__Tickets::get_all_event_tickets( $event->ID );
                
                    if($all_tickets) :
                        
                        foreach($all_tickets as $ticket) :
                            echo '<div class="col-6 col-md-4">';
                            $tickets_available = $ticket->stock > 0 ? true : false;
                            echo '<div class="card d-flex justify-content-between flex-column h-100">';
                                echo '<a href="' . $event->guid . '">';        
                                    echo '<h3 class="class-title h5 text-center">' . $event->post_title . '</h3>';
                                echo '</a>';
                                echo '<div class="class-date text-center">';
                                    echo '<strong>' . tribe_get_start_date($event->ID, false, 'l, M j') . '</strong><br/> ' . tribe_get_start_date($event->ID, false, 'g:sa') . ' - ' . tribe_get_end_date($event->ID, false, 'g:sa');
                                echo '</div>';
                                if($ticket->regular_price) :
                                    echo '<div class="price text-center mt-2 small">';
                                        echo '<span class="label fw-bold">Cost: </span class="label"> $' . $ticket->regular_price . '</span>';
                                    echo '</div>';
                                endif;


                                echo (!$tickets_available ? '<span class="class-full-label text-center text-danger">Class Full</span>' : '');
                                
                            echo '</div>';
                            
                            echo '</div>';
                        endforeach;
                        
                    endif;

                
            endforeach;
            echo '</div>';

    echo '</div>';
    endif;
endif;
