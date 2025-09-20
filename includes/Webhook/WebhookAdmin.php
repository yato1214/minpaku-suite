<?php
/**
 * Webhook Admin
 * Handles admin interface for webhook settings, testing, and delivery logs
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/WebhookDispatcher.php';
require_once __DIR__ . '/WebhookQueue.php';
require_once __DIR__ . '/WebhookWorker.php';

class WebhookAdmin {

    /**
     * Settings page slug
     */
    const PAGE_SLUG = 'minpaku-webhooks';

    /**
     * Settings option name
     */
    const SETTINGS_OPTION = 'minpaku_webhook_settings';

    /**
     * Webhook dispatcher
     */
    private $dispatcher;

    /**
     * Webhook queue
     */
    private $queue;

    /**
     * Webhook worker
     */
    private $worker;

    /**
     * Constructor
     */
    public function __construct() {
        $this->dispatcher = new WebhookDispatcher();
        $this->queue = new WebhookQueue();
        $this->worker = new WebhookWorker();

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_webhook_test_endpoint', [$this, 'handle_test_endpoint']);
        add_action('admin_post_webhook_settings', [$this, 'handle_settings_save']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=property',
            __('Webhooks', 'minpaku-suite'),
            __('Webhooks', 'minpaku-suite'),
            'manage_minpaku',
            self::PAGE_SLUG,
            [$this, 'render_admin_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            self::SETTINGS_OPTION . '_group',
            self::SETTINGS_OPTION,
            [$this, 'sanitize_settings']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'minpaku-webhook-admin',
            '',
            ['jquery'],
            '1.0.0',
            true
        );

        // Inline JavaScript for webhook admin
        $js = $this->getAdminJavaScript();
        wp_add_inline_script('minpaku-webhook-admin', $js);

        wp_localize_script('minpaku-webhook-admin', 'webhookAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('webhook_admin'),
            'strings' => [
                'testing' => __('Testing...', 'minpaku-suite'),
                'testSuccess' => __('Test successful!', 'minpaku-suite'),
                'testFailed' => __('Test failed:', 'minpaku-suite'),
                'confirmDelete' => __('Are you sure you want to delete this endpoint?', 'minpaku-suite'),
                'processing' => __('Processing...', 'minpaku-suite')
            ]
        ]);

        // Add admin styles
        wp_add_inline_style('wp-admin', $this->getAdminStyles());
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $current_tab = $_GET['tab'] ?? 'settings';
        $settings = $this->get_settings();

        ?>
        <div class="wrap">
            <h1><?php _e('Webhook Management', 'minpaku-suite'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?post_type=property&page=<?php echo self::PAGE_SLUG; ?>&tab=settings"
                   class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'minpaku-suite'); ?>
                </a>
                <a href="?post_type=property&page=<?php echo self::PAGE_SLUG; ?>&tab=deliveries"
                   class="nav-tab <?php echo $current_tab === 'deliveries' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Delivery Log', 'minpaku-suite'); ?>
                </a>
                <a href="?post_type=property&page=<?php echo self::PAGE_SLUG; ?>&tab=status"
                   class="nav-tab <?php echo $current_tab === 'status' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Status', 'minpaku-suite'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'deliveries':
                        $this->render_deliveries_tab();
                        break;
                    case 'status':
                        $this->render_status_tab();
                        break;
                    default:
                        $this->render_settings_tab($settings);
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
    private function render_settings_tab($settings) {
        ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="webhook_settings">
            <?php wp_nonce_field('webhook_settings', 'webhook_settings_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Processing Settings', 'minpaku-suite'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <?php _e('Batch Size:', 'minpaku-suite'); ?>
                                <input type="number" name="batch_size" value="<?php echo esc_attr($settings['batch_size'] ?? 3); ?>"
                                       min="1" max="20" class="small-text">
                                <p class="description"><?php _e('Number of webhooks to process simultaneously', 'minpaku-suite'); ?></p>
                            </label>
                        </fieldset>
                        <fieldset>
                            <label>
                                <?php _e('Processing Interval:', 'minpaku-suite'); ?>
                                <select name="processing_interval">
                                    <option value="30" <?php selected($settings['processing_interval'] ?? 60, 30); ?>>30 <?php _e('seconds', 'minpaku-suite'); ?></option>
                                    <option value="60" <?php selected($settings['processing_interval'] ?? 60, 60); ?>>1 <?php _e('minute', 'minpaku-suite'); ?></option>
                                    <option value="120" <?php selected($settings['processing_interval'] ?? 60, 120); ?>>2 <?php _e('minutes', 'minpaku-suite'); ?></option>
                                    <option value="300" <?php selected($settings['processing_interval'] ?? 60, 300); ?>>5 <?php _e('minutes', 'minpaku-suite'); ?></option>
                                </select>
                                <p class="description"><?php _e('How often to process webhook queue', 'minpaku-suite'); ?></p>
                            </label>
                        </fieldset>
                        <fieldset>
                            <label>
                                <?php _e('Retention Period:', 'minpaku-suite'); ?>
                                <input type="number" name="retention_days" value="<?php echo esc_attr($settings['retention_days'] ?? 30); ?>"
                                       min="1" max="365" class="small-text"> <?php _e('days', 'minpaku-suite'); ?>
                                <p class="description"><?php _e('How long to keep delivery logs', 'minpaku-suite'); ?></p>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <h3><?php _e('Webhook Endpoints', 'minpaku-suite'); ?></h3>

            <div id="webhook-endpoints">
                <?php
                $endpoints = $settings['endpoints'] ?? [];
                if (empty($endpoints)) {
                    $endpoints = [['url' => '', 'secret' => '', 'enabled' => true, 'events' => []]];
                }

                foreach ($endpoints as $index => $endpoint) {
                    $this->render_endpoint_row($index, $endpoint);
                }
                ?>
            </div>

            <p>
                <button type="button" class="button" id="add-endpoint"><?php _e('Add Endpoint', 'minpaku-suite'); ?></button>
            </p>

            <?php submit_button(__('Save Settings', 'minpaku-suite')); ?>
        </form>

        <!-- Endpoint template for JavaScript -->
        <script type="text/template" id="endpoint-template">
            <?php $this->render_endpoint_row('{{INDEX}}', ['url' => '', 'secret' => '', 'enabled' => true, 'events' => []]); ?>
        </script>
        <?php
    }

    /**
     * Render single endpoint row
     */
    private function render_endpoint_row($index, $endpoint) {
        $is_template = $index === '{{INDEX}}';
        ?>
        <div class="endpoint-row" data-index="<?php echo esc_attr($index); ?>">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Endpoint URL', 'minpaku-suite'); ?></th>
                    <td>
                        <input type="url" name="endpoints[<?php echo esc_attr($index); ?>][url]"
                               value="<?php echo esc_attr($endpoint['url'] ?? ''); ?>"
                               class="regular-text" required>
                        <button type="button" class="button test-endpoint" <?php echo $is_template ? 'style="display:none"' : ''; ?>>
                            <?php _e('Test', 'minpaku-suite'); ?>
                        </button>
                        <div class="test-result" style="display: none;"></div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Secret Key', 'minpaku-suite'); ?></th>
                    <td>
                        <input type="password" name="endpoints[<?php echo esc_attr($index); ?>][secret]"
                               value="<?php echo esc_attr($endpoint['secret'] ?? ''); ?>"
                               class="regular-text" autocomplete="off">
                        <button type="button" class="button toggle-secret"><?php _e('Show', 'minpaku-suite'); ?></button>
                        <p class="description"><?php _e('Used for HMAC-SHA256 signature verification', 'minpaku-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Events', 'minpaku-suite'); ?></th>
                    <td>
                        <fieldset>
                            <?php
                            $events = WebhookDispatcher::getSupportedEvents();
                            $labels = WebhookDispatcher::getEventLabels();
                            $selected_events = $endpoint['events'] ?? [];

                            foreach ($events as $event) {
                                $checked = empty($selected_events) || in_array($event, $selected_events);
                                ?>
                                <label>
                                    <input type="checkbox" name="endpoints[<?php echo esc_attr($index); ?>][events][]"
                                           value="<?php echo esc_attr($event); ?>" <?php checked($checked); ?>>
                                    <?php echo esc_html($labels[$event] ?? $event); ?>
                                </label><br>
                                <?php
                            }
                            ?>
                            <p class="description"><?php _e('Select events to send to this endpoint (leave all unchecked to send all events)', 'minpaku-suite'); ?></p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Status', 'minpaku-suite'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="endpoints[<?php echo esc_attr($index); ?>][enabled]"
                                   value="1" <?php checked($endpoint['enabled'] ?? true); ?>>
                            <?php _e('Enabled', 'minpaku-suite'); ?>
                        </label>
                        <?php if (!$is_template): ?>
                        <button type="button" class="button button-secondary delete-endpoint" style="margin-left: 20px;">
                            <?php _e('Delete', 'minpaku-suite'); ?>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <hr>
        </div>
        <?php
    }

    /**
     * Render deliveries tab
     */
    private function render_deliveries_tab() {
        $per_page = 20;
        $page = max(1, intval($_GET['paged'] ?? 1));
        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        $event_filter = sanitize_text_field($_GET['event'] ?? '');

        $filters = array_filter([
            'status' => $status_filter,
            'event' => $event_filter,
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page
        ]);

        $deliveries = $this->queue->getDeliveries($filters);
        $total_count = $this->queue->countDeliveries(array_filter([
            'status' => $status_filter,
            'event' => $event_filter
        ]));

        ?>
        <div class="delivery-filters">
            <form method="get">
                <input type="hidden" name="post_type" value="property">
                <input type="hidden" name="page" value="<?php echo self::PAGE_SLUG; ?>">
                <input type="hidden" name="tab" value="deliveries">

                <select name="status">
                    <option value=""><?php _e('All Statuses', 'minpaku-suite'); ?></option>
                    <option value="queued" <?php selected($status_filter, 'queued'); ?>><?php _e('Queued', 'minpaku-suite'); ?></option>
                    <option value="success" <?php selected($status_filter, 'success'); ?>><?php _e('Success', 'minpaku-suite'); ?></option>
                    <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php _e('Failed', 'minpaku-suite'); ?></option>
                </select>

                <select name="event">
                    <option value=""><?php _e('All Events', 'minpaku-suite'); ?></option>
                    <?php
                    $events = WebhookDispatcher::getSupportedEvents();
                    $labels = WebhookDispatcher::getEventLabels();
                    foreach ($events as $event) {
                        echo '<option value="' . esc_attr($event) . '" ' . selected($event_filter, $event, false) . '>';
                        echo esc_html($labels[$event] ?? $event);
                        echo '</option>';
                    }
                    ?>
                </select>

                <?php submit_button(__('Filter', 'minpaku-suite'), 'secondary', 'filter', false); ?>
            </form>
        </div>

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="webhook_process_queue">
                    <?php wp_nonce_field('webhook_process_queue', 'nonce'); ?>
                    <?php submit_button(__('Process Queue Now', 'minpaku-suite'), 'secondary', 'process_queue', false); ?>
                </form>
            </div>
            <div class="tablenav-pages">
                <?php
                $pagination_args = [
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => ceil($total_count / $per_page),
                    'current' => $page
                ];
                echo paginate_links($pagination_args);
                ?>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Event', 'minpaku-suite'); ?></th>
                    <th><?php _e('URL', 'minpaku-suite'); ?></th>
                    <th><?php _e('Status', 'minpaku-suite'); ?></th>
                    <th><?php _e('Attempt', 'minpaku-suite'); ?></th>
                    <th><?php _e('Created', 'minpaku-suite'); ?></th>
                    <th><?php _e('Actions', 'minpaku-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deliveries)): ?>
                <tr>
                    <td colspan="6"><?php _e('No deliveries found.', 'minpaku-suite'); ?></td>
                </tr>
                <?php else: ?>
                <?php foreach ($deliveries as $delivery): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($delivery['event']); ?></strong>
                    </td>
                    <td>
                        <code><?php echo esc_html($this->truncate_url($delivery['url'])); ?></code>
                    </td>
                    <td>
                        <span class="delivery-status delivery-status-<?php echo esc_attr($delivery['status']); ?>">
                            <?php echo esc_html(ucfirst($delivery['status'])); ?>
                        </span>
                        <?php if ($delivery['last_error']): ?>
                            <div class="error-message" title="<?php echo esc_attr($delivery['last_error']); ?>">
                                <?php echo esc_html($this->truncate_text($delivery['last_error'], 50)); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html($delivery['attempt']); ?>/<?php echo WebhookQueue::getMaxAttempts(); ?>
                        <?php if ($delivery['next_retry_at']): ?>
                            <br><small><?php printf(__('Next: %s', 'minpaku-suite'), esc_html($delivery['next_retry_at'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $delivery['created_at'])); ?>
                    </td>
                    <td>
                        <?php if ($delivery['can_retry']): ?>
                            <a href="<?php echo wp_nonce_url(
                                add_query_arg([
                                    'action' => 'retry_delivery',
                                    'delivery_key' => $delivery['delivery_key']
                                ], admin_url('admin-post.php')),
                                'retry_delivery_' . $delivery['delivery_key']
                            ); ?>" class="button button-small">
                                <?php _e('Retry', 'minpaku-suite'); ?>
                            </a>
                        <?php endif; ?>
                        <button type="button" class="button button-small view-details"
                                data-delivery-key="<?php echo esc_attr($delivery['delivery_key']); ?>">
                            <?php _e('Details', 'minpaku-suite'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render status tab
     */
    private function render_status_tab() {
        $worker_status = $this->worker->getStatus();
        $queue_stats = $this->queue->getStats();

        ?>
        <h3><?php _e('Worker Status', 'minpaku-suite'); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php _e('Scheduled', 'minpaku-suite'); ?></th>
                <td>
                    <?php if ($worker_status['is_scheduled']): ?>
                        <span class="status-good">✓ <?php _e('Yes', 'minpaku-suite'); ?></span>
                        <br><small><?php printf(__('Next run: %s', 'minpaku-suite'), esc_html($worker_status['next_run_formatted'])); ?></small>
                    <?php else: ?>
                        <span class="status-bad">✗ <?php _e('No', 'minpaku-suite'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Currently Running', 'minpaku-suite'); ?></th>
                <td>
                    <?php if ($worker_status['is_running']): ?>
                        <span class="status-warning">⚠ <?php _e('Yes', 'minpaku-suite'); ?></span>
                        <br><small><?php printf(__('Since: %s', 'minpaku-suite'), esc_html($worker_status['lock_time'])); ?></small>
                    <?php else: ?>
                        <span class="status-good">✓ <?php _e('No', 'minpaku-suite'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Processing Interval', 'minpaku-suite'); ?></th>
                <td><?php echo esc_html($worker_status['processing_interval']); ?> <?php _e('seconds', 'minpaku-suite'); ?></td>
            </tr>
            <tr>
                <th><?php _e('Batch Size', 'minpaku-suite'); ?></th>
                <td><?php echo esc_html($worker_status['batch_size']); ?></td>
            </tr>
        </table>

        <h3><?php _e('Queue Statistics', 'minpaku-suite'); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php _e('Queued', 'minpaku-suite'); ?></th>
                <td><?php echo intval($queue_stats['by_status']['queued'] ?? 0); ?></td>
            </tr>
            <tr>
                <th><?php _e('Successful', 'minpaku-suite'); ?></th>
                <td><?php echo intval($queue_stats['by_status']['success'] ?? 0); ?></td>
            </tr>
            <tr>
                <th><?php _e('Failed', 'minpaku-suite'); ?></th>
                <td><?php echo intval($queue_stats['by_status']['failed'] ?? 0); ?></td>
            </tr>
            <tr>
                <th><?php _e('Recent 24h', 'minpaku-suite'); ?></th>
                <td><?php echo intval($queue_stats['recent_24h'] ?? 0); ?></td>
            </tr>
            <tr>
                <th><?php _e('Success Rate', 'minpaku-suite'); ?></th>
                <td><?php echo esc_html($queue_stats['success_rate'] ?? 0); ?>%</td>
            </tr>
        </table>

        <h3><?php _e('Actions', 'minpaku-suite'); ?></h3>
        <p>
            <a href="<?php echo wp_nonce_url(
                add_query_arg(['action' => 'webhook_force_processing'], admin_url('admin-post.php')),
                'webhook_force_processing'
            ); ?>" class="button">
                <?php _e('Force Process Queue', 'minpaku-suite'); ?>
            </a>
            <a href="<?php echo wp_nonce_url(
                add_query_arg(['action' => 'webhook_cleanup'], admin_url('admin-post.php')),
                'webhook_cleanup'
            ); ?>" class="button">
                <?php _e('Cleanup Old Deliveries', 'minpaku-suite'); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Handle test endpoint AJAX request
     */
    public function handle_test_endpoint() {
        if (!current_user_can('manage_minpaku')) {
            wp_send_json_error(__('Insufficient permissions', 'minpaku-suite'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'webhook_admin')) {
            wp_send_json_error(__('Security check failed', 'minpaku-suite'));
        }

        $url = esc_url_raw($_POST['url'] ?? '');
        $secret = sanitize_text_field($_POST['secret'] ?? '');

        if (empty($url) || empty($secret)) {
            wp_send_json_error(__('URL and secret are required', 'minpaku-suite'));
        }

        $result = $this->dispatcher->testEndpoint($url, $secret);

        if ($result['success']) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Test successful! Response: %d in %dms', 'minpaku-suite'),
                    $result['response_code'],
                    $result['response_time']
                )
            ]);
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * Handle settings save
     */
    public function handle_settings_save() {
        if (!current_user_can('manage_minpaku')) {
            wp_die(__('Insufficient permissions', 'minpaku-suite'));
        }

        if (!wp_verify_nonce($_POST['webhook_settings_nonce'] ?? '', 'webhook_settings')) {
            wp_die(__('Security check failed', 'minpaku-suite'));
        }

        $settings = $this->sanitize_settings($_POST);
        update_option(self::SETTINGS_OPTION, $settings);

        // Reschedule worker if interval changed
        $old_settings = $this->get_settings();
        if (($old_settings['processing_interval'] ?? 60) !== $settings['processing_interval']) {
            $this->worker->reschedule($settings['processing_interval']);
        }

        wp_redirect(add_query_arg([
            'post_type' => 'property',
            'page' => self::PAGE_SLUG,
            'settings-updated' => 'true'
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        if (isset($_GET['settings-updated']) && $_GET['page'] === self::PAGE_SLUG) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Webhook settings saved successfully.', 'minpaku-suite'); ?></p>
            </div>
            <?php
        }

        if (isset($_GET['processed'])) {
            $processed = intval($_GET['processed']);
            $succeeded = intval($_GET['succeeded']);
            $failed = intval($_GET['failed']);

            ?>
            <div class="notice notice-info is-dismissible">
                <p><?php printf(
                    __('Processed %d webhook deliveries: %d succeeded, %d failed.', 'minpaku-suite'),
                    $processed,
                    $succeeded,
                    $failed
                ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $settings = [];

        $settings['batch_size'] = max(1, min(20, intval($input['batch_size'] ?? 3)));
        $settings['processing_interval'] = max(30, intval($input['processing_interval'] ?? 60));
        $settings['retention_days'] = max(1, min(365, intval($input['retention_days'] ?? 30)));

        $settings['endpoints'] = [];
        if (!empty($input['endpoints']) && is_array($input['endpoints'])) {
            foreach ($input['endpoints'] as $endpoint) {
                if (!empty($endpoint['url'])) {
                    $settings['endpoints'][] = [
                        'url' => esc_url_raw($endpoint['url']),
                        'secret' => sanitize_text_field($endpoint['secret'] ?? ''),
                        'enabled' => !empty($endpoint['enabled']),
                        'events' => array_map('sanitize_text_field', $endpoint['events'] ?? [])
                    ];
                }
            }
        }

        return $settings;
    }

    /**
     * Get settings with defaults
     */
    private function get_settings() {
        $defaults = [
            'batch_size' => 3,
            'processing_interval' => 60,
            'retention_days' => 30,
            'endpoints' => []
        ];

        return array_merge($defaults, get_option(self::SETTINGS_OPTION, []));
    }

    /**
     * Truncate URL for display
     */
    private function truncate_url($url, $length = 50) {
        if (strlen($url) <= $length) {
            return $url;
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';

        if (strlen($host . $path) <= $length) {
            return $host . $path;
        }

        return $host . '...' . substr($path, -($length - strlen($host) - 3));
    }

    /**
     * Truncate text for display
     */
    private function truncate_text($text, $length = 100) {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }

    /**
     * Get admin JavaScript
     */
    private function getAdminJavaScript() {
        return '
        jQuery(document).ready(function($) {
            var endpointIndex = $(".endpoint-row").length;

            $("#add-endpoint").click(function() {
                var template = $("#endpoint-template").html();
                var html = template.replace(/\{\{INDEX\}\}/g, endpointIndex);
                $("#webhook-endpoints").append(html);
                endpointIndex++;
            });

            $(document).on("click", ".delete-endpoint", function() {
                if (confirm(webhookAdmin.strings.confirmDelete)) {
                    $(this).closest(".endpoint-row").remove();
                }
            });

            $(document).on("click", ".toggle-secret", function() {
                var input = $(this).prev("input");
                var type = input.attr("type");
                if (type === "password") {
                    input.attr("type", "text");
                    $(this).text("Hide");
                } else {
                    input.attr("type", "password");
                    $(this).text("Show");
                }
            });

            $(document).on("click", ".test-endpoint", function() {
                var row = $(this).closest(".endpoint-row");
                var url = row.find("input[name*=\"[url]\"]").val();
                var secret = row.find("input[name*=\"[secret]\"]").val();
                var resultDiv = row.find(".test-result");
                var button = $(this);

                if (!url || !secret) {
                    alert("URL and secret are required for testing");
                    return;
                }

                button.prop("disabled", true).text(webhookAdmin.strings.testing);
                resultDiv.hide();

                $.post(webhookAdmin.ajaxUrl, {
                    action: "webhook_test_endpoint",
                    nonce: webhookAdmin.nonce,
                    url: url,
                    secret: secret
                }).done(function(response) {
                    if (response.success) {
                        resultDiv.removeClass("error").addClass("success")
                               .text(response.data.message).show();
                    } else {
                        resultDiv.removeClass("success").addClass("error")
                               .text(webhookAdmin.strings.testFailed + " " + response.data).show();
                    }
                }).fail(function() {
                    resultDiv.removeClass("success").addClass("error")
                           .text("Test request failed").show();
                }).always(function() {
                    button.prop("disabled", false).text("Test");
                });
            });
        });
        ';
    }

    /**
     * Get admin CSS styles
     */
    private function getAdminStyles() {
        return '
        .endpoint-row {
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 20px;
            background: #f9f9f9;
        }

        .test-result {
            margin-top: 5px;
            padding: 5px 10px;
            border-radius: 3px;
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

        .delivery-status {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .delivery-status-queued { background: #fff3cd; color: #856404; }
        .delivery-status-success { background: #d4edda; color: #155724; }
        .delivery-status-failed { background: #f8d7da; color: #721c24; }

        .error-message {
            font-size: 11px;
            color: #721c24;
            margin-top: 2px;
        }

        .status-good { color: #155724; }
        .status-bad { color: #721c24; }
        .status-warning { color: #856404; }

        .delivery-filters {
            margin: 10px 0;
            padding: 10px;
            background: #f0f0f1;
            border-radius: 3px;
        }

        .delivery-filters select {
            margin-right: 10px;
        }
        ';
    }
}