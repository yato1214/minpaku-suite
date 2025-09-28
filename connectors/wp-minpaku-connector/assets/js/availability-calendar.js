/**
 * WP Minpaku Connector - Responsive Availability Calendar with Quote Integration
 * Features: Month navigation, responsive 2-col/1-col layout, quote API integration
 */

(function($) {
    'use strict';

    // Global calendar state
    window.WPMCAvailabilityCalendar = {
        calendars: {},
        currentStartMonth: 0,
        visibleMonths: 1, // Will be updated based on screen size
        maxMonths: 12,
        selectedDates: [],
        quoteData: null
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('[WPMC Calendar] Initializing responsive availability calendar');
        initializeCalendars();
        bindGlobalEvents();
        updateResponsiveLayout();
    });

    /**
     * Initialize all calendar instances on the page
     */
    function initializeCalendars() {
        $('.wpmc-availability').each(function() {
            const $calendar = $(this);
            const calendarId = $calendar.attr('id');
            const propertyId = $calendar.data('property-id');
            const months = parseInt($calendar.data('months')) || 2;
            const interactions = $calendar.data('interactions') || 'modern';
            const showPrices = $calendar.data('show-prices') === 'true';

            console.log('[WPMC Calendar] Initializing calendar:', calendarId, 'property:', propertyId);

            // Store calendar data
            WPMCAvailabilityCalendar.calendars[calendarId] = {
                element: $calendar,
                propertyId: propertyId,
                maxMonths: months,
                interactions: interactions,
                showPrices: showPrices,
                startMonth: 0,
                loadedData: {}
            };

            // Initialize calendar display
            renderCalendarMonths(calendarId);
            updateNavigationState(calendarId);

            // Load initial data
            loadCalendarData(calendarId, 0, WPMCAvailabilityCalendar.visibleMonths);
        });
    }

    /**
     * Bind global event handlers
     */
    function bindGlobalEvents() {
        // Navigation button clicks
        $(document).on('click', '.wpmc-nav__prev', function(e) {
            e.preventDefault();
            const calendarId = $(this).closest('.wpmc-availability').attr('id');
            navigateMonth(calendarId, 'prev');
        });

        $(document).on('click', '.wpmc-nav__next', function(e) {
            e.preventDefault();
            const calendarId = $(this).closest('.wpmc-availability').attr('id');
            navigateMonth(calendarId, 'next');
        });

        // Day click handlers
        $(document).on('click', '.wpmc-day', function(e) {
            if ($(this).hasClass('wpmc-day--outside-month') ||
                $(this).hasClass('wpmc-day--past') ||
                $(this).hasClass('wpmc-day--closed')) {
                return;
            }

            const calendarId = $(this).closest('.wpmc-availability').attr('id');
            const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];
            const date = $(this).data('date');

            if (calendarData.interactions === 'modern') {
                handleModernDayClick(calendarId, date, $(this));
            } else {
                handleLegacyDayClick(calendarId, date, $(this));
            }
        });

        // Quote panel close
        $(document).on('click', '.wpmc-quote-panel__close', function() {
            $(this).closest('.wpmc-quote-panel').hide();
            clearSelectedDates();
        });

        // Window resize for responsive layout
        $(window).on('resize', debounce(function() {
            updateResponsiveLayout();
            Object.keys(WPMCAvailabilityCalendar.calendars).forEach(function(calendarId) {
                renderCalendarMonths(calendarId);
            });
        }, 250));

        // Keyboard navigation
        $(document).on('keydown', '.wpmc-day', function(e) {
            if (e.which === 13 || e.which === 32) { // Enter or Space
                e.preventDefault();
                $(this).click();
            }
        });
    }

    /**
     * Update responsive layout based on screen size
     */
    function updateResponsiveLayout() {
        const isDesktop = window.innerWidth >= 768;
        WPMCAvailabilityCalendar.visibleMonths = isDesktop ? 2 : 1;

        console.log('[WPMC Calendar] Updated layout - visible months:', WPMCAvailabilityCalendar.visibleMonths);
    }

    /**
     * Navigate to previous/next month
     */
    function navigateMonth(calendarId, direction) {
        const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];
        if (!calendarData) return;

        const currentStart = calendarData.startMonth;
        let newStart = currentStart;

        if (direction === 'prev' && currentStart > 0) {
            newStart = currentStart - 1;
        } else if (direction === 'next' && currentStart + WPMCAvailabilityCalendar.visibleMonths < WPMCAvailabilityCalendar.maxMonths) {
            newStart = currentStart + 1;
        }

        if (newStart !== currentStart) {
            calendarData.startMonth = newStart;
            renderCalendarMonths(calendarId);
            updateNavigationState(calendarId);
            loadCalendarData(calendarId, newStart, WPMCAvailabilityCalendar.visibleMonths);
            updateDateRangeLabel(calendarId);
        }
    }

    /**
     * Render calendar months HTML
     */
    function renderCalendarMonths(calendarId) {
        const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];
        const $grid = calendarData.element.find('.wpmc-calendar-grid');
        const startMonth = calendarData.startMonth;

        let monthsHtml = '';

        for (let i = 0; i < WPMCAvailabilityCalendar.visibleMonths; i++) {
            const monthOffset = startMonth + i;
            monthsHtml += generateMonthHtml(monthOffset, calendarData);
        }

        $grid.html(monthsHtml);
        updateDateRangeLabel(calendarId);
    }

    /**
     * Generate HTML for a single month
     */
    function generateMonthHtml(monthOffset, calendarData) {
        const date = new Date();
        date.setMonth(date.getMonth() + monthOffset);
        const year = date.getFullYear();
        const month = date.getMonth() + 1;
        const monthTitle = year + '年' + month + '月';

        let html = `<div class="wpmc-month" data-month-offset="${monthOffset}">`;
        html += `<div class="wpmc-month__header">`;
        html += `<h3 class="wpmc-month__title">${monthTitle}</h3>`;
        html += `</div>`;

        // Day headers
        html += `<div class="wpmc-calendar__headers">`;
        const dayHeaders = ['日', '月', '火', '水', '木', '金', '土'];
        dayHeaders.forEach(function(header) {
            html += `<div class="wpmc-calendar__header">${header}</div>`;
        });
        html += `</div>`;

        // Calendar days
        html += generateCalendarDays(year, month, calendarData);
        html += `</div>`;

        return html;
    }

    /**
     * Generate calendar days for a month
     */
    function generateCalendarDays(year, month, calendarData) {
        const firstDay = new Date(year, month - 1, 1);
        const lastDay = new Date(year, month, 0);
        const startOfWeek = new Date(firstDay);
        startOfWeek.setDate(startOfWeek.getDate() - firstDay.getDay());
        const endOfWeek = new Date(lastDay);
        endOfWeek.setDate(endOfWeek.getDate() + (6 - lastDay.getDay()));

        let html = '';
        let currentDate = new Date(startOfWeek);

        while (currentDate <= endOfWeek) {
            html += '<div class="wpmc-calendar__week">';

            for (let day = 0; day < 7; day++) {
                const isCurrentMonth = currentDate.getMonth() === month - 1;
                const isPast = currentDate < new Date().setHours(0, 0, 0, 0);
                const dateString = currentDate.toISOString().split('T')[0];
                const dayOfWeek = currentDate.getDay();

                let dayClasses = ['wpmc-day'];
                if (!isCurrentMonth) dayClasses.push('wpmc-day--outside-month');
                if (isPast) dayClasses.push('wpmc-day--past');

                // Add availability status (will be updated by loadCalendarData)
                if (isCurrentMonth && !isPast) {
                    dayClasses.push('wpmc-day--available'); // Default state
                }

                html += `<div class="${dayClasses.join(' ')}" data-date="${dateString}" tabindex="0">`;
                html += `<span class="wpmc-day__number">${currentDate.getDate()}</span>`;

                // Price badge (will be populated by data loading)
                if (isCurrentMonth && !isPast && calendarData.showPrices) {
                    html += `<span class="wpmc-day__price">¥-</span>`;
                }

                html += '</div>';
                currentDate.setDate(currentDate.getDate() + 1);
            }

            html += '</div>';
        }

        return html;
    }

    /**
     * Load calendar data from API
     */
    function loadCalendarData(calendarId, startMonth, monthCount) {
        const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];
        if (!calendarData) return;

        // Show loading state
        showLoadingState(calendarId, true);

        // Calculate date range
        const startDate = new Date();
        startDate.setMonth(startDate.getMonth() + startMonth);
        startDate.setDate(1);

        const endDate = new Date();
        endDate.setMonth(endDate.getMonth() + startMonth + monthCount);
        endDate.setDate(0);

        // AJAX request to get availability data
        $.ajax({
            url: wpmcAvailability.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmc_get_availability',
                nonce: wpmcAvailability.nonce,
                property_id: calendarData.propertyId,
                start_date: startDate.toISOString().split('T')[0],
                end_date: endDate.toISOString().split('T')[0]
            },
            success: function(response) {
                if (response.success && response.data) {
                    updateCalendarWithData(calendarId, response.data);
                    calendarData.loadedData = response.data;
                } else {
                    console.error('[WPMC Calendar] Failed to load calendar data:', response);
                    showError(calendarId, wpmcAvailability.i18n.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('[WPMC Calendar] AJAX error:', error);
                showError(calendarId, wpmcAvailability.i18n.error);
            },
            complete: function() {
                showLoadingState(calendarId, false);
            }
        });
    }

    /**
     * Update calendar with loaded data
     */
    function updateCalendarWithData(calendarId, data) {
        const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];
        const $calendar = calendarData.element;

        // Update each day with availability and pricing data
        Object.keys(data).forEach(function(dateString) {
            const dayData = data[dateString];
            const $day = $calendar.find(`.wpmc-day[data-date="${dateString}"]`);

            if ($day.length) {
                // Remove existing status classes
                $day.removeClass('wpmc-day--available wpmc-day--occupied wpmc-day--pending wpmc-day--closed');

                // Add appropriate status class
                if (dayData.status === 'available') {
                    $day.addClass('wpmc-day--available');
                } else if (dayData.status === 'occupied') {
                    $day.addClass('wpmc-day--occupied');
                } else if (dayData.status === 'pending') {
                    $day.addClass('wpmc-day--pending');
                } else if (dayData.status === 'closed') {
                    $day.addClass('wpmc-day--closed');
                }

                // Update price if available and day is bookable
                if (dayData.price && calendarData.showPrices && dayData.status === 'available') {
                    const $price = $day.find('.wpmc-day__price');
                    if ($price.length) {
                        $price.text('¥' + Number(dayData.price).toLocaleString());
                    }
                }

                // Set accessibility attributes
                $day.attr('aria-label',
                    dayData.status === 'available' ? wpmcAvailability.i18n.clickToSelect :
                    dayData.status === 'occupied' ? wpmcAvailability.i18n.occupied :
                    dayData.status === 'pending' ? wpmcAvailability.i18n.pending :
                    wpmcAvailability.i18n.closed
                );
            }
        });
    }

    /**
     * Handle modern interaction (quote integration)
     */
    function handleModernDayClick(calendarId, date, $day) {
        if (!$day.hasClass('wpmc-day--available')) return;

        // Clear previous selections
        clearSelectedDates();

        // Select this date
        $day.addClass('wpmc-day--selected');
        WPMCAvailabilityCalendar.selectedDates = [date];

        // Request quote for single night
        requestQuote(calendarId, date, date);
    }

    /**
     * Handle legacy interaction (direct booking navigation)
     */
    function handleLegacyDayClick(calendarId, date, $day) {
        if (!$day.hasClass('wpmc-day--available')) return;

        const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];

        // Construct booking URL (portal URL + booking path)
        const bookingUrl = `${getPortalUrl()}/booking/new?property_id=${calendarData.propertyId}&check_in=${date}`;

        // Open in new tab
        window.open(bookingUrl, '_blank', 'noopener,noreferrer');
    }

    /**
     * Request quote from API
     */
    function requestQuote(calendarId, startDate, endDate) {
        const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];

        $.ajax({
            url: wpmcAvailability.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmc_get_quote',
                nonce: wpmcAvailability.nonce,
                property_id: calendarData.propertyId,
                check_in: startDate,
                check_out: endDate,
                guests: 2 // Default guest count
            },
            success: function(response) {
                if (response.success && response.data) {
                    displayQuotePanel(calendarId, response.data);
                } else {
                    console.error('[WPMC Calendar] Quote request failed:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('[WPMC Calendar] Quote AJAX error:', error);
            }
        });
    }

    /**
     * Display quote panel
     */
    function displayQuotePanel(calendarId, quoteData) {
        const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];
        const $quotePanel = calendarData.element.siblings('.wpmc-quote-panel');

        if ($quotePanel.length) {
            // Populate quote content
            let quoteHtml = '<div class="wpmc-quote__summary">';
            quoteHtml += `<p><strong>${wpmcAvailability.i18n.priceLabel}:</strong> ¥${Number(quoteData.total_price || 0).toLocaleString()}</p>`;
            if (quoteData.nights) {
                quoteHtml += `<p><strong>宿泊日数:</strong> ${quoteData.nights}泊</p>`;
            }
            if (quoteData.check_in && quoteData.check_out) {
                quoteHtml += `<p><strong>日程:</strong> ${quoteData.check_in} 〜 ${quoteData.check_out}</p>`;
            }
            quoteHtml += '</div>';

            $quotePanel.find('.wpmc-quote-panel__content').html(quoteHtml);
            $quotePanel.show();

            // Scroll to quote panel
            $quotePanel[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Update navigation button states
     */
    function updateNavigationState(calendarId) {
        const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];
        const $calendar = calendarData.element;
        const $prevBtn = $calendar.find('.wpmc-nav__prev');
        const $nextBtn = $calendar.find('.wpmc-nav__next');

        const canGoPrev = calendarData.startMonth > 0;
        const canGoNext = calendarData.startMonth + WPMCAvailabilityCalendar.visibleMonths < WPMCAvailabilityCalendar.maxMonths;

        $prevBtn.prop('disabled', !canGoPrev);
        $nextBtn.prop('disabled', !canGoNext);

        $prevBtn.attr('aria-disabled', !canGoPrev);
        $nextBtn.attr('aria-disabled', !canGoNext);
    }

    /**
     * Update date range label in navigation
     */
    function updateDateRangeLabel(calendarId) {
        const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];
        const $dateRange = calendarData.element.find('.wpmc-nav__date-range');

        const startDate = new Date();
        startDate.setMonth(startDate.getMonth() + calendarData.startMonth);

        const endDate = new Date();
        endDate.setMonth(endDate.getMonth() + calendarData.startMonth + WPMCAvailabilityCalendar.visibleMonths - 1);

        const startMonth = startDate.getFullYear() + '年' + (startDate.getMonth() + 1) + '月';
        const endMonth = endDate.getFullYear() + '年' + (endDate.getMonth() + 1) + '月';

        const rangeText = startMonth === endMonth ? startMonth : startMonth + ' 〜 ' + endMonth;
        $dateRange.text(rangeText);
    }

    /**
     * Clear selected dates
     */
    function clearSelectedDates() {
        $('.wpmc-day--selected, .wpmc-day--range-start, .wpmc-day--range-end, .wpmc-day--range-middle')
            .removeClass('wpmc-day--selected wpmc-day--range-start wpmc-day--range-end wpmc-day--range-middle');
        WPMCAvailabilityCalendar.selectedDates = [];
    }

    /**
     * Show/hide loading state
     */
    function showLoadingState(calendarId, show) {
        const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];
        const $loading = calendarData.element.find('.wpmc-loading');
        const $grid = calendarData.element.find('.wpmc-calendar-grid');

        if (show) {
            $loading.show();
            $grid.css('opacity', '0.5');
        } else {
            $loading.hide();
            $grid.css('opacity', '1');
        }
    }

    /**
     * Show error message
     */
    function showError(calendarId, message) {
        const calendarData = WPMCAvailabilityCalendar.calendars[calendarId];
        const $grid = calendarData.element.find('.wpmc-calendar-grid');

        $grid.html(`<div class="wpmc-error">${message}</div>`);
    }

    /**
     * Get portal URL for legacy interactions
     */
    function getPortalUrl() {
        // This should be configured via wp_localize_script or similar
        return window.wpmcPortalUrl || '';
    }

    /**
     * Debounce utility function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Export for external access
    window.WPMCAvailabilityCalendar.refresh = function(calendarId) {
        if (WPMCAvailabilityCalendar.calendars[calendarId]) {
            renderCalendarMonths(calendarId);
            loadCalendarData(calendarId,
                WPMCAvailabilityCalendar.calendars[calendarId].startMonth,
                WPMCAvailabilityCalendar.visibleMonths);
        }
    };

})(jQuery);