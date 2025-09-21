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
     * Get dashboard counts and statistics
     *
     * @param int $days Number of days to look back for bookings
     * @return array
     */
    public static function get_counts(int $days = 30): array
    {
        $counts = [
            'properties' => 0,
            'bookings_period' => 0,
            'occupancy_pct' => null
        ];

        try {
            // Count published properties
            $properties_count = wp_count_posts('mcs_property');
            $counts['properties'] = isset($properties_count->publish) ? (int) $properties_count->publish : 0;

            // Count bookings in specified period
            $start_date = date('Y-m-d', strtotime("-{$days} days"));
            $end_date = date('Y-m-d');

            $bookings_query = new \WP_Query([
                'post_type' => 'mcs_booking',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'key' => 'check_in_date',
                        'value' => [$start_date, $end_date],
                        'compare' => 'BETWEEN',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => 'check_out_date',
                        'value' => [$start_date, $end_date],
                        'compare' => 'BETWEEN',
                        'type' => 'DATE'
                    ]
                ],
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false
            ]);

            $counts['bookings_period'] = $bookings_query->found_posts;
            wp_reset_postdata();

            // Calculate simple occupancy percentage (if possible)
            if ($counts['properties'] > 0 && $counts['bookings_period'] > 0) {
                // Simple calculation: bookings / (properties * days) * 100
                // This is a rough estimate, not precise occupancy
                $max_possible_days = $counts['properties'] * $days;
                $total_booking_days = self::count_total_booking_days($start_date, $end_date);

                if ($total_booking_days > 0 && $max_possible_days > 0) {
                    $counts['occupancy_pct'] = min(100, round(($total_booking_days / $max_possible_days) * 100, 1));
                }
            }

        } catch (Exception $e) {
            error_log('Minpaku Suite Dashboard Service Error: ' . $e->getMessage());
        }

        return $counts;
    }

    /**
     * Get recent bookings
     *
     * @param int $limit Number of bookings to retrieve
     * @param int|null $days Number of days to look back, null for all bookings
     * @return array
     */
    public static function get_recent_bookings(int $limit = 5, ?int $days = null): array
    {
        $bookings = [];

        try {
            $query_args = [
                'post_type' => 'mcs_booking',
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => true,
                'update_post_term_cache' => false
            ];

            // Add date filter if days specified
            if ($days !== null) {
                $start_date = date('Y-m-d', strtotime("-{$days} days"));
                $query_args['meta_query'] = [
                    'relation' => 'OR',
                    [
                        'key' => 'check_in_date',
                        'value' => $start_date,
                        'compare' => '>=',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => 'check_out_date',
                        'value' => $start_date,
                        'compare' => '>=',
                        'type' => 'DATE'
                    ]
                ];
            }

            $query = new \WP_Query($query_args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $booking_id = get_the_ID();

                    // Try multiple meta keys for property ID
                    $property_id = get_post_meta($booking_id, 'property_id', true);
                    if (!$property_id) {
                        $property_id = get_post_meta($booking_id, '_mcs_property_id', true);
                    }

                    $property_title = __('Unknown Property', 'minpaku-suite');
                    if ($property_id && get_post_status($property_id)) {
                        $property_title = get_the_title($property_id);
                    }

                    $check_in = get_post_meta($booking_id, 'check_in_date', true);
                    $check_out = get_post_meta($booking_id, 'check_out_date', true);
                    $status = get_post_meta($booking_id, 'status', true);
                    $guest_name = get_post_meta($booking_id, 'guest_name', true);

                    // Calculate nights
                    $nights = 0;
                    if ($check_in && $check_out) {
                        $check_in_date = new \DateTime($check_in);
                        $check_out_date = new \DateTime($check_out);
                        $interval = $check_in_date->diff($check_out_date);
                        $nights = $interval->days;
                    }

                    $bookings[] = [
                        'id' => $booking_id,
                        'property_title' => $property_title,
                        'property_id' => $property_id,
                        'guest_name' => $guest_name ?: __('Guest', 'minpaku-suite'),
                        'check_in' => $check_in,
                        'check_out' => $check_out,
                        'nights' => $nights,
                        'status' => $status ?: 'pending',
                        'edit_link' => get_edit_post_link($booking_id)
                    ];
                }
            }

            wp_reset_postdata();

        } catch (Exception $e) {
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
     * Count total booking days in a date range
     *
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return int Total booking days
     */
    private static function count_total_booking_days(string $start_date, string $end_date): int
    {
        $total_days = 0;

        try {
            $query = new \WP_Query([
                'post_type' => 'mcs_booking',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => 'status',
                        'value' => 'confirmed',
                        'compare' => '='
                    ]
                ],
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false
            ]);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $booking_id = get_the_ID();

                    $check_in = get_post_meta($booking_id, 'check_in_date', true);
                    $check_out = get_post_meta($booking_id, 'check_out_date', true);

                    if ($check_in && $check_out) {
                        $check_in_date = new \DateTime($check_in);
                        $check_out_date = new \DateTime($check_out);

                        // Only count days that overlap with our date range
                        $range_start = new \DateTime($start_date);
                        $range_end = new \DateTime($end_date);

                        $overlap_start = max($check_in_date, $range_start);
                        $overlap_end = min($check_out_date, $range_end);

                        if ($overlap_start <= $overlap_end) {
                            $interval = $overlap_start->diff($overlap_end);
                            $total_days += $interval->days;
                        }
                    }
                }
            }

            wp_reset_postdata();

        } catch (Exception $e) {
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