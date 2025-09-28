/**
 * Admin Calendar JS - Portal Side
 * Click-to-create booking functionality only, no legacy elements
 */

jQuery(document).ready(function($) {
    'use strict';

    // Get admin URL for booking creation
    var adminUrl = (window.minpakuAdmin && window.minpakuAdmin.bookingUrl) ||
                   '/wp-admin/post-new.php?post_type=mcs_booking';

    // Remove any legacy calendar initialization
    if (window.mcsCalendarInit) {
        window.mcsCalendarInit = function() {
            // Disabled - no legacy functionality
        };
    }

    // Direct navigation removed - admin calendar now uses unified interactions
    // NOTE: This disables admin direct booking creation. If admin users need to create bookings,
    // consider implementing a quote panel with a "Create Booking" action for admin users.
    $(document).on('click', '.mcs-day', function(e) {
        e.preventDefault();
        console.log('Admin calendar - direct navigation disabled, implement quote panel with admin actions if needed');
    });

    // Remove all legacy slot/status/legend elements on page load
    $(window).on('load', function() {
        $('.slot, .status-dot, .legend, .availability-slot, .mcs-availability-indicator').remove();
    });

    // Initialize clean calendar state
    function initCleanCalendar() {
        // Remove any dynamically added legacy elements
        $('.mcs-availability-calendar').each(function() {
            $(this).find('.slot, .status-dot, .legend, .availability-slot').remove();
        });
    }

    // Run initialization
    initCleanCalendar();

    // Re-run after any AJAX content loads
    $(document).ajaxComplete(function() {
        initCleanCalendar();
    });
});