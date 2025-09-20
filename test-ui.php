<?php
/**
 * UI Components Test Script
 * Quick test to verify shortcodes and components work
 */

// WordPress environment simulation for testing
if (!defined('ABSPATH')) {
    // Mock WordPress functions for testing
    function wp_enqueue_script() {}
    function wp_enqueue_style() {}
    function wp_localize_script() {}
    function admin_url($path) { return 'http://localhost/wp-admin/' . $path; }
    function wp_create_nonce($action) { return 'test_nonce_' . $action; }
    function __($text, $domain = '') { return $text; }
    function _e($text, $domain = '') { echo $text; }
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES); }
    function esc_html($text) { return htmlspecialchars($text, ENT_NOQUOTES); }
    function plugin_dir_url($file) { return 'http://localhost/wp-content/plugins/minpaku-suite/'; }
    function plugin_dir_path($file) { return '/var/www/html/wp-content/plugins/minpaku-suite/'; }
    if (!function_exists('filemtime')) {
        function filemtime($file) { return time(); }
    }
    if (!function_exists('file_exists')) {
        function file_exists($file) { return true; }
    }
    function wp_verify_nonce($nonce, $action) { return true; }
    function wp_send_json_success($data) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    function wp_send_json_error($data) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'data' => $data]);
        exit;
    }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) { return trim(strip_tags($str)); }
    }
    if (!function_exists('intval')) {
        function intval($val) { return (int) $val; }
    }
    function wp_die($message) { die($message); }
    function current_user_can($capability) { return true; }
    function check_ajax_referer($action, $query_arg) { return true; }
    function add_shortcode($tag, $callback) { return true; }
    function add_action($hook, $callback, $priority = 10, $args = 1) { return true; }
    function get_post($id) { return null; }
    function get_post_meta($id, $key, $single = false) { return $single ? '' : []; }
    function shortcode_atts($defaults, $atts) { return array_merge($defaults, $atts ?? []); }

    define('ABSPATH', '/var/www/html/');
}

// Include the components
require_once __DIR__ . '/includes/UI/AvailabilityCalendar.php';
require_once __DIR__ . '/includes/UI/QuoteCalculator.php';

echo "<h1>MinPaku Suite UI Components Test</h1>";

// Test AvailabilityCalendar instantiation
echo "<h2>Testing AvailabilityCalendar</h2>";
try {
    $calendar = new AvailabilityCalendar();
    echo "✅ AvailabilityCalendar class instantiated successfully<br>";

    // Test shortcode registration
    if (method_exists($calendar, 'render_calendar_shortcode')) {
        echo "✅ render_calendar_shortcode method exists<br>";

        // Test shortcode output
        $output = $calendar->render_calendar_shortcode(['property_id' => '1', 'check_in_out' => 'true']);
        if (!empty($output) && strpos($output, 'minpaku-calendar-wrapper') !== false) {
            echo "✅ Calendar shortcode renders HTML correctly<br>";
        } else {
            echo "❌ Calendar shortcode output is invalid. Got: " . substr($output, 0, 200) . "...<br>";
        }
    } else {
        echo "❌ render_calendar_shortcode method missing<br>";
    }

    // Note: get_property_availability is private, skip testing

} catch (Exception $e) {
    echo "❌ AvailabilityCalendar error: " . $e->getMessage() . "<br>";
}

echo "<h2>Testing QuoteCalculator</h2>";
try {
    $calculator = new QuoteCalculator();
    echo "✅ QuoteCalculator class instantiated successfully<br>";

    // Test shortcode registration
    if (method_exists($calculator, 'render_quote_shortcode')) {
        echo "✅ render_quote_shortcode method exists<br>";

        // Test shortcode output
        $output = $calculator->render_quote_shortcode([
            'property_id' => '1',
            'checkin' => '2025-01-01',
            'checkout' => '2025-01-05',
            'guests' => '2'
        ]);
        if (!empty($output) && strpos($output, 'minpaku-quote-wrapper') !== false) {
            echo "✅ Quote shortcode renders HTML correctly<br>";
        } else {
            echo "❌ Quote shortcode output is invalid<br>";
        }
    } else {
        echo "❌ render_quote_shortcode method missing<br>";
    }

    // Test quote calculation method
    if (method_exists($calculator, 'calculate_quote')) {
        echo "✅ calculate_quote method exists<br>";

        $quote = $calculator->calculate_quote(1, '2025-01-01', '2025-01-05', 2);
        if (is_array($quote) && isset($quote['success'])) {
            if ($quote['success']) {
                echo "✅ calculate_quote returns successful result<br>";
                if (isset($quote['total']) && is_numeric($quote['total'])) {
                    echo "✅ Quote total is numeric: " . $quote['total'] . "<br>";
                } else {
                    echo "❌ Quote total is not numeric<br>";
                }
                if (isset($quote['breakdown']) && is_array($quote['breakdown'])) {
                    echo "✅ Quote breakdown is array<br>";
                } else {
                    echo "❌ Quote breakdown is not array<br>";
                }
            } else {
                echo "⚠️ calculate_quote returns error: " . ($quote['message'] ?? 'Unknown error') . "<br>";
            }
        } else {
            echo "❌ calculate_quote does not return proper array<br>";
        }
    } else {
        echo "❌ calculate_quote method missing<br>";
    }

} catch (Exception $e) {
    echo "❌ QuoteCalculator error: " . $e->getMessage() . "<br>";
}

echo "<h2>Testing File Structure</h2>";

$files_to_check = [
    'assets/js/calendar.js' => 'Calendar JavaScript',
    'templates/calendar.php' => 'Calendar template',
    'templates/quote.php' => 'Quote template',
    'includes/ui-bootstrap.php' => 'UI bootstrap',
    'includes/bootstrap.php' => 'Main bootstrap'
];

foreach ($files_to_check as $file => $description) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path) && filesize($full_path) > 0) {
        echo "✅ $description exists and has content<br>";
    } else {
        echo "❌ $description missing or empty<br>";
    }
}

echo "<h2>Sample Shortcode Output</h2>";

echo "<h3>Calendar Shortcode</h3>";
echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
try {
    $calendar = new AvailabilityCalendar();
    echo $calendar->render_calendar_shortcode(['property_id' => '1', 'check_in_out' => 'true', 'months' => '2']);
} catch (Exception $e) {
    echo "Error rendering calendar: " . $e->getMessage();
}
echo "</div>";

echo "<h3>Quote Calculator Shortcode</h3>";
echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
try {
    $calculator = new QuoteCalculator();
    echo $calculator->render_quote_shortcode([
        'property_id' => '1',
        'checkin' => date('Y-m-d', strtotime('+1 day')),
        'checkout' => date('Y-m-d', strtotime('+4 days')),
        'guests' => '2'
    ]);
} catch (Exception $e) {
    echo "Error rendering quote: " . $e->getMessage();
}
echo "</div>";

echo "<h2>Test Complete</h2>";
echo "<p>If you see checkmarks (✅) for most items, the UI components are working correctly.</p>";
?>