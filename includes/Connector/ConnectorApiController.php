<?php
/**
 * Connector API Controller
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Connector;

use MinpakuSuite\Availability\AvailabilityService;

if (!defined('ABSPATH')) {
    exit;
}

class ConnectorApiController
{
    private const NAMESPACE = 'minpaku/v1/connector';

    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('init', [__CLASS__, 'handle_preflight'], 1);
    }

    /**
     * Handle preflight OPTIONS requests
     */
    public static function handle_preflight(): void
    {
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json/' . self::NAMESPACE) !== false) {
            ConnectorAuth::handle_preflight();
        }
    }

    /**
     * Register REST API routes
     */
    public static function register_routes(): void
    {
        // Verify endpoint
        register_rest_route(self::NAMESPACE, '/verify', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'verify_connection'],
            'permission_callback' => [__CLASS__, 'check_connector_permissions'],
        ]);

        // Properties endpoint
        register_rest_route(self::NAMESPACE, '/properties', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_properties'],
            'permission_callback' => [__CLASS__, 'check_connector_permissions'],
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
                'status' => [
                    'required' => false,
                    'default' => 'publish',
                    'type' => 'string',
                    'enum' => ['publish', 'private', 'any']
                ]
            ]
        ]);

        // Availability endpoint
        register_rest_route(self::NAMESPACE, '/availability', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_availability'],
            'permission_callback' => [__CLASS__, 'check_connector_permissions'],
            'args' => [
                'property_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'months' => [
                    'required' => false,
                    'default' => 2,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 12,
                    'sanitize_callback' => 'absint'
                ],
                'start_date' => [
                    'required' => false,
                    'type' => 'string',
                    'format' => 'date',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Quote endpoint (skeleton)
        register_rest_route(self::NAMESPACE, '/quote', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'get_quote'],
            'permission_callback' => [__CLASS__, 'check_connector_permissions'],
            'args' => [
                'property_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'check_in' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'date',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'check_out' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'date',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'guests' => [
                    'required' => false,
                    'default' => 2,
                    'type' => 'integer',
                    'minimum' => 1,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
    }

    /**
     * Check connector permissions and set CORS headers
     */
    public static function check_connector_permissions(\WP_REST_Request $request): bool
    {
        // Set CORS headers
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        ConnectorAuth::set_cors_headers($origin);

        // Verify HMAC authentication
        return ConnectorAuth::verify_request($request);
    }

    /**
     * Verify connection endpoint
     */
    public static function verify_connection(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Connection verified successfully.', 'minpaku-suite'),
            'version' => MINPAKU_SUITE_VERSION,
            'timestamp' => current_time('mysql'),
            'endpoints' => [
                'properties' => rest_url(self::NAMESPACE . '/properties'),
                'availability' => rest_url(self::NAMESPACE . '/availability'),
                'quote' => rest_url(self::NAMESPACE . '/quote')
            ]
        ], 200);
    }

    /**
     * Get properties endpoint
     */
    public static function get_properties(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $status = $request->get_param('status');

        try {
            $query_args = [
                'post_type' => 'mcs_property',
                'post_status' => $status,
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => 'title',
                'order' => 'ASC'
            ];

            $query = new \WP_Query($query_args);
            $properties = [];

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $property_id = get_the_ID();

                    $properties[] = self::format_property_for_connector($property_id);
                }
                wp_reset_postdata();
            }

            return new \WP_REST_Response([
                'success' => true,
                'data' => $properties,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $query->found_posts,
                    'total_pages' => $query->max_num_pages
                ]
            ], 200);

        } catch (\Exception $e) {
            error_log('Minpaku Connector API Error: ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Unable to fetch properties.', 'minpaku-suite'),
                'code' => 'properties_fetch_error'
            ], 500);
        }
    }

    /**
     * Get availability endpoint
     */
    public static function get_availability(\WP_REST_Request $request): \WP_REST_Response
    {
        $property_id = $request->get_param('property_id');
        $months = $request->get_param('months');
        $start_date = $request->get_param('start_date');

        try {
            // Verify property exists
            $property = get_post($property_id);
            if (!$property || $property->post_type !== 'mcs_property') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Property not found.', 'minpaku-suite'),
                    'code' => 'property_not_found'
                ], 404);
            }

            // Calculate date range
            $start = $start_date ? new \DateTime($start_date) : new \DateTime();
            $end = clone $start;
            $end->add(new \DateInterval('P' . $months . 'M'));

            // Get availability data
            $availability = [];
            if (class_exists('MinpakuSuite\Availability\AvailabilityService')) {
                $availability = AvailabilityService::get_availability_range(
                    $property_id,
                    $start->format('Y-m-d'),
                    $end->format('Y-m-d')
                );
            } else {
                // Fallback: generate basic availability data
                $availability = self::generate_basic_availability($property_id, $start, $end);
            }

            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'property_id' => $property_id,
                    'property_title' => $property->post_title,
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                    'availability' => $availability
                ]
            ], 200);

        } catch (\Exception $e) {
            error_log('Minpaku Connector API Error: ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Unable to fetch availability.', 'minpaku-suite'),
                'code' => 'availability_fetch_error'
            ], 500);
        }
    }

    /**
     * Get quote endpoint (skeleton implementation)
     */
    public static function get_quote(\WP_REST_Request $request): \WP_REST_Response
    {
        $property_id = $request->get_param('property_id');
        $check_in = $request->get_param('check_in');
        $check_out = $request->get_param('check_out');
        $guests = $request->get_param('guests');

        try {
            // Verify property exists
            $property = get_post($property_id);
            if (!$property || $property->post_type !== 'mcs_property') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Property not found.', 'minpaku-suite'),
                    'code' => 'property_not_found'
                ], 404);
            }

            // Basic date validation
            $check_in_date = new \DateTime($check_in);
            $check_out_date = new \DateTime($check_out);

            if ($check_in_date >= $check_out_date) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Check-out date must be after check-in date.', 'minpaku-suite'),
                    'code' => 'invalid_dates'
                ], 400);
            }

            $nights = $check_in_date->diff($check_out_date)->days;

            // Skeleton quote calculation
            $base_price = floatval(get_post_meta($property_id, 'base_price', true)) ?: 100;
            $subtotal = $base_price * $nights;
            $tax_rate = 0.1; // 10% tax
            $tax = $subtotal * $tax_rate;
            $total = $subtotal + $tax;

            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'property_id' => $property_id,
                    'property_title' => $property->post_title,
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'guests' => $guests,
                    'nights' => $nights,
                    'pricing' => [
                        'base_price' => $base_price,
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'total' => $total,
                        'currency' => 'JPY'
                    ],
                    'note' => __('This is a preliminary quote. Final pricing may vary.', 'minpaku-suite')
                ]
            ], 200);

        } catch (\Exception $e) {
            error_log('Minpaku Connector API Error: ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Unable to generate quote.', 'minpaku-suite'),
                'code' => 'quote_generation_error'
            ], 500);
        }
    }

    /**
     * Format property data for connector API
     */
    private static function format_property_for_connector(int $property_id): array
    {
        $property = get_post($property_id);
        $thumbnail = get_the_post_thumbnail_url($property_id, 'medium');

        // Get property meta
        $capacity = get_post_meta($property_id, 'capacity', true) ?: 0;
        $bedrooms = get_post_meta($property_id, 'bedrooms', true) ?: 0;
        $bathrooms = get_post_meta($property_id, 'bathrooms', true) ?: 0;
        $base_price = get_post_meta($property_id, 'base_price', true) ?: 0;

        // Get gallery images
        $gallery_ids = get_post_meta($property_id, 'gallery', true);
        $gallery = [];
        if ($gallery_ids && is_array($gallery_ids)) {
            foreach ($gallery_ids as $image_id) {
                $gallery[] = [
                    'id' => $image_id,
                    'url' => wp_get_attachment_image_url($image_id, 'large'),
                    'thumbnail' => wp_get_attachment_image_url($image_id, 'medium'),
                    'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
                ];
            }
        }

        return [
            'id' => $property_id,
            'title' => $property->post_title,
            'content' => $property->post_content,
            'excerpt' => $property->post_excerpt ?: wp_trim_words($property->post_content, 20),
            'status' => $property->post_status,
            'thumbnail' => $thumbnail ?: '',
            'gallery' => $gallery,
            'meta' => [
                'capacity' => intval($capacity),
                'bedrooms' => intval($bedrooms),
                'bathrooms' => intval($bathrooms),
                'base_price' => floatval($base_price)
            ],
            'amenities' => get_post_meta($property_id, 'amenities', true) ?: [],
            'location' => [
                'address' => get_post_meta($property_id, 'address', true) ?: '',
                'city' => get_post_meta($property_id, 'city', true) ?: '',
                'region' => get_post_meta($property_id, 'region', true) ?: '',
                'country' => get_post_meta($property_id, 'country', true) ?: 'Japan'
            ]
        ];
    }

    /**
     * Generate basic availability data (fallback)
     */
    private static function generate_basic_availability(int $property_id, \DateTime $start, \DateTime $end): array
    {
        $availability = [];
        $current = clone $start;

        // Get existing bookings for this property
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

        $booked_dates = [];
        foreach ($bookings as $booking) {
            $check_in = get_post_meta($booking->ID, 'check_in_date', true);
            $check_out = get_post_meta($booking->ID, 'check_out_date', true);

            if ($check_in && $check_out) {
                $booking_start = new \DateTime($check_in);
                $booking_end = new \DateTime($check_out);

                while ($booking_start < $booking_end) {
                    $booked_dates[] = $booking_start->format('Y-m-d');
                    $booking_start->add(new \DateInterval('P1D'));
                }
            }
        }

        while ($current < $end) {
            $date_string = $current->format('Y-m-d');
            $is_available = !in_array($date_string, $booked_dates);

            $availability[] = [
                'date' => $date_string,
                'available' => $is_available,
                'status' => $is_available ? 'available' : 'booked',
                'price' => $is_available ? floatval(get_post_meta($property_id, 'base_price', true)) ?: 100 : null
            ];

            $current->add(new \DateInterval('P1D'));
        }

        return $availability;
    }

    /**
     * Get REST API base URL
     */
    public static function get_api_url(string $endpoint = ''): string
    {
        $base = rest_url(self::NAMESPACE);
        return $endpoint ? trailingslashit($base) . ltrim($endpoint, '/') : $base;
    }
}