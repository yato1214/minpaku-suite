<?php
/**
 * Pricing Test Admin Page
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class PricingTestPage
{
    public static function init()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('admin_menu', [__CLASS__, 'add_test_page']);
            add_action('wp_ajax_run_pricing_tests', [__CLASS__, 'handle_test_request']);
        }
    }

    public static function add_test_page()
    {
        add_submenu_page(
            'minpaku-suite',
            __('Pricing Tests', 'minpaku-suite'),
            __('Pricing Tests', 'minpaku-suite'),
            'manage_options',
            'mcs-pricing-tests',
            [__CLASS__, 'render_test_page']
        );
    }

    public static function render_test_page()
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__('Pricing Engine Tests', 'minpaku-suite')); ?></h1>

            <div class="notice notice-info">
                <p><?php echo esc_html(__('This page is only available in debug mode. Tests help verify the pricing engine functionality.', 'minpaku-suite')); ?></p>
            </div>

            <div class="card">
                <h2><?php echo esc_html(__('Run Basic Tests', 'minpaku-suite')); ?></h2>
                <p><?php echo esc_html(__('Execute basic functionality tests for the pricing engine including rate calculation, discounts, and taxes.', 'minpaku-suite')); ?></p>

                <button id="run-tests" class="button button-primary">
                    <?php echo esc_html(__('Run Tests', 'minpaku-suite')); ?>
                </button>

                <div id="test-results" style="margin-top: 20px;"></div>
            </div>

            <div class="card">
                <h2><?php echo esc_html(__('Manual Quote Test', 'minpaku-suite')); ?></h2>
                <form id="manual-quote-test">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="property-id"><?php echo esc_html(__('Property ID', 'minpaku-suite')); ?></label>
                            </th>
                            <td>
                                <input type="number" id="property-id" name="property_id" value="1" min="1" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="checkin-date"><?php echo esc_html(__('Check-in Date', 'minpaku-suite')); ?></label>
                            </th>
                            <td>
                                <input type="date" id="checkin-date" name="checkin" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="checkout-date"><?php echo esc_html(__('Check-out Date', 'minpaku-suite')); ?></label>
                            </th>
                            <td>
                                <input type="date" id="checkout-date" name="checkout" value="<?php echo date('Y-m-d', strtotime('+4 days')); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="adults"><?php echo esc_html(__('Adults', 'minpaku-suite')); ?></label>
                            </th>
                            <td>
                                <input type="number" id="adults" name="adults" value="2" min="1" max="50" class="small-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="children"><?php echo esc_html(__('Children', 'minpaku-suite')); ?></label>
                            </th>
                            <td>
                                <input type="number" id="children" name="children" value="0" min="0" max="20" class="small-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="infants"><?php echo esc_html(__('Infants', 'minpaku-suite')); ?></label>
                            </th>
                            <td>
                                <input type="number" id="infants" name="infants" value="0" min="0" max="10" class="small-text" />
                            </td>
                        </tr>
                    </table>

                    <button type="submit" class="button button-primary">
                        <?php echo esc_html(__('Generate Quote', 'minpaku-suite')); ?>
                    </button>
                </form>

                <div id="quote-results" style="margin-top: 20px;"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#run-tests').on('click', function() {
                const button = $(this);
                const resultsDiv = $('#test-results');

                button.prop('disabled', true).text('Running...');
                resultsDiv.html('<p>Running tests...</p>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'run_pricing_tests',
                        nonce: '<?php echo wp_create_nonce('pricing_tests'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            let html = '<div class="notice notice-success"><p>';
                            html += 'Tests completed: ' + data.passed + '/' + data.total + ' passed';
                            html += '</p></div>';

                            html += '<h3>Test Results:</h3><ul>';
                            data.results.forEach(function(result) {
                                const status = result.status === 'passed' ? '✓' : '✗';
                                const statusClass = result.status === 'passed' ? 'success' : 'error';
                                html += '<li class="' + statusClass + '">' + status + ' ' + result.test;
                                if (result.error) {
                                    html += ' - ' + result.error;
                                }
                                html += '</li>';
                            });
                            html += '</ul>';

                            resultsDiv.html(html);
                        } else {
                            resultsDiv.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        resultsDiv.html('<div class="notice notice-error"><p>AJAX error occurred</p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Run Tests');
                    }
                });
            });

            $('#manual-quote-test').on('submit', function(e) {
                e.preventDefault();

                const form = $(this);
                const resultsDiv = $('#quote-results');
                const formData = form.serialize();

                resultsDiv.html('<p>Generating quote...</p>');

                // Make request to quote API endpoint
                const params = new URLSearchParams(formData);
                const quoteUrl = '<?php echo rest_url('minpaku/v1/connector/quote'); ?>?' + params.toString();

                $.ajax({
                    url: quoteUrl,
                    type: 'GET',
                    headers: {
                        'X-MCS-Key': 'test-key',
                        'X-MCS-Nonce': 'test-nonce',
                        'X-MCS-Timestamp': Math.floor(Date.now() / 1000),
                        'X-MCS-Signature': 'test-signature'
                    },
                    success: function(response) {
                        let html = '<h3>Quote Response:</h3>';
                        html += '<pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">';
                        html += JSON.stringify(response, null, 2);
                        html += '</pre>';
                        resultsDiv.html(html);
                    },
                    error: function(xhr) {
                        let errorMsg = 'Quote generation failed';
                        if (xhr.responseJSON) {
                            errorMsg += ': ' + (xhr.responseJSON.message || xhr.responseJSON.code);
                        }
                        resultsDiv.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                    }
                });
            });
        });
        </script>

        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h2 {
            margin-top: 0;
        }
        #test-results ul {
            list-style: none;
            padding: 0;
        }
        #test-results .success {
            color: #46b450;
        }
        #test-results .error {
            color: #dc3232;
        }
        </style>
        <?php
    }

    public static function handle_test_request()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pricing_tests')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $results = \MinpakuSuite\Pricing\TestRunner::run_basic_tests();
            wp_send_json_success($results);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}