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
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_styles'], 10);
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
            $show_owner_portal = false;
            if (class_exists('MinpakuSuite\Portal\OwnerRoles')) {
                try {
                    $show_owner_portal = \MinpakuSuite\Portal\OwnerRoles::user_can_access_portal();
                } catch (Exception $e) {
                    error_log('Owner Portal Menu Check Error: ' . $e->getMessage());
                    $show_owner_portal = current_user_can('manage_options'); // Fallback to admin check
                }
            } else {
                $show_owner_portal = current_user_can('manage_options'); // Fallback to admin check
            }

            if ($show_owner_portal) {
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
        // Load AdminDashboardService
        $service_file = MCS_PATH . 'includes/Admin/AdminDashboardService.php';
        if (file_exists($service_file)) {
            require_once $service_file;
        }

        // Use template if available, otherwise fallback
        $template_file = MCS_PATH . 'templates/admin/dashboard.php';
        if (file_exists($template_file) && class_exists('MinpakuSuite\Admin\AdminDashboardService')) {
            include $template_file;
        } else {
            // Fallback to simple dashboard
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
    }

    public static function render_owner_portal()
    {
        // Load Portal components first
        self::register_portal_components();

        // Check if user has portal access first
        if (!is_user_logged_in()) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html(__('Owner Portal', 'minpaku-suite')) . '</h1>';
            echo '<p class="notice notice-error">' . esc_html(__('Please log in to access the owner dashboard.', 'minpaku-suite')) . '</p>';
            echo '</div>';
            return;
        }

        // Check access with safer method
        $has_access = false;
        if (class_exists('MinpakuSuite\Portal\OwnerRoles')) {
            try {
                $has_access = \MinpakuSuite\Portal\OwnerRoles::user_can_access_portal();
            } catch (Exception $e) {
                error_log('Owner Portal Access Check Error: ' . $e->getMessage());
                $has_access = current_user_can('manage_options'); // Fallback to admin check
            }
        } else {
            $has_access = current_user_can('manage_options'); // Fallback to admin check
        }

        if (!$has_access) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html(__('Owner Portal', 'minpaku-suite')) . '</h1>';
            echo '<p class="notice notice-error">' . esc_html(__('You do not have permission to access the owner dashboard.', 'minpaku-suite')) . '</p>';
            echo '</div>';
            return;
        }

        // Use the admin dashboard template for consistent UI
        $service_file = MCS_PATH . 'includes/Admin/AdminDashboardService.php';
        if (file_exists($service_file)) {
            require_once $service_file;
        }

        $template_file = MCS_PATH . 'templates/admin/dashboard.php';
        if (file_exists($template_file) && class_exists('MinpakuSuite\Admin\AdminDashboardService')) {
            // Override the title for Owner Portal context
            add_filter('gettext', function($translation, $text, $domain) {
                if ($domain === 'minpaku-suite' && $text === 'Minpaku Suite') {
                    return __('Owner Portal', 'minpaku-suite');
                }
                return $translation;
            }, 10, 3);

            include $template_file;

            // Remove the filter after rendering
            remove_all_filters('gettext');
        } else {
            // Fallback display
            echo '<div class="wrap">';
            echo '<h1>' . esc_html(__('Owner Portal', 'minpaku-suite')) . '</h1>';
            echo '<p class="notice notice-error">' . esc_html(__('Owner portal template is not available.', 'minpaku-suite')) . '</p>';
            echo '</div>';
        }
    }

    public static function enqueue_admin_styles($hook_suffix)
    {
        // Only enqueue on our plugin pages - include both 'minpaku-suite' and 'mcs-owner-portal' slugs
        $our_pages = [
            'toplevel_page_minpaku-suite',
            'minpaku_page_mcs-owner-portal'
        ];

        if (in_array($hook_suffix, $our_pages)) {
            $css_file = MCS_URL . 'assets/admin.css';
            $css_path = MCS_PATH . 'assets/admin.css';

            if (file_exists($css_path)) {
                wp_enqueue_style(
                    'minpaku-admin',
                    $css_file,
                    [],
                    filemtime($css_path)
                );
            }
        }
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