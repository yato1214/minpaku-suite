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
     * Render calendar shortcode with price badges
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
            return '<p>' . __('Property ID is required for calendar display.', 'wp-minpaku-connector') . '</p>';
        }

        $property_id = sanitize_text_field($atts['property_id']);
        $months = max(1, min(12, intval($atts['months'])));
        $show_prices = ($atts['show_prices'] === 'true');
        $adults = max(1, intval($atts['adults']));
        $children = max(0, intval($atts['children']));
        $infants = max(0, intval($atts['infants']));
        $currency = sanitize_text_field($atts['currency']);

        $calendar_id = 'mpc-calendar-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($calendar_id); ?>" class="mpc-calendar-container"
             data-property-id="<?php echo esc_attr($property_id); ?>"
             data-show-prices="<?php echo $show_prices ? '1' : '0'; ?>"
             data-adults="<?php echo esc_attr($adults); ?>"
             data-children="<?php echo esc_attr($children); ?>"
             data-infants="<?php echo esc_attr($infants); ?>"
             data-currency="<?php echo esc_attr($currency); ?>">

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

                        <?php echo self::generate_calendar_days($year, $month, $property_id, $show_prices); ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Initialize price manager for this calendar
            if (typeof MPCPriceManager !== 'undefined') {
                const priceManager = new MPCPriceManager();
                priceManager.initCalendar('#<?php echo esc_js($calendar_id); ?>');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate calendar days for a specific month
     */
    private static function generate_calendar_days($year, $month, $property_id, $show_prices) {
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

                $cell_classes = array('mpc-calendar-day', 'calendar-cell');
                if (!$is_current_month) {
                    $cell_classes[] = 'other-month';
                }
                if ($is_past) {
                    $cell_classes[] = 'past-date';
                } else {
                    // Add availability status classes - will be updated via JavaScript
                    $cell_classes[] = 'availability-unknown';
                }

                $output .= sprintf(
                    '<div class="%s" data-date="%s" data-property-id="%s">',
                    esc_attr(implode(' ', $cell_classes)),
                    esc_attr($date_string),
                    esc_attr($property_id)
                );

                $output .= '<div class="mpc-calendar-day-content">';
                $output .= '<span class="mpc-calendar-day-number">' . $current_date->format('j') . '</span>';

                // Add availability indicator
                if (!$is_past && $is_current_month) {
                    $output .= '<div class="mpc-availability-indicator" data-date="' . esc_attr($date_string) . '"></div>';
                }

                // Add price badge placeholder if enabled and not past date
                if ($show_prices && !$is_past && $is_current_month) {
                    $output .= '<div class="mpc-price-badge loading" data-date="' . esc_attr($date_string) . '">' . __('...', 'wp-minpaku-connector') . '</div>';
                }
                $output .= '</div>';

                $output .= '</div>';
                $current_date->add(new \DateInterval('P1D'));
            }

            $output .= '</div>';
        }

        return $output;
    }
}