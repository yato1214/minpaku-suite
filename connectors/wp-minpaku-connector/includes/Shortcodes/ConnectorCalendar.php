<?php
/**
 * Connector Calendar Shortcode - Portal Calendar Parity
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class MPC_Shortcodes_ConnectorCalendar {

    public static function init() {
        add_shortcode('connector_calendar', [__CLASS__, 'render_calendar']);
        add_shortcode('minpaku_connector', [__CLASS__, 'render_calendar']); // Additional shortcode support

        // Register AJAX handlers for modal functionality
        add_action('wp_ajax_mpc_get_calendar', [__CLASS__, 'ajax_modal_content']);
        add_action('wp_ajax_nopriv_mpc_get_calendar', [__CLASS__, 'ajax_modal_content']);
    }

    /**
     * Render calendar shortcode for connector side - Portal Parity
     */
    public static function render_calendar($atts) {
        try {
            $atts = shortcode_atts([
                'property_id' => '',
                'months' => 2,
                'show_prices' => 'true',
                'modal' => 'false',
                'interactions' => 'modern',
                'type' => 'availability',
                'limit' => 6,
                'columns' => 2
            ], $atts, 'connector_calendar');

        $calendar_type = $atts['type'];

        // Handle different shortcode types
        if ($calendar_type === 'properties') {
            return self::render_properties_list($atts);
        } elseif ($calendar_type === 'property') {
            return self::render_property_detail($atts);
        }

        // For availability calendar, property_id is required
        $property_id = self::get_property_id($atts['property_id']);

        if (!$property_id) {
            return '<div class="mpc-error" style="background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 12px; border-radius: 6px; margin: 16px 0;">' .
                   __('Property ID is required for calendar display. Please specify property_id="X" in the shortcode.', 'wp-minpaku-connector') .
                   '</div>';
        }

        $months = max(1, min(12, intval($atts['months'])));
        $show_prices = ($atts['show_prices'] === 'true');
        $is_modal = false; // Modal functionality deprecated - always inline
        $interactions = $atts['interactions']; // Default to 'modern'
        $calendar_type = $atts['type']; // availability or other types

        // For availability calendar, ensure responsive design: PC=2col, Mobile=1col
        if ($calendar_type === 'availability') {
            $responsive_months = $months; // Keep original months setting
        } else {
            $responsive_months = $months;
        }

        // Check API configuration
        if (!class_exists('MinpakuConnector\Client\MPC_Client_Api')) {
            return '<div class="mpc-error" style="background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 12px; border-radius: 6px; margin: 16px 0;">' .
                   __('API client not available.', 'wp-minpaku-connector') .
                   '</div>';
        }

        $api = new \MinpakuConnector\Client\MPC_Client_Api();
        if (!$api->is_configured()) {
            return '<div class="mpc-error" style="background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 12px; border-radius: 6px; margin: 16px 0;">' .
                   __('Portal connection not configured. Please check the connector settings.', 'wp-minpaku-connector') .
                   '</div>';
        }

        // Skip property validation for now - let the calendar display and handle errors gracefully
        $property_title = '';

        // Try to get property info but don't fail if not found
        $property_response = $api->get_property($property_id);
        if ($property_response['success']) {
            $property_title = isset($property_response['data']['title']) ? $property_response['data']['title'] : '';
        }

        // モーダル表示の場合はボタンを返す
        if ($is_modal) {
            return self::render_modal_button($property_id, $months, $show_prices, $property_title, $interactions);
        }

        // Default to modern interactions with unified quote panel
        if ($interactions === 'legacy') {
            self::enqueue_legacy_assets();
        } else {
            // Always use modern assets for unified experience
            self::enqueue_modern_assets();
            $interactions = 'modern'; // Force modern UI
        }

        // For now, always use direct rendering for reliability
        // Template system can be added later when stable

        $calendar_id = 'connector-calendar-' . uniqid();

        ob_start();
        ?>

        <div id="<?php echo esc_attr($calendar_id); ?>" class="mcs-calendar-container wpmc-calendar-container connector-calendar"
             data-property-id="<?php echo esc_attr($property_id); ?>"
             data-show-prices="<?php echo $show_prices ? '1' : '0'; ?>"
             data-months="<?php echo esc_attr($months); ?>"
             data-interactions="modern"
             data-mode="inline">

            <!-- Calendar Header -->
            <div class="mcs-calendar-header wpmc-calendar-header">
                <h2 class="mcs-calendar-title wpmc-calendar-title">
                    <?php if ($property_title) { ?>
                        <?php echo esc_html($property_title); ?> -
                    <?php } ?>
                    <?php _e('空室カレンダー', 'wp-minpaku-connector'); ?>
                </h2>
            </div>

            <!-- Calendar Navigation -->
            <div class="mcs-calendar-nav wpmc-calendar-nav">
                <button type="button" class="mcs-nav-button mcs-nav-prev wpmc-nav-button wpmc-nav-prev">
                    <span class="mcs-nav-icon wpmc-nav-icon">‹</span>
                    <span class="mcs-nav-text wpmc-nav-text"><?php _e('前月', 'wp-minpaku-connector'); ?></span>
                </button>
                <button type="button" class="mcs-nav-button mcs-nav-next wpmc-nav-button wpmc-nav-next">
                    <span class="mcs-nav-text wpmc-nav-text"><?php _e('次月', 'wp-minpaku-connector'); ?></span>
                    <span class="mcs-nav-icon wpmc-nav-icon">›</span>
                </button>
            </div>

            <!-- Calendar Grid Container -->
            <div class="mpc-calendar-months-grid mpc-responsive-grid">
                <?php for ($i = 0; $i < $responsive_months; $i++) { ?>
                    <?php
                    $month_date = new \DateTime();
                    $month_date->add(new \DateInterval('P' . $i . 'M'));
                    $year = $month_date->format('Y');
                    $month = $month_date->format('n');
                    ?>

                    <div class="mpc-calendar-month"
                         data-year="<?php echo esc_attr($year); ?>"
                         data-month="<?php echo esc_attr($month); ?>"
                         data-month-index="<?php echo esc_attr($i); ?>">

                        <h3 class="mpc-calendar-month-title">
                            <?php echo esc_html($month_date->format('Y年n月')); ?>
                        </h3>

                        <div class="mpc-calendar-grid" style="display: grid !important; grid-template-columns: repeat(7, 1fr) !important; gap: 0 !important; width: 100% !important;">
                            <!-- Day headers -->
                            <div class="mpc-calendar-day-header" style="background: #f8fafc; padding: 12px 8px; text-align: center; font-weight: 600; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #e2e8f0;"><?php _e('日', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header" style="background: #f8fafc; padding: 12px 8px; text-align: center; font-weight: 600; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #e2e8f0;"><?php _e('月', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header" style="background: #f8fafc; padding: 12px 8px; text-align: center; font-weight: 600; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #e2e8f0;"><?php _e('火', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header" style="background: #f8fafc; padding: 12px 8px; text-align: center; font-weight: 600; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #e2e8f0;"><?php _e('水', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header" style="background: #f8fafc; padding: 12px 8px; text-align: center; font-weight: 600; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #e2e8f0;"><?php _e('木', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header" style="background: #f8fafc; padding: 12px 8px; text-align: center; font-weight: 600; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #e2e8f0;"><?php _e('金', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header" style="background: #f8fafc; padding: 12px 8px; text-align: center; font-weight: 600; border-bottom: 2px solid #e2e8f0;"><?php _e('土', 'wp-minpaku-connector'); ?></div>

                            <?php echo self::generate_calendar_days($year, $month, $property_id, $show_prices); ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

        </div>

        <!-- Connector Calendar: CSS/JS loaded via enqueue_modern_assets() -->

        <?php
        $calendar_html = ob_get_clean();

        // Include unified quote panel template
        ob_start();
        $texts = [
            'quote_title' => __('見積り', 'wp-minpaku-connector'),
            'clear_selection' => __('選択をクリア', 'wp-minpaku-connector'),
            'select_dates_placeholder' => __('日程を選択すると見積が表示されます', 'wp-minpaku-connector'),
            'loading' => __('読み込み中...', 'wp-minpaku-connector'),
            'breakdown_title' => __('内訳', 'wp-minpaku-connector'),
            'accommodation_fee' => __('宿泊料金', 'wp-minpaku-connector'),
            'cleaning_fee' => __('清掃料金', 'wp-minpaku-connector'),
            'total_amount' => __('合計金額', 'wp-minpaku-connector'),
            'final_notice' => __('※ 最終合計は予約時に確定します', 'wp-minpaku-connector'),
            'error_title' => __('エラー', 'wp-minpaku-connector')
        ];
        include plugin_dir_path(__FILE__) . '../../templates/connector/quote-panel.php';
        $quote_panel_html = ob_get_clean();

        return $calendar_html . $quote_panel_html;

        } catch (\Exception $e) {
            // Log the error for debugging
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[ConnectorCalendar] Fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            }

            return '<div class="mpc-error" style="background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 12px; border-radius: 6px; margin: 16px 0;">' .
                   '<strong>Calendar Error:</strong> ' . esc_html($e->getMessage()) .
                   '</div>';
        }
    }

    /**
     * Get property ID from shortcode attribute or auto-detect from current context
     */
    private static function get_property_id($provided_id) {
        // If property_id is provided in shortcode, use it
        if (!empty($provided_id)) {
            return intval($provided_id);
        }

        // Check if property_id is in URL parameters
        if (isset($_GET['property_id'])) {
            return intval($_GET['property_id']);
        }

        return 0; // Default fallback
    }

    /**
     * Generate calendar days for a specific month with portal parity
     */
    private static function generate_calendar_days($year, $month, $property_id, $show_prices = true) {
        $first_day = new \DateTime("{$year}-{$month}-01");
        $last_day = new \DateTime($first_day->format('Y-m-t'));

        $start_of_week = clone $first_day;
        $start_of_week->modify('last sunday');

        if ($start_of_week == $first_day) {
            $start_of_week->modify('-7 days');
        }

        $end_of_week = clone $last_day;
        $end_of_week->modify('next saturday');
        if ($end_of_week == $last_day) {
            $end_of_week->modify('+7 days');
        }

        // Get availability data from API
        $api = new \MinpakuConnector\Client\MPC_Client_Api();
        $availability_result = $api->get_availability($property_id, 2);

        if ($availability_result['success']) {
            $availability_data = isset($availability_result['data']) ? $availability_result['data'] : [];
        } else {
            $availability_data = [];
        }

        // Extract pricing data from availability result
        $pricing_data = [];
        if (isset($availability_result['pricing']) && is_array($availability_result['pricing'])) {
            foreach ($availability_result['pricing'] as $price_entry) {
                if (isset($price_entry['date']) && isset($price_entry['price'])) {
                    $pricing_data[$price_entry['date']] = $price_entry['price'];
                }
            }
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[Connector Calendar] Extracted pricing data: ' . count($pricing_data) . ' prices for property ' . $property_id);
            }
        } else {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[Connector Calendar] No pricing data found in availability result for property ' . $property_id);
            }
        }

        $output = '';
        $current_date = clone $start_of_week;

        while ($current_date <= $end_of_week) {
            $date_string = $current_date->format('Y-m-d');
            $is_current_month = ($current_date->format('Y-m') === $first_day->format('Y-m'));
            $is_past = ($current_date < new \DateTime('today'));

            // Get availability status for this date
            $availability_status = self::get_availability_status($date_string, $availability_data);

            // Get day classification for colors - Portal Parity
            $day_classification = self::getSimpleDayClasses($date_string, $availability_status);

            $is_available = ($availability_status === 'available');
            $is_disabled = $is_past || !$is_available;

            $cell_classes = $day_classification['css_classes'];
            if (!$is_current_month) {
                $cell_classes[] = 'mcs-day--empty';
            }
            if ($is_past) {
                $cell_classes[] = 'mcs-day--past';
            }

            $inline_day_style = sprintf(
                'min-height: 70px; padding: 8px 6px 6px 6px; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; cursor: pointer; background: %s; display: flex; flex-direction: column; align-items: flex-start; justify-content: space-between; overflow: hidden;',
                esc_attr($day_classification['background_color'])
            );

            $output .= sprintf(
                '<div class="%s" data-ymd="%s" data-property="%s" data-disabled="%d" style="%s">',
                esc_attr(implode(' ', $cell_classes)),
                esc_attr($date_string),
                esc_attr($property_id),
                $is_disabled ? 1 : 0,
                $inline_day_style
            );

            $output .= '<span class="mcs-day-number">' . $current_date->format('j') . '</span>';

            // Add price badge for available days, or 満室 badge for booked days
            if ($is_current_month && !$is_past) {
                if ($availability_status === 'available' && $show_prices) {
                    // Use real pricing data if available, otherwise fall back to simple pricing
                    $price = null;

                    // First try to get price from pricing data
                    if (isset($pricing_data[$date_string])) {
                        $price = $pricing_data[$date_string];
                    } else {
                        // Try to get price from availability data
                        foreach ($availability_data as $avail_entry) {
                            if (isset($avail_entry['date']) && $avail_entry['date'] === $date_string &&
                                isset($avail_entry['price']) && !empty($avail_entry['price'])) {
                                $price = $avail_entry['price'];
                                break;
                            }
                        }
                    }

                    // Fall back to default pricing if no real price found
                    if ($price === null || $price === '' || $price === 0) {
                        $day_of_week = $current_date->format('w');
                        $base_price = self::get_base_price_for_property($property_id);
                        if ($day_of_week == 6) { // Saturday
                            $price = $base_price + 2000;
                        } elseif ($day_of_week == 0) { // Sunday
                            $price = $base_price + 1000;
                        } else {
                            $price = $base_price;
                        }
                    }

                    $output .= '<span class="mcs-day-price" style="background: #1e293b !important; color: white !important; display: block !important;">¥' . number_format($price) . '</span>';
                } elseif ($availability_status === 'full' || $availability_status === 'booked') {
                    $output .= '<span class="mcs-day-full-badge">満室</span>';
                }
            }

            $output .= '</div>';
            $current_date->add(new \DateInterval('P1D'));
        }

        return $output;
    }

    /**
     * Get availability status for a specific date
     */
    private static function get_availability_status($date_string, $availability_data) {
        if (isset($availability_data['availability']) && is_array($availability_data['availability'])) {
            foreach ($availability_data['availability'] as $day_data) {
                if (isset($day_data['date']) && $day_data['date'] === $date_string) {
                    $available = isset($day_data['available']) ? $day_data['available'] : true;
                    $status = isset($day_data['status']) ? $day_data['status'] : 'available';

                    if (!$available) {
                        switch ($status) {
                            case 'booked':
                            case 'reserved':
                                return 'full';
                            case 'unavailable':
                            case 'blocked':
                                return 'unavailable';
                            default:
                                return 'full';
                        }
                    } else {
                        return 'available';
                    }
                }
            }
        }
        return 'available';
    }

    /**
     * Simple day classification for colors - Portal Parity (Next Day Logic)
     */
    private static function getSimpleDayClasses($date_string, $availability_status) {
        $checkin_date = new \DateTime($date_string);

        // 翌日（チェックアウト日）を計算 - ポータル側と同じロジック
        $checkout_date = clone $checkin_date;
        $checkout_date->add(new \DateInterval('P1D'));

        $checkout_day_of_week = $checkout_date->format('w'); // 0 = Sunday, 6 = Saturday
        $checkout_date_string = $checkout_date->format('Y-m-d');
        $is_checkout_holiday = self::isJapaneseHoliday($checkout_date_string);

        $css_classes = ['mcs-day'];
        $background_color = '#FFFFFF'; // Default white

        // Add availability class
        $css_classes[] = "mcs-day--{$availability_status}";

        // Add day type classes for color - 翌日ベースの判定
        if ($availability_status === 'available') {
            if ($checkout_day_of_week == 0 || $is_checkout_holiday) { // Next day is Sunday or Holiday
                $css_classes[] = 'mcs-day--sun';
                if ($is_checkout_holiday) {
                    $css_classes[] = 'mcs-day--holiday';
                }
                $background_color = '#fef2f2'; // Light red
            } elseif ($checkout_day_of_week == 6) { // Next day is Saturday
                $css_classes[] = 'mcs-day--sat';
                $background_color = '#eff6ff'; // Light blue
            } else { // Next day is weekday
                $css_classes[] = 'mcs-day--weekday';
                $background_color = '#FFFFFF'; // White
            }
        } elseif ($availability_status === 'full') {
            $css_classes[] = 'mcs-day--booked';
            $background_color = '#f3f4f6'; // Light gray
        }

        return [
            'css_classes' => $css_classes,
            'background_color' => $background_color
        ];
    }

    /**
     * Check if date is a Japanese holiday - Portal Parity
     */
    private static function isJapaneseHoliday($date_string) {
        $holidays = [
            '2024-01-01', '2024-01-08', '2024-02-11', '2024-02-12', '2024-02-23',
            '2024-03-20', '2024-04-29', '2024-05-03', '2024-05-04', '2024-05-05',
            '2024-07-15', '2024-08-11', '2024-08-12', '2024-09-16', '2024-09-22',
            '2024-09-23', '2024-10-14', '2024-11-03', '2024-11-04', '2024-11-23',
            '2025-01-01', '2025-01-13', '2025-02-11', '2025-02-23', '2025-02-24',
            '2025-03-20', '2025-04-29', '2025-05-03', '2025-05-04', '2025-05-05',
            '2025-07-21', '2025-08-11', '2025-09-15', '2025-09-22', '2025-09-23',
            '2025-10-13', '2025-11-03', '2025-11-23', '2025-11-24'
        ];
        return in_array($date_string, $holidays);
    }

    /**
     * Get base price for a property
     */
    private static function get_base_price_for_property($property_id) {
        return 15000; // Default base price
    }

    /**
     * Render properties list
     */
    private static function render_properties_list($atts) {
        $limit = max(1, min(50, intval($atts['limit'])));

        if (!class_exists('MinpakuConnector\Client\MPC_Client_Api')) {
            return '<div class="mpc-error" style="background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 12px; border-radius: 6px; margin: 16px 0;">' .
                   __('API client not available.', 'wp-minpaku-connector') .
                   '</div>';
        }

        $api = new \MinpakuConnector\Client\MPC_Client_Api();
        if (!$api->is_configured()) {
            return '<div class="mpc-error" style="background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 12px; border-radius: 6px; margin: 16px 0;">' .
                   __('Portal connection not configured. Please check the connector settings.', 'wp-minpaku-connector') .
                   '</div>';
        }

        $properties_response = $api->get_properties();
        if (!$properties_response['success']) {
            return '<div class="mpc-error" style="background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 12px; border-radius: 6px; margin: 16px 0;">' .
                   __('Failed to load properties: ', 'wp-minpaku-connector') . esc_html($properties_response['message'] ?? 'Unknown error') .
                   '</div>';
        }

        $properties = isset($properties_response['data']) ? $properties_response['data'] : [];

        if (empty($properties)) {
            return '<div class="mpc-notice" style="background: #fef9e7; border: 1px solid #f59e0b; color: #92400e; padding: 12px; border-radius: 6px; margin: 16px 0;">' .
                   __('No properties found.', 'wp-minpaku-connector') .
                   '</div>';
        }

        // Inline CSS for immediate layout
        $inline_styles = '
        <style>
        .mpc-properties-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)) !important;
            gap: 20px !important;
            margin: 20px 0 !important;
        }
        .mpc-property-card {
            border: 1px solid #ddd !important;
            border-radius: 8px !important;
            padding: 20px !important;
            background: white !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
        }
        .mpc-property-title {
            font-size: 18px !important;
            font-weight: bold !important;
            margin-bottom: 10px !important;
            color: #333 !important;
        }
        .mpc-property-meta {
            font-size: 14px !important;
            color: #666 !important;
            margin-bottom: 10px !important;
        }
        .mpc-property-summary {
            font-size: 14px !important;
            color: #555 !important;
            margin-bottom: 15px !important;
            line-height: 1.4 !important;
        }
        .mpc-property-amenities {
            margin-bottom: 15px !important;
        }
        .mpc-amenity-tag {
            display: inline-block !important;
            background: #f0f0f0 !important;
            padding: 2px 8px !important;
            border-radius: 4px !important;
            font-size: 11px !important;
            margin: 2px 4px 2px 0 !important;
        }
        .mpc-property-actions {
            text-align: center !important;
        }
        .mpc-property-external-btn {
            display: inline-block !important;
            padding: 8px 16px !important;
            background: #007cba !important;
            color: white !important;
            text-decoration: none !important;
            border-radius: 4px !important;
            font-size: 14px !important;
        }
        .mpc-property-external-btn:hover {
            background: #005a87 !important;
        }
        </style>';

        ob_start();
        echo $inline_styles;
        ?>
        <div class="mpc-properties-grid">
            <?php foreach (array_slice($properties, 0, $limit) as $property) { ?>
                <?php
                $property_id = isset($property['id']) ? $property['id'] : 0;
                $property_title = isset($property['title']) ? $property['title'] : (isset($property['name']) ? $property['name'] : __('Untitled Property', 'wp-minpaku-connector'));
                $property_summary = isset($property['excerpt']) ? $property['excerpt'] : (isset($property['description']) ? $property['description'] : (isset($property['content']) ? $property['content'] : ''));

                // Debug: Log property data structure for troubleshooting
                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[Connector Properties List] Property #' . ($property['id'] ?? 'unknown') . ' data keys: ' . implode(', ', array_keys($property)));
                    if (isset($property['meta']) && is_array($property['meta'])) {
                        error_log('[Connector Properties List] Property #' . ($property['id'] ?? 'unknown') . ' meta keys: ' . implode(', ', array_keys($property['meta'])));
                    }
                    if (isset($property['acf']) && is_array($property['acf'])) {
                        error_log('[Connector Properties List] Property #' . ($property['id'] ?? 'unknown') . ' acf keys: ' . implode(', ', array_keys($property['acf'])));
                    }
                    // Log any field containing 'url', 'link', 'bathroom', 'bath'
                    $debug_fields = [];
                    foreach ($property as $key => $value) {
                        if (stripos($key, 'url') !== false || stripos($key, 'link') !== false ||
                            stripos($key, 'bathroom') !== false || stripos($key, 'bath') !== false) {
                            $debug_fields[$key] = $value;
                        }
                    }
                    if (!empty($debug_fields)) {
                        error_log('[Connector Properties List] Property #' . ($property['id'] ?? 'unknown') . ' relevant fields: ' . print_r($debug_fields, true));
                    }
                }

                // Enhanced external URL detection with actual field names from logs
                $external_url = '';
                $external_button_text = '';

                // Check for _mcs_external_detail_url in meta (from debug logs)
                if (isset($property['meta']['_mcs_external_detail_url']) && !empty($property['meta']['_mcs_external_detail_url'])) {
                    $external_url = $property['meta']['_mcs_external_detail_url'];
                }

                // Check for button text
                if (isset($property['meta']['_mcs_external_button_text']) && !empty($property['meta']['_mcs_external_button_text'])) {
                    $external_button_text = $property['meta']['_mcs_external_button_text'];
                }

                // Fallback URL keys if the primary ones don't exist
                if (empty($external_url)) {
                    $url_keys = [
                        'external_detail_url', 'external_url', 'detail_url', 'booking_url', 'property_url', 'link_url', 'website_url', 'site_url',
                        'meta.external_detail_url', 'meta.external_url', 'meta.detail_url', 'meta.booking_url', 'meta.property_url', 'meta.link_url', 'meta.website_url', 'meta.site_url',
                        'acf.external_detail_url', 'acf.external_url', 'acf.detail_url', 'acf.booking_url', 'acf.property_url', 'acf.link_url', 'acf.website_url', 'acf.site_url'
                    ];

                    foreach ($url_keys as $key) {
                        if (strpos($key, '.') !== false) {
                            list($parent, $child) = explode('.', $key, 2);
                            if (isset($property[$parent][$child]) && !empty($property[$parent][$child])) {
                                $external_url = $property[$parent][$child];
                                break;
                            }
                        } else {
                            if (isset($property[$key]) && !empty($property[$key])) {
                                $external_url = $property[$key];
                                break;
                            }
                        }
                    }
                }

                // Default button text if not specified
                if (empty($external_button_text)) {
                    $external_button_text = '外部サイトで見る';
                }

                // Enhanced property data extraction with more fallbacks
                $amenities = $property['amenities'] ?? $property['meta']['amenities'] ?? $property['acf']['amenities'] ?? array();
                $max_guests = $property['max_guests'] ?? $property['meta']['max_guests'] ?? $property['acf']['max_guests'] ?? $property['capacity'] ?? $property['guest_capacity'] ?? $property['定員'] ?? '';
                $bedrooms = $property['bedrooms'] ?? $property['meta']['bedrooms'] ?? $property['acf']['bedrooms'] ?? $property['bedroom_count'] ?? $property['寝室数'] ?? '';
                $bathrooms = $property['bathrooms'] ?? $property['meta']['bathrooms'] ?? $property['acf']['bathrooms'] ?? $property['bathroom_count'] ?? $property['バスルーム数'] ?? $property['bath_count'] ?? $property['baths'] ?? '';
                $living_rooms = $property['living_rooms'] ?? $property['meta']['living_rooms'] ?? $property['acf']['living_rooms'] ?? $property['living_room_count'] ?? $property['リビング数'] ?? '';
                $floor_size = $property['floor_size'] ?? $property['meta']['floor_size'] ?? $property['acf']['floor_size'] ?? $property['size'] ?? $property['area'] ?? $property['広さ'] ?? '';
                ?>
                <div class="mpc-property-card">
                    <h3 class="mpc-property-title"><?php echo esc_html($property_title); ?></h3>

                    <?php if ($max_guests || $bedrooms || $bathrooms || $living_rooms || $floor_size) { ?>
                        <div class="mpc-property-meta">
                            <?php
                            $meta_items = [];
                            if ($max_guests) {
                                $meta_items[] = sprintf(__('最大 %d名', 'wp-minpaku-connector'), intval($max_guests));
                            }
                            if ($bedrooms) {
                                $meta_items[] = sprintf(__('%d寝室', 'wp-minpaku-connector'), intval($bedrooms));
                            }
                            if ($bathrooms) {
                                $meta_items[] = sprintf(__('%dバスルーム', 'wp-minpaku-connector'), intval($bathrooms));
                            }
                            if ($living_rooms) {
                                $meta_items[] = sprintf(__('%dリビング', 'wp-minpaku-connector'), intval($living_rooms));
                            }
                            if ($floor_size) {
                                $meta_items[] = sprintf(__('%s㎡', 'wp-minpaku-connector'), esc_html($floor_size));
                            }
                            echo implode(' • ', $meta_items);
                            ?>
                        </div>
                    <?php } ?>

                    <?php if ($property_summary) { ?>
                        <div class="mpc-property-summary">
                            <?php echo esc_html(wp_trim_words($property_summary, 20)); ?>
                        </div>
                    <?php } ?>

                    <?php if (!empty($amenities) && is_array($amenities)) { ?>
                        <div class="mpc-property-amenities">
                            <?php foreach (array_slice($amenities, 0, 5) as $amenity) { ?>
                                <span class="mpc-amenity-tag"><?php echo esc_html($amenity); ?></span>
                            <?php } ?>
                            <?php if (count($amenities) > 5) { ?>
                                <span class="mpc-amenity-tag">+<?php echo count($amenities) - 5; ?></span>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <div class="mpc-property-actions">
                        <?php if ($external_url) { ?>
                            <a href="<?php echo esc_url($external_url); ?>"
                               class="mpc-property-external-btn"
                               target="_blank"
                               rel="noopener">
                                <?php echo esc_html($external_button_text); ?>
                            </a>
                        <?php } else { ?>
                            <span class="mpc-property-external-btn" style="background: #6c757d !important; cursor: not-allowed !important;">
                                <?php _e('外部URLが未設定', 'wp-minpaku-connector'); ?>
                            </span>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue modern assets
     */
    private static function enqueue_modern_assets() {
        // Use absolute plugin URL for reliability
        $plugin_url = untrailingslashit(plugin_dir_url(__FILE__ . '/../..'));
        $plugin_path = untrailingslashit(plugin_dir_path(__FILE__ . '/../..'));

        // Calendar CSS
        $calendar_css_file = $plugin_url . '/assets/css/wpmc-calendar.css';
        $calendar_css_path = $plugin_path . '/assets/css/wpmc-calendar.css';

        if (file_exists($calendar_css_path)) {
            wp_enqueue_style(
                'wpmc-calendar',
                $calendar_css_file,
                [],
                filemtime($calendar_css_path)
            );
        }

        // Quote panel CSS
        $quote_css_file = $plugin_url . '/assets/css/wpmc-quote-panel.css';
        $quote_css_path = $plugin_path . '/assets/css/wpmc-quote-panel.css';

        if (file_exists($quote_css_path)) {
            wp_enqueue_style(
                'wpmc-quote-panel',
                $quote_css_file,
                [],
                filemtime($quote_css_path)
            );
        }

        // JavaScript
        $js_file = $plugin_url . '/assets/js/connector-calendar-interactions.js';
        $js_path = $plugin_path . '/assets/js/connector-calendar-interactions.js';

        if (file_exists($js_path)) {
            wp_enqueue_script(
                'wpmc-calendar-interactions',
                $js_file,
                ['jquery'],
                filemtime($js_path),
                true
            );

            // Pass portal URL and settings to JavaScript
            $settings = \WP_Minpaku_Connector::get_settings();
            $portal_url = '';
            if (!empty($settings['portal_url'])) {
                if (class_exists('MinpakuConnector\Admin\MPC_Admin_Settings')) {
                    $portal_url = \MinpakuConnector\Admin\MPC_Admin_Settings::normalize_portal_url($settings['portal_url']);
                    if ($portal_url === false) {
                        $portal_url = $settings['portal_url'];
                    }
                } else {
                    $portal_url = $settings['portal_url'];
                }
            }

            wp_localize_script('wpmc-calendar-interactions', 'wpmc_calendar_settings', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'portal_url' => $portal_url,
                'quote_api_url' => rest_url('minpaku-connector/v1/quote'),
                'nonce' => wp_create_nonce('mpc_calendar_nonce')
            ]);
        }
    }

    /**
     * Enqueue legacy assets (fallback)
     */
    private static function enqueue_legacy_assets() {
        // Legacy calendar CSS (simplified)
        $plugin_url = untrailingslashit(plugin_dir_url(__FILE__ . '/../..'));
        $plugin_path = untrailingslashit(plugin_dir_path(__FILE__ . '/../..'));

        $legacy_css_file = $plugin_url . '/assets/css/calendar.css';
        $legacy_css_path = $plugin_path . '/assets/css/calendar.css';

        if (file_exists($legacy_css_path)) {
            wp_enqueue_style(
                'mpc-legacy-calendar',
                $legacy_css_file,
                [],
                filemtime($legacy_css_path)
            );
        }
    }
}
