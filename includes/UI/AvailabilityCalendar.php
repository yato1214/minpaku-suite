<?php
/**
 * Availability Calendar UI
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\UI;

if (!defined('ABSPATH')) {
    exit;
}

class AvailabilityCalendar
{
    public static function init(): void
    {
        add_shortcode('mcs_availability', [__CLASS__, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Render availability calendar shortcode
     */
    public static function render_shortcode($atts): string
    {
        $atts = shortcode_atts([
            'id' => '',
            'slug' => '',
            'title' => '',
            'months' => 2,
            'show_prices' => 'true',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'JPY'
        ], $atts);

        $months = max(1, min(12, intval($atts['months'])));
        $property_id = self::resolvePropertyId($atts);
        $resolution_method = '';

        if (!$property_id) {
            return '<p class="mcs-error">' . __('Property is not specified. Please set id, slug or title.', 'minpaku-suite') . '</p>';
        }

        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'mcs_property') {
            return '<p class="mcs-error">' . __('Property not found.', 'minpaku-suite') . '</p>';
        }

        try {
            $diagnostic_comment = self::getDiagnosticComment($atts, $property_id);
            return $diagnostic_comment . self::renderCalendar($property_id, $months, $atts);
        } catch (Exception $e) {
            error_log('Minpaku Suite Calendar Error: ' . $e->getMessage());
            $notice = '<div class="mcs-calendar-notice">' . __('Unable to load availability data.', 'minpaku-suite') . '</div>';
            return $notice . self::renderCalendarSkeleton($months);
        }
    }

    /**
     * Resolve property ID from shortcode attributes
     */
    private static function resolvePropertyId($atts): int
    {
        // A. id が数値なら採用
        if (!empty($atts['id']) && is_numeric($atts['id'])) {
            $id = absint($atts['id']);
            if ($id > 0) {
                return $id;
            }
        }

        // B. slug があれば get_page_by_path($slug, OBJECT, 'mcs_property')
        if (!empty($atts['slug'])) {
            $slug = sanitize_text_field($atts['slug']);
            $property = get_page_by_path($slug, OBJECT, 'mcs_property');
            if ($property && $property->post_type === 'mcs_property') {
                return $property->ID;
            }
        }

        // C. title があれば WP_Query で post_type=mcs_property & title完全一致（1件）
        if (!empty($atts['title'])) {
            $title = sanitize_text_field($atts['title']);
            $query = new \WP_Query([
                'post_type' => 'mcs_property',
                'title' => $title,
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false
            ]);

            if ($query->have_posts()) {
                $property = $query->posts[0];
                wp_reset_postdata();
                return $property->ID;
            }
            wp_reset_postdata();
        }

        // D. is_singular('mcs_property') の時は get_the_ID()
        if (is_singular('mcs_property')) {
            $current_id = get_the_ID();
            if ($current_id) {
                return $current_id;
            }
        }

        return 0;
    }

    /**
     * Get diagnostic comment for development
     */
    private static function getDiagnosticComment($atts, int $property_id): string
    {
        if (!current_user_can('manage_options')) {
            return '';
        }

        $method = '';
        if (!empty($atts['id']) && is_numeric($atts['id']) && absint($atts['id']) === $property_id) {
            $method = 'id=' . $atts['id'];
        } elseif (!empty($atts['slug'])) {
            $method = 'slug=' . $atts['slug'];
        } elseif (!empty($atts['title'])) {
            $method = 'title=' . $atts['title'];
        } elseif (is_singular('mcs_property')) {
            $method = 'singular page';
        }

        return "<!-- mcs_availability: resolved by {$method} to ID={$property_id} -->\n";
    }

    /**
     * Render calendar skeleton when service fails
     */
    private static function renderCalendarSkeleton(int $months): string
    {
        $output = '<div class="mcs-availability-calendar mcs-calendar-skeleton">';

        $current_date = new \DateTime();
        $current_date->modify('first day of this month');

        for ($i = 0; $i < $months; $i++) {
            $output .= '<div class="mcs-calendar-month">';
            $output .= '<h3 class="mcs-month-title">' . $current_date->format('F Y') . '</h3>';
            $output .= '<div class="mcs-calendar-grid">';

            // Day headers
            $day_names = [
                __('Sun', 'minpaku-suite'),
                __('Mon', 'minpaku-suite'),
                __('Tue', 'minpaku-suite'),
                __('Wed', 'minpaku-suite'),
                __('Thu', 'minpaku-suite'),
                __('Fri', 'minpaku-suite'),
                __('Sat', 'minpaku-suite')
            ];

            foreach ($day_names as $day_name) {
                $output .= '<div class="mcs-day-header">' . esc_html($day_name) . '</div>';
            }

            // Empty calendar grid
            for ($j = 0; $j < 42; $j++) {
                $output .= '<div class="mcs-day mcs-day--skeleton"></div>';
            }

            $output .= '</div></div>';
            $current_date->modify('+1 month');
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render calendar HTML
     */
    private static function renderCalendar(int $property_id, int $months, array $atts = []): string
    {
        if (!class_exists('MinpakuSuite\Availability\AvailabilityService')) {
            return '<p class="mcs-error">' . __('Availability service not available.', 'minpaku-suite') . '</p>';
        }

        $show_prices = ($atts['show_prices'] ?? 'true') === 'true';
        $adults = max(1, intval($atts['adults'] ?? 2));
        $children = max(0, intval($atts['children'] ?? 0));
        $infants = max(0, intval($atts['infants'] ?? 0));
        $currency = sanitize_text_field($atts['currency'] ?? 'JPY');

        $calendar_id = 'mcs-calendar-' . uniqid();

        // Add legend first
        $legend_output = '<div class="mcs-calendar-legend">
            <h4>' . __('空室状況の見方', 'minpaku-suite') . '</h4>
            <div class="mcs-legend-items">
                <div class="mcs-legend-item">
                    <span class="mcs-legend-color mcs-legend-color--vacant"></span>
                    <span class="mcs-legend-label">' . __('空き', 'minpaku-suite') . '</span>
                </div>
                <div class="mcs-legend-item">
                    <span class="mcs-legend-color mcs-legend-color--partial"></span>
                    <span class="mcs-legend-label">' . __('一部予約あり', 'minpaku-suite') . '</span>
                </div>
                <div class="mcs-legend-item">
                    <span class="mcs-legend-color mcs-legend-color--full"></span>
                    <span class="mcs-legend-label">' . __('満室', 'minpaku-suite') . '</span>
                </div>
            </div>
        </div>';

        $output = $legend_output . sprintf(
            '<div id="%s" class="mcs-availability-calendar" data-property-id="%d" data-show-prices="%s" data-adults="%d" data-children="%d" data-infants="%d" data-currency="%s">',
            esc_attr($calendar_id),
            $property_id,
            $show_prices ? '1' : '0',
            $adults,
            $children,
            $infants,
            esc_attr($currency)
        );

        $current_date = new \DateTime();
        $current_date->modify('first day of this month');

        for ($i = 0; $i < $months; $i++) {
            $output .= self::renderMonth($property_id, clone $current_date, $show_prices);
            $current_date->modify('+1 month');
        }

        $output .= '</div>';

        // Add JavaScript initialization via footer to avoid content display
        add_action('wp_footer', function() use ($calendar_id) {
            echo '<script type="text/javascript">';
            echo 'jQuery(document).ready(function($) {';
            echo '    if (typeof window.mcsCalendarInit !== "undefined") {';
            echo '        window.mcsCalendarInit("#' . esc_js($calendar_id) . '");';
            echo '    }';
            echo '});';
            echo '</script>';
        }, 100);

        return $output;
    }

    /**
     * Render single month
     */
    private static function renderMonth(int $property_id, \DateTime $month_start, bool $show_prices = false): string
    {
        $month_end = clone $month_start;
        $month_end->modify('+1 month');

        // Get occupancy data for the month
        $occupancy_map = \MinpakuSuite\Availability\AvailabilityService::getPropertyOccupancyMap(
            $property_id,
            $month_start,
            $month_end
        );

        $output = '<div class="mcs-calendar-month">';
        $output .= '<h3 class="mcs-month-title">' . $month_start->format('F Y') . '</h3>';
        $output .= '<div class="mcs-calendar-grid">';

        // Day headers
        $day_names = [
            __('Sun', 'minpaku-suite'),
            __('Mon', 'minpaku-suite'),
            __('Tue', 'minpaku-suite'),
            __('Wed', 'minpaku-suite'),
            __('Thu', 'minpaku-suite'),
            __('Fri', 'minpaku-suite'),
            __('Sat', 'minpaku-suite')
        ];

        foreach ($day_names as $day_name) {
            $output .= '<div class="mcs-day-header">' . esc_html($day_name) . '</div>';
        }

        // Get first day of month and number of days
        $first_day = clone $month_start;
        $first_day_of_week = intval($first_day->format('w'));
        $days_in_month = intval($month_start->format('t'));

        // Empty cells for days before month starts
        for ($i = 0; $i < $first_day_of_week; $i++) {
            $output .= '<div class="mcs-day mcs-day--empty"></div>';
        }

        // Days of the month
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = clone $month_start;
            $date->setDate(intval($month_start->format('Y')), intval($month_start->format('n')), $day);
            $date_str = $date->format('Y-m-d');

            $status = $occupancy_map[$date_str] ?? \MinpakuSuite\Availability\AvailabilityService::STATUS_VACANT;
            $css_class = self::getStatusCssClass($status);

            $is_past = $date < new \DateTime('today');
            $is_disabled = $is_past || $status !== \MinpakuSuite\Availability\AvailabilityService::STATUS_VACANT;

            if ($is_past) {
                $css_class .= ' mcs-day--past';
            }
            if ($is_disabled) {
                $css_class .= ' mcs-day--disabled';
            }

            $output .= sprintf(
                '<div class="mcs-day %s" data-ymd="%s" data-property="%d" data-disabled="%d">',
                esc_attr($css_class),
                esc_attr($date_str),
                $property_id,
                $is_disabled ? 1 : 0
            );

            $output .= '<span class="mcs-day-number">' . $day . '</span>';

            // Add price badge for available days only when enabled
            if ($show_prices && $status === \MinpakuSuite\Availability\AvailabilityService::STATUS_VACANT && !$is_past) {
                $price_text = self::getPriceForDay($property_id, $date_str);
                $output .= '<span class="mcs-day-price">' . esc_html($price_text) . '</span>';
            }

            $output .= '</div>';
        }

        $output .= '</div></div>';

        return $output;
    }

    /**
     * Get CSS class for availability status
     */
    private static function getStatusCssClass(string $status): string
    {
        switch ($status) {
            case \MinpakuSuite\Availability\AvailabilityService::STATUS_FULL:
                return 'mcs-day--full';
            case \MinpakuSuite\Availability\AvailabilityService::STATUS_PARTIAL:
                return 'mcs-day--partial';
            case \MinpakuSuite\Availability\AvailabilityService::STATUS_VACANT:
            default:
                return 'mcs-day--vacant';
        }
    }

    /**
     * Get status label for tooltip
     */
    private static function getStatusLabel(string $status): string
    {
        switch ($status) {
            case \MinpakuSuite\Availability\AvailabilityService::STATUS_FULL:
                return __('Fully Booked', 'minpaku-suite');
            case \MinpakuSuite\Availability\AvailabilityService::STATUS_PARTIAL:
                return __('Partially Available', 'minpaku-suite');
            case \MinpakuSuite\Availability\AvailabilityService::STATUS_VACANT:
            default:
                return __('Available', 'minpaku-suite');
        }
    }

    /**
     * Get price for a specific day
     */
    private static function getPriceForDay(int $property_id, string $date_str): string
    {
        $date = new \DateTime($date_str);
        $today = new \DateTime('today');

        // For future dates, try to get price from pricing engine
        if ($date >= $today && class_exists('MinpakuSuite\Pricing\PricingEngine') && class_exists('MinpakuSuite\Pricing\RateContext')) {
            try {
                $checkin = new \DateTime($date_str);
                $checkout = clone $checkin;
                $checkout->add(new \DateInterval('P1D'));

                $context = new \MinpakuSuite\Pricing\RateContext(
                    $property_id,
                    $checkin->format('Y-m-d'),
                    $checkout->format('Y-m-d'),
                    2, // adults
                    0, // children
                    0  // infants
                );

                $pricing_engine = new \MinpakuSuite\Pricing\PricingEngine($context);
                $quote = $pricing_engine->calculateQuote();

                if ($quote && isset($quote['total_incl_tax']) && $quote['total_incl_tax'] > 0) {
                    return '¥' . number_format($quote['total_incl_tax']);
                }
            } catch (\Exception $e) {
                error_log('Price calculation error for property ' . $property_id . ' on ' . $date_str . ': ' . $e->getMessage());
                // For DomainException (availability issues), silently fall back to base price
                if ($e instanceof \DomainException) {
                    // This date is not available, don't show pricing engine error
                } else {
                    error_log('Unexpected pricing error: ' . get_class($e) . ' - ' . $e->getMessage());
                }
            }
        }

        // Fallback to base price meta (for past dates or when pricing engine fails)
        $price_fields = ['base_price_test', 'mcs_base_price', 'base_price', 'price'];
        foreach ($price_fields as $field) {
            $base_price = get_post_meta($property_id, $field, true);
            if ($base_price && is_numeric($base_price) && $base_price > 0) {
                return '¥' . number_format(intval($base_price));
            }
        }

        // No price available
        return __('—', 'minpaku-suite');
    }


    /**
     * Enqueue calendar styles
     */
    public static function enqueue_styles(): void
    {
        if (self::shouldEnqueueStyles()) {
            // Enqueue external CSS file instead of inline styles
            $css_file = MINPAKU_SUITE_PLUGIN_URL . 'assets/admin-calendar.css';
            $css_version = filemtime(MINPAKU_SUITE_PLUGIN_DIR . 'assets/admin-calendar.css');
            wp_enqueue_style('mcs-admin-calendar', $css_file, [], $css_version);
        }
    }

    /**
     * Enqueue calendar scripts for pricing functionality
     */
    public static function enqueue_scripts(): void
    {
        if (self::shouldEnqueueStyles()) {
            // Enqueue external JS file instead of inline scripts
            $js_file = MINPAKU_SUITE_PLUGIN_URL . 'assets/admin-calendar.js';
            $js_version = filemtime(MINPAKU_SUITE_PLUGIN_DIR . 'assets/admin-calendar.js');
            wp_enqueue_script('mcs-admin-calendar', $js_file, ['jquery'], $js_version, true);

            // Pass admin URL to JavaScript
            wp_localize_script('mcs-admin-calendar', 'minpakuAdmin', [
                'bookingUrl' => admin_url('post-new.php?post_type=mcs_booking')
            ]);
        }
    }

    /**
     * Check if we should enqueue styles (if shortcode is present)
     */
    private static function shouldEnqueueStyles(): bool
    {
        global $post;

        if (!$post) {
            return false;
        }

        return has_shortcode($post->post_content, 'mcs_availability');
    }

    /**
     * Get calendar CSS
     */
    private static function getCalendarCSS(): string
    {
        return '
            .mcs-availability-calendar {
                max-width: 100%;
                margin: 20px 0;
            }

            .mcs-calendar-month {
                margin-bottom: 30px;
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .mcs-month-title {
                background: #667eea;
                color: white;
                margin: 0;
                padding: 16px 20px;
                text-align: center;
                font-size: 18px;
                font-weight: 600;
            }

            .mcs-calendar-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                background: #f0f0f0;
            }

            .mcs-day-header {
                background: #f8f9fa;
                color: #666;
                padding: 12px 8px;
                text-align: center;
                font-weight: 600;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 1px solid #e9ecef;
            }

            .mcs-day {
                position: relative;
                min-height: 64px;
                padding: 8px;
                border-right: 1px solid #f0f0f0;
                cursor: pointer;
                transition: all 0.2s ease;
                background: white;
                display: flex;
                align-items: flex-start;
                justify-content: flex-start;
            }

            .mcs-day:last-child {
                border-right: none;
            }

            .mcs-day:hover:not(.mcs-day--disabled):not(.mcs-day--empty) {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }

            .mcs-day--empty {
                background: #fafafa;
                cursor: default;
            }

            .mcs-day--vacant {
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                border-left: 4px solid #28a745;
            }

            .mcs-day--partial {
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                border-left: 4px solid #ffc107;
                cursor: not-allowed;
            }

            .mcs-day--full {
                background: linear-gradient(135deg, #f8d7da 0%, #f5c2c7 100%);
                border-left: 4px solid #dc3545;
                cursor: not-allowed;
            }

            .mcs-day--past,
            .mcs-day--disabled {
                background: #f5f5f5;
                color: #999;
                cursor: not-allowed;
            }

            .mcs-day-number {
                font-weight: 600;
                font-size: 14px;
                line-height: 1;
                color: #333;
            }

            .mcs-day-price {
                position: absolute;
                top: 4px;
                right: 6px;
                font-size: 11px;
                padding: 2px 6px;
                border-radius: 4px;
                background: rgba(0,0,0,0.06);
                color: #333;
                font-weight: 500;
                white-space: nowrap;
            }

            /* Complete legacy UI removal */
            .slot, .status-dot, .legend, .availability-slot,
            .mcs-day .slot, .mcs-day .status-dot, .mcs-day .legend,
            .mcs-day .availability-slot, .mcs-availability-indicator,
            .legend-mark, .slot-bar {
                display: none !important;
                content: none !important;
            }

            .mcs-day::before, .mcs-day::after,
            .mcs-day *::before, .mcs-day *::after {
                display: none !important;
                content: none !important;
            }

            .mcs-error {
                color: #d63384;
                background: #f8d7da;
                padding: 10px;
                border-radius: 4px;
                border: 1px solid #f5c6cb;
            }

            .mcs-calendar-notice {
                color: #856404;
                background: #fff3cd;
                padding: 10px;
                border-radius: 4px;
                border: 1px solid #ffeaa7;
                margin-bottom: 15px;
            }

            .mcs-calendar-skeleton .mcs-day--skeleton {
                background: #f8f9fa;
                opacity: 0.7;
                cursor: default;
            }

            @media (max-width: 600px) {
                .mcs-day {
                    min-height: 48px;
                    padding: 6px 4px;
                    font-size: 12px;
                }

                .mcs-day-price {
                    font-size: 10px;
                    padding: 1px 4px;
                }
            }
        ';
    }

    /**
     * Get JavaScript for calendar click-to-create functionality
     */
    private static function getCalendarJS(): string
    {
        $admin_url = admin_url('post-new.php?post_type=mcs_booking');

        return '
        // Admin Calendar - Click-to-Create Booking ONLY
        jQuery(document).ready(function($) {
            var adminUrl = "' . esc_js($admin_url) . '";

            // Remove any legacy calendar initialization
            if (window.mcsCalendarInit) {
                window.mcsCalendarInit = function() {
                    // Disabled - no legacy functionality
                };
            }

            // Click handler for booking creation (vacant days only)
            $(document).on("click", ".mcs-day", function() {
                var cell = $(this);
                var date = cell.data("ymd");
                var propertyId = cell.data("property");
                var isDisabled = cell.data("disabled");

                if (date && propertyId && !isDisabled && cell.hasClass("mcs-day--vacant")) {
                    var nextDay = new Date(date + "T00:00:00");
                    nextDay.setDate(nextDay.getDate() + 1);
                    var checkout = nextDay.toISOString().split("T")[0];

                    var bookingUrl = adminUrl + "&mcs_property=" + propertyId +
                                    "&mcs_checkin=" + date + "&mcs_checkout=" + checkout;

                    window.location.href = bookingUrl;
                }
            });

            // Remove all legacy slot/status/legend initialization code
            $(".slot, .status-dot, .legend, .availability-slot, .mcs-availability-indicator").remove();
        });
        ';
    }
}