<?php
/**
 * Webhook API Controller
 * Provides REST API endpoints for webhook management (admin-only)
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/WebhookQueue.php';
require_once __DIR__ . '/WebhookDispatcher.php';

class WebhookApiController extends WP_REST_Controller {

    /**
     * Namespace for this controller's routes
     */
    protected $namespace = 'minpaku/v1';

    /**
     * Rest base for this controller
     */
    protected $rest_base = 'webhooks';

    /**
     * Webhook queue instance
     */
    private $queue;

    /**
     * Webhook dispatcher instance
     */
    private $dispatcher;

    /**
     * Constructor
     */
    public function __construct() {
        $this->queue = new WebhookQueue();
        $this->dispatcher = new WebhookDispatcher();
    }

    /**
     * Register the routes for the objects of the controller
     */
    public function register_routes() {
        // GET /webhooks/deliveries - List deliveries
        register_rest_route($this->namespace, '/' . $this->rest_base . '/deliveries', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_deliveries'],
                'permission_callback' => [$this, 'check_admin_permissions'],
                'args' => $this->get_deliveries_params(),
            ],
        ]);

        // POST /webhooks/redeliver/{delivery_key} - Retry delivery
        register_rest_route($this->namespace, '/' . $this->rest_base . '/redeliver/(?P<delivery_key>[a-f0-9-]+)', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'redeliver'],
                'permission_callback' => [$this, 'check_admin_permissions'],
                'args' => [
                    'delivery_key' => [
                        'description' => __('Delivery key to retry', 'minpaku-suite'),
                        'type' => 'string',
                        'required' => true,
                    ],
                ],
            ],
        ]);

        // GET /webhooks/deliveries/{delivery_key} - Get delivery details
        register_rest_route($this->namespace, '/' . $this->rest_base . '/deliveries/(?P<delivery_key>[a-f0-9-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_delivery'],
                'permission_callback' => [$this, 'check_admin_permissions'],
                'args' => [
                    'delivery_key' => [
                        'description' => __('Delivery key', 'minpaku-suite'),
                        'type' => 'string',
                        'required' => true,
                    ],
                ],
            ],
        ]);

        // POST /webhooks/test - Test webhook endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/test', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'test_endpoint'],
                'permission_callback' => [$this, 'check_admin_permissions'],
                'args' => [
                    'url' => [
                        'description' => __('Webhook URL to test', 'minpaku-suite'),
                        'type' => 'string',
                        'format' => 'uri',
                        'required' => true,
                    ],
                    'secret' => [
                        'description' => __('Webhook secret', 'minpaku-suite'),
                        'type' => 'string',
                        'required' => true,
                    ],
                ],
            ],
        ]);

        // POST /webhooks/dispatch - Manually dispatch webhook (for testing)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/dispatch', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'dispatch_webhook'],
                'permission_callback' => [$this, 'check_admin_permissions'],
                'args' => [
                    'event' => [
                        'description' => __('Event name', 'minpaku-suite'),
                        'type' => 'string',
                        'enum' => WebhookDispatcher::getSupportedEvents(),
                        'required' => true,
                    ],
                    'payload' => [
                        'description' => __('Event payload', 'minpaku-suite'),
                        'type' => 'object',
                        'required' => false,
                    ],
                    'async' => [
                        'description' => __('Process asynchronously', 'minpaku-suite'),
                        'type' => 'boolean',
                        'default' => true,
                    ],
                ],
            ],
        ]);

        // GET /webhooks/stats - Get webhook statistics
        register_rest_route($this->namespace, '/' . $this->rest_base . '/stats', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_stats'],
                'permission_callback' => [$this, 'check_admin_permissions'],
            ],
        ]);

        // POST /webhooks/process - Process queue manually
        register_rest_route($this->namespace, '/' . $this->rest_base . '/process', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'process_queue'],
                'permission_callback' => [$this, 'check_admin_permissions'],
                'args' => [
                    'batch_size' => [
                        'description' => __('Number of deliveries to process', 'minpaku-suite'),
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 50,
                        'default' => 10,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get deliveries list
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function get_deliveries($request) {
        $params = $request->get_params();

        // Build filters
        $filters = [];
        if (!empty($params['status'])) {
            $filters['status'] = $params['status'];
        }
        if (!empty($params['event'])) {
            $filters['event'] = $params['event'];
        }

        $filters['limit'] = min(100, max(1, $params['limit'] ?? 50));
        $filters['offset'] = max(0, $params['offset'] ?? 0);
        $filters['order'] = in_array(strtoupper($params['order'] ?? 'DESC'), ['ASC', 'DESC'])
            ? strtoupper($params['order'])
            : 'DESC';

        // Get deliveries
        $deliveries = $this->queue->getDeliveries($filters);
        $total_count = $this->queue->countDeliveries(array_filter([
            'status' => $filters['status'] ?? null,
            'event' => $filters['event'] ?? null
        ]));

        // Format response
        $response_data = [
            'deliveries' => $deliveries,
            'pagination' => [
                'total' => $total_count,
                'limit' => $filters['limit'],
                'offset' => $filters['offset'],
                'has_more' => ($filters['offset'] + $filters['limit']) < $total_count
            ],
            'filters' => array_filter([
                'status' => $filters['status'] ?? null,
                'event' => $filters['event'] ?? null
            ])
        ];

        $response = new WP_REST_Response($response_data, 200);

        // Add caching headers
        $response->set_headers([
            'Cache-Control' => 'private, max-age=60', // 1 minute cache
            'X-Total-Count' => $total_count
        ]);

        return $response;
    }

    /**
     * Get single delivery details
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function get_delivery($request) {
        $delivery_key = $request->get_param('delivery_key');
        $delivery = $this->queue->getDelivery($delivery_key);

        if (!$delivery) {
            return new WP_Error(
                'delivery_not_found',
                __('Delivery not found', 'minpaku-suite'),
                ['status' => 404]
            );
        }

        return new WP_REST_Response($delivery, 200);
    }

    /**
     * Retry delivery
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function redeliver($request) {
        $delivery_key = $request->get_param('delivery_key');
        $delivery = $this->queue->getDelivery($delivery_key);

        if (!$delivery) {
            return new WP_Error(
                'delivery_not_found',
                __('Delivery not found', 'minpaku-suite'),
                ['status' => 404]
            );
        }

        // Check if delivery can be retried
        if (!$delivery['can_retry']) {
            return new WP_Error(
                'delivery_cannot_retry',
                __('This delivery cannot be retried (max attempts reached or already successful)', 'minpaku-suite'),
                ['status' => 422]
            );
        }

        // Reset delivery for retry
        $success = $this->queue->resetForRetry($delivery_key);

        if (!$success) {
            return new WP_Error(
                'retry_failed',
                __('Failed to reset delivery for retry', 'minpaku-suite'),
                ['status' => 500]
            );
        }

        // Get updated delivery
        $updated_delivery = $this->queue->getDelivery($delivery_key);

        // Log retry action
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Webhook delivery manually retried', [
                'delivery_key' => $delivery_key,
                'user_id' => get_current_user_id(),
                'original_attempts' => $delivery['attempt']
            ]);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Delivery has been reset for retry', 'minpaku-suite'),
            'delivery' => $updated_delivery
        ], 200);
    }

    /**
     * Test webhook endpoint
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function test_endpoint($request) {
        $url = $request->get_param('url');
        $secret = $request->get_param('secret');

        $result = $this->dispatcher->testEndpoint($url, $secret);

        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Test successful', 'minpaku-suite'),
                'response_code' => $result['response_code'],
                'response_time' => $result['response_time'],
                'response_body' => $result['response_body'] ?? null
            ], 200);
        } else {
            return new WP_Error(
                'test_failed',
                $result['error'],
                [
                    'status' => 422,
                    'response_code' => $result['response_code'],
                    'response_time' => $result['response_time']
                ]
            );
        }
    }

    /**
     * Manually dispatch webhook
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function dispatch_webhook($request) {
        $event = $request->get_param('event');
        $payload = $request->get_param('payload') ?: [];
        $async = $request->get_param('async') ?? true;

        // Add test indication to payload
        $payload['test'] = true;
        $payload['dispatched_by'] = get_current_user_id();
        $payload['dispatched_at'] = current_time('c');

        // Use sample payload if none provided
        if (empty($payload) || (count($payload) === 3 && isset($payload['test']))) {
            $payload = array_merge(
                WebhookDispatcher::createSamplePayload($event),
                $payload
            );
        }

        try {
            $delivery_keys = $this->dispatcher->dispatch($event, $payload, $async);

            if (empty($delivery_keys)) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => __('No webhook endpoints configured', 'minpaku-suite'),
                    'delivery_keys' => []
                ], 200);
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => sprintf(
                    __('Webhook dispatched to %d endpoint(s)', 'minpaku-suite'),
                    count($delivery_keys)
                ),
                'event' => $event,
                'delivery_keys' => $delivery_keys,
                'async' => $async
            ], 201);

        } catch (Exception $e) {
            return new WP_Error(
                'dispatch_failed',
                sprintf(__('Failed to dispatch webhook: %s', 'minpaku-suite'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Get webhook statistics
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response Response object
     */
    public function get_stats($request) {
        $queue_stats = $this->queue->getStats();

        // Get worker status if available
        $worker_status = null;
        if (class_exists('WebhookWorker')) {
            $worker = new WebhookWorker();
            $worker_status = $worker->getStatus();
        }

        // Get supported events
        $supported_events = WebhookDispatcher::getSupportedEvents();
        $event_labels = WebhookDispatcher::getEventLabels();

        $response_data = [
            'queue' => $queue_stats,
            'worker' => $worker_status,
            'events' => [
                'supported' => $supported_events,
                'labels' => $event_labels
            ],
            'settings' => [
                'retry_intervals' => WebhookQueue::getRetryIntervals(),
                'max_attempts' => WebhookQueue::getMaxAttempts(),
                'valid_statuses' => WebhookQueue::getValidStatuses()
            ],
            'meta' => [
                'generated_at' => current_time('c'),
                'server_timezone' => wp_timezone_string()
            ]
        ];

        $response = new WP_REST_Response($response_data, 200);

        // Add caching headers
        $response->set_headers([
            'Cache-Control' => 'private, max-age=30' // 30 seconds cache for stats
        ]);

        return $response;
    }

    /**
     * Process webhook queue manually
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response Response object
     */
    public function process_queue($request) {
        $batch_size = $request->get_param('batch_size') ?? 10;

        try {
            $result = $this->dispatcher->processQueue($batch_size);

            return new WP_REST_Response([
                'success' => true,
                'message' => sprintf(
                    __('Processed %d deliveries (%d succeeded, %d failed)', 'minpaku-suite'),
                    $result['processed'],
                    $result['succeeded'],
                    $result['failed']
                ),
                'result' => $result,
                'batch_size' => $batch_size
            ], 200);

        } catch (Exception $e) {
            return new WP_Error(
                'processing_failed',
                sprintf(__('Failed to process queue: %s', 'minpaku-suite'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Check admin permissions
     *
     * @param WP_REST_Request $request Full data about the request
     * @return bool|WP_Error True if the request has admin access, WP_Error object otherwise
     */
    public function check_admin_permissions($request) {
        if (!current_user_can('manage_minpaku')) {
            return new WP_Error(
                'insufficient_permissions',
                __('You do not have permission to access webhook management', 'minpaku-suite'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Get the query params for deliveries endpoint
     *
     * @return array
     */
    public function get_deliveries_params() {
        return [
            'status' => [
                'description' => __('Filter by delivery status', 'minpaku-suite'),
                'type' => 'string',
                'enum' => WebhookQueue::getValidStatuses(),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'event' => [
                'description' => __('Filter by event type', 'minpaku-suite'),
                'type' => 'string',
                'enum' => WebhookDispatcher::getSupportedEvents(),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'limit' => [
                'description' => __('Number of deliveries to return', 'minpaku-suite'),
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 50,
                'sanitize_callback' => 'absint'
            ],
            'offset' => [
                'description' => __('Offset for pagination', 'minpaku-suite'),
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
                'sanitize_callback' => 'absint'
            ],
            'order' => [
                'description' => __('Sort order', 'minpaku-suite'),
                'type' => 'string',
                'enum' => ['ASC', 'DESC'],
                'default' => 'DESC',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }
}