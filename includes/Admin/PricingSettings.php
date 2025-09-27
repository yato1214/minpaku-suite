<?php
/**
 * Pricing Settings Admin Page
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class PricingSettings {

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('wp_ajax_mcs_save_pricing_settings', [__CLASS__, 'ajax_save_settings']);
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            'mcs_pricing_settings',
            'mcs_pricing_settings',
            [__CLASS__, 'sanitize_settings']
        );
    }

    /**
     * Render pricing settings page
     */
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'minpaku-suite'));
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('料金設定', 'minpaku-suite'); ?></h1>

            <p><?php echo esc_html__('料金設定は各施設ごとに個別で設定します。', 'minpaku-suite'); ?></p>

            <div class="notice notice-info">
                <p>
                    <strong><?php echo esc_html__('施設ごとの料金設定', 'minpaku-suite'); ?></strong><br>
                    <?php echo esc_html__('各施設の料金設定は「施設」の編集画面から行ってください。施設リストから編集したい施設を選択し、「料金設定」メタボックスで設定できます。', 'minpaku-suite'); ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('edit.php?post_type=mcs_property'); ?>" class="button button-primary">
                        <?php echo esc_html__('施設一覧を表示', 'minpaku-suite'); ?>
                    </a>
                </p>
            </div>

            <h2><?php echo esc_html__('カレンダー表示', 'minpaku-suite'); ?></h2>
            <p><?php echo esc_html__('ポータル側でもカレンダーを表示できます。施設編集画面では自動的にプレビューが表示され、他の場所ではショートコードを使用できます。', 'minpaku-suite'); ?></p>

            <div style="background: #f1f1f1; padding: 15px; border-radius: 4px; margin: 15px 0;">
                <h3><?php echo esc_html__('ショートコード例', 'minpaku-suite'); ?></h3>

                <h4><?php echo esc_html__('基本的な使用方法', 'minpaku-suite'); ?></h4>
                <code>[portal_calendar property_id="1" months="2" show_prices="true"]</code>

                <h4 style="margin-top: 15px;"><?php echo esc_html__('施設編集画面内での使用（property_id自動検出）', 'minpaku-suite'); ?></h4>
                <code>[portal_calendar months="2" show_prices="true"]</code>

                <ul style="margin-top: 15px;">
                    <li><strong>property_id:</strong> <?php echo esc_html__('表示する施設のID（施設編集画面では自動検出されるため省略可能）', 'minpaku-suite'); ?></li>
                    <li><strong>months:</strong> <?php echo esc_html__('表示する月数（デフォルト: 2）', 'minpaku-suite'); ?></li>
                    <li><strong>show_prices:</strong> <?php echo esc_html__('価格を表示するか（true/false、デフォルト: true）', 'minpaku-suite'); ?></li>
                </ul>
            </div>

            <div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 4px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: #0066cc;"><?php echo esc_html__('自動機能', 'minpaku-suite'); ?></h3>
                <ul style="margin-bottom: 0;">
                    <li><?php echo esc_html__('各施設の編集画面に「カレンダープレビュー」メタボックスが自動表示されます', 'minpaku-suite'); ?></li>
                    <li><?php echo esc_html__('施設編集画面内ではproperty_idの指定が不要です（自動検出）', 'minpaku-suite'); ?></li>
                    <li><?php echo esc_html__('料金設定を変更した場合は、保存後にプレビューが更新されます', 'minpaku-suite'); ?></li>
                </ul>
            </div>

        </div>

        <?php
    }
}