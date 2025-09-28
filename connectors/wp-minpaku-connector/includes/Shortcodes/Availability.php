<?php
/**
 * Availability Calendar Shortcode with Responsive Layout
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class MPC_Shortcodes_Availability {

    public static function init() {
        // This class is called from Embed.php, no separate shortcode registration needed
    }

    /**
     * Render responsive availability calendar
     */
    public static function render_calendar($atts, $api) {
        $property_id = intval($atts['property_id'] ?? 0);
        $months = max(1, min(12, intval($atts['months'] ?? 2)));
        $show_prices = ($atts['show_prices'] ?? 'true') === 'true';
        $interactions = sanitize_text_field($atts['interactions'] ?? 'modern');
        $css_class = sanitize_html_class($atts['class'] ?? '');

        if (empty($property_id)) {
            return self::render_error(__('物件IDが必要です。', 'wp-minpaku-connector'));
        }

        // Enqueue assets
        self::enqueue_assets();

        // Generate unique calendar ID
        $calendar_id = 'wpmc-availability-' . uniqid();

        // Get property data from API
        $property_data = self::get_property_data($api, $property_id);
        if (!$property_data) {
            return self::render_error(__('物件データの取得に失敗しました。', 'wp-minpaku-connector'));
        }

        // Generate calendar HTML with debug info
        ob_start();
        ?>
        <!-- Debug: Calendar initialization start -->
        <div style="background: #f0f8ff; padding: 10px; margin: 10px 0; border: 1px solid #0066cc;">
            <strong>デバッグ情報:</strong><br>
            Property ID: <?php echo esc_html($property_id); ?><br>
            Calendar ID: <?php echo esc_html($calendar_id); ?><br>
            Months: <?php echo esc_html($months); ?><br>
            Show Prices: <?php echo $show_prices ? 'Yes' : 'No'; ?><br>
            Interactions: <?php echo esc_html($interactions); ?><br>
            Property Title: <?php echo esc_html($property_data['title'] ?? 'No title'); ?>
        </div>

        <div class="wpmc-availability <?php echo esc_attr($css_class); ?>"
             id="<?php echo esc_attr($calendar_id); ?>"
             data-property-id="<?php echo esc_attr($property_id); ?>"
             data-months="<?php echo esc_attr($months); ?>"
             data-interactions="<?php echo esc_attr($interactions); ?>"
             data-show-prices="<?php echo $show_prices ? 'true' : 'false'; ?>">

            <!-- Calendar Navigation Header -->
            <div class="wpmc-nav">
                <button type="button" class="wpmc-nav__prev"
                        aria-label="<?php esc_attr_e('前月を表示', 'wp-minpaku-connector'); ?>"
                        disabled>
                    <span class="wpmc-nav__icon">‹</span>
                    <span class="wpmc-nav__text"><?php esc_html_e('前月', 'wp-minpaku-connector'); ?></span>
                </button>

                <div class="wpmc-nav__label">
                    <h3 class="wpmc-nav__title"><?php echo esc_html($property_data['title'] ?? __('空室カレンダー', 'wp-minpaku-connector')); ?></h3>
                    <span class="wpmc-nav__date-range" aria-live="polite"></span>
                </div>

                <button type="button" class="wpmc-nav__next"
                        aria-label="<?php esc_attr_e('次月を表示', 'wp-minpaku-connector'); ?>">
                    <span class="wpmc-nav__text"><?php esc_html_e('次月', 'wp-minpaku-connector'); ?></span>
                    <span class="wpmc-nav__icon">›</span>
                </button>
            </div>

            <!-- Calendar Months Grid -->
            <div class="wpmc-calendar-grid" role="application" aria-label="<?php esc_attr_e('空室カレンダー', 'wp-minpaku-connector'); ?>">
                <!-- Debug: Grid container ready for months -->
                <div style="grid-column: 1 / -1; background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffeaa7;">
                    <strong>JavaScriptがここに月表示を追加します</strong><br>
                    Grid ready for JavaScript calendar rendering.
                </div>
            </div>

            <!-- Loading State -->
            <div class="wpmc-loading" style="display: none;">
                <div class="wpmc-loading__spinner"></div>
                <p class="wpmc-loading__text"><?php esc_html_e('読み込み中...', 'wp-minpaku-connector'); ?></p>
            </div>
        </div>

        <!-- Quote Panel (separate from calendar) -->
        <div class="wpmc-quote-panel" aria-live="polite" style="display: none;">
            <div class="wpmc-quote-panel__header">
                <h4 class="wpmc-quote-panel__title"><?php esc_html_e('見積り詳細', 'wp-minpaku-connector'); ?></h4>
                <button type="button" class="wpmc-quote-panel__close" aria-label="<?php esc_attr_e('見積りパネルを閉じる', 'wp-minpaku-connector'); ?>">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="wpmc-quote-panel__content">
                <!-- Quote content will be populated by JavaScript -->
            </div>
        </div>

        <!-- Debug: CSS and JS load test -->
        <div style="background: #d4edda; padding: 10px; margin: 10px 0; border: 1px solid #c3e6cb;">
            <strong>CSS/JS テスト:</strong><br>
            <span class="wpmc-nav__title" style="color: #667eea;">CSSが読み込まれていればこの文字は青色になります</span><br>
            <button onclick="alert('JavaScriptが動作しています!')">JSテスト</button>
        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Get property data from API
     */
    private static function get_property_data($api, $property_id) {
        try {
            $response = $api->get_property($property_id);
            if ($response && isset($response['data'])) {
                return $response['data'];
            }
        } catch (Exception $e) {
            error_log('[WMC Availability] Property data fetch failed: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Enqueue calendar assets - using inline styles/scripts for reliability
     */
    private static function enqueue_assets() {
        // Get CSS file content
        $css_path = dirname(dirname(__FILE__)) . '/assets/css/availability-calendar.css';
        $js_path = dirname(dirname(__FILE__)) . '/assets/js/availability-calendar.js';

        // Inline CSS to avoid loading issues
        if (file_exists($css_path)) {
            $css_content = file_get_contents($css_path);
            if ($css_content) {
                echo '<style type="text/css" id="wpmc-availability-calendar-css">' . $css_content . '</style>';
            }
        }

        // Enqueue jQuery dependency
        wp_enqueue_script('jquery');

        // Inline JavaScript to avoid loading issues
        if (file_exists($js_path)) {
            $js_content = file_get_contents($js_path);
            if ($js_content) {
                // Localize variables for AJAX and i18n
                $localize_data = array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wpmc_availability_nonce'),
                    'i18n' => array(
                        'loading' => __('読み込み中...', 'wp-minpaku-connector'),
                        'error' => __('エラーが発生しました', 'wp-minpaku-connector'),
                        'noData' => __('データがありません', 'wp-minpaku-connector'),
                        'available' => __('空き', 'wp-minpaku-connector'),
                        'occupied' => __('満室', 'wp-minpaku-connector'),
                        'pending' => __('保留', 'wp-minpaku-connector'),
                        'closed' => __('休業', 'wp-minpaku-connector'),
                        'clickToSelect' => __('クリックして日付を選択', 'wp-minpaku-connector'),
                        'priceLabel' => __('価格', 'wp-minpaku-connector'),
                        'prevMonth' => __('前月', 'wp-minpaku-connector'),
                        'nextMonth' => __('次月', 'wp-minpaku-connector'),
                        'monthYear' => __('YYYY年M月', 'wp-minpaku-connector'),
                    )
                );

                echo '<script type="text/javascript" id="wpmc-availability-calendar-js">';
                echo 'var wpmcAvailability = ' . json_encode($localize_data) . ';';
                echo $js_content;
                // Add immediate debug check
                echo '
                jQuery(document).ready(function($) {
                    setTimeout(function() {

                        // Check if calendar containers exist
                        $(".wpmc-availability").each(function() {
                            var id = $(this).attr("id");
                            var $grid = $(this).find(".wpmc-calendar-grid");
                        });
                    }, 2000);
                });
                ';
                echo '</script>';
            }
        }
    }

    /**
     * Render error message
     */
    private static function render_error($message) {
        return '<div class="wpmc-error wpmc-error--availability">' .
               '<strong>' . esc_html__('カレンダーエラー', 'wp-minpaku-connector') . '</strong><br>' .
               '<span>' . esc_html($message) . '</span>' .
               '</div>';
    }
}