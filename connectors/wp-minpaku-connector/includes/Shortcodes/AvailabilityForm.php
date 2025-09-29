<?php
/**
 * Availability Form Shortcode
 *
 * @package WP_Minpaku_Connector
 */

namespace MpkConnector\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Availability Form Shortcode Class
 */
class AvailabilityForm {

    /**
     * Initialize the shortcode
     */
    public static function init() {
        add_shortcode('mpk_availability_form', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Enqueue scripts and styles for the shortcode
     */
    public static function enqueue_scripts() {
        // Only enqueue if the shortcode is used on the page
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mpk_availability_form')) {
            wp_enqueue_script(
                'mpk-availability-form',
                WP_MINPAKU_CONNECTOR_URL . 'assets/js/availability-form.js',
                ['jquery'],
                WP_MINPAKU_CONNECTOR_VERSION,
                true
            );

            wp_enqueue_style(
                'mpk-availability-form',
                WP_MINPAKU_CONNECTOR_URL . 'assets/css/availability-form.css',
                [],
                WP_MINPAKU_CONNECTOR_VERSION
            );

            // Localize script for AJAX
            wp_localize_script('mpk-availability-form', 'mpkAvailability', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mpk_availability_nonce'),
                'strings' => [
                    'loading' => __('Loading...', 'wp-minpaku-connector'),
                    'error' => __('An error occurred. Please try again.', 'wp-minpaku-connector'),
                    'selectDates' => __('Please select both from and to dates.', 'wp-minpaku-connector'),
                    'invalidDates' => __('Please select valid dates.', 'wp-minpaku-connector'),
                    'noResults' => __('No availability data found for the selected dates.', 'wp-minpaku-connector'),
                    'available' => __('Available', 'wp-minpaku-connector'),
                    'notAvailable' => __('Not Available', 'wp-minpaku-connector'),
                    'price' => __('Price:', 'wp-minpaku-connector'),
                    'currency' => __('¥', 'wp-minpaku-connector')
                ]
            ]);
        }
    }

    /**
     * Render the availability form shortcode
     */
    public static function render($atts) {
        $atts = shortcode_atts([
            'property_id' => '',
            'title' => __('Check Availability', 'wp-minpaku-connector'),
            'show_calendar' => 'true',
            'theme' => 'default'
        ], $atts, 'mpk_availability_form');

        // Sanitize attributes
        $property_id = !empty($atts['property_id']) ? absint($atts['property_id']) : 0;
        $title = sanitize_text_field($atts['title']);
        $show_calendar = filter_var($atts['show_calendar'], FILTER_VALIDATE_BOOLEAN);
        $theme = sanitize_text_field($atts['theme']);

        ob_start();
        ?>
        <div class="mpk-availability-form-wrapper mpk-theme-<?php echo esc_attr($theme); ?>">
            <?php if (!empty($title)) : ?>
                <h3 class="mpk-form-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <form class="mpk-availability-form" data-property-id="<?php echo esc_attr($property_id); ?>">
                <div class="mpk-form-row">
                    <?php if (empty($property_id)) : ?>
                        <div class="mpk-form-field">
                            <label for="mpk-property-id"><?php esc_html_e('Property ID:', 'wp-minpaku-connector'); ?></label>
                            <input type="number" id="mpk-property-id" name="property_id" required min="1" />
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mpk-form-row">
                    <div class="mpk-form-field">
                        <label for="mpk-from-date"><?php esc_html_e('From Date:', 'wp-minpaku-connector'); ?></label>
                        <input type="date" id="mpk-from-date" name="from_date" required min="<?php echo esc_attr(date('Y-m-d')); ?>" />
                    </div>

                    <div class="mpk-form-field">
                        <label for="mpk-to-date"><?php esc_html_e('To Date:', 'wp-minpaku-connector'); ?></label>
                        <input type="date" id="mpk-to-date" name="to_date" required min="<?php echo esc_attr(date('Y-m-d')); ?>" />
                    </div>
                </div>

                <div class="mpk-form-row">
                    <button type="submit" class="mpk-submit-button">
                        <?php esc_html_e('Check Availability', 'wp-minpaku-connector'); ?>
                    </button>
                </div>
            </form>

            <div class="mpk-results-container" style="display: none;">
                <div class="mpk-loading" style="display: none;">
                    <p><?php esc_html_e('Loading availability...', 'wp-minpaku-connector'); ?></p>
                </div>

                <div class="mpk-error" style="display: none;">
                    <p class="mpk-error-message"></p>
                </div>

                <div class="mpk-results" style="display: none;">
                    <?php if ($show_calendar) : ?>
                        <div class="mpk-calendar-view">
                            <h4><?php esc_html_e('Availability Calendar', 'wp-minpaku-connector'); ?></h4>
                            <div class="mpk-calendar-grid"></div>
                        </div>
                    <?php endif; ?>

                    <div class="mpk-list-view">
                        <h4><?php esc_html_e('Availability Details', 'wp-minpaku-connector'); ?></h4>
                        <div class="mpk-availability-list"></div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .mpk-availability-form-wrapper {
            max-width: 600px;
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
        }

        .mpk-form-title {
            margin: 0 0 20px 0;
            font-size: 24px;
            color: #333;
        }

        .mpk-availability-form {
            margin-bottom: 20px;
        }

        .mpk-form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .mpk-form-field {
            flex: 1;
            min-width: 200px;
        }

        .mpk-form-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        .mpk-form-field input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .mpk-form-field input:focus {
            outline: none;
            border-color: #0073aa;
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.2);
        }

        .mpk-submit-button {
            background: #0073aa;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .mpk-submit-button:hover {
            background: #005a87;
        }

        .mpk-submit-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .mpk-results-container {
            margin-top: 20px;
        }

        .mpk-loading {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }

        .mpk-error {
            background: #fff2f2;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .mpk-error-message {
            color: #721c24;
            margin: 0;
        }

        .mpk-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin: 10px 0;
        }

        .mpk-calendar-day {
            aspect-ratio: 1;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            text-align: center;
            background: #f9f9f9;
        }

        .mpk-calendar-day.available {
            background: #d4edda;
            border-color: #c3e6cb;
        }

        .mpk-calendar-day.not-available {
            background: #f8d7da;
            border-color: #f5c6cb;
        }

        .mpk-availability-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .mpk-availability-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }

        .mpk-availability-item:last-child {
            border-bottom: none;
        }

        .mpk-availability-item.available {
            background: #d4edda;
        }

        .mpk-availability-item.not-available {
            background: #f8d7da;
        }

        .mpk-date {
            font-weight: bold;
        }

        .mpk-status {
            font-size: 14px;
        }

        .mpk-price {
            font-weight: bold;
            color: #0073aa;
        }

        @media (max-width: 768px) {
            .mpk-form-row {
                flex-direction: column;
            }

            .mpk-form-field {
                min-width: auto;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
}