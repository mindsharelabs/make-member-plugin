<?php

/**
 * Make Track Courses
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $r_post_id The post ID this block is saved to.
 */

// Create id attribute allowing for custom "anchor" value.
$id = 'make-track-course-list-' . $block['id'];
if( !empty($block['anchor']) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$className = 'make-track-course-list';
if( !empty($block['className']) ) {
  $className .= ' ' . $block['className'];

}
if( !empty($block['align']) ) {
  $className .= ' align' . $block['align'];
}


$make_track_list = get_field('make_track_list');
$tracks = $make_track_list['tracks'];
$track_args = array(
  'post_type' => 'make_track',
  'posts_per_page' => -1
);

if($tracks) :
    $track_args['post__in'] = $tracks;
    $track_args['orderby'] = 'post__in';
endif;


$track_query = new WP_Query($track_args);
if($track_query->have_posts()) :

echo '<div class="' . $className . ' tracks" id= "' . $id .'">';
    echo '<div class="row gy-2">';
    while($track_query->have_posts()) :
        $track_query->the_post();


        echo '<div class="col-12 col-md-6 col-lg-4 mb-5">'; 
                echo '<div class="card track-card d-flex flex-column h-100">';
                    if(has_post_thumbnail( )) :
                        echo '<a href="' . get_the_permalink() . '">';
                            the_post_thumbnail( 'horz-thumbnail-lg', array('class' => 'card-img-top') );
                        echo '</a>'; 
                    endif;
                    echo '<div class="card-body">';
                        echo '<a href="' . get_the_permalink() . '">';
                            echo '<h2 class="display-5 card-title text-center">' . get_the_title() . '</h2>';
                        echo '</a>';
                        echo '<p class="card-text">' . get_the_excerpt() . '</p>';
                    echo '</div>';
                    echo '<div class="card-footer">';
                        $gained_badges = get_field('gained_badges');
                        if($gained_badges) :
                            echo '<h4 class="text-center h5 small">Gain these badges</h4>';
                            echo '<div class="badges">';
                            foreach($gained_badges as $badge):
                                if($image = get_field('badge_image', $badge)) :
                                    echo '<div class="badge-image" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' . get_the_title($badge) . '">';
                                        echo wp_get_attachment_image($image);
                                    echo '</div>';
                                endif;
                            endforeach;
                            echo '</div>';
                        endif;
                    echo '</div>';
                    echo '<a href="' . get_the_permalink() . '" class="track-button"><i class="fas fa-arrow-right"></i></a>';
                echo '</div>';
            echo '</div>';



    endwhile;
    echo '</div>';
echo '</div>';
endif;
