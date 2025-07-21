<?php

/**
 * MAKE Instructor Bios
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $post_id The post ID this block is saved to.
 */


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

$instructors = make_get_event_instructors(get_the_id());

if($is_preview) :
    echo '<div class="make-notice" style="padding: 10px; background: ##ebddb9; font-family:monospace; text-align:center; font-size:10px">';
        echo 'Instructor Bios will appear here when the event is published on the front end.';
    echo '</div>'; 
else :


    echo '<div class="' . $className . '" id= "' . $id .'">';

        if($instructors) :
            
            echo '<div class="row"><div class="col-12"><h2 class="mt-4">Your Instructor</h2></div></div>';
            echo '<section class="row makers">';
            foreach($instructors as $instructor) :
                $args = array(
                    'show_badges' => false,
                    'show_title' => true,
                    'show_bio' => false,
                    'show_gallery' => false,
                    'show_photo' => true,
                  );
                make_output_member_card($instructor->ID, true, $args);
            endforeach;
            echo '</section>';
        endif;
    echo '</div>';


endif;
