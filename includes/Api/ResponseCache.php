<?php
/**
 * Response Cache for API Endpoints
 * Implements response caching with TTL and pattern-based invalidation
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class ResponseCache {

    /**
     * Default TTL values for different cache types
     */
    const DEFAULT_TTL = [
        'availability' => 90, // 90 seconds for availability data
        'quote' => 30, // 30 seconds for quote calculations
        'webhook' => 60, // 60 seconds for webhook admin data
    ];

    /**
     * Cache key prefix
     */
    const CACHE_PREFIX = 'minpaku_api_cache:';

    /**
     * Cache group for WordPress object cache
     */
    const CACHE_GROUP = 'minpaku_api';

    /**
     * Get cached response
     *
     * @param string $key Cache key
     * @return array|null Cached data or null if not found/expired
     */
    public function get($key) {
        $cache_key = $this->normalizeCacheKey($key);
        $cached_data = $this->getFromCache($cache_key);

        if ($cached_data === null) {
            return null;
        }

        // Check if data has expired
        if (isset($cached_data['expires_at']) && time() > $cached_data['expires_at']) {
            $this->delete($key);
            return null;
        }

        // Update access time for LRU-style cleanup
        $cached_data['accessed_at'] = time();
        $this->setInCache($cache_key, $cached_data, $this->getTtlFromData($cached_data));

        return $cached_data['data'] ?? null;
    }

    /**
     * Store response in cache
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int|null $ttl TTL in seconds (uses default if null)
     * @param array $meta Additional metadata for cache entry
     */
    public function put($key, $data, $ttl = null, $meta = []) {
        $cache_key = $this->normalizeCacheKey($key);
        $cache_type = $this->extractCacheType($key);
        $actual_ttl = $ttl ?? $this->getDefaultTtl($cache_type);

        $cache_entry = [
            'data' => $data,
            'created_at' => time(),
            'accessed_at' => time(),
            'expires_at' => time() + $actual_ttl,
            'ttl' => $actual_ttl,
            'type' => $cache_type,
            'key' => $key,
            'meta' => $meta,
            'size' => $this->estimateSize($data)
        ];

        $this->setInCache($cache_key, $cache_entry, $actual_ttl + 60); // Add 60s buffer for cleanup

        // Log cache miss for debugging
        if (defined('WP_DEBUG') && WP_DEBUG && class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'API cache stored', [
                'key' => $key,
                'type' => $cache_type,
                'ttl' => $actual_ttl,
                'size' => $cache_entry['size']
            ]);
        }
    }

    /**
     * Delete specific cache entry
     *
     * @param string $key Cache key
     */
    public function delete($key) {
        $cache_key = $this->normalizeCacheKey($key);
        $this->deleteFromCache($cache_key);
    }

    /**
     * Invalidate cache entries by pattern
     *
     * @param string $pattern Pattern to match (supports wildcards)
     * @return int Number of entries invalidated
     */
    public function forget($pattern) {
        $normalized_pattern = $this->normalizeCacheKey($pattern);
        $deleted_count = 0;

        // Get all cache keys that match the pattern
        $matching_keys = $this->findMatchingKeys($normalized_pattern);

        foreach ($matching_keys as $cache_key) {
            $this->deleteFromCache($cache_key);
            $deleted_count++;
        }

        if ($deleted_count > 0 && class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'API cache invalidated', [
                'pattern' => $pattern,
                'deleted_count' => $deleted_count
            ]);
        }

        return $deleted_count;
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function getStats() {
        $stats = [
            'total_entries' => 0,
            'total_size' => 0,
            'by_type' => [],
            'oldest_entry' => null,
            'newest_entry' => null
        ];

        $all_keys = $this->getAllCacheKeys();

        foreach ($all_keys as $cache_key) {
            $entry = $this->getFromCache($cache_key);
            if (!$entry || !isset($entry['type'])) {
                continue;
            }

            $stats['total_entries']++;
            $stats['total_size'] += $entry['size'] ?? 0;

            $type = $entry['type'];
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = ['count' => 0, 'size' => 0];
            }
            $stats['by_type'][$type]['count']++;
            $stats['by_type'][$type]['size'] += $entry['size'] ?? 0;

            // Track oldest and newest
            $created_at = $entry['created_at'] ?? 0;
            if ($stats['oldest_entry'] === null || $created_at < $stats['oldest_entry']) {
                $stats['oldest_entry'] = $created_at;
            }
            if ($stats['newest_entry'] === null || $created_at > $stats['newest_entry']) {
                $stats['newest_entry'] = $created_at;
            }
        }

        return $stats;
    }

    /**
     * Clear all cache entries
     *
     * @param string|null $type Optional type filter
     * @return int Number of entries cleared
     */
    public function clear($type = null) {
        $cleared_count = 0;
        $all_keys = $this->getAllCacheKeys();

        foreach ($all_keys as $cache_key) {
            if ($type !== null) {
                $entry = $this->getFromCache($cache_key);
                if (!$entry || ($entry['type'] ?? '') !== $type) {
                    continue;
                }
            }

            $this->deleteFromCache($cache_key);
            $cleared_count++;
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'API cache cleared', [
                'type' => $type,
                'cleared_count' => $cleared_count
            ]);
        }

        return $cleared_count;
    }

    /**
     * Generate cache key for availability data
     *
     * @param int $property_id Property ID
     * @param string $start_date Start date (Y-m-d format)
     * @param string $end_date End date (Y-m-d format)
     * @param array $extra_params Additional parameters
     * @return string Cache key
     */
    public static function availabilityKey($property_id, $start_date, $end_date, $extra_params = []) {
        $key_parts = [
            'availability',
            'property:' . $property_id,
            'range:' . $start_date . ':' . $end_date
        ];

        if (!empty($extra_params)) {
            ksort($extra_params);
            $key_parts[] = 'params:' . md5(serialize($extra_params));
        }

        return implode(':', $key_parts);
    }

    /**
     * Generate cache key for quote data
     *
     * @param int $property_id Property ID
     * @param string $checkin_date Check-in date (Y-m-d format)
     * @param string $checkout_date Check-out date (Y-m-d format)
     * @param array $guests Guest count array
     * @param array $extra_params Additional parameters
     * @return string Cache key
     */
    public static function quoteKey($property_id, $checkin_date, $checkout_date, $guests, $extra_params = []) {
        $guest_string = '';
        if (is_array($guests)) {
            ksort($guests);
            $guest_string = 'guests:' . implode('-', $guests);
        }

        $key_parts = [
            'quote',
            'property:' . $property_id,
            'dates:' . $checkin_date . ':' . $checkout_date,
            $guest_string
        ];

        if (!empty($extra_params)) {
            ksort($extra_params);
            $key_parts[] = 'params:' . md5(serialize($extra_params));
        }

        return implode(':', $key_parts);
    }

    /**
     * Generate cache key for webhook admin data
     *
     * @param string $endpoint Endpoint identifier
     * @param array $params Parameters
     * @return string Cache key
     */
    public static function webhookKey($endpoint, $params = []) {
        $key_parts = ['webhook', 'endpoint:' . $endpoint];

        if (!empty($params)) {
            ksort($params);
            $key_parts[] = 'params:' . md5(serialize($params));
        }

        return implode(':', $key_parts);
    }

    /**
     * Normalize cache key
     *
     * @param string $key Original key
     * @return string Normalized key
     */
    private function normalizeCacheKey($key) {
        return self::CACHE_PREFIX . md5($key);
    }

    /**
     * Extract cache type from key
     *
     * @param string $key Cache key
     * @return string Cache type
     */
    private function extractCacheType($key) {
        $parts = explode(':', $key);
        return $parts[0] ?? 'unknown';
    }

    /**
     * Get default TTL for cache type
     *
     * @param string $type Cache type
     * @return int TTL in seconds
     */
    private function getDefaultTtl($type) {
        $settings = get_option('minpaku_api_settings', []);
        $custom_ttl = $settings['cache_ttl'][$type] ?? null;

        return $custom_ttl ?? self::DEFAULT_TTL[$type] ?? self::DEFAULT_TTL['availability'];
    }

    /**
     * Get TTL from cached data
     *
     * @param array $cached_data Cached data entry
     * @return int TTL in seconds
     */
    private function getTtlFromData($cached_data) {
        $expires_at = $cached_data['expires_at'] ?? 0;
        $now = time();
        return max(0, $expires_at - $now);
    }

    /**
     * Estimate size of data in bytes
     *
     * @param mixed $data Data to estimate
     * @return int Estimated size in bytes
     */
    private function estimateSize($data) {
        if (is_string($data)) {
            return strlen($data);
        }

        return strlen(serialize($data));
    }

    /**
     * Get value from cache storage
     *
     * @param string $cache_key Normalized cache key
     * @return mixed|null Cached value or null
     */
    private function getFromCache($cache_key) {
        // Try WordPress object cache first
        if (function_exists('wp_cache_get')) {
            $value = wp_cache_get($cache_key, self::CACHE_GROUP);
            if ($value !== false) {
                return $value;
            }
        }

        // Fall back to transients
        $value = get_transient($cache_key);
        return $value !== false ? $value : null;
    }

    /**
     * Set value in cache storage
     *
     * @param string $cache_key Normalized cache key
     * @param mixed $value Value to store
     * @param int $expiry Expiry time in seconds
     */
    private function setInCache($cache_key, $value, $expiry) {
        // Try WordPress object cache first
        if (function_exists('wp_cache_set')) {
            wp_cache_set($cache_key, $value, self::CACHE_GROUP, $expiry);
        }

        // Always set transient as fallback
        set_transient($cache_key, $value, $expiry);
    }

    /**
     * Delete value from cache storage
     *
     * @param string $cache_key Normalized cache key
     */
    private function deleteFromCache($cache_key) {
        // Try WordPress object cache first
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($cache_key, self::CACHE_GROUP);
        }

        // Delete transient
        delete_transient($cache_key);
    }

    /**
     * Find cache keys matching pattern
     *
     * @param string $pattern Pattern with wildcards
     * @return array Matching cache keys
     */
    private function findMatchingKeys($pattern) {
        // This is a simplified implementation
        // In production, you might want to maintain a registry of cache keys
        global $wpdb;

        $pattern_sql = str_replace('*', '%', $pattern);
        $transient_pattern = '_transient_' . $pattern_sql;

        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $transient_pattern
        ));

        $matching_keys = [];
        foreach ($results as $option_name) {
            if (strpos($option_name, '_transient_') === 0) {
                $matching_keys[] = substr($option_name, 11); // Remove '_transient_' prefix
            }
        }

        return $matching_keys;
    }

    /**
     * Get all cache keys
     *
     * @return array All cache keys
     */
    private function getAllCacheKeys() {
        global $wpdb;

        $prefix_pattern = '_transient_' . self::CACHE_PREFIX . '%';

        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $prefix_pattern
        ));

        $cache_keys = [];
        foreach ($results as $option_name) {
            if (strpos($option_name, '_transient_') === 0) {
                $cache_keys[] = substr($option_name, 11); // Remove '_transient_' prefix
            }
        }

        return $cache_keys;
    }
}