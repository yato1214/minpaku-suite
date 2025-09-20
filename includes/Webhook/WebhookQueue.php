<?php
/**
 * Webhook Queue
 * Manages webhook delivery queue with retry logic and failure handling
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../Install/Migrations.php';

class WebhookQueue {

    /**
     * Delivery statuses
     */
    const STATUS_QUEUED = 'queued';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    /**
     * Retry backoff intervals (seconds)
     */
    const RETRY_INTERVALS = [10, 60, 300, 1800, 7200]; // 10s, 1m, 5m, 30m, 2h

    /**
     * Maximum retry attempts
     */
    const MAX_ATTEMPTS = 5;

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        $this->table_name = Migrations::getWebhookDeliveriesTableName();
    }

    /**
     * Enqueue a webhook delivery
     *
     * @param array $delivery Delivery data
     * @return string|false Delivery key on success, false on failure
     */
    public function enqueue($delivery) {
        global $wpdb;

        // Validate required fields
        $required_fields = ['event', 'url', 'payload'];
        foreach ($required_fields as $field) {
            if (empty($delivery[$field])) {
                return false;
            }
        }

        // Generate unique delivery key for idempotency
        $delivery_key = wp_generate_uuid4();

        // Prepare data for database
        $data = [
            'event' => sanitize_text_field($delivery['event']),
            'url' => esc_url_raw($delivery['url']),
            'payload_json' => wp_json_encode($delivery['payload']),
            'headers_json' => wp_json_encode($delivery['headers'] ?? []),
            'attempt' => 1,
            'status' => self::STATUS_QUEUED,
            'delivery_key' => $delivery_key,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        // Insert into database
        $result = $wpdb->insert(
            $this->table_name,
            $data,
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            // Log error
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to enqueue webhook delivery', [
                    'event' => $delivery['event'],
                    'url' => $delivery['url'],
                    'error' => $wpdb->last_error
                ]);
            }
            return false;
        }

        // Log successful enqueue
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Webhook delivery enqueued', [
                'delivery_key' => $delivery_key,
                'event' => $delivery['event'],
                'url' => $delivery['url']
            ]);
        }

        return $delivery_key;
    }

    /**
     * Get next batch of deliveries to process
     *
     * @param int $limit Maximum number of deliveries to return
     * @return array Array of delivery records
     */
    public function nextBatch($limit = 10) {
        global $wpdb;

        $current_time = current_time('mysql');

        // Get queued deliveries or failed deliveries that are ready for retry
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE (status = %s OR (status = %s AND attempt < %d AND updated_at <= %s))
             ORDER BY created_at ASC
             LIMIT %d",
            self::STATUS_QUEUED,
            self::STATUS_FAILED,
            self::MAX_ATTEMPTS,
            $this->calculateRetryTime($current_time),
            $limit
        );

        $results = $wpdb->get_results($sql, ARRAY_A);

        if (!$results) {
            return [];
        }

        // Process and return deliveries
        $deliveries = [];
        foreach ($results as $row) {
            $deliveries[] = $this->processDeliveryRow($row);
        }

        return $deliveries;
    }

    /**
     * Mark delivery as successful
     *
     * @param string $delivery_key Delivery key
     * @return bool Success
     */
    public function markSuccess($delivery_key) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => self::STATUS_SUCCESS,
                'last_error' => null,
                'updated_at' => current_time('mysql')
            ],
            ['delivery_key' => $delivery_key],
            ['%s', '%s', '%s'],
            ['%s']
        );

        if ($result !== false) {
            // Log successful delivery
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Webhook delivery succeeded', [
                    'delivery_key' => $delivery_key
                ]);
            }
        }

        return $result !== false;
    }

    /**
     * Mark delivery as failed and increment attempt count
     *
     * @param string $delivery_key Delivery key
     * @param string $error_message Error message
     * @return bool Success
     */
    public function markFailure($delivery_key, $error_message) {
        global $wpdb;

        // Get current delivery to increment attempt count
        $delivery = $this->getDelivery($delivery_key);
        if (!$delivery) {
            return false;
        }

        $new_attempt = $delivery['attempt'] + 1;
        $status = $new_attempt >= self::MAX_ATTEMPTS ? self::STATUS_FAILED : self::STATUS_QUEUED;

        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => $status,
                'attempt' => $new_attempt,
                'last_error' => sanitize_textarea_field($error_message),
                'updated_at' => current_time('mysql')
            ],
            ['delivery_key' => $delivery_key],
            ['%s', '%d', '%s', '%s'],
            ['%s']
        );

        if ($result !== false) {
            // Log failure
            $log_level = $new_attempt >= self::MAX_ATTEMPTS ? 'ERROR' : 'WARNING';
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log($log_level, 'Webhook delivery failed', [
                    'delivery_key' => $delivery_key,
                    'attempt' => $new_attempt,
                    'max_attempts' => self::MAX_ATTEMPTS,
                    'error' => $error_message,
                    'will_retry' => $new_attempt < self::MAX_ATTEMPTS
                ]);
            }
        }

        return $result !== false;
    }

    /**
     * Get delivery by key
     *
     * @param string $delivery_key Delivery key
     * @return array|null Delivery data or null if not found
     */
    public function getDelivery($delivery_key) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE delivery_key = %s",
            $delivery_key
        );

        $result = $wpdb->get_row($sql, ARRAY_A);

        if (!$result) {
            return null;
        }

        return $this->processDeliveryRow($result);
    }

    /**
     * Get deliveries with filtering
     *
     * @param array $filters Filter options
     * @return array Array of deliveries
     */
    public function getDeliveries($filters = []) {
        global $wpdb;

        $defaults = [
            'status' => null,
            'event' => null,
            'limit' => 50,
            'offset' => 0,
            'order' => 'DESC'
        ];

        $filters = array_merge($defaults, $filters);

        $where_clauses = [];
        $where_values = [];

        if ($filters['status']) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $filters['status'];
        }

        if ($filters['event']) {
            $where_clauses[] = 'event = %s';
            $where_values[] = $filters['event'];
        }

        $where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);
        $order = strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             {$where_sql}
             ORDER BY created_at {$order}
             LIMIT %d OFFSET %d",
            array_merge($where_values, [$filters['limit'], $filters['offset']])
        );

        $results = $wpdb->get_results($sql, ARRAY_A);

        if (!$results) {
            return [];
        }

        $deliveries = [];
        foreach ($results as $row) {
            $deliveries[] = $this->processDeliveryRow($row);
        }

        return $deliveries;
    }

    /**
     * Count deliveries with filtering
     *
     * @param array $filters Filter options
     * @return int Count
     */
    public function countDeliveries($filters = []) {
        global $wpdb;

        $where_clauses = [];
        $where_values = [];

        if (!empty($filters['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $filters['status'];
        }

        if (!empty($filters['event'])) {
            $where_clauses[] = 'event = %s';
            $where_values[] = $filters['event'];
        }

        $where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);

        $sql = "SELECT COUNT(*) FROM {$this->table_name} {$where_sql}";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return intval($wpdb->get_var($sql));
    }

    /**
     * Delete old deliveries
     *
     * @param int $days Number of days to keep
     * @return int Number of deleted records
     */
    public function cleanupOldDeliveries($days = 30) {
        global $wpdb;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            $cutoff_date
        ));

        if ($result && class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Cleaned up old webhook deliveries', [
                'deleted_count' => $result,
                'cutoff_date' => $cutoff_date
            ]);
        }

        return $result ?: 0;
    }

    /**
     * Reset failed delivery for retry
     *
     * @param string $delivery_key Delivery key
     * @return bool Success
     */
    public function resetForRetry($delivery_key) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => self::STATUS_QUEUED,
                'attempt' => 1,
                'last_error' => null,
                'updated_at' => current_time('mysql')
            ],
            ['delivery_key' => $delivery_key],
            ['%s', '%d', '%s', '%s'],
            ['%s']
        );

        if ($result !== false && class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Webhook delivery reset for retry', [
                'delivery_key' => $delivery_key
            ]);
        }

        return $result !== false;
    }

    /**
     * Get queue statistics
     *
     * @return array Statistics
     */
    public function getStats() {
        global $wpdb;

        $stats = [];

        // Count by status
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status",
            ARRAY_A
        );

        foreach ($status_counts as $row) {
            $stats['by_status'][$row['status']] = intval($row['count']);
        }

        // Recent deliveries (last 24 hours)
        $recent_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at > %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
        $stats['recent_24h'] = intval($recent_count);

        // Success rate (last 100 deliveries)
        $recent_deliveries = $wpdb->get_results(
            "SELECT status FROM {$this->table_name} ORDER BY created_at DESC LIMIT 100",
            ARRAY_A
        );

        if (!empty($recent_deliveries)) {
            $success_count = 0;
            foreach ($recent_deliveries as $delivery) {
                if ($delivery['status'] === self::STATUS_SUCCESS) {
                    $success_count++;
                }
            }
            $stats['success_rate'] = round(($success_count / count($recent_deliveries)) * 100, 1);
        } else {
            $stats['success_rate'] = 0;
        }

        return $stats;
    }

    /**
     * Process delivery database row
     *
     * @param array $row Raw database row
     * @return array Processed delivery data
     */
    private function processDeliveryRow($row) {
        $payload = json_decode($row['payload_json'], true) ?: [];
        $headers = json_decode($row['headers_json'], true) ?: [];

        return [
            'id' => intval($row['id']),
            'event' => $row['event'],
            'url' => $row['url'],
            'payload' => $payload,
            'headers' => $headers,
            'attempt' => intval($row['attempt']),
            'status' => $row['status'],
            'last_error' => $row['last_error'],
            'delivery_key' => $row['delivery_key'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'can_retry' => $this->canRetry($row),
            'next_retry_at' => $this->getNextRetryTime($row),
            'is_final_failure' => $this->isFinalFailure($row)
        ];
    }

    /**
     * Check if delivery can be retried
     *
     * @param array $row Database row
     * @return bool Can retry
     */
    private function canRetry($row) {
        return $row['status'] === self::STATUS_FAILED &&
               intval($row['attempt']) < self::MAX_ATTEMPTS;
    }

    /**
     * Check if delivery is final failure
     *
     * @param array $row Database row
     * @return bool Is final failure
     */
    private function isFinalFailure($row) {
        return $row['status'] === self::STATUS_FAILED &&
               intval($row['attempt']) >= self::MAX_ATTEMPTS;
    }

    /**
     * Get next retry time for delivery
     *
     * @param array $row Database row
     * @return string|null Next retry time or null if no retry
     */
    private function getNextRetryTime($row) {
        if (!$this->canRetry($row)) {
            return null;
        }

        $attempt = intval($row['attempt']);
        $interval_index = min($attempt - 1, count(self::RETRY_INTERVALS) - 1);
        $interval = self::RETRY_INTERVALS[$interval_index];

        return date('Y-m-d H:i:s', strtotime($row['updated_at']) + $interval);
    }

    /**
     * Calculate retry time for SQL query
     *
     * @param string $current_time Current time in MySQL format
     * @return string Retry cutoff time
     */
    private function calculateRetryTime($current_time) {
        // Return time that's far enough in past to include all ready retries
        return date('Y-m-d H:i:s', strtotime($current_time) - max(self::RETRY_INTERVALS));
    }

    /**
     * Get retry intervals
     *
     * @return array Retry intervals in seconds
     */
    public static function getRetryIntervals() {
        return self::RETRY_INTERVALS;
    }

    /**
     * Get maximum attempts
     *
     * @return int Maximum attempts
     */
    public static function getMaxAttempts() {
        return self::MAX_ATTEMPTS;
    }

    /**
     * Get valid statuses
     *
     * @return array Valid status values
     */
    public static function getValidStatuses() {
        return [self::STATUS_QUEUED, self::STATUS_SUCCESS, self::STATUS_FAILED];
    }
}