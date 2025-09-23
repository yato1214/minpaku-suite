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

        // Get availability data from portal API
        $availability_result = $api->get_availability($property_id, $months);
        if (!$availability_result['success']) {
            $error_message = $availability_result['message'] ?? __('Unknown error', 'wp-minpaku-connector');

            // Log detailed error for debugging
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-connector] Calendar API error: ' . print_r($availability_result, true));
            }

            return '<p class="mpc-error">' . sprintf(__('空室状況を読み込めません: %s', 'wp-minpaku-connector'), esc_html($error_message)) . '</p>';
        }

        $availability_data = $availability_result['data'] ?? array();
        $calendar_id = 'mpc-calendar-' . uniqid();

        ob_start();
        ?>
        <!-- Calendar Legend -->
        <div class="mpc-calendar-legend">
            <h4><?php _e('空室状況の見方', 'wp-minpaku-connector'); ?></h4>
            <div class="mpc-legend-items">
                <div class="mpc-legend-item">
                    <span class="mpc-legend-color mpc-legend-color--vacant"></span>
                    <span class="mpc-legend-label"><?php _e('空き', 'wp-minpaku-connector'); ?></span>
                </div>
                <div class="mpc-legend-item">
                    <span class="mpc-legend-color mpc-legend-color--partial"></span>
                    <span class="mpc-legend-label"><?php _e('一部予約あり', 'wp-minpaku-connector'); ?></span>
                </div>
                <div class="mpc-legend-item">
                    <span class="mpc-legend-color mpc-legend-color--full"></span>
                    <span class="mpc-legend-label"><?php _e('満室', 'wp-minpaku-connector'); ?></span>
                </div>
            </div>
        </div>

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

        while ($current_date <= $end_of_week) {
            $week_start = clone $current_date;
            $output .= '<div class="mpc-calendar-week">';

            for ($day = 0; $day < 7; $day++) {
                $is_current_month = ($current_date->format('n') == $month);
                $is_past = ($current_date < new \DateTime('today'));
                $date_string = $current_date->format('Y-m-d');

                // Get availability status from real data
                $availability_status = self::get_availability_status($date_string, $availability_data);
                $is_available = ($availability_status === 'vacant');
                $is_disabled = $is_past || !$is_available;

                $cell_classes = array('mcs-day');
                if (!$is_current_month) {
                    $cell_classes[] = 'mcs-day--empty';
                } else {
                    $cell_classes[] = 'mcs-day--' . $availability_status;
                    if ($is_past) {
                        $cell_classes[] = 'mcs-day--past';
                    }
                    if ($is_disabled) {
                        $cell_classes[] = 'mcs-day--disabled';
                    }
                }

                $output .= sprintf(
                    '<div class="%s" data-ymd="%s" data-property="%s" data-disabled="%d">',
                    esc_attr(implode(' ', $cell_classes)),
                    esc_attr($date_string),
                    esc_attr($property_id),
                    $is_disabled ? 1 : 0
                );

                $output .= '<span class="mcs-day-number">' . $current_date->format('j') . '</span>';

                // Add price badge for available days only when vacant and not past
                if ($show_prices && $is_current_month && $availability_status === 'vacant' && !$is_past) {
                    $price_text = self::get_price_for_day($date_string, $availability_data);
                    $output .= '<span class="mcs-day-price">' . esc_html($price_text) . '</span>';
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

                    // Map API statuses to CSS class names
                    if (!$available) {
                        switch ($status) {
                            case 'booked':
                            case 'FULL':
                                return 'full';
                            case 'partial':
                            case 'PARTIAL':
                                return 'partial';
                            default:
                                return 'full';
                        }
                    } else {
                        return 'vacant';
                    }
                }
            }
        }
        return 'vacant'; // Default to available if no data
    }

    /**
     * Get price for a specific date
     */
    private static function get_price_for_day($date_string, $availability_data) {
        // Debug logging for price data structure
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Price lookup for date: ' . $date_string);
            error_log('[minpaku-connector] Availability data structure: ' . print_r($availability_data, true));
        }

        // First check availability array for price data
        if (isset($availability_data['availability']) && is_array($availability_data['availability'])) {
            foreach ($availability_data['availability'] as $day_data) {
                if (isset($day_data['date']) && $day_data['date'] === $date_string) {
                    // Check multiple possible price fields
                    $price = 0;

                    // Try different price field names
                    if (isset($day_data['price']) && is_numeric($day_data['price'])) {
                        $price = floatval($day_data['price']);
                    } elseif (isset($day_data['rate']) && is_numeric($day_data['rate'])) {
                        $price = floatval($day_data['rate']);
                    } elseif (isset($day_data['base_price']) && is_numeric($day_data['base_price'])) {
                        $price = floatval($day_data['base_price']);
                    } elseif (isset($day_data['total_price']) && is_numeric($day_data['total_price'])) {
                        $price = floatval($day_data['total_price']);
                    } elseif (isset($day_data['nightly_rate']) && is_numeric($day_data['nightly_rate'])) {
                        $price = floatval($day_data['nightly_rate']);
                    }

                    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('[minpaku-connector] Found price for ' . $date_string . ': ' . $price);
                        error_log('[minpaku-connector] Day data: ' . print_r($day_data, true));
                    }

                    if ($price > 0) {
                        return '¥' . number_format($price);
                    }
                }
            }
        }

        // Fallback to pricing array if available
        if (isset($availability_data['pricing']) && is_array($availability_data['pricing'])) {
            foreach ($availability_data['pricing'] as $day_data) {
                if (isset($day_data['date']) && $day_data['date'] === $date_string) {
                    $price = 0;

                    if (isset($day_data['price']) && is_numeric($day_data['price'])) {
                        $price = floatval($day_data['price']);
                    } elseif (isset($day_data['rate']) && is_numeric($day_data['rate'])) {
                        $price = floatval($day_data['rate']);
                    } elseif (isset($day_data['base_price']) && is_numeric($day_data['base_price'])) {
                        $price = floatval($day_data['base_price']);
                    }

                    if ($price > 0) {
                        return '¥' . number_format($price);
                    }
                }
            }
        }

        // Check if there's a rates array with date-indexed prices
        if (isset($availability_data['rates']) && is_array($availability_data['rates'])) {
            if (isset($availability_data['rates'][$date_string]) && is_numeric($availability_data['rates'][$date_string])) {
                $price = floatval($availability_data['rates'][$date_string]);
                if ($price > 0) {
                    return '¥' . number_format($price);
                }
            }
        }

        // Check for property base price as ultimate fallback
        if (isset($availability_data['property']['base_price']) && is_numeric($availability_data['property']['base_price'])) {
            $base_price = floatval($availability_data['property']['base_price']);
            if ($base_price > 0) {
                return '¥' . number_format($base_price);
            }
        }

        return __('—', 'wp-minpaku-connector');
    }
}