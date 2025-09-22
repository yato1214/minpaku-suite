<?php
/**
 * HMAC Signature Client
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Client;

if (!defined('ABSPATH')) {
    exit;
}

class MPC_Client_Signer {

    private $api_key;
    private $secret;

    public function __construct($api_key, $secret) {
        $this->api_key = $api_key;
        $this->secret = $secret;
    }

    /**
     * Sign a request with HMAC-SHA256
     */
    public function sign_request($method, $path, $body = '') {
        $nonce = $this->generate_nonce();
        $timestamp = time();

        // Check for server time sync issues
        $current_time = time();
        $time_drift = abs($timestamp - $current_time);
        if ($time_drift > 300) { // ±300 seconds
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-connector] WARNING: Server time drift detected: ' . $time_drift . ' seconds. Consider checking time synchronization.');
            }
        }

        $signature = $this->calculate_signature($method, $path, $nonce, $timestamp, $body);

        // Debug logging (no secrets logged)
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $body_hash = hash('sha256', $body);
            $debug_info = array(
                'method' => strtoupper($method),
                'path' => $path,
                'nonce' => substr($nonce, 0, 8) . '...',
                'timestamp' => $timestamp,
                'body_length' => strlen($body),
                'body_hash' => substr($body_hash, 0, 16) . '...',
                'api_key_prefix' => substr($this->api_key, 0, 8) . '...'
            );
            error_log('[minpaku-connector] HMAC signature generation: ' . json_encode($debug_info));
        }

        return array(
            'headers' => array(
                'X-MCS-Key' => $this->api_key,
                'X-MCS-Nonce' => $nonce,
                'X-MCS-Timestamp' => $timestamp,
                'X-MCS-Signature' => $signature,
                'Content-Type' => 'application/json'
            ),
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'signature' => $signature
        );
    }

    /**
     * Calculate HMAC signature
     */
    private function calculate_signature($method, $path, $nonce, $timestamp, $body) {
        // Sign with body SHA256 hash, not raw body
        $body_hash = hash('sha256', $body);

        $string_to_sign = implode("\n", array(
            strtoupper($method),
            $path,
            $nonce,
            $timestamp,
            $body_hash
        ));

        return base64_encode(hash_hmac('sha256', $string_to_sign, $this->secret, true));
    }

    /**
     * Generate unique nonce (16 random bytes as hex)
     */
    private function generate_nonce() {
        return bin2hex(random_bytes(16));
    }

    /**
     * Verify the signature components are valid
     */
    public static function validate_credentials($api_key, $secret) {
        if (empty($api_key) || empty($secret)) {
            return false;
        }

        // Basic validation of API key format
        if (!preg_match('/^mcs_[a-zA-Z0-9]{32}$/', $api_key)) {
            return false;
        }

        // Basic validation of secret length
        if (strlen($secret) < 32) {
            return false;
        }

        return true;
    }

    /**
     * Test signature generation with known values
     */
    public function test_signature() {
        $test_method = 'GET';
        $test_path = '/test';
        $test_nonce = 'test-nonce';
        $test_timestamp = 1234567890;
        $test_body = '';

        $expected_string = implode("\n", array(
            $test_method,
            $test_path,
            $test_nonce,
            $test_timestamp,
            $test_body
        ));

        $signature = base64_encode(hash_hmac('sha256', $expected_string, $this->secret, true));

        return array(
            'string_to_sign' => $expected_string,
            'signature' => $signature,
            'api_key' => $this->api_key
        );
    }
}