<?php
/**
 * Template: My Badges (WooCommerce My Account)
 *
 * Expected context:
 * - $user_id (int) may be provided by the calling endpoint.
 *
 * Data is intentionally pluggable:
 * - All badges: filter `makesf_my_badges_all_badges`
 * - User-earned badges: filter `makesf_my_badges_user_badges`
 * - Badge sign-ins: filter `makesf_my_badges_signins`
 */

if (!defined('ABSPATH')) {
  exit;
}

$user_id = isset($user_id) ? (int) $user_id : get_current_user_id();

// Best practice: prefer controller-provided data.
// These vars are set by makeProfile::render_my_badges_page().
$profile = isset($profile) ? $profile : null;

$all_badges = apply_filters('makesf_my_badges_all_badges', $all_badges, $user_id);

$earned_badges = apply_filters('makesf_my_badges_user_badges', $earned_badges, $user_id);

$user_signins = apply_filters('makesf_my_badges_signins', $user_signins, $user_id);

echo '<div class="woocommerce-badges-content">';
  echo '<div class="mb-4">';
    echo '<h2 class="mb-1">My Badges</h2>';
    echo '<p class="text-muted mb-0">View your earned badges, expiration timelines, and weekly studio sign-ins.</p>';
  echo '</div>';


  echo '<div class="row g-3">';
    foreach ((array) $earned_badges as $badge):
      $badge_id = $badge;
      $is_earned = $badge_id && in_array($badge_id, $earned_badges);
      $badge_image = get_field('badge_image', $badge);
      $thumb_html = wp_get_attachment_image($badge_image, 'thumbnail', false, array('class' => 'img-fluid rounded card-img-top'));

      $title = get_the_title($badge);
      $badge_expiration_length = get_field('expiration_time', $badge); //this is stored in days
      $badge_last_signin_time = get_user_meta($user_id, $badge . '_last_time', true);

      //get last sign in time


      //add badge expiration length to last signin time to get expiration time, then calculate days until expiration
      $time_remaining = null;
      if ($is_earned && $badge_expiration_length && $badge_last_signin_time) {
        $expiration_time = strtotime($badge_last_signin_time) + ($badge_expiration_length * DAY_IN_SECONDS);
        $time_remaining = ceil(($expiration_time - time()) / DAY_IN_SECONDS);
      }


      // Per-badge weekly counts
      $year_week_counts = ($badge_id && isset($badge_week_counts[$badge_id])) ? $badge_week_counts[$badge_id] : array();

      // Card styling
      $earned_class = $is_earned ? 'is-earned' : 'is-missing';
      ?>

      <div class="col-12 col-lg-6">
        <div class="card h-100 <?php echo $earned_class; ?>" data-badge-id="<?php echo esc_attr($badge_id); ?>">
          <div class="card-header d-flex align-items-start gap-3">
            <div class="badge-image-wrapper" style="width:128px;height:128px;">
              <?php
              if (!empty($thumb_html)):
                echo $thumb_html;
              endif;
              ?>
            </div>
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <h3 class="h5 mb-1"><?php echo esc_html($title); ?></h3>
                <div class="text-muted small">
                  <?php 
                  echo '<span class="badge bg-success">Earned</span>';
                  if ($time_remaining) {
                    echo '<div class="row">';
                      if ((int)$time_remaining >= 0) {
                        echo '<div class="small faded mt-2">Expires in <strong>' . $time_remaining . '</strong> days</div>';
                      }
                    echo '</div>';
                  }
                  
                  
                  ?>
                </div>
              </div>
            </div>

          </div>
          

          <?php //card-body

            echo '<div class="card-body">';
              echo '<div class="row mt-1">';
                // Build a day-level lookup set for this badge.
                $signed_set = array();
                if (!empty($user_signins) && isset($user_signins[$badge_id]) && is_array($user_signins[$badge_id])) {
                  foreach ($user_signins[$badge_id] as $dt_str) {
                    if (empty($dt_str))
                      continue;
                    // MySQL datetime -> YYYY-mm-dd
                    $day = substr((string) $dt_str, 0, 10);
                    if ($day) {
                      $signed_set[$day] = true;
                    }
                  }
                }


                // Show the last 12 months (including current month)
                $months_back = 12;
                $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
                $month_cursor = new DateTimeImmutable('first day of this month', $tz);



                  for ($mi = 0; $mi < $months_back; $mi++) {
                    $m = $month_cursor->modify('-' . $mi . ' months');
                    $year = (int) $m->format('Y');
                    $month_num = (int) $m->format('n');
                    $month_label = $m->format('M Y');
                    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month_num, $year);

                    echo '<div class="col-12 col-md-6 col-xl-4 mb-1">';
                      echo '<div class="border rounded p-2">';
                        echo '<div class="w-100 mb-1 text-center">';
                          echo '<div class="fw-semibold small">' . esc_html($month_label) . '</div>';
                        echo '</div>';

                        // Day boxes (calendar-style). Add blanks until the first weekday.
                        $first_of_month = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month_num), $tz);
                        // 0 (Sun) ... 6 (Sat)
                        $start_offset = (int) $first_of_month->format('w');

                        // Use a 7-column grid so it reads like a calendar.
                        echo '<div class="d-grid w-100" style="grid-template-columns: repeat(7, 1fr); gap:.15rem;">';

                          // Leading blanks
                          for ($b = 0; $b < $start_offset; $b++) {
                            echo '<span class="border rounded bg-white" style="display:block;width:100%;aspect-ratio:1/1;visibility:hidden;"></span>';
                          }

                          // Actual days
                          for ($d = 1; $d <= $days_in_month; $d++) {
                            $ymd = sprintf('%04d-%02d-%02d', $year, $month_num, $d);
                            $is_signed = isset($signed_set[$ymd]);
                            $cell_class = $is_signed ? 'bg-success' : 'bg-white';
                            $title_attr = $ymd . ($is_signed ? ' signed in' : ' not signed in');

                            echo '<span class="border rounded ' . esc_attr($cell_class) . '" title="' . esc_attr($title_attr) . '" aria-label="' . esc_attr($title_attr) . '" style="display:block;width:100%;aspect-ratio:1/1;"></span>';
                          }

                        echo '</div>';

                      echo '</div>';
                    echo '</div>';
                  }

              echo '</div>'; // .row
            echo '</div>'; // .card-body


          ?>


          
        </div>
      </div>
    <?php endforeach; 
  echo '</div>';


  //display un-earned badges in a separate section
  $unearned_badges = array_diff($all_badges, $earned_badges);
  if (!empty($unearned_badges)):
    echo '<div class="mt-5 mb-3">';
      echo '<h2 class="mb-1">Missing Badges</h2>';
    echo '</div>';
    // Similar card layout for unearned badges, but with "is-missing" class and no sign-in calendar.
    echo '<div class="row g-3">';
      foreach ($unearned_badges as $badge):
        $badge_id = $badge;
        $badge_image = get_field('badge_image', $badge);
        $thumb_html = wp_get_attachment_image($badge_image, 'thumbnail', false, array('class' => 'img-fluid rounded card-img-top'));
        $title = get_the_title($badge);
        ?>
        <div class="col-12 col-lg-6">
          <div class="card h-100 is-missing" data-badge-id="<?php echo esc_attr($badge_id); ?>">
            <div class="card-header d-flex align-items-start gap-3">
              <div class="badge-image-wrapper" style="width:128px;height:128px;">
                <?php
                if (!empty($thumb_html)):
                  echo $thumb_html;
                endif;
                ?>
              </div>
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h3 class="h5 mb-1"><?php echo esc_html($title); ?></h3>
                  <div class="text-muted  small">
                    <span class="badge bg-danger">Not yet earned</span>
                  </div>
                </div>
              </div>
              </div>

          </div>
        </div>
      <?php endforeach; 
      
      echo '</div>';
  endif;
      


echo '</div>';