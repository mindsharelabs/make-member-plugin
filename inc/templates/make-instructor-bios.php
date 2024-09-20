<?php

/**
 * MAKE Instructor Bios
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $post_id The post ID this block is saved to.
 */

mapi_write_log($post_id);

// Create id attribute allowing for custom "anchor" value.
$id = 'make-instructor-bios-' . $block['id'];
if( !empty($block['anchor']) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$className = 'make-instructor-bios';
if( !empty($block['className']) ) {
  $className .= ' ' . $block['className'];

}
if( !empty($block['align']) ) {
  $className .= ' align' . $block['align'];
}

$instructors = get_field('instructors', $post_id);
mapi_write_log($instructors);

if($is_preview) :
    echo '<div class="make-notice" style="padding: 10px; background: ##ebddb9; font-family:monospace; text-align:center; font-size:10px">';
    if(!$instructors) :
        echo 'Select instructors in the right sidebar to display their bios.';
    else :
        echo 'Instructor Bios will appear here when the event is published on the front end.';
    endif;
    echo '</div>'; 
else :


    echo '<div class="' . $className . '" id= "' . $id .'">';

        if($instructors) :
            echo '<div class="row"><div class="col-12"><h2 class="mt-4">Your Instructor' . (count($instructors) > 1 ? 's' : '') . '</h2></div></div>';
            echo '<section class="row makers">';
            foreach($instructors as $instructor) :
                $args = array(
                    'show_badges' => false,
                    'show_title' => true,
                    'show_bio' => false,
                    'show_gallery' => true,
                    'show_photo' => true,
                  );
                make_output_member_card($instructor->ID, true, $args);
            endforeach;
            echo '</section>';
        endif;
    echo '</div>';


endif;
