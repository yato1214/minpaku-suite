<?php
/**
 * Owner Dashboard for Property Management Portal
 * Provides owners with access to reservations, calendar, and billing
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class OwnerDashboard {

    private $owner_subscription;

    /**
     * Constructor
     */
    public function __construct() {
        $this->owner_subscription = new OwnerSubscription();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_dashboard_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_scripts']);
        add_action('wp_ajax_mcs_get_property_data', [$this, 'ajax_get_property_data']);
        add_action('wp_ajax_mcs_get_reservation_data', [$this, 'ajax_get_reservation_data']);
        add_action('wp_ajax_mcs_update_property_availability', [$this, 'ajax_update_availability']);

        // Hide admin menu items for owners
        add_action('admin_menu', [$this, 'customize_owner_menu'], 999);

        // Customize admin bar for owners
        add_action('wp_before_admin_bar_render', [$this, 'customize_admin_bar']);
    }

    /**
     * Add dashboard menu for owners
     */
    public function add_dashboard_menu() {
        if (!OwnerRoles::is_owner()) {
            return;
        }

        // Main dashboard page
        add_menu_page(
            __('Owner Dashboard', 'minpaku-suite'),
            __('Dashboard', 'minpaku-suite'),
            'mcs_access_owner_dashboard',
            'mcs-owner-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-admin-home',
            2
        );

        // Properties submenu
        add_submenu_page(
            'mcs-owner-dashboard',
            __('My Properties', 'minpaku-suite'),
            __('Properties', 'minpaku-suite'),
            'mcs_manage_own_properties',
            'mcs-owner-properties',
            [$this, 'render_properties_page']
        );

        // Reservations submenu
        add_submenu_page(
            'mcs-owner-dashboard',
            __('Reservations', 'minpaku-suite'),
            __('Reservations', 'minpaku-suite'),
            'mcs_view_own_reservations',
            'mcs-owner-reservations',
            [$this, 'render_reservations_page']
        );

        // Calendar submenu
        add_submenu_page(
            'mcs-owner-dashboard',
            __('Calendar & Availability', 'minpaku-suite'),
            __('Calendar', 'minpaku-suite'),
            'mcs_manage_own_calendar',
            'mcs-owner-calendar',
            [$this, 'render_calendar_page']
        );

        // Billing submenu
        add_submenu_page(
            'mcs-owner-dashboard',
            __('Billing & Subscription', 'minpaku-suite'),
            __('Billing', 'minpaku-suite'),
            'mcs_view_own_billing',
            'mcs-owner-billing',
            [$this, 'render_billing_page']
        );

        // Reports submenu
        add_submenu_page(
            'mcs-owner-dashboard',
            __('Reports', 'minpaku-suite'),
            __('Reports', 'minpaku-suite'),
            'mcs_view_own_reports',
            'mcs-owner-reports',
            [$this, 'render_reports_page']
        );

        // Settings submenu
        add_submenu_page(
            'mcs-owner-dashboard',
            __('Settings', 'minpaku-suite'),
            __('Settings', 'minpaku-suite'),
            'mcs_manage_own_settings',
            'mcs-owner-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue dashboard scripts and styles
     */
    public function enqueue_dashboard_scripts($hook) {
        if (strpos($hook, 'mcs-owner-') === false) {
            return;
        }

        wp_enqueue_script('mcs-owner-dashboard', plugins_url('assets/js/owner-dashboard.js', dirname(dirname(__FILE__))), ['jquery'], '1.0.0', true);
        wp_enqueue_style('mcs-owner-dashboard', plugins_url('assets/css/owner-dashboard.css', dirname(dirname(__FILE__))), [], '1.0.0');

        // Localize script with data
        wp_localize_script('mcs-owner-dashboard', 'mcsOwnerDashboard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mcs_owner_dashboard_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'minpaku-suite'),
                'error' => __('An error occurred', 'minpaku-suite'),
                'confirm' => __('Are you sure?', 'minpaku-suite')
            ]
        ]);
    }

    /**
     * Render main dashboard page
     */
    public function render_dashboard_page() {
        $user_id = get_current_user_id();
        $properties = OwnerRoles::get_user_properties($user_id);
        $subscription_status = $this->owner_subscription->get_subscription_status($user_id);

        echo '<div class="wrap mcs-owner-dashboard">';
        echo '<h1>' . __('Owner Dashboard', 'minpaku-suite') . '</h1>';

        // Subscription status alert
        if ($subscription_status['status'] !== OwnerSubscription::STATUS_ACTIVE) {
            $this->render_subscription_alert($subscription_status);
        }

        echo '<div class="mcs-dashboard-grid">';

        // Properties overview
        echo '<div class="mcs-dashboard-widget">';
        echo '<h2>' . __('Properties Overview', 'minpaku-suite') . '</h2>';
        echo '<div class="mcs-property-stats">';
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . count($properties) . '</span>';
        echo '<span class="stat-label">' . __('Total Properties', 'minpaku-suite') . '</span>';
        echo '</div>';

        $published_count = count(array_filter($properties, function($p) { return $p->post_status === 'publish'; }));
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . $published_count . '</span>';
        echo '<span class="stat-label">' . __('Published', 'minpaku-suite') . '</span>';
        echo '</div>';
        echo '</div>';

        echo '<p><a href="' . admin_url('admin.php?page=mcs-owner-properties') . '" class="button button-primary">';
        echo __('Manage Properties', 'minpaku-suite') . '</a></p>';
        echo '</div>';

        // Recent reservations
        echo '<div class="mcs-dashboard-widget">';
        echo '<h2>' . __('Recent Reservations', 'minpaku-suite') . '</h2>';
        $this->render_recent_reservations($properties);
        echo '<p><a href="' . admin_url('admin.php?page=mcs-owner-reservations') . '" class="button">';
        echo __('View All Reservations', 'minpaku-suite') . '</a></p>';
        echo '</div>';

        // Upcoming events
        echo '<div class="mcs-dashboard-widget">';
        echo '<h2>' . __('Upcoming Events', 'minpaku-suite') . '</h2>';
        $this->render_upcoming_events($properties);
        echo '<p><a href="' . admin_url('admin.php?page=mcs-owner-calendar') . '" class="button">';
        echo __('View Calendar', 'minpaku-suite') . '</a></p>';
        echo '</div>';

        // Billing summary
        echo '<div class="mcs-dashboard-widget">';
        echo '<h2>' . __('Billing Summary', 'minpaku-suite') . '</h2>';
        $this->render_billing_summary($subscription_status);
        echo '<p><a href="' . admin_url('admin.php?page=mcs-owner-billing') . '" class="button">';
        echo __('View Billing Details', 'minpaku-suite') . '</a></p>';
        echo '</div>';

        echo '</div>'; // .mcs-dashboard-grid
        echo '</div>'; // .wrap
    }

    /**
     * Render subscription status alert
     */
    private function render_subscription_alert($subscription_status) {
        $status = $subscription_status['status'];
        $class = 'notice-error';
        $message = '';

        switch ($status) {
            case OwnerSubscription::STATUS_WARNING:
                $class = 'notice-warning';
                $message = __('There is an issue with your subscription payment. Please update your payment method.', 'minpaku-suite');
                break;
            case OwnerSubscription::STATUS_SUSPENDED:
                $message = __('Your subscription has been suspended. Your properties are no longer visible on the website.', 'minpaku-suite');
                break;
            case OwnerSubscription::STATUS_CANCELLED:
                $message = __('Your subscription has been cancelled. Your properties are no longer visible on the website.', 'minpaku-suite');
                break;
        }

        if ($message) {
            echo '<div class="notice ' . $class . '">';
            echo '<p>' . $message . '</p>';
            echo '<p><a href="' . admin_url('admin.php?page=mcs-owner-billing') . '" class="button button-primary">';
            echo __('Manage Subscription', 'minpaku-suite') . '</a></p>';
            echo '</div>';
        }
    }

    /**
     * Render properties page
     */
    public function render_properties_page() {
        $user_id = get_current_user_id();
        $properties = OwnerRoles::get_user_properties($user_id);

        echo '<div class="wrap">';
        echo '<h1>' . __('My Properties', 'minpaku-suite') . '</h1>';

        echo '<p><a href="' . admin_url('post-new.php?post_type=property') . '" class="button button-primary">';
        echo __('Add New Property', 'minpaku-suite') . '</a></p>';

        if (empty($properties)) {
            echo '<p>' . __('You don\'t have any properties yet.', 'minpaku-suite') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . __('Property Name', 'minpaku-suite') . '</th>';
            echo '<th>' . __('Status', 'minpaku-suite') . '</th>';
            echo '<th>' . __('Reservations', 'minpaku-suite') . '</th>';
            echo '<th>' . __('Actions', 'minpaku-suite') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($properties as $property) {
                $reservation_count = $this->get_property_reservation_count($property->ID);
                $is_suspended = get_post_meta($property->ID, 'mcs_suspended', true);

                echo '<tr>';
                echo '<td><strong>' . esc_html($property->post_title) . '</strong></td>';
                echo '<td>';
                if ($is_suspended) {
                    echo '<span class="status-suspended">' . __('Suspended', 'minpaku-suite') . '</span>';
                } else {
                    echo '<span class="status-' . $property->post_status . '">' . ucfirst($property->post_status) . '</span>';
                }
                echo '</td>';
                echo '<td>' . $reservation_count . '</td>';
                echo '<td>';
                echo '<a href="' . admin_url('post.php?post=' . $property->ID . '&action=edit') . '" class="button button-small">' . __('Edit', 'minpaku-suite') . '</a> ';
                echo '<a href="' . get_permalink($property->ID) . '" class="button button-small" target="_blank">' . __('View', 'minpaku-suite') . '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * Render reservations page
     */
    public function render_reservations_page() {
        $user_id = get_current_user_id();
        $properties = OwnerRoles::get_user_properties($user_id);
        $property_ids = wp_list_pluck($properties, 'ID');

        echo '<div class="wrap">';
        echo '<h1>' . __('Reservations', 'minpaku-suite') . '</h1>';

        if (empty($property_ids)) {
            echo '<p>' . __('No properties found.', 'minpaku-suite') . '</p>';
            return;
        }

        // Filter form
        echo '<form method="get" class="mcs-reservations-filter">';
        echo '<input type="hidden" name="page" value="mcs-owner-reservations">';
        echo '<select name="property_id">';
        echo '<option value="">' . __('All Properties', 'minpaku-suite') . '</option>';
        foreach ($properties as $property) {
            $selected = selected($_GET['property_id'] ?? '', $property->ID, false);
            echo '<option value="' . $property->ID . '"' . $selected . '>' . esc_html($property->post_title) . '</option>';
        }
        echo '</select>';

        echo '<select name="status">';
        echo '<option value="">' . __('All Statuses', 'minpaku-suite') . '</option>';
        echo '<option value="upcoming"' . selected($_GET['status'] ?? '', 'upcoming', false) . '>' . __('Upcoming', 'minpaku-suite') . '</option>';
        echo '<option value="current"' . selected($_GET['status'] ?? '', 'current', false) . '>' . __('Current', 'minpaku-suite') . '</option>';
        echo '<option value="past"' . selected($_GET['status'] ?? '', 'past', false) . '>' . __('Past', 'minpaku-suite') . '</option>';
        echo '</select>';

        echo '<input type="submit" class="button" value="' . __('Filter', 'minpaku-suite') . '">';
        echo '</form>';

        // Reservations table
        $this->render_reservations_table($property_ids);

        echo '</div>';
    }

    /**
     * Render calendar page
     */
    public function render_calendar_page() {
        $user_id = get_current_user_id();
        $properties = OwnerRoles::get_user_properties($user_id);

        echo '<div class="wrap">';
        echo '<h1>' . __('Calendar & Availability', 'minpaku-suite') . '</h1>';

        if (empty($properties)) {
            echo '<p>' . __('No properties found.', 'minpaku-suite') . '</p>';
            return;
        }

        // Property selector
        echo '<div class="mcs-calendar-controls">';
        echo '<label for="property-select">' . __('Select Property:', 'minpaku-suite') . '</label>';
        echo '<select id="property-select">';
        foreach ($properties as $property) {
            echo '<option value="' . $property->ID . '">' . esc_html($property->post_title) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Calendar container
        echo '<div id="mcs-calendar-container">';
        echo '<div id="mcs-calendar"></div>';
        echo '</div>';

        // Booking form modal
        echo '<div id="mcs-booking-modal" style="display: none;">';
        echo '<div class="modal-content">';
        echo '<h3>' . __('Add Internal Booking', 'minpaku-suite') . '</h3>';
        echo '<form id="mcs-internal-booking-form">';
        echo '<label>' . __('Check-in Date:', 'minpaku-suite') . '<input type="date" name="checkin_date" required></label>';
        echo '<label>' . __('Check-out Date:', 'minpaku-suite') . '<input type="date" name="checkout_date" required></label>';
        echo '<label>' . __('Guest Name:', 'minpaku-suite') . '<input type="text" name="guest_name"></label>';
        echo '<label>' . __('Notes:', 'minpaku-suite') . '<textarea name="notes"></textarea></label>';
        echo '<div class="modal-buttons">';
        echo '<button type="submit" class="button button-primary">' . __('Add Booking', 'minpaku-suite') . '</button>';
        echo '<button type="button" class="button" onclick="closeMcsModal()">' . __('Cancel', 'minpaku-suite') . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render billing page
     */
    public function render_billing_page() {
        $user_id = get_current_user_id();
        $subscription_status = $this->owner_subscription->get_subscription_status($user_id);

        echo '<div class="wrap">';
        echo '<h1>' . __('Billing & Subscription', 'minpaku-suite') . '</h1>';

        // Current subscription status
        echo '<div class="mcs-billing-status">';
        echo '<h2>' . __('Subscription Status', 'minpaku-suite') . '</h2>';
        echo '<p><strong>' . __('Status:', 'minpaku-suite') . '</strong> ';

        switch ($subscription_status['status']) {
            case OwnerSubscription::STATUS_ACTIVE:
                echo '<span class="status-active">' . __('Active', 'minpaku-suite') . '</span>';
                break;
            case OwnerSubscription::STATUS_WARNING:
                echo '<span class="status-warning">' . __('Payment Issue', 'minpaku-suite') . '</span>';
                break;
            case OwnerSubscription::STATUS_SUSPENDED:
                echo '<span class="status-suspended">' . __('Suspended', 'minpaku-suite') . '</span>';
                break;
            case OwnerSubscription::STATUS_CANCELLED:
                echo '<span class="status-cancelled">' . __('Cancelled', 'minpaku-suite') . '</span>';
                break;
        }
        echo '</p>';

        if (!empty($subscription_status['current_period_end'])) {
            $end_date = date('Y-m-d', $subscription_status['current_period_end']);
            echo '<p><strong>' . __('Next Billing Date:', 'minpaku-suite') . '</strong> ' . $end_date . '</p>';
        }
        echo '</div>';

        // Subscription management
        echo '<div class="mcs-subscription-management">';
        echo '<h2>' . __('Manage Subscription', 'minpaku-suite') . '</h2>';

        if (empty($subscription_status['subscription_id'])) {
            // No subscription - show signup form
            $this->render_subscription_signup_form();
        } else {
            // Has subscription - show management options
            $this->render_subscription_management_form($subscription_status);
        }
        echo '</div>';

        // Payment history
        echo '<div class="mcs-payment-history">';
        echo '<h2>' . __('Payment History', 'minpaku-suite') . '</h2>';
        $this->render_payment_history($subscription_status);
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render reports page
     */
    public function render_reports_page() {
        $user_id = get_current_user_id();
        $properties = OwnerRoles::get_user_properties($user_id);

        echo '<div class="wrap">';
        echo '<h1>' . __('Reports', 'minpaku-suite') . '</h1>';

        if (empty($properties)) {
            echo '<p>' . __('No properties found.', 'minpaku-suite') . '</p>';
            return;
        }

        // Date range selector
        echo '<div class="mcs-report-controls">';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="mcs-owner-reports">';
        echo '<label>' . __('From:', 'minpaku-suite') . '<input type="date" name="from_date" value="' . ($_GET['from_date'] ?? date('Y-m-01')) . '"></label>';
        echo '<label>' . __('To:', 'minpaku-suite') . '<input type="date" name="to_date" value="' . ($_GET['to_date'] ?? date('Y-m-t')) . '"></label>';
        echo '<input type="submit" class="button" value="' . __('Generate Report', 'minpaku-suite') . '">';
        echo '</form>';
        echo '</div>';

        // Report content
        $from_date = $_GET['from_date'] ?? date('Y-m-01');
        $to_date = $_GET['to_date'] ?? date('Y-m-t');

        $this->render_property_performance_report($properties, $from_date, $to_date);

        echo '</div>';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Owner Settings', 'minpaku-suite') . '</h1>';

        echo '<form method="post" action="options.php">';
        settings_fields('mcs_owner_settings');
        do_settings_sections('mcs_owner_settings');

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . __('Email Notifications', 'minpaku-suite') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="mcs_owner_notifications[new_reservations]" value="1"> ' . __('New Reservations', 'minpaku-suite') . '</label><br>';
        echo '<label><input type="checkbox" name="mcs_owner_notifications[cancellations]" value="1"> ' . __('Cancellations', 'minpaku-suite') . '</label><br>';
        echo '<label><input type="checkbox" name="mcs_owner_notifications[payment_issues]" value="1"> ' . __('Payment Issues', 'minpaku-suite') . '</label>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        submit_button();
        echo '</form>';

        echo '</div>';
    }

    /**
     * Get property reservation count
     */
    private function get_property_reservation_count($property_id) {
        $booked_slots = get_post_meta($property_id, 'mcs_booked_slots', true);
        return is_array($booked_slots) ? count($booked_slots) : 0;
    }

    /**
     * Render recent reservations widget
     */
    private function render_recent_reservations($properties) {
        $property_ids = wp_list_pluck($properties, 'ID');

        if (empty($property_ids)) {
            echo '<p>' . __('No properties found.', 'minpaku-suite') . '</p>';
            return;
        }

        $recent_reservations = $this->get_recent_reservations($property_ids, 5);

        if (empty($recent_reservations)) {
            echo '<p>' . __('No recent reservations.', 'minpaku-suite') . '</p>';
            return;
        }

        echo '<ul class="mcs-recent-reservations">';
        foreach ($recent_reservations as $reservation) {
            echo '<li>';
            echo '<strong>' . esc_html($reservation['property_title']) . '</strong><br>';
            echo date('M j, Y', $reservation['start']) . ' - ' . date('M j, Y', $reservation['end']);
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Get recent reservations for properties
     */
    private function get_recent_reservations($property_ids, $limit = 10) {
        $reservations = [];

        foreach ($property_ids as $property_id) {
            $booked_slots = get_post_meta($property_id, 'mcs_booked_slots', true);
            if (!is_array($booked_slots)) continue;

            $property_title = get_the_title($property_id);

            foreach ($booked_slots as $slot) {
                $reservations[] = [
                    'property_id' => $property_id,
                    'property_title' => $property_title,
                    'start' => $slot[0],
                    'end' => $slot[1],
                    'source' => $slot[2] ?? 'unknown'
                ];
            }
        }

        // Sort by start date descending
        usort($reservations, function($a, $b) {
            return $b['start'] - $a['start'];
        });

        return array_slice($reservations, 0, $limit);
    }

    /**
     * Render upcoming events widget
     */
    private function render_upcoming_events($properties) {
        $property_ids = wp_list_pluck($properties, 'ID');
        $upcoming_events = $this->get_upcoming_events($property_ids, 5);

        if (empty($upcoming_events)) {
            echo '<p>' . __('No upcoming events.', 'minpaku-suite') . '</p>';
            return;
        }

        echo '<ul class="mcs-upcoming-events">';
        foreach ($upcoming_events as $event) {
            echo '<li>';
            echo '<strong>' . esc_html($event['property_title']) . '</strong><br>';
            echo date('M j, Y', $event['start']) . ' - ' . __('Check-in', 'minpaku-suite');
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Get upcoming events
     */
    private function get_upcoming_events($property_ids, $limit = 10) {
        $events = [];
        $now = current_time('timestamp');

        foreach ($property_ids as $property_id) {
            $booked_slots = get_post_meta($property_id, 'mcs_booked_slots', true);
            if (!is_array($booked_slots)) continue;

            $property_title = get_the_title($property_id);

            foreach ($booked_slots as $slot) {
                if ($slot[0] > $now) { // Future events only
                    $events[] = [
                        'property_id' => $property_id,
                        'property_title' => $property_title,
                        'start' => $slot[0],
                        'end' => $slot[1]
                    ];
                }
            }
        }

        // Sort by start date ascending
        usort($events, function($a, $b) {
            return $a['start'] - $b['start'];
        });

        return array_slice($events, 0, $limit);
    }

    /**
     * Render billing summary widget
     */
    private function render_billing_summary($subscription_status) {
        echo '<div class="mcs-billing-summary">';
        echo '<p><strong>' . __('Status:', 'minpaku-suite') . '</strong> ';

        switch ($subscription_status['status']) {
            case OwnerSubscription::STATUS_ACTIVE:
                echo '<span class="status-active">' . __('Active', 'minpaku-suite') . '</span>';
                break;
            default:
                echo '<span class="status-warning">' . __('Needs Attention', 'minpaku-suite') . '</span>';
                break;
        }
        echo '</p>';

        if (!empty($subscription_status['current_period_end'])) {
            $days_until_billing = ceil(($subscription_status['current_period_end'] - current_time('timestamp')) / DAY_IN_SECONDS);
            echo '<p>' . sprintf(__('Next billing in %d days', 'minpaku-suite'), $days_until_billing) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Customize admin menu for owners
     */
    public function customize_owner_menu() {
        if (!OwnerRoles::is_owner()) {
            return;
        }

        // Remove menu items owners don't need
        $items_to_remove = [
            'themes.php',
            'plugins.php',
            'tools.php',
            'options-general.php',
            'edit-comments.php'
        ];

        foreach ($items_to_remove as $item) {
            remove_menu_page($item);
        }

        // Remove submenu items
        remove_submenu_page('index.php', 'update-core.php');
    }

    /**
     * Customize admin bar for owners
     */
    public function customize_admin_bar() {
        if (!OwnerRoles::is_owner()) {
            return;
        }

        global $wp_admin_bar;

        // Remove items owners don't need
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('updates');
        $wp_admin_bar->remove_node('comments');
        $wp_admin_bar->remove_node('new-content');
    }

    /**
     * AJAX handler for getting property data
     */
    public function ajax_get_property_data() {
        check_ajax_referer('mcs_owner_dashboard_nonce', 'nonce');

        if (!current_user_can('mcs_view_own_reservations')) {
            wp_die('Unauthorized');
        }

        $property_id = intval($_POST['property_id']);

        if (!OwnerRoles::can_manage_property($property_id)) {
            wp_die('Unauthorized');
        }

        $booked_slots = get_post_meta($property_id, 'mcs_booked_slots', true);
        if (!is_array($booked_slots)) {
            $booked_slots = [];
        }

        wp_send_json_success([
            'property_id' => $property_id,
            'booked_slots' => $booked_slots
        ]);
    }

    /**
     * Render reservations table
     */
    private function render_reservations_table($property_ids) {
        $reservations = $this->get_reservations_for_properties($property_ids);

        if (empty($reservations)) {
            echo '<p>' . __('No reservations found.', 'minpaku-suite') . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Property', 'minpaku-suite') . '</th>';
        echo '<th>' . __('Check-in', 'minpaku-suite') . '</th>';
        echo '<th>' . __('Check-out', 'minpaku-suite') . '</th>';
        echo '<th>' . __('Status', 'minpaku-suite') . '</th>';
        echo '<th>' . __('Source', 'minpaku-suite') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($reservations as $reservation) {
            echo '<tr>';
            echo '<td>' . esc_html($reservation['property_title']) . '</td>';
            echo '<td>' . date('Y-m-d', $reservation['start']) . '</td>';
            echo '<td>' . date('Y-m-d', $reservation['end']) . '</td>';
            echo '<td>' . $this->get_reservation_status($reservation) . '</td>';
            echo '<td>' . esc_html($reservation['source']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Get reservations for properties
     */
    private function get_reservations_for_properties($property_ids) {
        $reservations = [];

        foreach ($property_ids as $property_id) {
            $booked_slots = get_post_meta($property_id, 'mcs_booked_slots', true);
            if (!is_array($booked_slots)) continue;

            $property_title = get_the_title($property_id);

            foreach ($booked_slots as $slot) {
                $reservations[] = [
                    'property_id' => $property_id,
                    'property_title' => $property_title,
                    'start' => $slot[0],
                    'end' => $slot[1],
                    'source' => $slot[2] ?? 'unknown'
                ];
            }
        }

        return $reservations;
    }

    /**
     * Get reservation status
     */
    private function get_reservation_status($reservation) {
        $now = current_time('timestamp');
        $start = $reservation['start'];
        $end = $reservation['end'];

        if ($now < $start) {
            return __('Upcoming', 'minpaku-suite');
        } elseif ($now >= $start && $now <= $end) {
            return __('Current', 'minpaku-suite');
        } else {
            return __('Past', 'minpaku-suite');
        }
    }

    /**
     * Render subscription signup form
     */
    private function render_subscription_signup_form() {
        echo '<p>' . __('You don\'t have an active subscription. Please subscribe to continue using our services.', 'minpaku-suite') . '</p>';
        echo '<div id="mcs-subscription-signup">';
        echo '<button class="button button-primary" onclick="startSubscriptionSignup()">' . __('Start Subscription', 'minpaku-suite') . '</button>';
        echo '</div>';
    }

    /**
     * Render subscription management form
     */
    private function render_subscription_management_form($subscription_status) {
        echo '<div class="mcs-subscription-actions">';
        echo '<button class="button" onclick="updatePaymentMethod()">' . __('Update Payment Method', 'minpaku-suite') . '</button>';
        echo '<button class="button button-secondary" onclick="cancelSubscription()">' . __('Cancel Subscription', 'minpaku-suite') . '</button>';
        echo '</div>';
    }

    /**
     * Render payment history
     */
    private function render_payment_history($subscription_status) {
        echo '<p>' . __('Payment history will be displayed here.', 'minpaku-suite') . '</p>';
        // This would integrate with Stripe's invoice API to show payment history
    }

    /**
     * Render property performance report
     */
    private function render_property_performance_report($properties, $from_date, $to_date) {
        echo '<div class="mcs-performance-report">';
        echo '<h3>' . sprintf(__('Performance Report: %s to %s', 'minpaku-suite'), $from_date, $to_date) . '</h3>';

        foreach ($properties as $property) {
            $stats = $this->get_property_stats($property->ID, $from_date, $to_date);

            echo '<div class="property-report">';
            echo '<h4>' . esc_html($property->post_title) . '</h4>';
            echo '<ul>';
            echo '<li>' . sprintf(__('Total Reservations: %d', 'minpaku-suite'), $stats['total_reservations']) . '</li>';
            echo '<li>' . sprintf(__('Total Nights: %d', 'minpaku-suite'), $stats['total_nights']) . '</li>';
            echo '<li>' . sprintf(__('Occupancy Rate: %s%%', 'minpaku-suite'), $stats['occupancy_rate']) . '</li>';
            echo '</ul>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Get property statistics for date range
     */
    private function get_property_stats($property_id, $from_date, $to_date) {
        $booked_slots = get_post_meta($property_id, 'mcs_booked_slots', true);
        if (!is_array($booked_slots)) {
            return ['total_reservations' => 0, 'total_nights' => 0, 'occupancy_rate' => 0];
        }

        $from_timestamp = strtotime($from_date);
        $to_timestamp = strtotime($to_date);
        $total_nights = 0;
        $total_reservations = 0;

        foreach ($booked_slots as $slot) {
            $start = $slot[0];
            $end = $slot[1];

            // Check if reservation overlaps with date range
            if ($start <= $to_timestamp && $end >= $from_timestamp) {
                $total_reservations++;
                $overlap_start = max($start, $from_timestamp);
                $overlap_end = min($end, $to_timestamp);
                $nights = ceil(($overlap_end - $overlap_start) / DAY_IN_SECONDS);
                $total_nights += max(0, $nights);
            }
        }

        $period_days = ceil(($to_timestamp - $from_timestamp) / DAY_IN_SECONDS);
        $occupancy_rate = $period_days > 0 ? round(($total_nights / $period_days) * 100, 1) : 0;

        return [
            'total_reservations' => $total_reservations,
            'total_nights' => $total_nights,
            'occupancy_rate' => $occupancy_rate
        ];
    }
}