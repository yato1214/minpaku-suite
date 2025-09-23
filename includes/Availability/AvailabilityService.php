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
        error_log('[AvailabilityService] Starting calculateOccupancyMap');

        // Initialize all dates as vacant
        $occupancy_map = self::getEmptyOccupancyMap($from, $to);
        error_log('[AvailabilityService] Created empty occupancy map with ' . count($occupancy_map) . ' dates');

        // Get all bookings for this property in the date range
        $bookings = self::getPropertyBookings($property_id, $from, $to);

        $processed_bookings = 0;
        $loop_protection = 0;
        $max_loops = 10000; // Prevent infinite loops

        foreach ($bookings as $booking) {
            if (++$loop_protection > $max_loops) {
                error_log('[AvailabilityService] Loop protection triggered - breaking out of booking processing');
                break;
            }

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
            $date_loop_protection = 0;
            $max_date_loops = 1000; // Prevent infinite date loops

            while ($current_date < $checkout_date) {
                if (++$date_loop_protection > $max_date_loops) {
                    error_log('[AvailabilityService] Date loop protection triggered for booking ' . $booking->ID);
                    break;
                }

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

            $processed_bookings++;
        }

        error_log('[AvailabilityService] calculateOccupancyMap completed. Processed ' . $processed_bookings . ' bookings');
        return $occupancy_map;
    }

    /**
     * Get property bookings in date range
     */
    private static function getPropertyBookings(int $property_id, \DateTime $from, \DateTime $to): array
    {
        error_log('[AvailabilityService] Starting getPropertyBookings for property ' . $property_id);

        // Add timeout protection
        $start_time = microtime(true);
        $max_execution_time = 10; // 10 seconds max

        $args = [
            'post_type' => 'mcs_booking',
            'post_status' => 'publish',
            'posts_per_page' => 500, // Limit to prevent memory issues
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

        try {
            $bookings = get_posts($args);
            $query_time = microtime(true) - $start_time;
            error_log('[AvailabilityService] getPropertyBookings completed in ' . number_format($query_time, 4) . ' seconds. Found ' . count($bookings) . ' bookings');
            return $bookings;
        } catch (\Exception $e) {
            error_log('[AvailabilityService] getPropertyBookings ERROR: ' . $e->getMessage());
            return [];
        }
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

    /**
     * Get availability data for date range (for API)
     */
    public static function get_availability_range(int $property_id, string $start_date, string $end_date): array
    {
        error_log('[AvailabilityService] Starting get_availability_range for property ' . $property_id . ', dates: ' . $start_date . ' to ' . $end_date);

        $start = \DateTime::createFromFormat('Y-m-d', $start_date);
        $end = \DateTime::createFromFormat('Y-m-d', $end_date);

        if (!$start || !$end || $start >= $end) {
            error_log('[AvailabilityService] Invalid date range provided');
            return [];
        }

        // Get occupancy map for the date range
        error_log('[AvailabilityService] Getting occupancy map...');
        $start_time = microtime(true);
        $occupancy_map = self::getPropertyOccupancyMap($property_id, $start, $end);
        $map_time = microtime(true) - $start_time;
        error_log('[AvailabilityService] Occupancy map generated in ' . number_format($map_time, 4) . ' seconds');

        $availability = [];
        $current = clone $start;

        error_log('[AvailabilityService] Starting to build availability array...');
        $data_start_time = microtime(true);

        // Get base price once to avoid repeated meta queries
        $base_price = floatval(get_post_meta($property_id, '_mcs_base_price', true)) ?:
                     floatval(get_post_meta($property_id, 'base_price', true)) ?: 100;

        while ($current < $end) {
            $date_string = $current->format('Y-m-d');
            $status = $occupancy_map[$date_string] ?? self::STATUS_VACANT;

            // Convert status to availability format
            $is_available = ($status === self::STATUS_VACANT);
            $status_string = match($status) {
                self::STATUS_VACANT => 'available',
                self::STATUS_PARTIAL => 'partial',
                self::STATUS_FULL => 'booked',
                default => 'available'
            };

            $availability[] = [
                'date' => $date_string,
                'available' => $is_available,
                'status' => $status_string,
                'price' => $is_available ? $base_price : null
            ];

            $current->add(new \DateInterval('P1D'));
        }

        $data_time = microtime(true) - $data_start_time;
        error_log('[AvailabilityService] Availability array built in ' . number_format($data_time, 4) . ' seconds. Total items: ' . count($availability));

        return $availability;
    }
}