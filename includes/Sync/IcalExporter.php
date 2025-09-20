<?php
/**
 * iCal Exporter for internal reservations
 * Exports internal bookings to ICS format with proper SEQUENCE handling
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class IcalExporter {

    private $property_id;
    private $base_sequence;

    /**
     * Constructor
     *
     * @param int $property_id
     */
    public function __construct($property_id) {
        $this->property_id = $property_id;
        $this->base_sequence = get_post_meta($property_id, 'mcs_export_sequence', true) ?: 0;
    }

    /**
     * Export internal reservations to ICS format
     *
     * @return string
     */
    public function exportToIcs() {
        $internal_bookings = $this->getInternalBookings();
        $property_title = get_the_title($this->property_id);

        $ics_content = $this->generateIcsHeader($property_title);

        foreach ($internal_bookings as $booking) {
            $ics_content .= $this->generateEventBlock($booking);
        }

        $ics_content .= $this->generateIcsFooter();

        return $ics_content;
    }

    /**
     * Get internal bookings for the property
     *
     * @return array
     */
    private function getInternalBookings() {
        $stored_events = get_post_meta($this->property_id, 'mcs_booked_slots', true);
        if (!is_array($stored_events)) {
            return [];
        }

        // Filter only internal bookings (source = 'internal')
        $internal_bookings = [];
        foreach ($stored_events as $event) {
            $source = isset($event[2]) ? $event[2] : 'unknown';
            if ($source === 'internal') {
                $internal_bookings[] = $event;
            }
        }

        return $internal_bookings;
    }

    /**
     * Generate ICS header
     *
     * @param string $property_title
     * @return string
     */
    private function generateIcsHeader($property_title) {
        $now = gmdate('Ymd\THis\Z');
        $property_title = $this->escapeIcsText($property_title);

        return "BEGIN:VCALENDAR\r\n" .
               "VERSION:2.0\r\n" .
               "PRODID:-//Minpaku Suite//NONSGML Calendar Export//EN\r\n" .
               "CALSCALE:GREGORIAN\r\n" .
               "X-WR-CALNAME:{$property_title} - Internal Bookings\r\n" .
               "X-WR-TIMEZONE:UTC\r\n" .
               "METHOD:PUBLISH\r\n";
    }

    /**
     * Generate ICS footer
     *
     * @return string
     */
    private function generateIcsFooter() {
        return "END:VCALENDAR\r\n";
    }

    /**
     * Generate VEVENT block for a booking
     *
     * @param array $booking
     * @return string
     */
    private function generateEventBlock($booking) {
        $start_timestamp = $booking[0];
        $end_timestamp = $booking[1];
        $uid = isset($booking[3]) ? $booking[3] : $this->generateUid($booking);
        $sequence = isset($booking[4]) ? $booking[4] : 0;

        // Format dates
        $dtstart = gmdate('Ymd\THis\Z', $start_timestamp);
        $dtend = gmdate('Ymd\THis\Z', $end_timestamp);
        $dtstamp = gmdate('Ymd\THis\Z');

        // Generate summary
        $summary = $this->generateEventSummary($booking);
        $description = $this->generateEventDescription($booking);

        $event_block = "BEGIN:VEVENT\r\n" .
                      "UID:{$uid}\r\n" .
                      "DTSTART:{$dtstart}\r\n" .
                      "DTEND:{$dtend}\r\n" .
                      "DTSTAMP:{$dtstamp}\r\n" .
                      "SEQUENCE:{$sequence}\r\n" .
                      "STATUS:CONFIRMED\r\n" .
                      "TRANSP:OPAQUE\r\n" .
                      "SUMMARY:{$summary}\r\n" .
                      "DESCRIPTION:{$description}\r\n" .
                      "CLASS:PRIVATE\r\n" .
                      "END:VEVENT\r\n";

        return $event_block;
    }

    /**
     * Generate UID for booking if not present
     *
     * @param array $booking
     * @return string
     */
    private function generateUid($booking) {
        $start = $booking[0];
        $end = $booking[1];
        $property_id = $this->property_id;

        return "internal-{$property_id}-{$start}-{$end}@minpaku-suite";
    }

    /**
     * Generate event summary
     *
     * @param array $booking
     * @return string
     */
    private function generateEventSummary($booking) {
        $property_title = get_the_title($this->property_id);
        $summary = "Reserved - {$property_title}";

        return $this->escapeIcsText($summary);
    }

    /**
     * Generate event description
     *
     * @param array $booking
     * @return string
     */
    private function generateEventDescription($booking) {
        $start_date = date('Y-m-d', $booking[0]);
        $end_date = date('Y-m-d', $booking[1]);
        $property_title = get_the_title($this->property_id);

        $description = "Internal booking for {$property_title}\\n" .
                      "Check-in: {$start_date}\\n" .
                      "Check-out: {$end_date}\\n" .
                      "Source: Internal\\n" .
                      "Managed by Minpaku Suite";

        return $this->escapeIcsText($description);
    }

    /**
     * Escape text for ICS format
     *
     * @param string $text
     * @return string
     */
    private function escapeIcsText($text) {
        // Escape special characters for ICS format
        $text = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $text);

        // Fold long lines (75 character limit)
        return $this->foldIcsLine($text);
    }

    /**
     * Fold ICS lines to 75 character limit
     *
     * @param string $line
     * @return string
     */
    private function foldIcsLine($line) {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        $remaining = $line;

        while (strlen($remaining) > 75) {
            $folded .= substr($remaining, 0, 75) . "\r\n ";
            $remaining = substr($remaining, 75);
        }

        $folded .= $remaining;

        return $folded;
    }

    /**
     * Add new internal booking and increment sequence
     *
     * @param int $start_timestamp
     * @param int $end_timestamp
     * @param array $metadata
     * @return string UID of the created event
     */
    public function addInternalBooking($start_timestamp, $end_timestamp, $metadata = []) {
        // Generate UID for new booking
        $uid = $this->generateUid([$start_timestamp, $end_timestamp]);

        // Get current sequence and increment
        $current_sequence = $this->incrementSequence();

        // Create booking array
        $booking = [
            $start_timestamp,           // [0] start timestamp
            $end_timestamp,            // [1] end timestamp
            'internal',                // [2] source
            $uid,                      // [3] UID
            $current_sequence,         // [4] sequence
            gmdate('Ymd\THis\Z')      // [5] dtstamp
        ];

        // Add any additional metadata
        if (!empty($metadata)) {
            $booking[6] = $metadata;
        }

        // Get existing bookings
        $stored_events = get_post_meta($this->property_id, 'mcs_booked_slots', true);
        if (!is_array($stored_events)) {
            $stored_events = [];
        }

        // Add new booking
        $stored_events[] = $booking;

        // Update post meta
        update_post_meta($this->property_id, 'mcs_booked_slots', $stored_events);

        // Log the addition
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Internal booking added', [
                'property_id' => $this->property_id,
                'uid' => $uid,
                'start' => $start_timestamp,
                'end' => $end_timestamp,
                'sequence' => $current_sequence
            ]);
        }

        return $uid;
    }

    /**
     * Update existing internal booking and increment sequence
     *
     * @param string $uid
     * @param int $start_timestamp
     * @param int $end_timestamp
     * @param array $metadata
     * @return bool
     */
    public function updateInternalBooking($uid, $start_timestamp, $end_timestamp, $metadata = []) {
        $stored_events = get_post_meta($this->property_id, 'mcs_booked_slots', true);
        if (!is_array($stored_events)) {
            return false;
        }

        // Find booking by UID
        $found_index = -1;
        foreach ($stored_events as $index => $event) {
            if (isset($event[3]) && $event[3] === $uid) {
                $found_index = $index;
                break;
            }
        }

        if ($found_index === -1) {
            return false;
        }

        // Get current sequence and increment
        $current_sequence = $this->incrementSequence();

        // Update booking
        $stored_events[$found_index] = [
            $start_timestamp,           // [0] start timestamp
            $end_timestamp,            // [1] end timestamp
            'internal',                // [2] source
            $uid,                      // [3] UID
            $current_sequence,         // [4] sequence
            gmdate('Ymd\THis\Z')      // [5] dtstamp
        ];

        // Add metadata if provided
        if (!empty($metadata)) {
            $stored_events[$found_index][6] = $metadata;
        }

        // Update post meta
        update_post_meta($this->property_id, 'mcs_booked_slots', $stored_events);

        // Log the update
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Internal booking updated', [
                'property_id' => $this->property_id,
                'uid' => $uid,
                'start' => $start_timestamp,
                'end' => $end_timestamp,
                'sequence' => $current_sequence
            ]);
        }

        return true;
    }

    /**
     * Cancel internal booking (mark as cancelled)
     *
     * @param string $uid
     * @return bool
     */
    public function cancelInternalBooking($uid) {
        $stored_events = get_post_meta($this->property_id, 'mcs_booked_slots', true);
        if (!is_array($stored_events)) {
            return false;
        }

        // Find and remove booking by UID
        $found_index = -1;
        foreach ($stored_events as $index => $event) {
            if (isset($event[3]) && $event[3] === $uid) {
                $found_index = $index;
                break;
            }
        }

        if ($found_index === -1) {
            return false;
        }

        // Remove the booking
        unset($stored_events[$found_index]);
        $stored_events = array_values($stored_events); // Re-index

        // Update post meta
        update_post_meta($this->property_id, 'mcs_booked_slots', $stored_events);

        // Increment sequence for cancellation
        $this->incrementSequence();

        // Log the cancellation
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Internal booking cancelled', [
                'property_id' => $this->property_id,
                'uid' => $uid
            ]);
        }

        return true;
    }

    /**
     * Increment and return the sequence number
     *
     * @return int
     */
    private function incrementSequence() {
        $current_sequence = get_post_meta($this->property_id, 'mcs_export_sequence', true) ?: 0;
        $new_sequence = $current_sequence + 1;

        update_post_meta($this->property_id, 'mcs_export_sequence', $new_sequence);

        return $new_sequence;
    }

    /**
     * Get current export sequence
     *
     * @return int
     */
    public function getCurrentSequence() {
        return get_post_meta($this->property_id, 'mcs_export_sequence', true) ?: 0;
    }

    /**
     * Export to file and return file path
     *
     * @param string $filename
     * @return string|false File path on success, false on failure
     */
    public function exportToFile($filename = null) {
        if (!$filename) {
            $filename = "property-{$this->property_id}-internal-bookings.ics";
        }

        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;

        $ics_content = $this->exportToIcs();

        if (file_put_contents($file_path, $ics_content) !== false) {
            return $file_path;
        }

        return false;
    }
}