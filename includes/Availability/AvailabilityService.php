<?php
/**
 * Availability Service
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Availability;

if (!defined('ABSPATH')) {
    exit;
}

class AvailabilityService
{
    const CACHE_DURATION = 600; // 10 minutes

    const STATUS_VACANT = 'VACANT';
    const STATUS_PARTIAL = 'PARTIAL';
    const STATUS_FULL = 'FULL';

    /**
     * Get property occupancy map for date range
     *
     * @param int $property_id Property ID
     * @param DateTime $from Start date
     * @param DateTime $to End date
     * @return array<string, string> Array of date => status mapping
     */
    public static function getPropertyOccupancyMap(int $property_id, \DateTime $from, \DateTime $to): array
    {
        if ($property_id <= 0 || $from >= $to) {
            return [];
        }

        $cache_key = self::getCacheKey($property_id, $from, $to);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        try {
            $occupancy_map = self::calculateOccupancyMap($property_id, $from, $to);
            set_transient($cache_key, $occupancy_map, self::CACHE_DURATION);
            return $occupancy_map;
        } catch (Exception $e) {
            error_log('Minpaku Suite Availability Error: ' . $e->getMessage());
            return self::getEmptyOccupancyMap($from, $to);
        }
    }

    /**
     * Calculate occupancy map from bookings
     */
    private static function calculateOccupancyMap(int $property_id, \DateTime $from, \DateTime $to): array
    {
        // Initialize all dates as vacant
        $occupancy_map = self::getEmptyOccupancyMap($from, $to);

        // Get all bookings for this property in the date range
        $bookings = self::getPropertyBookings($property_id, $from, $to);

        foreach ($bookings as $booking) {
            $status = get_post_meta($booking->ID, '_mcs_status', true);
            $checkin = get_post_meta($booking->ID, '_mcs_checkin', true);
            $checkout = get_post_meta($booking->ID, '_mcs_checkout', true);

            // Skip cancelled bookings
            if ($status === 'CANCELLED') {
                continue;
            }

            if (!$checkin || !$checkout) {
                continue;
            }

            $checkin_date = \DateTime::createFromFormat('Y-m-d', $checkin);
            $checkout_date = \DateTime::createFromFormat('Y-m-d', $checkout);

            if (!$checkin_date || !$checkout_date) {
                continue;
            }

            // Mark dates as occupied (checkout day is not included)
            $current_date = clone $checkin_date;
            while ($current_date < $checkout_date) {
                $date_str = $current_date->format('Y-m-d');

                if (isset($occupancy_map[$date_str])) {
                    if ($status === 'CONFIRMED') {
                        $occupancy_map[$date_str] = self::STATUS_FULL;
                    } elseif ($status === 'PENDING' && $occupancy_map[$date_str] === self::STATUS_VACANT) {
                        $occupancy_map[$date_str] = self::STATUS_PARTIAL;
                    }
                }

                $current_date->modify('+1 day');
            }
        }

        return $occupancy_map;
    }

    /**
     * Get property bookings in date range
     */
    private static function getPropertyBookings(int $property_id, \DateTime $from, \DateTime $to): array
    {
        $args = [
            'post_type' => 'mcs_booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_mcs_property_id',
                    'value' => $property_id,
                    'compare' => '='
                ],
                [
                    'relation' => 'OR',
                    [
                        'relation' => 'AND',
                        [
                            'key' => '_mcs_checkin',
                            'value' => $from->format('Y-m-d'),
                            'compare' => '>='
                        ],
                        [
                            'key' => '_mcs_checkin',
                            'value' => $to->format('Y-m-d'),
                            'compare' => '<'
                        ]
                    ],
                    [
                        'relation' => 'AND',
                        [
                            'key' => '_mcs_checkout',
                            'value' => $from->format('Y-m-d'),
                            'compare' => '>'
                        ],
                        [
                            'key' => '_mcs_checkout',
                            'value' => $to->format('Y-m-d'),
                            'compare' => '<='
                        ]
                    ],
                    [
                        'relation' => 'AND',
                        [
                            'key' => '_mcs_checkin',
                            'value' => $from->format('Y-m-d'),
                            'compare' => '<='
                        ],
                        [
                            'key' => '_mcs_checkout',
                            'value' => $to->format('Y-m-d'),
                            'compare' => '>='
                        ]
                    ]
                ]
            ]
        ];

        return get_posts($args);
    }

    /**
     * Create empty occupancy map with all dates as vacant
     */
    private static function getEmptyOccupancyMap(\DateTime $from, \DateTime $to): array
    {
        $map = [];
        $current_date = clone $from;

        while ($current_date < $to) {
            $map[$current_date->format('Y-m-d')] = self::STATUS_VACANT;
            $current_date->modify('+1 day');
        }

        return $map;
    }

    /**
     * Generate cache key for occupancy map
     */
    private static function getCacheKey(int $property_id, \DateTime $from, \DateTime $to): string
    {
        return sprintf(
            'mcs_avail_%d_%s_%s',
            $property_id,
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        );
    }

    /**
     * Clear cache for a property
     */
    public static function clearCache(int $property_id): void
    {
        global $wpdb;

        $pattern = $wpdb->esc_like('mcs_avail_' . $property_id . '_') . '%';

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $pattern
        ));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . $pattern
        ));
    }

    /**
     * Get date range for current month
     */
    public static function getCurrentMonthRange(): array
    {
        $now = new \DateTime();
        $from = new \DateTime($now->format('Y-m-01'));
        $to = clone $from;
        $to->modify('+1 month');

        return [$from, $to];
    }

    /**
     * Get availability status for a single date
     */
    public static function getDateAvailability(int $property_id, string $date): string
    {
        $date_obj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj) {
            return self::STATUS_VACANT;
        }

        $from = clone $date_obj;
        $to = clone $date_obj;
        $to->modify('+1 day');

        $map = self::getPropertyOccupancyMap($property_id, $from, $to);

        return $map[$date] ?? self::STATUS_VACANT;
    }

    /**
     * Check if property is available for a date range
     */
    public static function isPropertyAvailable(int $property_id, string $checkin, string $checkout): bool
    {
        $checkin_date = \DateTime::createFromFormat('Y-m-d', $checkin);
        $checkout_date = \DateTime::createFromFormat('Y-m-d', $checkout);

        if (!$checkin_date || !$checkout_date || $checkin_date >= $checkout_date) {
            return false;
        }

        $map = self::getPropertyOccupancyMap($property_id, $checkin_date, $checkout_date);

        foreach ($map as $status) {
            if ($status === self::STATUS_FULL) {
                return false;
            }
        }

        return true;
    }
}