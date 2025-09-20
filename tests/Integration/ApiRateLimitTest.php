<?php
/**
 * API Rate Limit Integration Tests
 * Tests rate limiting functionality with IP and API key scenarios
 *
 * @package MinpakuSuite
 */

use PHPUnit\Framework\TestCase;

class ApiRateLimitTest extends TestCase {

    /**
     * Rate limiter instance
     */
    private $rate_limiter;

    /**
     * API key manager instance
     */
    private $api_key_manager;

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();

        // Mock WordPress environment if not available
        if (!defined('ABSPATH')) {
            $this->mockWordPressEnvironment();
        }

        // Load required classes
        require_once __DIR__ . '/../../includes/Api/RateLimiter.php';
        require_once __DIR__ . '/../../includes/Api/ApiKeyManager.php';

        $this->rate_limiter = new RateLimiter();
        $this->api_key_manager = new ApiKeyManager();
    }

    /**
     * Test basic IP-based rate limiting
     */
    public function testBasicIpRateLimiting() {
        $bucket = 'api:availability';
        $limit = 5;
        $window = 60;

        // First 5 requests should be allowed
        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue(
                $this->rate_limiter->allow($bucket, $limit, $window),
                "Request $i should be allowed"
            );
            $this->rate_limiter->record($bucket);
        }

        // 6th request should be denied
        $this->assertFalse(
            $this->rate_limiter->allow($bucket, $limit, $window),
            "Request exceeding limit should be denied"
        );
    }

    /**
     * Test rate limiting with API key
     */
    public function testApiKeyRateLimiting() {
        $bucket = 'api:quote';
        $api_key = 'mk_test_api_key_12345678901234567890';

        // Mock API key data
        $this->mockApiKey($api_key);

        $limit = 3;
        $window = 60;

        // Test with API key
        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue(
                $this->rate_limiter->allow($bucket, $limit, $window, 'apikey:' . $api_key),
                "API key request $i should be allowed"
            );
            $this->rate_limiter->record($bucket, 'apikey:' . $api_key);
        }

        // Exceeding limit should be denied
        $this->assertFalse(
            $this->rate_limiter->allow($bucket, $limit, $window, 'apikey:' . $api_key),
            "API key request exceeding limit should be denied"
        );

        // Different API key should have separate limit
        $different_key = 'mk_different_key_12345678901234567890';
        $this->assertTrue(
            $this->rate_limiter->allow($bucket, $limit, $window, 'apikey:' . $different_key),
            "Different API key should have separate rate limit"
        );
    }

    /**
     * Test rate limit window expiration
     */
    public function testRateLimitWindowExpiration() {
        $bucket = 'api:availability';
        $limit = 2;
        $window = 1; // 1 second window for fast testing

        // Use up the limit
        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue($this->rate_limiter->allow($bucket, $limit, $window));
            $this->rate_limiter->record($bucket);
        }

        // Should be denied
        $this->assertFalse($this->rate_limiter->allow($bucket, $limit, $window));

        // Wait for window to expire
        sleep(2);

        // Should be allowed again
        $this->assertTrue(
            $this->rate_limiter->allow($bucket, $limit, $window),
            "Request should be allowed after window expires"
        );
    }

    /**
     * Test retry after calculation
     */
    public function testRetryAfterCalculation() {
        $bucket = 'api:quote';
        $limit = 1;
        $window = 10;

        // Use up the limit
        $this->assertTrue($this->rate_limiter->allow($bucket, $limit, $window));
        $this->rate_limiter->record($bucket);

        // Should be denied
        $this->assertFalse($this->rate_limiter->allow($bucket, $limit, $window));

        // Check retry after
        $retry_after = $this->rate_limiter->getRetryAfter($bucket);
        $this->assertGreaterThan(0, $retry_after);
        $this->assertLessThanOrEqual($window, $retry_after);
    }

    /**
     * Test different buckets have separate limits
     */
    public function testSeparateBucketLimits() {
        $bucket1 = 'api:availability';
        $bucket2 = 'api:quote';
        $limit = 2;
        $window = 60;

        // Use up limit for bucket1
        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue($this->rate_limiter->allow($bucket1, $limit, $window));
            $this->rate_limiter->record($bucket1);
        }

        // bucket1 should be exhausted
        $this->assertFalse($this->rate_limiter->allow($bucket1, $limit, $window));

        // bucket2 should still be available
        $this->assertTrue(
            $this->rate_limiter->allow($bucket2, $limit, $window),
            "Different bucket should have separate rate limit"
        );
    }

    /**
     * Test current count tracking
     */
    public function testCurrentCountTracking() {
        $bucket = 'api:availability';
        $limit = 5;
        $window = 60;

        // Initially should be 0
        $this->assertEquals(0, $this->rate_limiter->getCurrentCount($bucket));

        // Make some requests
        for ($i = 1; $i <= 3; $i++) {
            $this->assertTrue($this->rate_limiter->allow($bucket, $limit, $window));
            $this->rate_limiter->record($bucket);
            $this->assertEquals($i, $this->rate_limiter->getCurrentCount($bucket));
        }
    }

    /**
     * Test clear rate limit functionality
     */
    public function testClearRateLimit() {
        $bucket = 'api:quote';
        $limit = 1;
        $window = 60;

        // Use up the limit
        $this->assertTrue($this->rate_limiter->allow($bucket, $limit, $window));
        $this->rate_limiter->record($bucket);

        // Should be denied
        $this->assertFalse($this->rate_limiter->allow($bucket, $limit, $window));

        // Clear the rate limit
        $this->rate_limiter->clear($bucket);

        // Should be allowed again
        $this->assertTrue(
            $this->rate_limiter->allow($bucket, $limit, $window),
            "Request should be allowed after clearing rate limit"
        );
    }

    /**
     * Test IP address extraction
     */
    public function testIpAddressExtraction() {
        // Mock different IP scenarios
        $test_cases = [
            ['REMOTE_ADDR' => '192.168.1.1'],
            ['HTTP_X_FORWARDED_FOR' => '203.0.113.1, 192.168.1.1'],
            ['HTTP_CF_CONNECTING_IP' => '203.0.113.2']
        ];

        foreach ($test_cases as $case) {
            $this->mockServerVars($case);

            // The rate limiter should handle different IP scenarios
            $bucket = 'api:availability';
            $this->assertTrue(
                $this->rate_limiter->allow($bucket),
                "Rate limiter should work with different IP configurations"
            );
        }
    }

    /**
     * Test API key format validation
     */
    public function testApiKeyFormatValidation() {
        $valid_key = 'mk_valid_key_12345678901234567890123';
        $invalid_keys = [
            'invalid_prefix_key',
            'mk_',
            'mk_too_short',
            'mk_invalid_chars_!@#$%^&*()',
            ''
        ];

        // Valid key should work
        $bucket = 'api:availability';
        $this->assertTrue(
            $this->rate_limiter->allow($bucket, null, null, 'apikey:' . $valid_key),
            "Valid API key should be accepted"
        );

        // Invalid keys should fall back to IP-based limiting
        foreach ($invalid_keys as $invalid_key) {
            $this->assertTrue(
                $this->rate_limiter->allow($bucket, null, null, 'apikey:' . $invalid_key),
                "Invalid API key should fall back to IP limiting"
            );
        }
    }

    /**
     * Test concurrent requests from different sources
     */
    public function testConcurrentRequests() {
        $bucket = 'api:availability';
        $limit = 3;
        $window = 60;

        $ip1_key = 'ip:192.168.1.1';
        $ip2_key = 'ip:192.168.1.2';
        $api_key = 'apikey:mk_test_key_12345678901234567890';

        // Each source should have independent limits
        for ($i = 0; $i < $limit; $i++) {
            // IP 1
            $this->assertTrue($this->rate_limiter->allow($bucket, $limit, $window, $ip1_key));
            $this->rate_limiter->record($bucket, $ip1_key);

            // IP 2
            $this->assertTrue($this->rate_limiter->allow($bucket, $limit, $window, $ip2_key));
            $this->rate_limiter->record($bucket, $ip2_key);

            // API key
            $this->assertTrue($this->rate_limiter->allow($bucket, $limit, $window, $api_key));
            $this->rate_limiter->record($bucket, $api_key);
        }

        // All should now be at limit
        $this->assertFalse($this->rate_limiter->allow($bucket, $limit, $window, $ip1_key));
        $this->assertFalse($this->rate_limiter->allow($bucket, $limit, $window, $ip2_key));
        $this->assertFalse($this->rate_limiter->allow($bucket, $limit, $window, $api_key));
    }

    /**
     * Test rate limit with WordPress transients fallback
     */
    public function testTransientsFallback() {
        // Disable object cache to test transients
        $this->mockObjectCacheDisabled();

        $bucket = 'api:availability';
        $limit = 2;
        $window = 60;

        // Should work with transients
        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue($this->rate_limiter->allow($bucket, $limit, $window));
            $this->rate_limiter->record($bucket);
        }

        $this->assertFalse($this->rate_limiter->allow($bucket, $limit, $window));
    }

    /**
     * Mock API key for testing
     */
    private function mockApiKey($api_key) {
        // In a real test, this would set up the API key in the database
        // For this unit test, we just need the key format to be valid
    }

    /**
     * Mock server variables
     */
    private function mockServerVars($vars) {
        foreach ($vars as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }

    /**
     * Mock object cache disabled
     */
    private function mockObjectCacheDisabled() {
        // Mock wp_cache_get to return false
        if (!function_exists('wp_cache_get')) {
            function wp_cache_get($key, $group = '') {
                return false;
            }
        }
    }

    /**
     * Mock WordPress environment for testing
     */
    private function mockWordPressEnvironment() {
        if (!function_exists('get_transient')) {
            function get_transient($key) {
                static $transients = [];
                return isset($transients[$key]) ? $transients[$key] : false;
            }
        }

        if (!function_exists('set_transient')) {
            function set_transient($key, $value, $expiry) {
                static $transients = [];
                $transients[$key] = $value;
                return true;
            }
        }

        if (!function_exists('delete_transient')) {
            function delete_transient($key) {
                static $transients = [];
                unset($transients[$key]);
                return true;
            }
        }

        if (!function_exists('wp_cache_get')) {
            function wp_cache_get($key, $group = '') {
                return false; // Simulate no object cache
            }
        }

        if (!function_exists('wp_cache_set')) {
            function wp_cache_set($key, $value, $group = '', $expiry = 0) {
                return true;
            }
        }

        if (!function_exists('wp_cache_delete')) {
            function wp_cache_delete($key, $group = '') {
                return true;
            }
        }

        if (!class_exists('MCS_Logger')) {
            class MCS_Logger {
                public static function log($level, $message, $data = []) {
                    // Mock logger
                }
            }
        }

        // Mock server variables
        if (!isset($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
    }
}