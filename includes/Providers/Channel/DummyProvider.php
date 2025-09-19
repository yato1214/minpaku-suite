<?php
/**
 * Dummy Channel Provider
 * Mock implementation for testing and development
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/AbstractChannelProvider.php';

class DummyProvider extends AbstractChannelProvider {

    private $mock_data = [];

    /**
     * Constructor
     */
    public function __construct(array $config = []) {
        parent::__construct($config);
        $this->initializeMockData();
    }

    /**
     * Get provider name
     */
    public function getName(): string {
        return 'dummy';
    }

    /**
     * Get provider display name
     */
    public function getDisplayName(): string {
        return __('Dummy Provider (Testing)', 'minpaku-suite');
    }

    /**
     * Get provider description
     */
    public function getDescription(): string {
        return __('Mock provider for testing and development. Returns dummy data for all operations.', 'minpaku-suite');
    }

    /**
     * Get required configuration fields
     */
    public function getConfigFields(): array {
        return [
            'api_key' => [
                'label' => __('API Key', 'minpaku-suite'),
                'type' => 'password',
                'required' => true,
                'description' => __('Any value will work for testing', 'minpaku-suite')
            ],
            'base_url' => [
                'label' => __('Base URL', 'minpaku-suite'),
                'type' => 'url',
                'required' => false,
                'default' => 'https://api.dummy-channel.com',
                'description' => __('Mock API endpoint URL', 'minpaku-suite')
            ],
            'delay_simulation' => [
                'label' => __('Simulate API Delay (seconds)', 'minpaku-suite'),
                'type' => 'number',
                'required' => false,
                'default' => 0,
                'description' => __('Add artificial delay to simulate real API response times', 'minpaku-suite')
            ],
            'error_rate' => [
                'label' => __('Error Rate (%)', 'minpaku-suite'),
                'type' => 'number',
                'required' => false,
                'default' => 0,
                'description' => __('Percentage of requests that should fail (for testing error handling)', 'minpaku-suite')
            ]
        ];
    }

    /**
     * Connect to the channel
     */
    public function connect(): bool {
        $this->clearError();

        if (empty($this->config['api_key'])) {
            $this->setError(__('API key is required', 'minpaku-suite'));
            return false;
        }

        // Simulate connection delay
        $this->simulateDelay();

        // Simulate potential connection failure
        if ($this->shouldSimulateError()) {
            $this->setError(__('Simulated connection failure', 'minpaku-suite'));
            return false;
        }

        $this->connected = true;
        $this->log('INFO', 'Connected to dummy channel', ['api_key' => substr($this->config['api_key'], 0, 4) . '...']);

        return true;
    }

    /**
     * Test connection
     */
    public function testConnection(): array {
        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => $this->getLastError(),
                'data' => []
            ];
        }

        return [
            'success' => true,
            'message' => __('Connection successful', 'minpaku-suite'),
            'data' => [
                'provider' => $this->getDisplayName(),
                'api_version' => '1.0',
                'rate_limits' => $this->getRateLimits(),
                'capabilities' => $this->getCapabilities()
            ]
        ];
    }

    /**
     * Fetch availability
     */
    public function fetchAvailability(string $property_id, string $start_date, string $end_date): array {
        if (!$this->isConnected() && !$this->connect()) {
            return [
                'success' => false,
                'availability' => [],
                'message' => $this->getLastError()
            ];
        }

        $this->simulateDelay();

        if ($this->shouldSimulateError()) {
            return [
                'success' => false,
                'availability' => [],
                'message' => __('Simulated API error while fetching availability', 'minpaku-suite')
            ];
        }

        $availability = $this->generateMockAvailability($property_id, $start_date, $end_date);

        $this->log('INFO', 'Fetched availability', [
            'property_id' => $property_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'days_returned' => count($availability)
        ]);

        return [
            'success' => true,
            'availability' => $availability,
            'message' => sprintf(__('Fetched availability for %d days', 'minpaku-suite'), count($availability))
        ];
    }

    /**
     * Fetch reservations
     */
    public function fetchReservations(string $property_id, string $start_date = null, string $end_date = null): array {
        if (!$this->isConnected() && !$this->connect()) {
            return [
                'success' => false,
                'reservations' => [],
                'message' => $this->getLastError()
            ];
        }

        $this->simulateDelay();

        if ($this->shouldSimulateError()) {
            return [
                'success' => false,
                'reservations' => [],
                'message' => __('Simulated API error while fetching reservations', 'minpaku-suite')
            ];
        }

        $reservations = $this->generateMockReservations($property_id, $start_date, $end_date);

        $this->log('INFO', 'Fetched reservations', [
            'property_id' => $property_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'reservations_count' => count($reservations)
        ]);

        return [
            'success' => true,
            'reservations' => $reservations,
            'message' => sprintf(__('Fetched %d reservations', 'minpaku-suite'), count($reservations))
        ];
    }

    /**
     * Push reservation
     */
    public function pushReservation(string $property_id, array $reservation_data): array {
        if (!$this->isConnected() && !$this->connect()) {
            return [
                'success' => false,
                'reservation_id' => '',
                'message' => $this->getLastError()
            ];
        }

        $this->simulateDelay();

        if ($this->shouldSimulateError()) {
            return [
                'success' => false,
                'reservation_id' => '',
                'message' => __('Simulated API error while creating reservation', 'minpaku-suite')
            ];
        }

        $reservation_id = 'dummy_res_' . uniqid();

        // Store in mock data for later retrieval
        if (!isset($this->mock_data['reservations'][$property_id])) {
            $this->mock_data['reservations'][$property_id] = [];
        }

        $this->mock_data['reservations'][$property_id][$reservation_id] = array_merge($reservation_data, [
            'id' => $reservation_id,
            'status' => 'confirmed',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);

        $this->log('INFO', 'Created reservation', [
            'property_id' => $property_id,
            'reservation_id' => $reservation_id,
            'guest_name' => $reservation_data['guest_name'] ?? 'Unknown'
        ]);

        return [
            'success' => true,
            'reservation_id' => $reservation_id,
            'message' => __('Reservation created successfully', 'minpaku-suite')
        ];
    }

    /**
     * Update reservation
     */
    public function updateReservation(string $property_id, string $reservation_id, array $reservation_data): array {
        if (!$this->isConnected() && !$this->connect()) {
            return [
                'success' => false,
                'message' => $this->getLastError()
            ];
        }

        $this->simulateDelay();

        if ($this->shouldSimulateError()) {
            return [
                'success' => false,
                'message' => __('Simulated API error while updating reservation', 'minpaku-suite')
            ];
        }

        // Update mock data
        if (isset($this->mock_data['reservations'][$property_id][$reservation_id])) {
            $this->mock_data['reservations'][$property_id][$reservation_id] = array_merge(
                $this->mock_data['reservations'][$property_id][$reservation_id],
                $reservation_data,
                ['updated_at' => current_time('mysql')]
            );
        }

        $this->log('INFO', 'Updated reservation', [
            'property_id' => $property_id,
            'reservation_id' => $reservation_id
        ]);

        return [
            'success' => true,
            'message' => __('Reservation updated successfully', 'minpaku-suite')
        ];
    }

    /**
     * Cancel reservation
     */
    public function cancelReservation(string $property_id, string $reservation_id, string $reason = ''): array {
        if (!$this->isConnected() && !$this->connect()) {
            return [
                'success' => false,
                'message' => $this->getLastError()
            ];
        }

        $this->simulateDelay();

        if ($this->shouldSimulateError()) {
            return [
                'success' => false,
                'message' => __('Simulated API error while cancelling reservation', 'minpaku-suite')
            ];
        }

        // Update mock data
        if (isset($this->mock_data['reservations'][$property_id][$reservation_id])) {
            $this->mock_data['reservations'][$property_id][$reservation_id]['status'] = 'cancelled';
            $this->mock_data['reservations'][$property_id][$reservation_id]['cancellation_reason'] = $reason;
            $this->mock_data['reservations'][$property_id][$reservation_id]['cancelled_at'] = current_time('mysql');
        }

        $this->log('INFO', 'Cancelled reservation', [
            'property_id' => $property_id,
            'reservation_id' => $reservation_id,
            'reason' => $reason
        ]);

        return [
            'success' => true,
            'message' => __('Reservation cancelled successfully', 'minpaku-suite')
        ];
    }

    /**
     * Push availability
     */
    public function pushAvailability(string $property_id, array $availability_data): array {
        if (!$this->isConnected() && !$this->connect()) {
            return [
                'success' => false,
                'message' => $this->getLastError()
            ];
        }

        $this->simulateDelay();

        if ($this->shouldSimulateError()) {
            return [
                'success' => false,
                'message' => __('Simulated API error while updating availability', 'minpaku-suite')
            ];
        }

        // Store in mock data
        if (!isset($this->mock_data['availability'][$property_id])) {
            $this->mock_data['availability'][$property_id] = [];
        }

        foreach ($availability_data as $date => $data) {
            $this->mock_data['availability'][$property_id][$date] = $data;
        }

        $this->log('INFO', 'Updated availability', [
            'property_id' => $property_id,
            'dates_updated' => count($availability_data)
        ]);

        return [
            'success' => true,
            'message' => sprintf(__('Updated availability for %d dates', 'minpaku-suite'), count($availability_data))
        ];
    }

    /**
     * Get property
     */
    public function getProperty(string $property_id): array {
        if (!$this->isConnected() && !$this->connect()) {
            return [
                'success' => false,
                'property' => [],
                'message' => $this->getLastError()
            ];
        }

        $this->simulateDelay();

        if ($this->shouldSimulateError()) {
            return [
                'success' => false,
                'property' => [],
                'message' => __('Simulated API error while fetching property', 'minpaku-suite')
            ];
        }

        $property = $this->generateMockProperty($property_id);

        return [
            'success' => true,
            'property' => $property,
            'message' => __('Property retrieved successfully', 'minpaku-suite')
        ];
    }

    /**
     * List properties
     */
    public function listProperties(): array {
        if (!$this->isConnected() && !$this->connect()) {
            return [
                'success' => false,
                'properties' => [],
                'message' => $this->getLastError()
            ];
        }

        $this->simulateDelay();

        if ($this->shouldSimulateError()) {
            return [
                'success' => false,
                'properties' => [],
                'message' => __('Simulated API error while listing properties', 'minpaku-suite')
            ];
        }

        $properties = [];
        for ($i = 1; $i <= 5; $i++) {
            $properties[] = $this->generateMockProperty("prop_$i");
        }

        return [
            'success' => true,
            'properties' => $properties,
            'message' => sprintf(__('Retrieved %d properties', 'minpaku-suite'), count($properties))
        ];
    }

    /**
     * Get provider capabilities
     */
    public function getCapabilities(): array {
        return array_merge(parent::getCapabilities(), [
            'supports_real_time' => true,
            'supports_webhooks' => true,
            'can_simulate_errors' => true,
            'can_simulate_delays' => true
        ]);
    }

    /**
     * Handle webhook
     */
    public function handleWebhook(array $payload): array {
        $this->log('INFO', 'Webhook received', ['payload' => $payload]);

        // Simulate webhook processing
        return [
            'success' => true,
            'message' => __('Webhook processed successfully', 'minpaku-suite')
        ];
    }

    /**
     * Initialize mock data
     */
    private function initializeMockData(): void {
        $this->mock_data = [
            'properties' => [],
            'reservations' => [],
            'availability' => []
        ];
    }

    /**
     * Generate mock availability data
     */
    private function generateMockAvailability(string $property_id, string $start_date, string $end_date): array {
        $availability = [];
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = new DateInterval('P1D');

        while ($start <= $end) {
            $date = $start->format('Y-m-d');
            $day_of_week = (int) $start->format('w');

            // Simulate some bookings (weekends more likely to be booked)
            $is_weekend = in_array($day_of_week, [5, 6, 0]); // Fri, Sat, Sun
            $booking_probability = $is_weekend ? 0.3 : 0.15;
            $is_booked = rand(1, 100) <= ($booking_probability * 100);

            // Base rate with weekend premium
            $base_rate = 100;
            $weekend_multiplier = $is_weekend ? 1.5 : 1.0;
            $rate = $base_rate * $weekend_multiplier;

            $availability[$date] = [
                'available' => !$is_booked,
                'min_stay' => rand(1, 3),
                'max_stay' => 14,
                'rate' => $rate,
                'closed' => false,
                'notes' => $is_booked ? 'Occupied' : 'Available'
            ];

            $start->add($interval);
        }

        return $availability;
    }

    /**
     * Generate mock reservations
     */
    private function generateMockReservations(string $property_id, string $start_date = null, string $end_date = null): array {
        $reservations = [];
        $count = rand(0, 5); // 0-5 reservations

        for ($i = 0; $i < $count; $i++) {
            $check_in = date('Y-m-d', strtotime('+' . rand(1, 30) . ' days'));
            $nights = rand(1, 7);
            $check_out = date('Y-m-d', strtotime($check_in . ' +' . $nights . ' days'));

            $guests = [
                ['John Smith', 'john.smith@example.com'],
                ['Jane Doe', 'jane.doe@example.com'],
                ['Bob Johnson', 'bob.johnson@example.com'],
                ['Alice Brown', 'alice.brown@example.com'],
                ['Charlie Wilson', 'charlie.wilson@example.com']
            ];

            $guest = $guests[array_rand($guests)];

            $reservations[] = [
                'id' => 'dummy_res_' . uniqid(),
                'property_id' => $property_id,
                'guest_name' => $guest[0],
                'guest_email' => $guest[1],
                'check_in' => $check_in,
                'check_out' => $check_out,
                'guests' => rand(1, 4),
                'status' => rand(0, 10) > 8 ? 'cancelled' : 'confirmed',
                'total_amount' => rand(200, 1000),
                'currency' => 'USD',
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
                'updated_at' => date('Y-m-d H:i:s'),
                'notes' => 'Mock reservation from dummy provider',
                'booking_source' => 'Dummy Channel'
            ];
        }

        return $reservations;
    }

    /**
     * Generate mock property data
     */
    private function generateMockProperty(string $property_id): array {
        $names = [
            'Cozy Downtown Apartment',
            'Luxury Beachfront Villa',
            'Mountain Cabin Retreat',
            'Urban Loft Space',
            'Garden View Studio'
        ];

        return [
            'id' => $property_id,
            'name' => $names[array_rand($names)],
            'description' => 'A beautiful property perfect for your stay',
            'type' => 'apartment',
            'bedrooms' => rand(1, 4),
            'bathrooms' => rand(1, 3),
            'max_guests' => rand(2, 8),
            'address' => '123 Mock Street, Test City, TC 12345',
            'latitude' => round(rand(-90000, 90000) / 1000, 6),
            'longitude' => round(rand(-180000, 180000) / 1000, 6),
            'amenities' => ['WiFi', 'Kitchen', 'Parking', 'Air Conditioning'],
            'base_rate' => rand(50, 200),
            'currency' => 'USD',
            'min_stay' => rand(1, 3),
            'max_stay' => 30,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(30, 365) . ' days')),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Simulate API delay
     */
    private function simulateDelay(): void {
        $delay = (int) ($this->config['delay_simulation'] ?? 0);
        if ($delay > 0) {
            sleep($delay);
        }
    }

    /**
     * Check if should simulate an error
     */
    private function shouldSimulateError(): bool {
        $error_rate = (int) ($this->config['error_rate'] ?? 0);
        if ($error_rate <= 0) {
            return false;
        }

        return rand(1, 100) <= $error_rate;
    }

    /**
     * Get mock data for testing
     */
    public function getMockData(): array {
        return $this->mock_data;
    }

    /**
     * Set mock data for testing
     */
    public function setMockData(array $data): void {
        $this->mock_data = $data;
    }

    /**
     * Clear mock data
     */
    public function clearMockData(): void {
        $this->initializeMockData();
    }
}