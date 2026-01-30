<?php
/**
 * iCal Event Repository for hash storage and comparison
 * Manages event hashing for change detection and caching
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class IcalEventRepository {

    const HASH_CACHE_KEY = 'mcs_ical_hashes';
    const HASH_CACHE_EXPIRY = DAY_IN_SECONDS * 7; // 1 week

    /**
     * Store hash for a URL's iCal content
     *
     * @param string $url
     * @param string $content_hash
     * @param array $metadata
     */
    public function storeContentHash($url, $content_hash, $metadata = []) {
        $hashes = $this->getStoredHashes();

        $hashes[$url] = [
            'hash' => $content_hash,
            'timestamp' => current_time('timestamp'),
            'metadata' => $metadata
        ];

        $this->saveHashes($hashes);

        // Log hash storage
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'iCal content hash stored', [
                'url' => $url,
                'hash' => substr($content_hash, 0, 8) . '...',
                'metadata' => $metadata
            ]);
        }
    }

    /**
     * Get stored hash for a URL
     *
     * @param string $url
     * @return string|null
     */
    public function getContentHash($url) {
        $hashes = $this->getStoredHashes();

        if (isset($hashes[$url])) {
            return $hashes[$url]['hash'];
        }

        return null;
    }

    /**
     * Check if content has changed based on hash
     *
     * @param string $url
     * @param string $new_content_hash
     * @return bool
     */
    public function hasContentChanged($url, $new_content_hash) {
        $stored_hash = $this->getContentHash($url);

        if ($stored_hash === null) {
            return true; // No previous hash, consider changed
        }

        return $stored_hash !== $new_content_hash;
    }

    /**
     * Generate hash for iCal content
     *
     * @param string $ics_content
     * @return string
     */
    public function generateContentHash($ics_content) {
        // Normalize content for consistent hashing
        $normalized_content = $this->normalizeIcsContent($ics_content);

        return hash('sha256', $normalized_content);
    }

    /**
     * Normalize iCal content for consistent hashing
     *
     * @param string $ics_content
     * @return string
     */
    private function normalizeIcsContent($ics_content) {
        // Remove variable fields that don't affect event data
        $normalized = $ics_content;

        // Remove DTSTAMP lines (these change on every export)
        $normalized = preg_replace('/^DTSTAMP:.*$/m', '', $normalized);

        // Remove PRODID lines (may vary)
        $normalized = preg_replace('/^PRODID:.*$/m', '', $normalized);

        // Remove X- custom properties that may vary
        $normalized = preg_replace('/^X-[^:]*:.*$/m', '', $normalized);

        // Normalize line endings
        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);

        // Remove empty lines
        $normalized = preg_replace('/^\s*$/m', '', $normalized);

        // Sort VEVENT blocks for consistent ordering
        $normalized = $this->sortVEvents($normalized);

        return trim($normalized);
    }

    /**
     * Sort VEVENT blocks for consistent hashing
     *
     * @param string $ics_content
     * @return string
     */
    private function sortVEvents($ics_content) {
        $lines = explode("\n", $ics_content);
        $events = [];
        $header_lines = [];
        $footer_lines = [];
        $current_event = [];
        $in_event = false;
        $after_events = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $in_event = true;
                $current_event = [$line];
            } elseif ($line === 'END:VEVENT') {
                $current_event[] = $line;
                $events[] = $current_event;
                $current_event = [];
                $in_event = false;
                $after_events = true;
            } elseif ($in_event) {
                $current_event[] = $line;
            } elseif (!$after_events) {
                $header_lines[] = $line;
            } else {
                $footer_lines[] = $line;
            }
        }

        // Sort events by UID, then by DTSTART
        usort($events, function($a, $b) {
            $uid_a = $this->extractPropertyFromEvent($a, 'UID');
            $uid_b = $this->extractPropertyFromEvent($b, 'UID');

            if ($uid_a !== $uid_b) {
                return strcmp($uid_a, $uid_b);
            }

            $dtstart_a = $this->extractPropertyFromEvent($a, 'DTSTART');
            $dtstart_b = $this->extractPropertyFromEvent($b, 'DTSTART');

            return strcmp($dtstart_a, $dtstart_b);
        });

        // Reconstruct content
        $result = implode("\n", $header_lines) . "\n";

        foreach ($events as $event) {
            $result .= implode("\n", $event) . "\n";
        }

        $result .= implode("\n", $footer_lines);

        return $result;
    }

    /**
     * Extract property value from event lines
     *
     * @param array $event_lines
     * @param string $property
     * @return string
     */
    private function extractPropertyFromEvent($event_lines, $property) {
        foreach ($event_lines as $line) {
            if (stripos($line, $property . ':') === 0) {
                return substr($line, strlen($property) + 1);
            }
        }

        return '';
    }

    /**
     * Get all stored hashes
     *
     * @return array
     */
    private function getStoredHashes() {
        $hashes = get_option(self::HASH_CACHE_KEY, []);

        if (!is_array($hashes)) {
            return [];
        }

        // Clean up expired entries
        $current_time = current_time('timestamp');
        $cleaned_hashes = [];

        foreach ($hashes as $url => $data) {
            if (isset($data['timestamp']) &&
                ($current_time - $data['timestamp']) < self::HASH_CACHE_EXPIRY) {
                $cleaned_hashes[$url] = $data;
            }
        }

        // Save cleaned hashes if any were removed
        if (count($cleaned_hashes) !== count($hashes)) {
            $this->saveHashes($cleaned_hashes);
        }

        return $cleaned_hashes;
    }

    /**
     * Save hashes to storage
     *
     * @param array $hashes
     */
    private function saveHashes($hashes) {
        // Limit the number of stored hashes to prevent bloat
        if (count($hashes) > 200) {
            // Sort by timestamp and keep the 150 most recent
            uasort($hashes, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });

            $hashes = array_slice($hashes, 0, 150, true);
        }

        update_option(self::HASH_CACHE_KEY, $hashes, false);
    }

    /**
     * Store event fingerprints for change detection
     *
     * @param string $url
     * @param array $events
     */
    public function storeEventFingerprints($url, $events) {
        $fingerprints = [];

        foreach ($events as $event) {
            $uid = isset($event[3]) ? $event[3] : null;
            $start = isset($event[0]) ? $event[0] : 0;
            $end = isset($event[1]) ? $event[1] : 0;
            $sequence = isset($event[4]) ? $event[4] : 0;

            if ($uid) {
                $fingerprints[$uid] = [
                    'start' => $start,
                    'end' => $end,
                    'sequence' => $sequence,
                    'hash' => $this->generateEventHash($event)
                ];
            }
        }

        $this->storeFingerprints($url, $fingerprints);
    }

    /**
     * Get stored event fingerprints
     *
     * @param string $url
     * @return array
     */
    public function getEventFingerprints($url) {
        $fingerprint_key = 'mcs_event_fingerprints_' . md5($url);
        $fingerprints = get_option($fingerprint_key, []);

        return is_array($fingerprints) ? $fingerprints : [];
    }

    /**
     * Store fingerprints for URL
     *
     * @param string $url
     * @param array $fingerprints
     */
    private function storeFingerprints($url, $fingerprints) {
        $fingerprint_key = 'mcs_event_fingerprints_' . md5($url);
        update_option($fingerprint_key, $fingerprints, false);
    }

    /**
     * Generate hash for individual event
     *
     * @param array $event
     * @return string
     */
    private function generateEventHash($event) {
        $hash_data = [
            'start' => isset($event[0]) ? $event[0] : 0,
            'end' => isset($event[1]) ? $event[1] : 0,
            'source' => isset($event[2]) ? $event[2] : '',
            'uid' => isset($event[3]) ? $event[3] : '',
            'sequence' => isset($event[4]) ? $event[4] : 0
        ];

        return hash('md5', serialize($hash_data));
    }

    /**
     * Compare events and return changes
     *
     * @param string $url
     * @param array $new_events
     * @return array
     */
    public function compareEvents($url, $new_events) {
        $stored_fingerprints = $this->getEventFingerprints($url);
        $new_fingerprints = [];

        // Generate fingerprints for new events
        foreach ($new_events as $event) {
            $uid = isset($event[3]) ? $event[3] : null;

            if ($uid) {
                $new_fingerprints[$uid] = [
                    'start' => $event[0],
                    'end' => $event[1],
                    'sequence' => isset($event[4]) ? $event[4] : 0,
                    'hash' => $this->generateEventHash($event)
                ];
            }
        }

        $changes = [
            'added' => [],
            'updated' => [],
            'removed' => [],
            'unchanged' => []
        ];

        // Find added and updated events
        foreach ($new_fingerprints as $uid => $new_fp) {
            if (!isset($stored_fingerprints[$uid])) {
                $changes['added'][] = $uid;
            } elseif ($stored_fingerprints[$uid]['hash'] !== $new_fp['hash']) {
                $changes['updated'][] = $uid;
            } else {
                $changes['unchanged'][] = $uid;
            }
        }

        // Find removed events
        foreach ($stored_fingerprints as $uid => $stored_fp) {
            if (!isset($new_fingerprints[$uid])) {
                $changes['removed'][] = $uid;
            }
        }

        // Store new fingerprints
        $this->storeFingerprints($url, $new_fingerprints);

        return $changes;
    }

    /**
     * Clear all stored data for a URL
     *
     * @param string $url
     */
    public function clearUrlData($url) {
        // Remove from content hashes
        $hashes = $this->getStoredHashes();
        unset($hashes[$url]);
        $this->saveHashes($hashes);

        // Remove event fingerprints
        $fingerprint_key = 'mcs_event_fingerprints_' . md5($url);
        delete_option($fingerprint_key);

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Cleared iCal data for URL', ['url' => $url]);
        }
    }

    /**
     * Get statistics about stored data
     *
     * @return array
     */
    public function getStatistics() {
        $hashes = $this->getStoredHashes();
        $stats = [
            'total_urls' => count($hashes),
            'oldest_entry' => null,
            'newest_entry' => null,
            'total_fingerprint_options' => 0
        ];

        if (!empty($hashes)) {
            $timestamps = array_column($hashes, 'timestamp');
            $stats['oldest_entry'] = min($timestamps);
            $stats['newest_entry'] = max($timestamps);
        }

        // Count fingerprint options
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'mcs_event_fingerprints_%'");
        $stats['total_fingerprint_options'] = (int) $count;

        return $stats;
    }

    /**
     * Cleanup old data
     *
     * @param int $days_old
     * @return int Number of entries cleaned
     */
    public function cleanup($days_old = 30) {
        $cutoff_time = current_time('timestamp') - ($days_old * DAY_IN_SECONDS);
        $cleaned_count = 0;

        // Clean hashes
        $hashes = get_option(self::HASH_CACHE_KEY, []);
        $cleaned_hashes = [];

        foreach ($hashes as $url => $data) {
            if (isset($data['timestamp']) && $data['timestamp'] >= $cutoff_time) {
                $cleaned_hashes[$url] = $data;
            } else {
                $cleaned_count++;
            }
        }

        if ($cleaned_count > 0) {
            update_option(self::HASH_CACHE_KEY, $cleaned_hashes, false);
        }

        // Clean fingerprint options
        global $wpdb;
        $fingerprint_options = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'mcs_event_fingerprints_%'"
        );

        foreach ($fingerprint_options as $option) {
            $fingerprints = get_option($option->option_name, []);

            if (empty($fingerprints) || !is_array($fingerprints)) {
                delete_option($option->option_name);
                $cleaned_count++;
            }
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'iCal repository cleanup completed', [
                'cleaned_entries' => $cleaned_count,
                'cutoff_days' => $days_old
            ]);
        }

        return $cleaned_count;
    }
}