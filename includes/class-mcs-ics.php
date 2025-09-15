<?php
/**
 * ICS Calendar Handler
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles ICS calendar generation and serving for properties
 */
class MCS_Ics {

    /**
     * Initialize the ICS handler
     */
    public static function init() {
        add_action('init', [__CLASS__, 'add_rewrite_rules']);
        add_filter('query_vars', [__CLASS__, 'add_query_vars']);
        add_action('template_redirect', [__CLASS__, 'handle_ics_request']);
    }

    /**
     * Add rewrite rules for ICS endpoints
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^ics/property/([0-9]+)/([A-Za-z0-9]{24})\.ics$',
            'index.php?mcs_ics_property=1&post_id=$matches[1]&key=$matches[2]',
            'top'
        );
    }

    /**
     * Add custom query vars
     *
     * @param array $vars
     * @return array
     */
    public static function add_query_vars($vars) {
        $vars[] = 'mcs_ics_property';
        $vars[] = 'post_id';
        $vars[] = 'key';
        return $vars;
    }

    /**
     * Handle ICS requests via template redirect
     */
    public static function handle_ics_request() {
        if (!get_query_var('mcs_ics_property')) {
            return;
        }

        $post_id = absint(get_query_var('post_id'));
        $key = sanitize_text_field(get_query_var('key'));

        if (!$post_id || !$key) {
            status_header(404);
            exit;
        }

        // Verify the post exists and is a property
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'property' || $post->post_status !== 'publish') {
            status_header(404);
            exit;
        }

        // Verify the ICS key
        $stored_key = get_post_meta($post_id, '_ics_key', true);
        if (!$stored_key || !hash_equals($stored_key, $key)) {
            status_header(404);
            exit;
        }

        // Generate ETag and check for 304
        $etag = self::generate_etag($post_id);
        $last_modified = get_post_modified_time('U', true, $post);

        // Check If-None-Match header
        $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH'])
            ? trim($_SERVER['HTTP_IF_NONE_MATCH'], '"')
            : '';

        // Check If-Modified-Since header
        $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
            ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])
            : 0;

        // Return 304 if content hasn't changed
        if (($if_none_match && $if_none_match === $etag) ||
            ($if_modified_since && $if_modified_since >= $last_modified)) {
            status_header(304);
            header('ETag: "' . $etag . '"');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
            exit;
        }

        // Set headers for ICS response
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="property-' . $post_id . '.ics"');
        header('ETag: "' . $etag . '"');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
        header('Cache-Control: public, max-age=3600');

        // Generate and output ICS content
        echo self::generate_ics_content($post_id);
        exit;
    }

    /**
     * Generate ETag for cache validation
     *
     * @param int $post_id
     * @return string
     */
    private static function generate_etag($post_id) {
        $post = get_post($post_id);
        $settings = get_option('mcs_settings', []);

        $data = [
            'post_modified' => $post->post_modified_gmt,
            'settings_hash' => md5(serialize($settings)),
            'unavailable_dates' => get_post_meta($post_id, '_unavailable_dates', true),
        ];

        return md5(serialize($data));
    }

    /**
     * Generate ICS calendar content
     *
     * @param int $post_id
     * @return string
     */
    private static function generate_ics_content($post_id) {
        $post = get_post($post_id);

        // Start building ICS content
        $ics_lines = [];
        $ics_lines[] = 'BEGIN:VCALENDAR';
        $ics_lines[] = 'VERSION:2.0';
        $ics_lines[] = 'PRODID:-//minpaku-suite//JP';
        $ics_lines[] = 'CALSCALE:GREGORIAN';
        $ics_lines[] = 'METHOD:PUBLISH';
        $ics_lines[] = 'X-WR-CALNAME:' . self::escape_ics_value(get_the_title($post_id));
        $ics_lines[] = 'X-WR-CALDESC:' . self::escape_ics_value(__('Property availability calendar', 'minpaku-suite'));

        // Get events via filter
        $events = [];
        $events = apply_filters('mcs/property_ics_events', $events, $post_id);

        // Default implementation: add unavailable dates as events
        if (empty($events)) {
            $events = self::get_default_events($post_id);
        }

        // Add events to ICS
        foreach ($events as $event) {
            $ics_lines = array_merge($ics_lines, self::format_ics_event($event));
        }

        $ics_lines[] = 'END:VCALENDAR';

        // Join lines and apply proper line folding
        return self::fold_ics_lines(implode("\r\n", $ics_lines));
    }

    /**
     * Get default events from _unavailable_dates meta
     *
     * @param int $post_id
     * @return array
     */
    private static function get_default_events($post_id) {
        $unavailable_dates = get_post_meta($post_id, '_unavailable_dates', true);

        if (!is_array($unavailable_dates)) {
            return [];
        }

        $events = [];
        foreach ($unavailable_dates as $date) {
            // Validate date format (Y-m-d)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $timestamp = strtotime($date);
            if (!$timestamp) {
                continue;
            }

            $events[] = [
                'uid' => 'unavailable-' . $date . '@' . parse_url(home_url(), PHP_URL_HOST),
                'dtstart' => $date,
                'dtend' => $date,
                'summary' => __('Unavailable', 'minpaku-suite'),
                'description' => __('Property is not available on this date', 'minpaku-suite'),
                'all_day' => true,
            ];
        }

        return $events;
    }

    /**
     * Format a single event as ICS lines
     *
     * @param array $event
     * @return array
     */
    private static function format_ics_event($event) {
        $lines = [];
        $lines[] = 'BEGIN:VEVENT';

        // UID is required
        $lines[] = 'UID:' . self::escape_ics_value($event['uid']);

        // DTSTART (all-day events use VALUE=DATE)
        if (!empty($event['all_day'])) {
            $lines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $event['dtstart']);
            // For all-day events, DTEND should be the day after
            $end_date = date('Ymd', strtotime($event['dtend'] . ' +1 day'));
            $lines[] = 'DTEND;VALUE=DATE:' . $end_date;
        } else {
            $lines[] = 'DTSTART:' . self::format_ics_datetime($event['dtstart']);
            $lines[] = 'DTEND:' . self::format_ics_datetime($event['dtend']);
        }

        // SUMMARY
        if (!empty($event['summary'])) {
            $lines[] = 'SUMMARY:' . self::escape_ics_value($event['summary']);
        }

        // DESCRIPTION
        if (!empty($event['description'])) {
            $lines[] = 'DESCRIPTION:' . self::escape_ics_value($event['description']);
        }

        // DTSTAMP (current time)
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * Format datetime for ICS (UTC)
     *
     * @param string $datetime
     * @return string
     */
    private static function format_ics_datetime($datetime) {
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        return gmdate('Ymd\THis\Z', $timestamp);
    }

    /**
     * Escape values for ICS format
     *
     * @param string $value
     * @return string
     */
    private static function escape_ics_value($value) {
        // Escape special characters according to RFC 5545
        $value = str_replace(['\\', ',', ';', "\n", "\r"], ['\\\\', '\\,', '\\;', '\\n', '\\n'], $value);
        return $value;
    }

    /**
     * Apply RFC 5545 line folding (75 octets max)
     *
     * @param string $content
     * @return string
     */
    private static function fold_ics_lines($content) {
        $lines = explode("\r\n", $content);
        $folded_lines = [];

        foreach ($lines as $line) {
            // Convert to UTF-8 bytes for accurate length counting
            $line_bytes = strlen($line);

            if ($line_bytes <= 75) {
                $folded_lines[] = $line;
            } else {
                // Fold long lines
                $folded_lines[] = substr($line, 0, 75);
                $remaining = substr($line, 75);

                while (strlen($remaining) > 74) { // 74 because we add a space
                    $folded_lines[] = ' ' . substr($remaining, 0, 74);
                    $remaining = substr($remaining, 74);
                }

                if (strlen($remaining) > 0) {
                    $folded_lines[] = ' ' . $remaining;
                }
            }
        }

        return implode("\r\n", $folded_lines);
    }

    /**
     * Get or create ICS key for a property
     *
     * @param int $post_id
     * @return string|false
     */
    public static function get_or_create_key($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'property') {
            return false;
        }

        $key = get_post_meta($post_id, '_ics_key', true);

        if (empty($key)) {
            $key = wp_generate_password(24, false, false);
            update_post_meta($post_id, '_ics_key', $key);
        }

        return $key;
    }

    /**
     * Get the property ICS URL
     *
     * @param int $post_id
     * @return string|false
     */
    public static function property_url($post_id) {
        $key = self::get_or_create_key($post_id);

        if (!$key) {
            return false;
        }

        return home_url("ics/property/{$post_id}/{$key}.ics");
    }

    /**
     * Flush rewrite rules (for activation/deactivation)
     */
    public static function flush_rewrite_rules() {
        self::add_rewrite_rules();
        flush_rewrite_rules();
    }
}

/*
Example usage of the mcs/property_ics_events filter:

// Add custom events to property ICS calendars
add_filter('mcs/property_ics_events', function($events, $post_id) {
    // Example: Add booking events from custom meta
    $bookings = get_post_meta($post_id, '_bookings', true);

    if (is_array($bookings)) {
        foreach ($bookings as $booking) {
            $events[] = [
                'uid' => 'booking-' . $booking['id'] . '@' . parse_url(home_url(), PHP_URL_HOST),
                'dtstart' => $booking['check_in'],
                'dtend' => $booking['check_out'],
                'summary' => 'Booked',
                'description' => 'Property is booked',
                'all_day' => true,
            ];
        }
    }

    // Example: Add maintenance events
    $maintenance_dates = get_post_meta($post_id, '_maintenance_dates', true);

    if (is_array($maintenance_dates)) {
        foreach ($maintenance_dates as $date) {
            $events[] = [
                'uid' => 'maintenance-' . $date . '@' . parse_url(home_url(), PHP_URL_HOST),
                'dtstart' => $date,
                'dtend' => $date,
                'summary' => 'Maintenance',
                'description' => 'Property maintenance scheduled',
                'all_day' => true,
            ];
        }
    }

    return $events;
}, 10, 2);

// Example: Customize unavailable date events
add_filter('mcs/property_ics_events', function($events, $post_id) {
    // Remove default events and add custom ones
    $events = [];

    $unavailable_dates = get_post_meta($post_id, '_unavailable_dates', true);

    if (is_array($unavailable_dates)) {
        foreach ($unavailable_dates as $date) {
            $events[] = [
                'uid' => 'custom-unavailable-' . $date . '@' . parse_url(home_url(), PHP_URL_HOST),
                'dtstart' => $date,
                'dtend' => $date,
                'summary' => 'Not Available',
                'description' => 'This property is not available for booking on this date.',
                'all_day' => true,
            ];
        }
    }

    return $events;
}, 10, 2);
*/