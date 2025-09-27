<?php
/**
 * Portal Calendar Shortcode
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class PortalCalendar {

    public static function init() {
        add_shortcode('portal_calendar', [__CLASS__, 'render_calendar']);

        // Register AJAX handlers for modal functionality
        add_action('wp_ajax_portal_calendar_modal_content', [__CLASS__, 'ajax_modal_content']);
        add_action('wp_ajax_nopriv_portal_calendar_modal_content', [__CLASS__, 'ajax_modal_content']);
    }

    /**
     * Render calendar shortcode for portal side
     */
    public static function render_calendar($atts) {
        $atts = shortcode_atts([
            'property_id' => '',
            'months' => 2,
            'show_prices' => 'true',
            'modal' => 'false'
        ], $atts, 'portal_calendar');

        // Auto-detect property ID if not provided
        $property_id = self::get_property_id($atts['property_id']);

        if (!$property_id) {
            return '<div class="mpc-error" style="background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 12px; border-radius: 6px; margin: 16px 0;">' .
                   __('Property ID is required for calendar display. Please specify property_id="X" in the shortcode or use it within a property edit page.', 'minpaku-suite') .
                   '</div>';
        }

        $months = max(1, min(12, intval($atts['months'])));
        $show_prices = ($atts['show_prices'] === 'true');
        $is_modal = ($atts['modal'] === 'true');

        // Check if property exists
        if (get_post_type($property_id) !== 'mcs_property') {
            return '<div class="mpc-error" style="background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 12px; border-radius: 6px; margin: 16px 0;">' .
                   sprintf(__('Invalid property ID: %d. Property does not exist.', 'minpaku-suite'), $property_id) .
                   '</div>';
        }

        // モーダル表示の場合はボタンを返す
        if ($is_modal) {
            return self::render_modal_button($property_id, $months, $show_prices);
        }

        $calendar_id = 'portal-calendar-' . uniqid();

        ob_start();
        ?>

        <div id="<?php echo esc_attr($calendar_id); ?>" class="mpc-calendar-container portal-calendar"
             data-property-id="<?php echo esc_attr($property_id); ?>"
             data-show-prices="<?php echo $show_prices ? '1' : '0'; ?>"
             data-months="<?php echo esc_attr($months); ?>">

            <?php for ($i = 0; $i < $months; $i++): ?>
                <?php
                $month_date = new \DateTime();
                $month_date->add(new \DateInterval('P' . $i . 'M'));
                $year = $month_date->format('Y');
                $month = $month_date->format('n');
                ?>

                <div class="mpc-calendar-month" data-year="<?php echo esc_attr($year); ?>" data-month="<?php echo esc_attr($month); ?>">
                    <h3 class="mpc-calendar-month-title">
                        <?php echo esc_html($month_date->format('Y年n月')); ?>
                    </h3>

                    <div class="mpc-calendar-grid">
                        <div class="mpc-calendar-header">
                            <div class="mpc-calendar-day-header"><?php _e('日', 'minpaku-suite'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('月', 'minpaku-suite'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('火', 'minpaku-suite'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('水', 'minpaku-suite'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('木', 'minpaku-suite'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('金', 'minpaku-suite'); ?></div>
                            <div class="mpc-calendar-day-header"><?php _e('土', 'minpaku-suite'); ?></div>
                        </div>

                        <?php echo self::generate_calendar_days($year, $month, $property_id, $show_prices); ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <!-- Portal Calendar CSS - Improved Layout and Design -->
        <style>
        .portal-calendar {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }

        .portal-calendar .mpc-calendar-legend {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .portal-calendar .mpc-calendar-legend h4 {
            margin: 0 0 16px 0;
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }

        .portal-calendar .mpc-legend-items {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .portal-calendar .mpc-legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .portal-calendar .mpc-legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 1px solid rgba(0,0,0,0.15);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .portal-calendar .mpc-legend-label {
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }

        .portal-calendar .mpc-calendar-container {
            max-width: 100%;
            margin: 0;
        }

        .portal-calendar .mpc-calendar-month {
            margin-bottom: 32px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }

        .portal-calendar .mpc-calendar-month-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin: 0;
            padding: 20px 24px;
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .portal-calendar .mpc-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0;
            background: #fafafa;
        }

        .portal-calendar .mpc-calendar-header {
            display: contents;
        }

        .portal-calendar .mpc-calendar-day-header {
            background: #f8fafc;
            color: #64748b;
            padding: 16px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
        }

        .portal-calendar .mpc-calendar-day-header:last-child {
            border-right: none;
        }

        .portal-calendar .mpc-calendar-week {
            display: contents;
        }

        .portal-calendar .mcs-day {
            position: relative;
            min-height: 90px;
            padding: 12px 8px 8px 8px;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            overflow: hidden;
        }

        .portal-calendar .mcs-day:nth-child(7n) {
            border-right: none;
        }

        .portal-calendar .mcs-day:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 2;
        }

        .portal-calendar .mcs-day--empty {
            background: #f8fafc !important;
            cursor: default;
            opacity: 0.5;
        }

        .portal-calendar .mcs-day--empty:hover {
            transform: none;
            box-shadow: none;
        }

        .portal-calendar .mcs-day--weekday {
            background: #f0fdf4 !important;
            border-left: 3px solid #22c55e;
        }

        .portal-calendar .mcs-day--sat {
            background: #eff6ff !important;
            border-left: 3px solid #3b82f6;
        }

        .portal-calendar .mcs-day--sun {
            background: #fef2f2 !important;
            border-left: 3px solid #ef4444;
        }

        .portal-calendar .mcs-day--full,
        .portal-calendar .mcs-day--blackout {
            background: #f1f5f9 !important;
            cursor: not-allowed !important;
            opacity: 0.6;
            border-left: 3px solid #64748b;
        }

        .portal-calendar .mcs-day--booked {
            cursor: pointer !important;
        }

        .portal-calendar .mcs-day--full:hover,
        .portal-calendar .mcs-day--blackout:hover {
            transform: none;
            box-shadow: none;
        }

        .portal-calendar .mcs-day--booked:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .portal-calendar .mcs-day--past {
            background: #f8fafc !important;
            color: #94a3b8;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .portal-calendar .mcs-day--past:hover {
            transform: none;
            box-shadow: none;
        }

        .portal-calendar .mcs-day-number {
            font-weight: 700;
            font-size: 16px;
            line-height: 1.2;
            color: #1e293b;
            margin-bottom: auto;
        }

        .portal-calendar .mcs-day--past .mcs-day-number {
            color: #94a3b8;
        }

        .portal-calendar .mcs-day-price {
            align-self: stretch;
            margin-top: 8px;
            padding: 6px 8px;
            border-radius: 6px;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            font-weight: 600;
            font-size: 12px;
            text-align: center;
            line-height: 1.2;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .portal-calendar .mcs-day-full-badge {
            align-self: stretch;
            margin-top: 8px;
            padding: 6px 8px;
            border-radius: 6px;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            font-weight: 600;
            font-size: 12px;
            text-align: center;
            line-height: 1.2;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .portal-calendar .mpc-calendar-month-title {
                padding: 16px 20px;
                font-size: 18px;
            }

            .portal-calendar .mpc-calendar-day-header {
                padding: 12px 4px;
                font-size: 11px;
            }

            .portal-calendar .mcs-day {
                min-height: 75px;
                padding: 8px 6px 6px 6px;
            }

            .portal-calendar .mcs-day-number {
                font-size: 14px;
            }

            .portal-calendar .mcs-day-price {
                padding: 4px 6px;
                font-size: 11px;
                margin-top: 6px;
            }

            .portal-calendar .mcs-day-full-badge {
                padding: 4px 6px;
                font-size: 11px;
                margin-top: 6px;
            }

            .portal-calendar .mpc-legend-items {
                gap: 16px;
            }
        }

        @media (max-width: 480px) {
            .portal-calendar .mcs-day {
                min-height: 65px;
                padding: 6px 4px 4px 4px;
            }

            .portal-calendar .mcs-day-number {
                font-size: 13px;
            }

            .portal-calendar .mcs-day-price {
                padding: 3px 4px;
                font-size: 10px;
                margin-top: 4px;
            }

            .portal-calendar .mcs-day-full-badge {
                padding: 3px 4px;
                font-size: 10px;
                margin-top: 4px;
            }

            .portal-calendar .mpc-calendar-day-header {
                padding: 10px 2px;
                font-size: 10px;
            }
        }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle calendar day clicks for booking using event delegation (works with dynamically loaded content)
            document.addEventListener('click', function(e) {
                // Check if clicked element is a calendar day in portal calendar
                if ((e.target.classList.contains('mcs-day') || e.target.closest('.mcs-day')) &&
                    e.target.closest('.portal-calendar')) {

                    var dayElement = e.target.classList.contains('mcs-day') ? e.target : e.target.closest('.mcs-day');
                    e.preventDefault();

                    console.log('Portal calendar day clicked:', dayElement);

                    // Only allow clicks on available days and current month days
                    if (dayElement.dataset.disabled === '1' || dayElement.classList.contains('mcs-day--empty') || dayElement.classList.contains('mcs-day--past')) {
                        console.log('Day click ignored - disabled or invalid day');
                        return;
                    }

                    var date = dayElement.dataset.ymd;
                    var propertyId = dayElement.dataset.property;

                    console.log('Portal calendar navigation - Date:', date, 'Property:', propertyId);

                    if (date && propertyId) {
                        // Navigate to new booking page with property and date pre-filled
                        var bookingUrl = '<?php echo esc_js(admin_url('post-new.php?post_type=mcs_booking')); ?>' +
                                        '&property_id=' + encodeURIComponent(propertyId) +
                                        '&checkin=' + encodeURIComponent(date);

                        console.log('Navigating to booking URL:', bookingUrl);
                        window.location.href = bookingUrl;
                    } else {
                        console.warn('Missing date or property ID for navigation');
                    }
                }
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Get property ID from shortcode attribute or auto-detect from current context
     */
    private static function get_property_id($provided_id) {
        // If property_id is provided in shortcode, use it
        if (!empty($provided_id)) {
            return intval($provided_id);
        }

        // Auto-detect from current context
        global $post, $pagenow;

        // 1. Check if we're editing a property post
        if (is_admin() && $pagenow === 'post.php' && isset($_GET['post'])) {
            $current_post_id = intval($_GET['post']);
            if (get_post_type($current_post_id) === 'mcs_property') {
                return $current_post_id;
            }
        }

        // 2. Check if we're creating a new property post
        if (is_admin() && $pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'mcs_property') {
            // For new posts, we can't auto-detect, so return 0 to show error
            return 0;
        }

        // 3. Check current post in frontend
        if ($post && get_post_type($post) === 'mcs_property') {
            return $post->ID;
        }

        // 4. Check if property_id is in URL parameters
        if (isset($_GET['property_id'])) {
            $property_id = intval($_GET['property_id']);
            if (get_post_type($property_id) === 'mcs_property') {
                return $property_id;
            }
        }

        // 5. Check if we're in a property-related page context
        if (isset($_GET['post_type']) && $_GET['post_type'] === 'mcs_property') {
            // Get the first property as fallback for demo purposes
            $properties = get_posts([
                'post_type' => 'mcs_property',
                'post_status' => 'publish',
                'numberposts' => 1,
                'fields' => 'ids'
            ]);

            if (!empty($properties)) {
                return $properties[0];
            }
        }

        return 0; // No property ID found
    }

    /**
     * Generate calendar days for a specific month
     */
    private static function generate_calendar_days($year, $month, $property_id, $show_prices) {
        // Load DayClassifier
        require_once MCS_PATH . 'includes/Calendar/DayClassifier.php';

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
            $output .= '<div class="mpc-calendar-week">';

            for ($day = 0; $day < 7; $day++) {
                $is_current_month = ($current_date->format('n') == $month);
                $is_past = ($current_date < new \DateTime('today'));
                $date_string = $current_date->format('Y-m-d');

                // Get availability status
                $availability_status = \MinpakuSuite\Calendar\DayClassifier::getAvailabilityStatus($date_string, $property_id);

                // Get day classification for colors
                $day_classification = \MinpakuSuite\Calendar\DayClassifier::getSimpleDayClasses($date_string, $availability_status);

                $is_available = ($availability_status === 'available');
                $is_disabled = $is_past || !$is_available;

                $cell_classes = $day_classification['css_classes'];
                if (!$is_current_month) {
                    $cell_classes[] = 'mcs-day--empty';
                }
                if ($is_past) {
                    $cell_classes[] = 'mcs-day--past';
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
                    if ($availability_status === 'available' && $show_prices) {
                        $price = \MinpakuSuite\Calendar\DayClassifier::calculatePriceForDate($date_string, $property_id);
                        if ($price > 0) {
                            $output .= '<span class="mcs-day-price">¥' . number_format($price) . '</span>';
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
     * Render modal calendar button
     */
    private static function render_modal_button($property_id, $months, $show_prices) {
        // Enqueue modal scripts and styles
        self::enqueue_modal_assets();

        $property_title = get_the_title($property_id);
        $button_id = 'portal-calendar-btn-' . uniqid();

        ob_start();
        ?>
        <div class="portal-calendar-modal-wrapper">
            <div class="portal-calendar-button-container">
                <button id="<?php echo esc_attr($button_id); ?>"
                        class="portal-calendar-modal-button"
                        data-property-id="<?php echo esc_attr($property_id); ?>"
                        data-property-title="<?php echo esc_attr($property_title); ?>"
                        data-months="<?php echo esc_attr($months); ?>"
                        data-show-prices="<?php echo $show_prices ? '1' : '0'; ?>">
                    <span class="portal-calendar-icon">📅</span>
                    <span class="portal-calendar-text"><?php echo esc_html__('カレンダーを表示', 'minpaku-suite'); ?></span>
                </button>
            </div>
        </div>

        <!-- Modal Structure -->
        <div id="portal-calendar-modal-<?php echo esc_attr($property_id); ?>" class="portal-calendar-modal-overlay" style="display: none;">
            <div class="portal-calendar-modal-content">
                <div class="portal-calendar-modal-header">
                    <h3 class="portal-calendar-modal-title"><?php echo esc_html($property_title); ?> - <?php echo esc_html__('空室カレンダー', 'minpaku-suite'); ?></h3>
                    <button class="portal-calendar-modal-close">&times;</button>
                </div>
                <div class="portal-calendar-modal-body">
                    <div class="portal-calendar-modal-loading">
                        <div class="portal-calendar-loading-spinner"></div>
                        <p><?php echo esc_html__('カレンダーを読み込み中...', 'minpaku-suite'); ?></p>
                    </div>
                    <div class="portal-calendar-modal-calendar-content" style="display: none;"></div>
                </div>
            </div>
        </div>

        <style>
        .portal-calendar-modal-wrapper {
            margin: 20px 0;
        }

        .portal-calendar-modal-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .portal-calendar-modal-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .portal-calendar-icon {
            font-size: 18px;
        }

        .portal-calendar-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .portal-calendar-modal-content {
            background: white;
            border-radius: 12px;
            max-width: 90vw;
            max-height: 90vh;
            width: 900px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .portal-calendar-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .portal-calendar-modal-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .portal-calendar-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: white;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
            line-height: 1;
        }

        .portal-calendar-modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .portal-calendar-modal-body {
            padding: 24px;
            max-height: calc(90vh - 80px);
            overflow-y: auto;
        }

        .portal-calendar-modal-loading {
            text-align: center;
            padding: 40px;
        }

        .portal-calendar-loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        body.portal-calendar-modal-open {
            overflow: hidden;
        }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Portal Calendar Modal Script Loaded');

            // Modal button click handler
            var button = document.getElementById('<?php echo esc_js($button_id); ?>');
            if (button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Modal button clicked');

                    var propertyId = this.getAttribute('data-property-id');
                    var propertyTitle = this.getAttribute('data-property-title');
                    var months = this.getAttribute('data-months');
                    var showPrices = this.getAttribute('data-show-prices');

                    console.log('Opening modal for property:', propertyId);
                    openPortalCalendarModal(propertyId, propertyTitle, months, showPrices);
                });
            }

            // Modal close handlers
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('portal-calendar-modal-close') ||
                    e.target.classList.contains('portal-calendar-modal-overlay')) {
                    closePortalCalendarModal();
                }
            });

            // ESC key handler
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closePortalCalendarModal();
                }
            });
        });

        function openPortalCalendarModal(propertyId, propertyTitle, months, showPrices) {
            console.log('openPortalCalendarModal called with:', propertyId, propertyTitle, months, showPrices);

            var modal = document.getElementById('portal-calendar-modal-' + propertyId);
            console.log('Modal element found:', modal);

            if (!modal) {
                console.error('Modal not found for ID: portal-calendar-modal-' + propertyId);
                return;
            }

            var loadingDiv = modal.querySelector('.portal-calendar-modal-loading');
            var contentDiv = modal.querySelector('.portal-calendar-modal-calendar-content');

            // Show modal
            modal.style.display = 'flex';
            document.body.classList.add('portal-calendar-modal-open');

            console.log('Modal should now be visible');

            // Show loading
            if (loadingDiv) loadingDiv.style.display = 'block';
            if (contentDiv) {
                contentDiv.style.display = 'none';
                contentDiv.innerHTML = '';
            }

            // Generate calendar content directly (not via AJAX for portal side)
            setTimeout(function() {
                var calendarShortcode = '[portal_calendar property_id="' + propertyId + '" months="' + months + '" show_prices="' + (showPrices ? 'true' : 'false') + '"]';

                // For portal side, we can directly call the shortcode
                var formData = new FormData();
                formData.append('action', 'portal_calendar_modal_content');
                formData.append('property_id', propertyId);
                formData.append('months', months);
                formData.append('show_prices', showPrices);
                formData.append('nonce', '<?php echo wp_create_nonce('portal_calendar_modal'); ?>');

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (loadingDiv) loadingDiv.style.display = 'none';
                    if (data.success) {
                        if (contentDiv) {
                            contentDiv.innerHTML = data.data;
                            contentDiv.style.display = 'block';

                            // Initialize click handlers for modal calendar after content is loaded
                            initModalCalendarHandlers(contentDiv);
                        }
                    } else {
                        if (contentDiv) {
                            contentDiv.innerHTML = '<div class="portal-calendar-error">カレンダーの読み込みに失敗しました。</div>';
                            contentDiv.style.display = 'block';
                        }
                    }
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                    if (loadingDiv) loadingDiv.style.display = 'none';
                    if (contentDiv) {
                        contentDiv.innerHTML = '<div class="portal-calendar-error">ネットワークエラーが発生しました。</div>';
                        contentDiv.style.display = 'block';
                    }
                });
            }, 100);
        }

        function initModalCalendarHandlers(modalContent) {
            console.log('Initializing modal calendar handlers');

            // Find all calendar days in the modal content
            var calendarDays = modalContent.querySelectorAll('.mcs-day');
            console.log('Found calendar days in modal:', calendarDays.length);

            calendarDays.forEach(function(dayElement) {
                dayElement.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    console.log('Modal calendar day clicked:', dayElement);

                    // Only allow clicks on available days and current month days
                    if (dayElement.dataset.disabled === '1' ||
                        dayElement.classList.contains('mcs-day--empty') ||
                        dayElement.classList.contains('mcs-day--past')) {
                        console.log('Day click ignored - disabled or invalid day');
                        return;
                    }

                    var date = dayElement.dataset.ymd;
                    var propertyId = dayElement.dataset.property;

                    console.log('Modal calendar navigation - Date:', date, 'Property:', propertyId);

                    if (date && propertyId) {
                        // Navigate to new booking page with property and date pre-filled
                        var bookingUrl = '<?php echo esc_js(admin_url('post-new.php?post_type=mcs_booking')); ?>' +
                                        '&property_id=' + encodeURIComponent(propertyId) +
                                        '&checkin=' + encodeURIComponent(date);

                        console.log('Navigating to booking URL from modal:', bookingUrl);
                        window.location.href = bookingUrl;
                    } else {
                        console.warn('Missing date or property ID for navigation');
                    }
                });
            });
        }

        function closePortalCalendarModal() {
            var modals = document.querySelectorAll('.portal-calendar-modal-overlay');
            modals.forEach(function(modal) {
                if (modal.style.display !== 'none') {
                    modal.style.display = 'none';
                }
            });
            document.body.classList.remove('portal-calendar-modal-open');
        }
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue modal assets
     */
    private static function enqueue_modal_assets() {
        // Enqueue jQuery if not already loaded
        wp_enqueue_script('jquery');

        // Modal functionality is handled inline for simplicity
        // Could be moved to separate files if needed
        // AJAX handlers are registered in init() method
    }

    /**
     * AJAX handler for modal calendar content
     */
    public static function ajax_modal_content() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'portal_calendar_modal')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        $property_id = intval($_POST['property_id'] ?? 0);
        $months = intval($_POST['months'] ?? 2);
        $show_prices = ($_POST['show_prices'] ?? '0') === '1';

        if (!$property_id) {
            wp_send_json_error('Invalid property ID');
            return;
        }

        // Generate calendar content
        $calendar_content = self::render_calendar([
            'property_id' => $property_id,
            'months' => $months,
            'show_prices' => $show_prices ? 'true' : 'false',
            'modal' => 'false' // Prevent recursive modal rendering
        ]);

        wp_send_json_success($calendar_content);
    }
}