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
     * Verify HMAC signature for connector request
     */
    public static function verify_request(\WP_REST_Request $request): bool
    {
        // Check if connector is enabled
        if (!ConnectorSettings::is_enabled()) {
            return false;
        }

        // Get required headers
        $api_key = $request->get_header('X-MCS-Key');
        $nonce = $request->get_header('X-MCS-Nonce');
        $timestamp = $request->get_header('X-MCS-Timestamp');
        $signature = $request->get_header('X-MCS-Signature');

        if (!$api_key || !$nonce || !$timestamp || !$signature) {
            return false;
        }

        // Find API key data
        $key_data = self::find_api_key_data($api_key);
        if (!$key_data || !$key_data['active']) {
            return false;
        }

        // Verify timestamp
        if (!self::verify_timestamp($timestamp)) {
            return false;
        }

        // Check nonce replay
        if (!self::verify_nonce($nonce)) {
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

        if (!hash_equals($expected_signature, $signature)) {
            return false;
        }

        // Update last used timestamp
        ConnectorSettings::update_last_used($key_data['site_id']);

        // Store nonce to prevent replay
        self::store_nonce($nonce);

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
            header('Access-Control-Allow-Headers: Content-Type, X-MCS-Key, X-MCS-Nonce, X-MCS-Timestamp, X-MCS-Signature');
            header('Access-Control-Allow-Credentials: false');
            header('Access-Control-Max-Age: 86400');
        }
    }

    /**
     * Handle preflight OPTIONS request
     */
    public static function handle_preflight(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            self::set_cors_headers($origin);
            status_header(200);
            exit;
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