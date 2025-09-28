<?php
/**
 * Portal Calendar Template
 * Displays availability calendar for property pages
 *
 * @package MinpakuSuite
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
$calendar_id = 'mcs-cal-' . $property_id . '-' . uniqid();

/**
 * Safe helper function to generate calendar days with full functionality
 */
if (!function_exists('mcs_safe_generate_calendar_days')) {
function mcs_safe_generate_calendar_days($year, $month, $property_id, $show_prices) {
    // Load DayClassifier safely
    $classifier_path = MCS_PATH . 'includes/Calendar/DayClassifier.php';
    if (file_exists($classifier_path)) {
        require_once $classifier_path;
    }

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
        $output .= '<div class="mcs-calendar-week">';

        for ($day = 0; $day < 7; $day++) {
            $is_current_month = ($current_date->format('n') == $month);
            $is_past = ($current_date < new DateTime('today'));
            $date_string = $current_date->format('Y-m-d');

            // Get availability status using DayClassifier if available
            if (class_exists('\MinpakuSuite\Calendar\DayClassifier')) {
                $availability_status = \MinpakuSuite\Calendar\DayClassifier::getAvailabilityStatus($date_string, $property_id);
                $day_classification = \MinpakuSuite\Calendar\DayClassifier::getSimpleDayClasses($date_string, $availability_status);
                $is_available = ($availability_status === 'available');
            } else {
                // Fallback to simple logic
                $availability_status = (!$is_past && $is_current_month) ? 'available' : 'unavailable';
                $is_available = (!$is_past && $is_current_month);
                $day_classification = [
                    'css_classes' => ['mcs-day'],
                    'background_color' => '#ffffff'
                ];
            }

            $is_disabled = $is_past || !$is_available;

            $cell_classes = $day_classification['css_classes'] ?? ['mcs-day'];
            if (!$is_current_month) {
                $cell_classes[] = 'mcs-day--empty';
            }
            if ($is_past) {
                $cell_classes[] = 'mcs-day--past';
            }

            $style_attr = '';
            if (!empty($day_classification['background_color'])) {
                $style_attr = 'style="background-color: ' . esc_attr($day_classification['background_color']) . ';"';
            }

            $output .= sprintf(
                '<div class="%s" data-ymd="%s" data-property="%s" data-disabled="%d" %s>',
                esc_attr(implode(' ', $cell_classes)),
                esc_attr($date_string),
                esc_attr($property_id),
                $is_disabled ? 1 : 0,
                $style_attr
            );

            $output .= '<span class="mcs-day-number">' . $current_date->format('j') . '</span>';

            // Add price badge for available days, or status badges for unavailable days
            if ($is_current_month && !$is_past) {
                if ($availability_status === 'available' && $show_prices) {
                    // Get actual price using DayClassifier if available
                    if (class_exists('\MinpakuSuite\Calendar\DayClassifier')) {
                        $price = \MinpakuSuite\Calendar\DayClassifier::calculatePriceForDate($date_string, $property_id);
                        if ($price > 0) {
                            $output .= '<span class="mcs-day-price">¥' . number_format($price) . '</span>';
                        }
                    } else {
                        // Fallback pricing
                        $base_price = 15000;
                        if ($current_date->format('w') == 6) $base_price += 2000; // Saturday
                        if ($current_date->format('w') == 0) $base_price += 1000; // Sunday
                        $output .= '<span class="mcs-day-price">¥' . number_format($base_price) . '</span>';
                    }
                } elseif ($availability_status === 'full') {
                    $output .= '<span class="mcs-day-full-badge">満室</span>';
                } elseif ($availability_status === 'blocked') {
                    $output .= '<span class="mcs-day-blocked-badge">×</span>';
                }
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

<div class="mcs-availability"
     id="<?php echo esc_attr($calendar_id); ?>"
     data-property-id="<?php echo esc_attr($property_id); ?>"
     data-months="<?php echo esc_attr($months); ?>"
     data-show-prices="<?php echo $show_prices ? 'true' : 'false'; ?>"
     data-interactions="<?php echo esc_attr($interactions); ?>"
     data-calendar-id="<?php echo esc_attr($calendar_id); ?>">

    <div class="mcs-calendar-header">
        <div class="mcs-calendar-nav">
            <button class="mcs-nav-button mcs-nav-prev" data-action="prev" aria-label="<?php echo esc_attr__('前の月', 'minpaku-suite'); ?>">
                <span class="mcs-nav-icon">‹</span>
                <span class="mcs-nav-text"><?php echo esc_html__('前の月', 'minpaku-suite'); ?></span>
            </button>
            <button class="mcs-nav-button mcs-nav-next" data-action="next" aria-label="<?php echo esc_attr__('次の月', 'minpaku-suite'); ?>">
                <span class="mcs-nav-text"><?php echo esc_html__('次の月', 'minpaku-suite'); ?></span>
                <span class="mcs-nav-icon">›</span>
            </button>
        </div>
        <div class="mcs-calendar-subtitle">
            <?php echo esc_html__('日付を選択', 'minpaku-suite'); ?>
        </div>
    </div>

    <div class="mcs-calendar-months-grid" data-current-offset="<?php echo esc_attr($current_offset); ?>">
        <?php for ($i = 0; $i < $months; $i++): ?>
            <?php
            $month_date = clone $base_date;
            $month_date->add(new DateInterval('P' . $i . 'M'));
            $year = $month_date->format('Y');
            $month = $month_date->format('n');
            ?>

            <div class="mcs-calendar-month" data-year="<?php echo esc_attr($year); ?>" data-month="<?php echo esc_attr($month); ?>">
                <h3 class="mcs-calendar-month-title">
                    <?php echo esc_html($month_date->format('Y年n月')); ?>
                </h3>

                <div class="mcs-calendar-grid">
                    <div class="mcs-calendar-day-headers">
                        <div class="mcs-calendar-day-header"><?php _e('日', 'minpaku-suite'); ?></div>
                        <div class="mcs-calendar-day-header"><?php _e('月', 'minpaku-suite'); ?></div>
                        <div class="mcs-calendar-day-header"><?php _e('火', 'minpaku-suite'); ?></div>
                        <div class="mcs-calendar-day-header"><?php _e('水', 'minpaku-suite'); ?></div>
                        <div class="mcs-calendar-day-header"><?php _e('木', 'minpaku-suite'); ?></div>
                        <div class="mcs-calendar-day-header"><?php _e('金', 'minpaku-suite'); ?></div>
                        <div class="mcs-calendar-day-header"><?php _e('土', 'minpaku-suite'); ?></div>
                    </div>

                    <?php echo mcs_safe_generate_calendar_days($year, $month, $property_id, $show_prices); ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Quote Panel (initially hidden) -->
    <div class="mcs-quote-panel" style="display: none;" aria-live="polite">
        <div class="mcs-quote-header">
            <h3><?php echo esc_html__('見積り', 'minpaku-suite'); ?></h3>
            <button class="mcs-clear-selection-btn" aria-label="<?php echo esc_attr__('選択をクリア', 'minpaku-suite'); ?>">×</button>
        </div>
        <div class="mcs-quote-content">
            <div class="mcs-quote-placeholder">
                <p><?php echo esc_html__('日程を選択すると見積が表示されます', 'minpaku-suite'); ?></p>
            </div>
        </div>
    </div>
</div>