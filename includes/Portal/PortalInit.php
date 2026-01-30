<?php
/**
 * Portal Initialization
 * Initializes owner portal system, roles, and admin integration
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class PortalInit {

    private static $instance = null;
    private $owner_subscription;
    private $owner_dashboard;

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize the portal system
     */
    private function init() {
        // Initialize roles first
        OwnerRoles::init();

        // Initialize subscription system
        $this->owner_subscription = new OwnerSubscription();

        // Initialize dashboard
        $this->owner_dashboard = new OwnerDashboard();

        // Add admin hooks
        add_action('admin_init', [$this, 'init_admin_features']);
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('user_register', [$this, 'handle_new_user_registration']);

        // Property assignment hooks
        add_action('save_post', [$this, 'handle_property_save'], 10, 2);
        add_filter('wp_insert_post_data', [$this, 'filter_property_author'], 10, 2);

        // Frontend hooks for property visibility
        add_action('pre_get_posts', [$this, 'filter_frontend_properties']);

        // User profile hooks
        add_action('show_user_profile', [$this, 'add_owner_profile_fields']);
        add_action('edit_user_profile', [$this, 'add_owner_profile_fields']);
        add_action('personal_options_update', [$this, 'save_owner_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_owner_profile_fields']);

        // Registration hooks
        add_action('wp_ajax_mcs_register_owner', [$this, 'ajax_register_owner']);
        add_action('wp_ajax_nopriv_mcs_register_owner', [$this, 'ajax_register_owner']);

        // Cron hooks
        add_action('init', [$this, 'schedule_cron_jobs']);
    }

    /**
     * Initialize admin features
     */
    public function init_admin_features() {
        // Add custom user columns
        add_filter('manage_users_columns', [$this, 'add_user_columns']);
        add_action('manage_users_custom_column', [$this, 'render_user_columns'], 10, 3);

        // Add bulk actions for users
        add_filter('bulk_actions-users', [$this, 'add_user_bulk_actions']);
        add_filter('handle_bulk_actions-users', [$this, 'handle_user_bulk_actions'], 10, 3);

        // Property management filters
        add_filter('manage_property_posts_columns', [$this, 'add_property_columns']);
        add_action('manage_property_posts_custom_column', [$this, 'render_property_columns'], 10, 2);
    }

    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Main owner management menu
        add_menu_page(
            __('Owner Management', 'minpaku-suite'),
            __('Owners', 'minpaku-suite'),
            'manage_options',
            'mcs-owner-management',
            [$this, 'render_owner_management_page'],
            'dashicons-groups',
            30
        );

        // Subscription management submenu
        add_submenu_page(
            'mcs-owner-management',
            __('Subscription Management', 'minpaku-suite'),
            __('Subscriptions', 'minpaku-suite'),
            'manage_options',
            'mcs-subscription-management',
            [$this, 'render_subscription_management_page']
        );

        // Owner registration submenu
        add_submenu_page(
            'mcs-owner-management',
            __('Owner Registration', 'minpaku-suite'),
            __('Registration', 'minpaku-suite'),
            'manage_options',
            'mcs-owner-registration',
            [$this, 'render_owner_registration_page']
        );
    }

    /**
     * Handle new user registration
     */
    public function handle_new_user_registration($user_id) {
        // Check if this should be an owner (e.g., based on registration form data)
        if (isset($_POST['register_as_owner']) && $_POST['register_as_owner'] === '1') {
            $this->convert_user_to_owner($user_id);
        }
    }

    /**
     * Convert user to owner and create Stripe customer
     */
    public function convert_user_to_owner($user_id) {
        // Convert to owner role
        if (OwnerRoles::convert_to_owner($user_id)) {
            // Create Stripe customer
            $customer_id = $this->owner_subscription->create_stripe_customer($user_id);

            if ($customer_id) {
                // Send welcome email
                $this->send_owner_welcome_email($user_id);

                // Log successful conversion
                if (class_exists('MCS_Logger')) {
                    MCS_Logger::log('INFO', 'User converted to owner with Stripe customer', [
                        'user_id' => $user_id,
                        'customer_id' => $customer_id
                    ]);
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Send welcome email to new owner
     */
    private function send_owner_welcome_email($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $subject = __('Welcome to Property Owner Portal', 'minpaku-suite');
        $message = sprintf(
            __('Welcome %s! Your property owner account has been created. You can access your dashboard at: %s', 'minpaku-suite'),
            $user->display_name,
            admin_url('admin.php?page=mcs-owner-dashboard')
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Handle property save to assign owner
     */
    public function handle_property_save($post_id, $post) {
        if ($post->post_type !== 'property') {
            return;
        }

        // Auto-assign property to current user if they're an owner
        if (OwnerRoles::is_owner() && $post->post_author === get_current_user_id()) {
            OwnerRoles::assign_property_owner($post_id, get_current_user_id());
        }

        // Handle admin assignment
        if (isset($_POST['mcs_assign_owner']) && current_user_can('manage_options')) {
            $owner_id = intval($_POST['mcs_assign_owner']);
            if ($owner_id > 0) {
                OwnerRoles::assign_property_owner($post_id, $owner_id);
            }
        }
    }

    /**
     * Filter property author assignment
     */
    public function filter_property_author($data, $postarr) {
        if ($data['post_type'] !== 'property') {
            return $data;
        }

        // If creating new property and user is an owner, assign to them
        if (empty($postarr['ID']) && OwnerRoles::is_owner()) {
            $data['post_author'] = get_current_user_id();
        }

        return $data;
    }

    /**
     * Filter frontend properties to hide suspended ones
     */
    public function filter_frontend_properties($query) {
        // This is handled by OwnerSubscription class
        // Just ensure it's initialized
        if (!is_admin() && $query->is_main_query()) {
            // Additional frontend filtering can be added here
        }
    }

    /**
     * Add user columns to admin
     */
    public function add_user_columns($columns) {
        $columns['owner_status'] = __('Owner Status', 'minpaku-suite');
        $columns['subscription_status'] = __('Subscription', 'minpaku-suite');
        $columns['properties_count'] = __('Properties', 'minpaku-suite');
        return $columns;
    }

    /**
     * Render user columns
     */
    public function render_user_columns($value, $column_name, $user_id) {
        switch ($column_name) {
            case 'owner_status':
                if (OwnerRoles::is_owner($user_id)) {
                    $status = get_user_meta($user_id, 'mcs_owner_status', true) ?: 'active';
                    echo '<span class="owner-status status-' . $status . '">' . ucfirst($status) . '</span>';
                } else {
                    echo '-';
                }
                break;

            case 'subscription_status':
                if (OwnerRoles::is_owner($user_id)) {
                    $subscription_status = $this->owner_subscription->get_subscription_status($user_id);
                    echo '<span class="subscription-status status-' . $subscription_status['status'] . '">';
                    echo ucfirst($subscription_status['status']);
                    echo '</span>';
                } else {
                    echo '-';
                }
                break;

            case 'properties_count':
                if (OwnerRoles::is_owner($user_id)) {
                    $properties = OwnerRoles::get_user_properties($user_id);
                    echo count($properties);
                } else {
                    echo '-';
                }
                break;
        }
    }

    /**
     * Add bulk actions for users
     */
    public function add_user_bulk_actions($actions) {
        $actions['make_owner'] = __('Convert to Owner', 'minpaku-suite');
        $actions['remove_owner'] = __('Remove Owner Role', 'minpaku-suite');
        return $actions;
    }

    /**
     * Handle user bulk actions
     */
    public function handle_user_bulk_actions($redirect_to, $action, $user_ids) {
        if ($action === 'make_owner') {
            foreach ($user_ids as $user_id) {
                $this->convert_user_to_owner($user_id);
            }
            $redirect_to = add_query_arg('converted_owners', count($user_ids), $redirect_to);
        } elseif ($action === 'remove_owner') {
            foreach ($user_ids as $user_id) {
                OwnerRoles::remove_owner_role($user_id);
            }
            $redirect_to = add_query_arg('removed_owners', count($user_ids), $redirect_to);
        }

        return $redirect_to;
    }

    /**
     * Add property columns
     */
    public function add_property_columns($columns) {
        $columns['owner'] = __('Owner', 'minpaku-suite');
        $columns['subscription_status'] = __('Owner Subscription', 'minpaku-suite');
        $columns['visibility'] = __('Visibility', 'minpaku-suite');
        return $columns;
    }

    /**
     * Render property columns
     */
    public function render_property_columns($column, $post_id) {
        switch ($column) {
            case 'owner':
                $post = get_post($post_id);
                if ($post) {
                    $owner = get_user_by('id', $post->post_author);
                    if ($owner) {
                        echo esc_html($owner->display_name);
                        if (OwnerRoles::is_owner($owner->ID)) {
                            echo ' <span class="owner-badge">' . __('Owner', 'minpaku-suite') . '</span>';
                        }
                    }
                }
                break;

            case 'subscription_status':
                $post = get_post($post_id);
                if ($post && OwnerRoles::is_owner($post->post_author)) {
                    $subscription_status = $this->owner_subscription->get_subscription_status($post->post_author);
                    echo '<span class="subscription-status status-' . $subscription_status['status'] . '">';
                    echo ucfirst($subscription_status['status']);
                    echo '</span>';
                } else {
                    echo '-';
                }
                break;

            case 'visibility':
                $is_suspended = get_post_meta($post_id, 'mcs_suspended', true);
                if ($is_suspended) {
                    echo '<span class="visibility-status suspended">' . __('Hidden (Suspended)', 'minpaku-suite') . '</span>';
                } else {
                    echo '<span class="visibility-status visible">' . __('Visible', 'minpaku-suite') . '</span>';
                }
                break;
        }
    }

    /**
     * Add owner profile fields
     */
    public function add_owner_profile_fields($user) {
        if (!current_user_can('manage_options') && !OwnerRoles::is_owner($user->ID)) {
            return;
        }

        $is_owner = OwnerRoles::is_owner($user->ID);
        $subscription_status = $is_owner ? $this->owner_subscription->get_subscription_status($user->ID) : null;

        echo '<h3>' . __('Property Owner Information', 'minpaku-suite') . '</h3>';
        echo '<table class="form-table">';

        // Owner status
        echo '<tr>';
        echo '<th><label>' . __('Owner Status', 'minpaku-suite') . '</label></th>';
        echo '<td>';
        if ($is_owner) {
            $status = get_user_meta($user->ID, 'mcs_owner_status', true) ?: 'active';
            echo '<span class="owner-status-' . $status . '">' . ucfirst($status) . '</span>';

            if (current_user_can('manage_options')) {
                echo '<br><label><input type="checkbox" name="remove_owner_role" value="1"> ' . __('Remove owner role', 'minpaku-suite') . '</label>';
            }
        } else {
            echo __('Not an owner', 'minpaku-suite');
            if (current_user_can('manage_options')) {
                echo '<br><label><input type="checkbox" name="make_owner" value="1"> ' . __('Convert to owner', 'minpaku-suite') . '</label>';
            }
        }
        echo '</td>';
        echo '</tr>';

        // Subscription information
        if ($is_owner && $subscription_status) {
            echo '<tr>';
            echo '<th><label>' . __('Subscription Status', 'minpaku-suite') . '</label></th>';
            echo '<td>';
            echo '<span class="subscription-status-' . $subscription_status['status'] . '">';
            echo ucfirst($subscription_status['status']);
            echo '</span>';

            if (!empty($subscription_status['subscription_id'])) {
                echo '<br><small>' . __('Subscription ID:', 'minpaku-suite') . ' ' . $subscription_status['subscription_id'] . '</small>';
            }
            echo '</td>';
            echo '</tr>';

            // Customer ID
            echo '<tr>';
            echo '<th><label>' . __('Stripe Customer ID', 'minpaku-suite') . '</label></th>';
            echo '<td>';
            echo $subscription_status['customer_id'] ?: __('Not created', 'minpaku-suite');
            echo '</td>';
            echo '</tr>';
        }

        // Properties count
        if ($is_owner) {
            $properties = OwnerRoles::get_user_properties($user->ID);
            echo '<tr>';
            echo '<th><label>' . __('Properties', 'minpaku-suite') . '</label></th>';
            echo '<td>';
            echo count($properties) . ' ' . __('properties', 'minpaku-suite');
            if (!empty($properties)) {
                echo '<ul>';
                foreach ($properties as $property) {
                    echo '<li><a href="' . admin_url('post.php?post=' . $property->ID . '&action=edit') . '">';
                    echo esc_html($property->post_title);
                    echo '</a></li>';
                }
                echo '</ul>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }

    /**
     * Save owner profile fields
     */
    public function save_owner_profile_fields($user_id) {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['make_owner']) && $_POST['make_owner'] === '1') {
            $this->convert_user_to_owner($user_id);
        }

        if (isset($_POST['remove_owner_role']) && $_POST['remove_owner_role'] === '1') {
            OwnerRoles::remove_owner_role($user_id);
        }
    }

    /**
     * AJAX handler for owner registration
     */
    public function ajax_register_owner() {
        check_ajax_referer('mcs_register_nonce', 'nonce');

        $email = sanitize_email($_POST['email'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($name) || empty($password)) {
            wp_send_json_error(__('All fields are required', 'minpaku-suite'));
        }

        if (email_exists($email)) {
            wp_send_json_error(__('Email already exists', 'minpaku-suite'));
        }

        // Create user
        $user_id = wp_create_user($email, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }

        // Update user data
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $name
        ]);

        // Convert to owner
        if ($this->convert_user_to_owner($user_id)) {
            wp_send_json_success([
                'user_id' => $user_id,
                'message' => __('Owner account created successfully', 'minpaku-suite')
            ]);
        } else {
            wp_send_json_error(__('Failed to create owner account', 'minpaku-suite'));
        }
    }

    /**
     * Render owner management page
     */
    public function render_owner_management_page() {
        $stats = OwnerRoles::get_owner_statistics();
        $subscription_stats = $this->owner_subscription->get_subscription_statistics();

        echo '<div class="wrap">';
        echo '<h1>' . __('Owner Management', 'minpaku-suite') . '</h1>';

        // Statistics overview
        echo '<div class="mcs-admin-stats">';
        echo '<div class="stat-box">';
        echo '<h3>' . __('Owners', 'minpaku-suite') . '</h3>';
        echo '<p class="stat-number">' . $stats['total_owners'] . '</p>';
        echo '<p class="stat-label">' . __('Total Owners', 'minpaku-suite') . '</p>';
        echo '</div>';

        echo '<div class="stat-box">';
        echo '<h3>' . __('Properties', 'minpaku-suite') . '</h3>';
        echo '<p class="stat-number">' . $stats['total_properties'] . '</p>';
        echo '<p class="stat-label">' . __('Total Properties', 'minpaku-suite') . '</p>';
        echo '</div>';

        echo '<div class="stat-box">';
        echo '<h3>' . __('Subscriptions', 'minpaku-suite') . '</h3>';
        echo '<p class="stat-number">' . $subscription_stats['active_subscriptions'] . '</p>';
        echo '<p class="stat-label">' . __('Active Subscriptions', 'minpaku-suite') . '</p>';
        echo '</div>';
        echo '</div>';

        // Quick actions
        echo '<div class="mcs-quick-actions">';
        echo '<h2>' . __('Quick Actions', 'minpaku-suite') . '</h2>';
        echo '<p><a href="' . admin_url('users.php') . '" class="button">' . __('Manage Users', 'minpaku-suite') . '</a></p>';
        echo '<p><a href="' . admin_url('admin.php?page=mcs-subscription-management') . '" class="button">' . __('Manage Subscriptions', 'minpaku-suite') . '</a></p>';
        echo '<p><a href="' . admin_url('edit.php?post_type=property') . '" class="button">' . __('Manage Properties', 'minpaku-suite') . '</a></p>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render subscription management page
     */
    public function render_subscription_management_page() {
        $stats = $this->owner_subscription->get_subscription_statistics();

        echo '<div class="wrap">';
        echo '<h1>' . __('Subscription Management', 'minpaku-suite') . '</h1>';

        // Statistics
        echo '<div class="mcs-subscription-stats">';
        echo '<h2>' . __('Subscription Statistics', 'minpaku-suite') . '</h2>';
        echo '<ul>';
        echo '<li>' . sprintf(__('Active: %d', 'minpaku-suite'), $stats['active_subscriptions']) . '</li>';
        echo '<li>' . sprintf(__('Warning: %d', 'minpaku-suite'), $stats['warning_subscriptions']) . '</li>';
        echo '<li>' . sprintf(__('Suspended: %d', 'minpaku-suite'), $stats['suspended_subscriptions']) . '</li>';
        echo '<li>' . sprintf(__('Cancelled: %d', 'minpaku-suite'), $stats['cancelled_subscriptions']) . '</li>';
        echo '</ul>';
        echo '</div>';

        // Subscription list would go here
        echo '<p>' . __('Detailed subscription management interface would be implemented here.', 'minpaku-suite') . '</p>';

        echo '</div>';
    }

    /**
     * Render owner registration page
     */
    public function render_owner_registration_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Owner Registration', 'minpaku-suite') . '</h1>';

        echo '<form id="mcs-admin-owner-registration">';
        wp_nonce_field('mcs_register_nonce', 'nonce');
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="owner_name">' . __('Name', 'minpaku-suite') . '</label></th>';
        echo '<td><input type="text" id="owner_name" name="name" required></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="owner_email">' . __('Email', 'minpaku-suite') . '</label></th>';
        echo '<td><input type="email" id="owner_email" name="email" required></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="owner_password">' . __('Password', 'minpaku-suite') . '</label></th>';
        echo '<td><input type="password" id="owner_password" name="password" required></td>';
        echo '</tr>';
        echo '</table>';
        echo '<p class="submit">';
        echo '<input type="submit" class="button button-primary" value="' . __('Create Owner Account', 'minpaku-suite') . '">';
        echo '</p>';
        echo '</form>';

        echo '</div>';
    }

    /**
     * Schedule cron jobs
     */
    public function schedule_cron_jobs() {
        if (!wp_next_scheduled('mcs_check_subscription_status')) {
            wp_schedule_event(time(), 'daily', 'mcs_check_subscription_status');
        }

        if (!wp_next_scheduled('mcs_process_failed_payments')) {
            wp_schedule_event(time(), 'hourly', 'mcs_process_failed_payments');
        }
    }
}