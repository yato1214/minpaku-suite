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
        // Force logging for availability endpoint debugging
        $debug_file = ABSPATH . 'wp-content/minpaku-debug.log';
        $is_availability = strpos($request->get_route(), '/availability') !== false;

        if ($is_availability) {
            $debug_message = '[' . date('Y-m-d H:i:s') . '] verify_request() called for AVAILABILITY - Route: ' . $request->get_route() . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
        }

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

        // Build the full path to match connector-side path (including /wp-json/ prefix and query parameters)
        $raw_path = $request->get_route();

        // Add query parameters if they exist
        $query_params = $request->get_query_params();
        if (!empty($query_params)) {
            $raw_path .= '?' . http_build_query($query_params);
        }

        $full_path = '/wp-json' . $raw_path;

        // Normalize the path to match connector-side normalization
        $normalized_path = '/' . ltrim($full_path, '/');
        $normalized_path = preg_replace('#/+#', '/', $normalized_path);

        // Verify signature
        $expected_signature = self::calculate_signature(
            $request->get_method(),
            $normalized_path,
            $nonce,
            $timestamp,
            $request->get_body(),
            $key_data['secret']
        );

        $signature_valid = hash_equals($expected_signature, $signature);

        // Force logging for availability endpoint
        if ($is_availability) {
            $debug_message = '[' . date('Y-m-d H:i:s') . '] AVAILABILITY signature check - Valid: ' . ($signature_valid ? 'YES' : 'NO') . PHP_EOL;
            $debug_message .= 'Raw path: ' . $request->get_route() . PHP_EOL;
            $debug_message .= 'Normalized path: ' . $normalized_path . PHP_EOL;
            $debug_message .= 'Received signature: ' . substr($signature, 0, 12) . '...' . PHP_EOL;
            $debug_message .= 'Expected signature: ' . substr($expected_signature, 0, 12) . '...' . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
        }

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-suite] HMAC verification - Signature valid: ' . ($signature_valid ? 'true' : 'false'));
            if (!$signature_valid) {
                // Log string components (no secret) for debugging
                $string_components = [
                    'method' => strtoupper($request->get_method()),
                    'raw_path' => $request->get_route(),
                    'normalized_path' => $normalized_path,
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
     * Verify HMAC signature with detailed error responses for diagnostics
     */
    public static function verify_request_detailed(\WP_REST_Request $request)
    {
        // Force log that method was called - Multiple methods for debugging
        error_log('[minpaku-suite] verify_request_detailed() called - Method: ' . $request->get_method() . ', Route: ' . $request->get_route());

        // Also write to a specific debug file in case error_log isn't working
        $debug_file = ABSPATH . 'wp-content/minpaku-debug.log';
        $debug_message = '[' . date('Y-m-d H:i:s') . '] verify_request_detailed() called - Method: ' . $request->get_method() . ', Route: ' . $request->get_route() . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        // Check if connector is enabled
        $connector_enabled = ConnectorSettings::is_enabled();
        error_log('[minpaku-suite] Connector enabled check: ' . ($connector_enabled ? 'true' : 'false'));
        $debug_message = '[' . date('Y-m-d H:i:s') . '] Connector enabled: ' . ($connector_enabled ? 'true' : 'false') . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        if (!$connector_enabled) {
            error_log('[minpaku-suite] Connector authentication failed: connector disabled');
            return new \WP_Error('connector_disabled', 'Connector is disabled', ['status' => 503]);
        }

        // Get required headers
        $api_key = $request->get_header('X-MCS-Key');
        $nonce = $request->get_header('X-MCS-Nonce');
        $timestamp = $request->get_header('X-MCS-Timestamp');
        $signature = $request->get_header('X-MCS-Signature');
        $origin_header = $request->get_header('X-MCS-Origin');

        // Debug log headers extraction
        $debug_message = '[' . date('Y-m-d H:i:s') . '] Headers extracted - API Key: ' . ($api_key ? 'present' : 'missing') .
                        ', Nonce: ' . ($nonce ? 'present' : 'missing') .
                        ', Timestamp: ' . ($timestamp ? 'present' : 'missing') .
                        ', Signature: ' . ($signature ? 'present' : 'missing') . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        // Debug logging (no secrets logged) - Force logging for debugging
        $debug_headers = [
            'api_key_present' => !empty($api_key),
            'nonce_present' => !empty($nonce),
            'timestamp_present' => !empty($timestamp),
            'signature_present' => !empty($signature),
            'origin_header' => $origin_header ?: 'none',
            'nonce' => $nonce ? substr($nonce, 0, 8) . '...' : 'none',
            'timestamp' => $timestamp ?: 'none',
            'wp_debug_defined' => defined('WP_DEBUG'),
            'wp_debug_value' => defined('WP_DEBUG') ? WP_DEBUG : 'undefined',
            'wp_debug_log_defined' => defined('WP_DEBUG_LOG'),
            'wp_debug_log_value' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : 'undefined'
        ];
        error_log('[minpaku-suite] HMAC detailed verification - Headers: ' . json_encode($debug_headers));

        if (!$api_key || !$nonce || !$timestamp || !$signature) {
            error_log('[minpaku-suite] HMAC detailed verification failed: missing required headers');
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Missing headers - returning 401' . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
            return new \WP_Error('missing_headers', 'Missing required authentication headers', ['status' => 401]);
        }

        // Find API key data
        $key_data = self::find_api_key_data($api_key);
        $debug_message = '[' . date('Y-m-d H:i:s') . '] API key lookup - Found: ' . ($key_data ? 'yes' : 'no') .
                        ', Active: ' . (($key_data && $key_data['active']) ? 'yes' : 'no') . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        if (!$key_data || !$key_data['active']) {
            error_log('[minpaku-suite] HMAC detailed verification failed: API key not found or inactive');
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Invalid API key - returning 401' . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
            return new \WP_Error('invalid_api_key', 'Invalid or inactive API key', ['status' => 401]);
        }

        // Verify timestamp and calculate time difference
        $request_time = intval($timestamp);
        $current_time = time();
        $time_diff = $current_time - $request_time;

        $debug_message = '[' . date('Y-m-d H:i:s') . '] Timestamp check - Request: ' . $request_time .
                        ', Current: ' . $current_time . ', Diff: ' . $time_diff .
                        ', Max allowed: ' . self::MAX_TIMESTAMP_DIFF . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        error_log('[minpaku-suite] HMAC detailed verification - Time diff: ' . $time_diff . ' seconds (max: ' . self::MAX_TIMESTAMP_DIFF . ')');

        if (abs($time_diff) > self::MAX_TIMESTAMP_DIFF) {
            error_log('[minpaku-suite] HMAC detailed verification failed: timestamp outside acceptable range');
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Timestamp failed - returning 401' . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
            return new \WP_Error('clock_skew', 'Request timestamp outside acceptable range', [
                'status' => 401,
                'skew_seconds' => $time_diff
            ]);
        }

        // Check nonce replay
        $nonce_valid = self::verify_nonce($nonce);
        $debug_message = '[' . date('Y-m-d H:i:s') . '] Nonce check - Valid: ' . ($nonce_valid ? 'yes' : 'no (replay detected)') . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        if (!$nonce_valid) {
            error_log('[minpaku-suite] HMAC detailed verification failed: nonce replay detected');
            $debug_message = '[' . date('Y-m-d H:i:s') . '] Nonce replay failed - returning 401' . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
            return new \WP_Error('nonce_replay', 'Nonce has already been used', ['status' => 401]);
        }

        // Verify signature (with body hash)
        $debug_message = '[' . date('Y-m-d H:i:s') . '] Starting HMAC signature verification' . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        // Build the full path to match connector-side path (including /wp-json/ prefix and query parameters)
        $raw_path = $request->get_route();

        // Add query parameters if they exist
        $query_params = $request->get_query_params();
        if (!empty($query_params)) {
            $raw_path .= '?' . http_build_query($query_params);
        }

        $full_path = '/wp-json' . $raw_path;

        // Normalize the path to match connector-side normalization
        $normalized_path = '/' . ltrim($full_path, '/');
        $normalized_path = preg_replace('#/+#', '/', $normalized_path);

        $debug_message = '[' . date('Y-m-d H:i:s') . '] Path normalization - Raw: ' . $raw_path . ', Full: ' . $full_path . ', Normalized: ' . $normalized_path . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        $expected_signature = self::calculate_signature(
            $request->get_method(),
            $normalized_path,
            $nonce,
            $timestamp,
            $request->get_body(),
            $key_data['secret']
        );

        $signature_valid = hash_equals($expected_signature, $signature);

        // Enhanced debug logging for signature verification - Force logging
        $body_hash = hash('sha256', $request->get_body());
        $string_to_sign = implode("\n", [
            strtoupper($request->get_method()),
            $normalized_path,
            $nonce,
            $timestamp,
            $body_hash
        ]);

        $debug_signature = [
            'method' => strtoupper($request->get_method()),
            'raw_path' => $request->get_route(),
            'full_path' => $full_path,
            'normalized_path' => $normalized_path,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'body_length' => strlen($request->get_body()),
            'body_hash' => $body_hash,
            'string_to_sign' => $string_to_sign,
            'string_to_sign_length' => strlen($string_to_sign),
            'received_signature' => $signature,
            'expected_signature' => $expected_signature,
            'signatures_match' => $signature_valid,
            'secret_length' => strlen($key_data['secret'])
        ];

        error_log('[minpaku-suite] HMAC signature verification (portal): ' . json_encode($debug_signature, JSON_PRETTY_PRINT));

        // Also write signature debug to file (in case error_log is not working properly)
        $debug_message = '[' . date('Y-m-d H:i:s') . '] HMAC signature verification details:' . PHP_EOL;
        $debug_message .= 'Method: ' . $debug_signature['method'] . PHP_EOL;
        $debug_message .= 'Raw path: ' . $debug_signature['raw_path'] . PHP_EOL;
        $debug_message .= 'Full path: ' . $debug_signature['full_path'] . PHP_EOL;
        $debug_message .= 'Normalized path: ' . $debug_signature['normalized_path'] . PHP_EOL;
        $debug_message .= 'Nonce: ' . substr($debug_signature['nonce'], 0, 8) . '...' . PHP_EOL;
        $debug_message .= 'Timestamp: ' . $debug_signature['timestamp'] . PHP_EOL;
        $debug_message .= 'Body length: ' . $debug_signature['body_length'] . PHP_EOL;
        $debug_message .= 'Body hash: ' . $debug_signature['body_hash'] . PHP_EOL;
        $debug_message .= 'String to sign length: ' . $debug_signature['string_to_sign_length'] . PHP_EOL;
        $debug_message .= 'String to sign (raw): ' . json_encode($debug_signature['string_to_sign']) . PHP_EOL;
        $debug_message .= 'String to sign (lines): ' . PHP_EOL;
        $lines = explode("\n", $debug_signature['string_to_sign']);
        foreach ($lines as $i => $line) {
            $debug_message .= '  Line ' . $i . ': ' . json_encode($line) . PHP_EOL;
        }
        $debug_message .= 'Received signature: ' . substr($debug_signature['received_signature'], 0, 12) . '...' . PHP_EOL;
        $debug_message .= 'Expected signature: ' . substr($debug_signature['expected_signature'], 0, 12) . '...' . PHP_EOL;
        $debug_message .= 'Signatures match: ' . ($debug_signature['signatures_match'] ? 'YES' : 'NO') . PHP_EOL;
        $debug_message .= 'Secret length: ' . $debug_signature['secret_length'] . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        if (!$signature_valid) {
            $debug_message = '[' . date('Y-m-d H:i:s') . '] HMAC signature verification FAILED - returning 401' . PHP_EOL;
            file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);
            return new \WP_Error('invalid_signature', 'HMAC signature verification failed', ['status' => 401]);
        }

        $debug_message = '[' . date('Y-m-d H:i:s') . '] HMAC signature verification PASSED - proceeding to success' . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

        // Check origin policy if X-MCS-Origin header is present
        if ($origin_header) {
            $enforce_origin = apply_filters('mcs_connector_enforce_origin_on_verify', false);
            if ($enforce_origin) {
                $parsed_origin = parse_url($origin_header);
                $domain = $parsed_origin['host'] ?? '';

                if ($domain && !ConnectorSettings::is_domain_allowed($domain)) {
                    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('[minpaku-suite] HMAC detailed verification failed: origin not in allowed domains - ' . $domain);
                    }
                    return new \WP_Error('origin_not_allowed', 'Origin domain not in allowed list', ['status' => 403]);
                }
            }

            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-suite] HMAC detailed verification - Origin header: ' . $origin_header);
            }
        }

        // Update last used timestamp
        ConnectorSettings::update_last_used($key_data['site_id']);

        // Store nonce to prevent replay
        self::store_nonce($nonce);

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-suite] HMAC detailed verification successful for site: ' . $key_data['site_id']);
        }

        $debug_message = '[' . date('Y-m-d H:i:s') . '] AUTHENTICATION SUCCESSFUL - returning true (site: ' . $key_data['site_id'] . ')' . PHP_EOL;
        file_put_contents($debug_file, $debug_message, FILE_APPEND | LOCK_EX);

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
        // Use body SHA256 hash for consistency with external connector
        $body_hash = hash('sha256', $body);

        $string_to_sign = implode("\n", [
            strtoupper($method),
            $path,
            $nonce,
            $timestamp,
            $body_hash
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