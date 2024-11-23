<?php
/**
 * Image Slider
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $post_id The post ID this block is saved to.
 */

// Create id attribute allowing for custom "anchor" value.
$id = 'make-image-slider-' . $block['id'];
if( !empty($block['anchor']) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$className = 'make-image-slider';
if( !empty($block['className']) ) {
    $className .= ' ' . $block['className'];
}
if( !empty($block['align']) ) {
    $className .= ' align' . $block['align'];
}

$block_image_slider = get_field('block_image_slider');

if($block_image_slider) :
echo '<div id="' . esc_attr($id) . '" class="' . esc_attr($className) . '">';

  echo '<div class="container">';
    echo '<div class="row">';


      echo '<div class="col-12 col-md-7 order-last">';
      if($block_image_slider['images']) :
        echo '<div class="gallery-images">';
          echo '<div class="gallery-controles">';
            echo '<span class="prev"><i class="fas fa-angle-left"></i></span>';
            echo '<span class="next"><i class="fas fa-angle-right"></i></span>';
          echo '</div>';
          echo '<div class="gallery-slideshow" data-blockID="make-image-slider-' . $block['id'] . '">';
            foreach ($block_image_slider['images'] as $key => $image) :
              echo '<div class="gallery-slide">';
                echo wp_get_attachment_image( $image['image']['id'], 'large', false, array('class' => 'w-100'));
                echo '<div class="caption">' . $image['caption'] . '</div>';
              echo '</div>';
            endforeach;
          echo '</div>';
        echo '</div>';
      endif;
      echo '</div>';


      echo '<div class="col-12 col-md-5 order-first bg-white my-auto">';
        echo '<div class="gallery-info pe-md-4">';
          echo ($block_image_slider['gallery_title'] ? '<h2 class="text-primary">' . $block_image_slider['gallery_title'] . '</h2>' : '');
          echo ($block_image_slider['gallery_description'] ? '<p class="desc">' . $block_image_slider['gallery_description'] . '</p>' : '');
        echo '</div>';
      echo '</div>';

    echo '</div>';
  echo '</div>';



echo '</div>';
endif;
