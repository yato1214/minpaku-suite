<?php
/**
 * Owner Helper Functions
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Portal;

if (!defined('ABSPATH')) {
    exit;
}

class OwnerHelpers
{
    public static function init(): void
    {
        add_action('save_post_mcs_property', [__CLASS__, 'sync_owner_on_save'], 10, 3);
    }

    /**
     * Get the owner ID for a property
     *
     * @param int $property_id Property ID
     * @return int Owner user ID, 0 if not found
     */
    public static function get_owner_id_for_property(int $property_id): int
    {
        if (!$property_id) {
            return 0;
        }

        // Check for custom owner meta first
        $owner_meta = get_post_meta($property_id, '_mcs_owner_user_id', true);
        if ($owner_meta && is_numeric($owner_meta)) {
            $owner_id = absint($owner_meta);
            if ($owner_id > 0 && get_user_by('id', $owner_id)) {
                return $owner_id;
            }
        }

        // Fall back to post author
        $property = get_post($property_id);
        if ($property && $property->post_type === 'mcs_property') {
            return absint($property->post_author);
        }

        return 0;
    }

    /**
     * Check if a user owns a property
     *
     * @param int $user_id User ID
     * @param int $property_id Property ID
     * @return bool True if user owns the property
     */
    public static function user_owns_property(int $user_id, int $property_id): bool
    {
        if (!$user_id || !$property_id) {
            return false;
        }

        // Administrators can access all properties
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $owner_id = self::get_owner_id_for_property($property_id);
        return $owner_id === $user_id;
    }

    /**
     * Get properties owned by a user
     *
     * @param int $user_id User ID
     * @param array $args Additional WP_Query arguments
     * @return \WP_Query
     */
    public static function get_user_properties(int $user_id, array $args = []): \WP_Query
    {
        $default_args = [
            'post_type' => 'mcs_property',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => 20,
            'meta_query' => [],
            'author' => 0,
        ];

        // Administrators see all properties
        if (user_can($user_id, 'manage_options')) {
            $query_args = array_merge($default_args, $args);
            unset($query_args['author']);
            return new \WP_Query($query_args);
        }

        // Regular owners see their own properties
        $meta_query = [
            'relation' => 'OR',
            [
                'key' => '_mcs_owner_user_id',
                'value' => $user_id,
                'compare' => '='
            ]
        ];

        $query_args = array_merge($default_args, $args, [
            'author' => $user_id,
            'meta_query' => array_merge($default_args['meta_query'], $meta_query)
        ]);

        return new \WP_Query($query_args);
    }

    /**
     * Get booking counts for a property within a date range
     *
     * @param int $property_id Property ID
     * @param int $days Number of days from today (default: 30)
     * @return array Booking counts by status
     */
    public static function get_property_booking_counts(int $property_id, int $days = 30): array
    {
        $start_date = current_time('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$days} days"));

        $query = new \WP_Query([
            'post_type' => 'mcs_booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'property_id',
                    'value' => $property_id,
                    'compare' => '='
                ],
                [
                    'key' => 'check_in_date',
                    'value' => $end_date,
                    'compare' => '<='
                ],
                [
                    'key' => 'check_out_date',
                    'value' => $start_date,
                    'compare' => '>='
                ]
            ],
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ]);

        $counts = [
            'total' => 0,
            'confirmed' => 0,
            'pending' => 0,
            'cancelled' => 0
        ];

        if ($query->have_posts()) {
            foreach ($query->posts as $booking) {
                $status = get_post_meta($booking->ID, 'status', true);
                $counts['total']++;

                switch ($status) {
                    case 'confirmed':
                        $counts['confirmed']++;
                        break;
                    case 'pending':
                        $counts['pending']++;
                        break;
                    case 'cancelled':
                        $counts['cancelled']++;
                        break;
                }
            }
        }

        wp_reset_postdata();
        return $counts;
    }

    /**
     * Get summary statistics for an owner
     *
     * @param int $user_id User ID
     * @param int $days Number of days from today (default: 30)
     * @return array Summary statistics
     */
    public static function get_owner_summary(int $user_id, int $days = 30): array
    {
        $properties_query = self::get_user_properties($user_id, [
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        $property_ids = $properties_query->posts;
        $summary = [
            'properties_count' => count($property_ids),
            'total_bookings' => 0,
            'confirmed_bookings' => 0,
            'pending_bookings' => 0,
            'cancelled_bookings' => 0
        ];

        foreach ($property_ids as $property_id) {
            $counts = self::get_property_booking_counts($property_id, $days);
            $summary['total_bookings'] += $counts['total'];
            $summary['confirmed_bookings'] += $counts['confirmed'];
            $summary['pending_bookings'] += $counts['pending'];
            $summary['cancelled_bookings'] += $counts['cancelled'];
        }

        return $summary;
    }

    /**
     * Sync owner information when property is saved (admin only)
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     * @param bool $update Whether this is an existing post being updated
     */
    public static function sync_owner_on_save(int $post_id, \WP_Post $post, bool $update): void
    {
        if (!is_admin() || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if ($post->post_type !== 'mcs_property') {
            return;
        }

        // Only sync if _mcs_owner_user_id is empty
        $current_owner_meta = get_post_meta($post_id, '_mcs_owner_user_id', true);
        if (empty($current_owner_meta)) {
            $current_user_id = get_current_user_id();
            if ($current_user_id && $post->post_author != $current_user_id) {
                // Update post author to match current user
                wp_update_post([
                    'ID' => $post_id,
                    'post_author' => $current_user_id
                ]);
            }
        }
    }

    /**
     * Set custom owner for a property
     *
     * @param int $property_id Property ID
     * @param int $user_id User ID
     * @return bool Success
     */
    public static function set_property_owner(int $property_id, int $user_id): bool
    {
        if (!$property_id || !$user_id) {
            return false;
        }

        if (!get_user_by('id', $user_id)) {
            return false;
        }

        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'mcs_property') {
            return false;
        }

        return update_post_meta($property_id, '_mcs_owner_user_id', $user_id);
    }
}