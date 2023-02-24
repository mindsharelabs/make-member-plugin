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
    
      echo '<h1 class="text-center mb-5 d-block">Member Sign In</h1>';
      echo '<div id="reader" class="mt-3" style="width:100%"></div>';
      echo '<div id="result"></div>';
      echo '<div id="signFooter" class="mt-2 sign-in-footer text-center"><button class="btn btn-info btn-lg sign-in-email">Sign in by email</button></div>';

  echo '</div>';



echo '</div>';

