<?php
/**
 * Owner Subscription Management with Stripe Integration
 * Handles subscription lifecycle, billing, and state transitions
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class OwnerSubscription {

    const STATUS_ACTIVE = 'active';
    const STATUS_WARNING = 'warning';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CANCELLED = 'cancelled';

    private $stripe_secret_key;
    private $stripe_public_key;
    private $webhook_secret;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_stripe_keys();
        $this->init_hooks();
    }

    /**
     * Initialize Stripe API keys from settings
     */
    private function init_stripe_keys() {
        $settings = get_option('mcs_stripe_settings', []);
        $this->stripe_secret_key = $settings['secret_key'] ?? '';
        $this->stripe_public_key = $settings['public_key'] ?? '';
        $this->webhook_secret = $settings['webhook_secret'] ?? '';

        if ($this->stripe_secret_key) {
            // Initialize Stripe if keys are available
            if (class_exists('\Stripe\Stripe')) {
                \Stripe\Stripe::setApiKey($this->stripe_secret_key);
            }
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'handle_stripe_webhook']);
        add_action('wp_ajax_mcs_create_subscription', [$this, 'ajax_create_subscription']);
        add_action('wp_ajax_mcs_cancel_subscription', [$this, 'ajax_cancel_subscription']);
        add_action('wp_ajax_mcs_update_payment_method', [$this, 'ajax_update_payment_method']);

        // Cron hooks for subscription monitoring
        add_action('mcs_check_subscription_status', [$this, 'check_all_subscriptions']);
        add_action('mcs_process_failed_payments', [$this, 'process_failed_payments']);

        // Property visibility hooks
        add_filter('posts_where', [$this, 'filter_suspended_properties'], 10, 2);
        add_action('pre_get_posts', [$this, 'hide_suspended_properties']);

        // User registration hook
        add_action('mcs/owner/created', [$this, 'create_stripe_customer']);
    }

    /**
     * Create Stripe customer for new owner
     *
     * @param int $user_id
     * @return string|false Customer ID or false on failure
     */
    public function create_stripe_customer($user_id) {
        if (!$this->stripe_secret_key) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Stripe not configured', ['user_id' => $user_id]);
            }
            return false;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        try {
            $customer = \Stripe\Customer::create([
                'email' => $user->user_email,
                'name' => $user->display_name,
                'metadata' => [
                    'wp_user_id' => $user_id,
                    'user_login' => $user->user_login
                ]
            ]);

            // Store customer ID
            update_user_meta($user_id, 'mcs_stripe_customer_id', $customer->id);
            update_user_meta($user_id, 'mcs_subscription_status', self::STATUS_ACTIVE);
            update_user_meta($user_id, 'mcs_customer_created_date', current_time('mysql'));

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Stripe customer created', [
                    'user_id' => $user_id,
                    'customer_id' => $customer->id,
                    'email' => $user->user_email
                ]);
            }

            do_action('mcs/subscription/customer_created', $user_id, $customer->id);

            return $customer->id;

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to create Stripe customer', [
                    'user_id' => $user_id,
                    'error' => $e->getMessage()
                ]);
            }

            return false;
        }
    }

    /**
     * Create subscription for owner
     *
     * @param int $user_id
     * @param string $price_id
     * @param string $payment_method_id
     * @return array
     */
    public function create_subscription($user_id, $price_id, $payment_method_id = null) {
        $customer_id = get_user_meta($user_id, 'mcs_stripe_customer_id', true);

        if (!$customer_id) {
            $customer_id = $this->create_stripe_customer($user_id);
            if (!$customer_id) {
                return ['success' => false, 'error' => 'Failed to create customer'];
            }
        }

        try {
            $subscription_data = [
                'customer' => $customer_id,
                'items' => [
                    ['price' => $price_id]
                ],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'save_default_payment_method' => 'on_subscription'
                ],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'wp_user_id' => $user_id
                ]
            ];

            if ($payment_method_id) {
                $subscription_data['default_payment_method'] = $payment_method_id;
            }

            $subscription = \Stripe\Subscription::create($subscription_data);

            // Store subscription data
            update_user_meta($user_id, 'mcs_stripe_subscription_id', $subscription->id);
            update_user_meta($user_id, 'mcs_subscription_status', self::STATUS_ACTIVE);
            update_user_meta($user_id, 'mcs_subscription_created_date', current_time('mysql'));
            update_user_meta($user_id, 'mcs_current_period_start', $subscription->current_period_start);
            update_user_meta($user_id, 'mcs_current_period_end', $subscription->current_period_end);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Subscription created', [
                    'user_id' => $user_id,
                    'subscription_id' => $subscription->id,
                    'customer_id' => $customer_id
                ]);
            }

            do_action('mcs/subscription/created', $user_id, $subscription->id);

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret ?? null
            ];

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to create subscription', [
                    'user_id' => $user_id,
                    'error' => $e->getMessage()
                ]);
            }

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cancel subscription
     *
     * @param int $user_id
     * @param bool $immediately
     * @return array
     */
    public function cancel_subscription($user_id, $immediately = false) {
        $subscription_id = get_user_meta($user_id, 'mcs_stripe_subscription_id', true);

        if (!$subscription_id) {
            return ['success' => false, 'error' => 'No subscription found'];
        }

        try {
            if ($immediately) {
                $subscription = \Stripe\Subscription::retrieve($subscription_id);
                $subscription->cancel();
            } else {
                $subscription = \Stripe\Subscription::update($subscription_id, [
                    'cancel_at_period_end' => true
                ]);
            }

            // Update local status
            update_user_meta($user_id, 'mcs_subscription_status', self::STATUS_CANCELLED);
            update_user_meta($user_id, 'mcs_subscription_cancelled_date', current_time('mysql'));

            if ($immediately) {
                $this->suspend_owner_properties($user_id);
            }

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Subscription cancelled', [
                    'user_id' => $user_id,
                    'subscription_id' => $subscription_id,
                    'immediately' => $immediately
                ]);
            }

            do_action('mcs/subscription/cancelled', $user_id, $subscription_id, $immediately);

            return ['success' => true];

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to cancel subscription', [
                    'user_id' => $user_id,
                    'error' => $e->getMessage()
                ]);
            }

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update subscription status based on Stripe webhook
     *
     * @param int $user_id
     * @param string $new_status
     * @param array $metadata
     */
    public function update_subscription_status($user_id, $new_status, $metadata = []) {
        $current_status = get_user_meta($user_id, 'mcs_subscription_status', true);

        if ($current_status === $new_status) {
            return; // No change needed
        }

        // State transition logic
        switch ($new_status) {
            case self::STATUS_ACTIVE:
                $this->activate_owner_subscription($user_id);
                break;

            case self::STATUS_WARNING:
                $this->warn_owner_subscription($user_id);
                break;

            case self::STATUS_SUSPENDED:
                $this->suspend_owner_subscription($user_id);
                break;

            case self::STATUS_CANCELLED:
                $this->cancel_owner_subscription($user_id);
                break;
        }

        // Update status and metadata
        update_user_meta($user_id, 'mcs_subscription_status', $new_status);
        update_user_meta($user_id, 'mcs_status_updated_date', current_time('mysql'));

        foreach ($metadata as $key => $value) {
            update_user_meta($user_id, 'mcs_' . $key, $value);
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Subscription status updated', [
                'user_id' => $user_id,
                'old_status' => $current_status,
                'new_status' => $new_status
            ]);
        }

        do_action('mcs/subscription/status_updated', $user_id, $new_status, $current_status);
    }

    /**
     * Activate owner subscription
     *
     * @param int $user_id
     */
    private function activate_owner_subscription($user_id) {
        // Restore property visibility
        $this->restore_owner_properties($user_id);

        // Send activation email
        $this->send_status_email($user_id, 'activated');
    }

    /**
     * Warn owner about subscription issues
     *
     * @param int $user_id
     */
    private function warn_owner_subscription($user_id) {
        // Send warning email
        $this->send_status_email($user_id, 'warning');

        // Properties remain visible during warning period
    }

    /**
     * Suspend owner subscription
     *
     * @param int $user_id
     */
    private function suspend_owner_subscription($user_id) {
        // Hide properties from frontend
        $this->suspend_owner_properties($user_id);

        // Send suspension email
        $this->send_status_email($user_id, 'suspended');

        // Update owner user meta
        update_user_meta($user_id, 'mcs_owner_status', 'suspended');
    }

    /**
     * Cancel owner subscription
     *
     * @param int $user_id
     */
    private function cancel_owner_subscription($user_id) {
        // Hide properties
        $this->suspend_owner_properties($user_id);

        // Send cancellation email
        $this->send_status_email($user_id, 'cancelled');

        // Update owner status
        update_user_meta($user_id, 'mcs_owner_status', 'cancelled');
    }

    /**
     * Suspend owner properties (hide from frontend)
     *
     * @param int $user_id
     */
    private function suspend_owner_properties($user_id) {
        $properties = OwnerRoles::get_user_properties($user_id);

        foreach ($properties as $property) {
            // Store original status
            $original_status = $property->post_status;
            update_post_meta($property->ID, 'mcs_original_status', $original_status);

            // Set to private/draft
            wp_update_post([
                'ID' => $property->ID,
                'post_status' => 'private'
            ]);

            update_post_meta($property->ID, 'mcs_suspended', true);
            update_post_meta($property->ID, 'mcs_suspended_date', current_time('mysql'));
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Owner properties suspended', [
                'user_id' => $user_id,
                'property_count' => count($properties)
            ]);
        }
    }

    /**
     * Restore owner properties visibility
     *
     * @param int $user_id
     */
    private function restore_owner_properties($user_id) {
        $properties = OwnerRoles::get_user_properties($user_id);

        foreach ($properties as $property) {
            if (get_post_meta($property->ID, 'mcs_suspended', true)) {
                // Restore original status
                $original_status = get_post_meta($property->ID, 'mcs_original_status', true);
                if ($original_status) {
                    wp_update_post([
                        'ID' => $property->ID,
                        'post_status' => $original_status
                    ]);
                }

                // Remove suspension flags
                delete_post_meta($property->ID, 'mcs_suspended');
                delete_post_meta($property->ID, 'mcs_suspended_date');
                delete_post_meta($property->ID, 'mcs_original_status');
            }
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Owner properties restored', [
                'user_id' => $user_id,
                'property_count' => count($properties)
            ]);
        }
    }

    /**
     * Send status change email to owner
     *
     * @param int $user_id
     * @param string $status
     */
    private function send_status_email($user_id, $status) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $subject = '';
        $message = '';

        switch ($status) {
            case 'activated':
                $subject = __('Your subscription is now active', 'minpaku-suite');
                $message = __('Your property owner subscription has been activated. Your properties are now visible on the website.', 'minpaku-suite');
                break;

            case 'warning':
                $subject = __('Payment issue with your subscription', 'minpaku-suite');
                $message = __('There was an issue with your subscription payment. Please update your payment method to avoid service interruption.', 'minpaku-suite');
                break;

            case 'suspended':
                $subject = __('Your subscription has been suspended', 'minpaku-suite');
                $message = __('Your property owner subscription has been suspended due to payment issues. Your properties are no longer visible on the website.', 'minpaku-suite');
                break;

            case 'cancelled':
                $subject = __('Your subscription has been cancelled', 'minpaku-suite');
                $message = __('Your property owner subscription has been cancelled. Your properties are no longer visible on the website.', 'minpaku-suite');
                break;
        }

        if ($subject && $message) {
            wp_mail($user->user_email, $subject, $message);
        }

        do_action('mcs/subscription/email_sent', $user_id, $status);
    }

    /**
     * Filter suspended properties from frontend queries
     */
    public function hide_suspended_properties($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // Only apply to property queries
        if ($query->get('post_type') === 'property' ||
            (is_home() && get_option('show_on_front') === 'posts')) {

            $meta_query = $query->get('meta_query') ?: [];
            $meta_query[] = [
                'key' => 'mcs_suspended',
                'compare' => 'NOT EXISTS'
            ];

            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Filter where clause to exclude suspended properties
     */
    public function filter_suspended_properties($where, $query) {
        global $wpdb;

        if (is_admin() || !$query->is_main_query()) {
            return $where;
        }

        if ($query->get('post_type') === 'property') {
            $where .= " AND {$wpdb->posts}.ID NOT IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = 'mcs_suspended' AND meta_value = '1'
            )";
        }

        return $where;
    }

    /**
     * Get subscription status for user
     *
     * @param int $user_id
     * @return array
     */
    public function get_subscription_status($user_id) {
        $customer_id = get_user_meta($user_id, 'mcs_stripe_customer_id', true);
        $subscription_id = get_user_meta($user_id, 'mcs_stripe_subscription_id', true);
        $status = get_user_meta($user_id, 'mcs_subscription_status', true);

        $data = [
            'customer_id' => $customer_id,
            'subscription_id' => $subscription_id,
            'status' => $status ?: self::STATUS_ACTIVE,
            'current_period_start' => get_user_meta($user_id, 'mcs_current_period_start', true),
            'current_period_end' => get_user_meta($user_id, 'mcs_current_period_end', true),
            'created_date' => get_user_meta($user_id, 'mcs_subscription_created_date', true),
            'updated_date' => get_user_meta($user_id, 'mcs_status_updated_date', true)
        ];

        // Get latest from Stripe if subscription exists
        if ($subscription_id && $this->stripe_secret_key) {
            try {
                $subscription = \Stripe\Subscription::retrieve($subscription_id);
                $data['stripe_status'] = $subscription->status;
                $data['stripe_current_period_start'] = $subscription->current_period_start;
                $data['stripe_current_period_end'] = $subscription->current_period_end;
            } catch (Exception $e) {
                $data['stripe_error'] = $e->getMessage();
            }
        }

        return $data;
    }

    /**
     * Handle Stripe webhooks
     */
    public function handle_stripe_webhook() {
        if (!isset($_GET['mcs_stripe_webhook'])) {
            return;
        }

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $this->webhook_secret
            );
        } catch (Exception $e) {
            http_response_code(400);
            exit('Webhook signature verification failed');
        }

        $this->process_webhook_event($event);

        http_response_code(200);
        exit('Webhook processed');
    }

    /**
     * Process Stripe webhook event
     *
     * @param object $event
     */
    private function process_webhook_event($event) {
        $user_id = null;

        // Extract user ID from metadata
        if (isset($event->data->object->metadata->wp_user_id)) {
            $user_id = (int) $event->data->object->metadata->wp_user_id;
        } elseif (isset($event->data->object->customer)) {
            // Find user by customer ID
            $users = get_users([
                'meta_key' => 'mcs_stripe_customer_id',
                'meta_value' => $event->data->object->customer,
                'number' => 1
            ]);
            if (!empty($users)) {
                $user_id = $users[0]->ID;
            }
        }

        if (!$user_id) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('WARNING', 'Webhook event without user ID', [
                    'event_type' => $event->type,
                    'event_id' => $event->id
                ]);
            }
            return;
        }

        // Handle different event types
        switch ($event->type) {
            case 'customer.subscription.created':
                $this->update_subscription_status($user_id, self::STATUS_ACTIVE);
                break;

            case 'customer.subscription.updated':
                $subscription = $event->data->object;
                $status = $this->map_stripe_status($subscription->status);
                $this->update_subscription_status($user_id, $status, [
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end
                ]);
                break;

            case 'customer.subscription.deleted':
                $this->update_subscription_status($user_id, self::STATUS_CANCELLED);
                break;

            case 'invoice.payment_failed':
                $this->handle_payment_failed($user_id, $event->data->object);
                break;

            case 'invoice.payment_succeeded':
                $this->update_subscription_status($user_id, self::STATUS_ACTIVE);
                break;
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Webhook event processed', [
                'event_type' => $event->type,
                'user_id' => $user_id
            ]);
        }
    }

    /**
     * Map Stripe subscription status to internal status
     *
     * @param string $stripe_status
     * @return string
     */
    private function map_stripe_status($stripe_status) {
        switch ($stripe_status) {
            case 'active':
                return self::STATUS_ACTIVE;
            case 'past_due':
                return self::STATUS_WARNING;
            case 'canceled':
            case 'cancelled':
                return self::STATUS_CANCELLED;
            case 'unpaid':
                return self::STATUS_SUSPENDED;
            default:
                return self::STATUS_WARNING;
        }
    }

    /**
     * Handle failed payment
     *
     * @param int $user_id
     * @param object $invoice
     */
    private function handle_payment_failed($user_id, $invoice) {
        $attempt_count = get_user_meta($user_id, 'mcs_payment_failures', true) ?: 0;
        $attempt_count++;

        update_user_meta($user_id, 'mcs_payment_failures', $attempt_count);
        update_user_meta($user_id, 'mcs_last_payment_failure', current_time('mysql'));

        // Determine status based on failure count
        if ($attempt_count >= 3) {
            $this->update_subscription_status($user_id, self::STATUS_SUSPENDED);
        } else {
            $this->update_subscription_status($user_id, self::STATUS_WARNING);
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('WARNING', 'Payment failed', [
                'user_id' => $user_id,
                'attempt_count' => $attempt_count,
                'invoice_id' => $invoice->id
            ]);
        }
    }

    /**
     * AJAX handler for creating subscription
     */
    public function ajax_create_subscription() {
        check_ajax_referer('mcs_subscription_nonce', 'nonce');

        if (!current_user_can('mcs_manage_own_subscription')) {
            wp_die('Unauthorized');
        }

        $price_id = sanitize_text_field($_POST['price_id'] ?? '');
        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');

        $result = $this->create_subscription(get_current_user_id(), $price_id, $payment_method_id);

        wp_send_json($result);
    }

    /**
     * AJAX handler for cancelling subscription
     */
    public function ajax_cancel_subscription() {
        check_ajax_referer('mcs_subscription_nonce', 'nonce');

        if (!current_user_can('mcs_manage_own_subscription')) {
            wp_die('Unauthorized');
        }

        $immediately = (bool) ($_POST['immediately'] ?? false);

        $result = $this->cancel_subscription(get_current_user_id(), $immediately);

        wp_send_json($result);
    }

    /**
     * Get subscription statistics
     *
     * @return array
     */
    public function get_subscription_statistics() {
        global $wpdb;

        $stats = [
            'total_customers' => 0,
            'active_subscriptions' => 0,
            'warning_subscriptions' => 0,
            'suspended_subscriptions' => 0,
            'cancelled_subscriptions' => 0,
            'monthly_revenue' => 0
        ];

        // Get customer count
        $stats['total_customers'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'mcs_stripe_customer_id'"
        );

        // Get subscription status counts
        foreach ([self::STATUS_ACTIVE, self::STATUS_WARNING, self::STATUS_SUSPENDED, self::STATUS_CANCELLED] as $status) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'mcs_subscription_status' AND meta_value = %s",
                $status
            ));
            $stats[$status . '_subscriptions'] = (int) $count;
        }

        return $stats;
    }
}