<?php
/**
 * Quote Calculator UI Component
 * Calculates and displays booking quotes with breakdown
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class QuoteCalculator {

    private static $instance = null;
    private $rate_resolver;
    private $rule_engine;

    /**
     * Get singleton instance
     */
    public static function getInstance(): QuoteCalculator {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the quote calculator
     */
    public function __construct() {
        $this->init_dependencies();
        $this->init_hooks();
    }

    /**
     * Initialize dependencies
     */
    private function init_dependencies(): void {
        // Initialize RateResolver if available
        if (class_exists('RateResolver')) {
            $this->rate_resolver = new RateResolver();
        }

        // Initialize RuleEngine if available
        if (class_exists('RuleEngine')) {
            $this->rule_engine = new RuleEngine();
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        add_shortcode('minpaku_quote', [$this, 'render_quote_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_mcs_calculate_quote', [$this, 'ajax_calculate_quote']);
        add_action('wp_ajax_nopriv_mcs_calculate_quote', [$this, 'ajax_calculate_quote']);
    }

    /**
     * Render quote shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Quote HTML
     */
    public function render_quote_shortcode($atts): string {
        $atts = shortcode_atts([
            'property_id' => 0,
            'checkin' => '',
            'checkout' => '',
            'guests' => 2,
            'show_breakdown' => true,
            'show_form' => true,
            'theme' => 'default',
            'class' => '',
            'currency' => 'USD'
        ], $atts, 'minpaku_quote');

        $property_id = intval($atts['property_id']);
        if (!$property_id) {
            return '<div class="mcs-quote-error">' . __('Property ID is required', 'minpaku-suite') . '</div>';
        }

        // Verify property exists
        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'property') {
            return '<div class="mcs-quote-error">' . __('Property not found', 'minpaku-suite') . '</div>';
        }

        // Generate unique quote ID
        $quote_id = 'mcs-quote-' . $property_id . '-' . uniqid();

        // Prepare quote data
        $quote_data = [
            'property_id' => $property_id,
            'checkin' => sanitize_text_field($atts['checkin']),
            'checkout' => sanitize_text_field($atts['checkout']),
            'guests' => intval($atts['guests']),
            'show_breakdown' => filter_var($atts['show_breakdown'], FILTER_VALIDATE_BOOLEAN),
            'show_form' => filter_var($atts['show_form'], FILTER_VALIDATE_BOOLEAN),
            'theme' => sanitize_text_field($atts['theme']),
            'currency' => sanitize_text_field($atts['currency']),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mcs_quote_nonce')
        ];

        // Calculate initial quote if dates provided
        $initial_quote = null;
        if ($quote_data['checkin'] && $quote_data['checkout']) {
            $initial_quote = $this->calculate_quote($property_id, $quote_data['checkin'], $quote_data['checkout'], $quote_data['guests']);
        }

        // Load template
        ob_start();
        $this->load_template('quote', [
            'quote_id' => $quote_id,
            'quote_data' => $quote_data,
            'property' => $property,
            'initial_quote' => $initial_quote,
            'class' => sanitize_html_class($atts['class'])
        ]);
        return ob_get_clean();
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts(): void {
        // Only enqueue on pages that have the quote shortcode
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'minpaku_quote')) {
            return;
        }

        wp_enqueue_script(
            'mcs-quote',
            plugins_url('assets/js/quote.js', dirname(dirname(__FILE__))),
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'mcs-quote',
            plugins_url('assets/css/quote.css', dirname(dirname(__FILE__))),
            [],
            '1.0.0'
        );

        // Localize script
        wp_localize_script('mcs-quote', 'mcsQuote', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mcs_quote_nonce'),
            'strings' => [
                'calculating' => __('Calculating quote...', 'minpaku-suite'),
                'error' => __('Error calculating quote', 'minpaku-suite'),
                'invalidDates' => __('Please select valid dates', 'minpaku-suite'),
                'selectDates' => __('Please select check-in and check-out dates', 'minpaku-suite'),
                'minGuests' => __('Minimum 1 guest required', 'minpaku-suite'),
                'maxGuests' => __('Maximum guests exceeded', 'minpaku-suite'),
                'nights' => __('nights', 'minpaku-suite'),
                'night' => __('night', 'minpaku-suite'),
                'total' => __('Total', 'minpaku-suite'),
                'perNight' => __('per night', 'minpaku-suite'),
                'taxes' => __('Taxes', 'minpaku-suite'),
                'fees' => __('Fees', 'minpaku-suite'),
                'bookNow' => __('Book Now', 'minpaku-suite')
            ],
            'currency' => get_option('mcs_default_currency', 'USD'),
            'currencySymbol' => $this->get_currency_symbol(get_option('mcs_default_currency', 'USD'))
        ]);
    }

    /**
     * AJAX handler for calculating quotes
     */
    public function ajax_calculate_quote(): void {
        check_ajax_referer('mcs_quote_nonce', 'nonce');

        $property_id = intval($_POST['property_id'] ?? 0);
        $checkin = sanitize_text_field($_POST['checkin'] ?? '');
        $checkout = sanitize_text_field($_POST['checkout'] ?? '');
        $guests = intval($_POST['guests'] ?? 1);

        if (!$property_id) {
            wp_send_json_error(['message' => __('Property ID is required', 'minpaku-suite')]);
        }

        if (!$checkin || !$checkout) {
            wp_send_json_error(['message' => __('Check-in and check-out dates are required', 'minpaku-suite')]);
        }

        if ($guests < 1) {
            wp_send_json_error(['message' => __('At least 1 guest is required', 'minpaku-suite')]);
        }

        // Calculate quote
        $quote = $this->calculate_quote($property_id, $checkin, $checkout, $guests);

        if (!$quote['success']) {
            wp_send_json_error(['message' => $quote['message'] ?? __('Failed to calculate quote', 'minpaku-suite')]);
        }

        wp_send_json_success($quote);
    }

    /**
     * Calculate quote for booking
     *
     * @param int $property_id
     * @param string $checkin
     * @param string $checkout
     * @param int $guests
     * @return array
     */
    public function calculate_quote(int $property_id, string $checkin, string $checkout, int $guests): array {
        // Validate inputs
        $validation = $this->validate_quote_inputs($property_id, $checkin, $checkout, $guests);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => implode(', ', $validation['errors'])
            ];
        }

        $nights = $validation['nights'];

        // Prepare booking data
        $booking_data = [
            'start_date' => $checkin,
            'end_date' => $checkout,
            'property_id' => $property_id,
            'guests' => $guests
        ];

        // Calculate base rate using RateResolver
        $rate_result = $this->calculate_base_rate($booking_data);

        if (!$rate_result['success']) {
            return [
                'success' => false,
                'message' => $rate_result['message'] ?? __('Failed to calculate rate', 'minpaku-suite')
            ];
        }

        $base_total = $rate_result['total_rate'];
        $breakdown = $rate_result['breakdown'] ?? [];

        // Apply rules using RuleEngine
        $rule_result = $this->apply_booking_rules($booking_data);

        // Calculate taxes and fees
        $taxes_and_fees = $this->calculate_taxes_and_fees($property_id, $base_total, $booking_data);

        // Build final breakdown
        $quote_breakdown = [
            'accommodation' => [
                'label' => sprintf(__('%d x %s nights', 'minpaku-suite'), $nights, number_format($base_total / $nights, 2)),
                'amount' => $base_total,
                'type' => 'accommodation'
            ]
        ];

        // Add rate adjustments from RateResolver
        if (!empty($rate_result['adjustments'])) {
            foreach ($rate_result['adjustments'] as $adjustment) {
                $quote_breakdown['adjustment_' . $adjustment['type']] = [
                    'label' => $adjustment['description'],
                    'amount' => $adjustment['amount'],
                    'type' => 'adjustment'
                ];
            }
        }

        // Add taxes and fees
        foreach ($taxes_and_fees as $key => $fee) {
            $quote_breakdown[$key] = $fee;
        }

        // Calculate totals
        $subtotal = $base_total;
        $tax_total = 0;
        $fee_total = 0;

        foreach ($quote_breakdown as $item) {
            if ($item['type'] === 'tax') {
                $tax_total += $item['amount'];
            } elseif ($item['type'] === 'fee') {
                $fee_total += $item['amount'];
            }
        }

        $total = $subtotal + $tax_total + $fee_total;

        return [
            'success' => true,
            'property_id' => $property_id,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'guests' => $guests,
            'nights' => $nights,
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($tax_total, 2),
            'fee_total' => round($fee_total, 2),
            'total' => round($total, 2),
            'average_per_night' => round($total / $nights, 2),
            'currency' => get_option('mcs_default_currency', 'USD'),
            'breakdown' => $quote_breakdown,
            'rate_details' => $breakdown,
            'rule_validation' => $rule_result,
            'calculated_at' => current_time('mysql')
        ];
    }

    /**
     * Calculate base rate using RateResolver
     *
     * @param array $booking_data
     * @return array
     */
    private function calculate_base_rate(array $booking_data): array {
        if (!$this->rate_resolver) {
            // Fallback to simple calculation
            $base_rate = get_post_meta($booking_data['property_id'], 'mcs_base_rate', true) ?: 100;
            $nights = (new DateTime($booking_data['start_date']))->diff(new DateTime($booking_data['end_date']))->days;

            return [
                'success' => true,
                'total_rate' => $base_rate * $nights,
                'breakdown' => [
                    'nights' => $nights,
                    'base_rate_per_night' => $base_rate,
                    'total' => $base_rate * $nights
                ],
                'adjustments' => []
            ];
        }

        // Configure rate resolver
        $base_rate = get_post_meta($booking_data['property_id'], 'mcs_base_rate', true) ?: 100;
        $currency = get_option('mcs_default_currency', 'USD');

        $this->rate_resolver->configure([
            'base_rate' => $base_rate,
            'currency' => $currency
        ]);

        return $this->rate_resolver->resolveRate($booking_data);
    }

    /**
     * Apply booking rules using RuleEngine
     *
     * @param array $booking_data
     * @return array
     */
    private function apply_booking_rules(array $booking_data): array {
        if (!$this->rule_engine) {
            return [
                'valid' => true,
                'errors' => [],
                'warnings' => []
            ];
        }

        return $this->rule_engine->validateBooking($booking_data);
    }

    /**
     * Calculate taxes and fees
     *
     * @param int $property_id
     * @param float $subtotal
     * @param array $booking_data
     * @return array
     */
    private function calculate_taxes_and_fees(int $property_id, float $subtotal, array $booking_data): array {
        $taxes_and_fees = [];

        // Get property tax settings
        $tax_rate = get_post_meta($property_id, 'mcs_tax_rate', true) ?: 0;
        if ($tax_rate > 0) {
            $tax_amount = $subtotal * ($tax_rate / 100);
            $taxes_and_fees['tax'] = [
                'label' => sprintf(__('Taxes (%s%%)', 'minpaku-suite'), $tax_rate),
                'amount' => $tax_amount,
                'type' => 'tax'
            ];
        }

        // Cleaning fee
        $cleaning_fee = get_post_meta($property_id, 'mcs_cleaning_fee', true) ?: 0;
        if ($cleaning_fee > 0) {
            $taxes_and_fees['cleaning_fee'] = [
                'label' => __('Cleaning Fee', 'minpaku-suite'),
                'amount' => $cleaning_fee,
                'type' => 'fee'
            ];
        }

        // Service fee
        $service_fee_rate = get_option('mcs_service_fee_rate', 0);
        if ($service_fee_rate > 0) {
            $service_fee = $subtotal * ($service_fee_rate / 100);
            $taxes_and_fees['service_fee'] = [
                'label' => sprintf(__('Service Fee (%s%%)', 'minpaku-suite'), $service_fee_rate),
                'amount' => $service_fee,
                'type' => 'fee'
            ];
        }

        // Security deposit (not added to total, just displayed)
        $security_deposit = get_post_meta($property_id, 'mcs_security_deposit', true) ?: 0;
        if ($security_deposit > 0) {
            $taxes_and_fees['security_deposit'] = [
                'label' => __('Security Deposit (Hold)', 'minpaku-suite'),
                'amount' => $security_deposit,
                'type' => 'deposit'
            ];
        }

        // Additional guest fee
        $max_guests = get_post_meta($property_id, 'mcs_max_guests', true) ?: 4;
        $additional_guest_fee = get_post_meta($property_id, 'mcs_additional_guest_fee', true) ?: 0;
        $guests = $booking_data['guests'] ?? 1;

        if ($additional_guest_fee > 0 && $guests > $max_guests) {
            $extra_guests = $guests - $max_guests;
            $nights = (new DateTime($booking_data['start_date']))->diff(new DateTime($booking_data['end_date']))->days;
            $extra_guest_total = $extra_guests * $additional_guest_fee * $nights;

            $taxes_and_fees['additional_guest_fee'] = [
                'label' => sprintf(__('Additional Guest Fee (%d x %d nights)', 'minpaku-suite'), $extra_guests, $nights),
                'amount' => $extra_guest_total,
                'type' => 'fee'
            ];
        }

        return $taxes_and_fees;
    }

    /**
     * Validate quote inputs
     *
     * @param int $property_id
     * @param string $checkin
     * @param string $checkout
     * @param int $guests
     * @return array
     */
    private function validate_quote_inputs(int $property_id, string $checkin, string $checkout, int $guests): array {
        $errors = [];

        // Validate property exists
        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'property') {
            $errors[] = __('Property not found', 'minpaku-suite');
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate dates
        $checkin_date = DateTime::createFromFormat('Y-m-d', $checkin);
        $checkout_date = DateTime::createFromFormat('Y-m-d', $checkout);

        if (!$checkin_date || !$checkout_date) {
            $errors[] = __('Invalid date format', 'minpaku-suite');
        } elseif ($checkout_date <= $checkin_date) {
            $errors[] = __('Check-out must be after check-in', 'minpaku-suite');
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        $nights = $checkin_date->diff($checkout_date)->days;

        // Validate stay length
        $min_stay = get_post_meta($property_id, 'mcs_min_stay', true) ?: 1;
        $max_stay = get_post_meta($property_id, 'mcs_max_stay', true) ?: 30;

        if ($nights < $min_stay) {
            $errors[] = sprintf(__('Minimum stay is %d nights', 'minpaku-suite'), $min_stay);
        }

        if ($nights > $max_stay) {
            $errors[] = sprintf(__('Maximum stay is %d nights', 'minpaku-suite'), $max_stay);
        }

        // Validate guests
        $max_guests = get_post_meta($property_id, 'mcs_max_guests', true) ?: 10;
        if ($guests > $max_guests) {
            $errors[] = sprintf(__('Maximum %d guests allowed', 'minpaku-suite'), $max_guests);
        }

        // Check availability (basic check)
        if (class_exists('AvailabilityCalendar')) {
            $calendar = AvailabilityCalendar::getInstance();
            $availability_check = $calendar->validate_date_selection($property_id, $checkin, $checkout);

            if (!$availability_check['valid']) {
                $errors = array_merge($errors, $availability_check['errors']);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'nights' => $nights
        ];
    }

    /**
     * Load template file
     *
     * @param string $template_name
     * @param array $args
     */
    private function load_template(string $template_name, array $args = []): void {
        extract($args);

        // Look for template in theme first, then plugin
        $template_paths = [
            get_stylesheet_directory() . '/minpaku-suite/' . $template_name . '.php',
            get_template_directory() . '/minpaku-suite/' . $template_name . '.php',
            dirname(dirname(dirname(__FILE__))) . '/templates/' . $template_name . '.php'
        ];

        foreach ($template_paths as $path) {
            if (file_exists($path)) {
                include $path;
                return;
            }
        }

        // Fallback to default template
        if ($template_name === 'quote') {
            $this->render_default_quote($args);
        }
    }

    /**
     * Render default quote template
     *
     * @param array $args
     */
    private function render_default_quote(array $args): void {
        $quote_id = $args['quote_id'] ?? 'mcs-quote';
        $quote_data = $args['quote_data'] ?? [];
        $property = $args['property'] ?? null;
        $initial_quote = $args['initial_quote'] ?? null;
        $class = $args['class'] ?? '';

        echo '<div class="mcs-quote-container ' . esc_attr($class) . '" id="' . esc_attr($quote_id) . '">';

        // Quote header
        echo '<div class="mcs-quote-header">';
        if ($property) {
            echo '<h3 class="mcs-quote-title">' . esc_html($property->post_title) . ' - ' . __('Quote', 'minpaku-suite') . '</h3>';
        }
        echo '</div>';

        // Quote form
        if ($quote_data['show_form']) {
            echo '<div class="mcs-quote-form">';
            echo '<form class="mcs-quote-inputs">';
            echo '<div class="mcs-input-group">';
            echo '<label for="mcs-checkin">' . __('Check-in', 'minpaku-suite') . '</label>';
            echo '<input type="date" id="mcs-checkin" name="checkin" value="' . esc_attr($quote_data['checkin']) . '" required>';
            echo '</div>';

            echo '<div class="mcs-input-group">';
            echo '<label for="mcs-checkout">' . __('Check-out', 'minpaku-suite') . '</label>';
            echo '<input type="date" id="mcs-checkout" name="checkout" value="' . esc_attr($quote_data['checkout']) . '" required>';
            echo '</div>';

            echo '<div class="mcs-input-group">';
            echo '<label for="mcs-guests">' . __('Guests', 'minpaku-suite') . '</label>';
            echo '<select id="mcs-guests" name="guests">';
            for ($i = 1; $i <= 10; $i++) {
                $selected = selected($quote_data['guests'], $i, false);
                echo '<option value="' . $i . '"' . $selected . '>' . $i . ' ' . ($i === 1 ? __('guest', 'minpaku-suite') : __('guests', 'minpaku-suite')) . '</option>';
            }
            echo '</select>';
            echo '</div>';

            echo '<div class="mcs-input-group">';
            echo '<button type="button" class="mcs-calculate-quote">' . __('Calculate Quote', 'minpaku-suite') . '</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
        }

        // Quote results
        echo '<div class="mcs-quote-results">';

        if ($initial_quote) {
            $this->render_quote_display($initial_quote, $quote_data);
        } else {
            echo '<div class="mcs-quote-placeholder">';
            echo '<p>' . __('Select dates and guests to see pricing', 'minpaku-suite') . '</p>';
            echo '</div>';
        }

        echo '</div>'; // .mcs-quote-results

        // Loading indicator
        echo '<div class="mcs-quote-loading" style="display: none;">';
        echo '<div class="mcs-spinner"></div>';
        echo '<p>' . __('Calculating quote...', 'minpaku-suite') . '</p>';
        echo '</div>';

        // Store quote data as JSON
        echo '<script type="application/json" class="mcs-quote-data">';
        echo json_encode($quote_data);
        echo '</script>';

        echo '</div>'; // .mcs-quote-container
    }

    /**
     * Render quote display
     *
     * @param array $quote
     * @param array $quote_data
     */
    private function render_quote_display(array $quote, array $quote_data): void {
        if (!$quote['success']) {
            echo '<div class="mcs-quote-error">';
            echo '<p>' . esc_html($quote['message']) . '</p>';
            echo '</div>';
            return;
        }

        $currency_symbol = $this->get_currency_symbol($quote['currency']);

        echo '<div class="mcs-quote-summary">';
        echo '<div class="mcs-quote-total">';
        echo '<span class="mcs-total-amount">' . $currency_symbol . number_format($quote['total'], 2) . '</span>';
        echo '<span class="mcs-total-label">' . sprintf(__('for %d nights', 'minpaku-suite'), $quote['nights']) . '</span>';
        echo '</div>';
        echo '<div class="mcs-quote-average">';
        echo '<span class="mcs-average-amount">' . $currency_symbol . number_format($quote['average_per_night'], 2) . '</span>';
        echo '<span class="mcs-average-label">' . __('average per night', 'minpaku-suite') . '</span>';
        echo '</div>';
        echo '</div>';

        // Breakdown
        if ($quote_data['show_breakdown'] && !empty($quote['breakdown'])) {
            echo '<div class="mcs-quote-breakdown">';
            echo '<h4>' . __('Price Breakdown', 'minpaku-suite') . '</h4>';
            echo '<div class="mcs-breakdown-items">';

            foreach ($quote['breakdown'] as $key => $item) {
                $amount_display = $currency_symbol . number_format($item['amount'], 2);

                echo '<div class="mcs-breakdown-item mcs-' . esc_attr($item['type']) . '">';
                echo '<span class="mcs-item-label">' . esc_html($item['label']) . '</span>';
                echo '<span class="mcs-item-amount">' . $amount_display . '</span>';
                echo '</div>';
            }

            // Totals
            if ($quote['tax_total'] > 0 || $quote['fee_total'] > 0) {
                echo '<div class="mcs-breakdown-subtotal">';
                echo '<span class="mcs-subtotal-label">' . __('Subtotal', 'minpaku-suite') . '</span>';
                echo '<span class="mcs-subtotal-amount">' . $currency_symbol . number_format($quote['subtotal'], 2) . '</span>';
                echo '</div>';

                if ($quote['tax_total'] > 0) {
                    echo '<div class="mcs-breakdown-tax-total">';
                    echo '<span class="mcs-tax-label">' . __('Taxes', 'minpaku-suite') . '</span>';
                    echo '<span class="mcs-tax-amount">' . $currency_symbol . number_format($quote['tax_total'], 2) . '</span>';
                    echo '</div>';
                }

                if ($quote['fee_total'] > 0) {
                    echo '<div class="mcs-breakdown-fee-total">';
                    echo '<span class="mcs-fee-label">' . __('Fees', 'minpaku-suite') . '</span>';
                    echo '<span class="mcs-fee-amount">' . $currency_symbol . number_format($quote['fee_total'], 2) . '</span>';
                    echo '</div>';
                }
            }

            echo '<div class="mcs-breakdown-total">';
            echo '<span class="mcs-total-label">' . __('Total', 'minpaku-suite') . '</span>';
            echo '<span class="mcs-total-amount">' . $currency_symbol . number_format($quote['total'], 2) . '</span>';
            echo '</div>';

            echo '</div>'; // .mcs-breakdown-items
            echo '</div>'; // .mcs-quote-breakdown
        }

        // Book now button
        echo '<div class="mcs-quote-actions">';
        echo '<button type="button" class="mcs-book-now">' . __('Book Now', 'minpaku-suite') . '</button>';
        echo '</div>';
    }

    /**
     * Get currency symbol
     *
     * @param string $currency
     * @return string
     */
    private function get_currency_symbol(string $currency): string {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'SEK' => 'kr',
            'NZD' => 'NZ$'
        ];

        return $symbols[strtoupper($currency)] ?? strtoupper($currency);
    }

    /**
     * Get quote summary for external use
     *
     * @param int $property_id
     * @param string $checkin
     * @param string $checkout
     * @param int $guests
     * @return array
     */
    public function get_quote_summary(int $property_id, string $checkin, string $checkout, int $guests): array {
        $quote = $this->calculate_quote($property_id, $checkin, $checkout, $guests);

        if (!$quote['success']) {
            return $quote;
        }

        return [
            'success' => true,
            'total' => $quote['total'],
            'nights' => $quote['nights'],
            'average_per_night' => $quote['average_per_night'],
            'currency' => $quote['currency'],
            'formatted_total' => $this->get_currency_symbol($quote['currency']) . number_format($quote['total'], 2)
        ];
    }
}