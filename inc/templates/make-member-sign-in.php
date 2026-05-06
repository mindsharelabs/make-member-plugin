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


  echo '<div class="' . esc_attr($className) . '" id="' . esc_attr($id) . '">';

  if(function_exists('make_can_access_member_signin') && !make_can_access_member_signin()) {
      echo '<div class="alert alert-warning text-center">';
      echo 'Please log in with your kiosk or staff account to access member sign in.';
      echo '</div>';
      echo '</div>';
      return;
  }

  echo '<div id="MAKEMemberSignIn">';
   
      echo '<h1 id="makesf-signin-heading" class="text-center mb-2 pb-2 d-block display-1 strong"><strong>Member Sign In</strong></h1>';
      
      echo '<div id="memberList" class="mt-3" style="width:100%"></div>';
      echo '<div id="result"></div>';
      
      
  echo '</div>';



echo '</div>';
