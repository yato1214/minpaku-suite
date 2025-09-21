<?php
/**
 * Owner API Controller
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Api;

use MinpakuSuite\Portal\OwnerHelpers;
use MinpakuSuite\Portal\OwnerRoles;

if (!defined('ABSPATH')) {
    exit;
}

class OwnerApiController
{
    private const NAMESPACE = 'minpaku/v1';

    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public static function register_routes(): void
    {
        // Owner summary endpoint
        register_rest_route(self::NAMESPACE, '/owner/summary', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_owner_summary'],
            'permission_callback' => [__CLASS__, 'check_owner_permissions'],
            'args' => [
                'days' => [
                    'required' => false,
                    'default' => 30,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 365,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);

        // Owner properties endpoint
        register_rest_route(self::NAMESPACE, '/owner/properties', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_owner_properties'],
            'permission_callback' => [__CLASS__, 'check_owner_permissions'],
            'args' => [
                'page' => [
                    'required' => false,
                    'default' => 1,
                    'type' => 'integer',
                    'minimum' => 1,
                    'sanitize_callback' => 'absint'
                ],
                'per_page' => [
                    'required' => false,
                    'default' => 20,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'sanitize_callback' => 'absint'
                ],
                'days' => [
                    'required' => false,
                    'default' => 30,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 365,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);

        // Single property stats endpoint
        register_rest_route(self::NAMESPACE, '/owner/properties/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_property_stats'],
            'permission_callback' => [__CLASS__, 'check_property_permissions'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'days' => [
                    'required' => false,
                    'default' => 30,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 365,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
    }

    /**
     * Check if user has owner permissions
     */
    public static function check_owner_permissions(\WP_REST_Request $request): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        return OwnerRoles::user_can_access_portal($user_id);
    }

    /**
     * Check if user can access specific property
     */
    public static function check_property_permissions(\WP_REST_Request $request): bool
    {
        if (!self::check_owner_permissions($request)) {
            return false;
        }

        $property_id = absint($request->get_param('id'));
        $user_id = get_current_user_id();

        return OwnerHelpers::user_owns_property($user_id, $property_id);
    }

    /**
     * Get owner summary statistics
     */
    public static function get_owner_summary(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_id = get_current_user_id();
        $days = $request->get_param('days');

        try {
            $summary = OwnerHelpers::get_owner_summary($user_id, $days);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $summary,
                'period' => $days
            ], 200);

        } catch (Exception $e) {
            error_log('Minpaku Suite API Error: ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Unable to fetch summary data.', 'minpaku-suite'),
                'code' => 'summary_fetch_error'
            ], 500);
        }
    }

    /**
     * Get owner properties with stats
     */
    public static function get_owner_properties(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_id = get_current_user_id();
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $days = $request->get_param('days');

        try {
            $query_args = [
                'posts_per_page' => $per_page,
                'paged' => $page
            ];

            $properties_query = OwnerHelpers::get_user_properties($user_id, $query_args);
            $properties = [];

            if ($properties_query->have_posts()) {
                while ($properties_query->have_posts()) {
                    $properties_query->the_post();
                    $property_id = get_the_ID();

                    $properties[] = self::format_property_data($property_id, $days);
                }
                wp_reset_postdata();
            }

            return new \WP_REST_Response([
                'success' => true,
                'data' => $properties,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $properties_query->found_posts,
                    'total_pages' => $properties_query->max_num_pages
                ],
                'period' => $days
            ], 200);

        } catch (Exception $e) {
            error_log('Minpaku Suite API Error: ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Unable to fetch properties data.', 'minpaku-suite'),
                'code' => 'properties_fetch_error'
            ], 500);
        }
    }

    /**
     * Get single property statistics
     */
    public static function get_property_stats(\WP_REST_Request $request): \WP_REST_Response
    {
        $property_id = $request->get_param('id');
        $days = $request->get_param('days');

        try {
            $property_data = self::format_property_data($property_id, $days);

            if (!$property_data) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Property not found.', 'minpaku-suite'),
                    'code' => 'property_not_found'
                ], 404);
            }

            return new \WP_REST_Response([
                'success' => true,
                'data' => $property_data,
                'period' => $days
            ], 200);

        } catch (Exception $e) {
            error_log('Minpaku Suite API Error: ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Unable to fetch property data.', 'minpaku-suite'),
                'code' => 'property_fetch_error'
            ], 500);
        }
    }

    /**
     * Format property data for API response
     */
    private static function format_property_data(int $property_id, int $days): ?array
    {
        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'mcs_property') {
            return null;
        }

        $booking_counts = OwnerHelpers::get_property_booking_counts($property_id, $days);
        $thumbnail = get_the_post_thumbnail_url($property_id, 'medium');

        return [
            'id' => $property_id,
            'title' => $property->post_title,
            'status' => $property->post_status,
            'url' => get_permalink($property_id),
            'edit_url' => get_edit_post_link($property_id),
            'thumbnail' => $thumbnail ?: '',
            'meta' => [
                'capacity' => get_post_meta($property_id, 'capacity', true) ?: 0,
                'bedrooms' => get_post_meta($property_id, 'bedrooms', true) ?: 0,
                'bathrooms' => get_post_meta($property_id, 'bathrooms', true) ?: 0,
            ],
            'booking_counts' => $booking_counts,
            'links' => [
                'view' => get_permalink($property_id),
                'edit' => get_edit_post_link($property_id),
                'add_booking' => admin_url('post-new.php?post_type=mcs_booking&property_id=' . $property_id),
                'calendar_shortcode' => '[mcs_availability id="' . $property_id . '"]'
            ]
        ];
    }

    /**
     * Get REST API base URL
     */
    public static function get_api_url(string $endpoint = ''): string
    {
        $base = rest_url(self::NAMESPACE);
        return $endpoint ? trailingslashit($base) . ltrim($endpoint, '/') : $base;
    }

    /**
     * Get API nonce for AJAX requests
     */
    public static function get_api_nonce(): string
    {
        return wp_create_nonce('wp_rest');
    }
}