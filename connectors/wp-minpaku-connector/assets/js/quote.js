/**
 * Quote functionality for Minpaku Connector
 *
 * @package WP_Minpaku_Connector
 */

(function($) {
    'use strict';

    // Quote functionality object
    window.MinpakuQuote = {
        // State
        selectedCheckin: null,
        selectedCheckout: null,
        currentPropertyId: null,

        // Initialize quote functionality
        init: function() {
            this.bindEvents();
            this.setupSelectionMode();
        },

        // Bind event handlers
        bindEvents: function() {
            // Handle calendar day clicks for range selection
            $(document).on('click', '.connector-calendar .mcs-day', this.handleDayClick.bind(this));

            // Handle quote form submission
            $(document).on('click', '.mpc-get-quote-btn', this.handleQuoteRequest.bind(this));

            // Handle quote modal close
            $(document).on('click', '.mpc-quote-modal-close, .mpc-quote-modal-overlay', this.closeQuoteModal.bind(this));

            // ESC key to close modal
            $(document).keydown(function(e) {
                if (e.key === 'Escape') {
                    MinpakuQuote.closeQuoteModal();
                }
            });
        },

        // Setup calendar for range selection mode
        setupSelectionMode: function() {
            $('.connector-calendar').addClass('mpc-selection-mode');
        },

        // Handle calendar day click for range selection
        handleDayClick: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $day = $(e.currentTarget);

            // Only handle available days in current month
            if ($day.hasClass('mcs-day--empty') ||
                $day.hasClass('mcs-day--past') ||
                $day.hasClass('mcs-day--full') ||
                $day.data('disabled') === 1) {
                return;
            }

            const date = $day.data('ymd');
            const propertyId = $day.data('property');

            if (!date || !propertyId) {
                return;
            }

            this.currentPropertyId = propertyId;

            // Range selection logic
            if (!this.selectedCheckin) {
                // First click - select check-in
                this.selectedCheckin = date;
                this.selectedCheckout = null;
                this.updateCalendarSelection();
            } else if (!this.selectedCheckout) {
                // Second click - select check-out
                if (date <= this.selectedCheckin) {
                    // If selected date is before or same as check-in, start over
                    this.selectedCheckin = date;
                    this.selectedCheckout = null;
                } else {
                    this.selectedCheckout = date;
                    this.showQuoteForm();
                }
                this.updateCalendarSelection();
            } else {
                // Third click - start over
                this.selectedCheckin = date;
                this.selectedCheckout = null;
                this.updateCalendarSelection();
            }
        },

        // Update calendar visual selection
        updateCalendarSelection: function() {
            // Clear previous selection
            $('.connector-calendar .mcs-day').removeClass('mpc-selected-checkin mpc-selected-checkout mpc-selected-range');

            if (this.selectedCheckin) {
                // Mark check-in date
                $(`.connector-calendar .mcs-day[data-ymd="${this.selectedCheckin}"]`).addClass('mpc-selected-checkin');

                if (this.selectedCheckout) {
                    // Mark check-out date
                    $(`.connector-calendar .mcs-day[data-ymd="${this.selectedCheckout}"]`).addClass('mpc-selected-checkout');

                    // Mark range between dates
                    const checkinDate = new Date(this.selectedCheckin);
                    const checkoutDate = new Date(this.selectedCheckout);

                    $('.connector-calendar .mcs-day').each(function() {
                        const dayDate = new Date($(this).data('ymd'));
                        if (dayDate > checkinDate && dayDate < checkoutDate) {
                            $(this).addClass('mpc-selected-range');
                        }
                    });
                }
            }
        },

        // Show quote form after range selection
        showQuoteForm: function() {
            if (!this.selectedCheckin || !this.selectedCheckout || !this.currentPropertyId) {
                return;
            }

            const checkinDate = new Date(this.selectedCheckin);
            const checkoutDate = new Date(this.selectedCheckout);
            const nights = Math.ceil((checkoutDate - checkinDate) / (1000 * 60 * 60 * 24));

            const formHtml = `
                <div class="mpc-quote-form-container">
                    <div class="mpc-quote-selection-summary">
                        <h4>${this.i18n.selectedDates || '選択された日程'}</h4>
                        <div class="mpc-date-range">
                            <span class="mpc-checkin-date">${this.formatDate(checkinDate)}</span>
                            <span class="mpc-date-separator">〜</span>
                            <span class="mpc-checkout-date">${this.formatDate(checkoutDate)}</span>
                            <span class="mpc-nights-count">${nights}${this.i18n.nights || '泊'}</span>
                        </div>
                    </div>

                    <div class="mpc-guest-form">
                        <h4>${this.i18n.guestCount || '宿泊人数'}</h4>
                        <div class="mpc-guest-inputs">
                            <div class="mpc-guest-input">
                                <label for="mpc-adults">${this.i18n.adults || '大人'}</label>
                                <select id="mpc-adults" class="mpc-guest-select">
                                    <option value="1">1</option>
                                    <option value="2" selected>2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                    <option value="6">6</option>
                                    <option value="7">7</option>
                                    <option value="8">8</option>
                                </select>
                            </div>
                            <div class="mpc-guest-input">
                                <label for="mpc-children">${this.i18n.children || '子供'}</label>
                                <select id="mpc-children" class="mpc-guest-select">
                                    <option value="0" selected>0</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mpc-quote-actions">
                        <button type="button" class="mpc-get-quote-btn mpc-btn-primary">
                            ${this.i18n.getQuote || '見積を取得'}
                        </button>
                        <button type="button" class="mpc-clear-selection-btn mpc-btn-secondary">
                            ${this.i18n.clearSelection || '選択をクリア'}
                        </button>
                    </div>
                </div>
            `;

            // Remove existing quote form
            $('.mpc-quote-form-container').remove();

            // Add form after calendar
            $('.connector-calendar').after(formHtml);

            // Bind clear selection handler
            $('.mpc-clear-selection-btn').on('click', this.clearSelection.bind(this));
        },

        // Clear date selection
        clearSelection: function() {
            this.selectedCheckin = null;
            this.selectedCheckout = null;
            this.updateCalendarSelection();
            $('.mpc-quote-form-container').remove();
        },

        // Handle quote request
        handleQuoteRequest: function(e) {
            e.preventDefault();

            const adults = parseInt($('#mpc-adults').val()) || 2;
            const children = parseInt($('#mpc-children').val()) || 0;

            this.requestQuote(this.currentPropertyId, this.selectedCheckin, this.selectedCheckout, adults, children);
        },

        // Request quote from API
        requestQuote: function(propertyId, checkin, checkout, adults, children) {
            const $btn = $('.mpc-get-quote-btn');
            const originalText = $btn.text();

            // Show loading state
            $btn.prop('disabled', true).text(this.i18n.loading || '読み込み中...');

            // Make AJAX request
            $.ajax({
                url: mpcQuoteData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpc_get_quote',
                    nonce: mpcQuoteData.nonce,
                    property_id: propertyId,
                    checkin: checkin,
                    checkout: checkout,
                    adults: adults,
                    children: children
                },
                success: (response) => {
                    if (response.success) {
                        this.showQuoteModal(response.data);
                    } else {
                        this.showError(response.data || this.i18n.quoteError || '見積の取得に失敗しました。');
                    }
                },
                error: () => {
                    this.showError(this.i18n.networkError || 'ネットワークエラーが発生しました。');
                },
                complete: () => {
                    // Reset button state
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        // Show quote modal
        showQuoteModal: function(quoteData) {
            const modalHtml = this.buildQuoteModalHtml(quoteData);

            // Remove existing modal
            $('.mpc-quote-modal').remove();

            // Add modal to body
            $('body').append(modalHtml);

            // Show modal with animation
            setTimeout(() => {
                $('.mpc-quote-modal').addClass('mpc-modal-active');
                $('body').addClass('mpc-modal-open');
            }, 10);
        },

        // Build quote modal HTML
        buildQuoteModalHtml: function(quote) {
            const checkinDate = new Date(this.selectedCheckin);
            const checkoutDate = new Date(this.selectedCheckout);

            return `
                <div class="mpc-quote-modal">
                    <div class="mpc-quote-modal-overlay"></div>
                    <div class="mpc-quote-modal-content">
                        <div class="mpc-quote-modal-header">
                            <h3>${this.i18n.quoteDetails || '宿泊料金の詳細'}</h3>
                            <button class="mpc-quote-modal-close">&times;</button>
                        </div>
                        <div class="mpc-quote-modal-body">
                            <div class="mpc-quote-summary">
                                <div class="mpc-quote-dates">
                                    <span class="mpc-checkin">${this.formatDate(checkinDate)}</span>
                                    <span class="mpc-separator">〜</span>
                                    <span class="mpc-checkout">${this.formatDate(checkoutDate)}</span>
                                    <span class="mpc-nights">${quote.nights}${this.i18n.nights || '泊'}</span>
                                </div>
                            </div>

                            <div class="mpc-quote-breakdown">
                                <h4>${this.i18n.priceBreakdown || '料金内訳'}</h4>

                                ${this.buildNightlyBreakdown(quote.breakdown)}

                                <div class="mpc-quote-subtotal">
                                    <span class="mpc-quote-label">${this.i18n.accommodationTotal || '宿泊料金小計'}</span>
                                    <span class="mpc-quote-amount">¥${this.formatPrice(quote.base_nightly_total)}</span>
                                </div>

                                ${quote.cleaning_fee > 0 ? `
                                <div class="mpc-quote-line-item">
                                    <span class="mpc-quote-label">${this.i18n.cleaningFee || '清掃料金'}</span>
                                    <span class="mpc-quote-amount">¥${this.formatPrice(quote.cleaning_fee)}</span>
                                </div>
                                ` : ''}

                                ${quote.extra_guest_total > 0 ? `
                                <div class="mpc-quote-line-item">
                                    <span class="mpc-quote-label">${this.i18n.extraGuestFee || '追加人数料金'}</span>
                                    <span class="mpc-quote-amount">¥${this.formatPrice(quote.extra_guest_total)}</span>
                                </div>
                                ` : ''}

                                ${quote.addons_total > 0 ? `
                                <div class="mpc-quote-line-item">
                                    <span class="mpc-quote-label">${this.i18n.addonsTotal || 'オプション料金'}</span>
                                    <span class="mpc-quote-amount">¥${this.formatPrice(quote.addons_total)}</span>
                                </div>
                                ` : ''}
                            </div>

                            <div class="mpc-quote-total">
                                <span class="mpc-quote-total-label">${this.i18n.totalAmount || '合計金額'}</span>
                                <span class="mpc-quote-total-amount">¥${this.formatPrice(quote.total)}</span>
                            </div>

                            <div class="mpc-quote-actions">
                                <button type="button" class="mpc-booking-disabled-btn" disabled>
                                    ${this.i18n.bookingNotAvailable || '予約機能は準備中です'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        // Build nightly breakdown HTML
        buildNightlyBreakdown: function(breakdown) {
            let html = '<div class="mpc-nightly-breakdown">';

            breakdown.forEach(night => {
                const date = new Date(night.date);
                html += `
                    <div class="mpc-nightly-item">
                        <span class="mpc-night-date">${this.formatDate(date)}</span>
                        <span class="mpc-night-price">¥${this.formatPrice(night.nightly_price)}</span>
                    </div>
                `;
            });

            html += '</div>';
            return html;
        },

        // Close quote modal
        closeQuoteModal: function() {
            $('.mpc-quote-modal').removeClass('mpc-modal-active');
            $('body').removeClass('mpc-modal-open');

            setTimeout(() => {
                $('.mpc-quote-modal').remove();
            }, 300);
        },

        // Show error message
        showError: function(message) {
            alert(message); // Simple alert for now, could be enhanced with better UI
        },

        // Format date for display
        formatDate: function(date) {
            return date.toLocaleDateString('ja-JP', {
                month: 'long',
                day: 'numeric',
                weekday: 'short'
            });
        },

        // Format price with commas
        formatPrice: function(price) {
            return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        // i18n strings (can be localized)
        i18n: {
            selectedDates: '選択された日程',
            nights: '泊',
            guestCount: '宿泊人数',
            adults: '大人',
            children: '子供',
            getQuote: '見積を取得',
            clearSelection: '選択をクリア',
            loading: '読み込み中...',
            quoteError: '見積の取得に失敗しました。',
            networkError: 'ネットワークエラーが発生しました。',
            quoteDetails: '宿泊料金の詳細',
            priceBreakdown: '料金内訳',
            accommodationTotal: '宿泊料金小計',
            cleaningFee: '清掃料金',
            extraGuestFee: '追加人数料金',
            addonsTotal: 'オプション料金',
            totalAmount: '合計金額',
            bookingNotAvailable: '予約機能は準備中です'
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        MinpakuQuote.init();
    });

})(jQuery);