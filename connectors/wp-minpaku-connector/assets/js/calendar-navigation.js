/*
 * Calendar Navigation - Month Navigation and Responsive Behavior
 * Minpaku Suite Calendar System - Fixed Navigation
 */

(function($) {
    'use strict';

    var CalendarNavigation = {
        calendars: {},

        init: function() {
            console.log('[CalendarNav] Initializing calendar navigation');
            this.bindEvents();
            this.initializeCalendars();
        },

        bindEvents: function() {
            // Navigation button events
            $(document).on('click', '.mpc-nav-prev', this.navigatePrev.bind(this));
            $(document).on('click', '.mpc-nav-next', this.navigateNext.bind(this));

            // Keyboard navigation
            $(document).on('keydown', '.mpc-responsive-calendar', this.handleKeyboard.bind(this));

            // Window resize for responsive adjustments
            $(window).on('resize', this.debounce(this.handleResize.bind(this), 250));
        },

        initializeCalendars: function() {
            $('.mpc-responsive-calendar').each(function() {
                var $calendar = $(this);
                var calendarId = $calendar.attr('id') || 'calendar-' + Math.random().toString(36).substr(2, 9);
                var propertyId = $calendar.data('property-id');
                var months = parseInt($calendar.data('months')) || 2;
                var showPrices = $calendar.data('show-prices') === '1';
                var interactions = $calendar.data('interactions') || 'modern';

                console.log('[CalendarNav] Initializing calendar:', calendarId, 'property:', propertyId);

                // Store calendar data
                CalendarNavigation.calendars[calendarId] = {
                    element: $calendar,
                    propertyId: propertyId,
                    totalMonths: months,
                    showPrices: showPrices,
                    interactions: interactions,
                    startMonth: 0
                };

                // Set ID if not present
                if (!$calendar.attr('id')) {
                    $calendar.attr('id', calendarId);
                }

                // Initialize navigation state
                CalendarNavigation.updateNavigationState(calendarId);
            });
        },

        navigatePrev: function(e) {
            e.preventDefault();
            var $calendar = $(e.target).closest('.mpc-responsive-calendar');
            var calendarId = $calendar.attr('id');
            var calendarData = this.calendars[calendarId];

            if (!calendarData) {
                console.warn('[CalendarNav] Calendar data not found for:', calendarId);
                return;
            }

            if (calendarData.startMonth > 0) {
                calendarData.startMonth--;
                console.log('[CalendarNav] Navigate prev:', calendarData.startMonth);
                this.updateCalendarDisplay(calendarId);
                this.updateNavigationState(calendarId);
            }
        },

        navigateNext: function(e) {
            e.preventDefault();
            var $calendar = $(e.target).closest('.mpc-responsive-calendar');
            var calendarId = $calendar.attr('id');
            var calendarData = this.calendars[calendarId];

            if (!calendarData) {
                console.warn('[CalendarNav] Calendar data not found for:', calendarId);
                return;
            }

            var visibleMonths = this.getVisibleMonthsCount();
            if (calendarData.startMonth + visibleMonths < 12) {
                calendarData.startMonth++;
                console.log('[CalendarNav] Navigate next:', calendarData.startMonth);
                this.updateCalendarDisplay(calendarId);
                this.updateNavigationState(calendarId);
            }
        },

        updateCalendarDisplay: function(calendarId) {
            var calendarData = this.calendars[calendarId];
            var $calendar = calendarData.element;
            var $monthsGrid = $calendar.find('.mpc-calendar-months-grid');
            var visibleMonths = this.getVisibleMonthsCount();

            console.log('[CalendarNav] Updating display for:', calendarId, 'start month:', calendarData.startMonth);

            // Add loading state
            $monthsGrid.addClass('mpc-loading');

            // Generate new months HTML
            this.generateMonthsHTML(calendarId, calendarData.startMonth, visibleMonths, calendarData);
        },

        generateMonthsHTML: function(calendarId, startMonth, visibleMonths, calendarData) {
            var self = this;
            var $monthsGrid = calendarData.element.find('.mpc-calendar-months-grid');
            var monthsHtml = '';

            for (var i = 0; i < visibleMonths; i++) {
                var monthOffset = startMonth + i;
                var monthDate = new Date();
                monthDate.setMonth(monthDate.getMonth() + monthOffset);

                var year = monthDate.getFullYear();
                var month = monthDate.getMonth() + 1; // JavaScript months are 0-indexed
                var monthTitle = this.formatMonthTitle(monthDate);

                console.log('[CalendarNav] Generating month:', year, month, monthTitle);

                monthsHtml += this.getMonthHTML(year, month, monthTitle, monthOffset, calendarData);
            }

            // Animate transition
            $monthsGrid.fadeOut(200, function() {
                $monthsGrid.html(monthsHtml);
                $monthsGrid.removeClass('mpc-loading');

                // Re-populate calendar days via AJAX or existing data
                self.loadCalendarData(calendarId, startMonth, visibleMonths, calendarData);

                $monthsGrid.fadeIn(200);

                // Re-initialize interactions if needed
                if (calendarData.interactions === 'modern' && window.minpakuCalendarData) {
                    // Trigger calendar interactions initialization
                    $(document).trigger('minpaku:calendar:refreshed', [calendarData.element]);
                }
            });
        },

        loadCalendarData: function(calendarId, startMonth, visibleMonths, calendarData) {
            // Check if we're dealing with connector calendar that needs AJAX
            if (calendarData.element.hasClass('connector-calendar') && calendarData.propertyId) {
                console.log('[CalendarNav] Loading connector calendar data via AJAX');
                this.loadConnectorCalendarData(calendarId, startMonth, visibleMonths, calendarData);
            } else {
                console.log('[CalendarNav] Loading portal calendar data');
                this.loadPortalCalendarData(calendarId, startMonth, visibleMonths, calendarData);
            }
        },

        loadConnectorCalendarData: function(calendarId, startMonth, visibleMonths, calendarData) {
            // This would make AJAX calls to get calendar data for specific months
            // For now, we'll regenerate the static structure and let existing systems populate it
            for (var i = 0; i < visibleMonths; i++) {
                var monthOffset = startMonth + i;
                var monthDate = new Date();
                monthDate.setMonth(monthDate.getMonth() + monthOffset);
                var year = monthDate.getFullYear();
                var month = monthDate.getMonth() + 1;

                // Trigger calendar day generation for this month
                this.generateCalendarDays(calendarId, year, month, calendarData);
            }
        },

        loadPortalCalendarData: function(calendarId, startMonth, visibleMonths, calendarData) {
            // Similar to connector but for portal side
            for (var i = 0; i < visibleMonths; i++) {
                var monthOffset = startMonth + i;
                var monthDate = new Date();
                monthDate.setMonth(monthDate.getMonth() + monthOffset);
                var year = monthDate.getFullYear();
                var month = monthDate.getMonth() + 1;

                // Trigger calendar day generation for this month
                this.generateCalendarDays(calendarId, year, month, calendarData);
            }
        },

        generateCalendarDays: function(calendarId, year, month, calendarData) {
            // Generate calendar days for a specific month
            var $monthContainer = calendarData.element.find('.mpc-calendar-month[data-year="' + year + '"][data-month="' + month + '"]');
            var $grid = $monthContainer.find('.mpc-calendar-grid');

            if ($grid.length === 0) return;

            // Calculate calendar structure
            var firstDay = new Date(year, month - 1, 1);
            var lastDay = new Date(year, month, 0);
            var startOfWeek = new Date(firstDay);
            startOfWeek.setDate(startOfWeek.getDate() - firstDay.getDay());

            var endOfWeek = new Date(lastDay);
            endOfWeek.setDate(endOfWeek.getDate() + (6 - lastDay.getDay()));

            var currentDate = new Date(startOfWeek);
            var daysHtml = '';

            while (currentDate <= endOfWeek) {
                var weekHtml = '<div class="mpc-calendar-week">';

                for (var day = 0; day < 7; day++) {
                    var isCurrentMonth = (currentDate.getMonth() === month - 1);
                    var isPast = (currentDate < new Date().setHours(0, 0, 0, 0));
                    var dateString = currentDate.toISOString().split('T')[0];

                    var dayClasses = ['mcs-day'];
                    if (!isCurrentMonth) dayClasses.push('mcs-day--empty');
                    if (isPast) dayClasses.push('mcs-day--past');

                    // Add day type classes
                    var dayOfWeek = currentDate.getDay();
                    if (isCurrentMonth && !isPast) {
                        if (dayOfWeek === 0) {
                            dayClasses.push('mcs-day--sun');
                        } else if (dayOfWeek === 6) {
                            dayClasses.push('mcs-day--sat');
                        } else {
                            dayClasses.push('mcs-day--weekday');
                        }
                    }

                    weekHtml += '<div class="' + dayClasses.join(' ') + '" data-ymd="' + dateString + '">';
                    weekHtml += '<span class="mcs-day-number">' + currentDate.getDate() + '</span>';

                    // Add price placeholder if needed
                    if (isCurrentMonth && !isPast && calendarData.showPrices) {
                        weekHtml += '<span class="mcs-day-price">¥' + this.getEstimatedPrice(dayOfWeek) + '</span>';
                    }

                    weekHtml += '</div>';

                    currentDate.setDate(currentDate.getDate() + 1);
                }

                weekHtml += '</div>';
                daysHtml += weekHtml;
            }

            // Replace existing days with new ones
            $grid.find('.mpc-calendar-week').remove();
            $grid.append(daysHtml);
        },

        getEstimatedPrice: function(dayOfWeek) {
            // Simple price estimation based on day of week
            var basePrice = 15000;
            if (dayOfWeek === 6) return basePrice + 2000; // Saturday
            if (dayOfWeek === 0) return basePrice + 1000; // Sunday
            return basePrice; // Weekday
        },

        getMonthHTML: function(year, month, monthTitle, monthIndex, calendarData) {
            return '<div class="mpc-calendar-month" data-year="' + year + '" data-month="' + month + '" data-month-index="' + monthIndex + '">' +
                   '<h3 class="mpc-calendar-month-title">' + monthTitle + '</h3>' +
                   '<div class="mpc-calendar-grid">' +
                   '<div class="mpc-calendar-day-headers">' +
                   '<div class="mpc-calendar-day-header">日</div>' +
                   '<div class="mpc-calendar-day-header">月</div>' +
                   '<div class="mpc-calendar-day-header">火</div>' +
                   '<div class="mpc-calendar-day-header">水</div>' +
                   '<div class="mpc-calendar-day-header">木</div>' +
                   '<div class="mpc-calendar-day-header">金</div>' +
                   '<div class="mpc-calendar-day-header">土</div>' +
                   '</div>' +
                   '</div>' +
                   '</div>';
        },

        updateNavigationState: function(calendarId) {
            var calendarData = this.calendars[calendarId];
            var $calendar = calendarData.element;
            var visibleMonths = this.getVisibleMonthsCount();
            var $prevBtn = $calendar.find('.mpc-nav-prev');
            var $nextBtn = $calendar.find('.mpc-nav-next');

            // Update button states
            $prevBtn.prop('disabled', calendarData.startMonth <= 0);
            $nextBtn.prop('disabled', calendarData.startMonth + visibleMonths >= 12);

            // Update accessibility
            $prevBtn.attr('aria-disabled', calendarData.startMonth <= 0);
            $nextBtn.attr('aria-disabled', calendarData.startMonth + visibleMonths >= 12);

            // Update visual states
            $prevBtn.toggleClass('mpc-nav-disabled', calendarData.startMonth <= 0);
            $nextBtn.toggleClass('mpc-nav-disabled', calendarData.startMonth + visibleMonths >= 12);

            console.log('[CalendarNav] Navigation state updated for:', calendarId, 'start:', calendarData.startMonth, 'visible:', visibleMonths);
        },

        getVisibleMonthsCount: function() {
            // Return number of months visible based on screen size
            if (window.innerWidth >= 1024) {
                return 2; // Desktop: 2 months (2 columns)
            } else {
                return 2; // Mobile: 2 months (1 column each)
            }
        },

        formatMonthTitle: function(date) {
            var year = date.getFullYear();
            var month = date.getMonth() + 1;
            return year + '年' + month + '月';
        },

        handleKeyboard: function(e) {
            var $calendar = $(e.target).closest('.mpc-responsive-calendar');

            switch(e.which) {
                case 37: // Left arrow
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        $calendar.find('.mpc-nav-prev').click();
                    }
                    break;
                case 39: // Right arrow
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        $calendar.find('.mpc-nav-next').click();
                    }
                    break;
            }
        },

        handleResize: function() {
            console.log('[CalendarNav] Window resized, updating calendars');

            for (var calendarId in this.calendars) {
                this.updateNavigationState(calendarId);
            }
        },

        // Utility function for debouncing resize events
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        CalendarNavigation.init();
    });

    // Export for external use
    window.CalendarNavigation = CalendarNavigation;

})(jQuery);