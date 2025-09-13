<?php
if ( ! defined('ABSPATH') ) exit;

class MCS_ICS_Importer {

  public static function run() {
    $opts = MCS_Settings::get();
    $mappings = $opts['mappings'];
    if (empty($mappings)) {
      MCS_Logger::log('INFO', 'No ICS mappings configured.');
      return;
    }

    $total_added = 0; $total_updated = 0; $total_skipped = 0; $total_errors = 0;
    $results_by_url = [];

    foreach ($mappings as $mapping) {
      $url = $mapping['url'];
      $post_id = $mapping['post_id'];
      
      $url_added = 0; $url_updated = 0; $url_skipped = 0;
      
      $res = wp_remote_get($url, ['timeout' => 10]);
      if (is_wp_error($res)) {
        $total_errors++;
        MCS_Logger::log('ERROR', 'Fetch failed', ['url' => $url, 'post_id' => $post_id, 'error' => $res->get_error_message()]);
        continue;
      }
      
      $code = wp_remote_retrieve_response_code($res);
      $body = wp_remote_retrieve_body($res);
      if ($code !== 200 || empty($body)) {
        $total_errors++;
        MCS_Logger::log('ERROR', 'Invalid response', ['url' => $url, 'post_id' => $post_id, 'code' => $code]);
        continue;
      }

      $events = self::parse_ics($body);
      if (empty($events)) {
        MCS_Logger::log('INFO', 'No events found in ICS', ['url' => $url, 'post_id' => $post_id]);
        continue;
      }

      $merge_result = self::apply_to_post($post_id, $events);
      $url_added = $merge_result['added'];
      $url_updated = $merge_result['updated'];
      $url_skipped = $merge_result['skipped'];
      
      $total_added += $url_added;
      $total_updated += $url_updated;
      $total_skipped += $url_skipped;
      
      $results_by_url[$url] = [
        'added' => $url_added,
        'updated' => $url_updated,
        'skipped' => $url_skipped
      ];
      
      MCS_Logger::log('INFO', 'URL processed', [
        'url' => $url,
        'post_id' => $post_id,
        'added' => $url_added,
        'updated' => $url_updated,
        'skipped' => $url_skipped
      ]);
    }

    $total_results = [
      'total' => compact('total_added', 'total_updated', 'total_skipped', 'total_errors'),
      'by_url' => $results_by_url
    ];
    
    // Store results for display in admin
    set_transient('mcs_last_sync_results', [
      'total' => [
        'added' => $total_added,
        'updated' => $total_updated,
        'skipped' => $total_skipped,
        'errors' => $total_errors
      ],
      'by_url' => $results_by_url
    ], 300); // 5 minutes

    MCS_Logger::log('INFO', 'Sync completed', $total_results['total']);
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

  private static function apply_to_post($post_id, $events) {
    $stored = get_post_meta($post_id, 'mcs_booked_slots', true);
    if ( ! is_array($stored)) $stored = [];
    
    $added = 0; $updated = 0; $skipped = 0;
    
    foreach ($events as $new_event) {
      $new_start = $new_event[0];
      $new_end = $new_event[1];
      $new_source = $new_event[2];
      $new_uid = isset($new_event[3]) ? $new_event[3] : null;
      
      $found_index = -1;
      
      // Find existing event by UID first, then by (start, end)
      foreach ($stored as $i => $existing) {
        $existing_uid = isset($existing[3]) ? $existing[3] : null;
        
        if ($new_uid && $existing_uid && $new_uid === $existing_uid) {
          $found_index = $i;
          break;
        } elseif (!$new_uid || !$existing_uid) {
          // Fallback to (start, end) matching
          if ($existing[0] == $new_start && $existing[1] == $new_end) {
            $found_index = $i;
            break;
          }
        }
      }
      
      if ($found_index >= 0) {
        // Check if update is needed
        $existing = $stored[$found_index];
        if ($existing[0] != $new_start || $existing[1] != $new_end || 
            (isset($existing[3]) ? $existing[3] : null) != $new_uid) {
          $stored[$found_index] = $new_event;
          $updated++;
        } else {
          $skipped++;
        }
      } else {
        // Add new event
        $stored[] = $new_event;
        $added++;
      }
    }
    
    update_post_meta($post_id, 'mcs_booked_slots', $stored);
    
    return compact('added', 'updated', 'skipped');
  }
}
