<?php
/**
 * Connector Calendar Template
 * Displays availability calendar for connector properties (mirrors portal template)
 *
 * @package WP_Minpaku_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

// Default values
$property_id = $property_id ?? 0;
$months = $months ?? 2;
$show_prices = $show_prices ?? true;
$interactions = $interactions ?? 'modern';

// Handle navigation parameters
$current_offset = isset($_GET['calendar_offset']) ? intval($_GET['calendar_offset']) : 0;
$base_date = new DateTime();
$base_date->add(new DateInterval('P' . $current_offset . 'M'));

// Unique calendar ID
$calendar_id = 'wpmc-cal-' . $property_id . '-' . uniqid();

/**
 * Safe helper function to generate calendar days with full functionality
 */
if (!function_exists('wpmc_safe_generate_calendar_days')) {
function wpmc_safe_generate_calendar_days($year, $month, $property_id, $show_prices) {
    $first_day = new DateTime("$year-$month-01");
    $last_day = new DateTime($first_day->format('Y-m-t'));
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
        $output .= '<div class="wpmc-calendar-week">';

        for ($day = 0; $day < 7; $day++) {
            $is_current_month = ($current_date->format('n') == $month);
            $is_past = ($current_date < new DateTime('today'));
            $date_string = $current_date->format('Y-m-d');

            // Simple availability for connector (real data comes from portal)
            $availability_status = (!$is_past && $is_current_month) ? 'available' : 'unavailable';
            $is_available = (!$is_past && $is_current_month);

            $is_disabled = $is_past || !$is_available;

            $cell_classes = ['wpmc-day'];
            if (!$is_current_month) {
                $cell_classes[] = 'wpmc-day--empty';
            }
            if ($is_past) {
                $cell_classes[] = 'wpmc-day--past';
            }
            if ($current_date->format('w') == 0) {
                $cell_classes[] = 'wpmc-day--sun';
            } elseif ($current_date->format('w') == 6) {
                $cell_classes[] = 'wpmc-day--sat';
            } else {
                $cell_classes[] = 'wpmc-day--weekday';
            }

            $output .= sprintf(
                '<div class="%s" data-ymd="%s" data-property="%s" data-disabled="%d">',
                esc_attr(implode(' ', $cell_classes)),
                esc_attr($date_string),
                esc_attr($property_id),
                $is_disabled ? 1 : 0
            );

            $output .= '<span class="wpmc-day-number">' . $current_date->format('j') . '</span>';

            // Add price badge for available days (placeholder)
            if ($is_current_month && !$is_past && $show_prices) {
                $base_price = 15000; // Placeholder - real prices come from API
                if ($current_date->format('w') == 6) $base_price += 2000; // Saturday
                if ($current_date->format('w') == 0) $base_price += 1000; // Sunday
                $output .= '<span class="wpmc-day-price">¥' . number_format($base_price) . '</span>';
            }

            $output .= '</div>';

            $current_date->add(new DateInterval('P1D'));
        }

        $output .= '</div>';
    }

    return $output;
}
}
?>

<div class="wpmc-availability connector-calendar"
     id="<?php echo esc_attr($calendar_id); ?>"
     data-property-id="<?php echo esc_attr($property_id); ?>"
     data-months="<?php echo esc_attr($months); ?>"
     data-show-prices="<?php echo $show_prices ? 'true' : 'false'; ?>"
     data-interactions="<?php echo esc_attr($interactions); ?>"
     data-calendar-id="<?php echo esc_attr($calendar_id); ?>">

    <div class="wpmc-calendar-header">
        <div class="wpmc-calendar-nav">
            <button class="wpmc-nav-button wpmc-nav-prev" data-action="prev" aria-label="<?php echo esc_attr__('前の月', 'wp-minpaku-connector'); ?>">
                <span class="wpmc-nav-icon">‹</span>
                <span class="wpmc-nav-text"><?php echo esc_html__('前の月', 'wp-minpaku-connector'); ?></span>
            </button>
            <button class="wpmc-nav-button wpmc-nav-next" data-action="next" aria-label="<?php echo esc_attr__('次の月', 'wp-minpaku-connector'); ?>">
                <span class="wpmc-nav-text"><?php echo esc_html__('次の月', 'wp-minpaku-connector'); ?></span>
                <span class="wpmc-nav-icon">›</span>
            </button>
        </div>
        <div class="wpmc-calendar-subtitle">
            <?php echo esc_html__('日付を選択', 'wp-minpaku-connector'); ?>
        </div>
    </div>

    <div class="wpmc-calendar-months-grid" data-current-offset="<?php echo esc_attr($current_offset); ?>">
        <?php for ($i = 0; $i < $months; $i++): ?>
            <?php
            $month_date = clone $base_date;
            $month_date->add(new DateInterval('P' . $i . 'M'));
            $year = $month_date->format('Y');
            $month = $month_date->format('n');
            ?>

            <div class="wpmc-calendar-month" data-year="<?php echo esc_attr($year); ?>" data-month="<?php echo esc_attr($month); ?>">
                <h3 class="wpmc-calendar-month-title">
                    <?php echo esc_html($month_date->format('Y年n月')); ?>
                </h3>

                <div class="wpmc-calendar-grid">
                    <div class="wpmc-calendar-day-headers">
                        <div class="wpmc-calendar-day-header"><?php _e('日', 'wp-minpaku-connector'); ?></div>
                        <div class="wpmc-calendar-day-header"><?php _e('月', 'wp-minpaku-connector'); ?></div>
                        <div class="wpmc-calendar-day-header"><?php _e('火', 'wp-minpaku-connector'); ?></div>
                        <div class="wpmc-calendar-day-header"><?php _e('水', 'wp-minpaku-connector'); ?></div>
                        <div class="wpmc-calendar-day-header"><?php _e('木', 'wp-minpaku-connector'); ?></div>
                        <div class="wpmc-calendar-day-header"><?php _e('金', 'wp-minpaku-connector'); ?></div>
                        <div class="wpmc-calendar-day-header"><?php _e('土', 'wp-minpaku-connector'); ?></div>
                    </div>

                    <?php echo wpmc_safe_generate_calendar_days($year, $month, $property_id, $show_prices); ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Quote Panel (initially hidden) -->
    <div class="wpmc-quote-panel" style="display: none;" aria-live="polite">
        <div class="wpmc-quote-header">
            <h3><?php echo esc_html__('見積り', 'wp-minpaku-connector'); ?></h3>
            <button class="wpmc-clear-selection-btn" aria-label="<?php echo esc_attr__('選択をクリア', 'wp-minpaku-connector'); ?>">×</button>
        </div>
        <div class="wpmc-quote-content">
            <div class="wpmc-quote-placeholder">
                <p><?php echo esc_html__('日程を選択すると見積が表示されます', 'wp-minpaku-connector'); ?></p>
            </div>
        </div>
    </div>
</div>