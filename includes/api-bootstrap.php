<?php
/**
 * API Bootstrap
 * Initializes the MinPaku Suite REST API
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load API service provider
require_once __DIR__ . '/Api/ApiServiceProvider.php';

// Initialize API on plugins loaded
add_action('plugins_loaded', function() {
    $api_provider = new ApiServiceProvider();
    $api_provider->init();

    // Log API initialization
    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('INFO', 'MinPaku API initialized', [
            'namespace' => ApiServiceProvider::NAMESPACE,
            'endpoints' => ['availability', 'quote']
        ]);
    }
}, 20);