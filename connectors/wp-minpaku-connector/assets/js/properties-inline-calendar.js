/*
 * Properties Inline Calendar - Toggle and AJAX functionality
 * Minpaku Suite Connector - Property Cards with Inline Calendar
 */

(function($) {
    'use strict';

    var PropertiesInlineCalendar = {
        activePropertyId: null,
        loadingTimeouts: {},

        init: function() {
            console.log('[PropertiesInlineCalendar] Initializing inline calendar functionality');
            this.bindEvents();
        },

        bindEvents: function() {
            // Toggle button click
            $(document).on('click', '.wmc-availability-toggle', this.handleToggleClick.bind(this));

            // Close button in calendars
            $(document).on('click', '.wmc-inline-calendar-close', this.handleCloseClick.bind(this));

            // Keyboard support
            $(document).on('keydown', '.wmc-availability-toggle', this.handleKeydown.bind(this));

            // Click outside to close
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.wmc-property-card').length) {
                    PropertiesInlineCalendar.closeAllCalendars();
                }
            });
        },

        handleToggleClick: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(e.currentTarget);
            var $card = $button.closest('.wmc-property-card');
            var $calendar = $card.find('.wmc-inline-calendar');
            var propertyId = $button.data('property-id');
            var propertyTitle = $button.data('property-title') || 'Property';
            var months = parseInt($button.data('calendar-months')) || 1;

            console.log('[PropertiesInlineCalendar] Toggle clicked for property:', propertyId);

            // If this calendar is already open, close it
            if ($button.hasClass('wmc-active')) {
                this.closeCalendar($card);
                return;
            }

            // Close any other open calendars first (only one at a time)
            this.closeAllCalendars();

            // Open this calendar
            this.openCalendar($card, propertyId, propertyTitle, months);
        },

        handleCloseClick: function(e) {
            e.preventDefault();
            var $card = $(e.target).closest('.wmc-property-card');
            this.closeCalendar($card);
        },

        handleKeydown: function(e) {
            if (e.which === 13 || e.which === 32) { // Enter or Space
                e.preventDefault();
                $(e.target).click();
            } else if (e.which === 27) { // Escape
                e.preventDefault();
                this.closeAllCalendars();
            }
        },

        openCalendar: function($card, propertyId, propertyTitle, months) {
            var $button = $card.find('.wmc-availability-toggle');
            var $calendar = $card.find('.wmc-inline-calendar');

            console.log('[PropertiesInlineCalendar] Opening calendar for property:', propertyId, 'months:', months);

            // Update button state
            $button.addClass('wmc-active');
            $button.find('.wmc-availability-text').text(
                window.mpcInlineCalendarData?.texts?.hideAvailability || '閉じる'
            );

            // Show calendar with loading state
            $calendar.slideDown(300);
            this.activePropertyId = propertyId;

            // Load calendar content via AJAX
            this.loadCalendarContent($card, propertyId, months);

            // Update accessibility
            $button.attr('aria-expanded', 'true');
            $calendar.attr('aria-hidden', 'false');
        },

        closeCalendar: function($card) {
            var $button = $card.find('.wmc-availability-toggle');
            var $calendar = $card.find('.wmc-inline-calendar');

            console.log('[PropertiesInlineCalendar] Closing calendar for card');

            // Update button state
            $button.removeClass('wmc-active');
            $button.find('.wmc-availability-text').text(
                window.mpcInlineCalendarData?.texts?.showAvailability || '空き状況を見る'
            );

            // Hide calendar
            $calendar.slideUp(300);

            // Clear active property
            if (this.activePropertyId === $button.data('property-id')) {
                this.activePropertyId = null;
            }

            // Update accessibility
            $button.attr('aria-expanded', 'false');
            $calendar.attr('aria-hidden', 'true');
        },

        closeAllCalendars: function() {
            console.log('[PropertiesInlineCalendar] Closing all calendars');

            $('.wmc-property-card').each(function() {
                var $card = $(this);
                var $button = $card.find('.wmc-availability-toggle');

                if ($button.hasClass('wmc-active')) {
                    PropertiesInlineCalendar.closeCalendar($card);
                }
            });

            this.activePropertyId = null;
        },

        loadCalendarContent: function($card, propertyId, months) {
            var $calendar = $card.find('.wmc-inline-calendar');
            var $loading = $calendar.find('.wmc-inline-calendar-loading');

            // Show loading state
            $loading.show().html(
                '<div class="wmc-loading-spinner"></div>' +
                '<p>' + (window.mpcInlineCalendarData?.texts?.loading || '読み込み中...') + '</p>'
            );

            // Clear any existing timeout
            if (this.loadingTimeouts[propertyId]) {
                clearTimeout(this.loadingTimeouts[propertyId]);
            }

            // Set loading timeout to prevent infinite loading
            this.loadingTimeouts[propertyId] = setTimeout(function() {
                $loading.html('<p class="wmc-error">タイムアウト: カレンダーの読み込みに時間がかかっています。</p>');
            }, 10000); // 10 second timeout

            // Make AJAX request
            var ajaxData = {
                action: 'mpc_get_inline_calendar',
                property_id: propertyId,
                months: months,
                nonce: window.mpcInlineCalendarData?.nonce || ''
            };

            console.log('[PropertiesInlineCalendar] Making AJAX request:', ajaxData);

            $.ajax({
                url: window.mpcInlineCalendarData?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: ajaxData,
                timeout: 15000,
                success: function(response) {
                    PropertiesInlineCalendar.handleCalendarResponse($card, propertyId, response);
                },
                error: function(xhr, status, error) {
                    PropertiesInlineCalendar.handleCalendarError($card, propertyId, error);
                }
            });
        },

        handleCalendarResponse: function($card, propertyId, response) {
            var $calendar = $card.find('.wmc-inline-calendar');
            var $loading = $calendar.find('.wmc-inline-calendar-loading');

            // Clear loading timeout
            if (this.loadingTimeouts[propertyId]) {
                clearTimeout(this.loadingTimeouts[propertyId]);
                delete this.loadingTimeouts[propertyId];
            }

            console.log('[PropertiesInlineCalendar] AJAX response received:', response);

            if (response.success && response.data) {
                // Hide loading and show calendar
                $loading.hide();

                // Add close button to calendar content
                var calendarHtml = response.data;
                var closeButton = '<button class="wmc-inline-calendar-close" aria-label="閉じる">' +
                                 '<span aria-hidden="true">&times;</span>' +
                                 '</button>';

                calendarHtml = closeButton + calendarHtml;

                // Insert calendar content
                $calendar.append('<div class="wmc-inline-calendar-content">' + calendarHtml + '</div>');

                // Initialize calendar interactions if available
                if (window.minpakuCalendarData && typeof window.initializeCalendarInteractions === 'function') {
                    window.initializeCalendarInteractions($calendar);
                }

                // Trigger custom event for other scripts
                $(document).trigger('mpc:inline-calendar:loaded', [$card, propertyId]);

            } else {
                this.handleCalendarError($card, propertyId, response.data || 'Unknown error');
            }
        },

        handleCalendarError: function($card, propertyId, error) {
            var $calendar = $card.find('.wmc-inline-calendar');
            var $loading = $calendar.find('.wmc-inline-calendar-loading');

            // Clear loading timeout
            if (this.loadingTimeouts[propertyId]) {
                clearTimeout(this.loadingTimeouts[propertyId]);
                delete this.loadingTimeouts[propertyId];
            }

            console.error('[PropertiesInlineCalendar] Calendar loading error:', error);

            var errorMessage = window.mpcInlineCalendarData?.texts?.error || 'エラーが発生しました';

            $loading.html(
                '<div class="wmc-error">' +
                '<p><strong>' + errorMessage + '</strong></p>' +
                '<p>' + error + '</p>' +
                '<button class="wmc-retry-button" data-property-id="' + propertyId + '">再試行</button>' +
                '</div>'
            );

            // Retry button functionality
            $loading.on('click', '.wmc-retry-button', function(e) {
                e.preventDefault();
                var retryPropertyId = $(this).data('property-id');
                var $retryCard = $(this).closest('.wmc-property-card');
                var $retryButton = $retryCard.find('.wmc-availability-toggle');
                var retryMonths = parseInt($retryButton.data('calendar-months')) || 1;

                PropertiesInlineCalendar.loadCalendarContent($retryCard, retryPropertyId, retryMonths);
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        PropertiesInlineCalendar.init();
    });

    // Export for external use
    window.PropertiesInlineCalendar = PropertiesInlineCalendar;

})(jQuery);