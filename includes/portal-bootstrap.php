<?php
/**
 * Portal System Bootstrap
 * Initializes the complete owner portal system
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once __DIR__ . '/Portal/OwnerRoles.php';
require_once __DIR__ . '/Portal/OwnerSubscription.php';
require_once __DIR__ . '/Portal/OwnerDashboard.php';
require_once __DIR__ . '/Portal/PortalInit.php';

// Initialize the portal system
add_action('plugins_loaded', function() {
    // Check if Stripe is available
    if (!class_exists('\Stripe\Stripe')) {
        // Load Stripe SDK if not already loaded
        // This would normally be loaded via Composer or included manually
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning">';
            echo '<p>' . __('Stripe PHP SDK is required for subscription management. Please install it via Composer.', 'minpaku-suite') . '</p>';
            echo '</div>';
        });
    }

    // Initialize the portal system
    PortalInit::getInstance();
});

// Activation hook to create necessary database structure
register_activation_hook(__FILE__, function() {
    // Flush rewrite rules to ensure custom post types work
    flush_rewrite_rules();

    // Create default owner role
    OwnerRoles::register_owner_role();

    // Schedule cron jobs
    if (!wp_next_scheduled('mcs_check_subscription_status')) {
        wp_schedule_event(time(), 'daily', 'mcs_check_subscription_status');
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clear scheduled cron jobs
    wp_clear_scheduled_hook('mcs_check_subscription_status');
    wp_clear_scheduled_hook('mcs_process_failed_payments');
});

// Add CSS for admin styling
add_action('admin_head', function() {
    echo '<style>
    .mcs-owner-dashboard .mcs-dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .mcs-dashboard-widget {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 20px;
    }

    .mcs-dashboard-widget h2 {
        margin-top: 0;
        color: #1d2327;
    }

    .mcs-property-stats {
        display: flex;
        gap: 20px;
    }

    .stat-item {
        text-align: center;
    }

    .stat-number {
        display: block;
        font-size: 2em;
        font-weight: bold;
        color: #2271b1;
    }

    .stat-label {
        display: block;
        font-size: 0.9em;
        color: #646970;
    }

    .owner-status-active { color: #008a00; }
    .owner-status-suspended { color: #d63384; }
    .owner-status-cancelled { color: #6c757d; }

    .subscription-status-active { color: #008a00; }
    .subscription-status-warning { color: #ffc107; }
    .subscription-status-suspended { color: #d63384; }
    .subscription-status-cancelled { color: #6c757d; }

    .status-suspended { color: #d63384; font-weight: bold; }
    .status-active { color: #008a00; font-weight: bold; }

    .owner-badge {
        background: #2271b1;
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.8em;
    }

    .mcs-admin-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .stat-box {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 20px;
        text-align: center;
    }

    .stat-box h3 {
        margin-top: 0;
        color: #1d2327;
    }

    .stat-box .stat-number {
        font-size: 2.5em;
        font-weight: bold;
        color: #2271b1;
        margin: 10px 0;
    }

    .visibility-status.suspended {
        color: #d63384;
        font-weight: bold;
    }

    .visibility-status.visible {
        color: #008a00;
    }

    .mcs-billing-status, .mcs-subscription-management {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 20px;
        margin: 20px 0;
    }

    .notice.notice-warning.mcs-subscription-alert {
        border-left-color: #ffc107;
    }

    .notice.notice-error.mcs-subscription-alert {
        border-left-color: #d63384;
    }
    </style>';
});