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

// --- Load core functionality (guarded to avoid double-load with subplugin) ---
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$ics_local_path = __DIR__ . '/includes/class-mcs-ics.php';
$ics_subplugin  = 'minpaku-channel-sync/minpaku-channel-sync.php';
$ics_sub_active = function_exists('is_plugin_active') && is_plugin_active($ics_subplugin);

// サブプラグインが有効な場合は本体側の ICS を読み込まない（重複定義を回避）
if ( ! $ics_sub_active && file_exists($ics_local_path) ) {
    require_once $ics_local_path;
}

// 他コアは存在確認のうえ読み込む（存在しない場合も Fatal にしない）
foreach ([
    __DIR__ . '/includes/class-mcs-sync.php',
    __DIR__ . '/includes/class-mcs-cli.php',
    __DIR__ . '/includes/cpt-property.php',
] as $core_file) {
    if (file_exists($core_file)) {
        require_once $core_file;
    }
}


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

// Load all system components
require_once __DIR__ . '/includes/bootstrap.php';
