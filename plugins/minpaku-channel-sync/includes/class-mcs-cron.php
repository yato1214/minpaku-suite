<?php
if ( ! defined('ABSPATH') ) exit;

class MCS_Cron {

  public static function init() {
    add_filter('cron_schedules', [__CLASS__, 'add_schedules']);
  }

  public static function add_schedules($schedules) {
    if ( ! isset($schedules['2hours']) ) {
      $schedules['2hours'] = [
        'interval' => 2 * HOUR_IN_SECONDS,
        'display'  => __('Every 2 hours', 'minpaku-channel-sync')
      ];
    }
    if ( ! isset($schedules['6hours']) ) {
      $schedules['6hours'] = [
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => __('Every 6 hours', 'minpaku-channel-sync')
      ];
    }
    return $schedules;
  }

  public static function reschedule($interval) {
    wp_clear_scheduled_hook('mcs_sync_event');
    wp_schedule_event(time() + 60, $interval, 'mcs_sync_event');
    MCS_Logger::log('INFO', 'Cron rescheduled', ['interval' => $interval]);
  }
}
