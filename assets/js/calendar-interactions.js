/**
 * Unified Calendar Interactions for MinPaku Suite
 * Supports both portal and connector calendars with modal/inline modes
 *
 * Features:
 * - Single click: 1-night quote preview
 * - Double click/drag/long press: Range selection with quote
 * - Accessibility: ARIA, keyboard navigation
 * - Mobile: Touch gestures with 500ms long press
 * - i18n: Japanese text support
 *
 * @package MinpakuSuite
 */

(function(window, document) {
    'use strict';

    /**
     * Unified Calendar Interactions Manager
     */
    class CalendarInteractions {
        constructor(rootElement, options = {}) {
            this.root = rootElement;
            this.options = {
                mode: options.mode || this.detectMode(),
                canBook: options.canBook || false,
                apiBase: options.apiBase || '/wp-json/minpaku/v1',
                propertyId: options.propertyId || null,
                texts: options.texts || {},
                isConnector: options.isConnector || false,
                ...options
            };

            // State management
            this.state = {
                selectedCheckin: null,
                selectedCheckout: null,
                isSelecting: false,
                isDragging: false,
                longPressTimer: null,
                clickTimer: null,
                lastTouchTarget: null
            };

            // Texts with defaults (will be overridden by localized data)
            const localizedTexts = (typeof window.mcsCalendarData !== 'undefined' && window.mcsCalendarData.texts)
                ? window.mcsCalendarData.texts
                : {};

            this.texts = {
                loading: '読み込み中...',
                nightsLabel: '泊',
                adultsLabel: '大人',
                childrenLabel: '子供',
                totalLabel: '合計金額',
                getQuoteLabel: '見積を取得',
                clearSelectionLabel: '選択をクリア',
                bookingDisabled: '予約機能は準備中です',
                quotePreview: '見積プレビュー',
                accommodationFee: '宿泊料金',
                cleaningFee: '清掃料金',
                extraGuestFee: '追加人数料金',
                errorTitle: 'エラー',
                dateUnavailable: 'この日程は満室です',
                occupancyExceeded: '定員を超えています',
                networkError: 'ネットワークエラーが発生しました',
                ...localizedTexts,
                ...this.options.texts
            };

            this.init();
        }

        /**
         * Debug helper - only logs for administrators
         */
        debug(message, data = null) {
            if (typeof window.mcsCalendarData !== 'undefined' && window.mcsCalendarData.isAdmin) {
                if (data) {
                    console.debug(`[MCS Calendar] ${message}`, data);
                } else {
                    console.debug(`[MCS Calendar] ${message}`);
                }
            }
        }

        /**
         * Initialize calendar interactions
         */
        init() {
            // Guard: Only initialize on modern interactions
            const interactions = this.root.getAttribute('data-interactions');
            if (interactions !== 'modern') {
                return;
            }

            this.setupAccessibility();
            this.bindEvents();
            this.createQuotePanel();
            this.initNavigation();

            // Set data attributes (preserve existing interactions setting)
            this.root.setAttribute('data-interactions', 'modern');
            this.root.setAttribute('data-mode', this.options.mode);

            // Required debug output
            this.debug('Init', {
                el: this.root,
                propertyId: this.options.propertyId
            });
        }

        /**
         * Detect mode based on DOM context
         */
        detectMode() {
            const isInModal = this.root.closest('.modal, .dialog, [role="dialog"]');
            return isInModal ? 'modal' : 'inline';
        }

        /**
         * Setup accessibility attributes
         */
        setupAccessibility() {
            // Set calendar as grid
            this.root.setAttribute('role', 'grid');
            this.root.setAttribute('aria-label', 'カレンダー');

            // Setup grid cells
            const cells = this.root.querySelectorAll('.mcs-day');
            cells.forEach((cell, index) => {
                cell.setAttribute('role', 'gridcell');
                cell.setAttribute('tabindex', index === 0 ? '0' : '-1');

                // Set aria-disabled for unavailable days
                if (cell.classList.contains('mcs-day--full') ||
                    cell.classList.contains('mcs-day--blackout') ||
                    cell.classList.contains('mcs-day--past')) {
                    cell.setAttribute('aria-disabled', 'true');
                }

                // Add date label
                const dateStr = cell.dataset.ymd;
                if (dateStr) {
                    const date = new Date(dateStr);
                    const label = date.toLocaleDateString('ja-JP', {
                        month: 'long',
                        day: 'numeric',
                        weekday: 'short'
                    });
                    cell.setAttribute('aria-label', label);
                }
            });
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Mouse events with click/dblclick coordination
            this.root.addEventListener('click', this.handleClick.bind(this));
            this.root.addEventListener('dblclick', this.handleDoubleClick.bind(this));
            this.root.addEventListener('mousedown', this.handleMouseDown.bind(this));
            this.root.addEventListener('mousemove', this.handleMouseMove.bind(this));
            this.root.addEventListener('mouseup', this.handleMouseUp.bind(this));

            // Touch events for mobile
            this.root.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });
            this.root.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
            this.root.addEventListener('touchend', this.handleTouchEnd.bind(this));

            // Keyboard navigation
            this.root.addEventListener('keydown', this.handleKeyDown.bind(this));

            // Prevent text selection during drag
            this.root.style.userSelect = 'none';
            this.root.style.webkitUserSelect = 'none';

            // Mobile optimization
            this.root.style.touchAction = 'manipulation';
        }

        /**
         * Handle single click with dblclick coordination
         */
        handleClick(event) {
            const cell = event.target.closest('.mcs-day');
            if (!cell || this.isCellDisabled(cell)) return;

            // Clear any existing click timer
            if (this.state.clickTimer) {
                clearTimeout(this.state.clickTimer);
                this.state.clickTimer = null;
                return; // This will be handled by dblclick
            }

            // Set timer to handle single click after dblclick detection window
            this.state.clickTimer = setTimeout(() => {
                this.state.clickTimer = null;
                this.handleSingleClick(cell);
            }, 250);
        }

        /**
         * Handle double click
         */
        handleDoubleClick(event) {
            event.preventDefault();
            const cell = event.target.closest('.mcs-day');
            if (!cell || this.isCellDisabled(cell)) return;

            // Clear single click timer
            if (this.state.clickTimer) {
                clearTimeout(this.state.clickTimer);
                this.state.clickTimer = null;
            }

            this.handleRangeSelection(cell);
        }

        /**
         * Handle single click - show 1-night preview
         */
        handleSingleClick(cell) {
            const checkinDate = cell.dataset.ymd;
            if (!checkinDate) return;

            // Calculate checkout date (next day)
            const checkoutDate = this.getNextDay(checkinDate);

            this.debug('Single click preview', { checkinDate, checkoutDate });

            this.clearSelection();
            this.state.selectedCheckin = checkinDate;
            this.state.selectedCheckout = checkoutDate;

            this.updateVisualSelection();
            this.fetchQuote();
        }

        /**
         * Handle range selection (double click or drag end)
         */
        handleRangeSelection(cell) {
            const clickedDate = cell.dataset.ymd;
            if (!clickedDate) return;

            if (!this.state.selectedCheckin) {
                // First click - set as checkin
                this.state.selectedCheckin = clickedDate;
                this.state.selectedCheckout = null;
                this.debug('Range start', { clickedDate });
            } else {
                // Second click - set as checkout
                const checkin = new Date(this.state.selectedCheckin);
                const checkout = new Date(clickedDate);

                if (checkout <= checkin) {
                    // Invalid range, restart
                    this.state.selectedCheckin = clickedDate;
                    this.state.selectedCheckout = null;
                } else {
                    this.state.selectedCheckout = clickedDate;
                    this.debug('Range complete', { checkin: this.state.selectedCheckin, checkout: clickedDate });
                    this.fetchQuote();
                }
            }

            this.updateVisualSelection();
        }

        /**
         * Mouse drag handling
         */
        handleMouseDown(event) {
            const cell = event.target.closest('.mcs-day');
            if (!cell || this.isCellDisabled(cell)) return;

            this.state.isDragging = true;
            this.state.selectedCheckin = cell.dataset.ymd;
            this.state.selectedCheckout = null;

            event.preventDefault();
        }

        handleMouseMove(event) {
            if (!this.state.isDragging) return;

            const cell = event.target.closest('.mcs-day');
            if (!cell || this.isCellDisabled(cell)) return;

            const endDate = cell.dataset.ymd;
            if (endDate && endDate !== this.state.selectedCheckin) {
                const start = new Date(this.state.selectedCheckin);
                const end = new Date(endDate);

                if (end > start) {
                    this.state.selectedCheckout = endDate;
                    this.updateVisualSelection();
                }
            }
        }

        handleMouseUp(event) {
            if (this.state.isDragging) {
                this.state.isDragging = false;

                if (this.state.selectedCheckin && this.state.selectedCheckout) {
                    this.debug('Drag complete', { checkin: this.state.selectedCheckin, checkout: this.state.selectedCheckout });
                    this.fetchQuote();
                }
            }
        }

        /**
         * Touch handling for mobile
         */
        handleTouchStart(event) {
            const cell = event.target.closest('.mcs-day');
            if (!cell || this.isCellDisabled(cell)) return;

            this.state.lastTouchTarget = cell;

            // Start long press timer (500ms)
            this.state.longPressTimer = setTimeout(() => {
                this.startLongPressSelection(cell);
            }, 500);
        }

        handleTouchMove(event) {
            // Cancel long press if finger moves
            if (this.state.longPressTimer) {
                clearTimeout(this.state.longPressTimer);
                this.state.longPressTimer = null;
            }
        }

        handleTouchEnd(event) {
            // Cancel long press timer
            if (this.state.longPressTimer) {
                clearTimeout(this.state.longPressTimer);
                this.state.longPressTimer = null;

                // Handle as regular tap
                const cell = this.state.lastTouchTarget;
                if (cell) {
                    this.handleSingleClick(cell);
                }
            }
        }

        /**
         * Start long press selection mode
         */
        startLongPressSelection(cell) {
            this.debug('Long press selection started');
            this.state.isSelecting = true;
            this.state.selectedCheckin = cell.dataset.ymd;
            this.state.selectedCheckout = null;
            this.updateVisualSelection();

            // Show visual feedback
            this.root.classList.add('mcs-selecting-range', 'is-selecting');
        }

        /**
         * Keyboard navigation
         */
        handleKeyDown(event) {
            const currentCell = document.activeElement;
            if (!currentCell.classList.contains('mcs-day')) return;

            const cells = Array.from(this.root.querySelectorAll('.mcs-day'));
            const currentIndex = cells.indexOf(currentCell);

            let targetIndex = currentIndex;

            switch (event.key) {
                case 'ArrowLeft':
                    targetIndex = Math.max(0, currentIndex - 1);
                    break;
                case 'ArrowRight':
                    targetIndex = Math.min(cells.length - 1, currentIndex + 1);
                    break;
                case 'ArrowUp':
                    targetIndex = Math.max(0, currentIndex - 7);
                    break;
                case 'ArrowDown':
                    targetIndex = Math.min(cells.length - 1, currentIndex + 7);
                    break;
                case 'Enter':
                case ' ':
                    event.preventDefault();
                    if (event.shiftKey) {
                        this.handleRangeSelection(currentCell);
                    } else {
                        this.handleSingleClick(currentCell);
                    }
                    return;
                case 'Escape':
                    this.clearSelection();
                    return;
                default:
                    return;
            }

            event.preventDefault();
            cells[targetIndex].focus();
        }

        /**
         * Check if cell is disabled
         */
        isCellDisabled(cell) {
            return cell.classList.contains('mcs-day--full') ||
                   cell.classList.contains('mcs-day--blackout') ||
                   cell.classList.contains('mcs-day--past') ||
                   cell.classList.contains('mcs-day--empty') ||
                   cell.getAttribute('aria-disabled') === 'true';
        }

        /**
         * Update visual selection
         */
        updateVisualSelection() {
            // Clear previous selection
            this.root.querySelectorAll('.mcs-selected, .mcs-selected-start, .mcs-selected-end, .mcs-selected-range')
                .forEach(cell => {
                    cell.classList.remove('mcs-selected', 'mcs-selected-start', 'mcs-selected-end', 'mcs-selected-range', 'is-selected');
                    cell.removeAttribute('aria-selected');
                });

            if (!this.state.selectedCheckin) return;

            // Mark checkin date
            const checkinCell = this.root.querySelector(`[data-ymd="${this.state.selectedCheckin}"]`);
            if (checkinCell) {
                checkinCell.classList.add('mcs-selected-start', 'is-selected');
                checkinCell.setAttribute('aria-selected', 'true');
            }

            if (this.state.selectedCheckout) {
                // Mark checkout date
                const checkoutCell = this.root.querySelector(`[data-ymd="${this.state.selectedCheckout}"]`);
                if (checkoutCell) {
                    checkoutCell.classList.add('mcs-selected-end', 'is-selected');
                    checkoutCell.setAttribute('aria-selected', 'true');
                }

                // Mark range between
                const checkinDate = new Date(this.state.selectedCheckin);
                const checkoutDate = new Date(this.state.selectedCheckout);

                this.root.querySelectorAll('.mcs-day[data-ymd]').forEach(cell => {
                    const cellDate = new Date(cell.dataset.ymd);
                    if (cellDate > checkinDate && cellDate < checkoutDate) {
                        cell.classList.add('mcs-selected-range', 'is-selected');
                    }
                });
            }
        }

        /**
         * Create quote panel
         */
        createQuotePanel() {
            // Check if panel already exists
            let panel = this.getQuotePanel();
            if (panel) return;

            // Generate stable calendar ID for panel
            const calendarId = this.root.getAttribute('data-calendar-id') || `cal-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            this.root.setAttribute('data-calendar-id', calendarId);

            // Create panel HTML with robust anchoring
            const panelHtml = `
                <div class="mcs-quote-panel" data-calendar-id="${calendarId}" role="region" aria-label="${this.texts.quotePreview}" aria-live="polite">
                    <div class="mcs-quote-header">
                        <h3>見積</h3>
                        <button type="button" class="mcs-clear-selection-btn" aria-label="${this.texts.clearSelectionLabel}">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="mcs-quote-content">
                        <div class="mcs-quote-placeholder">
                            <p>日程を選択すると見積が表示されます</p>
                        </div>
                    </div>
                </div>
            `;

            // Insert panel with robust anchoring: try existing, else create
            if (this.options.mode === 'modal') {
                // Insert into modal
                const modalBody = this.root.closest('.modal-body, .mcs-modal-body');
                if (modalBody) {
                    modalBody.insertAdjacentHTML('beforeend', panelHtml);
                }
            } else {
                // Insert after calendar
                this.root.insertAdjacentHTML('afterend', panelHtml);
            }

            // Bind clear button
            const clearBtn = this.getQuotePanel()?.querySelector('.mcs-clear-selection-btn');
            if (clearBtn) {
                clearBtn.addEventListener('click', () => this.clearSelection());
            }
        }

        /**
         * Initialize navigation functionality
         */
        initNavigation() {
            const navButtons = this.root.querySelectorAll('.mcs-nav-button');
            navButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const action = button.getAttribute('data-action');
                    if (action === 'prev' || action === 'next') {
                        this.navigateMonth(action);
                    }
                });
            });

            // Update button states
            this.updateNavigationState();
        }

        /**
         * Update navigation button states
         */
        updateNavigationState() {
            const monthsGrid = this.root.querySelector('.mcs-calendar-months-grid');
            const prevBtn = this.root.querySelector('.mcs-nav-prev');
            const nextBtn = this.root.querySelector('.mcs-nav-next');

            if (!monthsGrid || !prevBtn || !nextBtn) return;

            const currentOffset = parseInt(monthsGrid.getAttribute('data-current-offset')) || 0;

            // Disable/enable buttons based on limits
            if (currentOffset <= -12) {
                prevBtn.disabled = true;
                prevBtn.setAttribute('aria-disabled', 'true');
            } else {
                prevBtn.disabled = false;
                prevBtn.setAttribute('aria-disabled', 'false');
            }

            if (currentOffset >= 24) {
                nextBtn.disabled = true;
                nextBtn.setAttribute('aria-disabled', 'true');
            } else {
                nextBtn.disabled = false;
                nextBtn.setAttribute('aria-disabled', 'false');
            }
        }

        /**
         * Navigate to previous or next month
         */
        navigateMonth(direction) {
            this.debug('Navigate month', { direction });

            // Clear current selection
            this.clearSelection();

            // Get current offset from the months grid
            const monthsGrid = this.root.querySelector('.mcs-calendar-months-grid');
            let currentOffset = 0;

            if (monthsGrid) {
                currentOffset = parseInt(monthsGrid.getAttribute('data-current-offset')) || 0;
            }

            let newOffset = currentOffset;
            if (direction === 'next') {
                newOffset += 1;
            } else if (direction === 'prev') {
                newOffset -= 1;
            }

            // Prevent going too far back
            if (newOffset < -12) {
                newOffset = -12;
            }

            // Prevent going too far forward (2 years)
            if (newOffset > 24) {
                newOffset = 24;
            }

            this.debug('Navigating to offset', { currentOffset, newOffset });

            // Update the URL and reload
            this.updateCalendarWithOffset(newOffset);
        }

        /**
         * Update calendar with new offset
         */
        updateCalendarWithOffset(offset) {
            const url = new URL(window.location);

            if (offset === 0) {
                url.searchParams.delete('calendar_offset');
            } else {
                url.searchParams.set('calendar_offset', offset);
            }

            this.debug('Navigating to URL', url.toString());
            window.location.href = url.toString();
        }

        /**
         * Get quote panel element
         */
        getQuotePanel() {
            // First try to find panel in the same container
            let panel = this.root.querySelector('.mcs-quote-panel');
            if (panel) return panel;

            const calendarId = this.root.getAttribute('data-calendar-id');
            if (calendarId) {
                panel = document.querySelector(`.mcs-quote-panel[data-calendar-id="${calendarId}"]`);
                if (panel) return panel;
            }

            if (this.options.mode === 'modal') {
                const modal = this.root.closest('.modal, .dialog, [role="dialog"]');
                panel = modal?.querySelector('.mcs-quote-panel');
                if (panel) return panel;
            } else {
                panel = this.root.parentNode.querySelector('.mcs-quote-panel');
                if (panel) return panel;
            }

            // Create panel if it doesn't exist
            return this.createQuotePanel();
        }

        createQuotePanel() {
            const panel = document.createElement('div');
            panel.className = 'mcs-quote-panel';
            panel.style.display = 'none';
            panel.setAttribute('aria-live', 'polite');

            panel.innerHTML = `
                <div class="mcs-quote-header">
                    <h3>${this.texts.quotePreview || '見積り'}</h3>
                    <button class="mcs-clear-selection-btn" aria-label="${this.texts.clearSelection || '選択をクリア'}">×</button>
                </div>
                <div class="mcs-quote-content">
                    <div class="mcs-quote-placeholder">
                        <p>${this.texts.selectDates || '日程を選択すると見積が表示されます'}</p>
                    </div>
                </div>
            `;

            // Add click handler for clear button
            const clearBtn = panel.querySelector('.mcs-clear-selection-btn');
            if (clearBtn) {
                clearBtn.addEventListener('click', () => this.clearSelection());
            }

            // Insert after the calendar container
            this.root.parentNode.insertBefore(panel, this.root.nextSibling);
            return panel;
        }

        /**
         * Fetch quote from API
         */
        async fetchQuote() {
            if (!this.state.selectedCheckin || !this.state.selectedCheckout) return;

            const panel = this.getQuotePanel();
            if (!panel) return;

            // Show panel
            panel.style.display = 'block';

            const content = panel.querySelector('.mcs-quote-content');
            content.innerHTML = `<div class="mcs-quote-loading">${this.texts.loading}</div>`;

            const payload = {
                property_id: parseInt(this.options.propertyId),
                checkin: this.state.selectedCheckin,
                checkout: this.state.selectedCheckout,
                guests: this.options.guests || 2
            };

            // Required debug output
            this.debug('Quote request (dummy)', payload);

            // Dummy implementation for hotfix - just show UI with mock data
            setTimeout(() => {
                const nights = this.calculateNights();
                const basePrice = 15000;
                const totalPrice = basePrice * nights;

                const mockQuoteData = {
                    nights: nights,
                    base_total: totalPrice,
                    cleaning_fee: 3000,
                    grand_total: totalPrice + 3000,
                    breakdown: [
                        {
                            date: this.state.selectedCheckin,
                            base: basePrice,
                            surcharge: 0,
                            note: '平日料金'
                        }
                    ]
                };

                this.displayQuote(mockQuoteData);
            }, 500);
        }

        /**
         * Display quote data
         */
        displayQuote(quoteData) {
            const panel = this.getQuotePanel();
            if (!panel) return;

            const nights = quoteData.nights || this.calculateNights();
            const checkinDate = new Date(this.state.selectedCheckin);
            const checkoutDate = new Date(this.state.selectedCheckout);

            const formatter = new Intl.NumberFormat('ja-JP', {style: 'currency', currency: 'JPY'});

            const html = `
                <div class="mcs-quote-summary">
                    <div class="mcs-quote-dates">
                        <span class="mcs-checkin-date">${this.formatDate(checkinDate)}</span>
                        <span class="mcs-date-separator">〜</span>
                        <span class="mcs-checkout-date">${this.formatDate(checkoutDate)}</span>
                        <span class="mcs-nights-count">${nights}泊数</span>
                    </div>
                </div>

                <div class="mcs-quote-breakdown">
                    <table class="mcs-quote-table">
                        <tr><th>内訳</th><th>金額</th></tr>
                        ${quoteData.base_total ? `<tr><td>宿泊料金 (${nights}泊)</td><td>${formatter.format(quoteData.base_total)}</td></tr>` : ''}
                        ${quoteData.cleaning_fee && quoteData.cleaning_fee > 0 ? `<tr><td>清掃費</td><td>${formatter.format(quoteData.cleaning_fee)}</td></tr>` : ''}
                        <tr class="mcs-total-row"><td><strong>合計</strong></td><td><strong>${formatter.format(quoteData.grand_total || quoteData.total || 0)}</strong></td></tr>
                    </table>
                </div>

                <div class="mcs-quote-notice">
                    <p><small>注意: 最終合計は予約時に確定します</small></p>
                </div>

                ${this.options.canBook ? this.buildBookingButtonHtml() : this.buildDisabledBookingHtml()}
            `;

            const content = panel.querySelector('.mcs-quote-content');
            content.innerHTML = html;
        }

        /**
         * Display quote error
         */
        displayQuoteError(message) {
            const panel = this.getQuotePanel();
            if (!panel) return;

            const html = `
                <div class="mcs-quote-error">
                    <h4>${this.texts.errorTitle}</h4>
                    <p>${message}</p>
                </div>
            `;

            const content = panel.querySelector('.mcs-quote-content');
            content.innerHTML = html;
        }

        /**
         * Build breakdown HTML
         */
        buildBreakdownHtml(quoteData) {
            let html = '';

            if (quoteData.base_nightly_total > 0) {
                html += `
                    <div class="mcs-quote-line-item">
                        <span class="mcs-quote-label">${this.texts.accommodationFee}</span>
                        <span class="mcs-quote-amount">¥${this.formatPrice(quoteData.base_nightly_total)}</span>
                    </div>
                `;
            }

            if (quoteData.cleaning_fee > 0) {
                html += `
                    <div class="mcs-quote-line-item">
                        <span class="mcs-quote-label">${this.texts.cleaningFee}</span>
                        <span class="mcs-quote-amount">¥${this.formatPrice(quoteData.cleaning_fee)}</span>
                    </div>
                `;
            }

            if (quoteData.extra_guest_total > 0) {
                html += `
                    <div class="mcs-quote-line-item">
                        <span class="mcs-quote-label">${this.texts.extraGuestFee}</span>
                        <span class="mcs-quote-amount">¥${this.formatPrice(quoteData.extra_guest_total)}</span>
                    </div>
                `;
            }

            return html;
        }

        /**
         * Build booking button HTML
         */
        buildBookingButtonHtml() {
            return `
                <div class="mcs-quote-actions">
                    <button type="button" class="mcs-book-now-btn mcs-btn-primary">
                        予約する
                    </button>
                </div>
            `;
        }

        /**
         * Build disabled booking HTML
         */
        buildDisabledBookingHtml() {
            return `
                <div class="mcs-quote-actions">
                    <button type="button" class="mcs-book-disabled-btn" disabled>
                        ${this.texts.bookingDisabled}
                    </button>
                </div>
            `;
        }

        /**
         * Clear selection
         */
        clearSelection() {
            this.state.selectedCheckin = null;
            this.state.selectedCheckout = null;
            this.state.isSelecting = false;

            this.updateVisualSelection();
            this.root.classList.remove('mcs-selecting-range', 'is-selecting');

            const panel = this.getQuotePanel();
            if (panel) {
                const content = panel.querySelector('.mcs-quote-content');
                content.innerHTML = `
                    <div class="mcs-quote-placeholder">
                        <p>日程を選択すると見積が表示されます</p>
                    </div>
                `;
            }
        }

        /**
         * Utility functions
         */
        getNextDay(dateString) {
            const date = new Date(dateString);
            date.setDate(date.getDate() + 1);
            return date.toISOString().split('T')[0];
        }

        calculateNights() {
            if (!this.state.selectedCheckin || !this.state.selectedCheckout) return 0;
            const checkin = new Date(this.state.selectedCheckin);
            const checkout = new Date(this.state.selectedCheckout);
            return Math.ceil((checkout - checkin) / (1000 * 60 * 60 * 24));
        }

        formatDate(date) {
            return date.toLocaleDateString('ja-JP', {
                month: 'long',
                day: 'numeric',
                weekday: 'short'
            });
        }

        formatPrice(price) {
            return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    }

    /**
     * Initialize unified calendar interactions
     */
    function initCalendarInteractions(rootElement, options = {}) {
        return new CalendarInteractions(rootElement, options);
    }

    // Export to global scope
    window.MinpakuSuite = window.MinpakuSuite || {};
    window.MinpakuSuite.CalendarInteractions = CalendarInteractions;
    window.initCalendarInteractions = initCalendarInteractions;

    // Export main interface for hotfix
    window.MCSCalendarInteractions = {
        init: function(options = {}) {
            const selector = options.selector || '[data-interactions="modern"]';
            const calendars = document.querySelectorAll(selector);

            calendars.forEach(calendar => {
                const propertyId = calendar.dataset.propertyId;
                const mode = calendar.dataset.mode || 'inline';

                if (propertyId) {
                    const instance = initCalendarInteractions(calendar, {
                        mode: mode,
                        propertyId: propertyId,
                        isConnector: false,
                        apiBase: '/wp-json/minpaku/v1'
                    });

                    // Track active instances for debugging
                    window.MinpakuSuite.activeInstances = window.MinpakuSuite.activeInstances || [];
                    window.MinpakuSuite.activeInstances.push(instance);
                }
            });
        }
    };

    // Debug functionality for administrators
    window.__MCS_DEBUG = window.__MCS_DEBUG || {};
    window.__MCS_DEBUG.status = function() {
        const calendars = document.querySelectorAll('[data-interactions="modern"]:not(.connector-calendar)');
        const activeInstances = window.MinpakuSuite.activeInstances || [];

        return {
            ready: true,
            interactions: 'modern',
            calendars: calendars.length,
            activeInstances: activeInstances.length,
            version: '1.0.0',
            selectors: {
                portal: '[data-interactions="modern"]:not(.connector-calendar)',
                connector: '.connector-calendar[data-interactions="modern"]'
            }
        };
    };

    // Auto-initialize calendars with modern interactions
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize portal calendars with strict data-interactions="modern" guard (NOT connector)
        document.querySelectorAll('[data-interactions="modern"]:not(.connector-calendar)').forEach(calendar => {
            const propertyId = calendar.dataset.propertyId;
            const mode = calendar.dataset.mode || 'inline';

            if (propertyId && calendar.getAttribute('data-interactions') === 'modern') {
                const instance = initCalendarInteractions(calendar, {
                    mode: mode,
                    propertyId: propertyId,
                    isConnector: false,
                    apiBase: '/wp-json/minpaku/v1'
                });

                // Track active instances for debugging
                window.MinpakuSuite.activeInstances = window.MinpakuSuite.activeInstances || [];
                window.MinpakuSuite.activeInstances.push(instance);
            }
        });
    });

})(window, document);