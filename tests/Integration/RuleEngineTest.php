<?php
/**
 * Integration Tests for Rule Engine System
 * Tests minimum stay, day-of-week restrictions, and buffer day applications
 */

use PHPUnit\Framework\TestCase;

class RuleEngineTest extends TestCase {

    private $property_id;
    private $rule_engine;

    protected function setUp(): void {
        parent::setUp();

        // Create test property
        $this->property_id = wp_insert_post([
            'post_type' => 'property',
            'post_title' => 'Test Property for Rules',
            'post_status' => 'publish'
        ]);

        // Initialize rule engine
        $this->rule_engine = new RuleEngine();

        // Clean up existing rules
        $this->cleanupTestRules();
    }

    protected function tearDown(): void {
        // Clean up
        wp_delete_post($this->property_id, true);
        $this->cleanupTestRules();
        parent::tearDown();
    }

    private function cleanupTestRules() {
        global $wpdb;
        $wpdb->delete($wpdb->posts, ['post_type' => 'booking_rule']);
    }

    /**
     * Test minimum stay rule validation
     */
    public function testMinimumStayRule() {
        // Create minimum stay rule: 3 nights minimum
        $rule_id = $this->createMinStayRule(3);

        // Test valid booking (3 nights)
        $valid_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-01',
            'checkout' => '2025-02-04',
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($valid_booking);
        $this->assertTrue($result['is_valid'], 'Valid 3-night booking should pass');
        $this->assertEmpty($result['errors'], 'Should have no errors');

        // Test invalid booking (2 nights)
        $invalid_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-01',
            'checkout' => '2025-02-03',
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($invalid_booking);
        $this->assertFalse($result['is_valid'], 'Invalid 2-night booking should fail');
        $this->assertContains('minimum stay', strtolower(implode(' ', $result['errors'])), 'Should mention minimum stay');

        // Test valid longer booking (5 nights)
        $long_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-01',
            'checkout' => '2025-02-06',
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($long_booking);
        $this->assertTrue($result['is_valid'], 'Valid 5-night booking should pass');

        // Clean up
        wp_delete_post($rule_id, true);
    }

    /**
     * Test day-of-week restrictions
     */
    public function testDayOfWeekRestrictions() {
        // Create rule: check-in only on Saturday (day 6)
        $rule_id = $this->createCheckinDowRule([6]); // Saturday

        // Test valid booking (check-in on Saturday)
        $valid_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-01', // Saturday
            'checkout' => '2025-02-05',
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($valid_booking);
        $this->assertTrue($result['is_valid'], 'Saturday check-in should be valid');

        // Test invalid booking (check-in on Monday)
        $invalid_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-03', // Monday
            'checkout' => '2025-02-07',
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($invalid_booking);
        $this->assertFalse($result['is_valid'], 'Monday check-in should be invalid');
        $this->assertContains('check-in day', strtolower(implode(' ', $result['errors'])), 'Should mention check-in day restriction');

        // Clean up
        wp_delete_post($rule_id, true);

        // Test checkout restrictions
        $checkout_rule_id = $this->createCheckoutDowRule([0]); // Sunday

        // Test valid booking (check-out on Sunday)
        $valid_checkout = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-04', // Tuesday
            'checkout' => '2025-02-09', // Sunday
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($valid_checkout);
        $this->assertTrue($result['is_valid'], 'Sunday check-out should be valid');

        // Test invalid booking (check-out on Wednesday)
        $invalid_checkout = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-04', // Tuesday
            'checkout' => '2025-02-07', // Friday
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($invalid_checkout);
        $this->assertFalse($result['is_valid'], 'Friday check-out should be invalid');

        wp_delete_post($checkout_rule_id, true);
    }

    /**
     * Test buffer day rules
     */
    public function testBufferDayRules() {
        // Create existing reservation
        $existing_reservation_id = wp_insert_post([
            'post_type' => 'reservation',
            'post_title' => 'Existing Reservation',
            'post_status' => 'confirmed'
        ]);

        update_post_meta($existing_reservation_id, 'property_id', $this->property_id);
        update_post_meta($existing_reservation_id, 'checkin_date', '2025-02-10');
        update_post_meta($existing_reservation_id, 'checkout_date', '2025-02-15');

        // Create buffer rule: 1 day before and after
        $buffer_rule_id = $this->createBufferRule(1, 1);

        // Test booking too close before (should fail)
        $too_close_before = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-08',
            'checkout' => '2025-02-09', // 1 day before existing check-in
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($too_close_before);
        $this->assertFalse($result['is_valid'], 'Booking too close before should fail');
        $this->assertContains('buffer', strtolower(implode(' ', $result['errors'])), 'Should mention buffer requirement');

        // Test booking with proper buffer before (should pass)
        $proper_buffer_before = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-07',
            'checkout' => '2025-02-08', // 2 days before existing check-in
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($proper_buffer_before);
        $this->assertTrue($result['is_valid'], 'Booking with proper buffer before should pass');

        // Test booking too close after (should fail)
        $too_close_after = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-16', // 1 day after existing check-out
            'checkout' => '2025-02-18',
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($too_close_after);
        $this->assertFalse($result['is_valid'], 'Booking too close after should fail');

        // Test booking with proper buffer after (should pass)
        $proper_buffer_after = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-17', // 2 days after existing check-out
            'checkout' => '2025-02-20',
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($proper_buffer_after);
        $this->assertTrue($result['is_valid'], 'Booking with proper buffer after should pass');

        // Clean up
        wp_delete_post($existing_reservation_id, true);
        wp_delete_post($buffer_rule_id, true);
    }

    /**
     * Test multiple rules combination
     */
    public function testMultipleRulesCombination() {
        // Create multiple rules
        $min_stay_rule = $this->createMinStayRule(3);
        $checkin_dow_rule = $this->createCheckinDowRule([5, 6]); // Friday and Saturday
        $buffer_rule = $this->createBufferRule(1, 1);

        // Create existing reservation for buffer test
        $existing_reservation_id = wp_insert_post([
            'post_type' => 'reservation',
            'post_title' => 'Buffer Test Reservation',
            'post_status' => 'confirmed'
        ]);

        update_post_meta($existing_reservation_id, 'property_id', $this->property_id);
        update_post_meta($existing_reservation_id, 'checkin_date', '2025-02-20');
        update_post_meta($existing_reservation_id, 'checkout_date', '2025-02-25');

        // Test booking that violates minimum stay
        $min_stay_violation = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-07', // Friday (valid DOW)
            'checkout' => '2025-02-09', // Only 2 nights (violates min stay)
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($min_stay_violation);
        $this->assertFalse($result['is_valid'], 'Should fail on minimum stay violation');

        // Test booking that violates DOW restriction
        $dow_violation = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-10', // Monday (invalid DOW)
            'checkout' => '2025-02-13', // 3 nights (valid min stay)
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($dow_violation);
        $this->assertFalse($result['is_valid'], 'Should fail on DOW violation');

        // Test booking that violates buffer
        $buffer_violation = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-14', // Friday (valid DOW)
            'checkout' => '2025-02-17', // 3 nights (valid min stay), but too close to existing reservation
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($buffer_violation);
        $this->assertFalse($result['is_valid'], 'Should fail on buffer violation');

        // Test booking that passes all rules
        $valid_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-07', // Friday (valid DOW)
            'checkout' => '2025-02-10', // 3 nights (valid min stay), proper buffer
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($valid_booking);
        $this->assertTrue($result['is_valid'], 'Should pass all rules');
        $this->assertEmpty($result['errors'], 'Should have no errors');

        // Clean up
        wp_delete_post($min_stay_rule, true);
        wp_delete_post($checkin_dow_rule, true);
        wp_delete_post($buffer_rule, true);
        wp_delete_post($existing_reservation_id, true);
    }

    /**
     * Test seasonal rule variations
     */
    public function testSeasonalRules() {
        // Create seasonal rule: different min stay for summer
        $summer_rule_id = $this->createSeasonalMinStayRule(7, '2025-06-01', '2025-08-31');
        $regular_rule_id = $this->createMinStayRule(3);

        // Test summer booking (should require 7 nights)
        $summer_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-07-01',
            'checkout' => '2025-07-06', // Only 5 nights
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($summer_booking);
        $this->assertFalse($result['is_valid'], 'Summer booking with 5 nights should fail (needs 7)');

        // Test valid summer booking
        $valid_summer_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-07-01',
            'checkout' => '2025-07-08', // 7 nights
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($valid_summer_booking);
        $this->assertTrue($result['is_valid'], 'Summer booking with 7 nights should pass');

        // Test off-season booking (should only need 3 nights)
        $off_season_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-03-01',
            'checkout' => '2025-03-04', // 3 nights
            'guests' => 2
        ];

        $result = $this->rule_engine->validate_booking($off_season_booking);
        $this->assertTrue($result['is_valid'], 'Off-season booking with 3 nights should pass');

        // Clean up
        wp_delete_post($summer_rule_id, true);
        wp_delete_post($regular_rule_id, true);
    }

    /**
     * Helper: Create minimum stay rule
     */
    private function createMinStayRule(int $min_nights): int {
        $rule_id = wp_insert_post([
            'post_type' => 'booking_rule',
            'post_title' => "Min Stay Rule: $min_nights nights",
            'post_status' => 'active'
        ]);

        update_post_meta($rule_id, 'property_id', $this->property_id);
        update_post_meta($rule_id, 'rule_type', 'min_stay');
        update_post_meta($rule_id, 'min_nights', $min_nights);
        update_post_meta($rule_id, 'priority', 10);

        return $rule_id;
    }

    /**
     * Helper: Create check-in day-of-week rule
     */
    private function createCheckinDowRule(array $allowed_days): int {
        $rule_id = wp_insert_post([
            'post_type' => 'booking_rule',
            'post_title' => 'Check-in DOW Rule',
            'post_status' => 'active'
        ]);

        update_post_meta($rule_id, 'property_id', $this->property_id);
        update_post_meta($rule_id, 'rule_type', 'checkin_dow');
        update_post_meta($rule_id, 'allowed_checkin_days', $allowed_days);
        update_post_meta($rule_id, 'priority', 20);

        return $rule_id;
    }

    /**
     * Helper: Create check-out day-of-week rule
     */
    private function createCheckoutDowRule(array $allowed_days): int {
        $rule_id = wp_insert_post([
            'post_type' => 'booking_rule',
            'post_title' => 'Check-out DOW Rule',
            'post_status' => 'active'
        ]);

        update_post_meta($rule_id, 'property_id', $this->property_id);
        update_post_meta($rule_id, 'rule_type', 'checkout_dow');
        update_post_meta($rule_id, 'allowed_checkout_days', $allowed_days);
        update_post_meta($rule_id, 'priority', 20);

        return $rule_id;
    }

    /**
     * Helper: Create buffer day rule
     */
    private function createBufferRule(int $before_days, int $after_days): int {
        $rule_id = wp_insert_post([
            'post_type' => 'booking_rule',
            'post_title' => "Buffer Rule: $before_days before, $after_days after",
            'post_status' => 'active'
        ]);

        update_post_meta($rule_id, 'property_id', $this->property_id);
        update_post_meta($rule_id, 'rule_type', 'buffer_days');
        update_post_meta($rule_id, 'buffer_before', $before_days);
        update_post_meta($rule_id, 'buffer_after', $after_days);
        update_post_meta($rule_id, 'priority', 30);

        return $rule_id;
    }

    /**
     * Helper: Create seasonal minimum stay rule
     */
    private function createSeasonalMinStayRule(int $min_nights, string $start_date, string $end_date): int {
        $rule_id = wp_insert_post([
            'post_type' => 'booking_rule',
            'post_title' => "Seasonal Min Stay Rule: $min_nights nights",
            'post_status' => 'active'
        ]);

        update_post_meta($rule_id, 'property_id', $this->property_id);
        update_post_meta($rule_id, 'rule_type', 'seasonal_min_stay');
        update_post_meta($rule_id, 'min_nights', $min_nights);
        update_post_meta($rule_id, 'season_start', $start_date);
        update_post_meta($rule_id, 'season_end', $end_date);
        update_post_meta($rule_id, 'priority', 5);

        return $rule_id;
    }
}