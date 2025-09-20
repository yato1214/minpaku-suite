<?php
/**
 * Rate Limiter for API Endpoints
 * Implements IP-based and API key-based rate limiting with sliding window
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class RateLimiter {

    /**
     * Default rate limits per bucket
     */
    const DEFAULT_LIMITS = [
        'api:availability' => ['limit' => 60, 'window' => 60], // 60 requests per minute
        'api:quote' => ['limit' => 30, 'window' => 60], // 30 requests per minute
        'api:webhook' => ['limit' => 100, 'window' => 60], // 100 requests per minute for admin
    ];

    /**
     * Sample rate for logging violations (1 in N requests)
     */
    const LOG_SAMPLE_RATE = 10;

    /**
     * Check if request is allowed under rate limit
     *
     * @param string $bucket Rate limit bucket (e.g., 'api:availability')
     * @param int|null $limit Optional custom limit (uses default if null)
     * @param int|null $window_sec Optional custom window (uses default if null)
     * @param string|null $key Optional custom key (uses IP if null)
     * @return bool True if request is allowed
     */
    public function allow($bucket, $limit = null, $window_sec = null, $key = null) {
        $config = $this->getBucketConfig($bucket, $limit, $window_sec);
        $identifier = $this->getIdentifier($bucket, $key);

        $cache_key = $this->getCacheKey($bucket, $identifier);
        $current_count = $this->getCurrentCount($cache_key, $config['window']);

        $allowed = $current_count < $config['limit'];

        if (!$allowed) {
            $this->logViolation($bucket, $identifier, $current_count, $config['limit']);
        }

        return $allowed;
    }

    /**
     * Record a request for rate limiting
     *
     * @param string $bucket Rate limit bucket
     * @param string|null $key Optional custom key (uses IP if null)
     */
    public function record($bucket, $key = null) {
        $identifier = $this->getIdentifier($bucket, $key);
        $cache_key = $this->getCacheKey($bucket, $identifier);

        $this->incrementCount($cache_key);
    }

    /**
     * Get time until rate limit resets
     *
     * @param string $bucket Rate limit bucket
     * @param string|null $key Optional custom key
     * @return int Seconds until reset
     */
    public function getRetryAfter($bucket, $key = null) {
        $config = $this->getBucketConfig($bucket);
        $identifier = $this->getIdentifier($bucket, $key);
        $cache_key = $this->getCacheKey($bucket, $identifier);

        $window_start = $this->getWindowStart($cache_key);
        if (!$window_start) {
            return 0;
        }

        $window_end = $window_start + $config['window'];
        $now = time();

        return max(0, $window_end - $now);
    }

    /**
     * Get current request count for identifier
     *
     * @param string $bucket Rate limit bucket
     * @param string|null $key Optional custom key
     * @return int Current request count
     */
    public function getCurrentCount($bucket, $key = null) {
        $config = $this->getBucketConfig($bucket);
        $identifier = $this->getIdentifier($bucket, $key);
        $cache_key = $this->getCacheKey($bucket, $identifier);

        return $this->getCurrentCountInternal($cache_key, $config['window']);
    }

    /**
     * Clear rate limit for identifier (admin function)
     *
     * @param string $bucket Rate limit bucket
     * @param string|null $key Optional custom key
     */
    public function clear($bucket, $key = null) {
        $identifier = $this->getIdentifier($bucket, $key);
        $cache_key = $this->getCacheKey($bucket, $identifier);

        $this->deleteCache($cache_key);
        $this->deleteCache($cache_key . ':start');
    }

    /**
     * Get bucket configuration
     *
     * @param string $bucket Bucket name
     * @param int|null $limit Custom limit
     * @param int|null $window_sec Custom window
     * @return array Configuration array
     */
    private function getBucketConfig($bucket, $limit = null, $window_sec = null) {
        $defaults = self::DEFAULT_LIMITS[$bucket] ?? self::DEFAULT_LIMITS['api:availability'];

        // Allow admin to override via settings
        $settings = get_option('minpaku_api_settings', []);
        $bucket_settings = $settings['rate_limits'][$bucket] ?? [];

        return [
            'limit' => $limit ?? $bucket_settings['limit'] ?? $defaults['limit'],
            'window' => $window_sec ?? $bucket_settings['window'] ?? $defaults['window']
        ];
    }

    /**
     * Get identifier for rate limiting (API key or IP)
     *
     * @param string $bucket Bucket name
     * @param string|null $key Custom key
     * @return string Identifier
     */
    private function getIdentifier($bucket, $key = null) {
        if ($key !== null) {
            return $key;
        }

        // Check for API key in headers
        $api_key = $this->getApiKeyFromRequest();
        if ($api_key) {
            return 'apikey:' . $api_key;
        }

        // Fall back to IP address
        return 'ip:' . $this->getClientIp();
    }

    /**
     * Get API key from request headers
     *
     * @return string|null API key if present
     */
    private function getApiKeyFromRequest() {
        $headers = getallheaders();
        if (!$headers) {
            return null;
        }

        // Check various header formats
        $possible_headers = [
            'X-Minpaku-Api-Key',
            'X-API-Key',
            'Authorization'
        ];

        foreach ($possible_headers as $header) {
            if (isset($headers[$header])) {
                $value = $headers[$header];

                // Handle Authorization: Bearer <key>
                if ($header === 'Authorization' && strpos($value, 'Bearer ') === 0) {
                    return substr($value, 7);
                }

                return $value;
            }
        }

        return null;
    }

    /**
     * Get client IP address
     *
     * @return string Client IP
     */
    private function getClientIp() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Generate cache key for rate limiting
     *
     * @param string $bucket Bucket name
     * @param string $identifier Identifier (IP or API key)
     * @return string Cache key
     */
    private function getCacheKey($bucket, $identifier) {
        return 'minpaku_rate_limit:' . $bucket . ':' . md5($identifier);
    }

    /**
     * Get current request count within window (internal method)
     *
     * @param string $cache_key Cache key
     * @param int $window_sec Window size in seconds
     * @return int Current count
     */
    private function getCurrentCountInternal($cache_key, $window_sec) {
        $now = time();
        $window_start = $this->getWindowStart($cache_key);

        // If no window start or window expired, reset
        if (!$window_start || ($now - $window_start) >= $window_sec) {
            return 0;
        }

        return (int) $this->getCache($cache_key, 0);
    }

    /**
     * Increment request count
     *
     * @param string $cache_key Cache key
     */
    private function incrementCount($cache_key) {
        $now = time();
        $window_start_key = $cache_key . ':start';
        $window_start = $this->getCache($window_start_key);

        // Start new window if needed
        if (!$window_start) {
            $this->setCache($window_start_key, $now, 3600); // 1 hour expiry
            $this->setCache($cache_key, 1, 3600);
        } else {
            $count = (int) $this->getCache($cache_key, 0);
            $this->setCache($cache_key, $count + 1, 3600);
        }
    }

    /**
     * Get window start time
     *
     * @param string $cache_key Base cache key
     * @return int|null Window start timestamp
     */
    private function getWindowStart($cache_key) {
        return $this->getCache($cache_key . ':start');
    }

    /**
     * Log rate limit violation
     *
     * @param string $bucket Bucket name
     * @param string $identifier Identifier
     * @param int $current_count Current request count
     * @param int $limit Rate limit
     */
    private function logViolation($bucket, $identifier, $current_count, $limit) {
        // Sample logging to avoid spam
        if (mt_rand(1, self::LOG_SAMPLE_RATE) !== 1) {
            return;
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Rate limit exceeded', [
                'bucket' => $bucket,
                'identifier' => substr($identifier, 0, 20) . '...', // Truncate for privacy
                'current_count' => $current_count,
                'limit' => $limit,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referer' => $_SERVER['HTTP_REFERER'] ?? ''
            ]);
        }
    }

    /**
     * Get value from cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value
     * @return mixed Cached value
     */
    private function getCache($key, $default = null) {
        // Try WordPress object cache first
        if (function_exists('wp_cache_get')) {
            $value = wp_cache_get($key, 'minpaku_rate_limit');
            if ($value !== false) {
                return $value;
            }
        }

        // Fall back to transients
        $value = get_transient($key);
        return $value !== false ? $value : $default;
    }

    /**
     * Set value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $expiry Expiry time in seconds
     */
    private function setCache($key, $value, $expiry) {
        // Try WordPress object cache first
        if (function_exists('wp_cache_set')) {
            wp_cache_set($key, $value, 'minpaku_rate_limit', $expiry);
        }

        // Always set transient as fallback
        set_transient($key, $value, $expiry);
    }

    /**
     * Delete value from cache
     *
     * @param string $key Cache key
     */
    private function deleteCache($key) {
        // Try WordPress object cache first
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($key, 'minpaku_rate_limit');
        }

        // Delete transient
        delete_transient($key);
    }
}