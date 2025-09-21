<?php

namespace MinpakuSuite;

if (!defined('ABSPATH')) {
    exit;
}

class Bootstrap
{
    public static function init()
    {
        add_action('plugins_loaded', [__CLASS__, 'i18n'], 5);
        add_action('init', [__CLASS__, 'register_cpts'], 10);
        add_action('init', [__CLASS__, 'register_booking_components'], 15);
        add_action('init', [__CLASS__, 'register_ui_components'], 15);
        add_action('init', [__CLASS__, 'register_portal_components'], 15);
        add_action('acf/init', [__CLASS__, 'register_acf'], 10);
        add_action('admin_menu', [__CLASS__, 'register_menu'], 9);
        add_action('rest_api_init', [__CLASS__, 'register_rest'], 10);
    }

    public static function activate()
    {
        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        flush_rewrite_rules();
    }

    public static function i18n()
    {
        // Text domain already loaded in main plugin file
    }

    public static function register_cpts()
    {
        try {
            $cpt_file = MCS_PATH . 'includes/cpt-property.php';
            if (file_exists($cpt_file)) {
                require_once $cpt_file;
            }
        } catch (Exception $e) {
            error_log('Minpaku Suite CPT Error: ' . $e->getMessage());
        }
    }

    public static function register_acf()
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        try {
            $acf_file = MCS_PATH . 'includes/Acf/RegisterFields.php';
            if (file_exists($acf_file)) {
                require_once $acf_file;
                if (class_exists('MinpakuSuite\Acf\RegisterFields')) {
                    \MinpakuSuite\Acf\RegisterFields::init();
                }
            }
        } catch (Exception $e) {
            error_log('Minpaku Suite ACF Error: ' . $e->getMessage());
        }
    }

    public static function register_menu()
    {
        try {
            add_menu_page(
                __('Minpaku', 'minpaku-suite'),
                __('Minpaku', 'minpaku-suite'),
                'manage_options',
                'minpaku-suite',
                [__CLASS__, 'render_dashboard'],
                'dashicons-admin-multisite',
                25
            );

            add_submenu_page(
                'minpaku-suite',
                __('Properties', 'minpaku-suite'),
                __('Properties', 'minpaku-suite'),
                'edit_posts',
                'edit.php?post_type=mcs_property'
            );

            add_submenu_page(
                'minpaku-suite',
                __('Bookings', 'minpaku-suite'),
                __('Bookings', 'minpaku-suite'),
                'edit_posts',
                'edit.php?post_type=mcs_booking'
            );

            // Add Owner Portal submenu for users with portal access
            if (class_exists('MinpakuSuite\Portal\OwnerRoles') &&
                \MinpakuSuite\Portal\OwnerRoles::user_can_access_portal()) {
                add_submenu_page(
                    'minpaku-suite',
                    __('Owner Portal', 'minpaku-suite'),
                    __('Owner Portal', 'minpaku-suite'),
                    'read',
                    'mcs-owner-portal',
                    [__CLASS__, 'render_owner_portal']
                );
            }
        } catch (Exception $e) {
            error_log('Minpaku Suite Menu Error: ' . $e->getMessage());
        }
    }

    public static function render_dashboard()
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(__('Minpaku Suite', 'minpaku-suite')) . '</h1>';
        echo '<p>' . esc_html(__('Welcome to Minpaku Suite dashboard.', 'minpaku-suite')) . '</p>';
        echo '<h2>' . esc_html(__('Quick Links', 'minpaku-suite')) . '</h2>';
        echo '<ul>';
        echo '<li><a href="' . admin_url('edit.php?post_type=mcs_property') . '">' . esc_html(__('Manage Properties', 'minpaku-suite')) . '</a></li>';
        echo '<li><a href="' . admin_url('edit.php?post_type=mcs_booking') . '">' . esc_html(__('Manage Bookings', 'minpaku-suite')) . '</a></li>';
        echo '</ul>';
        echo '</div>';
    }

    public static function render_owner_portal()
    {
        // Render the owner portal using the shortcode
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(__('Owner Portal', 'minpaku-suite')) . '</h1>';

        if (class_exists('MinpakuSuite\Portal\OwnerDashboard')) {
            // Use the shortcode to render the dashboard
            echo do_shortcode('[mcs_owner_dashboard]');
        } else {
            echo '<p class="notice notice-error">' . esc_html(__('Owner portal is not available.', 'minpaku-suite')) . '</p>';
        }

        echo '</div>';
    }

    public static function register_booking_components()
    {
        try {
            // Register booking admin components
            $metabox_file = MCS_PATH . 'includes/Booking/AdminMetabox.php';
            if (file_exists($metabox_file)) {
                require_once $metabox_file;
                if (class_exists('MinpakuSuite\Booking\AdminMetabox')) {
                    \MinpakuSuite\Booking\AdminMetabox::init();
                }
            }

            $columns_file = MCS_PATH . 'includes/Booking/ListColumns.php';
            if (file_exists($columns_file)) {
                require_once $columns_file;
                if (class_exists('MinpakuSuite\Booking\ListColumns')) {
                    \MinpakuSuite\Booking\ListColumns::init();
                }
            }

            $quicklinks_file = MCS_PATH . 'includes/Booking/AdminQuickLinks.php';
            if (file_exists($quicklinks_file)) {
                require_once $quicklinks_file;
                if (class_exists('MinpakuSuite\Booking\AdminQuickLinks')) {
                    \MinpakuSuite\Booking\AdminQuickLinks::init();
                }
            }

            $preset_file = MCS_PATH . 'includes/Booking/AdminNewPreset.php';
            if (file_exists($preset_file)) {
                require_once $preset_file;
                if (class_exists('MinpakuSuite\Booking\AdminNewPreset')) {
                    \MinpakuSuite\Booking\AdminNewPreset::init();
                }
            }
        } catch (Exception $e) {
            error_log('Minpaku Suite Booking Components Error: ' . $e->getMessage());
        }
    }

    public static function register_ui_components()
    {
        try {
            // Register UI components
            $calendar_file = MCS_PATH . 'includes/UI/AvailabilityCalendar.php';
            if (file_exists($calendar_file)) {
                require_once $calendar_file;
                if (class_exists('MinpakuSuite\UI\AvailabilityCalendar')) {
                    \MinpakuSuite\UI\AvailabilityCalendar::init();
                }
            }

            // Register availability service
            $service_file = MCS_PATH . 'includes/Availability/AvailabilityService.php';
            if (file_exists($service_file)) {
                require_once $service_file;
            }
        } catch (Exception $e) {
            error_log('Minpaku Suite UI Components Error: ' . $e->getMessage());
        }
    }

    public static function register_portal_components()
    {
        try {
            // Register Portal components
            $roles_file = MCS_PATH . 'includes/Portal/OwnerRoles.php';
            if (file_exists($roles_file)) {
                require_once $roles_file;
                if (class_exists('MinpakuSuite\Portal\OwnerRoles')) {
                    \MinpakuSuite\Portal\OwnerRoles::init();
                }
            }

            $helpers_file = MCS_PATH . 'includes/Portal/OwnerHelpers.php';
            if (file_exists($helpers_file)) {
                require_once $helpers_file;
                if (class_exists('MinpakuSuite\Portal\OwnerHelpers')) {
                    \MinpakuSuite\Portal\OwnerHelpers::init();
                }
            }

            $dashboard_file = MCS_PATH . 'includes/Portal/OwnerDashboard.php';
            if (file_exists($dashboard_file)) {
                require_once $dashboard_file;
                if (class_exists('MinpakuSuite\Portal\OwnerDashboard')) {
                    \MinpakuSuite\Portal\OwnerDashboard::init();
                }
            }
        } catch (Exception $e) {
            error_log('Minpaku Suite Portal Components Error: ' . $e->getMessage());
        }
    }

    public static function register_rest()
    {
        try {
            // Register API components
            $api_file = MCS_PATH . 'includes/Api/OwnerApiController.php';
            if (file_exists($api_file)) {
                require_once $api_file;
                if (class_exists('MinpakuSuite\Api\OwnerApiController')) {
                    \MinpakuSuite\Api\OwnerApiController::init();
                }
            }
        } catch (Exception $e) {
            error_log('Minpaku Suite REST API Error: ' . $e->getMessage());
        }
    }
}