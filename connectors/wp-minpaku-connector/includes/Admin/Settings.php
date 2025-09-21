<?php
/**
 * Admin Settings Class
 *
 * @package WP_Minpaku_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

class WMC_Admin_Settings {

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            'wp_minpaku_connector_settings',
            'wp_minpaku_connector_settings',
            array(__CLASS__, 'sanitize_settings')
        );

        add_settings_section(
            'wmc_connection_section',
            __('Portal Connection', 'wp-minpaku-connector'),
            array(__CLASS__, 'connection_section_callback'),
            'wp-minpaku-connector'
        );

        add_settings_field(
            'portal_url',
            __('Portal Base URL', 'wp-minpaku-connector'),
            array(__CLASS__, 'portal_url_callback'),
            'wp-minpaku-connector',
            'wmc_connection_section'
        );

        add_settings_field(
            'site_id',
            __('Site ID', 'wp-minpaku-connector'),
            array(__CLASS__, 'site_id_callback'),
            'wp-minpaku-connector',
            'wmc_connection_section'
        );

        add_settings_field(
            'api_key',
            __('API Key', 'wp-minpaku-connector'),
            array(__CLASS__, 'api_key_callback'),
            'wp-minpaku-connector',
            'wmc_connection_section'
        );

        add_settings_field(
            'secret',
            __('Secret', 'wp-minpaku-connector'),
            array(__CLASS__, 'secret_callback'),
            'wp-minpaku-connector',
            'wmc_connection_section'
        );
    }

    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['portal_url'])) {
            $sanitized['portal_url'] = esc_url_raw(trim($input['portal_url']));
        }

        if (isset($input['site_id'])) {
            $sanitized['site_id'] = sanitize_text_field(trim($input['site_id']));
        }

        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field(trim($input['api_key']));
        }

        if (isset($input['secret'])) {
            $sanitized['secret'] = sanitize_text_field(trim($input['secret']));
        }

        return $sanitized;
    }

    /**
     * Connection section callback
     */
    public static function connection_section_callback() {
        echo '<p>' . esc_html__('Enter your Minpaku Suite portal connection details. You can get these from your portal admin under Minpaku › Settings › Connector.', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * Portal URL field callback
     */
    public static function portal_url_callback() {
        $settings = WP_Minpaku_Connector::get_settings();
        echo '<input type="url" id="portal_url" name="wp_minpaku_connector_settings[portal_url]" value="' . esc_attr($settings['portal_url']) . '" class="regular-text" placeholder="https://your-portal.com" required />';
        echo '<p class="description">' . esc_html__('The base URL of your Minpaku Suite portal (e.g., https://yoursite.com)', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * Site ID field callback
     */
    public static function site_id_callback() {
        $settings = WP_Minpaku_Connector::get_settings();
        echo '<input type="text" id="site_id" name="wp_minpaku_connector_settings[site_id]" value="' . esc_attr($settings['site_id']) . '" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('The Site ID generated in your portal connector settings.', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * API Key field callback
     */
    public static function api_key_callback() {
        $settings = WP_Minpaku_Connector::get_settings();
        echo '<input type="text" id="api_key" name="wp_minpaku_connector_settings[api_key]" value="' . esc_attr($settings['api_key']) . '" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('The API Key generated in your portal connector settings.', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * Secret field callback
     */
    public static function secret_callback() {
        $settings = WP_Minpaku_Connector::get_settings();
        echo '<input type="password" id="secret" name="wp_minpaku_connector_settings[secret]" value="' . esc_attr($settings['secret']) . '" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('The Secret key generated in your portal connector settings.', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * Render admin page
     */
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'wp-minpaku-connector'));
        }

        $settings = WP_Minpaku_Connector::get_settings();
        $is_configured = !empty($settings['portal_url']) && !empty($settings['api_key']) && !empty($settings['secret']) && !empty($settings['site_id']);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Minpaku Connector Settings', 'wp-minpaku-connector'); ?></h1>

            <p><?php echo esc_html__('Connect your WordPress site to a Minpaku Suite portal to display property listings and availability calendars.', 'wp-minpaku-connector'); ?></p>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('wp_minpaku_connector_settings');
                do_settings_sections('wp-minpaku-connector');
                submit_button();
                ?>
            </form>

            <?php if ($is_configured): ?>
                <hr>
                <h2><?php echo esc_html__('Connection Test', 'wp-minpaku-connector'); ?></h2>
                <p><?php echo esc_html__('Test your connection to the Minpaku Suite portal.', 'wp-minpaku-connector'); ?></p>
                <p>
                    <button type="button" id="test-connection" class="button button-secondary">
                        <?php echo esc_html__('Test Connection', 'wp-minpaku-connector'); ?>
                    </button>
                    <span id="test-result"></span>
                </p>

                <hr>
                <h2><?php echo esc_html__('Usage', 'wp-minpaku-connector'); ?></h2>
                <p><?php echo esc_html__('Use these shortcodes to display content from your Minpaku Suite portal:', 'wp-minpaku-connector'); ?></p>

                <h3><?php echo esc_html__('Property Listings', 'wp-minpaku-connector'); ?></h3>
                <code>[minpaku_connector type="properties" limit="12" columns="3"]</code>
                <p class="description"><?php echo esc_html__('Display a grid of property listings. Parameters: limit (number of properties), columns (grid columns).', 'wp-minpaku-connector'); ?></p>

                <h3><?php echo esc_html__('Availability Calendar', 'wp-minpaku-connector'); ?></h3>
                <code>[minpaku_connector type="availability" property_id="123" months="2"]</code>
                <p class="description"><?php echo esc_html__('Display availability calendar for a specific property. Parameters: property_id (required), months (number of months to display).', 'wp-minpaku-connector'); ?></p>

                <h3><?php echo esc_html__('Property Details', 'wp-minpaku-connector'); ?></h3>
                <code>[minpaku_connector type="property" property_id="123"]</code>
                <p class="description"><?php echo esc_html__('Display detailed information for a specific property. Parameters: property_id (required).', 'wp-minpaku-connector'); ?></p>

            <?php else: ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html__('Please complete the connection settings above before using shortcodes.', 'wp-minpaku-connector'); ?></p>
                </div>
            <?php endif; ?>

            <hr>
            <h2><?php echo esc_html__('Setup Instructions', 'wp-minpaku-connector'); ?></h2>
            <ol>
                <li><?php echo esc_html__('Log in to your Minpaku Suite portal admin area.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Go to Minpaku › Settings › Connector.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Enable the connector and add your WordPress site domain to the allowed domains list.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Generate new API keys for this site.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Copy the Portal Base URL, Site ID, API Key, and Secret to the form above.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Save the settings and test the connection.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Use the shortcodes on your pages and posts to display content.', 'wp-minpaku-connector'); ?></li>
            </ol>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#test-connection').on('click', function() {
                var button = $(this);
                var result = $('#test-result');

                button.prop('disabled', true).text(wmcAdmin.strings.testing);
                result.removeClass('success error').text('');

                $.post(wmcAdmin.ajaxUrl, {
                    action: 'wmc_test_connection',
                    nonce: wmcAdmin.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        result.addClass('success').html('✓ ' + wmcAdmin.strings.success);
                        if (response.data.message) {
                            result.append('<br><small>' + response.data.message + '</small>');
                        }
                    } else {
                        result.addClass('error').html('✗ ' + (response.data.message || wmcAdmin.strings.error));
                    }
                })
                .fail(function() {
                    result.addClass('error').html('✗ ' + wmcAdmin.strings.error);
                })
                .always(function() {
                    button.prop('disabled', false).text('<?php echo esc_js(__('Test Connection', 'wp-minpaku-connector')); ?>');
                });
            });
        });
        </script>

        <style>
        #test-result.success {
            color: #00a32a;
            font-weight: bold;
            margin-left: 10px;
        }

        #test-result.error {
            color: #d63638;
            font-weight: bold;
            margin-left: 10px;
        }

        .wrap code {
            background: #f0f0f0;
            padding: 8px 12px;
            border-radius: 4px;
            display: inline-block;
            margin: 5px 0;
            font-family: Consolas, Monaco, 'Courier New', monospace;
        }

        .wrap h3 {
            margin-top: 25px;
            margin-bottom: 5px;
        }

        .wrap .description {
            font-style: italic;
            margin-top: 5px;
            margin-bottom: 15px;
        }
        </style>
        <?php
    }
}