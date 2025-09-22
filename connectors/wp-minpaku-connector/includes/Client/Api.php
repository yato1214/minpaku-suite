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

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) === 200 && isset($data['success']) && $data['success']) {
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
                'message' => isset($data['message']) ? $data['message'] : __('Unknown error occurred.', 'wp-minpaku-connector')
            );
        }
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
    public function get_availability($property_id, $months = 2, $start_date = null) {
        $args = array(
            'property_id' => intval($property_id),
            'months' => intval($months)
        );

        if ($start_date) {
            $args['start_date'] = sanitize_text_field($start_date);
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

        if (wp_remote_retrieve_response_code($response) === 200 && isset($data['success']) && $data['success']) {
            // Cache for shorter duration since availability changes frequently
            set_transient($cache_key, $data, 60); // 1 minute cache
            return $data;
        } else {
            return array(
                'success' => false,
                'message' => isset($data['message']) ? $data['message'] : __('Failed to fetch availability.', 'wp-minpaku-connector')
            );
        }
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
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent' => 'WP-Minpaku-Connector/' . WP_MINPAKU_CONNECTOR_VERSION,
            'reject_unsafe_urls' => true,
            'sslverify' => true
        );

        if (!empty($body)) {
            $args['body'] = $body;
        }

        return wp_remote_request($url, $args);
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