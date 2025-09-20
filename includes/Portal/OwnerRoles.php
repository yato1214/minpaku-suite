<?php
/**
 * Owner Roles and Capabilities Management
 * Handles property owner role creation, capabilities, and access control
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class OwnerRoles {

    const OWNER_ROLE = 'property_owner';
    const CAPABILITY_PREFIX = 'mcs_';

    /**
     * Initialize owner roles and capabilities
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_owner_role']);
        add_action('init', [__CLASS__, 'add_owner_capabilities']);

        // Hook into user registration
        add_action('user_register', [__CLASS__, 'maybe_assign_owner_role'], 10, 1);

        // Filter capabilities for property management
        add_filter('user_has_cap', [__CLASS__, 'filter_property_capabilities'], 10, 4);

        // Modify post query for owners
        add_action('pre_get_posts', [__CLASS__, 'filter_owner_posts']);

        // Admin interface hooks
        add_action('admin_menu', [__CLASS__, 'add_owner_admin_menu']);
        add_action('admin_init', [__CLASS__, 'handle_owner_assignment']);

        // Dashboard customization
        add_action('wp_dashboard_setup', [__CLASS__, 'customize_owner_dashboard']);
    }

    /**
     * Register the property owner role
     */
    public static function register_owner_role() {
        $capabilities = self::get_owner_capabilities();

        // Add the property owner role
        add_role(
            self::OWNER_ROLE,
            __('Property Owner', 'minpaku-suite'),
            $capabilities
        );

        // Update existing role if capabilities changed
        $role = get_role(self::OWNER_ROLE);
        if ($role) {
            foreach ($capabilities as $cap => $grant) {
                $role->add_cap($cap, $grant);
            }
        }
    }

    /**
     * Add owner capabilities to administrator role
     */
    public static function add_owner_capabilities() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $owner_capabilities = self::get_owner_capabilities();
            foreach ($owner_capabilities as $cap => $grant) {
                $admin_role->add_cap($cap, true);
            }

            // Admin-specific capabilities
            $admin_role->add_cap(self::CAPABILITY_PREFIX . 'manage_all_properties', true);
            $admin_role->add_cap(self::CAPABILITY_PREFIX . 'manage_owner_subscriptions', true);
            $admin_role->add_cap(self::CAPABILITY_PREFIX . 'view_all_reservations', true);
        }
    }

    /**
     * Get default owner capabilities
     *
     * @return array
     */
    private static function get_owner_capabilities() {
        return [
            // Basic WordPress capabilities
            'read' => true,
            'upload_files' => true,

            // Property management
            self::CAPABILITY_PREFIX . 'manage_own_properties' => true,
            self::CAPABILITY_PREFIX . 'edit_own_properties' => true,
            self::CAPABILITY_PREFIX . 'delete_own_properties' => true,
            self::CAPABILITY_PREFIX . 'publish_own_properties' => true,

            // Reservation management
            self::CAPABILITY_PREFIX . 'view_own_reservations' => true,
            self::CAPABILITY_PREFIX . 'manage_own_reservations' => true,
            self::CAPABILITY_PREFIX . 'create_internal_reservations' => true,

            // Calendar and availability
            self::CAPABILITY_PREFIX . 'manage_own_calendar' => true,
            self::CAPABILITY_PREFIX . 'sync_own_calendars' => true,

            // Billing and subscription
            self::CAPABILITY_PREFIX . 'view_own_billing' => true,
            self::CAPABILITY_PREFIX . 'manage_own_subscription' => true,

            // Reports and analytics
            self::CAPABILITY_PREFIX . 'view_own_reports' => true,

            // Settings
            self::CAPABILITY_PREFIX . 'manage_own_settings' => true,

            // Dashboard access
            self::CAPABILITY_PREFIX . 'access_owner_dashboard' => true,
        ];
    }

    /**
     * Check if user is a property owner
     *
     * @param int|WP_User $user
     * @return bool
     */
    public static function is_owner($user = null) {
        if (!$user) {
            $user = wp_get_current_user();
        }

        if (is_int($user)) {
            $user = get_user_by('id', $user);
        }

        if (!$user || !($user instanceof WP_User)) {
            return false;
        }

        return in_array(self::OWNER_ROLE, $user->roles);
    }

    /**
     * Get properties owned by user
     *
     * @param int $user_id
     * @return array
     */
    public static function get_user_properties($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $args = [
            'post_type' => 'property',
            'author' => $user_id,
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'mcs_owner_id',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ]
        ];

        return get_posts($args);
    }

    /**
     * Assign owner to property
     *
     * @param int $property_id
     * @param int $owner_id
     * @return bool
     */
    public static function assign_property_owner($property_id, $owner_id) {
        // Verify the user is an owner
        if (!self::is_owner($owner_id)) {
            return false;
        }

        // Update post author
        $result = wp_update_post([
            'ID' => $property_id,
            'post_author' => $owner_id
        ]);

        if (!is_wp_error($result)) {
            // Store additional owner metadata
            update_post_meta($property_id, 'mcs_owner_id', $owner_id);
            update_post_meta($property_id, 'mcs_owner_assigned_date', current_time('mysql'));

            do_action('mcs/owner/property_assigned', $property_id, $owner_id);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Property assigned to owner', [
                    'property_id' => $property_id,
                    'owner_id' => $owner_id
                ]);
            }

            return true;
        }

        return false;
    }

    /**
     * Check if user can manage specific property
     *
     * @param int $property_id
     * @param int $user_id
     * @return bool
     */
    public static function can_manage_property($property_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Admins can manage all properties
        if (user_can($user_id, 'administrator') ||
            user_can($user_id, self::CAPABILITY_PREFIX . 'manage_all_properties')) {
            return true;
        }

        // Owners can only manage their own properties
        if (self::is_owner($user_id)) {
            $property = get_post($property_id);
            if ($property && $property->post_author == $user_id) {
                return true;
            }

            // Check owner metadata as fallback
            $owner_id = get_post_meta($property_id, 'mcs_owner_id', true);
            return $owner_id == $user_id;
        }

        return false;
    }

    /**
     * Filter post capabilities for property owners
     *
     * @param array $allcaps
     * @param array $caps
     * @param array $args
     * @param WP_User $user
     * @return array
     */
    public static function filter_property_capabilities($allcaps, $caps, $args, $user) {
        if (!self::is_owner($user)) {
            return $allcaps;
        }

        // Handle property-specific capabilities
        if (isset($args[0]) && isset($args[2])) {
            $cap = $args[0];
            $post_id = $args[2];

            $property_caps = [
                'edit_post',
                'delete_post',
                'publish_post',
                'edit_published_posts',
                'delete_published_posts'
            ];

            if (in_array($cap, $property_caps)) {
                $post = get_post($post_id);
                if ($post && $post->post_type === 'property') {
                    if (self::can_manage_property($post_id, $user->ID)) {
                        $allcaps[$cap] = true;
                    } else {
                        $allcaps[$cap] = false;
                    }
                }
            }
        }

        return $allcaps;
    }

    /**
     * Filter posts query for owners to show only their properties
     *
     * @param WP_Query $query
     */
    public static function filter_owner_posts($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        global $pagenow;

        if ($pagenow === 'edit.php' &&
            isset($_GET['post_type']) &&
            $_GET['post_type'] === 'property' &&
            self::is_owner()) {

            $query->set('author', get_current_user_id());
        }
    }

    /**
     * Maybe assign owner role during user registration
     *
     * @param int $user_id
     */
    public static function maybe_assign_owner_role($user_id) {
        // This would be called when an owner signs up
        // The actual assignment logic would depend on the registration flow

        // For now, we'll provide a hook for other plugins to use
        do_action('mcs/owner/user_registered', $user_id);
    }

    /**
     * Convert existing user to owner
     *
     * @param int $user_id
     * @return bool
     */
    public static function convert_to_owner($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        // Add owner role
        $user->add_role(self::OWNER_ROLE);

        // Initialize owner metadata
        update_user_meta($user_id, 'mcs_owner_status', 'active');
        update_user_meta($user_id, 'mcs_owner_created_date', current_time('mysql'));

        // Trigger owner creation hook
        do_action('mcs/owner/created', $user_id);

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'User converted to property owner', [
                'user_id' => $user_id,
                'email' => $user->user_email
            ]);
        }

        return true;
    }

    /**
     * Remove owner role from user
     *
     * @param int $user_id
     * @return bool
     */
    public static function remove_owner_role($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        // Remove owner role
        $user->remove_role(self::OWNER_ROLE);

        // Update status
        update_user_meta($user_id, 'mcs_owner_status', 'removed');

        // Trigger removal hook
        do_action('mcs/owner/removed', $user_id);

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Owner role removed from user', [
                'user_id' => $user_id,
                'email' => $user->user_email
            ]);
        }

        return true;
    }

    /**
     * Get owner statistics
     *
     * @return array
     */
    public static function get_owner_statistics() {
        $users = get_users(['role' => self::OWNER_ROLE]);
        $total_owners = count($users);

        $active_owners = 0;
        $suspended_owners = 0;
        $properties_count = 0;

        foreach ($users as $user) {
            $status = get_user_meta($user->ID, 'mcs_owner_status', true);

            if ($status === 'active') {
                $active_owners++;
            } elseif ($status === 'suspended') {
                $suspended_owners++;
            }

            $properties = self::get_user_properties($user->ID);
            $properties_count += count($properties);
        }

        return [
            'total_owners' => $total_owners,
            'active_owners' => $active_owners,
            'suspended_owners' => $suspended_owners,
            'total_properties' => $properties_count,
            'avg_properties_per_owner' => $total_owners > 0 ? round($properties_count / $total_owners, 2) : 0
        ];
    }

    /**
     * Add owner management to admin menu
     */
    public static function add_owner_admin_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_submenu_page(
            'users.php',
            __('Property Owners', 'minpaku-suite'),
            __('Property Owners', 'minpaku-suite'),
            'manage_options',
            'mcs-owners',
            [__CLASS__, 'render_owner_admin_page']
        );
    }

    /**
     * Render owner admin page
     */
    public static function render_owner_admin_page() {
        $owners = get_users(['role' => self::OWNER_ROLE]);
        $stats = self::get_owner_statistics();

        echo '<div class="wrap">';
        echo '<h1>' . __('Property Owners', 'minpaku-suite') . '</h1>';

        // Statistics
        echo '<div class="mcs-owner-stats">';
        echo '<h2>' . __('Statistics', 'minpaku-suite') . '</h2>';
        echo '<p>' . sprintf(__('Total Owners: %d', 'minpaku-suite'), $stats['total_owners']) . '</p>';
        echo '<p>' . sprintf(__('Active Owners: %d', 'minpaku-suite'), $stats['active_owners']) . '</p>';
        echo '<p>' . sprintf(__('Total Properties: %d', 'minpaku-suite'), $stats['total_properties']) . '</p>';
        echo '</div>';

        // Owner assignment form
        echo '<div class="mcs-owner-assignment">';
        echo '<h2>' . __('Convert User to Owner', 'minpaku-suite') . '</h2>';
        echo '<form method="post" action="">';
        wp_nonce_field('mcs_assign_owner', 'mcs_owner_nonce');
        echo '<label for="user_id">' . __('Select User:', 'minpaku-suite') . '</label>';
        wp_dropdown_users([
            'name' => 'user_id',
            'id' => 'user_id',
            'role__not_in' => [self::OWNER_ROLE]
        ]);
        echo '<input type="submit" name="convert_to_owner" class="button button-primary" value="' . __('Convert to Owner', 'minpaku-suite') . '">';
        echo '</form>';
        echo '</div>';

        // Owners list
        echo '<div class="mcs-owners-list">';
        echo '<h2>' . __('Current Owners', 'minpaku-suite') . '</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Name', 'minpaku-suite') . '</th>';
        echo '<th>' . __('Email', 'minpaku-suite') . '</th>';
        echo '<th>' . __('Properties', 'minpaku-suite') . '</th>';
        echo '<th>' . __('Status', 'minpaku-suite') . '</th>';
        echo '<th>' . __('Actions', 'minpaku-suite') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($owners as $owner) {
            $properties = self::get_user_properties($owner->ID);
            $status = get_user_meta($owner->ID, 'mcs_owner_status', true) ?: 'active';

            echo '<tr>';
            echo '<td>' . esc_html($owner->display_name) . '</td>';
            echo '<td>' . esc_html($owner->user_email) . '</td>';
            echo '<td>' . count($properties) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>';
            echo '<a href="' . admin_url('user-edit.php?user_id=' . $owner->ID) . '" class="button button-small">' . __('Edit', 'minpaku-suite') . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Handle owner assignment from admin
     */
    public static function handle_owner_assignment() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['convert_to_owner']) &&
            isset($_POST['user_id']) &&
            wp_verify_nonce($_POST['mcs_owner_nonce'], 'mcs_assign_owner')) {

            $user_id = intval($_POST['user_id']);
            if (self::convert_to_owner($user_id)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' . __('User converted to property owner successfully.', 'minpaku-suite') . '</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . __('Failed to convert user to property owner.', 'minpaku-suite') . '</p></div>';
                });
            }
        }
    }

    /**
     * Customize dashboard for owners
     */
    public static function customize_owner_dashboard() {
        if (!self::is_owner()) {
            return;
        }

        // Remove default widgets that owners don't need
        $widgets_to_remove = [
            'dashboard_primary',
            'dashboard_secondary',
            'dashboard_plugins',
            'dashboard_recent_comments'
        ];

        foreach ($widgets_to_remove as $widget) {
            remove_meta_box($widget, 'dashboard', 'side');
            remove_meta_box($widget, 'dashboard', 'normal');
        }

        // Add owner-specific widgets
        wp_add_dashboard_widget(
            'mcs_owner_properties',
            __('My Properties', 'minpaku-suite'),
            [__CLASS__, 'render_properties_widget']
        );

        wp_add_dashboard_widget(
            'mcs_owner_recent_reservations',
            __('Recent Reservations', 'minpaku-suite'),
            [__CLASS__, 'render_reservations_widget']
        );
    }

    /**
     * Render properties widget for owner dashboard
     */
    public static function render_properties_widget() {
        $properties = self::get_user_properties();

        echo '<ul>';
        foreach ($properties as $property) {
            echo '<li>';
            echo '<a href="' . admin_url('post.php?post=' . $property->ID . '&action=edit') . '">';
            echo esc_html($property->post_title);
            echo '</a>';
            echo ' (' . esc_html($property->post_status) . ')';
            echo '</li>';
        }

        if (empty($properties)) {
            echo '<li>' . __('No properties found.', 'minpaku-suite') . '</li>';
        }

        echo '</ul>';

        echo '<p><a href="' . admin_url('post-new.php?post_type=property') . '" class="button button-primary">';
        echo __('Add New Property', 'minpaku-suite');
        echo '</a></p>';
    }

    /**
     * Render reservations widget for owner dashboard
     */
    public static function render_reservations_widget() {
        $properties = self::get_user_properties();
        $property_ids = wp_list_pluck($properties, 'ID');

        if (empty($property_ids)) {
            echo '<p>' . __('No properties found.', 'minpaku-suite') . '</p>';
            return;
        }

        // This would need to be adapted based on your reservation system
        echo '<p>' . __('Recent reservation activity will be displayed here.', 'minpaku-suite') . '</p>';

        // Link to full dashboard
        echo '<p><a href="' . admin_url('admin.php?page=mcs-owner-dashboard') . '" class="button">';
        echo __('View Full Dashboard', 'minpaku-suite');
        echo '</a></p>';
    }
}