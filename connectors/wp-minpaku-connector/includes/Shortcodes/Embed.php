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

        // Check if API is configured
        $api = new WMC_Client_Api();
        if (!$api->is_configured()) {
            return '<div class="wmc-error">' . esc_html__('Minpaku Connector is not properly configured.', 'wp-minpaku-connector') . '</div>';
        }

        switch ($atts['type']) {
            case 'properties':
                return self::render_properties($atts, $api);

            case 'availability':
                return self::render_availability($atts, $api);

            case 'property':
                return self::render_property($atts, $api);

            default:
                return '<div class="wmc-error">' . esc_html__('Invalid shortcode type specified.', 'wp-minpaku-connector') . '</div>';
        }
    }

    /**
     * Render properties listing
     */
    private static function render_properties($atts, $api) {
        $limit = intval($atts['limit']);
        $columns = intval($atts['columns']);
        $css_class = sanitize_html_class($atts['class']);

        $response = $api->get_properties(array(
            'per_page' => $limit,
            'page' => 1
        ));

        if (!$response['success']) {
            return '<div class="wmc-error">' . esc_html($response['message']) . '</div>';
        }

        $properties = $response['data'];
        if (empty($properties)) {
            return '<div class="wmc-no-content">' . esc_html__('No properties found.', 'wp-minpaku-connector') . '</div>';
        }

        $output = '<div class="wmc-properties wmc-grid wmc-columns-' . esc_attr($columns) . ' ' . esc_attr($css_class) . '">';

        foreach ($properties as $property) {
            $output .= self::render_property_card($property);
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render availability calendar
     */
    private static function render_availability($atts, $api) {
        $property_id = intval($atts['property_id']);
        $months = intval($atts['months']);
        $start_date = sanitize_text_field($atts['start_date']);
        $css_class = sanitize_html_class($atts['class']);

        if (empty($property_id)) {
            return '<div class="wmc-error">' . esc_html__('Property ID is required for availability display.', 'wp-minpaku-connector') . '</div>';
        }

        $response = $api->get_availability($property_id, $months, $start_date);

        if (!$response['success']) {
            return '<div class="wmc-error">' . esc_html($response['message']) . '</div>';
        }

        $data = $response['data'];
        $availability = $data['availability'];

        if (empty($availability)) {
            return '<div class="wmc-no-content">' . esc_html__('No availability data found.', 'wp-minpaku-connector') . '</div>';
        }

        $output = '<div class="wmc-availability ' . esc_attr($css_class) . '">';
        $output .= '<h3 class="wmc-availability-title">' . esc_html($data['property_title']) . '</h3>';
        $output .= '<div class="wmc-availability-period">';
        $output .= sprintf(
            esc_html__('Availability from %s to %s', 'wp-minpaku-connector'),
            esc_html($data['start_date']),
            esc_html($data['end_date'])
        );
        $output .= '</div>';

        $output .= self::render_calendar($availability);
        $output .= '</div>';

        return $output;
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