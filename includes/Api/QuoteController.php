<?php
/**
 * Quote API Controller
 *
 * Handles quote generation API endpoints
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Api;

use MinpakuSuite\Services\RateEngine;

if (!defined('ABSPATH')) {
    exit;
}

class QuoteController {

    /**
     * Initialize the Quote API
     */
    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public static function register_routes(): void {
        // Quote endpoint
        register_rest_route('minpaku/v1', '/quote', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'generate_quote'],
            'permission_callback' => [__CLASS__, 'check_permissions'],
            'args' => [
                'property_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return $param > 0;
                    }
                ],
                'checkin' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                ],
                'checkout' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                ],
                'guests' => [
                    'required' => false,
                    'default' => 2,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return $param >= 1 && $param <= 50;
                    }
                ]
            ]
        ]);
    }

    /**
     * Check permissions for quote API
     */
    public static function check_permissions(\WP_REST_Request $request): bool {
        // For now, allow all requests
        // In production, you might want to implement API key validation
        return true;
    }

    /**
     * Generate quote endpoint handler
     */
    public static function generate_quote(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $property_id = $request->get_param('property_id');
            $checkin = $request->get_param('checkin');
            $checkout = $request->get_param('checkout');
            $guests = $request->get_param('guests');

            // Verify property exists and is published
            $property = get_post($property_id);
            if (!$property || $property->post_type !== 'mcs_property') {
                return new \WP_REST_Response([
                    'error' => __('物件が見つかりません。', 'minpaku-suite'),
                    'message_ja' => __('指定された物件が存在しないか、無効です。', 'minpaku-suite'),
                    'code' => 'property_not_found'
                ], 404);
            }

            if ($property->post_status !== 'publish') {
                return new \WP_REST_Response([
                    'error' => __('この物件は現在利用できません。', 'minpaku-suite'),
                    'message_ja' => __('物件が公開されていないため利用できません。', 'minpaku-suite'),
                    'code' => 'property_not_available'
                ], 422);
            }

            // Additional date validation
            $today = date('Y-m-d');
            if ($checkin < $today) {
                return new \WP_REST_Response([
                    'error' => __('過去の日付は選択できません。', 'minpaku-suite'),
                    'message_ja' => __('チェックイン日は今日以降を選択してください。', 'minpaku-suite'),
                    'code' => 'invalid_checkin_date'
                ], 400);
            }

            // Calculate quote using RateEngine
            $quote = RateEngine::calculate_quote($property_id, $checkin, $checkout, $guests);

            // Log successful quote generation
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[MinpakuSuite] Quote generated for property ' . $property_id .
                         ' (' . $checkin . ' to ' . $checkout . '): ¥' . $quote['grand_total']);
            }

            return new \WP_REST_Response($quote, 200);

        } catch (\InvalidArgumentException $e) {
            // Validation errors (400)
            return new \WP_REST_Response([
                'error' => $e->getMessage(),
                'message_ja' => $e->getMessage(),
                'code' => 'validation_error'
            ], 400);

        } catch (\DomainException $e) {
            // Business logic errors - constraints/availability (422)
            return new \WP_REST_Response([
                'error' => $e->getMessage(),
                'message_ja' => $e->getMessage(),
                'code' => 'constraint_violation'
            ], 422);

        } catch (\Exception $e) {
            // System errors (500)
            error_log('MinpakuSuite Quote API Error: ' . $e->getMessage());

            return new \WP_REST_Response([
                'error' => __('見積の生成に失敗しました。しばらくしてから再度お試しください。', 'minpaku-suite'),
                'message_ja' => __('システムエラーが発生しました。管理者にお問い合わせください。', 'minpaku-suite'),
                'code' => 'quote_generation_error'
            ], 500);
        }
    }

    /**
     * Get REST API base URL for quotes
     */
    public static function get_api_url(): string {
        return rest_url('minpaku/v1/quote');
    }
}