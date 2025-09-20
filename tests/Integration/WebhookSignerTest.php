<?php
/**
 * Webhook Signer Integration Tests
 * Tests HMAC-SHA256 signature generation and verification
 *
 * @package MinpakuSuite
 */

use PHPUnit\Framework\TestCase;

class WebhookSignerTest extends TestCase {

    /**
     * Webhook signer instance
     */
    private $signer;

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();

        // Mock WordPress environment if not available
        if (!defined('ABSPATH')) {
            $this->mockWordPressEnvironment();
        }

        // Load required class
        require_once __DIR__ . '/../../includes/Webhook/WebhookSigner.php';

        $this->signer = new WebhookSigner();
    }

    /**
     * Test signature generation with known values
     */
    public function testSignatureGenerationWithKnownValues() {
        $body = '{"event":"booking.confirmed","data":{"booking":{"id":123}}}';
        $secret = 'test_secret_123';
        $timestamp = 1634567890; // Fixed timestamp for reproducible test

        $signature = $this->signer->sign($body, $secret, $timestamp);

        // Expected signature calculated manually
        $expected_payload = $timestamp . '.' . $body;
        $expected_hash = hash_hmac('sha256', $expected_payload, $secret);
        $expected_signature = 'sha256=' . $expected_hash;

        $this->assertEquals($expected_signature, $signature);
        $this->assertStringStartsWith('sha256=', $signature);
    }

    /**
     * Test signature verification with valid signature
     */
    public function testSignatureVerificationWithValidSignature() {
        $body = '{"test":"payload","timestamp":"2025-01-01T00:00:00Z"}';
        $secret = 'webhook_secret_456';
        $timestamp = time();

        // Generate signature
        $signature = $this->signer->sign($body, $secret, $timestamp);

        // Verify signature
        $is_valid = $this->signer->verify($body, $signature, $secret, $timestamp);

        $this->assertTrue($is_valid);
    }

    /**
     * Test signature verification with invalid signature
     */
    public function testSignatureVerificationWithInvalidSignature() {
        $body = '{"event":"payment.captured","amount":1000}';
        $secret = 'correct_secret';
        $timestamp = time();

        // Generate signature with correct secret
        $valid_signature = $this->signer->sign($body, $secret, $timestamp);

        // Try to verify with wrong secret
        $is_valid = $this->signer->verify($body, $valid_signature, 'wrong_secret', $timestamp);

        $this->assertFalse($is_valid);
    }

    /**
     * Test signature verification with tampered body
     */
    public function testSignatureVerificationWithTamperedBody() {
        $original_body = '{"booking":{"id":123,"status":"confirmed"}}';
        $tampered_body = '{"booking":{"id":456,"status":"confirmed"}}';
        $secret = 'secure_secret_789';
        $timestamp = time();

        // Generate signature for original body
        $signature = $this->signer->sign($original_body, $secret, $timestamp);

        // Try to verify with tampered body
        $is_valid = $this->signer->verify($tampered_body, $signature, $secret, $timestamp);

        $this->assertFalse($is_valid);
    }

    /**
     * Test timestamp validation within tolerance
     */
    public function testTimestampValidationWithinTolerance() {
        $body = '{"test":"timestamp_validation"}';
        $secret = 'timestamp_test_secret';
        $original_timestamp = time();

        $signature = $this->signer->sign($body, $secret, $original_timestamp);

        // Test with timestamp 2 minutes in the past (within 5-minute tolerance)
        $past_timestamp = $original_timestamp - 120;
        $is_valid = $this->signer->verify($body, $signature, $secret, $past_timestamp);
        $this->assertTrue($is_valid);

        // Test with timestamp 2 minutes in the future (within 5-minute tolerance)
        $future_timestamp = $original_timestamp + 120;
        $is_valid = $this->signer->verify($body, $signature, $secret, $future_timestamp);
        $this->assertTrue($is_valid);
    }

    /**
     * Test timestamp validation outside tolerance
     */
    public function testTimestampValidationOutsideTolerance() {
        $body = '{"test":"expired_timestamp"}';
        $secret = 'expired_test_secret';
        $original_timestamp = time();

        $signature = $this->signer->sign($body, $secret, $original_timestamp);

        // Test with timestamp 10 minutes in the past (outside 5-minute tolerance)
        $expired_timestamp = $original_timestamp - 600;
        $is_valid = $this->signer->verify($body, $signature, $secret, $expired_timestamp);
        $this->assertFalse($is_valid);

        // Test with timestamp 10 minutes in the future (outside 5-minute tolerance)
        $future_timestamp = $original_timestamp + 600;
        $is_valid = $this->signer->verify($body, $signature, $secret, $future_timestamp);
        $this->assertFalse($is_valid);
    }

    /**
     * Test header generation includes all required headers
     */
    public function testHeaderGenerationIncludesAllRequiredHeaders() {
        $body = '{"webhook":"test","payload":{"data":"value"}}';
        $secret = 'header_test_secret';
        $timestamp = time();
        $delivery_key = 'test-delivery-key-123';
        $event = 'booking.confirmed';

        $headers = $this->signer->generateHeaders($body, $secret, $timestamp, $delivery_key, $event);

        // Check all required headers are present
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('X-Minpaku-Signature', $headers);
        $this->assertArrayHasKey('X-Minpaku-Timestamp', $headers);
        $this->assertArrayHasKey('X-Minpaku-Delivery', $headers);
        $this->assertArrayHasKey('X-Minpaku-Event', $headers);

        // Check header values
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals($timestamp, $headers['X-Minpaku-Timestamp']);
        $this->assertEquals($delivery_key, $headers['X-Minpaku-Delivery']);
        $this->assertEquals($event, $headers['X-Minpaku-Event']);

        // Verify signature format
        $this->assertStringStartsWith('sha256=', $headers['X-Minpaku-Signature']);

        // Verify signature is valid
        $is_valid = $this->signer->verify($body, $headers['X-Minpaku-Signature'], $secret, $timestamp);
        $this->assertTrue($is_valid);
    }

    /**
     * Test signature format is correct
     */
    public function testSignatureFormatIsCorrect() {
        $body = '{"format":"test"}';
        $secret = 'format_test_secret';
        $timestamp = time();

        $signature = $this->signer->sign($body, $secret, $timestamp);

        // Should start with 'sha256='
        $this->assertStringStartsWith('sha256=', $signature);

        // Should be followed by 64 character hex string
        $hash_part = substr($signature, 7); // Remove 'sha256=' prefix
        $this->assertEquals(64, strlen($hash_part));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash_part);
    }

    /**
     * Test empty body handling
     */
    public function testEmptyBodyHandling() {
        $empty_body = '';
        $secret = 'empty_body_secret';
        $timestamp = time();

        $signature = $this->signer->sign($empty_body, $secret, $timestamp);
        $is_valid = $this->signer->verify($empty_body, $signature, $secret, $timestamp);

        $this->assertTrue($is_valid);
        $this->assertStringStartsWith('sha256=', $signature);
    }

    /**
     * Test signature with special characters in body
     */
    public function testSignatureWithSpecialCharacters() {
        $body = '{"special":"データテスト","unicode":"🎉","newlines":"line1\nline2\r\nline3"}';
        $secret = 'special_chars_secret';
        $timestamp = time();

        $signature = $this->signer->sign($body, $secret, $timestamp);
        $is_valid = $this->signer->verify($body, $signature, $secret, $timestamp);

        $this->assertTrue($is_valid);
    }

    /**
     * Test large payload signing
     */
    public function testLargePayloadSigning() {
        // Create a large payload (1MB)
        $large_data = str_repeat('A', 1024 * 1024);
        $body = json_encode(['large_data' => $large_data]);
        $secret = 'large_payload_secret';
        $timestamp = time();

        $signature = $this->signer->sign($body, $secret, $timestamp);
        $is_valid = $this->signer->verify($body, $signature, $secret, $timestamp);

        $this->assertTrue($is_valid);
    }

    /**
     * Test different secret lengths
     */
    public function testDifferentSecretLengths() {
        $body = '{"test":"secret_length"}';
        $timestamp = time();

        $test_secrets = [
            'short',
            'medium_length_secret_123',
            'very_long_secret_that_contains_many_characters_and_numbers_123456789_abcdefghijklmnopqrstuvwxyz'
        ];

        foreach ($test_secrets as $secret) {
            $signature = $this->signer->sign($body, $secret, $timestamp);
            $is_valid = $this->signer->verify($body, $signature, $secret, $timestamp);

            $this->assertTrue($is_valid, "Failed with secret length: " . strlen($secret));
        }
    }

    /**
     * Test signature verification with malformed signature
     */
    public function testSignatureVerificationWithMalformedSignature() {
        $body = '{"test":"malformed_signature"}';
        $secret = 'malformed_test_secret';
        $timestamp = time();

        $malformed_signatures = [
            'invalid_format',
            'sha256=',
            'sha256=invalid_hex',
            'sha256=too_short',
            'md5=valid_hash_wrong_algorithm',
            '',
            null
        ];

        foreach ($malformed_signatures as $malformed_signature) {
            $is_valid = $this->signer->verify($body, $malformed_signature, $secret, $timestamp);
            $this->assertFalse($is_valid, "Should reject malformed signature: " . var_export($malformed_signature, true));
        }
    }

    /**
     * Test signature consistency across multiple calls
     */
    public function testSignatureConsistencyAcrossMultipleCalls() {
        $body = '{"consistency":"test"}';
        $secret = 'consistency_secret';
        $timestamp = 1640995200; // Fixed timestamp

        // Generate signature multiple times
        $signatures = [];
        for ($i = 0; $i < 5; $i++) {
            $signatures[] = $this->signer->sign($body, $secret, $timestamp);
        }

        // All signatures should be identical
        $first_signature = $signatures[0];
        foreach ($signatures as $signature) {
            $this->assertEquals($first_signature, $signature);
        }
    }

    /**
     * Test header generation with null timestamp uses current time
     */
    public function testHeaderGenerationWithNullTimestamp() {
        $body = '{"test":"null_timestamp"}';
        $secret = 'null_timestamp_secret';
        $delivery_key = 'test-key';
        $event = 'test.event';

        $before_time = time();
        $headers = $this->signer->generateHeaders($body, $secret, null, $delivery_key, $event);
        $after_time = time();

        $timestamp = $headers['X-Minpaku-Timestamp'];

        // Timestamp should be between before and after time
        $this->assertGreaterThanOrEqual($before_time, $timestamp);
        $this->assertLessThanOrEqual($after_time, $timestamp);

        // Signature should be valid
        $is_valid = $this->signer->verify($body, $headers['X-Minpaku-Signature'], $secret, $timestamp);
        $this->assertTrue($is_valid);
    }

    /**
     * Mock WordPress environment for testing
     */
    private function mockWordPressEnvironment() {
        if (!function_exists('current_time')) {
            function current_time($type) {
                return $type === 'mysql' ? date('Y-m-d H:i:s') :
                       ($type === 'c' ? date('c') : time());
            }
        }

        if (!class_exists('MCS_Logger')) {
            class MCS_Logger {
                public static function log($level, $message, $data = []) {
                    // Mock logger
                }
            }
        }
    }
}