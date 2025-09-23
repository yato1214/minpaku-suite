<?php
/**
 * Quote API Client for Pricing Integration
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Client;

if (!defined('ABSPATH')) {
    exit;
}

class MPC_Client_QuoteApi {

    private $api;
    private $cache = [];
    private $pending_requests = [];
    private $cache_expiry = [];
    private $max_cache_size = 100;

    public function __construct() {
        $this->api = new MPC_Client_Api();
    }

    /**
     * Get quote with caching and deduplication
     */
    public function get_quote($property_id, $checkin, $checkout, $adults = 2, $children = 0, $infants = 0, $currency = 'JPY') {
        // Create cache key
        $cache_key = $this->create_cache_key($property_id, $checkin, $checkout, $adults, $children, $infants, $currency);

        // Check memory cache first
        if (isset($this->cache[$cache_key])) {
            // Check if cache is still valid
            if (isset($this->cache_expiry[$cache_key]) && time() < $this->cache_expiry[$cache_key]) {
                return $this->cache[$cache_key];
            } else {
                // Remove expired cache
                unset($this->cache[$cache_key], $this->cache_expiry[$cache_key]);
            }
        }

        // Check WordPress transient cache
        $wp_cache = get_transient('mpc_quote_' . $cache_key);
        if ($wp_cache !== false) {
            $this->cache[$cache_key] = $wp_cache;
            $this->cache_expiry[$cache_key] = time() + 300; // 5 minutes
            return $wp_cache;
        }

        // Check session storage cache
        $session_cache = $this->get_session_cache($cache_key);
        if ($session_cache !== null) {
            $this->cache[$cache_key] = $session_cache;
            $this->cache_expiry[$cache_key] = time() + 300; // 5 minutes
            return $session_cache;
        }

        // Check if request is already pending (coalescing)
        if (isset($this->pending_requests[$cache_key])) {
            return $this->pending_requests[$cache_key];
        }

        // Make new request
        $request_promise = $this->make_quote_request($property_id, $checkin, $checkout, $adults, $children, $infants, $currency);
        $this->pending_requests[$cache_key] = $request_promise;

        return $request_promise;
    }

    /**
     * Make actual quote request with retry logic
     */
    private function make_quote_request($property_id, $checkin, $checkout, $adults, $children, $infants, $currency) {
        $params = [
            'property_id' => $property_id,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'currency' => $currency
        ];

        $cache_key = $this->create_cache_key($property_id, $checkin, $checkout, $adults, $children, $infants, $currency);

        // Try request with retries
        $max_retries = 2;
        $retry_delay = 1000; // 1 second

        for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
            try {
                $response = $this->execute_quote_request($params);

                // Cache successful response
                $this->cache_response($cache_key, $response);
                unset($this->pending_requests[$cache_key]);

                return [
                    'success' => true,
                    'data' => $response
                ];

            } catch (\Exception $e) {
                $last_error = $e->getMessage();

                if ($attempt < $max_retries) {
                    // Exponential backoff
                    usleep($retry_delay * 1000 * pow(2, $attempt));
                    continue;
                }
            }
        }

        // All retries failed
        unset($this->pending_requests[$cache_key]);

        return [
            'success' => false,
            'error' => isset($last_error) ? $last_error : __('Unknown error', 'wp-minpaku-connector')
        ];
    }

    /**
     * Execute the actual HTTP request
     */
    private function execute_quote_request($params) {
        if (!$this->api->is_configured()) {
            throw new \Exception(__('API not configured', 'wp-minpaku-connector'));
        }

        // Build URL
        $base_url = trailingslashit($this->api->get_portal_url()) . 'wp-json/minpaku/v1/connector/quote';
        $url = add_query_arg($params, $base_url);

        // Get signed headers
        $signer = new MPC_Client_Signer($this->api->get_api_key(), $this->api->get_secret());
        $signed_data = $signer->sign_request('GET', '/wp-json/minpaku/v1/connector/quote?' . http_build_query($params));

        // Add origin header
        $signed_data['headers']['X-MCS-Origin'] = home_url();

        // Make request with increased timeout for stability
        $response = wp_remote_get($url, [
            'headers' => $signed_data['headers'],
            'timeout' => 30,
            'sslverify' => false // For development environments
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'HTTP ' . $status_code;
            throw new \Exception($error_message);
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(__('Invalid JSON response', 'wp-minpaku-connector'));
        }

        return $data;
    }

    /**
     * Create cache key
     */
    private function create_cache_key($property_id, $checkin, $checkout, $adults, $children, $infants, $currency) {
        return 'quote_' . md5($property_id . '_' . $checkin . '_' . $checkout . '_' . $adults . '_' . $children . '_' . $infants . '_' . $currency);
    }

    /**
     * Cache response in memory, WordPress transients, and session storage
     */
    private function cache_response($cache_key, $response) {
        // Memory cache with LRU eviction
        if (count($this->cache) >= $this->max_cache_size) {
            // Remove oldest entries by expiry time
            $oldest_keys = array_keys($this->cache_expiry);
            if (!empty($oldest_keys)) {
                $oldest_key = $oldest_keys[0];
                foreach ($oldest_keys as $key) {
                    if ($this->cache_expiry[$key] < $this->cache_expiry[$oldest_key]) {
                        $oldest_key = $key;
                    }
                }
                unset($this->cache[$oldest_key], $this->cache_expiry[$oldest_key]);
            }
        }

        $expiry_time = time() + 300; // 5 minutes
        $this->cache[$cache_key] = $response;
        $this->cache_expiry[$cache_key] = $expiry_time;

        // WordPress transient cache (15 minutes)
        set_transient('mpc_quote_' . $cache_key, $response, 900);

        // Session storage cache (handled by JavaScript)
        // We'll implement this in the frontend
    }

    /**
     * Get from session cache (placeholder - handled by JS)
     */
    private function get_session_cache($cache_key) {
        // This will be implemented in JavaScript
        return null;
    }

    /**
     * Get quick quote for single night (used for calendar badges)
     */
    public function get_single_night_quote($property_id, $date, $adults = 2, $children = 0, $infants = 0, $currency = 'JPY') {
        $checkin = $date;
        $checkout = date('Y-m-d', strtotime($date . ' +1 day'));

        return $this->get_quote($property_id, $checkin, $checkout, $adults, $children, $infants, $currency);
    }

    /**
     * Get quick quote for multiple nights (used for property cards)
     */
    public function get_multi_night_quote($property_id, $nights = 2, $adults = 2, $children = 0, $infants = 0, $currency = 'JPY') {
        $checkin = date('Y-m-d', strtotime('+1 day'));
        $checkout = date('Y-m-d', strtotime('+' . ($nights + 1) . ' days'));

        return $this->get_quote($property_id, $checkin, $checkout, $adults, $children, $infants, $currency);
    }

    /**
     * Get minimum price for next 30 days (used for property card badges)
     */
    public function get_minimum_daily_rate($property_id, $adults = 2, $children = 0, $infants = 0, $currency = 'JPY') {
        $min_price = null;
        $days_to_check = 30;

        // Check daily rates for next 30 days
        for ($i = 1; $i <= $days_to_check; $i++) {
            $date = date('Y-m-d', strtotime('+' . $i . ' days'));

            try {
                $quote = $this->get_single_night_quote($property_id, $date, $adults, $children, $infants, $currency);

                if ($quote['success'] && isset($quote['data']['total_incl_tax'])) {
                    $price = $quote['data']['total_incl_tax'];
                    if ($min_price === null || $price < $min_price) {
                        $min_price = $price;
                    }
                }
            } catch (\Exception $e) {
                // Skip this date on error
                continue;
            }
        }

        return $min_price;
    }

    /**
     * Format price for display
     */
    public function format_price($amount, $currency = 'JPY') {
        if ($currency === 'JPY') {
            return '¥' . number_format_i18n($amount, 0);
        } elseif ($currency === 'USD') {
            return '$' . number_format_i18n($amount, 2);
        } elseif ($currency === 'EUR') {
            return '€' . number_format_i18n($amount, 2);
        } else {
            return $currency . ' ' . number_format_i18n($amount, 2);
        }
    }

    /**
     * Get site default currency
     */
    public function get_default_currency() {
        return get_option('mpc_default_currency', 'JPY');
    }
}