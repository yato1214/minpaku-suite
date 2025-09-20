<?php
/**
 * Availability API Controller
 * Handles property availability requests with rate limiting and caching
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/ResponseCache.php';
require_once __DIR__ . '/ApiKeyManager.php';

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
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_availability'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => $this->get_availability_params(),
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<property_id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_property_availability'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => $this->get_property_availability_params(),
            ],
        ]);
    }

    /**
     * Get availability for multiple properties
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function get_availability($request) {
        // Apply rate limiting
        $rate_limit_result = $this->applyRateLimit('api:availability', $request);
        if (is_wp_error($rate_limit_result)) {
            return $rate_limit_result;
        }

        $params = $request->get_params();
        $property_ids = $this->sanitizePropertyIds($params['property_ids'] ?? []);
        $start_date = sanitize_text_field($params['start_date'] ?? '');
        $end_date = sanitize_text_field($params['end_date'] ?? '');

        // Validate required parameters
        if (empty($property_ids)) {
            return new WP_Error(
                'missing_property_ids',
                __('Property IDs are required', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        if (empty($start_date) || empty($end_date)) {
            return new WP_Error(
                'missing_dates',
                __('Start date and end date are required', 'minpaku-suite'),
                ['status' => 400]
            );
        }

        // Generate cache key
        $cache_key = ResponseCache::availabilityKey(
            implode(',', $property_ids),
            $start_date,
            $end_date,
            array_filter($params, function($key) {
                return !in_array($key, ['property_ids', 'start_date', 'end_date']);
            }, ARRAY_FILTER_USE_KEY)
        );

        // Try to get from cache
        $cached_response = $this->cache->get($cache_key);
        if ($cached_response !== null) {
            $response = new WP_REST_Response($cached_response, 200);
            $response->set_headers(['X-Minpaku-Cache' => 'HIT']);
            return $response;
        }

        // Calculate availability
        $availability_data = $this->calculateAvailability($property_ids, $start_date, $end_date, $params);

        // Cache the response
        $cache_ttl = $this->getCacheTtl('availability');
        $this->cache->put($cache_key, $availability_data, $cache_ttl, [
            'property_ids' => $property_ids,
            'date_range' => $start_date . ':' . $end_date
        ]);

        // Record rate limit usage
        $this->rate_limiter->record('api:availability');

        $response = new WP_REST_Response($availability_data, 200);
        $response->set_headers(['X-Minpaku-Cache' => 'MISS']);

        return $response;
    }

    /**
     * Get availability for a specific property
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function get_property_availability($request) {
        // Apply rate limiting
        $rate_limit_result = $this->applyRateLimit('api:availability', $request);
        if (is_wp_error($rate_limit_result)) {
            return $rate_limit_result;
        }

        $property_id = (int) $request->get_param('property_id');
        $start_date = sanitize_text_field($request->get_param('start_date') ?? '');
        $end_date = sanitize_text_field($request->get_param('end_date') ?? '');

        // Validate property exists
        if (!get_post($property_id) || get_post_type($property_id) !== 'property') {
            return new WP_Error(
                'property_not_found',
                __('Property not found', 'minpaku-suite'),
                ['status' => 404]
            );
        }

        // Generate cache key
        $cache_key = ResponseCache::availabilityKey(
            $property_id,
            $start_date,
            $end_date,
            array_filter($request->get_params(), function($key) {
                return !in_array($key, ['property_id', 'start_date', 'end_date']);
            }, ARRAY_FILTER_USE_KEY)
        );

        // Try to get from cache
        $cached_response = $this->cache->get($cache_key);
        if ($cached_response !== null) {
            $response = new WP_REST_Response($cached_response, 200);
            $response->set_headers(['X-Minpaku-Cache' => 'HIT']);
            return $response;
        }

        // Calculate availability for single property
        $availability_data = $this->calculatePropertyAvailability($property_id, $start_date, $end_date, $request->get_params());

        // Cache the response
        $cache_ttl = $this->getCacheTtl('availability');
        $this->cache->put($cache_key, $availability_data, $cache_ttl, [
            'property_id' => $property_id,
            'date_range' => $start_date . ':' . $end_date
        ]);

        // Record rate limit usage
        $this->rate_limiter->record('api:availability');

        $response = new WP_REST_Response($availability_data, 200);
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
            if (!$this->api_key_manager->hasPermission($key_data, 'read:availability')) {
                return new WP_Error(
                    'insufficient_permissions',
                    __('API key does not have permission to read availability', 'minpaku-suite'),
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
     * Get cache TTL for availability data
     *
     * @param string $type Cache type
     * @return int TTL in seconds
     */
    private function getCacheTtl($type) {
        $settings = get_option('minpaku_api_settings', []);
        return $settings['cache_ttl'][$type] ?? 90;
    }

    /**
     * Calculate availability for multiple properties
     *
     * @param array $property_ids Property IDs
     * @param string $start_date Start date
     * @param string $end_date End date
     * @param array $params Additional parameters
     * @return array Availability data
     */
    private function calculateAvailability($property_ids, $start_date, $end_date, $params) {
        $availability = [];

        foreach ($property_ids as $property_id) {
            $availability[$property_id] = $this->calculatePropertyAvailability($property_id, $start_date, $end_date, $params);
        }

        return [
            'properties' => $availability,
            'date_range' => [
                'start' => $start_date,
                'end' => $end_date
            ],
            'generated_at' => current_time('c')
        ];
    }

    /**
     * Calculate availability for a single property
     *
     * @param int $property_id Property ID
     * @param string $start_date Start date
     * @param string $end_date End date
     * @param array $params Additional parameters
     * @return array Property availability data
     */
    private function calculatePropertyAvailability($property_id, $start_date, $end_date, $params) {
        // This is a simplified implementation
        // In practice, this would check various sources:
        // - Booking ledger for confirmed bookings
        // - iCal imports for blocked dates
        // - Property-specific rules and schedules

        $dates = [];
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);

        while ($current <= $end) {
            $date_string = $current->format('Y-m-d');
            $dates[$date_string] = [
                'date' => $date_string,
                'available' => $this->isDateAvailable($property_id, $date_string),
                'status' => 'available', // available, booked, blocked, maintenance
                'min_stay' => $this->getMinStay($property_id, $date_string),
                'price' => $this->getDatePrice($property_id, $date_string)
            ];

            $current->add(new DateInterval('P1D'));
        }

        return [
            'property_id' => $property_id,
            'property_name' => get_the_title($property_id),
            'dates' => $dates,
            'summary' => [
                'total_days' => count($dates),
                'available_days' => count(array_filter($dates, function($d) { return $d['available']; })),
                'booked_days' => count(array_filter($dates, function($d) { return !$d['available']; }))
            ]
        ];
    }

    /**
     * Check if a specific date is available
     *
     * @param int $property_id Property ID
     * @param string $date Date string (Y-m-d)
     * @return bool True if available
     */
    private function isDateAvailable($property_id, $date) {
        // Simplified check - in practice, this would query the booking system
        return true;
    }

    /**
     * Get minimum stay for a date
     *
     * @param int $property_id Property ID
     * @param string $date Date string (Y-m-d)
     * @return int Minimum stay in nights
     */
    private function getMinStay($property_id, $date) {
        return get_post_meta($property_id, 'min_stay', true) ?: 1;
    }

    /**
     * Get price for a specific date
     *
     * @param int $property_id Property ID
     * @param string $date Date string (Y-m-d)
     * @return float|null Price or null if not set
     */
    private function getDatePrice($property_id, $date) {
        $base_price = get_post_meta($property_id, 'base_price', true);
        return $base_price ? (float) $base_price : null;
    }

    /**
     * Sanitize property IDs
     *
     * @param mixed $property_ids Property IDs input
     * @return array Sanitized property IDs
     */
    private function sanitizePropertyIds($property_ids) {
        if (is_string($property_ids)) {
            $property_ids = explode(',', $property_ids);
        }

        if (!is_array($property_ids)) {
            return [];
        }

        return array_map('intval', array_filter($property_ids, 'is_numeric'));
    }

    /**
     * Get the query params for availability endpoint
     *
     * @return array
     */
    public function get_availability_params() {
        return [
            'property_ids' => [
                'description' => __('Property IDs (comma-separated or array)', 'minpaku-suite'),
                'type' => ['array', 'string'],
                'required' => true,
                'sanitize_callback' => [$this, 'sanitizePropertyIds']
            ],
            'start_date' => [
                'description' => __('Start date (Y-m-d format)', 'minpaku-suite'),
                'type' => 'string',
                'format' => 'date',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'end_date' => [
                'description' => __('End date (Y-m-d format)', 'minpaku-suite'),
                'type' => 'string',
                'format' => 'date',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }

    /**
     * Get the query params for property availability endpoint
     *
     * @return array
     */
    public function get_property_availability_params() {
        return [
            'start_date' => [
                'description' => __('Start date (Y-m-d format)', 'minpaku-suite'),
                'type' => 'string',
                'format' => 'date',
                'required' => false,
                'default' => date('Y-m-d'),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'end_date' => [
                'description' => __('End date (Y-m-d format)', 'minpaku-suite'),
                'type' => 'string',
                'format' => 'date',
                'required' => false,
                'default' => date('Y-m-d', strtotime('+30 days')),
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }
}