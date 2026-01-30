<?php
/**
 * Stripe Payment Provider
 * Migrated from Phase B OwnerSubscription for better architecture
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/AbstractPaymentProvider.php';

class StripePaymentProvider extends AbstractPaymentProvider {

    private $stripe_initialized = false;

    /**
     * Get provider name
     */
    public function getName(): string {
        return 'stripe';
    }

    /**
     * Get provider display name
     */
    public function getDisplayName(): string {
        return __('Stripe', 'minpaku-suite');
    }

    /**
     * Get provider description
     */
    public function getDescription(): string {
        return __('Accept credit cards, debit cards, and other payment methods with Stripe.', 'minpaku-suite');
    }

    /**
     * Get required configuration fields
     */
    public function getConfigFields(): array {
        return [
            'secret_key' => [
                'label' => __('Secret Key', 'minpaku-suite'),
                'type' => 'password',
                'required' => true,
                'description' => __('Your Stripe secret key (starts with sk_)', 'minpaku-suite')
            ],
            'public_key' => [
                'label' => __('Publishable Key', 'minpaku-suite'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Stripe publishable key (starts with pk_)', 'minpaku-suite')
            ],
            'webhook_secret' => [
                'label' => __('Webhook Secret', 'minpaku-suite'),
                'type' => 'password',
                'required' => false,
                'description' => __('Webhook signing secret for validating webhooks', 'minpaku-suite')
            ],
            'test_mode' => [
                'label' => __('Test Mode', 'minpaku-suite'),
                'type' => 'checkbox',
                'required' => false,
                'description' => __('Enable test mode for development', 'minpaku-suite')
            ]
        ];
    }

    /**
     * Initialize Stripe
     */
    public function initialize(): bool {
        if ($this->stripe_initialized) {
            return true;
        }

        if (!class_exists('\Stripe\Stripe')) {
            $this->setError(__('Stripe PHP SDK is not installed', 'minpaku-suite'));
            return false;
        }

        $secret_key = $this->config['secret_key'] ?? '';
        if (empty($secret_key)) {
            $this->setError(__('Stripe secret key is not configured', 'minpaku-suite'));
            return false;
        }

        try {
            \Stripe\Stripe::setApiKey($secret_key);
            \Stripe\Stripe::setAppInfo(
                'Minpaku Suite',
                '1.0.0',
                'https://example.com',
                'pp_partner_123' // Replace with actual partner ID if applicable
            );

            $this->stripe_initialized = true;
            $this->clearError();

            return true;

        } catch (Exception $e) {
            $this->setError(sprintf(__('Failed to initialize Stripe: %s', 'minpaku-suite'), $e->getMessage()));
            return false;
        }
    }

    /**
     * Test connection
     */
    public function testConnection(): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'message' => $this->getLastError(),
                'data' => []
            ];
        }

        try {
            // Try to retrieve account information
            $account = \Stripe\Account::retrieve();

            $this->log('INFO', 'Stripe connection test successful', [
                'account_id' => $account->id,
                'country' => $account->country
            ]);

            return [
                'success' => true,
                'message' => __('Connection successful', 'minpaku-suite'),
                'data' => [
                    'account_id' => $account->id,
                    'business_name' => $account->business_profile->name ?? '',
                    'country' => $account->country,
                    'currency' => $account->default_currency,
                    'charges_enabled' => $account->charges_enabled,
                    'payouts_enabled' => $account->payouts_enabled
                ]
            ];

        } catch (\Stripe\Exception\AuthenticationException $e) {
            return [
                'success' => false,
                'message' => __('Invalid API key', 'minpaku-suite'),
                'data' => []
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Stripe API error: %s', 'minpaku-suite'), $e->getMessage()),
                'data' => []
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Connection failed: %s', 'minpaku-suite'), $e->getMessage()),
                'data' => []
            ];
        }
    }

    /**
     * Create payment intent
     */
    public function createPaymentIntent(array $payment_data): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'payment_intent_id' => '',
                'client_secret' => '',
                'message' => $this->getLastError()
            ];
        }

        $validation = $this->validatePaymentData($payment_data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'payment_intent_id' => '',
                'client_secret' => '',
                'message' => implode(', ', $validation['errors'])
            ];
        }

        try {
            $amount = $this->formatAmount($payment_data['amount'], $payment_data['currency']);
            $currency = strtolower($payment_data['currency']);

            $intent_data = [
                'amount' => $amount,
                'currency' => $currency,
                'automatic_payment_methods' => ['enabled' => true],
                'description' => $this->generateDescription($payment_data),
                'metadata' => $payment_data['metadata'] ?? []
            ];

            // Add customer if provided
            if (!empty($payment_data['customer_id'])) {
                $intent_data['customer'] = $payment_data['customer_id'];
            }

            // Add payment method if provided
            if (!empty($payment_data['payment_method'])) {
                $intent_data['payment_method'] = $payment_data['payment_method'];
                $intent_data['confirmation_method'] = 'manual';
                $intent_data['confirm'] = true;
            }

            // Add idempotency key
            $options = [
                'idempotency_key' => $this->createIdempotencyKey($payment_data)
            ];

            $intent = \Stripe\PaymentIntent::create($intent_data, $options);

            $this->log('INFO', 'Payment intent created', [
                'payment_intent_id' => $intent->id,
                'amount' => $payment_data['amount'],
                'currency' => $payment_data['currency']
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'message' => __('Payment intent created successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'payment_intent_id' => '',
                'client_secret' => '',
                'message' => $e->getError()->message
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->setError($e->getMessage());
            return [
                'success' => false,
                'payment_intent_id' => '',
                'client_secret' => '',
                'message' => __('Payment processing error', 'minpaku-suite')
            ];
        }
    }

    /**
     * Charge payment method
     */
    public function charge(array $charge_data): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'charge_id' => '',
                'transaction_id' => '',
                'message' => $this->getLastError()
            ];
        }

        $validation = $this->validatePaymentData($charge_data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'charge_id' => '',
                'transaction_id' => '',
                'message' => implode(', ', $validation['errors'])
            ];
        }

        try {
            $amount = $this->formatAmount($charge_data['amount'], $charge_data['currency']);
            $currency = strtolower($charge_data['currency']);

            $charge_params = [
                'amount' => $amount,
                'currency' => $currency,
                'description' => $this->generateDescription($charge_data),
                'metadata' => $charge_data['metadata'] ?? []
            ];

            // Payment source (card, token, or payment method)
            if (!empty($charge_data['source'])) {
                $charge_params['source'] = $charge_data['source'];
            } elseif (!empty($charge_data['payment_method'])) {
                $charge_params['payment_method'] = $charge_data['payment_method'];
                $charge_params['confirm'] = true;
            } else {
                return [
                    'success' => false,
                    'charge_id' => '',
                    'transaction_id' => '',
                    'message' => __('Payment source or method is required', 'minpaku-suite')
                ];
            }

            // Add customer if provided
            if (!empty($charge_data['customer_id'])) {
                $charge_params['customer'] = $charge_data['customer_id'];
            }

            $charge = \Stripe\Charge::create($charge_params);

            $this->log('INFO', 'Charge created', [
                'charge_id' => $charge->id,
                'amount' => $charge_data['amount'],
                'currency' => $charge_data['currency'],
                'status' => $charge->status
            ]);

            return [
                'success' => true,
                'charge_id' => $charge->id,
                'transaction_id' => $charge->balance_transaction,
                'message' => __('Payment charged successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'charge_id' => '',
                'transaction_id' => '',
                'message' => $e->getError()->message
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->setError($e->getMessage());
            return [
                'success' => false,
                'charge_id' => '',
                'transaction_id' => '',
                'message' => __('Payment processing error', 'minpaku-suite')
            ];
        }
    }

    /**
     * Capture payment intent
     */
    public function capture(string $payment_intent_id, float $amount = null): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'charge_id' => '',
                'message' => $this->getLastError()
            ];
        }

        try {
            $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);

            $capture_params = [];
            if ($amount !== null) {
                $capture_params['amount_to_capture'] = $this->formatAmount($amount, $intent->currency);
            }

            $captured_intent = $intent->capture($capture_params);

            $this->log('INFO', 'Payment captured', [
                'payment_intent_id' => $payment_intent_id,
                'amount' => $amount,
                'status' => $captured_intent->status
            ]);

            return [
                'success' => true,
                'charge_id' => $captured_intent->charges->data[0]->id ?? '',
                'message' => __('Payment captured successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->setError($e->getMessage());
            return [
                'success' => false,
                'charge_id' => '',
                'message' => __('Failed to capture payment', 'minpaku-suite')
            ];
        }
    }

    /**
     * Cancel payment intent
     */
    public function cancel(string $payment_intent_id, string $reason = ''): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'message' => $this->getLastError()
            ];
        }

        try {
            $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);

            $cancel_params = [];
            if ($reason) {
                $cancel_params['cancellation_reason'] = $reason;
            }

            $cancelled_intent = $intent->cancel($cancel_params);

            $this->log('INFO', 'Payment cancelled', [
                'payment_intent_id' => $payment_intent_id,
                'reason' => $reason,
                'status' => $cancelled_intent->status
            ]);

            return [
                'success' => true,
                'message' => __('Payment cancelled successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->setError($e->getMessage());
            return [
                'success' => false,
                'message' => __('Failed to cancel payment', 'minpaku-suite')
            ];
        }
    }

    /**
     * Refund payment
     */
    public function refund(string $charge_id, float $amount = null, string $reason = ''): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'refund_id' => '',
                'message' => $this->getLastError()
            ];
        }

        try {
            $refund_params = [
                'charge' => $charge_id
            ];

            if ($amount !== null) {
                // Get charge to determine currency for amount formatting
                $charge = \Stripe\Charge::retrieve($charge_id);
                $refund_params['amount'] = $this->formatAmount($amount, $charge->currency);
            }

            if ($reason) {
                $refund_params['reason'] = $reason;
            }

            $refund = \Stripe\Refund::create($refund_params);

            $this->log('INFO', 'Refund created', [
                'charge_id' => $charge_id,
                'refund_id' => $refund->id,
                'amount' => $amount,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'message' => __('Refund processed successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->setError($e->getMessage());
            return [
                'success' => false,
                'refund_id' => '',
                'message' => __('Failed to process refund', 'minpaku-suite')
            ];
        }
    }

    /**
     * Get payment details
     */
    public function getPayment(string $payment_id): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'payment' => [],
                'message' => $this->getLastError()
            ];
        }

        try {
            // Try to retrieve as PaymentIntent first, then as Charge
            try {
                $payment = \Stripe\PaymentIntent::retrieve($payment_id);
                $type = 'payment_intent';
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                $payment = \Stripe\Charge::retrieve($payment_id);
                $type = 'charge';
            }

            $normalized_payment = $this->normalizeStripePayment($payment, $type);

            return [
                'success' => true,
                'payment' => $normalized_payment,
                'message' => __('Payment retrieved successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'payment' => [],
                'message' => sprintf(__('Payment not found: %s', 'minpaku-suite'), $e->getMessage())
            ];
        }
    }

    /**
     * List payments for customer
     */
    public function listPayments(string $customer_id, array $filters = []): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'payments' => [],
                'message' => $this->getLastError()
            ];
        }

        try {
            $params = [
                'customer' => $customer_id,
                'limit' => $filters['limit'] ?? 100
            ];

            if (!empty($filters['starting_after'])) {
                $params['starting_after'] = $filters['starting_after'];
            }

            if (!empty($filters['ending_before'])) {
                $params['ending_before'] = $filters['ending_before'];
            }

            $charges = \Stripe\Charge::all($params);

            $payments = [];
            foreach ($charges->data as $charge) {
                $payments[] = $this->normalizeStripePayment($charge, 'charge');
            }

            return [
                'success' => true,
                'payments' => $payments,
                'message' => sprintf(__('Retrieved %d payments', 'minpaku-suite'), count($payments))
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'payments' => [],
                'message' => sprintf(__('Failed to retrieve payments: %s', 'minpaku-suite'), $e->getMessage())
            ];
        }
    }

    /**
     * Create customer (migrated from Phase B)
     */
    public function createCustomer(array $customer_data): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'customer_id' => '',
                'message' => $this->getLastError()
            ];
        }

        try {
            $customer_params = [
                'email' => $customer_data['email'] ?? '',
                'name' => $customer_data['name'] ?? '',
                'metadata' => $customer_data['metadata'] ?? []
            ];

            if (!empty($customer_data['description'])) {
                $customer_params['description'] = $customer_data['description'];
            }

            if (!empty($customer_data['phone'])) {
                $customer_params['phone'] = $customer_data['phone'];
            }

            $customer = \Stripe\Customer::create($customer_params);

            $this->log('INFO', 'Customer created', [
                'customer_id' => $customer->id,
                'email' => $customer_data['email'] ?? ''
            ]);

            return [
                'success' => true,
                'customer_id' => $customer->id,
                'message' => __('Customer created successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->setError($e->getMessage());
            return [
                'success' => false,
                'customer_id' => '',
                'message' => __('Failed to create customer', 'minpaku-suite')
            ];
        }
    }

    /**
     * Update customer
     */
    public function updateCustomer(string $customer_id, array $customer_data): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'message' => $this->getLastError()
            ];
        }

        try {
            $update_params = [];

            $allowed_fields = ['email', 'name', 'description', 'phone', 'metadata'];
            foreach ($allowed_fields as $field) {
                if (isset($customer_data[$field])) {
                    $update_params[$field] = $customer_data[$field];
                }
            }

            \Stripe\Customer::update($customer_id, $update_params);

            $this->log('INFO', 'Customer updated', ['customer_id' => $customer_id]);

            return [
                'success' => true,
                'message' => __('Customer updated successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->setError($e->getMessage());
            return [
                'success' => false,
                'message' => __('Failed to update customer', 'minpaku-suite')
            ];
        }
    }

    /**
     * Get customer
     */
    public function getCustomer(string $customer_id): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'customer' => [],
                'message' => $this->getLastError()
            ];
        }

        try {
            $customer = \Stripe\Customer::retrieve($customer_id);

            return [
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'email' => $customer->email,
                    'name' => $customer->name,
                    'description' => $customer->description,
                    'phone' => $customer->phone,
                    'created' => $customer->created,
                    'metadata' => $customer->metadata->toArray()
                ],
                'message' => __('Customer retrieved successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'customer' => [],
                'message' => sprintf(__('Customer not found: %s', 'minpaku-suite'), $e->getMessage())
            ];
        }
    }

    /**
     * Delete customer
     */
    public function deleteCustomer(string $customer_id): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'message' => $this->getLastError()
            ];
        }

        try {
            $customer = \Stripe\Customer::retrieve($customer_id);
            $customer->delete();

            $this->log('INFO', 'Customer deleted', ['customer_id' => $customer_id]);

            return [
                'success' => true,
                'message' => __('Customer deleted successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Failed to delete customer: %s', 'minpaku-suite'), $e->getMessage())
            ];
        }
    }

    /**
     * Add payment method
     */
    public function addPaymentMethod(string $customer_id, array $payment_method_data): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'payment_method_id' => '',
                'message' => $this->getLastError()
            ];
        }

        try {
            $payment_method = \Stripe\PaymentMethod::create($payment_method_data);
            $payment_method->attach(['customer' => $customer_id]);

            return [
                'success' => true,
                'payment_method_id' => $payment_method->id,
                'message' => __('Payment method added successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'payment_method_id' => '',
                'message' => sprintf(__('Failed to add payment method: %s', 'minpaku-suite'), $e->getMessage())
            ];
        }
    }

    /**
     * Remove payment method
     */
    public function removePaymentMethod(string $payment_method_id): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'message' => $this->getLastError()
            ];
        }

        try {
            $payment_method = \Stripe\PaymentMethod::retrieve($payment_method_id);
            $payment_method->detach();

            return [
                'success' => true,
                'message' => __('Payment method removed successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Failed to remove payment method: %s', 'minpaku-suite'), $e->getMessage())
            ];
        }
    }

    /**
     * List payment methods
     */
    public function listPaymentMethods(string $customer_id): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'payment_methods' => [],
                'message' => $this->getLastError()
            ];
        }

        try {
            $payment_methods = \Stripe\PaymentMethod::all([
                'customer' => $customer_id,
                'type' => 'card'
            ]);

            $methods = [];
            foreach ($payment_methods->data as $pm) {
                $methods[] = [
                    'id' => $pm->id,
                    'type' => $pm->type,
                    'card' => $pm->card ? [
                        'brand' => $pm->card->brand,
                        'last4' => $pm->card->last4,
                        'exp_month' => $pm->card->exp_month,
                        'exp_year' => $pm->card->exp_year
                    ] : null
                ];
            }

            return [
                'success' => true,
                'payment_methods' => $methods,
                'message' => sprintf(__('Retrieved %d payment methods', 'minpaku-suite'), count($methods))
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'payment_methods' => [],
                'message' => sprintf(__('Failed to retrieve payment methods: %s', 'minpaku-suite'), $e->getMessage())
            ];
        }
    }

    /**
     * Handle Stripe webhook (migrated from Phase B)
     */
    public function handleWebhook(array $payload, string $signature = ''): array {
        if (!$this->initialize()) {
            return [
                'success' => false,
                'message' => $this->getLastError()
            ];
        }

        $endpoint_secret = $this->config['webhook_secret'] ?? '';

        if (empty($endpoint_secret)) {
            $this->log('WARNING', 'Webhook secret not configured');
            return [
                'success' => false,
                'message' => __('Webhook secret not configured', 'minpaku-suite')
            ];
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                json_encode($payload),
                $signature,
                $endpoint_secret
            );

            $this->log('INFO', 'Webhook received', [
                'event_type' => $event->type,
                'event_id' => $event->id
            ]);

            // Process the event
            $this->processWebhookEvent($event);

            return [
                'success' => true,
                'message' => __('Webhook processed successfully', 'minpaku-suite')
            ];

        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return [
                'success' => false,
                'message' => __('Webhook signature verification failed', 'minpaku-suite')
            ];
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            return [
                'success' => false,
                'message' => __('Webhook processing failed', 'minpaku-suite')
            ];
        }
    }

    /**
     * Get supported capabilities
     */
    public function getCapabilities(): array {
        return [
            'can_charge' => true,
            'can_refund' => true,
            'can_capture' => true,
            'can_cancel' => true,
            'supports_subscriptions' => true,
            'supports_marketplace' => true,
            'supports_instant_payouts' => true,
            'supports_webhooks' => true,
            'requires_redirect' => false
        ];
    }

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array {
        return [
            'USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'DKK', 'NOK', 'SEK',
            'PLN', 'CZK', 'HUF', 'BGN', 'HRK', 'RON', 'ISK', 'MXN', 'BRL', 'SGD',
            'HKD', 'KRW', 'MYR', 'THB', 'PHP', 'INR', 'NZD'
        ];
    }

    /**
     * Normalize Stripe payment data
     */
    private function normalizeStripePayment($stripe_payment, $type): array {
        $normalized = [
            'id' => $stripe_payment->id,
            'type' => $type,
            'created_at' => date('Y-m-d H:i:s', $stripe_payment->created),
            'status' => $this->mapStatus($stripe_payment->status),
            'provider_data' => $stripe_payment->toArray()
        ];

        if ($type === 'payment_intent') {
            $normalized['amount'] = $this->unformatAmount($stripe_payment->amount, $stripe_payment->currency);
            $normalized['currency'] = strtoupper($stripe_payment->currency);
            $normalized['customer_id'] = $stripe_payment->customer;
            $normalized['description'] = $stripe_payment->description;
            $normalized['metadata'] = $stripe_payment->metadata->toArray();
        } else { // charge
            $normalized['amount'] = $this->unformatAmount($stripe_payment->amount, $stripe_payment->currency);
            $normalized['currency'] = strtoupper($stripe_payment->currency);
            $normalized['customer_id'] = $stripe_payment->customer;
            $normalized['description'] = $stripe_payment->description;
            $normalized['metadata'] = $stripe_payment->metadata->toArray();
            $normalized['transaction_id'] = $stripe_payment->balance_transaction;
        }

        return $normalized;
    }

    /**
     * Process webhook event
     */
    private function processWebhookEvent($event): void {
        // Fire WordPress action for other plugins to handle
        do_action('mcs/stripe/webhook', $event);
        do_action('mcs/stripe/webhook/' . $event->type, $event->data->object, $event);

        // Log event processing
        $this->log('INFO', 'Webhook event processed', [
            'event_type' => $event->type,
            'event_id' => $event->id
        ]);
    }
}