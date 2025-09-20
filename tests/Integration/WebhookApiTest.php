<?php
/**
 * Webhook API Integration Tests
 * Tests REST API endpoints for webhook management
 *
 * @package MinpakuSuite
 */

use PHPUnit\Framework\TestCase;

class WebhookApiTest extends TestCase {

    /**
     * API controller instance
     */
    private $api;

    /**
     * Mock queue instance
     */
    private $queue;

    /**
     * Mock dispatcher instance
     */
    private $dispatcher;

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();

        // Mock WordPress environment if not available
        if (!defined('ABSPATH')) {
            $this->mockWordPressEnvironment();
        }

        // Load required classes
        require_once __DIR__ . '/../../includes/Webhook/WebhookApiController.php';
        require_once __DIR__ . '/../../includes/Webhook/WebhookQueue.php';
        require_once __DIR__ . '/../../includes/Webhook/WebhookDispatcher.php';

        // Create controller with mocked dependencies
        $this->queue = $this->createMock(WebhookQueue::class);
        $this->dispatcher = $this->createMock(WebhookDispatcher::class);

        $this->api = new WebhookApiController();

        // Inject mocks via reflection
        $reflection = new ReflectionClass($this->api);

        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setAccessible(true);
        $queueProperty->setValue($this->api, $this->queue);

        $dispatcherProperty = $reflection->getProperty('dispatcher');
        $dispatcherProperty->setAccessible(true);
        $dispatcherProperty->setValue($this->api, $this->dispatcher);
    }

    /**
     * Test get deliveries endpoint returns paginated results
     */
    public function testGetDeliveriesEndpointReturnsPaginatedResults() {
        $mock_deliveries = [
            [
                'delivery_key' => 'key-1',
                'event' => 'booking.confirmed',
                'url' => 'https://example.com/webhook',
                'status' => 'success',
                'attempt' => 1,
                'created_at' => '2025-01-01 10:00:00'
            ],
            [
                'delivery_key' => 'key-2',
                'event' => 'payment.captured',
                'url' => 'https://example2.com/webhook',
                'status' => 'failed',
                'attempt' => 3,
                'created_at' => '2025-01-01 11:00:00'
            ]
        ];

        $this->queue->expects($this->once())
                   ->method('getDeliveries')
                   ->with($this->callback(function($filters) {
                       return $filters['limit'] === 50 &&
                              $filters['offset'] === 0 &&
                              $filters['order'] === 'DESC';
                   }))
                   ->willReturn($mock_deliveries);

        $this->queue->expects($this->once())
                   ->method('countDeliveries')
                   ->willReturn(25);

        $request = $this->createMockRequest([]);
        $response = $this->api->get_deliveries($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('deliveries', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertEquals($mock_deliveries, $data['deliveries']);
        $this->assertEquals(25, $data['pagination']['total']);
    }

    /**
     * Test get deliveries with filtering
     */
    public function testGetDeliveriesWithFiltering() {
        $this->queue->expects($this->once())
                   ->method('getDeliveries')
                   ->with($this->callback(function($filters) {
                       return $filters['status'] === 'failed' &&
                              $filters['event'] === 'booking.confirmed';
                   }))
                   ->willReturn([]);

        $this->queue->expects($this->once())
                   ->method('countDeliveries')
                   ->willReturn(0);

        $request = $this->createMockRequest([
            'status' => 'failed',
            'event' => 'booking.confirmed',
            'limit' => 20,
            'offset' => 10
        ]);

        $response = $this->api->get_deliveries($request);
        $data = $response->get_data();

        $this->assertEquals('failed', $data['filters']['status']);
        $this->assertEquals('booking.confirmed', $data['filters']['event']);
        $this->assertEquals(20, $data['pagination']['limit']);
        $this->assertEquals(10, $data['pagination']['offset']);
    }

    /**
     * Test get single delivery returns delivery data
     */
    public function testGetSingleDeliveryReturnsDeliveryData() {
        $mock_delivery = [
            'delivery_key' => 'test-key-123',
            'event' => 'booking.confirmed',
            'url' => 'https://example.com/webhook',
            'payload' => ['booking' => ['id' => 123]],
            'status' => 'success',
            'attempt' => 1,
            'response_code' => 200,
            'created_at' => '2025-01-01 10:00:00'
        ];

        $this->queue->expects($this->once())
                   ->method('getDelivery')
                   ->with('test-key-123')
                   ->willReturn($mock_delivery);

        $request = $this->createMockRequest([], ['delivery_key' => 'test-key-123']);
        $response = $this->api->get_delivery($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals($mock_delivery, $response->get_data());
    }

    /**
     * Test get single delivery returns 404 for non-existent delivery
     */
    public function testGetSingleDeliveryReturns404ForNonExistentDelivery() {
        $this->queue->expects($this->once())
                   ->method('getDelivery')
                   ->with('non-existent-key')
                   ->willReturn(null);

        $request = $this->createMockRequest([], ['delivery_key' => 'non-existent-key']);
        $response = $this->api->get_delivery($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('delivery_not_found', $response->get_error_code());
        $this->assertEquals(404, $response->get_error_data()['status']);
    }

    /**
     * Test redeliver endpoint resets delivery for retry
     */
    public function testRedeliverEndpointResetsDeliveryForRetry() {
        $mock_delivery = [
            'delivery_key' => 'retry-key-456',
            'status' => 'failed',
            'attempt' => 3,
            'can_retry' => true
        ];

        $updated_delivery = [
            'delivery_key' => 'retry-key-456',
            'status' => 'queued',
            'attempt' => 3,
            'can_retry' => true,
            'last_error' => null
        ];

        $this->queue->expects($this->once())
                   ->method('getDelivery')
                   ->with('retry-key-456')
                   ->willReturn($mock_delivery);

        $this->queue->expects($this->once())
                   ->method('resetForRetry')
                   ->with('retry-key-456')
                   ->willReturn(true);

        $this->queue->expects($this->once())
                   ->method('getDelivery')
                   ->with('retry-key-456')
                   ->willReturn($updated_delivery);

        $request = $this->createMockRequest([], ['delivery_key' => 'retry-key-456']);
        $response = $this->api->redeliver($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals($updated_delivery, $data['delivery']);
    }

    /**
     * Test redeliver rejects delivery that cannot be retried
     */
    public function testRedeliverRejectsDeliveryThatCannotBeRetried() {
        $mock_delivery = [
            'delivery_key' => 'max-attempts-key',
            'status' => 'failed',
            'attempt' => 5,
            'can_retry' => false
        ];

        $this->queue->expects($this->once())
                   ->method('getDelivery')
                   ->with('max-attempts-key')
                   ->willReturn($mock_delivery);

        $request = $this->createMockRequest([], ['delivery_key' => 'max-attempts-key']);
        $response = $this->api->redeliver($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('delivery_cannot_retry', $response->get_error_code());
        $this->assertEquals(422, $response->get_error_data()['status']);
    }

    /**
     * Test test endpoint succeeds with valid endpoint
     */
    public function testTestEndpointSucceedsWithValidEndpoint() {
        $this->dispatcher->expects($this->once())
                        ->method('testEndpoint')
                        ->with('https://test.com/webhook', 'test_secret')
                        ->willReturn([
                            'success' => true,
                            'response_code' => 200,
                            'response_time' => 150,
                            'response_body' => 'OK'
                        ]);

        $request = $this->createMockRequest([
            'url' => 'https://test.com/webhook',
            'secret' => 'test_secret'
        ]);

        $response = $this->api->test_endpoint($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(200, $data['response_code']);
        $this->assertEquals(150, $data['response_time']);
    }

    /**
     * Test test endpoint fails with invalid endpoint
     */
    public function testTestEndpointFailsWithInvalidEndpoint() {
        $this->dispatcher->expects($this->once())
                        ->method('testEndpoint')
                        ->with('https://invalid.com/webhook', 'wrong_secret')
                        ->willReturn([
                            'success' => false,
                            'response_code' => 401,
                            'response_time' => 100,
                            'error' => 'Unauthorized'
                        ]);

        $request = $this->createMockRequest([
            'url' => 'https://invalid.com/webhook',
            'secret' => 'wrong_secret'
        ]);

        $response = $this->api->test_endpoint($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('test_failed', $response->get_error_code());
        $this->assertEquals(422, $response->get_error_data()['status']);
    }

    /**
     * Test dispatch webhook creates deliveries
     */
    public function testDispatchWebhookCreatesDeliveries() {
        $this->dispatcher->expects($this->once())
                        ->method('dispatch')
                        ->with(
                            'booking.confirmed',
                            $this->callback(function($payload) {
                                return $payload['test'] === true &&
                                       isset($payload['dispatched_by']);
                            }),
                            true
                        )
                        ->willReturn(['delivery-key-1', 'delivery-key-2']);

        $request = $this->createMockRequest([
            'event' => 'booking.confirmed',
            'payload' => ['booking' => ['id' => 123]],
            'async' => true
        ]);

        $response = $this->api->dispatch_webhook($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(['delivery-key-1', 'delivery-key-2'], $data['delivery_keys']);
    }

    /**
     * Test dispatch webhook with no configured endpoints
     */
    public function testDispatchWebhookWithNoConfiguredEndpoints() {
        $this->dispatcher->expects($this->once())
                        ->method('dispatch')
                        ->willReturn([]);

        $request = $this->createMockRequest([
            'event' => 'payment.captured',
            'async' => false
        ]);

        $response = $this->api->dispatch_webhook($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEmpty($data['delivery_keys']);
        $this->assertStringContains('No webhook endpoints', $data['message']);
    }

    /**
     * Test get stats returns comprehensive statistics
     */
    public function testGetStatsReturnsComprehensiveStatistics() {
        $mock_queue_stats = [
            'by_status' => [
                'queued' => 5,
                'success' => 120,
                'failed' => 8
            ],
            'recent_24h' => 45,
            'success_rate' => 92.3
        ];

        $this->queue->expects($this->once())
                   ->method('getStats')
                   ->willReturn($mock_queue_stats);

        $request = $this->createMockRequest([]);
        $response = $this->api->get_stats($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertArrayHasKey('queue', $data);
        $this->assertArrayHasKey('events', $data);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('meta', $data);

        $this->assertEquals($mock_queue_stats, $data['queue']);
        $this->assertArrayHasKey('supported', $data['events']);
        $this->assertArrayHasKey('retry_intervals', $data['settings']);
    }

    /**
     * Test process queue endpoint processes deliveries
     */
    public function testProcessQueueEndpointProcessesDeliveries() {
        $this->dispatcher->expects($this->once())
                        ->method('processQueue')
                        ->with(15)
                        ->willReturn([
                            'processed' => 15,
                            'succeeded' => 12,
                            'failed' => 3
                        ]);

        $request = $this->createMockRequest(['batch_size' => 15]);
        $response = $this->api->process_queue($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(15, $data['result']['processed']);
        $this->assertEquals(15, $data['batch_size']);
    }

    /**
     * Test permission check denies access for non-admin users
     */
    public function testPermissionCheckDeniesAccessForNonAdminUsers() {
        // Mock current_user_can to return false
        global $mock_user_can;
        $mock_user_can = false;

        $request = $this->createMockRequest([]);
        $result = $this->api->check_admin_permissions($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('insufficient_permissions', $result->get_error_code());
        $this->assertEquals(403, $result->get_error_data()['status']);

        // Reset mock
        $mock_user_can = true;
    }

    /**
     * Test permission check allows access for admin users
     */
    public function testPermissionCheckAllowsAccessForAdminUsers() {
        // Mock current_user_can to return true
        global $mock_user_can;
        $mock_user_can = true;

        $request = $this->createMockRequest([]);
        $result = $this->api->check_admin_permissions($request);

        $this->assertTrue($result);
    }

    /**
     * Test deliveries params validation
     */
    public function testDeliveriesParamsValidation() {
        $params = $this->api->get_deliveries_params();

        $this->assertArrayHasKey('status', $params);
        $this->assertArrayHasKey('event', $params);
        $this->assertArrayHasKey('limit', $params);
        $this->assertArrayHasKey('offset', $params);
        $this->assertArrayHasKey('order', $params);

        // Check limit constraints
        $this->assertEquals(1, $params['limit']['minimum']);
        $this->assertEquals(100, $params['limit']['maximum']);
        $this->assertEquals(50, $params['limit']['default']);

        // Check order enum
        $this->assertEquals(['ASC', 'DESC'], $params['order']['enum']);
    }

    /**
     * Create mock request object
     */
    private function createMockRequest($params = [], $url_params = []) {
        $request = $this->createMock(WP_REST_Request::class);

        $request->method('get_params')
               ->willReturn($params);

        $request->method('get_param')
               ->willReturnCallback(function($key) use ($params, $url_params) {
                   if (isset($url_params[$key])) {
                       return $url_params[$key];
                   }
                   return $params[$key] ?? null;
               });

        return $request;
    }

    /**
     * Mock WordPress environment for testing
     */
    private function mockWordPressEnvironment() {
        if (!function_exists('current_time')) {
            function current_time($type) {
                return $type === 'mysql' ? date('Y-m-d H:i:s') :
                       ($type === 'c' ? date('c') : time());
            }
        }

        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                global $mock_user_can;
                return $mock_user_can ?? true;
            }
        }

        if (!function_exists('get_current_user_id')) {
            function get_current_user_id() {
                return 1;
            }
        }

        if (!function_exists('wp_timezone_string')) {
            function wp_timezone_string() {
                return 'UTC';
            }
        }

        if (!function_exists('__')) {
            function __($text, $domain = '') {
                return $text;
            }
        }

        if (!class_exists('WP_REST_Controller')) {
            class WP_REST_Controller {}
        }

        if (!class_exists('WP_REST_Server')) {
            class WP_REST_Server {
                const READABLE = 'GET';
                const CREATABLE = 'POST';
            }
        }

        if (!class_exists('WP_REST_Response')) {
            class WP_REST_Response {
                private $data;
                private $status;
                private $headers = [];

                public function __construct($data, $status = 200) {
                    $this->data = $data;
                    $this->status = $status;
                }

                public function get_data() {
                    return $this->data;
                }

                public function get_status() {
                    return $this->status;
                }

                public function set_headers($headers) {
                    $this->headers = $headers;
                }
            }
        }

        if (!class_exists('WP_REST_Request')) {
            class WP_REST_Request {}
        }

        if (!class_exists('WP_Error')) {
            class WP_Error {
                private $code;
                private $message;
                private $data;

                public function __construct($code, $message, $data = []) {
                    $this->code = $code;
                    $this->message = $message;
                    $this->data = $data;
                }

                public function get_error_code() {
                    return $this->code;
                }

                public function get_error_message() {
                    return $this->message;
                }

                public function get_error_data() {
                    return $this->data;
                }
            }
        }

        if (!class_exists('MCS_Logger')) {
            class MCS_Logger {
                public static function log($level, $message, $data = []) {
                    // Mock logger
                }
            }
        }

        if (!class_exists('WebhookDispatcher')) {
            class WebhookDispatcher {
                public static function getSupportedEvents() {
                    return [
                        'booking.confirmed',
                        'booking.cancelled',
                        'booking.completed',
                        'payment.authorized',
                        'payment.captured',
                        'payment.refunded'
                    ];
                }

                public static function getEventLabels() {
                    return [
                        'booking.confirmed' => 'Booking Confirmed',
                        'booking.cancelled' => 'Booking Cancelled',
                        'booking.completed' => 'Booking Completed',
                        'payment.authorized' => 'Payment Authorized',
                        'payment.captured' => 'Payment Captured',
                        'payment.refunded' => 'Payment Refunded'
                    ];
                }
            }
        }

        if (!class_exists('WebhookQueue')) {
            class WebhookQueue {
                public static function getRetryIntervals() {
                    return [10, 60, 300, 1800, 7200];
                }

                public static function getMaxAttempts() {
                    return 5;
                }

                public static function getValidStatuses() {
                    return ['queued', 'success', 'failed'];
                }
            }
        }

        // Set global mock for user permissions
        global $mock_user_can;
        $mock_user_can = true;
    }
}