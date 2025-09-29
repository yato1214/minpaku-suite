<?php
/**
 * Settings Page for WP Minpaku Connector
 *
 * @package WP_Minpaku_Connector
 */

namespace MpkConnector\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Page Class
 */
class SettingsPage {

    /**
     * Option name for storing settings
     */
    const OPTION_NAME = 'mpk_connector_options';

    /**
     * Initialize the settings page
     */
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Register settings and fields
     */
    public static function register_settings() {
        register_setting(
            'mpk_connector_settings',
            self::OPTION_NAME,
            [
                'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
                'default' => self::get_default_settings()
            ]
        );

        add_settings_section(
            'mpk_api_section',
            __('API Settings', 'wp-minpaku-connector'),
            [__CLASS__, 'api_section_callback'],
            'mpk_connector_settings'
        );

        add_settings_field(
            'api_base_url',
            __('API Base URL', 'wp-minpaku-connector'),
            [__CLASS__, 'api_base_url_callback'],
            'mpk_connector_settings',
            'mpk_api_section'
        );

        add_settings_field(
            'api_key',
            __('API Key', 'wp-minpaku-connector'),
            [__CLASS__, 'api_key_callback'],
            'mpk_connector_settings',
            'mpk_api_section'
        );

        add_settings_field(
            'timeout',
            __('Timeout (seconds)', 'wp-minpaku-connector'),
            [__CLASS__, 'timeout_callback'],
            'mpk_connector_settings',
            'mpk_api_section'
        );
    }

    /**
     * Get default settings
     */
    public static function get_default_settings() {
        return [
            'api_base_url' => '',
            'api_key' => '',
            'timeout' => 30
        ];
    }

    /**
     * Get current settings
     */
    public static function get_settings() {
        $defaults = self::get_default_settings();
        $settings = get_option(self::OPTION_NAME, $defaults);

        return wp_parse_args($settings, $defaults);
    }

    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = [];

        if (isset($input['api_base_url'])) {
            $sanitized['api_base_url'] = sanitize_url($input['api_base_url']);
        }

        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }

        if (isset($input['timeout'])) {
            $timeout = absint($input['timeout']);
            $sanitized['timeout'] = max(1, min(300, $timeout)); // Between 1-300 seconds
        }

        return $sanitized;
    }

    /**
     * API section description
     */
    public static function api_section_callback() {
        echo '<p>' . esc_html__('Configure API connection settings for Minpaku Suite.', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * API Base URL field callback
     */
    public static function api_base_url_callback() {
        $settings = self::get_settings();
        $value = esc_attr($settings['api_base_url']);

        echo '<input type="url" id="api_base_url" name="' . esc_attr(self::OPTION_NAME) . '[api_base_url]" value="' . $value . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Enter the base URL for the Minpaku Suite API (e.g., https://your-portal.com)', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * API Key field callback
     */
    public static function api_key_callback() {
        $settings = self::get_settings();
        $value = $settings['api_key'];
        $masked_value = !empty($value) ? str_repeat('*', 8) . substr($value, -4) : '';

        echo '<input type="password" id="api_key" name="' . esc_attr(self::OPTION_NAME) . '[api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Enter your API key for authentication.', 'wp-minpaku-connector') . '</p>';

        if (!empty($value)) {
            echo '<p class="description"><strong>' . esc_html__('Current key:', 'wp-minpaku-connector') . '</strong> ' . esc_html($masked_value) . '</p>';
        }
    }

    /**
     * Timeout field callback
     */
    public static function timeout_callback() {
        $settings = self::get_settings();
        $value = absint($settings['timeout']);

        echo '<input type="number" id="timeout" name="' . esc_attr(self::OPTION_NAME) . '[timeout]" value="' . $value . '" min="1" max="300" class="small-text" />';
        echo '<p class="description">' . esc_html__('API request timeout in seconds (1-300).', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * Render the settings page
     */
    public static function render_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-minpaku-connector'));
        }

        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('mpk_connector_settings-options')) {
            // WordPress will handle the saving via the registered setting
            add_settings_error(
                self::OPTION_NAME,
                'settings_updated',
                __('Settings saved successfully.', 'wp-minpaku-connector'),
                'updated'
            );
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('mpk_connector_settings');
                do_settings_sections('mpk_connector_settings');
                submit_button(__('Save Settings', 'wp-minpaku-connector'));
                ?>
            </form>

            <div class="mpk-settings-info">
                <h3><?php esc_html_e('Connection Test', 'wp-minpaku-connector'); ?></h3>
                <p><?php esc_html_e('Save your settings first, then use the test button to verify the connection.', 'wp-minpaku-connector'); ?></p>
                <button type="button" id="mpk-test-connection" class="button button-secondary">
                    <?php esc_html_e('Test Connection', 'wp-minpaku-connector'); ?>
                </button>
                <div id="mpk-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#mpk-test-connection').on('click', function() {
                var $button = $(this);
                var $result = $('#mpk-test-result');

                $button.prop('disabled', true).text('<?php esc_html_e('Testing...', 'wp-minpaku-connector'); ?>');
                $result.html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpk_test_connection',
                        nonce: '<?php echo wp_create_nonce('mpk_test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline"><p><?php esc_html_e('Connection test failed.', 'wp-minpaku-connector'); ?></p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php esc_html_e('Test Connection', 'wp-minpaku-connector'); ?>');
                    }
                });
            });
        });
        </script>

        <style>
        .mpk-settings-info {
            margin-top: 30px;
            padding: 20px;
            background: #f1f1f1;
            border-radius: 5px;
        }
        </style>
        <?php
    }
}