<?php
/**
 * Property Card Shortcode with Quick Quote
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class MPC_Shortcodes_PropertyCard {

    public static function init() {
        add_shortcode('minpaku_property_card', array(__CLASS__, 'render_property_card'));
        add_shortcode('minpaku_property_list', array(__CLASS__, 'render_property_list'));
    }

    /**
     * Render single property card shortcode
     */
    public static function render_property_card($atts) {
        $atts = shortcode_atts(array(
            'property_id' => '',
            'show_price' => 'true',
            'price_nights' => 2,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'JPY',
            'layout' => 'card'
        ), $atts, 'minpaku_property_card');

        if (empty($atts['property_id'])) {
            return '<p>' . __('Property ID is required.', 'wp-minpaku-connector') . '</p>';
        }

        $property_data = self::get_property_data($atts['property_id']);
        if (!$property_data) {
            return '<p>' . __('Property not found.', 'wp-minpaku-connector') . '</p>';
        }

        return self::render_single_card($property_data, $atts);
    }

    /**
     * Render property list shortcode
     */
    public static function render_property_list($atts) {
        $atts = shortcode_atts(array(
            'limit' => 12,
            'show_prices' => 'true',
            'price_nights' => 2,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'JPY',
            'layout' => 'grid',
            'columns' => 3
        ), $atts, 'minpaku_property_list');

        $properties = self::get_properties_list($atts['limit']);

        if (empty($properties)) {
            return '<p>' . __('No properties found.', 'wp-minpaku-connector') . '</p>';
        }

        return self::render_property_grid($properties, $atts);
    }

    /**
     * Render single property card
     */
    private static function render_single_card($property, $atts) {
        $property_id = $property['id'];
        $show_price = ($atts['show_price'] === 'true');
        $price_nights = max(1, intval($atts['price_nights']));
        $adults = max(1, intval($atts['adults']));
        $children = max(0, intval($atts['children']));
        $infants = max(0, intval($atts['infants']));
        $currency = sanitize_text_field($atts['currency']);
        $layout = sanitize_text_field($atts['layout']);

        $card_id = 'mpc-property-card-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($card_id); ?>"
             class="mpc-property-card mpc-property-card-<?php echo esc_attr($layout); ?>"
             data-property-id="<?php echo esc_attr($property_id); ?>"
             data-show-price="<?php echo $show_price ? '1' : '0'; ?>"
             data-price-nights="<?php echo esc_attr($price_nights); ?>"
             data-adults="<?php echo esc_attr($adults); ?>"
             data-children="<?php echo esc_attr($children); ?>"
             data-infants="<?php echo esc_attr($infants); ?>"
             data-currency="<?php echo esc_attr($currency); ?>">

            <?php if (!empty($property['image'])): ?>
                <div class="mpc-property-image">
                    <img src="<?php echo esc_url($property['image']); ?>"
                         alt="<?php echo esc_attr($property['title']); ?>"
                         loading="lazy">

                    <?php if ($show_price): ?>
                        <div class="mpc-property-quick-price loading" data-property-id="<?php echo esc_attr($property_id); ?>">
                            <?php _e('Loading...', 'wp-minpaku-connector'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="mpc-property-details">
                <h3 class="mpc-property-title">
                    <?php echo esc_html($property['title']); ?>
                </h3>

                <?php if (!empty($property['address'])): ?>
                    <p class="mpc-property-address">
                        <span class="dashicons dashicons-location"></span>
                        <?php echo esc_html($property['address']); ?>
                    </p>
                <?php endif; ?>

                <div class="mpc-property-specs">
                    <?php if (!empty($property['capacity'])): ?>
                        <span class="mpc-property-spec">
                            <span class="dashicons dashicons-groups"></span>
                            <?php printf(__('%d guests', 'wp-minpaku-connector'), intval($property['capacity'])); ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($property['bedrooms'])): ?>
                        <span class="mpc-property-spec">
                            <span class="dashicons dashicons-building"></span>
                            <?php printf(__('%d bedrooms', 'wp-minpaku-connector'), intval($property['bedrooms'])); ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($property['bathrooms'])): ?>
                        <span class="mpc-property-spec">
                            <span class="dashicons dashicons-admin-home"></span>
                            <?php printf(__('%d baths', 'wp-minpaku-connector'), floatval($property['bathrooms'])); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($property['amenities'])): ?>
                    <div class="mpc-property-amenities">
                        <?php foreach (array_slice($property['amenities'], 0, 4) as $amenity): ?>
                            <span class="mpc-amenity-tag"><?php echo esc_html($amenity); ?></span>
                        <?php endforeach; ?>

                        <?php if (count($property['amenities']) > 4): ?>
                            <span class="mpc-amenity-more">
                                <?php printf(__('+ %d more', 'wp-minpaku-connector'), count($property['amenities']) - 4); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($property['description'])): ?>
                    <p class="mpc-property-description">
                        <?php echo esc_html(wp_trim_words($property['description'], 20)); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="mpc-property-actions">
                <a href="<?php echo esc_url($property['url']); ?>" class="mpc-property-link">
                    <?php _e('View Details', 'wp-minpaku-connector'); ?>
                </a>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Initialize price manager for this property card
            if (typeof MPCPriceManager !== 'undefined') {
                const priceManager = new MPCPriceManager();
                priceManager.initPropertyCard('#<?php echo esc_js($card_id); ?>');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render property grid
     */
    private static function render_property_grid($properties, $atts) {
        $show_prices = ($atts['show_prices'] === 'true');
        $columns = max(1, min(6, intval($atts['columns'])));
        $layout = sanitize_text_field($atts['layout']);

        $grid_id = 'mpc-property-grid-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($grid_id); ?>"
             class="mpc-property-grid mpc-property-grid-<?php echo esc_attr($layout); ?> mpc-columns-<?php echo esc_attr($columns); ?>"
             data-show-prices="<?php echo $show_prices ? '1' : '0'; ?>"
             data-price-nights="<?php echo esc_attr($atts['price_nights']); ?>"
             data-adults="<?php echo esc_attr($atts['adults']); ?>"
             data-children="<?php echo esc_attr($atts['children']); ?>"
             data-infants="<?php echo esc_attr($atts['infants']); ?>"
             data-currency="<?php echo esc_attr($atts['currency']); ?>">

            <?php foreach ($properties as $property): ?>
                <?php echo self::render_single_card($property, array_merge($atts, array('layout' => 'grid-item'))); ?>
            <?php endforeach; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Initialize price manager for property grid
            if (typeof MPCPriceManager !== 'undefined') {
                const priceManager = new MPCPriceManager();
                priceManager.initPropertyGrid('#<?php echo esc_js($grid_id); ?>');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Get property data from API
     */
    private static function get_property_data($property_id) {
        // Try to get from transient cache first
        $cache_key = 'mpc_property_' . $property_id;
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        // Fetch from API
        if (class_exists('MinpakuConnector\Client\MPC_Client_Api')) {
            $api = new \MinpakuConnector\Client\MPC_Client_Api();

            if ($api->is_configured()) {
                $result = $api->get_property($property_id);

                if ($result['success'] && !empty($result['data'])) {
                    $property_data = $result['data'];

                    // Cache for 1 hour
                    set_transient($cache_key, $property_data, HOUR_IN_SECONDS);

                    return $property_data;
                }
            }
        }

        // Return null if property not found
        return null;
    }

    /**
     * Get properties list from API
     */
    private static function get_properties_list($limit) {
        // Try to get from transient cache first
        $cache_key = 'mpc_properties_list_' . $limit;
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        // Fetch from API
        if (class_exists('MinpakuConnector\Client\MPC_Client_Api')) {
            $api = new \MinpakuConnector\Client\MPC_Client_Api();

            if ($api->is_configured()) {
                $result = $api->get_properties(array('limit' => $limit));

                if ($result['success'] && !empty($result['data'])) {
                    $properties = $result['data'];

                    // Cache for 30 minutes
                    set_transient($cache_key, $properties, 30 * MINUTE_IN_SECONDS);

                    return $properties;
                }
            }
        }

        // Return empty array if no properties found
        return array();
    }
}