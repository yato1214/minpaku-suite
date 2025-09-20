<?php
/**
 * Webhook Worker
 * Handles background processing of webhook deliveries via WP-Cron
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/WebhookDispatcher.php';

class WebhookWorker {

    /**
     * Cron hook name
     */
    const CRON_HOOK = 'minpaku_webhook_worker';

    /**
     * Cleanup cron hook name
     */
    const CLEANUP_CRON_HOOK = 'minpaku_webhook_cleanup';

    /**
     * Default processing interval (1 minute)
     */
    const DEFAULT_INTERVAL = 60;

    /**
     * Cleanup interval (daily)
     */
    const CLEANUP_INTERVAL = 86400; // 24 hours

    /**
     * Webhook dispatcher instance
     */
    private $dispatcher;

    /**
     * Constructor
     */
    public function __construct() {
        $this->dispatcher = new WebhookDispatcher();
        $this->init();
    }

    /**
     * Initialize worker hooks and schedules
     */
    public function init() {
        // Register cron hooks
        add_action(self::CRON_HOOK, [$this, 'processQueue']);
        add_action(self::CLEANUP_CRON_HOOK, [$this, 'cleanupOldDeliveries']);

        // Schedule cron jobs if not already scheduled
        add_action('init', [$this, 'scheduleEvents']);

        // Add custom cron interval if needed
        add_filter('cron_schedules', [$this, 'addCustomCronIntervals']);

        // Add hooks for manual queue processing
        add_action('wp_ajax_webhook_process_queue', [$this, 'handleManualProcessing']);
        add_action('admin_post_webhook_process_queue', [$this, 'handleManualProcessing']);

        // Log worker initialization
        if (class_exists('MCS_Logger')) {
            add_action('wp_loaded', function() {
                MCS_Logger::log('DEBUG', 'Webhook worker initialized', [
                    'cron_hook' => self::CRON_HOOK,
                    'interval' => $this->getProcessingInterval()
                ]);
            });
        }
    }

    /**
     * Schedule cron events
     */
    public function scheduleEvents() {
        // Schedule webhook processing
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            $interval = $this->getProcessingInterval();
            wp_schedule_event(time(), $this->getCronInterval($interval), self::CRON_HOOK);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Webhook worker cron scheduled', [
                    'hook' => self::CRON_HOOK,
                    'interval' => $interval,
                    'next_run' => wp_next_scheduled(self::CRON_HOOK)
                ]);
            }
        }

        // Schedule cleanup
        if (!wp_next_scheduled(self::CLEANUP_CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CLEANUP_CRON_HOOK);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Webhook cleanup cron scheduled', [
                    'hook' => self::CLEANUP_CRON_HOOK,
                    'interval' => 'daily'
                ]);
            }
        }
    }

    /**
     * Unschedule cron events
     */
    public function unscheduleEvents() {
        // Clear webhook processing schedule
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }

        // Clear cleanup schedule
        $timestamp = wp_next_scheduled(self::CLEANUP_CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CLEANUP_CRON_HOOK);
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Webhook worker cron events unscheduled');
        }
    }

    /**
     * Process webhook queue (cron callback)
     */
    public function processQueue() {
        // Prevent overlapping executions
        $lock_key = 'webhook_worker_lock';
        $lock_timeout = 300; // 5 minutes

        if (get_transient($lock_key)) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('DEBUG', 'Webhook worker already running, skipping');
            }
            return;
        }

        // Set lock
        set_transient($lock_key, time(), $lock_timeout);

        try {
            // Get batch size from settings
            $batch_size = $this->getConfiguredBatchSize();

            // Process queue
            $result = $this->dispatcher->processQueue($batch_size);

            // Log processing results
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Webhook worker completed queue processing', [
                    'batch_size' => $batch_size,
                    'processed' => $result['processed'],
                    'succeeded' => $result['succeeded'],
                    'failed' => $result['failed']
                ]);
            }

            // If we processed a full batch, schedule immediate re-run
            if ($result['processed'] >= $batch_size) {
                wp_schedule_single_event(time() + 10, self::CRON_HOOK);
            }

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Webhook worker error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } finally {
            // Always release lock
            delete_transient($lock_key);
        }
    }

    /**
     * Clean up old webhook deliveries (cron callback)
     */
    public function cleanupOldDeliveries() {
        try {
            $retention_days = $this->getRetentionDays();
            $deleted_count = $this->dispatcher->getQueue()->cleanupOldDeliveries($retention_days);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Webhook cleanup completed', [
                    'retention_days' => $retention_days,
                    'deleted_count' => $deleted_count
                ]);
            }

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Webhook cleanup error', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle manual queue processing from admin
     */
    public function handleManualProcessing() {
        // Check permissions
        if (!current_user_can('manage_minpaku')) {
            wp_die(__('Insufficient permissions', 'minpaku-suite'));
        }

        // Verify nonce
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'webhook_process_queue')) {
            wp_die(__('Security check failed', 'minpaku-suite'));
        }

        // Process queue manually
        $batch_size = intval($_REQUEST['batch_size'] ?? $this->getConfiguredBatchSize());
        $result = $this->dispatcher->processQueue($batch_size);

        // Return JSON response for AJAX
        if (wp_doing_ajax()) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Processed %d deliveries (%d succeeded, %d failed)', 'minpaku-suite'),
                    $result['processed'],
                    $result['succeeded'],
                    $result['failed']
                ),
                'result' => $result
            ]);
        }

        // Redirect for regular POST
        $redirect_url = add_query_arg([
            'page' => 'minpaku-webhooks',
            'processed' => $result['processed'],
            'succeeded' => $result['succeeded'],
            'failed' => $result['failed']
        ], admin_url('admin.php'));

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Add custom cron intervals
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function addCustomCronIntervals($schedules) {
        // Add every minute interval
        $schedules['every_minute'] = [
            'interval' => 60,
            'display' => __('Every Minute', 'minpaku-suite')
        ];

        // Add every 30 seconds interval for high-frequency processing
        $schedules['every_30_seconds'] = [
            'interval' => 30,
            'display' => __('Every 30 Seconds', 'minpaku-suite')
        ];

        // Add every 2 minutes interval
        $schedules['every_2_minutes'] = [
            'interval' => 120,
            'display' => __('Every 2 Minutes', 'minpaku-suite')
        ];

        // Add every 5 minutes interval
        $schedules['every_5_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'minpaku-suite')
        ];

        return $schedules;
    }

    /**
     * Get cron interval name for given seconds
     *
     * @param int $seconds Interval in seconds
     * @return string Cron interval name
     */
    private function getCronInterval($seconds) {
        switch ($seconds) {
            case 30:
                return 'every_30_seconds';
            case 60:
                return 'every_minute';
            case 120:
                return 'every_2_minutes';
            case 300:
                return 'every_5_minutes';
            default:
                return 'hourly'; // Fallback to hourly
        }
    }

    /**
     * Get processing interval from settings
     *
     * @return int Interval in seconds
     */
    private function getProcessingInterval() {
        $settings = get_option('minpaku_webhook_settings', []);
        return intval($settings['processing_interval'] ?? self::DEFAULT_INTERVAL);
    }

    /**
     * Get configured batch size
     *
     * @return int Batch size
     */
    private function getConfiguredBatchSize() {
        $settings = get_option('minpaku_webhook_settings', []);
        return intval($settings['batch_size'] ?? 3);
    }

    /**
     * Get retention days for cleanup
     *
     * @return int Days to retain
     */
    private function getRetentionDays() {
        $settings = get_option('minpaku_webhook_settings', []);
        return intval($settings['retention_days'] ?? 30);
    }

    /**
     * Get worker status information
     *
     * @return array Status information
     */
    public function getStatus() {
        $next_run = wp_next_scheduled(self::CRON_HOOK);
        $next_cleanup = wp_next_scheduled(self::CLEANUP_CRON_HOOK);
        $is_locked = get_transient('webhook_worker_lock');

        return [
            'is_scheduled' => $next_run !== false,
            'next_run' => $next_run,
            'next_run_formatted' => $next_run ? date('Y-m-d H:i:s', $next_run) : null,
            'cleanup_scheduled' => $next_cleanup !== false,
            'next_cleanup' => $next_cleanup,
            'next_cleanup_formatted' => $next_cleanup ? date('Y-m-d H:i:s', $next_cleanup) : null,
            'is_running' => $is_locked !== false,
            'lock_time' => $is_locked ? date('Y-m-d H:i:s', $is_locked) : null,
            'processing_interval' => $this->getProcessingInterval(),
            'batch_size' => $this->getConfiguredBatchSize(),
            'retention_days' => $this->getRetentionDays()
        ];
    }

    /**
     * Force immediate queue processing
     *
     * @return array Processing results
     */
    public function forceProcessing() {
        // Remove any existing lock to force processing
        delete_transient('webhook_worker_lock');

        // Process queue
        return $this->dispatcher->processQueue();
    }

    /**
     * Reschedule worker with new interval
     *
     * @param int $interval New interval in seconds
     * @return bool Success
     */
    public function reschedule($interval) {
        // Unschedule existing event
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }

        // Schedule with new interval
        $cron_interval = $this->getCronInterval($interval);
        $result = wp_schedule_event(time(), $cron_interval, self::CRON_HOOK);

        if ($result !== false && class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Webhook worker rescheduled', [
                'new_interval' => $interval,
                'cron_interval' => $cron_interval,
                'next_run' => wp_next_scheduled(self::CRON_HOOK)
            ]);
        }

        return $result !== false;
    }

    /**
     * Get cron hook name
     *
     * @return string Cron hook name
     */
    public static function getCronHook() {
        return self::CRON_HOOK;
    }

    /**
     * Get cleanup cron hook name
     *
     * @return string Cleanup cron hook name
     */
    public static function getCleanupCronHook() {
        return self::CLEANUP_CRON_HOOK;
    }

    /**
     * Get dispatcher instance
     *
     * @return WebhookDispatcher
     */
    public function getDispatcher() {
        return $this->dispatcher;
    }
}