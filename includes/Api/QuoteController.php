<?php
/**
 * Quote API Controller
 * Provides read-only quote calculations for external systems
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class QuoteController extends WP_REST_Controller {

    /**
     * Namespace for this controller's routes
     */
    protected $namespace = 'minpaku/v1';

    /**
     * Rest base for this controller
     */
    protected $rest_base = 'quote';

    /**
     * Rate resolver service
     */
    private $rate_resolver;

    /**
     * Rule engine service
     */
    private $rule_engine;

    /**
     * Constructor
     */
    public function __construct() {
        if (class_exists('RateResolver')) {
            $this->rate_resolver = new RateResolver();
        }

        if (class_exists('RuleEngine')) {
            $this->rule_engine = new RuleEngine();
        }
    }

    /**
     * Register the routes for the objects of the controller
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_quote'],
                'permission_callback' => [$this, 'get_quote_permissions_check'],
                'args' => $this->get_quote_params(),
            ],
        ]);
    }

    /**
     * Get quote calculation for a property and booking details
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function get_quote($request) {
        $property_id = $request->get_param('property_id');
        $checkin = $request->get_param('checkin');
        $checkout = $request->get_param('checkout');
        $adults = $request->get_param('adults') ?: 1;
        $children = $request->get_param('children') ?: 0;

        // Validate property exists
        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'property' || $property->post_status !== 'publish') {
            return new WP_Error(
                'property_not_found',
                __('Property not found or not available', 'minpaku-suite'),
                ['status' => 404]
            );
        }

        // Validate booking dates
        $validation_error = $this->validate_booking_dates($checkin, $checkout);
        if (is_wp_error($validation_error)) {
            return $validation_error;
        }

        // Validate guest count
        $guest_validation = $this->validate_guest_count($property_id, $adults, $children);
        if (is_wp_error($guest_validation)) {
            return $guest_validation;
        }

        try {
            // Check availability for the requested dates
            $availability_check = $this->check_availability($property_id, $checkin, $checkout);
            if (is_wp_error($availability_check)) {
                return $availability_check;
            }

            // Validate booking rules
            if ($this->rule_engine) {
                $booking_data = [
                    'property_id' => $property_id,
                    'checkin' => $checkin,
                    'checkout' => $checkout,
                    'guests' => $adults + $children,
                    'adults' => $adults,
                    'children' => $children
                ];

                $rule_validation = $this->rule_engine->validate_booking($booking_data);
                if (!$rule_validation['is_valid']) {
                    return new WP_Error(
                        'booking_rules_violation',
                        __('Booking does not meet property requirements', 'minpaku-suite'),
                        [
                            'status' => 422,
                            'errors' => $rule_validation['errors']
                        ]
                    );
                }
            }

            // Calculate quote
            $quote_data = $this->calculate_quote($property_id, $checkin, $checkout, $adults, $children);

            // Prepare response
            $response_data = [
                'property_id' => $property_id,
                'property_title' => $property->post_title,
                'booking' => [
                    'checkin' => $checkin,
                    'checkout' => $checkout,
                    'nights' => $this->calculate_nights($checkin, $checkout),
                    'adults' => $adults,
                    'children' => $children,
                    'total_guests' => $adults + $children
                ],
                'pricing' => [
                    'base' => $quote_data['base_total'],
                    'taxes' => $quote_data['taxes_total'],
                    'fees' => $quote_data['fees_total'],
                    'adjustments' => $quote_data['adjustments_total'],
                    'total' => $quote_data['grand_total'],
                    'currency' => $quote_data['currency']
                ],
                'breakdown' => $quote_data['breakdown'],
                'meta' => [
                    'generated_at' => current_time('c'),
                    'timezone' => wp_timezone_string(),
                    'cache_ttl' => 60, // 1 minute for quotes
                    'valid_until' => gmdate('c', time() + 3600) // 1 hour validity
                ]
            ];

            $response = new WP_REST_Response($response_data, 200);

            // Add caching headers (shorter cache for quotes)
            $response->set_headers([
                'Cache-Control' => 'private, max-age=60', // 1 minute
                'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + 60),
                'X-API-Version' => 'v1'
            ]);

            return $response;

        } catch (Exception $e) {
            return new WP_Error(
                'quote_calculation_error',
                __('Unable to calculate quote', 'minpaku-suite'),
                ['status' => 500, 'details' => $e->getMessage()]
            );
        }
    }

    /**
     * Check if a given request has access to get quotes
     *
     * @param WP_REST_Request $request Full data about the request
     * @return bool|WP_Error True if the request has read access, WP_Error object otherwise
     */
    public function get_quote_permissions_check($request) {
        // Public API - no authentication required
        return true;
    }

    /**
     * Get the query params for quote endpoint
     *
     * @return array
     */
    public function get_quote_params() {
        return [
            'property_id' => [
                'description' => __('Property ID to get quote for', 'minpaku-suite'),
                'type' => 'integer',
                'required' => true,
                'minimum' => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                }
            ],
            'checkin' => [
                'description' => __('Check-in date in Y-m-d format', 'minpaku-suite'),
                'type' => 'string',
                'required' => true,
                'pattern' => '^\d{4}-\d{2}-\d{2}$',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [$this, 'validate_date']
            ],
            'checkout' => [
                'description' => __('Check-out date in Y-m-d format', 'minpaku-suite'),
                'type' => 'string',
                'required' => true,
                'pattern' => '^\d{4}-\d{2}-\d{2}$',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [$this, 'validate_date']
            ],
            'adults' => [
                'description' => __('Number of adult guests', 'minpaku-suite'),
                'type' => 'integer',
                'required' => false,
                'default' => 1,
                'minimum' => 1,
                'maximum' => 20,
                'sanitize_callback' => 'absint'
            ],
            'children' => [
                'description' => __('Number of child guests', 'minpaku-suite'),
                'type' => 'integer',
                'required' => false,
                'default' => 0,
                'minimum' => 0,
                'maximum' => 10,
                'sanitize_callback' => 'absint'
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
     * Validate booking dates
     *
     * @param string $checkin Check-in date
     * @param string $checkout Check-out date
     * @return true|WP_Error True if valid, WP_Error if not
     */
    private function validate_booking_dates($checkin, $checkout) {
        $checkin_date = new DateTime($checkin);
        $checkout_date = new DateTime($checkout);
        $today = new DateTime();
        $today->setTime(0, 0, 0);

        // Check date order
        if ($checkin_date >= $checkout_date) {
            return new WP_Error(
                'invalid_checkout_date',
                __('Check-out date must be after check-in date', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        // Check if check-in is not in the past
        if ($checkin_date < $today) {
            return new WP_Error(
                'past_checkin_date',
                __('Check-in date cannot be in the past', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        // Check maximum advance booking (e.g., 2 years)
        $max_advance = clone $today;
        $max_advance->modify('+2 years');
        if ($checkin_date > $max_advance) {
            return new WP_Error(
                'checkin_too_far',
                __('Check-in date cannot be more than 2 years in advance', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        // Check maximum stay length
        $diff = $checkin_date->diff($checkout_date);
        if ($diff->days > 365) {
            return new WP_Error(
                'stay_too_long',
                __('Stay duration cannot exceed 365 days', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Validate guest count against property limits
     *
     * @param int $property_id Property ID
     * @param int $adults Number of adults
     * @param int $children Number of children
     * @return true|WP_Error True if valid, WP_Error if not
     */
    private function validate_guest_count($property_id, $adults, $children) {
        $max_guests = get_post_meta($property_id, 'max_guests', true) ?: 8;
        $total_guests = $adults + $children;

        if ($total_guests > $max_guests) {
            return new WP_Error(
                'too_many_guests',
                sprintf(
                    __('Total guests (%d) exceeds property maximum (%d)', 'minpaku-suite'),
                    $total_guests,
                    $max_guests
                ),
                ['status' => 400]
            );
        }

        if ($adults < 1) {
            return new WP_Error(
                'no_adults',
                __('At least one adult guest is required', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Check if property is available for the requested dates
     *
     * @param int $property_id Property ID
     * @param string $checkin Check-in date
     * @param string $checkout Check-out date
     * @return true|WP_Error True if available, WP_Error if not
     */
    private function check_availability($property_id, $checkin, $checkout) {
        // Get overlapping reservations
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
                        'relation' => 'AND',
                        [
                            'key' => 'checkin_date',
                            'value' => $checkin,
                            'compare' => '<',
                            'type' => 'DATE'
                        ],
                        [
                            'key' => 'checkout_date',
                            'value' => $checkin,
                            'compare' => '>',
                            'type' => 'DATE'
                        ]
                    ],
                    [
                        'relation' => 'AND',
                        [
                            'key' => 'checkin_date',
                            'value' => $checkout,
                            'compare' => '<',
                            'type' => 'DATE'
                        ],
                        [
                            'key' => 'checkout_date',
                            'value' => $checkout,
                            'compare' => '>',
                            'type' => 'DATE'
                        ]
                    ],
                    [
                        'relation' => 'AND',
                        [
                            'key' => 'checkin_date',
                            'value' => $checkin,
                            'compare' => '>=',
                            'type' => 'DATE'
                        ],
                        [
                            'key' => 'checkin_date',
                            'value' => $checkout,
                            'compare' => '<',
                            'type' => 'DATE'
                        ]
                    ]
                ]
            ]
        ]);

        if (!empty($reservations)) {
            return new WP_Error(
                'property_not_available',
                __('Property is not available for the selected dates', 'minpaku-suite'),
                ['status' => 422]
            );
        }

        return true;
    }

    /**
     * Calculate quote for the booking
     *
     * @param int $property_id Property ID
     * @param string $checkin Check-in date
     * @param string $checkout Check-out date
     * @param int $adults Number of adults
     * @param int $children Number of children
     * @return array Quote calculation results
     */
    private function calculate_quote($property_id, $checkin, $checkout, $adults, $children) {
        $base_rate = floatval(get_post_meta($property_id, 'base_rate', true)) ?: 100.00;
        $currency = get_post_meta($property_id, 'currency', true) ?: 'JPY';
        $tax_rate = floatval(get_post_meta($property_id, 'tax_rate', true)) ?: 10.0;

        $nights = $this->calculate_nights($checkin, $checkout);
        $total_guests = $adults + $children;

        // Use RateResolver if available
        if ($this->rate_resolver) {
            $booking_data = [
                'property_id' => $property_id,
                'checkin' => $checkin,
                'checkout' => $checkout,
                'guests' => $total_guests,
                'adults' => $adults,
                'children' => $children
            ];

            $rate_result = $this->rate_resolver->resolveRate($booking_data);

            return [
                'base_total' => $rate_result['base_rate'] * $nights,
                'taxes_total' => $rate_result['taxes_total'] ?? 0,
                'fees_total' => $rate_result['fees_total'] ?? 0,
                'adjustments_total' => $rate_result['adjustments_total'] ?? 0,
                'grand_total' => $rate_result['total_rate'],
                'currency' => $currency,
                'breakdown' => $rate_result['breakdown']
            ];
        }

        // Fallback calculation without RateResolver
        $accommodation_total = $base_rate * $nights;
        $cleaning_fee = 5000; // Fixed cleaning fee
        $tax_amount = ($accommodation_total + $cleaning_fee) * ($tax_rate / 100);
        $grand_total = $accommodation_total + $cleaning_fee + $tax_amount;

        return [
            'base_total' => $accommodation_total,
            'taxes_total' => $tax_amount,
            'fees_total' => $cleaning_fee,
            'adjustments_total' => 0,
            'grand_total' => $grand_total,
            'currency' => $currency,
            'breakdown' => [
                'accommodation' => [
                    [
                        'label' => sprintf(__('%d nights at %s %s', 'minpaku-suite'), $nights, number_format($base_rate), $currency),
                        'amount' => $accommodation_total,
                        'details' => sprintf(__('Base rate: %s per night', 'minpaku-suite'), number_format($base_rate))
                    ]
                ],
                'fees' => [
                    [
                        'label' => __('Cleaning fee', 'minpaku-suite'),
                        'amount' => $cleaning_fee,
                        'details' => __('One-time cleaning fee', 'minpaku-suite')
                    ]
                ],
                'taxes' => [
                    [
                        'label' => sprintf(__('Tax (%s%%)', 'minpaku-suite'), $tax_rate),
                        'amount' => $tax_amount,
                        'details' => __('Local accommodation tax', 'minpaku-suite')
                    ]
                ]
            ]
        ];
    }

    /**
     * Calculate number of nights between two dates
     *
     * @param string $checkin Check-in date
     * @param string $checkout Check-out date
     * @return int Number of nights
     */
    private function calculate_nights($checkin, $checkout) {
        $checkin_date = new DateTime($checkin);
        $checkout_date = new DateTime($checkout);
        $diff = $checkin_date->diff($checkout_date);
        return $diff->days;
    }
}