<?php
/**
 * Property Calendar Preview Metabox
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyCalendarMetabox {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
    }

    /**
     * Add calendar preview metabox to property post type
     */
    public static function add_metabox() {
        add_meta_box(
            'mcs-property-calendar-preview',
            __('カレンダープレビュー', 'minpaku-suite'),
            [__CLASS__, 'render_metabox'],
            'mcs_property',
            'normal',
            'default'
        );
    }

    /**
     * Render calendar preview metabox
     */
    public static function render_metabox($post) {
        ?>
        <div id="mcs-property-calendar-preview-container">
            <p style="margin-bottom: 15px; color: #666;">
                <?php echo esc_html__('この施設のカレンダー表示プレビューです。料金設定を変更した後は、保存してからプレビューを確認してください。', 'minpaku-suite'); ?>
            </p>

            <?php if ($post->post_status === 'auto-draft'): ?>
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 6px;">
                    <strong><?php echo esc_html__('注意', 'minpaku-suite'); ?>:</strong>
                    <?php echo esc_html__('新しい施設では、最初に保存した後にカレンダーが表示されます。', 'minpaku-suite'); ?>
                </div>
            <?php else: ?>
                <!-- Display calendar for this property -->
                <div style="border: 1px solid #e1e5e9; border-radius: 8px; padding: 16px; background: #fafbfc;">
                    <?php
                    // Include the calendar shortcode
                    if (class_exists('MinpakuSuite\Shortcodes\PortalCalendar')) {
                        echo do_shortcode('[portal_calendar property_id="' . $post->ID . '" months="2" show_prices="true"]');
                    } else {
                        echo '<p style="color: #dc3545;">' . esc_html__('カレンダー機能が利用できません。', 'minpaku-suite') . '</p>';
                    }
                    ?>
                </div>

                <div style="margin-top: 16px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #667eea;">
                    <h4 style="margin: 0 0 8px 0; color: #2c3e50; font-size: 14px;">
                        <?php echo esc_html__('ショートコード', 'minpaku-suite'); ?>
                    </h4>
                    <p style="margin: 0 0 8px 0; font-size: 13px; color: #666;">
                        <?php echo esc_html__('この施設のカレンダーを他の場所で表示する場合は、以下のショートコードを使用してください：', 'minpaku-suite'); ?>
                    </p>
                    <code style="background: #fff; padding: 8px 12px; border-radius: 4px; display: block; border: 1px solid #ddd; font-family: 'Courier New', monospace; font-size: 12px;">
                        [portal_calendar property_id="<?php echo $post->ID; ?>" months="2" show_prices="true"]
                    </code>
                    <p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">
                        <?php echo esc_html__('施設編集画面内では property_id の指定は不要です（自動検出されます）', 'minpaku-suite'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <style>
        #mcs-property-calendar-preview-container .portal-calendar {
            margin: 0;
        }

        #mcs-property-calendar-preview-container .portal-calendar .mpc-calendar-month {
            margin-bottom: 16px;
        }

        #mcs-property-calendar-preview-container .portal-calendar .mpc-calendar-legend {
            margin-bottom: 16px;
        }
        </style>
        <?php
    }
}