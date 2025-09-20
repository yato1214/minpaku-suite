<?php
/**
 * Booking Ledger
 * Handles recording and retrieval of booking events for audit trail
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class BookingLedger {

    /**
     * Ledger event types
     */
    const EVENT_RESERVE = 'reserve';
    const EVENT_CONFIRM = 'confirm';
    const EVENT_CANCEL = 'cancel';
    const EVENT_COMPLETE = 'complete';
    const EVENT_REFUND = 'refund';
    const EVENT_PAYMENT = 'payment';
    const EVENT_ADJUSTMENT = 'adjustment';
    const EVENT_NOTE = 'note';

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ms_booking_ledger';
    }

    /**
     * Get all valid event types
     *
     * @return array
     */
    public static function getValidEvents() {
        return [
            self::EVENT_RESERVE,
            self::EVENT_CONFIRM,
            self::EVENT_CANCEL,
            self::EVENT_COMPLETE,
            self::EVENT_REFUND,
            self::EVENT_PAYMENT,
            self::EVENT_ADJUSTMENT,
            self::EVENT_NOTE
        ];
    }

    /**
     * Get event display labels
     *
     * @return array
     */
    public static function getEventLabels() {
        return [
            self::EVENT_RESERVE => __('Reserved', 'minpaku-suite'),
            self::EVENT_CONFIRM => __('Confirmed', 'minpaku-suite'),
            self::EVENT_CANCEL => __('Cancelled', 'minpaku-suite'),
            self::EVENT_COMPLETE => __('Completed', 'minpaku-suite'),
            self::EVENT_REFUND => __('Refunded', 'minpaku-suite'),
            self::EVENT_PAYMENT => __('Payment', 'minpaku-suite'),
            self::EVENT_ADJUSTMENT => __('Adjustment', 'minpaku-suite'),
            self::EVENT_NOTE => __('Note', 'minpaku-suite')
        ];
    }

    /**
     * Append entry to ledger
     *
     * @param int $booking_id Booking ID
     * @param string $event Event type
     * @param float $amount Amount (default: 0)
     * @param string $currency Currency code (default: 'JPY')
     * @param array $meta Additional metadata
     * @return int|false Entry ID on success, false on failure
     */
    public function append($booking_id, $event, $amount = 0.0, $currency = 'JPY', array $meta = []) {
        global $wpdb;

        // Validate inputs
        if (!$booking_id || !is_numeric($booking_id)) {
            return false;
        }

        if (!in_array($event, self::getValidEvents())) {
            return false;
        }

        // Prepare data
        $data = [
            'booking_id' => intval($booking_id),
            'event' => sanitize_text_field($event),
            'amount' => floatval($amount),
            'currency' => sanitize_text_field($currency),
            'meta_json' => wp_json_encode($meta),
            'created_at' => current_time('mysql')
        ];

        // Insert into database
        $result = $wpdb->insert(
            $this->table_name,
            $data,
            ['%d', '%s', '%f', '%s', '%s', '%s']
        );

        if ($result === false) {
            // Log error if logger is available
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to append ledger entry', [
                    'booking_id' => $booking_id,
                    'event' => $event,
                    'error' => $wpdb->last_error
                ]);
            }
            return false;
        }

        $entry_id = $wpdb->insert_id;

        // Log successful entry
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Ledger entry created', [
                'entry_id' => $entry_id,
                'booking_id' => $booking_id,
                'event' => $event,
                'amount' => $amount,
                'currency' => $currency
            ]);
        }

        return $entry_id;
    }

    /**
     * Get ledger entries for a booking
     *
     * @param int $booking_id Booking ID
     * @param array $args Query arguments
     * @return array Array of ledger entries
     */
    public function list($booking_id, $args = []) {
        global $wpdb;

        $defaults = [
            'event' => null,
            'limit' => 50,
            'offset' => 0,
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where_clauses = ['booking_id = %d'];
        $where_values = [intval($booking_id)];

        if ($args['event']) {
            $where_clauses[] = 'event = %s';
            $where_values[] = sanitize_text_field($args['event']);
        }

        $where_sql = implode(' AND ', $where_clauses);
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE {$where_sql}
             ORDER BY created_at {$order}, id {$order}
             LIMIT %d OFFSET %d",
            array_merge($where_values, [$limit, $offset])
        );

        $results = $wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            return [];
        }

        // Process results
        $entries = [];
        foreach ($results as $row) {
            $entries[] = $this->processLedgerRow($row);
        }

        return $entries;
    }

    /**
     * Get ledger entry by ID
     *
     * @param int $entry_id Entry ID
     * @return array|null Ledger entry or null if not found
     */
    public function getEntry($entry_id) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            intval($entry_id)
        );

        $result = $wpdb->get_row($sql, ARRAY_A);

        if (!$result) {
            return null;
        }

        return $this->processLedgerRow($result);
    }

    /**
     * Count ledger entries for a booking
     *
     * @param int $booking_id Booking ID
     * @param string|null $event Filter by event type
     * @return int
     */
    public function count($booking_id, $event = null) {
        global $wpdb;

        $where_clauses = ['booking_id = %d'];
        $where_values = [intval($booking_id)];

        if ($event) {
            $where_clauses[] = 'event = %s';
            $where_values[] = sanitize_text_field($event);
        }

        $where_sql = implode(' AND ', $where_clauses);

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}",
            $where_values
        );

        return intval($wpdb->get_var($sql));
    }

    /**
     * Get total amount for a booking by event type
     *
     * @param int $booking_id Booking ID
     * @param string|null $event Event type (null for all)
     * @param string $currency Currency filter
     * @return float
     */
    public function getTotalAmount($booking_id, $event = null, $currency = 'JPY') {
        global $wpdb;

        $where_clauses = ['booking_id = %d', 'currency = %s'];
        $where_values = [intval($booking_id), sanitize_text_field($currency)];

        if ($event) {
            $where_clauses[] = 'event = %s';
            $where_values[] = sanitize_text_field($event);
        }

        $where_sql = implode(' AND ', $where_clauses);

        $sql = $wpdb->prepare(
            "SELECT SUM(amount) FROM {$this->table_name} WHERE {$where_sql}",
            $where_values
        );

        return floatval($wpdb->get_var($sql));
    }

    /**
     * Get ledger summary for a booking
     *
     * @param int $booking_id Booking ID
     * @return array Summary data
     */
    public function getSummary($booking_id) {
        $entries = $this->list($booking_id, ['limit' => -1]);

        $summary = [
            'total_entries' => count($entries),
            'events' => [],
            'amounts' => [],
            'first_entry' => null,
            'last_entry' => null
        ];

        foreach ($entries as $entry) {
            $event = $entry['event'];
            $currency = $entry['currency'];

            // Count events
            if (!isset($summary['events'][$event])) {
                $summary['events'][$event] = 0;
            }
            $summary['events'][$event]++;

            // Sum amounts by currency
            if (!isset($summary['amounts'][$currency])) {
                $summary['amounts'][$currency] = 0.0;
            }
            $summary['amounts'][$currency] += $entry['amount'];

            // Track first and last entries
            if (!$summary['first_entry'] || $entry['created_at'] < $summary['first_entry']['created_at']) {
                $summary['first_entry'] = $entry;
            }
            if (!$summary['last_entry'] || $entry['created_at'] > $summary['last_entry']['created_at']) {
                $summary['last_entry'] = $entry;
            }
        }

        return $summary;
    }

    /**
     * Delete ledger entries for a booking
     *
     * @param int $booking_id Booking ID
     * @return bool Success
     */
    public function deleteForBooking($booking_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            ['booking_id' => intval($booking_id)],
            ['%d']
        );

        if ($result !== false && class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Ledger entries deleted for booking', [
                'booking_id' => $booking_id,
                'deleted_count' => $result
            ]);
        }

        return $result !== false;
    }

    /**
     * Process raw database row into formatted entry
     *
     * @param array $row Raw database row
     * @return array Processed entry
     */
    private function processLedgerRow($row) {
        $meta_data = [];
        if (!empty($row['meta_json'])) {
            $meta_data = json_decode($row['meta_json'], true) ?: [];
        }

        return [
            'id' => intval($row['id']),
            'booking_id' => intval($row['booking_id']),
            'event' => $row['event'],
            'event_label' => $this->getEventLabel($row['event']),
            'amount' => floatval($row['amount']),
            'currency' => $row['currency'],
            'meta_data' => $meta_data,
            'created_at' => $row['created_at'],
            'formatted_amount' => $this->formatAmount($row['amount'], $row['currency']),
            'formatted_date' => $this->formatDate($row['created_at'])
        ];
    }

    /**
     * Get event label
     *
     * @param string $event Event type
     * @return string
     */
    private function getEventLabel($event) {
        $labels = self::getEventLabels();
        return $labels[$event] ?? $event;
    }

    /**
     * Format amount for display
     *
     * @param float $amount
     * @param string $currency
     * @return string
     */
    private function formatAmount($amount, $currency) {
        if ($currency === 'JPY') {
            return number_format($amount, 0) . ' ' . $currency;
        }

        return number_format($amount, 2) . ' ' . $currency;
    }

    /**
     * Format date for display
     *
     * @param string $date MySQL datetime
     * @return string
     */
    private function formatDate($date) {
        return mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $date);
    }

    /**
     * Check if table exists
     *
     * @return bool
     */
    public function tableExists() {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)) === $this->table_name;
    }
}