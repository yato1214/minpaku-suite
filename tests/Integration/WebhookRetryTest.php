<?php
/**
 * Webhook Retry Logic Integration Tests
 * Tests webhook retry mechanisms and exponential backoff
 *
 * @package MinpakuSuite
 */

use PHPUnit\Framework\TestCase;

class WebhookRetryTest extends TestCase {

    /**
     * Webhook queue instance
     */
    private $queue;

    /**
     * Webhook dispatcher instance
     */
    private $dispatcher;

    /**
     * Test database table name
     */
    private $table_name;

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
        require_once __DIR__ . '/../../includes/Webhook/WebhookQueue.php';
        require_once __DIR__ . '/../../includes/Webhook/WebhookDispatcher.php';

        // Create instances
        $this->queue = new WebhookQueue();
        $this->dispatcher = $this->createPartialMock(WebhookDispatcher::class, ['sendWebhook']);

        // Set test table name
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ms_webhook_deliveries';

        // Clean up test data
        $this->cleanupTestData();
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void {
        $this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * Test retry intervals follow exponential backoff
     */
    public function testRetryIntervalsFollowExponentialBackoff() {
        $expected_intervals = [10, 60, 300, 1800, 7200]; // 10s, 1m, 5m, 30m, 2h
        $actual_intervals = WebhookQueue::getRetryIntervals();

        $this->assertEquals($expected_intervals, $actual_intervals);
    }

    /**
     * Test max attempts configuration
     */
    public function testMaxAttemptsConfiguration() {
        $this->assertEquals(5, WebhookQueue::getMaxAttempts());
    }

    /**
     * Test delivery progresses through retry attempts
     */
    public function testDeliveryProgressesThroughRetryAttempts() {
        // Create test delivery
        $delivery = [
            'event' => 'booking.confirmed',
            'url' => 'https://example.com/webhook',
            'payload' => ['booking' => ['id' => 123]],
            'headers' => ['Content-Type' => 'application/json'],
            'attempt' => 1,
            'status' => 'queued'
        ];

        $delivery_key = $this->queue->enqueue($delivery);
        $this->assertNotEmpty($delivery_key);

        // Simulate failures and check retry progression
        $retry_intervals = WebhookQueue::getRetryIntervals();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            // Get delivery and verify attempt number
            $stored_delivery = $this->queue->getDelivery($delivery_key);
            $this->assertEquals($attempt, $stored_delivery['attempt']);
            $this->assertEquals('queued', $stored_delivery['status']);

            // Mark as failed
            $this->queue->markFailure($delivery_key, "Attempt {$attempt} failed");

            // Check if retry was scheduled correctly
            $updated_delivery = $this->queue->getDelivery($delivery_key);

            if ($attempt < WebhookQueue::getMaxAttempts()) {
                $this->assertEquals('queued', $updated_delivery['status']);
                $this->assertEquals($attempt + 1, $updated_delivery['attempt']);

                // Verify next run time is approximately correct
                $expected_next_run = time() + $retry_intervals[$attempt - 1];
                $actual_next_run = strtotime($updated_delivery['next_run']);
                $this->assertLessThan(5, abs($expected_next_run - $actual_next_run)); // 5 second tolerance
            }
        }
    }

    /**
     * Test delivery fails permanently after max attempts
     */
    public function testDeliveryFailsPermanentlyAfterMaxAttempts() {
        // Create test delivery
        $delivery = [
            'event' => 'payment.captured',
            'url' => 'https://failing-endpoint.com/webhook',
            'payload' => ['payment' => ['id' => 456]],
            'headers' => ['Content-Type' => 'application/json'],
            'attempt' => 1,
            'status' => 'queued'
        ];

        $delivery_key = $this->queue->enqueue($delivery);

        // Fail all attempts
        $max_attempts = WebhookQueue::getMaxAttempts();
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $stored_delivery = $this->queue->getDelivery($delivery_key);
            $this->assertEquals($attempt, $stored_delivery['attempt']);

            $this->queue->markFailure($delivery_key, "Attempt {$attempt} failed with timeout");

            $updated_delivery = $this->queue->getDelivery($delivery_key);

            if ($attempt < $max_attempts) {
                $this->assertEquals('queued', $updated_delivery['status']);
            } else {
                $this->assertEquals('failed', $updated_delivery['status']);
                $this->assertNull($updated_delivery['next_run']);
                $this->assertFalse($updated_delivery['can_retry']);
            }
        }

        // Verify final state
        $final_delivery = $this->queue->getDelivery($delivery_key);
        $this->assertEquals('failed', $final_delivery['status']);
        $this->assertEquals($max_attempts, $final_delivery['attempt']);
        $this->assertStringContains('Attempt 5 failed', $final_delivery['last_error']);
    }

    /**
     * Test successful delivery stops retry cycle
     */
    public function testSuccessfulDeliveryStopsRetryCycle() {
        // Create test delivery
        $delivery = [
            'event' => 'booking.cancelled',
            'url' => 'https://example.com/webhook',
            'payload' => ['booking' => ['id' => 789]],
            'headers' => ['Content-Type' => 'application/json'],
            'attempt' => 1,
            'status' => 'queued'
        ];

        $delivery_key = $this->queue->enqueue($delivery);

        // Fail first attempt
        $this->queue->markFailure($delivery_key, 'First attempt failed');

        $after_failure = $this->queue->getDelivery($delivery_key);
        $this->assertEquals(2, $after_failure['attempt']);
        $this->assertEquals('queued', $after_failure['status']);

        // Succeed on second attempt
        $this->queue->markSuccess($delivery_key, 200, 'Success');

        $after_success = $this->queue->getDelivery($delivery_key);
        $this->assertEquals('success', $after_success['status']);
        $this->assertEquals(2, $after_success['attempt']);
        $this->assertNull($after_success['next_run']);
        $this->assertFalse($after_success['can_retry']);
    }

    /**
     * Test nextBatch respects retry timing
     */
    public function testNextBatchRespectsRetryTiming() {
        // Create multiple deliveries with different next_run times
        $now = time();

        // Delivery ready now
        $ready_delivery = [
            'event' => 'booking.confirmed',
            'url' => 'https://ready.com/webhook',
            'payload' => ['test' => 'ready'],
            'headers' => [],
            'attempt' => 1,
            'status' => 'queued'
        ];
        $ready_key = $this->queue->enqueue($ready_delivery);

        // Delivery not ready yet (future retry)
        $future_delivery = [
            'event' => 'booking.confirmed',
            'url' => 'https://future.com/webhook',
            'payload' => ['test' => 'future'],
            'headers' => [],
            'attempt' => 2,
            'status' => 'queued'
        ];
        $future_key = $this->queue->enqueue($future_delivery);

        // Manually set future next_run time
        global $wpdb;
        $future_time = date('Y-m-d H:i:s', $now + 3600); // 1 hour from now
        $wpdb->update(
            $this->table_name,
            ['next_run' => $future_time],
            ['delivery_key' => $future_key]
        );

        // Get next batch - should only include ready delivery
        $batch = $this->queue->nextBatch(10);
        $this->assertCount(1, $batch);
        $this->assertEquals($ready_key, $batch[0]['delivery_key']);
    }

    /**
     * Test manual retry resets delivery properly
     */
    public function testManualRetryResetsDeliveryProperly() {
        // Create failed delivery
        $delivery = [
            'event' => 'payment.refunded',
            'url' => 'https://retry-test.com/webhook',
            'payload' => ['payment' => ['id' => 999]],
            'headers' => [],
            'attempt' => 3,
            'status' => 'queued'
        ];

        $delivery_key = $this->queue->enqueue($delivery);

        // Mark as failed
        $this->queue->markFailure($delivery_key, 'Test failure');

        $failed_delivery = $this->queue->getDelivery($delivery_key);
        $this->assertEquals(4, $failed_delivery['attempt']);

        // Reset for retry
        $reset_success = $this->queue->resetForRetry($delivery_key);
        $this->assertTrue($reset_success);

        // Verify delivery was reset
        $reset_delivery = $this->queue->getDelivery($delivery_key);
        $this->assertEquals('queued', $reset_delivery['status']);
        $this->assertEquals(4, $reset_delivery['attempt']); // Attempt number unchanged
        $this->assertNull($reset_delivery['next_run']); // Should be processed immediately
        $this->assertNull($reset_delivery['last_error']);
        $this->assertTrue($reset_delivery['can_retry']);
    }

    /**
     * Test cannot retry successful or max-failed deliveries
     */
    public function testCannotRetrySuccessfulOrMaxFailedDeliveries() {
        // Test successful delivery
        $success_delivery = [
            'event' => 'booking.confirmed',
            'url' => 'https://success.com/webhook',
            'payload' => ['test' => 'success'],
            'headers' => [],
            'attempt' => 1,
            'status' => 'queued'
        ];
        $success_key = $this->queue->enqueue($success_delivery);
        $this->queue->markSuccess($success_key, 200, 'OK');

        $this->assertFalse($this->queue->resetForRetry($success_key));

        // Test max-failed delivery
        $failed_delivery = [
            'event' => 'booking.confirmed',
            'url' => 'https://failed.com/webhook',
            'payload' => ['test' => 'failed'],
            'headers' => [],
            'attempt' => 5,
            'status' => 'queued'
        ];
        $failed_key = $this->queue->enqueue($failed_delivery);
        $this->queue->markFailure($failed_key, 'Final failure');

        $this->assertFalse($this->queue->resetForRetry($failed_key));
    }

    /**
     * Test batch processing with mixed delivery states
     */
    public function testBatchProcessingWithMixedDeliveryStates() {
        // Create dispatcher mock that fails some and succeeds others
        $dispatcher = $this->createPartialMock(WebhookDispatcher::class, ['sendWebhook']);

        $call_count = 0;
        $dispatcher->method('sendWebhook')
                  ->willReturnCallback(function($delivery) use (&$call_count) {
                      $call_count++;
                      // Fail first delivery, succeed second
                      return $call_count === 1 ? false : true;
                  });

        // Create test deliveries
        $delivery1 = [
            'event' => 'booking.confirmed',
            'url' => 'https://fail.com/webhook',
            'payload' => ['test' => 'fail'],
            'headers' => [],
            'attempt' => 1,
            'status' => 'queued'
        ];
        $key1 = $this->queue->enqueue($delivery1);

        $delivery2 = [
            'event' => 'booking.confirmed',
            'url' => 'https://success.com/webhook',
            'payload' => ['test' => 'success'],
            'headers' => [],
            'attempt' => 1,
            'status' => 'queued'
        ];
        $key2 = $this->queue->enqueue($delivery2);

        // Process batch
        $result = $dispatcher->processQueue(10);

        $this->assertEquals(2, $result['processed']);
        $this->assertEquals(1, $result['succeeded']);
        $this->assertEquals(1, $result['failed']);

        // Verify delivery states
        $delivery1_after = $this->queue->getDelivery($key1);
        $delivery2_after = $this->queue->getDelivery($key2);

        $this->assertEquals('queued', $delivery1_after['status']); // Failed, queued for retry
        $this->assertEquals(2, $delivery1_after['attempt']);

        $this->assertEquals('success', $delivery2_after['status']); // Succeeded
        $this->assertEquals(1, $delivery2_after['attempt']);
    }

    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->table_name} WHERE url LIKE '%example.com%' OR url LIKE '%test%'");
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

        if (!class_exists('MCS_Logger')) {
            class MCS_Logger {
                public static function log($level, $message, $data = []) {
                    // Mock logger
                }
            }
        }

        // Mock WordPress database
        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new stdClass();
            $GLOBALS['wpdb']->prefix = 'wp_';
            $GLOBALS['wpdb']->prepare = function($query, ...$args) {
                return vsprintf(str_replace('%s', "'%s'", $query), $args);
            };
            $GLOBALS['wpdb']->get_results = function($query) {
                return [];
            };
            $GLOBALS['wpdb']->get_row = function($query) {
                return null;
            };
            $GLOBALS['wpdb']->insert = function($table, $data) {
                return 1;
            };
            $GLOBALS['wpdb']->update = function($table, $data, $where) {
                return 1;
            };
            $GLOBALS['wpdb']->query = function($query) {
                return 1;
            };
        }
    }
}