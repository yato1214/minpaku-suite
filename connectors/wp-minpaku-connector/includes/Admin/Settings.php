<?php
/**
 * Admin Settings Class
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class MPC_Admin_Settings {

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('wp_ajax_mpc_test_connection', array(__CLASS__, 'ajax_test_connection'));
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
            'mpc_connection_section',
            __('Portal Connection', 'wp-minpaku-connector'),
            array(__CLASS__, 'connection_section_callback'),
            'wp-minpaku-connector'
        );

        add_settings_field(
            'portal_url',
            __('Portal Base URL', 'wp-minpaku-connector'),
            array(__CLASS__, 'portal_url_callback'),
            'wp-minpaku-connector',
            'mpc_connection_section'
        );

        add_settings_field(
            'site_id',
            __('Site ID', 'wp-minpaku-connector'),
            array(__CLASS__, 'site_id_callback'),
            'wp-minpaku-connector',
            'mpc_connection_section'
        );

        add_settings_field(
            'api_key',
            __('API Key', 'wp-minpaku-connector'),
            array(__CLASS__, 'api_key_callback'),
            'wp-minpaku-connector',
            'mpc_connection_section'
        );

        add_settings_field(
            'secret',
            __('Secret', 'wp-minpaku-connector'),
            array(__CLASS__, 'secret_callback'),
            'wp-minpaku-connector',
            'mpc_connection_section'
        );
    }

    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['portal_url'])) {
            $normalized_url = self::normalize_portal_url($input['portal_url']);
            if ($normalized_url !== false) {
                $sanitized['portal_url'] = $normalized_url;
            } else {
                // Keep the original value and let validation show error
                $sanitized['portal_url'] = esc_url_raw(trim($input['portal_url']));
                \add_settings_error(
                    'wp_minpaku_connector_settings',
                    'invalid_portal_url',
                    \__('Portal URL is invalid. Only http(s) development domains (.local/.test/localhost/with ports) are allowed.', 'wp-minpaku-connector'),
                    'error'
                );
            }
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
     * Normalize and validate portal URL
     */
    public static function normalize_portal_url($url) {
        if (empty($url)) {
            return '';
        }

        // Normalize input: trim, replace full-width spaces, escape, remove trailing slash
        $url = trim($url ?? '');
        $url = preg_replace('/\x{3000}/u', ' ', $url); // Full-width space to half-width
        $url = esc_url_raw($url);
        $url = untrailingslashit($url);

        // Validate with WordPress function
        if (!\wp_http_validate_url($url)) {
            return false;
        }

        // Parse URL components
        $parts = \wp_parse_url($url);
        if (!$parts) {
            return false;
        }

        // Check scheme
        $allowed_schemes = ['http', 'https'];
        if (!in_array($parts['scheme'] ?? '', $allowed_schemes, true)) {
            return false;
        }

        // Check host
        $host = $parts['host'] ?? '';
        if (empty($host)) {
            return false;
        }

        $allowed_dev_tlds = ['local', 'test', 'localhost', 'localdomain'];
        $ok_host = false;

        if (preg_match('/^[^\.]+$/', $host)) {
            // Host without dots (localhost-style)
            $ok_host = in_array($host, ['localhost'], true);
        } else {
            // Host with dots - check TLD
            $tld = substr(strrchr($host, '.'), 1);
            if ($tld) {
                // Allow development TLDs or standard domain TLDs
                $ok_host = in_array($tld, $allowed_dev_tlds, true) ||
                          preg_match('/^[a-z]{2,63}$/i', $tld);
            }
        }

        if (!$ok_host) {
            return false;
        }

        return $url;
    }

    /**
     * AJAX handler for connection test
     */
    public static function ajax_test_connection() {
        check_ajax_referer('mpc_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wp-minpaku-connector'));
        }

        // Log connection test attempt
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Connection test initiated by user: ' . get_current_user_id());
        }

        $api = new \MinpakuConnector\Client\MPC_Client_Api();
        $result = $api->test_connection();

        // Enhanced error handling for specific cases
        if (!$result['success']) {
            $message = $result['message'];
            $error_type = 'general';

            // Check for specific error patterns
            if (strpos($message, '401') !== false || strpos($message, 'Unauthorized') !== false) {
                $message = __('Authentication failed. Please check your API Key and Secret.', 'wp-minpaku-connector');
                $error_type = 'auth';
            } elseif (strpos($message, '403') !== false || strpos($message, 'Forbidden') !== false) {
                $message = __('Access denied. Please check that your domain is added to the allowed domains list in the portal.', 'wp-minpaku-connector');
                $error_type = 'permission';
            } elseif (strpos($message, '404') !== false || strpos($message, 'Not Found') !== false) {
                $message = __('Portal endpoint not found. Please check your Portal Base URL.', 'wp-minpaku-connector');
                $error_type = 'url';
            } elseif (strpos($message, '408') !== false || strpos($message, 'timeout') !== false) {
                $message = __('Connection timeout. Please check your portal server and network connectivity.', 'wp-minpaku-connector');
                $error_type = 'timeout';
            } elseif (strpos($message, '5') === 0 || strpos($message, 'Internal Server Error') !== false) {
                $message = __('Portal server error. Please contact your portal administrator.', 'wp-minpaku-connector');
                $error_type = 'server';
            } elseif (strpos($message, 'time') !== false && strpos($message, 'sync') !== false) {
                $message = __('Server time synchronization issue. Please check your server clock or contact your hosting provider.', 'wp-minpaku-connector');
                $error_type = 'time';
            }

            // Log detailed error for debugging
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-connector] Connection test failed (' . $error_type . '): ' . $result['message']);
            }

            wp_send_json_error(array(
                'message' => $message,
                'type' => $error_type,
                'original' => $result['message']
            ));
        }

        // Success case - check for time sync warnings
        $warnings = array();
        if (isset($result['data']['server_time'])) {
            $server_time = strtotime($result['data']['server_time']);
            $local_time = time();
            $time_diff = abs($server_time - $local_time);

            if ($time_diff > 300) { // More than 5 minutes difference
                $warnings[] = sprintf(
                    __('Warning: Server time difference detected (%d seconds). Consider checking time synchronization.', 'wp-minpaku-connector'),
                    $time_diff
                );
            }
        }

        // Log successful connection
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Connection test successful to: ' . (isset($result['data']['portal_url']) ? $result['data']['portal_url'] : 'unknown'));
        }

        wp_send_json_success(array(
            'message' => $result['message'],
            'warnings' => $warnings,
            'data' => $result['data']
        ));
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
        $settings = \WP_Minpaku_Connector::get_settings();
        echo '<input type="url" id="portal_url" name="wp_minpaku_connector_settings[portal_url]" value="' . esc_attr($settings['portal_url']) . '" class="regular-text" placeholder="https://your-portal.com" required />';
        echo '<p class="description">' . esc_html__('The base URL of your Minpaku Suite portal (e.g., https://yoursite.com)', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * Site ID field callback
     */
    public static function site_id_callback() {
        $settings = \WP_Minpaku_Connector::get_settings();
        echo '<input type="text" id="site_id" name="wp_minpaku_connector_settings[site_id]" value="' . esc_attr($settings['site_id']) . '" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('The Site ID generated in your portal connector settings.', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * API Key field callback
     */
    public static function api_key_callback() {
        $settings = \WP_Minpaku_Connector::get_settings();
        echo '<input type="text" id="api_key" name="wp_minpaku_connector_settings[api_key]" value="' . esc_attr($settings['api_key']) . '" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('The API Key generated in your portal connector settings.', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * Secret field callback
     */
    public static function secret_callback() {
        $settings = \WP_Minpaku_Connector::get_settings();
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

        $settings = \WP_Minpaku_Connector::get_settings();
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

                button.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'wp-minpaku-connector')); ?>');
                result.removeClass('success error warning').html('<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span><?php echo esc_js(__('Testing connection...', 'wp-minpaku-connector')); ?>');

                $.post(ajaxurl, {
                    action: 'mpc_test_connection',
                    nonce: '<?php echo wp_create_nonce('mpc_test_connection'); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        var html = '<span style="color: #00a32a; font-weight: bold;">✓ ' + response.data.message + '</span>';

                        // Add warnings if any
                        if (response.data.warnings && response.data.warnings.length > 0) {
                            html += '<br><span style="color: #dba617; font-size: 0.9em;">⚠ ' + response.data.warnings.join('<br>⚠ ') + '</span>';
                        }

                        result.removeClass('error warning').addClass('success').html(html);

                        // Show additional success info
                        if (response.data.data && response.data.data.version) {
                            result.append('<br><small style="color: #666;"><?php echo esc_js(__('Portal version:', 'wp-minpaku-connector')); ?> ' + response.data.data.version + '</small>');
                        }
                    } else {
                        var errorHtml = '<span style="color: #d63638; font-weight: bold;">✗ ' + response.data.message + '</span>';

                        // Add specific error guidance
                        if (response.data.type === 'auth') {
                            errorHtml += '<br><small style="color: #666;"><?php echo esc_js(__('Check: API Key format, Secret key, Site ID', 'wp-minpaku-connector')); ?></small>';
                        } else if (response.data.type === 'permission') {
                            errorHtml += '<br><small style="color: #666;"><?php echo esc_js(__('Check: Allowed domains list in portal settings', 'wp-minpaku-connector')); ?></small>';
                        } else if (response.data.type === 'url') {
                            errorHtml += '<br><small style="color: #666;"><?php echo esc_js(__('Check: Portal Base URL spelling and accessibility', 'wp-minpaku-connector')); ?></small>';
                        } else if (response.data.type === 'timeout') {
                            errorHtml += '<br><small style="color: #666;"><?php echo esc_js(__('Check: Network connectivity and portal server status', 'wp-minpaku-connector')); ?></small>';
                        }

                        result.removeClass('success warning').addClass('error').html(errorHtml);

                        // Log error for debugging
                        if (window.console && response.data.original) {
                            console.log('WMC Connection Test Error:', response.data.original);
                        }
                    }
                })
                .fail(function(xhr, status, error) {
                    var errorMsg = '<?php echo esc_js(__('Network error occurred. Please try again.', 'wp-minpaku-connector')); ?>';
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMsg = xhr.responseJSON.data;
                    }
                    result.removeClass('success warning').addClass('error').html('<span style="color: #d63638; font-weight: bold;">✗ ' + errorMsg + '</span>');

                    // Log error for debugging
                    if (window.console) {
                        console.log('WMC AJAX Error:', xhr, status, error);
                    }
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