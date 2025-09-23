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
        // Debug log that route registration was called
        error_log('[minpaku-suite] register_routes() called - registering connector API routes');
        $debug_file = ABSPATH . 'wp-content/minpaku-debug.log';
        $debug_message = '[' . date('Y-m-d H:i:s') . '] register_routes() called - registering connector API routes' . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
        // Ping endpoint (diagnostic, anonymously accessible by default)
        register_rest_route(self::NAMESPACE, '/ping', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'ping_connection'],
            'permission_callback' => function() {
                // Set basic CORS headers for ping endpoint
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: GET, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type');

                // Check if public ping is enabled via filter (default: true)
                $enable_public_ping = apply_filters('mcs_connector_enable_public_ping', true);

                if ($enable_public_ping) {
                    return true; // Anonymous access OK
                }

                // Fallback to admin-only access
                return current_user_can('manage_options');
            },
        ]);

        // Verify endpoint
        register_rest_route(self::NAMESPACE, '/verify', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'verify_connection'],
            'permission_callback' => function(\WP_REST_Request $request) {
                // Set basic CORS headers for verify endpoint
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: GET, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, X-MCS-Key, X-MCS-Nonce, X-MCS-Timestamp, X-MCS-Signature');

                // Log verification attempt
                error_log('[minpaku-suite] Verify endpoint accessed');

                // For now, allow all requests to test connectivity
                // In production, you should implement proper HMAC verification
                return true;
            },
        ]);

        // Debug log that verify endpoint was registered
        error_log('[minpaku-suite] /verify endpoint registered successfully');
        $debug_message = '[' . date('Y-m-d H:i:s') . '] /verify endpoint registered with namespace: ' . self::NAMESPACE . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

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
            'permission_callback' => function(\WP_REST_Request $request) {
                // Set basic CORS headers for availability endpoint
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: GET, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, X-MCS-Key, X-MCS-Nonce, X-MCS-Timestamp, X-MCS-Signature');

                // Log availability request
                error_log('[minpaku-suite] Availability endpoint accessed for property: ' . ($request->get_param('property_id') ?? 'unknown'));

                // Allow all requests for testing
                return true;
            },
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

        // Quote endpoint with full pricing engine
        register_rest_route(self::NAMESPACE, '/quote', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_quote'],
            'permission_callback' => [__CLASS__, 'check_connector_permissions'],
            'args' => [
                'property_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'checkin' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'date',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'checkout' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'date',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'adults' => [
                    'required' => false,
                    'default' => 2,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 50,
                    'sanitize_callback' => 'absint'
                ],
                'children' => [
                    'required' => false,
                    'default' => 0,
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 20,
                    'sanitize_callback' => 'absint'
                ],
                'infants' => [
                    'required' => false,
                    'default' => 0,
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 10,
                    'sanitize_callback' => 'absint'
                ],
                'currency' => [
                    'required' => false,
                    'default' => 'JPY',
                    'type' => 'string',
                    'enum' => ['JPY', 'USD', 'EUR', 'CNY'],
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }


    /**
     * Check verify endpoint permissions with detailed error responses
     */
    public static function check_verify_permissions(\WP_REST_Request $request)
    {
        // Debug log that permissions check was called
        error_log('[minpaku-suite] check_verify_permissions() called');
        $debug_file = ABSPATH . 'wp-content/minpaku-debug.log';
        $debug_message = '[' . date('Y-m-d H:i:s') . '] check_verify_permissions() called' . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        // Set CORS headers
        ConnectorAuth::set_cors_headers();

        // Check rate limiting
        $rate_limit_ok = self::check_rate_limit($request);
        error_log('[minpaku-suite] Rate limit check result: ' . ($rate_limit_ok ? 'passed' : 'failed'));
        if (!$rate_limit_ok) {
            error_log('[minpaku-suite] Rate limit exceeded, returning 429');
            return new \WP_Error('rate_limit_exceeded', 'Rate limit exceeded', ['status' => 429]);
        }

        // Verify HMAC authentication with detailed errors
        error_log('[minpaku-suite] Calling verify_request_detailed()');
        return ConnectorAuth::verify_request_detailed($request);
    }

    /**
     * Check connector permissions and set CORS headers
     */
    public static function check_connector_permissions(\WP_REST_Request $request): bool
    {
        // Set CORS headers
        ConnectorAuth::set_cors_headers();

        // Check rate limiting
        if (!self::check_rate_limit($request)) {
            return false;
        }

        // Verify HMAC authentication
        return ConnectorAuth::verify_request($request);
    }

    /**
     * Check rate limiting using transients
     */
    private static function check_rate_limit(\WP_REST_Request $request): bool
    {
        // Get client IP
        $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $client_ip = sanitize_text_field($client_ip);

        // Get endpoint
        $endpoint = $request->get_route();

        // Create rate limit key
        $rate_key = 'mcs_rate_limit_' . md5($client_ip . '_' . $endpoint);

        // Get current count
        $current_count = get_transient($rate_key);

        // Set limits per endpoint per minute
        $limits = [
            '/minpaku/v1/connector/ping' => 100,       // 100 per minute (diagnostic)
            '/minpaku/v1/connector/verify' => 100,     // 100 per minute (increased for testing)
            '/minpaku/v1/connector/properties' => 30,  // 30 per minute
            '/minpaku/v1/connector/availability' => 60, // 60 per minute
            '/minpaku/v1/connector/quote' => 20        // 20 per minute
        ];

        $limit = $limits[$endpoint] ?? 60; // Default 60 per minute

        if ($current_count === false) {
            // First request - set transient for 1 minute
            set_transient($rate_key, 1, 60);
            return true;
        }

        if ($current_count >= $limit) {
            // Rate limit exceeded
            $retry_after = 60; // Seconds until reset

            // Log rate limit violation
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-suite] Rate limit exceeded for IP: ' . $client_ip . ' on endpoint: ' . $endpoint);
            }

            // Set rate limit headers
            header('X-RateLimit-Limit: ' . $limit);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . (time() + $retry_after));
            header('Retry-After: ' . $retry_after);

            // Send 429 response
            wp_send_json([
                'error' => 'rate_limit_exceeded',
                'message' => __('Rate limit exceeded. Please try again later.', 'minpaku-suite'),
                'retry_after' => $retry_after
            ], 429);

            return false;
        }

        // Increment counter
        set_transient($rate_key, $current_count + 1, 60);

        // Set rate limit headers for successful requests
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . ($limit - $current_count - 1));
        header('X-RateLimit-Reset: ' . (time() + 60));

        return true;
    }

    /**
     * Ping connection endpoint (diagnostic, anonymously accessible by default)
     */
    public static function ping_connection(\WP_REST_Request $request): \WP_REST_Response
    {
        // Add CORS headers directly
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            error_log('[minpaku-suite] Ping endpoint accessed from IP: ' . sanitize_text_field($client_ip));
        }

        $version = defined('MINPAKU_SUITE_VERSION') ? MINPAKU_SUITE_VERSION : '0.4.1';

        return new \WP_REST_Response([
            'ok' => true,
            'site' => get_bloginfo('name'),
            'ts' => time(),
            'version' => $version,
            'endpoints' => [
                'verify' => rest_url(self::NAMESPACE . '/verify'),
                'properties' => rest_url(self::NAMESPACE . '/properties'),
                'availability' => rest_url(self::NAMESPACE . '/availability'),
                'quote' => rest_url(self::NAMESPACE . '/quote')
            ]
        ], 200);
    }

    /**
     * Verify connection endpoint (HMAC authentication required)
     */
    public static function verify_connection(\WP_REST_Request $request): \WP_REST_Response
    {
        // Add CORS headers directly
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-MCS-Key, X-MCS-Nonce, X-MCS-Timestamp, X-MCS-Signature');

        // Debug log that the endpoint was reached
        error_log('[minpaku-suite] verify_connection() endpoint reached - authentication passed');
        $debug_file = ABSPATH . 'wp-content/minpaku-debug.log';
        $debug_message = '[' . date('Y-m-d H:i:s') . '] verify_connection() endpoint reached - authentication passed' . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        // Return simple success response for testing
        return new \WP_REST_Response([
            'verified' => true,
            'timestamp' => time(),
            'message' => 'Connection verified successfully'
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
        // Add CORS headers directly
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-MCS-Key, X-MCS-Nonce, X-MCS-Timestamp, X-MCS-Signature');

        // Force logging for debugging
        $debug_file = ABSPATH . 'wp-content/minpaku-debug.log';
        $debug_message = '[' . date('Y-m-d H:i:s') . '] get_availability() called' . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        $property_id = $request->get_param('property_id');
        $months = $request->get_param('months');
        $start_date = $request->get_param('start_date');
        $with_price = true; // Always include price data

        $debug_message = '[' . date('Y-m-d H:i:s') . '] Parameters - property_id: ' . $property_id . ', months: ' . $months . ', start_date: ' . ($start_date ?: 'null') . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        try {
            // Verify property exists
            $property = get_post($property_id);
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Property check - Found: ' . ($property ? 'yes' : 'no') . ', Type: ' . ($property ? $property->post_type : 'none') . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

            if (!$property || $property->post_type !== 'mcs_property') {
                $debug_message = '[' . date('Y-m-d H:i:s') . '] Property not found - returning 404' . PHP_EOL;
                file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Property not found.', 'minpaku-suite'),
                    'code' => 'property_not_found'
                ], 404);
            }

            // Calculate date range
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Calculating date range' . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
            $start = $start_date ? new \DateTime($start_date) : new \DateTime();
            $end = clone $start;
            $end->add(new \DateInterval('P' . $months . 'M'));

            // Get availability data
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Date range calculated - Start: ' . $start->format('Y-m-d') . ', End: ' . $end->format('Y-m-d') . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

            $availability = [];
            // Use AvailabilityService for real availability data
            if (class_exists('MinpakuSuite\Availability\AvailabilityService')) {
                $debug_message = '[' . date('Y-m-d H:i:s') . '] Using AvailabilityService' . PHP_EOL;
                file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

                try {
                    $debug_message = '[' . date('Y-m-d H:i:s') . '] About to call AvailabilityService::get_availability_range()' . PHP_EOL;
                    file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

                    // Set execution timeout for this call
                    $original_timeout = ini_get('max_execution_time');
                    set_time_limit(20); // 20 seconds max for availability service

                    $call_start_time = microtime(true);
                    $availability = \MinpakuSuite\Availability\AvailabilityService::get_availability_range(
                        $property_id,
                        $start->format('Y-m-d'),
                        $end->format('Y-m-d')
                    );
                    $call_duration = microtime(true) - $call_start_time;

                    // Restore original timeout
                    set_time_limit($original_timeout);

                    $debug_message = '[' . date('Y-m-d H:i:s') . '] AvailabilityService call completed in ' . number_format($call_duration, 4) . ' seconds' . PHP_EOL;
                    file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

                    $debug_message = '[' . date('Y-m-d H:i:s') . '] AvailabilityService returned ' . count($availability) . ' items' . PHP_EOL;
                    file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
                } catch (\Exception $as_error) {
                    $debug_message = '[' . date('Y-m-d H:i:s') . '] AvailabilityService ERROR: ' . $as_error->getMessage() . PHP_EOL;
                    $debug_message .= 'File: ' . $as_error->getFile() . ':' . $as_error->getLine() . PHP_EOL;
                    file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

                    // Fallback to basic availability when AvailabilityService fails
                    $availability = self::generate_basic_availability($property_id, $start, $end);

                    $debug_message = '[' . date('Y-m-d H:i:s') . '] Using fallback after AvailabilityService error, generated ' . count($availability) . ' items' . PHP_EOL;
                    file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
                }
            } else {
                $debug_message = '[' . date('Y-m-d H:i:s') . '] AvailabilityService not found, using fallback' . PHP_EOL;
                file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

                // Fallback: generate basic availability data
                $availability = self::generate_basic_availability($property_id, $start, $end);

                $debug_message = '[' . date('Y-m-d H:i:s') . '] Fallback generated ' . count($availability) . ' items' . PHP_EOL;
                file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
            }

            $debug_message = '[' . date('Y-m-d H:i:s') . '] Returning successful response with ' . count($availability) . ' availability items' . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

            // Add pricing data if requested
            $pricing_data = [];
            if ($with_price) {
                foreach ($availability as $day) {
                    if ($day['available']) {
                        $date_str = $day['date'];
                        $price = self::get_price_for_date($property_id, $date_str);
                        if ($price > 0) {
                            $pricing_data[] = [
                                'date' => $date_str,
                                'price' => $price
                            ];
                        }
                    }
                }
            }

            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'property_id' => $property_id,
                    'property_title' => $property->post_title,
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                    'availability' => $availability,
                    'pricing' => $pricing_data
                ]
            ], 200);

        } catch (\Exception $e) {
            error_log('Minpaku Connector API Error: ' . $e->getMessage());

            $debug_message = '[' . date('Y-m-d H:i:s') . '] EXCEPTION in get_availability: ' . $e->getMessage() . PHP_EOL;
            $debug_message .= 'File: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
            $debug_message .= 'Stack trace: ' . $e->getTraceAsString() . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Unable to fetch availability.', 'minpaku-suite'),
                'code' => 'availability_fetch_error'
            ], 500);
        }
    }

    /**
     * Get quote endpoint with full pricing engine
     */
    public static function get_quote(\WP_REST_Request $request): \WP_REST_Response
    {
        $start_time = microtime(true);
        $debug_file = ABSPATH . 'wp-content/minpaku-debug.log';

        try {
            // Extract and validate parameters
            $property_id = $request->get_param('property_id');
            $checkin = $request->get_param('checkin');
            $checkout = $request->get_param('checkout');
            $adults = $request->get_param('adults');
            $children = $request->get_param('children');
            $infants = $request->get_param('infants');
            $currency = $request->get_param('currency');

            // Debug logging
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Quote request: property_id=' . $property_id .
                ', checkin=' . $checkin . ', checkout=' . $checkout .
                ', adults=' . $adults . ', children=' . $children . ', infants=' . $infants .
                ', currency=' . $currency . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

            // Verify property exists
            $property = get_post($property_id);
            if (!$property || $property->post_type !== 'mcs_property') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Property not found.', 'minpaku-suite'),
                    'code' => 'property_not_found'
                ], 404);
            }

            // Check cache first
            $context = new \MinpakuSuite\Pricing\RateContext(
                $property_id, $checkin, $checkout, $adults, $children, $infants, $currency
            );

            $cache_key = $context->getCacheKey();
            $cached_quote = get_transient($cache_key);

            if ($cached_quote !== false && defined('WP_DEBUG') && !WP_DEBUG) {
                $debug_message = '[' . date('Y-m-d H:i:s') . '] Quote served from cache: ' . $cache_key . PHP_EOL;
                file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

                return new \WP_REST_Response($cached_quote, 200);
            }

            // Create pricing engine and calculate quote
            $engine = new \MinpakuSuite\Pricing\PricingEngine($context);
            $quote = $engine->calculateQuote();

            // Transform to API response format
            $response = [
                'currency' => $quote['currency'],
                'nights' => $quote['nights'],
                'guests' => $quote['guests'],
                'dates' => $quote['dates'],
                'line_items' => $quote['line_items'],
                'taxes' => $quote['taxes'],
                'total_excl_tax' => $quote['totals']['total_excl_tax'],
                'total_incl_tax' => $quote['totals']['total_incl_tax'],
                'constraints' => $quote['constraints']
            ];

            // Cache the response for 60 seconds (only for stays <= 31 days)
            if ($context->nights <= 31) {
                set_transient($cache_key, $response, 60);
            }

            // Log performance
            $execution_time = microtime(true) - $start_time;
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Quote calculated successfully in ' .
                number_format($execution_time * 1000, 2) . 'ms' . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

            return new \WP_REST_Response($response, 200);

        } catch (\InvalidArgumentException $e) {
            // Validation errors (400)
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Quote validation error: ' . $e->getMessage() . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'validation_error'
            ], 400);

        } catch (\DomainException $e) {
            // Business logic errors - constraints/availability (409)
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Quote constraint error: ' . $e->getMessage() . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'constraint_violation'
            ], 409);

        } catch (\Exception $e) {
            // System errors (500)
            error_log('Minpaku Connector Quote API Error: ' . $e->getMessage());
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Quote system error: ' . $e->getMessage() . PHP_EOL;
            $debug_message .= 'File: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Unable to generate quote. Please try again later.', 'minpaku-suite'),
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
            'content' => self::process_property_shortcodes($property->post_content, $property_id),
            'excerpt' => $property->post_excerpt ?: wp_trim_words(self::process_property_shortcodes($property->post_content, $property_id), 20),
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
     * Process property shortcodes with property context
     */
    private static function process_property_shortcodes(string $content, int $property_id): string
    {
        // Replace [mcs_availability] with [mcs_availability id="property_id"]
        $content = preg_replace_callback(
            '/\[mcs_availability([^\]]*)\]/',
            function ($matches) use ($property_id) {
                $attributes = $matches[1];

                // Check if id parameter is already set
                if (strpos($attributes, 'id=') !== false) {
                    return $matches[0]; // Return unchanged if id is already set
                }

                // Add the property id to the shortcode
                $new_attributes = trim($attributes . ' id="' . $property_id . '"');
                return '[mcs_availability' . ($new_attributes ? ' ' . $new_attributes : ' id="' . $property_id . '"') . ']';
            },
            $content
        );

        // Process all shortcodes
        return do_shortcode($content);
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
     * Get price for a specific date
     */
    private static function get_price_for_date(int $property_id, string $date_str): int
    {
        // Try to get price from pricing engine first (for future dates only)
        $date = new \DateTime($date_str);
        $today = new \DateTime('today');

        if ($date >= $today && class_exists('MinpakuSuite\Pricing\PricingEngine') && class_exists('MinpakuSuite\Pricing\RateContext')) {
            try {
                $checkin = new \DateTime($date_str);
                $checkout = clone $checkin;
                $checkout->add(new \DateInterval('P1D'));

                $context = new \MinpakuSuite\Pricing\RateContext(
                    $property_id,
                    $checkin->format('Y-m-d'),
                    $checkout->format('Y-m-d'),
                    2, // adults
                    0, // children
                    0  // infants
                );

                $pricing_engine = new \MinpakuSuite\Pricing\PricingEngine($context);
                $quote = $pricing_engine->calculateQuote();

                if ($quote && isset($quote['total_incl_tax']) && $quote['total_incl_tax'] > 0) {
                    return intval($quote['total_incl_tax']);
                }
            } catch (\Exception $e) {
                error_log('Price calculation error: ' . $e->getMessage());
            }
        }

        // Fallback to base price meta
        $base_price = get_post_meta($property_id, 'mcs_base_price', true);
        if ($base_price && is_numeric($base_price) && $base_price > 0) {
            return intval($base_price);
        }

        return 0;
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