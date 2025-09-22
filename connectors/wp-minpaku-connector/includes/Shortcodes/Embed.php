<?php
/**
 * Shortcodes for Embedding Content
 *
 * @package WP_Minpaku_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

class WMC_Shortcodes_Embed {

    public static function init() {
        add_shortcode('minpaku_connector', array(__CLASS__, 'render_shortcode'));
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
            $api = new WMC_Client_Api();
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
        // Validate and sanitize input
        $property_id = intval($atts['property_id']);
        $months = max(1, min(12, intval($atts['months']))); // Limit between 1-12 months
        $start_date = sanitize_text_field($atts['start_date']);
        $css_class = sanitize_html_class($atts['class']);

        // Property ID is required
        if (empty($property_id) || $property_id <= 0) {
            self::log_error('Property ID missing or invalid: ' . $property_id, 'shortcode');
            return self::render_error_notice(
                __('Property ID required', 'wp-minpaku-connector'),
                __('Please specify a valid property ID to display the availability calendar.', 'wp-minpaku-connector'),
                'validation'
            );
        }

        self::log_error("Fetching availability: property_id=$property_id, months=$months", 'shortcode');

        try {
            $response = $api->get_availability($property_id, $months, $start_date);

            if (!$response['success']) {
                self::log_error('Availability API failed: ' . $response['message'], 'shortcode');

                // Provide user-friendly error based on the issue
                if (strpos($response['message'], '404') !== false || strpos($response['message'], 'not found') !== false) {
                    return self::render_error_notice(
                        __('Property not found', 'wp-minpaku-connector'),
                        sprintf(__('Property with ID %d was not found. Please check the property ID.', 'wp-minpaku-connector'), $property_id),
                        'notfound'
                    );
                } elseif (strpos($response['message'], '401') !== false || strpos($response['message'], '403') !== false) {
                    return self::render_error_notice(
                        __('Access denied', 'wp-minpaku-connector'),
                        __('Unable to access property availability. Please check your connection settings.', 'wp-minpaku-connector'),
                        'access'
                    );
                } else {
                    return self::render_error_notice(
                        __('Unable to load availability', 'wp-minpaku-connector'),
                        __('There was a problem loading the availability calendar. Please try again later.', 'wp-minpaku-connector'),
                        'api'
                    );
                }
            }

            $data = $response['data'];
            $availability = isset($data['availability']) ? $data['availability'] : array();

            if (empty($availability)) {
                self::log_error('No availability data returned for property: ' . $property_id, 'shortcode');
                return '<div class="wmc-no-content">' .
                       '<p><strong>' . esc_html__('No availability data', 'wp-minpaku-connector') . '</strong></p>' .
                       '<p>' . esc_html__('Availability information is not currently available for this property.', 'wp-minpaku-connector') . '</p>' .
                       '</div>';
            }

            self::log_error('Successfully loaded availability for property: ' . $property_id, 'shortcode');

            // Semantic HTML structure
            $output = '<section class="wmc-availability ' . esc_attr($css_class) . '" aria-label="' . esc_attr__('Property availability calendar', 'wp-minpaku-connector') . '">';

            if (isset($data['property_title'])) {
                $output .= '<h3 class="wmc-availability-title">' . esc_html($data['property_title']) . '</h3>';
            }

            if (isset($data['start_date']) && isset($data['end_date'])) {
                $output .= '<div class="wmc-availability-period">';
                $output .= sprintf(
                    esc_html__('Availability from %s to %s', 'wp-minpaku-connector'),
                    esc_html(date_i18n(get_option('date_format'), strtotime($data['start_date']))),
                    esc_html(date_i18n(get_option('date_format'), strtotime($data['end_date'])))
                );
                $output .= '</div>';
            }

            $output .= self::render_calendar($availability);
            $output .= '</section>';

            return $output;

        } catch (Exception $e) {
            self::log_error('Availability rendering exception: ' . $e->getMessage(), 'shortcode');
            return self::render_error_notice(
                __('System error', 'wp-minpaku-connector'),
                __('Unable to display availability calendar due to a technical issue.', 'wp-minpaku-connector'),
                'system'
            );
        }
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

        $output = '<div class="wmc-calendar">';

        // Group by month
        $months = array();
        foreach ($availability as $day) {
            $date = new DateTime($day['date']);
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
            $output .= '<div class="wmc-calendar-month">';
            $output .= '<h4 class="wmc-month-title">' . esc_html($month['name']) . '</h4>';
            $output .= '<div class="wmc-calendar-grid">';

            // Day headers
            $day_names = array(__('Sun', 'wp-minpaku-connector'), __('Mon', 'wp-minpaku-connector'), __('Tue', 'wp-minpaku-connector'), __('Wed', 'wp-minpaku-connector'), __('Thu', 'wp-minpaku-connector'), __('Fri', 'wp-minpaku-connector'), __('Sat', 'wp-minpaku-connector'));
            foreach ($day_names as $day_name) {
                $output .= '<div class="wmc-day-header">' . esc_html($day_name) . '</div>';
            }

            // Calendar days
            foreach ($month['days'] as $day) {
                $date = new DateTime($day['date']);
                $status_class = $day['available'] ? 'available' : 'unavailable';
                $price_text = '';

                if ($day['available'] && isset($day['price']) && $day['price'] > 0) {
                    $price_text = '¥' . number_format($day['price']);
                }

                $output .= '<div class="wmc-calendar-day wmc-day-' . esc_attr($status_class) . '" data-date="' . esc_attr($day['date']) . '">';
                $output .= '<span class="wmc-day-number">' . esc_html($date->format('j')) . '</span>';
                if ($price_text) {
                    $output .= '<span class="wmc-day-price">' . esc_html($price_text) . '</span>';
                }
                $output .= '</div>';
            }

            $output .= '</div>';
            $output .= '</div>';
        }

        // Legend
        $output .= '<div class="wmc-calendar-legend">';
        $output .= '<span class="wmc-legend-item">';
        $output .= '<span class="wmc-legend-color wmc-available"></span>';
        $output .= '<span class="wmc-legend-text">' . esc_html__('Available', 'wp-minpaku-connector') . '</span>';
        $output .= '</span>';
        $output .= '<span class="wmc-legend-item">';
        $output .= '<span class="wmc-legend-color wmc-unavailable"></span>';
        $output .= '<span class="wmc-legend-text">' . esc_html__('Unavailable', 'wp-minpaku-connector') . '</span>';
        $output .= '</span>';
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }
}