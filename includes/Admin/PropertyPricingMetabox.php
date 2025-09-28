<?php
/**
 * Property Pricing Metabox
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyPricingMetabox {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post', [__CLASS__, 'save_metabox']);
        add_action('wp_ajax_mcs_save_property_pricing', [__CLASS__, 'ajax_save_pricing']);
    }

    /**
     * Add pricing metabox to property post type
     */
    public static function add_metabox() {
        add_meta_box(
            'mcs-property-pricing',
            __('料金設定', 'minpaku-suite'),
            [__CLASS__, 'render_metabox'],
            'mcs_property',
            'normal',
            'high'
        );
    }

    /**
     * Render pricing metabox
     */
    public static function render_metabox($post) {
        wp_nonce_field('mcs_property_pricing_nonce', 'mcs_property_pricing_nonce');

        $pricing = self::get_property_pricing($post->ID);
        ?>
        <div id="mcs-property-pricing-container">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="base_nightly_price"><?php echo esc_html__('ベース1泊料金', 'minpaku-suite'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="base_nightly_price" name="mcs_property_pricing[base_nightly_price]"
                                   value="<?php echo esc_attr($pricing['base_nightly_price']); ?>"
                                   class="regular-text" min="0" step="100" />
                            <span class="description"><?php echo esc_html__('円', 'minpaku-suite'); ?></span>
                            <p class="description"><?php echo esc_html__('この施設のベース1泊料金。季節料金や前日割増の基準となります。', 'minpaku-suite'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="cleaning_fee_per_booking"><?php echo esc_html__('清掃費（予約あたり）', 'minpaku-suite'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cleaning_fee_per_booking" name="mcs_property_pricing[cleaning_fee_per_booking]"
                                   value="<?php echo esc_attr($pricing['cleaning_fee_per_booking']); ?>"
                                   class="regular-text" min="0" step="100" />
                            <span class="description"><?php echo esc_html__('円', 'minpaku-suite'); ?></span>
                            <p class="description"><?php echo esc_html__('予約あたりの清掃費。カレンダーには表示されず、予約時のみ加算されます。', 'minpaku-suite'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3><?php echo esc_html__('前日割増料金', 'minpaku-suite'); ?></h3>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('土曜前夜', 'minpaku-suite'); ?></th>
                        <td>
                            <input type="number" name="mcs_property_pricing[eve_surcharge_sat]"
                                   value="<?php echo esc_attr($pricing['eve_surcharge_sat']); ?>"
                                   min="0" step="100" />
                            <span class="description"><?php echo esc_html__('円', 'minpaku-suite'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('日曜前夜', 'minpaku-suite'); ?></th>
                        <td>
                            <input type="number" name="mcs_property_pricing[eve_surcharge_sun]"
                                   value="<?php echo esc_attr($pricing['eve_surcharge_sun']); ?>"
                                   min="0" step="100" />
                            <span class="description"><?php echo esc_html__('円', 'minpaku-suite'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('祝日前夜', 'minpaku-suite'); ?></th>
                        <td>
                            <input type="number" name="mcs_property_pricing[eve_surcharge_holiday]"
                                   value="<?php echo esc_attr($pricing['eve_surcharge_holiday']); ?>"
                                   min="0" step="100" />
                            <span class="description"><?php echo esc_html__('円', 'minpaku-suite'); ?></span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3><?php echo esc_html__('季節料金ルール', 'minpaku-suite'); ?></h3>
            <div id="seasonal-rules-container" style="margin-bottom: 20px;">
                <?php if (!empty($pricing['seasonal_rules'])): ?>
                    <?php foreach ($pricing['seasonal_rules'] as $index => $rule): ?>
                        <?php self::render_seasonal_rule_row($index, $rule); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php self::render_seasonal_rule_row(0, []); ?>
                <?php endif; ?>
            </div>

            <p>
                <button type="button" id="add-seasonal-rule" class="button">
                    <?php echo esc_html__('ルールを追加', 'minpaku-suite'); ?>
                </button>
            </p>
            <p class="description"><?php echo esc_html__('特定期間の料金設定。「上書き」は基本料金を置き換え、「加算」は基本料金に追加されます。', 'minpaku-suite'); ?></p>

            <h3><?php echo esc_html__('ブラックアウト期間', 'minpaku-suite'); ?></h3>
            <div id="blackout-ranges-container" style="margin-bottom: 20px;">
                <?php if (!empty($pricing['blackout_ranges'])): ?>
                    <?php foreach ($pricing['blackout_ranges'] as $index => $range): ?>
                        <?php self::render_blackout_range_row($index, $range); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php self::render_blackout_range_row(0, []); ?>
                <?php endif; ?>
            </div>

            <p>
                <button type="button" id="add-blackout-range" class="button">
                    <?php echo esc_html__('期間を追加', 'minpaku-suite'); ?>
                </button>
            </p>
            <p class="description"><?php echo esc_html__('予約を受け付けない期間を設定します。この期間の日付はグレー表示され、クリックできません。', 'minpaku-suite'); ?></p>
        </div>

        <style>
        .seasonal-rule-row, .blackout-range-row {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }
        .seasonal-rule-row input, .blackout-range-row input,
        .seasonal-rule-row select {
            margin-right: 5px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Add seasonal rule
            $('#add-seasonal-rule').on('click', function() {
                var container = $('#seasonal-rules-container');
                var index = container.find('.seasonal-rule-row').length;
                var newRow = `
                    <div class="seasonal-rule-row">
                        <input type="date" name="mcs_property_pricing[seasonal_rules][${index}][date_from]" value="" placeholder="<?php echo esc_attr__('開始日', 'minpaku-suite'); ?>" />
                        -
                        <input type="date" name="mcs_property_pricing[seasonal_rules][${index}][date_to]" value="" placeholder="<?php echo esc_attr__('終了日', 'minpaku-suite'); ?>" />
                        <select name="mcs_property_pricing[seasonal_rules][${index}][mode]">
                            <option value="override"><?php echo esc_html__('上書き', 'minpaku-suite'); ?></option>
                            <option value="add"><?php echo esc_html__('加算', 'minpaku-suite'); ?></option>
                        </select>
                        <input type="number" name="mcs_property_pricing[seasonal_rules][${index}][amount]" value="" min="0" step="100" placeholder="<?php echo esc_attr__('金額', 'minpaku-suite'); ?>" />
                        <?php echo esc_html__('円', 'minpaku-suite'); ?>
                        <button type="button" class="button remove-seasonal-rule"><?php echo esc_html__('削除', 'minpaku-suite'); ?></button>
                    </div>
                `;
                container.append(newRow);
            });

            // Remove seasonal rule
            $(document).on('click', '.remove-seasonal-rule', function() {
                $(this).closest('.seasonal-rule-row').remove();
            });

            // Add blackout range
            $('#add-blackout-range').on('click', function() {
                var container = $('#blackout-ranges-container');
                var index = container.find('.blackout-range-row').length;
                var newRow = `
                    <div class="blackout-range-row">
                        <input type="date" name="mcs_property_pricing[blackout_ranges][${index}][date_from]" value="" placeholder="<?php echo esc_attr__('開始日', 'minpaku-suite'); ?>" />
                        -
                        <input type="date" name="mcs_property_pricing[blackout_ranges][${index}][date_to]" value="" placeholder="<?php echo esc_attr__('終了日', 'minpaku-suite'); ?>" />
                        <button type="button" class="button remove-blackout-range"><?php echo esc_html__('削除', 'minpaku-suite'); ?></button>
                    </div>
                `;
                container.append(newRow);
            });

            // Remove blackout range
            $(document).on('click', '.remove-blackout-range', function() {
                $(this).closest('.blackout-range-row').remove();
            });
        });
        </script>
        <?php
    }

    /**
     * Render seasonal rule row
     */
    private static function render_seasonal_rule_row($index, $rule = []) {
        $rule = array_merge([
            'date_from' => '',
            'date_to' => '',
            'mode' => 'override',
            'amount' => 0
        ], $rule);
        ?>
        <div class="seasonal-rule-row">
            <input type="date" name="mcs_property_pricing[seasonal_rules][<?php echo $index; ?>][date_from]"
                   value="<?php echo esc_attr($rule['date_from']); ?>" placeholder="<?php echo esc_attr__('開始日', 'minpaku-suite'); ?>" />
            -
            <input type="date" name="mcs_property_pricing[seasonal_rules][<?php echo $index; ?>][date_to]"
                   value="<?php echo esc_attr($rule['date_to']); ?>" placeholder="<?php echo esc_attr__('終了日', 'minpaku-suite'); ?>" />
            <select name="mcs_property_pricing[seasonal_rules][<?php echo $index; ?>][mode]">
                <option value="override" <?php selected($rule['mode'], 'override'); ?>><?php echo esc_html__('上書き', 'minpaku-suite'); ?></option>
                <option value="add" <?php selected($rule['mode'], 'add'); ?>><?php echo esc_html__('加算', 'minpaku-suite'); ?></option>
            </select>
            <input type="number" name="mcs_property_pricing[seasonal_rules][<?php echo $index; ?>][amount]"
                   value="<?php echo esc_attr($rule['amount']); ?>" min="0" step="100" placeholder="<?php echo esc_attr__('金額', 'minpaku-suite'); ?>" />
            <?php echo esc_html__('円', 'minpaku-suite'); ?>
            <button type="button" class="button remove-seasonal-rule"><?php echo esc_html__('削除', 'minpaku-suite'); ?></button>
        </div>
        <?php
    }

    /**
     * Render blackout range row
     */
    private static function render_blackout_range_row($index, $range = []) {
        $range = array_merge([
            'date_from' => '',
            'date_to' => ''
        ], $range);
        ?>
        <div class="blackout-range-row">
            <input type="date" name="mcs_property_pricing[blackout_ranges][<?php echo $index; ?>][date_from]"
                   value="<?php echo esc_attr($range['date_from']); ?>" placeholder="<?php echo esc_attr__('開始日', 'minpaku-suite'); ?>" />
            -
            <input type="date" name="mcs_property_pricing[blackout_ranges][<?php echo $index; ?>][date_to]"
                   value="<?php echo esc_attr($range['date_to']); ?>" placeholder="<?php echo esc_attr__('終了日', 'minpaku-suite'); ?>" />
            <button type="button" class="button remove-blackout-range"><?php echo esc_html__('削除', 'minpaku-suite'); ?></button>
        </div>
        <?php
    }

    /**
     * Save metabox data
     */
    public static function save_metabox($post_id) {
        // Check nonce
        if (!isset($_POST['mcs_property_pricing_nonce']) ||
            !wp_verify_nonce($_POST['mcs_property_pricing_nonce'], 'mcs_property_pricing_nonce')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check post type
        if (get_post_type($post_id) !== 'mcs_property') {
            return;
        }

        // Save pricing data
        if (isset($_POST['mcs_property_pricing'])) {
            $pricing_data = self::sanitize_pricing_data($_POST['mcs_property_pricing']);
            update_post_meta($post_id, '_mcs_property_pricing', $pricing_data);
        }
    }

    /**
     * Sanitize pricing data
     */
    private static function sanitize_pricing_data($input) {
        $sanitized = [];

        // Basic pricing fields
        if (isset($input['base_nightly_price'])) {
            $sanitized['base_nightly_price'] = max(0, floatval($input['base_nightly_price']));
        }

        if (isset($input['cleaning_fee_per_booking'])) {
            $sanitized['cleaning_fee_per_booking'] = max(0, floatval($input['cleaning_fee_per_booking']));
        }

        if (isset($input['eve_surcharge_sat'])) {
            $sanitized['eve_surcharge_sat'] = max(0, floatval($input['eve_surcharge_sat']));
        }

        if (isset($input['eve_surcharge_sun'])) {
            $sanitized['eve_surcharge_sun'] = max(0, floatval($input['eve_surcharge_sun']));
        }

        if (isset($input['eve_surcharge_holiday'])) {
            $sanitized['eve_surcharge_holiday'] = max(0, floatval($input['eve_surcharge_holiday']));
        }

        // Sanitize seasonal rules
        if (isset($input['seasonal_rules']) && is_array($input['seasonal_rules'])) {
            $sanitized['seasonal_rules'] = [];
            foreach ($input['seasonal_rules'] as $rule) {
                if (!empty($rule['date_from']) && !empty($rule['date_to']) && !empty($rule['mode']) && isset($rule['amount'])) {
                    $sanitized_rule = [
                        'date_from' => sanitize_text_field($rule['date_from']),
                        'date_to' => sanitize_text_field($rule['date_to']),
                        'mode' => in_array($rule['mode'], ['override', 'add']) ? $rule['mode'] : 'override',
                        'amount' => max(0, floatval($rule['amount']))
                    ];
                    if (strtotime($sanitized_rule['date_from']) && strtotime($sanitized_rule['date_to'])) {
                        $sanitized['seasonal_rules'][] = $sanitized_rule;
                    }
                }
            }
        }

        // Sanitize blackout ranges
        if (isset($input['blackout_ranges']) && is_array($input['blackout_ranges'])) {
            $sanitized['blackout_ranges'] = [];
            foreach ($input['blackout_ranges'] as $range) {
                if (!empty($range['date_from']) && !empty($range['date_to'])) {
                    $sanitized_range = [
                        'date_from' => sanitize_text_field($range['date_from']),
                        'date_to' => sanitize_text_field($range['date_to'])
                    ];
                    if (strtotime($sanitized_range['date_from']) && strtotime($sanitized_range['date_to'])) {
                        $sanitized['blackout_ranges'][] = $sanitized_range;
                    }
                }
            }
        }

        return $sanitized;
    }

    /**
     * Get property pricing settings
     */
    public static function get_property_pricing($property_id) {
        $pricing = get_post_meta($property_id, '_mcs_property_pricing', true);

        if (!is_array($pricing)) {
            $pricing = [];
        }

        return array_merge([
            'base_nightly_price' => 15000,
            'cleaning_fee_per_booking' => 3000,
            'eve_surcharge_sat' => 2000,
            'eve_surcharge_sun' => 1000,
            'eve_surcharge_holiday' => 1500,
            'seasonal_rules' => [],
            'blackout_ranges' => []
        ], $pricing);
    }

    /**
     * AJAX save property pricing
     */
    public static function ajax_save_pricing() {
        check_ajax_referer('mcs_property_pricing_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to perform this action.', 'minpaku-suite'));
        }

        $property_id = intval($_POST['property_id'] ?? 0);
        $pricing_data = $_POST['pricing'] ?? [];

        if (!$property_id || get_post_type($property_id) !== 'mcs_property') {
            wp_send_json_error(['message' => __('Invalid property ID.', 'minpaku-suite')]);
        }

        $sanitized = self::sanitize_pricing_data($pricing_data);
        update_post_meta($property_id, '_mcs_property_pricing', $sanitized);

        wp_send_json_success([
            'message' => __('料金設定を保存しました。', 'minpaku-suite'),
            'pricing' => $sanitized
        ]);
    }
}