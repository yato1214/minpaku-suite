<?php
/**
 * Calendar Shortcode with Price Integration
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class MPC_Shortcodes_Calendar {

    public static function init() {
        add_shortcode('minpaku_calendar', array(__CLASS__, 'render_calendar'));
    }

    /**
     * Render calendar shortcode with live data from portal API
     */
    public static function render_calendar($atts) {
        $atts = shortcode_atts(array(
            'property_id' => '',
            'months' => 2,
            'show_prices' => 'true',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'JPY'
        ), $atts, 'minpaku_calendar');

        if (empty($atts['property_id'])) {
            return '<p class="mpc-error">' . __('Property ID is required for calendar display.', 'wp-minpaku-connector') . '</p>';
        }

        $property_id = sanitize_text_field($atts['property_id']);
        $months = max(1, min(12, intval($atts['months'])));
        $show_prices = ($atts['show_prices'] === 'true');
        $adults = max(1, intval($atts['adults']));
        $children = max(0, intval($atts['children']));
        $infants = max(0, intval($atts['infants']));
        $currency = sanitize_text_field($atts['currency']);

        // Check API configuration
        if (!class_exists('MinpakuConnector\Client\MPC_Client_Api')) {
            return '<p class="mpc-error">' . __('API client not available.', 'wp-minpaku-connector') . '</p>';
        }

        $api = new \MinpakuConnector\Client\MPC_Client_Api();
        if (!$api->is_configured()) {
            return '<p class="mpc-error">' . __('Portal connection not configured. Please check the connector settings.', 'wp-minpaku-connector') . '</p>';
        }

        // Get availability data from portal API with pricing
        $availability_result = $api->get_availability($property_id, $months, null, true);

        // Enhanced debugging for availability data
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Calendar API call result: ' . print_r($availability_result, true));
        }

        if (!$availability_result['success']) {
            $error_message = $availability_result['message'] ?? __('Unknown error', 'wp-minpaku-connector');

            // Log detailed error for debugging
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-connector] Calendar API error: ' . print_r($availability_result, true));
            }

            return '<p class="mpc-error">' . sprintf(__('空室状況を読み込めません: %s', 'wp-minpaku-connector'), esc_html($error_message)) . '</p>';
        }

        $availability_data = $availability_result['data'] ?? array();

        // Debug pricing data specifically
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Calendar pricing data received: ' . print_r($availability_data['pricing'] ?? 'none', true));
            error_log('[minpaku-connector] Calendar availability data structure: ' . print_r(array_keys($availability_data), true));
        }

        // Ensure property_id is available for price lookup
        if (!isset($availability_data['property_id'])) {
            $availability_data['property_id'] = $property_id;
        }

        // Generate calendar with portal data
        $calendar_id = 'mpc-calendar-' . uniqid();

        ob_start();
        ?>

        <div id="<?php echo esc_attr($calendar_id); ?>" class="mpc-calendar-container"
             data-property-id="<?php echo esc_attr($property_id); ?>"
             data-show-prices="<?php echo $show_prices ? '1' : '0'; ?>"
             data-adults="<?php echo esc_attr($adults); ?>"
             data-children="<?php echo esc_attr($children); ?>"
             data-infants="<?php echo esc_attr($infants); ?>"
             data-currency="<?php echo esc_attr($currency); ?>"
             data-auto-init="true">

            <?php for ($i = 0; $i < $months; $i++): ?>
                <?php
                $month_date = new \DateTime();
                $month_date->add(new \DateInterval('P' . $i . 'M'));
                $year = $month_date->format('Y');
                $month = $month_date->format('n');
                ?>

                <div class="mpc-calendar-month" data-year="<?php echo esc_attr($year); ?>" data-month="<?php echo esc_attr($month); ?>">
                    <h3 class="mpc-calendar-month-title">
                        <?php echo esc_html($month_date->format('F Y')); ?>
                    </h3>

                    <div class="mpc-calendar-grid">
                        <div class="mpc-calendar-header">
                            <div class="mpc-calendar-day-header"><?php _e('Sun', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('Mon', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('Tue', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('Wed', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('Thu', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('Fri', 'wp-minpaku-connector'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('Sat', 'wp-minpaku-connector'); ?></div>
                        </div>

                        <?php echo self::generate_calendar_days($year, $month, $property_id, $availability_data, $show_prices); ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Generate calendar days for a specific month with real availability data
     */
    private static function generate_calendar_days($year, $month, $property_id, $availability_data, $show_prices) {

        $first_day = new \DateTime("$year-$month-01");
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

        $current_date = clone $start_of_week;
        $output = '';

        // Get blackout ranges for checking
        $blackout_ranges = array(); // Remove blackout functionality

        while ($current_date <= $end_of_week) {
            $week_start = clone $current_date;
            $output .= '<div class="mpc-calendar-week">';

            for ($day = 0; $day < 7; $day++) {
                $is_current_month = ($current_date->format('n') == $month);
                $is_past = ($current_date < new \DateTime('today'));
                $date_string = $current_date->format('Y-m-d');

                // Check if date is in blackout range
                $is_blackout = self::is_date_in_blackout_range($date_string, $blackout_ranges);

                // Get availability status from real data
                if ($is_blackout) {
                    $availability_status = 'blackout';
                } else {
                    $availability_status = self::get_availability_status($date_string, $availability_data);
                }

                // Simple day classification for colors
                $day_classification = self::getSimpleDayClasses($date_string, $availability_status);

                $is_available = ($availability_status === 'available');
                $is_disabled = $is_past || !$is_available || $is_blackout;

                $cell_classes = $day_classification['css_classes'];
                if (!$is_current_month) {
                    $cell_classes[] = 'mcs-day--empty';
                }

                $output .= sprintf(
                    '<div class="%s" data-ymd="%s" data-property="%s" data-disabled="%d" style="background-color: %s;">',
                    esc_attr(implode(' ', $cell_classes)),
                    esc_attr($date_string),
                    esc_attr($property_id),
                    $is_disabled ? 1 : 0,
                    esc_attr($day_classification['background_color'])
                );

                $output .= '<span class="mcs-day-number">' . $current_date->format('j') . '</span>';

                // Add price badge for available days, or 満室 badge for booked days
                if ($is_current_month && !$is_past) {
                    if ($availability_status === 'available' && $show_prices && !$is_blackout) {
                        $price_text = self::get_price_for_day($date_string, $availability_data);
                        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                            error_log('[minpaku-connector] Calendar price display for ' . $date_string . ': ' . $price_text);
                        }
                        if ($price_text !== '—' && !empty($price_text)) {
                            $output .= '<span class="mcs-day-price">' . esc_html($price_text) . '</span>';
                        }
                    } elseif ($availability_status === 'full') {
                        $output .= '<span class="mcs-day-full-badge">満室</span>';
                    }
                }

                $output .= '</div>';
                $current_date->add(new \DateInterval('P1D'));
            }

            $output .= '</div>';
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
                    // Check available flag first
                    $available = $day_data['available'] ?? true;
                    $status = $day_data['status'] ?? 'available';

                    // Map API statuses to standardized names
                    if (!$available) {
                        switch ($status) {
                            case 'booked':
                            case 'FULL':
                                return 'full';
                            case 'partial':
                            case 'PARTIAL':
                                return 'pending';
                            default:
                                return 'full';
                        }
                    } else {
                        return 'available';
                    }
                }
            }
        }
        return 'available'; // Default to available if no data
    }

    /**
     * Check if a date is in any blackout range
     */
    private static function is_date_in_blackout_range($date, $blackout_ranges) {
        if (empty($blackout_ranges) || !is_array($blackout_ranges)) {
            return false;
        }

        foreach ($blackout_ranges as $range) {
            if (isset($range['date_from']) && isset($range['date_to'])) {
                if ($date >= $range['date_from'] && $date <= $range['date_to']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Simple day classification for colors (matching portal side)
     */
    private static function getSimpleDayClasses($date_string, $availability_status) {
        $date = new \DateTime($date_string);
        $day_of_week = $date->format('w'); // 0 = Sunday, 6 = Saturday

        $css_classes = ['mcs-day'];
        $background_color = '#FFFFFF'; // Default white

        // Add availability class
        $css_classes[] = "mcs-day--{$availability_status}";

        // Add day type classes for color (same logic as portal)
        if ($availability_status === 'available') {
            if ($day_of_week == 0 || self::isJapaneseHoliday($date_string)) { // Sunday or Holiday
                $css_classes[] = 'mcs-day--sun';
                if (self::isJapaneseHoliday($date_string)) {
                    $css_classes[] = 'mcs-day--holiday';
                }
                $background_color = '#FFE7EC'; // Pink
            } elseif ($day_of_week == 6) { // Saturday
                $css_classes[] = 'mcs-day--sat';
                $background_color = '#E7F2FF'; // Light blue
            } else { // Weekday
                $css_classes[] = 'mcs-day--weekday';
                $background_color = '#F0F9F0'; // Light green
            }
        } elseif ($availability_status === 'full') {
            // Booked dates keep white background (満室 badge provides visual indication)
            $css_classes[] = 'mcs-day--booked';
            $background_color = '#FFFFFF'; // White background
        }

        return array(
            'css_classes' => $css_classes,
            'background_color' => $background_color
        );
    }

    /**
     * Check if date is a Japanese holiday (matching portal side)
     */
    private static function isJapaneseHoliday($date_string) {
        // Simplified holiday check - matching portal side logic
        $holidays = [
            // 2025 holidays
            '2025-01-01', // New Year
            '2025-01-13', // Coming of Age Day
            '2025-02-11', // Foundation Day
            '2025-02-23', // Emperor's Birthday
            '2025-03-20', // Spring Equinox
            '2025-04-29', // Showa Day
            '2025-05-03', // Constitution Day
            '2025-05-04', // Greenery Day
            '2025-05-05', // Children's Day
            '2025-07-21', // Marine Day
            '2025-08-11', // Mountain Day
            '2025-09-15', // Respect for the Aged Day
            '2025-09-23', // Autumn Equinox
            '2025-10-13', // Sports Day
            '2025-11-03', // Culture Day
            '2025-11-23', // Labor Thanksgiving Day
            // 2024 holidays (for historical data)
            '2024-01-01', '2024-01-08', '2024-02-11', '2024-02-23',
            '2024-03-20', '2024-04-29', '2024-05-03', '2024-05-04',
            '2024-05-05', '2024-07-15', '2024-08-11', '2024-09-16',
            '2024-09-22', '2024-10-14', '2024-11-03', '2024-11-23',
            // 2026 holidays (for future bookings)
            '2026-01-01', '2026-01-12', '2026-02-11', '2026-02-23',
            '2026-03-20', '2026-04-29', '2026-05-03', '2026-05-04',
            '2026-05-05', '2026-07-20', '2026-08-11', '2026-09-21',
            '2026-09-22', '2026-10-12', '2026-11-03', '2026-11-23'
        ];

        return in_array($date_string, $holidays);
    }

    /**
     * Get price for a specific date from portal API data (trust portal calculations)
     */
    private static function get_price_for_day($date_string, $availability_data) {
        // Enhanced debug logging
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Getting price for date: ' . $date_string);
            error_log('[minpaku-connector] Available pricing data: ' . print_r($availability_data['pricing'] ?? 'none', true));
        }

        // First, check the pricing array for date-specific pricing (TRUST PORTAL CALCULATIONS)
        if (isset($availability_data['pricing']) && is_array($availability_data['pricing'])) {
            foreach ($availability_data['pricing'] as $pricing_data) {
                if (isset($pricing_data['date']) && $pricing_data['date'] === $date_string) {
                    if (isset($pricing_data['price']) && is_numeric($pricing_data['price'])) {
                        $price = floatval($pricing_data['price']);

                        // Trust portal calculations - don't filter out any prices
                        if ($price > 0) {
                            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                                error_log('[minpaku-connector] Using pricing array price for ' . $date_string . ': ¥' . $price);
                            }
                            return '¥' . number_format($price);
                        }
                    }
                }
            }
        }

        // Then check availability array for pricing (but trust portal values)
        if (isset($availability_data['availability']) && is_array($availability_data['availability'])) {
            foreach ($availability_data['availability'] as $day_data) {
                if (isset($day_data['date']) && $day_data['date'] === $date_string) {
                    // Check price field
                    if (isset($day_data['price']) && is_numeric($day_data['price'])) {
                        $price = floatval($day_data['price']);
                        if ($price > 0) {
                            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                                error_log('[minpaku-connector] Using availability array price for ' . $date_string . ': ¥' . $price);
                            }
                            return '¥' . number_format($price);
                        }
                    }
                    // Check min_price as fallback
                    if (isset($day_data['min_price']) && is_numeric($day_data['min_price'])) {
                        $price = floatval($day_data['min_price']);
                        if ($price > 0) {
                            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                                error_log('[minpaku-connector] Using availability array min_price for ' . $date_string . ': ¥' . $price);
                            }
                            return '¥' . number_format($price);
                        }
                    }
                }
            }
        }

        // If no date-specific pricing found, try to get general property pricing from pricing array
        if (isset($availability_data['property_id'])) {
            $real_price = self::get_real_property_price($availability_data['property_id'], $availability_data);
            if ($real_price > 0) {
                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[minpaku-connector] Using fallback price for ' . $date_string . ': ¥' . $real_price);
                }
                return '¥' . number_format($real_price);
            }
        }

        // No price available
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] No price found for ' . $date_string);
        }
        return '—';
    }

    /**
     * Get real property price from availability data pricing array
     */
    private static function get_real_property_price($property_id, $availability_data = null) {
        // Try to get price from pricing array in availability data
        if ($availability_data && isset($availability_data['pricing']) && is_array($availability_data['pricing'])) {
            foreach ($availability_data['pricing'] as $price_data) {
                if (isset($price_data['price']) && is_numeric($price_data['price']) && $price_data['price'] > 100) {
                    return floatval($price_data['price']);
                }
            }
        }

        // Fallback to default pricing
        return 15000; // Default ¥15,000
    }

}