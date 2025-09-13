<?php
if ( ! defined('ABSPATH') ) exit;

class MCS_Logger {
  const OPT_KEY = 'mcs_logs';
  const MAX = 200;

  public static function log($level, $message, $context = []) {
    $logs = get_option(self::OPT_KEY, []);
    $logs[] = [
      'time' => current_time('mysql'),
      'level' => strtoupper($level),
      'message' => $message,
      'context' => $context,
    ];
    if (count($logs) > self::MAX) {
      $logs = array_slice($logs, -self::MAX);
    }
    update_option(self::OPT_KEY, $logs, false);
  }

  public static function get_logs($limit = 50) {
    $logs = get_option(self::OPT_KEY, []);
    return array_slice(array_reverse($logs), 0, $limit);
  }

  public static function clear() {
    delete_option(self::OPT_KEY);
  }
}
