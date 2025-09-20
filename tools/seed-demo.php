<?php
/**
 * Demo Data Seeder
 * Creates demo properties, rates, reservations, and owners for testing
 */

if (!defined('ABSPATH')) {
    // Allow CLI execution
    if (php_sapi_name() === 'cli') {
        // Mock WordPress environment for CLI
        define('ABSPATH', '/var/www/html/');
    } else {
        exit;
    }
}

class MinPakuDemoSeeder {

    private $created_items = [];
    private $demo_owners = [];
    private $demo_properties = [];

    public function __construct() {
        $this->created_items = [
            'users' => [],
            'properties' => [],
            'reservations' => [],
            'rates' => [],
            'rules' => []
        ];
    }

    /**
     * Seed all demo data
     */
    public function seed_all(): array {
        $results = [];

        try {
            $results['owners'] = $this->seed_demo_owners();
            $results['properties'] = $this->seed_demo_properties();
            $results['rates'] = $this->seed_rate_rules();
            $results['reservations'] = $this->seed_demo_reservations();
            $results['subscriptions'] = $this->seed_owner_subscriptions();

            $results['success'] = true;
            $results['message'] = 'Demo data seeded successfully';
            $results['summary'] = $this->get_seeding_summary();

        } catch (Exception $e) {
            $results['success'] = false;
            $results['message'] = 'Demo seeding failed: ' . $e->getMessage();
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Create demo property owners
     */
    public function seed_demo_owners(): array {
        $owners = [
            [
                'username' => 'karuizawa_owner',
                'email' => 'owner@karuizawa-villa.com',
                'first_name' => 'Hiroshi',
                'last_name' => 'Tanaka',
                'display_name' => 'Hiroshi Tanaka',
                'plan' => 'premium'
            ],
            [
                'username' => 'tokyo_owner',
                'email' => 'owner@tokyo-apartment.com',
                'first_name' => 'Yuki',
                'last_name' => 'Sato',
                'display_name' => 'Yuki Sato',
                'plan' => 'basic'
            ],
            [
                'username' => 'kyoto_owner',
                'email' => 'owner@kyoto-traditional.com',
                'first_name' => 'Kenji',
                'last_name' => 'Yamamoto',
                'display_name' => 'Kenji Yamamoto',
                'plan' => 'premium'
            ]
        ];

        $created_owners = [];

        foreach ($owners as $owner_data) {
            // Check if user already exists
            $existing_user = get_user_by('login', $owner_data['username']);
            if ($existing_user) {
                $user_id = $existing_user->ID;
                $this->log("User {$owner_data['username']} already exists, using existing user");
            } else {
                $user_id = wp_insert_user([
                    'user_login' => $owner_data['username'],
                    'user_email' => $owner_data['email'],
                    'user_pass' => 'demo_password_123',
                    'first_name' => $owner_data['first_name'],
                    'last_name' => $owner_data['last_name'],
                    'display_name' => $owner_data['display_name'],
                    'role' => 'property_owner'
                ]);

                if (is_wp_error($user_id)) {
                    throw new Exception("Failed to create user {$owner_data['username']}: " . $user_id->get_error_message());
                }

                $this->created_items['users'][] = $user_id;
            }

            // Set owner metadata
            update_user_meta($user_id, 'owner_plan', $owner_data['plan']);
            update_user_meta($user_id, 'demo_account', true);

            $this->demo_owners[] = [
                'user_id' => $user_id,
                'username' => $owner_data['username'],
                'plan' => $owner_data['plan']
            ];

            $created_owners[] = [
                'user_id' => $user_id,
                'username' => $owner_data['username'],
                'email' => $owner_data['email'],
                'plan' => $owner_data['plan']
            ];

            $this->log("Created owner: {$owner_data['display_name']} ({$owner_data['username']})");
        }

        return $created_owners;
    }

    /**
     * Create demo properties
     */
    public function seed_demo_properties(): array {
        $properties = [
            [
                'title' => 'Karuizawa Villa',
                'description' => 'Luxurious mountain villa in Karuizawa with stunning nature views. Perfect for families and groups seeking a peaceful retreat.',
                'owner' => 'karuizawa_owner',
                'base_rate' => 250.00,
                'max_guests' => 8,
                'bedrooms' => 4,
                'bathrooms' => 3,
                'address' => '1234 Mountain View Drive, Karuizawa, Nagano 389-0102',
                'amenities' => ['WiFi', 'Kitchen', 'Parking', 'Garden', 'Fireplace', 'Mountain View'],
                'images' => [
                    'https://example.com/karuizawa-villa-1.jpg',
                    'https://example.com/karuizawa-villa-2.jpg'
                ]
            ],
            [
                'title' => 'Tokyo Modern Apartment',
                'description' => 'Stylish apartment in central Tokyo with easy access to major attractions. Modern amenities and traditional Japanese touches.',
                'owner' => 'tokyo_owner',
                'base_rate' => 180.00,
                'max_guests' => 4,
                'bedrooms' => 2,
                'bathrooms' => 1,
                'address' => '5-10-15 Shibuya, Shibuya-ku, Tokyo 150-0002',
                'amenities' => ['WiFi', 'Kitchen', 'Washer', 'AC', 'City View'],
                'images' => [
                    'https://example.com/tokyo-apartment-1.jpg',
                    'https://example.com/tokyo-apartment-2.jpg'
                ]
            ],
            [
                'title' => 'Kyoto Traditional House',
                'description' => 'Authentic traditional Japanese house in historic Kyoto. Experience the beauty of traditional architecture and culture.',
                'owner' => 'kyoto_owner',
                'base_rate' => 200.00,
                'max_guests' => 6,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'address' => '123 Gion District, Higashiyama-ku, Kyoto 605-0001',
                'amenities' => ['WiFi', 'Traditional Bath', 'Garden', 'Tatami Rooms', 'Tea Ceremony Room'],
                'images' => [
                    'https://example.com/kyoto-house-1.jpg',
                    'https://example.com/kyoto-house-2.jpg'
                ]
            ]
        ];

        $created_properties = [];

        foreach ($properties as $property_data) {
            // Find owner
            $owner = array_filter($this->demo_owners, function($o) use ($property_data) {
                return $o['username'] === $property_data['owner'];
            });

            if (empty($owner)) {
                throw new Exception("Owner {$property_data['owner']} not found for property {$property_data['title']}");
            }

            $owner_id = array_values($owner)[0]['user_id'];

            // Create property
            $property_id = wp_insert_post([
                'post_type' => 'property',
                'post_title' => $property_data['title'],
                'post_content' => $property_data['description'],
                'post_status' => 'publish',
                'post_author' => $owner_id
            ]);

            if (is_wp_error($property_id)) {
                throw new Exception("Failed to create property {$property_data['title']}: " . $property_id->get_error_message());
            }

            $this->created_items['properties'][] = $property_id;

            // Set property metadata
            update_post_meta($property_id, 'base_rate', $property_data['base_rate']);
            update_post_meta($property_id, 'max_guests', $property_data['max_guests']);
            update_post_meta($property_id, 'base_guests', 2);
            update_post_meta($property_id, 'bedrooms', $property_data['bedrooms']);
            update_post_meta($property_id, 'bathrooms', $property_data['bathrooms']);
            update_post_meta($property_id, 'address', $property_data['address']);
            update_post_meta($property_id, 'amenities', $property_data['amenities']);
            update_post_meta($property_id, 'images', $property_data['images']);
            update_post_meta($property_id, 'tax_rate', 10.0);
            update_post_meta($property_id, 'tax_type', 'percentage');
            update_post_meta($property_id, 'demo_property', true);

            // Set check-in/check-out times
            update_post_meta($property_id, 'checkin_time', '15:00');
            update_post_meta($property_id, 'checkout_time', '11:00');

            $this->demo_properties[] = [
                'property_id' => $property_id,
                'title' => $property_data['title'],
                'owner_id' => $owner_id,
                'base_rate' => $property_data['base_rate']
            ];

            $created_properties[] = [
                'property_id' => $property_id,
                'title' => $property_data['title'],
                'owner' => $property_data['owner'],
                'base_rate' => $property_data['base_rate']
            ];

            $this->log("Created property: {$property_data['title']} (Owner: {$property_data['owner']})");
        }

        return $created_properties;
    }

    /**
     * Create rate rules and seasonal pricing
     */
    public function seed_rate_rules(): array {
        $created_rules = [];

        foreach ($this->demo_properties as $property) {
            $property_id = $property['property_id'];

            // Weekend premium (25% increase on Friday-Saturday)
            $weekend_rule_id = wp_insert_post([
                'post_type' => 'rate_rule',
                'post_title' => 'Weekend Premium - ' . $property['title'],
                'post_status' => 'active'
            ]);

            update_post_meta($weekend_rule_id, 'property_id', $property_id);
            update_post_meta($weekend_rule_id, 'rule_type', 'day_of_week');
            update_post_meta($weekend_rule_id, 'applicable_days', [5, 6]); // Friday, Saturday
            update_post_meta($weekend_rule_id, 'adjustment_type', 'percentage');
            update_post_meta($weekend_rule_id, 'adjustment_value', 25);
            update_post_meta($weekend_rule_id, 'priority', 20);

            $this->created_items['rates'][] = $weekend_rule_id;

            // Weekly discount (10% off for 7+ nights)
            $weekly_rule_id = wp_insert_post([
                'post_type' => 'rate_rule',
                'post_title' => 'Weekly Discount - ' . $property['title'],
                'post_status' => 'active'
            ]);

            update_post_meta($weekly_rule_id, 'property_id', $property_id);
            update_post_meta($weekly_rule_id, 'rule_type', 'length_of_stay');
            update_post_meta($weekly_rule_id, 'min_nights', 7);
            update_post_meta($weekly_rule_id, 'adjustment_type', 'percentage');
            update_post_meta($weekly_rule_id, 'adjustment_value', -10);
            update_post_meta($weekly_rule_id, 'priority', 30);

            $this->created_items['rates'][] = $weekly_rule_id;

            // Monthly discount (20% off for 28+ nights)
            $monthly_rule_id = wp_insert_post([
                'post_type' => 'rate_rule',
                'post_title' => 'Monthly Discount - ' . $property['title'],
                'post_status' => 'active'
            ]);

            update_post_meta($monthly_rule_id, 'property_id', $property_id);
            update_post_meta($monthly_rule_id, 'rule_type', 'length_of_stay');
            update_post_meta($monthly_rule_id, 'min_nights', 28);
            update_post_meta($monthly_rule_id, 'adjustment_type', 'percentage');
            update_post_meta($monthly_rule_id, 'adjustment_value', -20);
            update_post_meta($monthly_rule_id, 'priority', 25);

            $this->created_items['rates'][] = $monthly_rule_id;

            // Cleaning fee
            $cleaning_fee_id = wp_insert_post([
                'post_type' => 'rate_rule',
                'post_title' => 'Cleaning Fee - ' . $property['title'],
                'post_status' => 'active'
            ]);

            update_post_meta($cleaning_fee_id, 'property_id', $property_id);
            update_post_meta($cleaning_fee_id, 'rule_type', 'fixed_fee');
            update_post_meta($cleaning_fee_id, 'fee_amount', 50.00);
            update_post_meta($cleaning_fee_id, 'fee_type', 'cleaning');

            $this->created_items['rates'][] = $cleaning_fee_id;

            // High season rates (Summer: June-August, +40%)
            $summer_season_id = wp_insert_post([
                'post_type' => 'seasonal_rate',
                'post_title' => 'Summer High Season - ' . $property['title'],
                'post_status' => 'active'
            ]);

            update_post_meta($summer_season_id, 'property_id', $property_id);
            update_post_meta($summer_season_id, 'start_date', '2025-06-01');
            update_post_meta($summer_season_id, 'end_date', '2025-08-31');
            update_post_meta($summer_season_id, 'adjustment_type', 'percentage');
            update_post_meta($summer_season_id, 'adjustment_value', 40);
            update_post_meta($summer_season_id, 'priority', 10);

            $this->created_items['rates'][] = $summer_season_id;

            // New Year peak season (Dec 28 - Jan 3, +60%)
            $newyear_season_id = wp_insert_post([
                'post_type' => 'seasonal_rate',
                'post_title' => 'New Year Peak - ' . $property['title'],
                'post_status' => 'active'
            ]);

            update_post_meta($newyear_season_id, 'property_id', $property_id);
            update_post_meta($newyear_season_id, 'start_date', '2024-12-28');
            update_post_meta($newyear_season_id, 'end_date', '2025-01-03');
            update_post_meta($newyear_season_id, 'adjustment_type', 'percentage');
            update_post_meta($newyear_season_id, 'adjustment_value', 60);
            update_post_meta($newyear_season_id, 'priority', 5);

            $this->created_items['rates'][] = $newyear_season_id;

            $created_rules[] = [
                'property' => $property['title'],
                'rules_created' => 6
            ];

            $this->log("Created rate rules for: {$property['title']}");
        }

        return $created_rules;
    }

    /**
     * Create demo reservations
     */
    public function seed_demo_reservations(): array {
        $reservations_data = [
            // Karuizawa Villa reservations
            [
                'property_title' => 'Karuizawa Villa',
                'guest_name' => 'John Smith',
                'guest_email' => 'john.smith@example.com',
                'checkin' => '2025-02-15',
                'checkout' => '2025-02-20',
                'guests' => 4,
                'status' => 'confirmed',
                'total_amount' => 1250.00,
                'source' => 'direct'
            ],
            [
                'property_title' => 'Karuizawa Villa',
                'guest_name' => 'Emma Wilson',
                'guest_email' => 'emma.wilson@example.com',
                'checkin' => '2025-03-10',
                'checkout' => '2025-03-17',
                'guests' => 6,
                'status' => 'confirmed',
                'total_amount' => 1750.00,
                'source' => 'airbnb'
            ],
            [
                'property_title' => 'Karuizawa Villa',
                'guest_name' => 'David Lee',
                'guest_email' => 'david.lee@example.com',
                'checkin' => '2025-04-05',
                'checkout' => '2025-04-08',
                'guests' => 2,
                'status' => 'pending',
                'total_amount' => 750.00,
                'source' => 'booking.com'
            ],

            // Tokyo Apartment reservations
            [
                'property_title' => 'Tokyo Modern Apartment',
                'guest_name' => 'Sarah Johnson',
                'guest_email' => 'sarah.johnson@example.com',
                'checkin' => '2025-02-20',
                'checkout' => '2025-02-25',
                'guests' => 2,
                'status' => 'confirmed',
                'total_amount' => 900.00,
                'source' => 'direct'
            ],
            [
                'property_title' => 'Tokyo Modern Apartment',
                'guest_name' => 'Michael Brown',
                'guest_email' => 'michael.brown@example.com',
                'checkin' => '2025-03-15',
                'checkout' => '2025-03-18',
                'guests' => 3,
                'status' => 'confirmed',
                'total_amount' => 540.00,
                'source' => 'expedia'
            ],

            // Kyoto Traditional House reservations
            [
                'property_title' => 'Kyoto Traditional House',
                'guest_name' => 'Lisa Chen',
                'guest_email' => 'lisa.chen@example.com',
                'checkin' => '2025-03-01',
                'checkout' => '2025-03-05',
                'guests' => 4,
                'status' => 'confirmed',
                'total_amount' => 800.00,
                'source' => 'vrbo'
            ],
            [
                'property_title' => 'Kyoto Traditional House',
                'guest_name' => 'Robert Garcia',
                'guest_email' => 'robert.garcia@example.com',
                'checkin' => '2025-04-12',
                'checkout' => '2025-04-19',
                'guests' => 5,
                'status' => 'confirmed',
                'total_amount' => 1400.00,
                'source' => 'direct'
            ]
        ];

        $created_reservations = [];

        foreach ($reservations_data as $reservation_data) {
            // Find property
            $property = array_filter($this->demo_properties, function($p) use ($reservation_data) {
                return $p['title'] === $reservation_data['property_title'];
            });

            if (empty($property)) {
                $this->log("Property {$reservation_data['property_title']} not found, skipping reservation");
                continue;
            }

            $property_info = array_values($property)[0];
            $property_id = $property_info['property_id'];

            // Create reservation
            $reservation_id = wp_insert_post([
                'post_type' => 'reservation',
                'post_title' => "Reservation - {$reservation_data['guest_name']} ({$reservation_data['property_title']})",
                'post_status' => $reservation_data['status']
            ]);

            if (is_wp_error($reservation_id)) {
                throw new Exception("Failed to create reservation: " . $reservation_id->get_error_message());
            }

            $this->created_items['reservations'][] = $reservation_id;

            // Set reservation metadata
            update_post_meta($reservation_id, 'property_id', $property_id);
            update_post_meta($reservation_id, 'guest_name', $reservation_data['guest_name']);
            update_post_meta($reservation_id, 'guest_email', $reservation_data['guest_email']);
            update_post_meta($reservation_id, 'checkin_date', $reservation_data['checkin']);
            update_post_meta($reservation_id, 'checkout_date', $reservation_data['checkout']);
            update_post_meta($reservation_id, 'guests', $reservation_data['guests']);
            update_post_meta($reservation_id, 'total_amount', $reservation_data['total_amount']);
            update_post_meta($reservation_id, 'booking_source', $reservation_data['source']);
            update_post_meta($reservation_id, 'created_date', current_time('mysql'));
            update_post_meta($reservation_id, 'demo_reservation', true);

            // Generate iCal UID
            $ical_uid = 'demo-reservation-' . $reservation_id . '@minpaku-suite.local';
            update_post_meta($reservation_id, 'ical_uid', $ical_uid);

            $created_reservations[] = [
                'reservation_id' => $reservation_id,
                'property' => $reservation_data['property_title'],
                'guest' => $reservation_data['guest_name'],
                'checkin' => $reservation_data['checkin'],
                'status' => $reservation_data['status']
            ];

            $this->log("Created reservation: {$reservation_data['guest_name']} at {$reservation_data['property_title']}");
        }

        return $created_reservations;
    }

    /**
     * Set up owner subscriptions
     */
    public function seed_owner_subscriptions(): array {
        if (!class_exists('OwnerSubscription')) {
            $this->log("OwnerSubscription class not available, skipping subscription setup");
            return ['skipped' => 'OwnerSubscription class not available'];
        }

        $subscription_manager = new OwnerSubscription();
        $created_subscriptions = [];

        foreach ($this->demo_owners as $owner) {
            $user_id = $owner['user_id'];
            $plan = $owner['plan'];

            // Set up mock Stripe subscription
            $stripe_customer_id = 'cus_demo_' . $user_id;
            $stripe_subscription_id = 'sub_demo_' . $user_id;

            update_user_meta($user_id, 'stripe_customer_id', $stripe_customer_id);
            update_user_meta($user_id, 'stripe_subscription_id', $stripe_subscription_id);
            update_user_meta($user_id, 'subscription_status', 'active');
            update_user_meta($user_id, 'subscription_plan', 'price_' . $plan);
            update_user_meta($user_id, 'subscription_current_period_end', time() + (30 * 24 * 60 * 60));
            update_user_meta($user_id, 'demo_subscription', true);

            $created_subscriptions[] = [
                'user_id' => $user_id,
                'username' => $owner['username'],
                'plan' => $plan,
                'status' => 'active'
            ];

            $this->log("Set up subscription for {$owner['username']}: {$plan} plan");
        }

        return $created_subscriptions;
    }

    /**
     * Clean up demo data
     */
    public function cleanup_demo_data(): array {
        $cleanup_results = [];

        try {
            // Delete reservations
            foreach ($this->created_items['reservations'] as $reservation_id) {
                wp_delete_post($reservation_id, true);
            }
            $cleanup_results['reservations'] = count($this->created_items['reservations']);

            // Delete rate rules
            foreach (array_merge($this->created_items['rates'], $this->created_items['rules']) as $rule_id) {
                wp_delete_post($rule_id, true);
            }
            $cleanup_results['rates'] = count($this->created_items['rates']) + count($this->created_items['rules']);

            // Delete properties
            foreach ($this->created_items['properties'] as $property_id) {
                wp_delete_post($property_id, true);
            }
            $cleanup_results['properties'] = count($this->created_items['properties']);

            // Delete users
            foreach ($this->created_items['users'] as $user_id) {
                wp_delete_user($user_id);
            }
            $cleanup_results['users'] = count($this->created_items['users']);

            $cleanup_results['success'] = true;
            $cleanup_results['message'] = 'Demo data cleaned up successfully';

        } catch (Exception $e) {
            $cleanup_results['success'] = false;
            $cleanup_results['message'] = 'Cleanup failed: ' . $e->getMessage();
        }

        return $cleanup_results;
    }

    /**
     * Get seeding summary
     */
    private function get_seeding_summary(): array {
        return [
            'owners_created' => count($this->demo_owners),
            'properties_created' => count($this->demo_properties),
            'reservations_created' => count($this->created_items['reservations']),
            'rate_rules_created' => count($this->created_items['rates']),
            'total_items' => array_sum(array_map('count', $this->created_items))
        ];
    }

    /**
     * Log message
     */
    private function log(string $message): void {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::log($message);
        } else {
            error_log("MinPaku Demo Seeder: $message");
        }
    }
}

// Allow direct execution for testing
if (php_sapi_name() === 'cli' && isset($argv) && basename($argv[0]) === 'seed-demo.php') {
    echo "MinPaku Demo Seeder - CLI Mode\n";
    echo "This script should be run via WP-CLI: wp minpaku seed-demo\n";
    exit(1);
}