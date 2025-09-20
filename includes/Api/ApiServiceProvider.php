<?php
/**
 * API Service Provider
 * Registers and manages all REST API endpoints under minpaku/v1 namespace
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class ApiServiceProvider {

    /**
     * API namespace
     */
    const NAMESPACE = 'minpaku/v1';

    /**
     * Registered controllers
     */
    private $controllers = [];

    /**
     * Rate limiting settings
     */
    private $rate_limit_settings = [
        'enabled' => true,
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'burst_limit' => 10
    ];

    /**
     * Initialize the API service provider
     */
    public function init() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('rest_api_init', [$this, 'register_namespace_hooks']);

        // Add rate limiting hooks
        if ($this->rate_limit_settings['enabled']) {
            add_filter('rest_pre_dispatch', [$this, 'check_rate_limit'], 10, 3);
        }

        // Add CORS headers for public API
        add_action('rest_api_init', [$this, 'setup_cors']);

        // Log API usage if logger is available
        if (class_exists('MCS_Logger')) {
            add_action('rest_post_dispatch', [$this, 'log_api_request'], 10, 3);
        }
    }

    /**
     * Register all API routes
     */
    public function register_routes() {
        // Load and register controllers
        $this->load_controllers();
        $this->register_controllers();

        // Register namespace info endpoint
        $this->register_namespace_info();
    }

    /**
     * Load controller classes
     */
    private function load_controllers() {
        require_once __DIR__ . '/AvailabilityController.php';
        require_once __DIR__ . '/QuoteController.php';

        $this->controllers = [
            'availability' => new AvailabilityController(),
            'quote' => new QuoteController()
        ];
    }

    /**
     * Register all controllers
     */
    private function register_controllers() {
        foreach ($this->controllers as $name => $controller) {
            if (method_exists($controller, 'register_routes')) {
                $controller->register_routes();
            }
        }
    }

    /**
     * Register namespace information endpoint
     */
    private function register_namespace_info() {
        register_rest_route(self::NAMESPACE, '/', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_namespace_info'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Register namespace-level hooks and filters
     */
    public function register_namespace_hooks() {
        // Add custom headers to all responses
        add_filter('rest_post_dispatch', [$this, 'add_custom_headers'], 10, 3);

        // Add caching recommendations
        add_filter('rest_post_dispatch', [$this, 'add_caching_headers'], 10, 3);
    }

    /**
     * Get namespace information
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_namespace_info($request) {
        $info = [
            'namespace' => self::NAMESPACE,
            'description' => __('MinPaku Suite Read-Only API', 'minpaku-suite'),
            'version' => '1.0.0',
            'endpoints' => [
                'availability' => [
                    'url' => rest_url(self::NAMESPACE . '/availability'),
                    'description' => __('Get property availability data', 'minpaku-suite'),
                    'methods' => ['GET'],
                    'parameters' => [
                        'property_id' => __('Property ID (required)', 'minpaku-suite'),
                        'from' => __('Start date in Y-m-d format (required)', 'minpaku-suite'),
                        'to' => __('End date in Y-m-d format (required)', 'minpaku-suite')
                    ]
                ],
                'quote' => [
                    'url' => rest_url(self::NAMESPACE . '/quote'),
                    'description' => __('Calculate booking quote', 'minpaku-suite'),
                    'methods' => ['GET'],
                    'parameters' => [
                        'property_id' => __('Property ID (required)', 'minpaku-suite'),
                        'checkin' => __('Check-in date in Y-m-d format (required)', 'minpaku-suite'),
                        'checkout' => __('Check-out date in Y-m-d format (required)', 'minpaku-suite'),
                        'adults' => __('Number of adult guests (optional, default: 1)', 'minpaku-suite'),
                        'children' => __('Number of child guests (optional, default: 0)', 'minpaku-suite')
                    ]
                ]
            ],
            'features' => [
                'rate_limiting' => $this->rate_limit_settings['enabled'],
                'caching' => true,
                'cors' => true,
                'authentication' => 'none (public API)'
            ],
            'rate_limits' => $this->rate_limit_settings['enabled'] ? [
                'requests_per_minute' => $this->rate_limit_settings['requests_per_minute'],
                'requests_per_hour' => $this->rate_limit_settings['requests_per_hour'],
                'burst_limit' => $this->rate_limit_settings['burst_limit']
            ] : null,
            'documentation' => [
                'examples' => [
                    'availability' => rest_url(self::NAMESPACE . '/availability?property_id=123&from=2025-10-01&to=2025-10-10'),
                    'quote' => rest_url(self::NAMESPACE . '/quote?property_id=123&checkin=2025-10-01&checkout=2025-10-05&adults=2')
                ],
                'support' => 'https://github.com/your-org/minpaku-suite',
                'changelog' => rest_url(self::NAMESPACE . '/changelog')
            ],
            'meta' => [
                'generated_at' => current_time('c'),
                'server_timezone' => wp_timezone_string(),
                'wordpress_version' => get_bloginfo('version'),
                'plugin_version' => defined('MINPAKU_SUITE_VERSION') ? MINPAKU_SUITE_VERSION : '1.0.0'
            ]
        ];

        return new WP_REST_Response($info, 200);
    }

    /**
     * Setup CORS headers for public API access
     */
    public function setup_cors() {
        add_action('rest_pre_serve_request', function($served, $result, $request, $server) {
            // Only apply CORS to our namespace
            $route = $request->get_route();
            if (strpos($route, '/' . self::NAMESPACE . '/') !== 0) {
                return $served;
            }

            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 86400'); // 24 hours

            // Handle preflight requests
            if ($request->get_method() === 'OPTIONS') {
                status_header(200);
                exit();
            }

            return $served;
        }, 10, 4);
    }

    /**
     * Add custom headers to all API responses
     *
     * @param WP_REST_Response $response Response object
     * @param WP_REST_Server $server Server instance
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Modified response
     */
    public function add_custom_headers($response, $server, $request) {
        // Only apply to our namespace
        $route = $request->get_route();
        if (strpos($route, '/' . self::NAMESPACE . '/') !== 0) {
            return $response;
        }

        $response->set_headers([
            'X-API-Namespace' => self::NAMESPACE,
            'X-API-Version' => '1.0.0',
            'X-Plugin-Version' => defined('MINPAKU_SUITE_VERSION') ? MINPAKU_SUITE_VERSION : '1.0.0',
            'X-Response-Time' => sprintf('%.3f', microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) . 's'
        ]);

        return $response;
    }

    /**
     * Add caching recommendations to responses
     *
     * @param WP_REST_Response $response Response object
     * @param WP_REST_Server $server Server instance
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Modified response
     */
    public function add_caching_headers($response, $server, $request) {
        // Only apply to our namespace
        $route = $request->get_route();
        if (strpos($route, '/' . self::NAMESPACE . '/') !== 0) {
            return $response;
        }

        // Set default caching headers if not already set
        $headers = $response->get_headers();
        if (!isset($headers['Cache-Control'])) {
            $cache_duration = $this->get_cache_duration($route);
            $response->set_headers([
                'Cache-Control' => "public, max-age=$cache_duration",
                'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + $cache_duration)
            ]);
        }

        return $response;
    }

    /**
     * Get cache duration based on endpoint
     *
     * @param string $route API route
     * @return int Cache duration in seconds
     */
    private function get_cache_duration($route) {
        if (strpos($route, '/availability') !== false) {
            return 300; // 5 minutes for availability
        } elseif (strpos($route, '/quote') !== false) {
            return 60; // 1 minute for quotes
        }

        return 300; // Default 5 minutes
    }

    /**
     * Check rate limiting for API requests
     *
     * @param mixed $result Response data
     * @param WP_REST_Server $server Server instance
     * @param WP_REST_Request $request Request object
     * @return mixed Response or WP_Error if rate limited
     */
    public function check_rate_limit($result, $server, $request) {
        // Only apply to our namespace
        $route = $request->get_route();
        if (strpos($route, '/' . self::NAMESPACE . '/') !== 0) {
            return $result;
        }

        $client_ip = $this->get_client_ip();
        $rate_limit_key = 'minpaku_api_rate_limit_' . md5($client_ip);

        // Get current usage
        $current_usage = get_transient($rate_limit_key);
        if (!$current_usage) {
            $current_usage = [
                'minute' => ['count' => 0, 'reset' => time() + 60],
                'hour' => ['count' => 0, 'reset' => time() + 3600]
            ];
        }

        // Reset counters if time has passed
        if (time() > $current_usage['minute']['reset']) {
            $current_usage['minute'] = ['count' => 0, 'reset' => time() + 60];
        }
        if (time() > $current_usage['hour']['reset']) {
            $current_usage['hour'] = ['count' => 0, 'reset' => time() + 3600];
        }

        // Check limits
        if ($current_usage['minute']['count'] >= $this->rate_limit_settings['requests_per_minute']) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Rate limit exceeded. Please try again later.', 'minpaku-suite'),
                [
                    'status' => 429,
                    'headers' => [
                        'Retry-After' => $current_usage['minute']['reset'] - time(),
                        'X-RateLimit-Limit' => $this->rate_limit_settings['requests_per_minute'],
                        'X-RateLimit-Remaining' => 0,
                        'X-RateLimit-Reset' => $current_usage['minute']['reset']
                    ]
                ]
            );
        }

        if ($current_usage['hour']['count'] >= $this->rate_limit_settings['requests_per_hour']) {
            return new WP_Error(
                'hourly_rate_limit_exceeded',
                __('Hourly rate limit exceeded. Please try again later.', 'minpaku-suite'),
                [
                    'status' => 429,
                    'headers' => [
                        'Retry-After' => $current_usage['hour']['reset'] - time(),
                        'X-RateLimit-Limit' => $this->rate_limit_settings['requests_per_hour'],
                        'X-RateLimit-Remaining' => 0,
                        'X-RateLimit-Reset' => $current_usage['hour']['reset']
                    ]
                ]
            );
        }

        // Increment usage
        $current_usage['minute']['count']++;
        $current_usage['hour']['count']++;

        // Save updated usage
        set_transient($rate_limit_key, $current_usage, 3600);

        // Add rate limit headers to response
        add_filter('rest_post_dispatch', function($response) use ($current_usage) {
            if ($response instanceof WP_REST_Response) {
                $response->set_headers([
                    'X-RateLimit-Limit' => $this->rate_limit_settings['requests_per_minute'],
                    'X-RateLimit-Remaining' => max(0, $this->rate_limit_settings['requests_per_minute'] - $current_usage['minute']['count']),
                    'X-RateLimit-Reset' => $current_usage['minute']['reset']
                ]);
            }
            return $response;
        });

        return $result;
    }

    /**
     * Log API requests for monitoring
     *
     * @param WP_REST_Response $response Response object
     * @param WP_REST_Server $server Server instance
     * @param WP_REST_Request $request Request object
     */
    public function log_api_request($response, $server, $request) {
        // Only log our namespace
        $route = $request->get_route();
        if (strpos($route, '/' . self::NAMESPACE . '/') !== 0) {
            return;
        }

        $status_code = $response->get_status();
        $endpoint = str_replace('/' . self::NAMESPACE . '/', '', $route);

        $log_data = [
            'endpoint' => $endpoint,
            'method' => $request->get_method(),
            'status' => $status_code,
            'ip' => $this->get_client_ip(),
            'user_agent' => $request->get_header('User-Agent') ?: 'Unknown',
            'response_time' => sprintf('%.3f', microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']),
            'params' => $request->get_query_params()
        ];

        if ($status_code >= 400) {
            MCS_Logger::log('WARNING', 'API request failed', $log_data);
        } else {
            MCS_Logger::log('INFO', 'API request', $log_data);
        }
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return '127.0.0.1';
    }

    /**
     * Get all registered controllers
     *
     * @return array Array of controllers
     */
    public function get_controllers() {
        return $this->controllers;
    }

    /**
     * Update rate limiting settings
     *
     * @param array $settings New rate limiting settings
     */
    public function update_rate_limit_settings($settings) {
        $this->rate_limit_settings = array_merge($this->rate_limit_settings, $settings);
    }

    /**
     * Get current rate limiting settings
     *
     * @return array Rate limiting settings
     */
    public function get_rate_limit_settings() {
        return $this->rate_limit_settings;
    }
}