<?php
/**
 * Day Classification for Calendar Display
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Calendar;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/JPHolidays.php';

class DayClassifier {

    /**
     * Classify a date and return day type information
     *
     * @param string $date Date in Y-m-d format
     * @param string $timezone Timezone for calculations (default: Asia/Tokyo)
     * @return array Classification information
     */
    public static function classifyDate($date, $timezone = 'Asia/Tokyo') {
        try {
            $dt = new \DateTime($date, new \DateTimeZone($timezone));
        } catch (\Exception $e) {
            return [
                'is_saturday' => false,
                'is_sunday' => false,
                'is_holiday' => false,
                'day_of_week' => 0,
                'day_name' => '',
                'holiday_name' => null,
                'css_classes' => ['mcs-day--unknown'],
                'color_class' => 'unknown',
                'error' => 'Invalid date format'
            ];
        }

        $day_of_week = (int) $dt->format('w'); // 0 = Sunday, 6 = Saturday
        $is_sunday = ($day_of_week === 0);
        $is_saturday = ($day_of_week === 6);
        $is_holiday = JPHolidays::isHoliday($date);
        $holiday_name = JPHolidays::getHolidayName($date);

        // Determine color class based on priority
        $color_class = 'weekday'; // Default
        $css_classes = ['mcs-day'];

        if ($is_holiday) {
            $color_class = 'holiday';
            $css_classes[] = 'mcs-day--holiday';
        } elseif ($is_sunday) {
            $color_class = 'sunday';
            $css_classes[] = 'mcs-day--sun';
        } elseif ($is_saturday) {
            $color_class = 'saturday';
            $css_classes[] = 'mcs-day--sat';
        } else {
            $css_classes[] = 'mcs-day--weekday';
        }

        return [
            'is_saturday' => $is_saturday,
            'is_sunday' => $is_sunday,
            'is_holiday' => $is_holiday,
            'day_of_week' => $day_of_week,
            'day_name' => self::getDayName($day_of_week),
            'holiday_name' => $holiday_name,
            'css_classes' => $css_classes,
            'color_class' => $color_class,
            'background_color' => self::getBackgroundColor($color_class),
            'date_obj' => $dt
        ];
    }

    /**
     * Get day name in Japanese
     *
     * @param int $day_of_week 0=Sunday to 6=Saturday
     * @return string
     */
    private static function getDayName($day_of_week) {
        $day_names = [
            0 => '日曜日',
            1 => '月曜日',
            2 => '火曜日',
            3 => '水曜日',
            4 => '木曜日',
            5 => '金曜日',
            6 => '土曜日'
        ];
        return $day_names[$day_of_week] ?? '';
    }

    /**
     * Get background color for different day types
     *
     * @param string $color_class
     * @return string CSS color value
     */
    private static function getBackgroundColor($color_class) {
        $colors = [
            'holiday' => '#FFE7EC',  // Pink for holidays
            'sunday' => '#FFE7EC',   // Pink for Sundays
            'saturday' => '#E7F2FF', // Light blue for Saturdays
            'weekday' => '#F0F9F0',  // Light green for weekdays (default)
            'unknown' => '#F8F9FA'   // Light gray for unknown
        ];
        return $colors[$color_class] ?? $colors['weekday'];
    }

    /**
     * Check if a date qualifies for eve surcharges
     * (the night before Saturday, Sunday, or Holiday)
     *
     * @param string $stay_date The date of the stay (Y-m-d)
     * @param string $timezone Timezone for calculations
     * @return array Eve surcharge information
     */
    public static function checkEveSurcharges($stay_date, $timezone = 'Asia/Tokyo') {
        try {
            $stay_dt = new \DateTime($stay_date, new \DateTimeZone($timezone));
            $next_day = clone $stay_dt;
            $next_day->add(new \DateInterval('P1D'));
            $next_date = $next_day->format('Y-m-d');
        } catch (\Exception $e) {
            return [
                'has_surcharge' => false,
                'surcharge_type' => null,
                'next_day_info' => null,
                'error' => 'Invalid date format'
            ];
        }

        $next_day_info = self::classifyDate($next_date, $timezone);

        $surcharge_type = null;
        $has_surcharge = false;

        if ($next_day_info['is_holiday']) {
            $surcharge_type = 'holiday_eve';
            $has_surcharge = true;
        } elseif ($next_day_info['is_sunday']) {
            $surcharge_type = 'sunday_eve';
            $has_surcharge = true;
        } elseif ($next_day_info['is_saturday']) {
            $surcharge_type = 'saturday_eve';
            $has_surcharge = true;
        }

        return [
            'has_surcharge' => $has_surcharge,
            'surcharge_type' => $surcharge_type,
            'next_day_info' => $next_day_info,
            'stay_date' => $stay_date,
            'next_date' => $next_date
        ];
    }

    /**
     * Get CSS classes for availability status combined with day type
     *
     * @param string $date Date in Y-m-d format
     * @param string $availability_status available|full|pending|blackout
     * @param string $timezone Timezone for calculations
     * @return array Combined CSS classes and information
     */
    public static function getCombinedClasses($date, $availability_status, $timezone = 'Asia/Tokyo') {
        $day_info = self::classifyDate($date, $timezone);
        $classes = $day_info['css_classes'];

        // Add availability status classes
        switch ($availability_status) {
            case 'available':
                $classes[] = 'mcs-day--available';
                break;
            case 'full':
                $classes[] = 'mcs-day--full';
                // Override day type colors for full days
                $day_info['background_color'] = '#CFD4DA'; // Gray
                break;
            case 'pending':
                $classes[] = 'mcs-day--pending';
                $day_info['background_color'] = '#FFF3CD'; // Yellow
                break;
            case 'blackout':
                $classes[] = 'mcs-day--blackout';
                $day_info['background_color'] = '#CFD4DA'; // Gray
                $classes[] = 'mcs-day--disabled';
                break;
        }

        // Add past date class if applicable
        $today = new \DateTime('today', new \DateTimeZone($timezone));
        $date_obj = new \DateTime($date, new \DateTimeZone($timezone));
        if ($date_obj < $today) {
            $classes[] = 'mcs-day--past';
        }

        return array_merge($day_info, [
            'availability_status' => $availability_status,
            'css_classes' => array_unique($classes),
            'is_clickable' => !in_array($availability_status, ['full', 'blackout']) && $date_obj >= $today
        ]);
    }

    /**
     * Generate CSS for day type colors
     *
     * @return string CSS rules for day type colors
     */
    public static function generateDayTypeCSS() {
        return '
        /* Day type background colors */
        .mcs-day--holiday,
        .mcs-day--sun {
            background-color: #FFE7EC !important; /* Pink for holidays and Sundays */
        }

        .mcs-day--sat {
            background-color: #E7F2FF !important; /* Light blue for Saturdays */
        }

        .mcs-day--weekday {
            background-color: #F0F9F0 !important; /* Light green for weekdays */
        }

        /* Override colors for special states */
        .mcs-day--full,
        .mcs-day--blackout {
            background-color: #CFD4DA !important; /* Gray for unavailable */
            cursor: not-allowed !important;
            pointer-events: none !important;
        }

        .mcs-day--pending {
            background-color: #FFF3CD !important; /* Yellow for pending */
        }

        .mcs-day--past {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }

        /* Ensure clickable days have proper cursor */
        .mcs-day:not(.mcs-day--disabled):not(.mcs-day--past):not(.mcs-day--full):not(.mcs-day--blackout) {
            cursor: pointer;
        }
        ';
    }

    /**
     * Batch classify multiple dates (for performance)
     *
     * @param array $dates Array of dates in Y-m-d format
     * @param string $timezone Timezone for calculations
     * @return array Array of date => classification_info
     */
    public static function batchClassifyDates($dates, $timezone = 'Asia/Tokyo') {
        $results = [];
        foreach ($dates as $date) {
            $results[$date] = self::classifyDate($date, $timezone);
        }
        return $results;
    }

    /**
     * Get month date range for batch processing
     *
     * @param int $year
     * @param int $month
     * @param string $timezone
     * @return array Array of dates in the month
     */
    public static function getMonthDateRange($year, $month, $timezone = 'Asia/Tokyo') {
        $dates = [];
        try {
            $first_day = new \DateTime("$year-$month-01", new \DateTimeZone($timezone));
            $last_day = new \DateTime($first_day->format('Y-m-t'), new \DateTimeZone($timezone));

            $current = clone $first_day;
            while ($current <= $last_day) {
                $dates[] = $current->format('Y-m-d');
                $current->add(new \DateInterval('P1D'));
            }
        } catch (\Exception $e) {
            // Return empty array on error
        }
        return $dates;
    }
}