<?php
/**
 * Plugin Name: Minpaku Channel Sync
 * Description: iCal(ICS) import/export for stock sync (mini channel manager).
 * Version: 0.2.0
 * Author: Okamoto
 * Requires PHP: 8.1
 * Text Domain: minpaku-channel-sync
 */

if ( ! defined('ABSPATH') ) exit;

define('MCS_PLUGIN_FILE', __FILE__);
define('MCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MCS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once MCS_PLUGIN_DIR . 'includes/class-mcs-loader.php';

register_activation_hook(__FILE__, function() {
  if ( ! wp_next_scheduled('mcs_sync_event') ) {
    wp_schedule_event(time() + 60, 'hourly', 'mcs_sync_event');
  }
  // rewrite rules for ICS (support both with and without trailing slash)
  add_rewrite_rule('^ics/([0-9]+)\.ics/?$', 'index.php?mcs_ics_post_id=$matches[1]', 'top');
  flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
  wp_clear_scheduled_hook('mcs_sync_event');
  flush_rewrite_rules();
});
