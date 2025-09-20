<?php
/**
 * Simple Test Runner for Webhook System
 * Validates webhook implementation without PHPUnit
 *
 * @package MinpakuSuite
 */

// Mock WordPress environment globally
if (!class_exists('MCS_Logger')) {
    class MCS_Logger {
        public static function log($level, $message, $data = []) {
            echo "[{$level}] {$message}\n";
            if (!empty($data)) {
                echo "  Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            }
        }
    }
}

// Mock WordPress functions
if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? date('Y-m-d H:i:s') :
               ($type === 'c' ? date('c') : time());
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

echo "=== MinPaku Suite Webhook System Test Runner ===\n\n";

$tests_passed = 0;
$tests_failed = 0;

function test_assert($condition, $message) {
    global $tests_passed, $tests_failed;

    if ($condition) {
        echo "✓ PASS: {$message}\n";
        $tests_passed++;
    } else {
        echo "✗ FAIL: {$message}\n";
        $tests_failed++;
    }
}

// Test 1: WebhookSigner Basic Functionality
echo "--- Testing WebhookSigner ---\n";

try {
    require_once __DIR__ . '/../includes/Webhook/WebhookSigner.php';

    $signer = new WebhookSigner();
    $body = '{"test":"payload"}';
    $secret = 'test_secret_123';
    $timestamp = time();

    // Test signature generation
    $signature = $signer->sign($body, $secret, $timestamp);
    test_assert(
        strpos($signature, 'sha256=') === 0,
        'Signature has correct sha256= prefix'
    );

    // Test signature verification
    $is_valid = $signer->verify($body, $signature, $secret, $timestamp);
    test_assert($is_valid, 'Signature verification succeeds with correct data');

    // Test signature verification with wrong secret
    $is_invalid = $signer->verify($body, $signature, 'wrong_secret', $timestamp);
    test_assert(!$is_invalid, 'Signature verification fails with wrong secret');

    // Test header generation
    $headers = $signer->generateHeaders($body, $secret, $timestamp, 'test-key', 'test.event');
    test_assert(
        isset($headers['X-Minpaku-Signature']) &&
        isset($headers['X-Minpaku-Timestamp']) &&
        isset($headers['Content-Type']),
        'Headers contain all required fields'
    );

    echo "WebhookSigner tests completed.\n\n";

} catch (Exception $e) {
    echo "✗ FAIL: WebhookSigner test failed with exception: " . $e->getMessage() . "\n\n";
    $tests_failed++;
}

// Test 2: WebhookQueue Basic Functionality
echo "--- Testing WebhookQueue ---\n";

try {
    require_once __DIR__ . '/../includes/Webhook/WebhookQueue.php';

    // Mock database
    global $wpdb;
    $wpdb = new stdClass();
    $wpdb->prefix = 'wp_';
    $wpdb->prepare = function($query, ...$args) {
        return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
    };
    $wpdb->insert = function($table, $data) {
        return 1;
    };
    $wpdb->get_row = function($query) {
        return (object) [
            'delivery_key' => 'test-key-123',
            'event' => 'booking.confirmed',
            'status' => 'queued',
            'attempt' => 1,
            'can_retry' => true
        ];
    };

    $queue = new WebhookQueue();

    // Test retry intervals
    $intervals = WebhookQueue::getRetryIntervals();
    test_assert(
        $intervals === [10, 60, 300, 1800, 7200],
        'Retry intervals match expected exponential backoff'
    );

    // Test max attempts
    test_assert(
        WebhookQueue::getMaxAttempts() === 5,
        'Max attempts is set to 5'
    );

    // Test valid statuses
    $statuses = WebhookQueue::getValidStatuses();
    test_assert(
        in_array('queued', $statuses) &&
        in_array('success', $statuses) &&
        in_array('failed', $statuses),
        'Valid statuses include queued, success, and failed'
    );

    echo "WebhookQueue tests completed.\n\n";

} catch (Exception $e) {
    echo "✗ FAIL: WebhookQueue test failed with exception: " . $e->getMessage() . "\n\n";
    $tests_failed++;
}

// Test 3: WebhookDispatcher Basic Functionality
echo "--- Testing WebhookDispatcher ---\n";

try {
    require_once __DIR__ . '/../includes/Webhook/WebhookDispatcher.php';

    // Test supported events
    $events = WebhookDispatcher::getSupportedEvents();
    $expected_events = [
        'booking.confirmed',
        'booking.cancelled',
        'booking.completed',
        'payment.authorized',
        'payment.captured',
        'payment.refunded'
    ];

    test_assert(
        count(array_intersect($events, $expected_events)) === count($expected_events),
        'All expected webhook events are supported'
    );

    // Test event labels
    $labels = WebhookDispatcher::getEventLabels();
    test_assert(
        isset($labels['booking.confirmed']) &&
        isset($labels['payment.captured']),
        'Event labels are properly defined'
    );

    echo "WebhookDispatcher tests completed.\n\n";

} catch (Exception $e) {
    echo "✗ FAIL: WebhookDispatcher test failed with exception: " . $e->getMessage() . "\n\n";
    $tests_failed++;
}

// Test 4: File Structure Validation
echo "--- Testing File Structure ---\n";

$required_files = [
    'includes/Webhook/WebhookSigner.php',
    'includes/Webhook/WebhookQueue.php',
    'includes/Webhook/WebhookDispatcher.php',
    'includes/Webhook/WebhookWorker.php',
    'includes/Webhook/WebhookApiController.php',
    'includes/Webhook/WebhookAdmin.php',
    'includes/Webhook/Hooks.php',
    'includes/Webhook/WebhookCLI.php',
    'includes/webhook-bootstrap.php'
];

foreach ($required_files as $file) {
    $file_path = __DIR__ . '/../' . $file;
    test_assert(
        file_exists($file_path),
        "Required file exists: {$file}"
    );
}

echo "File structure tests completed.\n\n";

// Test 5: Bootstrap Integration
echo "--- Testing Bootstrap Integration ---\n";

try {
    $bootstrap_content = file_get_contents(__DIR__ . '/../includes/bootstrap.php');
    test_assert(
        strpos($bootstrap_content, 'webhook-bootstrap.php') !== false,
        'Main bootstrap includes webhook-bootstrap.php'
    );

    $webhook_bootstrap_content = file_get_contents(__DIR__ . '/../includes/webhook-bootstrap.php');
    test_assert(
        strpos($webhook_bootstrap_content, 'WebhookWorker') !== false,
        'Webhook bootstrap initializes WebhookWorker'
    );

    echo "Bootstrap integration tests completed.\n\n";

} catch (Exception $e) {
    echo "✗ FAIL: Bootstrap test failed with exception: " . $e->getMessage() . "\n\n";
    $tests_failed++;
}

// Test Summary
echo "=== Test Summary ===\n";
echo "Tests Passed: {$tests_passed}\n";
echo "Tests Failed: {$tests_failed}\n";
echo "Total Tests: " . ($tests_passed + $tests_failed) . "\n";

if ($tests_failed === 0) {
    echo "🎉 All tests passed! Webhook system implementation appears to be working correctly.\n";
    exit(0);
} else {
    echo "❌ Some tests failed. Please review the implementation.\n";
    exit(1);
}