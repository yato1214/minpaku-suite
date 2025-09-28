/**
 * Connector Calendar Interactions - Portal Parity
 * Mirrors portal calendar-interactions.js with connector-specific API endpoints
 *
 * @package WP_Minpaku_Connector
 */

(function(window, document) {
    'use strict';

    /**
     * Connector Calendar Interactions Class (mirrors portal CalendarInteractions)
     */
    class ConnectorCalendarInteractions {
        constructor(rootElement, options = {}) {
            this.root = rootElement;
            this.options = {
                mode: options.mode || this.detectMode(),
                canBook: options.canBook || false,
                apiBase: options.apiBase || '/wp-json/minpaku-connector/v1',
                propertyId: options.propertyId || null,
                texts: options.texts || {},
                isConnector: true,
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
                lastTouchTarget: null,
                lastClickTime: null
            };

            // Texts with defaults (will be overridden by localized data)
            const localizedTexts = (typeof window.minpakuCalendarData !== 'undefined' && window.minpakuCalendarData.texts)
                ? window.minpakuCalendarData.texts
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
         * Debug helper - only logs when MCS_DEBUG is defined
         */
        debug(message, data = null) {
            if (typeof window.MCS_DEBUG !== 'undefined' && window.MCS_DEBUG) {
                if (data) {
                    console.debug(`[Connector Calendar] ${message}`, data);
                } else {
                    console.debug(`[Connector Calendar] ${message}`);
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

            // Initialize debug output only when enabled
            this.debug('Calendar initialized', {
                el: this.root,
                propertyId: this.options.propertyId,
                mode: this.options.mode,
                interactions: 'modern'
            });
        }

        /**
         * Detect mode based on DOM context
         */
        detectMode() {
            if (this.root && this.root.closest('.modal, .dialog, [role="dialog"]')) {
                return 'modal';
            }
            return 'inline';
        }

        /**
         * Setup accessibility attributes
         */
        setupAccessibility() {
            this.root.setAttribute('role', 'application');
            this.root.setAttribute('aria-label', 'カレンダー - 日付を選択してください');

            // Add keyboard navigation
            this.root.setAttribute('tabindex', '0');
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Prevent default form submission
            this.root.addEventListener('submit', e => e.preventDefault());

            // Mouse events
            this.root.addEventListener('click', this.handleClick.bind(this));
            this.root.addEventListener('mousedown', this.handleMouseDown.bind(this));
            this.root.addEventListener('mousemove', this.handleMouseMove.bind(this));
            this.root.addEventListener('mouseup', this.handleMouseUp.bind(this));

            // Touch events
            this.root.addEventListener('touchstart', this.handleTouchStart.bind(this));
            this.root.addEventListener('touchmove', this.handleTouchMove.bind(this));
            this.root.addEventListener('touchend', this.handleTouchEnd.bind(this));

            // Keyboard events
            this.root.addEventListener('keydown', this.handleKeyDown.bind(this));

            // Window events for cleanup
            window.addEventListener('resize', this.handleResize.bind(this));
        }

        /**
         * Handle click events
         */
        handleClick(e) {
            const dayElement = e.target.closest('.mcs-day, .mpc-day');
            if (!dayElement || dayElement.getAttribute('data-disabled') === '1') {
                return;
            }

            const clickedDate = dayElement.getAttribute('data-ymd');
            if (!clickedDate) return;

            e.preventDefault();

            // Cancel any pending click timer
            if (this.state.clickTimer) {
                clearTimeout(this.state.clickTimer);
                this.state.clickTimer = null;
            }

            // Check for double click (within 300ms)
            if (this.state.lastClickTime && (Date.now() - this.state.lastClickTime) < 300) {
                this.handleDoubleClick(clickedDate);
                this.state.lastClickTime = null;
                return;
            }

            this.state.lastClickTime = Date.now();

            // Single click - preview 1 night
            this.state.clickTimer = setTimeout(() => {
                this.handleSingleClick(clickedDate);
                this.state.clickTimer = null;
            }, 100);
        }

        /**
         * Handle single click - 1 night preview
         */
        handleSingleClick(checkinDate) {
            // Calculate checkout date (next day)
            const checkoutDate = this.getNextDay(checkinDate);

            this.debug('Single click preview', { checkinDate, checkoutDate });

            this.clearSelection();
            this.state.selectedCheckin = checkinDate;
            this.state.selectedCheckout = checkoutDate;
            this.updateVisualSelection();
            this.showSelectionUI();
        }

        /**
         * Handle double click - start range selection
         */
        handleDoubleClick(clickedDate) {
            this.debug('Double click range selection', { clickedDate });

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
                    // If clicked date is before or same as checkin, reset
                    this.state.selectedCheckin = clickedDate;
                    this.state.selectedCheckout = null;
                } else {
                    this.state.selectedCheckout = clickedDate;
                    this.debug('Range complete', { checkin: this.state.selectedCheckin, checkout: clickedDate });
                    this.showSelectionUI();
                }
            }

            this.updateVisualSelection();
        }

        /**
         * Handle mouse down for drag selection
         */
        handleMouseDown(e) {
            const dayElement = e.target.closest('.mcs-day, .mpc-day');
            if (!dayElement || dayElement.getAttribute('data-disabled') === '1') {
                return;
            }

            const clickedDate = dayElement.getAttribute('data-ymd');
            if (!clickedDate) return;

            this.state.isDragging = true;
            this.state.dragStartDate = clickedDate;
            this.state.selectedCheckin = clickedDate;
            this.state.selectedCheckout = null;

            this.updateVisualSelection();
            e.preventDefault();
        }

        /**
         * Handle mouse move for drag selection
         */
        handleMouseMove(e) {
            if (!this.state.isDragging) return;

            const dayElement = e.target.closest('.mcs-day, .mpc-day');
            if (!dayElement || dayElement.getAttribute('data-disabled') === '1') {
                return;
            }

            const currentDate = dayElement.getAttribute('data-ymd');
            if (!currentDate) return;

            const startDate = new Date(this.state.dragStartDate);
            const endDate = new Date(currentDate);

            if (endDate > startDate) {
                this.state.selectedCheckout = currentDate;
            } else {
                this.state.selectedCheckin = currentDate;
                this.state.selectedCheckout = this.state.dragStartDate;
            }

            this.updateVisualSelection();
        }

        /**
         * Handle mouse up for drag selection
         */
        handleMouseUp(e) {
            if (this.state.isDragging) {
                this.state.isDragging = false;

                if (this.state.selectedCheckin && this.state.selectedCheckout) {
                    this.debug('Drag complete', { checkin: this.state.selectedCheckin, checkout: this.state.selectedCheckout });
                    this.showSelectionUI();
                }
            }
        }

        /**
         * Handle touch start for mobile
         */
        handleTouchStart(e) {
            const dayElement = e.target.closest('.mcs-day, .mpc-day');
            if (!dayElement || dayElement.getAttribute('data-disabled') === '1') {
                return;
            }

            const touchedDate = dayElement.getAttribute('data-ymd');
            if (!touchedDate) return;

            this.state.lastTouchTarget = dayElement;

            // Start long press timer
            this.state.longPressTimer = setTimeout(() => {
                this.startLongPressSelection(dayElement);
            }, 500);
        }

        /**
         * Handle touch move
         */
        handleTouchMove(e) {
            // Cancel long press if touch moves
            if (this.state.longPressTimer) {
                clearTimeout(this.state.longPressTimer);
                this.state.longPressTimer = null;
            }
        }

        /**
         * Handle touch end
         */
        handleTouchEnd(e) {
            // Cancel long press timer
            if (this.state.longPressTimer) {
                clearTimeout(this.state.longPressTimer);
                this.state.longPressTimer = null;

                // If it was a quick tap, treat as click
                if (this.state.lastTouchTarget) {
                    const touchedDate = this.state.lastTouchTarget.getAttribute('data-ymd');
                    if (touchedDate) {
                        this.handleSingleClick(touchedDate);
                    }
                }
            }

            this.state.lastTouchTarget = null;
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

            // Add visual feedback
            this.root.classList.add('mpc-selecting-range', 'is-selecting');
        }

        /**
         * Handle keyboard navigation
         */
        handleKeyDown(e) {
            // Implement keyboard navigation if needed
        }

        /**
         * Handle window resize
         */
        handleResize() {
            // Handle responsive layout changes if needed
        }

        /**
         * Update visual selection on calendar
         */
        updateVisualSelection() {
            // Clear previous selection
            this.root.querySelectorAll('.mpc-selected, .mcs-selected, .mpc-selected-start, .mcs-selected-start, .mpc-selected-end, .mcs-selected-end, .mpc-selected-range, .mcs-selected-range')
                .forEach(cell => {
                    cell.classList.remove('mpc-selected', 'mcs-selected', 'mpc-selected-start', 'mcs-selected-start', 'mpc-selected-end', 'mcs-selected-end', 'mpc-selected-range', 'mcs-selected-range', 'is-selected');
                    cell.removeAttribute('aria-selected');
                });

            if (!this.state.selectedCheckin) return;

            // Mark checkin date
            const checkinCell = this.root.querySelector(`[data-ymd="${this.state.selectedCheckin}"]`);
            if (checkinCell) {
                checkinCell.classList.add('mpc-selected-start', 'mcs-selected-start', 'is-selected');
                checkinCell.setAttribute('aria-selected', 'true');
            }

            if (this.state.selectedCheckout) {
                // Mark checkout date
                const checkoutCell = this.root.querySelector(`[data-ymd="${this.state.selectedCheckout}"]`);
                if (checkoutCell) {
                    checkoutCell.classList.add('mpc-selected-end', 'mcs-selected-end', 'is-selected');
                    checkoutCell.setAttribute('aria-selected', 'true');
                }

                // Mark range between
                const checkinDate = new Date(this.state.selectedCheckin);
                const checkoutDate = new Date(this.state.selectedCheckout);

                this.root.querySelectorAll('.mcs-day[data-ymd], .mpc-day[data-ymd]').forEach(cell => {
                    const cellDate = new Date(cell.dataset.ymd);
                    if (cellDate > checkinDate && cellDate < checkoutDate) {
                        cell.classList.add('mpc-selected-range', 'mcs-selected-range', 'is-selected');
                    }
                });
            }
        }

        /**
         * Get next day for single night selection
         */
        getNextDay(dateString) {
            const date = new Date(dateString);
            date.setDate(date.getDate() + 1);
            return date.toISOString().split('T')[0];
        }

        /**
         * Calculate nights between dates
         */
        calculateNights() {
            if (!this.state.selectedCheckin || !this.state.selectedCheckout) return 0;

            const checkin = new Date(this.state.selectedCheckin);
            const checkout = new Date(this.state.selectedCheckout);
            const diffTime = checkout - checkin;
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        }

        /**
         * Show selection UI (modern quote panel)
         */
        showSelectionUI() {
            if (!this.state.selectedCheckin || !this.state.selectedCheckout) return;

            const panel = this.getQuotePanel();
            if (!panel) return;

            // Show panel
            panel.style.display = 'block';

            // Hide placeholder, show selection form
            const placeholder = panel.querySelector('.mcs-quote-placeholder, .mpc-quote-placeholder');
            const selection = panel.querySelector('.mcs-quote-selection, .mpc-quote-selection');

            if (placeholder) placeholder.style.display = 'none';
            if (selection) selection.style.display = 'block';

            // Update date display
            this.updateDateDisplay();

            // Bind quote button
            this.bindQuoteButton();
        }

        /**
         * Update date display in quote panel
         */
        updateDateDisplay() {
            if (!this.state.selectedCheckin || !this.state.selectedCheckout) return;

            const panel = this.getQuotePanel();
            if (!panel) return;

            const nights = this.calculateNights();
            const checkinDate = new Date(this.state.selectedCheckin);
            const checkoutDate = new Date(this.state.selectedCheckout);

            const checkinFormatted = checkinDate.toLocaleDateString('ja-JP', {
                month: 'long',
                day: 'numeric'
            });

            const checkoutFormatted = checkoutDate.toLocaleDateString('ja-JP', {
                month: 'long',
                day: 'numeric'
            });

            // Update date elements
            const checkinEl = panel.querySelector('.mcs-checkin-date, .mpc-checkin-date');
            const checkoutEl = panel.querySelector('.mcs-checkout-date, .mpc-checkout-date');
            const nightsEl = panel.querySelector('.mcs-nights-count, .mpc-nights-count');

            if (checkinEl) checkinEl.textContent = checkinFormatted;
            if (checkoutEl) checkoutEl.textContent = checkoutFormatted;
            if (nightsEl) nightsEl.textContent = `${nights}${this.texts.nightsLabel}`;
        }

        /**
         * Bind quote button event
         */
        bindQuoteButton() {
            const panel = this.getQuotePanel();
            if (!panel) return;

            const quoteBtn = panel.querySelector('.mcs-get-quote, .mpc-get-quote');
            if (quoteBtn && !quoteBtn.hasAttribute('data-bound')) {
                quoteBtn.setAttribute('data-bound', 'true');
                quoteBtn.addEventListener('click', () => this.fetchQuote());
            }
        }

        /**
         * Create quote panel if it doesn't exist
         */
        createQuotePanel() {
            let panel = this.getQuotePanel();
            if (panel) return;

            // Generate stable calendar ID for panel
            const calendarId = this.root.getAttribute('data-calendar-id') ||
                              this.root.getAttribute('id') ||
                              'mpc-calendar-' + Date.now();

            // Set calendar ID if not present
            if (!this.root.getAttribute('data-calendar-id')) {
                this.root.setAttribute('data-calendar-id', calendarId);
            }

            // Clear button event binding
            const clearBtn = this.getQuotePanel()?.querySelector('.mcs-clear-selection-btn, .mpc-clear-selection-btn');
            if (clearBtn) {
                clearBtn.addEventListener('click', () => this.clearSelection());
            }
        }

        /**
         * Initialize navigation functionality
         */
        initNavigation() {
            const navButtons = this.root.querySelectorAll('.mpc-nav-button, .mcs-nav-button');
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
            const monthsGrid = this.root.querySelector('.mpc-calendar-months-grid, .mcs-calendar-months-grid');
            const prevBtn = this.root.querySelector('.mpc-nav-prev, .mcs-nav-prev');
            const nextBtn = this.root.querySelector('.mpc-nav-next, .mcs-nav-next');

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
            const monthsGrid = this.root.querySelector('.mpc-calendar-months-grid, .mcs-calendar-months-grid');
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
            let panel = this.root.querySelector('.mcs-quote-panel, .mpc-quote-panel');
            if (panel) return panel;

            const calendarId = this.root.getAttribute('data-calendar-id');
            if (calendarId) {
                panel = document.querySelector(`.mcs-quote-panel[data-calendar-id="${calendarId}"], .mpc-quote-panel[data-calendar-id="${calendarId}"]`);
                if (panel) return panel;
            }

            if (this.options.mode === 'modal') {
                const modal = this.root.closest('.modal, .dialog, [role="dialog"]');
                panel = modal?.querySelector('.mcs-quote-panel, .mpc-quote-panel');
                if (panel) return panel;
            } else {
                panel = this.root.parentNode.querySelector('.mcs-quote-panel, .mpc-quote-panel');
                if (panel) return panel;
            }

            // Create panel if it doesn't exist
            return this.createQuotePanelElement();
        }

        createQuotePanelElement() {
            const panel = document.createElement('div');
            panel.className = 'mcs-quote-panel mpc-quote-panel';
            panel.style.display = 'none';
            panel.setAttribute('aria-live', 'polite');

            panel.innerHTML = `
                <div class="mcs-quote-header mpc-quote-header">
                    <h3>${this.texts.quotePreview || '見積り'}</h3>
                    <button class="mcs-clear-selection-btn mpc-clear-selection-btn" aria-label="${this.texts.clearSelection || '選択をクリア'}">×</button>
                </div>
                <div class="mcs-quote-content mpc-quote-content">
                    <div class="mcs-quote-placeholder mpc-quote-placeholder">
                        <p>${this.texts.selectDates || '日程を選択すると見積が表示されます'}</p>
                    </div>
                </div>
            `;

            // Add click handler for clear button
            const clearBtn = panel.querySelector('.mcs-clear-selection-btn, .mpc-clear-selection-btn');
            if (clearBtn) {
                clearBtn.addEventListener('click', () => this.clearSelection());
            }

            // Insert after the calendar container
            this.root.parentNode.insertBefore(panel, this.root.nextSibling);
            return panel;
        }

        /**
         * Fetch quote from API (Portal direct endpoint)
         */
        async fetchQuote() {
            if (!this.state.selectedCheckin || !this.state.selectedCheckout) return;

            const panel = this.getQuotePanel();
            if (!panel) return;

            // Hide selection form, show loading
            const selection = panel.querySelector('.mcs-quote-selection, .mpc-quote-selection');
            const loading = panel.querySelector('.mcs-quote-loading, .mpc-quote-loading');
            const result = panel.querySelector('.mcs-quote-result, .mpc-quote-result');
            const error = panel.querySelector('.mcs-quote-error, .mpc-quote-error');

            if (selection) selection.style.display = 'none';
            if (loading) loading.style.display = 'flex';
            if (result) result.style.display = 'none';
            if (error) error.style.display = 'none';

            // Get guest counts
            const adultsSelect = panel.querySelector('#quote-adults, .mcs-guest-select[name="adults"], .mpc-guest-select[name="adults"]');
            const childrenSelect = panel.querySelector('#quote-children, .mcs-guest-select[name="children"], .mpc-guest-select[name="children"]');

            const payload = {
                property_id: parseInt(this.options.propertyId),
                checkin: this.state.selectedCheckin,
                checkout: this.state.selectedCheckout,
                adults: adultsSelect ? parseInt(adultsSelect.value) : 2,
                children: childrenSelect ? parseInt(childrenSelect.value) : 0
            };

            // Debug output for quote requests
            this.debug('Quote request initiated', payload);

            try {
                const quoteUrl = (typeof window.minpakuCalendarData !== 'undefined' && window.minpakuCalendarData.apiBase)
                    ? window.minpakuCalendarData.apiBase + '/quote'
                    : '/wp-json/minpaku/v1/quote';

                const response = await fetch(quoteUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });

                const responseData = await response.json();

                if (!response.ok) {
                    this.debug('Quote error response', responseData);
                    throw new Error(responseData.error || responseData.message || 'Quote request failed');
                }

                this.displayQuote(responseData);

                // Debug function only when debug mode is enabled
                if (typeof window.MCS_DEBUG !== 'undefined' && window.MCS_DEBUG) {
                    if (typeof window.__MPC_DEBUG === 'undefined') {
                        window.__MPC_DEBUG = {};
                    }
                    window.__MPC_DEBUG.quote = function(data) {
                        console.log('[Portal Debug] Quote data:', data || { payload, response: responseData });
                    };
                }

            } catch (error) {
                this.debug('Quote error', error);
                this.displayQuoteError(error.message);
            }
        }

        /**
         * Display quote data
         */
        displayQuote(quoteData) {
            const panel = this.getQuotePanel();
            if (!panel) return;

            const loading = panel.querySelector('.mcs-quote-loading, .mpc-quote-loading');
            const result = panel.querySelector('.mcs-quote-result, .mpc-quote-result');

            if (loading) loading.style.display = 'none';
            if (result) result.style.display = 'block';

            const nights = quoteData.nights || this.calculateNights();
            const formatter = new Intl.NumberFormat('ja-JP');

            // Update breakdown table
            const accommodationRow = result.querySelector('.mcs-breakdown-accommodation, .mpc-breakdown-accommodation');
            const cleaningRow = result.querySelector('.mcs-breakdown-cleaning, .mpc-breakdown-cleaning');
            const totalAmount = result.querySelector('.mcs-total-amount, .mpc-total-amount');

            // Show/hide rows based on data
            if (accommodationRow && quoteData.base_total > 0) {
                const label = accommodationRow.querySelector('.mcs-accommodation-label, .mpc-accommodation-label');
                const amount = accommodationRow.querySelector('.mcs-accommodation-amount, .mpc-accommodation-amount');
                if (label) label.textContent = `${this.texts.accommodationFee} (${nights}${this.texts.nightsLabel})`;
                if (amount) amount.textContent = `¥${formatter.format(quoteData.base_total)}`;
                accommodationRow.style.display = 'table-row';
            }

            if (cleaningRow && quoteData.cleaning_fee > 0) {
                const amount = cleaningRow.querySelector('.mcs-cleaning-amount, .mpc-cleaning-amount');
                if (amount) amount.textContent = `¥${formatter.format(quoteData.cleaning_fee)}`;
                cleaningRow.style.display = 'table-row';
            }

            if (totalAmount) {
                totalAmount.innerHTML = `<strong>¥${formatter.format(quoteData.grand_total || 0)}</strong>`;
            }
        }

        /**
         * Display quote error
         */
        displayQuoteError(errorMessage) {
            const panel = this.getQuotePanel();
            if (!panel) return;

            const loading = panel.querySelector('.mcs-quote-loading, .mpc-quote-loading');
            const error = panel.querySelector('.mcs-quote-error, .mpc-quote-error');
            const errorMessageEl = error?.querySelector('.mcs-error-message, .mpc-error-message');

            if (loading) loading.style.display = 'none';
            if (error) error.style.display = 'block';
            if (errorMessageEl) errorMessageEl.textContent = errorMessage;
        }

        /**
         * Clear selection
         */
        clearSelection() {
            this.state.selectedCheckin = null;
            this.state.selectedCheckout = null;
            this.state.isSelecting = false;

            this.updateVisualSelection();
            this.root.classList.remove('mpc-selecting-range', 'mcs-selecting-range', 'is-selecting');

            const panel = this.getQuotePanel();
            if (panel) {
                const placeholder = panel.querySelector('.mcs-quote-placeholder, .mpc-quote-placeholder');
                const selection = panel.querySelector('.mcs-quote-selection, .mpc-quote-selection');
                const loading = panel.querySelector('.mcs-quote-loading, .mpc-quote-loading');
                const result = panel.querySelector('.mcs-quote-result, .mpc-quote-result');
                const error = panel.querySelector('.mcs-quote-error, .mpc-quote-error');

                if (placeholder) placeholder.style.display = 'block';
                if (selection) selection.style.display = 'none';
                if (loading) loading.style.display = 'none';
                if (result) result.style.display = 'none';
                if (error) error.style.display = 'none';

                panel.style.display = 'none';
            }
        }
    }

    /**
     * Initialize calendar interactions
     */
    function initConnectorCalendarInteractions(rootElement, options = {}) {
        return new ConnectorCalendarInteractions(rootElement, options);
    }

    // Export to global scope
    window.MinpakuConnector = window.MinpakuConnector || {};
    window.MinpakuConnector.CalendarInteractions = ConnectorCalendarInteractions;
    window.initConnectorCalendarInteractions = initConnectorCalendarInteractions;

    // Export main interface for hotfix
    window.MPCCalendarInteractions = {
        init: function(options = {}) {
            const selector = options.selector || '.connector-calendar[data-interactions="modern"], .mpc-calendar-container[data-interactions="modern"]';
            const calendars = document.querySelectorAll(selector);

            calendars.forEach(calendar => {
                const propertyId = calendar.dataset.propertyId;
                const mode = calendar.dataset.mode || 'inline';

                if (propertyId) {
                    const instance = initConnectorCalendarInteractions(calendar, {
                        mode: mode,
                        propertyId: propertyId,
                        isConnector: true,
                        apiBase: '/wp-json/minpaku-connector/v1'
                    });

                    // Track active instances for debugging
                    window.MinpakuConnector.activeInstances = window.MinpakuConnector.activeInstances || [];
                    window.MinpakuConnector.activeInstances.push(instance);
                }
            });
        }
    };

    // Debug functionality only when debug mode is enabled
    if (typeof window.MCS_DEBUG !== 'undefined' && window.MCS_DEBUG) {
        window.__MPC_DEBUG = window.__MPC_DEBUG || {};
        window.__MPC_DEBUG.status = function() {
            const calendars = document.querySelectorAll('.connector-calendar[data-interactions="modern"], .mpc-calendar-container[data-interactions="modern"]');
            const activeInstances = window.MinpakuConnector.activeInstances || [];

            return {
                ready: true,
                interactions: 'modern',
                calendars: calendars.length,
                activeInstances: activeInstances.length,
                version: '1.1.0',
                selectors: {
                    connector: '.connector-calendar[data-interactions="modern"], .mpc-calendar-container[data-interactions="modern"]'
                }
            };
        };
    }

    // Auto-initialize calendars with modern interactions
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize connector calendars
        document.querySelectorAll('.connector-calendar[data-interactions="modern"], .mpc-calendar-container[data-interactions="modern"]').forEach(calendar => {
            const propertyId = calendar.dataset.propertyId;
            const mode = calendar.dataset.mode || 'inline';

            if (propertyId && calendar.getAttribute('data-interactions') === 'modern') {
                const instance = initConnectorCalendarInteractions(calendar, {
                    mode: mode,
                    propertyId: propertyId,
                    isConnector: true,
                    apiBase: '/wp-json/minpaku-connector/v1'
                });

                // Track active instances for debugging
                window.MinpakuConnector.activeInstances = window.MinpakuConnector.activeInstances || [];
                window.MinpakuConnector.activeInstances.push(instance);
            }
        });
    });

})(window, document);