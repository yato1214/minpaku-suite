<?php
/**
 * AJAX handlers for Availability Calendar
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

class MPC_Ajax_Availability {

    public static function init() {
        // AJAX handlers for logged in users and guests
        add_action('wp_ajax_wpmc_get_availability', [__CLASS__, 'get_availability']);
        add_action('wp_ajax_nopriv_wpmc_get_availability', [__CLASS__, 'get_availability']);
        add_action('wp_ajax_wpmc_get_quote', [__CLASS__, 'get_quote']);
        add_action('wp_ajax_nopriv_wpmc_get_quote', [__CLASS__, 'get_quote']);
    }

    /**
     * Get availability data for calendar display
     */
    public static function get_availability() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpmc_availability_nonce')) {
            wp_send_json_error([
                'message' => __('セキュリティチェックに失敗しました。', 'wp-minpaku-connector')
            ]);
        }

        $property_id = intval($_POST['property_id'] ?? 0);
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');

        if (empty($property_id) || empty($start_date) || empty($end_date)) {
            wp_send_json_error([
                'message' => __('必要なパラメータが不足しています。', 'wp-minpaku-connector')
            ]);
        }

        try {
            // Initialize API client
            $api = new \MinpakuConnector\Client\MPC_Client_Api();
            if (!$api->is_configured()) {
                wp_send_json_error([
                    'message' => __('API設定が完了していません。', 'wp-minpaku-connector')
                ]);
            }

            // Request availability data from portal
            $response = $api->get_availability($property_id, $start_date, $end_date);

            if ($response && isset($response['data'])) {
                // Process and format availability data
                $availability_data = self::process_availability_data($response['data']);

                wp_send_json_success($availability_data);
            } else {
                wp_send_json_error([
                    'message' => __('空室データの取得に失敗しました。', 'wp-minpaku-connector')
                ]);
            }

        } catch (Exception $e) {
            error_log('[WPMC Availability] Error getting availability: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('データの取得中にエラーが発生しました。', 'wp-minpaku-connector')
            ]);
        }
    }

    /**
     * Get quote data for selected dates
     */
    public static function get_quote() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpmc_availability_nonce')) {
            wp_send_json_error([
                'message' => __('セキュリティチェックに失敗しました。', 'wp-minpaku-connector')
            ]);
        }

        $property_id = intval($_POST['property_id'] ?? 0);
        $check_in = sanitize_text_field($_POST['check_in'] ?? '');
        $check_out = sanitize_text_field($_POST['check_out'] ?? '');
        $guests = intval($_POST['guests'] ?? 2);

        if (empty($property_id) || empty($check_in)) {
            wp_send_json_error([
                'message' => __('必要なパラメータが不足しています。', 'wp-minpaku-connector')
            ]);
        }

        // If check_out is not provided, assume single night
        if (empty($check_out)) {
            $check_out_date = new DateTime($check_in);
            $check_out_date->add(new DateInterval('P1D'));
            $check_out = $check_out_date->format('Y-m-d');
        }

        try {
            // Initialize API client
            $api = new \MinpakuConnector\Client\MPC_Client_Api();
            if (!$api->is_configured()) {
                wp_send_json_error([
                    'message' => __('API設定が完了していません。', 'wp-minpaku-connector')
                ]);
            }

            // Request quote from portal
            $response = $api->get_quote($property_id, $check_in, $check_out, $guests);

            if ($response && isset($response['data'])) {
                // Process and format quote data
                $quote_data = self::process_quote_data($response['data']);

                wp_send_json_success($quote_data);
            } else {
                wp_send_json_error([
                    'message' => __('見積りの取得に失敗しました。', 'wp-minpaku-connector')
                ]);
            }

        } catch (Exception $e) {
            error_log('[WPMC Availability] Error getting quote: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('見積りの取得中にエラーが発生しました。', 'wp-minpaku-connector')
            ]);
        }
    }

    /**
     * Process raw availability data from API
     */
    private static function process_availability_data($raw_data) {
        $processed = [];

        if (!is_array($raw_data)) {
            return $processed;
        }

        foreach ($raw_data as $date_string => $day_data) {
            if (!is_array($day_data)) continue;

            $processed[$date_string] = [
                'date' => $date_string,
                'status' => self::determine_availability_status($day_data),
                'price' => isset($day_data['price']) ? floatval($day_data['price']) : null,
                'min_nights' => isset($day_data['min_nights']) ? intval($day_data['min_nights']) : 1,
                'available_rooms' => isset($day_data['available_rooms']) ? intval($day_data['available_rooms']) : 0,
                'restrictions' => isset($day_data['restrictions']) ? $day_data['restrictions'] : []
            ];
        }

        return $processed;
    }

    /**
     * Process raw quote data from API
     */
    private static function process_quote_data($raw_data) {
        if (!is_array($raw_data)) {
            return [];
        }

        return [
            'property_id' => isset($raw_data['property_id']) ? intval($raw_data['property_id']) : 0,
            'check_in' => isset($raw_data['check_in']) ? sanitize_text_field($raw_data['check_in']) : '',
            'check_out' => isset($raw_data['check_out']) ? sanitize_text_field($raw_data['check_out']) : '',
            'nights' => isset($raw_data['nights']) ? intval($raw_data['nights']) : 0,
            'guests' => isset($raw_data['guests']) ? intval($raw_data['guests']) : 2,
            'room_rate' => isset($raw_data['room_rate']) ? floatval($raw_data['room_rate']) : 0,
            'fees' => isset($raw_data['fees']) ? $raw_data['fees'] : [],
            'taxes' => isset($raw_data['taxes']) ? $raw_data['taxes'] : [],
            'discounts' => isset($raw_data['discounts']) ? $raw_data['discounts'] : [],
            'total_price' => isset($raw_data['total_price']) ? floatval($raw_data['total_price']) : 0,
            'currency' => isset($raw_data['currency']) ? sanitize_text_field($raw_data['currency']) : 'JPY',
            'breakdown' => isset($raw_data['breakdown']) ? $raw_data['breakdown'] : [],
            'restrictions' => isset($raw_data['restrictions']) ? $raw_data['restrictions'] : [],
            'booking_url' => isset($raw_data['booking_url']) ? esc_url_raw($raw_data['booking_url']) : ''
        ];
    }

    /**
     * Determine availability status from day data
     */
    private static function determine_availability_status($day_data) {
        // Priority order: closed > occupied > pending > available

        if (isset($day_data['closed']) && $day_data['closed']) {
            return 'closed';
        }

        if (isset($day_data['occupied']) && $day_data['occupied']) {
            return 'occupied';
        }

        if (isset($day_data['status'])) {
            $status = strtolower($day_data['status']);
            if (in_array($status, ['occupied', 'booked', 'unavailable'])) {
                return 'occupied';
            }
            if (in_array($status, ['pending', 'reserved', 'hold'])) {
                return 'pending';
            }
            if (in_array($status, ['closed', 'blocked', 'blackout'])) {
                return 'closed';
            }
        }

        // Check if available rooms
        if (isset($day_data['available_rooms'])) {
            $available = intval($day_data['available_rooms']);
            if ($available > 0) {
                return 'available';
            } else {
                return 'occupied';
            }
        }

        // Check boolean available flag
        if (isset($day_data['available'])) {
            return $day_data['available'] ? 'available' : 'occupied';
        }

        // Default to available if no clear indication
        return 'available';
    }
}