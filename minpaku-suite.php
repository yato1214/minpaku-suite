<?php
/*
Plugin Name: Minpaku Suite
Description: ICS 同期＆ユーティリティ
Version: 0.1.0
Requires at least: 6.0
Requires PHP: 8.1
Text Domain: minpaku-suite
*/
if (!defined('ABSPATH')) exit;

// Plugin activation hook
register_activation_hook(__FILE__, 'mcs_activate_plugin');

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'mcs_deactivate_plugin');

/**
 * Plugin activation callback
 */
function mcs_activate_plugin() {
    // Load ICS class to register rewrite rules
    require_once __DIR__ . '/includes/class-mcs-ics.php';
    MCS_Ics::flush_rewrite_rules();
}

/**
 * Plugin deactivation callback
 */
function mcs_deactivate_plugin() {
    // Flush rewrite rules to clean up
    flush_rewrite_rules();
}

// Load core functionality
require_once __DIR__ . '/includes/class-mcs-ics.php';
require_once __DIR__ . '/includes/class-mcs-sync.php';
require_once __DIR__ . '/includes/class-mcs-cli.php';
require_once __DIR__ . '/includes/cpt-property.php';

// Initialize ICS handler
add_action('init', ['MCS_Ics', 'init']);

// Initialize WP-CLI commands
add_action('init', ['MCS_CLI', 'init']);

// Load admin UI if in admin area
if (is_admin()) {
    require_once __DIR__ . '/admin/class-mcs-admin.php';
    add_action('plugins_loaded', ['MCS_Admin', 'init']);
}

// Add settings link to plugin actions
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    if (current_user_can('manage_options')) {
        $settings_link = '<a href="' . admin_url('admin.php?page=mcs-settings') . '">' . __('Settings', 'minpaku-suite') . '</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
});

// 必要ならここから読み込み
// require_once __DIR__ . '/includes/bootstrap.php';
