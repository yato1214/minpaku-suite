<?php
/**
 * Shortcodes for Embedding Content
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class MPC_Shortcodes_Embed {

    public static function init() {
        add_shortcode('minpaku_connector', array(__CLASS__, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }

    /**
     * Main shortcode handler
     */
    public static function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'properties',
            'property_id' => '',
            'limit' => '12',
            'columns' => '3',
            'months' => '2',
            'start_date' => '',
            'class' => ''
        ), $atts, 'minpaku_connector');

        // Log shortcode usage for debugging
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Shortcode called: type=' . $atts['type'] . ', property_id=' . $atts['property_id']);
        }

        // Check if API is configured
        try {
            $api = new \MinpakuConnector\Client\MPC_Client_Api();
            if (!$api->is_configured()) {
                self::log_error('API not configured', 'shortcode');
                return self::render_error_notice(
                    __('Connection not configured', 'wp-minpaku-connector'),
                    __('Please configure the Minpaku Connector in Settings > Minpaku Connector.', 'wp-minpaku-connector'),
                    'configuration'
                );
            }
        } catch (Exception $e) {
            self::log_error('API initialization failed: ' . $e->getMessage(), 'shortcode');
            return self::render_error_notice(
                __('System error', 'wp-minpaku-connector'),
                __('Unable to initialize connector. Please check your configuration.', 'wp-minpaku-connector'),
                'system'
            );
        }

        // Validate shortcode type
        $valid_types = array('properties', 'availability', 'property');
        if (!in_array($atts['type'], $valid_types)) {
            self::log_error('Invalid shortcode type: ' . $atts['type'], 'shortcode');
            return self::render_error_notice(
                __('Invalid shortcode type', 'wp-minpaku-connector'),
                sprintf(__('Valid types are: %s', 'wp-minpaku-connector'), implode(', ', $valid_types)),
                'validation'
            );
        }

        // Route to appropriate handler with error handling
        try {
            switch ($atts['type']) {
                case 'properties':
                    return self::render_properties($atts, $api);

                case 'availability':
                    return self::render_availability($atts, $api);

                case 'property':
                    return self::render_property($atts, $api);
            }
        } catch (Exception $e) {
            self::log_error('Shortcode rendering failed: ' . $e->getMessage(), 'shortcode');
            return self::render_error_notice(
                __('Display error', 'wp-minpaku-connector'),
                __('Unable to display content. Please try again later.', 'wp-minpaku-connector'),
                'display'
            );
        }
    }

    /**
     * Render user-friendly error notice
     */
    private static function render_error_notice($title, $message, $type = 'general') {
        $classes = 'wmc-error wmc-error-' . esc_attr($type);

        $output = '<div class="' . $classes . '">';
        $output .= '<strong>' . esc_html($title) . '</strong>';
        if ($message) {
            $output .= '<br><span class="wmc-error-message">' . esc_html($message) . '</span>';
        }

        // Add help link for admin users
        if (current_user_can('manage_options') && $type === 'configuration') {
            $settings_url = admin_url('options-general.php?page=wp-minpaku-connector');
            $output .= '<br><a href="' . esc_url($settings_url) . '" class="wmc-error-link">' .
                      esc_html__('Go to Settings', 'wp-minpaku-connector') . '</a>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Log error messages
     */
    private static function log_error($message, $context = 'general') {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] [' . $context . '] ' . $message);
        }
    }

    /**
     * Render properties listing
     */
    private static function render_properties($atts, $api) {
        // Validate and sanitize input
        $limit = max(1, min(50, intval($atts['limit']))); // Limit between 1-50
        $columns = max(1, min(6, intval($atts['columns']))); // Columns between 1-6
        $css_class = sanitize_html_class($atts['class']);

        self::log_error("Fetching properties: limit=$limit, columns=$columns", 'shortcode');

        try {
            $response = $api->get_properties(array(
                'per_page' => $limit,
                'page' => 1
            ));

            if (!$response['success']) {
                self::log_error('Properties API failed: ' . $response['message'], 'shortcode');

                // Provide user-friendly error based on the issue
                if (strpos($response['message'], '401') !== false || strpos($response['message'], '403') !== false) {
                    return self::render_error_notice(
                        __('Access denied', 'wp-minpaku-connector'),
                        __('Unable to access property listings. Please check your connection settings.', 'wp-minpaku-connector'),
                        'access'
                    );
                } elseif (strpos($response['message'], 'timeout') !== false) {
                    return self::render_error_notice(
                        __('Connection timeout', 'wp-minpaku-connector'),
                        __('The portal is taking too long to respond. Please try again later.', 'wp-minpaku-connector'),
                        'timeout'
                    );
                } else {
                    return self::render_error_notice(
                        __('Unable to load properties', 'wp-minpaku-connector'),
                        __('There was a problem connecting to the portal. Please try again later.', 'wp-minpaku-connector'),
                        'api'
                    );
                }
            }

            $properties = $response['data'];
            if (empty($properties)) {
                self::log_error('No properties returned from API', 'shortcode');
                return '<div class="wmc-no-content">' .
                       '<p><strong>' . esc_html__('No properties available', 'wp-minpaku-connector') . '</strong></p>' .
                       '<p>' . esc_html__('There are currently no properties to display.', 'wp-minpaku-connector') . '</p>' .
                       '</div>';
            }

            self::log_error('Successfully loaded ' . count($properties) . ' properties', 'shortcode');

            // Semantic HTML structure
            $output = '<section class="wmc-properties wmc-grid wmc-columns-' . esc_attr($columns) . ' ' . esc_attr($css_class) . '" aria-label="' . esc_attr__('Property listings', 'wp-minpaku-connector') . '">';
            $output .= '<h3 class="screen-reader-text">' . esc_html__('Available Properties', 'wp-minpaku-connector') . '</h3>';

            foreach ($properties as $property) {
                $output .= self::render_property_card($property);
            }

            $output .= '</section>';

            return $output;

        } catch (Exception $e) {
            self::log_error('Properties rendering exception: ' . $e->getMessage(), 'shortcode');
            return self::render_error_notice(
                __('System error', 'wp-minpaku-connector'),
                __('Unable to display properties due to a technical issue.', 'wp-minpaku-connector'),
                'system'
            );
        }
    }

    /**
     * Render availability calendar
     */
    private static function render_availability($atts, $api) {
        // Use the new Calendar class with proper attribute mapping
        if (class_exists('MinpakuConnector\Shortcodes\MPC_Shortcodes_Calendar')) {
            // Map attributes from main shortcode to calendar-specific format
            $calendar_atts = array(
                'property_id' => $atts['property_id'] ?? '',
                'months' => $atts['months'] ?? 2,
                'show_prices' => $atts['show_prices'] ?? 'true',
                'adults' => $atts['adults'] ?? 2,
                'children' => $atts['children'] ?? 0,
                'infants' => $atts['infants'] ?? 0,
                'currency' => $atts['currency'] ?? 'JPY'
            );

            return \MinpakuConnector\Shortcodes\MPC_Shortcodes_Calendar::render_calendar($calendar_atts);
        }

        // Fallback error
        return self::render_error_notice(
            __('Calendar not available', 'wp-minpaku-connector'),
            __('The calendar component is not loaded. Please check your plugin installation.', 'wp-minpaku-connector'),
            'system'
        );
    }

    /**
     * Render single property details
     */
    private static function render_property($atts, $api) {
        $property_id = intval($atts['property_id']);
        $css_class = sanitize_html_class($atts['class']);

        if (empty($property_id)) {
            return '<div class="wmc-error">' . esc_html__('Property ID is required.', 'wp-minpaku-connector') . '</div>';
        }

        $response = $api->get_property($property_id);

        if (!$response['success']) {
            return '<div class="wmc-error">' . esc_html($response['message']) . '</div>';
        }

        $property = $response['data'];

        $output = '<div class="wmc-property-details ' . esc_attr($css_class) . '">';
        $output .= self::render_property_full($property);
        $output .= '</div>';

        return $output;
    }

    /**
     * Render property card for grid view
     */
    private static function render_property_card($property) {
        $output = '<div class="wmc-property-card">';

        // Thumbnail
        if (!empty($property['thumbnail'])) {
            $output .= '<div class="wmc-property-image">';
            $output .= '<img src="' . esc_url($property['thumbnail']) . '" alt="' . esc_attr($property['title']) . '">';
            $output .= '</div>';
        }

        // Content
        $output .= '<div class="wmc-property-content">';
        $output .= '<h3 class="wmc-property-title">' . esc_html($property['title']) . '</h3>';

        if (!empty($property['excerpt'])) {
            $output .= '<p class="wmc-property-excerpt">' . esc_html($property['excerpt']) . '</p>';
        }

        // Meta information
        $output .= '<div class="wmc-property-meta">';
        if ($property['meta']['capacity'] > 0) {
            $output .= '<span class="wmc-meta-item">';
            $output .= '<span class="wmc-meta-label">' . esc_html__('Capacity:', 'wp-minpaku-connector') . '</span> ';
            $output .= '<span class="wmc-meta-value">' . esc_html($property['meta']['capacity']) . '</span>';
            $output .= '</span>';
        }

        if ($property['meta']['bedrooms'] > 0) {
            $output .= '<span class="wmc-meta-item">';
            $output .= '<span class="wmc-meta-label">' . esc_html__('Bedrooms:', 'wp-minpaku-connector') . '</span> ';
            $output .= '<span class="wmc-meta-value">' . esc_html($property['meta']['bedrooms']) . '</span>';
            $output .= '</span>';
        }

        if ($property['meta']['base_price'] > 0) {
            $output .= '<span class="wmc-meta-item wmc-price">';
            $output .= '<span class="wmc-meta-label">' . esc_html__('From:', 'wp-minpaku-connector') . '</span> ';
            $output .= '<span class="wmc-meta-value">¥' . number_format($property['meta']['base_price']) . '</span>';
            $output .= '</span>';
        }
        $output .= '</div>';

        // Add calendar button for property listing
        $output .= '<div class="wmc-property-actions">';
        $output .= '<button class="wmc-calendar-button" data-property-id="' . esc_attr($property['id']) . '" data-property-title="' . esc_attr($property['title']) . '">';
        $output .= '<span class="wmc-calendar-icon">📅</span>';
        $output .= '<span class="wmc-calendar-text">' . esc_html__('Check Availability', 'wp-minpaku-connector') . '</span>';
        $output .= '</button>';
        $output .= '</div>';

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render full property details
     */
    private static function render_property_full($property) {
        $output = '<div class="wmc-property-full">';

        // Gallery or main image
        if (!empty($property['gallery']) && is_array($property['gallery'])) {
            $output .= '<div class="wmc-property-gallery">';
            foreach ($property['gallery'] as $image) {
                $output .= '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt']) . '">';
            }
            $output .= '</div>';
        } elseif (!empty($property['thumbnail'])) {
            $output .= '<div class="wmc-property-image">';
            $output .= '<img src="' . esc_url($property['thumbnail']) . '" alt="' . esc_attr($property['title']) . '">';
            $output .= '</div>';
        }

        // Title and content
        $output .= '<h2 class="wmc-property-title">' . esc_html($property['title']) . '</h2>';

        if (!empty($property['content'])) {
            $output .= '<div class="wmc-property-description">' . wp_kses_post($property['content']) . '</div>';
        }

        // Meta details
        $output .= '<div class="wmc-property-details-grid">';

        $meta_items = array(
            'capacity' => __('Capacity', 'wp-minpaku-connector'),
            'bedrooms' => __('Bedrooms', 'wp-minpaku-connector'),
            'bathrooms' => __('Bathrooms', 'wp-minpaku-connector')
        );

        foreach ($meta_items as $key => $label) {
            if (!empty($property['meta'][$key])) {
                $output .= '<div class="wmc-detail-item">';
                $output .= '<span class="wmc-detail-label">' . esc_html($label) . ':</span> ';
                $output .= '<span class="wmc-detail-value">' . esc_html($property['meta'][$key]) . '</span>';
                $output .= '</div>';
            }
        }

        if (!empty($property['meta']['base_price'])) {
            $output .= '<div class="wmc-detail-item wmc-price-item">';
            $output .= '<span class="wmc-detail-label">' . esc_html__('Base Price:', 'wp-minpaku-connector') . '</span> ';
            $output .= '<span class="wmc-detail-value">¥' . number_format($property['meta']['base_price']) . ' ' . esc_html__('per night', 'wp-minpaku-connector') . '</span>';
            $output .= '</div>';
        }

        $output .= '</div>';

        // Location
        if (!empty($property['location']['address']) || !empty($property['location']['city'])) {
            $output .= '<div class="wmc-property-location">';
            $output .= '<h4>' . esc_html__('Location', 'wp-minpaku-connector') . '</h4>';

            $location_parts = array_filter(array(
                $property['location']['address'],
                $property['location']['city'],
                $property['location']['region'],
                $property['location']['country']
            ));

            $output .= '<p>' . esc_html(implode(', ', $location_parts)) . '</p>';
            $output .= '</div>';
        }

        // Amenities
        if (!empty($property['amenities']) && is_array($property['amenities'])) {
            $output .= '<div class="wmc-property-amenities">';
            $output .= '<h4>' . esc_html__('Amenities', 'wp-minpaku-connector') . '</h4>';
            $output .= '<ul class="wmc-amenities-list">';
            foreach ($property['amenities'] as $amenity) {
                $output .= '<li>' . esc_html($amenity) . '</li>';
            }
            $output .= '</ul>';
            $output .= '</div>';
        }

        // Add availability calendar for property view
        if (class_exists('MinpakuConnector\Shortcodes\MPC_Shortcodes_Calendar')) {
            $output .= '<div class="wmc-property-calendar">';
            $output .= '<h4>' . esc_html__('Availability Calendar', 'wp-minpaku-connector') . '</h4>';

            $calendar_atts = array(
                'property_id' => $property['id'],
                'months' => 3,
                'show_prices' => 'true'
            );

            $output .= \MinpakuConnector\Shortcodes\MPC_Shortcodes_Calendar::render_calendar($calendar_atts);
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render calendar from availability data
     */
    private static function render_calendar($availability) {
        if (empty($availability)) {
            return '';
        }

        $output = '<div class="mcs-availability-calendar">';

        // Group by month
        $months = array();
        foreach ($availability as $day) {
            $date = new \DateTime($day['date']);
            $month_key = $date->format('Y-m');
            if (!isset($months[$month_key])) {
                $months[$month_key] = array(
                    'name' => $date->format('F Y'),
                    'days' => array()
                );
            }
            $months[$month_key]['days'][] = $day;
        }

        foreach ($months as $month) {
            $output .= '<div class="mcs-calendar-month">';
            $output .= '<h3 class="mcs-month-title">' . esc_html($month['name']) . '</h3>';
            $output .= '<div class="mcs-calendar-grid">';

            // Day headers
            $day_names = array(__('Sun', 'wp-minpaku-connector'), __('Mon', 'wp-minpaku-connector'), __('Tue', 'wp-minpaku-connector'), __('Wed', 'wp-minpaku-connector'), __('Thu', 'wp-minpaku-connector'), __('Fri', 'wp-minpaku-connector'), __('Sat', 'wp-minpaku-connector'));
            foreach ($day_names as $day_name) {
                $output .= '<div class="mcs-day-header">' . esc_html($day_name) . '</div>';
            }

            // Calendar days
            foreach ($month['days'] as $day) {
                $date = new \DateTime($day['date']);
                $status_class = $day['available'] ? 'vacant' : 'full';
                $price_text = '';

                if ($day['available'] && isset($day['price']) && $day['price'] > 0) {
                    $price_text = '¥' . number_format($day['price']);
                }

                $status_label = $day['available'] ? __('Available', 'wp-minpaku-connector') : __('Unavailable', 'wp-minpaku-connector');
                $output .= '<div class="mcs-day mcs-day--' . esc_attr($status_class) . '" data-date="' . esc_attr($day['date']) . '" title="' . esc_attr($status_label) . '">';
                $output .= esc_html($date->format('j'));
                if ($price_text) {
                    $output .= '<br><small>' . esc_html($price_text) . '</small>';
                }
                $output .= '</div>';
            }

            $output .= '</div>';
            $output .= '</div>';
        }

        // Legend
        $output .= '<div class="mcs-calendar-legend">';
        $output .= '<h4>' . __('Legend', 'wp-minpaku-connector') . '</h4>';
        $output .= '<div class="mcs-legend-items">';
        $output .= '<div class="mcs-legend-item">';
        $output .= '<span class="mcs-legend-color mcs-day---vacant"></span> ';
        $output .= esc_html__('Available', 'wp-minpaku-connector');
        $output .= '</div>';
        $output .= '<div class="mcs-legend-item">';
        $output .= '<span class="mcs-legend-color mcs-day---full"></span> ';
        $output .= esc_html__('Unavailable', 'wp-minpaku-connector');
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Enqueue calendar styles
     */
    public static function enqueue_styles() {
        global $post;

        // Only enqueue if shortcode is present
        if (!$post || !has_shortcode($post->post_content, 'minpaku_connector')) {
            return;
        }

        // Enqueue calendar CSS file with cache-busting
        $css_file = plugin_dir_url(__FILE__) . '../../assets/css/calendar.css';
        $css_path = plugin_dir_path(__FILE__) . '../../assets/css/calendar.css';

        if (file_exists($css_path)) {
            wp_enqueue_style(
                'wp-minpaku-connector-calendar',
                $css_file,
                [],
                filemtime($css_path)
            );
        } else {
            // Fallback to inline styles
            wp_add_inline_style('wp-block-library', self::get_calendar_css());
        }
    }

    /**
     * Enqueue calendar JavaScript for interactivity
     */
    public static function enqueue_scripts() {
        global $post;

        // Only add if shortcode is present
        if (!$post || !has_shortcode($post->post_content, 'minpaku_connector')) {
            return;
        }

        // Enqueue calendar JS file with cache-busting
        $js_file = plugin_dir_url(__FILE__) . '../../assets/js/calendar.js';
        $js_path = plugin_dir_path(__FILE__) . '../../assets/js/calendar.js';

        if (file_exists($js_path)) {
            wp_enqueue_script(
                'wp-minpaku-connector-calendar',
                $js_file,
                ['jquery'],
                filemtime($js_path),
                true
            );

            // Localize script with portal URL for calendar redirects
            $settings = \WP_Minpaku_Connector::get_settings();
            $portal_url = '';

            if (!empty($settings['portal_url'])) {
                if (\class_exists('MinpakuConnector\Admin\MPC_Admin_Settings')) {
                    $portal_url = \MinpakuConnector\Admin\MPC_Admin_Settings::normalize_portal_url($settings['portal_url']);
                    if ($portal_url === false) {
                        $portal_url = $settings['portal_url']; // Fallback to original
                    }
                } else {
                    $portal_url = $settings['portal_url'];
                }
            }

            wp_localize_script(
                'wp-minpaku-connector-calendar',
                'mpcCalendarData',
                array(
                    'portalUrl' => untrailingslashit($portal_url),
                    'nonce' => wp_create_nonce('mpc_calendar_nonce'),
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'debug' => defined('WP_DEBUG') && WP_DEBUG
                )
            );
        }
    }

    /**
     * Get compact calendar CSS with popup functionality
     */
    private static function get_calendar_css() {
        return '
            /* Minpaku Connector Calendar Styles */
            .mcs-availability-calendar {
                max-width: 100%;
                margin: 15px 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }

            /* Compact calendar button */
            .mcs-calendar-toggle {
                display: inline-block;
                background: #0073aa;
                color: white;
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                margin: 5px 0;
                transition: background-color 0.2s;
            }

            .mcs-calendar-toggle:hover {
                background: #005a87;
            }

            /* Calendar container - hidden by default in compact mode */
            .mcs-calendar-container {
                position: relative;
            }

            /* Popup overlay */
            .mcs-calendar-popup {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 999999;
                justify-content: center;
                align-items: center;
            }

            .mcs-calendar-popup.active {
                display: flex;
            }

            .mcs-calendar-popup-content {
                background: white;
                border-radius: 8px;
                padding: 20px;
                max-width: 90vw;
                max-height: 90vh;
                overflow-y: auto;
                position: relative;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            }

            .mcs-calendar-close {
                position: absolute;
                top: 10px;
                right: 15px;
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
                line-height: 1;
                padding: 5px;
            }

            .mcs-calendar-close:hover {
                color: #000;
            }

            /* Calendar months - compact layout */
            .mcs-calendar-month {
                margin-bottom: 20px;
                border: 1px solid #ddd;
                border-radius: 6px;
                overflow: hidden;
                background: white;
            }

            .mcs-month-title {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                margin: 0;
                padding: 12px 15px;
                text-align: center;
                font-size: 16px;
                font-weight: 600;
            }

            .mcs-calendar-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 1px;
                background: #e9ecef;
            }

            .mcs-day-header {
                background: #495057;
                color: white;
                padding: 8px 4px;
                text-align: center;
                font-weight: 600;
                font-size: 11px;
                text-transform: uppercase;
            }

            .mcs-day {
                background: white;
                padding: 8px 4px;
                text-align: center;
                min-height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 13px;
                transition: all 0.2s ease;
                position: relative;
                cursor: pointer;
            }

            .mcs-day:hover {
                transform: scale(1.1);
                z-index: 10;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
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
                opacity: 0.5;
                cursor: not-allowed;
            }

            .mcs-day--past:hover {
                transform: none;
                box-shadow: none;
            }

            /* Compact legend */
            .mcs-calendar-legend {
                margin-top: 15px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 4px;
                border: 1px solid #dee2e6;
            }

            .mcs-calendar-legend h4 {
                margin: 0 0 8px 0;
                font-size: 13px;
                font-weight: 600;
                color: #495057;
            }

            .mcs-legend-items {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
            }

            .mcs-legend-item {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 12px;
            }

            .mcs-legend-color {
                width: 14px;
                height: 14px;
                border-radius: 2px;
                border: 1px solid #ccc;
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

            /* Property list layout adjustments */
            .wmc-properties .mcs-availability-calendar {
                margin-top: 10px;
            }

            .wmc-property-card .mcs-calendar-toggle {
                font-size: 12px;
                padding: 6px 12px;
            }

            /* Error and notice styles */
            .mcs-error {
                color: #d63384;
                background: #f8d7da;
                padding: 8px 12px;
                border-radius: 4px;
                border: 1px solid #f5c6cb;
                font-size: 13px;
                margin: 10px 0;
            }

            .mcs-calendar-notice {
                color: #856404;
                background: #fff3cd;
                padding: 8px 12px;
                border-radius: 4px;
                border: 1px solid #ffeaa7;
                margin-bottom: 10px;
                font-size: 13px;
            }

            /* Mobile responsive */
            @media (max-width: 768px) {
                .mcs-calendar-popup-content {
                    margin: 10px;
                    padding: 15px;
                }

                .mcs-day-header, .mcs-day {
                    padding: 6px 2px;
                    font-size: 11px;
                    min-height: 28px;
                }

                .mcs-legend-items {
                    flex-direction: column;
                    gap: 8px;
                }

                .mcs-month-title {
                    font-size: 14px;
                    padding: 10px;
                }
            }

            @media (max-width: 480px) {
                .mcs-calendar-popup-content {
                    max-width: 95vw;
                }

                .mcs-day {
                    min-height: 24px;
                    font-size: 10px;
                }
            }
        ';
    }

    /**
     * Get calendar JavaScript for popup functionality
     */
    private static function get_calendar_javascript() {
        return '
            document.addEventListener("DOMContentLoaded", function() {
                // Convert calendar displays to compact popup mode
                const calendars = document.querySelectorAll(".mcs-availability-calendar");

                calendars.forEach(function(calendar) {
                    // Skip if already processed
                    if (calendar.classList.contains("mcs-processed")) {
                        return;
                    }
                    calendar.classList.add("mcs-processed");

                    // Create toggle button
                    const toggleButton = document.createElement("button");
                    toggleButton.className = "mcs-calendar-toggle";
                    toggleButton.innerHTML = "📅 空室カレンダーを表示";

                    // Create popup structure
                    const popup = document.createElement("div");
                    popup.className = "mcs-calendar-popup";

                    const popupContent = document.createElement("div");
                    popupContent.className = "mcs-calendar-popup-content";

                    const closeButton = document.createElement("button");
                    closeButton.className = "mcs-calendar-close";
                    closeButton.innerHTML = "×";
                    closeButton.setAttribute("aria-label", "閉じる");

                    // Move calendar content to popup
                    const calendarContent = calendar.cloneNode(true);
                    calendarContent.classList.remove("mcs-processed");

                    popupContent.appendChild(closeButton);
                    popupContent.appendChild(calendarContent);
                    popup.appendChild(popupContent);

                    // Replace calendar with toggle button
                    calendar.parentNode.insertBefore(toggleButton, calendar);
                    calendar.parentNode.insertBefore(popup, calendar);
                    calendar.style.display = "none";

                    // Event listeners
                    toggleButton.addEventListener("click", function(e) {
                        e.preventDefault();
                        popup.classList.add("active");
                        document.body.style.overflow = "hidden";
                    });

                    closeButton.addEventListener("click", function() {
                        popup.classList.remove("active");
                        document.body.style.overflow = "";
                    });

                    popup.addEventListener("click", function(e) {
                        if (e.target === popup) {
                            popup.classList.remove("active");
                            document.body.style.overflow = "";
                        }
                    });

                    // ESC key to close
                    document.addEventListener("keydown", function(e) {
                        if (e.key === "Escape" && popup.classList.contains("active")) {
                            popup.classList.remove("active");
                            document.body.style.overflow = "";
                        }
                    });
                });
            });
        ';
    }
}