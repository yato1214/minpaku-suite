<?php
/**
 * API Key Manager
 * Manages API key generation, validation, and lifecycle
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class ApiKeyManager {

    /**
     * Option key for storing API keys
     */
    const OPTION_KEY = 'minpaku_api_keys';

    /**
     * Default API key length
     */
    const DEFAULT_KEY_LENGTH = 32;

    /**
     * API key prefix
     */
    const KEY_PREFIX = 'mk_';

    /**
     * Generate new API key
     *
     * @param string $name Human-readable name for the key
     * @param array $permissions Array of permissions (optional)
     * @param int|null $expires_at Expiration timestamp (null for no expiration)
     * @param int|null $user_id Associated user ID (optional)
     * @return array API key data
     */
    public function generateKey($name, $permissions = [], $expires_at = null, $user_id = null) {
        $key = $this->createRandomKey();
        $key_data = [
            'key' => $key,
            'name' => sanitize_text_field($name),
            'permissions' => $this->sanitizePermissions($permissions),
            'created_at' => time(),
            'created_by' => $user_id ?? get_current_user_id(),
            'expires_at' => $expires_at,
            'last_used_at' => null,
            'usage_count' => 0,
            'is_active' => true,
            'rate_limit_override' => null, // Can override default rate limits
            'ip_whitelist' => [], // Optional IP restrictions
            'user_agent_pattern' => null // Optional user agent restrictions
        ];

        $this->storeKey($key, $key_data);

        // Log key generation
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'API key generated', [
                'key_name' => $name,
                'created_by' => $key_data['created_by'],
                'permissions' => $permissions,
                'key_preview' => substr($key, 0, 8) . '...'
            ]);
        }

        return $key_data;
    }

    /**
     * Validate API key and return key data
     *
     * @param string $key API key to validate
     * @return array|null Key data if valid, null if invalid
     */
    public function validateKey($key) {
        if (!$this->isValidKeyFormat($key)) {
            return null;
        }

        $key_data = $this->getKeyData($key);
        if (!$key_data) {
            return null;
        }

        // Check if key is active
        if (!$key_data['is_active']) {
            return null;
        }

        // Check expiration
        if ($key_data['expires_at'] && time() > $key_data['expires_at']) {
            return null;
        }

        // Check IP whitelist if configured
        if (!empty($key_data['ip_whitelist'])) {
            $client_ip = $this->getClientIp();
            if (!in_array($client_ip, $key_data['ip_whitelist'])) {
                return null;
            }
        }

        // Check user agent pattern if configured
        if ($key_data['user_agent_pattern']) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (!preg_match($key_data['user_agent_pattern'], $user_agent)) {
                return null;
            }
        }

        // Update usage statistics
        $this->recordUsage($key);

        return $key_data;
    }

    /**
     * Revoke API key
     *
     * @param string $key API key to revoke
     * @return bool True if revoked, false if not found
     */
    public function revokeKey($key) {
        $key_data = $this->getKeyData($key);
        if (!$key_data) {
            return false;
        }

        $key_data['is_active'] = false;
        $key_data['revoked_at'] = time();
        $key_data['revoked_by'] = get_current_user_id();

        $this->storeKey($key, $key_data);

        // Log key revocation
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'API key revoked', [
                'key_name' => $key_data['name'],
                'revoked_by' => $key_data['revoked_by'],
                'key_preview' => substr($key, 0, 8) . '...'
            ]);
        }

        return true;
    }

    /**
     * Get all API keys (excluding the actual key values)
     *
     * @param bool $include_inactive Include inactive keys
     * @return array Array of key data
     */
    public function getAllKeys($include_inactive = false) {
        $all_keys = get_option(self::OPTION_KEY, []);
        $result = [];

        foreach ($all_keys as $key => $key_data) {
            if (!$include_inactive && !$key_data['is_active']) {
                continue;
            }

            // Remove sensitive data for listing
            $safe_data = $key_data;
            $safe_data['key'] = substr($key, 0, 8) . '...' . substr($key, -4);
            $safe_data['key_id'] = md5($key); // Use for identification in admin

            $result[] = $safe_data;
        }

        // Sort by creation date (newest first)
        usort($result, function($a, $b) {
            return $b['created_at'] - $a['created_at'];
        });

        return $result;
    }

    /**
     * Update API key data
     *
     * @param string $key API key
     * @param array $updates Data to update
     * @return bool True if updated, false if not found
     */
    public function updateKey($key, $updates) {
        $key_data = $this->getKeyData($key);
        if (!$key_data) {
            return false;
        }

        // Only allow updating certain fields
        $allowed_fields = [
            'name', 'permissions', 'expires_at', 'rate_limit_override',
            'ip_whitelist', 'user_agent_pattern', 'is_active'
        ];

        foreach ($updates as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                if ($field === 'permissions') {
                    $key_data[$field] = $this->sanitizePermissions($value);
                } elseif ($field === 'name') {
                    $key_data[$field] = sanitize_text_field($value);
                } else {
                    $key_data[$field] = $value;
                }
            }
        }

        $key_data['updated_at'] = time();
        $key_data['updated_by'] = get_current_user_id();

        $this->storeKey($key, $key_data);

        return true;
    }

    /**
     * Get API key by ID (for admin interface)
     *
     * @param string $key_id MD5 hash of the key
     * @return array|null Key data if found
     */
    public function getKeyById($key_id) {
        $all_keys = get_option(self::OPTION_KEY, []);

        foreach ($all_keys as $key => $key_data) {
            if (md5($key) === $key_id) {
                $key_data['key_id'] = $key_id;
                return $key_data;
            }
        }

        return null;
    }

    /**
     * Get usage statistics for API keys
     *
     * @param int $days Number of days to look back
     * @return array Usage statistics
     */
    public function getUsageStats($days = 30) {
        $all_keys = get_option(self::OPTION_KEY, []);
        $cutoff_time = time() - ($days * 24 * 60 * 60);

        $stats = [
            'total_keys' => 0,
            'active_keys' => 0,
            'inactive_keys' => 0,
            'expired_keys' => 0,
            'recent_usage' => 0,
            'top_keys' => []
        ];

        foreach ($all_keys as $key => $key_data) {
            $stats['total_keys']++;

            if ($key_data['is_active']) {
                $stats['active_keys']++;
            } else {
                $stats['inactive_keys']++;
            }

            if ($key_data['expires_at'] && time() > $key_data['expires_at']) {
                $stats['expired_keys']++;
            }

            if ($key_data['last_used_at'] && $key_data['last_used_at'] > $cutoff_time) {
                $stats['recent_usage']++;
            }

            // Track top keys by usage
            $stats['top_keys'][] = [
                'name' => $key_data['name'],
                'usage_count' => $key_data['usage_count'],
                'last_used_at' => $key_data['last_used_at'],
                'key_preview' => substr($key, 0, 8) . '...'
            ];
        }

        // Sort top keys by usage
        usort($stats['top_keys'], function($a, $b) {
            return $b['usage_count'] - $a['usage_count'];
        });

        $stats['top_keys'] = array_slice($stats['top_keys'], 0, 10);

        return $stats;
    }

    /**
     * Clean up expired and inactive keys
     *
     * @param int $inactive_days Days after which inactive keys are removed
     * @return int Number of keys cleaned up
     */
    public function cleanup($inactive_days = 90) {
        $all_keys = get_option(self::OPTION_KEY, []);
        $cutoff_time = time() - ($inactive_days * 24 * 60 * 60);
        $cleaned_count = 0;

        foreach ($all_keys as $key => $key_data) {
            $should_remove = false;

            // Remove expired keys
            if ($key_data['expires_at'] && time() > $key_data['expires_at']) {
                $should_remove = true;
            }

            // Remove long-inactive revoked keys
            if (!$key_data['is_active'] &&
                isset($key_data['revoked_at']) &&
                $key_data['revoked_at'] < $cutoff_time) {
                $should_remove = true;
            }

            if ($should_remove) {
                unset($all_keys[$key]);
                $cleaned_count++;
            }
        }

        if ($cleaned_count > 0) {
            update_option(self::OPTION_KEY, $all_keys);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'API keys cleaned up', [
                    'cleaned_count' => $cleaned_count
                ]);
            }
        }

        return $cleaned_count;
    }

    /**
     * Check if user has permission with given API key
     *
     * @param array $key_data API key data
     * @param string $permission Permission to check
     * @return bool True if has permission
     */
    public function hasPermission($key_data, $permission) {
        $permissions = $key_data['permissions'] ?? [];

        // Check for wildcard permission
        if (in_array('*', $permissions)) {
            return true;
        }

        // Check for specific permission
        return in_array($permission, $permissions);
    }

    /**
     * Create random API key
     *
     * @param int $length Key length (excluding prefix)
     * @return string Generated API key
     */
    private function createRandomKey($length = self::DEFAULT_KEY_LENGTH) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $random_string = '';

        for ($i = 0; $i < $length; $i++) {
            $random_string .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return self::KEY_PREFIX . $random_string;
    }

    /**
     * Validate API key format
     *
     * @param string $key API key to validate
     * @return bool True if format is valid
     */
    private function isValidKeyFormat($key) {
        if (!is_string($key)) {
            return false;
        }

        // Check prefix
        if (strpos($key, self::KEY_PREFIX) !== 0) {
            return false;
        }

        // Check length
        $expected_length = self::DEFAULT_KEY_LENGTH + strlen(self::KEY_PREFIX);
        if (strlen($key) !== $expected_length) {
            return false;
        }

        // Check characters (alphanumeric only)
        $key_part = substr($key, strlen(self::KEY_PREFIX));
        return ctype_alnum($key_part);
    }

    /**
     * Get API key data from storage
     *
     * @param string $key API key
     * @return array|null Key data or null if not found
     */
    private function getKeyData($key) {
        $all_keys = get_option(self::OPTION_KEY, []);
        return $all_keys[$key] ?? null;
    }

    /**
     * Store API key data
     *
     * @param string $key API key
     * @param array $key_data Key data to store
     */
    private function storeKey($key, $key_data) {
        $all_keys = get_option(self::OPTION_KEY, []);
        $all_keys[$key] = $key_data;
        update_option(self::OPTION_KEY, $all_keys);
    }

    /**
     * Record API key usage
     *
     * @param string $key API key
     */
    private function recordUsage($key) {
        $key_data = $this->getKeyData($key);
        if (!$key_data) {
            return;
        }

        $key_data['last_used_at'] = time();
        $key_data['usage_count'] = ($key_data['usage_count'] ?? 0) + 1;

        $this->storeKey($key, $key_data);
    }

    /**
     * Sanitize permissions array
     *
     * @param array $permissions Raw permissions
     * @return array Sanitized permissions
     */
    private function sanitizePermissions($permissions) {
        if (!is_array($permissions)) {
            return [];
        }

        $valid_permissions = [
            'read:availability',
            'read:quote',
            'read:webhooks',
            'write:webhooks',
            '*' // Wildcard for all permissions
        ];

        return array_intersect($permissions, $valid_permissions);
    }

    /**
     * Get client IP address
     *
     * @return string Client IP
     */
    private function getClientIp() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
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
}