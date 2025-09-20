<?php
/**
 * Webhook Dispatch Integration Tests
 * Tests webhook dispatching when booking events occur
 *
 * @package MinpakuSuite
 */

use PHPUnit\Framework\TestCase;

class WebhookDispatchTest extends TestCase {

    /**
     * Webhook dispatcher instance
     */
    private $dispatcher;

    /**
     * Webhook queue instance
     */
    private $queue;

    /**
     * Booking service instance
     */
    private $booking_service;

    /**
     * Mock webhook endpoints
     */
    private $mock_endpoints = [];

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
        require_once __DIR__ . '/../../includes/Webhook/WebhookDispatcher.php';
        require_once __DIR__ . '/../../includes/Webhook/WebhookQueue.php';
        require_once __DIR__ . '/../../includes/Services/BookingService.php';

        // Create mock instances
        $this->queue = $this->createMock(WebhookQueue::class);
        $this->dispatcher = $this->createPartialMock(WebhookDispatcher::class, ['getConfiguredEndpoints']);
        $this->booking_service = $this->createMock(BookingService::class);

        // Set up mock endpoints
        $this->mock_endpoints = [
            [
                'url' => 'https://example.com/webhook',
                'secret' => 'test_secret_123',
                'enabled' => true,
                'events' => []
            ]
        ];

        // Mock getConfiguredEndpoints to return our test endpoints
        $this->dispatcher->method('getConfiguredEndpoints')
                        ->willReturn($this->mock_endpoints);

        // Replace queue instance via reflection
        $reflection = new ReflectionClass($this->dispatcher);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setAccessible(true);
        $queueProperty->setValue($this->dispatcher, $this->queue);
    }

    /**
     * Test booking.confirmed event creates queue entry
     */
    public function testBookingConfirmedCreatesQueueEntry() {
        // Set up queue mock to capture enqueue call
        $captured_delivery = null;

        $this->queue->expects($this->once())
                   ->method('enqueue')
                   ->willReturnCallback(function($delivery) use (&$captured_delivery) {
                       $captured_delivery = $delivery;
                       return 'test-delivery-key-123';
                   });

        // Prepare test payload
        $payload = [
            'booking' => [
                'id' => 123,
                'property_id' => 45,
                'checkin' => '2025-10-01',
                'checkout' => '2025-10-05',
                'guests' => ['adults' => 2, 'children' => 1],
                'state' => 'confirmed'
            ],
            'quote' => [
                'base' => 40000,
                'taxes' => 4000,
                'fees' => 2000,
                'total' => 46000,
                'currency' => 'JPY'
            ]
        ];

        // Dispatch webhook
        $delivery_keys = $this->dispatcher->dispatch('booking.confirmed', $payload);

        // Verify delivery was created
        $this->assertCount(1, $delivery_keys);
        $this->assertEquals('test-delivery-key-123', $delivery_keys[0]);

        // Verify captured delivery data
        $this->assertNotNull($captured_delivery);
        $this->assertEquals('booking.confirmed', $captured_delivery['event']);
        $this->assertEquals('https://example.com/webhook', $captured_delivery['url']);
        $this->assertEquals('1', $captured_delivery['payload']['version']);
        $this->assertEquals('booking.confirmed', $captured_delivery['payload']['event']);
        $this->assertEquals($payload, $captured_delivery['payload']['data']);

        // Verify headers are present
        $this->assertArrayHasKey('headers', $captured_delivery);
        $headers = $captured_delivery['headers'];
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('booking.confirmed', $headers['X-Minpaku-Event']);
        $this->assertArrayHasKey('X-Minpaku-Signature', $headers);
        $this->assertArrayHasKey('X-Minpaku-Timestamp', $headers);
        $this->assertArrayHasKey('X-Minpaku-Delivery', $headers);
    }

    /**
     * Test booking.cancelled event creates queue entry
     */
    public function testBookingCancelledCreatesQueueEntry() {
        $captured_delivery = null;

        $this->queue->expects($this->once())
                   ->method('enqueue')
                   ->willReturnCallback(function($delivery) use (&$captured_delivery) {
                       $captured_delivery = $delivery;
                       return 'test-delivery-key-456';
                   });

        $payload = [
            'booking' => [
                'id' => 123,
                'property_id' => 45,
                'state' => 'cancelled'
            ],
            'reason' => 'Customer request',
            'cancelled_at' => '2025-09-20T15:30:00Z'
        ];

        $delivery_keys = $this->dispatcher->dispatch('booking.cancelled', $payload);

        $this->assertCount(1, $delivery_keys);
        $this->assertEquals('test-delivery-key-456', $delivery_keys[0]);
        $this->assertEquals('booking.cancelled', $captured_delivery['event']);
        $this->assertEquals('Customer request', $captured_delivery['payload']['data']['reason']);
    }

    /**
     * Test payment.captured event creates queue entry
     */
    public function testPaymentCapturedCreatesQueueEntry() {
        $captured_delivery = null;

        $this->queue->expects($this->once())
                   ->method('enqueue')
                   ->willReturnCallback(function($delivery) use (&$captured_delivery) {
                       $captured_delivery = $delivery;
                       return 'test-delivery-key-789';
                   });

        $payload = [
            'booking_id' => 123,
            'amount' => 46000,
            'currency' => 'JPY',
            'provider' => 'stripe',
            'transaction_id' => 'ch_abc123',
            'captured_at' => '2025-09-20T10:20:00Z'
        ];

        $delivery_keys = $this->dispatcher->dispatch('payment.captured', $payload);

        $this->assertCount(1, $delivery_keys);
        $this->assertEquals('test-delivery-key-789', $delivery_keys[0]);
        $this->assertEquals('payment.captured', $captured_delivery['event']);
        $this->assertEquals(46000, $captured_delivery['payload']['data']['amount']);
        $this->assertEquals('stripe', $captured_delivery['payload']['data']['provider']);
    }

    /**
     * Test multiple endpoints receive webhooks
     */
    public function testMultipleEndpointsReceiveWebhooks() {
        // Set up multiple endpoints
        $endpoints = [
            [
                'url' => 'https://endpoint1.com/webhook',
                'secret' => 'secret1',
                'enabled' => true,
                'events' => []
            ],
            [
                'url' => 'https://endpoint2.com/webhook',
                'secret' => 'secret2',
                'enabled' => true,
                'events' => []
            ]
        ];

        $this->dispatcher->method('getConfiguredEndpoints')
                        ->willReturn($endpoints);

        $captured_deliveries = [];

        $this->queue->expects($this->exactly(2))
                   ->method('enqueue')
                   ->willReturnCallback(function($delivery) use (&$captured_deliveries) {
                       $captured_deliveries[] = $delivery;
                       return 'delivery-key-' . count($captured_deliveries);
                   });

        $payload = ['booking' => ['id' => 123, 'state' => 'confirmed']];
        $delivery_keys = $this->dispatcher->dispatch('booking.confirmed', $payload);

        $this->assertCount(2, $delivery_keys);
        $this->assertCount(2, $captured_deliveries);

        // Verify both endpoints received the webhook
        $urls = array_column($captured_deliveries, 'url');
        $this->assertContains('https://endpoint1.com/webhook', $urls);
        $this->assertContains('https://endpoint2.com/webhook', $urls);
    }

    /**
     * Test event filtering by endpoint configuration
     */
    public function testEventFilteringByEndpoint() {
        // Set up endpoint that only receives booking events
        $endpoints = [
            [
                'url' => 'https://booking-only.com/webhook',
                'secret' => 'secret1',
                'enabled' => true,
                'events' => ['booking.confirmed', 'booking.cancelled']
            ],
            [
                'url' => 'https://payment-only.com/webhook',
                'secret' => 'secret2',
                'enabled' => true,
                'events' => ['payment.captured', 'payment.refunded']
            ]
        ];

        $this->dispatcher->method('getConfiguredEndpoints')
                        ->willReturn($endpoints);

        $captured_deliveries = [];

        $this->queue->expects($this->once())
                   ->method('enqueue')
                   ->willReturnCallback(function($delivery) use (&$captured_deliveries) {
                       $captured_deliveries[] = $delivery;
                       return 'delivery-key-filtered';
                   });

        // Dispatch booking event - should only go to booking-only endpoint
        $payload = ['booking' => ['id' => 123, 'state' => 'confirmed']];
        $delivery_keys = $this->dispatcher->dispatch('booking.confirmed', $payload);

        $this->assertCount(1, $delivery_keys);
        $this->assertCount(1, $captured_deliveries);
        $this->assertEquals('https://booking-only.com/webhook', $captured_deliveries[0]['url']);
    }

    /**
     * Test disabled endpoints are skipped
     */
    public function testDisabledEndpointsAreSkipped() {
        $endpoints = [
            [
                'url' => 'https://enabled.com/webhook',
                'secret' => 'secret1',
                'enabled' => true,
                'events' => []
            ],
            [
                'url' => 'https://disabled.com/webhook',
                'secret' => 'secret2',
                'enabled' => false,
                'events' => []
            ]
        ];

        $this->dispatcher->method('getConfiguredEndpoints')
                        ->willReturn($endpoints);

        $this->queue->expects($this->once())
                   ->method('enqueue')
                   ->willReturn('delivery-key-enabled');

        $payload = ['booking' => ['id' => 123]];
        $delivery_keys = $this->dispatcher->dispatch('booking.confirmed', $payload);

        // Only enabled endpoint should receive webhook
        $this->assertCount(1, $delivery_keys);
    }

    /**
     * Test invalid event names are rejected
     */
    public function testInvalidEventNamesAreRejected() {
        $this->queue->expects($this->never())
                   ->method('enqueue');

        $payload = ['test' => 'data'];
        $delivery_keys = $this->dispatcher->dispatch('invalid.event', $payload);

        $this->assertEmpty($delivery_keys);
    }

    /**
     * Test synchronous dispatch sends immediately
     */
    public function testSynchronousDispatchSendsImmediately() {
        // Create a partial mock that we can control
        $dispatcher = $this->createPartialMock(WebhookDispatcher::class, ['getConfiguredEndpoints', 'sendWebhook']);

        $dispatcher->method('getConfiguredEndpoints')
                  ->willReturn($this->mock_endpoints);

        // Replace queue with real instance for this test
        $reflection = new ReflectionClass($dispatcher);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setAccessible(true);
        $queueProperty->setValue($dispatcher, new WebhookQueue());

        // Mock sendWebhook to track calls
        $webhook_sent = false;
        $dispatcher->expects($this->once())
                  ->method('sendWebhook')
                  ->willReturnCallback(function($delivery) use (&$webhook_sent) {
                      $webhook_sent = true;
                      return true;
                  });

        $payload = ['booking' => ['id' => 123]];
        $delivery_keys = $dispatcher->dispatch('booking.confirmed', $payload, false); // async = false

        $this->assertTrue($webhook_sent);
        $this->assertCount(1, $delivery_keys);
    }

    /**
     * Test payload structure is correct
     */
    public function testPayloadStructureIsCorrect() {
        $captured_delivery = null;

        $this->queue->expects($this->once())
                   ->method('enqueue')
                   ->willReturnCallback(function($delivery) use (&$captured_delivery) {
                       $captured_delivery = $delivery;
                       return 'test-delivery-key';
                   });

        $input_payload = ['booking' => ['id' => 123]];
        $this->dispatcher->dispatch('booking.confirmed', $input_payload);

        // Verify standardized payload structure
        $payload = $captured_delivery['payload'];
        $this->assertEquals('1', $payload['version']);
        $this->assertEquals('booking.confirmed', $payload['event']);
        $this->assertArrayHasKey('timestamp', $payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertEquals($input_payload, $payload['data']);

        // Verify timestamp is in ISO 8601 format
        $timestamp = $payload['timestamp'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $timestamp);
    }

    /**
     * Mock WordPress environment for testing
     */
    private function mockWordPressEnvironment() {
        $this->defineMockFunctions();
        $this->defineMockClasses();
    }

    private function defineMockFunctions() {
        if (!function_exists('current_time')) {
            function current_time($type) {
                return $type === 'mysql' ? date('Y-m-d H:i:s') :
                       ($type === 'c' ? date('c') : time());
            }
        }

        if (!function_exists('wp_generate_uuid4')) {
            function wp_generate_uuid4() {
                return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }
        }

        if (!function_exists('wp_json_encode')) {
            function wp_json_encode($data) {
                return json_encode($data);
            }
        }

        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) {
                return trim(strip_tags($str));
            }
        }

        if (!function_exists('esc_url_raw')) {
            function esc_url_raw($url) {
                return filter_var($url, FILTER_SANITIZE_URL);
            }
        }

        if (!function_exists('__')) {
            function __($text, $domain = '') {
                return $text;
            }
        }
    }

    private function defineMockClasses() {
        // Mock classes are defined globally to avoid nesting
    }
}
}