<?php
/**
 * API Settings Admin Interface
 * Manages rate limits, cache settings, and API keys
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../Api/ApiKeyManager.php';
require_once __DIR__ . '/../Api/ResponseCache.php';

class ApiSettings {

    /**
     * Option key for API settings
     */
    const OPTION_KEY = 'minpaku_api_settings';

    /**
     * Settings page slug
     */
    const PAGE_SLUG = 'minpaku-api-settings';

    /**
     * API key manager instance
     */
    private $api_key_manager;

    /**
     * Response cache instance
     */
    private $cache;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key_manager = new ApiKeyManager();
        $this->cache = new ResponseCache();

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_minpaku_generate_api_key', [$this, 'ajax_generate_api_key']);
        add_action('wp_ajax_minpaku_revoke_api_key', [$this, 'ajax_revoke_api_key']);
        add_action('wp_ajax_minpaku_clear_cache', [$this, 'ajax_clear_cache']);
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=property',
            __('API Settings', 'minpaku-suite'),
            __('API Settings', 'minpaku-suite'),
            'manage_minpaku',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);

        // Rate Limits Section
        add_settings_section(
            'rate_limits',
            __('Rate Limits', 'minpaku-suite'),
            [$this, 'render_rate_limits_section'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'availability_rate_limit',
            __('Availability API', 'minpaku-suite'),
            [$this, 'render_rate_limit_field'],
            self::PAGE_SLUG,
            'rate_limits',
            ['bucket' => 'api:availability']
        );

        add_settings_field(
            'quote_rate_limit',
            __('Quote API', 'minpaku-suite'),
            [$this, 'render_rate_limit_field'],
            self::PAGE_SLUG,
            'rate_limits',
            ['bucket' => 'api:quote']
        );

        add_settings_field(
            'webhook_rate_limit',
            __('Webhook API', 'minpaku-suite'),
            [$this, 'render_rate_limit_field'],
            self::PAGE_SLUG,
            'rate_limits',
            ['bucket' => 'api:webhook']
        );

        // Cache Settings Section
        add_settings_section(
            'cache_settings',
            __('Cache Settings', 'minpaku-suite'),
            [$this, 'render_cache_section'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'availability_cache_ttl',
            __('Availability Cache TTL', 'minpaku-suite'),
            [$this, 'render_cache_ttl_field'],
            self::PAGE_SLUG,
            'cache_settings',
            ['type' => 'availability']
        );

        add_settings_field(
            'quote_cache_ttl',
            __('Quote Cache TTL', 'minpaku-suite'),
            [$this, 'render_cache_ttl_field'],
            self::PAGE_SLUG,
            'cache_settings',
            ['type' => 'quote']
        );

        add_settings_field(
            'webhook_cache_ttl',
            __('Webhook Cache TTL', 'minpaku-suite'),
            [$this, 'render_cache_ttl_field'],
            self::PAGE_SLUG,
            'cache_settings',
            ['type' => 'webhook']
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        wp_enqueue_script(
            'minpaku-api-settings',
            plugin_dir_url(__FILE__) . '../../assets/js/api-settings.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('minpaku-api-settings', 'minpakuApiSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('minpaku_api_settings'),
            'strings' => [
                'confirmRevoke' => __('Are you sure you want to revoke this API key? This cannot be undone.', 'minpaku-suite'),
                'confirmClearCache' => __('Are you sure you want to clear the cache? This will temporarily slow down API responses.', 'minpaku-suite'),
                'keyGenerated' => __('API key generated successfully. Please copy it now as it will not be shown again.', 'minpaku-suite'),
                'keyRevoked' => __('API key revoked successfully.', 'minpaku-suite'),
                'cacheCleared' => __('Cache cleared successfully.', 'minpaku-suite'),
                'error' => __('An error occurred. Please try again.', 'minpaku-suite')
            ]
        ]);

        wp_enqueue_style(
            'minpaku-api-settings',
            plugin_dir_url(__FILE__) . '../../assets/css/api-settings.css',
            [],
            '1.0.0'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $active_tab = $_GET['tab'] ?? 'settings';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('API Settings', 'minpaku-suite'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?post_type=property&page=<?php echo self::PAGE_SLUG; ?>&tab=settings"
                   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Settings', 'minpaku-suite'); ?>
                </a>
                <a href="?post_type=property&page=<?php echo self::PAGE_SLUG; ?>&tab=api-keys"
                   class="nav-tab <?php echo $active_tab === 'api-keys' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('API Keys', 'minpaku-suite'); ?>
                </a>
                <a href="?post_type=property&page=<?php echo self::PAGE_SLUG; ?>&tab=cache"
                   class="nav-tab <?php echo $active_tab === 'cache' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Cache Management', 'minpaku-suite'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'api-keys':
                        $this->render_api_keys_tab();
                        break;
                    case 'cache':
                        $this->render_cache_tab();
                        break;
                    default:
                        $this->render_settings_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields(self::OPTION_KEY);
            do_settings_sections(self::PAGE_SLUG);
            submit_button();
            ?>
        </form>
        <?php
    }

    /**
     * Render API keys tab
     */
    private function render_api_keys_tab() {
        $api_keys = $this->api_key_manager->getAllKeys(true);
        $usage_stats = $this->api_key_manager->getUsageStats();
        ?>
        <div class="api-keys-section">
            <div class="api-key-generator">
                <h2><?php esc_html_e('Generate New API Key', 'minpaku-suite'); ?></h2>
                <div class="form-table">
                    <div class="form-row">
                        <label for="api-key-name"><?php esc_html_e('Key Name', 'minpaku-suite'); ?></label>
                        <input type="text" id="api-key-name" placeholder="<?php esc_attr_e('e.g., Mobile App, Partner Integration', 'minpaku-suite'); ?>">
                    </div>
                    <div class="form-row">
                        <label for="api-key-permissions"><?php esc_html_e('Permissions', 'minpaku-suite'); ?></label>
                        <div class="permissions-checkboxes">
                            <label><input type="checkbox" value="read:availability"> <?php esc_html_e('Read Availability', 'minpaku-suite'); ?></label>
                            <label><input type="checkbox" value="read:quote"> <?php esc_html_e('Read Quotes', 'minpaku-suite'); ?></label>
                            <label><input type="checkbox" value="read:webhooks"> <?php esc_html_e('Read Webhooks', 'minpaku-suite'); ?></label>
                            <label><input type="checkbox" value="write:webhooks"> <?php esc_html_e('Write Webhooks', 'minpaku-suite'); ?></label>
                        </div>
                    </div>
                    <div class="form-row">
                        <button type="button" id="generate-api-key" class="button button-primary">
                            <?php esc_html_e('Generate API Key', 'minpaku-suite'); ?>
                        </button>
                    </div>
                </div>

                <div id="new-api-key-display" style="display: none;" class="notice notice-success">
                    <p><strong><?php esc_html_e('New API Key Generated:', 'minpaku-suite'); ?></strong></p>
                    <code id="new-api-key-value"></code>
                    <p class="description"><?php esc_html_e('Please copy this key now. You will not be able to see it again for security reasons.', 'minpaku-suite'); ?></p>
                </div>
            </div>

            <div class="api-keys-list">
                <h2><?php esc_html_e('Existing API Keys', 'minpaku-suite'); ?></h2>

                <div class="usage-stats">
                    <h3><?php esc_html_e('Usage Statistics', 'minpaku-suite'); ?></h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $usage_stats['total_keys']; ?></span>
                            <span class="stat-label"><?php esc_html_e('Total Keys', 'minpaku-suite'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $usage_stats['active_keys']; ?></span>
                            <span class="stat-label"><?php esc_html_e('Active Keys', 'minpaku-suite'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $usage_stats['recent_usage']; ?></span>
                            <span class="stat-label"><?php esc_html_e('Used (30 days)', 'minpaku-suite'); ?></span>
                        </div>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'minpaku-suite'); ?></th>
                            <th><?php esc_html_e('Key', 'minpaku-suite'); ?></th>
                            <th><?php esc_html_e('Permissions', 'minpaku-suite'); ?></th>
                            <th><?php esc_html_e('Usage', 'minpaku-suite'); ?></th>
                            <th><?php esc_html_e('Status', 'minpaku-suite'); ?></th>
                            <th><?php esc_html_e('Created', 'minpaku-suite'); ?></th>
                            <th><?php esc_html_e('Actions', 'minpaku-suite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($api_keys)): ?>
                            <tr>
                                <td colspan="7"><?php esc_html_e('No API keys found.', 'minpaku-suite'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($api_keys as $key_data): ?>
                                <tr data-key-id="<?php echo esc_attr($key_data['key_id']); ?>">
                                    <td><strong><?php echo esc_html($key_data['name']); ?></strong></td>
                                    <td><code><?php echo esc_html($key_data['key']); ?></code></td>
                                    <td>
                                        <?php if (empty($key_data['permissions'])): ?>
                                            <span class="permission-tag all"><?php esc_html_e('All', 'minpaku-suite'); ?></span>
                                        <?php else: ?>
                                            <?php foreach ($key_data['permissions'] as $permission): ?>
                                                <span class="permission-tag"><?php echo esc_html($permission); ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo number_format($key_data['usage_count']); ?>
                                        <?php if ($key_data['last_used_at']): ?>
                                            <br><small><?php echo esc_html(human_time_diff($key_data['last_used_at'])) . ' ' . __('ago', 'minpaku-suite'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($key_data['is_active']): ?>
                                            <span class="status-badge active"><?php esc_html_e('Active', 'minpaku-suite'); ?></span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><?php esc_html_e('Revoked', 'minpaku-suite'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($key_data['expires_at'] && time() > $key_data['expires_at']): ?>
                                            <span class="status-badge expired"><?php esc_html_e('Expired', 'minpaku-suite'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), $key_data['created_at'])); ?></td>
                                    <td>
                                        <?php if ($key_data['is_active']): ?>
                                            <button type="button" class="button button-small revoke-key"
                                                    data-key-id="<?php echo esc_attr($key_data['key_id']); ?>">
                                                <?php esc_html_e('Revoke', 'minpaku-suite'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render cache management tab
     */
    private function render_cache_tab() {
        $cache_stats = $this->cache->getStats();
        ?>
        <div class="cache-management-section">
            <h2><?php esc_html_e('Cache Statistics', 'minpaku-suite'); ?></h2>

            <div class="cache-stats">
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo number_format($cache_stats['total_entries']); ?></span>
                        <span class="stat-label"><?php esc_html_e('Total Entries', 'minpaku-suite'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo size_format($cache_stats['total_size'], 2); ?></span>
                        <span class="stat-label"><?php esc_html_e('Total Size', 'minpaku-suite'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">
                            <?php echo $cache_stats['oldest_entry'] ? human_time_diff($cache_stats['oldest_entry']) : 'N/A'; ?>
                        </span>
                        <span class="stat-label"><?php esc_html_e('Oldest Entry', 'minpaku-suite'); ?></span>
                    </div>
                </div>

                <?php if (!empty($cache_stats['by_type'])): ?>
                    <h3><?php esc_html_e('By Type', 'minpaku-suite'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Type', 'minpaku-suite'); ?></th>
                                <th><?php esc_html_e('Entries', 'minpaku-suite'); ?></th>
                                <th><?php esc_html_e('Size', 'minpaku-suite'); ?></th>
                                <th><?php esc_html_e('Actions', 'minpaku-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cache_stats['by_type'] as $type => $stats): ?>
                                <tr>
                                    <td><strong><?php echo esc_html(ucfirst($type)); ?></strong></td>
                                    <td><?php echo number_format($stats['count']); ?></td>
                                    <td><?php echo size_format($stats['size'], 2); ?></td>
                                    <td>
                                        <button type="button" class="button button-small clear-cache-type"
                                                data-type="<?php echo esc_attr($type); ?>">
                                            <?php esc_html_e('Clear', 'minpaku-suite'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="cache-actions">
                <h3><?php esc_html_e('Cache Actions', 'minpaku-suite'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Use these actions to manage the API response cache. Clearing cache will temporarily slow down API responses until the cache is rebuilt.', 'minpaku-suite'); ?>
                </p>

                <div class="cache-action-buttons">
                    <button type="button" id="clear-all-cache" class="button button-secondary">
                        <?php esc_html_e('Clear All Cache', 'minpaku-suite'); ?>
                    </button>
                    <button type="button" id="clear-availability-cache" class="button button-secondary" data-type="availability">
                        <?php esc_html_e('Clear Availability Cache', 'minpaku-suite'); ?>
                    </button>
                    <button type="button" id="clear-quote-cache" class="button button-secondary" data-type="quote">
                        <?php esc_html_e('Clear Quote Cache', 'minpaku-suite'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render rate limits section description
     */
    public function render_rate_limits_section() {
        echo '<p>' . esc_html__('Configure rate limits for different API endpoints. Rate limits help prevent abuse and ensure fair usage.', 'minpaku-suite') . '</p>';
    }

    /**
     * Render cache section description
     */
    public function render_cache_section() {
        echo '<p>' . esc_html__('Configure cache TTL (Time To Live) for different API responses. Shorter TTL means more up-to-date data but higher server load.', 'minpaku-suite') . '</p>';
    }

    /**
     * Render rate limit field
     *
     * @param array $args Field arguments
     */
    public function render_rate_limit_field($args) {
        $bucket = $args['bucket'];
        $settings = get_option(self::OPTION_KEY, []);
        $rate_limits = $settings['rate_limits'][$bucket] ?? [];

        $limit = $rate_limits['limit'] ?? RateLimiter::DEFAULT_LIMITS[$bucket]['limit'];
        $window = $rate_limits['window'] ?? RateLimiter::DEFAULT_LIMITS[$bucket]['window'];
        ?>
        <div class="rate-limit-field">
            <label for="<?php echo esc_attr($bucket); ?>_limit"><?php esc_html_e('Requests', 'minpaku-suite'); ?></label>
            <input type="number"
                   id="<?php echo esc_attr($bucket); ?>_limit"
                   name="<?php echo self::OPTION_KEY; ?>[rate_limits][<?php echo esc_attr($bucket); ?>][limit]"
                   value="<?php echo esc_attr($limit); ?>"
                   min="1" max="1000" style="width: 80px;">

            <label for="<?php echo esc_attr($bucket); ?>_window"><?php esc_html_e('per', 'minpaku-suite'); ?></label>
            <select id="<?php echo esc_attr($bucket); ?>_window"
                    name="<?php echo self::OPTION_KEY; ?>[rate_limits][<?php echo esc_attr($bucket); ?>][window]">
                <option value="60" <?php selected($window, 60); ?>><?php esc_html_e('Minute', 'minpaku-suite'); ?></option>
                <option value="300" <?php selected($window, 300); ?>><?php esc_html_e('5 Minutes', 'minpaku-suite'); ?></option>
                <option value="900" <?php selected($window, 900); ?>><?php esc_html_e('15 Minutes', 'minpaku-suite'); ?></option>
                <option value="3600" <?php selected($window, 3600); ?>><?php esc_html_e('Hour', 'minpaku-suite'); ?></option>
            </select>
        </div>
        <?php
    }

    /**
     * Render cache TTL field
     *
     * @param array $args Field arguments
     */
    public function render_cache_ttl_field($args) {
        $type = $args['type'];
        $settings = get_option(self::OPTION_KEY, []);
        $ttl = $settings['cache_ttl'][$type] ?? ResponseCache::DEFAULT_TTL[$type];
        ?>
        <input type="number"
               name="<?php echo self::OPTION_KEY; ?>[cache_ttl][<?php echo esc_attr($type); ?>]"
               value="<?php echo esc_attr($ttl); ?>"
               min="10" max="3600" style="width: 80px;">
        <span class="description"><?php esc_html_e('seconds', 'minpaku-suite'); ?></span>
        <?php
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input settings
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];

        // Sanitize rate limits
        if (isset($input['rate_limits'])) {
            foreach ($input['rate_limits'] as $bucket => $settings) {
                $sanitized['rate_limits'][$bucket] = [
                    'limit' => max(1, min(1000, (int) $settings['limit'])),
                    'window' => in_array($settings['window'], [60, 300, 900, 3600]) ? (int) $settings['window'] : 60
                ];
            }
        }

        // Sanitize cache TTL
        if (isset($input['cache_ttl'])) {
            foreach ($input['cache_ttl'] as $type => $ttl) {
                $sanitized['cache_ttl'][$type] = max(10, min(3600, (int) $ttl));
            }
        }

        return $sanitized;
    }

    /**
     * AJAX handler for generating API key
     */
    public function ajax_generate_api_key() {
        check_ajax_referer('minpaku_api_settings', 'nonce');

        if (!current_user_can('manage_minpaku')) {
            wp_die(__('Insufficient permissions', 'minpaku-suite'));
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $permissions = array_map('sanitize_text_field', $_POST['permissions'] ?? []);

        if (empty($name)) {
            wp_send_json_error(['message' => __('Key name is required', 'minpaku-suite')]);
        }

        try {
            $key_data = $this->api_key_manager->generateKey($name, $permissions);
            wp_send_json_success([
                'key' => $key_data['key'],
                'name' => $key_data['name'],
                'permissions' => $key_data['permissions']
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX handler for revoking API key
     */
    public function ajax_revoke_api_key() {
        check_ajax_referer('minpaku_api_settings', 'nonce');

        if (!current_user_can('manage_minpaku')) {
            wp_die(__('Insufficient permissions', 'minpaku-suite'));
        }

        $key_id = sanitize_text_field($_POST['key_id'] ?? '');

        if (empty($key_id)) {
            wp_send_json_error(['message' => __('Key ID is required', 'minpaku-suite')]);
        }

        $key_data = $this->api_key_manager->getKeyById($key_id);
        if (!$key_data) {
            wp_send_json_error(['message' => __('API key not found', 'minpaku-suite')]);
        }

        // Find the actual key from the key_id
        $all_keys = get_option(ApiKeyManager::OPTION_KEY, []);
        $actual_key = null;
        foreach ($all_keys as $key => $data) {
            if (md5($key) === $key_id) {
                $actual_key = $key;
                break;
            }
        }

        if (!$actual_key) {
            wp_send_json_error(['message' => __('API key not found', 'minpaku-suite')]);
        }

        try {
            $success = $this->api_key_manager->revokeKey($actual_key);
            if ($success) {
                wp_send_json_success(['message' => __('API key revoked successfully', 'minpaku-suite')]);
            } else {
                wp_send_json_error(['message' => __('Failed to revoke API key', 'minpaku-suite')]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('minpaku_api_settings', 'nonce');

        if (!current_user_can('manage_minpaku')) {
            wp_die(__('Insufficient permissions', 'minpaku-suite'));
        }

        $type = sanitize_text_field($_POST['type'] ?? '');

        try {
            $cleared_count = $this->cache->clear($type ?: null);
            wp_send_json_success([
                'message' => sprintf(
                    __('Cleared %d cache entries', 'minpaku-suite'),
                    $cleared_count
                ),
                'cleared_count' => $cleared_count
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}