<?php
/**
 * Database Migrations
 * Handles database schema creation and updates
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class Migrations {

    /**
     * Current database version
     */
    const DB_VERSION = '1.0.0';

    /**
     * Database version option key
     */
    const DB_VERSION_OPTION = 'minpaku_suite_db_version';

    /**
     * Run all migrations
     */
    public static function run() {
        $current_version = get_option(self::DB_VERSION_OPTION, '0.0.0');

        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::createBookingLedgerTable();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);

            // Log migration completion
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Database migrations completed', [
                    'from_version' => $current_version,
                    'to_version' => self::DB_VERSION
                ]);
            }
        }
    }

    /**
     * Create booking ledger table
     */
    private static function createBookingLedgerTable() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ms_booking_ledger';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id BIGINT(20) UNSIGNED NOT NULL,
            event VARCHAR(32) NOT NULL,
            amount DECIMAL(12,2) DEFAULT 0.00,
            currency VARCHAR(8) DEFAULT 'JPY',
            meta_json LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY created_at (created_at),
            KEY event (event)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Verify table was created
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Booking ledger table created successfully', [
                    'table_name' => $table_name
                ]);
            }
        } else {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to create booking ledger table', [
                    'table_name' => $table_name,
                    'sql' => $sql
                ]);
            }
        }
    }

    /**
     * Drop all custom tables (for uninstallation)
     */
    public static function dropTables() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'ms_booking_ledger'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option(self::DB_VERSION_OPTION);

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'All MinPaku Suite tables dropped', [
                'tables' => $tables
            ]);
        }
    }

    /**
     * Get current database version
     *
     * @return string
     */
    public static function getCurrentVersion() {
        return get_option(self::DB_VERSION_OPTION, '0.0.0');
    }

    /**
     * Check if migrations are needed
     *
     * @return bool
     */
    public static function needsMigration() {
        $current_version = self::getCurrentVersion();
        return version_compare($current_version, self::DB_VERSION, '<');
    }

    /**
     * Get ledger table name
     *
     * @return string
     */
    public static function getLedgerTableName() {
        global $wpdb;
        return $wpdb->prefix . 'ms_booking_ledger';
    }
}