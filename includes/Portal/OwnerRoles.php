<?php
/**
 * Owner Roles Management
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Portal;

if (!defined('ABSPATH')) {
    exit;
}

class OwnerRoles
{
    public static function init(): void
    {
        add_action('plugins_loaded', [__CLASS__, 'register_role'], 10);
        register_activation_hook(MINPAKU_SUITE_PLUGIN_FILE, [__CLASS__, 'on_activation']);
    }

    /**
     * Register or update the mcs_owner role
     */
    public static function register_role(): void
    {
        $role = get_role('mcs_owner');

        $capabilities = [
            'read' => true,
            'edit_mcs_property' => true,
            'read_mcs_property' => true,
            'delete_mcs_property' => true,
            'edit_mcs_properties' => true,
            'publish_mcs_properties' => true,
            'edit_mcs_booking' => true,
            'read_mcs_booking' => true,
            'edit_mcs_bookings' => true,
            'read_mcs_bookings' => true,
        ];

        if (!$role) {
            // Create new role
            add_role(
                'mcs_owner',
                __('Property Owner', 'minpaku-suite'),
                $capabilities
            );
        } else {
            // Update existing role capabilities
            foreach ($capabilities as $cap => $grant) {
                $role->add_cap($cap, $grant);
            }
        }
    }

    /**
     * Handle plugin activation
     */
    public static function on_activation(): void
    {
        self::register_role();
        flush_rewrite_rules();
    }

    /**
     * Check if user has owner capabilities
     */
    public static function user_can_access_portal(int $user_id = 0): bool
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        // Allow administrators and mcs_owner role
        return user_can($user, 'manage_options') || user_can($user, 'edit_mcs_properties');
    }

    /**
     * Get owner role capabilities
     */
    public static function get_owner_capabilities(): array
    {
        return [
            'read' => true,
            'edit_mcs_property' => true,
            'read_mcs_property' => true,
            'delete_mcs_property' => true,
            'edit_mcs_properties' => true,
            'publish_mcs_properties' => true,
            'edit_mcs_booking' => true,
            'read_mcs_booking' => true,
            'edit_mcs_bookings' => true,
            'read_mcs_bookings' => true,
        ];
    }
}