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

    $lines = [];
    $lines[] = 'BEGIN:VCALENDAR';
    $lines[] = 'VERSION:2.0';
    $lines[] = 'PRODID:-//Minpaku Channel Sync//EN';
    $lines[] = 'CALSCALE:GREGORIAN';

    foreach ($slots as $i => $slot) {
      $start = isset($slot[0]) ? intval($slot[0]) : 0;
      $end   = isset($slot[1]) ? intval($slot[1]) : 0;
      if (!$start || !$end) continue;
      $uid = isset($slot[3]) && $slot[3] ? $slot[3] : sprintf('%d-%d-%d@%s', $post_id, $start, $end, $uid_base);
      $lines[] = 'BEGIN:VEVENT';
      $lines[] = 'UID:' . $uid;
      $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
      $lines[] = 'DTSTART:' . gmdate('Ymd\THis\Z', $start);
      $lines[] = 'DTEND:'   . gmdate('Ymd\THis\Z', $end);
      $summary = sprintf('%s #%d booked', $site, $post_id);
      $lines[] = 'SUMMARY:' . self::escape_text($summary);
      $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';
    $ics = implode("\r\n", $lines) . "\r\n";

    nocache_headers();
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="property-' . $post_id . '.ics"');
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
}
