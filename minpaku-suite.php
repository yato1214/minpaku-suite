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

// Load core sync functionality
require_once __DIR__ . '/includes/class-mcs-sync.php';

// 必要ならここから読み込み
// require_once __DIR__ . '/includes/bootstrap.php';
