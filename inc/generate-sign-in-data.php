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



function makesf_delete_test_signins_last_years($user_id, $years = 3) {
  global $wpdb;
  $table = 'make_signin';
  $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
  $cutoff = (new DateTimeImmutable('now', $tz))->sub(new DateInterval('P' . (int)$years . 'Y'))->format('Y-m-d H:i:s');

  return $wpdb->query(
    $wpdb->prepare(
      "DELETE FROM {$table} WHERE user = %d AND time >= %s",
      (int) $user_id,
      $cutoff
    )
  );
}