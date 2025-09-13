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

  public static function warning($message, $context = []) {
    self::log('WARNING', $message, $context);
  }

  public static function error($message, $context = []) {
    self::log('ERROR', $message, $context);
  }

  public static function get_warning_error_summary($limit = 10) {
    $logs = get_option(self::OPT_KEY, []);
    $filtered = array_filter($logs, function($log) {
      return in_array($log['level'], ['WARNING', 'ERROR'], true);
    });
    return array_slice(array_reverse($filtered), 0, $limit);
  }
}
