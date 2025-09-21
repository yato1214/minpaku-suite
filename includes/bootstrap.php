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

    public static function register_rest()
    {
        // REST API initialization will be implemented in future milestones
    }
}