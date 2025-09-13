<?php
if ( ! defined('ABSPATH') ) exit;

class MCS_ICS_Importer {

  public static function run() {
    $opts = MCS_Settings::get();
    $urls = $opts['ics_urls'];
    if (empty($urls)) {
      MCS_Logger::log('INFO', 'No ICS URLs configured.');
      return;
    }

    $added = 0; $updated = 0; $skipped = 0; $errors = 0;

    foreach ($urls as $url) {
      $res = wp_remote_get($url, ['timeout' => 15]);
      if (is_wp_error($res)) {
        $errors++;
        MCS_Logger::log('ERROR', 'Fetch failed', ['url' => $url, 'error' => $res->get_error_message()]);
        continue;
      }
      $code = wp_remote_retrieve_response_code($res);
      $body = wp_remote_retrieve_body($res);
      if ($code !== 200 || empty($body)) {
        $errors++;
        MCS_Logger::log('ERROR', 'Invalid response', ['url' => $url, 'code' => $code]);
        continue;
      }

      $events = self::parse_ics($body);
      // For MVP: map to a single post (first found) — later, map by UID/URL mapping
      // Here we simply store into a "global" resource post if needed.
      // For now, skip post mapping: just log count.
      $added += count($events);

      // TODO: Decide mapping to posts: by CPT, meta 'ics_source', or a designated "property" id list.
      // You may extend: self::apply_to_post($post_id, $events);
    }

    MCS_Logger::log('INFO', 'Sync done', compact('added','updated','skipped','errors'));
  }

  // Very simple ics parser for DTSTART/DTEND/UID inside VEVENT
  private static function parse_ics($ics) {
    $events = [];
    $lines = preg_split('/\r\n|\n|\r/', $ics);
    $in = false; $cur = ['UID'=>null,'DTSTART'=>null,'DTEND'=>null];
    foreach ($lines as $ln) {
      $ln = trim($ln);
      if ($ln === 'BEGIN:VEVENT') { $in = true; $cur = ['UID'=>null,'DTSTART'=>null,'DTEND'=>null]; continue; }
      if ($ln === 'END:VEVENT') {
        if ($cur['DTSTART'] && $cur['DTEND']) {
          $start = self::parse_dt($cur['DTSTART']);
          $end   = self::parse_dt($cur['DTEND']);
          $uid   = $cur['UID'] ?: null;
          if ($start && $end) $events[] = [$start,$end,'import',$uid];
        }
        $in = false; continue;
      }
      if ($in) {
        if (stripos($ln, 'UID:') === 0) {
          $cur['UID'] = substr($ln, 4);
        } elseif (stripos($ln, 'DTSTART') === 0) {
          $cur['DTSTART'] = preg_replace('/^DTSTART[^:]*:/', '', $ln);
        } elseif (stripos($ln, 'DTEND') === 0) {
          $cur['DTEND'] = preg_replace('/^DTEND[^:]*:/', '', $ln);
        }
      }
    }
    return $events;
  }

  private static function parse_dt($v) {
    // Accept YYYYMMDD or YYYYMMDDTHHMMSSZ
    $v = trim($v);
    if (preg_match('/^\d{8}$/', $v)) {
      $dt = DateTime::createFromFormat('Ymd', $v, new DateTimeZone('UTC'));
      return $dt ? $dt->getTimestamp() : 0;
    }
    if (preg_match('/^\d{8}T\d{6}Z$/', $v)) {
      $dt = DateTime::createFromFormat('Ymd\THis\Z', $v, new DateTimeZone('UTC'));
      return $dt ? $dt->getTimestamp() : 0;
    }
    // Fallback
    $ts = strtotime($v);
    return $ts ? $ts : 0;
  }

  // Placeholder for applying to a post (not wired in MVP)
  private static function apply_to_post($post_id, $events) {
    $stored = get_post_meta($post_id, 'mcs_booked_slots', true);
    if ( ! is_array($stored)) $stored = [];
    // TODO: diff merge: update or append by UID / by (start,end)
    foreach ($events as $e) {
      $stored[] = $e;
    }
    update_post_meta($post_id, 'mcs_booked_slots', $stored);
  }
}
