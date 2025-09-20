<?php
/**
 * MinPaku Suite Bootstrap
 * Loads all system components and initializes the plugin
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load API hardening system
require_once __DIR__ . '/api-bootstrap.php';

// Log bootstrap completion
if (class_exists('MCS_Logger')) {
    add_action('plugins_loaded', function() {
        MCS_Logger::log('INFO', 'MinPaku Suite bootstrap completed', [
            'components' => [
                'api' => 'REST API endpoints with rate limiting and caching'
            ],
            'version' => '0.1.0'
        ]);
    }, 30);
}
