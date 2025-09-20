<?php
/**
 * PHPUnit bootstrap file for MinPaku Suite tests
 */

// Prevent timeout during testing
if (!defined('WP_MAX_EXECUTION_TIME')) {
    define('WP_MAX_EXECUTION_TIME', 0);
}

// Define test mode
if (!defined('MINPAKU_TEST_MODE')) {
    define('MINPAKU_TEST_MODE', true);
}

// WordPress test environment detection
$wp_tests_dir = getenv('WP_TESTS_DIR');

// If WP_TESTS_DIR is not set, try common locations
if (!$wp_tests_dir) {
    $possible_paths = [
        '/tmp/wordpress-tests-lib',
        '/var/www/wordpress-tests-lib',
        dirname(__FILE__) . '/../../wordpress-tests-lib'
    ];

    foreach ($possible_paths as $path) {
        if (file_exists($path . '/includes/functions.php')) {
            $wp_tests_dir = $path;
            break;
        }
    }
}

if (!$wp_tests_dir || !file_exists($wp_tests_dir . '/includes/functions.php')) {
    // WordPress test suite not available, continue with mocks
    $wp_tests_dir = null;
}

// WordPress test configuration (if available)
if ($wp_tests_dir) {
    require_once $wp_tests_dir . '/includes/functions.php';
}

/**
 * Mock WordPress functions for testing when WP test suite is not available
 */
function mock_wordpress_functions() {
    if (!function_exists('wp_insert_post')) {
        function wp_insert_post($postarr) {
            static $post_id = 1;
            return $post_id++;
        }
    }

    if (!function_exists('wp_insert_user')) {
        function wp_insert_user($userdata) {
            static $user_id = 1;
            return $user_id++;
        }
    }

    if (!function_exists('update_post_meta')) {
        function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') {
            return true;
        }
    }

    if (!function_exists('update_user_meta')) {
        function update_user_meta($user_id, $meta_key, $meta_value, $prev_value = '') {
            return true;
        }
    }

    if (!function_exists('get_post_meta')) {
        function get_post_meta($post_id, $key = '', $single = false) {
            $mock_data = [
                'base_rate' => 100.00,
                'max_guests' => 4,
                'property_id' => 1
            ];

            if ($key && isset($mock_data[$key])) {
                return $single ? $mock_data[$key] : [$mock_data[$key]];
            }

            return $single ? '' : [];
        }
    }

    if (!function_exists('get_user_meta')) {
        function get_user_meta($user_id, $key = '', $single = false) {
            return $single ? '' : [];
        }
    }

    if (!function_exists('get_posts')) {
        function get_posts($args = []) {
            return [];
        }
    }

    if (!function_exists('wp_delete_post')) {
        function wp_delete_post($postid = 0, $force_delete = false) {
            return true;
        }
    }

    if (!function_exists('wp_delete_user')) {
        function wp_delete_user($id, $reassign = null) {
            return true;
        }
    }

    if (!function_exists('current_time')) {
        function current_time($type, $gmt = 0) {
            return $type === 'mysql' ? date('Y-m-d H:i:s') : time();
        }
    }

    if (!function_exists('home_url')) {
        function home_url($path = '', $scheme = null) {
            return 'http://localhost' . ($path ? '/' . ltrim($path, '/') : '');
        }
    }

    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing) {
            return is_object($thing) && is_a($thing, 'WP_Error');
        }
    }

    if (!class_exists('WP_Error')) {
        class WP_Error {
            public $errors = [];
            public function __construct($code = '', $message = '', $data = '') {
                if (!empty($code)) {
                    $this->errors[$code][] = $message;
                }
            }
            public function get_error_message() {
                return 'Test error';
            }
        }
    }

    // Additional WordPress functions for plugin loading
    if (!function_exists('add_action')) {
        function add_action($hook, $function_to_add, $priority = 10, $accepted_args = 1) {
            return true;
        }
    }

    if (!function_exists('add_filter')) {
        function add_filter($hook, $function_to_add, $priority = 10, $accepted_args = 1) {
            return true;
        }
    }

    if (!function_exists('register_post_type')) {
        function register_post_type($post_type, $args = []) {
            return true;
        }
    }

    if (!function_exists('register_taxonomy')) {
        function register_taxonomy($taxonomy, $object_type, $args = []) {
            return true;
        }
    }

    if (!function_exists('flush_rewrite_rules')) {
        function flush_rewrite_rules($hard = true) {
            return true;
        }
    }

    if (!function_exists('__')) {
        function __($text, $domain = 'default') {
            return $text;
        }
    }

    if (!function_exists('_e')) {
        function _e($text, $domain = 'default') {
            echo $text;
        }
    }

    if (!function_exists('esc_html')) {
        function esc_html($text) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('esc_attr')) {
        function esc_attr($text) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('did_action')) {
        function did_action($hook) {
            return 1;
        }
    }
}

/**
 * Load plugin for testing
 */
function load_minpaku_suite_plugin() {
    // Mock WordPress functions if not available
    if (!function_exists('wp_insert_post')) {
        mock_wordpress_functions();
    }

    // Load plugin files
    $plugin_dir = dirname(dirname(__FILE__));

    // Define WordPress constants if not defined
    if (!defined('ABSPATH')) {
        define('ABSPATH', $plugin_dir . '/');
    }

    // Load required plugin classes
    require_once $plugin_dir . '/includes/class-mcs-ics.php';
    require_once $plugin_dir . '/includes/class-mcs-sync.php';
    require_once $plugin_dir . '/includes/cpt-property.php';

    // Mock missing interfaces first
    if (!interface_exists('ResolverInterface')) {
        interface ResolverInterface {
            public function resolveRate(array $booking_data, array $context = []): array;
        }
    }

    if (!interface_exists('RuleInterface')) {
        interface RuleInterface {
            public function validate(array $booking_data): array;
        }
    }

    // Try to load interfaces first
    $interface_files = [
        '/includes/Services/Rates/ResolverInterface.php',
        '/includes/Services/Rules/RuleInterface.php'
    ];

    foreach ($interface_files as $file) {
        $file_path = $plugin_dir . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }

    // Load all component files if they exist
    $component_files = [
        '/includes/Sync/IcalImporter.php',
        '/includes/Sync/IcalExporter.php',
        '/includes/Services/Rules/RuleEngine.php',
        '/includes/Services/Rates/RateResolver.php',
        '/includes/Portal/OwnerSubscription.php',
        '/includes/UI/AvailabilityCalendar.php',
        '/includes/UI/QuoteCalculator.php'
    ];

    foreach ($component_files as $file) {
        $file_path = $plugin_dir . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}

// Hook to load our plugin
if ($wp_tests_dir && function_exists('tests_add_filter')) {
    tests_add_filter('muplugins_loaded', 'load_minpaku_suite_plugin');
} else {
    // Mock WordPress functions first if not available
    if (!function_exists('add_action')) {
        mock_wordpress_functions();
    }

    // If WordPress test suite is not available, load directly
    load_minpaku_suite_plugin();
}

// Start up the WP testing environment if available
if ($wp_tests_dir && file_exists($wp_tests_dir . '/includes/bootstrap.php')) {
    require $wp_tests_dir . '/includes/bootstrap.php';
} else {
    // Mock basic WordPress environment for testing
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__FILE__) . '/');
    }

    // Define other WordPress constants
    if (!defined('WP_CONTENT_DIR')) {
        define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
    }

    if (!defined('WP_PLUGIN_DIR')) {
        define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
    }

    // Mock WordPress actions/filters
    if (!function_exists('add_action')) {
        function add_action($hook, $function_to_add, $priority = 10, $accepted_args = 1) {
            return true;
        }
    }

    if (!function_exists('add_filter')) {
        function add_filter($hook, $function_to_add, $priority = 10, $accepted_args = 1) {
            return true;
        }
    }

    if (!function_exists('did_action')) {
        function did_action($hook) {
            return 1;
        }
    }

    if (!function_exists('register_post_type')) {
        function register_post_type($post_type, $args = []) {
            return true;
        }
    }

    if (!function_exists('register_taxonomy')) {
        function register_taxonomy($taxonomy, $object_type, $args = []) {
            return true;
        }
    }

    if (!function_exists('flush_rewrite_rules')) {
        function flush_rewrite_rules($hard = true) {
            return true;
        }
    }

    if (!function_exists('__')) {
        function __($text, $domain = 'default') {
            return $text;
        }
    }

    if (!function_exists('_e')) {
        function _e($text, $domain = 'default') {
            echo $text;
        }
    }

    if (!function_exists('esc_html')) {
        function esc_html($text) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('esc_attr')) {
        function esc_attr($text) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    // Load our plugin
    load_minpaku_suite_plugin();
}