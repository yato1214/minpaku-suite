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

// Load all bootstrap components
require_once __DIR__ . '/portal-bootstrap.php';
require_once __DIR__ . '/provider-bootstrap.php';
require_once __DIR__ . '/ui-bootstrap.php';
require_once __DIR__ . '/api-bootstrap.php';
require_once __DIR__ . '/booking-bootstrap.php';
require_once __DIR__ . '/webhook-bootstrap.php';

// Log bootstrap completion
if (class_exists('MCS_Logger')) {
    add_action('plugins_loaded', function() {
        MCS_Logger::log('INFO', 'MinPaku Suite bootstrap completed', [
            'components' => [
                'portal' => 'Portal system with owner roles and subscriptions',
                'providers' => 'Channel and payment provider system',
                'ui' => 'Frontend UI components (calendar and quote)',
                'api' => 'REST API endpoints for availability and quotes',
                'booking' => 'Booking state machine and ledger system',
                'webhook' => 'Webhook system for booking and payment events'
            ],
            'version' => '0.1.0'
        ]);
    }, 30);
}
