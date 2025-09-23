<?php
/**
 * Simple Test Runner for Pricing Engine
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Pricing;

if (!defined('ABSPATH')) {
    exit;
}

class TestRunner
{
    private static $test_results = [];

    public static function run_basic_tests()
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return ['error' => 'Tests can only be run in debug mode'];
        }

        error_log('[MinpakuSuite] Starting pricing engine tests...');

        // Test 1: Basic rate calculation
        self::test_basic_rate_calculation();

        // Test 2: Weekly discount
        self::test_weekly_discount();

        // Test 3: Seasonal override
        self::test_seasonal_override();

        // Test 4: Extra guest fees
        self::test_extra_guest_fees();

        // Test 5: Tax calculation
        self::test_tax_calculation();

        $passed = count(array_filter(self::$test_results, function($result) {
            return $result['status'] === 'passed';
        }));

        $total = count(self::$test_results);

        error_log("[MinpakuSuite] Tests completed: {$passed}/{$total} passed");

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $total - $passed,
            'results' => self::$test_results
        ];
    }

    private static function test_basic_rate_calculation()
    {
        try {
            // Create a test property (assuming property ID 1 exists)
            $property_id = 1;

            // Mock basic rate data
            update_post_meta($property_id, 'base_rate', 10000);

            $context = new RateContext(
                $property_id,
                '2024-12-01',
                '2024-12-03',
                2, 0, 0, 'JPY'
            );

            $engine = new PricingEngine($context);
            $quote = $engine->calculateQuote();

            $expected_accommodation = 20000; // 2 nights × 10000
            $actual_accommodation = 0;

            foreach ($quote['line_items'] as $item) {
                if ($item['code'] === 'base') {
                    $actual_accommodation = $item['subtotal'];
                    break;
                }
            }

            self::assert_equals(
                'Basic Rate Calculation',
                $expected_accommodation,
                $actual_accommodation
            );

        } catch (\Exception $e) {
            self::$test_results[] = [
                'test' => 'Basic Rate Calculation',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private static function test_weekly_discount()
    {
        try {
            $property_id = 1;

            // Mock weekly discount data
            update_post_meta($property_id, 'base_rate', 10000);
            update_post_meta($property_id, 'weekly_discount_percent', 10.0);
            update_post_meta($property_id, 'weekly_discount_threshold', 7);

            $context = new RateContext(
                $property_id,
                '2024-12-01',
                '2024-12-08',
                2, 0, 0, 'JPY'
            );

            $engine = new PricingEngine($context);
            $quote = $engine->calculateQuote();

            $has_weekly_discount = false;
            foreach ($quote['line_items'] as $item) {
                if ($item['code'] === 'weekly') {
                    $has_weekly_discount = true;
                    $expected_discount = -7000; // 10% of 70000
                    self::assert_equals(
                        'Weekly Discount Amount',
                        $expected_discount,
                        $item['subtotal']
                    );
                    break;
                }
            }

            self::assert_true('Weekly Discount Applied', $has_weekly_discount);

        } catch (\Exception $e) {
            self::$test_results[] = [
                'test' => 'Weekly Discount',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private static function test_seasonal_override()
    {
        try {
            $property_id = 1;

            // Mock seasonal rate data
            update_post_meta($property_id, 'base_rate', 10000);
            update_post_meta($property_id, 'seasonal_rates', [
                [
                    'name' => 'Test Season',
                    'start_date' => '2024-12-01',
                    'end_date' => '2024-12-31',
                    'rate' => 15000,
                    'priority' => 1
                ]
            ]);

            $context = new RateContext(
                $property_id,
                '2024-12-15',
                '2024-12-17',
                2, 0, 0, 'JPY'
            );

            $engine = new PricingEngine($context);
            $quote = $engine->calculateQuote();

            $expected_accommodation = 30000; // 2 nights × 15000 (seasonal rate)
            $actual_accommodation = 0;

            foreach ($quote['line_items'] as $item) {
                if ($item['code'] === 'base') {
                    $actual_accommodation = $item['subtotal'];
                    break;
                }
            }

            self::assert_equals(
                'Seasonal Rate Override',
                $expected_accommodation,
                $actual_accommodation
            );

        } catch (\Exception $e) {
            self::$test_results[] = [
                'test' => 'Seasonal Rate Override',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private static function test_extra_guest_fees()
    {
        try {
            $property_id = 1;

            // Mock extra guest fee data
            update_post_meta($property_id, 'base_rate', 10000);
            update_post_meta($property_id, 'extra_guest_threshold', 2);
            update_post_meta($property_id, 'extra_guest_fee', 2000);

            $context = new RateContext(
                $property_id,
                '2024-12-01',
                '2024-12-03',
                4, 0, 0, 'JPY' // 4 adults = 2 extra guests
            );

            $engine = new PricingEngine($context);
            $quote = $engine->calculateQuote();

            $expected_extra_fee = 8000; // 2 extra guests × 2 nights × 2000
            $actual_extra_fee = 0;

            foreach ($quote['line_items'] as $item) {
                if ($item['code'] === 'extra_guest') {
                    $actual_extra_fee = $item['subtotal'];
                    break;
                }
            }

            self::assert_equals(
                'Extra Guest Fee',
                $expected_extra_fee,
                $actual_extra_fee
            );

        } catch (\Exception $e) {
            self::$test_results[] = [
                'test' => 'Extra Guest Fee',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private static function test_tax_calculation()
    {
        try {
            $property_id = 1;

            // Mock tax data
            update_post_meta($property_id, 'base_rate', 10000);
            update_post_meta($property_id, 'taxes', [
                [
                    'name' => 'Test Tax (10%)',
                    'rate' => 10.0,
                    'inclusive' => false,
                    'taxable_items' => ['accommodation']
                ]
            ]);

            $context = new RateContext(
                $property_id,
                '2024-12-01',
                '2024-12-02',
                2, 0, 0, 'JPY'
            );

            $engine = new PricingEngine($context);
            $quote = $engine->calculateQuote();

            $expected_tax = 1000; // 10% of 10000
            $actual_tax = 0;

            foreach ($quote['taxes'] as $tax) {
                $actual_tax += $tax['amount'];
            }

            self::assert_equals(
                'Tax Calculation',
                $expected_tax,
                $actual_tax
            );

        } catch (\Exception $e) {
            self::$test_results[] = [
                'test' => 'Tax Calculation',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private static function assert_equals($test_name, $expected, $actual)
    {
        if ($expected === $actual) {
            self::$test_results[] = [
                'test' => $test_name,
                'status' => 'passed',
                'expected' => $expected,
                'actual' => $actual
            ];
            error_log("[MinpakuSuite] ✓ {$test_name} passed");
        } else {
            self::$test_results[] = [
                'test' => $test_name,
                'status' => 'failed',
                'expected' => $expected,
                'actual' => $actual,
                'error' => "Expected {$expected}, got {$actual}"
            ];
            error_log("[MinpakuSuite] ✗ {$test_name} failed: Expected {$expected}, got {$actual}");
        }
    }

    private static function assert_true($test_name, $condition)
    {
        self::assert_equals($test_name, true, $condition);
    }
}