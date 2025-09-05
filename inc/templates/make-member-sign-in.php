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
      // Badge selection styling overrides: align badges/buttons with card styles and reduce scroll
      echo '<style>
        /* Layout and spacing */
        #MAKEMemberSignIn #result .badge-list{margin-bottom:0}
        #MAKEMemberSignIn #result{padding-bottom:96px}
        #MAKEMemberSignIn #result .badge-footer{padding:16px 12px}
        #MAKEMemberSignIn .badge-list{gap:12px}
        /* Square badge cards (non-activity) */
        #MAKEMemberSignIn .badge-list .badge-item:not(.activity-item){
          width:200px; /* adjust as desired */
          aspect-ratio:1/1;
          margin:0;
          border:1px solid #efefef;
          border-radius:10px;
          background:#fff;
          padding:12px;
          box-shadow:0 0 10px 0 rgba(1,1,1,.1);
          transition:box-shadow 120ms ease,border-color 120ms ease;
          display:flex;flex-direction:column;align-items:center;justify-content:flex-start;overflow:hidden;
        }
        #MAKEMemberSignIn .badge-list .badge-item:not(.activity-item):hover{box-shadow:0 0 10px 0 rgba(1,1,1,.2);border-color:#dcdcdc}
        #MAKEMemberSignIn .badge-list .badge-item:not(.activity-item).selected{border:3px solid #be202e;box-shadow:0 0 0 3px rgba(190,32,46,.08) inset}
        #MAKEMemberSignIn .badge-list .badge-item:not(.activity-item) img{max-width:76%;max-height:60%;height:auto;object-fit:contain;margin-top:4px}
        #MAKEMemberSignIn .badge-list .badge-item:not(.activity-item) span{white-space:normal;word-break:break-word;text-align:center;margin-top:auto;padding:6px 6px;line-height:1.15}
        /* Full-width placement for other activities directly under badges */
        #MAKEMemberSignIn .badge-list .activity-item{width:100% !important;max-width:none;border:1px solid #efefef;border-radius:10px;background:#fff;padding:12px;box-shadow:0 0 10px 0 rgba(1,1,1,.1)}
        #MAKEMemberSignIn .badge-list .activity-item:hover{box-shadow:0 0 10px 0 rgba(1,1,1,.2);border-color:#dcdcdc}
        #MAKEMemberSignIn .badge-list .activity-item.selected{border:3px solid #be202e;box-shadow:0 0 0 3px rgba(190,32,46,.08) inset}
        #MAKEMemberSignIn .badge-list .activity-item h3{margin:8px 0}
      </style>';
    
      echo '<h1 id="makesf-signin-heading" class="text-center ' . (get_field('enable_member_notice', 'option') ? ' ' : 'mb-5 pb-5') . ' d-block display-1 strong"><strong>Member Sign In</strong></h1>';
      
      echo '<div id="memberList" class="mt-3" style="width:100%"></div>';
      echo '<div id="result"></div>';
      
      
  echo '</div>';



echo '</div>';
