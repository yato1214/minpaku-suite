<?php
/**
 * ACF Fields Registration
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Acf;

if (!defined('ABSPATH')) {
    exit;
}

class RegisterFields
{
    public static function init(): void
    {
        error_log('[MinpakuSuite] RegisterFields::init() called');

        if (function_exists('acf_add_local_field_group')) {
            error_log('[MinpakuSuite] ACF is available, registering fields');
            self::register_property_fields();
        } else {
            error_log('[MinpakuSuite] ACF not available - acf_add_local_field_group function missing');
        }
    }

    private static function register_property_fields(): void
    {
        // Register main property fields
        acf_add_local_field_group([
            'key' => 'group_mcs_property_details',
            'title' => __('Property Details', 'minpaku-suite'),
            'fields' => [
                [
                    'key' => 'field_mcs_capacity',
                    'label' => __('Capacity', 'minpaku-suite'),
                    'name' => 'capacity',
                    'type' => 'number',
                    'instructions' => __('Maximum number of guests', 'minpaku-suite'),
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '25',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => 1,
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => __('guests', 'minpaku-suite'),
                    'min' => 1,
                    'max' => '',
                    'step' => 1,
                ],
                [
                    'key' => 'field_mcs_bedrooms',
                    'label' => __('Bedrooms', 'minpaku-suite'),
                    'name' => 'bedrooms',
                    'type' => 'number',
                    'instructions' => __('Number of bedrooms', 'minpaku-suite'),
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '25',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => 1,
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                    'min' => 0,
                    'max' => '',
                    'step' => 1,
                ],
                [
                    'key' => 'field_mcs_baths',
                    'label' => __('Bathrooms', 'minpaku-suite'),
                    'name' => 'baths',
                    'type' => 'number',
                    'instructions' => __('Number of bathrooms', 'minpaku-suite'),
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '25',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => 1,
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                    'min' => 0,
                    'max' => '',
                    'step' => 0.5,
                ],
                [
                    'key' => 'field_mcs_amenities',
                    'label' => __('Amenities', 'minpaku-suite'),
                    'name' => 'amenities',
                    'type' => 'checkbox',
                    'instructions' => __('Select available amenities', 'minpaku-suite'),
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '25',
                        'class' => '',
                        'id' => '',
                    ],
                    'choices' => [
                        'wifi' => __('WiFi', 'minpaku-suite'),
                        'kitchen' => __('Kitchen', 'minpaku-suite'),
                        'tv' => __('TV', 'minpaku-suite'),
                        'ac' => __('AC', 'minpaku-suite'),
                        'washer' => __('Washer', 'minpaku-suite'),
                        'parking' => __('Parking', 'minpaku-suite'),
                        'bath_tub' => __('Bath Tub', 'minpaku-suite'),
                        'workspace' => __('Workspace', 'minpaku-suite'),
                    ],
                    'allow_custom' => 0,
                    'default_value' => [],
                    'layout' => 'vertical',
                    'toggle' => 0,
                    'return_format' => 'value',
                    'save_custom' => 0,
                ],
                [
                    'key' => 'field_mcs_address',
                    'label' => __('Address', 'minpaku-suite'),
                    'name' => 'address',
                    'type' => 'text',
                    'instructions' => __('Property address', 'minpaku-suite'),
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => '',
                    'placeholder' => __('Enter full address', 'minpaku-suite'),
                    'prepend' => '',
                    'append' => '',
                    'maxlength' => '',
                ],
                [
                    'key' => 'field_mcs_gallery',
                    'label' => __('Gallery', 'minpaku-suite'),
                    'name' => 'gallery',
                    'type' => 'gallery',
                    'instructions' => __('Property photo gallery', 'minpaku-suite'),
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'return_format' => 'array',
                    'preview_size' => 'medium',
                    'insert' => 'append',
                    'library' => 'all',
                    'min' => '',
                    'max' => '',
                    'mime_types' => 'jpg,jpeg,png,webp',
                ],
                [
                    'key' => 'field_mcs_property_excerpt',
                    'label' => __('物件概要', 'minpaku-suite'),
                    'name' => 'property_excerpt',
                    'type' => 'textarea',
                    'instructions' => __('物件一覧およびコネクタサイトで表示される簡潔な説明文', 'minpaku-suite'),
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => '',
                    'placeholder' => __('この物件の簡潔な説明を入力してください...', 'minpaku-suite'),
                    'maxlength' => 300,
                    'rows' => 4,
                    'new_lines' => 'br',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'mcs_property',
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_rest' => 1,
        ]);

    }
}