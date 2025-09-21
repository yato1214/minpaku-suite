<?php
/**
 * Admin Dashboard Service
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class AdminDashboardService
{
    /**
     * Read booking meta with fallback support
     *
     * @param int $post_id Booking post ID
     * @return array Normalized booking meta data
     */
    public static function read_booking_meta(int $post_id): array
    {
        $meta = [
            'status' => 'PENDING',
            'property_id' => null,
            'checkin_date' => null,
            'checkout_date' => null,
            'guest_name' => ''
        ];

        // Status with fallbacks (convert to uppercase)
        $status_keys = ['_mcs_status', '_booking_status', 'status'];
        foreach ($status_keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (!empty($value)) {
                $meta['status'] = strtoupper(trim($value));
                break;
            }
        }

        // Property ID with fallbacks
        $property_keys = ['_mcs_property_id', '_property_id', 'mcs_property_id', 'property_id'];
        foreach ($property_keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (!empty($value) && is_numeric($value)) {
                $meta['property_id'] = absint($value);
                break;
            }
        }

        // Check-in date with fallbacks
        $checkin_keys = ['_mcs_checkin_date', '_checkin', 'checkin_date', 'check_in_date'];
        foreach ($checkin_keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (!empty($value)) {
                // Try to normalize date format
                $timestamp = is_numeric($value) ? $value : strtotime($value);
                if ($timestamp !== false) {
                    $meta['checkin_date'] = date('Y-m-d', $timestamp);
                    break;
                }
            }
        }

        // Check-out date with fallbacks
        $checkout_keys = ['_mcs_checkout_date', '_checkout', 'checkout_date', 'check_out_date'];
        foreach ($checkout_keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (!empty($value)) {
                // Try to normalize date format
                $timestamp = is_numeric($value) ? $value : strtotime($value);
                if ($timestamp !== false) {
                    $meta['checkout_date'] = date('Y-m-d', $timestamp);
                    break;
                }
            }
        }

        // Guest name with fallbacks
        $guest_keys = ['_mcs_guest_name', 'guest_name', '_guest_name'];
        foreach ($guest_keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (!empty($value)) {
                $meta['guest_name'] = sanitize_text_field($value);
                break;
            }
        }

        return $meta;
    }

    /**
     * Get dashboard counts and statistics
     *
     * @param int $days Number of days to look forward for future bookings
     * @param int|null $owner_user_id If set, filter to properties owned by this user
     * @return array
     */
    public static function get_counts(int $days = 30, ?int $owner_user_id = null): array
    {
        $counts = [
            'properties' => 0,
            'confirmed_count' => 0,
            'pending_count' => 0,
            'total_count' => 0,
            'bookings_period' => 0, // For backward compatibility
            'occupancy_pct' => null
        ];

        try {
            // Get property IDs for owner filtering
            $property_ids = [];
            if ($owner_user_id !== null) {
                $properties_query = new \WP_Query([
                    'post_type' => 'mcs_property',
                    'post_status' => 'any',
                    'author' => $owner_user_id,
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false
                ]);
                $property_ids = $properties_query->posts;
                $counts['properties'] = count($property_ids);
            } else {
                // Count all published properties
                $properties_count = wp_count_posts('mcs_property');
                $counts['properties'] = isset($properties_count->publish) ? (int) $properties_count->publish : 0;
            }

            // Period range: today (00:00) to today + days (00:00) - bookings that overlap this period
            $period_start = date('Y-m-d'); // today 00:00
            $period_end = date('Y-m-d', strtotime("+{$days} days")); // today + days 00:00

            // Get all bookings
            $bookings_query = new \WP_Query([
                'post_type' => 'mcs_booking',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'no_found_rows' => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false
            ]);

            if ($bookings_query->have_posts()) {
                while ($bookings_query->have_posts()) {
                    $bookings_query->the_post();
                    $booking_id = get_the_ID();
                    $booking_meta = self::read_booking_meta($booking_id);

                    // Skip if no property ID or dates
                    if (!$booking_meta['property_id'] || !$booking_meta['checkin_date']) {
                        continue;
                    }

                    // Filter by owner's properties if specified
                    if (!empty($property_ids) && !in_array($booking_meta['property_id'], $property_ids)) {
                        continue;
                    }

                    // Check if booking overlaps with our period
                    // Condition: checkout > period_start && checkin < period_end
                    $checkin = $booking_meta['checkin_date'];
                    $checkout = $booking_meta['checkout_date'] ?: $checkin;

                    if ($checkout > $period_start && $checkin < $period_end) {
                        $counts['total_count']++;

                        if ($booking_meta['status'] === 'CONFIRMED') {
                            $counts['confirmed_count']++;
                        } elseif ($booking_meta['status'] === 'PENDING') {
                            $counts['pending_count']++;
                        }
                    }
                }
            }

            wp_reset_postdata();

            // Set backward compatibility field
            $counts['bookings_period'] = $counts['total_count'];

            // Calculate simple occupancy percentage (rough estimate)
            if ($counts['properties'] > 0 && $counts['confirmed_count'] > 0) {
                $max_possible_days = $counts['properties'] * $days;
                $confirmed_booking_days = self::count_confirmed_booking_days($start_date, $end_date, $property_ids);

                if ($confirmed_booking_days > 0 && $max_possible_days > 0) {
                    $counts['occupancy_pct'] = min(100, round(($confirmed_booking_days / $max_possible_days) * 100, 1));
                }
            }

        } catch (\Exception $e) {
            error_log('Minpaku Suite Dashboard Service Error: ' . $e->getMessage());
        }

        return $counts;
    }

    /**
     * Get recent bookings
     *
     * @param int $limit Number of bookings to retrieve
     * @param int|null $days Number of days to look forward for future bookings, null for all bookings
     * @param int|null $owner_user_id If set, filter to properties owned by this user
     * @return array
     */
    public static function get_recent_bookings(int $limit = 5, ?int $days = null, ?int $owner_user_id = null): array
    {
        $bookings = [];

        try {
            // Get property IDs for owner filtering
            $property_ids = [];
            if ($owner_user_id !== null) {
                $properties_query = new \WP_Query([
                    'post_type' => 'mcs_property',
                    'post_status' => 'any',
                    'author' => $owner_user_id,
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false
                ]);
                $property_ids = $properties_query->posts;
            }

            $query_args = [
                'post_type' => 'mcs_booking',
                'post_status' => 'publish',
                'posts_per_page' => -1, // Get all first, then filter and limit
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => true,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => true
            ];

            $query = new \WP_Query($query_args);
            $matched_bookings = [];

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $booking_id = get_the_ID();
                    $booking_meta = self::read_booking_meta($booking_id);

                    // Skip if no property ID
                    if (!$booking_meta['property_id']) {
                        continue;
                    }

                    // Filter by owner's properties if specified
                    if (!empty($property_ids) && !in_array($booking_meta['property_id'], $property_ids)) {
                        continue;
                    }

                    // Date filtering if days specified - use same overlap logic as get_counts
                    if ($days !== null && $booking_meta['checkin_date']) {
                        $period_start = date('Y-m-d'); // today 00:00
                        $period_end = date('Y-m-d', strtotime("+{$days} days")); // today + days 00:00
                        $checkin = $booking_meta['checkin_date'];
                        $checkout = $booking_meta['checkout_date'] ?: $checkin;

                        // Only include if booking overlaps with period: checkout > period_start && checkin < period_end
                        if (!($checkout > $period_start && $checkin < $period_end)) {
                            continue;
                        }
                    }

                    // Get property title with fallback
                    $property_title = __('Unknown Property', 'minpaku-suite');
                    if ($booking_meta['property_id'] && get_post_status($booking_meta['property_id'])) {
                        $property_title = get_the_title($booking_meta['property_id']);
                    }

                    // Calculate nights
                    $nights = 0;
                    if ($booking_meta['checkin_date'] && $booking_meta['checkout_date']) {
                        try {
                            $check_in_date = new \DateTime($booking_meta['checkin_date']);
                            $check_out_date = new \DateTime($booking_meta['checkout_date']);
                            $interval = $check_in_date->diff($check_out_date);
                            $nights = $interval->days;
                        } catch (\Exception $e) {
                            // Date parsing error, nights stays 0
                        }
                    }

                    $matched_bookings[] = [
                        'id' => $booking_id,
                        'property_id' => $booking_meta['property_id'],
                        'property_title' => $property_title,
                        'guest_name' => $booking_meta['guest_name'] ?: __('Guest', 'minpaku-suite'),
                        'check_in' => $booking_meta['checkin_date'],
                        'check_out' => $booking_meta['checkout_date'],
                        'nights' => $nights,
                        'status' => strtolower($booking_meta['status']), // Convert back to lowercase for display
                        'edit_link' => get_edit_post_link($booking_id)
                    ];

                    // Stop when we have enough
                    if (count($matched_bookings) >= $limit) {
                        break;
                    }
                }
            }

            wp_reset_postdata();
            $bookings = $matched_bookings;

        } catch (\Exception $e) {
            error_log('Minpaku Suite Dashboard Service Error: ' . $e->getMessage());
        }

        return $bookings;
    }

    /**
     * Get user's properties (limited list for dashboard)
     *
     * @param int $limit Number of properties to retrieve
     * @return array
     */
    public static function get_my_properties(int $limit = 5): array
    {
        $properties = [];

        try {
            $user_id = get_current_user_id();
            $query_args = [
                'post_type' => 'mcs_property',
                'post_status' => ['publish', 'draft', 'private'],
                'posts_per_page' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => true,
                'update_post_term_cache' => false
            ];

            // If not admin, limit to user's own properties
            if (!current_user_can('manage_options')) {
                $query_args['author'] = $user_id;
            }

            $query = new \WP_Query($query_args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $property_id = get_the_ID();

                    $capacity = get_post_meta($property_id, 'capacity', true);
                    $thumbnail = get_the_post_thumbnail_url($property_id, 'thumbnail');

                    $properties[] = [
                        'id' => $property_id,
                        'title' => get_the_title(),
                        'status' => get_post_status(),
                        'capacity' => $capacity ? absint($capacity) : 0,
                        'thumbnail' => $thumbnail,
                        'edit_link' => get_edit_post_link($property_id),
                        'view_link' => get_permalink($property_id)
                    ];
                }
            }

            wp_reset_postdata();

        } catch (Exception $e) {
            error_log('Minpaku Suite Dashboard Service Error: ' . $e->getMessage());
        }

        return $properties;
    }

    /**
     * Count confirmed booking days in a date range
     *
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @param array $property_ids Optional array of property IDs to filter by
     * @return int Total confirmed booking days
     */
    private static function count_confirmed_booking_days(string $start_date, string $end_date, array $property_ids = []): int
    {
        $total_days = 0;

        try {
            $query = new \WP_Query([
                'post_type' => 'mcs_booking',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'no_found_rows' => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false
            ]);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $booking_id = get_the_ID();
                    $booking_meta = self::read_booking_meta($booking_id);

                    // Only count confirmed bookings
                    if ($booking_meta['status'] !== 'CONFIRMED') {
                        continue;
                    }

                    // Filter by property IDs if specified
                    if (!empty($property_ids) && !in_array($booking_meta['property_id'], $property_ids)) {
                        continue;
                    }

                    if ($booking_meta['checkin_date'] && $booking_meta['checkout_date']) {
                        try {
                            $check_in_date = new \DateTime($booking_meta['checkin_date']);
                            $check_out_date = new \DateTime($booking_meta['checkout_date']);

                            // Only count days that overlap with our date range
                            $range_start = new \DateTime($start_date);
                            $range_end = new \DateTime($end_date);

                            $overlap_start = max($check_in_date, $range_start);
                            $overlap_end = min($check_out_date, $range_end);

                            if ($overlap_start <= $overlap_end) {
                                $interval = $overlap_start->diff($overlap_end);
                                $total_days += $interval->days;
                            }
                        } catch (\Exception $e) {
                            // Date parsing error, skip this booking
                            continue;
                        }
                    }
                }
            }

            wp_reset_postdata();

        } catch (\Exception $e) {
            error_log('Minpaku Suite Dashboard Service Error: ' . $e->getMessage());
        }

        return $total_days;
    }

    /**
     * Get status label with translation
     *
     * @param string $status Status key
     * @return string Translated status label
     */
    public static function get_status_label(string $status): string
    {
        $labels = [
            'confirmed' => __('Confirmed', 'minpaku-suite'),
            'pending' => __('Pending', 'minpaku-suite'),
            'cancelled' => __('Cancelled', 'minpaku-suite')
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Get property status label with translation
     *
     * @param string $status Property status
     * @return string Translated status label
     */
    public static function get_property_status_label(string $status): string
    {
        $labels = [
            'publish' => __('Published', 'minpaku-suite'),
            'draft' => __('Draft', 'minpaku-suite'),
            'private' => __('Private', 'minpaku-suite'),
            'pending' => __('Pending Review', 'minpaku-suite')
        ];

        return $labels[$status] ?? ucfirst($status);
    }
}