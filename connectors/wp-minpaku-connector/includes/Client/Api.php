<?php
/**
 * API Client Class
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Client;

if (!defined('ABSPATH')) {
    exit;
}

class MPC_Client_Api {

    private $portal_url;
    private $signer;
    private $cache_duration = 300; // 5 minutes

    public function __construct() {
        $settings = \WP_Minpaku_Connector::get_settings();

        // Normalize the portal URL using the same logic as settings validation
        $normalized_url = '';
        if (!empty($settings['portal_url'])) {
            if (\class_exists('MinpakuConnector\Admin\MPC_Admin_Settings')) {
                $normalized_url = \MinpakuConnector\Admin\MPC_Admin_Settings::normalize_portal_url($settings['portal_url']);

                // Debug log for API client URL normalization
                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[minpaku-connector] API Client URL normalization - Original: ' . $settings['portal_url'] . ' | Normalized: ' . ($normalized_url ?: 'false'));
                }
            }
            if ($normalized_url === false) {
                $normalized_url = $settings['portal_url']; // Fallback to original
                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[minpaku-connector] API Client using fallback URL: ' . $normalized_url);
                }
            }
        }
        $this->portal_url = trailingslashit($normalized_url);

        // Debug log for final portal URL
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] API Client final portal_url: ' . $this->portal_url);
        }

        if (!empty($settings['api_key']) && !empty($settings['secret'])) {
            $this->signer = new MPC_Client_Signer($settings['api_key'], $settings['secret']);
        }
    }

    /**
     * Get the portal URL
     */
    public function get_portal_url() {
        return rtrim($this->portal_url, '/');
    }

    /**
     * Get the API key
     */
    public function get_api_key() {
        $settings = \WP_Minpaku_Connector::get_settings();
        return $settings['api_key'] ?? '';
    }

    /**
     * Get the API secret
     */
    public function get_secret() {
        $settings = \WP_Minpaku_Connector::get_settings();
        return $settings['secret'] ?? '';
    }

    /**
     * Check if API is properly configured
     */
    public function is_configured() {
        $settings = \WP_Minpaku_Connector::get_settings();

        return !empty($settings['portal_url']) &&
               !empty($settings['api_key']) &&
               !empty($settings['secret']) &&
               !empty($settings['site_id']) &&
               $this->signer !== null;
    }

    /**
     * Test connection to the portal
     */
    public function test_connection() {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] test_connection() called - portal_url: ' . $this->portal_url . ' | has_signer: ' . ($this->signer ? 'true' : 'false'));
        }

        if (!$this->signer) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-connector] test_connection() failed: API credentials not configured');
            }
            return array(
                'success' => false,
                'message' => __('API credentials not configured.', 'wp-minpaku-connector')
            );
        }

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Making request to: ' . $this->portal_url . 'wp-json/minpaku/v1/connector/verify');
        }

        $response = $this->make_request('GET', '/wp-json/minpaku/v1/connector/verify');

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Connection failed: %s', 'wp-minpaku-connector'), $response->get_error_message())
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Handle 204 No Content (successful authentication)
        if ($status_code === 204) {
            return array(
                'success' => true,
                'message' => sprintf(__('Connected successfully to %s', 'wp-minpaku-connector'), $this->portal_url),
                'data' => array('status' => 204)
            );
        }

        // Handle JSON responses (200, error responses)
        $data = json_decode($body, true);

        if ($status_code === 200 && isset($data['success']) && $data['success']) {
            return array(
                'success' => true,
                'message' => sprintf(__('Connected successfully to %s (v%s)', 'wp-minpaku-connector'),
                    $this->portal_url,
                    $data['version'] ?? 'unknown'
                ),
                'data' => $data
            );
        } else {
            return array(
                'success' => false,
                'message' => isset($data['message']) ? $data['message'] : __('Unknown error occurred.', 'wp-minpaku-connector'),
                'status_code' => $status_code
            );
        }
    }

    /**
     * Test connection with detailed diagnostic information
     */
    public function test_connection_detailed() {
        $start_time = microtime(true);
        $result = array(
            'success' => false,
            'http_status' => null,
            'response_body' => '',
            'response_body_preview' => '',
            'request_url' => '',
            'request_headers_sent' => array(),
            'request_time_ms' => 0,
            'wp_error' => null,
            'curl_error' => null,
            'parsed_data' => null,
            'wp_http_block_external_warning' => false,
            'signature_debug' => array()
        );

        if (!$this->signer) {
            $result['wp_error'] = array(
                'code' => 'no_credentials',
                'message' => __('API credentials not configured.', 'wp-minpaku-connector')
            );
            return $result;
        }

        // Strict request specification enforcement
        $path = '/wp-json/minpaku/v1/connector/verify';

        // Ensure proper URL construction with no trailing/double slashes
        $base_url = rtrim($this->portal_url, '/');
        $clean_path = '/' . ltrim($path, '/');
        $clean_path = preg_replace('#/+#', '/', $clean_path); // Remove double slashes
        $url = $base_url . $clean_path;
        $result['request_url'] = $url;

        // Get signature data - enforce GET method with empty body
        // Use the same clean path for signature calculation
        $signature_data = $this->signer->sign_request('GET', $clean_path, '');

        // Capture signature debugging information
        $body_hash = hash('sha256', '');
        $string_to_sign = implode("\n", array(
            'GET',
            $clean_path,
            $signature_data['nonce'],
            $signature_data['timestamp'],
            $body_hash
        ));

        $result['signature_debug'] = array(
            'method' => 'GET',
            'original_path' => $path,
            'clean_path' => $clean_path,
            'nonce' => $signature_data['nonce'],
            'timestamp' => $signature_data['timestamp'],
            'body_length' => 0,
            'body_hash' => $body_hash,
            'string_to_sign' => $string_to_sign,
            'string_to_sign_length' => strlen($string_to_sign),
            'signature_full' => $signature_data['signature'],
            'api_key' => substr($signature_data['headers']['X-MCS-Key'], 0, 8) . '...'
        );

        // Mask sensitive headers for logging
        $masked_headers = array();
        foreach ($signature_data['headers'] as $key => $value) {
            if ($key === 'X-MCS-Signature') {
                $masked_headers[$key] = substr($value, 0, 8) . '...';
            } elseif ($key === 'X-MCS-Key') {
                $masked_headers[$key] = substr($value, 0, 8) . '...';
            } else {
                $masked_headers[$key] = $value;
            }
        }
        $result['request_headers_sent'] = $masked_headers;

        // Add X-MCS-Origin header for server-to-server identification
        $signature_data['headers']['X-MCS-Origin'] = get_site_url();

        // Strict request arguments
        $args = array(
            'method' => 'GET',
            'headers' => $signature_data['headers'],
            'timeout' => 30, // Increased timeout for debugging
            'redirection' => 2,
            'httpversion' => '1.1',
            'user-agent' => 'WPMC/1.0',
            'blocking' => true,
            'reject_unsafe_urls' => false, // Always false for connector requests
            'sslverify' => (strpos($url, 'https://') === 0) // Only verify SSL for HTTPS
        );

        // Check for WP_HTTP_BLOCK_EXTERNAL
        if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
            $result['wp_http_block_external_warning'] = true;
        }

        // Debug logging for request
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Detailed diagnostic request - Method: GET');
            error_log('[minpaku-connector] Request URL: ' . $url);
            error_log('[minpaku-connector] Request headers (masked): ' . json_encode($masked_headers));
            error_log('[minpaku-connector] WP_HTTP_BLOCK_EXTERNAL: ' . (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL ? 'true' : 'false'));
        }

        // Temporarily disable unsafe URL rejection for this specific request
        add_filter('http_request_reject_unsafe_urls', '__return_false', 999);

        // Temporarily disable WP_HTTP_BLOCK_EXTERNAL for this request
        $external_block_disabled = false;
        if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
            add_filter('pre_option_wp_http_block_external', '__return_false', 999);
            $external_block_disabled = true;
        }

        try {
            $response = wp_remote_request($url, $args);
            $result['request_time_ms'] = round((microtime(true) - $start_time) * 1000);

            if (is_wp_error($response)) {
                $result['wp_error'] = array(
                    'code' => $response->get_error_code(),
                    'message' => $response->get_error_message(),
                    'data' => $response->get_error_data()
                );

                // Extract cURL error details if available
                $error_data = $response->get_error_data();
                if (is_array($error_data) && isset($error_data['curl'])) {
                    $result['curl_error'] = $error_data['curl'];
                }

                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[minpaku-connector] WP_Error in detailed test: ' . $response->get_error_message());
                    if ($result['curl_error']) {
                        error_log('[minpaku-connector] cURL error details: ' . json_encode($result['curl_error']));
                    }
                }

                return $result;
            }

            $result['http_status'] = wp_remote_retrieve_response_code($response);
            $result['response_body'] = wp_remote_retrieve_body($response);
            $result['response_body_preview'] = substr($result['response_body'], 0, 400);

            $parsed_data = json_decode($result['response_body'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result['parsed_data'] = $parsed_data;
            }

            // Determine success based on status (204 for success, other codes for specific errors)
            if ($result['http_status'] === 204) {
                $result['success'] = true;
            } elseif ($result['http_status'] === 200) {
                // Some servers might return 200 instead of 204
                $result['success'] = true;
            }

            // Debug logging for response
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-connector] Detailed diagnostic response - Status: ' . $result['http_status'] . ', Body length: ' . strlen($result['response_body']));
                if ($result['parsed_data']) {
                    error_log('[minpaku-connector] Parsed response data: ' . json_encode($result['parsed_data']));
                }
            }

        } finally {
            // Always restore filters
            remove_filter('http_request_reject_unsafe_urls', '__return_false', 999);
            if ($external_block_disabled) {
                remove_filter('pre_option_wp_http_block_external', '__return_false', 999);
            }
        }

        return $result;
    }

    /**
     * Get properties from the portal
     */
    public function get_properties($args = array()) {
        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'status' => 'publish'
        );

        $args = wp_parse_args($args, $defaults);
        $cache_key = 'mpc_properties_' . md5(serialize($args));

        // Try to get from cache first
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $query_string = http_build_query($args);
        $response = $this->make_request('GET', '/wp-json/minpaku/v1/connector/properties?' . $query_string);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) === 200 && isset($data['success']) && $data['success']) {
            // Cache the successful response
            set_transient($cache_key, $data, $this->cache_duration);
            return $data;
        } else {
            return array(
                'success' => false,
                'message' => isset($data['message']) ? $data['message'] : __('Failed to fetch properties.', 'wp-minpaku-connector')
            );
        }
    }

    /**
     * Get availability for a property
     */
    public function get_availability($property_id, $months = 2, $start_date = null, $with_price = false) {
        $args = array(
            'property_id' => intval($property_id),
            'months' => intval($months)
        );

        if ($start_date) {
            $args['start_date'] = sanitize_text_field($start_date);
        }

        // Add with_price parameter to request pricing data
        if ($with_price) {
            $args['with_price'] = 1;
        }

        $cache_key = 'mpc_availability_' . md5(serialize($args));

        // Try to get from cache first
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $query_string = http_build_query($args);
        $response = $this->make_request('GET', '/wp-json/minpaku/v1/connector/availability?' . $query_string);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $response_code = wp_remote_retrieve_response_code($response);

        // Enhanced debugging
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Availability API response code: ' . $response_code);
            error_log('[minpaku-connector] Availability API response body: ' . $body);
            if ($data) {
                error_log('[minpaku-connector] Availability API parsed data: ' . print_r($data, true));

                // Debug pricing data structure specifically
                if (isset($data['data']) && is_array($data['data'])) {
                    error_log('[minpaku-connector] Availability data structure found: ' . print_r($data['data'], true));

                    // Check for pricing arrays
                    if (isset($data['data']['pricing'])) {
                        error_log('[minpaku-connector] Pricing data found: ' . print_r($data['data']['pricing'], true));
                    }

                    if (isset($data['data']['rates'])) {
                        error_log('[minpaku-connector] Rates data found: ' . print_r($data['data']['rates'], true));
                    }

                    if (isset($data['data']['availability'])) {
                        error_log('[minpaku-connector] Availability array found: ' . print_r($data['data']['availability'], true));
                    }
                }
            }
        }

        if ($response_code === 200 && isset($data['success']) && $data['success']) {
            // Apply local pricing calculations if with_price was requested
            if ($with_price) {
                $data = $this->apply_local_pricing_calculations($data, $property_id);
            }

            // Cache for shorter duration since availability changes frequently
            set_transient($cache_key, $data, 60); // 1 minute cache
            return $data;
        } else {
            $error_message = isset($data['message']) ? $data['message'] : __('Failed to fetch availability.', 'wp-minpaku-connector');
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-connector] Availability API error: ' . $error_message);
            }
            return array(
                'success' => false,
                'message' => $error_message . ' (HTTP ' . $response_code . ')'
            );
        }
    }

    /**
     * Apply local pricing calculations to availability data
     */
    private function apply_local_pricing_calculations($data, $property_id) {
        // Load required classes
        require_once WP_MINPAKU_CONNECTOR_PATH . 'includes/Calendar/JPHolidays.php';
        require_once WP_MINPAKU_CONNECTOR_PATH . 'includes/Calendar/DayClassifier.php';

        $pricing_settings = $this->get_pricing_settings();

        if (!isset($data['data']['availability']) || !is_array($data['data']['availability'])) {
            return $data;
        }

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Applying local pricing calculations for property ' . $property_id);
        }

        foreach ($data['data']['availability'] as &$day_data) {
            if (!isset($day_data['date'])) {
                continue;
            }

            $date = $day_data['date'];
            $is_available = $day_data['available'] ?? true;

            // Only calculate prices for available days
            if (!$is_available) {
                continue;
            }

            // Check if API already provided a min_price
            $api_price = $day_data['min_price'] ?? null;

            if ($api_price && $api_price > 0) {
                // Use API price if available and valid
                $day_data['price'] = floatval($api_price);
                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[minpaku-connector] Using API price for ' . $date . ': ¥' . $api_price);
                }
            } else {
                // Calculate local price
                $local_price = $this->calculate_local_price($date, $pricing_settings);
                $day_data['price'] = $local_price;

                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[minpaku-connector] Calculated local price for ' . $date . ': ¥' . $local_price);
                }
            }

            // Add day classification information
            $day_info = \MinpakuConnector\Calendar\DayClassifier::classifyDate($date);
            $day_data['day_type'] = $day_info['color_class'];
            $day_data['is_holiday'] = $day_info['is_holiday'];
            $day_data['is_weekend'] = $day_info['is_saturday'] || $day_info['is_sunday'];
        }

        return $data;
    }

    /**
     * Calculate local price for a specific date
     */
    private function calculate_local_price($date, $pricing_settings) {
        $base_price = floatval($pricing_settings['base_nightly_price']);

        // Check for seasonal rules first (highest priority)
        $seasonal_price = $this->apply_seasonal_rules($date, $base_price, $pricing_settings['seasonal_rules']);

        if ($seasonal_price !== $base_price) {
            // Seasonal rule applied, don't add eve surcharges (to avoid double charging)
            return $seasonal_price;
        }

        // Check for eve surcharges (second priority)
        $eve_surcharge = $this->calculate_eve_surcharge($date, $pricing_settings);

        return $base_price + $eve_surcharge;
    }

    /**
     * Apply seasonal rules to base price
     */
    private function apply_seasonal_rules($date, $base_price, $seasonal_rules) {
        if (empty($seasonal_rules) || !is_array($seasonal_rules)) {
            return $base_price;
        }

        foreach ($seasonal_rules as $rule) {
            if (!isset($rule['date_from']) || !isset($rule['date_to']) || !isset($rule['mode']) || !isset($rule['amount'])) {
                continue;
            }

            $date_from = $rule['date_from'];
            $date_to = $rule['date_to'];

            // Check if date falls within this rule's range
            if ($date >= $date_from && $date <= $date_to) {
                $amount = floatval($rule['amount']);

                if ($rule['mode'] === 'override') {
                    return $amount; // Replace base price
                } elseif ($rule['mode'] === 'add') {
                    return $base_price + $amount; // Add to base price
                }
            }
        }

        return $base_price; // No seasonal rule applied
    }

    /**
     * Calculate eve surcharge for a date
     */
    private function calculate_eve_surcharge($date, $pricing_settings) {
        $eve_info = \MinpakuConnector\Calendar\DayClassifier::checkEveSurcharges($date);

        if (!$eve_info['has_surcharge']) {
            return 0;
        }

        switch ($eve_info['surcharge_type']) {
            case 'saturday_eve':
                return floatval($pricing_settings['eve_surcharge_sat'] ?? 0);
            case 'sunday_eve':
                return floatval($pricing_settings['eve_surcharge_sun'] ?? 0);
            case 'holiday_eve':
                return floatval($pricing_settings['eve_surcharge_holiday'] ?? 0);
            default:
                return 0;
        }
    }

    /**
     * Get pricing settings
     */
    private function get_pricing_settings() {
        if (class_exists('MinpakuConnector\Admin\MPC_Admin_Settings')) {
            return \MinpakuConnector\Admin\MPC_Admin_Settings::get_pricing_settings();
        }

        // Fallback defaults
        return array(
            'base_nightly_price' => 15000,
            'cleaning_fee_per_booking' => 3000,
            'eve_surcharge_sat' => 2000,
            'eve_surcharge_sun' => 1000,
            'eve_surcharge_holiday' => 1500,
            'seasonal_rules' => array(),
            'blackout_ranges' => array()
        );
    }

    /**
     * Get a single property details
     */
    public function get_property($property_id) {
        $properties = $this->get_properties(array(
            'per_page' => 1,
            'page' => 1
        ));

        if (!$properties['success']) {
            return $properties;
        }

        // Find the specific property
        foreach ($properties['data'] as $property) {
            if ($property['id'] == $property_id) {
                return array(
                    'success' => true,
                    'data' => $property
                );
            }
        }

        return array(
            'success' => false,
            'message' => __('Property not found.', 'wp-minpaku-connector')
        );
    }

    /**
     * Get quote for a booking
     */
    public function get_quote($property_id, $check_in, $check_out, $guests = 2) {
        $body = json_encode(array(
            'property_id' => intval($property_id),
            'check_in' => sanitize_text_field($check_in),
            'check_out' => sanitize_text_field($check_out),
            'guests' => intval($guests)
        ));

        $response = $this->make_request('POST', '/wp-json/minpaku/v1/connector/quote', $body);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (wp_remote_retrieve_response_code($response) === 200 && isset($data['success']) && $data['success']) {
            return $data;
        } else {
            return array(
                'success' => false,
                'message' => isset($data['message']) ? $data['message'] : __('Failed to generate quote.', 'wp-minpaku-connector')
            );
        }
    }

    /**
     * Make authenticated request to the portal API
     */
    private function make_request($method, $path, $body = '') {
        if (!$this->signer) {
            return new WP_Error('no_credentials', __('API credentials not configured.', 'wp-minpaku-connector'));
        }

        $url = $this->portal_url . ltrim($path, '/');
        $signature_data = $this->signer->sign_request($method, $path, $body);

        $args = array(
            'method' => $method,
            'headers' => $signature_data['headers'],
            'timeout' => 30, // Increased timeout for stability
            'redirection' => 2,
            'httpversion' => '1.1',
            'user-agent' => 'WPMC/1.0',
            'reject_unsafe_urls' => true,
            'sslverify' => true
        );

        // Check if this is a development domain and adjust settings
        $parsed_url = wp_parse_url($url);
        $host = $parsed_url['host'] ?? '';
        $is_dev_domain = $this->is_development_domain($host);

        if ($is_dev_domain) {
            $args['reject_unsafe_urls'] = false;

            // Debug logging for development domain
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-connector] Development domain detected: ' . $host . ', adjusting request args');
            }
        }

        if (!empty($body)) {
            $args['body'] = $body;
        }

        // Debug logging for outgoing request
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $debug_request = array(
                'method' => $method,
                'url' => $url,
                'headers_sent' => array_keys($signature_data['headers']),
                'body_length' => strlen($body),
                'timeout' => 30,
                'redirection' => 2,
                'is_dev_domain' => $is_dev_domain
            );
            error_log('[minpaku-connector] Outgoing request: ' . json_encode($debug_request));
        }

        $start_time = microtime(true);

        // For development domains, temporarily disable external blocking
        if ($is_dev_domain && defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
            add_filter('pre_option_wp_http_block_external', '__return_false', 999);
        }

        $response = wp_remote_request($url, $args);

        // Restore external blocking if it was disabled
        if ($is_dev_domain && defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
            remove_filter('pre_option_wp_http_block_external', '__return_false', 999);
        }

        $request_time = round((microtime(true) - $start_time) * 1000); // milliseconds

        // Debug logging for response
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if (is_wp_error($response)) {
                $error_data = array(
                    'errors' => $response->get_error_messages(),
                    'error_data' => $response->get_error_data(),
                    'request_time_ms' => $request_time
                );
                error_log('[minpaku-connector] Request failed: ' . json_encode($error_data));
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $response_headers = wp_remote_retrieve_headers($response);
                $debug_response = array(
                    'status_code' => $response_code,
                    'request_time_ms' => $request_time,
                    'response_headers' => array_keys($response_headers->getAll())
                );
                error_log('[minpaku-connector] Request completed: ' . json_encode($debug_response));
            }
        }

        return $response;
    }

    /**
     * Check if the given host is a development domain
     */
    private function is_development_domain($host) {
        // IPv4 addresses
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        // localhost
        if ($host === 'localhost') {
            return true;
        }

        // Development TLDs
        if (strpos($host, '.') !== false) {
            $tld = substr(strrchr($host, '.'), 1);
            return in_array($tld, ['local', 'test', 'localdomain'], true);
        }

        return false;
    }

    /**
     * Clear all cached data
     */
    public static function clear_cache() {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_mpc_%'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_mpc_%'
            )
        );
    }

}