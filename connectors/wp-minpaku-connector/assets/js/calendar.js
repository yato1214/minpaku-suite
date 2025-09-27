/**
 * Connector Calendar JS - New Checkin/Checkout Selection with Live Quote
 * Implements the new calendar selection flow with real-time pricing
 */

class MPCConnectorCalendar {
    constructor() {
        this.initialized = false;
        this.calendars = new Map();
        this.selectedCheckin = null;
        this.selectedCheckout = null;
        this.currentPropertyId = null;
        this.selectionState = 'waiting_checkin'; // waiting_checkin, waiting_checkout, completed
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

            // Check if Shift key is held for direct booking (bypass quote flow)
            if (event.shiftKey && date && propertyId && !isDisabled && (dayCell.hasClass('mcs-day--available') || dayCell.hasClass('mcs-day--vacant'))) {
                // Direct booking - redirect to portal immediately with check-in date only
                this.redirectToPortalBookingDirect({
                    propertyId: propertyId,
                    checkin: date,
                    adults: calendarData.adults || 2,
                    children: calendarData.children || 0,
                    infants: calendarData.infants || 0,
                    currency: calendarData.currency || 'JPY'
                });
                return;
            }

            // Only handle clicks on available, non-disabled days (new classes)
            if (date && propertyId && !isDisabled && (dayCell.hasClass('mcs-day--available') || dayCell.hasClass('mcs-day--vacant'))) {
                // Add direct booking option: Ctrl+Click for immediate booking
                if (event.ctrlKey || event.metaKey) {
                    // Direct booking - skip quote flow
                    console.log('MPC Calendar: Direct booking triggered for', date);
                    this.redirectToPortalBookingDirect({
                        propertyId: propertyId,
                        checkin: date,
                        adults: calendarData.adults || 2,
                        children: calendarData.children || 0,
                        infants: calendarData.infants || 0,
                        currency: calendarData.currency || 'JPY'
                    });
                    return;
                }

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
     * Handle day click - NEW 2-click selection flow with live quote
     */
    handleDayClick(dayCell, date, propertyId, calendarData) {
        const container = calendarData.container;

        if (this.selectionState === 'waiting_checkin') {
            // First click - select checkin date
            this.selectCheckinDate(dayCell, date, propertyId, container);
        } else if (this.selectionState === 'waiting_checkout') {
            // Second click - select checkout date
            this.selectCheckoutDate(dayCell, date, propertyId, container, calendarData);
        }
    }

    /**
     * Select checkin date (first click)
     */
    selectCheckinDate(dayCell, date, propertyId, container) {
        // Clear any previous selections
        this.clearSelection(container);

        // Set checkin selection
        this.selectedCheckin = date;
        this.currentPropertyId = propertyId;
        this.selectionState = 'waiting_checkout';

        // Apply visual selection
        dayCell.addClass('mcs-day--selected-checkin');
        dayCell.attr('data-selection-type', 'checkin');

        // Show instruction message
        this.showMessage(container, 'チェックアウト日を選択してください', 'info');

        // Disable past dates and dates before checkin
        this.updateDateAvailability(container);

        console.log('Checkin selected:', date);
    }

    /**
     * Select checkout date (second click)
     */
    selectCheckoutDate(dayCell, date, propertyId, container, calendarData) {
        // Validate selection
        if (date <= this.selectedCheckin) {
            this.showMessage(container, 'チェックアウト日はチェックイン日より後の日付を選択してください', 'error');
            return;
        }

        if (propertyId !== this.currentPropertyId) {
            this.showMessage(container, '同じ物件の日付を選択してください', 'error');
            return;
        }

        // Set checkout selection
        this.selectedCheckout = date;
        this.selectionState = 'completed';

        // Apply visual selection
        dayCell.addClass('mcs-day--selected-checkout');
        dayCell.attr('data-selection-type', 'checkout');

        // Highlight range between dates
        this.highlightDateRange(container);

        // Show loading message
        this.showMessage(container, '見積を取得中...', 'loading');

        // Get live quote
        this.getLiveQuote(propertyId, this.selectedCheckin, this.selectedCheckout, calendarData);

        console.log('Checkout selected:', date, 'Range:', this.selectedCheckin, 'to', this.selectedCheckout);
    }

    /**
     * Clear current selection
     */
    clearSelection(container) {
        container.find('.mcs-day').removeClass('mcs-day--selected-checkin mcs-day--selected-checkout mcs-day--in-range');
        container.find('.mcs-day').removeAttr('data-selection-type');
        this.hideQuoteDisplay(container);
        this.selectedCheckin = null;
        this.selectedCheckout = null;
        this.currentPropertyId = null;
        this.selectionState = 'waiting_checkin';
    }

    /**
     * Highlight date range between checkin and checkout
     */
    highlightDateRange(container) {
        if (!this.selectedCheckin || !this.selectedCheckout) return;

        const checkinDate = new Date(this.selectedCheckin);
        const checkoutDate = new Date(this.selectedCheckout);

        container.find('.mcs-day').each(function() {
            const dayDate = new Date($(this).data('ymd'));
            if (dayDate > checkinDate && dayDate < checkoutDate) {
                $(this).addClass('mcs-day--in-range');
            }
        });
    }

    /**
     * Update date availability based on selection state
     */
    updateDateAvailability(container) {
        // This can be enhanced to disable unavailable checkout dates based on booking rules
        // For now, just ensure past dates are disabled
    }

    /**
     * Show message to user
     */
    showMessage(container, message, type = 'info') {
        let messageContainer = container.find('.mpc-selection-message');
        if (messageContainer.length === 0) {
            messageContainer = $('<div class="mpc-selection-message"></div>');
            container.prepend(messageContainer);
        }

        messageContainer.removeClass('mpc-message--info mpc-message--error mpc-message--loading mpc-message--success');
        messageContainer.addClass('mpc-message--' + type);
        messageContainer.text(message);
        messageContainer.show();
    }

    /**
     * Get live quote from the portal
     */
    getLiveQuote(propertyId, checkin, checkout, calendarData) {
        const container = calendarData.container;

        // Check if we have the necessary data
        if (typeof mpcCalendarData === 'undefined') {
            this.showMessage(container, '設定エラー: 見積を取得できません', 'error');
            return;
        }

        // Make AJAX request for quote
        $.ajax({
            url: mpcCalendarData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mpc_get_quote',
                property_id: propertyId,
                checkin: checkin,
                checkout: checkout,
                adults: calendarData.adults || 2,
                children: calendarData.children || 0,
                infants: calendarData.infants || 0,
                nonce: mpcCalendarData.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.displayQuote(container, response.data, checkin, checkout);
                } else {
                    this.showMessage(container, '見積取得エラー: ' + (response.data || '不明なエラー'), 'error');
                }
            },
            error: (xhr, status, error) => {
                console.error('Quote AJAX error:', status, error);
                this.showMessage(container, 'ネットワークエラー: 見積を取得できませんでした', 'error');
            }
        });
    }

    /**
     * Display quote results
     */
    displayQuote(container, quoteData, checkin, checkout) {
        this.showMessage(container, '見積を取得しました', 'success');

        // Create or update quote display
        let quoteContainer = container.find('.mpc-quote-container');
        if (quoteContainer.length === 0) {
            quoteContainer = $('<div class="mpc-quote-container"></div>');
            container.after(quoteContainer);
        }

        // Calculate number of nights
        const checkinDate = new Date(checkin);
        const checkoutDate = new Date(checkout);
        const nights = Math.ceil((checkoutDate - checkinDate) / (1000 * 60 * 60 * 24));

        // Build quote HTML with Japanese labels
        let quoteHtml = `
            <div class="mpc-quote-title">見積詳細</div>
            <div class="mpc-quote-details">
                <div class="mpc-quote-item">
                    <span class="mpc-quote-label">チェックイン:</span>
                    <span class="mpc-quote-amount">${this.formatDateJP(checkin)}</span>
                </div>
                <div class="mpc-quote-item">
                    <span class="mpc-quote-label">チェックアウト:</span>
                    <span class="mpc-quote-amount">${this.formatDateJP(checkout)}</span>
                </div>
                <div class="mpc-quote-item">
                    <span class="mpc-quote-label">宿泊日数:</span>
                    <span class="mpc-quote-amount">${nights}泊</span>
                </div>
        `;

        // Add pricing breakdown if available
        if (quoteData.accommodation_total) {
            quoteHtml += `
                <div class="mpc-quote-item">
                    <span class="mpc-quote-label">宿泊料金:</span>
                    <span class="mpc-quote-amount">¥${this.formatNumber(quoteData.accommodation_total)}</span>
                </div>
            `;
        }

        if (quoteData.cleaning_fee && quoteData.cleaning_fee > 0) {
            quoteHtml += `
                <div class="mpc-quote-item">
                    <span class="mpc-quote-label">清掃費:</span>
                    <span class="mpc-quote-amount">¥${this.formatNumber(quoteData.cleaning_fee)}</span>
                </div>
            `;
        }

        // Total
        if (quoteData.total) {
            quoteHtml += `
                <div class="mpc-quote-item">
                    <span class="mpc-quote-label">合計:</span>
                    <span class="mpc-quote-amount">¥${this.formatNumber(quoteData.total)}</span>
                </div>
            `;
        }

        // Nightly breakdown if available
        if (quoteData.nightly_breakdown && quoteData.nightly_breakdown.length > 0) {
            quoteHtml += `
                <div class="mpc-quote-breakdown">
                    <h5>日毎料金内訳</h5>
                    <div class="mpc-nightly-breakdown">
            `;

            quoteData.nightly_breakdown.forEach(night => {
                quoteHtml += `
                    <div class="mpc-nightly-item">
                        <span>${this.formatDateJP(night.date)}</span>
                        <span>¥${this.formatNumber(night.price)}</span>
                    </div>
                `;
            });

            quoteHtml += `
                    </div>
                </div>
            `;
        }

        // Add booking action button
        quoteHtml += `
            <div class="mpc-quote-actions">
                <button class="mpc-booking-button" data-property-id="${this.currentPropertyId}" data-checkin="${checkin}" data-checkout="${checkout}" data-adults="${calendarData.adults}" data-children="${calendarData.children}" data-infants="${calendarData.infants}">
                    この条件で予約する
                </button>
                <button class="mpc-clear-selection-button">
                    選択をクリア
                </button>
            </div>
        `;

        quoteHtml += '</div>';

        quoteContainer.html(quoteHtml);
        quoteContainer.addClass('active');

        // Bind booking button click
        quoteContainer.find('.mpc-booking-button').on('click', (e) => {
            const button = $(e.target);
            const eventData = {
                propertyId: button.data('property-id'),
                checkin: button.data('checkin'),
                checkout: button.data('checkout'),
                adults: button.data('adults'),
                children: button.data('children'),
                infants: button.data('infants'),
                currency: calendarData.currency
            };
            this.redirectToPortalBooking(eventData);
        });

        // Bind clear selection button
        quoteContainer.find('.mpc-clear-selection-button').on('click', () => {
            this.clearSelection(container);
        });

        // Scroll to quote display
        quoteContainer[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /**
     * Hide quote display
     */
    hideQuoteDisplay(container) {
        container.siblings('.mpc-quote-container').removeClass('active');
    }

    /**
     * Format date for Japanese display
     */
    formatDateJP(dateStr) {
        const date = new Date(dateStr);
        const year = date.getFullYear();
        const month = date.getMonth() + 1;
        const day = date.getDate();
        const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
        const dayName = dayNames[date.getDay()];

        return `${year}年${month}月${day}日 (${dayName})`;
    }

    /**
     * Format number with commas
     */
    formatNumber(num) {
        return Number(num).toLocaleString('ja-JP');
    }

    /**
     * Redirect to portal booking page (admin new booking page)
     */
    redirectToPortalBooking(eventData) {
        this.performRedirectToPortal(eventData, false);
    }

    /**
     * Direct redirect to portal booking page (for Shift+click)
     */
    redirectToPortalBookingDirect(eventData) {
        this.performRedirectToPortal(eventData, true);
    }

    /**
     * Perform the actual redirect to portal
     */
    performRedirectToPortal(eventData, isDirect = false) {
        // Debug logging
        console.log('MPC Calendar: Attempting redirect to portal', eventData);

        // Get portal URL from WordPress localization or global variable
        let portalUrl = '';

        if (typeof mpcCalendarData !== 'undefined' && mpcCalendarData.portalUrl) {
            portalUrl = mpcCalendarData.portalUrl;
            console.log('MPC Calendar: Using portalUrl from mpcCalendarData:', portalUrl);
            console.log('MPC Calendar: Full mpcCalendarData:', mpcCalendarData);
        } else if (typeof wpMinpakuConnector !== 'undefined' && wpMinpakuConnector.portalUrl) {
            portalUrl = wpMinpakuConnector.portalUrl;
            console.log('MPC Calendar: Using portalUrl from wpMinpakuConnector:', portalUrl);
        } else {
            console.warn('MPC Calendar: Portal URL not configured');
            console.log('MPC Calendar: Available globals:', {
                mpcCalendarData: typeof mpcCalendarData !== 'undefined' ? mpcCalendarData : 'undefined',
                wpMinpakuConnector: typeof wpMinpakuConnector !== 'undefined' ? wpMinpakuConnector : 'undefined'
            });

            // More detailed error information
            if (typeof mpcCalendarData !== 'undefined') {
                console.log('MPC Calendar: mpcCalendarData exists but portalUrl is:', mpcCalendarData.portalUrl);
                console.log('MPC Calendar: mpcCalendarData keys:', Object.keys(mpcCalendarData));
            }

            // Try to show error message to user with debug info
            const debugInfo = typeof mpcCalendarData !== 'undefined' ?
                `Debug: mpcCalendarData.portalUrl = ${mpcCalendarData.portalUrl}` :
                'Debug: mpcCalendarData is undefined';

            alert(`設定エラー: ポータルURLが設定されていません。\n${debugInfo}\n管理者にお問い合わせください。`);
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
            adults: eventData.adults,
            children: eventData.children,
            infants: eventData.infants,
            currency: eventData.currency,
            _mcs_nonce: nonceParam
        });

        // Add checkout date only if it's provided (not for direct single-day booking)
        if (eventData.checkout && !isDirect) {
            bookingParams.set('checkout', eventData.checkout);
        }

        // Construct the admin booking URL (WordPress admin format)
        const bookingUrl = `${portalUrl.replace(/\/$/, '')}/wp-admin/post-new.php?${bookingParams.toString()}`;

        // Debug logging
        console.log('MPC Calendar: Final booking URL:', bookingUrl);

        // Show user feedback before redirect
        if (isDirect) {
            console.log('MPC Calendar: Direct booking mode - redirecting immediately');
        } else {
            console.log('MPC Calendar: Quote mode - redirecting with full date range');
        }

        // Open in new tab/window to preserve user's current page
        const newWindow = window.open(bookingUrl, '_blank', 'noopener,noreferrer');

        // Check if popup was blocked
        if (!newWindow) {
            alert('ポップアップがブロックされました。ポップアップを許可してから再度お試しください。\n\n予約URL: ' + bookingUrl);
            console.warn('MPC Calendar: Popup blocked, showing URL to user');
        } else {
            console.log('MPC Calendar: Successfully opened new window');
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
        console.log('MPC Calendar: Button clicked');

        const propertyId = $(this).data('property-id');
        const propertyTitle = $(this).data('property-title');

        console.log('MPC Calendar: Property data', { propertyId, propertyTitle });

        if (!propertyId) {
            console.warn('Property ID not found for calendar button');
            return;
        }

        console.log('MPC Calendar: Calling showCalendarModal');
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

    console.log('MPC Calendar: showCalendarModal called', { propertyId, propertyTitle });

    // Create modal if it doesn't exist
    if ($('#wmc-calendar-modal').length === 0) {
        console.log('MPC Calendar: Creating modal');
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
                console.log('MPC Calendar: Modal calendar container found:', calendarContainer.length);

                if (calendarContainer.length && calendarContainer.attr('id')) {
                    const calendarId = '#' + calendarContainer.attr('id');
                    console.log('MPC Calendar: Initializing modal calendar:', calendarId);

                    // Add debug data attributes
                    console.log('MPC Calendar: Container data attributes:', {
                        propertyId: calendarContainer.data('property-id'),
                        showPrices: calendarContainer.data('show-prices'),
                        autoInit: calendarContainer.data('auto-init')
                    });

                    window.mpcCalendarInstances.initCalendar(calendarId);

                    // Verify initialization
                    setTimeout(() => {
                        const clickableDays = calendarContainer.find('.mcs-day--available');
                        console.log('MPC Calendar: Available clickable days found:', clickableDays.length);

                        // Check if booking navigation is configured
                        console.log('MPC Calendar: Booking configuration:', {
                            mpcCalendarData: typeof mpcCalendarData !== 'undefined' ? mpcCalendarData : 'undefined',
                            portalUrl: typeof mpcCalendarData !== 'undefined' ? mpcCalendarData.portalUrl : 'N/A'
                        });
                    }, 100);
                } else {
                    console.warn('MPC Calendar: No valid calendar container found in modal content');
                }
            } else {
                console.error('MPC Calendar: mpcCalendarInstances not available');
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
 * Load calendar content via AJAX
 */
function loadCalendarContent(propertyId, callback) {
    const $ = jQuery;

    // Check if mpcCalendarData is available
    if (typeof mpcCalendarData === 'undefined') {
        console.error('MPC Calendar: Calendar data not available');
        console.log('MPC Calendar: Available global objects:', Object.keys(window));
        callback(false, 'Configuration error: Calendar data not available');
        return;
    }

    console.log('MPC Calendar: AJAX request starting', {
        propertyId: propertyId,
        ajaxUrl: mpcCalendarData.ajaxUrl,
        nonce: mpcCalendarData.nonce
    });

    // Make AJAX request to get calendar content
    $.ajax({
        url: mpcCalendarData.ajaxUrl,
        type: 'POST',
        data: {
            action: 'mpc_get_calendar',
            property_id: propertyId,
            nonce: mpcCalendarData.nonce
        },
        success: function(response) {
            console.log('MPC Calendar: AJAX response received', response);
            if (response.success) {
                console.log('MPC Calendar: Calendar content loaded successfully');
                callback(true, response.data);
            } else {
                console.error('MPC Calendar: AJAX error:', response.data);
                console.log('MPC Calendar: Full response object:', response);
                callback(false, response.data || 'Failed to load calendar content');
            }
        },
        error: function(xhr, status, error) {
            console.error('MPC Calendar: AJAX request failed:', status, error);
            console.log('MPC Calendar: XHR object:', xhr);
            console.log('MPC Calendar: Response text:', xhr.responseText);
            callback(false, 'Network error: Failed to load calendar content');
        }
    });
}