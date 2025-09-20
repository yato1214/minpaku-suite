<?php
/**
 * Quote API Controller
 * Handles pricing quote requests with rate limiting and caching
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/ResponseCache.php';
require_once __DIR__ . '/ApiKeyManager.php';

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
     * Rate limiter instance
     */
    private $rate_limiter;

    /**
     * Response cache instance
     */
    private $cache;

    /**
     * API key manager instance
     */
    private $api_key_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->rate_limiter = new RateLimiter();
        $this->cache = new ResponseCache();
        $this->api_key_manager = new ApiKeyManager();
    }

    /**
     * Register the routes for the objects of the controller
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'calculate_quote'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => $this->get_quote_params(),
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<property_id>\d+)', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'calculate_property_quote'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => $this->get_property_quote_params(),
            ],
        ]);
    }

    /**
     * Calculate quote for booking request
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function calculate_quote($request) {
        // Apply rate limiting
        $rate_limit_result = $this->applyRateLimit('api:quote', $request);
        if (is_wp_error($rate_limit_result)) {
            return $rate_limit_result;
        }

        $params = $request->get_params();
        $property_id = (int) $params['property_id'];
        $checkin_date = sanitize_text_field($params['checkin_date']);
        $checkout_date = sanitize_text_field($params['checkout_date']);
        $guests = $this->sanitizeGuests($params['guests'] ?? []);

        // Validate required parameters
        $validation_error = $this->validateQuoteParams($property_id, $checkin_date, $checkout_date, $guests);
        if (is_wp_error($validation_error)) {
            return $validation_error;
        }

        // Generate cache key
        $cache_key = ResponseCache::quoteKey(
            $property_id,
            $checkin_date,
            $checkout_date,
            $guests,
            array_filter($params, function($key) {
                return !in_array($key, ['property_id', 'checkin_date', 'checkout_date', 'guests']);
            }, ARRAY_FILTER_USE_KEY)
        );

        // Try to get from cache
        $cached_response = $this->cache->get($cache_key);
        if ($cached_response !== null) {
            $response = new WP_REST_Response($cached_response, 200);
            $response->set_headers(['X-Minpaku-Cache' => 'HIT']);
            return $response;
        }

        // Calculate quote
        $quote_data = $this->calculateQuote($property_id, $checkin_date, $checkout_date, $guests, $params);

        // Cache the response
        $cache_ttl = $this->getCacheTtl('quote');
        $this->cache->put($cache_key, $quote_data, $cache_ttl, [
            'property_id' => $property_id,
            'dates' => $checkin_date . ':' . $checkout_date,
            'guests' => $guests
        ]);

        // Record rate limit usage
        $this->rate_limiter->record('api:quote');

        $response = new WP_REST_Response($quote_data, 200);
        $response->set_headers(['X-Minpaku-Cache' => 'MISS']);

        return $response;
    }

    /**
     * Calculate quote for specific property (alternative endpoint)
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function calculate_property_quote($request) {
        // Apply rate limiting
        $rate_limit_result = $this->applyRateLimit('api:quote', $request);
        if (is_wp_error($rate_limit_result)) {
            return $rate_limit_result;
        }

        $property_id = (int) $request->get_param('property_id');
        $params = $request->get_params();
        $checkin_date = sanitize_text_field($params['checkin_date']);
        $checkout_date = sanitize_text_field($params['checkout_date']);
        $guests = $this->sanitizeGuests($params['guests'] ?? []);

        // Validate required parameters
        $validation_error = $this->validateQuoteParams($property_id, $checkin_date, $checkout_date, $guests);
        if (is_wp_error($validation_error)) {
            return $validation_error;
        }

        // Generate cache key
        $cache_key = ResponseCache::quoteKey(
            $property_id,
            $checkin_date,
            $checkout_date,
            $guests,
            array_filter($params, function($key) {
                return !in_array($key, ['property_id', 'checkin_date', 'checkout_date', 'guests']);
            }, ARRAY_FILTER_USE_KEY)
        );

        // Try to get from cache
        $cached_response = $this->cache->get($cache_key);
        if ($cached_response !== null) {
            $response = new WP_REST_Response($cached_response, 200);
            $response->set_headers(['X-Minpaku-Cache' => 'HIT']);
            return $response;
        }

        // Calculate quote
        $quote_data = $this->calculateQuote($property_id, $checkin_date, $checkout_date, $guests, $params);

        // Cache the response
        $cache_ttl = $this->getCacheTtl('quote');
        $this->cache->put($cache_key, $quote_data, $cache_ttl, [
            'property_id' => $property_id,
            'dates' => $checkin_date . ':' . $checkout_date,
            'guests' => $guests
        ]);

        // Record rate limit usage
        $this->rate_limiter->record('api:quote');

        $response = new WP_REST_Response($quote_data, 200);
        $response->set_headers(['X-Minpaku-Cache' => 'MISS']);

        return $response;
    }

    /**
     * Check permissions for API access
     *
     * @param WP_REST_Request $request Full data about the request
     * @return bool|WP_Error True if the request has access, WP_Error object otherwise
     */
    public function check_permissions($request) {
        // Check if API key is provided
        $api_key = $this->getApiKeyFromRequest();
        if ($api_key) {
            $key_data = $this->api_key_manager->validateKey($api_key);
            if (!$key_data) {
                return new WP_Error(
                    'invalid_api_key',
                    __('Invalid API key', 'minpaku-suite'),
                    ['status' => 401]
                );
            }

            // Check permissions
            if (!$this->api_key_manager->hasPermission($key_data, 'read:quote')) {
                return new WP_Error(
                    'insufficient_permissions',
                    __('API key does not have permission to read quotes', 'minpaku-suite'),
                    ['status' => 403]
                );
            }

            return true;
        }

        // If no API key, allow public access (subject to rate limiting)
        return true;
    }

    /**
     * Apply rate limiting to request
     *
     * @param string $bucket Rate limit bucket
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if allowed, WP_Error if rate limited
     */
    private function applyRateLimit($bucket, $request) {
        $api_key = $this->getApiKeyFromRequest();
        $rate_key = $api_key ? 'apikey:' . $api_key : null;

        if (!$this->rate_limiter->allow($bucket, null, null, $rate_key)) {
            $retry_after = $this->rate_limiter->getRetryAfter($bucket, $rate_key);

            return new WP_Error(
                'rate_limit_exceeded',
                __('Rate limit exceeded. Please try again later.', 'minpaku-suite'),
                [
                    'status' => 429,
                    'headers' => [
                        'Retry-After' => $retry_after,
                        'X-RateLimit-Bucket' => $bucket,
                        'X-RateLimit-Reset' => time() + $retry_after
                    ]
                ]
            );
        }

        return true;
    }

    /**
     * Validate quote parameters
     *
     * @param int $property_id Property ID
     * @param string $checkin_date Check-in date
     * @param string $checkout_date Check-out date
     * @param array $guests Guest information
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validateQuoteParams($property_id, $checkin_date, $checkout_date, $guests) {
        // Validate property exists
        if (!get_post($property_id) || get_post_type($property_id) !== 'property') {
            return new WP_Error(
                'property_not_found',
                __('Property not found', 'minpaku-suite'),
                ['status' => 404]
            );
        }

        // Validate dates
        if (empty($checkin_date) || empty($checkout_date)) {
            return new WP_Error(
                'missing_dates',
                __('Check-in and check-out dates are required', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        $checkin = new DateTime($checkin_date);
        $checkout = new DateTime($checkout_date);

        if ($checkin >= $checkout) {
            return new WP_Error(
                'invalid_date_range',
                __('Check-out date must be after check-in date', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        // Validate guests
        if (empty($guests) || !isset($guests['adults']) || $guests['adults'] < 1) {
            return new WP_Error(
                'invalid_guests',
                __('At least one adult guest is required', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Calculate quote for booking
     *
     * @param int $property_id Property ID
     * @param string $checkin_date Check-in date
     * @param string $checkout_date Check-out date
     * @param array $guests Guest information
     * @param array $params Additional parameters
     * @return array Quote data
     */
    private function calculateQuote($property_id, $checkin_date, $checkout_date, $guests, $params) {
        $checkin = new DateTime($checkin_date);
        $checkout = new DateTime($checkout_date);
        $nights = $checkin->diff($checkout)->days;

        // Get base pricing
        $base_price_per_night = $this->getBasePricePerNight($property_id);
        $subtotal = $base_price_per_night * $nights;

        // Apply guest charges
        $guest_fees = $this->calculateGuestFees($property_id, $guests, $nights);
        $subtotal += $guest_fees['total'];

        // Apply seasonal adjustments
        $seasonal_adjustments = $this->calculateSeasonalAdjustments($property_id, $checkin_date, $checkout_date);
        $subtotal += $seasonal_adjustments['total'];

        // Calculate taxes
        $tax_rate = $this->getTaxRate($property_id);
        $taxes = $subtotal * ($tax_rate / 100);

        // Calculate fees
        $cleaning_fee = $this->getCleaningFee($property_id);
        $service_fee = $this->calculateServiceFee($subtotal);
        $total_fees = $cleaning_fee + $service_fee;

        // Calculate total
        $total = $subtotal + $taxes + $total_fees;

        return [
            'property_id' => $property_id,
            'property_name' => get_the_title($property_id),
            'booking_details' => [
                'checkin_date' => $checkin_date,
                'checkout_date' => $checkout_date,
                'nights' => $nights,
                'guests' => $guests
            ],
            'pricing' => [
                'base_price_per_night' => $base_price_per_night,
                'nights' => $nights,
                'subtotal' => $subtotal,
                'guest_fees' => $guest_fees,
                'seasonal_adjustments' => $seasonal_adjustments,
                'taxes' => [
                    'rate' => $tax_rate,
                    'amount' => $taxes
                ],
                'fees' => [
                    'cleaning_fee' => $cleaning_fee,
                    'service_fee' => $service_fee,
                    'total_fees' => $total_fees
                ],
                'total' => $total,
                'currency' => $this->getCurrency()
            ],
            'availability' => [
                'available' => $this->checkAvailability($property_id, $checkin_date, $checkout_date),
                'instant_booking' => $this->isInstantBookingEnabled($property_id),
                'min_stay_met' => $nights >= $this->getMinStay($property_id)
            ],
            'policies' => [
                'cancellation_policy' => $this->getCancellationPolicy($property_id),
                'house_rules' => $this->getHouseRules($property_id)
            ],
            'generated_at' => current_time('c'),
            'valid_until' => date('c', time() + 1800) // Valid for 30 minutes
        ];
    }

    /**
     * Get base price per night for property
     *
     * @param int $property_id Property ID
     * @return float Base price per night
     */
    private function getBasePricePerNight($property_id) {
        $base_price = get_post_meta($property_id, 'base_price', true);
        return $base_price ? (float) $base_price : 0.0;
    }

    /**
     * Calculate guest fees
     *
     * @param int $property_id Property ID
     * @param array $guests Guest information
     * @param int $nights Number of nights
     * @return array Guest fees breakdown
     */
    private function calculateGuestFees($property_id, $guests, $nights) {
        $adult_fee = get_post_meta($property_id, 'extra_adult_fee', true) ?: 0;
        $child_fee = get_post_meta($property_id, 'extra_child_fee', true) ?: 0;
        $included_adults = get_post_meta($property_id, 'included_adults', true) ?: 2;

        $extra_adults = max(0, $guests['adults'] - $included_adults);
        $children = $guests['children'] ?? 0;

        $adult_fees = $extra_adults * $adult_fee * $nights;
        $child_fees = $children * $child_fee * $nights;

        return [
            'extra_adults' => $extra_adults,
            'adult_fee_per_night' => $adult_fee,
            'adult_fees' => $adult_fees,
            'children' => $children,
            'child_fee_per_night' => $child_fee,
            'child_fees' => $child_fees,
            'total' => $adult_fees + $child_fees
        ];
    }

    /**
     * Calculate seasonal adjustments
     *
     * @param int $property_id Property ID
     * @param string $checkin_date Check-in date
     * @param string $checkout_date Check-out date
     * @return array Seasonal adjustments
     */
    private function calculateSeasonalAdjustments($property_id, $checkin_date, $checkout_date) {
        // Simplified implementation - would normally check seasonal rules
        return [
            'adjustments' => [],
            'total' => 0.0
        ];
    }

    /**
     * Get tax rate for property
     *
     * @param int $property_id Property ID
     * @return float Tax rate percentage
     */
    private function getTaxRate($property_id) {
        $tax_rate = get_post_meta($property_id, 'tax_rate', true);
        return $tax_rate ? (float) $tax_rate : 10.0; // Default 10%
    }

    /**
     * Get cleaning fee for property
     *
     * @param int $property_id Property ID
     * @return float Cleaning fee
     */
    private function getCleaningFee($property_id) {
        $cleaning_fee = get_post_meta($property_id, 'cleaning_fee', true);
        return $cleaning_fee ? (float) $cleaning_fee : 0.0;
    }

    /**
     * Calculate service fee
     *
     * @param float $subtotal Subtotal amount
     * @return float Service fee
     */
    private function calculateServiceFee($subtotal) {
        $service_fee_rate = get_option('minpaku_service_fee_rate', 3.0);
        return $subtotal * ($service_fee_rate / 100);
    }

    /**
     * Check availability for dates
     *
     * @param int $property_id Property ID
     * @param string $checkin_date Check-in date
     * @param string $checkout_date Check-out date
     * @return bool True if available
     */
    private function checkAvailability($property_id, $checkin_date, $checkout_date) {
        // Simplified check - would normally query booking system
        return true;
    }

    /**
     * Check if instant booking is enabled
     *
     * @param int $property_id Property ID
     * @return bool True if instant booking enabled
     */
    private function isInstantBookingEnabled($property_id) {
        return (bool) get_post_meta($property_id, 'instant_booking', true);
    }

    /**
     * Get minimum stay requirement
     *
     * @param int $property_id Property ID
     * @return int Minimum stay in nights
     */
    private function getMinStay($property_id) {
        return (int) get_post_meta($property_id, 'min_stay', true) ?: 1;
    }

    /**
     * Get cancellation policy
     *
     * @param int $property_id Property ID
     * @return string Cancellation policy
     */
    private function getCancellationPolicy($property_id) {
        return get_post_meta($property_id, 'cancellation_policy', true) ?: 'moderate';
    }

    /**
     * Get house rules
     *
     * @param int $property_id Property ID
     * @return array House rules
     */
    private function getHouseRules($property_id) {
        $rules = get_post_meta($property_id, 'house_rules', true);
        return $rules ? explode("\n", $rules) : [];
    }

    /**
     * Get currency code
     *
     * @return string Currency code
     */
    private function getCurrency() {
        return get_option('minpaku_currency', 'JPY');
    }

    /**
     * Get API key from request headers
     *
     * @return string|null API key if present
     */
    private function getApiKeyFromRequest() {
        $headers = getallheaders();
        if (!$headers) {
            return null;
        }

        $possible_headers = [
            'X-Minpaku-Api-Key',
            'X-API-Key',
            'Authorization'
        ];

        foreach ($possible_headers as $header) {
            if (isset($headers[$header])) {
                $value = $headers[$header];

                // Handle Authorization: Bearer <key>
                if ($header === 'Authorization' && strpos($value, 'Bearer ') === 0) {
                    return substr($value, 7);
                }

                return $value;
            }
        }

        return null;
    }

    /**
     * Get cache TTL for quote data
     *
     * @param string $type Cache type
     * @return int TTL in seconds
     */
    private function getCacheTtl($type) {
        $settings = get_option('minpaku_api_settings', []);
        return $settings['cache_ttl'][$type] ?? 30;
    }

    /**
     * Sanitize guest information
     *
     * @param mixed $guests Guest data
     * @return array Sanitized guest data
     */
    private function sanitizeGuests($guests) {
        if (!is_array($guests)) {
            return ['adults' => 1, 'children' => 0];
        }

        return [
            'adults' => max(1, (int) ($guests['adults'] ?? 1)),
            'children' => max(0, (int) ($guests['children'] ?? 0)),
            'infants' => max(0, (int) ($guests['infants'] ?? 0))
        ];
    }

    /**
     * Get the query params for quote endpoint
     *
     * @return array
     */
    public function get_quote_params() {
        return [
            'property_id' => [
                'description' => __('Property ID', 'minpaku-suite'),
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint'
            ],
            'checkin_date' => [
                'description' => __('Check-in date (Y-m-d format)', 'minpaku-suite'),
                'type' => 'string',
                'format' => 'date',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'checkout_date' => [
                'description' => __('Check-out date (Y-m-d format)', 'minpaku-suite'),
                'type' => 'string',
                'format' => 'date',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'guests' => [
                'description' => __('Guest information (adults, children, infants)', 'minpaku-suite'),
                'type' => 'object',
                'required' => true,
                'properties' => [
                    'adults' => ['type' => 'integer', 'minimum' => 1],
                    'children' => ['type' => 'integer', 'minimum' => 0],
                    'infants' => ['type' => 'integer', 'minimum' => 0]
                ],
                'sanitize_callback' => [$this, 'sanitizeGuests']
            ]
        ];
    }

    /**
     * Get the query params for property quote endpoint
     *
     * @return array
     */
    public function get_property_quote_params() {
        return [
            'checkin_date' => [
                'description' => __('Check-in date (Y-m-d format)', 'minpaku-suite'),
                'type' => 'string',
                'format' => 'date',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'checkout_date' => [
                'description' => __('Check-out date (Y-m-d format)', 'minpaku-suite'),
                'type' => 'string',
                'format' => 'date',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'guests' => [
                'description' => __('Guest information (adults, children, infants)', 'minpaku-suite'),
                'type' => 'object',
                'required' => true,
                'properties' => [
                    'adults' => ['type' => 'integer', 'minimum' => 1],
                    'children' => ['type' => 'integer', 'minimum' => 0],
                    'infants' => ['type' => 'integer', 'minimum' => 0]
                ],
                'sanitize_callback' => [$this, 'sanitizeGuests']
            ]
        ];
    }
}