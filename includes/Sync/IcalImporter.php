<?php
/**
 * iCal Importer with robust UID/DTSTAMP/SEQUENCE/STATUS handling
 * Handles event deduplication, cancellation, and differential updates
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class IcalImporter {

    /**
     * Import events from iCal content with robust deduplication
     *
     * @param string $ics_content
     * @param string $url
     * @return array
     */
    public function importEvents($ics_content, $url = '') {
        $raw_events = $this->parseIcsContent($ics_content);

        // Apply deduplication and cancellation logic
        $processed_events = $this->processEvents($raw_events, $url);

        return $processed_events;
    }

    /**
     * Parse iCal content into structured events
     *
     * @param string $ics_content
     * @return array
     */
    private function parseIcsContent($ics_content) {
        $events = [];
        $lines = preg_split('/\r\n|\n|\r/', $ics_content);
        $in_event = false;
        $current_event = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $in_event = true;
                $current_event = [
                    'UID' => null,
                    'DTSTART' => null,
                    'DTEND' => null,
                    'DTSTAMP' => null,
                    'SEQUENCE' => 0,
                    'STATUS' => null,
                    'SUMMARY' => null
                ];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($in_event && $current_event['DTSTART'] && $current_event['DTEND']) {
                    $events[] = $current_event;
                }
                $in_event = false;
                continue;
            }

            if ($in_event) {
                $this->parseEventProperty($line, $current_event);
            }
        }

        return $events;
    }

    /**
     * Parse individual event property line
     *
     * @param string $line
     * @param array &$current_event
     */
    private function parseEventProperty($line, &$current_event) {
        if (stripos($line, 'UID:') === 0) {
            $current_event['UID'] = trim(substr($line, 4));
        } elseif (stripos($line, 'DTSTART') === 0) {
            $current_event['DTSTART'] = $this->extractDateTimeValue($line);
        } elseif (stripos($line, 'DTEND') === 0) {
            $current_event['DTEND'] = $this->extractDateTimeValue($line);
        } elseif (stripos($line, 'DTSTAMP:') === 0) {
            $current_event['DTSTAMP'] = $this->extractDateTimeValue($line);
        } elseif (stripos($line, 'SEQUENCE:') === 0) {
            $current_event['SEQUENCE'] = (int) trim(substr($line, 9));
        } elseif (stripos($line, 'STATUS:') === 0) {
            $current_event['STATUS'] = strtoupper(trim(substr($line, 7)));
        } elseif (stripos($line, 'SUMMARY:') === 0) {
            $current_event['SUMMARY'] = trim(substr($line, 8));
        }
    }

    /**
     * Extract datetime value from property line
     *
     * @param string $line
     * @return string|null
     */
    private function extractDateTimeValue($line) {
        if (preg_match('/[^:]*:(.+)$/', $line, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Process events with deduplication and cancellation logic
     *
     * @param array $raw_events
     * @param string $url
     * @return array
     */
    private function processEvents($raw_events, $url = '') {
        $events_by_uid = [];
        $events_without_uid = [];
        $cancelled_uids = [];

        // Group events by UID and track cancellations
        foreach ($raw_events as $event) {
            $uid = $event['UID'];

            if (empty($uid)) {
                // Events without UID are processed as-is
                $events_without_uid[] = $this->convertToStandardFormat($event);
                continue;
            }

            // Handle cancellation
            if ($event['STATUS'] === 'CANCELLED') {
                $cancelled_uids[$uid] = true;
                continue;
            }

            // Group by UID for deduplication
            if (!isset($events_by_uid[$uid])) {
                $events_by_uid[$uid] = [];
            }

            $events_by_uid[$uid][] = $event;
        }

        // Process each UID group to find the latest version
        $final_events = [];

        foreach ($events_by_uid as $uid => $uid_events) {
            // Skip if cancelled
            if (isset($cancelled_uids[$uid])) {
                continue;
            }

            // Find the latest event version
            $latest_event = $this->findLatestEventVersion($uid_events);

            if ($latest_event) {
                $final_events[] = $this->convertToStandardFormat($latest_event);
            }
        }

        // Add events without UID
        $final_events = array_merge($final_events, $events_without_uid);

        // Log processing stats
        if (class_exists('MCS_Logger')) {
            $stats = [
                'total_raw' => count($raw_events),
                'with_uid' => count($events_by_uid),
                'without_uid' => count($events_without_uid),
                'cancelled' => count($cancelled_uids),
                'final' => count($final_events)
            ];

            MCS_Logger::log('INFO', 'iCal import processing completed', [
                'url' => $url,
                'stats' => $stats
            ]);
        }

        return $final_events;
    }

    /**
     * Find the latest version of an event based on SEQUENCE and DTSTAMP
     *
     * @param array $events
     * @return array|null
     */
    private function findLatestEventVersion($events) {
        if (empty($events)) {
            return null;
        }

        if (count($events) === 1) {
            return $events[0];
        }

        // Sort by SEQUENCE (descending), then by DTSTAMP (descending)
        usort($events, function($a, $b) {
            // Compare SEQUENCE first
            $seq_diff = $b['SEQUENCE'] - $a['SEQUENCE'];
            if ($seq_diff !== 0) {
                return $seq_diff;
            }

            // If SEQUENCE is same, compare DTSTAMP
            $a_stamp = $this->parseDatetime($a['DTSTAMP']);
            $b_stamp = $this->parseDatetime($b['DTSTAMP']);

            return $b_stamp - $a_stamp;
        });

        return $events[0];
    }

    /**
     * Convert event to standard format for compatibility
     *
     * @param array $event
     * @return array
     */
    private function convertToStandardFormat($event) {
        $start_timestamp = $this->parseDatetime($event['DTSTART']);
        $end_timestamp = $this->parseDatetime($event['DTEND']);

        return [
            $start_timestamp,           // [0] start timestamp
            $end_timestamp,             // [1] end timestamp
            'import',                   // [2] source
            $event['UID'],              // [3] UID
            $event['SEQUENCE'],         // [4] sequence
            $event['DTSTAMP']           // [5] dtstamp
        ];
    }

    /**
     * Parse datetime string to timestamp
     *
     * @param string|null $datetime_str
     * @return int
     */
    private function parseDatetime($datetime_str) {
        if (empty($datetime_str)) {
            return 0;
        }

        $datetime_str = trim($datetime_str);

        // Handle YYYYMMDD format
        if (preg_match('/^\d{8}$/', $datetime_str)) {
            $dt = DateTime::createFromFormat('Ymd', $datetime_str, new DateTimeZone('UTC'));
            return $dt ? $dt->getTimestamp() : 0;
        }

        // Handle YYYYMMDDTHHMMSSZ format
        if (preg_match('/^\d{8}T\d{6}Z?$/', $datetime_str)) {
            $format = str_ends_with($datetime_str, 'Z') ? 'Ymd\THis\Z' : 'Ymd\THis';
            $dt = DateTime::createFromFormat($format, $datetime_str, new DateTimeZone('UTC'));
            return $dt ? $dt->getTimestamp() : 0;
        }

        // Fallback to strtotime
        $timestamp = strtotime($datetime_str);
        return $timestamp !== false ? $timestamp : 0;
    }

    /**
     * Apply differential update logic
     *
     * @param int $post_id
     * @param array $new_events
     * @return array
     */
    public function applyDifferentialUpdate($post_id, $new_events) {
        $stored_events = get_post_meta($post_id, 'mcs_booked_slots', true);
        if (!is_array($stored_events)) {
            $stored_events = [];
        }

        $added = 0;
        $updated = 0;
        $removed = 0;
        $skipped = 0;

        // Index stored events by UID for efficient lookup
        $stored_by_uid = [];
        $stored_without_uid = [];

        foreach ($stored_events as $index => $stored_event) {
            $uid = isset($stored_event[3]) ? $stored_event[3] : null;

            if ($uid) {
                $stored_by_uid[$uid] = [
                    'index' => $index,
                    'event' => $stored_event
                ];
            } else {
                $stored_without_uid[] = [
                    'index' => $index,
                    'event' => $stored_event
                ];
            }
        }

        $new_events_processed = [];

        // Process new events
        foreach ($new_events as $new_event) {
            $uid = isset($new_event[3]) ? $new_event[3] : null;

            if ($uid && isset($stored_by_uid[$uid])) {
                // Update existing event with UID
                $stored_info = $stored_by_uid[$uid];
                $stored_event = $stored_info['event'];

                // Check if update is needed (compare timestamps and sequence)
                if ($this->needsUpdate($stored_event, $new_event)) {
                    $stored_events[$stored_info['index']] = $new_event;
                    $updated++;
                } else {
                    $skipped++;
                }

                unset($stored_by_uid[$uid]);
            } else {
                // New event
                $stored_events[] = $new_event;
                $added++;
            }

            $new_events_processed[] = $new_event;
        }

        // Remove events that are no longer in the new feed (by UID)
        foreach ($stored_by_uid as $uid => $stored_info) {
            unset($stored_events[$stored_info['index']]);
            $removed++;
        }

        // Re-index array to remove gaps
        $stored_events = array_values($stored_events);

        update_post_meta($post_id, 'mcs_booked_slots', $stored_events);

        return [
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
            'skipped' => $skipped
        ];
    }

    /**
     * Check if stored event needs update based on new event
     *
     * @param array $stored_event
     * @param array $new_event
     * @return bool
     */
    private function needsUpdate($stored_event, $new_event) {
        // Compare timestamps
        if ($stored_event[0] !== $new_event[0] || $stored_event[1] !== $new_event[1]) {
            return true;
        }

        // Compare sequence if available
        $stored_sequence = isset($stored_event[4]) ? $stored_event[4] : 0;
        $new_sequence = isset($new_event[4]) ? $new_event[4] : 0;

        if ($new_sequence > $stored_sequence) {
            return true;
        }

        // Compare DTSTAMP if sequence is same
        if ($new_sequence === $stored_sequence) {
            $stored_dtstamp = isset($stored_event[5]) ? $this->parseDatetime($stored_event[5]) : 0;
            $new_dtstamp = isset($new_event[5]) ? $this->parseDatetime($new_event[5]) : 0;

            return $new_dtstamp > $stored_dtstamp;
        }

        return false;
    }
}