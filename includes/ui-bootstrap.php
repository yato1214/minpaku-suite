<?php
/**
 * UI Bootstrap
 * Initializes UI components, AJAX handlers, and script loading
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include UI components
require_once __DIR__ . '/UI/AvailabilityCalendar.php';
require_once __DIR__ . '/UI/QuoteCalculator.php';

/**
 * Initialize UI system
 */
add_action('init', function() {
    // Initialize UI components
    new AvailabilityCalendar();
    new QuoteCalculator();

    // Register AJAX handlers
    add_action('wp_ajax_minpaku_get_availability', 'handle_get_availability_ajax');
    add_action('wp_ajax_nopriv_minpaku_get_availability', 'handle_get_availability_ajax');

    add_action('wp_ajax_minpaku_calculate_quote', 'handle_calculate_quote_ajax');
    add_action('wp_ajax_nopriv_minpaku_calculate_quote', 'handle_calculate_quote_ajax');

    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', 'enqueue_ui_assets');
});

/**
 * Handle availability AJAX request
 */
function handle_get_availability_ajax() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'minpaku_ui_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    $property_id = intval($_POST['property_id'] ?? 0);
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date = sanitize_text_field($_POST['end_date'] ?? '');

    if (!$property_id || !$start_date || !$end_date) {
        wp_send_json_error(['message' => 'Missing required parameters']);
        return;
    }

    try {
        $calendar = new AvailabilityCalendar();
        $availability = $calendar->get_property_availability($property_id, $start_date, $end_date);

        wp_send_json_success([
            'availability' => $availability,
            'property_id' => $property_id,
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
    } catch (Exception $e) {
        error_log('MinPaku availability error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Failed to fetch availability']);
    }
}

/**
 * Handle quote calculation AJAX request
 */
function handle_calculate_quote_ajax() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'minpaku_ui_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    $property_id = intval($_POST['property_id'] ?? 0);
    $checkin = sanitize_text_field($_POST['checkin'] ?? '');
    $checkout = sanitize_text_field($_POST['checkout'] ?? '');
    $guests = intval($_POST['guests'] ?? 1);

    if (!$property_id || !$checkin || !$checkout) {
        wp_send_json_error(['message' => 'Missing required parameters']);
        return;
    }

    // Validate dates
    $checkin_date = DateTime::createFromFormat('Y-m-d', $checkin);
    $checkout_date = DateTime::createFromFormat('Y-m-d', $checkout);

    if (!$checkin_date || !$checkout_date) {
        wp_send_json_error(['message' => 'Invalid date format']);
        return;
    }

    if ($checkin_date >= $checkout_date) {
        wp_send_json_error(['message' => 'Check-out must be after check-in']);
        return;
    }

    if ($checkin_date < new DateTime()) {
        wp_send_json_error(['message' => 'Check-in date cannot be in the past']);
        return;
    }

    try {
        $calculator = new QuoteCalculator();
        $quote = $calculator->calculate_quote($property_id, $checkin, $checkout, $guests);

        if ($quote['success']) {
            wp_send_json_success($quote);
        } else {
            wp_send_json_error(['message' => $quote['message'] ?? 'Quote calculation failed']);
        }
    } catch (Exception $e) {
        error_log('MinPaku quote calculation error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Failed to calculate quote']);
    }
}

/**
 * Enqueue UI assets
 */
function enqueue_ui_assets() {
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    $plugin_path = plugin_dir_path(dirname(__FILE__));

    // Enqueue calendar JavaScript
    $calendar_js_path = $plugin_path . 'assets/js/calendar.js';
    if (file_exists($calendar_js_path)) {
        wp_enqueue_script(
            'minpaku-calendar',
            $plugin_url . 'assets/js/calendar.js',
            ['jquery'],
            filemtime($calendar_js_path),
            true
        );
    }

    // Enqueue calendar CSS if it exists
    $calendar_css_path = $plugin_path . 'assets/css/calendar.css';
    if (file_exists($calendar_css_path)) {
        wp_enqueue_style(
            'minpaku-calendar',
            $plugin_url . 'assets/css/calendar.css',
            [],
            filemtime($calendar_css_path)
        );
    }

    // Enqueue quote CSS if it exists
    $quote_css_path = $plugin_path . 'assets/css/quote.css';
    if (file_exists($quote_css_path)) {
        wp_enqueue_style(
            'minpaku-quote',
            $plugin_url . 'assets/css/quote.css',
            [],
            filemtime($quote_css_path)
        );
    }

    // Localize script for AJAX
    wp_localize_script('minpaku-calendar', 'minpaku_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('minpaku_ui_nonce'),
        'strings' => [
            'loading' => __('Loading...', 'minpaku-suite'),
            'error' => __('An error occurred', 'minpaku-suite'),
            'no_availability' => __('No availability data found', 'minpaku-suite'),
            'invalid_dates' => __('Please select valid dates', 'minpaku-suite'),
            'calculation_failed' => __('Quote calculation failed', 'minpaku-suite')
        ]
    ]);
}

/**
 * Add admin AJAX handlers for testing
 */
add_action('wp_ajax_minpaku_test_ui_calendar', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_ajax_referer('minpaku_test_ui_nonce', 'nonce');

    $property_id = intval($_POST['property_id'] ?? 1);
    $start_date = sanitize_text_field($_POST['start_date'] ?? date('Y-m-d'));
    $end_date = sanitize_text_field($_POST['end_date'] ?? date('Y-m-d', strtotime('+1 month')));

    try {
        $calendar = new AvailabilityCalendar();
        $availability = $calendar->get_property_availability($property_id, $start_date, $end_date);

        wp_send_json_success([
            'message' => 'Calendar test completed',
            'availability' => $availability,
            'property_id' => $property_id,
            'date_range' => $start_date . ' to ' . $end_date,
            'total_days' => count($availability)
        ]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Calendar test failed: ' . $e->getMessage()]);
    }
});

add_action('wp_ajax_minpaku_test_ui_quote', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_ajax_referer('minpaku_test_ui_nonce', 'nonce');

    $property_id = intval($_POST['property_id'] ?? 1);
    $checkin = sanitize_text_field($_POST['checkin'] ?? date('Y-m-d', strtotime('+1 day')));
    $checkout = sanitize_text_field($_POST['checkout'] ?? date('Y-m-d', strtotime('+4 days')));
    $guests = intval($_POST['guests'] ?? 2);

    try {
        $calculator = new QuoteCalculator();
        $quote = $calculator->calculate_quote($property_id, $checkin, $checkout, $guests);

        wp_send_json_success([
            'message' => 'Quote test completed',
            'quote' => $quote,
            'input_params' => [
                'property_id' => $property_id,
                'checkin' => $checkin,
                'checkout' => $checkout,
                'guests' => $guests
            ]
        ]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Quote test failed: ' . $e->getMessage()]);
    }
});

/**
 * Add admin menu for UI testing
 */
add_action('admin_menu', function() {
    if (!current_user_can('manage_options')) {
        return;
    }

    add_submenu_page(
        'mcs-providers',
        __('Test UI Components', 'minpaku-suite'),
        __('Test UI', 'minpaku-suite'),
        'manage_options',
        'mcs-test-ui',
        function() {
            ?>
            <div class="wrap">
                <h1><?php _e('Test UI Components', 'minpaku-suite'); ?></h1>
                <p><?php _e('This page allows you to test the calendar and quote calculator components.', 'minpaku-suite'); ?></p>

                <div class="test-section">
                    <h2><?php _e('Test Calendar Component', 'minpaku-suite'); ?></h2>
                    <form id="calendar-test-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="calendar-property-id"><?php _e('Property ID', 'minpaku-suite'); ?></label></th>
                                <td><input type="number" id="calendar-property-id" value="1" min="1" /></td>
                            </tr>
                            <tr>
                                <th><label for="calendar-start-date"><?php _e('Start Date', 'minpaku-suite'); ?></label></th>
                                <td><input type="date" id="calendar-start-date" value="<?php echo date('Y-m-d'); ?>" /></td>
                            </tr>
                            <tr>
                                <th><label for="calendar-end-date"><?php _e('End Date', 'minpaku-suite'); ?></label></th>
                                <td><input type="date" id="calendar-end-date" value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>" /></td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="button" id="test-calendar" class="button button-primary"><?php _e('Test Calendar', 'minpaku-suite'); ?></button>
                        </p>
                    </form>
                    <div id="calendar-results"></div>
                </div>

                <div class="test-section">
                    <h2><?php _e('Test Quote Calculator', 'minpaku-suite'); ?></h2>
                    <form id="quote-test-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="quote-property-id"><?php _e('Property ID', 'minpaku-suite'); ?></label></th>
                                <td><input type="number" id="quote-property-id" value="1" min="1" /></td>
                            </tr>
                            <tr>
                                <th><label for="quote-checkin"><?php _e('Check-in', 'minpaku-suite'); ?></label></th>
                                <td><input type="date" id="quote-checkin" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" /></td>
                            </tr>
                            <tr>
                                <th><label for="quote-checkout"><?php _e('Check-out', 'minpaku-suite'); ?></label></th>
                                <td><input type="date" id="quote-checkout" value="<?php echo date('Y-m-d', strtotime('+4 days')); ?>" /></td>
                            </tr>
                            <tr>
                                <th><label for="quote-guests"><?php _e('Guests', 'minpaku-suite'); ?></label></th>
                                <td><input type="number" id="quote-guests" value="2" min="1" max="10" /></td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="button" id="test-quote" class="button button-primary"><?php _e('Test Quote', 'minpaku-suite'); ?></button>
                        </p>
                    </form>
                    <div id="quote-results"></div>
                </div>

                <div class="test-section">
                    <h2><?php _e('Live Component Demos', 'minpaku-suite'); ?></h2>
                    <p><?php _e('Below are live examples of the shortcodes:', 'minpaku-suite'); ?></p>

                    <h3><?php _e('Calendar Shortcode', 'minpaku-suite'); ?></h3>
                    <div class="shortcode-demo">
                        <?php echo do_shortcode('[minpaku_calendar property_id="1" check_in_out="true" months="2"]'); ?>
                    </div>

                    <h3><?php _e('Quote Calculator Shortcode', 'minpaku-suite'); ?></h3>
                    <div class="shortcode-demo">
                        <?php echo do_shortcode('[minpaku_quote property_id="1" checkin="' . date('Y-m-d', strtotime('+1 day')) . '" checkout="' . date('Y-m-d', strtotime('+4 days')) . '" guests="2"]'); ?>
                    </div>
                </div>
            </div>

            <style>
                .test-section {
                    margin: 30px 0;
                    padding: 20px;
                    background: #fff;
                    border: 1px solid #c3c4c7;
                    border-radius: 4px;
                }

                .test-section h2 {
                    margin-top: 0;
                    border-bottom: 1px solid #ddd;
                    padding-bottom: 10px;
                }

                .shortcode-demo {
                    margin: 20px 0;
                    padding: 20px;
                    background: #f8f9fa;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }

                #calendar-results, #quote-results {
                    margin-top: 20px;
                    padding: 15px;
                    background: #f9f9f9;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    display: none;
                }

                .test-result {
                    margin: 10px 0;
                    padding: 10px;
                    border-radius: 4px;
                }

                .test-result.success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }

                .test-result.error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }

                .test-output {
                    margin-top: 10px;
                    padding: 10px;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-family: monospace;
                    white-space: pre-wrap;
                    font-size: 0.9em;
                }
            </style>

            <script>
                document.getElementById('test-calendar').addEventListener('click', function() {
                    const button = this;
                    const results = document.getElementById('calendar-results');

                    button.disabled = true;
                    button.textContent = '<?php _e('Testing...', 'minpaku-suite'); ?>';
                    results.style.display = 'block';
                    results.innerHTML = '<p><?php _e('Testing calendar component...', 'minpaku-suite'); ?></p>';

                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'minpaku_test_ui_calendar',
                            property_id: document.getElementById('calendar-property-id').value,
                            start_date: document.getElementById('calendar-start-date').value,
                            end_date: document.getElementById('calendar-end-date').value,
                            nonce: '<?php echo wp_create_nonce('minpaku_test_ui_nonce'); ?>'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        button.disabled = false;
                        button.textContent = '<?php _e('Test Calendar', 'minpaku-suite'); ?>';

                        if (data.success) {
                            results.innerHTML = `
                                <div class="test-result success">
                                    <strong><?php _e('Calendar Test Successful!', 'minpaku-suite'); ?></strong>
                                    <p>${data.data.message}</p>
                                </div>
                                <div class="test-output">${JSON.stringify(data.data, null, 2)}</div>
                            `;
                        } else {
                            results.innerHTML = `
                                <div class="test-result error">
                                    <strong><?php _e('Calendar Test Failed!', 'minpaku-suite'); ?></strong>
                                    <p>${data.data.message}</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        button.disabled = false;
                        button.textContent = '<?php _e('Test Calendar', 'minpaku-suite'); ?>';
                        results.innerHTML = `
                            <div class="test-result error">
                                <strong><?php _e('Network Error!', 'minpaku-suite'); ?></strong>
                                <p>${error.message}</p>
                            </div>
                        `;
                    });
                });

                document.getElementById('test-quote').addEventListener('click', function() {
                    const button = this;
                    const results = document.getElementById('quote-results');

                    button.disabled = true;
                    button.textContent = '<?php _e('Testing...', 'minpaku-suite'); ?>';
                    results.style.display = 'block';
                    results.innerHTML = '<p><?php _e('Testing quote calculator...', 'minpaku-suite'); ?></p>';

                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'minpaku_test_ui_quote',
                            property_id: document.getElementById('quote-property-id').value,
                            checkin: document.getElementById('quote-checkin').value,
                            checkout: document.getElementById('quote-checkout').value,
                            guests: document.getElementById('quote-guests').value,
                            nonce: '<?php echo wp_create_nonce('minpaku_test_ui_nonce'); ?>'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        button.disabled = false;
                        button.textContent = '<?php _e('Test Quote', 'minpaku-suite'); ?>';

                        if (data.success) {
                            results.innerHTML = `
                                <div class="test-result success">
                                    <strong><?php _e('Quote Test Successful!', 'minpaku-suite'); ?></strong>
                                    <p>${data.data.message}</p>
                                </div>
                                <div class="test-output">${JSON.stringify(data.data, null, 2)}</div>
                            `;
                        } else {
                            results.innerHTML = `
                                <div class="test-result error">
                                    <strong><?php _e('Quote Test Failed!', 'minpaku-suite'); ?></strong>
                                    <p>${data.data.message}</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        button.disabled = false;
                        button.textContent = '<?php _e('Test Quote', 'minpaku-suite'); ?>';
                        results.innerHTML = `
                            <div class="test-result error">
                                <strong><?php _e('Network Error!', 'minpaku-suite'); ?></strong>
                                <p>${error.message}</p>
                            </div>
                        `;
                    });
                });
            </script>
            <?php
        }
    );
});

// Log UI system initialization
if (class_exists('MCS_Logger')) {
    add_action('init', function() {
        MCS_Logger::log('INFO', 'UI system initialized', [
            'components' => ['AvailabilityCalendar', 'QuoteCalculator'],
            'ajax_handlers' => ['minpaku_get_availability', 'minpaku_calculate_quote']
        ]);
    }, 20);
}