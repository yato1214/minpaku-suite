<?php
/**
 * API System Bootstrap
 * Initializes the MinPaku Suite API with rate limiting, caching, and hardening
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load API system components
require_once __DIR__ . '/Api/RateLimiter.php';
require_once __DIR__ . '/Api/ResponseCache.php';
require_once __DIR__ . '/Api/ApiKeyManager.php';
require_once __DIR__ . '/Api/CacheInvalidator.php';
require_once __DIR__ . '/Api/AvailabilityController.php';
require_once __DIR__ . '/Api/QuoteController.php';
require_once __DIR__ . '/Admin/ApiSettings.php';

// Initialize API system on plugins loaded
add_action('plugins_loaded', function() {
    // Initialize cache invalidator (sets up hooks)
    new CacheInvalidator();

    // Initialize admin interface
    if (is_admin()) {
        new ApiSettings();
    }

    // Log API system initialization
    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('INFO', 'API hardening system initialized', [
            'components' => [
                'rate_limiter' => 'IP and API key-based rate limiting',
                'response_cache' => 'TTL-based response caching with invalidation',
                'api_keys' => 'API key management and permissions',
                'cache_invalidator' => 'Automatic cache invalidation on data changes'
            ],
            'admin_ui_loaded' => is_admin()
        ]);
    }
}, 25);

// Initialize REST API on rest_api_init
add_action('rest_api_init', function() {
    // Register API controllers
    $availability_controller = new AvailabilityController();
    $availability_controller->register_routes();

    $quote_controller = new QuoteController();
    $quote_controller->register_routes();

    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('DEBUG', 'API REST routes registered', [
            'namespace' => 'minpaku/v1',
            'endpoints' => [
                'availability' => '/wp-json/minpaku/v1/availability',
                'availability_property' => '/wp-json/minpaku/v1/availability/{property_id}',
                'quote' => '/wp-json/minpaku/v1/quote',
                'quote_property' => '/wp-json/minpaku/v1/quote/{property_id}'
            ]
        ]);
    }
});

// Add CORS headers for API endpoints
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
        $origin = get_http_origin();
        $allowed_origins = apply_filters('minpaku_api_allowed_origins', []);

        // Allow CORS for minpaku/v1 endpoints
        if (strpos($request->get_route(), '/minpaku/v1/') === 0) {
            if ($origin && (empty($allowed_origins) || in_array($origin, $allowed_origins))) {
                header('Access-Control-Allow-Origin: ' . $origin);
            } else {
                header('Access-Control-Allow-Origin: *');
            }

            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Minpaku-Api-Key, X-API-Key');
            header('Access-Control-Expose-Headers: X-Minpaku-Cache, X-RateLimit-Bucket, X-RateLimit-Reset');
            header('Access-Control-Max-Age: 86400');

            // Handle preflight OPTIONS requests
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                status_header(200);
                exit;
            }
        }

        return $served;
    }, 15, 4);
});

// Add rate limit headers to all API responses
add_filter('rest_post_dispatch', function($response, $server, $request) {
    // Only add headers to minpaku/v1 endpoints
    if (strpos($request->get_route(), '/minpaku/v1/') !== 0) {
        return $response;
    }

    $rate_limiter = new RateLimiter();
    $api_key = getApiKeyFromHeaders();

    // Determine bucket from route
    $route = $request->get_route();
    $bucket = 'api:availability'; // default
    if (strpos($route, '/quote') !== false) {
        $bucket = 'api:quote';
    }

    $rate_key = $api_key ? 'apikey:' . $api_key : null;
    $current_count = $rate_limiter->getCurrentCount($bucket, $rate_key);
    $retry_after = $rate_limiter->getRetryAfter($bucket, $rate_key);

    $response->header('X-RateLimit-Bucket', $bucket);
    $response->header('X-RateLimit-Current', $current_count);

    if ($retry_after > 0) {
        $response->header('X-RateLimit-Reset', time() + $retry_after);
    }

    return $response;
}, 10, 3);

// Add API documentation endpoint
add_action('rest_api_init', function() {
    register_rest_route('minpaku/v1', '/docs', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => function() {
            return [
                'name' => 'MinPaku Suite API',
                'version' => '1.0.0',
                'description' => 'Property availability and quote API with rate limiting and caching',
                'endpoints' => [
                    'availability' => [
                        'path' => '/wp-json/minpaku/v1/availability',
                        'method' => 'GET',
                        'description' => 'Get availability for multiple properties',
                        'parameters' => [
                            'property_ids' => 'Comma-separated property IDs or array',
                            'start_date' => 'Start date (Y-m-d format)',
                            'end_date' => 'End date (Y-m-d format)'
                        ],
                        'rate_limit' => '60 requests per minute (default)'
                    ],
                    'availability_property' => [
                        'path' => '/wp-json/minpaku/v1/availability/{property_id}',
                        'method' => 'GET',
                        'description' => 'Get availability for a specific property',
                        'parameters' => [
                            'start_date' => 'Start date (Y-m-d format, optional)',
                            'end_date' => 'End date (Y-m-d format, optional)'
                        ],
                        'rate_limit' => '60 requests per minute (default)'
                    ],
                    'quote' => [
                        'path' => '/wp-json/minpaku/v1/quote',
                        'method' => 'POST',
                        'description' => 'Calculate quote for booking request',
                        'parameters' => [
                            'property_id' => 'Property ID (required)',
                            'checkin_date' => 'Check-in date (Y-m-d format, required)',
                            'checkout_date' => 'Check-out date (Y-m-d format, required)',
                            'guests' => 'Guest object with adults, children, infants (required)'
                        ],
                        'rate_limit' => '30 requests per minute (default)'
                    ],
                    'quote_property' => [
                        'path' => '/wp-json/minpaku/v1/quote/{property_id}',
                        'method' => 'POST',
                        'description' => 'Calculate quote for specific property',
                        'parameters' => [
                            'checkin_date' => 'Check-in date (Y-m-d format, required)',
                            'checkout_date' => 'Check-out date (Y-m-d format, required)',
                            'guests' => 'Guest object with adults, children, infants (required)'
                        ],
                        'rate_limit' => '30 requests per minute (default)'
                    ]
                ],
                'authentication' => [
                    'type' => 'API Key (optional)',
                    'header' => 'X-Minpaku-Api-Key or Authorization: Bearer <key>',
                    'benefits' => 'Higher rate limits and usage tracking'
                ],
                'caching' => [
                    'availability_ttl' => '90 seconds (default)',
                    'quote_ttl' => '30 seconds (default)',
                    'cache_header' => 'X-Minpaku-Cache: HIT/MISS'
                ],
                'rate_limiting' => [
                    'header_current' => 'X-RateLimit-Current',
                    'header_reset' => 'X-RateLimit-Reset',
                    'header_bucket' => 'X-RateLimit-Bucket',
                    'error_code' => 429,
                    'retry_header' => 'Retry-After'
                ]
            ];
        },
        'permission_callback' => '__return_true'
    ]);
});

// Plugin activation hook for API
register_activation_hook(__FILE__, function() {
    // Flush rewrite rules to ensure API endpoints work
    flush_rewrite_rules();

    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('INFO', 'API hardening system activated', [
            'rewrite_rules_flushed' => true
        ]);
    }
});

// Plugin deactivation hook for API
register_deactivation_hook(__FILE__, function() {
    // Clean up any scheduled events or temporary data
    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('INFO', 'API hardening system deactivated');
    }
});

// Helper function to get API key from headers
if (!function_exists('getApiKeyFromHeaders')) {
    function getApiKeyFromHeaders() {
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
}

// Add API status endpoint for monitoring
add_action('rest_api_init', function() {
    register_rest_route('minpaku/v1', '/status', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => function() {
            $cache = new ResponseCache();
            $api_key_manager = new ApiKeyManager();

            return [
                'status' => 'ok',
                'timestamp' => current_time('c'),
                'version' => '1.0.0',
                'cache_stats' => $cache->getStats(),
                'api_keys' => [
                    'total' => count($api_key_manager->getAllKeys(true)),
                    'active' => count($api_key_manager->getAllKeys(false))
                ],
                'endpoints' => [
                    'availability' => rest_url('minpaku/v1/availability'),
                    'quote' => rest_url('minpaku/v1/quote'),
                    'docs' => rest_url('minpaku/v1/docs')
                ]
            ];
        },
        'permission_callback' => function() {
            return current_user_can('manage_minpaku');
        }
    ]);
});

// Add health check endpoint (no auth required)
add_action('rest_api_init', function() {
    register_rest_route('minpaku/v1', '/health', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => function() {
            return [
                'status' => 'healthy',
                'timestamp' => current_time('c'),
                'uptime' => 'ok'
            ];
        },
        'permission_callback' => '__return_true'
    ]);
});