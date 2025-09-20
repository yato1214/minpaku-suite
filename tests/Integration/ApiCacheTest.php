<?php
/**
 * API Cache Integration Tests
 * Tests response caching and invalidation functionality
 *
 * @package MinpakuSuite
 */

use PHPUnit\Framework\TestCase;

class ApiCacheTest extends TestCase {

    /**
     * Response cache instance
     */
    private $cache;

    /**
     * Cache invalidator instance
     */
    private $invalidator;

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
        require_once __DIR__ . '/../../includes/Api/ResponseCache.php';
        require_once __DIR__ . '/../../includes/Api/CacheInvalidator.php';

        $this->cache = new ResponseCache();
        $this->invalidator = new CacheInvalidator();
    }

    /**
     * Test basic cache put and get operations
     */
    public function testBasicCachePutAndGet() {
        $key = 'test:cache:key';
        $data = ['property_id' => 123, 'available' => true];
        $ttl = 60;

        // Put data in cache
        $this->cache->put($key, $data, $ttl);

        // Get data from cache
        $cached_data = $this->cache->get($key);

        $this->assertEquals($data, $cached_data);
    }

    /**
     * Test cache miss scenario
     */
    public function testCacheMiss() {
        $non_existent_key = 'non:existent:key';
        $cached_data = $this->cache->get($non_existent_key);

        $this->assertNull($cached_data);
    }

    /**
     * Test cache expiration
     */
    public function testCacheExpiration() {
        $key = 'test:expiring:key';
        $data = ['test' => 'data'];
        $ttl = 1; // 1 second TTL

        // Put data in cache
        $this->cache->put($key, $data, $ttl);

        // Should be available immediately
        $this->assertEquals($data, $this->cache->get($key));

        // Wait for expiration
        sleep(2);

        // Should be null after expiration
        $this->assertNull($this->cache->get($key));
    }

    /**
     * Test availability cache key generation
     */
    public function testAvailabilityCacheKeyGeneration() {
        $property_id = 123;
        $start_date = '2025-01-01';
        $end_date = '2025-01-31';
        $extra_params = ['param1' => 'value1'];

        $key = ResponseCache::availabilityKey($property_id, $start_date, $end_date, $extra_params);

        $this->assertStringContains('availability', $key);
        $this->assertStringContains('property:123', $key);
        $this->assertStringContains('range:2025-01-01:2025-01-31', $key);
    }

    /**
     * Test quote cache key generation
     */
    public function testQuoteCacheKeyGeneration() {
        $property_id = 456;
        $checkin_date = '2025-02-01';
        $checkout_date = '2025-02-05';
        $guests = ['adults' => 2, 'children' => 1];

        $key = ResponseCache::quoteKey($property_id, $checkin_date, $checkout_date, $guests);

        $this->assertStringContains('quote', $key);
        $this->assertStringContains('property:456', $key);
        $this->assertStringContains('dates:2025-02-01:2025-02-05', $key);
        $this->assertStringContains('guests:', $key);
    }

    /**
     * Test cache invalidation by pattern
     */
    public function testCacheInvalidationByPattern() {
        // Put multiple cache entries
        $property_id = 123;
        $keys = [
            ResponseCache::availabilityKey($property_id, '2025-01-01', '2025-01-31'),
            ResponseCache::availabilityKey($property_id, '2025-02-01', '2025-02-28'),
            ResponseCache::quoteKey($property_id, '2025-01-15', '2025-01-20', ['adults' => 2])
        ];

        $test_data = ['test' => 'data'];
        foreach ($keys as $key) {
            $this->cache->put($key, $test_data, 3600);
        }

        // Verify all are cached
        foreach ($keys as $key) {
            $this->assertEquals($test_data, $this->cache->get($key));
        }

        // Invalidate availability cache for this property
        $pattern = "availability:property:{$property_id}:*";
        $cleared_count = $this->cache->forget($pattern);

        $this->assertGreaterThan(0, $cleared_count);

        // Availability entries should be gone, quote should remain
        $this->assertNull($this->cache->get($keys[0])); // availability
        $this->assertNull($this->cache->get($keys[1])); // availability
        $this->assertEquals($test_data, $this->cache->get($keys[2])); // quote should remain
    }

    /**
     * Test cache statistics
     */
    public function testCacheStatistics() {
        // Put some test data
        $test_entries = [
            ['key' => 'availability:test1', 'data' => ['test' => 'data1'], 'type' => 'availability'],
            ['key' => 'quote:test1', 'data' => ['test' => 'data2'], 'type' => 'quote'],
            ['key' => 'webhook:test1', 'data' => ['test' => 'data3'], 'type' => 'webhook']
        ];

        foreach ($test_entries as $entry) {
            $this->cache->put($entry['key'], $entry['data'], 3600);
        }

        $stats = $this->cache->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_entries', $stats);
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertGreaterThanOrEqual(count($test_entries), $stats['total_entries']);
    }

    /**
     * Test cache clearing by type
     */
    public function testCacheClearingByType() {
        // Put entries of different types
        $availability_key = 'availability:property:123:range:2025-01-01:2025-01-31';
        $quote_key = 'quote:property:456:dates:2025-02-01:2025-02-05:guests:2-1';

        $this->cache->put($availability_key, ['test' => 'availability'], 3600);
        $this->cache->put($quote_key, ['test' => 'quote'], 3600);

        // Verify both are cached
        $this->assertNotNull($this->cache->get($availability_key));
        $this->assertNotNull($this->cache->get($quote_key));

        // Clear only availability cache
        $cleared_count = $this->cache->clear('availability');
        $this->assertGreaterThan(0, $cleared_count);

        // Availability should be gone, quote should remain
        $this->assertNull($this->cache->get($availability_key));
        $this->assertNotNull($this->cache->get($quote_key));
    }

    /**
     * Test cache invalidation on property save
     */
    public function testCacheInvalidationOnPropertySave() {
        $property_id = 789;

        // Put some cache entries for this property
        $availability_key = ResponseCache::availabilityKey($property_id, '2025-01-01', '2025-01-31');
        $quote_key = ResponseCache::quoteKey($property_id, '2025-01-15', '2025-01-20', ['adults' => 2]);

        $this->cache->put($availability_key, ['test' => 'availability'], 3600);
        $this->cache->put($quote_key, ['test' => 'quote'], 3600);

        // Verify cached
        $this->assertNotNull($this->cache->get($availability_key));
        $this->assertNotNull($this->cache->get($quote_key));

        // Simulate property save
        $this->invalidator->invalidatePropertyCache($property_id);

        // Both should be invalidated
        $this->assertNull($this->cache->get($availability_key));
        $this->assertNull($this->cache->get($quote_key));
    }

    /**
     * Test cache invalidation on booking state change
     */
    public function testCacheInvalidationOnBookingStateChange() {
        $property_id = 101;
        $checkin_date = '2025-03-01';
        $checkout_date = '2025-03-05';

        // Put cache entries
        $availability_key = ResponseCache::availabilityKey($property_id, '2025-03-01', '2025-03-31');
        $quote_key = ResponseCache::quoteKey($property_id, $checkin_date, $checkout_date, ['adults' => 2]);

        $this->cache->put($availability_key, ['test' => 'availability'], 3600);
        $this->cache->put($quote_key, ['test' => 'quote'], 3600);

        // Simulate booking state change
        $booking = (object) [
            'id' => 123,
            'property_id' => $property_id,
            'checkin_date' => $checkin_date,
            'checkout_date' => $checkout_date
        ];

        // Manually call the invalidation method
        $this->invalidator->invalidateAvailabilityCache($property_id, $checkin_date, $checkout_date);
        $this->invalidator->invalidateQuoteCache($property_id);

        // Cache should be invalidated
        $this->assertNull($this->cache->get($availability_key));
        $this->assertNull($this->cache->get($quote_key));
    }

    /**
     * Test cache with metadata
     */
    public function testCacheWithMetadata() {
        $key = 'test:metadata:key';
        $data = ['property' => 'data'];
        $meta = ['property_id' => 123, 'date_range' => '2025-01-01:2025-01-31'];

        $this->cache->put($key, $data, 3600, $meta);

        $cached_data = $this->cache->get($key);
        $this->assertEquals($data, $cached_data);
    }

    /**
     * Test cache size estimation
     */
    public function testCacheSizeEstimation() {
        $small_data = ['small' => 'data'];
        $large_data = ['large' => str_repeat('data', 1000)];

        $this->cache->put('small:key', $small_data, 3600);
        $this->cache->put('large:key', $large_data, 3600);

        $stats = $this->cache->getStats();
        $this->assertGreaterThan(0, $stats['total_size']);
    }

    /**
     * Test webhook cache key generation
     */
    public function testWebhookCacheKeyGeneration() {
        $endpoint = 'deliveries';
        $params = ['status' => 'success', 'limit' => 50];

        $key = ResponseCache::webhookKey($endpoint, $params);

        $this->assertStringContains('webhook', $key);
        $this->assertStringContains('endpoint:deliveries', $key);
    }

    /**
     * Test cache access time update
     */
    public function testCacheAccessTimeUpdate() {
        $key = 'test:access:key';
        $data = ['test' => 'access'];

        $this->cache->put($key, $data, 3600);

        // First access
        $first_access = $this->cache->get($key);
        $this->assertEquals($data, $first_access);

        // Second access should update access time
        $second_access = $this->cache->get($key);
        $this->assertEquals($data, $second_access);
    }

    /**
     * Test cache with WordPress object cache integration
     */
    public function testWordPressObjectCacheIntegration() {
        // Mock object cache functions
        $this->mockObjectCache();

        $key = 'test:object:cache';
        $data = ['object' => 'cache'];

        $this->cache->put($key, $data, 3600);
        $cached_data = $this->cache->get($key);

        $this->assertEquals($data, $cached_data);
    }

    /**
     * Test transients fallback when object cache unavailable
     */
    public function testTransientsFallback() {
        // Ensure object cache functions return false
        $this->mockObjectCacheDisabled();

        $key = 'test:transient:fallback';
        $data = ['transient' => 'fallback'];

        $this->cache->put($key, $data, 3600);
        $cached_data = $this->cache->get($key);

        $this->assertEquals($data, $cached_data);
    }

    /**
     * Mock object cache functions
     */
    private function mockObjectCache() {
        static $object_cache = [];

        if (!function_exists('wp_cache_get')) {
            function wp_cache_get($key, $group = '') {
                global $object_cache;
                $cache_key = $group . ':' . $key;
                return isset($object_cache[$cache_key]) ? $object_cache[$cache_key] : false;
            }
        }

        if (!function_exists('wp_cache_set')) {
            function wp_cache_set($key, $value, $group = '', $expiry = 0) {
                global $object_cache;
                $cache_key = $group . ':' . $key;
                $object_cache[$cache_key] = $value;
                return true;
            }
        }

        if (!function_exists('wp_cache_delete')) {
            function wp_cache_delete($key, $group = '') {
                global $object_cache;
                $cache_key = $group . ':' . $key;
                unset($object_cache[$cache_key]);
                return true;
            }
        }
    }

    /**
     * Mock object cache disabled
     */
    private function mockObjectCacheDisabled() {
        if (!function_exists('wp_cache_get')) {
            function wp_cache_get($key, $group = '') {
                return false;
            }
        }

        if (!function_exists('wp_cache_set')) {
            function wp_cache_set($key, $value, $group = '', $expiry = 0) {
                return false;
            }
        }

        if (!function_exists('wp_cache_delete')) {
            function wp_cache_delete($key, $group = '') {
                return false;
            }
        }
    }

    /**
     * Mock WordPress environment for testing
     */
    private function mockWordPressEnvironment() {
        static $transients = [];

        if (!function_exists('get_transient')) {
            function get_transient($key) {
                global $transients;
                if (isset($transients[$key])) {
                    $data = $transients[$key];
                    if ($data['expires'] > time()) {
                        return $data['value'];
                    } else {
                        unset($transients[$key]);
                    }
                }
                return false;
            }
        }

        if (!function_exists('set_transient')) {
            function set_transient($key, $value, $expiry) {
                global $transients;
                $transients[$key] = [
                    'value' => $value,
                    'expires' => time() + $expiry
                ];
                return true;
            }
        }

        if (!function_exists('delete_transient')) {
            function delete_transient($key) {
                global $transients;
                unset($transients[$key]);
                return true;
            }
        }

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

        // Mock WordPress database functions
        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new stdClass();
            $GLOBALS['wpdb']->get_col = function($query) {
                return [];
            };
            $GLOBALS['wpdb']->prepare = function($query, ...$args) {
                return vsprintf(str_replace('%s', "'%s'", $query), $args);
            };
        }
    }
}