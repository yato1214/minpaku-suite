<?php
/**
 * Abstract Channel Provider
 * Base class for external channel integrations (Airbnb, Booking.com, etc.)
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractChannelProvider {

    protected $config = [];
    protected $connected = false;
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
     * @return array ['field_name' => ['label' => 'Label', 'type' => 'text|password|select', 'required' => true|false]]
     */
    abstract public function getConfigFields(): array;

    /**
     * Connect to the external channel
     *
     * @return bool True on success, false on failure
     */
    abstract public function connect(): bool;

    /**
     * Test connection to the channel
     *
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    abstract public function testConnection(): array;

    /**
     * Fetch availability from the channel
     *
     * @param string $property_id External property ID
     * @param string $start_date YYYY-MM-DD format
     * @param string $end_date YYYY-MM-DD format
     * @return array ['success' => bool, 'availability' => array, 'message' => string]
     */
    abstract public function fetchAvailability(string $property_id, string $start_date, string $end_date): array;

    /**
     * Fetch reservations from the channel
     *
     * @param string $property_id External property ID
     * @param string $start_date YYYY-MM-DD format (optional)
     * @param string $end_date YYYY-MM-DD format (optional)
     * @return array ['success' => bool, 'reservations' => array, 'message' => string]
     */
    abstract public function fetchReservations(string $property_id, string $start_date = null, string $end_date = null): array;

    /**
     * Push reservation to the channel
     *
     * @param string $property_id External property ID
     * @param array $reservation_data Reservation details
     * @return array ['success' => bool, 'reservation_id' => string, 'message' => string]
     */
    abstract public function pushReservation(string $property_id, array $reservation_data): array;

    /**
     * Update reservation on the channel
     *
     * @param string $property_id External property ID
     * @param string $reservation_id External reservation ID
     * @param array $reservation_data Updated reservation details
     * @return array ['success' => bool, 'message' => string]
     */
    abstract public function updateReservation(string $property_id, string $reservation_id, array $reservation_data): array;

    /**
     * Cancel reservation on the channel
     *
     * @param string $property_id External property ID
     * @param string $reservation_id External reservation ID
     * @param string $reason Cancellation reason
     * @return array ['success' => bool, 'message' => string]
     */
    abstract public function cancelReservation(string $property_id, string $reservation_id, string $reason = ''): array;

    /**
     * Push availability/rates to the channel
     *
     * @param string $property_id External property ID
     * @param array $availability_data Availability and rate data
     * @return array ['success' => bool, 'message' => string]
     */
    abstract public function pushAvailability(string $property_id, array $availability_data): array;

    /**
     * Get property details from the channel
     *
     * @param string $property_id External property ID
     * @return array ['success' => bool, 'property' => array, 'message' => string]
     */
    abstract public function getProperty(string $property_id): array;

    /**
     * List properties from the channel
     *
     * @return array ['success' => bool, 'properties' => array, 'message' => string]
     */
    abstract public function listProperties(): array;

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
        $this->connected = false; // Reset connection status
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
     * Check if provider is connected
     *
     * @return bool
     */
    public function isConnected(): bool {
        return $this->connected;
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
            MCS_Logger::log('ERROR', 'Channel provider error', [
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
     * Make HTTP request (helper method)
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return array ['success' => bool, 'data' => mixed, 'status_code' => int, 'message' => string]
     */
    protected function makeRequest(string $method, string $url, array $data = [], array $headers = []): array {
        $args = [
            'method' => strtoupper($method),
            'headers' => array_merge([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Minpaku Suite/1.0'
            ], $headers),
            'timeout' => 30
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'data' => null,
                'status_code' => 0,
                'message' => $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);

        $success = $status_code >= 200 && $status_code < 300;

        return [
            'success' => $success,
            'data' => $decoded_body,
            'status_code' => $status_code,
            'message' => $success ? 'Request successful' : sprintf('HTTP %d error', $status_code)
        ];
    }

    /**
     * Get rate limiting information
     *
     * @return array ['requests_per_minute' => int, 'requests_per_hour' => int, 'requests_per_day' => int]
     */
    public function getRateLimits(): array {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'requests_per_day' => 10000
        ];
    }

    /**
     * Check if rate limit allows request
     *
     * @return bool
     */
    public function isRateLimitOk(): bool {
        // Basic rate limiting implementation
        $cache_key = 'mcs_rate_limit_' . $this->getName();
        $requests = get_transient($cache_key) ?: [];
        $now = time();

        // Clean old requests (older than 1 hour)
        $requests = array_filter($requests, function($timestamp) use ($now) {
            return ($now - $timestamp) < 3600;
        });

        $limits = $this->getRateLimits();

        // Check minute limit
        $recent_requests = array_filter($requests, function($timestamp) use ($now) {
            return ($now - $timestamp) < 60;
        });

        if (count($recent_requests) >= $limits['requests_per_minute']) {
            return false;
        }

        // Check hour limit
        if (count($requests) >= $limits['requests_per_hour']) {
            return false;
        }

        return true;
    }

    /**
     * Record request for rate limiting
     *
     * @return void
     */
    protected function recordRequest(): void {
        $cache_key = 'mcs_rate_limit_' . $this->getName();
        $requests = get_transient($cache_key) ?: [];
        $requests[] = time();

        // Keep only last hour of requests
        $one_hour_ago = time() - 3600;
        $requests = array_filter($requests, function($timestamp) use ($one_hour_ago) {
            return $timestamp > $one_hour_ago;
        });

        set_transient($cache_key, $requests, HOUR_IN_SECONDS);
    }

    /**
     * Normalize reservation data format
     *
     * @param array $external_data Raw data from external channel
     * @return array Normalized reservation data
     */
    protected function normalizeReservationData(array $external_data): array {
        // Override in child classes to handle channel-specific data formats
        return [
            'external_id' => $external_data['id'] ?? '',
            'guest_name' => $external_data['guest_name'] ?? '',
            'guest_email' => $external_data['guest_email'] ?? '',
            'check_in' => $external_data['check_in'] ?? '',
            'check_out' => $external_data['check_out'] ?? '',
            'guests' => $external_data['guests'] ?? 1,
            'status' => $external_data['status'] ?? 'confirmed',
            'total_amount' => $external_data['total_amount'] ?? 0,
            'currency' => $external_data['currency'] ?? 'USD',
            'created_at' => $external_data['created_at'] ?? current_time('mysql'),
            'updated_at' => $external_data['updated_at'] ?? current_time('mysql'),
            'notes' => $external_data['notes'] ?? '',
            'channel_data' => $external_data // Keep original data
        ];
    }

    /**
     * Normalize availability data format
     *
     * @param array $external_data Raw data from external channel
     * @return array Normalized availability data
     */
    protected function normalizeAvailabilityData(array $external_data): array {
        // Override in child classes to handle channel-specific data formats
        $normalized = [];

        foreach ($external_data as $date => $data) {
            $normalized[$date] = [
                'available' => $data['available'] ?? true,
                'min_stay' => $data['min_stay'] ?? 1,
                'max_stay' => $data['max_stay'] ?? 30,
                'rate' => $data['rate'] ?? 0,
                'closed' => $data['closed'] ?? false,
                'channel_data' => $data
            ];
        }

        return $normalized;
    }

    /**
     * Get provider capabilities
     *
     * @return array ['can_fetch_availability', 'can_push_availability', 'can_fetch_reservations', etc.]
     */
    public function getCapabilities(): array {
        return [
            'can_fetch_availability' => true,
            'can_push_availability' => true,
            'can_fetch_reservations' => true,
            'can_push_reservations' => true,
            'can_update_reservations' => true,
            'can_cancel_reservations' => true,
            'can_fetch_properties' => true,
            'can_manage_rates' => true,
            'supports_real_time' => false,
            'supports_webhooks' => false
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
     * Get sync frequency recommendation
     *
     * @return array ['availability' => 'hourly|daily', 'reservations' => 'minutes|hourly']
     */
    public function getSyncFrequency(): array {
        return [
            'availability' => 'daily',
            'reservations' => 'hourly'
        ];
    }

    /**
     * Get webhook endpoint URL for this provider
     *
     * @return string
     */
    public function getWebhookUrl(): string {
        return add_query_arg([
            'mcs_webhook' => 'channel',
            'provider' => $this->getName()
        ], home_url('/'));
    }

    /**
     * Handle webhook from channel
     *
     * @param array $payload Webhook payload
     * @return array ['success' => bool, 'message' => string]
     */
    public function handleWebhook(array $payload): array {
        // Override in child classes to handle webhooks
        return [
            'success' => false,
            'message' => 'Webhooks not supported by this provider'
        ];
    }
}