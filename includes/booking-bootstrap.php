<?php
/**
 * Booking System Bootstrap
 * Initializes the MinPaku Suite booking state machine and ledger system
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load booking system components
require_once __DIR__ . '/Install/Migrations.php';
require_once __DIR__ . '/Booking/Booking.php';
require_once __DIR__ . '/Booking/BookingRepository.php';
require_once __DIR__ . '/Booking/BookingLedger.php';
require_once __DIR__ . '/Services/BookingService.php';
require_once __DIR__ . '/Admin/BookingAdminUI.php';

// Initialize booking system on plugins loaded
add_action('plugins_loaded', function() {
    // Run database migrations
    Migrations::run();

    // Initialize booking repository (registers post type)
    new BookingRepository();

    // Initialize admin UI
    if (is_admin()) {
        new BookingAdminUI();
    }

    // Log booking system initialization
    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('INFO', 'Booking system initialized', [
            'db_version' => Migrations::getCurrentVersion(),
            'post_type_registered' => 'minpaku_booking',
            'admin_ui_loaded' => is_admin()
        ]);
    }
}, 25);

// Add capability on user role setup
add_action('init', function() {
    // Add booking management capability to administrators
    $admin_role = get_role('administrator');
    if ($admin_role && !$admin_role->has_cap('manage_minpaku')) {
        $admin_role->add_cap('manage_minpaku');
    }
}, 30);

// Plugin activation hook
register_activation_hook(__FILE__, function() {
    // Ensure migrations run on activation
    Migrations::run();

    // Flush rewrite rules to register custom post type
    flush_rewrite_rules();

    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('INFO', 'Booking system activated', [
            'db_version' => Migrations::getCurrentVersion()
        ]);
    }
});

// Plugin deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();

    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('INFO', 'Booking system deactivated');
    }
});

// Uninstall hook (for complete removal)
if (defined('WP_UNINSTALL_PLUGIN')) {
    add_action('uninstall_' . plugin_basename(__FILE__), function() {
        // Remove all booking posts
        $bookings = get_posts([
            'post_type' => 'minpaku_booking',
            'numberposts' => -1,
            'post_status' => 'any'
        ]);

        foreach ($bookings as $booking) {
            wp_delete_post($booking->ID, true);
        }

        // Drop custom tables
        Migrations::dropTables();

        // Remove capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->remove_cap('manage_minpaku');
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Booking system uninstalled');
        }
    });
}