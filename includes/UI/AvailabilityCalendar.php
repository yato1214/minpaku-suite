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
            'id' => 0,
            'months' => 2
        ], $atts);

        $property_id = intval($atts['id']);
        $months = max(1, min(12, intval($atts['months'])));

        if (!$property_id) {
            return '<p class="mcs-error">' . __('Property ID is required.', 'minpaku-suite') . '</p>';
        }

        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'mcs_property') {
            return '<p class="mcs-error">' . __('Property not found.', 'minpaku-suite') . '</p>';
        }

        try {
            return self::renderCalendar($property_id, $months);
        } catch (Exception $e) {
            error_log('Minpaku Suite Calendar Error: ' . $e->getMessage());
            return '<p class="mcs-error">' . __('Unable to load calendar.', 'minpaku-suite') . '</p>';
        }
    }

    /**
     * Render calendar HTML
     */
    private static function renderCalendar(int $property_id, int $months): string
    {
        if (!class_exists('MinpakuSuite\Availability\AvailabilityService')) {
            return '<p class="mcs-error">' . __('Availability service not available.', 'minpaku-suite') . '</p>';
        }

        $output = '<div class="mcs-availability-calendar">';

        $current_date = new \DateTime();
        $current_date->modify('first day of this month');

        for ($i = 0; $i < $months; $i++) {
            $output .= self::renderMonth($property_id, clone $current_date);
            $current_date->modify('+1 month');
        }

        $output .= self::renderLegend();
        $output .= '</div>';

        return $output;
    }

    /**
     * Render single month
     */
    private static function renderMonth(int $property_id, \DateTime $month_start): string
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
                '<div class="mcs-day %s" data-date="%s" title="%s">%d</div>',
                esc_attr($css_class),
                esc_attr($date_str),
                esc_attr(self::getStatusLabel($status)),
                $day
            );
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
                max-width: 800px;
                margin: 20px 0;
            }

            .mcs-calendar-month {
                margin-bottom: 30px;
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
            }

            .mcs-month-title {
                background: #f8f9fa;
                margin: 0;
                padding: 15px;
                text-align: center;
                border-bottom: 1px solid #ddd;
                font-size: 18px;
                font-weight: 600;
            }

            .mcs-calendar-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 1px;
                background: #e9ecef;
            }

            .mcs-day-header {
                background: #6c757d;
                color: white;
                padding: 10px;
                text-align: center;
                font-weight: 600;
                font-size: 12px;
                text-transform: uppercase;
            }

            .mcs-day {
                background: white;
                padding: 12px;
                text-align: center;
                min-height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s ease;
                position: relative;
            }

            .mcs-day:hover {
                transform: scale(1.05);
                z-index: 10;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            }

            .mcs-day--empty {
                background: #f8f9fa;
                cursor: default;
            }

            .mcs-day--empty:hover {
                transform: none;
                box-shadow: none;
            }

            .mcs-day--vacant {
                background: #d4edda;
                color: #155724;
            }

            .mcs-day--partial {
                background: #fff3cd;
                color: #856404;
            }

            .mcs-day--full {
                background: #f8d7da;
                color: #721c24;
            }

            .mcs-day--past {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .mcs-day--past:hover {
                transform: none;
                box-shadow: none;
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