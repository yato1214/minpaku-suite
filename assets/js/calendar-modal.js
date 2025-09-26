/**
 * Portal-side Calendar Modal Functionality
 * For MinPaku Suite [mcs_availability modal="true"] shortcode
 */

(function($) {
    'use strict';

    // Initialize modal functionality when document is ready
    $(document).ready(function() {
        console.log('[MCS Calendar Modal] Initializing...');

        // Initialize modal buttons
        initModalButtons();

        // Ensure modal container exists
        ensureModalContainer();
    });

    /**
     * Initialize modal calendar buttons
     */
    function initModalButtons() {
        $(document).off('click.mcs-modal', '.mcs-calendar-modal-button');
        $(document).on('click.mcs-modal', '.mcs-calendar-modal-button', function(e) {
            e.preventDefault();

            const $button = $(this);
            const propertyId = $button.data('property-id');
            const propertyTitle = $button.data('property-title') || 'Property';
            const modalId = $button.data('modal-id') || 'mcs-modal-' + propertyId;
            const months = $button.data('months') || 2;
            const showPrices = $button.data('show-prices') !== 'false';
            const adults = $button.data('adults') || 2;
            const children = $button.data('children') || 0;
            const infants = $button.data('infants') || 0;
            const currency = $button.data('currency') || 'JPY';

            console.log('[MCS Calendar Modal] Button clicked for property:', propertyId);

            // Show loading state
            $button.prop('disabled', true);
            $button.find('.mcs-calendar-text').text('読み込み中...');

            // Load calendar content via AJAX
            loadModalCalendar({
                property_id: propertyId,
                property_title: propertyTitle,
                modal_id: modalId,
                months: months,
                show_prices: showPrices,
                adults: adults,
                children: children,
                infants: infants,
                currency: currency
            }).then(function(html) {
                // Reset button state
                $button.prop('disabled', false);
                $button.find('.mcs-calendar-text').text('空室カレンダーを見る');

                // Show modal
                showModal(modalId, propertyTitle, html);
            }).catch(function(error) {
                console.error('[MCS Calendar Modal] Error:', error);

                // Reset button state
                $button.prop('disabled', false);
                $button.find('.mcs-calendar-text').text('空室カレンダーを見る');

                // Show error message
                alert('カレンダーを読み込めませんでした。しばらく時間をおいて再度お試しください。');
            });
        });
    }

    /**
     * Load calendar content via AJAX
     */
    function loadModalCalendar(params) {
        return new Promise(function(resolve, reject) {
            console.log('[MCS Calendar Modal] Loading calendar for:', params);

            $.ajax({
                url: mcsCalendarModal.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mcs_get_calendar_modal',
                    nonce: mcsCalendarModal.nonce,
                    property_id: params.property_id,
                    months: params.months,
                    show_prices: params.show_prices ? 'true' : 'false',
                    adults: params.adults,
                    children: params.children,
                    infants: params.infants,
                    currency: params.currency
                },
                success: function(response) {
                    console.log('[MCS Calendar Modal] AJAX success:', response);

                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(response.data || 'Unknown error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[MCS Calendar Modal] AJAX error:', error);
                    reject(error);
                }
            });
        });
    }

    /**
     * Show modal with calendar content
     */
    function showModal(modalId, title, content) {
        console.log('[MCS Calendar Modal] Showing modal:', modalId);

        const modalHtml = `
            <div id="${modalId}" class="mcs-modal-overlay">
                <div class="mcs-modal-dialog">
                    <div class="mcs-modal-content">
                        <div class="mcs-modal-header">
                            <h3 class="mcs-modal-title">${escapeHtml(title)} - 空室カレンダー</h3>
                            <button type="button" class="mcs-modal-close" aria-label="閉じる">&times;</button>
                        </div>
                        <div class="mcs-modal-body">
                            ${content}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal
        $('#' + modalId).remove();

        // Add new modal to body
        $('body').append(modalHtml);

        // Show modal with animation
        const $modal = $('#' + modalId);
        setTimeout(function() {
            $modal.addClass('mcs-modal-show');
            $('body').addClass('mcs-modal-open');
        }, 10);

        // Close modal handlers
        $modal.find('.mcs-modal-close').on('click', function() {
            closeModal(modalId);
        });

        $modal.on('click', function(e) {
            if (e.target === this) {
                closeModal(modalId);
            }
        });

        // ESC key to close
        $(document).on('keydown.mcs-modal-' + modalId, function(e) {
            if (e.keyCode === 27) {
                closeModal(modalId);
            }
        });
    }

    /**
     * Close modal
     */
    function closeModal(modalId) {
        console.log('[MCS Calendar Modal] Closing modal:', modalId);

        const $modal = $('#' + modalId);
        $modal.removeClass('mcs-modal-show');
        $('body').removeClass('mcs-modal-open');

        // Remove modal after animation
        setTimeout(function() {
            $modal.remove();
            $(document).off('keydown.mcs-modal-' + modalId);
        }, 300);
    }

    /**
     * Ensure modal container exists
     */
    function ensureModalContainer() {
        if ($('#mcs-modal-container').length === 0) {
            $('body').append('<div id="mcs-modal-container"></div>');
        }
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Make functions globally available for debugging
    window.mcsModalDebug = {
        loadModalCalendar: loadModalCalendar,
        showModal: showModal,
        closeModal: closeModal
    };

})(jQuery);