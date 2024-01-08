<?php

/**
 * MAKE Blog Categories
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $r_post_id The post ID this block is saved to.
 */

// Create id attribute allowing for custom "anchor" value.
$id = 'make-blog-categories-' . $block['id'];
if( !empty($block['anchor']) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$className = 'make-blog-categories';
if( !empty($block['className']) ) {
  $className .= ' ' . $block['className'];

}
if( !empty($block['align']) ) {
  $className .= ' align' . $block['align'];
}

$cat_list = get_field('make_category_list');
$terms_args = array (
    'taxonomy' => 'category', //empty string(''), false, 0 don't work, and return empty array
    'orderby' => 'name',
    'order' => 'ASC',
    'hide_empty' => true, //can be 1, '1' too
);
if(! empty($cat_list['categories'])) :
    $terms_args['include'] =  $cat_list['categories'];
endif;
$terms = get_terms($terms_args);


echo '<div class="' . $className . '" id= "' . $id .'">';
    if($terms) :
        echo '<div class="row gy-2 mb-3">';
            foreach($terms as $term) :
                $image = get_field('category_image', $term);
                echo '<div class="col-12 col-md-6 col-lg-4">';
                    echo '<div class="card d-flex justify-content-between flex-column h-100">';
                        if($image) :
                            echo wp_get_attachment_image( $image['ID'], 'horz-thumbnail', false, array('class' => 'card-image-top w-100'));
                        endif;
                        echo '<div class="card-body">';
                            echo '<h5 class="card-title">' . $term->name . '</h5>';
                            echo ($term->description ? '<p class="card-text">' . $term->description . '</p>' : '');
                            echo '<a href="' . get_term_link($term) . '" class="btn btn-block btn-primary btn-sm">Read More <i class="fas fa-arrow-right"></i></a>';
                        echo '</div>';
                    echo '</div>';
                echo '</div>';
            endforeach;
        echo '</div>';
    endif;
echo '</div>';
