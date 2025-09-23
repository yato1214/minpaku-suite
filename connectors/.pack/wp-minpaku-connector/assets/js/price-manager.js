/**
 * Minpaku Connector Price Manager
 * Real pricing integration with /connector/quote API
 */
class MPCPriceManager {
    constructor() {
        this.cache = new Map();
        this.sessionCache = new Map();
        this.pendingRequests = new Map();
        this.maxCacheSize = 100;
        this.cacheExpiry = 300000; // 5 minutes

        // Calendar state
        this.selectedRange = {
            checkin: null,
            checkout: null,
            propertyId: null
        };

        this.initSessionStorage();
    }

    /**
     * Initialize session storage cache
     */
    initSessionStorage() {
        try {
            const cached = sessionStorage.getItem('mpc_price_cache');
            if (cached) {
                const data = JSON.parse(cached);
                // Restore valid cached entries
                Object.entries(data).forEach(([key, value]) => {
                    if (value.timestamp && (Date.now() - value.timestamp) < this.cacheExpiry) {
                        this.sessionCache.set(key, value);
                    }
                });
            }
        } catch (e) {
            console.warn('MPC: Session storage not available');
        }
    }

    /**
     * Save cache to session storage
     */
    saveSessionStorage() {
        try {
            const cacheData = {};
            this.sessionCache.forEach((value, key) => {
                if ((Date.now() - value.timestamp) < this.cacheExpiry) {
                    cacheData[key] = value;
                }
            });
            sessionStorage.setItem('mpc_price_cache', JSON.stringify(cacheData));
        } catch (e) {
            // Session storage not available
        }
    }

    /**
     * Create cache key
     */
    createCacheKey(propertyId, checkin, checkout, adults, children, infants, currency) {
        return `${propertyId}_${checkin}_${checkout}_${adults}_${children}_${infants}_${currency}`;
    }

    /**
     * Get quote with caching and request coalescing
     */
    async getQuote(propertyId, checkin, checkout, adults = 2, children = 0, infants = 0, currency = 'JPY') {
        const cacheKey = this.createCacheKey(propertyId, checkin, checkout, adults, children, infants, currency);

        // Check memory cache first
        if (this.cache.has(cacheKey)) {
            const cached = this.cache.get(cacheKey);
            if ((Date.now() - cached.timestamp) < this.cacheExpiry) {
                return cached.data;
            }
            this.cache.delete(cacheKey);
        }

        // Check session cache
        if (this.sessionCache.has(cacheKey)) {
            const cached = this.sessionCache.get(cacheKey);
            if ((Date.now() - cached.timestamp) < this.cacheExpiry) {
                // Copy to memory cache
                this.cache.set(cacheKey, cached);
                return cached.data;
            }
            this.sessionCache.delete(cacheKey);
        }

        // Check if request is already pending (coalescing)
        if (this.pendingRequests.has(cacheKey)) {
            return this.pendingRequests.get(cacheKey);
        }

        // Make new request
        const requestPromise = this.makeQuoteRequest(propertyId, checkin, checkout, adults, children, infants, currency);
        this.pendingRequests.set(cacheKey, requestPromise);

        try {
            const result = await requestPromise;
            this.pendingRequests.delete(cacheKey);

            // Cache successful results
            if (result.success) {
                const cacheData = {
                    data: result,
                    timestamp: Date.now()
                };

                // LRU eviction for memory cache
                if (this.cache.size >= this.maxCacheSize) {
                    const oldestKey = this.cache.keys().next().value;
                    this.cache.delete(oldestKey);
                }

                this.cache.set(cacheKey, cacheData);
                this.sessionCache.set(cacheKey, cacheData);
                this.saveSessionStorage();
            }

            return result;
        } catch (error) {
            this.pendingRequests.delete(cacheKey);
            throw error;
        }
    }

    /**
     * Make actual quote request with HMAC authentication
     */
    async makeQuoteRequest(propertyId, checkin, checkout, adults, children, infants, currency) {
        const maxRetries = 2;
        let lastError;

        for (let attempt = 0; attempt <= maxRetries; attempt++) {
            try {
                const response = await this.executeQuoteRequest(propertyId, checkin, checkout, adults, children, infants, currency);
                return response;
            } catch (error) {
                lastError = error;

                if (attempt < maxRetries) {
                    // Exponential backoff
                    const delay = Math.pow(2, attempt) * 1000;
                    await new Promise(resolve => setTimeout(resolve, delay));
                    continue;
                }
            }
        }

        return {
            success: false,
            error: lastError.message || (window.mpcPricing?.strings?.error || 'Quote request failed')
        };
    }

    /**
     * Execute the HTTP request
     */
    async executeQuoteRequest(propertyId, checkin, checkout, adults, children, infants, currency) {
        if (!window.mpcPricing?.ajaxUrl) {
            throw new Error(window.mpcPricing?.strings?.error || 'API not configured');
        }

        const response = await fetch(window.mpcPricing.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'mpc_get_quote',
                nonce: window.mpcPricing.nonce,
                property_id: propertyId,
                checkin: checkin,
                checkout: checkout,
                adults: adults,
                children: children,
                infants: infants,
                currency: currency
            }),
            signal: AbortSignal.timeout(8000) // 8 second timeout
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.warn(`MPC: Quote request failed (${response.status}):`, errorText.substring(0, 200));
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        return data;
    }

    /**
     * Format price for display
     */
    formatPrice(amount, currency = 'JPY') {
        if (!amount || isNaN(amount)) return '—';

        if (currency === 'JPY') {
            return '¥' + Math.round(amount).toLocaleString();
        } else if (currency === 'USD') {
            return '$' + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else if (currency === 'EUR') {
            return '€' + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else {
            return currency + ' ' + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }

    /**
     * Initialize calendar with real pricing
     */
    initCalendar(calendarSelector) {
        const calendar = jQuery(calendarSelector);
        if (calendar.length === 0) {
            console.warn('MPC: Calendar not found', calendarSelector);
            return;
        }

        const propertyId = calendar.data('property-id');
        if (!propertyId) {
            console.warn('MPC: No property ID found');
            return;
        }

        const showPrices = calendar.data('show-prices');
        const adults = calendar.data('adults') || 2;
        const children = calendar.data('children') || 0;
        const infants = calendar.data('infants') || 0;
        const currency = calendar.data('currency') || 'JPY';

        // Initialize price badges for available days
        if (showPrices) {
            this.initPriceBadges(calendar, propertyId, adults, children, infants, currency);
        }

        // Initialize date range selection
        this.initDateRangeSelection(calendar, propertyId, adults, children, infants, currency);

        // Add quote summary area
        this.addQuoteSummaryArea(calendar);
    }

    /**
     * Initialize price badges for calendar days
     */
    initPriceBadges(calendar, propertyId, adults, children, infants, currency) {
        const badges = calendar.find('.mpc-price-badge');

        badges.each((index, badge) => {
            const $badge = jQuery(badge);
            const cell = $badge.closest('.mpc-calendar-day');
            const date = cell.data('date');

            if (date && !cell.hasClass('past-date') && !cell.hasClass('other-month')) {
                // Show loading initially
                $badge.text(window.mpcPricing?.strings?.loading || '...').addClass('loading');

                // Stagger API calls to avoid overwhelming the server
                setTimeout(() => {
                    this.loadSingleNightPrice($badge, propertyId, date, adults, children, infants, currency);
                }, index * 100);
            } else {
                $badge.remove();
            }
        });
    }

    /**
     * Load single night price for a badge
     */
    async loadSingleNightPrice(badge, propertyId, date, adults, children, infants, currency) {
        try {
            const checkin = date;
            const checkout = this.addDays(date, 1);

            const result = await this.getQuote(propertyId, checkin, checkout, adults, children, infants, currency);

            if (result.success && result.data?.total_incl_tax) {
                const formattedPrice = this.formatPrice(result.data.total_incl_tax, currency);
                badge.removeClass('loading').addClass('loaded').text(formattedPrice);
            } else {
                badge.removeClass('loading').addClass('error').text('—');
            }
        } catch (error) {
            console.warn('MPC: Price loading error for', date, error);
            badge.removeClass('loading').addClass('error').text('—');
        }
    }

    /**
     * Initialize date range selection functionality
     */
    initDateRangeSelection(calendar, propertyId, adults, children, infants, currency) {
        this.selectedRange.propertyId = propertyId;

        calendar.find('.mpc-calendar-day').on('click', (e) => {
            const cell = jQuery(e.currentTarget);
            const date = cell.data('date');

            if (!date || cell.hasClass('past-date') || cell.hasClass('other-month')) {
                return;
            }

            this.handleDateSelection(calendar, cell, date, adults, children, infants, currency);
        });
    }

    /**
     * Handle date selection for range booking
     */
    handleDateSelection(calendar, cell, date, adults, children, infants, currency) {
        const propertyId = this.selectedRange.propertyId;

        if (!this.selectedRange.checkin) {
            // First click - set check-in
            this.selectedRange.checkin = date;
            this.selectedRange.checkout = null;

            // Clear previous selections
            calendar.find('.mpc-calendar-day').removeClass('selected-checkin selected-checkout selected-range');

            // Mark check-in
            cell.addClass('selected-checkin');

            this.updateQuoteSummary(calendar, window.mpcPricing?.strings?.selectCheckout || 'Select checkout date', '');

        } else if (!this.selectedRange.checkout) {
            // Second click - set check-out
            if (date <= this.selectedRange.checkin) {
                // Invalid range, reset
                this.resetSelection(calendar);
                return;
            }

            this.selectedRange.checkout = date;
            cell.addClass('selected-checkout');

            // Mark range
            this.markDateRange(calendar, this.selectedRange.checkin, this.selectedRange.checkout);

            // Calculate quote for range
            this.calculateRangeQuote(calendar, propertyId, this.selectedRange.checkin, this.selectedRange.checkout, adults, children, infants, currency);

        } else {
            // Third click - reset and start new selection
            this.resetSelection(calendar);
            this.selectedRange.checkin = date;
            cell.addClass('selected-checkin');
            this.updateQuoteSummary(calendar, window.mpcPricing?.strings?.selectCheckout || 'Select checkout date', '');
        }
    }

    /**
     * Mark date range in calendar
     */
    markDateRange(calendar, checkin, checkout) {
        const checkinDate = new Date(checkin);
        const checkoutDate = new Date(checkout);

        calendar.find('.mpc-calendar-day').each((index, dayEl) => {
            const day = jQuery(dayEl);
            const date = new Date(day.data('date'));

            if (date > checkinDate && date < checkoutDate) {
                day.addClass('selected-range');
            }
        });
    }

    /**
     * Reset date selection
     */
    resetSelection(calendar) {
        this.selectedRange.checkin = null;
        this.selectedRange.checkout = null;
        calendar.find('.mpc-calendar-day').removeClass('selected-checkin selected-checkout selected-range');
        this.updateQuoteSummary(calendar, window.mpcPricing?.strings?.clickToSelect || 'Click to select dates', '');
    }

    /**
     * Calculate quote for selected date range
     */
    async calculateRangeQuote(calendar, propertyId, checkin, checkout, adults, children, infants, currency) {
        this.updateQuoteSummary(calendar, window.mpcPricing?.strings?.calculating || 'Calculating quote...', '', true);

        try {
            const result = await this.getQuote(propertyId, checkin, checkout, adults, children, infants, currency);

            if (result.success && result.data) {
                this.displayQuoteBreakdown(calendar, result.data, checkin, checkout);
            } else {
                this.updateQuoteSummary(calendar, window.mpcPricing?.strings?.quoteFailed || 'Quote calculation failed', '');
            }
        } catch (error) {
            console.warn('MPC: Range quote error', error);
            this.updateQuoteSummary(calendar, window.mpcPricing?.strings?.quoteFailed || 'Quote calculation failed', '');
        }
    }

    /**
     * Display quote breakdown
     */
    displayQuoteBreakdown(calendar, quoteData, checkin, checkout) {
        const nights = this.calculateNights(checkin, checkout);
        const nightText = nights === 1 ? (window.mpcPricing?.strings?.night || 'night') : (window.mpcPricing?.strings?.nights || 'nights');
        const datesText = `${checkin} → ${checkout} (${nights} ${nightText})`;

        let breakdown = '';
        if (quoteData.line_items && quoteData.line_items.length > 0) {
            breakdown = '<div class="mpc-quote-breakdown">';

            quoteData.line_items.forEach(item => {
                breakdown += `<div class="mpc-quote-line">
                    <span class="mpc-quote-label">${item.description}</span>
                    <span class="mpc-quote-amount">${this.formatPrice(item.amount, quoteData.currency)}</span>
                </div>`;
            });

            // Add taxes if present
            if (quoteData.taxes && quoteData.taxes.length > 0) {
                quoteData.taxes.forEach(tax => {
                    breakdown += `<div class="mpc-quote-line mpc-quote-tax">
                        <span class="mpc-quote-label">${tax.description}</span>
                        <span class="mpc-quote-amount">${this.formatPrice(tax.amount, quoteData.currency)}</span>
                    </div>`;
                });
            }

            // Total
            breakdown += `<div class="mpc-quote-line mpc-quote-total">
                <span class="mpc-quote-label"><strong>${window.mpcPricing?.strings?.total || 'Total'}</strong></span>
                <span class="mpc-quote-amount"><strong>${this.formatPrice(quoteData.total_incl_tax, quoteData.currency)}</strong></span>
            </div>`;

            breakdown += '</div>';
        }

        this.updateQuoteSummary(calendar, datesText, breakdown);
    }

    /**
     * Add quote summary area to calendar
     */
    addQuoteSummaryArea(calendar) {
        if (calendar.find('.mpc-quote-summary').length === 0) {
            const summaryHtml = `
                <div class="mpc-quote-summary">
                    <div class="mpc-quote-title">${window.mpcPricing?.strings?.clickToSelect || 'Click to select dates'}</div>
                    <div class="mpc-quote-content"></div>
                </div>
            `;
            calendar.append(summaryHtml);
        }
    }

    /**
     * Update quote summary display
     */
    updateQuoteSummary(calendar, title, content, loading = false) {
        const summary = calendar.find('.mpc-quote-summary');
        summary.find('.mpc-quote-title').text(title);
        summary.find('.mpc-quote-content').html(content);

        if (loading) {
            summary.addClass('loading');
        } else {
            summary.removeClass('loading');
        }
    }

    /**
     * Helper: Calculate nights between dates
     */
    calculateNights(checkin, checkout) {
        const checkinDate = new Date(checkin);
        const checkoutDate = new Date(checkout);
        const diffTime = Math.abs(checkoutDate - checkinDate);
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    }

    /**
     * Helper: Add days to date
     */
    addDays(dateString, days) {
        const date = new Date(dateString);
        date.setDate(date.getDate() + days);
        return date.toISOString().split('T')[0];
    }

    /**
     * Initialize property card pricing
     */
    initPropertyCard(cardSelector) {
        const card = jQuery(cardSelector);
        if (card.length === 0) return;

        const propertyId = card.data('property-id');
        const showPrice = card.data('show-price');
        const nights = card.data('price-nights') || 2;
        const adults = card.data('adults') || 2;
        const children = card.data('children') || 0;
        const infants = card.data('infants') || 0;
        const currency = card.data('currency') || 'JPY';

        if (!showPrice || !propertyId) return;

        this.loadPropertyCardPrice(card, propertyId, nights, adults, children, infants, currency);
    }

    /**
     * Load price for property card
     */
    async loadPropertyCardPrice(card, propertyId, nights, adults, children, infants, currency) {
        const priceElement = card.find('.mpc-property-quick-price');
        if (priceElement.length === 0) return;

        try {
            const checkin = this.addDays(new Date().toISOString().split('T')[0], 1);
            const checkout = this.addDays(checkin, nights);

            const result = await this.getQuote(propertyId, checkin, checkout, adults, children, infants, currency);

            if (result.success && result.data?.total_incl_tax) {
                const formattedPrice = this.formatPrice(result.data.total_incl_tax, currency);
                const nightsText = nights === 1 ? (window.mpcPricing?.strings?.night || 'night') : (window.mpcPricing?.strings?.nights || 'nights');
                priceElement.removeClass('loading').addClass('loaded').text(`${formattedPrice}/${nights} ${nightsText}`);
            } else {
                priceElement.removeClass('loading').addClass('error').text('—');
            }
        } catch (error) {
            console.warn('MPC: Property card price error', error);
            priceElement.removeClass('loading').addClass('error').text('—');
        }
    }

    /**
     * Initialize property grid
     */
    initPropertyGrid(gridSelector) {
        const grid = jQuery(gridSelector);
        if (grid.length === 0) return;

        grid.find('.mpc-property-card').each((index, card) => {
            setTimeout(() => {
                this.initPropertyCard(jQuery(card));
            }, index * 200);
        });
    }
}

// Global initialization
jQuery(document).ready(function($) {
    // Create global instance
    window.MPCPriceManager = MPCPriceManager;
    const priceManager = new MPCPriceManager();

    // Auto-initialize calendars
    $('.mpc-calendar-container').each(function(index) {
        const calendar = $(this);
        const calendarId = calendar.attr('id');

        if (calendarId) {
            setTimeout(() => {
                priceManager.initCalendar('#' + calendarId);
            }, 100 + (index * 100));
        }
    });

    // Auto-initialize property cards
    $('.mpc-property-card').each(function(index) {
        const card = $(this);
        const cardId = card.attr('id');

        if (cardId) {
            setTimeout(() => {
                priceManager.initPropertyCard('#' + cardId);
            }, 200 + (index * 100));
        }
    });

    // Auto-initialize property grids
    $('.mpc-property-grid').each(function(index) {
        const grid = $(this);
        const gridId = grid.attr('id');

        if (gridId) {
            setTimeout(() => {
                priceManager.initPropertyGrid('#' + gridId);
            }, 300 + (index * 100));
        }
    });
});