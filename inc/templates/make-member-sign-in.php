<?php

/**
 * MAKE Member Sign In
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $r_post_id The post ID this block is saved to.
 */

// Create id attribute allowing for custom "anchor" value.
$id = 'make-member-sign-in-' . $block['id'];
if( !empty($block['anchor']) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$className = 'make-member-sign-in';
if( !empty($block['className']) ) {
  $className .= ' ' . $block['className'];

}
if( !empty($block['align']) ) {
  $className .= ' align' . $block['align'];
}


echo '<div class="' . $className . '" id= "' . $id .'">';

  echo '<div id="MAKEMemberSignIn">';
    echo '<form id="makeMemberSearch">';

      echo '<div class="form-group">';
        echo '<label for="makerEmail">Email Address</lable>';
        echo '<input class="form-control" type="text" id="makerEmail" name="makerEmail" placeholder="Email Address" required>';
      echo '</div>';


      echo '<button type="submit" class="mt-4 btn btn-primary">Search</button>';
    echo '</form>';
  echo '</div>';


  echo '<div id="makerProfileReturn"></div>';


echo '</div>';

