/**
 * Connector Calendar Interactions - Modern Quote API Integration
 * Uses unified calendar interactions with REST API quote endpoint
 *
 * @package WP_Minpaku_Connector
 */

(function(window, document) {
    'use strict';

    /**
     * Connector Calendar Interactions Manager
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

            // Texts with defaults
            this.texts = {
                loading: '読み込み中...',
                nightsLabel: '泊',
                totalLabel: '合計金額',
                clearSelectionLabel: '選択をクリア',
                errorTitle: 'エラー',
                networkError: 'ネットワークエラーが発生しました',
                ...this.options.texts
            };

            this.init();
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

            // Set data attributes
            this.root.setAttribute('data-interactions', 'modern');
            this.root.setAttribute('data-mode', this.options.mode);

            // Required debug output
            console.debug('[quote] init', {
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
            this.root.setAttribute('role', 'grid');
            this.root.setAttribute('aria-label', 'カレンダー');

            const cells = this.root.querySelectorAll('.mcs-day');
            cells.forEach((cell, index) => {
                cell.setAttribute('role', 'gridcell');
                cell.setAttribute('tabindex', index === 0 ? '0' : '-1');

                if (cell.classList.contains('mcs-day--full') ||
                    cell.classList.contains('mcs-day--blackout') ||
                    cell.classList.contains('mcs-day--past')) {
                    cell.setAttribute('aria-disabled', 'true');
                }

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
            this.root.addEventListener('click', this.handleClick.bind(this));
            this.root.addEventListener('dblclick', this.handleDoubleClick.bind(this));
            this.root.addEventListener('mousedown', this.handleMouseDown.bind(this));
            this.root.addEventListener('mousemove', this.handleMouseMove.bind(this));
            this.root.addEventListener('mouseup', this.handleMouseUp.bind(this));
            this.root.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });
            this.root.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
            this.root.addEventListener('touchend', this.handleTouchEnd.bind(this));
            this.root.addEventListener('keydown', this.handleKeyDown.bind(this));

            this.root.style.userSelect = 'none';
            this.root.style.webkitUserSelect = 'none';
            this.root.style.touchAction = 'manipulation';
        }

        /**
         * Handle single click with dblclick coordination
         */
        handleClick(event) {
            const cell = event.target.closest('.mcs-day');
            if (!cell || this.isCellDisabled(cell)) return;

            if (this.state.clickTimer) {
                clearTimeout(this.state.clickTimer);
                this.state.clickTimer = null;
                return;
            }

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

            const checkoutDate = this.getNextDay(checkinDate);

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
                this.state.selectedCheckin = clickedDate;
                this.state.selectedCheckout = null;
            } else {
                const checkin = new Date(this.state.selectedCheckin);
                const checkout = new Date(clickedDate);

                if (checkout <= checkin) {
                    this.state.selectedCheckin = clickedDate;
                    this.state.selectedCheckout = null;
                } else {
                    this.state.selectedCheckout = clickedDate;
                    this.fetchQuote();
                }
            }

            this.updateVisualSelection();
        }

        // Mouse and touch event handlers
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
                    this.fetchQuote();
                }
            }
        }

        handleTouchStart(event) {
            const cell = event.target.closest('.mcs-day');
            if (!cell || this.isCellDisabled(cell)) return;

            this.state.lastTouchTarget = cell;
            this.state.longPressTimer = setTimeout(() => {
                this.startLongPressSelection(cell);
            }, 500);
        }

        handleTouchMove(event) {
            if (this.state.longPressTimer) {
                clearTimeout(this.state.longPressTimer);
                this.state.longPressTimer = null;
            }
        }

        handleTouchEnd(event) {
            if (this.state.longPressTimer) {
                clearTimeout(this.state.longPressTimer);
                this.state.longPressTimer = null;

                const cell = this.state.lastTouchTarget;
                if (cell) {
                    this.handleSingleClick(cell);
                }
            }
        }

        startLongPressSelection(cell) {
            this.state.isSelecting = true;
            this.state.selectedCheckin = cell.dataset.ymd;
            this.state.selectedCheckout = null;
            this.updateVisualSelection();
            this.root.classList.add('mcs-selecting-range');
        }

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
            this.root.querySelectorAll('.mcs-selected, .mcs-selected-start, .mcs-selected-end, .mcs-selected-range')
                .forEach(cell => {
                    cell.classList.remove('mcs-selected', 'mcs-selected-start', 'mcs-selected-end', 'mcs-selected-range');
                    cell.removeAttribute('aria-selected');
                });

            if (!this.state.selectedCheckin) return;

            const checkinCell = this.root.querySelector(`[data-ymd="${this.state.selectedCheckin}"]`);
            if (checkinCell) {
                checkinCell.classList.add('mcs-selected-start');
                checkinCell.setAttribute('aria-selected', 'true');
            }

            if (this.state.selectedCheckout) {
                const checkoutCell = this.root.querySelector(`[data-ymd="${this.state.selectedCheckout}"]`);
                if (checkoutCell) {
                    checkoutCell.classList.add('mcs-selected-end');
                    checkoutCell.setAttribute('aria-selected', 'true');
                }

                const checkinDate = new Date(this.state.selectedCheckin);
                const checkoutDate = new Date(this.state.selectedCheckout);

                this.root.querySelectorAll('.mcs-day[data-ymd]').forEach(cell => {
                    const cellDate = new Date(cell.dataset.ymd);
                    if (cellDate > checkinDate && cellDate < checkoutDate) {
                        cell.classList.add('mcs-selected-range');
                    }
                });
            }
        }

        /**
         * Create quote panel
         */
        createQuotePanel() {
            let panel = this.getQuotePanel();
            if (panel) return;

            const calendarId = this.root.getAttribute('data-calendar-id') || `cal-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            this.root.setAttribute('data-calendar-id', calendarId);

            const panelHtml = `
                <div class="wpmc-quote-panel" data-calendar-id="${calendarId}" role="region" aria-label="見積プレビュー" aria-live="polite">
                    <div class="wpmc-quote-header">
                        <h3>見積</h3>
                        <button type="button" class="wpmc-clear-selection-btn" aria-label="${this.texts.clearSelectionLabel}">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="wpmc-quote-content">
                        <div class="wpmc-quote-placeholder">
                            <p>日程を選択すると見積が表示されます</p>
                        </div>
                    </div>
                </div>
            `;

            if (this.options.mode === 'modal') {
                const modalBody = this.root.closest('.modal-body, .wpmc-modal-body');
                if (modalBody) {
                    modalBody.insertAdjacentHTML('beforeend', panelHtml);
                }
            } else {
                this.root.insertAdjacentHTML('afterend', panelHtml);
            }

            const clearBtn = this.getQuotePanel()?.querySelector('.wpmc-clear-selection-btn');
            if (clearBtn) {
                clearBtn.addEventListener('click', () => this.clearSelection());
            }
        }

        /**
         * Get quote panel element
         */
        getQuotePanel() {
            const calendarId = this.root.getAttribute('data-calendar-id');
            if (calendarId) {
                return document.querySelector(`.wpmc-quote-panel[data-calendar-id="${calendarId}"]`);
            }

            if (this.options.mode === 'modal') {
                const modal = this.root.closest('.modal, .dialog, [role="dialog"]');
                return modal?.querySelector('.wpmc-quote-panel');
            } else {
                return this.root.parentNode.querySelector('.wpmc-quote-panel');
            }
        }

        /**
         * Fetch quote from API
         */
        async fetchQuote() {
            if (!this.state.selectedCheckin || !this.state.selectedCheckout) return;

            const panel = this.getQuotePanel();
            if (!panel) return;

            const content = panel.querySelector('.wpmc-quote-content');
            content.innerHTML = `<div class="wpmc-quote-loading">${this.texts.loading}</div>`;

            try {
                const payload = {
                    property_id: parseInt(this.options.propertyId),
                    checkin: this.state.selectedCheckin,
                    checkout: this.state.selectedCheckout,
                    guests: this.options.guests || 2
                };

                // Required debug output
                console.debug('[quote] request', payload);

                const response = await fetch('/wp-json/minpaku-connector/v1/quote', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    console.debug('[quote] error', errorData);
                    throw new Error(errorData.error || errorData.message || 'Quote request failed');
                }

                const quoteData = await response.json();
                this.displayQuote(quoteData);

            } catch (error) {
                console.debug('[quote] error', error);
                this.displayQuoteError(error.message);
            }
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
                <div class="wpmc-quote-summary">
                    <div class="wpmc-quote-dates">
                        <span class="wpmc-checkin-date">${this.formatDate(checkinDate)}</span>
                        <span class="wpmc-date-separator">〜</span>
                        <span class="wpmc-checkout-date">${this.formatDate(checkoutDate)}</span>
                        <span class="wpmc-nights-count">${nights}泊数</span>
                    </div>
                </div>

                <div class="wpmc-quote-breakdown">
                    <table class="wpmc-quote-table">
                        <tr><th>内訳</th><th>金額</th></tr>
                        ${quoteData.base_total ? `<tr><td>宿泊料金 (${nights}泊)</td><td>${formatter.format(quoteData.base_total)}</td></tr>` : ''}
                        ${quoteData.cleaning_fee && quoteData.cleaning_fee > 0 ? `<tr><td>清掃費</td><td>${formatter.format(quoteData.cleaning_fee)}</td></tr>` : ''}
                        <tr class="wpmc-total-row"><td><strong>合計</strong></td><td><strong>${formatter.format(quoteData.grand_total || quoteData.total || 0)}</strong></td></tr>
                    </table>
                </div>

                <div class="wpmc-quote-notice">
                    <p><small>注意: 最終合計は予約時に確定します</small></p>
                </div>
            `;

            const content = panel.querySelector('.wpmc-quote-content');
            content.innerHTML = html;
        }

        /**
         * Display quote error
         */
        displayQuoteError(message) {
            const panel = this.getQuotePanel();
            if (!panel) return;

            const html = `
                <div class="wpmc-quote-error">
                    <h4>${this.texts.errorTitle}</h4>
                    <p>${message}</p>
                </div>
            `;

            const content = panel.querySelector('.wpmc-quote-content');
            content.innerHTML = html;
        }

        /**
         * Clear selection
         */
        clearSelection() {
            this.state.selectedCheckin = null;
            this.state.selectedCheckout = null;
            this.state.isSelecting = false;

            this.updateVisualSelection();
            this.root.classList.remove('mcs-selecting-range');

            const panel = this.getQuotePanel();
            if (panel) {
                const content = panel.querySelector('.wpmc-quote-content');
                content.innerHTML = `
                    <div class="wpmc-quote-placeholder">
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
    }

    /**
     * Initialize connector calendar interactions
     */
    function initConnectorCalendarInteractions(rootElement, options = {}) {
        return new ConnectorCalendarInteractions(rootElement, options);
    }

    // Export to global scope
    window.MinpakuConnector = window.MinpakuConnector || {};
    window.MinpakuConnector.CalendarInteractions = ConnectorCalendarInteractions;
    window.initConnectorCalendarInteractions = initConnectorCalendarInteractions;

    // Auto-initialize calendars with modern interactions
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.connector-calendar[data-interactions="modern"]').forEach(calendar => {
            const propertyId = calendar.dataset.propertyId;
            const mode = calendar.dataset.mode || 'inline';

            if (propertyId && calendar.getAttribute('data-interactions') === 'modern') {
                initConnectorCalendarInteractions(calendar, {
                    mode: mode,
                    propertyId: propertyId,
                    apiBase: '/wp-json/minpaku-connector/v1'
                });
            }
        });
    });

})(window, document);