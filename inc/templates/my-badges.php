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

      $post_data = get_post($badge);
      if(is_null($post_data))
        continue;

      $badge_id = $badge;
      $is_earned = true; // Since we're in the earned badges loop, this is always true for these items.
      $badge_image = get_field('badge_image', $badge);
      $thumb_html = wp_get_attachment_image($badge_image, 'thumbnail', false, array('class' => 'img-fluid rounded card-img-top'));

      $title = get_the_title($badge);
      $badge_expiration_length = get_field('expiration_time', $badge); //this is stored in days


      $badge_signins = (isset($user_signins[$badge_id]) ? $user_signins[$badge_id] : array());
      mapi_write_log("Badge " . get_the_title($badge_id) . " sign-in times: " . var_export($badge_signins, true));
      //get last item in badge_signins, which is the most recent signin time for this badge

      if(!empty($badge_signins)) {
        $badge_last_signin_time = end($badge_signins);
      } else {
        $badge_last_signin_time = get_user_meta($user_id, $badge . '_last_time', true);
      }
      
      if (empty($badge_last_signin_time)) {
        $badge_last_signin_time = null;
      }

      mapi_write_log("Badge " . get_the_title($badge_id) . " last signin time: " . var_export($badge_last_signin_time, true));
      

      //get last sign in time
      //add badge expiration length to last signin time to get expiration time, then calculate days until expiration
      $time_remaining = null;

      // Only compute expiration if we have a last sign-in time.
      // Note: `ceil()` on a negative fraction returns 0 (e.g. -0.2 -> 0),
      // so we use `floor()` when expired to ensure we get -1 immediately after passing.
      if ($is_earned && !empty($badge_expiration_length) && !empty($badge_last_signin_time)) {
        $last_ts = strtotime($badge_last_signin_time);

        if ($last_ts) {
          $expiration_time = $last_ts + ((int) $badge_expiration_length * DAY_IN_SECONDS);
          $seconds_remaining = $expiration_time - time();

          if ($seconds_remaining >= 0) {
            $time_remaining = (int) ceil($seconds_remaining / DAY_IN_SECONDS);
          } else {
            $time_remaining = (int) floor($seconds_remaining / DAY_IN_SECONDS);
          }
        }
      }

      $is_expired = ($time_remaining !== null && $time_remaining < 0);


      // Per-badge weekly counts
      $year_week_counts = ($badge_id && isset($badge_week_counts[$badge_id])) ? $badge_week_counts[$badge_id] : array();

      // Card styling
      $earned_class = $is_earned ? 'is-earned' : 'is-missing';
      $expired_class = $is_expired ? 'is-expired' : '';
      ?>

      <div class="col-12 col-lg-6">
        <div class="card h-100 <?php echo $earned_class; ?> <?php echo $expired_class; ?>" data-badge-id="<?php echo esc_attr($badge_id); ?>">
          <div class="card-header d-flex align-items-start gap-3">
            <div class="badge-image-wrapper" style="width:100px;height:100px;">
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
                  $threshold_days = 14;
                  $status = 'Earned';
                  $status_class = 'bg-success';

                  if ($time_remaining !== null && $time_remaining < 0) {
                    $status = 'Expired';
                    $status_class = 'bg-danger';
                  } elseif ($time_remaining !== null && $time_remaining <= $threshold_days) {
                    $status = 'Expiring Soon';
                    $status_class = 'bg-warning text-dark';
                  }

                  // Optional helper text under the status
                  $helper = '';
                  if ($time_remaining !== null) {
                    if ($time_remaining < 0) {
                      $helper = 'Expired ' . abs((int) $time_remaining) . ' days ago. Please re-take this badge class to earn it again.';
                    } elseif ($time_remaining == 0 || $time_remaining == -0) {
                      $helper = 'Expires today. Practice this badge to reset the timer.';
                    } elseif ($time_remaining == 1) {
                      $helper = 'Expires tomorrow. Practice this badge to reset the timer.';
                    } else {
                      $helper = 'Expires in ' . (int) $time_remaining . ' days.';
                    }
                  }

                  echo '<div>';
                    echo '<span class="badge ' . esc_attr($status_class) . '">' . esc_html($status) . '</span>';
                    if (!empty($helper)) {
                      echo '<div class="small faded mt-2">' . esc_html($helper) . '</div>';
                    }
                  echo '</div>';
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
                } else {
                  echo '<div class="col-12">';
                    echo '<p class="text-muted text-center mb-0">No sign-in data for the past 12 months available for this badge.</p>';
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
              <div class="badge-image-wrapper" style="width:100px;height:100px;">
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