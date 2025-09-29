<?php
/**
 * Admin Menu for WP Minpaku Connector
 *
 * @package WP_Minpaku_Connector
 */

namespace MpkConnector\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Menu Class
 */
class Menu {

    /**
     * Initialize the admin menu
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_pages']);
    }

    /**
     * Add menu pages to WordPress admin
     */
    public static function add_menu_pages() {
        // Main menu page
        add_menu_page(
            __('Minpaku Connector', 'wp-minpaku-connector'),
            __('Connector', 'wp-minpaku-connector'),
            'manage_options',
            'mpk-connector',
            [__CLASS__, 'dashboard_page'],
            'dashicons-networking',
            30
        );

        // Dashboard submenu (same as main page)
        add_submenu_page(
            'mpk-connector',
            __('Dashboard', 'wp-minpaku-connector'),
            __('Dashboard', 'wp-minpaku-connector'),
            'manage_options',
            'mpk-connector',
            [__CLASS__, 'dashboard_page']
        );

        // Settings submenu
        add_submenu_page(
            'mpk-connector',
            __('Connector Settings', 'wp-minpaku-connector'),
            __('Settings', 'wp-minpaku-connector'),
            'manage_options',
            'mpk-connector-settings',
            [__CLASS__, 'settings_page']
        );
    }

    /**
     * Render the dashboard page
     */
    public static function dashboard_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-minpaku-connector'));
        }

        $settings = SettingsPage::get_settings();
        $is_configured = !empty($settings['api_base_url']) && !empty($settings['api_key']);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Minpaku Connector Dashboard', 'wp-minpaku-connector'); ?></h1>

            <?php if (!$is_configured) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('Please configure your API settings to start using the connector.', 'wp-minpaku-connector'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=mpk-connector-settings')); ?>" class="button button-primary">
                            <?php esc_html_e('Go to Settings', 'wp-minpaku-connector'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="mpk-dashboard-grid">
                <div class="mpk-dashboard-card">
                    <h3><?php esc_html_e('Connection Status', 'wp-minpaku-connector'); ?></h3>
                    <div id="mpk-connection-status">
                        <?php if ($is_configured) : ?>
                            <span class="mpk-status-unknown"><?php esc_html_e('Click to test connection', 'wp-minpaku-connector'); ?></span>
                        <?php else : ?>
                            <span class="mpk-status-error"><?php esc_html_e('Not configured', 'wp-minpaku-connector'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_configured) : ?>
                        <button type="button" id="mpk-test-connection-dashboard" class="button button-secondary">
                            <?php esc_html_e('Test Connection', 'wp-minpaku-connector'); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="mpk-dashboard-card">
                    <h3><?php esc_html_e('Quick Actions', 'wp-minpaku-connector'); ?></h3>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=mpk-connector-settings')); ?>" class="button">
                            <?php esc_html_e('Settings', 'wp-minpaku-connector'); ?>
                        </a>
                    </p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=page')); ?>" class="button">
                            <?php esc_html_e('Create Availability Page', 'wp-minpaku-connector'); ?>
                        </a>
                    </p>
                </div>

                <div class="mpk-dashboard-card">
                    <h3><?php esc_html_e('Usage', 'wp-minpaku-connector'); ?></h3>
                    <p><?php esc_html_e('Use the shortcode to display availability forms:', 'wp-minpaku-connector'); ?></p>
                    <code>[mpk_availability_form]</code>
                    <p><?php esc_html_e('You can also specify a property ID:', 'wp-minpaku-connector'); ?></p>
                    <code>[mpk_availability_form property_id="123"]</code>
                </div>

                <div class="mpk-dashboard-card">
                    <h3><?php esc_html_e('System Information', 'wp-minpaku-connector'); ?></h3>
                    <ul>
                        <li><strong><?php esc_html_e('Plugin Version:', 'wp-minpaku-connector'); ?></strong> <?php echo esc_html(WP_MINPAKU_CONNECTOR_VERSION); ?></li>
                        <li><strong><?php esc_html_e('WordPress Version:', 'wp-minpaku-connector'); ?></strong> <?php echo esc_html(get_bloginfo('version')); ?></li>
                        <li><strong><?php esc_html_e('PHP Version:', 'wp-minpaku-connector'); ?></strong> <?php echo esc_html(PHP_VERSION); ?></li>
                        <li><strong><?php esc_html_e('API Base URL:', 'wp-minpaku-connector'); ?></strong>
                            <?php echo $settings['api_base_url'] ? esc_html($settings['api_base_url']) : '<em>' . esc_html__('Not set', 'wp-minpaku-connector') . '</em>'; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if ($is_configured) : ?>
        <script>
        jQuery(document).ready(function($) {
            $('#mpk-test-connection-dashboard').on('click', function() {
                var $button = $(this);
                var $status = $('#mpk-connection-status');

                $button.prop('disabled', true).text('<?php esc_html_e('Testing...', 'wp-minpaku-connector'); ?>');
                $status.html('<span class="mpk-status-testing"><?php esc_html_e('Testing connection...', 'wp-minpaku-connector'); ?></span>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpk_test_connection',
                        nonce: '<?php echo wp_create_nonce('mpk_test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<span class="mpk-status-success"><?php esc_html_e('Connected', 'wp-minpaku-connector'); ?></span>');
                        } else {
                            $status.html('<span class="mpk-status-error"><?php esc_html_e('Connection failed', 'wp-minpaku-connector'); ?></span>');
                        }
                    },
                    error: function() {
                        $status.html('<span class="mpk-status-error"><?php esc_html_e('Connection failed', 'wp-minpaku-connector'); ?></span>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php esc_html_e('Test Connection', 'wp-minpaku-connector'); ?>');
                    }
                });
            });
        });
        </script>
        <?php endif; ?>

        <style>
        .mpk-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .mpk-dashboard-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .mpk-dashboard-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .mpk-dashboard-card ul {
            margin: 0;
            padding-left: 20px;
        }

        .mpk-dashboard-card code {
            display: block;
            margin: 10px 0;
            padding: 10px;
            background: #f1f1f1;
            border-radius: 3px;
        }

        .mpk-status-success { color: #46b450; font-weight: bold; }
        .mpk-status-error { color: #dc3232; font-weight: bold; }
        .mpk-status-unknown { color: #666; }
        .mpk-status-testing { color: #0073aa; }
        </style>
        <?php
    }

    /**
     * Render the settings page
     */
    public static function settings_page() {
        SettingsPage::render_page();
    }
}