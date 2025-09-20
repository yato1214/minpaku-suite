<?php
/**
 * Webhook CLI Commands
 * WP-CLI commands for webhook management
 *
 * @package MinpakuSuite
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

require_once __DIR__ . '/WebhookDispatcher.php';
require_once __DIR__ . '/WebhookQueue.php';
require_once __DIR__ . '/WebhookWorker.php';

/**
 * Webhook management commands
 */
class WebhookCLI {

    /**
     * Process webhook queue
     *
     * ## OPTIONS
     *
     * [--batch-size=<size>]
     * : Number of deliveries to process
     * ---
     * default: 10
     * ---
     *
     * ## EXAMPLES
     *
     *     wp minpaku webhook process
     *     wp minpaku webhook process --batch-size=20
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function process($args, $assoc_args) {
        $batch_size = intval($assoc_args['batch-size'] ?? 10);

        WP_CLI::log('Processing webhook queue...');

        $dispatcher = new WebhookDispatcher();
        $result = $dispatcher->processQueue($batch_size);

        WP_CLI::success(sprintf(
            'Processed %d deliveries (%d succeeded, %d failed)',
            $result['processed'],
            $result['succeeded'],
            $result['failed']
        ));
    }

    /**
     * Show webhook statistics
     *
     * ## EXAMPLES
     *
     *     wp minpaku webhook stats
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function stats($args, $assoc_args) {
        $queue = new WebhookQueue();
        $worker = new WebhookWorker();

        $stats = $queue->getStats();
        $status = $worker->getStatus();

        WP_CLI::log('Webhook Statistics:');
        WP_CLI::log('==================');

        // Queue stats
        WP_CLI::log('Queue:');
        WP_CLI::log('  Queued: ' . intval($stats['by_status']['queued'] ?? 0));
        WP_CLI::log('  Success: ' . intval($stats['by_status']['success'] ?? 0));
        WP_CLI::log('  Failed: ' . intval($stats['by_status']['failed'] ?? 0));
        WP_CLI::log('  Recent 24h: ' . intval($stats['recent_24h'] ?? 0));
        WP_CLI::log('  Success Rate: ' . ($stats['success_rate'] ?? 0) . '%');

        // Worker status
        WP_CLI::log('');
        WP_CLI::log('Worker:');
        WP_CLI::log('  Scheduled: ' . ($status['is_scheduled'] ? 'Yes' : 'No'));
        WP_CLI::log('  Running: ' . ($status['is_running'] ? 'Yes' : 'No'));
        WP_CLI::log('  Interval: ' . $status['processing_interval'] . ' seconds');
        WP_CLI::log('  Batch Size: ' . $status['batch_size']);

        if ($status['next_run_formatted']) {
            WP_CLI::log('  Next Run: ' . $status['next_run_formatted']);
        }
    }

    /**
     * Test webhook endpoint
     *
     * ## OPTIONS
     *
     * <url>
     * : Webhook URL to test
     *
     * <secret>
     * : Webhook secret
     *
     * ## EXAMPLES
     *
     *     wp minpaku webhook test https://example.com/webhook mysecret
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function test($args, $assoc_args) {
        if (count($args) < 2) {
            WP_CLI::error('URL and secret are required');
        }

        $url = $args[0];
        $secret = $args[1];

        WP_CLI::log('Testing webhook endpoint...');

        $dispatcher = new WebhookDispatcher();
        $result = $dispatcher->testEndpoint($url, $secret);

        if ($result['success']) {
            WP_CLI::success(sprintf(
                'Test successful! Response: %d in %dms',
                $result['response_code'],
                $result['response_time']
            ));
        } else {
            WP_CLI::error('Test failed: ' . $result['error']);
        }
    }

    /**
     * Dispatch test webhook
     *
     * ## OPTIONS
     *
     * <event>
     * : Event name to dispatch
     *
     * [--async]
     * : Process asynchronously
     * ---
     * default: true
     * ---
     *
     * ## EXAMPLES
     *
     *     wp minpaku webhook dispatch booking.confirmed
     *     wp minpaku webhook dispatch payment.captured --async=false
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function dispatch($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('Event name is required');
        }

        $event = $args[0];
        $async = $assoc_args['async'] ?? true;

        $supported_events = WebhookDispatcher::getSupportedEvents();
        if (!in_array($event, $supported_events)) {
            WP_CLI::error('Invalid event. Supported events: ' . implode(', ', $supported_events));
        }

        $payload = WebhookDispatcher::createSamplePayload($event);
        $payload['test'] = true;
        $payload['dispatched_via'] = 'wp-cli';

        WP_CLI::log('Dispatching test webhook...');

        $dispatcher = new WebhookDispatcher();
        $delivery_keys = $dispatcher->dispatch($event, $payload, $async);

        if (empty($delivery_keys)) {
            WP_CLI::warning('No webhook endpoints configured');
        } else {
            WP_CLI::success(sprintf(
                'Webhook dispatched to %d endpoint(s). Delivery keys: %s',
                count($delivery_keys),
                implode(', ', $delivery_keys)
            ));
        }
    }

    /**
     * Clean up old deliveries
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Days to retain
     * ---
     * default: 30
     * ---
     *
     * ## EXAMPLES
     *
     *     wp minpaku webhook cleanup
     *     wp minpaku webhook cleanup --days=7
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function cleanup($args, $assoc_args) {
        $days = intval($assoc_args['days'] ?? 30);

        WP_CLI::log('Cleaning up old webhook deliveries...');

        $queue = new WebhookQueue();
        $deleted_count = $queue->cleanupOldDeliveries($days);

        WP_CLI::success(sprintf(
            'Cleaned up %d old deliveries (older than %d days)',
            $deleted_count,
            $days
        ));
    }
}

// Register CLI commands
WP_CLI::add_command('minpaku webhook', 'WebhookCLI');