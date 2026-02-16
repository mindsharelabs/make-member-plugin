<?php
/**
 * DEV ONLY: Generate test sign-in data for a user across the last 3 years.
 *
 * Usage:
 * - Set $user_id to your dev account user ID
 * - Provide $earned_badges as your earned badges array (or just badge IDs)
 * - Call makesf_generate_test_signins($user_id, $earned_badges);
 */
function makesf_generate_test_signins($user_id, $earned_badges, $years = 3) {
  global $wpdb;

  if (!$user_id) {
    return new WP_Error('missing_user_id', 'Missing user_id');
  }

  // Normalize $earned_badges into a flat array of badge IDs
  $badge_ids = array();
  foreach ((array) $earned_badges as $b) {
    if (is_string($b)) {
      $badge_ids[] = $b;
    } elseif (is_array($b) && !empty($b['id'])) {
      $badge_ids[] = (string) $b['id'];
    }
  }
  $badge_ids = array_values(array_unique(array_filter($badge_ids)));

  if (empty($badge_ids)) {
    return new WP_Error('no_badges', 'No earned badge IDs provided');
  }

  // Table name: adjust if your install uses a prefixed table name.
  // Your existing code uses 'make_signin' (no prefix), so we’ll keep that.
  $table = 'make_signin';

  $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
  $end   = new DateTimeImmutable('now', $tz);
  $start = $end->sub(new DateInterval('P' . (int)$years . 'Y'));

  // Config: tune these to change density
  $min_per_badge = 80;   // ~2/month over 3 years
  $max_per_badge = 260;  // ~1-2/week over 3 years

  // Used to spread sign-ins in a “realistic” way:
  // - Most sign-ins happen weekdays
  // - Some bursts (project weeks)
  $weekday_weights = array(
    0 => 0.35, // Sun
    1 => 1.10, // Mon
    2 => 1.25, // Tue
    3 => 1.30, // Wed
    4 => 1.25, // Thu
    5 => 1.05, // Fri
    6 => 0.55, // Sat
  );

  $inserted = 0;
  $by_badge = array_fill_keys($badge_ids, 0);

  foreach ($badge_ids as $badge_id) {
    $target = random_int($min_per_badge, $max_per_badge);

    for ($i = 0; $i < $target; $i++) {

      // Pick a random day in range, then weight by weekday preference
      // We try a few times to get a weighted day.
      $chosen = null;
      for ($tries = 0; $tries < 6; $tries++) {
        $span_seconds = $end->getTimestamp() - $start->getTimestamp();
        $t = $start->getTimestamp() + random_int(0, max(1, $span_seconds));
        $candidate = (new DateTimeImmutable('@' . $t))->setTimezone($tz)->setTime(0,0,0);

        $dow = (int) $candidate->format('w'); // 0=Sun
        $roll = mt_rand() / mt_getrandmax();
        $accept = min(1.0, $weekday_weights[$dow] / 1.30); // normalize

        if ($roll <= $accept) {
          $chosen = $candidate;
          break;
        }
      }
      if (!$chosen) {
        $chosen = (new DateTimeImmutable('@' . $t))->setTimezone($tz)->setTime(0,0,0);
      }

      // Random time-of-day (Make-ish hours: 9am–9pm)
      $hour   = random_int(9, 21);
      $minute = random_int(0, 59);
      $second = random_int(0, 59);

      $dt = $chosen->setTime($hour, $minute, $second);

      // Badge list stored on the signin row.
      // Mostly single badge, sometimes add a second badge to mimic multi-studio days.
      $badges = array($badge_id);

      if (count($badge_ids) > 1) {
        $multi_roll = mt_rand() / mt_getrandmax();
        if ($multi_roll < 0.12) { // ~12% multi-badge sign-ins
          $other = $badge_ids[array_rand($badge_ids)];
          if ($other !== $badge_id) {
            $badges[] = $other;
          }
        }
      }

      // Serialize to match your existing pattern.
      $badges_serialized = serialize($badges);

      // Insert row. Use explicit time rather than current_time().
      $result = $wpdb->insert(
        $table,
        array(
          'time'   => $dt->format('Y-m-d H:i:s'),
          'badges' => $badges_serialized,
          'user'   => (int) $user_id,
        ),
        array('%s', '%s', '%d')
      );

      if ($result) {
        $inserted++;
        $by_badge[$badge_id]++;
      }
    }
  }

  return array(
    'user_id'   => (int) $user_id,
    'badges'    => $badge_ids,
    'inserted'  => $inserted,
    'by_badge'  => $by_badge,
    'start'     => $start->format('Y-m-d'),
    'end'       => $end->format('Y-m-d'),
    'table'     => $table,
  );
}

function makesf_delete_test_signins_last_years($user_id, $years = 3, $badge_id = null) {
  global $wpdb;
  $table = 'make_signin';
  $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
  $cutoff = (new DateTimeImmutable('now', $tz))->sub(new DateInterval('P' . (int)$years . 'Y'))->format('Y-m-d H:i:s');

  if ($badge_id) {
    // Serialized array: match badge as string or int
    // This will match: s:2:"12"; or i:12;
    $like1 = '%s:' . strlen((string)$badge_id) . ':"' . $badge_id . '";%';
    $like2 = '%i:' . $badge_id . ';%';
    return $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM {$table} WHERE user = %d AND time >= %s AND (badges LIKE %s OR badges LIKE %s)",
        (int) $user_id,
        $cutoff,
        $like1,
        $like2
      )
    );
  } else {
    return $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM {$table} WHERE user = %d AND time >= %s",
        (int) $user_id,
        $cutoff
      )
    );
  }
}



function makesf_user_signin_meta_generator($user_id) {

  //we only want to run this once so lets set a user meta flag to check if we have already run this for this user
  $meta_key = 'signin_meta_generated';
  if(get_user_meta($user_id, $meta_key, true)) {
    return;
  }
  mapi_write_log("Generating signin meta for user $user_id");
  

  $signins = get_user_signins($user_id);
  $all_badges = get_all_badges(); //get all badges to compare against
  foreach ($all_badges as $badge) {
    $meta_key_time = $badge . '_last_time';
    $meta_key_count = $badge . '_total_count';
    if (isset($signins[$badge])) {
      //if the user has signed into this badge, update the last_time and total_count meta for that badge

      mapi_write_log("User $user_id has signed into badge " . get_the_title($badge) . " " . count($signins[$badge]) . " times. Updating meta.");
      

      $last_time = end($signins[$badge]);
      $total_count = count($signins[$badge]);
      update_user_meta($user_id, $meta_key_time, $last_time);
      update_user_meta($user_id, $meta_key_count, $total_count);
    } else {
      //if the user has not signed into this badge then get all event attends for this user and find the most recent time they attended an event associated with that badge, and update the last_time meta for that badge with that time
      $last_attend_time = get_last_attend_time_for_badge($user_id, $badge);
      if($last_attend_time) {
        mapi_write_log("User $user_id has not signed into badge " . get_the_title($badge) . ". Using last attend time: $last_attend_time");
        update_user_meta($user_id, $meta_key_count, 1);
      } else {
        mapi_write_log("User $user_id has not signed into badge " . get_the_title($badge) . " and has no attend record. Setting count to 0.");
        update_user_meta($user_id, $meta_key_count, 0);
      }
      //set the last_time to a date in the past to force these badges to show as expired in the UI, since the user has not actually signed into them
      //find badge expiration timeline and set last_time to that many days in the past so that the badge will show as expired in the UI
      $expiration_length = get_field('expiration_time', $badge); //this is stored in days
      if ($expiration_length) {
        $past_time = (new DateTimeImmutable("-$expiration_length days"))->format('Y-m-d H:i:s');
        update_user_meta($user_id, $meta_key_time, $past_time);
        mapi_write_log("Setting last_time for badge " . get_the_title($badge) . " to $past_time based on expiration length of $expiration_length days.");
      }



    }
  }

  // Set the flag to indicate that the meta has been generated for this user
  update_user_meta($user_id, $meta_key, true);

}



function get_user_signins($user_id){
    global $wpdb;
    $results = $wpdb->get_results("SELECT * FROM `make_signin` where user = $user_id;");
    $badge_signins = array();
    foreach ($results as $result) {
      $badges = unserialize($result->badges);
      foreach ($badges as $badge) {
        $badge_signins[$badge][] = $result->time;
      }
    }
    // Sort each badge's sign-ins by date (oldest to newest)
    foreach ($badge_signins as &$times) {
      sort($times);
    }
    unset($times);

    // array multisort by count (optional, for display)
    $keys = array_keys($badge_signins);
    array_multisort(array_map('count', $badge_signins), SORT_DESC, $badge_signins);
    $badge_signins = array_combine($keys, $badge_signins);
    
    return $badge_signins;
}

function get_all_badges(){
  $badges = get_posts(array(
    'post_type' => 'certs',
    'posts_per_page' => -1,
  ));
  return wp_list_pluck($badges, 'ID');
}


function get_last_attend_time_for_badge($user_id, $badge_id) {
  $events = get_events_associated_with_badge($badge_id);
  // mapi_write_log($events);
  //get attendees for those events
  foreach($events as $event_id) {
    $attendees = get_post_meta($event_id, 'attendees', true);
    foreach ($attendees as $sub_event_id => $sub_event_attendees) {
      foreach ($sub_event_attendees as $attendee) {
        if ($attendee['user_id'] == $user_id) {
          return get_post_meta($sub_event_id, 'event_start_time_stamp', true);
        }
      }
    }
  }
}




function get_events_associated_with_badge($badge_id) {
  // This function should return an array of event IDs that are associated with the given badge ID.


  $events = get_posts(array(
    'post_type' => 'events',
    'orderby' => 'meta_value',
    'meta_key' => 'first_event_date',
    'meta_type' => 'DATETIME',
    'meta_query' => array(
      array(
        'key' => 'badge_cert_id', // Assuming you have a meta field that stores associated badge IDs
        'value' => $badge_id, // Search for the badge ID in the serialized array
        'compare' => '='
      )
    ),
    'posts_per_page' => -1,
  ));
  
  return wp_list_pluck($events, 'ID');
}