<?php
/**
 * Rate Engine Service
 *
 * Provides unified rate calculation and pricing logic
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Services;

if (!defined('ABSPATH')) {
    exit;
}

class RateEngine {

    /**
     * Get base rate for a property
     */
    public static function get_base_rate(int $property_id): float {
        // Try ACF field first
        $rate = function_exists('get_field') ? get_field('accommodation_rate', $property_id) : null;

        // Fallback to meta
        if (empty($rate)) {
            $rate = get_post_meta($property_id, 'accommodation_rate', true);
        }

        // Legacy fallback
        if (empty($rate)) {
            $rate = get_post_meta($property_id, 'test_base_rate', true) ?:
                    get_post_meta($property_id, 'base_price_test', true);
        }

        return floatval($rate ?: 15000);
    }

    /**
     * Get cleaning fee for a property
     */
    public static function get_cleaning_fee(int $property_id): float {
        // Try ACF field first
        $fee = function_exists('get_field') ? get_field('cleaning_fee', $property_id) : null;

        // Fallback to meta
        if (empty($fee)) {
            $fee = get_post_meta($property_id, 'cleaning_fee', true);
        }

        // Legacy fallback
        if (empty($fee)) {
            $fee = get_post_meta($property_id, 'test_cleaning_fee', true);
        }

        return floatval($fee ?: 0);
    }

    /**
     * Get weekend/holiday addon percentage
     */
    public static function get_weekend_holiday_addon(int $property_id): float {
        $addon = function_exists('get_field') ? get_field('weekend_holiday_addon', $property_id) : null;

        if (empty($addon)) {
            $addon = get_post_meta($property_id, 'weekend_holiday_addon', true);
        }

        return floatval($addon ?: 0);
    }

    /**
     * Get seasonal rules for a property
     * Returns array of rules with start_date, end_date, addon_percentage, priority
     */
    public static function get_seasonal_rules(int $property_id): array {
        $rules = function_exists('get_field') ? get_field('seasonal_rules', $property_id) : null;

        if (empty($rules)) {
            $rules = get_post_meta($property_id, 'seasonal_rules', true);
        }

        if (!is_array($rules)) {
            return [];
        }

        // Sort by priority (higher priority first)
        usort($rules, function($a, $b) {
            return ($b['priority'] ?? 0) - ($a['priority'] ?? 0);
        });

        return $rules;
    }

    /**
     * Get blackout date ranges for a property
     * Returns array of ranges with start_date, end_date
     */
    public static function get_blackout_ranges(int $property_id): array {
        $ranges = function_exists('get_field') ? get_field('blackout_ranges', $property_id) : null;

        if (empty($ranges)) {
            $ranges = get_post_meta($property_id, 'blackout_ranges', true);
        }

        return is_array($ranges) ? $ranges : [];
    }

    /**
     * Check if a date is weekend or holiday
     */
    public static function is_weekend_or_holiday(string $date): bool {
        $timestamp = strtotime($date);
        $day_of_week = date('w', $timestamp);

        // Weekend check (Saturday = 6, Sunday = 0)
        if ($day_of_week == 0 || $day_of_week == 6) {
            return true;
        }

        // Holiday check - using basic Japanese holidays
        $holidays = self::get_japanese_holidays(date('Y', $timestamp));
        return in_array($date, $holidays);
    }

    /**
     * Check if a date is in blackout period
     */
    public static function is_blackout_date(string $date, int $property_id): bool {
        $blackout_ranges = self::get_blackout_ranges($property_id);

        foreach ($blackout_ranges as $range) {
            if (!isset($range['start_date']) || !isset($range['end_date'])) {
                continue;
            }

            if ($date >= $range['start_date'] && $date <= $range['end_date']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get seasonal addon for a specific date
     */
    public static function get_seasonal_addon(string $date, int $property_id): array {
        $seasonal_rules = self::get_seasonal_rules($property_id);

        foreach ($seasonal_rules as $rule) {
            if (!isset($rule['start_date']) || !isset($rule['end_date'])) {
                continue;
            }

            if ($date >= $rule['start_date'] && $date <= $rule['end_date']) {
                return [
                    'percentage' => floatval($rule['addon_percentage'] ?? 0),
                    'note' => $rule['note'] ?? __('季節料金', 'minpaku-suite')
                ];
            }
        }

        return ['percentage' => 0, 'note' => ''];
    }

    /**
     * Calculate rate for a specific date
     * Applies priority: Seasonal > Weekend/Holiday
     */
    public static function calculate_daily_rate(string $date, int $property_id): array {
        $base_rate = self::get_base_rate($property_id);
        $surcharge = 0;
        $note = '';

        // Check seasonal rules first (higher priority)
        $seasonal_addon = self::get_seasonal_addon($date, $property_id);
        if ($seasonal_addon['percentage'] > 0) {
            $surcharge = $base_rate * ($seasonal_addon['percentage'] / 100);
            $note = $seasonal_addon['note'];
        } else {
            // Check weekend/holiday if no seasonal rule
            if (self::is_weekend_or_holiday($date)) {
                $weekend_addon = self::get_weekend_holiday_addon($property_id);
                if ($weekend_addon > 0) {
                    $surcharge = $base_rate * ($weekend_addon / 100);
                    $note = __('週末・祝日料金', 'minpaku-suite');
                }
            }
        }

        return [
            'base' => intval($base_rate),
            'surcharge' => intval($surcharge),
            'total' => intval($base_rate + $surcharge),
            'note' => $note
        ];
    }

    /**
     * Calculate quote for stay period
     */
    public static function calculate_quote(int $property_id, string $checkin, string $checkout, int $guests = 2): array {
        // Validate dates
        if ($checkin >= $checkout) {
            throw new \InvalidArgumentException(__('チェックアウト日はチェックイン日より後である必要があります。', 'minpaku-suite'));
        }

        $checkin_date = new \DateTime($checkin);
        $checkout_date = new \DateTime($checkout);
        $nights = $checkin_date->diff($checkout_date)->days;

        // Maximum stay validation
        if ($nights > 30) {
            throw new \InvalidArgumentException(__('最大宿泊期間は30日です。', 'minpaku-suite'));
        }

        // Check capacity
        $capacity = intval(get_post_meta($property_id, 'capacity', true) ?: 10);
        if ($guests > $capacity) {
            throw new \DomainException(sprintf(__('定員を超えています。最大定員は %d 名です。', 'minpaku-suite'), $capacity));
        }

        $breakdown = [];
        $base_total = 0;
        $surcharge_total = 0;
        $current_date = clone $checkin_date;

        // Calculate each night
        while ($current_date < $checkout_date) {
            $date_string = $current_date->format('Y-m-d');

            // Check blackout
            if (self::is_blackout_date($date_string, $property_id)) {
                throw new \DomainException(sprintf(__('選択された日程は予約不可です: %s', 'minpaku-suite'), $date_string));
            }

            // Check availability (basic check for existing bookings)
            if (self::is_date_booked($date_string, $property_id)) {
                throw new \DomainException(sprintf(__('選択された日程は満室です: %s', 'minpaku-suite'), $date_string));
            }

            $daily_rate = self::calculate_daily_rate($date_string, $property_id);

            $breakdown[] = [
                'date' => $date_string,
                'base' => $daily_rate['base'],
                'surcharge' => $daily_rate['surcharge'],
                'note' => $daily_rate['note']
            ];

            $base_total += $daily_rate['base'];
            $surcharge_total += $daily_rate['surcharge'];

            $current_date->add(new \DateInterval('P1D'));
        }

        $cleaning_fee = intval(self::get_cleaning_fee($property_id));
        $grand_total = $base_total + $surcharge_total + $cleaning_fee;

        return [
            'currency' => 'JPY',
            'nights' => $nights,
            'base_total' => $base_total,
            'surcharge_total' => $surcharge_total,
            'cleaning_fee' => $cleaning_fee,
            'grand_total' => $grand_total,
            'breakdown' => $breakdown
        ];
    }

    /**
     * Check if a date is already booked
     */
    private static function is_date_booked(string $date, int $property_id): bool {
        // Check existing bookings
        $bookings = get_posts([
            'post_type' => 'mcs_booking',
            'post_status' => ['publish', 'confirmed'],
            'meta_query' => [
                [
                    'key' => 'property_id',
                    'value' => $property_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1
        ]);

        foreach ($bookings as $booking) {
            $check_in = get_post_meta($booking->ID, 'check_in_date', true);
            $check_out = get_post_meta($booking->ID, 'check_out_date', true);

            if ($check_in && $check_out && $date >= $check_in && $date < $check_out) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Japanese holidays for a year
     */
    private static function get_japanese_holidays(int $year): array {
        // Basic Japanese holidays - can be extended
        return [
            "$year-01-01", // New Year's Day
            "$year-01-08", // Coming of Age Day (approximate)
            "$year-02-11", // National Foundation Day
            "$year-02-23", // Emperor's Birthday
            "$year-03-20", // Vernal Equinox (approximate)
            "$year-04-29", // Showa Day
            "$year-05-03", // Constitution Memorial Day
            "$year-05-04", // Greenery Day
            "$year-05-05", // Children's Day
            "$year-07-15", // Marine Day (approximate)
            "$year-08-11", // Mountain Day
            "$year-09-16", // Respect for the Aged Day (approximate)
            "$year-09-23", // Autumnal Equinox (approximate)
            "$year-10-14", // Sports Day (approximate)
            "$year-11-03", // Culture Day
            "$year-11-23", // Labor Thanksgiving Day
        ];
    }
}