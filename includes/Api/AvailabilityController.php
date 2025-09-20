<?php
/**
 * Availability API Controller
 * Provides read-only availability data for external systems
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class AvailabilityController extends WP_REST_Controller {

    /**
     * Namespace for this controller's routes
     */
    protected $namespace = 'minpaku/v1';

    /**
     * Rest base for this controller
     */
    protected $rest_base = 'availability';

    /**
     * Register the routes for the objects of the controller
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_availability'],
                'permission_callback' => [$this, 'get_availability_permissions_check'],
                'args' => $this->get_availability_params(),
            ],
        ]);
    }

    /**
     * Get availability data for a property within a date range
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function get_availability($request) {
        $property_id = $request->get_param('property_id');
        $from_date = $request->get_param('from');
        $to_date = $request->get_param('to');

        // Validate property exists
        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'property' || $property->post_status !== 'publish') {
            return new WP_Error(
                'property_not_found',
                __('Property not found or not available', 'minpaku-suite'),
                ['status' => 404]
            );
        }

        // Validate date range
        $validation_error = $this->validate_date_range($from_date, $to_date);
        if (is_wp_error($validation_error)) {
            return $validation_error;
        }

        try {
            // Get availability data
            $availability_data = $this->get_property_availability($property_id, $from_date, $to_date);

            // Prepare response
            $response_data = [
                'property_id' => $property_id,
                'property_title' => $property->post_title,
                'from' => $from_date,
                'to' => $to_date,
                'dates' => $availability_data['dates'],
                'summary' => [
                    'total_days' => count($availability_data['dates']),
                    'available_days' => $availability_data['available_count'],
                    'booked_days' => $availability_data['booked_count'],
                    'blocked_days' => $availability_data['blocked_count']
                ],
                'meta' => [
                    'generated_at' => current_time('c'),
                    'timezone' => wp_timezone_string(),
                    'cache_ttl' => 300 // 5 minutes
                ]
            ];

            $response = new WP_REST_Response($response_data, 200);

            // Add caching headers
            $response->set_headers([
                'Cache-Control' => 'public, max-age=300', // 5 minutes
                'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + 300),
                'X-API-Version' => 'v1'
            ]);

            return $response;

        } catch (Exception $e) {
            return new WP_Error(
                'availability_error',
                __('Unable to retrieve availability data', 'minpaku-suite'),
                ['status' => 500, 'details' => $e->getMessage()]
            );
        }
    }

    /**
     * Check if a given request has access to get availability
     *
     * @param WP_REST_Request $request Full data about the request
     * @return bool|WP_Error True if the request has read access, WP_Error object otherwise
     */
    public function get_availability_permissions_check($request) {
        // Public API - no authentication required
        return true;
    }

    /**
     * Get the query params for availability endpoint
     *
     * @return array
     */
    public function get_availability_params() {
        return [
            'property_id' => [
                'description' => __('Property ID to get availability for', 'minpaku-suite'),
                'type' => 'integer',
                'required' => true,
                'minimum' => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                }
            ],
            'from' => [
                'description' => __('Start date in Y-m-d format', 'minpaku-suite'),
                'type' => 'string',
                'required' => true,
                'pattern' => '^\d{4}-\d{2}-\d{2}$',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [$this, 'validate_date']
            ],
            'to' => [
                'description' => __('End date in Y-m-d format', 'minpaku-suite'),
                'type' => 'string',
                'required' => true,
                'pattern' => '^\d{4}-\d{2}-\d{2}$',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [$this, 'validate_date']
            ]
        ];
    }

    /**
     * Validate date format
     *
     * @param string $date Date string to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_date($date) {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date;
    }

    /**
     * Validate date range
     *
     * @param string $from_date Start date
     * @param string $to_date End date
     * @return true|WP_Error True if valid, WP_Error if not
     */
    private function validate_date_range($from_date, $to_date) {
        $from = new DateTime($from_date);
        $to = new DateTime($to_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);

        // Check date order
        if ($from >= $to) {
            return new WP_Error(
                'invalid_date_range',
                __('End date must be after start date', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        // Check if dates are not too far in the past
        $min_date = clone $today;
        $min_date->modify('-30 days');
        if ($from < $min_date) {
            return new WP_Error(
                'date_too_old',
                __('Start date cannot be more than 30 days in the past', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        // Check if date range is not too large
        $diff = $from->diff($to);
        if ($diff->days > 365) {
            return new WP_Error(
                'date_range_too_large',
                __('Date range cannot exceed 365 days', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Get property availability data for the specified date range
     *
     * @param int $property_id Property ID
     * @param string $from_date Start date (Y-m-d)
     * @param string $to_date End date (Y-m-d)
     * @return array Availability data
     */
    private function get_property_availability($property_id, $from_date, $to_date) {
        $dates = [];
        $available_count = 0;
        $booked_count = 0;
        $blocked_count = 0;

        // Get existing reservations for the property
        $reservations = $this->get_property_reservations($property_id, $from_date, $to_date);

        // Get blocked dates (maintenance, owner use, etc.)
        $blocked_dates = $this->get_blocked_dates($property_id, $from_date, $to_date);

        // Generate availability for each date in range
        $current_date = new DateTime($from_date);
        $end_date = new DateTime($to_date);

        while ($current_date < $end_date) {
            $date_str = $current_date->format('Y-m-d');
            $status = 'available';

            // Check if date is blocked
            if (in_array($date_str, $blocked_dates)) {
                $status = 'blocked';
                $blocked_count++;
            }
            // Check if date has a reservation
            else if ($this->is_date_reserved($date_str, $reservations)) {
                $status = 'booked';
                $booked_count++;
            }
            // Check if property allows bookings on this date (rules)
            else if (!$this->is_date_bookable($property_id, $date_str)) {
                $status = 'restricted';
                $blocked_count++;
            }
            else {
                $available_count++;
            }

            $dates[$date_str] = $status;
            $current_date->modify('+1 day');
        }

        return [
            'dates' => $dates,
            'available_count' => $available_count,
            'booked_count' => $booked_count,
            'blocked_count' => $blocked_count
        ];
    }

    /**
     * Get reservations for a property within date range
     *
     * @param int $property_id Property ID
     * @param string $from_date Start date
     * @param string $to_date End date
     * @return array Array of reservations
     */
    private function get_property_reservations($property_id, $from_date, $to_date) {
        $reservations = get_posts([
            'post_type' => 'reservation',
            'post_status' => ['confirmed', 'pending', 'checked_in'],
            'numberposts' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'property_id',
                    'value' => $property_id,
                    'compare' => '='
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => 'checkin_date',
                        'value' => [$from_date, $to_date],
                        'compare' => 'BETWEEN',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => 'checkout_date',
                        'value' => [$from_date, $to_date],
                        'compare' => 'BETWEEN',
                        'type' => 'DATE'
                    ],
                    [
                        'relation' => 'AND',
                        [
                            'key' => 'checkin_date',
                            'value' => $from_date,
                            'compare' => '<=',
                            'type' => 'DATE'
                        ],
                        [
                            'key' => 'checkout_date',
                            'value' => $to_date,
                            'compare' => '>=',
                            'type' => 'DATE'
                        ]
                    ]
                ]
            ]
        ]);

        $reservation_data = [];
        foreach ($reservations as $reservation) {
            $checkin = get_post_meta($reservation->ID, 'checkin_date', true);
            $checkout = get_post_meta($reservation->ID, 'checkout_date', true);

            if ($checkin && $checkout) {
                $reservation_data[] = [
                    'id' => $reservation->ID,
                    'checkin' => $checkin,
                    'checkout' => $checkout,
                    'status' => $reservation->post_status
                ];
            }
        }

        return $reservation_data;
    }

    /**
     * Get blocked dates for a property
     *
     * @param int $property_id Property ID
     * @param string $from_date Start date
     * @param string $to_date End date
     * @return array Array of blocked date strings
     */
    private function get_blocked_dates($property_id, $from_date, $to_date) {
        // Get manually blocked dates
        $blocked_dates = get_post_meta($property_id, 'blocked_dates', false);

        // Filter dates within range
        $filtered_dates = [];
        foreach ($blocked_dates as $date) {
            if ($date >= $from_date && $date < $to_date) {
                $filtered_dates[] = $date;
            }
        }

        return $filtered_dates;
    }

    /**
     * Check if a specific date is reserved
     *
     * @param string $date Date to check (Y-m-d)
     * @param array $reservations Array of reservations
     * @return bool True if reserved, false otherwise
     */
    private function is_date_reserved($date, $reservations) {
        $check_date = new DateTime($date);

        foreach ($reservations as $reservation) {
            $checkin = new DateTime($reservation['checkin']);
            $checkout = new DateTime($reservation['checkout']);

            // Date is reserved if it's between checkin (inclusive) and checkout (exclusive)
            if ($check_date >= $checkin && $check_date < $checkout) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a date is bookable according to property rules
     *
     * @param int $property_id Property ID
     * @param string $date Date to check
     * @return bool True if bookable, false otherwise
     */
    private function is_date_bookable($property_id, $date) {
        // Check day-of-week restrictions
        $allowed_checkin_days = get_post_meta($property_id, 'allowed_checkin_days', true);
        if (!empty($allowed_checkin_days)) {
            $day_of_week = date('w', strtotime($date)); // 0 = Sunday, 6 = Saturday
            if (!in_array($day_of_week, $allowed_checkin_days)) {
                return false;
            }
        }

        // Additional rule checks can be added here
        // For now, default to bookable
        return true;
    }
}