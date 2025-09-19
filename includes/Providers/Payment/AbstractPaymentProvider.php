<?php
/**
 * Abstract Payment Provider
 * Base class for payment integrations (Stripe, PayPal, etc.)
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractPaymentProvider {

    protected $config = [];
    protected $last_error = null;

    /**
     * Constructor
     *
     * @param array $config Provider configuration
     */
    public function __construct(array $config = []) {
        $this->config = $config;
    }

    /**
     * Get provider name
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Get provider display name
     *
     * @return string
     */
    abstract public function getDisplayName(): string;

    /**
     * Get provider description
     *
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Get required configuration fields
     *
     * @return array
     */
    abstract public function getConfigFields(): array;

    /**
     * Initialize the payment provider
     *
     * @return bool True on success, false on failure
     */
    abstract public function initialize(): bool;

    /**
     * Test the connection/configuration
     *
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    abstract public function testConnection(): array;

    /**
     * Create a payment intent/session
     *
     * @param array $payment_data Payment details
     * @return array ['success' => bool, 'payment_intent_id' => string, 'client_secret' => string, 'message' => string]
     */
    abstract public function createPaymentIntent(array $payment_data): array;

    /**
     * Charge a payment method
     *
     * @param array $charge_data Charge details
     * @return array ['success' => bool, 'charge_id' => string, 'transaction_id' => string, 'message' => string]
     */
    abstract public function charge(array $charge_data): array;

    /**
     * Capture a previously authorized payment
     *
     * @param string $payment_intent_id Payment intent ID
     * @param float $amount Amount to capture (optional, defaults to full authorized amount)
     * @return array ['success' => bool, 'charge_id' => string, 'message' => string]
     */
    abstract public function capture(string $payment_intent_id, float $amount = null): array;

    /**
     * Cancel/void a payment
     *
     * @param string $payment_intent_id Payment intent ID
     * @param string $reason Cancellation reason
     * @return array ['success' => bool, 'message' => string]
     */
    abstract public function cancel(string $payment_intent_id, string $reason = ''): array;

    /**
     * Refund a payment
     *
     * @param string $charge_id Charge ID to refund
     * @param float $amount Amount to refund (optional, defaults to full amount)
     * @param string $reason Refund reason
     * @return array ['success' => bool, 'refund_id' => string, 'message' => string]
     */
    abstract public function refund(string $charge_id, float $amount = null, string $reason = ''): array;

    /**
     * Get payment details
     *
     * @param string $payment_id Payment ID (charge_id or payment_intent_id)
     * @return array ['success' => bool, 'payment' => array, 'message' => string]
     */
    abstract public function getPayment(string $payment_id): array;

    /**
     * List payments for a customer
     *
     * @param string $customer_id Customer ID
     * @param array $filters Optional filters (date_range, status, etc.)
     * @return array ['success' => bool, 'payments' => array, 'message' => string]
     */
    abstract public function listPayments(string $customer_id, array $filters = []): array;

    /**
     * Create a customer
     *
     * @param array $customer_data Customer details
     * @return array ['success' => bool, 'customer_id' => string, 'message' => string]
     */
    abstract public function createCustomer(array $customer_data): array;

    /**
     * Update a customer
     *
     * @param string $customer_id Customer ID
     * @param array $customer_data Updated customer details
     * @return array ['success' => bool, 'message' => string]
     */
    abstract public function updateCustomer(string $customer_id, array $customer_data): array;

    /**
     * Get customer details
     *
     * @param string $customer_id Customer ID
     * @return array ['success' => bool, 'customer' => array, 'message' => string]
     */
    abstract public function getCustomer(string $customer_id): array;

    /**
     * Delete a customer
     *
     * @param string $customer_id Customer ID
     * @return array ['success' => bool, 'message' => string]
     */
    abstract public function deleteCustomer(string $customer_id): array;

    /**
     * Add payment method to customer
     *
     * @param string $customer_id Customer ID
     * @param array $payment_method_data Payment method details
     * @return array ['success' => bool, 'payment_method_id' => string, 'message' => string]
     */
    abstract public function addPaymentMethod(string $customer_id, array $payment_method_data): array;

    /**
     * Remove payment method from customer
     *
     * @param string $payment_method_id Payment method ID
     * @return array ['success' => bool, 'message' => string]
     */
    abstract public function removePaymentMethod(string $payment_method_id): array;

    /**
     * List customer payment methods
     *
     * @param string $customer_id Customer ID
     * @return array ['success' => bool, 'payment_methods' => array, 'message' => string]
     */
    abstract public function listPaymentMethods(string $customer_id): array;

    /**
     * Handle webhook from payment provider
     *
     * @param array $payload Webhook payload
     * @param string $signature Webhook signature (if applicable)
     * @return array ['success' => bool, 'message' => string]
     */
    abstract public function handleWebhook(array $payload, string $signature = ''): array;

    /**
     * Validate configuration
     *
     * @param array $config Configuration to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateConfig(array $config): array {
        $errors = [];
        $required_fields = $this->getConfigFields();

        foreach ($required_fields as $field_name => $field_config) {
            if (($field_config['required'] ?? false) && empty($config[$field_name])) {
                $errors[] = sprintf(__('Field "%s" is required', 'minpaku-suite'), $field_config['label'] ?? $field_name);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Set configuration
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void {
        $this->config = $config;
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfig(): array {
        return $this->config;
    }

    /**
     * Get last error message
     *
     * @return string|null
     */
    public function getLastError(): ?string {
        return $this->last_error;
    }

    /**
     * Set error message
     *
     * @param string $error
     * @return void
     */
    protected function setError(string $error): void {
        $this->last_error = $error;

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('ERROR', 'Payment provider error', [
                'provider' => $this->getName(),
                'error' => $error
            ]);
        }
    }

    /**
     * Clear error message
     *
     * @return void
     */
    protected function clearError(): void {
        $this->last_error = null;
    }

    /**
     * Log activity
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void {
        if (class_exists('MCS_Logger')) {
            $context['provider'] = $this->getName();
            MCS_Logger::log($level, $message, $context);
        }
    }

    /**
     * Get supported currencies
     *
     * @return array
     */
    public function getSupportedCurrencies(): array {
        return ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'];
    }

    /**
     * Check if currency is supported
     *
     * @param string $currency
     * @return bool
     */
    public function supportsCurrency(string $currency): bool {
        return in_array(strtoupper($currency), $this->getSupportedCurrencies());
    }

    /**
     * Get payment methods supported by this provider
     *
     * @return array
     */
    public function getSupportedPaymentMethods(): array {
        return ['card', 'bank_transfer', 'digital_wallet'];
    }

    /**
     * Check if payment method is supported
     *
     * @param string $payment_method
     * @return bool
     */
    public function supportsPaymentMethod(string $payment_method): bool {
        return in_array($payment_method, $this->getSupportedPaymentMethods());
    }

    /**
     * Get provider capabilities
     *
     * @return array
     */
    public function getCapabilities(): array {
        return [
            'can_charge' => true,
            'can_refund' => true,
            'can_capture' => true,
            'can_cancel' => true,
            'supports_subscriptions' => false,
            'supports_marketplace' => false,
            'supports_instant_payouts' => false,
            'supports_webhooks' => false,
            'requires_redirect' => false
        ];
    }

    /**
     * Check if provider supports a specific capability
     *
     * @param string $capability
     * @return bool
     */
    public function supports(string $capability): bool {
        $capabilities = $this->getCapabilities();
        return $capabilities[$capability] ?? false;
    }

    /**
     * Get webhook endpoint URL for this provider
     *
     * @return string
     */
    public function getWebhookUrl(): string {
        return add_query_arg([
            'mcs_webhook' => 'payment',
            'provider' => $this->getName()
        ], home_url('/'));
    }

    /**
     * Format amount for provider (handle currency decimals)
     *
     * @param float $amount
     * @param string $currency
     * @return int
     */
    protected function formatAmount(float $amount, string $currency): int {
        // Most currencies use 2 decimal places
        $zero_decimal_currencies = ['JPY', 'KRW', 'VND', 'CLP'];

        if (in_array(strtoupper($currency), $zero_decimal_currencies)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }

    /**
     * Unformat amount from provider format
     *
     * @param int $amount
     * @param string $currency
     * @return float
     */
    protected function unformatAmount(int $amount, string $currency): float {
        $zero_decimal_currencies = ['JPY', 'KRW', 'VND', 'CLP'];

        if (in_array(strtoupper($currency), $zero_decimal_currencies)) {
            return (float) $amount;
        }

        return (float) ($amount / 100);
    }

    /**
     * Normalize payment data format
     *
     * @param array $external_data Raw data from payment provider
     * @return array Normalized payment data
     */
    protected function normalizePaymentData(array $external_data): array {
        return [
            'id' => $external_data['id'] ?? '',
            'amount' => $external_data['amount'] ?? 0,
            'currency' => $external_data['currency'] ?? 'USD',
            'status' => $external_data['status'] ?? 'unknown',
            'payment_method' => $external_data['payment_method'] ?? 'unknown',
            'customer_id' => $external_data['customer_id'] ?? '',
            'description' => $external_data['description'] ?? '',
            'metadata' => $external_data['metadata'] ?? [],
            'created_at' => $external_data['created_at'] ?? current_time('mysql'),
            'updated_at' => $external_data['updated_at'] ?? current_time('mysql'),
            'provider_data' => $external_data // Keep original data
        ];
    }

    /**
     * Get transaction fees for this provider
     *
     * @param float $amount
     * @param string $currency
     * @param string $payment_method
     * @return array ['fixed_fee' => float, 'percentage_fee' => float, 'total_fee' => float]
     */
    public function calculateFees(float $amount, string $currency = 'USD', string $payment_method = 'card'): array {
        // Override in child classes with actual fee structure
        return [
            'fixed_fee' => 0.30,
            'percentage_fee' => 2.9,
            'total_fee' => 0.30 + ($amount * 0.029)
        ];
    }

    /**
     * Validate payment data before processing
     *
     * @param array $payment_data
     * @return array ['valid' => bool, 'errors' => array]
     */
    protected function validatePaymentData(array $payment_data): array {
        $errors = [];

        // Required fields
        $required_fields = ['amount', 'currency'];
        foreach ($required_fields as $field) {
            if (!isset($payment_data[$field]) || empty($payment_data[$field])) {
                $errors[] = sprintf(__('Field "%s" is required', 'minpaku-suite'), $field);
            }
        }

        // Amount validation
        if (isset($payment_data['amount'])) {
            $amount = (float) $payment_data['amount'];
            if ($amount <= 0) {
                $errors[] = __('Amount must be greater than zero', 'minpaku-suite');
            }

            // Check minimum amounts (varies by currency)
            $min_amounts = [
                'USD' => 0.50,
                'EUR' => 0.50,
                'GBP' => 0.30,
                'JPY' => 50
            ];

            $currency = strtoupper($payment_data['currency'] ?? 'USD');
            $min_amount = $min_amounts[$currency] ?? 0.50;

            if ($amount < $min_amount) {
                $errors[] = sprintf(__('Amount must be at least %s %s', 'minpaku-suite'), $min_amount, $currency);
            }
        }

        // Currency validation
        if (isset($payment_data['currency']) && !$this->supportsCurrency($payment_data['currency'])) {
            $errors[] = sprintf(__('Currency "%s" is not supported', 'minpaku-suite'), $payment_data['currency']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get payment status mapping
     *
     * @return array
     */
    protected function getStatusMapping(): array {
        return [
            'pending' => 'pending',
            'processing' => 'processing',
            'succeeded' => 'completed',
            'failed' => 'failed',
            'canceled' => 'cancelled',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'partially_refunded' => 'partially_refunded'
        ];
    }

    /**
     * Map external status to internal status
     *
     * @param string $external_status
     * @return string
     */
    protected function mapStatus(string $external_status): string {
        $mapping = $this->getStatusMapping();
        return $mapping[strtolower($external_status)] ?? 'unknown';
    }

    /**
     * Generate payment description
     *
     * @param array $payment_data
     * @return string
     */
    protected function generateDescription(array $payment_data): string {
        $property_name = $payment_data['property_name'] ?? '';
        $guest_name = $payment_data['guest_name'] ?? '';
        $dates = '';

        if (!empty($payment_data['check_in']) && !empty($payment_data['check_out'])) {
            $dates = sprintf(' (%s to %s)', $payment_data['check_in'], $payment_data['check_out']);
        }

        if ($property_name && $guest_name) {
            return sprintf(__('Booking: %s for %s%s', 'minpaku-suite'), $property_name, $guest_name, $dates);
        } elseif ($property_name) {
            return sprintf(__('Booking: %s%s', 'minpaku-suite'), $property_name, $dates);
        } else {
            return __('Property booking payment', 'minpaku-suite');
        }
    }

    /**
     * Create idempotency key for request
     *
     * @param array $data
     * @return string
     */
    protected function createIdempotencyKey(array $data): string {
        $key_data = [
            'amount' => $data['amount'] ?? '',
            'currency' => $data['currency'] ?? '',
            'customer_id' => $data['customer_id'] ?? '',
            'timestamp' => floor(time() / 300) // 5-minute window
        ];

        return 'mcs_' . hash('sha256', json_encode($key_data));
    }
}