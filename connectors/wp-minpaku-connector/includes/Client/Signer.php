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

        $signature = $this->calculate_signature($method, $path, $nonce, $timestamp, $body);

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
        $string_to_sign = implode("\n", array(
            strtoupper($method),
            $path,
            $nonce,
            $timestamp,
            $body
        ));

        return base64_encode(hash_hmac('sha256', $string_to_sign, $this->secret, true));
    }

    /**
     * Generate unique nonce
     */
    private function generate_nonce() {
        return wp_generate_uuid4();
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