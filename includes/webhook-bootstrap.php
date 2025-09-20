<?php
/**
 * Webhook System Bootstrap
 * Initializes the MinPaku Suite webhook system for booking and payment events
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load webhook system components
require_once __DIR__ . '/Webhook/WebhookSigner.php';
require_once __DIR__ . '/Webhook/WebhookQueue.php';
require_once __DIR__ . '/Webhook/WebhookDispatcher.php';
require_once __DIR__ . '/Webhook/WebhookWorker.php';
require_once __DIR__ . '/Webhook/WebhookApiController.php';
require_once __DIR__ . '/Webhook/Hooks.php';

// Initialize webhook system on plugins loaded
add_action('plugins_loaded', function() {
    // Initialize webhook worker (handles cron scheduling)
    new WebhookWorker();

    // Initialize webhook hooks (connects to BookingService)
    new WebhookHooks();

    // Initialize admin interface
    if (is_admin()) {
        require_once __DIR__ . '/Webhook/WebhookAdmin.php';
        new WebhookAdmin();
    }

    // Log webhook system initialization
    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('INFO', 'Webhook system initialized', [
            'worker_scheduled' => wp_next_scheduled(WebhookWorker::getCronHook()) !== false,
            'supported_events' => WebhookDispatcher::getSupportedEvents(),
            'admin_ui_loaded' => is_admin()
        ]);
    }
}, 30);

// Initialize REST API on rest_api_init
add_action('rest_api_init', function() {
    $webhook_api = new WebhookApiController();
    $webhook_api->register_routes();

    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('DEBUG', 'Webhook REST API routes registered', [
            'namespace' => 'minpaku/v1',
            'endpoints' => ['deliveries', 'redeliver', 'test', 'dispatch', 'stats', 'process']
        ]);
    }
});

// Add webhook management capability on user role setup
add_action('init', function() {
    // Webhook management is covered by existing manage_minpaku capability
    // No additional capabilities needed
}, 30);

// Plugin activation hook for webhooks
register_activation_hook(__FILE__, function() {
    // Ensure webhook worker is scheduled
    if (class_exists('WebhookWorker')) {
        $worker = new WebhookWorker();
        $worker->scheduleEvents();
    }

    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('INFO', 'Webhook system activated', [
            'worker_scheduled' => wp_next_scheduled(WebhookWorker::getCronHook()) !== false
        ]);
    }
});

// Plugin deactivation hook for webhooks
register_deactivation_hook(__FILE__, function() {
    // Unschedule webhook worker
    if (class_exists('WebhookWorker')) {
        $worker = new WebhookWorker();
        $worker->unscheduleEvents();
    }

    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('INFO', 'Webhook system deactivated');
    }
});

// Uninstall hook for complete removal
if (defined('WP_UNINSTALL_PLUGIN')) {
    add_action('uninstall_' . plugin_basename(__FILE__), function() {
        // Remove webhook settings
        delete_option('minpaku_webhook_settings');

        // Unschedule all webhook cron events
        wp_clear_scheduled_hook(WebhookWorker::getCronHook());
        wp_clear_scheduled_hook(WebhookWorker::getCleanupCronHook());

        // Webhook deliveries table is handled by main Migrations::dropTables()

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Webhook system uninstalled');
        }
    });
}

// Add admin post handlers for webhook actions
add_action('admin_post_webhook_force_processing', function() {
    if (!current_user_can('manage_minpaku')) {
        wp_die(__('Insufficient permissions', 'minpaku-suite'));
    }

    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'webhook_force_processing')) {
        wp_die(__('Security check failed', 'minpaku-suite'));
    }

    try {
        $worker = new WebhookWorker();
        $result = $worker->forceProcessing();

        $redirect_url = add_query_arg([
            'post_type' => 'property',
            'page' => 'minpaku-webhooks',
            'tab' => 'status',
            'processed' => $result['processed'],
            'succeeded' => $result['succeeded'],
            'failed' => $result['failed']
        ], admin_url('admin.php'));

    } catch (Exception $e) {
        $redirect_url = add_query_arg([
            'post_type' => 'property',
            'page' => 'minpaku-webhooks',
            'tab' => 'status',
            'error' => urlencode($e->getMessage())
        ], admin_url('admin.php'));
    }

    wp_redirect($redirect_url);
    exit;
});

add_action('admin_post_webhook_cleanup', function() {
    if (!current_user_can('manage_minpaku')) {
        wp_die(__('Insufficient permissions', 'minpaku-suite'));
    }

    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'webhook_cleanup')) {
        wp_die(__('Security check failed', 'minpaku-suite'));
    }

    try {
        $queue = new WebhookQueue();
        $settings = get_option('minpaku_webhook_settings', []);
        $retention_days = intval($settings['retention_days'] ?? 30);
        $deleted_count = $queue->cleanupOldDeliveries($retention_days);

        $redirect_url = add_query_arg([
            'post_type' => 'property',
            'page' => 'minpaku-webhooks',
            'tab' => 'status',
            'cleanup_count' => $deleted_count
        ], admin_url('admin.php'));

    } catch (Exception $e) {
        $redirect_url = add_query_arg([
            'post_type' => 'property',
            'page' => 'minpaku-webhooks',
            'tab' => 'status',
            'error' => urlencode($e->getMessage())
        ], admin_url('admin.php'));
    }

    wp_redirect($redirect_url);
    exit;
});

add_action('admin_post_retry_delivery', function() {
    if (!current_user_can('manage_minpaku')) {
        wp_die(__('Insufficient permissions', 'minpaku-suite'));
    }

    $delivery_key = sanitize_text_field($_GET['delivery_key'] ?? '');

    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'retry_delivery_' . $delivery_key)) {
        wp_die(__('Security check failed', 'minpaku-suite'));
    }

    try {
        $queue = new WebhookQueue();
        $success = $queue->resetForRetry($delivery_key);

        $redirect_url = add_query_arg([
            'post_type' => 'property',
            'page' => 'minpaku-webhooks',
            'tab' => 'deliveries',
            'retry_result' => $success ? 'success' : 'failed'
        ], admin_url('admin.php'));

    } catch (Exception $e) {
        $redirect_url = add_query_arg([
            'post_type' => 'property',
            'page' => 'minpaku-webhooks',
            'tab' => 'deliveries',
            'error' => urlencode($e->getMessage())
        ], admin_url('admin.php'));
    }

    wp_redirect($redirect_url);
    exit;
});

// Add CLI commands if WP-CLI is available
if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/Webhook/WebhookCLI.php';
}