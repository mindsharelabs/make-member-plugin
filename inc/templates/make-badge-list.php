<?php

/**
 * MAKE Badge List
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $r_post_id The post ID this block is saved to.
 */

// Create id attribute allowing for custom "anchor" value.
$id = 'make-badge-list-' . $block['id'];
if( !empty($block['anchor']) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$className = 'make-badge-list';
if( !empty($block['className']) ) {
  $className .= ' ' . $block['className'];

}
if( !empty($block['align']) ) {
  $className .= ' align' . $block['align'];
}


$make_badge_list = get_field('make_badge_list');
$badges = $make_badge_list['badges'];
$badge_args = array(
  'post_type' => 'certs',
  'posts_per_page' => -1
);

if($badges) :
  $badge_args['post__in'] = $badges;
  $badge_args['orderby'] = 'post__in';
endif;


$badge_query = new WP_Query($badge_args);
if($badge_query->have_posts()) :

echo '<div class="' . $className . '" id= "' . $id .'">';
    echo '<div class="row gy-2">';
    while($badge_query->have_posts()) :
        $badge_query->the_post();

        $badge_icon = wp_get_attachment_image( get_field('badge_image', get_the_id()), 'thumbnail', false );

        echo '<div class="col-6 col-md-4 col-lg-3">';
            echo '<div class="card p-2 badge-card text-center d-flex flex-column justify-content-center h-100">';
                echo ($badge_icon ? '<div class="badge-image">' . $badge_icon . '</div>' : null);
                echo '<div class="badge__title">';
                    echo '<a href="' . get_the_permalink() . '">';
                        echo '<h3 class="h5 text-center">' . get_the_title() . '</h3>';
                    echo '</a>';
                echo '</div>';
                $short_desc = get_field('short_description', get_the_id());
                if($short_desc) :
                    echo '<div class="badge__description">';
                        echo '<p>' . $short_desc . '</p>';
                    echo '</div>';
                endif;
            echo '</div>';
        echo '</div>';
    endwhile;
    echo '</div>';
echo '</div>';
endif;
