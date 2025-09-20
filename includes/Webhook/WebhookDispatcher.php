<?php
/**
 * Webhook Dispatcher
 * Handles webhook event dispatching with queue integration and HTTP delivery
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/WebhookQueue.php';
require_once __DIR__ . '/WebhookSigner.php';

class WebhookDispatcher {

    /**
     * Supported webhook events
     */
    const EVENTS = [
        'booking.confirmed',
        'booking.cancelled',
        'booking.completed',
        'payment.authorized',
        'payment.captured',
        'payment.refunded'
    ];

    /**
     * HTTP timeout for webhook requests (seconds)
     */
    const HTTP_TIMEOUT = 5;

    /**
     * Webhook queue instance
     */
    private $queue;

    /**
     * Webhook signer instance
     */
    private $signer;

    /**
     * Constructor
     */
    public function __construct() {
        $this->queue = new WebhookQueue();
        $this->signer = new WebhookSigner();
    }

    /**
     * Dispatch webhook event
     *
     * @param string $event_name Event name (e.g., 'booking.confirmed')
     * @param array $payload Event payload
     * @param bool $async Whether to process asynchronously (default: true)
     * @return array Array of delivery keys for each endpoint
     */
    public function dispatch($event_name, array $payload, $async = true) {
        // Validate event name
        if (!$this->isValidEvent($event_name)) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Invalid webhook event', [
                    'event' => $event_name,
                    'valid_events' => self::EVENTS
                ]);
            }
            return [];
        }

        // Get configured webhook endpoints
        $endpoints = $this->getConfiguredEndpoints();
        if (empty($endpoints)) {
            // No endpoints configured, nothing to dispatch
            return [];
        }

        // Prepare payload with standard structure
        $full_payload = $this->preparePayload($event_name, $payload);

        $delivery_keys = [];

        // Dispatch to each configured endpoint
        foreach ($endpoints as $endpoint) {
            if ($this->shouldSendToEndpoint($endpoint, $event_name)) {
                $delivery_key = $this->dispatchToEndpoint($endpoint, $event_name, $full_payload, $async);
                if ($delivery_key) {
                    $delivery_keys[] = $delivery_key;
                }
            }
        }

        // Log dispatch summary
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Webhook event dispatched', [
                'event' => $event_name,
                'endpoints_count' => count($endpoints),
                'deliveries_created' => count($delivery_keys),
                'async' => $async
            ]);
        }

        return $delivery_keys;
    }

    /**
     * Dispatch to specific endpoint
     *
     * @param array $endpoint Endpoint configuration
     * @param string $event_name Event name
     * @param array $payload Event payload
     * @param bool $async Whether to process asynchronously
     * @return string|false Delivery key on success, false on failure
     */
    private function dispatchToEndpoint($endpoint, $event_name, $payload, $async) {
        $delivery_key = wp_generate_uuid4();
        $body = wp_json_encode($payload);
        $headers = $this->signer->generateHeaders($event_name, $delivery_key, $body, $endpoint['secret']);

        // Prepare delivery data
        $delivery = [
            'event' => $event_name,
            'url' => $endpoint['url'],
            'payload' => $payload,
            'headers' => $headers
        ];

        if ($async) {
            // Queue for async processing
            return $this->queue->enqueue($delivery);
        } else {
            // Send immediately
            $queue_key = $this->queue->enqueue($delivery);
            if ($queue_key) {
                $this->sendWebhook($this->queue->getDelivery($queue_key));
                return $queue_key;
            }
            return false;
        }
    }

    /**
     * Send webhook HTTP request
     *
     * @param array $delivery Delivery data from queue
     * @return bool Success
     */
    public function sendWebhook($delivery) {
        if (!$delivery) {
            return false;
        }

        $url = $delivery['url'];
        $headers = $delivery['headers'];
        $body = wp_json_encode($delivery['payload']);

        // Prepare WordPress HTTP args
        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body,
            'timeout' => self::HTTP_TIMEOUT,
            'user-agent' => 'MinPaku-Suite-Webhook/1.0',
            'blocking' => true,
            'sslverify' => !defined('WP_DEBUG') || !WP_DEBUG, // Allow self-signed in debug mode
        ];

        // Log outgoing request
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'Sending webhook request', [
                'delivery_key' => $delivery['delivery_key'],
                'url' => $url,
                'event' => $delivery['event'],
                'attempt' => $delivery['attempt']
            ]);
        }

        // Send HTTP request
        $response = wp_remote_post($url, $args);

        // Handle response
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->queue->markFailure($delivery['delivery_key'], $error_message);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('WARNING', 'Webhook delivery failed', [
                    'delivery_key' => $delivery['delivery_key'],
                    'error' => $error_message,
                    'attempt' => $delivery['attempt']
                ]);
            }

            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Check if response indicates success (2xx status codes)
        if ($status_code >= 200 && $status_code < 300) {
            $this->queue->markSuccess($delivery['delivery_key']);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Webhook delivery succeeded', [
                    'delivery_key' => $delivery['delivery_key'],
                    'status_code' => $status_code,
                    'attempt' => $delivery['attempt']
                ]);
            }

            return true;
        } else {
            $error_message = sprintf(
                __('HTTP %d: %s', 'minpaku-suite'),
                $status_code,
                substr($response_body, 0, 200) // Truncate response body
            );

            $this->queue->markFailure($delivery['delivery_key'], $error_message);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('WARNING', 'Webhook delivery failed with HTTP error', [
                    'delivery_key' => $delivery['delivery_key'],
                    'status_code' => $status_code,
                    'response_body' => substr($response_body, 0, 500),
                    'attempt' => $delivery['attempt']
                ]);
            }

            return false;
        }
    }

    /**
     * Process webhook queue (called by cron)
     *
     * @param int $batch_size Number of deliveries to process in this batch
     * @return array Processing results
     */
    public function processQueue($batch_size = null) {
        if ($batch_size === null) {
            $batch_size = $this->getConfiguredBatchSize();
        }

        $deliveries = $this->queue->nextBatch($batch_size);

        if (empty($deliveries)) {
            return [
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0
            ];
        }

        $succeeded = 0;
        $failed = 0;

        foreach ($deliveries as $delivery) {
            if ($this->sendWebhook($delivery)) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        // Log batch processing results
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Webhook queue batch processed', [
                'processed' => count($deliveries),
                'succeeded' => $succeeded,
                'failed' => $failed,
                'batch_size' => $batch_size
            ]);
        }

        return [
            'processed' => count($deliveries),
            'succeeded' => $succeeded,
            'failed' => $failed
        ];
    }

    /**
     * Test webhook endpoint
     *
     * @param string $url Webhook URL
     * @param string $secret Webhook secret
     * @return array Test result
     */
    public function testEndpoint($url, $secret) {
        // Validate URL security
        $security_check = $this->signer->validateEndpointSecurity($url);
        if (!$security_check['valid']) {
            return [
                'success' => false,
                'error' => implode(', ', $security_check['errors']),
                'response_code' => null,
                'response_time' => null
            ];
        }

        // Create test payload
        $test_payload = [
            'version' => '1',
            'event' => 'test.webhook',
            'test' => true,
            'timestamp' => time(),
            'message' => __('This is a test webhook from MinPaku Suite', 'minpaku-suite')
        ];

        $delivery_key = wp_generate_uuid4();
        $body = wp_json_encode($test_payload);
        $headers = $this->signer->generateHeaders('test.webhook', $delivery_key, $body, $secret);

        // Record start time for response time measurement
        $start_time = microtime(true);

        // Send test request
        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body,
            'timeout' => self::HTTP_TIMEOUT,
            'user-agent' => 'MinPaku-Suite-Webhook/1.0',
            'blocking' => true,
            'sslverify' => !defined('WP_DEBUG') || !WP_DEBUG,
        ];

        $response = wp_remote_post($url, $args);
        $response_time = round((microtime(true) - $start_time) * 1000); // Convert to milliseconds

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'response_code' => null,
                'response_time' => $response_time
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        return [
            'success' => $status_code >= 200 && $status_code < 300,
            'error' => $status_code >= 200 && $status_code < 300 ? null : sprintf(__('HTTP %d', 'minpaku-suite'), $status_code),
            'response_code' => $status_code,
            'response_time' => $response_time,
            'response_body' => substr($response_body, 0, 1000) // Truncate for display
        ];
    }

    /**
     * Prepare standardized payload structure
     *
     * @param string $event_name Event name
     * @param array $payload Raw payload data
     * @return array Standardized payload
     */
    private function preparePayload($event_name, $payload) {
        return [
            'version' => '1',
            'event' => $event_name,
            'timestamp' => current_time('c'), // ISO 8601 format
            'data' => $payload
        ];
    }

    /**
     * Check if event name is valid
     *
     * @param string $event_name Event name to validate
     * @return bool True if valid
     */
    private function isValidEvent($event_name) {
        return in_array($event_name, self::EVENTS);
    }

    /**
     * Get configured webhook endpoints from settings
     *
     * @return array Array of endpoint configurations
     */
    private function getConfiguredEndpoints() {
        $settings = get_option('minpaku_webhook_settings', []);
        $endpoints = $settings['endpoints'] ?? [];

        // Filter out disabled endpoints and validate
        $active_endpoints = [];
        foreach ($endpoints as $endpoint) {
            if (!empty($endpoint['url']) && !empty($endpoint['secret']) && !empty($endpoint['enabled'])) {
                $active_endpoints[] = $endpoint;
            }
        }

        return $active_endpoints;
    }

    /**
     * Check if event should be sent to specific endpoint
     *
     * @param array $endpoint Endpoint configuration
     * @param string $event_name Event name
     * @return bool True if should send
     */
    private function shouldSendToEndpoint($endpoint, $event_name) {
        // Check if endpoint has event filters
        if (!empty($endpoint['events'])) {
            return in_array($event_name, $endpoint['events']);
        }

        // If no specific events configured, send all
        return true;
    }

    /**
     * Get configured batch size for queue processing
     *
     * @return int Batch size
     */
    private function getConfiguredBatchSize() {
        $settings = get_option('minpaku_webhook_settings', []);
        return intval($settings['batch_size'] ?? 3);
    }

    /**
     * Get queue instance
     *
     * @return WebhookQueue
     */
    public function getQueue() {
        return $this->queue;
    }

    /**
     * Get signer instance
     *
     * @return WebhookSigner
     */
    public function getSigner() {
        return $this->signer;
    }

    /**
     * Get supported events
     *
     * @return array Supported event names
     */
    public static function getSupportedEvents() {
        return self::EVENTS;
    }

    /**
     * Get event display labels
     *
     * @return array Event labels
     */
    public static function getEventLabels() {
        return [
            'booking.confirmed' => __('Booking Confirmed', 'minpaku-suite'),
            'booking.cancelled' => __('Booking Cancelled', 'minpaku-suite'),
            'booking.completed' => __('Booking Completed', 'minpaku-suite'),
            'payment.authorized' => __('Payment Authorized', 'minpaku-suite'),
            'payment.captured' => __('Payment Captured', 'minpaku-suite'),
            'payment.refunded' => __('Payment Refunded', 'minpaku-suite')
        ];
    }

    /**
     * Create sample payload for event type
     *
     * @param string $event_name Event name
     * @return array Sample payload
     */
    public static function createSamplePayload($event_name) {
        switch ($event_name) {
            case 'booking.confirmed':
                return [
                    'booking' => [
                        'id' => 123,
                        'property_id' => 45,
                        'checkin' => '2025-10-01',
                        'checkout' => '2025-10-05',
                        'guests' => ['adults' => 2, 'children' => 1],
                        'state' => 'confirmed',
                        'created_at' => '2025-09-20T10:00:00Z',
                        'updated_at' => '2025-09-20T10:10:00Z'
                    ],
                    'quote' => [
                        'base' => 40000,
                        'taxes' => 4000,
                        'fees' => 2000,
                        'total' => 46000,
                        'currency' => 'JPY'
                    ]
                ];

            case 'booking.cancelled':
                return [
                    'booking' => [
                        'id' => 123,
                        'property_id' => 45,
                        'checkin' => '2025-10-01',
                        'checkout' => '2025-10-05',
                        'state' => 'cancelled',
                        'cancelled_at' => '2025-09-20T15:30:00Z'
                    ],
                    'reason' => 'Customer request'
                ];

            case 'payment.captured':
                return [
                    'booking_id' => 123,
                    'amount' => 46000,
                    'currency' => 'JPY',
                    'provider' => 'stripe',
                    'transaction_id' => 'ch_abc123',
                    'captured_at' => '2025-09-20T10:20:00Z'
                ];

            default:
                return [
                    'message' => sprintf(__('Sample payload for %s event', 'minpaku-suite'), $event_name)
                ];
        }
    }
}