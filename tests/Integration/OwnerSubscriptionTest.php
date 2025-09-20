<?php
/**
 * Integration Tests for Owner Subscription System
 * Tests Stripe subscription state transitions (active → warning → suspended → cancelled)
 */

use PHPUnit\Framework\TestCase;

class OwnerSubscriptionTest extends TestCase {

    private $owner_user_id;
    private $owner_subscription;
    private $test_stripe_customer_id;
    private $test_stripe_subscription_id;

    protected function setUp(): void {
        parent::setUp();

        // Create test owner user
        $this->owner_user_id = wp_insert_user([
            'user_login' => 'test_owner_' . uniqid(),
            'user_email' => 'test_owner_' . uniqid() . '@example.com',
            'user_pass' => 'test_password',
            'role' => 'property_owner'
        ]);

        // Initialize subscription manager
        $this->owner_subscription = new OwnerSubscription();

        // Set up test Stripe IDs
        $this->test_stripe_customer_id = 'cus_test_' . uniqid();
        $this->test_stripe_subscription_id = 'sub_test_' . uniqid();

        // Mock Stripe API responses
        $this->setupStripeMocks();

        // Clean up any existing subscription data
        $this->cleanupSubscriptionData();
    }

    protected function tearDown(): void {
        // Clean up
        wp_delete_user($this->owner_user_id);
        $this->cleanupSubscriptionData();
        parent::tearDown();
    }

    private function cleanupSubscriptionData() {
        delete_user_meta($this->owner_user_id, 'stripe_customer_id');
        delete_user_meta($this->owner_user_id, 'stripe_subscription_id');
        delete_user_meta($this->owner_user_id, 'subscription_status');
        delete_user_meta($this->owner_user_id, 'subscription_plan');
        delete_user_meta($this->owner_user_id, 'subscription_current_period_end');
        delete_user_meta($this->owner_user_id, 'subscription_warnings_sent');
    }

    private function setupStripeMocks() {
        // Mock successful Stripe customer creation
        add_filter('minpaku_stripe_create_customer', function($customer_data) {
            return [
                'id' => $this->test_stripe_customer_id,
                'email' => $customer_data['email'],
                'metadata' => $customer_data['metadata']
            ];
        });

        // Mock successful Stripe subscription creation
        add_filter('minpaku_stripe_create_subscription', function($subscription_data) {
            return [
                'id' => $this->test_stripe_subscription_id,
                'customer' => $this->test_stripe_customer_id,
                'status' => 'active',
                'current_period_end' => time() + (30 * 24 * 60 * 60), // 30 days from now
                'items' => [
                    'data' => [
                        [
                            'price' => [
                                'id' => 'price_test_basic'
                            ]
                        ]
                    ]
                ]
            ];
        });

        // Mock Stripe subscription updates
        add_filter('minpaku_stripe_update_subscription', function($subscription_id, $update_data) {
            return [
                'id' => $subscription_id,
                'status' => $update_data['status'] ?? 'active',
                'current_period_end' => time() + (30 * 24 * 60 * 60)
            ];
        }, 10, 2);
    }

    /**
     * Test subscription creation and activation
     */
    public function testSubscriptionCreation() {
        $result = $this->owner_subscription->create_subscription(
            $this->owner_user_id,
            'price_test_basic',
            'pm_test_card'
        );

        $this->assertTrue($result['success'], 'Subscription creation should succeed');
        $this->assertEquals('active', $result['status'], 'New subscription should be active');

        // Verify user metadata is set
        $customer_id = get_user_meta($this->owner_user_id, 'stripe_customer_id', true);
        $subscription_id = get_user_meta($this->owner_user_id, 'stripe_subscription_id', true);
        $status = get_user_meta($this->owner_user_id, 'subscription_status', true);

        $this->assertEquals($this->test_stripe_customer_id, $customer_id, 'Customer ID should be saved');
        $this->assertEquals($this->test_stripe_subscription_id, $subscription_id, 'Subscription ID should be saved');
        $this->assertEquals('active', $status, 'Status should be active');

        // Test subscription retrieval
        $subscription_info = $this->owner_subscription->get_subscription_status($this->owner_user_id);
        $this->assertEquals('active', $subscription_info['status'], 'Retrieved status should be active');
        $this->assertArrayHasKey('plan', $subscription_info, 'Should include plan information');
        $this->assertArrayHasKey('next_billing_date', $subscription_info, 'Should include billing date');
    }

    /**
     * Test subscription state transition: active → warning
     */
    public function testActiveToWarningTransition() {
        // Set up active subscription
        $this->setupActiveSubscription();

        // Simulate payment failure
        $result = $this->owner_subscription->handle_payment_failure($this->owner_user_id);

        $this->assertTrue($result['success'], 'Payment failure handling should succeed');

        // Check status transition
        $status = get_user_meta($this->owner_user_id, 'subscription_status', true);
        $this->assertEquals('warning', $status, 'Status should transition to warning');

        // Check warning tracking
        $warnings_sent = get_user_meta($this->owner_user_id, 'subscription_warnings_sent', true);
        $this->assertEquals(1, $warnings_sent, 'Should track first warning sent');

        // Test warning notification was triggered
        $this->assertTrue(
            did_action('minpaku_subscription_payment_failed') > 0,
            'Should trigger payment failed action'
        );
    }

    /**
     * Test subscription state transition: warning → suspended
     */
    public function testWarningToSuspendedTransition() {
        // Set up subscription in warning state
        $this->setupWarningSubscription();

        // Simulate grace period expiration
        $result = $this->owner_subscription->handle_grace_period_expired($this->owner_user_id);

        $this->assertTrue($result['success'], 'Grace period expiration should succeed');

        // Check status transition
        $status = get_user_meta($this->owner_user_id, 'subscription_status', true);
        $this->assertEquals('suspended', $status, 'Status should transition to suspended');

        // Test property access restriction
        $property_id = wp_insert_post([
            'post_type' => 'property',
            'post_title' => 'Test Property',
            'post_author' => $this->owner_user_id,
            'post_status' => 'publish'
        ]);

        // User should not be able to access suspended properties
        $can_access = $this->owner_subscription->can_access_property($this->owner_user_id, $property_id);
        $this->assertFalse($can_access, 'Should not access properties when suspended');

        // Clean up
        wp_delete_post($property_id, true);
    }

    /**
     * Test subscription state transition: suspended → cancelled
     */
    public function testSuspendedToCancelledTransition() {
        // Set up suspended subscription
        $this->setupSuspendedSubscription();

        // Simulate final cancellation after extended non-payment
        $result = $this->owner_subscription->cancel_subscription($this->owner_user_id, 'non_payment');

        $this->assertTrue($result['success'], 'Subscription cancellation should succeed');

        // Check status transition
        $status = get_user_meta($this->owner_user_id, 'subscription_status', true);
        $this->assertEquals('cancelled', $status, 'Status should transition to cancelled');

        // Test complete access restriction
        $property_id = wp_insert_post([
            'post_type' => 'property',
            'post_title' => 'Test Property',
            'post_author' => $this->owner_user_id,
            'post_status' => 'publish'
        ]);

        $can_access = $this->owner_subscription->can_access_property($this->owner_user_id, $property_id);
        $this->assertFalse($can_access, 'Should not access properties when cancelled');

        // Test data retention policy
        $subscription_info = $this->owner_subscription->get_subscription_status($this->owner_user_id);
        $this->assertEquals('cancelled', $subscription_info['status'], 'Should retain cancellation status');
        $this->assertArrayHasKey('cancellation_reason', $subscription_info, 'Should track cancellation reason');

        // Clean up
        wp_delete_post($property_id, true);
    }

    /**
     * Test subscription reactivation from suspended state
     */
    public function testSubscriptionReactivation() {
        // Set up suspended subscription
        $this->setupSuspendedSubscription();

        // Mock successful payment
        add_filter('minpaku_stripe_update_subscription', function($subscription_id, $update_data) {
            return [
                'id' => $subscription_id,
                'status' => 'active',
                'current_period_end' => time() + (30 * 24 * 60 * 60)
            ];
        }, 10, 2);

        // Reactivate subscription
        $result = $this->owner_subscription->reactivate_subscription($this->owner_user_id);

        $this->assertTrue($result['success'], 'Subscription reactivation should succeed');

        // Check status transition back to active
        $status = get_user_meta($this->owner_user_id, 'subscription_status', true);
        $this->assertEquals('active', $status, 'Status should return to active');

        // Test property access restored
        $property_id = wp_insert_post([
            'post_type' => 'property',
            'post_title' => 'Test Property',
            'post_author' => $this->owner_user_id,
            'post_status' => 'publish'
        ]);

        $can_access = $this->owner_subscription->can_access_property($this->owner_user_id, $property_id);
        $this->assertTrue($can_access, 'Should regain property access when reactivated');

        // Clean up
        wp_delete_post($property_id, true);
    }

    /**
     * Test subscription plan upgrades and downgrades
     */
    public function testSubscriptionPlanChanges() {
        // Set up active basic subscription
        $this->setupActiveSubscription('price_test_basic');

        // Test upgrade to premium
        $result = $this->owner_subscription->change_subscription_plan(
            $this->owner_user_id,
            'price_test_premium'
        );

        $this->assertTrue($result['success'], 'Plan upgrade should succeed');

        $subscription_info = $this->owner_subscription->get_subscription_status($this->owner_user_id);
        $this->assertEquals('price_test_premium', $subscription_info['plan'], 'Plan should be updated to premium');

        // Test property limit increase
        $property_limit_before = $this->owner_subscription->get_property_limit($this->owner_user_id);

        // Upgrade should increase property limit
        $this->assertGreaterThan(1, $property_limit_before, 'Premium plan should have higher property limit');

        // Test downgrade back to basic
        $result = $this->owner_subscription->change_subscription_plan(
            $this->owner_user_id,
            'price_test_basic'
        );

        $this->assertTrue($result['success'], 'Plan downgrade should succeed');

        $subscription_info = $this->owner_subscription->get_subscription_status($this->owner_user_id);
        $this->assertEquals('price_test_basic', $subscription_info['plan'], 'Plan should be downgraded to basic');
    }

    /**
     * Test webhook processing for subscription events
     */
    public function testWebhookProcessing() {
        // Set up active subscription
        $this->setupActiveSubscription();

        // Test invoice payment failed webhook
        $payment_failed_webhook = [
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'subscription' => $this->test_stripe_subscription_id,
                    'customer' => $this->test_stripe_customer_id,
                    'attempt_count' => 2
                ]
            ]
        ];

        $result = $this->owner_subscription->process_webhook($payment_failed_webhook);
        $this->assertTrue($result['success'], 'Payment failed webhook should be processed successfully');

        $status = get_user_meta($this->owner_user_id, 'subscription_status', true);
        $this->assertEquals('warning', $status, 'Status should change to warning after payment failure');

        // Test subscription cancelled webhook
        $cancelled_webhook = [
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => $this->test_stripe_subscription_id,
                    'customer' => $this->test_stripe_customer_id,
                    'status' => 'canceled'
                ]
            ]
        ];

        $result = $this->owner_subscription->process_webhook($cancelled_webhook);
        $this->assertTrue($result['success'], 'Cancelled webhook should be processed successfully');

        $status = get_user_meta($this->owner_user_id, 'subscription_status', true);
        $this->assertEquals('cancelled', $status, 'Status should change to cancelled');
    }

    /**
     * Test billing cycle and prorated charges
     */
    public function testBillingCycleManagement() {
        // Set up active subscription
        $this->setupActiveSubscription();

        // Test prorated upgrade mid-cycle
        $current_period_end = get_user_meta($this->owner_user_id, 'subscription_current_period_end', true);

        $result = $this->owner_subscription->change_subscription_plan(
            $this->owner_user_id,
            'price_test_premium',
            ['prorate' => true]
        );

        $this->assertTrue($result['success'], 'Prorated upgrade should succeed');

        // Test billing date remains unchanged for mid-cycle changes
        $new_period_end = get_user_meta($this->owner_user_id, 'subscription_current_period_end', true);
        $this->assertEquals($current_period_end, $new_period_end, 'Billing cycle should remain unchanged for prorated upgrade');

        // Test billing history tracking
        $billing_history = $this->owner_subscription->get_billing_history($this->owner_user_id);
        $this->assertIsArray($billing_history, 'Should return billing history array');
        $this->assertNotEmpty($billing_history, 'Should have billing history entries');
    }

    // Helper methods for setting up subscription states

    private function setupActiveSubscription($plan = 'price_test_basic') {
        update_user_meta($this->owner_user_id, 'stripe_customer_id', $this->test_stripe_customer_id);
        update_user_meta($this->owner_user_id, 'stripe_subscription_id', $this->test_stripe_subscription_id);
        update_user_meta($this->owner_user_id, 'subscription_status', 'active');
        update_user_meta($this->owner_user_id, 'subscription_plan', $plan);
        update_user_meta($this->owner_user_id, 'subscription_current_period_end', time() + (30 * 24 * 60 * 60));
        update_user_meta($this->owner_user_id, 'subscription_warnings_sent', 0);
    }

    private function setupWarningSubscription() {
        $this->setupActiveSubscription();
        update_user_meta($this->owner_user_id, 'subscription_status', 'warning');
        update_user_meta($this->owner_user_id, 'subscription_warnings_sent', 2);
        update_user_meta($this->owner_user_id, 'grace_period_start', time() - (5 * 24 * 60 * 60)); // 5 days ago
    }

    private function setupSuspendedSubscription() {
        $this->setupWarningSubscription();
        update_user_meta($this->owner_user_id, 'subscription_status', 'suspended');
        update_user_meta($this->owner_user_id, 'suspension_date', time() - (7 * 24 * 60 * 60)); // 7 days ago
    }
}