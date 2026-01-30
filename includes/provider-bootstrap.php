<?php
/**
 * Provider System Bootstrap
 * Initializes the complete provider system for channels and payments
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once __DIR__ . '/Providers/Channel/AbstractChannelProvider.php';
require_once __DIR__ . '/Providers/Channel/DummyProvider.php';
require_once __DIR__ . '/Providers/Payment/AbstractPaymentProvider.php';
require_once __DIR__ . '/Providers/Payment/StripePaymentProvider.php';
require_once __DIR__ . '/Services/ProviderContainer.php';
require_once __DIR__ . '/Services/ProviderAdmin.php';

// Initialize the provider system
add_action('plugins_loaded', function() {
    // Initialize the provider container and admin interface
    $container = ProviderContainer::getInstance();
    $admin = new ProviderAdmin();

    // Add hook for testing DummyChannelProvider functionality
    add_action('wp_ajax_mcs_test_dummy_provider', function() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_ajax_referer('mcs_test_dummy_nonce', 'nonce');

        $container = ProviderContainer::getInstance();
        $dummy_provider = $container->getChannelProvider('dummy');

        if (!$dummy_provider) {
            wp_send_json_error(['message' => 'Dummy provider not found']);
        }

        // Configure with test data
        $dummy_provider->setConfig([
            'api_key' => 'test_api_key_123',
            'base_url' => 'https://api.dummy-channel.com',
            'delay_simulation' => 0,
            'error_rate' => 0
        ]);

        $results = [];

        // Test connection
        $results['connection'] = $dummy_provider->testConnection();

        // Test fetching availability
        $results['availability'] = $dummy_provider->fetchAvailability('prop_1', '2024-01-01', '2024-01-07');

        // Test fetching reservations
        $results['reservations'] = $dummy_provider->fetchReservations('prop_1');

        // Test creating a reservation
        $reservation_data = [
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'check_in' => '2024-02-01',
            'check_out' => '2024-02-03',
            'guests' => 2
        ];
        $results['create_reservation'] = $dummy_provider->pushReservation('prop_1', $reservation_data);

        // Test property listing
        $results['properties'] = $dummy_provider->listProperties();

        wp_send_json_success([
            'message' => 'DummyChannelProvider tests completed',
            'results' => $results
        ]);
    });

    // Add test page for DummyChannelProvider
    add_action('admin_menu', function() {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_submenu_page(
            'mcs-providers',
            __('Test Dummy Provider', 'minpaku-suite'),
            __('Test Dummy', 'minpaku-suite'),
            'manage_options',
            'mcs-test-dummy',
            function() {
                echo '<div class="wrap">';
                echo '<h1>' . __('Test Dummy Channel Provider', 'minpaku-suite') . '</h1>';
                echo '<p>' . __('This page allows you to test the DummyChannelProvider functionality.', 'minpaku-suite') . '</p>';

                echo '<div id="test-results" style="margin: 20px 0;"></div>';

                echo '<button id="run-dummy-tests" class="button button-primary">' . __('Run Tests', 'minpaku-suite') . '</button>';
                echo '<div id="test-output" style="margin-top: 20px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; display: none;"></div>';

                // JavaScript for running tests
                echo '<script>
                document.getElementById("run-dummy-tests").addEventListener("click", function() {
                    const button = this;
                    const output = document.getElementById("test-output");
                    const results = document.getElementById("test-results");

                    button.disabled = true;
                    button.textContent = "Running tests...";
                    output.style.display = "block";
                    output.innerHTML = "<p>Running DummyChannelProvider tests...</p>";

                    fetch(ajaxurl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: new URLSearchParams({
                            action: "mcs_test_dummy_provider",
                            nonce: "' . wp_create_nonce('mcs_test_dummy_nonce') . '"
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        button.disabled = false;
                        button.textContent = "Run Tests";

                        if (data.success) {
                            output.innerHTML = "<h3>Test Results:</h3><pre>" + JSON.stringify(data.data.results, null, 2) + "</pre>";
                            results.innerHTML = "<div class=\"notice notice-success\"><p>All tests completed successfully!</p></div>";
                        } else {
                            output.innerHTML = "<h3>Error:</h3><p>" + data.data.message + "</p>";
                            results.innerHTML = "<div class=\"notice notice-error\"><p>Tests failed!</p></div>";
                        }
                    })
                    .catch(error => {
                        button.disabled = false;
                        button.textContent = "Run Tests";
                        output.innerHTML = "<h3>Network Error:</h3><p>" + error.message + "</p>";
                        results.innerHTML = "<div class=\"notice notice-error\"><p>Network error occurred!</p></div>";
                    });
                });
                </script>';

                echo '</div>';
            }
        );
    });

    // Log system initialization
    if (class_exists('MCS_Logger')) {
        MCS_Logger::log('INFO', 'Provider system initialized', [
            'channel_providers' => count($container->getChannelProviders()),
            'payment_providers' => count($container->getPaymentProviders())
        ]);
    }
});

// Activation hook
register_activation_hook(__FILE__, function() {
    // Initialize provider configurations
    $container = ProviderContainer::getInstance();

    // Set Stripe as default payment provider if available
    if ($container->getPaymentProvider('stripe')) {
        $container->setDefaultPaymentProvider('stripe');
    }

    // Set dummy as default channel provider for testing
    if ($container->getChannelProvider('dummy')) {
        $container->setDefaultChannelProvider('dummy');
    }
});

// Add CSS for admin styling
add_action('admin_head', function() {
    echo '<style>
    .mcs-provider-stats {
        margin: 20px 0;
    }

    .stat-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 20px;
        text-align: center;
    }

    .stat-card h3 {
        margin-top: 0;
        color: #1d2327;
    }

    .stat-number {
        font-size: 2.5em;
        font-weight: bold;
        color: #2271b1;
        margin: 10px 0;
    }

    .stat-details {
        color: #646970;
        font-size: 0.9em;
    }

    .mcs-providers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .provider-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 20px;
    }

    .provider-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f0f0f1;
    }

    .provider-header h3 {
        margin: 0;
        color: #1d2327;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 0.8em;
        font-weight: bold;
        text-transform: uppercase;
    }

    .status-badge.connected {
        background: #d4edda;
        color: #155724;
    }

    .status-badge.configured {
        background: #fff3cd;
        color: #856404;
    }

    .status-badge.not-configured {
        background: #f8d7da;
        color: #721c24;
    }

    .provider-description {
        margin-bottom: 20px;
        color: #646970;
    }

    .config-field {
        margin-bottom: 15px;
    }

    .config-field label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #1d2327;
    }

    .config-field .required {
        color: #d63384;
    }

    .config-field input[type="text"],
    .config-field input[type="password"],
    .config-field input[type="url"],
    .config-field input[type="number"],
    .config-field select,
    .config-field textarea {
        width: 100%;
        max-width: 400px;
    }

    .config-field .description {
        margin-top: 5px;
        color: #646970;
        font-style: italic;
        font-size: 0.9em;
    }

    .provider-actions {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #f0f0f1;
    }

    .provider-actions .button {
        margin-right: 10px;
    }

    .provider-webhook {
        margin-top: 15px;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 4px;
    }

    .provider-webhook h4 {
        margin-top: 0;
        margin-bottom: 10px;
    }

    .provider-webhook code {
        display: inline-block;
        padding: 5px 10px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 3px;
        font-size: 0.9em;
        word-break: break-all;
        margin-right: 10px;
    }

    .provider-error {
        margin-top: 15px;
        padding: 10px;
        background: #f8d7da;
        color: #721c24;
        border-radius: 4px;
        border-left: 4px solid #dc3545;
    }

    .mcs-default-providers,
    .mcs-quick-actions {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 20px;
        margin: 20px 0;
    }

    .mcs-default-providers h2,
    .mcs-quick-actions h2 {
        margin-top: 0;
    }
    </style>';
});