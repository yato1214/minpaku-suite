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

    // Click handler for booking creation (all available days)
    $(document).on('click', '.mcs-day', function(e) {
        e.preventDefault();

        var cell = $(this);
        var date = cell.data('ymd');
        var propertyId = cell.data('property');
        var isDisabled = cell.data('disabled');

        console.log('Day clicked:', {
            date: date,
            propertyId: propertyId,
            isDisabled: isDisabled,
            classes: cell.attr('class'),
            adminUrl: adminUrl
        });

        // Only allow clicks on vacant (available) days that are not past dates
        if (date && propertyId && !isDisabled && !cell.hasClass('mcs-day--past') && !cell.hasClass('mcs-day--empty') && cell.hasClass('mcs-day--vacant')) {
            var nextDay = new Date(date + 'T00:00:00');
            nextDay.setDate(nextDay.getDate() + 1);
            var checkout = nextDay.toISOString().split('T')[0];

            var bookingUrl = adminUrl + '&mcs_property=' + propertyId +
                            '&mcs_checkin=' + date + '&mcs_checkout=' + checkout;

            console.log('Navigating to:', bookingUrl);
            window.location.href = bookingUrl;
        } else {
            console.log('Click ignored - conditions not met:', {
                hasDate: !!date,
                hasPropertyId: !!propertyId,
                isDisabled: isDisabled,
                isPast: cell.hasClass('mcs-day--past'),
                isEmpty: cell.hasClass('mcs-day--empty'),
                isVacant: cell.hasClass('mcs-day--vacant')
            });
        }
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