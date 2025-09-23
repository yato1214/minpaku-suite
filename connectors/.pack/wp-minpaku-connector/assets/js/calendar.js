/**
 * Connector Calendar JS - Live Data Display
 * Minimal calendar interaction with custom events for external WP sites
 */

class MPCConnectorCalendar {
    constructor() {
        this.initialized = false;
        this.calendars = new Map();
    }

    /**
     * Initialize calendar functionality
     */
    initCalendar(selector) {
        const container = jQuery(selector);
        if (!container.length) {
            console.warn('MPC Calendar: Container not found', selector);
            return;
        }

        const calendarId = container.attr('id');
        if (this.calendars.has(calendarId)) {
            console.warn('MPC Calendar: Already initialized', calendarId);
            return;
        }

        const calendarData = {
            container: container,
            propertyId: container.data('property-id'),
            showPrices: container.data('show-prices') === 1,
            adults: container.data('adults') || 2,
            children: container.data('children') || 0,
            infants: container.data('infants') || 0,
            currency: container.data('currency') || 'JPY'
        };

        this.calendars.set(calendarId, calendarData);
        this.bindEvents(calendarData);
        this.initialized = true;

        console.log('MPC Calendar initialized:', calendarId, calendarData);
    }

    /**
     * Bind events to calendar
     */
    bindEvents(calendarData) {
        const { container } = calendarData;

        // Click handler for available days
        container.on('click', '.mcs-day', (event) => {
            const dayCell = jQuery(event.currentTarget);
            const date = dayCell.data('ymd');
            const propertyId = dayCell.data('property');
            const isDisabled = dayCell.data('disabled');

            // Only handle clicks on available, non-disabled days
            if (date && propertyId && !isDisabled && dayCell.hasClass('mcs-day--vacant')) {
                this.handleDayClick(dayCell, date, propertyId, calendarData);
            }
        });

        // Hover effects for available days
        container.on('mouseenter', '.mcs-day--vacant:not(.mcs-day--disabled)', (event) => {
            const dayCell = jQuery(event.currentTarget);
            dayCell.addClass('mcs-day--hover');
        });

        container.on('mouseleave', '.mcs-day--vacant', (event) => {
            const dayCell = jQuery(event.currentTarget);
            dayCell.removeClass('mcs-day--hover');
        });
    }

    /**
     * Handle day click - dispatch custom event
     */
    handleDayClick(dayCell, date, propertyId, calendarData) {
        // Calculate checkout date (next day)
        const checkinDate = new Date(date + 'T00:00:00');
        const checkoutDate = new Date(checkinDate);
        checkoutDate.setDate(checkoutDate.getDate() + 1);
        const checkout = checkoutDate.toISOString().split('T')[0];

        // Create custom event with booking data
        const eventData = {
            type: 'mcs:day-click',
            propertyId: propertyId,
            checkin: date,
            checkout: checkout,
            adults: calendarData.adults,
            children: calendarData.children,
            infants: calendarData.infants,
            currency: calendarData.currency,
            dayElement: dayCell[0],
            originalEvent: event
        };

        // Dispatch custom event on the day element
        const customEvent = new CustomEvent('mcs:day-click', {
            detail: eventData,
            bubbles: true,
            cancelable: true
        });

        dayCell[0].dispatchEvent(customEvent);

        // Also trigger jQuery event for backward compatibility
        dayCell.trigger('mpc:day-selected', eventData);

        // Log for development
        if (window.console && window.console.log) {
            console.log('MPC Calendar: Day clicked', eventData);
        }

        // Future: Could implement booking modal or redirect here
        // For now, just provide the event for external handling
    }

    /**
     * Get calendar data for external access
     */
    getCalendarData(calendarId) {
        return this.calendars.get(calendarId);
    }

    /**
     * Update calendar availability (for future real-time updates)
     */
    updateAvailability(calendarId, availabilityData) {
        const calendar = this.calendars.get(calendarId);
        if (!calendar) {
            console.warn('MPC Calendar: Calendar not found for update', calendarId);
            return;
        }

        // Implementation for updating availability in real-time
        // This could be used for WebSocket updates or periodic refreshes
        console.log('MPC Calendar: Availability update', calendarId, availabilityData);
    }

    /**
     * Destroy calendar instance
     */
    destroy(calendarId) {
        const calendar = this.calendars.get(calendarId);
        if (calendar) {
            calendar.container.off('click', '.mcs-day');
            calendar.container.off('mouseenter', '.mcs-day--vacant');
            calendar.container.off('mouseleave', '.mcs-day--vacant');
            this.calendars.delete(calendarId);
            console.log('MPC Calendar: Destroyed', calendarId);
        }
    }
}

// Global instance
window.MPCConnectorCalendar = MPCConnectorCalendar;

// jQuery ready initialization for backward compatibility
jQuery(document).ready(function($) {
    'use strict';

    // Auto-initialize calendars that don't have explicit initialization
    $('.mpc-calendar-container').each(function() {
        const container = $(this);
        const calendarId = container.attr('id');

        // Check if this calendar is already initialized
        if (calendarId && !window.mpcCalendarInstances) {
            window.mpcCalendarInstances = new MPCConnectorCalendar();
        }
    });

    // Provide a global helper function for external sites
    window.initMPCCalendar = function(selector) {
        if (!window.mpcCalendarInstances) {
            window.mpcCalendarInstances = new MPCConnectorCalendar();
        }
        window.mpcCalendarInstances.initCalendar(selector);
    };

    // Example usage documentation (will be removed in production)
    if (window.console && window.console.log && typeof WP_DEBUG !== 'undefined') {
        console.log('MPC Calendar: Available events:');
        console.log('- mcs:day-click: Fired when user clicks an available day');
        console.log('- mpc:day-selected: jQuery event for backward compatibility');
        console.log('Example: document.addEventListener("mcs:day-click", function(e) { console.log(e.detail); });');
    }
});