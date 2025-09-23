<?php
/**
 * ACF Pricing Fields Configuration
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Acf;

if (!defined('ABSPATH')) {
    exit;
}

class PricingFields
{
    public static function init()
    {
        error_log('[MinpakuSuite] PricingFields::init() called');
        add_action('acf/init', [__CLASS__, 'register_pricing_fields']);
    }

    public static function register_pricing_fields()
    {
        error_log('[MinpakuSuite] register_pricing_fields() called');

        if (!function_exists('acf_add_local_field_group')) {
            error_log('[MinpakuSuite] ACF not available - acf_add_local_field_group function not found');
            return;
        }

        error_log('[MinpakuSuite] Registering pricing field group');

        acf_add_local_field_group([
            'key' => 'group_pricing',
            'title' => __('Pricing Settings', 'minpaku-suite'),
            'fields' => [
                // Basic Rate
                [
                    'key' => 'field_base_rate',
                    'label' => __('Base Rate per Night', 'minpaku-suite'),
                    'name' => 'base_rate',
                    'type' => 'number',
                    'instructions' => __('Default nightly rate in JPY', 'minpaku-suite'),
                    'required' => 1,
                    'default_value' => 10000,
                    'min' => 0,
                    'step' => 100,
                    'wrapper' => [
                        'width' => '50'
                    ]
                ],

                // Currency Override
                [
                    'key' => 'field_currency',
                    'label' => __('Currency', 'minpaku-suite'),
                    'name' => 'currency',
                    'type' => 'select',
                    'instructions' => __('Override default site currency for this property', 'minpaku-suite'),
                    'choices' => [
                        'JPY' => 'Japanese Yen (¥)',
                        'USD' => 'US Dollar ($)',
                        'EUR' => 'Euro (€)',
                        'CNY' => 'Chinese Yuan (¥)'
                    ],
                    'default_value' => 'JPY',
                    'wrapper' => [
                        'width' => '50'
                    ]
                ],

                // Weekday Rates Tab
                [
                    'key' => 'field_weekday_rates_tab',
                    'label' => __('Weekday Rates', 'minpaku-suite'),
                    'type' => 'tab',
                    'instructions' => __('Override base rate for specific days of the week. Leave empty to use base rate.', 'minpaku-suite'),
                ],

                // Weekday Rates Group
                [
                    'key' => 'field_weekday_rates',
                    'label' => __('Daily Rate Overrides', 'minpaku-suite'),
                    'name' => 'weekday_rates',
                    'type' => 'group',
                    'layout' => 'table',
                    'sub_fields' => [
                        [
                            'key' => 'field_sunday_rate',
                            'label' => __('Sunday', 'minpaku-suite'),
                            'name' => '0',
                            'type' => 'number',
                            'min' => 0,
                            'step' => 100,
                            'wrapper' => ['width' => '14.28']
                        ],
                        [
                            'key' => 'field_monday_rate',
                            'label' => __('Monday', 'minpaku-suite'),
                            'name' => '1',
                            'type' => 'number',
                            'min' => 0,
                            'step' => 100,
                            'wrapper' => ['width' => '14.28']
                        ],
                        [
                            'key' => 'field_tuesday_rate',
                            'label' => __('Tuesday', 'minpaku-suite'),
                            'name' => '2',
                            'type' => 'number',
                            'min' => 0,
                            'step' => 100,
                            'wrapper' => ['width' => '14.28']
                        ],
                        [
                            'key' => 'field_wednesday_rate',
                            'label' => __('Wednesday', 'minpaku-suite'),
                            'name' => '3',
                            'type' => 'number',
                            'min' => 0,
                            'step' => 100,
                            'wrapper' => ['width' => '14.28']
                        ],
                        [
                            'key' => 'field_thursday_rate',
                            'label' => __('Thursday', 'minpaku-suite'),
                            'name' => '4',
                            'type' => 'number',
                            'min' => 0,
                            'step' => 100,
                            'wrapper' => ['width' => '14.28']
                        ],
                        [
                            'key' => 'field_friday_rate',
                            'label' => __('Friday', 'minpaku-suite'),
                            'name' => '5',
                            'type' => 'number',
                            'min' => 0,
                            'step' => 100,
                            'wrapper' => ['width' => '14.28']
                        ],
                        [
                            'key' => 'field_saturday_rate',
                            'label' => __('Saturday', 'minpaku-suite'),
                            'name' => '6',
                            'type' => 'number',
                            'min' => 0,
                            'step' => 100,
                            'wrapper' => ['width' => '14.28']
                        ]
                    ]
                ],

                // Seasonal Rates Tab
                [
                    'key' => 'field_seasonal_rates_tab',
                    'label' => __('Seasonal Rates', 'minpaku-suite'),
                    'type' => 'tab',
                    'instructions' => __('Set special rates for specific date ranges. Higher priority numbers override lower ones.', 'minpaku-suite'),
                ],

                // Seasonal Rates Repeater
                [
                    'key' => 'field_seasonal_rates',
                    'label' => __('Seasonal Rate Overrides', 'minpaku-suite'),
                    'name' => 'seasonal_rates',
                    'type' => 'repeater',
                    'layout' => 'table',
                    'button_label' => __('Add Seasonal Rate', 'minpaku-suite'),
                    'sub_fields' => [
                        [
                            'key' => 'field_season_name',
                            'label' => __('Season Name', 'minpaku-suite'),
                            'name' => 'name',
                            'type' => 'text',
                            'wrapper' => ['width' => '20']
                        ],
                        [
                            'key' => 'field_season_start',
                            'label' => __('Start Date', 'minpaku-suite'),
                            'name' => 'start_date',
                            'type' => 'date_picker',
                            'display_format' => 'Y-m-d',
                            'return_format' => 'Y-m-d',
                            'wrapper' => ['width' => '15']
                        ],
                        [
                            'key' => 'field_season_end',
                            'label' => __('End Date', 'minpaku-suite'),
                            'name' => 'end_date',
                            'type' => 'date_picker',
                            'display_format' => 'Y-m-d',
                            'return_format' => 'Y-m-d',
                            'wrapper' => ['width' => '15']
                        ],
                        [
                            'key' => 'field_season_rate',
                            'label' => __('Rate', 'minpaku-suite'),
                            'name' => 'rate',
                            'type' => 'number',
                            'min' => 0,
                            'step' => 100,
                            'wrapper' => ['width' => '15']
                        ],
                        [
                            'key' => 'field_season_min_nights',
                            'label' => __('Min Nights', 'minpaku-suite'),
                            'name' => 'min_nights',
                            'type' => 'number',
                            'min' => 1,
                            'default_value' => 1,
                            'wrapper' => ['width' => '10']
                        ],
                        [
                            'key' => 'field_season_max_nights',
                            'label' => __('Max Nights', 'minpaku-suite'),
                            'name' => 'max_nights',
                            'type' => 'number',
                            'min' => 0,
                            'instructions' => __('0 = no limit', 'minpaku-suite'),
                            'wrapper' => ['width' => '10']
                        ],
                        [
                            'key' => 'field_season_priority',
                            'label' => __('Priority', 'minpaku-suite'),
                            'name' => 'priority',
                            'type' => 'number',
                            'default_value' => 0,
                            'instructions' => __('Higher numbers = higher priority', 'minpaku-suite'),
                            'wrapper' => ['width' => '15']
                        ]
                    ]
                ],

                // Fees Tab
                [
                    'key' => 'field_fees_tab',
                    'label' => __('Fees & Charges', 'minpaku-suite'),
                    'type' => 'tab'
                ],

                // Cleaning Fee
                [
                    'key' => 'field_cleaning_fee',
                    'label' => __('Cleaning Fee', 'minpaku-suite'),
                    'name' => 'cleaning_fee',
                    'type' => 'number',
                    'instructions' => __('One-time cleaning fee per stay', 'minpaku-suite'),
                    'min' => 0,
                    'step' => 100,
                    'default_value' => 0,
                    'wrapper' => ['width' => '33']
                ],

                // Service Fee Type
                [
                    'key' => 'field_service_fee_type',
                    'label' => __('Service Fee Type', 'minpaku-suite'),
                    'name' => 'service_fee_type',
                    'type' => 'radio',
                    'choices' => [
                        'percent' => __('Percentage', 'minpaku-suite'),
                        'fixed' => __('Fixed Amount', 'minpaku-suite')
                    ],
                    'default_value' => 'percent',
                    'wrapper' => ['width' => '33']
                ],

                // Service Fee Percent
                [
                    'key' => 'field_service_fee_percent',
                    'label' => __('Service Fee (%)', 'minpaku-suite'),
                    'name' => 'service_fee_percent',
                    'type' => 'number',
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.1,
                    'default_value' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_service_fee_type',
                                'operator' => '==',
                                'value' => 'percent'
                            ]
                        ]
                    ],
                    'wrapper' => ['width' => '34']
                ],

                // Service Fee Fixed
                [
                    'key' => 'field_service_fee_fixed',
                    'label' => __('Service Fee (Fixed)', 'minpaku-suite'),
                    'name' => 'service_fee_fixed',
                    'type' => 'number',
                    'min' => 0,
                    'step' => 100,
                    'default_value' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_service_fee_type',
                                'operator' => '==',
                                'value' => 'fixed'
                            ]
                        ]
                    ],
                    'wrapper' => ['width' => '34']
                ],

                // Extra Guest Settings
                [
                    'key' => 'field_extra_guest_threshold',
                    'label' => __('Extra Guest Threshold', 'minpaku-suite'),
                    'name' => 'extra_guest_threshold',
                    'type' => 'number',
                    'instructions' => __('Number of guests included in base rate', 'minpaku-suite'),
                    'min' => 0,
                    'default_value' => 2,
                    'wrapper' => ['width' => '50']
                ],

                [
                    'key' => 'field_extra_guest_fee',
                    'label' => __('Extra Guest Fee per Night', 'minpaku-suite'),
                    'name' => 'extra_guest_fee',
                    'type' => 'number',
                    'instructions' => __('Additional fee per extra guest per night', 'minpaku-suite'),
                    'min' => 0,
                    'step' => 100,
                    'default_value' => 0,
                    'wrapper' => ['width' => '50']
                ],

                // Taxes Tab
                [
                    'key' => 'field_taxes_tab',
                    'label' => __('Taxes', 'minpaku-suite'),
                    'type' => 'tab'
                ],

                // Taxes Repeater
                [
                    'key' => 'field_taxes',
                    'label' => __('Tax Configuration', 'minpaku-suite'),
                    'name' => 'taxes',
                    'type' => 'repeater',
                    'layout' => 'table',
                    'button_label' => __('Add Tax', 'minpaku-suite'),
                    'sub_fields' => [
                        [
                            'key' => 'field_tax_name',
                            'label' => __('Tax Name', 'minpaku-suite'),
                            'name' => 'name',
                            'type' => 'text',
                            'default_value' => __('Consumption Tax (10%)', 'minpaku-suite'),
                            'wrapper' => ['width' => '30']
                        ],
                        [
                            'key' => 'field_tax_rate',
                            'label' => __('Rate (%)', 'minpaku-suite'),
                            'name' => 'rate',
                            'type' => 'number',
                            'min' => 0,
                            'max' => 100,
                            'step' => 0.1,
                            'default_value' => 10,
                            'wrapper' => ['width' => '20']
                        ],
                        [
                            'key' => 'field_tax_inclusive',
                            'label' => __('Inclusive', 'minpaku-suite'),
                            'name' => 'inclusive',
                            'type' => 'true_false',
                            'instructions' => __('Tax already included in prices', 'minpaku-suite'),
                            'wrapper' => ['width' => '20']
                        ],
                        [
                            'key' => 'field_tax_items',
                            'label' => __('Applies To', 'minpaku-suite'),
                            'name' => 'taxable_items',
                            'type' => 'checkbox',
                            'choices' => [
                                'accommodation' => __('Accommodation', 'minpaku-suite'),
                                'cleaning' => __('Cleaning Fee', 'minpaku-suite'),
                                'service' => __('Service Fee', 'minpaku-suite'),
                                'extra_guest' => __('Extra Guest Fee', 'minpaku-suite')
                            ],
                            'default_value' => ['accommodation', 'cleaning', 'service', 'extra_guest'],
                            'wrapper' => ['width' => '30']
                        ]
                    ]
                ],

                // Discounts Tab
                [
                    'key' => 'field_discounts_tab',
                    'label' => __('Discounts', 'minpaku-suite'),
                    'type' => 'tab'
                ],

                // Weekly Discount
                [
                    'key' => 'field_weekly_discount_threshold',
                    'label' => __('Weekly Discount Threshold', 'minpaku-suite'),
                    'name' => 'weekly_discount_threshold',
                    'type' => 'number',
                    'instructions' => __('Minimum nights to qualify for weekly discount', 'minpaku-suite'),
                    'min' => 1,
                    'default_value' => 7,
                    'wrapper' => ['width' => '50']
                ],

                [
                    'key' => 'field_weekly_discount_percent',
                    'label' => __('Weekly Discount (%)', 'minpaku-suite'),
                    'name' => 'weekly_discount_percent',
                    'type' => 'number',
                    'instructions' => __('Percentage discount for weekly stays', 'minpaku-suite'),
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.1,
                    'default_value' => 0,
                    'wrapper' => ['width' => '50']
                ],

                // Monthly Discount
                [
                    'key' => 'field_monthly_discount_threshold',
                    'label' => __('Monthly Discount Threshold', 'minpaku-suite'),
                    'name' => 'monthly_discount_threshold',
                    'type' => 'number',
                    'instructions' => __('Minimum nights to qualify for monthly discount', 'minpaku-suite'),
                    'min' => 1,
                    'default_value' => 28,
                    'wrapper' => ['width' => '50']
                ],

                [
                    'key' => 'field_monthly_discount_percent',
                    'label' => __('Monthly Discount (%)', 'minpaku-suite'),
                    'name' => 'monthly_discount_percent',
                    'type' => 'number',
                    'instructions' => __('Percentage discount for monthly stays', 'minpaku-suite'),
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.1,
                    'default_value' => 0,
                    'wrapper' => ['width' => '50']
                ],

                // Constraints Tab
                [
                    'key' => 'field_constraints_tab',
                    'label' => __('Booking Constraints', 'minpaku-suite'),
                    'type' => 'tab'
                ],

                // Stay Length Constraints
                [
                    'key' => 'field_min_nights',
                    'label' => __('Minimum Nights', 'minpaku-suite'),
                    'name' => 'min_nights',
                    'type' => 'number',
                    'instructions' => __('Minimum stay length required', 'minpaku-suite'),
                    'min' => 1,
                    'default_value' => 1,
                    'wrapper' => ['width' => '50']
                ],

                [
                    'key' => 'field_max_nights',
                    'label' => __('Maximum Nights', 'minpaku-suite'),
                    'name' => 'max_nights',
                    'type' => 'number',
                    'instructions' => __('Maximum stay length allowed (0 = no limit)', 'minpaku-suite'),
                    'min' => 0,
                    'default_value' => 0,
                    'wrapper' => ['width' => '50']
                ],

                // Check-in Days
                [
                    'key' => 'field_checkin_days',
                    'label' => __('Allowed Check-in Days', 'minpaku-suite'),
                    'name' => 'checkin_days',
                    'type' => 'checkbox',
                    'instructions' => __('Days of the week when check-in is allowed', 'minpaku-suite'),
                    'choices' => [
                        '0' => __('Sunday', 'minpaku-suite'),
                        '1' => __('Monday', 'minpaku-suite'),
                        '2' => __('Tuesday', 'minpaku-suite'),
                        '3' => __('Wednesday', 'minpaku-suite'),
                        '4' => __('Thursday', 'minpaku-suite'),
                        '5' => __('Friday', 'minpaku-suite'),
                        '6' => __('Saturday', 'minpaku-suite')
                    ],
                    'default_value' => ['0', '1', '2', '3', '4', '5', '6'],
                    'wrapper' => ['width' => '50']
                ],

                // Check-out Days
                [
                    'key' => 'field_checkout_days',
                    'label' => __('Allowed Check-out Days', 'minpaku-suite'),
                    'name' => 'checkout_days',
                    'type' => 'checkbox',
                    'instructions' => __('Days of the week when check-out is allowed', 'minpaku-suite'),
                    'choices' => [
                        '0' => __('Sunday', 'minpaku-suite'),
                        '1' => __('Monday', 'minpaku-suite'),
                        '2' => __('Tuesday', 'minpaku-suite'),
                        '3' => __('Wednesday', 'minpaku-suite'),
                        '4' => __('Thursday', 'minpaku-suite'),
                        '5' => __('Friday', 'minpaku-suite'),
                        '6' => __('Saturday', 'minpaku-suite')
                    ],
                    'default_value' => ['0', '1', '2', '3', '4', '5', '6'],
                    'wrapper' => ['width' => '50']
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
            'menu_order' => 20,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label'
        ]);

        error_log('[MinpakuSuite] Pricing field group registered successfully');
    }
}