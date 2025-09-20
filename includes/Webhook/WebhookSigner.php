<?php
/**
 * Webhook Signer
 * Handles HMAC-SHA256 signing and verification for webhook security
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class WebhookSigner {

    /**
     * Signature header name
     */
    const SIGNATURE_HEADER = 'X-Minpaku-Signature';

    /**
     * Timestamp header name
     */
    const TIMESTAMP_HEADER = 'X-Minpaku-Timestamp';

    /**
     * Maximum allowed timestamp difference (5 minutes)
     */
    const MAX_TIMESTAMP_DIFF = 300;

    /**
     * Sign a webhook payload
     *
     * @param string $body Raw payload body
     * @param string $secret Webhook secret
     * @param int|null $timestamp Unix timestamp (defaults to current time)
     * @return string Signature in format "sha256={hash}"
     */
    public function sign($body, $secret, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }

        // Create signature payload: timestamp + '.' + body
        $signature_payload = $timestamp . '.' . $body;

        // Generate HMAC-SHA256 hash
        $hash = hash_hmac('sha256', $signature_payload, $secret);

        return 'sha256=' . $hash;
    }

    /**
     * Verify a webhook signature
     *
     * @param string $body Raw payload body
     * @param string $signature Signature from header
     * @param string $secret Webhook secret
     * @param int|null $timestamp Timestamp from header
     * @return bool True if signature is valid, false otherwise
     */
    public function verify($body, $signature, $secret, $timestamp = null) {
        // Validate signature format
        if (!$this->isValidSignatureFormat($signature)) {
            return false;
        }

        // Verify timestamp if provided
        if ($timestamp !== null && !$this->isValidTimestamp($timestamp)) {
            return false;
        }

        // If no timestamp provided, try to verify without timestamp check
        if ($timestamp === null) {
            $timestamp = time();
        }

        // Generate expected signature
        $expected_signature = $this->sign($body, $secret, $timestamp);

        // Constant-time string comparison to prevent timing attacks
        return hash_equals($signature, $expected_signature);
    }

    /**
     * Verify signature with timestamp tolerance
     *
     * @param string $body Raw payload body
     * @param string $signature Signature from header
     * @param string $secret Webhook secret
     * @param int $timestamp Timestamp from header
     * @param int $tolerance Allowed time difference in seconds
     * @return bool True if signature is valid within tolerance
     */
    public function verifyWithTolerance($body, $signature, $secret, $timestamp, $tolerance = self::MAX_TIMESTAMP_DIFF) {
        // Validate signature format
        if (!$this->isValidSignatureFormat($signature)) {
            return false;
        }

        // Check timestamp is within tolerance
        $current_time = time();
        $time_diff = abs($current_time - $timestamp);

        if ($time_diff > $tolerance) {
            return false;
        }

        // Generate expected signature using the provided timestamp
        $expected_signature = $this->sign($body, $secret, $timestamp);

        // Constant-time string comparison
        return hash_equals($signature, $expected_signature);
    }

    /**
     * Generate headers for webhook request
     *
     * @param string $event Event name
     * @param string $delivery_key Delivery key for idempotency
     * @param string $body Raw payload body
     * @param string $secret Webhook secret
     * @return array HTTP headers
     */
    public function generateHeaders($event, $delivery_key, $body, $secret) {
        $timestamp = time();
        $signature = $this->sign($body, $secret, $timestamp);

        return [
            'Content-Type' => 'application/json',
            'X-Minpaku-Event' => $event,
            'X-Minpaku-Delivery' => $delivery_key,
            self::TIMESTAMP_HEADER => (string) $timestamp,
            self::SIGNATURE_HEADER => $signature,
            'User-Agent' => 'MinPaku-Suite-Webhook/1.0'
        ];
    }

    /**
     * Validate signature format
     *
     * @param string $signature Signature to validate
     * @return bool True if format is valid
     */
    private function isValidSignatureFormat($signature) {
        // Must start with "sha256=" and have 64 hex characters
        return preg_match('/^sha256=[a-f0-9]{64}$/', $signature) === 1;
    }

    /**
     * Validate timestamp is reasonable
     *
     * @param int $timestamp Timestamp to validate
     * @return bool True if timestamp is valid
     */
    private function isValidTimestamp($timestamp) {
        $current_time = time();
        $time_diff = abs($current_time - $timestamp);

        // Reject timestamps too far in past or future
        return $time_diff <= self::MAX_TIMESTAMP_DIFF;
    }

    /**
     * Extract signature hash from header value
     *
     * @param string $signature_header Header value like "sha256=abc123..."
     * @return string|null Hash portion or null if invalid
     */
    public function extractHash($signature_header) {
        if (!$this->isValidSignatureFormat($signature_header)) {
            return null;
        }

        return substr($signature_header, 7); // Remove "sha256=" prefix
    }

    /**
     * Create test signature for development/testing
     *
     * @param array $payload Payload data
     * @param string $secret Secret key
     * @return array Headers and body for testing
     */
    public function createTestSignature($payload, $secret) {
        $event = $payload['event'] ?? 'test.event';
        $delivery_key = wp_generate_uuid4();
        $body = wp_json_encode($payload);

        $headers = $this->generateHeaders($event, $delivery_key, $body, $secret);

        return [
            'headers' => $headers,
            'body' => $body,
            'delivery_key' => $delivery_key
        ];
    }

    /**
     * Validate webhook endpoint security
     *
     * @param string $url Webhook URL
     * @return array Validation result with errors if any
     */
    public function validateEndpointSecurity($url) {
        $errors = [];

        // Parse URL
        $parsed = parse_url($url);

        if (!$parsed) {
            $errors[] = __('Invalid URL format', 'minpaku-suite');
            return ['valid' => false, 'errors' => $errors];
        }

        // Require HTTPS in production
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            if ($parsed['scheme'] !== 'https') {
                $errors[] = __('HTTPS is required for webhook endpoints', 'minpaku-suite');
            }
        }

        // Check for localhost/private IPs in production
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            $host = $parsed['host'] ?? '';
            if ($this->isPrivateOrLocalHost($host)) {
                $errors[] = __('Webhook endpoints cannot point to private or local addresses', 'minpaku-suite');
            }
        }

        // Check URL length
        if (strlen($url) > 2048) {
            $errors[] = __('Webhook URL is too long (maximum 2048 characters)', 'minpaku-suite');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Check if host is private or localhost
     *
     * @param string $host Hostname or IP
     * @return bool True if private/local
     */
    private function isPrivateOrLocalHost($host) {
        // Check for localhost variants
        $localhost_patterns = [
            'localhost',
            '127.0.0.1',
            '::1',
            '0.0.0.0'
        ];

        if (in_array($host, $localhost_patterns)) {
            return true;
        }

        // Check for private IP ranges
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        // Check for internal domains
        if (strpos($host, '.local') !== false || strpos($host, '.internal') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Get signature algorithm information
     *
     * @return array Algorithm details
     */
    public function getAlgorithmInfo() {
        return [
            'algorithm' => 'HMAC-SHA256',
            'header' => self::SIGNATURE_HEADER,
            'format' => 'sha256={hash}',
            'timestamp_header' => self::TIMESTAMP_HEADER,
            'max_timestamp_diff' => self::MAX_TIMESTAMP_DIFF,
            'description' => __('HMAC-SHA256 signature of timestamp.body using webhook secret', 'minpaku-suite')
        ];
    }
}