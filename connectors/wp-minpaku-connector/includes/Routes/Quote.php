<?php
/**
 * Quote Route Handler
 *
 * Proxies quote requests to portal with HMAC authentication
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Routes;

use MinpakuConnector\Client\MPC_Client_Signer;

if (!defined('ABSPATH')) {
    exit;
}

class Quote {

    /**
     * Initialize quote routes
     */
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        register_rest_route('minpaku-connector/v1', '/quote', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'proxy_quote_request'],
            'permission_callback' => '__return_true', // Public access for now
            'args' => [
                'property_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'checkin' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'checkout' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'adults' => [
                    'required' => false,
                    'default' => 2,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'children' => [
                    'required' => false,
                    'default' => 0,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                // Legacy parameter support for backward compatibility
                'start_date' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'end_date' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'guests' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
    }

    /**
     * Proxy quote request to portal
     */
    public static function proxy_quote_request(\WP_REST_Request $request) {
        try {
            $settings = \WP_Minpaku_Connector::get_settings();

            if (empty($settings['portal_url']) || empty($settings['api_key']) || empty($settings['secret'])) {
                return new \WP_REST_Response([
                    'error' => __('コネクター設定が不完全です。', 'wp-minpaku-connector'),
                    'code' => 'invalid_configuration'
                ], 500);
            }

            // Prepare request data - unified portal format with legacy support
            $quote_data = [
                'property_id' => $request->get_param('property_id'),
                'checkin' => $request->get_param('checkin') ?: $request->get_param('start_date'),
                'checkout' => $request->get_param('checkout') ?: $request->get_param('end_date'),
                'adults' => $request->get_param('adults') ?: $request->get_param('guests') ?: 2,
                'children' => $request->get_param('children') ?: 0
            ];

            // Build portal URL
            $portal_url = rtrim($settings['portal_url'], '/');
            $quote_endpoint = $portal_url . '/wp-json/minpaku/v1/quote';

            // Create HMAC signer
            $signer = new MPC_Client_Signer($settings['api_key'], $settings['secret']);

            // Create request
            $body = json_encode($quote_data);
            $timestamp = time();
            $nonce = wp_generate_uuid4();

            // Generate signature
            $signature = $signer->sign_request('POST', '/wp-json/minpaku/v1/quote', $body, $timestamp, $nonce);

            // Make request to portal
            $response = wp_remote_post($quote_endpoint, [
                'method' => 'POST',
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-MCS-Key' => $settings['api_key'],
                    'X-MCS-Timestamp' => $timestamp,
                    'X-MCS-Nonce' => $nonce,
                    'X-MCS-Signature' => $signature
                ],
                'body' => $body
            ]);

            if (is_wp_error($response)) {
                error_log('[Connector] Quote proxy error: ' . $response->get_error_message());
                return new \WP_REST_Response([
                    'error' => __('ポータルとの通信に失敗しました。', 'wp-minpaku-connector'),
                    'code' => 'connection_error'
                ], 500);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            // Enhanced error handling with Japanese messages
            if ($response_code !== 200) {
                $error_message = '';
                $error_code = 'quote_error';

                switch ($response_code) {
                    case 401:
                        $error_message = __('認証エラー: 署名が不正またはタイムスタンプが古すぎます。', 'wp-minpaku-connector');
                        $error_code = 'authentication_error';
                        break;
                    case 404:
                        $error_message = __('APIエンドポイントが見つかりません。ポータル側のプラグインを更新してください。', 'wp-minpaku-connector');
                        $error_code = 'endpoint_not_found';
                        break;
                    case 422:
                        $portal_error = isset($response_data['message_ja']) ? $response_data['message_ja'] :
                                       (isset($response_data['message']) ? $response_data['message'] : '期間・在庫・定員エラー');
                        $error_message = $portal_error;
                        $error_code = 'validation_error';
                        break;
                    case 500:
                    default:
                        $error_message = __('予期せぬエラーが発生しました。', 'wp-minpaku-connector');
                        $error_code = 'server_error';
                        break;
                }

                return new \WP_REST_Response([
                    'error' => $error_message,
                    'code' => $error_code,
                    'status' => $response_code
                ], $response_code);
            }

            // Log successful quote proxy
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[Connector] Quote proxied successfully for property ' . $quote_data['property_id']);
            }

            // Return portal response
            return new \WP_REST_Response($response_data, 200);

        } catch (\Exception $e) {
            error_log('[Connector] Quote proxy exception: ' . $e->getMessage());
            return new \WP_REST_Response([
                'error' => __('見積り処理中にエラーが発生しました。', 'wp-minpaku-connector'),
                'code' => 'quote_error'
            ], 500);
        }
    }

    /**
     * Get quote API URL
     */
    public static function get_api_url(): string {
        return rest_url('minpaku-connector/v1/quote');
    }
}