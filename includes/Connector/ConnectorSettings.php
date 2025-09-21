<?php
/**
 * Connector Settings
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Connector;

if (!defined('ABSPATH')) {
    exit;
}

class ConnectorSettings
{
    private const OPTION_PREFIX = 'mcs_connector_';
    private const SETTINGS_GROUP = 'mcs_connector_settings';

    public static function init(): void
    {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('wp_ajax_mcs_connector_generate_keys', [__CLASS__, 'ajax_generate_keys']);
        add_action('wp_ajax_mcs_connector_rotate_keys', [__CLASS__, 'ajax_rotate_keys']);
    }

    /**
     * Register settings
     */
    public static function register_settings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_PREFIX . 'allowed_domains',
            [
                'type' => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize_domains'],
                'default' => []
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_PREFIX . 'api_keys',
            [
                'type' => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize_api_keys'],
                'default' => []
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_PREFIX . 'enabled',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false
            ]
        );
    }

    /**
     * Get allowed domains
     */
    public static function get_allowed_domains(): array
    {
        return get_option(self::OPTION_PREFIX . 'allowed_domains', []);
    }

    /**
     * Get API keys
     */
    public static function get_api_keys(): array
    {
        return get_option(self::OPTION_PREFIX . 'api_keys', []);
    }

    /**
     * Check if connector is enabled
     */
    public static function is_enabled(): bool
    {
        return get_option(self::OPTION_PREFIX . 'enabled', false);
    }

    /**
     * Check if domain is allowed
     */
    public static function is_domain_allowed(string $domain): bool
    {
        if (!self::is_enabled()) {
            return false;
        }

        $allowed_domains = self::get_allowed_domains();
        $domain = self::normalize_domain($domain);

        foreach ($allowed_domains as $allowed) {
            if (self::normalize_domain($allowed) === $domain) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get API key data by site ID
     */
    public static function get_api_key_data(string $site_id): ?array
    {
        $api_keys = self::get_api_keys();
        return $api_keys[$site_id] ?? null;
    }

    /**
     * Generate new API keys
     */
    public static function generate_api_keys(string $site_name = ''): array
    {
        $site_id = wp_generate_uuid4();
        $api_key = 'mcs_' . wp_generate_password(32, false);
        $secret = wp_generate_password(64, false);

        $key_data = [
            'site_id' => $site_id,
            'site_name' => $site_name ?: __('Unnamed Site', 'minpaku-suite'),
            'api_key' => $api_key,
            'secret' => $secret,
            'created_at' => current_time('mysql'),
            'last_used' => null,
            'active' => true
        ];

        $api_keys = self::get_api_keys();
        $api_keys[$site_id] = $key_data;

        update_option(self::OPTION_PREFIX . 'api_keys', $api_keys);

        return $key_data;
    }

    /**
     * Rotate API keys for a site
     */
    public static function rotate_api_keys(string $site_id): ?array
    {
        $api_keys = self::get_api_keys();

        if (!isset($api_keys[$site_id])) {
            return null;
        }

        $api_keys[$site_id]['api_key'] = 'mcs_' . wp_generate_password(32, false);
        $api_keys[$site_id]['secret'] = wp_generate_password(64, false);
        $api_keys[$site_id]['rotated_at'] = current_time('mysql');

        update_option(self::OPTION_PREFIX . 'api_keys', $api_keys);

        return $api_keys[$site_id];
    }

    /**
     * Delete API keys
     */
    public static function delete_api_keys(string $site_id): bool
    {
        $api_keys = self::get_api_keys();

        if (!isset($api_keys[$site_id])) {
            return false;
        }

        unset($api_keys[$site_id]);
        update_option(self::OPTION_PREFIX . 'api_keys', $api_keys);

        return true;
    }

    /**
     * Update last used timestamp
     */
    public static function update_last_used(string $site_id): void
    {
        $api_keys = self::get_api_keys();

        if (isset($api_keys[$site_id])) {
            $api_keys[$site_id]['last_used'] = current_time('mysql');
            update_option(self::OPTION_PREFIX . 'api_keys', $api_keys);
        }
    }

    /**
     * Sanitize domains array
     */
    public static function sanitize_domains($domains): array
    {
        if (!is_array($domains)) {
            return [];
        }

        $sanitized = [];
        foreach ($domains as $domain) {
            $domain = sanitize_text_field($domain);
            $domain = self::normalize_domain($domain);

            if (!empty($domain) && filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
                $sanitized[] = $domain;
            }
        }

        return array_unique($sanitized);
    }

    /**
     * Sanitize API keys array
     */
    public static function sanitize_api_keys($api_keys): array
    {
        if (!is_array($api_keys)) {
            return [];
        }

        $sanitized = [];
        foreach ($api_keys as $site_id => $data) {
            if (is_array($data) && isset($data['api_key'], $data['secret'])) {
                $sanitized[sanitize_text_field($site_id)] = [
                    'site_id' => sanitize_text_field($data['site_id'] ?? $site_id),
                    'site_name' => sanitize_text_field($data['site_name'] ?? ''),
                    'api_key' => sanitize_text_field($data['api_key']),
                    'secret' => sanitize_text_field($data['secret']),
                    'created_at' => sanitize_text_field($data['created_at'] ?? ''),
                    'last_used' => $data['last_used'] ? sanitize_text_field($data['last_used']) : null,
                    'active' => rest_sanitize_boolean($data['active'] ?? true),
                    'rotated_at' => isset($data['rotated_at']) ? sanitize_text_field($data['rotated_at']) : null
                ];
            }
        }

        return $sanitized;
    }

    /**
     * Normalize domain (remove protocol, www, trailing slash)
     */
    private static function normalize_domain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = rtrim($domain, '/');

        return $domain;
    }

    /**
     * AJAX handler for generating new keys
     */
    public static function ajax_generate_keys(): void
    {
        check_ajax_referer('mcs_connector_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'minpaku-suite'));
        }

        $site_name = sanitize_text_field($_POST['site_name'] ?? '');
        $key_data = self::generate_api_keys($site_name);

        wp_send_json_success([
            'message' => __('API keys generated successfully.', 'minpaku-suite'),
            'keys' => $key_data
        ]);
    }

    /**
     * AJAX handler for rotating keys
     */
    public static function ajax_rotate_keys(): void
    {
        check_ajax_referer('mcs_connector_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'minpaku-suite'));
        }

        $site_id = sanitize_text_field($_POST['site_id'] ?? '');

        if (empty($site_id)) {
            wp_send_json_error(__('Site ID is required.', 'minpaku-suite'));
        }

        $key_data = self::rotate_api_keys($site_id);

        if ($key_data) {
            wp_send_json_success([
                'message' => __('API keys rotated successfully.', 'minpaku-suite'),
                'keys' => $key_data
            ]);
        } else {
            wp_send_json_error(__('Site not found.', 'minpaku-suite'));
        }
    }

    /**
     * Render settings page
     */
    public static function render_settings_page(): void
    {
        $allowed_domains = self::get_allowed_domains();
        $api_keys = self::get_api_keys();
        $is_enabled = self::is_enabled();

        include MCS_PATH . 'templates/admin/connector-settings.php';
    }
}