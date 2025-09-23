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

        $output = sprintf(
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

        $output .= self::renderLegend();
        $output .= '</div>';

        // Add JavaScript initialization
        $output .= '<script>
        jQuery(document).ready(function($) {
            if (typeof window.mcsCalendarInit !== "undefined") {
                window.mcsCalendarInit("#' . esc_js($calendar_id) . '");
            }
        });
        </script>';

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
            if ($is_past) {
                $css_class .= ' mcs-day--past';
            }

            $output .= sprintf(
                '<div class="mcs-day %s" data-date="%s" data-property-id="%d" title="%s">',
                esc_attr($css_class),
                esc_attr($date_str),
                $property_id,
                esc_attr(self::getStatusLabel($status))
            );

            $output .= '<div class="mcs-day-content">';
            $output .= '<span class="mcs-day-number">' . $day . '</span>';

            // Add availability indicator
            if (!$is_past) {
                $output .= '<div class="mcs-availability-indicator" data-status="' . esc_attr($status) . '"></div>';
            }

            // Add price badge placeholder if enabled and not past date
            if ($show_prices && !$is_past) {
                $output .= '<div class="mcs-price-badge loading" data-date="' . esc_attr($date_str) . '">' . __('...', 'minpaku-suite') . '</div>';
            }

            $output .= '</div></div>';
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
     * Render calendar legend
     */
    private static function renderLegend(): string
    {
        $output = '<div class="mcs-calendar-legend">';
        $output .= '<h4>' . __('Legend', 'minpaku-suite') . '</h4>';
        $output .= '<div class="mcs-legend-items">';

        $legend_items = [
            'vacant' => __('Available', 'minpaku-suite'),
            'partial' => __('Partially Available', 'minpaku-suite'),
            'full' => __('Fully Booked', 'minpaku-suite')
        ];

        foreach ($legend_items as $status => $label) {
            $output .= sprintf(
                '<div class="mcs-legend-item"><span class="mcs-legend-color mcs-day---%s"></span> %s</div>',
                esc_attr($status),
                esc_html($label)
            );
        }

        $output .= '</div></div>';

        return $output;
    }

    /**
     * Enqueue calendar styles
     */
    public static function enqueue_styles(): void
    {
        if (self::shouldEnqueueStyles()) {
            wp_add_inline_style('wp-block-library', self::getCalendarCSS());
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
                min-height: 70px;
                padding: 0;
                border-right: 1px solid #f0f0f0;
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                background: white;
            }

            .mcs-day:last-child {
                border-right: none;
            }

            .mcs-day:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }

            .mcs-day--empty {
                background: #fafafa;
                cursor: default;
            }

            .mcs-day--empty:hover {
                transform: none;
                box-shadow: none;
            }

            .mcs-day--vacant {
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                border-left: 4px solid #28a745;
            }

            .mcs-day--partial {
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                border-left: 4px solid #ffc107;
            }

            .mcs-day--full {
                background: linear-gradient(135deg, #f8d7da 0%, #f5c2c7 100%);
                border-left: 4px solid #dc3545;
            }

            .mcs-day--past {
                background: #f5f5f5;
                color: #999;
                cursor: not-allowed;
            }

            .mcs-day--past:hover {
                transform: none;
                box-shadow: none;
            }

            .mcs-day-content {
                position: relative;
                width: 100%;
                height: 100%;
                padding: 8px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            .mcs-day-number {
                font-weight: 600;
                font-size: 14px;
                line-height: 1;
                color: #333;
                align-self: flex-start;
            }

            .mcs-availability-indicator {
                position: absolute;
                top: 4px;
                right: 4px;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #6c757d;
                transition: all 0.2s ease;
            }

            .mcs-day--vacant .mcs-availability-indicator {
                background: #28a745;
                box-shadow: 0 0 4px rgba(40, 167, 69, 0.5);
            }

            .mcs-day--partial .mcs-availability-indicator {
                background: #ffc107;
                box-shadow: 0 0 4px rgba(255, 193, 7, 0.5);
            }

            .mcs-day--full .mcs-availability-indicator {
                background: #dc3545;
                box-shadow: 0 0 4px rgba(220, 53, 69, 0.5);
            }

            .mcs-price-badge {
                position: relative;
                align-self: flex-end;
                background: rgba(102, 126, 234, 0.9);
                color: white;
                font-size: 9px;
                font-weight: 600;
                padding: 2px 4px;
                border-radius: 4px;
                white-space: nowrap;
                box-shadow: 0 1px 3px rgba(0,0,0,0.2);
                line-height: 1.1;
                min-width: 18px;
                text-align: center;
                opacity: 0;
                transform: scale(0.8);
                transition: all 0.2s ease;
                margin-top: auto;
                backdrop-filter: blur(4px);
            }

            .mcs-price-badge.loaded {
                opacity: 1;
                transform: scale(1);
            }

            .mcs-price-badge.error {
                background: #ff6b6b;
                font-size: 8px;
                padding: 1px 3px;
            }

            .mcs-price-badge.loading {
                background: #e0e0e0;
                color: #999;
                animation: pulse 1.5s ease-in-out infinite;
            }

            @keyframes pulse {
                0% { opacity: 0.6; }
                50% { opacity: 1; }
                100% { opacity: 0.6; }
            }

            .mcs-calendar-legend {
                margin-top: 20px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
                border: 1px solid #ddd;
            }

            .mcs-calendar-legend h4 {
                margin: 0 0 10px 0;
                font-size: 14px;
                font-weight: 600;
            }

            .mcs-legend-items {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
            }

            .mcs-legend-item {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
            }

            .mcs-legend-color {
                width: 16px;
                height: 16px;
                border-radius: 3px;
                border: 1px solid #ddd;
            }

            .mcs-legend-color.mcs-day---vacant {
                background: #d4edda;
            }

            .mcs-legend-color.mcs-day---partial {
                background: #fff3cd;
            }

            .mcs-legend-color.mcs-day---full {
                background: #f8d7da;
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

            .mcs-calendar-skeleton .mcs-day--skeleton:hover {
                transform: none;
                box-shadow: none;
            }

            @media (max-width: 600px) {
                .mcs-day-header, .mcs-day {
                    padding: 8px 4px;
                    font-size: 12px;
                }

                .mcs-legend-items {
                    flex-direction: column;
                    gap: 8px;
                }
            }
        ';
    }
}