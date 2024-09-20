<?php
if(!function_exists('mapi_var_dump')) {
    function mapi_var_dump($var) {
      if (current_user_can('administrator') && isset($var)) {
          echo '<pre>';
          var_dump($var);
          echo '</pre>'; 
      }
    }
  }
  
if(!function_exists('mapi_write_log')) {
    function mapi_write_log($message) {
        if ( WP_DEBUG === true ) {
            if ( is_array($message) || is_object($message) ) {
                error_log( print_r($message, true) );
            } else {
                error_log( $message );
            }
        }
    }
}





if(!function_exists('make_output_member_card')) :
    function make_output_member_card($maker, $echo = false, $args = array()) {
        if(!is_object($maker)) :
          $maker = get_user_by('ID', $maker);
        endif;
  
        $args = wp_parse_args($args, array(
          'show_badges' => true,
          'show_title' => true,
          'show_bio' => false,
          'show_gallery' => false,
          'show_photo' => true,
        ));
  
  
        $html = '';
          $name = (get_field('display_name', 'user_' . $maker->ID ) ? get_field('display_name', 'user_' . $maker->ID ) : $maker->display_name);
          $badges = ($args['show_badges'] ? get_field('certifications', 'user_' . $maker->ID ) : false);
          $title = ($args['show_title'] ? get_field('title', 'user_' . $maker->ID) : false);
          $bio = ($args['show_bio'] ? get_field('bio', 'user_' . $maker->ID) : false);
          $gallery = ($args['show_gallery'] ? get_field('image_gallery', 'user_' . $maker->ID) : false);
          $photo = ($args['show_photo'] ? get_field('photo', 'user_' . $maker->ID) : false);
          
          $link = get_author_posts_url($maker->ID);
          
  
          if(!$photo) {
            $image_url = get_template_directory_uri() . '/img/nophoto.svg';
            $image = '<img src="' . $image_url . '" class="rounded-circle">';
          } else {
            $image = wp_get_attachment_image( $photo['ID'], 'small-square', false, array('alt' => $name, 'class' => 'rounded-circle'));
          }
          $html .='<div class="col-6 col-md-4 text-center mb-2">';
            $html .='<div class="mb-4 text-center card make-member-card d-flex flex-column justify-content-start h-100">';
              if($image) :
                $html .='<div class="image profile-image p-3 w-75 mx-auto">';
                  $html .='<a href="' . $link . '">';
                    $html .= $image;
                  $html .='</a>';
                $html .='</div>';
              endif;
  
              $html .='<div class="content">';
                $html .='<a href="' . $link . '">';
                  $html .='<h5 class="text-center">' . $name . '</h5>';
                $html .='</a>';
                $html .= ($title ? '<p class="text-center small mb-0">' . $title . '</p>' : '');
              $html .='</div>';
  
              
              if($badges) :
                $html .= '<div class="maker-badges d-flex justify-content-center flex-wrap">';
                foreach($badges as $badge) :
                  if($image = get_field('badge_image', $badge)) :
                    $html .= '<a class="badge-image-holder m-1" href="' . get_permalink($badge) . '">';
                      $html .= wp_get_attachment_image($image);
                      $html .= '<span class="badge-name">' . get_the_title($badge) . '</span>';
                    $html .= '</a>';
                  endif;
                endforeach;
                $html .= '</div>';
              endif;
  
  
              if($gallery) :
                $html .= '<div class="maker-gallery d-flex justify-content-center flex-wrap w-100">';
                  $html .= '<div class="row w-100 my-3 gy-2">';
                  foreach ($gallery as $image) :
                    if($image['image'] != '') :
                      $image_elem = wp_get_attachment_image( $image['image']['ID'], array(100,100), false, array('class' => 'gallery-image'));
                      if($image_elem):
                        $html .= '<div class="col-3">' . $image_elem . '</div>';
                      endif;
                    endif;
                  endforeach;
  
                  $html .= '</div>';
                $html .= '</div>';
              endif;
            
  
  
            $html .='</div>';
          $html .='</div>';
  
      if($echo) :
        echo $html;
      else :
        return $html; 
      endif;
    }
  endif;