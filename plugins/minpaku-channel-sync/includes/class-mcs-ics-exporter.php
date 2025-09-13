<?php
if ( ! defined('ABSPATH') ) exit;

class MCS_ICS_Exporter {

  public static function output_ics_for_post($post_id) {
    $post = get_post($post_id);
    if ( ! $post || 'publish' !== $post->post_status ) {
      status_header(404);
      echo 'Not found';
      return;
    }

    $slots = get_post_meta($post_id, 'mcs_booked_slots', true);
    if ( ! is_array($slots) ) $slots = [];

    $site = get_bloginfo('name');
    $uid_base = wp_parse_url(home_url(), PHP_URL_HOST);
    $post_title = $post->post_title;

    $lines = [];
    $lines[] = 'BEGIN:VCALENDAR';
    $lines[] = 'VERSION:2.0';
    $lines[] = 'PRODID:-//Minpaku Channel Sync//EN';
    $lines[] = 'CALSCALE:GREGORIAN';

    foreach ($slots as $i => $slot) {
      $start = isset($slot[0]) ? intval($slot[0]) : 0;
      $end   = isset($slot[1]) ? intval($slot[1]) : 0;
      if (!$start || !$end) {
        MCS_Logger::log('WARNING', 'Invalid slot skipped during ICS export', [
          'post_id' => $post_id,
          'slot_index' => $i,
          'start' => $start,
          'end' => $end
        ]);
        continue;
      }
      $uid = isset($slot[3]) && $slot[3] ? $slot[3] : sprintf('%d-%d-%d@%s', $post_id, $start, $end, $uid_base);
      $lines[] = 'BEGIN:VEVENT';
      $lines[] = self::fold_line('UID:' . $uid);
      $lines[] = self::fold_line('DTSTAMP:' . gmdate('Ymd\THis\Z'));
      $lines[] = self::fold_line('DTSTART:' . gmdate('Ymd\THis\Z', $start));
      $lines[] = self::fold_line('DTEND:' . gmdate('Ymd\THis\Z', $end));
      // SUMMARY must include post title as per requirements
      $summary = sprintf('%s - Booked', $post_title);
      $lines[] = self::fold_line('SUMMARY:' . self::escape_text($summary));
      $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';
    $ics = implode("\r\n", $lines) . "\r\n";

    // Get export disposition setting
    $settings = MCS_Settings::get();
    $disposition = $settings['export_disposition'];

    nocache_headers();
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: ' . $disposition . '; filename="property-' . $post_id . '.ics"');
    echo $ics;
  }

  private static function escape_text($text) {
    // Basic iCal escaping
    return str_replace(
      ['\\', ';', ',', "\n", "\r"],
      ['\\\\', '\;', '\,', '\\n', ''],
      $text
    );
  }

  /**
   * Fold lines at 75 bytes as per RFC5545
   * Lines longer than 75 octets are folded with CRLF followed by a space
   * @param string $line
   * @return string
   */
  private static function fold_line($line) {
    // Convert to bytes for accurate measurement (UTF-8 support)
    $bytes = strlen($line);
    if ($bytes <= 75) {
      return $line;
    }

    $folded = [];
    $pos = 0;
    while ($pos < $bytes) {
      if ($pos === 0) {
        // First line can be up to 75 octets
        $chunk = substr($line, 0, 75);
        $folded[] = $chunk;
        $pos = 75;
      } else {
        // Continuation lines start with space and can be 74 octets (75 - 1 space)
        $chunk = substr($line, $pos, 74);
        if ($chunk !== '') {
          $folded[] = ' ' . $chunk;
          $pos += 74;
        } else {
          break;
        }
      }
    }
    return implode("\r\n", $folded);
  }
}
