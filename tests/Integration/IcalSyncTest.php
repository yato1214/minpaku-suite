<?php
/**
 * Integration Tests for iCal Sync System
 * Tests import/export roundtrip and deduplication logic
 */

use PHPUnit\Framework\TestCase;

class IcalSyncTest extends TestCase {

    private $property_id;
    private $test_calendar_url;

    protected function setUp(): void {
        parent::setUp();

        // Create test property
        $this->property_id = wp_insert_post([
            'post_type' => 'property',
            'post_title' => 'Test Property for iCal Sync',
            'post_status' => 'publish'
        ]);

        // Mock external calendar URL
        $this->test_calendar_url = 'https://test-calendar.example.com/calendar.ics';

        // Clean up any existing sync data
        delete_post_meta($this->property_id, 'ical_import_url');
        delete_post_meta($this->property_id, 'last_sync_time');

        // Clean up reservations
        $this->cleanupTestReservations();
    }

    protected function tearDown(): void {
        // Clean up test data
        wp_delete_post($this->property_id, true);
        $this->cleanupTestReservations();
        parent::tearDown();
    }

    private function cleanupTestReservations() {
        global $wpdb;
        $wpdb->delete(
            $wpdb->posts,
            ['post_type' => 'reservation', 'post_title' => 'Test Reservation%'],
            ['%s', '%s']
        );
    }

    /**
     * Test iCal import with UID deduplication
     */
    public function testIcalImportWithDeduplication() {
        // Create test iCal content
        $ical_content = $this->createTestIcalContent();

        // Mock HTTP response for calendar fetch
        add_filter('pre_http_request', function($preempt, $args, $url) use ($ical_content) {
            if ($url === $this->test_calendar_url) {
                return [
                    'response' => ['code' => 200],
                    'body' => $ical_content
                ];
            }
            return $preempt;
        }, 10, 3);

        // Set up property for sync
        update_post_meta($this->property_id, 'ical_import_url', $this->test_calendar_url);

        // First import
        $importer = new IcalImporter();
        $result1 = $importer->import_from_url($this->property_id, $this->test_calendar_url);

        $this->assertTrue($result1['success'], 'First import should succeed');
        $this->assertGreaterThan(0, $result1['imported'], 'Should import events');

        // Check that reservations were created
        $reservations = get_posts([
            'post_type' => 'reservation',
            'meta_query' => [
                ['key' => 'property_id', 'value' => $this->property_id]
            ]
        ]);

        $initial_count = count($reservations);
        $this->assertGreaterThan(0, $initial_count, 'Should have imported reservations');

        // Second import (should deduplicate)
        $result2 = $importer->import_from_url($this->property_id, $this->test_calendar_url);

        $this->assertTrue($result2['success'], 'Second import should succeed');
        $this->assertEquals(0, $result2['imported'], 'Should not import duplicates');

        // Verify reservation count unchanged
        $reservations_after = get_posts([
            'post_type' => 'reservation',
            'meta_query' => [
                ['key' => 'property_id', 'value' => $this->property_id]
            ]
        ]);

        $this->assertEquals($initial_count, count($reservations_after), 'Reservation count should not change');
    }

    /**
     * Test iCal import with event updates (SEQUENCE)
     */
    public function testIcalImportWithEventUpdates() {
        // Import initial calendar
        $initial_ical = $this->createTestIcalContent();

        add_filter('pre_http_request', function($preempt, $args, $url) use ($initial_ical) {
            if ($url === $this->test_calendar_url) {
                return [
                    'response' => ['code' => 200],
                    'body' => $initial_ical
                ];
            }
            return $preempt;
        }, 10, 3);

        update_post_meta($this->property_id, 'ical_import_url', $this->test_calendar_url);

        $importer = new IcalImporter();
        $result1 = $importer->import_from_url($this->property_id, $this->test_calendar_url);

        $this->assertTrue($result1['success'], 'Initial import should succeed');

        // Get initial reservation
        $reservations = get_posts([
            'post_type' => 'reservation',
            'meta_query' => [
                ['key' => 'property_id', 'value' => $this->property_id]
            ]
        ]);

        $this->assertGreaterThan(0, count($reservations), 'Should have reservations');
        $initial_reservation = $reservations[0];
        $initial_checkin = get_post_meta($initial_reservation->ID, 'checkin_date', true);

        // Create updated calendar with same UID but higher SEQUENCE
        $updated_ical = $this->createUpdatedIcalContent();

        // Update mock to return updated content
        remove_all_filters('pre_http_request');
        add_filter('pre_http_request', function($preempt, $args, $url) use ($updated_ical) {
            if ($url === $this->test_calendar_url) {
                return [
                    'response' => ['code' => 200],
                    'body' => $updated_ical
                ];
            }
            return $preempt;
        }, 10, 3);

        // Import updated calendar
        $result2 = $importer->import_from_url($this->property_id, $this->test_calendar_url);

        $this->assertTrue($result2['success'], 'Updated import should succeed');
        $this->assertGreaterThan(0, $result2['updated'], 'Should update existing events');

        // Verify reservation was updated
        $updated_reservations = get_posts([
            'post_type' => 'reservation',
            'meta_query' => [
                ['key' => 'property_id', 'value' => $this->property_id]
            ]
        ]);

        $updated_reservation = $updated_reservations[0];
        $updated_checkin = get_post_meta($updated_reservation->ID, 'checkin_date', true);

        $this->assertNotEquals($initial_checkin, $updated_checkin, 'Check-in date should be updated');
    }

    /**
     * Test iCal import with event cancellations
     */
    public function testIcalImportWithCancellations() {
        // Import initial calendar
        $initial_ical = $this->createTestIcalContent();

        add_filter('pre_http_request', function($preempt, $args, $url) use ($initial_ical) {
            if ($url === $this->test_calendar_url) {
                return [
                    'response' => ['code' => 200],
                    'body' => $initial_ical
                ];
            }
            return $preempt;
        }, 10, 3);

        update_post_meta($this->property_id, 'ical_import_url', $this->test_calendar_url);

        $importer = new IcalImporter();
        $result1 = $importer->import_from_url($this->property_id, $this->test_calendar_url);

        $this->assertTrue($result1['success'], 'Initial import should succeed');

        $initial_count = count(get_posts([
            'post_type' => 'reservation',
            'meta_query' => [
                ['key' => 'property_id', 'value' => $this->property_id]
            ]
        ]));

        // Create calendar with cancelled event
        $cancelled_ical = $this->createCancelledIcalContent();

        remove_all_filters('pre_http_request');
        add_filter('pre_http_request', function($preempt, $args, $url) use ($cancelled_ical) {
            if ($url === $this->test_calendar_url) {
                return [
                    'response' => ['code' => 200],
                    'body' => $cancelled_ical
                ];
            }
            return $preempt;
        }, 10, 3);

        // Import cancelled calendar
        $result2 = $importer->import_from_url($this->property_id, $this->test_calendar_url);

        $this->assertTrue($result2['success'], 'Cancellation import should succeed');
        $this->assertGreaterThan(0, $result2['cancelled'], 'Should cancel events');

        // Verify reservation count decreased
        $final_count = count(get_posts([
            'post_type' => 'reservation',
            'meta_query' => [
                ['key' => 'property_id', 'value' => $this->property_id]
            ]
        ]));

        $this->assertLessThan($initial_count, $final_count, 'Should have fewer reservations after cancellation');
    }

    /**
     * Test iCal export roundtrip
     */
    public function testIcalExportRoundtrip() {
        // Create test reservations
        $reservation_id = wp_insert_post([
            'post_type' => 'reservation',
            'post_title' => 'Test Reservation Export',
            'post_status' => 'confirmed'
        ]);

        update_post_meta($reservation_id, 'property_id', $this->property_id);
        update_post_meta($reservation_id, 'checkin_date', '2025-02-01');
        update_post_meta($reservation_id, 'checkout_date', '2025-02-05');
        update_post_meta($reservation_id, 'guest_name', 'John Doe');
        update_post_meta($reservation_id, 'guest_email', 'john@example.com');
        update_post_meta($reservation_id, 'ical_uid', 'test-reservation-' . $reservation_id . '@minpaku-suite.local');

        // Export calendar
        $exporter = new IcalExporter();
        $exported_ical = $exporter->export_property_calendar($this->property_id);

        $this->assertNotEmpty($exported_ical, 'Should export iCal content');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $exported_ical, 'Should contain calendar header');
        $this->assertStringContainsString('BEGIN:VEVENT', $exported_ical, 'Should contain event');
        $this->assertStringContainsString('John Doe', $exported_ical, 'Should contain guest name');

        // Test roundtrip: import the exported calendar
        add_filter('pre_http_request', function($preempt, $args, $url) use ($exported_ical) {
            if ($url === $this->test_calendar_url) {
                return [
                    'response' => ['code' => 200],
                    'body' => $exported_ical
                ];
            }
            return $preempt;
        }, 10, 3);

        // Create new property for roundtrip test
        $roundtrip_property_id = wp_insert_post([
            'post_type' => 'property',
            'post_title' => 'Roundtrip Test Property',
            'post_status' => 'publish'
        ]);

        update_post_meta($roundtrip_property_id, 'ical_import_url', $this->test_calendar_url);

        $importer = new IcalImporter();
        $import_result = $importer->import_from_url($roundtrip_property_id, $this->test_calendar_url);

        $this->assertTrue($import_result['success'], 'Roundtrip import should succeed');
        $this->assertGreaterThan(0, $import_result['imported'], 'Should import exported events');

        // Verify imported data matches original
        $imported_reservations = get_posts([
            'post_type' => 'reservation',
            'meta_query' => [
                ['key' => 'property_id', 'value' => $roundtrip_property_id]
            ]
        ]);

        $this->assertCount(1, $imported_reservations, 'Should import exactly one reservation');

        $imported_reservation = $imported_reservations[0];
        $imported_checkin = get_post_meta($imported_reservation->ID, 'checkin_date', true);
        $imported_guest = get_post_meta($imported_reservation->ID, 'guest_name', true);

        $this->assertEquals('2025-02-01', $imported_checkin, 'Check-in date should match');
        $this->assertEquals('John Doe', $imported_guest, 'Guest name should match');

        // Clean up
        wp_delete_post($reservation_id, true);
        wp_delete_post($roundtrip_property_id, true);
    }

    /**
     * Create test iCal content
     */
    private function createTestIcalContent(): string {
        return "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test Calendar//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
UID:test-event-1@example.com
DTSTART:20250201T150000Z
DTEND:20250205T110000Z
DTSTAMP:20250101T120000Z
SEQUENCE:0
SUMMARY:Test Reservation 1
DESCRIPTION:Initial test reservation
ORGANIZER:mailto:host@example.com
ATTENDEE:mailto:guest@example.com
STATUS:CONFIRMED
END:VEVENT
BEGIN:VEVENT
UID:test-event-2@example.com
DTSTART:20250210T150000Z
DTEND:20250215T110000Z
DTSTAMP:20250101T120000Z
SEQUENCE:0
SUMMARY:Test Reservation 2
DESCRIPTION:Second test reservation
ORGANIZER:mailto:host@example.com
ATTENDEE:mailto:guest2@example.com
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR";
    }

    /**
     * Create updated iCal content with higher SEQUENCE
     */
    private function createUpdatedIcalContent(): string {
        return "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test Calendar//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
UID:test-event-1@example.com
DTSTART:20250203T150000Z
DTEND:20250207T110000Z
DTSTAMP:20250102T120000Z
SEQUENCE:1
SUMMARY:Test Reservation 1 (Updated)
DESCRIPTION:Updated test reservation with new dates
ORGANIZER:mailto:host@example.com
ATTENDEE:mailto:guest@example.com
STATUS:CONFIRMED
END:VEVENT
BEGIN:VEVENT
UID:test-event-2@example.com
DTSTART:20250210T150000Z
DTEND:20250215T110000Z
DTSTAMP:20250101T120000Z
SEQUENCE:0
SUMMARY:Test Reservation 2
DESCRIPTION:Second test reservation (unchanged)
ORGANIZER:mailto:host@example.com
ATTENDEE:mailto:guest2@example.com
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR";
    }

    /**
     * Create cancelled iCal content
     */
    private function createCancelledIcalContent(): string {
        return "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test Calendar//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
UID:test-event-1@example.com
DTSTART:20250201T150000Z
DTEND:20250205T110000Z
DTSTAMP:20250103T120000Z
SEQUENCE:2
SUMMARY:Test Reservation 1 (Cancelled)
DESCRIPTION:Cancelled test reservation
ORGANIZER:mailto:host@example.com
ATTENDEE:mailto:guest@example.com
STATUS:CANCELLED
END:VEVENT
BEGIN:VEVENT
UID:test-event-2@example.com
DTSTART:20250210T150000Z
DTEND:20250215T110000Z
DTSTAMP:20250101T120000Z
SEQUENCE:0
SUMMARY:Test Reservation 2
DESCRIPTION:Second test reservation (still confirmed)
ORGANIZER:mailto:host@example.com
ATTENDEE:mailto:guest2@example.com
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR";
    }
}