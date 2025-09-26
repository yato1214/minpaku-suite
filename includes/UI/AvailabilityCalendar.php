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
        add_action('wp_ajax_mcs_get_calendar_modal', [__CLASS__, 'ajax_get_calendar_modal']);
        add_action('wp_ajax_nopriv_mcs_get_calendar_modal', [__CLASS__, 'ajax_get_calendar_modal']);
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
            'currency' => 'JPY',
            'modal' => 'false'
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

        // Check if modal mode is requested
        if ($atts['modal'] === 'true') {
            return self::renderCalendarButton($property_id, $property->post_title, $atts);
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
     * Render calendar modal button
     */
    private static function renderCalendarButton(int $property_id, string $property_title, array $atts = []): string
    {
        $button_text = $atts['button_text'] ?? __('空室カレンダーを見る', 'minpaku-suite');
        $button_class = $atts['button_class'] ?? 'mcs-calendar-modal-button';
        $modal_id = 'mcs-calendar-modal-' . $property_id . '-' . uniqid();

        $output = '<div class="mcs-calendar-button-wrapper">';
        $output .= '<button class="' . esc_attr($button_class) . '" ';
        $output .= 'data-property-id="' . esc_attr($property_id) . '" ';
        $output .= 'data-property-title="' . esc_attr($property_title) . '" ';
        $output .= 'data-modal-id="' . esc_attr($modal_id) . '" ';
        $output .= 'data-months="' . esc_attr($atts['months'] ?? 2) . '" ';
        $output .= 'data-show-prices="' . esc_attr($atts['show_prices'] ?? 'true') . '" ';
        $output .= 'data-adults="' . esc_attr($atts['adults'] ?? 2) . '" ';
        $output .= 'data-children="' . esc_attr($atts['children'] ?? 0) . '" ';
        $output .= 'data-infants="' . esc_attr($atts['infants'] ?? 0) . '" ';
        $output .= 'data-currency="' . esc_attr($atts['currency'] ?? 'JPY') . '">';
        $output .= '<span class="mcs-calendar-icon">📅</span>';
        $output .= '<span class="mcs-calendar-text">' . esc_html($button_text) . '</span>';
        $output .= '</button>';
        $output .= '</div>';

        // Add modal container with unique ID
        $output .= '<div id="' . esc_attr($modal_id) . '" class="mcs-modal" style="display: none;">';
        $output .= '<div class="mcs-modal-overlay"></div>';
        $output .= '<div class="mcs-modal-content">';
        $output .= '<div class="mcs-modal-header">';
        $output .= '<h3 class="mcs-modal-title">' . esc_html($property_title) . ' - ' . __('空室カレンダー', 'minpaku-suite') . '</h3>';
        $output .= '<button class="mcs-modal-close">&times;</button>';
        $output .= '</div>';
        $output .= '<div class="mcs-modal-body" id="' . esc_attr($modal_id) . '-content">';
        $output .= '<div class="mcs-loading">カレンダーを読み込み中...</div>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';

        // Add JavaScript for modal functionality
        add_action('wp_footer', function() {
            // Localize script to provide AJAX URL
            wp_localize_script('jquery', 'mcs_ajax', array(
                'ajaxurl' => admin_url('admin-ajax.php')
            ));

            echo '<script type="text/javascript">';
            echo 'jQuery(document).ready(function($) {';
            echo '  $(".mcs-calendar-modal-button").click(function(e) {';
            echo '    e.preventDefault();';
            echo '    var button = $(this);';
            echo '    var propertyId = button.data("property-id");';
            echo '    var propertyTitle = button.data("property-title");';
            echo '    var modalId = button.data("modal-id");';
            echo '    var months = button.data("months");';
            echo '    var showPrices = button.data("show-prices");';
            echo '    ';
            echo '    console.log("Modal button clicked", {propertyId: propertyId, modalId: modalId, months: months});';
            echo '    ';
            echo '    $("#" + modalId).show();';
            echo '    $("body").addClass("mcs-modal-open");';
            echo '    ';
            echo '    // Load calendar content via AJAX';
            echo '    var ajaxUrl = (typeof mcs_ajax !== "undefined") ? mcs_ajax.ajaxurl : (typeof ajaxurl !== "undefined" ? ajaxurl : "/wp-admin/admin-ajax.php");';
            echo '    var params = {';
            echo '      action: "mcs_get_calendar_modal",';
            echo '      property_id: propertyId,';
            echo '      months: months,';
            echo '      show_prices: showPrices';
            echo '    };';
            echo '    ';
            echo '    console.log("Sending AJAX request to", ajaxUrl, params);';
            echo '    ';
            echo '    $.post(ajaxUrl, params, function(response) {';
            echo '      console.log("AJAX response", response);';
            echo '      if (response.success) {';
            echo '        $("#" + modalId + "-content").html(response.data);';
            echo '      } else {';
            echo '        $("#" + modalId + "-content").html("<p>カレンダーの読み込みに失敗しました。</p>");';
            echo '      }';
            echo '    }).fail(function(xhr, status, error) {';
            echo '      console.error("AJAX failed", xhr, status, error);';
            echo '      $("#" + modalId + "-content").html("<p>カレンダーの読み込みに失敗しました。ネットワークエラーです。</p>");';
            echo '    });';
            echo '  });';
            echo '  ';
            echo '  $(".mcs-modal-close, .mcs-modal-overlay").click(function() {';
            echo '    $(this).closest(".mcs-modal").hide();';
            echo '    $("body").removeClass("mcs-modal-open");';
            echo '  });';
            echo '  ';
            echo '  // ESC key to close modal';
            echo '  $(document).keydown(function(e) {';
            echo '    if (e.keyCode === 27) {';
            echo '      $(".mcs-modal:visible").hide();';
            echo '      $("body").removeClass("mcs-modal-open");';
            echo '    }';
            echo '  });';
            echo '});';
            echo '</script>';

            // Add modal styles
            echo '<style>';
            echo '.mcs-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; }';
            echo '.mcs-modal-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); }';
            echo '.mcs-modal-content { position: relative; max-width: 90vw; max-height: 90vh; margin: 5vh auto; background: white; border-radius: 8px; overflow: hidden; }';
            echo '.mcs-modal-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }';
            echo '.mcs-modal-title { margin: 0; font-size: 18px; }';
            echo '.mcs-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; padding: 0; width: 30px; height: 30px; }';
            echo '.mcs-modal-body { padding: 20px; max-height: calc(90vh - 120px); overflow-y: auto; }';
            echo '.mcs-calendar-modal-button { background: #667eea; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }';
            echo '.mcs-calendar-modal-button:hover { background: #5a67d8; }';
            echo '.mcs-calendar-icon { font-size: 16px; }';
            echo 'body.mcs-modal-open { overflow: hidden; }';
            echo '.mcs-loading { text-align: center; padding: 40px; color: #666; }';
            echo '</style>';
        }, 100);

        return $output;
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
     * Get price for a specific day (display price = accommodation rate + cleaning fee)
     */
    private static function getPriceForDay(int $property_id, string $date_str): string
    {
        // Get unified accommodation rate
        $accommodation_rate = (float) get_post_meta($property_id, 'accommodation_rate', true);

        // Fallback to legacy test fields if new field is not set
        if ($accommodation_rate == 0) {
            $test_base_rate = (float) get_post_meta($property_id, 'test_base_rate', true);
            $base_price_test = (float) get_post_meta($property_id, 'base_price_test', true);
            $accommodation_rate = $test_base_rate ?: ($base_price_test ?: 15000.0);
        }

        // Get cleaning fee
        $cleaning_fee = (float) get_post_meta($property_id, 'cleaning_fee', true);

        // Fallback to legacy test field if new field is not set
        if ($cleaning_fee == 0) {
            $cleaning_fee = (float) get_post_meta($property_id, 'test_cleaning_fee', true) ?: 0.0;
        }

        // Calculate display price = accommodation rate + cleaning fee
        $display_price = $accommodation_rate + $cleaning_fee;

        if ($display_price > 0) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log("[Calendar] Property $property_id display price: ¥$display_price (accommodation: ¥$accommodation_rate + cleaning: ¥$cleaning_fee)");
            }
            return '¥' . number_format($display_price);
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
            // Check if we need modal functionality
            global $post;
            $needs_modal = false;

            if ($post && has_shortcode($post->post_content, 'mcs_availability')) {
                // Parse shortcodes to check for modal="true"
                $pattern = get_shortcode_regex(['mcs_availability']);
                preg_match_all('/' . $pattern . '/s', $post->post_content, $matches);

                foreach ($matches[3] as $attrs) {
                    $atts = shortcode_parse_atts($attrs);
                    if (isset($atts['modal']) && $atts['modal'] === 'true') {
                        $needs_modal = true;
                        break;
                    }
                }
            }

            if ($needs_modal) {
                // Enqueue modal calendar CSS
                $css_file = MINPAKU_SUITE_PLUGIN_URL . 'assets/css/calendar-modal.css';
                $css_version = file_exists(MINPAKU_SUITE_PLUGIN_DIR . 'assets/css/calendar-modal.css')
                    ? filemtime(MINPAKU_SUITE_PLUGIN_DIR . 'assets/css/calendar-modal.css')
                    : MINPAKU_SUITE_VERSION;
                wp_enqueue_style('mcs-calendar-modal', $css_file, [], $css_version);

                // Enqueue modal calendar JavaScript
                $js_file = MINPAKU_SUITE_PLUGIN_URL . 'assets/js/calendar-modal.js';
                $js_version = file_exists(MINPAKU_SUITE_PLUGIN_DIR . 'assets/js/calendar-modal.js')
                    ? filemtime(MINPAKU_SUITE_PLUGIN_DIR . 'assets/js/calendar-modal.js')
                    : MINPAKU_SUITE_VERSION;
                wp_enqueue_script('mcs-calendar-modal', $js_file, ['jquery'], $js_version, true);

                // Localize script for AJAX
                wp_localize_script('mcs-calendar-modal', 'mcsCalendarModal', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mcs_calendar_modal'),
                    'debug' => defined('WP_DEBUG') && WP_DEBUG
                ]);
            }

            // Enqueue admin calendar for backend functionality
            $admin_js_file = MINPAKU_SUITE_PLUGIN_URL . 'assets/admin-calendar.js';
            if (file_exists(MINPAKU_SUITE_PLUGIN_DIR . 'assets/admin-calendar.js')) {
                $admin_js_version = filemtime(MINPAKU_SUITE_PLUGIN_DIR . 'assets/admin-calendar.js');
                wp_enqueue_script('mcs-admin-calendar', $admin_js_file, ['jquery'], $admin_js_version, true);

                // Pass admin URL to JavaScript
                wp_localize_script('mcs-admin-calendar', 'minpakuAdmin', [
                    'bookingUrl' => admin_url('post-new.php?post_type=mcs_booking')
                ]);
            }
        }
    }

    /**
     * AJAX handler for modal calendar
     */
    public static function ajax_get_calendar_modal(): void
    {
        // Verify nonce for security
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'mcs_calendar_modal')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        $property_id = intval($_POST['property_id'] ?? 0);
        $months = max(1, min(12, intval($_POST['months'] ?? 2)));
        $show_prices = ($_POST['show_prices'] ?? 'true') === 'true';

        if (!$property_id) {
            wp_send_json_error('Invalid property ID');
            return;
        }

        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'mcs_property') {
            wp_send_json_error('Property not found');
            return;
        }

        try {
            $atts = [
                'months' => $months,
                'show_prices' => $show_prices ? 'true' : 'false',
                'adults' => intval($_POST['adults'] ?? 2),
                'children' => intval($_POST['children'] ?? 0),
                'infants' => intval($_POST['infants'] ?? 0),
                'currency' => sanitize_text_field($_POST['currency'] ?? 'JPY')
            ];

            $calendar_html = self::renderCalendar($property_id, $months, $atts);
            wp_send_json_success($calendar_html);
        } catch (Exception $e) {
            error_log('Modal calendar error: ' . $e->getMessage());
            wp_send_json_error('カレンダーの読み込みに失敗しました。');
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