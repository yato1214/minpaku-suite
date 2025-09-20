<?php
/**
 * Booking State Integration Tests
 * Tests booking state machine functionality
 *
 * @package MinpakuSuite
 */

use PHPUnit\Framework\TestCase;

class BookingStateTest extends TestCase {

    /**
     * Booking instance for testing
     */
    private $booking;

    /**
     * Booking repository
     */
    private $repository;

    /**
     * Booking service
     */
    private $service;

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
        require_once __DIR__ . '/../../includes/Booking/Booking.php';
        require_once __DIR__ . '/../../includes/Booking/BookingRepository.php';
        require_once __DIR__ . '/../../includes/Services/BookingService.php';

        // Initialize test objects
        $this->repository = $this->createMock(BookingRepository::class);
        $this->service = $this->createMock(BookingService::class);

        // Create test booking
        $this->booking = new Booking([
            'id' => 1,
            'property_id' => 123,
            'checkin' => '2025-10-01',
            'checkout' => '2025-10-05',
            'adults' => 2,
            'children' => 0,
            'state' => Booking::STATE_DRAFT
        ]);
    }

    /**
     * Test draft to pending transition succeeds
     */
    public function testDraftToPendingTransitionSucceeds() {
        $result = $this->booking->transitionTo(Booking::STATE_PENDING);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(Booking::STATE_PENDING, $result->getNewState());
        $this->assertEquals(Booking::STATE_PENDING, $this->booking->getState());
    }

    /**
     * Test pending to confirmed transition succeeds
     */
    public function testPendingToConfirmedTransitionSucceeds() {
        // First move to pending
        $this->booking->transitionTo(Booking::STATE_PENDING);

        // Then move to confirmed
        $result = $this->booking->transitionTo(Booking::STATE_CONFIRMED, [
            'payment_method' => 'credit_card'
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(Booking::STATE_CONFIRMED, $result->getNewState());
        $this->assertEquals(Booking::STATE_CONFIRMED, $this->booking->getState());
    }

    /**
     * Test full workflow: draft → pending → confirmed
     */
    public function testFullWorkflowDraftToPendingToConfirmed() {
        // Step 1: Draft to Pending
        $result1 = $this->booking->transitionTo(Booking::STATE_PENDING);
        $this->assertTrue($result1->isSuccess());
        $this->assertEquals(Booking::STATE_PENDING, $this->booking->getState());

        // Step 2: Pending to Confirmed
        $result2 = $this->booking->transitionTo(Booking::STATE_CONFIRMED, [
            'payment_method' => 'credit_card'
        ]);
        $this->assertTrue($result2->isSuccess());
        $this->assertEquals(Booking::STATE_CONFIRMED, $this->booking->getState());
    }

    /**
     * Test confirmed to cancelled transition succeeds
     */
    public function testConfirmedToCancelledTransitionSucceeds() {
        // First move to confirmed
        $this->booking->transitionTo(Booking::STATE_PENDING);
        $this->booking->transitionTo(Booking::STATE_CONFIRMED, [
            'payment_method' => 'credit_card'
        ]);

        // Then cancel
        $result = $this->booking->transitionTo(Booking::STATE_CANCELLED, [
            'reason' => 'Customer request'
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(Booking::STATE_CANCELLED, $result->getNewState());
        $this->assertEquals(Booking::STATE_CANCELLED, $this->booking->getState());
    }

    /**
     * Test cancelled to completed transition fails
     */
    public function testCancelledToCompletedTransitionFails() {
        // Move to cancelled state
        $this->booking->transitionTo(Booking::STATE_PENDING);
        $this->booking->transitionTo(Booking::STATE_CONFIRMED, [
            'payment_method' => 'credit_card'
        ]);
        $this->booking->transitionTo(Booking::STATE_CANCELLED);

        // Try to complete (should fail)
        $result = $this->booking->transitionTo(Booking::STATE_COMPLETED);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('invalid_transition', $result->getErrorCode());
        $this->assertStringContains('terminal state', $result->getErrorMessage());
        $this->assertEquals(Booking::STATE_CANCELLED, $this->booking->getState());
    }

    /**
     * Test completed state is terminal
     */
    public function testCompletedStateIsTerminal() {
        // Move to completed state
        $this->booking->transitionTo(Booking::STATE_PENDING);
        $this->booking->transitionTo(Booking::STATE_CONFIRMED, [
            'payment_method' => 'credit_card'
        ]);
        $this->booking->transitionTo(Booking::STATE_COMPLETED);

        // Try to transition from completed (should fail)
        $result = $this->booking->transitionTo(Booking::STATE_CANCELLED);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('invalid_transition', $result->getErrorCode());
        $this->assertStringContains('terminal state', $result->getErrorMessage());
        $this->assertEquals(Booking::STATE_COMPLETED, $this->booking->getState());
    }

    /**
     * Test all transitions from completed state fail
     */
    public function testAllTransitionsFromCompletedStateFail() {
        // Move to completed state
        $this->booking->transitionTo(Booking::STATE_PENDING);
        $this->booking->transitionTo(Booking::STATE_CONFIRMED, [
            'payment_method' => 'credit_card'
        ]);
        $this->booking->transitionTo(Booking::STATE_COMPLETED);

        $states_to_try = [
            Booking::STATE_DRAFT,
            Booking::STATE_PENDING,
            Booking::STATE_CONFIRMED,
            Booking::STATE_CANCELLED
        ];

        foreach ($states_to_try as $state) {
            $result = $this->booking->transitionTo($state);
            $this->assertFalse($result->isSuccess());
            $this->assertEquals('invalid_transition', $result->getErrorCode());
            $this->assertEquals(Booking::STATE_COMPLETED, $this->booking->getState());
        }
    }

    /**
     * Test invalid transitions fail with proper error codes
     */
    public function testInvalidTransitionsFailWithProperErrorCodes() {
        // Test draft to confirmed (should skip pending)
        $result1 = $this->booking->transitionTo(Booking::STATE_CONFIRMED);
        $this->assertFalse($result1->isSuccess());
        $this->assertEquals('invalid_transition', $result1->getErrorCode());

        // Test draft to completed
        $result2 = $this->booking->transitionTo(Booking::STATE_COMPLETED);
        $this->assertFalse($result2->isSuccess());
        $this->assertEquals('invalid_transition', $result2->getErrorCode());

        // Test draft to cancelled
        $result3 = $this->booking->transitionTo(Booking::STATE_CANCELLED);
        $this->assertFalse($result3->isSuccess());
        $this->assertEquals('invalid_transition', $result3->getErrorCode());
    }

    /**
     * Test validation errors
     */
    public function testValidationErrors() {
        // Test transition to confirmed without payment method
        $this->booking->transitionTo(Booking::STATE_PENDING);

        $result = $this->booking->transitionTo(Booking::STATE_CONFIRMED);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('missing_payment_method', $result->getErrorCode());
    }

    /**
     * Test completion before checkout date fails
     */
    public function testCompletionBeforeCheckoutDateFails() {
        // Create booking with future checkout date
        $futureBooking = new Booking([
            'id' => 2,
            'property_id' => 123,
            'checkin' => date('Y-m-d', strtotime('+1 day')),
            'checkout' => date('Y-m-d', strtotime('+5 days')),
            'adults' => 2,
            'children' => 0,
            'state' => Booking::STATE_CONFIRMED
        ]);

        $result = $futureBooking->transitionTo(Booking::STATE_COMPLETED);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('premature_completion', $result->getErrorCode());
    }

    /**
     * Test booking data validation
     */
    public function testBookingDataValidation() {
        // Test missing property ID
        $invalidBooking1 = new Booking([
            'checkin' => '2025-10-01',
            'checkout' => '2025-10-05'
        ]);

        $result1 = $invalidBooking1->transitionTo(Booking::STATE_PENDING);
        $this->assertFalse($result1->isSuccess());
        $this->assertEquals('missing_property', $result1->getErrorCode());

        // Test invalid date order
        $invalidBooking2 = new Booking([
            'property_id' => 123,
            'checkin' => '2025-10-05',
            'checkout' => '2025-10-01', // Before checkin
            'adults' => 2
        ]);

        $result2 = $invalidBooking2->transitionTo(Booking::STATE_PENDING);
        $this->assertFalse($result2->isSuccess());
        $this->assertEquals('invalid_date_order', $result2->getErrorCode());

        // Test invalid guest count
        $invalidBooking3 = new Booking([
            'property_id' => 123,
            'checkin' => '2025-10-01',
            'checkout' => '2025-10-05',
            'adults' => 0 // Invalid
        ]);

        $result3 = $invalidBooking3->transitionTo(Booking::STATE_PENDING);
        $this->assertFalse($result3->isSuccess());
        $this->assertEquals('invalid_guest_count', $result3->getErrorCode());
    }

    /**
     * Test can transition static method
     */
    public function testCanTransitionStaticMethod() {
        // Valid transitions
        $this->assertTrue(Booking::canTransition(Booking::STATE_DRAFT, Booking::STATE_PENDING));
        $this->assertTrue(Booking::canTransition(Booking::STATE_PENDING, Booking::STATE_CONFIRMED));
        $this->assertTrue(Booking::canTransition(Booking::STATE_CONFIRMED, Booking::STATE_CANCELLED));
        $this->assertTrue(Booking::canTransition(Booking::STATE_CONFIRMED, Booking::STATE_COMPLETED));

        // Invalid transitions
        $this->assertFalse(Booking::canTransition(Booking::STATE_DRAFT, Booking::STATE_CONFIRMED));
        $this->assertFalse(Booking::canTransition(Booking::STATE_CANCELLED, Booking::STATE_COMPLETED));
        $this->assertFalse(Booking::canTransition(Booking::STATE_COMPLETED, Booking::STATE_CANCELLED));
    }

    /**
     * Test terminal state detection
     */
    public function testTerminalStateDetection() {
        $this->assertFalse($this->booking->isTerminal()); // Draft state

        $this->booking->transitionTo(Booking::STATE_PENDING);
        $this->assertFalse($this->booking->isTerminal()); // Pending state

        $this->booking->transitionTo(Booking::STATE_CONFIRMED, [
            'payment_method' => 'credit_card'
        ]);
        $this->assertFalse($this->booking->isTerminal()); // Confirmed state

        $this->booking->transitionTo(Booking::STATE_CANCELLED);
        $this->assertTrue($this->booking->isTerminal()); // Cancelled state (terminal)
    }

    /**
     * Test modification capability
     */
    public function testModificationCapability() {
        $this->assertTrue($this->booking->canBeModified()); // Draft state

        $this->booking->transitionTo(Booking::STATE_PENDING);
        $this->assertTrue($this->booking->canBeModified()); // Pending state

        $this->booking->transitionTo(Booking::STATE_CONFIRMED, [
            'payment_method' => 'credit_card'
        ]);
        $this->assertTrue($this->booking->canBeModified()); // Confirmed state

        $this->booking->transitionTo(Booking::STATE_CANCELLED);
        $this->assertFalse($this->booking->canBeModified()); // Cancelled state (terminal)
    }

    /**
     * Mock WordPress environment for testing
     */
    private function mockWordPressEnvironment() {
        if (!function_exists('current_time')) {
            function current_time($type) {
                return $type === 'mysql' ? date('Y-m-d H:i:s') : time();
            }
        }

        if (!function_exists('__')) {
            function __($text, $domain = '') {
                return $text;
            }
        }

        if (!function_exists('_n')) {
            function _n($single, $plural, $number, $domain = '') {
                return $number === 1 ? $single : $plural;
            }
        }

        if (!class_exists('DateTime')) {
            // DateTime should be available in PHP, but just in case
        }
    }
}