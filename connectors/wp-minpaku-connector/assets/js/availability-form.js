/**
 * Availability Form Frontend JavaScript
 *
 * @package WP_Minpaku_Connector
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        $('.mpk-availability-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $wrapper = $form.closest('.mpk-availability-form-wrapper');
            var $resultsContainer = $wrapper.find('.mpk-results-container');
            var $loading = $wrapper.find('.mpk-loading');
            var $error = $wrapper.find('.mpk-error');
            var $results = $wrapper.find('.mpk-results');
            var $submitButton = $form.find('.mpk-submit-button');

            // Get form data
            var propertyId = $form.data('property-id') || $form.find('input[name="property_id"]').val();
            var fromDate = $form.find('input[name="from_date"]').val();
            var toDate = $form.find('input[name="to_date"]').val();

            // Basic validation
            if (!propertyId || !fromDate || !toDate) {
                showError($wrapper, mpkAvailability.strings.selectDates);
                return;
            }

            if (fromDate >= toDate) {
                showError($wrapper, mpkAvailability.strings.invalidDates);
                return;
            }

            // Show loading state
            $resultsContainer.show();
            $loading.show();
            $error.hide();
            $results.hide();
            $submitButton.prop('disabled', true).text(mpkAvailability.strings.loading);

            // Make AJAX request
            $.ajax({
                url: mpkAvailability.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpk_get_availability',
                    nonce: mpkAvailability.nonce,
                    property_id: propertyId,
                    from: fromDate,
                    to: toDate
                },
                success: function(response) {
                    if (response.success) {
                        displayResults($wrapper, response.data);
                    } else {
                        showError($wrapper, response.data.message || mpkAvailability.strings.error);
                    }
                },
                error: function(xhr, status, error) {
                    showError($wrapper, mpkAvailability.strings.error + ' (' + error + ')');
                },
                complete: function() {
                    $loading.hide();
                    $submitButton.prop('disabled', false).text($submitButton.data('original-text') || 'Check Availability');
                }
            });
        });

        // Store original button text
        $('.mpk-submit-button').each(function() {
            $(this).data('original-text', $(this).text());
        });
    });

    function showError($wrapper, message) {
        var $resultsContainer = $wrapper.find('.mpk-results-container');
        var $error = $wrapper.find('.mpk-error');
        var $errorMessage = $wrapper.find('.mpk-error-message');
        var $loading = $wrapper.find('.mpk-loading');
        var $results = $wrapper.find('.mpk-results');

        $resultsContainer.show();
        $loading.hide();
        $results.hide();
        $errorMessage.text(message);
        $error.show();
    }

    function displayResults($wrapper, data) {
        var $resultsContainer = $wrapper.find('.mpk-results-container');
        var $error = $wrapper.find('.mpk-error');
        var $results = $wrapper.find('.mpk-results');
        var $calendarGrid = $wrapper.find('.mpk-calendar-grid');
        var $availabilityList = $wrapper.find('.mpk-availability-list');

        $error.hide();
        $results.show();

        // Clear previous results
        $calendarGrid.empty();
        $availabilityList.empty();

        if (!data.availability || data.availability.length === 0) {
            showError($wrapper, mpkAvailability.strings.noResults);
            return;
        }

        // Generate calendar view
        if ($calendarGrid.length > 0) {
            generateCalendarView($calendarGrid, data.availability);
        }

        // Generate list view
        generateListView($availabilityList, data.availability);
    }

    function generateCalendarView($container, availability) {
        // Add day headers
        var dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        dayHeaders.forEach(function(day) {
            $container.append('<div class="mpk-calendar-header">' + day + '</div>');
        });

        // Add calendar days
        availability.forEach(function(day) {
            var date = new Date(day.date);
            var dayNum = date.getDate();
            var statusClass = day.available ? 'available' : 'not-available';
            var priceText = day.available && day.price ?
                mpkAvailability.strings.currency + formatNumber(day.price) : '';

            var $dayElement = $('<div class="mpk-calendar-day ' + statusClass + '">' +
                '<div class="mpk-day-number">' + dayNum + '</div>' +
                '<div class="mpk-day-price">' + priceText + '</div>' +
                '</div>');

            $container.append($dayElement);
        });
    }

    function generateListView($container, availability) {
        availability.forEach(function(day) {
            var date = new Date(day.date);
            var formattedDate = date.toLocaleDateString();
            var statusClass = day.available ? 'available' : 'not-available';
            var statusText = day.available ?
                mpkAvailability.strings.available :
                mpkAvailability.strings.notAvailable;
            var priceText = day.available && day.price ?
                mpkAvailability.strings.price + ' ' + mpkAvailability.strings.currency + formatNumber(day.price) : '';

            var $item = $('<div class="mpk-availability-item ' + statusClass + '">' +
                '<div class="mpk-date">' + formattedDate + '</div>' +
                '<div class="mpk-status">' + statusText + '</div>' +
                '<div class="mpk-price">' + priceText + '</div>' +
                '</div>');

            $container.append($item);
        });
    }

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

})(jQuery);