<?php
/**
 * Provider Admin Interface
 * Admin interface for managing channel and payment providers
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProviderAdmin {

    private $container;

    /**
     * Constructor
     */
    public function __construct() {
        $this->container = ProviderContainer::getInstance();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_mcs_test_provider', [$this, 'ajax_test_provider']);
        add_action('wp_ajax_mcs_save_provider_config', [$this, 'ajax_save_provider_config']);
        add_action('init', [$this, 'handle_webhook_endpoints']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_menu_page(
            __('Provider Settings', 'minpaku-suite'),
            __('Providers', 'minpaku-suite'),
            'manage_options',
            'mcs-providers',
            [$this, 'render_providers_page'],
            'dashicons-admin-plugins',
            80
        );

        add_submenu_page(
            'mcs-providers',
            __('Channel Providers', 'minpaku-suite'),
            __('Channels', 'minpaku-suite'),
            'manage_options',
            'mcs-channel-providers',
            [$this, 'render_channel_providers_page']
        );

        add_submenu_page(
            'mcs-providers',
            __('Payment Providers', 'minpaku-suite'),
            __('Payments', 'minpaku-suite'),
            'manage_options',
            'mcs-payment-providers',
            [$this, 'render_payment_providers_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        // Provider configuration will be handled via AJAX for better UX
        register_setting('mcs_provider_settings', 'mcs_provider_config');
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook): void {
        if (strpos($hook, 'mcs-provider') === false && strpos($hook, 'mcs-channel') === false && strpos($hook, 'mcs-payment') === false) {
            return;
        }

        wp_enqueue_script('mcs-provider-admin', plugins_url('assets/js/provider-admin.js', dirname(dirname(__FILE__))), ['jquery'], '1.0.0', true);
        wp_enqueue_style('mcs-provider-admin', plugins_url('assets/css/provider-admin.css', dirname(dirname(__FILE__))), [], '1.0.0');

        wp_localize_script('mcs-provider-admin', 'mcsProviderAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mcs_provider_admin_nonce'),
            'strings' => [
                'testing' => __('Testing connection...', 'minpaku-suite'),
                'saving' => __('Saving configuration...', 'minpaku-suite'),
                'success' => __('Success', 'minpaku-suite'),
                'error' => __('Error', 'minpaku-suite'),
                'confirm_reset' => __('Are you sure you want to reset this provider configuration?', 'minpaku-suite')
            ]
        ]);
    }

    /**
     * Render main providers page
     */
    public function render_providers_page(): void {
        $stats = $this->container->getStatistics();

        echo '<div class="wrap">';
        echo '<h1>' . __('Provider Management', 'minpaku-suite') . '</h1>';

        // Statistics overview
        echo '<div class="mcs-provider-stats">';
        echo '<div class="stat-cards">';

        echo '<div class="stat-card">';
        echo '<h3>' . __('Channel Providers', 'minpaku-suite') . '</h3>';
        echo '<div class="stat-number">' . $stats['channel_providers']['total'] . '</div>';
        echo '<div class="stat-details">';
        echo sprintf(__('%d configured, %d connected', 'minpaku-suite'),
            $stats['channel_providers']['configured'],
            $stats['channel_providers']['connected']);
        echo '</div>';
        echo '</div>';

        echo '<div class="stat-card">';
        echo '<h3>' . __('Payment Providers', 'minpaku-suite') . '</h3>';
        echo '<div class="stat-number">' . $stats['payment_providers']['total'] . '</div>';
        echo '<div class="stat-details">';
        echo sprintf(__('%d configured, %d connected', 'minpaku-suite'),
            $stats['payment_providers']['configured'],
            $stats['payment_providers']['connected']);
        echo '</div>';
        echo '</div>';

        echo '</div>'; // stat-cards
        echo '</div>'; // mcs-provider-stats

        // Default providers
        echo '<div class="mcs-default-providers">';
        echo '<h2>' . __('Default Providers', 'minpaku-suite') . '</h2>';

        echo '<form method="post" action="options.php">';
        settings_fields('mcs_provider_settings');

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . __('Default Channel Provider', 'minpaku-suite') . '</th>';
        echo '<td>';
        $this->render_provider_select('channel', $stats['defaults']['channel']);
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Default Payment Provider', 'minpaku-suite') . '</th>';
        echo '<td>';
        $this->render_provider_select('payment', $stats['defaults']['payment']);
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        submit_button();
        echo '</form>';
        echo '</div>';

        // Quick actions
        echo '<div class="mcs-quick-actions">';
        echo '<h2>' . __('Quick Actions', 'minpaku-suite') . '</h2>';
        echo '<p><a href="' . admin_url('admin.php?page=mcs-channel-providers') . '" class="button">' . __('Manage Channel Providers', 'minpaku-suite') . '</a></p>';
        echo '<p><a href="' . admin_url('admin.php?page=mcs-payment-providers') . '" class="button">' . __('Manage Payment Providers', 'minpaku-suite') . '</a></p>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render channel providers page
     */
    public function render_channel_providers_page(): void {
        echo '<div class="wrap">';
        echo '<h1>' . __('Channel Providers', 'minpaku-suite') . '</h1>';

        $providers = $this->container->getChannelProviders();

        if (empty($providers)) {
            echo '<p>' . __('No channel providers available.', 'minpaku-suite') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="mcs-providers-grid">';

        foreach ($providers as $name => $provider) {
            $this->render_provider_card('channel', $name, $provider);
        }

        echo '</div>'; // mcs-providers-grid
        echo '</div>';
    }

    /**
     * Render payment providers page
     */
    public function render_payment_providers_page(): void {
        echo '<div class="wrap">';
        echo '<h1>' . __('Payment Providers', 'minpaku-suite') . '</h1>';

        $providers = $this->container->getPaymentProviders();

        if (empty($providers)) {
            echo '<p>' . __('No payment providers available.', 'minpaku-suite') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="mcs-providers-grid">';

        foreach ($providers as $name => $provider) {
            $this->render_provider_card('payment', $name, $provider);
        }

        echo '</div>'; // mcs-providers-grid
        echo '</div>';
    }

    /**
     * Render provider select dropdown
     */
    private function render_provider_select(string $type, ?string $selected): void {
        $providers = $type === 'channel' ? $this->container->getChannelProviders() : $this->container->getPaymentProviders();

        echo '<select name="mcs_default_' . $type . '_provider" id="default_' . $type . '_provider">';
        echo '<option value="">' . __('Select a provider', 'minpaku-suite') . '</option>';

        foreach ($providers as $name => $provider) {
            $selected_attr = selected($selected, $name, false);
            echo '<option value="' . esc_attr($name) . '"' . $selected_attr . '>';
            echo esc_html($provider->getDisplayName());
            echo '</option>';
        }

        echo '</select>';
    }

    /**
     * Render provider card
     */
    private function render_provider_card(string $type, string $name, $provider): void {
        $status = $this->container->getProviderStatus($type, $name);
        $config = $provider->getConfig();

        echo '<div class="provider-card" data-provider="' . esc_attr($name) . '" data-type="' . esc_attr($type) . '">';

        // Header
        echo '<div class="provider-header">';
        echo '<h3>' . esc_html($provider->getDisplayName()) . '</h3>';
        echo '<div class="provider-status">';

        if ($status['connected']) {
            echo '<span class="status-badge connected">' . __('Connected', 'minpaku-suite') . '</span>';
        } elseif ($status['configured']) {
            echo '<span class="status-badge configured">' . __('Configured', 'minpaku-suite') . '</span>';
        } else {
            echo '<span class="status-badge not-configured">' . __('Not Configured', 'minpaku-suite') . '</span>';
        }

        echo '</div>';
        echo '</div>';

        // Description
        echo '<div class="provider-description">';
        echo '<p>' . esc_html($provider->getDescription()) . '</p>';
        echo '</div>';

        // Configuration form
        echo '<div class="provider-config">';
        echo '<form class="provider-config-form" data-provider="' . esc_attr($name) . '" data-type="' . esc_attr($type) . '">';

        $config_fields = $provider->getConfigFields();
        foreach ($config_fields as $field_name => $field_config) {
            $this->render_config_field($field_name, $field_config, $config[$field_name] ?? '');
        }

        echo '<div class="provider-actions">';
        echo '<button type="submit" class="button button-primary save-config">' . __('Save Configuration', 'minpaku-suite') . '</button>';
        echo '<button type="button" class="button test-connection">' . __('Test Connection', 'minpaku-suite') . '</button>';
        echo '<button type="button" class="button reset-config">' . __('Reset', 'minpaku-suite') . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';

        // Webhook URL (if supported)
        if (method_exists($provider, 'getWebhookUrl')) {
            echo '<div class="provider-webhook">';
            echo '<h4>' . __('Webhook URL', 'minpaku-suite') . '</h4>';
            echo '<code>' . esc_html($provider->getWebhookUrl()) . '</code>';
            echo '<button type="button" class="button copy-webhook" data-url="' . esc_attr($provider->getWebhookUrl()) . '">' . __('Copy', 'minpaku-suite') . '</button>';
            echo '</div>';
        }

        // Last error (if any)
        if ($status['last_error']) {
            echo '<div class="provider-error">';
            echo '<strong>' . __('Last Error:', 'minpaku-suite') . '</strong> ';
            echo esc_html($status['last_error']);
            echo '</div>';
        }

        echo '</div>'; // provider-card
    }

    /**
     * Render configuration field
     */
    private function render_config_field(string $field_name, array $field_config, string $value): void {
        $field_type = $field_config['type'] ?? 'text';
        $field_label = $field_config['label'] ?? $field_name;
        $field_required = $field_config['required'] ?? false;
        $field_description = $field_config['description'] ?? '';
        $field_default = $field_config['default'] ?? '';

        if (empty($value) && !empty($field_default)) {
            $value = $field_default;
        }

        echo '<div class="config-field">';
        echo '<label for="' . esc_attr($field_name) . '">';
        echo esc_html($field_label);
        if ($field_required) {
            echo ' <span class="required">*</span>';
        }
        echo '</label>';

        switch ($field_type) {
            case 'password':
                echo '<input type="password" id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '"';
                if ($field_required) echo ' required';
                echo ' />';
                break;

            case 'textarea':
                echo '<textarea id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) . '"';
                if ($field_required) echo ' required';
                echo '>' . esc_textarea($value) . '</textarea>';
                break;

            case 'select':
                echo '<select id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) . '"';
                if ($field_required) echo ' required';
                echo '>';

                $options = $field_config['options'] ?? [];
                foreach ($options as $option_value => $option_label) {
                    $selected = selected($value, $option_value, false);
                    echo '<option value="' . esc_attr($option_value) . '"' . $selected . '>' . esc_html($option_label) . '</option>';
                }
                echo '</select>';
                break;

            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) . '" value="1"';
                checked($value, '1');
                echo ' />';
                break;

            case 'number':
                echo '<input type="number" id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '"';
                if ($field_required) echo ' required';
                if (isset($field_config['min'])) echo ' min="' . esc_attr($field_config['min']) . '"';
                if (isset($field_config['max'])) echo ' max="' . esc_attr($field_config['max']) . '"';
                echo ' />';
                break;

            case 'url':
                echo '<input type="url" id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '"';
                if ($field_required) echo ' required';
                echo ' />';
                break;

            default: // text
                echo '<input type="text" id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '"';
                if ($field_required) echo ' required';
                echo ' />';
                break;
        }

        if ($field_description) {
            echo '<p class="description">' . esc_html($field_description) . '</p>';
        }

        echo '</div>';
    }

    /**
     * AJAX handler for testing provider connection
     */
    public function ajax_test_provider(): void {
        check_ajax_referer('mcs_provider_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $type = sanitize_text_field($_POST['type'] ?? '');
        $provider_name = sanitize_text_field($_POST['provider'] ?? '');

        if (empty($type) || empty($provider_name)) {
            wp_send_json_error(__('Missing parameters', 'minpaku-suite'));
        }

        $result = $this->container->testProvider($type, $provider_name);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for saving provider configuration
     */
    public function ajax_save_provider_config(): void {
        check_ajax_referer('mcs_provider_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $type = sanitize_text_field($_POST['type'] ?? '');
        $provider_name = sanitize_text_field($_POST['provider'] ?? '');
        $config = $_POST['config'] ?? [];

        if (empty($type) || empty($provider_name)) {
            wp_send_json_error(__('Missing parameters', 'minpaku-suite'));
        }

        // Sanitize configuration data
        $sanitized_config = [];
        foreach ($config as $key => $value) {
            $sanitized_config[sanitize_key($key)] = sanitize_text_field($value);
        }

        $success = $this->container->configureProvider($type, $provider_name, $sanitized_config);

        if ($success) {
            wp_send_json_success([
                'message' => __('Configuration saved successfully', 'minpaku-suite')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to save configuration', 'minpaku-suite')
            ]);
        }
    }

    /**
     * Handle webhook endpoints
     */
    public function handle_webhook_endpoints(): void {
        if (!isset($_GET['mcs_webhook'])) {
            return;
        }

        $webhook_type = sanitize_text_field($_GET['mcs_webhook']);
        $provider_name = sanitize_text_field($_GET['provider'] ?? '');

        if (empty($provider_name)) {
            http_response_code(400);
            exit('Missing provider parameter');
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        $result = $this->container->handleWebhook($webhook_type, $provider_name, $payload, $signature);

        if ($result['success']) {
            http_response_code(200);
            echo 'Webhook processed successfully';
        } else {
            http_response_code(400);
            echo 'Webhook processing failed: ' . $result['message'];
        }

        exit;
    }
}