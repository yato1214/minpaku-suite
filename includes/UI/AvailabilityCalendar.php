<?php
/**
 * Availability Calendar UI Component
 * Displays property availability with AJAX calendar interface
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class AvailabilityCalendar {

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function getInstance(): AvailabilityCalendar {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the calendar component
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        add_shortcode('minpaku_calendar', [$this, 'render_calendar_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_mcs_get_availability', [$this, 'ajax_get_availability']);
        add_action('wp_ajax_nopriv_mcs_get_availability', [$this, 'ajax_get_availability']);
    }

    /**
     * Render calendar shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Calendar HTML
     */
    public function render_calendar_shortcode($atts): string {
        $atts = shortcode_atts([
            'property_id' => 0,
            'months' => 2,
            'start_date' => '',
            'theme' => 'default',
            'show_rates' => false,
            'show_legend' => true,
            'min_stay' => 1,
            'max_stay' => 30,
            'class' => ''
        ], $atts, 'minpaku_calendar');

        $property_id = intval($atts['property_id']);
        if (!$property_id) {
            return '<div class="mcs-calendar-error">' . __('Property ID is required', 'minpaku-suite') . '</div>';
        }

        // Verify property exists
        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'property') {
            return '<div class="mcs-calendar-error">' . __('Property not found', 'minpaku-suite') . '</div>';
        }

        // Generate unique calendar ID
        $calendar_id = 'mcs-calendar-' . $property_id . '-' . uniqid();

        // Prepare calendar data
        $calendar_data = [
            'property_id' => $property_id,
            'months' => intval($atts['months']),
            'start_date' => $atts['start_date'] ?: date('Y-m-01'),
            'theme' => sanitize_text_field($atts['theme']),
            'show_rates' => filter_var($atts['show_rates'], FILTER_VALIDATE_BOOLEAN),
            'show_legend' => filter_var($atts['show_legend'], FILTER_VALIDATE_BOOLEAN),
            'min_stay' => intval($atts['min_stay']),
            'max_stay' => intval($atts['max_stay']),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mcs_calendar_nonce')
        ];

        // Load template
        ob_start();
        $this->load_template('calendar', [
            'calendar_id' => $calendar_id,
            'calendar_data' => $calendar_data,
            'property' => $property,
            'class' => sanitize_html_class($atts['class'])
        ]);
        return ob_get_clean();
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts(): void {
        // Only enqueue on pages that have the calendar shortcode
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'minpaku_calendar')) {
            return;
        }

        wp_enqueue_script(
            'mcs-calendar',
            plugins_url('assets/js/calendar.js', dirname(dirname(__FILE__))),
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'mcs-calendar',
            plugins_url('assets/css/calendar.css', dirname(dirname(__FILE__))),
            [],
            '1.0.0'
        );

        // Localize script with calendar data
        wp_localize_script('mcs-calendar', 'mcsCalendar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mcs_calendar_nonce'),
            'strings' => [
                'loading' => __('Loading availability...', 'minpaku-suite'),
                'error' => __('Error loading calendar data', 'minpaku-suite'),
                'available' => __('Available', 'minpaku-suite'),
                'booked' => __('Booked', 'minpaku-suite'),
                'blocked' => __('Blocked', 'minpaku-suite'),
                'checkin' => __('Check-in', 'minpaku-suite'),
                'checkout' => __('Check-out', 'minpaku-suite'),
                'selectCheckin' => __('Select check-in date', 'minpaku-suite'),
                'selectCheckout' => __('Select check-out date', 'minpaku-suite'),
                'invalidSelection' => __('Invalid date selection', 'minpaku-suite'),
                'minStayError' => __('Minimum stay is %d nights', 'minpaku-suite'),
                'maxStayError' => __('Maximum stay is %d nights', 'minpaku-suite'),
                'unavailableDates' => __('Selected dates are not available', 'minpaku-suite')
            ],
            'dateFormat' => get_option('date_format', 'F j, Y'),
            'firstDayOfWeek' => get_option('start_of_week', 0),
            'months' => [
                __('January', 'minpaku-suite'),
                __('February', 'minpaku-suite'),
                __('March', 'minpaku-suite'),
                __('April', 'minpaku-suite'),
                __('May', 'minpaku-suite'),
                __('June', 'minpaku-suite'),
                __('July', 'minpaku-suite'),
                __('August', 'minpaku-suite'),
                __('September', 'minpaku-suite'),
                __('October', 'minpaku-suite'),
                __('November', 'minpaku-suite'),
                __('December', 'minpaku-suite')
            ],
            'daysShort' => [
                __('Sun', 'minpaku-suite'),
                __('Mon', 'minpaku-suite'),
                __('Tue', 'minpaku-suite'),
                __('Wed', 'minpaku-suite'),
                __('Thu', 'minpaku-suite'),
                __('Fri', 'minpaku-suite'),
                __('Sat', 'minpaku-suite')
            ]
        ]);
    }

    /**
     * AJAX handler for getting availability data
     */
    public function ajax_get_availability(): void {
        check_ajax_referer('mcs_calendar_nonce', 'nonce');

        $property_id = intval($_GET['property_id'] ?? 0);
        $start_date = sanitize_text_field($_GET['start_date'] ?? '');
        $end_date = sanitize_text_field($_GET['end_date'] ?? '');

        if (!$property_id) {
            wp_send_json_error(['message' => __('Property ID is required', 'minpaku-suite')]);
        }

        if (!$start_date || !$end_date) {
            wp_send_json_error(['message' => __('Start and end dates are required', 'minpaku-suite')]);
        }

        // Validate dates
        $start = DateTime::createFromFormat('Y-m-d', $start_date);
        $end = DateTime::createFromFormat('Y-m-d', $end_date);

        if (!$start || !$end || $start > $end) {
            wp_send_json_error(['message' => __('Invalid date range', 'minpaku-suite')]);
        }

        // Get availability data
        $availability = $this->get_property_availability($property_id, $start_date, $end_date);

        wp_send_json_success([
            'availability' => $availability,
            'property_id' => $property_id,
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
    }

    /**
     * Get property availability for date range
     *
     * @param int $property_id
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    private function get_property_availability(int $property_id, string $start_date, string $end_date): array {
        $availability = [];

        // Get booked slots from post meta
        $booked_slots = get_post_meta($property_id, 'mcs_booked_slots', true);
        if (!is_array($booked_slots)) {
            $booked_slots = [];
        }

        // Get blocked dates from post meta
        $blocked_dates = get_post_meta($property_id, 'mcs_blocked_dates', true);
        if (!is_array($blocked_dates)) {
            $blocked_dates = [];
        }

        // Get property settings
        $property_settings = [
            'min_stay' => get_post_meta($property_id, 'mcs_min_stay', true) ?: 1,
            'max_stay' => get_post_meta($property_id, 'mcs_max_stay', true) ?: 30,
            'base_rate' => get_post_meta($property_id, 'mcs_base_rate', true) ?: 100,
            'check_in_day' => get_post_meta($property_id, 'mcs_check_in_day', true),
            'check_out_day' => get_post_meta($property_id, 'mcs_check_out_day', true)
        ];

        // Generate availability for each day in range
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);

        while ($current <= $end) {
            $date_str = $current->format('Y-m-d');
            $timestamp = $current->getTimestamp();

            // Check if date is booked
            $is_booked = false;
            foreach ($booked_slots as $slot) {
                $slot_start = $slot[0] ?? 0;
                $slot_end = $slot[1] ?? 0;

                if ($timestamp >= $slot_start && $timestamp < $slot_end) {
                    $is_booked = true;
                    break;
                }
            }

            // Check if date is blocked
            $is_blocked = in_array($date_str, $blocked_dates);

            // Determine availability status
            $status = 'available';
            if ($is_blocked) {
                $status = 'blocked';
            } elseif ($is_booked) {
                $status = 'booked';
            }

            // Get rate for this date (if enabled)
            $rate = null;
            if ($status === 'available') {
                $rate = $this->calculate_date_rate($property_id, $date_str, $property_settings['base_rate']);
            }

            $availability[$date_str] = [
                'status' => $status,
                'rate' => $rate,
                'min_stay' => $property_settings['min_stay'],
                'max_stay' => $property_settings['max_stay'],
                'check_in_allowed' => $this->is_check_in_allowed($current, $property_settings),
                'check_out_allowed' => $this->is_check_out_allowed($current, $property_settings),
                'day_of_week' => $current->format('w')
            ];

            $current->add(new DateInterval('P1D'));
        }

        return $availability;
    }

    /**
     * Calculate rate for specific date
     *
     * @param int $property_id
     * @param string $date
     * @param float $base_rate
     * @return float|null
     */
    private function calculate_date_rate(int $property_id, string $date, float $base_rate): ?float {
        // Use RateResolver if available
        if (class_exists('RateResolver')) {
            $rate_resolver = new RateResolver();
            $rate_resolver->configure([
                'base_rate' => $base_rate,
                'currency' => 'USD'
            ]);

            // Calculate rate for single night
            $check_in = $date;
            $check_out = date('Y-m-d', strtotime($date . ' +1 day'));

            $booking_data = [
                'start_date' => $check_in,
                'end_date' => $check_out,
                'property_id' => $property_id,
                'guests' => 1
            ];

            $rate_result = $rate_resolver->resolveRate($booking_data);

            if ($rate_result['success'] ?? false) {
                return $rate_result['total_rate'] ?? $base_rate;
            }
        }

        // Fallback to base rate with simple day-of-week adjustment
        $day_of_week = date('w', strtotime($date));
        $weekend_multiplier = in_array($day_of_week, [5, 6, 0]) ? 1.2 : 1.0; // Fri, Sat, Sun

        return $base_rate * $weekend_multiplier;
    }

    /**
     * Check if check-in is allowed on this day
     *
     * @param DateTime $date
     * @param array $settings
     * @return bool
     */
    private function is_check_in_allowed(DateTime $date, array $settings): bool {
        $check_in_day = $settings['check_in_day'] ?? '';

        if (empty($check_in_day) || $check_in_day === 'any') {
            return true;
        }

        $day_of_week = $date->format('w');
        $allowed_days = is_array($check_in_day) ? $check_in_day : [$check_in_day];

        return in_array($day_of_week, $allowed_days);
    }

    /**
     * Check if check-out is allowed on this day
     *
     * @param DateTime $date
     * @param array $settings
     * @return bool
     */
    private function is_check_out_allowed(DateTime $date, array $settings): bool {
        $check_out_day = $settings['check_out_day'] ?? '';

        if (empty($check_out_day) || $check_out_day === 'any') {
            return true;
        }

        $day_of_week = $date->format('w');
        $allowed_days = is_array($check_out_day) ? $check_out_day : [$check_out_day];

        return in_array($day_of_week, $allowed_days);
    }

    /**
     * Load template file
     *
     * @param string $template_name
     * @param array $args
     */
    private function load_template(string $template_name, array $args = []): void {
        extract($args);

        // Look for template in theme first, then plugin
        $template_paths = [
            get_stylesheet_directory() . '/minpaku-suite/' . $template_name . '.php',
            get_template_directory() . '/minpaku-suite/' . $template_name . '.php',
            dirname(dirname(dirname(__FILE__))) . '/templates/' . $template_name . '.php'
        ];

        foreach ($template_paths as $path) {
            if (file_exists($path)) {
                include $path;
                return;
            }
        }

        // Fallback to default template
        $this->render_default_calendar($args);
    }

    /**
     * Render default calendar template
     *
     * @param array $args
     */
    private function render_default_calendar(array $args): void {
        $calendar_id = $args['calendar_id'] ?? 'mcs-calendar';
        $calendar_data = $args['calendar_data'] ?? [];
        $property = $args['property'] ?? null;
        $class = $args['class'] ?? '';

        echo '<div class="mcs-calendar-container ' . esc_attr($class) . '" id="' . esc_attr($calendar_id) . '">';

        // Calendar header
        echo '<div class="mcs-calendar-header">';
        if ($property) {
            echo '<h3 class="mcs-calendar-title">' . esc_html($property->post_title) . ' - ' . __('Availability', 'minpaku-suite') . '</h3>';
        }
        echo '</div>';

        // Loading indicator
        echo '<div class="mcs-calendar-loading">';
        echo '<div class="mcs-spinner"></div>';
        echo '<p>' . __('Loading availability...', 'minpaku-suite') . '</p>';
        echo '</div>';

        // Calendar content (populated by JavaScript)
        echo '<div class="mcs-calendar-content"></div>';

        // Legend
        if ($calendar_data['show_legend'] ?? true) {
            echo '<div class="mcs-calendar-legend">';
            echo '<div class="mcs-legend-item">';
            echo '<span class="mcs-legend-color mcs-available"></span>';
            echo '<span class="mcs-legend-label">' . __('Available', 'minpaku-suite') . '</span>';
            echo '</div>';
            echo '<div class="mcs-legend-item">';
            echo '<span class="mcs-legend-color mcs-booked"></span>';
            echo '<span class="mcs-legend-label">' . __('Booked', 'minpaku-suite') . '</span>';
            echo '</div>';
            echo '<div class="mcs-legend-item">';
            echo '<span class="mcs-legend-color mcs-blocked"></span>';
            echo '<span class="mcs-legend-label">' . __('Blocked', 'minpaku-suite') . '</span>';
            echo '</div>';
            echo '</div>';
        }

        // Selection info
        echo '<div class="mcs-calendar-selection">';
        echo '<div class="mcs-selection-dates">';
        echo '<span class="mcs-checkin-date"></span>';
        echo '<span class="mcs-checkout-date"></span>';
        echo '</div>';
        echo '<div class="mcs-selection-actions">';
        echo '<button type="button" class="mcs-clear-selection">' . __('Clear Selection', 'minpaku-suite') . '</button>';
        echo '<button type="button" class="mcs-get-quote">' . __('Get Quote', 'minpaku-suite') . '</button>';
        echo '</div>';
        echo '</div>';

        // Store calendar data as JSON
        echo '<script type="application/json" class="mcs-calendar-data">';
        echo json_encode($calendar_data);
        echo '</script>';

        echo '</div>'; // .mcs-calendar-container
    }

    /**
     * Get calendar HTML for specific month
     *
     * @param int $property_id
     * @param string $month YYYY-MM format
     * @param array $availability
     * @param array $options
     * @return string
     */
    public function get_month_html(int $property_id, string $month, array $availability, array $options = []): string {
        $date = new DateTime($month . '-01');
        $month_name = $date->format('F Y');
        $days_in_month = $date->format('t');
        $first_day_of_week = intval($date->format('w'));

        $show_rates = $options['show_rates'] ?? false;

        $html = '<div class="mcs-calendar-month" data-month="' . esc_attr($month) . '">';
        $html .= '<div class="mcs-month-header">';
        $html .= '<h4 class="mcs-month-title">' . esc_html($month_name) . '</h4>';
        $html .= '</div>';

        $html .= '<div class="mcs-month-grid">';

        // Day headers
        $html .= '<div class="mcs-day-headers">';
        $days = [__('Sun', 'minpaku-suite'), __('Mon', 'minpaku-suite'), __('Tue', 'minpaku-suite'), __('Wed', 'minpaku-suite'), __('Thu', 'minpaku-suite'), __('Fri', 'minpaku-suite'), __('Sat', 'minpaku-suite')];
        foreach ($days as $day) {
            $html .= '<div class="mcs-day-header">' . esc_html($day) . '</div>';
        }
        $html .= '</div>';

        // Calendar days
        $html .= '<div class="mcs-calendar-days">';

        // Empty cells for days before month starts
        for ($i = 0; $i < $first_day_of_week; $i++) {
            $html .= '<div class="mcs-calendar-day mcs-empty"></div>';
        }

        // Days of the month
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date_str = $month . '-' . sprintf('%02d', $day);
            $day_availability = $availability[$date_str] ?? ['status' => 'available', 'rate' => null];

            $classes = ['mcs-calendar-day', 'mcs-' . $day_availability['status']];

            if ($day_availability['check_in_allowed'] ?? true) {
                $classes[] = 'mcs-checkin-allowed';
            }

            if ($day_availability['check_out_allowed'] ?? true) {
                $classes[] = 'mcs-checkout-allowed';
            }

            $html .= '<div class="' . implode(' ', $classes) . '" data-date="' . esc_attr($date_str) . '">';
            $html .= '<span class="mcs-day-number">' . $day . '</span>';

            if ($show_rates && $day_availability['rate']) {
                $html .= '<span class="mcs-day-rate">$' . number_format($day_availability['rate'], 0) . '</span>';
            }

            $html .= '</div>';
        }

        $html .= '</div>'; // .mcs-calendar-days
        $html .= '</div>'; // .mcs-month-grid
        $html .= '</div>'; // .mcs-calendar-month

        return $html;
    }

    /**
     * Validate date selection
     *
     * @param int $property_id
     * @param string $check_in
     * @param string $check_out
     * @return array
     */
    public function validate_date_selection(int $property_id, string $check_in, string $check_out): array {
        $errors = [];

        // Validate date format
        $check_in_date = DateTime::createFromFormat('Y-m-d', $check_in);
        $check_out_date = DateTime::createFromFormat('Y-m-d', $check_out);

        if (!$check_in_date || !$check_out_date) {
            $errors[] = __('Invalid date format', 'minpaku-suite');
            return ['valid' => false, 'errors' => $errors];
        }

        // Check if check-out is after check-in
        if ($check_out_date <= $check_in_date) {
            $errors[] = __('Check-out must be after check-in', 'minpaku-suite');
        }

        // Calculate nights
        $nights = $check_in_date->diff($check_out_date)->days;

        // Check minimum stay
        $min_stay = get_post_meta($property_id, 'mcs_min_stay', true) ?: 1;
        if ($nights < $min_stay) {
            $errors[] = sprintf(__('Minimum stay is %d nights', 'minpaku-suite'), $min_stay);
        }

        // Check maximum stay
        $max_stay = get_post_meta($property_id, 'mcs_max_stay', true) ?: 30;
        if ($nights > $max_stay) {
            $errors[] = sprintf(__('Maximum stay is %d nights', 'minpaku-suite'), $max_stay);
        }

        // Check availability for all nights
        $availability = $this->get_property_availability($property_id, $check_in, $check_out);

        $current = clone $check_in_date;
        while ($current < $check_out_date) {
            $date_str = $current->format('Y-m-d');
            $day_availability = $availability[$date_str] ?? ['status' => 'blocked'];

            if ($day_availability['status'] !== 'available') {
                $errors[] = sprintf(__('Date %s is not available', 'minpaku-suite'), $date_str);
                break;
            }

            $current->add(new DateInterval('P1D'));
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'nights' => $nights
        ];
    }
}