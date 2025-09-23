<?php
/**
 * Simple ACF Pricing Fields for Testing
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Acf;

if (!defined('ABSPATH')) {
    exit;
}

class PricingFieldsSimple
{
    public static function init()
    {
        error_log('[MinpakuSuite] PricingFieldsSimple::init() called');
        add_action('acf/init', [__CLASS__, 'register_simple_pricing_fields']);
    }

    public static function register_simple_pricing_fields()
    {
        error_log('[MinpakuSuite] register_simple_pricing_fields() called');

        if (!function_exists('acf_add_local_field_group')) {
            error_log('[MinpakuSuite] ACF not available');
            return;
        }

        error_log('[MinpakuSuite] Adding simple pricing field group');

        acf_add_local_field_group([
            'key' => 'group_simple_pricing',
            'title' => '料金設定 (テスト)',
            'fields' => [
                [
                    'key' => 'field_test_base_rate',
                    'label' => '基本料金',
                    'name' => 'test_base_rate',
                    'type' => 'number',
                    'instructions' => '1泊あたりの基本料金（円）',
                    'required' => 0,
                    'default_value' => 10000,
                    'min' => 0,
                    'step' => 100
                ],
                [
                    'key' => 'field_test_cleaning_fee',
                    'label' => '清掃費',
                    'name' => 'test_cleaning_fee',
                    'type' => 'number',
                    'instructions' => '1回あたりの清掃費（円）',
                    'required' => 0,
                    'default_value' => 0,
                    'min' => 0,
                    'step' => 100
                ]
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'mcs_property'
                    ]
                ]
            ],
            'menu_order' => 30,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label'
        ]);

        error_log('[MinpakuSuite] Simple pricing field group registered successfully');
    }
}