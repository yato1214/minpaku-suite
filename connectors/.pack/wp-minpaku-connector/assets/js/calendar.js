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
     * Handle day click - dispatch custom event and redirect to portal
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

        // Check if custom event was prevented
        if (!customEvent.defaultPrevented) {
            this.redirectToPortalBooking(eventData);
        }
    }

    /**
     * Redirect to portal booking page (admin new booking page)
     */
    redirectToPortalBooking(eventData) {
        // Get portal URL from WordPress localization or global variable
        let portalUrl = '';

        if (typeof mpcCalendarData !== 'undefined' && mpcCalendarData.portalUrl) {
            portalUrl = mpcCalendarData.portalUrl;
        } else if (typeof wpMinpakuConnector !== 'undefined' && wpMinpakuConnector.portalUrl) {
            portalUrl = wpMinpakuConnector.portalUrl;
        } else {
            console.warn('MPC Calendar: Portal URL not configured');
            return;
        }

        // Generate nonce-like parameter (simplified for external sites)
        const timestamp = Date.now().toString(36);
        const randomStr = Math.random().toString(36).substring(2, 8);
        const nonceParam = timestamp + randomStr;

        // Build admin booking URL with parameters (matches the actual booking page format)
        const bookingParams = new URLSearchParams({
            post_type: 'mcs_booking',
            property_id: eventData.propertyId,
            checkin: eventData.checkin,
            checkout: eventData.checkout,
            adults: eventData.adults,
            children: eventData.children,
            infants: eventData.infants,
            currency: eventData.currency,
            _mcs_nonce: nonceParam
        });

        // Construct the admin booking URL (WordPress admin format)
        const bookingUrl = `${portalUrl.replace(/\/$/, '')}/wp-admin/post-new.php?${bookingParams.toString()}`;

        // Open in new tab/window to preserve user's current page
        window.open(bookingUrl, '_blank', 'noopener,noreferrer');

        // Log for debugging
        if (window.console && window.console.log) {
            console.log('MPC Calendar: Redirecting to admin booking page:', bookingUrl);
        }
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
    if (!window.mpcCalendarInstances) {
        window.mpcCalendarInstances = new MPCConnectorCalendar();
    }

    // Auto-initialize calendars marked for auto-init
    $('.mpc-calendar-container[data-auto-init="true"]').each(function() {
        const container = $(this);
        const calendarId = container.attr('id');

        if (calendarId) {
            window.mpcCalendarInstances.initCalendar('#' + calendarId);
        }
    });

    // Initialize modal calendar functionality for property listings
    initModalCalendar();

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

/**
 * Modal Calendar functionality for property listings
 */
function initModalCalendar() {
    const $ = jQuery;

    // Handle calendar button clicks
    $(document).on('click', '.wmc-calendar-button', function(e) {
        e.preventDefault();

        const propertyId = $(this).data('property-id');
        const propertyTitle = $(this).data('property-title');

        if (!propertyId) {
            console.warn('Property ID not found for calendar button');
            return;
        }

        showCalendarModal(propertyId, propertyTitle);
    });

    // Close modal when clicking outside or on close button
    $(document).on('click', '.wmc-modal-overlay, .wmc-modal-close', function(e) {
        if (e.target === this) {
            closeCalendarModal();
        }
    });

    // Close modal with ESC key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCalendarModal();
        }
    });
}

/**
 * Show calendar modal for a property
 */
function showCalendarModal(propertyId, propertyTitle) {
    const $ = jQuery;

    // Create modal if it doesn't exist
    if ($('#wmc-calendar-modal').length === 0) {
        const modalHtml = `
            <div id="wmc-calendar-modal" class="wmc-modal-overlay">
                <div class="wmc-modal-content">
                    <div class="wmc-modal-header">
                        <h3 class="wmc-modal-title"></h3>
                        <button class="wmc-modal-close" aria-label="Close">&times;</button>
                    </div>
                    <div class="wmc-modal-body">
                        <div class="wmc-modal-loading">
                            <div class="wmc-loading-spinner"></div>
                            <p>Loading availability calendar...</p>
                        </div>
                        <div class="wmc-modal-calendar-content"></div>
                    </div>
                </div>
            </div>
        `;
        $('body').append(modalHtml);
    }

    const modal = $('#wmc-calendar-modal');
    const title = modal.find('.wmc-modal-title');
    const loadingDiv = modal.find('.wmc-modal-loading');
    const contentDiv = modal.find('.wmc-modal-calendar-content');

    // Set title and show modal
    title.text(propertyTitle || `Property ${propertyId} - Availability Calendar`);
    loadingDiv.show();
    contentDiv.hide().empty();
    modal.addClass('wmc-modal-active');
    $('body').addClass('wmc-modal-open');

    // Load calendar content via AJAX
    loadCalendarContent(propertyId, function(success, content) {
        loadingDiv.hide();
        if (success) {
            contentDiv.html(content).show();

            // Initialize calendar functionality for the modal content
            if (window.mpcCalendarInstances) {
                const calendarContainer = contentDiv.find('.mpc-calendar-container');
                if (calendarContainer.length && calendarContainer.attr('id')) {
                    window.mpcCalendarInstances.initCalendar('#' + calendarContainer.attr('id'));
                }
            }
        } else {
            contentDiv.html('<div class="wmc-error">Failed to load calendar. Please try again.</div>').show();
        }
    });
}

/**
 * Close calendar modal
 */
function closeCalendarModal() {
    const $ = jQuery;
    const modal = $('#wmc-calendar-modal');

    modal.removeClass('wmc-modal-active');
    $('body').removeClass('wmc-modal-open');

    // Clean up calendar instances
    setTimeout(function() {
        const contentDiv = modal.find('.wmc-modal-calendar-content');
        const calendarContainer = contentDiv.find('.mpc-calendar-container');

        if (calendarContainer.length && calendarContainer.attr('id') && window.mpcCalendarInstances) {
            window.mpcCalendarInstances.destroy(calendarContainer.attr('id'));
        }

        contentDiv.empty();
    }, 300);
}

/**
 * Load calendar content via AJAX or generate it directly
 */
function loadCalendarContent(propertyId, callback) {
    // For now, generate calendar shortcode HTML directly
    // In a full implementation, this would make an AJAX call to WordPress

    setTimeout(function() {
        const calendarId = 'mpc-modal-calendar-' + propertyId;
        const calendarHtml = `
            <div class="mpc-calendar-legend">
                <h4>空室状況の見方</h4>
                <div class="mpc-legend-items">
                    <div class="mpc-legend-item">
                        <span class="mpc-legend-color mpc-legend-color--vacant"></span>
                        <span class="mpc-legend-label">空き</span>
                    </div>
                    <div class="mpc-legend-item">
                        <span class="mpc-legend-color mpc-legend-color--partial"></span>
                        <span class="mpc-legend-label">一部予約あり</span>
                    </div>
                    <div class="mpc-legend-item">
                        <span class="mpc-legend-color mpc-legend-color--full"></span>
                        <span class="mpc-legend-label">満室</span>
                    </div>
                </div>
            </div>
            <div id="${calendarId}" class="mpc-calendar-container"
                 data-property-id="${propertyId}"
                 data-show-prices="1"
                 data-adults="2"
                 data-children="0"
                 data-infants="0"
                 data-currency="JPY">
                <div class="wmc-error">Calendar content will be loaded here...</div>
            </div>
        `;

        callback(true, calendarHtml);
    }, 500);
}