<?php
/**
 * Provider Container
 * Service container for managing channel and payment providers
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProviderContainer {

    private static $instance = null;
    private $channel_providers = [];
    private $payment_providers = [];
    private $default_channel_provider = null;
    private $default_payment_provider = null;

    /**
     * Get singleton instance
     */
    public static function getInstance(): ProviderContainer {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        $this->loadProviders();
        $this->loadConfiguration();
    }

    /**
     * Register a channel provider
     *
     * @param string $name Provider name
     * @param AbstractChannelProvider $provider Provider instance
     * @return void
     */
    public function registerChannelProvider(string $name, AbstractChannelProvider $provider): void {
        $this->channel_providers[$name] = $provider;

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'Channel provider registered', [
                'provider' => $name,
                'class' => get_class($provider)
            ]);
        }
    }

    /**
     * Register a payment provider
     *
     * @param string $name Provider name
     * @param AbstractPaymentProvider $provider Provider instance
     * @return void
     */
    public function registerPaymentProvider(string $name, AbstractPaymentProvider $provider): void {
        $this->payment_providers[$name] = $provider;

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'Payment provider registered', [
                'provider' => $name,
                'class' => get_class($provider)
            ]);
        }
    }

    /**
     * Get channel provider by name
     *
     * @param string $name Provider name
     * @return AbstractChannelProvider|null
     */
    public function getChannelProvider(string $name): ?AbstractChannelProvider {
        return $this->channel_providers[$name] ?? null;
    }

    /**
     * Get payment provider by name
     *
     * @param string $name Provider name
     * @return AbstractPaymentProvider|null
     */
    public function getPaymentProvider(string $name): ?AbstractPaymentProvider {
        return $this->payment_providers[$name] ?? null;
    }

    /**
     * Get default channel provider
     *
     * @return AbstractChannelProvider|null
     */
    public function getDefaultChannelProvider(): ?AbstractChannelProvider {
        if ($this->default_channel_provider) {
            return $this->getChannelProvider($this->default_channel_provider);
        }

        // Return first available provider if no default set
        $providers = array_keys($this->channel_providers);
        return !empty($providers) ? $this->getChannelProvider($providers[0]) : null;
    }

    /**
     * Get default payment provider
     *
     * @return AbstractPaymentProvider|null
     */
    public function getDefaultPaymentProvider(): ?AbstractPaymentProvider {
        if ($this->default_payment_provider) {
            return $this->getPaymentProvider($this->default_payment_provider);
        }

        // Return first available provider if no default set
        $providers = array_keys($this->payment_providers);
        return !empty($providers) ? $this->getPaymentProvider($providers[0]) : null;
    }

    /**
     * Set default channel provider
     *
     * @param string $name Provider name
     * @return bool True if set successfully
     */
    public function setDefaultChannelProvider(string $name): bool {
        if (!isset($this->channel_providers[$name])) {
            return false;
        }

        $this->default_channel_provider = $name;
        $this->saveConfiguration();

        return true;
    }

    /**
     * Set default payment provider
     *
     * @param string $name Provider name
     * @return bool True if set successfully
     */
    public function setDefaultPaymentProvider(string $name): bool {
        if (!isset($this->payment_providers[$name])) {
            return false;
        }

        $this->default_payment_provider = $name;
        $this->saveConfiguration();

        return true;
    }

    /**
     * Get all channel providers
     *
     * @return array
     */
    public function getChannelProviders(): array {
        return $this->channel_providers;
    }

    /**
     * Get all payment providers
     *
     * @return array
     */
    public function getPaymentProviders(): array {
        return $this->payment_providers;
    }

    /**
     * Get channel provider names
     *
     * @return array
     */
    public function getChannelProviderNames(): array {
        return array_keys($this->channel_providers);
    }

    /**
     * Get payment provider names
     *
     * @return array
     */
    public function getPaymentProviderNames(): array {
        return array_keys($this->payment_providers);
    }

    /**
     * Configure provider
     *
     * @param string $type 'channel' or 'payment'
     * @param string $name Provider name
     * @param array $config Configuration array
     * @return bool True if configured successfully
     */
    public function configureProvider(string $type, string $name, array $config): bool {
        $provider = null;

        if ($type === 'channel') {
            $provider = $this->getChannelProvider($name);
        } elseif ($type === 'payment') {
            $provider = $this->getPaymentProvider($name);
        }

        if (!$provider) {
            return false;
        }

        // Validate configuration
        $validation = $provider->validateConfig($config);
        if (!$validation['valid']) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Provider configuration validation failed', [
                    'type' => $type,
                    'provider' => $name,
                    'errors' => $validation['errors']
                ]);
            }
            return false;
        }

        // Set configuration
        $provider->setConfig($config);

        // Save to WordPress options
        $this->saveProviderConfig($type, $name, $config);

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Provider configured', [
                'type' => $type,
                'provider' => $name
            ]);
        }

        return true;
    }

    /**
     * Get provider configuration
     *
     * @param string $type 'channel' or 'payment'
     * @param string $name Provider name
     * @return array
     */
    public function getProviderConfig(string $type, string $name): array {
        $option_key = "mcs_{$type}_provider_{$name}";
        return get_option($option_key, []);
    }

    /**
     * Create channel provider instance by name
     *
     * @param string $name Provider name
     * @param array $config Optional configuration
     * @return AbstractChannelProvider|null
     */
    public function createChannelProvider(string $name, array $config = []): ?AbstractChannelProvider {
        $class_map = [
            'dummy' => 'DummyProvider',
            // Add more providers here as they're implemented
            // 'airbnb' => 'AirbnbProvider',
            // 'booking' => 'BookingProvider',
        ];

        $class_name = $class_map[$name] ?? null;
        if (!$class_name) {
            return null;
        }

        $class_path = __DIR__ . "/../Providers/Channel/{$class_name}.php";
        if (!file_exists($class_path)) {
            return null;
        }

        require_once $class_path;

        if (!class_exists($class_name)) {
            return null;
        }

        // Merge with saved configuration
        $saved_config = $this->getProviderConfig('channel', $name);
        $final_config = array_merge($saved_config, $config);

        return new $class_name($final_config);
    }

    /**
     * Create payment provider instance by name
     *
     * @param string $name Provider name
     * @param array $config Optional configuration
     * @return AbstractPaymentProvider|null
     */
    public function createPaymentProvider(string $name, array $config = []): ?AbstractPaymentProvider {
        $class_map = [
            'stripe' => 'StripePaymentProvider',
            // Add more providers here as they're implemented
            // 'paypal' => 'PayPalProvider',
            // 'square' => 'SquareProvider',
        ];

        $class_name = $class_map[$name] ?? null;
        if (!$class_name) {
            return null;
        }

        $class_path = __DIR__ . "/../Providers/Payment/{$class_name}.php";
        if (!file_exists($class_path)) {
            return null;
        }

        require_once $class_path;

        if (!class_exists($class_name)) {
            return null;
        }

        // Merge with saved configuration
        $saved_config = $this->getProviderConfig('payment', $name);
        $final_config = array_merge($saved_config, $config);

        return new $class_name($final_config);
    }

    /**
     * Test provider connection
     *
     * @param string $type 'channel' or 'payment'
     * @param string $name Provider name
     * @return array
     */
    public function testProvider(string $type, string $name): array {
        $provider = null;

        if ($type === 'channel') {
            $provider = $this->getChannelProvider($name);
        } elseif ($type === 'payment') {
            $provider = $this->getPaymentProvider($name);
        }

        if (!$provider) {
            return [
                'success' => false,
                'message' => __('Provider not found', 'minpaku-suite'),
                'data' => []
            ];
        }

        return $provider->testConnection();
    }

    /**
     * Get provider status
     *
     * @param string $type 'channel' or 'payment'
     * @param string $name Provider name
     * @return array
     */
    public function getProviderStatus(string $type, string $name): array {
        $provider = null;

        if ($type === 'channel') {
            $provider = $this->getChannelProvider($name);
        } elseif ($type === 'payment') {
            $provider = $this->getPaymentProvider($name);
        }

        if (!$provider) {
            return [
                'configured' => false,
                'connected' => false,
                'last_error' => __('Provider not found', 'minpaku-suite')
            ];
        }

        $config = $provider->getConfig();
        $required_fields = $provider->getConfigFields();

        // Check if all required fields are configured
        $configured = true;
        foreach ($required_fields as $field_name => $field_config) {
            if (($field_config['required'] ?? false) && empty($config[$field_name])) {
                $configured = false;
                break;
            }
        }

        $connected = false;
        if ($type === 'channel' && method_exists($provider, 'isConnected')) {
            $connected = $provider->isConnected();
        } elseif ($type === 'payment' && method_exists($provider, 'initialize')) {
            $connected = $provider->initialize();
        }

        return [
            'configured' => $configured,
            'connected' => $connected,
            'last_error' => $provider->getLastError()
        ];
    }

    /**
     * Get provider statistics
     *
     * @return array
     */
    public function getStatistics(): array {
        return [
            'channel_providers' => [
                'total' => count($this->channel_providers),
                'configured' => $this->getConfiguredProvidersCount('channel'),
                'connected' => $this->getConnectedProvidersCount('channel')
            ],
            'payment_providers' => [
                'total' => count($this->payment_providers),
                'configured' => $this->getConfiguredProvidersCount('payment'),
                'connected' => $this->getConnectedProvidersCount('payment')
            ],
            'defaults' => [
                'channel' => $this->default_channel_provider,
                'payment' => $this->default_payment_provider
            ]
        ];
    }

    /**
     * Handle webhook for providers
     *
     * @param string $type 'channel' or 'payment'
     * @param string $provider_name Provider name
     * @param array $payload Webhook payload
     * @param string $signature Webhook signature (if applicable)
     * @return array
     */
    public function handleWebhook(string $type, string $provider_name, array $payload, string $signature = ''): array {
        $provider = null;

        if ($type === 'channel') {
            $provider = $this->getChannelProvider($provider_name);
        } elseif ($type === 'payment') {
            $provider = $this->getPaymentProvider($provider_name);
        }

        if (!$provider) {
            return [
                'success' => false,
                'message' => __('Provider not found', 'minpaku-suite')
            ];
        }

        if (!method_exists($provider, 'handleWebhook')) {
            return [
                'success' => false,
                'message' => __('Provider does not support webhooks', 'minpaku-suite')
            ];
        }

        return $provider->handleWebhook($payload, $signature);
    }

    /**
     * Load available providers
     */
    private function loadProviders(): void {
        // Load channel providers
        $this->loadChannelProviders();

        // Load payment providers
        $this->loadPaymentProviders();

        // Allow plugins to register additional providers
        do_action('mcs/providers/load', $this);
    }

    /**
     * Load channel providers
     */
    private function loadChannelProviders(): void {
        // Dummy provider
        $dummy_provider = $this->createChannelProvider('dummy');
        if ($dummy_provider) {
            $this->registerChannelProvider('dummy', $dummy_provider);
        }

        // Additional providers can be loaded here
    }

    /**
     * Load payment providers
     */
    private function loadPaymentProviders(): void {
        // Stripe provider
        $stripe_provider = $this->createPaymentProvider('stripe');
        if ($stripe_provider) {
            $this->registerPaymentProvider('stripe', $stripe_provider);
        }

        // Additional providers can be loaded here
    }

    /**
     * Load configuration from WordPress options
     */
    private function loadConfiguration(): void {
        $config = get_option('mcs_provider_config', []);

        $this->default_channel_provider = $config['default_channel_provider'] ?? null;
        $this->default_payment_provider = $config['default_payment_provider'] ?? null;
    }

    /**
     * Save configuration to WordPress options
     */
    private function saveConfiguration(): void {
        $config = [
            'default_channel_provider' => $this->default_channel_provider,
            'default_payment_provider' => $this->default_payment_provider
        ];

        update_option('mcs_provider_config', $config);
    }

    /**
     * Save provider configuration
     */
    private function saveProviderConfig(string $type, string $name, array $config): void {
        $option_key = "mcs_{$type}_provider_{$name}";
        update_option($option_key, $config);
    }

    /**
     * Get count of configured providers
     */
    private function getConfiguredProvidersCount(string $type): int {
        $providers = $type === 'channel' ? $this->channel_providers : $this->payment_providers;
        $count = 0;

        foreach ($providers as $name => $provider) {
            $status = $this->getProviderStatus($type, $name);
            if ($status['configured']) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get count of connected providers
     */
    private function getConnectedProvidersCount(string $type): int {
        $providers = $type === 'channel' ? $this->channel_providers : $this->payment_providers;
        $count = 0;

        foreach ($providers as $name => $provider) {
            $status = $this->getProviderStatus($type, $name);
            if ($status['connected']) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Reset all configurations (for development/testing)
     */
    public function reset(): void {
        // Clear all provider configurations
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            'mcs_channel_provider_%',
            'mcs_payment_provider_%'
        ));

        delete_option('mcs_provider_config');

        // Reset instance variables
        $this->default_channel_provider = null;
        $this->default_payment_provider = null;

        // Reload providers with default configurations
        $this->loadProviders();
        $this->loadConfiguration();

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Provider container reset');
        }
    }

    /**
     * Get available provider types
     *
     * @return array
     */
    public function getAvailableProviderTypes(): array {
        return [
            'channel' => [
                'dummy' => __('Dummy Provider (Testing)', 'minpaku-suite'),
                // Add more as implemented
            ],
            'payment' => [
                'stripe' => __('Stripe', 'minpaku-suite'),
                // Add more as implemented
            ]
        ];
    }

    /**
     * Export provider configurations
     *
     * @return array
     */
    public function exportConfigurations(): array {
        $export = [
            'defaults' => [
                'channel' => $this->default_channel_provider,
                'payment' => $this->default_payment_provider
            ],
            'channel_providers' => [],
            'payment_providers' => []
        ];

        // Export channel provider configs
        foreach ($this->getChannelProviderNames() as $name) {
            $export['channel_providers'][$name] = $this->getProviderConfig('channel', $name);
        }

        // Export payment provider configs
        foreach ($this->getPaymentProviderNames() as $name) {
            $export['payment_providers'][$name] = $this->getProviderConfig('payment', $name);
        }

        return $export;
    }

    /**
     * Import provider configurations
     *
     * @param array $configurations
     * @return bool
     */
    public function importConfigurations(array $configurations): bool {
        try {
            // Import defaults
            if (isset($configurations['defaults'])) {
                if (!empty($configurations['defaults']['channel'])) {
                    $this->setDefaultChannelProvider($configurations['defaults']['channel']);
                }
                if (!empty($configurations['defaults']['payment'])) {
                    $this->setDefaultPaymentProvider($configurations['defaults']['payment']);
                }
            }

            // Import channel provider configs
            if (isset($configurations['channel_providers'])) {
                foreach ($configurations['channel_providers'] as $name => $config) {
                    $this->configureProvider('channel', $name, $config);
                }
            }

            // Import payment provider configs
            if (isset($configurations['payment_providers'])) {
                foreach ($configurations['payment_providers'] as $name => $config) {
                    $this->configureProvider('payment', $name, $config);
                }
            }

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Provider configurations imported successfully');
            }

            return true;

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to import provider configurations', [
                    'error' => $e->getMessage()
                ]);
            }

            return false;
        }
    }
}