<?php
/**
 * Integration Tests for Rate Resolver System
 * Tests Season + DOW + LOS pricing composition
 */

use PHPUnit\Framework\TestCase;

class RateResolverTest extends TestCase {

    private $property_id;
    private $rate_resolver;

    protected function setUp(): void {
        parent::setUp();

        // Create test property
        $this->property_id = wp_insert_post([
            'post_type' => 'property',
            'post_title' => 'Test Property for Rate Resolution',
            'post_status' => 'publish'
        ]);

        // Set base rate for property
        update_post_meta($this->property_id, 'base_rate', 100.00);

        // Initialize rate resolver
        $this->rate_resolver = new RateResolver();

        // Clean up existing rates
        $this->cleanupTestRates();
    }

    protected function tearDown(): void {
        // Clean up
        wp_delete_post($this->property_id, true);
        $this->cleanupTestRates();
        parent::tearDown();
    }

    private function cleanupTestRates() {
        global $wpdb;
        $wpdb->delete($wpdb->posts, ['post_type' => 'rate_rule']);
        $wpdb->delete($wpdb->posts, ['post_type' => 'seasonal_rate']);
    }

    /**
     * Test basic rate resolution with base rate only
     */
    public function testBasicRateResolution() {
        $booking_data = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-01',
            'checkout' => '2025-02-04',
            'guests' => 2
        ];

        $result = $this->rate_resolver->resolveRate($booking_data);

        $this->assertIsArray($result, 'Should return array');
        $this->assertArrayHasKey('base_rate', $result, 'Should have base_rate');
        $this->assertArrayHasKey('total_rate', $result, 'Should have total_rate');
        $this->assertArrayHasKey('breakdown', $result, 'Should have breakdown');

        $this->assertEquals(100.00, $result['base_rate'], 'Base rate should match property setting');
        $this->assertEquals(300.00, $result['total_rate'], 'Total should be 3 nights × $100');

        // Check breakdown
        $this->assertIsArray($result['breakdown'], 'Breakdown should be array');
        $this->assertArrayHasKey('accommodation', $result['breakdown'], 'Should have accommodation breakdown');
    }

    /**
     * Test seasonal rate adjustments
     */
    public function testSeasonalRateAdjustments() {
        // Create high season rate: +50% from June-August
        $high_season_id = $this->createSeasonalRate(
            'High Season',
            '2025-06-01',
            '2025-08-31',
            'percentage',
            50
        );

        // Create low season rate: -20% from November-February
        $low_season_id = $this->createSeasonalRate(
            'Low Season',
            '2025-11-01',
            '2025-02-28',
            'percentage',
            -20
        );

        // Test high season booking
        $high_season_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-07-01',
            'checkout' => '2025-07-04',
            'guests' => 2
        ];

        $result = $this->rate_resolver->resolveRate($high_season_booking);
        $this->assertEquals(150.00, $result['base_rate'], 'High season should be $150 (100 + 50%)');
        $this->assertEquals(450.00, $result['total_rate'], 'Total should be 3 nights × $150');

        // Test low season booking
        $low_season_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-01-01',
            'checkout' => '2025-01-04',
            'guests' => 2
        ];

        $result = $this->rate_resolver->resolveRate($low_season_booking);
        $this->assertEquals(80.00, $result['base_rate'], 'Low season should be $80 (100 - 20%)');
        $this->assertEquals(240.00, $result['total_rate'], 'Total should be 3 nights × $80');

        // Test regular season booking
        $regular_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-04-01',
            'checkout' => '2025-04-04',
            'guests' => 2
        ];

        $result = $this->rate_resolver->resolveRate($regular_booking);
        $this->assertEquals(100.00, $result['base_rate'], 'Regular season should be $100');

        // Clean up
        wp_delete_post($high_season_id, true);
        wp_delete_post($low_season_id, true);
    }

    /**
     * Test day-of-week rate adjustments
     */
    public function testDayOfWeekRateAdjustments() {
        // Create weekend rate: +30% for Friday and Saturday
        $weekend_rate_id = $this->createDowRate([5, 6], 'percentage', 30);

        // Create weekday discount: -10% for Tuesday and Wednesday
        $weekday_rate_id = $this->createDowRate([2, 3], 'percentage', -10);

        // Test weekend booking (Friday-Sunday, includes Friday and Saturday)
        $weekend_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-01-31', // Friday
            'checkout' => '2025-02-03', // Monday
            'guests' => 2
        ];

        $result = $this->rate_resolver->resolveRate($weekend_booking);

        // Should have mixed rates: Fri($130) + Sat($130) + Sun($100) = $360
        $this->assertEquals(360.00, $result['total_rate'], 'Weekend booking should have mixed rates');

        // Check breakdown includes weekend adjustment
        $accommodation = $result['breakdown']['accommodation'];
        $weekend_nights = array_filter($accommodation, function($item) {
            return strpos($item['label'], 'weekend') !== false || strpos($item['label'], 'Weekend') !== false;
        });
        $this->assertNotEmpty($weekend_nights, 'Should include weekend rate adjustments');

        // Test weekday booking (Tuesday-Thursday)
        $weekday_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-04', // Tuesday
            'checkout' => '2025-02-06', // Thursday
            'guests' => 2
        ];

        $result = $this->rate_resolver->resolveRate($weekday_booking);
        // Should be: Tue($90) + Wed($90) = $180
        $this->assertEquals(180.00, $result['total_rate'], 'Weekday booking should have discounted rates');

        // Clean up
        wp_delete_post($weekend_rate_id, true);
        wp_delete_post($weekday_rate_id, true);
    }

    /**
     * Test length-of-stay discounts
     */
    public function testLengthOfStayDiscounts() {
        // Create weekly discount: -15% for 7+ nights
        $weekly_discount_id = $this->createLosDiscount(7, 'percentage', -15);

        // Create monthly discount: -25% for 28+ nights
        $monthly_discount_id = $this->createLosDiscount(28, 'percentage', -25);

        // Test short stay (no discount)
        $short_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-01',
            'checkout' => '2025-02-04',
            'guests' => 2
        ];

        $result = $this->rate_resolver->resolveRate($short_booking);
        $this->assertEquals(300.00, $result['total_rate'], 'Short stay should have no LOS discount');

        // Test weekly stay
        $weekly_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-01',
            'checkout' => '2025-02-08', // 7 nights
            'guests' => 2
        ];

        $result = $this->rate_resolver->resolveRate($weekly_booking);
        // 7 nights × $100 = $700, minus 15% = $595
        $this->assertEquals(595.00, $result['total_rate'], 'Weekly stay should have 15% discount');

        // Check for LOS discount in breakdown
        $this->assertArrayHasKey('adjustments', $result['breakdown'], 'Should have adjustments');
        $los_adjustments = array_filter($result['breakdown']['adjustments'], function($item) {
            return strpos(strtolower($item['label']), 'length of stay') !== false ||
                   strpos(strtolower($item['label']), 'weekly') !== false;
        });
        $this->assertNotEmpty($los_adjustments, 'Should include LOS discount in breakdown');

        // Test monthly stay
        $monthly_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-01',
            'checkout' => '2025-03-01', // 28 nights
            'guests' => 2
        ];

        $result = $this->rate_resolver->resolveRate($monthly_booking);
        // 28 nights × $100 = $2800, minus 25% = $2100
        $this->assertEquals(2100.00, $result['total_rate'], 'Monthly stay should have 25% discount');

        // Clean up
        wp_delete_post($weekly_discount_id, true);
        wp_delete_post($monthly_discount_id, true);
    }

    /**
     * Test guest count surcharges
     */
    public function testGuestCountSurcharges() {
        // Set property max guests
        update_post_meta($this->property_id, 'max_guests', 4);
        update_post_meta($this->property_id, 'base_guests', 2);

        // Create additional guest rate: $25 per night per extra guest
        $extra_guest_id = $this->createExtraGuestRate(25.00);

        // Test base guest count (no surcharge)
        $base_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-01',
            'checkout' => '2025-02-04',
            'guests' => 2
        ];

        $result = $this->rate_resolver->resolveRate($base_booking);
        $this->assertEquals(300.00, $result['total_rate'], 'Base guest count should have no surcharge');

        // Test extra guests
        $extra_guest_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-01',
            'checkout' => '2025-02-04',
            'guests' => 4 // 2 extra guests
        ];

        $result = $this->rate_resolver->resolveRate($extra_guest_booking);
        // Base: 3 nights × $100 = $300
        // Extra: 3 nights × 2 guests × $25 = $150
        // Total: $450
        $this->assertEquals(450.00, $result['total_rate'], 'Extra guests should add surcharge');

        // Check for guest surcharge in breakdown
        $this->assertArrayHasKey('fees', $result['breakdown'], 'Should have fees breakdown');
        $guest_fees = array_filter($result['breakdown']['fees'], function($item) {
            return strpos(strtolower($item['label']), 'guest') !== false ||
                   strpos(strtolower($item['label']), 'additional') !== false;
        });
        $this->assertNotEmpty($guest_fees, 'Should include guest fees in breakdown');

        // Clean up
        wp_delete_post($extra_guest_id, true);
    }

    /**
     * Test complex rate composition (Season + DOW + LOS + Guests)
     */
    public function testComplexRateComposition() {
        // Create multiple rate rules
        $high_season_id = $this->createSeasonalRate('High Season', '2025-07-01', '2025-07-31', 'percentage', 50);
        $weekend_rate_id = $this->createDowRate([5, 6], 'percentage', 20);
        $weekly_discount_id = $this->createLosDiscount(7, 'percentage', -10);
        $extra_guest_id = $this->createExtraGuestRate(20.00);

        // Set property settings
        update_post_meta($this->property_id, 'max_guests', 6);
        update_post_meta($this->property_id, 'base_guests', 2);

        // Test complex booking: High season, includes weekend, weekly stay, extra guests
        $complex_booking = [
            'property_id' => $this->property_id,
            'checkin' => '2025-07-11', // Friday (start of high season weekend)
            'checkout' => '2025-07-18', // Friday (7 nights, qualifies for weekly discount)
            'guests' => 4 // 2 extra guests
        ];

        $result = $this->rate_resolver->resolveRate($complex_booking);

        $this->assertIsArray($result, 'Should return result array');
        $this->assertGreaterThan(0, $result['total_rate'], 'Should have positive total rate');

        // Verify all components are in breakdown
        $this->assertArrayHasKey('accommodation', $result['breakdown'], 'Should have accommodation');
        $this->assertArrayHasKey('fees', $result['breakdown'], 'Should have fees');
        $this->assertArrayHasKey('adjustments', $result['breakdown'], 'Should have adjustments');

        // Check that seasonal rates are applied
        $accommodation = $result['breakdown']['accommodation'];
        $seasonal_nights = array_filter($accommodation, function($item) {
            return $item['amount'] > 100; // Should be higher than base rate
        });
        $this->assertNotEmpty($seasonal_nights, 'Should apply seasonal rates');

        // Check for weekend premiums
        $weekend_nights = array_filter($accommodation, function($item) {
            return strpos(strtolower($item['label']), 'weekend') !== false;
        });
        $this->assertNotEmpty($weekend_nights, 'Should apply weekend rates');

        // Check for LOS discount
        $los_discounts = array_filter($result['breakdown']['adjustments'], function($item) {
            return $item['amount'] < 0; // Discounts should be negative
        });
        $this->assertNotEmpty($los_discounts, 'Should apply LOS discount');

        // Check for guest fees
        $guest_fees = array_filter($result['breakdown']['fees'], function($item) {
            return strpos(strtolower($item['label']), 'guest') !== false;
        });
        $this->assertNotEmpty($guest_fees, 'Should apply guest fees');

        // Verify total calculation integrity
        $accommodation_total = array_sum(array_column($result['breakdown']['accommodation'], 'amount'));
        $fees_total = array_sum(array_column($result['breakdown']['fees'] ?? [], 'amount'));
        $adjustments_total = array_sum(array_column($result['breakdown']['adjustments'] ?? [], 'amount'));
        $calculated_total = $accommodation_total + $fees_total + $adjustments_total;

        $this->assertEquals($calculated_total, $result['total_rate'], 'Total should match sum of breakdown components');

        // Clean up
        wp_delete_post($high_season_id, true);
        wp_delete_post($weekend_rate_id, true);
        wp_delete_post($weekly_discount_id, true);
        wp_delete_post($extra_guest_id, true);
    }

    /**
     * Test tax calculations
     */
    public function testTaxCalculations() {
        // Set up tax rates
        update_post_meta($this->property_id, 'tax_rate', 10.5); // 10.5% tax
        update_post_meta($this->property_id, 'tax_type', 'percentage');

        $booking_data = [
            'property_id' => $this->property_id,
            'checkin' => '2025-02-01',
            'checkout' => '2025-02-04',
            'guests' => 2
        ];

        $result = $this->rate_resolver->resolveRate($booking_data);

        // Check for tax in breakdown
        $this->assertArrayHasKey('fees', $result['breakdown'], 'Should have fees breakdown');
        $tax_fees = array_filter($result['breakdown']['fees'], function($item) {
            return strpos(strtolower($item['label']), 'tax') !== false;
        });
        $this->assertNotEmpty($tax_fees, 'Should include tax in breakdown');

        // Verify tax calculation (10.5% of $300 = $31.50)
        $tax_amount = array_sum(array_column($tax_fees, 'amount'));
        $this->assertEquals(31.50, $tax_amount, 'Tax should be 10.5% of accommodation total');
    }

    // Helper methods for creating test data

    private function createSeasonalRate(string $name, string $start_date, string $end_date, string $type, float $value): int {
        $rate_id = wp_insert_post([
            'post_type' => 'seasonal_rate',
            'post_title' => $name,
            'post_status' => 'active'
        ]);

        update_post_meta($rate_id, 'property_id', $this->property_id);
        update_post_meta($rate_id, 'start_date', $start_date);
        update_post_meta($rate_id, 'end_date', $end_date);
        update_post_meta($rate_id, 'adjustment_type', $type);
        update_post_meta($rate_id, 'adjustment_value', $value);
        update_post_meta($rate_id, 'priority', 10);

        return $rate_id;
    }

    private function createDowRate(array $days, string $type, float $value): int {
        $rate_id = wp_insert_post([
            'post_type' => 'rate_rule',
            'post_title' => 'DOW Rate Rule',
            'post_status' => 'active'
        ]);

        update_post_meta($rate_id, 'property_id', $this->property_id);
        update_post_meta($rate_id, 'rule_type', 'day_of_week');
        update_post_meta($rate_id, 'applicable_days', $days);
        update_post_meta($rate_id, 'adjustment_type', $type);
        update_post_meta($rate_id, 'adjustment_value', $value);
        update_post_meta($rate_id, 'priority', 20);

        return $rate_id;
    }

    private function createLosDiscount(int $min_nights, string $type, float $value): int {
        $rate_id = wp_insert_post([
            'post_type' => 'rate_rule',
            'post_title' => "LOS Discount: $min_nights+ nights",
            'post_status' => 'active'
        ]);

        update_post_meta($rate_id, 'property_id', $this->property_id);
        update_post_meta($rate_id, 'rule_type', 'length_of_stay');
        update_post_meta($rate_id, 'min_nights', $min_nights);
        update_post_meta($rate_id, 'adjustment_type', $type);
        update_post_meta($rate_id, 'adjustment_value', $value);
        update_post_meta($rate_id, 'priority', 30);

        return $rate_id;
    }

    private function createExtraGuestRate(float $rate_per_night): int {
        $rate_id = wp_insert_post([
            'post_type' => 'rate_rule',
            'post_title' => 'Extra Guest Rate',
            'post_status' => 'active'
        ]);

        update_post_meta($rate_id, 'property_id', $this->property_id);
        update_post_meta($rate_id, 'rule_type', 'extra_guest');
        update_post_meta($rate_id, 'rate_per_guest_per_night', $rate_per_night);
        update_post_meta($rate_id, 'priority', 40);

        return $rate_id;
    }
}