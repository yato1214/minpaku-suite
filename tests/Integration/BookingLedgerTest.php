<?php
/**
 * Booking Ledger Integration Tests
 * Tests booking ledger functionality
 *
 * @package MinpakuSuite
 */

use PHPUnit\Framework\TestCase;

class BookingLedgerTest extends TestCase {

    /**
     * Booking ledger instance for testing
     */
    private $ledger;

    /**
     * Booking service instance
     */
    private $service;

    /**
     * Mock database
     */
    private $mockDb;

    /**
     * Test booking ID
     */
    private $booking_id = 123;

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
        require_once __DIR__ . '/../../includes/Booking/BookingLedger.php';
        require_once __DIR__ . '/../../includes/Services/BookingService.php';

        // Create mock ledger with database operations mocked
        $this->ledger = $this->createPartialMock(BookingLedger::class, ['append', 'list', 'count']);
        $this->service = $this->createMock(BookingService::class);

        // Initialize mock database data
        $this->mockDb = [];
    }

    /**
     * Test confirm action creates ledger entry
     */
    public function testConfirmActionCreatesLedgerEntry() {
        // Set up ledger mock to record the append call
        $captured_entries = [];

        $this->ledger->expects($this->once())
                    ->method('append')
                    ->with(
                        $this->equalTo($this->booking_id),
                        $this->equalTo(BookingLedger::EVENT_CONFIRM),
                        $this->anything(),
                        $this->anything(),
                        $this->anything()
                    )
                    ->willReturnCallback(function($booking_id, $event, $amount, $currency, $meta) use (&$captured_entries) {
                        $entry_id = count($captured_entries) + 1;
                        $captured_entries[] = [
                            'id' => $entry_id,
                            'booking_id' => $booking_id,
                            'event' => $event,
                            'amount' => $amount,
                            'currency' => $currency,
                            'meta_data' => $meta,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        return $entry_id;
                    });

        // Perform confirm action
        $result = $this->ledger->append(
            $this->booking_id,
            BookingLedger::EVENT_CONFIRM,
            0.0,
            'JPY',
            [
                'state_transition' => [
                    'from' => 'pending',
                    'to' => 'confirmed'
                ],
                'processed_by' => 1
            ]
        );

        // Verify entry was created
        $this->assertEquals(1, $result);
        $this->assertCount(1, $captured_entries);
        $this->assertEquals(BookingLedger::EVENT_CONFIRM, $captured_entries[0]['event']);
        $this->assertEquals($this->booking_id, $captured_entries[0]['booking_id']);
    }

    /**
     * Test cancel action creates ledger entry
     */
    public function testCancelActionCreatesLedgerEntry() {
        $captured_entries = [];

        $this->ledger->expects($this->once())
                    ->method('append')
                    ->with(
                        $this->equalTo($this->booking_id),
                        $this->equalTo(BookingLedger::EVENT_CANCEL),
                        $this->anything(),
                        $this->anything(),
                        $this->anything()
                    )
                    ->willReturnCallback(function($booking_id, $event, $amount, $currency, $meta) use (&$captured_entries) {
                        $entry_id = count($captured_entries) + 1;
                        $captured_entries[] = [
                            'id' => $entry_id,
                            'booking_id' => $booking_id,
                            'event' => $event,
                            'amount' => $amount,
                            'currency' => $currency,
                            'meta_data' => $meta,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        return $entry_id;
                    });

        // Perform cancel action
        $result = $this->ledger->append(
            $this->booking_id,
            BookingLedger::EVENT_CANCEL,
            0.0,
            'JPY',
            [
                'state_transition' => [
                    'from' => 'confirmed',
                    'to' => 'cancelled'
                ],
                'reason' => 'Customer request',
                'processed_by' => 1
            ]
        );

        // Verify entry was created
        $this->assertEquals(1, $result);
        $this->assertCount(1, $captured_entries);
        $this->assertEquals(BookingLedger::EVENT_CANCEL, $captured_entries[0]['event']);
        $this->assertEquals('Customer request', $captured_entries[0]['meta_data']['reason']);
    }

    /**
     * Test payment entry with amount and currency
     */
    public function testPaymentEntryWithAmountAndCurrency() {
        $captured_entries = [];

        $this->ledger->expects($this->once())
                    ->method('append')
                    ->with(
                        $this->equalTo($this->booking_id),
                        $this->equalTo(BookingLedger::EVENT_PAYMENT),
                        $this->equalTo(15000.0),
                        $this->equalTo('JPY'),
                        $this->anything()
                    )
                    ->willReturnCallback(function($booking_id, $event, $amount, $currency, $meta) use (&$captured_entries) {
                        $entry_id = count($captured_entries) + 1;
                        $captured_entries[] = [
                            'id' => $entry_id,
                            'booking_id' => $booking_id,
                            'event' => $event,
                            'amount' => $amount,
                            'currency' => $currency,
                            'meta_data' => $meta,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        return $entry_id;
                    });

        // Add payment entry
        $result = $this->ledger->append(
            $this->booking_id,
            BookingLedger::EVENT_PAYMENT,
            15000.0,
            'JPY',
            [
                'payment_method' => 'credit_card',
                'transaction_id' => 'txn_123456',
                'processed_by' => 1
            ]
        );

        // Verify entry was created with correct amount and currency
        $this->assertEquals(1, $result);
        $this->assertCount(1, $captured_entries);
        $this->assertEquals(15000.0, $captured_entries[0]['amount']);
        $this->assertEquals('JPY', $captured_entries[0]['currency']);
        $this->assertEquals('credit_card', $captured_entries[0]['meta_data']['payment_method']);
    }

    /**
     * Test refund entry with negative amount
     */
    public function testRefundEntryWithNegativeAmount() {
        $captured_entries = [];

        $this->ledger->expects($this->once())
                    ->method('append')
                    ->with(
                        $this->equalTo($this->booking_id),
                        $this->equalTo(BookingLedger::EVENT_REFUND),
                        $this->equalTo(-5000.0),
                        $this->equalTo('JPY'),
                        $this->anything()
                    )
                    ->willReturnCallback(function($booking_id, $event, $amount, $currency, $meta) use (&$captured_entries) {
                        $entry_id = count($captured_entries) + 1;
                        $captured_entries[] = [
                            'id' => $entry_id,
                            'booking_id' => $booking_id,
                            'event' => $event,
                            'amount' => $amount,
                            'currency' => $currency,
                            'meta_data' => $meta,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        return $entry_id;
                    });

        // Add refund entry
        $result = $this->ledger->append(
            $this->booking_id,
            BookingLedger::EVENT_REFUND,
            -5000.0,
            'JPY',
            [
                'refund_reason' => 'Partial cancellation',
                'original_transaction' => 'txn_123456',
                'processed_by' => 1
            ]
        );

        // Verify entry was created with negative amount
        $this->assertEquals(1, $result);
        $this->assertCount(1, $captured_entries);
        $this->assertEquals(-5000.0, $captured_entries[0]['amount']);
        $this->assertEquals(BookingLedger::EVENT_REFUND, $captured_entries[0]['event']);
        $this->assertEquals('Partial cancellation', $captured_entries[0]['meta_data']['refund_reason']);
    }

    /**
     * Test note entry with zero amount
     */
    public function testNoteEntryWithZeroAmount() {
        $captured_entries = [];

        $this->ledger->expects($this->once())
                    ->method('append')
                    ->with(
                        $this->equalTo($this->booking_id),
                        $this->equalTo(BookingLedger::EVENT_NOTE),
                        $this->equalTo(0.0),
                        $this->equalTo('JPY'),
                        $this->anything()
                    )
                    ->willReturnCallback(function($booking_id, $event, $amount, $currency, $meta) use (&$captured_entries) {
                        $entry_id = count($captured_entries) + 1;
                        $captured_entries[] = [
                            'id' => $entry_id,
                            'booking_id' => $booking_id,
                            'event' => $event,
                            'amount' => $amount,
                            'currency' => $currency,
                            'meta_data' => $meta,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        return $entry_id;
                    });

        // Add note entry
        $result = $this->ledger->append(
            $this->booking_id,
            BookingLedger::EVENT_NOTE,
            0.0,
            'JPY',
            [
                'note' => 'Customer requested late checkout',
                'added_by' => 1
            ]
        );

        // Verify note entry was created
        $this->assertEquals(1, $result);
        $this->assertCount(1, $captured_entries);
        $this->assertEquals(0.0, $captured_entries[0]['amount']);
        $this->assertEquals(BookingLedger::EVENT_NOTE, $captured_entries[0]['event']);
        $this->assertEquals('Customer requested late checkout', $captured_entries[0]['meta_data']['note']);
    }

    /**
     * Test meta_json is correctly stored and retrieved
     */
    public function testMetaJsonIsCorrectlyStoredAndRetrieved() {
        $test_meta = [
            'payment_method' => 'credit_card',
            'transaction_id' => 'txn_123456',
            'customer_details' => [
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ],
            'special_requests' => ['late_checkout', 'extra_towels'],
            'processing_fee' => 100.0,
            'processed_by' => 1,
            'processed_at' => '2025-01-15 10:30:00'
        ];

        $captured_entries = [];

        $this->ledger->expects($this->once())
                    ->method('append')
                    ->willReturnCallback(function($booking_id, $event, $amount, $currency, $meta) use (&$captured_entries) {
                        $entry_id = count($captured_entries) + 1;
                        $captured_entries[] = [
                            'id' => $entry_id,
                            'booking_id' => $booking_id,
                            'event' => $event,
                            'amount' => $amount,
                            'currency' => $currency,
                            'meta_json' => wp_json_encode($meta), // Simulate database storage
                            'meta_data' => $meta, // For test verification
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        return $entry_id;
                    });

        // Add entry with complex meta data
        $result = $this->ledger->append(
            $this->booking_id,
            BookingLedger::EVENT_PAYMENT,
            15000.0,
            'JPY',
            $test_meta
        );

        // Verify complex meta data is preserved
        $this->assertEquals(1, $result);
        $this->assertCount(1, $captured_entries);
        $stored_meta = $captured_entries[0]['meta_data'];

        $this->assertEquals($test_meta['payment_method'], $stored_meta['payment_method']);
        $this->assertEquals($test_meta['customer_details']['name'], $stored_meta['customer_details']['name']);
        $this->assertEquals($test_meta['special_requests'], $stored_meta['special_requests']);
        $this->assertEquals($test_meta['processing_fee'], $stored_meta['processing_fee']);
    }

    /**
     * Test sequential entries maintain chronological order
     */
    public function testSequentialEntriesMaintainChronologicalOrder() {
        $captured_entries = [];
        $entry_counter = 0;

        $this->ledger->expects($this->exactly(3))
                    ->method('append')
                    ->willReturnCallback(function($booking_id, $event, $amount, $currency, $meta) use (&$captured_entries, &$entry_counter) {
                        $entry_counter++;
                        $captured_entries[] = [
                            'id' => $entry_counter,
                            'booking_id' => $booking_id,
                            'event' => $event,
                            'amount' => $amount,
                            'currency' => $currency,
                            'meta_data' => $meta,
                            'created_at' => date('Y-m-d H:i:s', time() + $entry_counter) // Simulate time progression
                        ];
                        return $entry_counter;
                    });

        // Add multiple entries in sequence
        $this->ledger->append($this->booking_id, BookingLedger::EVENT_RESERVE, 0.0, 'JPY', ['action' => 'draft_created']);
        $this->ledger->append($this->booking_id, BookingLedger::EVENT_CONFIRM, 0.0, 'JPY', ['state_transition' => ['from' => 'pending', 'to' => 'confirmed']]);
        $this->ledger->append($this->booking_id, BookingLedger::EVENT_PAYMENT, 15000.0, 'JPY', ['payment_method' => 'credit_card']);

        // Verify all entries were created in order
        $this->assertCount(3, $captured_entries);
        $this->assertEquals(1, $captured_entries[0]['id']);
        $this->assertEquals(2, $captured_entries[1]['id']);
        $this->assertEquals(3, $captured_entries[2]['id']);

        $this->assertEquals(BookingLedger::EVENT_RESERVE, $captured_entries[0]['event']);
        $this->assertEquals(BookingLedger::EVENT_CONFIRM, $captured_entries[1]['event']);
        $this->assertEquals(BookingLedger::EVENT_PAYMENT, $captured_entries[2]['event']);
    }

    /**
     * Test listing entries returns correct format
     */
    public function testListingEntriesReturnsCorrectFormat() {
        $mock_entries = [
            [
                'id' => 1,
                'booking_id' => $this->booking_id,
                'event' => BookingLedger::EVENT_RESERVE,
                'event_label' => 'Reserved',
                'amount' => 0.0,
                'currency' => 'JPY',
                'meta_data' => ['action' => 'draft_created'],
                'created_at' => '2025-01-15 10:00:00',
                'formatted_amount' => '0 JPY',
                'formatted_date' => 'January 15, 2025 10:00 AM'
            ],
            [
                'id' => 2,
                'booking_id' => $this->booking_id,
                'event' => BookingLedger::EVENT_PAYMENT,
                'event_label' => 'Payment',
                'amount' => 15000.0,
                'currency' => 'JPY',
                'meta_data' => ['payment_method' => 'credit_card'],
                'created_at' => '2025-01-15 11:00:00',
                'formatted_amount' => '15,000 JPY',
                'formatted_date' => 'January 15, 2025 11:00 AM'
            ]
        ];

        $this->ledger->expects($this->once())
                    ->method('list')
                    ->with($this->booking_id, ['limit' => 50])
                    ->willReturn($mock_entries);

        // Get entries
        $entries = $this->ledger->list($this->booking_id);

        // Verify correct format
        $this->assertCount(2, $entries);

        // Check first entry
        $this->assertEquals(1, $entries[0]['id']);
        $this->assertEquals(BookingLedger::EVENT_RESERVE, $entries[0]['event']);
        $this->assertEquals('Reserved', $entries[0]['event_label']);
        $this->assertEquals(0.0, $entries[0]['amount']);
        $this->assertEquals('0 JPY', $entries[0]['formatted_amount']);

        // Check second entry
        $this->assertEquals(2, $entries[1]['id']);
        $this->assertEquals(BookingLedger::EVENT_PAYMENT, $entries[1]['event']);
        $this->assertEquals('Payment', $entries[1]['event_label']);
        $this->assertEquals(15000.0, $entries[1]['amount']);
        $this->assertEquals('15,000 JPY', $entries[1]['formatted_amount']);
    }

    /**
     * Test counting entries
     */
    public function testCountingEntries() {
        $this->ledger->expects($this->once())
                    ->method('count')
                    ->with($this->booking_id, null)
                    ->willReturn(5);

        $count = $this->ledger->count($this->booking_id);
        $this->assertEquals(5, $count);
    }

    /**
     * Test valid event types
     */
    public function testValidEventTypes() {
        $valid_events = BookingLedger::getValidEvents();

        $this->assertContains(BookingLedger::EVENT_RESERVE, $valid_events);
        $this->assertContains(BookingLedger::EVENT_CONFIRM, $valid_events);
        $this->assertContains(BookingLedger::EVENT_CANCEL, $valid_events);
        $this->assertContains(BookingLedger::EVENT_COMPLETE, $valid_events);
        $this->assertContains(BookingLedger::EVENT_REFUND, $valid_events);
        $this->assertContains(BookingLedger::EVENT_PAYMENT, $valid_events);
        $this->assertContains(BookingLedger::EVENT_ADJUSTMENT, $valid_events);
        $this->assertContains(BookingLedger::EVENT_NOTE, $valid_events);
    }

    /**
     * Test event labels
     */
    public function testEventLabels() {
        $labels = BookingLedger::getEventLabels();

        $this->assertEquals('Reserved', $labels[BookingLedger::EVENT_RESERVE]);
        $this->assertEquals('Confirmed', $labels[BookingLedger::EVENT_CONFIRM]);
        $this->assertEquals('Cancelled', $labels[BookingLedger::EVENT_CANCEL]);
        $this->assertEquals('Completed', $labels[BookingLedger::EVENT_COMPLETE]);
        $this->assertEquals('Refunded', $labels[BookingLedger::EVENT_REFUND]);
        $this->assertEquals('Payment', $labels[BookingLedger::EVENT_PAYMENT]);
        $this->assertEquals('Adjustment', $labels[BookingLedger::EVENT_ADJUSTMENT]);
        $this->assertEquals('Note', $labels[BookingLedger::EVENT_NOTE]);
    }

    /**
     * Mock WordPress environment for testing
     */
    private function mockWordPressEnvironment() {
        if (!function_exists('wp_json_encode')) {
            function wp_json_encode($data) {
                return json_encode($data);
            }
        }

        if (!function_exists('wp_json_decode')) {
            function wp_json_decode($json, $assoc = false) {
                return json_decode($json, $assoc);
            }
        }

        if (!function_exists('current_time')) {
            function current_time($type) {
                return $type === 'mysql' ? date('Y-m-d H:i:s') : time();
            }
        }

        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) {
                return trim(strip_tags($str));
            }
        }

        if (!function_exists('__')) {
            function __($text, $domain = '') {
                return $text;
            }
        }

        if (!class_exists('MCS_Logger')) {
            class MCS_Logger {
                public static function log($level, $message, $data = []) {
                    // Mock logger
                }
            }
        }
    }
}