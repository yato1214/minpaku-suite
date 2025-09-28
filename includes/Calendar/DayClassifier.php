<?php
/**
 * Day Classifier for Portal Side Calendar
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Calendar;

if (!defined('ABSPATH')) {
    exit;
}

class DayClassifier {

    /**
     * Simple day classification for colors (same as connector side)
     */
    public static function getSimpleDayClasses($date_string, $availability_status) {
        $date = new \DateTime($date_string);
        $day_of_week = $date->format('w'); // 0 = Sunday, 6 = Saturday

        $css_classes = ['mcs-day'];
        $background_color = '#FFFFFF'; // Default white

        // Add availability class
        $css_classes[] = "mcs-day--{$availability_status}";

        // Add day type classes for color (same logic as connector)
        if ($availability_status === 'available') {
            if ($day_of_week == 0 || self::isJapaneseHoliday($date_string)) { // Sunday or Holiday
                $css_classes[] = 'mcs-day--sun';
                if (self::isJapaneseHoliday($date_string)) {
                    $css_classes[] = 'mcs-day--holiday';
                }
                $background_color = '#FFE7EC'; // Pink
            } elseif ($day_of_week == 6) { // Saturday
                $css_classes[] = 'mcs-day--sat';
                $background_color = '#E7F2FF'; // Light blue
            } else { // Weekday
                $css_classes[] = 'mcs-day--weekday';
                $background_color = '#F0F9F0'; // Light green
            }
        } elseif ($availability_status === 'full') {
            // Booked dates keep white background (満室 badge provides visual indication)
            $css_classes[] = 'mcs-day--booked';
            $background_color = '#FFFFFF'; // White background
        }

        return [
            'css_classes' => $css_classes,
            'background_color' => $background_color
        ];
    }

    /**
     * Check if date is a Japanese holiday (simplified version)
     */
    public static function isJapaneseHoliday($date_string) {
        // Simplified holiday check - you can expand this
        $holidays = [
            '2025-01-01', // New Year
            '2025-01-13', // Coming of Age Day
            '2025-02-11', // Foundation Day
            '2025-02-23', // Emperor's Birthday
            '2025-03-20', // Spring Equinox
            '2025-04-29', // Showa Day
            '2025-05-03', // Constitution Day
            '2025-05-04', // Greenery Day
            '2025-05-05', // Children's Day
            '2025-07-21', // Marine Day
            '2025-08-11', // Mountain Day
            '2025-09-15', // Respect for the Aged Day
            '2025-09-23', // Autumn Equinox
            '2025-10-13', // Sports Day
            '2025-11-03', // Culture Day
            '2025-11-23', // Labor Thanksgiving Day
            // Add more holidays as needed
        ];

        return in_array($date_string, $holidays);
    }

    /**
     * Get availability status for a date based on pricing rules
     */
    public static function getAvailabilityStatus($date_string, $property_id) {
        // Check blackout periods
        if (self::isInBlackoutPeriod($date_string, $property_id)) {
            return 'blackout';
        }

        // Check existing bookings (simplified)
        if (self::hasBooking($date_string, $property_id)) {
            return 'full';
        }

        return 'available';
    }

    /**
     * Check if date is in blackout period
     */
    private static function isInBlackoutPeriod($date_string, $property_id) {
        $pricing = \MinpakuSuite\Admin\PropertyPricingMetabox::get_property_pricing($property_id);
        $blackout_ranges = $pricing['blackout_ranges'] ?? [];

        foreach ($blackout_ranges as $range) {
            if ($date_string >= $range['date_from'] && $date_string <= $range['date_to']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if date has booking
     */
    private static function hasBooking($date_string, $property_id) {
        global $wpdb;

        // Query for bookings that overlap with this date
        $query = $wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_mcs_property_id'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_mcs_checkin'
            INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_mcs_checkout'
            INNER JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_mcs_status'
            WHERE p.post_type = 'mcs_booking'
            AND p.post_status = 'publish'
            AND pm1.meta_value = %d
            AND pm4.meta_value IN ('CONFIRMED', 'PENDING')
            AND pm2.meta_value <= %s
            AND pm3.meta_value > %s
        ", $property_id, $date_string, $date_string);

        $count = $wpdb->get_var($query);
        return $count > 0;
    }

    /**
     * Calculate price for a specific date using property pricing
     */
    public static function calculatePriceForDate($date_string, $property_id) {
        $pricing = \MinpakuSuite\Admin\PropertyPricingMetabox::get_property_pricing($property_id);
        $base_price = floatval($pricing['base_nightly_price']);

        // Check for seasonal rules first (highest priority)
        $seasonal_price = self::applySeasonalRules($date_string, $base_price, $pricing['seasonal_rules']);

        if ($seasonal_price !== $base_price) {
            // Seasonal rule applied, don't add eve surcharges
            return $seasonal_price;
        }

        // Check for eve surcharges (second priority)
        $eve_surcharge = self::calculateEveSurcharge($date_string, $pricing);

        return $base_price + $eve_surcharge;
    }

    /**
     * Apply seasonal rules to base price
     */
    private static function applySeasonalRules($date_string, $base_price, $seasonal_rules) {
        if (empty($seasonal_rules) || !is_array($seasonal_rules)) {
            return $base_price;
        }

        foreach ($seasonal_rules as $rule) {
            if (!isset($rule['date_from']) || !isset($rule['date_to']) || !isset($rule['mode']) || !isset($rule['amount'])) {
                continue;
            }

            $date_from = $rule['date_from'];
            $date_to = $rule['date_to'];

            // Check if date falls within this rule's range
            if ($date_string >= $date_from && $date_string <= $date_to) {
                $amount = floatval($rule['amount']);

                if ($rule['mode'] === 'override') {
                    return $amount; // Replace base price
                } elseif ($rule['mode'] === 'add') {
                    return $base_price + $amount; // Add to base price
                }
            }
        }

        return $base_price; // No seasonal rule applied
    }

    /**
     * Calculate eve surcharge for a date
     */
    private static function calculateEveSurcharge($date_string, $pricing) {
        $date = new \DateTime($date_string);
        $tomorrow = clone $date;
        $tomorrow->add(new \DateInterval('P1D'));
        $tomorrow_dow = $tomorrow->format('w');
        $tomorrow_string = $tomorrow->format('Y-m-d');

        // Check if tomorrow is Saturday
        if ($tomorrow_dow == 6) {
            return floatval($pricing['eve_surcharge_sat'] ?? 0);
        }

        // Check if tomorrow is Sunday
        if ($tomorrow_dow == 0) {
            return floatval($pricing['eve_surcharge_sun'] ?? 0);
        }

        // Check if tomorrow is a holiday
        if (self::isJapaneseHoliday($tomorrow_string)) {
            return floatval($pricing['eve_surcharge_holiday'] ?? 0);
        }

        return 0;
    }
}