<?php
/**
 * Plugin Name: Minpaku Suite
 * Plugin URI: https://github.com/yato1214/minpaku-suite
 * Description: Comprehensive suite for managing minpaku (vacation rental) properties with channel sync capabilities.
 * Version: 0.4.1
 * Author: Yato1214
 * Text Domain: minpaku-suite
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Network: false
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MINPAKU_SUITE_VERSION', '0.4.1');
define('MINPAKU_SUITE_PLUGIN_FILE', __FILE__);
define('MINPAKU_SUITE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MINPAKU_SUITE_PLUGIN_URL', plugin_dir_url(__FILE__));

function minpaku_suite_load_textdomain() {
    load_plugin_textdomain(
        'minpaku-suite',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'minpaku_suite_load_textdomain');

function minpaku_suite_load_ics() {
    $mcs_ics_path = WP_PLUGIN_DIR . '/minpaku-channel-sync/includes/class-mcs-ics.php';

    if (is_plugin_active('minpaku-channel-sync/minpaku-channel-sync.php')) {
        if (file_exists($mcs_ics_path)) {
            require_once $mcs_ics_path;
        }
    }

    $builtin_ics_path = MINPAKU_SUITE_PLUGIN_DIR . 'includes/class-ics.php';
    if (file_exists($builtin_ics_path) && !class_exists('MCS_Ics')) {
        require_once $builtin_ics_path;
    }
}

function minpaku_suite_init() {
    minpaku_suite_load_ics();

    if (file_exists(MINPAKU_SUITE_PLUGIN_DIR . 'includes/Bootstrap.php')) {
        require_once MINPAKU_SUITE_PLUGIN_DIR . 'includes/Bootstrap.php';

        if (class_exists('MinpakuSuite\Bootstrap')) {
            MinpakuSuite\Bootstrap::init();
        }
    }
}
add_action('init', 'minpaku_suite_init');

function minpaku_suite_activate() {
    flush_rewrite_rules();

    if (class_exists('MinpakuSuite\Bootstrap')) {
        MinpakuSuite\Bootstrap::activate();
    }
}
register_activation_hook(__FILE__, 'minpaku_suite_activate');

function minpaku_suite_deactivate() {
    flush_rewrite_rules();

    if (class_exists('MinpakuSuite\Bootstrap')) {
        MinpakuSuite\Bootstrap::deactivate();
    }
}
register_deactivation_hook(__FILE__, 'minpaku_suite_deactivate');
