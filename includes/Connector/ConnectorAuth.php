<?php
/**
 * Connector Authentication
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Connector;

if (!defined('ABSPATH')) {
    exit;
}

class ConnectorAuth
{
    private const NONCE_CACHE_PREFIX = 'mcs_connector_nonce_';
    private const MAX_TIMESTAMP_DIFF = 300; // 5 minutes

    /**
     * Handle CORS preflight requests
     */
    public static function handle_preflight(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
            return;
        }

        // Only handle preflight for connector endpoints
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($request_uri, '/wp-json/minpaku/v1/connector') === false) {
            return;
        }

        // Check if origin is allowed
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (!self::is_origin_allowed($origin)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'forbidden',
                'message' => __('Origin not allowed', 'minpaku-suite')
            ]);
            exit;
        }

        // Set CORS headers for preflight
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: X-MCS-Key, X-MCS-Nonce, X-MCS-Timestamp, X-MCS-Signature, Content-Type');
        header('Access-Control-Allow-Credentials: false');
        header('Access-Control-Max-Age: 86400'); // 24 hours
        header('Vary: Origin');

        // Debug logging for preflight
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-suite] CORS preflight handled for origin: ' . $origin);
        }

        // Preflight response
        http_response_code(200);
        exit;
    }

    /**
     * Check if origin is allowed based on domain settings
     */
    private static function is_origin_allowed(string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }

        // Parse origin to get domain
        $parsed = parse_url($origin);
        if (!isset($parsed['host'])) {
            return false;
        }

        $domain = $parsed['host'];

        // Remove www prefix for comparison
        $domain = preg_replace('/^www\./', '', strtolower($domain));

        // Check against allowed domains
        $allowed_domains = ConnectorSettings::get_allowed_domains();
        foreach ($allowed_domains as $allowed) {
            $allowed = preg_replace('/^www\./', '', strtolower($allowed));
            if ($domain === $allowed) {
                return true;
            }
        }

        return false;
    }


    /**
     * Verify HMAC signature for connector request
     */
    public static function verify_request(\WP_REST_Request $request): bool
    {
        // Check if connector is enabled
        if (!ConnectorSettings::is_enabled()) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-suite] Connector authentication failed: connector disabled');
            }
            return false;
        }

        // Get required headers
        $api_key = $request->get_header('X-MCS-Key');
        $nonce = $request->get_header('X-MCS-Nonce');
        $timestamp = $request->get_header('X-MCS-Timestamp');
        $signature = $request->get_header('X-MCS-Signature');

        // Debug logging (no secrets logged)
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $debug_headers = [
                'api_key_present' => !empty($api_key),
                'nonce_present' => !empty($nonce),
                'timestamp_present' => !empty($timestamp),
                'signature_present' => !empty($signature),
                'nonce' => $nonce ? substr($nonce, 0, 8) . '...' : 'none',
                'timestamp' => $timestamp ?: 'none'
            ];
            error_log('[minpaku-suite] HMAC verification - Headers: ' . json_encode($debug_headers));
        }

        if (!$api_key || !$nonce || !$timestamp || !$signature) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-suite] HMAC verification failed: missing required headers');
            }
            return false;
        }

        // Find API key data
        $key_data = self::find_api_key_data($api_key);
        if (!$key_data || !$key_data['active']) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-suite] HMAC verification failed: API key not found or inactive');
            }
            return false;
        }

        // Verify timestamp and calculate time difference
        $request_time = intval($timestamp);
        $current_time = time();
        $time_diff = $current_time - $request_time;

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-suite] HMAC verification - Time diff: ' . $time_diff . ' seconds (max: ' . self::MAX_TIMESTAMP_DIFF . ')');
        }

        if (!self::verify_timestamp($timestamp)) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-suite] HMAC verification failed: timestamp outside acceptable range');
            }
            return false;
        }

        // Check nonce replay
        if (!self::verify_nonce($nonce)) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-suite] HMAC verification failed: nonce replay detected');
            }
            return false;
        }

        // Verify signature
        $expected_signature = self::calculate_signature(
            $request->get_method(),
            $request->get_route(),
            $nonce,
            $timestamp,
            $request->get_body(),
            $key_data['secret']
        );

        $signature_valid = hash_equals($expected_signature, $signature);

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-suite] HMAC verification - Signature valid: ' . ($signature_valid ? 'true' : 'false'));
            if (!$signature_valid) {
                // Log string components (no secret) for debugging
                $string_components = [
                    'method' => strtoupper($request->get_method()),
                    'path' => $request->get_route(),
                    'nonce' => substr($nonce, 0, 8) . '...',
                    'timestamp' => $timestamp,
                    'body_length' => strlen($request->get_body())
                ];
                error_log('[minpaku-suite] HMAC verification - String components: ' . json_encode($string_components));
            }
        }

        if (!$signature_valid) {
            return false;
        }

        // Update last used timestamp
        ConnectorSettings::update_last_used($key_data['site_id']);

        // Store nonce to prevent replay
        self::store_nonce($nonce);

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-suite] HMAC verification successful for site: ' . $key_data['site_id']);
        }

        return true;
    }

    /**
     * Calculate HMAC signature
     */
    public static function calculate_signature(
        string $method,
        string $path,
        string $nonce,
        string $timestamp,
        string $body,
        string $secret
    ): string {
        $string_to_sign = implode("\n", [
            strtoupper($method),
            $path,
            $nonce,
            $timestamp,
            $body
        ]);

        return base64_encode(hash_hmac('sha256', $string_to_sign, $secret, true));
    }

    /**
     * Find API key data by API key
     */
    private static function find_api_key_data(string $api_key): ?array
    {
        $api_keys = ConnectorSettings::get_api_keys();

        foreach ($api_keys as $data) {
            if (isset($data['api_key']) && hash_equals($data['api_key'], $api_key)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Verify timestamp is within acceptable range
     */
    private static function verify_timestamp(string $timestamp): bool
    {
        $request_time = intval($timestamp);
        $current_time = time();
        $diff = abs($current_time - $request_time);

        return $diff <= self::MAX_TIMESTAMP_DIFF;
    }

    /**
     * Verify nonce hasn't been used before
     */
    private static function verify_nonce(string $nonce): bool
    {
        $cache_key = self::NONCE_CACHE_PREFIX . $nonce;
        return get_transient($cache_key) === false;
    }

    /**
     * Store nonce to prevent replay
     */
    private static function store_nonce(string $nonce): void
    {
        $cache_key = self::NONCE_CACHE_PREFIX . $nonce;
        set_transient($cache_key, true, self::MAX_TIMESTAMP_DIFF * 2);
    }

    /**
     * Check CORS for connector endpoints
     */
    public static function check_cors_origin(string $origin): bool
    {
        if (!ConnectorSettings::is_enabled()) {
            return false;
        }

        $parsed_origin = parse_url($origin);
        if (!$parsed_origin || !isset($parsed_origin['host'])) {
            return false;
        }

        $domain = $parsed_origin['host'];
        return ConnectorSettings::is_domain_allowed($domain);
    }

    /**
     * Set CORS headers for connector endpoints
     */
    public static function set_cors_headers(string $origin = ''): void
    {
        if (empty($origin)) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        }

        if (!empty($origin) && self::check_cors_origin($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: X-MCS-Key, X-MCS-Nonce, X-MCS-Timestamp, X-MCS-Signature, Content-Type');
            header('Access-Control-Allow-Credentials: false');
            header('Access-Control-Max-Age: 86400');
            header('Vary: Origin');

            // Debug logging for CORS headers
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-suite] CORS headers set for origin: ' . $origin);
            }
        } else if (!empty($origin)) {
            // Debug logging for blocked origin
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-suite] CORS blocked for origin: ' . $origin);
            }
        }
    }


    /**
     * Generate nonce for external clients
     */
    public static function generate_nonce(): string
    {
        return wp_generate_uuid4();
    }

    /**
     * Get current timestamp
     */
    public static function get_timestamp(): int
    {
        return time();
    }
}