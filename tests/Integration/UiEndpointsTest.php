<?php
/**
 * Integration Tests for UI Endpoints
 * Tests calendar API and quote API integration with actual data
 */

use PHPUnit\Framework\TestCase;

class UiEndpointsTest extends TestCase {

    private $property_id;
    private $calendar_component;
    private $quote_component;

    protected function setUp(): void {
        parent::setUp();

        // Create test property with complete data
        $this->property_id = wp_insert_post([
            'post_type' => 'property',
            'post_title' => 'Test Property for UI Endpoints',
            'post_status' => 'publish',
            'post_content' => 'Beautiful test property for UI testing'
        ]);

        // Set up property metadata
        update_post_meta($this->property_id, 'base_rate', 150.00);
        update_post_meta($this->property_id, 'max_guests', 4);
        update_post_meta($this->property_id, 'base_guests', 2);
        update_post_meta($this->property_id, 'tax_rate', 8.5);
        update_post_meta($this->property_id, 'tax_type', 'percentage');
        update_post_meta($this->property_id, 'address', '123 Test Street, Test City');

        // Initialize UI components
        $this->calendar_component = new AvailabilityCalendar();
        $this->quote_component = new QuoteCalculator();

        // Set up test data
        $this->setupTestReservations();
        $this->setupTestRates();

        // Mock AJAX environment
        $this->setupAjaxEnvironment();
    }

    protected function tearDown(): void {
        // Clean up
        wp_delete_post($this->property_id, true);
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function setupTestReservations() {
        // Create existing reservations for availability testing
        $reservations = [
            [
                'checkin' => '2025-02-10',
                'checkout' => '2025-02-15',
                'guest_name' => 'John Doe',
                'status' => 'confirmed'
            ],
            [
                'checkin' => '2025-02-20',
                'checkout' => '2025-02-25',
                'guest_name' => 'Jane Smith',
                'status' => 'confirmed'
            ],
            [
                'checkin' => '2025-03-01',
                'checkout' => '2025-03-05',
                'guest_name' => 'Bob Johnson',
                'status' => 'pending'
            ]
        ];

        foreach ($reservations as $reservation) {
            $reservation_id = wp_insert_post([
                'post_type' => 'reservation',
                'post_title' => "Test Reservation - {$reservation['guest_name']}",
                'post_status' => $reservation['status']
            ]);

            update_post_meta($reservation_id, 'property_id', $this->property_id);
            update_post_meta($reservation_id, 'checkin_date', $reservation['checkin']);
            update_post_meta($reservation_id, 'checkout_date', $reservation['checkout']);
            update_post_meta($reservation_id, 'guest_name', $reservation['guest_name']);
        }
    }

    private function setupTestRates() {
        // Create weekend rate rule
        $weekend_rate_id = wp_insert_post([
            'post_type' => 'rate_rule',
            'post_title' => 'Weekend Premium',
            'post_status' => 'active'
        ]);

        update_post_meta($weekend_rate_id, 'property_id', $this->property_id);
        update_post_meta($weekend_rate_id, 'rule_type', 'day_of_week');
        update_post_meta($weekend_rate_id, 'applicable_days', [5, 6]); // Friday, Saturday
        update_post_meta($weekend_rate_id, 'adjustment_type', 'percentage');
        update_post_meta($weekend_rate_id, 'adjustment_value', 25);

        // Create weekly discount
        $weekly_discount_id = wp_insert_post([
            'post_type' => 'rate_rule',
            'post_title' => 'Weekly Discount',
            'post_status' => 'active'
        ]);

        update_post_meta($weekly_discount_id, 'property_id', $this->property_id);
        update_post_meta($weekly_discount_id, 'rule_type', 'length_of_stay');
        update_post_meta($weekly_discount_id, 'min_nights', 7);
        update_post_meta($weekly_discount_id, 'adjustment_type', 'percentage');
        update_post_meta($weekly_discount_id, 'adjustment_value', -10);

        // Create cleaning fee
        $cleaning_fee_id = wp_insert_post([
            'post_type' => 'rate_rule',
            'post_title' => 'Cleaning Fee',
            'post_status' => 'active'
        ]);

        update_post_meta($cleaning_fee_id, 'property_id', $this->property_id);
        update_post_meta($cleaning_fee_id, 'rule_type', 'fixed_fee');
        update_post_meta($cleaning_fee_id, 'fee_amount', 75.00);
        update_post_meta($cleaning_fee_id, 'fee_type', 'cleaning');
    }

    private function setupAjaxEnvironment() {
        // Mock WordPress AJAX functions
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }

        // Mock nonce verification
        add_filter('wp_verify_nonce', '__return_true');

        // Set up global variables that AJAX handlers expect
        $_POST['nonce'] = 'test_nonce';
    }

    private function cleanupTestData() {
        global $wpdb;
        $wpdb->delete($wpdb->posts, ['post_type' => 'reservation']);
        $wpdb->delete($wpdb->posts, ['post_type' => 'rate_rule']);
    }

    /**
     * Test calendar availability endpoint
     */
    public function testCalendarAvailabilityEndpoint() {
        // Set up request parameters
        $_POST['property_id'] = $this->property_id;
        $_POST['start_date'] = '2025-02-01';
        $_POST['end_date'] = '2025-02-28';

        // Capture output
        ob_start();
        handle_get_availability_ajax();
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        $this->assertIsArray($response, 'Should return JSON array');
        $this->assertTrue($response['success'], 'Should return success response');
        $this->assertArrayHasKey('data', $response, 'Should have data key');
        $this->assertArrayHasKey('availability', $response['data'], 'Should have availability data');

        $availability = $response['data']['availability'];

        // Test specific dates
        $this->assertArrayHasKey('2025-02-01', $availability, 'Should have availability for first date');
        $this->assertArrayHasKey('2025-02-10', $availability, 'Should have availability for reservation date');

        // Test availability logic
        $this->assertTrue($availability['2025-02-01'], 'Available date should be true');
        $this->assertFalse($availability['2025-02-10'], 'Reserved date should be false');
        $this->assertFalse($availability['2025-02-12'], 'Date within reservation should be false');
        $this->assertTrue($availability['2025-02-16'], 'Date after reservation should be true');

        // Test date range coverage
        $expected_days = 28; // February 2025 has 28 days
        $this->assertCount($expected_days, $availability, "Should have $expected_days days of availability data");
    }

    /**
     * Test calendar endpoint error handling
     */
    public function testCalendarEndpointErrorHandling() {
        // Test missing property ID
        unset($_POST['property_id']);
        $_POST['start_date'] = '2025-02-01';
        $_POST['end_date'] = '2025-02-28';

        ob_start();
        handle_get_availability_ajax();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'], 'Should fail with missing property ID');
        $this->assertArrayHasKey('data', $response, 'Should have error data');

        // Test invalid date format
        $_POST['property_id'] = $this->property_id;
        $_POST['start_date'] = 'invalid-date';
        $_POST['end_date'] = '2025-02-28';

        ob_start();
        handle_get_availability_ajax();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'], 'Should fail with invalid date format');

        // Test non-existent property
        $_POST['property_id'] = 99999;
        $_POST['start_date'] = '2025-02-01';
        $_POST['end_date'] = '2025-02-28';

        ob_start();
        handle_get_availability_ajax();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'], 'Should fail with non-existent property');
    }

    /**
     * Test quote calculation endpoint
     */
    public function testQuoteCalculationEndpoint() {
        // Set up request parameters for weekend stay
        $_POST['property_id'] = $this->property_id;
        $_POST['checkin'] = '2025-02-07'; // Friday
        $_POST['checkout'] = '2025-02-10'; // Monday (3 nights, includes weekend)
        $_POST['guests'] = 2;

        // Capture output
        ob_start();
        handle_calculate_quote_ajax();
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        $this->assertIsArray($response, 'Should return JSON array');
        $this->assertTrue($response['success'], 'Should return success response');
        $this->assertArrayHasKey('data', $response, 'Should have data key');

        $quote_data = $response['data'];

        // Test quote structure
        $this->assertArrayHasKey('total', $quote_data, 'Should have total amount');
        $this->assertArrayHasKey('breakdown', $quote_data, 'Should have breakdown');

        // Test total is positive and reasonable
        $this->assertGreaterThan(0, $quote_data['total'], 'Total should be positive');
        $this->assertLessThan(10000, $quote_data['total'], 'Total should be reasonable');

        // Test breakdown structure
        $breakdown = $quote_data['breakdown'];
        $this->assertArrayHasKey('accommodation', $breakdown, 'Should have accommodation breakdown');
        $this->assertArrayHasKey('fees', $breakdown, 'Should have fees breakdown');

        // Test accommodation breakdown for weekend rates
        $accommodation = $breakdown['accommodation'];
        $this->assertGreaterThan(0, count($accommodation), 'Should have accommodation items');

        // Look for weekend premium in accommodation
        $weekend_nights = array_filter($accommodation, function($item) {
            return $item['amount'] > 150; // Should be higher than base rate due to weekend premium
        });
        $this->assertGreaterThan(0, count($weekend_nights), 'Should apply weekend premium');

        // Test fees (should include cleaning fee and taxes)
        $fees = $breakdown['fees'];
        $this->assertGreaterThan(0, count($fees), 'Should have fees');

        $cleaning_fees = array_filter($fees, function($item) {
            return strpos(strtolower($item['label']), 'cleaning') !== false;
        });
        $this->assertGreaterThan(0, count($cleaning_fees), 'Should include cleaning fee');

        $tax_fees = array_filter($fees, function($item) {
            return strpos(strtolower($item['label']), 'tax') !== false;
        });
        $this->assertGreaterThan(0, count($tax_fees), 'Should include tax');
    }

    /**
     * Test quote with weekly discount
     */
    public function testQuoteWithWeeklyDiscount() {
        // Set up request for 7-night stay
        $_POST['property_id'] = $this->property_id;
        $_POST['checkin'] = '2025-03-10';
        $_POST['checkout'] = '2025-03-17'; // 7 nights
        $_POST['guests'] = 3; // Extra guest

        ob_start();
        handle_calculate_quote_ajax();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'], 'Weekly booking should succeed');

        $quote_data = $response['data'];
        $breakdown = $quote_data['breakdown'];

        // Test for weekly discount in adjustments
        $this->assertArrayHasKey('adjustments', $breakdown, 'Should have adjustments for weekly stay');

        $adjustments = $breakdown['adjustments'];
        $weekly_discounts = array_filter($adjustments, function($item) {
            return $item['amount'] < 0 && (
                strpos(strtolower($item['label']), 'weekly') !== false ||
                strpos(strtolower($item['label']), 'length') !== false
            );
        });
        $this->assertGreaterThan(0, count($weekly_discounts), 'Should apply weekly discount');

        // Test extra guest fees
        $fees = $breakdown['fees'];
        $guest_fees = array_filter($fees, function($item) {
            return strpos(strtolower($item['label']), 'guest') !== false ||
                   strpos(strtolower($item['label']), 'additional') !== false;
        });
        $this->assertGreaterThan(0, count($guest_fees), 'Should charge for extra guest');
    }

    /**
     * Test quote endpoint error handling
     */
    public function testQuoteEndpointErrorHandling() {
        // Test invalid dates (checkout before checkin)
        $_POST['property_id'] = $this->property_id;
        $_POST['checkin'] = '2025-02-10';
        $_POST['checkout'] = '2025-02-08'; // Before checkin
        $_POST['guests'] = 2;

        ob_start();
        handle_calculate_quote_ajax();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'], 'Should fail with invalid date order');

        // Test past dates
        $_POST['checkin'] = '2024-01-01'; // Past date
        $_POST['checkout'] = '2024-01-05';

        ob_start();
        handle_calculate_quote_ajax();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'], 'Should fail with past dates');

        // Test overlapping with existing reservation
        $_POST['checkin'] = '2025-02-12'; // Overlaps with existing reservation
        $_POST['checkout'] = '2025-02-16';

        ob_start();
        handle_calculate_quote_ajax();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'], 'Should fail with overlapping reservation');
    }

    /**
     * Test calendar and quote integration
     */
    public function testCalendarQuoteIntegration() {
        // First, get availability data
        $_POST['property_id'] = $this->property_id;
        $_POST['start_date'] = '2025-03-01';
        $_POST['end_date'] = '2025-03-31';

        ob_start();
        handle_get_availability_ajax();
        $availability_output = ob_get_clean();

        $availability_response = json_decode($availability_output, true);
        $this->assertTrue($availability_response['success'], 'Availability request should succeed');

        $availability = $availability_response['data']['availability'];

        // Find available dates
        $available_dates = array_keys(array_filter($availability, function($is_available) {
            return $is_available === true;
        }));

        $this->assertGreaterThan(7, count($available_dates), 'Should have enough available dates for testing');

        // Use available dates for quote calculation
        $checkin = $available_dates[0];
        $checkout_index = min(3, count($available_dates) - 1); // 3-night stay or max available
        $checkout = $available_dates[$checkout_index];

        // Ensure checkout is after checkin
        if (strtotime($checkout) <= strtotime($checkin)) {
            $checkout = date('Y-m-d', strtotime($checkin . ' +3 days'));
        }

        // Calculate quote for available dates
        $_POST['checkin'] = $checkin;
        $_POST['checkout'] = $checkout;
        $_POST['guests'] = 2;

        ob_start();
        handle_calculate_quote_ajax();
        $quote_output = ob_get_clean();

        $quote_response = json_decode($quote_output, true);
        $this->assertTrue($quote_response['success'], 'Quote calculation for available dates should succeed');

        // Test that quote calculation respects availability
        $quote_data = $quote_response['data'];
        $this->assertGreaterThan(0, $quote_data['total'], 'Should generate valid quote for available dates');
    }

    /**
     * Test AJAX security and nonce verification
     */
    public function testAjaxSecurity() {
        // Remove nonce verification filter to test actual nonce checking
        remove_filter('wp_verify_nonce', '__return_true');

        // Test with invalid nonce
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['property_id'] = $this->property_id;
        $_POST['start_date'] = '2025-02-01';
        $_POST['end_date'] = '2025-02-28';

        ob_start();
        handle_get_availability_ajax();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'], 'Should fail with invalid nonce');
        $this->assertStringContainsString('security', strtolower($response['data']['message']), 'Should mention security failure');

        // Restore filter for other tests
        add_filter('wp_verify_nonce', '__return_true');
    }

    /**
     * Test performance with large date ranges
     */
    public function testPerformanceWithLargeDateRanges() {
        // Test with 1-year date range
        $_POST['property_id'] = $this->property_id;
        $_POST['start_date'] = '2025-01-01';
        $_POST['end_date'] = '2025-12-31';

        $start_time = microtime(true);

        ob_start();
        handle_get_availability_ajax();
        $output = ob_get_clean();

        $end_time = microtime(true);
        $execution_time = $end_time - $start_time;

        $response = json_decode($output, true);
        $this->assertTrue($response['success'], 'Large date range should succeed');

        // Should complete within reasonable time (5 seconds)
        $this->assertLessThan(5.0, $execution_time, 'Should complete large date range within 5 seconds');

        // Should return correct number of days
        $availability = $response['data']['availability'];
        $this->assertEquals(365, count($availability), 'Should return 365 days for full year');
    }
}