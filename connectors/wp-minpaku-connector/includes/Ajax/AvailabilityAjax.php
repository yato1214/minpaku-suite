<?php
/**
 * AJAX Handler for Availability Requests
 *
 * @package WP_Minpaku_Connector
 */

namespace MpkConnector\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Availability AJAX Class
 */
class AvailabilityAjax {

    /**
     * Initialize AJAX handlers
     */
    public static function init() {
        // AJAX handlers for logged in users and guests
        add_action('wp_ajax_mpk_get_availability', [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_mpk_get_availability', [__CLASS__, 'handle']);
        add_action('wp_ajax_mpk_test_connection', [__CLASS__, 'test_connection']);
    }

    /**
     * Handle availability request
     */
    public static function handle() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpk_availability_nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed.', 'wp-minpaku-connector'),
                'code' => 'nonce_failed'
            ], 403);
        }

        // Validate and sanitize input
        $property_id = isset($_POST['property_id']) ? absint($_POST['property_id']) : 0;
        $from_date = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : '';
        $to_date = isset($_POST['to']) ? sanitize_text_field($_POST['to']) : '';

        // Validate required fields
        if (empty($property_id)) {
            wp_send_json_error([
                'message' => __('Property ID is required.', 'wp-minpaku-connector'),
                'code' => 'missing_property_id'
            ], 400);
        }

        if (empty($from_date) || empty($to_date)) {
            wp_send_json_error([
                'message' => __('From and To dates are required.', 'wp-minpaku-connector'),
                'code' => 'missing_dates'
            ], 400);
        }

        // Validate date format (YYYY-MM-DD)
        if (!self::validate_date($from_date) || !self::validate_date($to_date)) {
            wp_send_json_error([
                'message' => __('Invalid date format. Use YYYY-MM-DD.', 'wp-minpaku-connector'),
                'code' => 'invalid_date_format'
            ], 400);
        }

        // Validate date range
        $from_timestamp = strtotime($from_date);
        $to_timestamp = strtotime($to_date);

        if ($from_timestamp === false || $to_timestamp === false) {
            wp_send_json_error([
                'message' => __('Invalid dates provided.', 'wp-minpaku-connector'),
                'code' => 'invalid_dates'
            ], 400);
        }

        if ($from_timestamp >= $to_timestamp) {
            wp_send_json_error([
                'message' => __('From date must be before To date.', 'wp-minpaku-connector'),
                'code' => 'invalid_date_range'
            ], 400);
        }

        // Check if dates are not too far in the past
        $today = strtotime('today');
        if ($from_timestamp < $today) {
            wp_send_json_error([
                'message' => __('From date cannot be in the past.', 'wp-minpaku-connector'),
                'code' => 'past_date'
            ], 400);
        }

        // Check if date range is not too long (max 1 year)
        $max_range = 365 * 24 * 60 * 60; // 1 year in seconds
        if (($to_timestamp - $from_timestamp) > $max_range) {
            wp_send_json_error([
                'message' => __('Date range cannot exceed 1 year.', 'wp-minpaku-connector'),
                'code' => 'range_too_long'
            ], 400);
        }

        // Get settings
        $settings = \MpkConnector\Admin\SettingsPage::get_settings();

        if (empty($settings['api_base_url']) || empty($settings['api_key'])) {
            wp_send_json_error([
                'message' => __('API is not configured. Please contact administrator.', 'wp-minpaku-connector'),
                'code' => 'api_not_configured'
            ], 500);
        }

        // Make API request
        $response = self::make_api_request($settings, $property_id, $from_date, $to_date);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => $response->get_error_message(),
                'code' => $response->get_error_code()
            ], 500);
        }

        // Return successful response
        wp_send_json_success($response);
    }

    /**
     * Test API connection
     */
    public static function test_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpk_test_connection')) {
            wp_send_json_error([
                'message' => __('Security check failed.', 'wp-minpaku-connector'),
                'code' => 'nonce_failed'
            ], 403);
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions.', 'wp-minpaku-connector'),
                'code' => 'insufficient_permissions'
            ], 403);
        }

        // Get settings
        $settings = \MpkConnector\Admin\SettingsPage::get_settings();

        if (empty($settings['api_base_url']) || empty($settings['api_key'])) {
            wp_send_json_error([
                'message' => __('API settings are not configured.', 'wp-minpaku-connector'),
                'code' => 'api_not_configured'
            ], 400);
        }

        // Test connection with a simple request
        $test_url = trailingslashit($settings['api_base_url']) . 'test';
        $timeout = absint($settings['timeout']);

        $response = wp_remote_get($test_url, [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json',
                'User-Agent' => 'WP-Minpaku-Connector/' . WP_MINPAKU_CONNECTOR_VERSION
            ]
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Connection failed: %s', 'wp-minpaku-connector'),
                    $response->get_error_message()
                ),
                'code' => 'connection_failed'
            ], 500);
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 200) {
            wp_send_json_success([
                'message' => __('Connection successful!', 'wp-minpaku-connector')
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(
                    __('Connection failed with HTTP %d', 'wp-minpaku-connector'),
                    $response_code
                ),
                'code' => 'http_error'
            ], $response_code);
        }
    }

    /**
     * Validate date format (YYYY-MM-DD)
     */
    private static function validate_date($date) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parts = explode('-', $date);
        return checkdate($parts[1], $parts[2], $parts[0]);
    }

    /**
     * Make API request for availability
     */
    private static function make_api_request($settings, $property_id, $from_date, $to_date) {
        $api_url = trailingslashit($settings['api_base_url']) . 'availability';
        $timeout = absint($settings['timeout']);

        // Build query parameters
        $query_params = [
            'property_id' => $property_id,
            'from' => $from_date,
            'to' => $to_date
        ];

        $request_url = add_query_arg($query_params, $api_url);

        // For demo purposes, we'll return mock data if the URL contains 'demo'
        if (strpos($settings['api_base_url'], 'demo') !== false) {
            return self::get_mock_data($property_id, $from_date, $to_date);
        }

        $response = wp_remote_get($request_url, [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json',
                'User-Agent' => 'WP-Minpaku-Connector/' . WP_MINPAKU_CONNECTOR_VERSION
            ]
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return new \WP_Error(
                'api_error',
                sprintf(
                    __('API returned HTTP %d: %s', 'wp-minpaku-connector'),
                    $response_code,
                    $response_body
                )
            );
        }

        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'json_decode_error',
                __('Invalid JSON response from API.', 'wp-minpaku-connector')
            );
        }

        return $data;
    }

    /**
     * Generate mock data for demo purposes
     */
    private static function get_mock_data($property_id, $from_date, $to_date) {
        $availability = [];
        $current_date = $from_date;

        while ($current_date <= $to_date) {
            $timestamp = strtotime($current_date);
            $day_of_week = date('N', $timestamp);

            // Mock availability (weekends less available)
            $is_available = $day_of_week < 6 ? (rand(1, 10) > 2) : (rand(1, 10) > 6);

            $availability[] = [
                'date' => $current_date,
                'available' => $is_available,
                'price' => $is_available ? rand(8000, 15000) : null,
                'min_nights' => 1,
                'max_nights' => 30
            ];

            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }

        return [
            'success' => true,
            'property_id' => $property_id,
            'from' => $from_date,
            'to' => $to_date,
            'availability' => $availability
        ];
    }
}