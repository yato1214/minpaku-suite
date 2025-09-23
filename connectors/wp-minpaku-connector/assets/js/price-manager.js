/**
 * Price Manager for Calendar and Property Cards
 */
class MPCPriceManager {
    constructor(options = {}) {
        this.options = {
            adults: 2,
            children: 0,
            infants: 0,
            currency: 'JPY',
            cacheTimeout: 300000, // 5 minutes
            maxCacheSize: 100,
            retryDelay: 1000,
            maxRetries: 2,
            requestTimeout: 8000,
            ...options
        };

        this.memoryCache = new Map();
        this.pendingRequests = new Map();
        this.requestQueue = [];
        this.isProcessing = false;

        this.initSessionCache();
        this.bindEvents();
    }

    /**
     * Initialize session storage cache
     */
    initSessionCache() {
        try {
            this.sessionCache = JSON.parse(sessionStorage.getItem('mpc_quote_cache') || '{}');
            // Clean expired entries
            const now = Date.now();
            for (const [key, entry] of Object.entries(this.sessionCache)) {
                if (entry.timestamp + this.options.cacheTimeout < now) {
                    delete this.sessionCache[key];
                }
            }
            this.saveSessionCache();
        } catch (e) {
            console.warn('Failed to initialize session cache:', e);
            this.sessionCache = {};
        }
    }

    /**
     * Save session cache
     */
    saveSessionCache() {
        try {
            sessionStorage.setItem('mpc_quote_cache', JSON.stringify(this.sessionCache));
        } catch (e) {
            console.warn('Failed to save session cache:', e);
        }
    }

    /**
     * Bind events
     */
    bindEvents() {
        // Calendar cell interactions
        jQuery(document).on('click', '.mpc-calendar-day:not(.past-date, .other-month)', (e) => {
            this.handleCalendarClick(e);
        });

        jQuery(document).on('mouseenter', '.mpc-calendar-day:not(.past-date, .other-month)', (e) => {
            this.handleCalendarHover(e);
        });

        // Property card quote buttons
        jQuery(document).on('click', '.mpc-quick-quote-btn', (e) => {
            this.handleQuickQuoteClick(e);
        });

        // Intersection Observer for lazy loading
        if ('IntersectionObserver' in window) {
            this.setupIntersectionObserver();
        }
    }

    /**
     * Setup Intersection Observer for lazy loading
     */
    setupIntersectionObserver() {
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadPriceForCalendarDay(entry.target);
                }
            });
        }, {
            rootMargin: '50px',
            threshold: 0.1
        });
    }

    /**
     * Get quote with caching and deduplication
     */
    async getQuote(propertyId, checkin, checkout, adults = null, children = null, infants = null, currency = null) {
        adults = adults ?? this.options.adults;
        children = children ?? this.options.children;
        infants = infants ?? this.options.infants;
        currency = currency ?? this.options.currency;

        const cacheKey = this.createCacheKey(propertyId, checkin, checkout, adults, children, infants, currency);

        // Check memory cache
        if (this.memoryCache.has(cacheKey)) {
            const cached = this.memoryCache.get(cacheKey);
            if (Date.now() - cached.timestamp < this.options.cacheTimeout) {
                return cached.data;
            }
            this.memoryCache.delete(cacheKey);
        }

        // Check session cache
        if (this.sessionCache[cacheKey]) {
            const cached = this.sessionCache[cacheKey];
            if (Date.now() - cached.timestamp < this.options.cacheTimeout) {
                // Move to memory cache
                this.memoryCache.set(cacheKey, cached);
                return cached.data;
            }
            delete this.sessionCache[cacheKey];
            this.saveSessionCache();
        }

        // Check if request is pending
        if (this.pendingRequests.has(cacheKey)) {
            return this.pendingRequests.get(cacheKey);
        }

        // Make new request
        const requestPromise = this.makeQuoteRequest(propertyId, checkin, checkout, adults, children, infants, currency);
        this.pendingRequests.set(cacheKey, requestPromise);

        try {
            const result = await requestPromise;
            this.cacheResult(cacheKey, result);
            return result;
        } finally {
            this.pendingRequests.delete(cacheKey);
        }
    }

    /**
     * Make actual quote request using WordPress AJAX
     */
    async makeQuoteRequest(propertyId, checkin, checkout, adults, children, infants, currency) {
        const formData = new FormData();
        formData.append('action', 'mpc_get_quote');
        formData.append('nonce', mpcPricing.nonce);
        formData.append('property_id', propertyId);
        formData.append('checkin', checkin);
        formData.append('checkout', checkout);
        formData.append('adults', adults);
        formData.append('children', children);
        formData.append('infants', infants);
        formData.append('currency', currency);

        let lastError;
        for (let attempt = 0; attempt <= this.options.maxRetries; attempt++) {
            try {
                const response = await fetch(mpcPricing.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    signal: AbortSignal.timeout(this.options.requestTimeout)
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    return { success: true, data: result.data };
                } else {
                    throw new Error(result.data?.message || 'Request failed');
                }

            } catch (error) {
                lastError = error;
                if (attempt < this.options.maxRetries) {
                    await this.delay(this.options.retryDelay * Math.pow(2, attempt));
                }
            }
        }

        return {
            success: false,
            error: lastError.message || mpcPricing.strings.error
        };
    }

    /**
     * Cache result
     */
    cacheResult(cacheKey, result) {
        const cacheEntry = {
            data: result,
            timestamp: Date.now()
        };

        // Memory cache with LRU
        if (this.memoryCache.size >= this.options.maxCacheSize) {
            const firstKey = this.memoryCache.keys().next().value;
            this.memoryCache.delete(firstKey);
        }
        this.memoryCache.set(cacheKey, cacheEntry);

        // Session cache
        this.sessionCache[cacheKey] = cacheEntry;
        this.saveSessionCache();
    }

    /**
     * Create cache key
     */
    createCacheKey(propertyId, checkin, checkout, adults, children, infants, currency) {
        return `${propertyId}_${checkin}_${checkout}_${adults}_${children}_${infants}_${currency}`;
    }

    /**
     * Handle calendar cell click
     */
    async handleCalendarClick(e) {
        e.preventDefault();
        const cell = e.currentTarget;
        const date = cell.dataset.date;

        if (!date) return;

        const propertyId = this.getPropertyId(cell);
        if (!propertyId) return;

        this.showPriceModal(propertyId, date, date);
    }

    /**
     * Handle calendar cell hover
     */
    async handleCalendarHover(e) {
        const cell = e.currentTarget;
        if (cell.dataset.priceLoaded) return;

        this.loadPriceForCalendarDay(cell);
    }

    /**
     * Load price for calendar day
     */
    async loadPriceForCalendarDay(cell) {
        if (cell.dataset.priceLoaded) return;

        const date = cell.dataset.date;
        const propertyId = this.getPropertyId(cell);

        if (!date || !propertyId) return;

        // Mark as loading
        cell.dataset.priceLoaded = 'loading';
        this.showPriceSkeleton(cell);

        try {
            const checkout = this.addDays(date, 1);
            const result = await this.getQuote(propertyId, date, checkout);

            if (result.success && result.data.total_incl_tax) {
                this.showPriceBadge(cell, result.data.total_incl_tax, result.data.currency);
                cell.dataset.priceLoaded = 'true';
            } else {
                this.showPriceError(cell);
                cell.dataset.priceLoaded = 'error';
            }
        } catch (error) {
            console.warn('Failed to load price for date:', date, error);
            this.showPriceError(cell);
            cell.dataset.priceLoaded = 'error';
        }
    }

    /**
     * Show price skeleton
     */
    showPriceSkeleton(cell) {
        const existingBadge = cell.querySelector('.mpc-price-badge');
        if (existingBadge) return;

        const badge = document.createElement('div');
        badge.className = 'mpc-price-badge mpc-price-skeleton';
        badge.innerHTML = '<div class="mpc-skeleton-line"></div>';
        cell.appendChild(badge);
    }

    /**
     * Show price badge
     */
    showPriceBadge(cell, price, currency) {
        let badge = cell.querySelector('.mpc-price-badge');
        if (!badge) {
            badge = document.createElement('div');
            badge.className = 'mpc-price-badge';
            cell.appendChild(badge);
        }

        badge.className = 'mpc-price-badge'; // Remove skeleton class
        badge.textContent = this.formatPrice(price, currency);
        badge.title = mpcPricing.strings.loading;
    }

    /**
     * Show price error
     */
    showPriceError(cell) {
        let badge = cell.querySelector('.mpc-price-badge');
        if (!badge) {
            badge = document.createElement('div');
            badge.className = 'mpc-price-badge mpc-price-error';
            cell.appendChild(badge);
        }

        badge.className = 'mpc-price-badge mpc-price-error';
        badge.textContent = '—';
        badge.title = mpcPricing.strings.unavailable;
    }

    /**
     * Show price breakdown modal
     */
    async showPriceModal(propertyId, checkin, checkout) {
        // Create modal if it doesn't exist
        let modal = document.querySelector('.mpc-quote-modal');
        if (!modal) {
            modal = this.createPriceModal();
            document.body.appendChild(modal);
        }

        // Show loading state
        this.showModalLoading(modal);
        modal.classList.add('active');

        try {
            const result = await this.getQuote(propertyId, checkin, checkout);

            if (result.success) {
                this.populateModal(modal, result.data, checkin, checkout);
            } else {
                this.showModalError(modal, result.error);
            }
        } catch (error) {
            this.showModalError(modal, error.message);
        }
    }

    /**
     * Create price modal
     */
    createPriceModal() {
        const modal = document.createElement('div');
        modal.className = 'mpc-quote-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-labelledby', 'mpc-modal-title');
        modal.setAttribute('aria-hidden', 'true');

        modal.innerHTML = `
            <div class="mpc-quote-modal-content">
                <div class="mpc-quote-modal-header">
                    <h3 class="mpc-quote-modal-title">${mpcPricing.strings.quoteTitle}</h3>
                    <button class="mpc-quote-modal-close" type="button" aria-label="${mpcPricing.strings.loading}">×</button>
                </div>
                <div class="mpc-quote-modal-body">
                    <!-- Content will be populated dynamically -->
                </div>
            </div>
        `;

        // Event listeners
        modal.querySelector('.mpc-quote-modal-close').addEventListener('click', () => {
            this.closeModal(modal);
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeModal(modal);
            }
        });

        // ESC key handler
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                this.closeModal(modal);
            }
        });

        return modal;
    }

    /**
     * Show modal loading state
     */
    showModalLoading(modal) {
        const body = modal.querySelector('.mpc-quote-modal-body');
        body.innerHTML = `
            <div class="mpc-quote-loading">
                <p>${mpcPricing.strings.loading}</p>
            </div>
        `;
    }

    /**
     * Populate modal with quote data
     */
    populateModal(modal, quote, checkin, checkout) {
        const body = modal.querySelector('.mpc-quote-modal-body');

        const nights = Math.ceil((new Date(checkout) - new Date(checkin)) / (1000 * 60 * 60 * 24));

        let html = `
            <div class="mpc-quote-dates">
                <p class="mpc-quote-dates-text">
                    ${this.formatDate(checkin)} - ${this.formatDate(checkout)}
                    (${nights} ${nights === 1 ? mpcPricing.strings.perNight.replace(' per', '') : mpcPricing.strings.perNight.replace(' per', 's')})
                </p>
            </div>

            <div class="mpc-quote-breakdown">
        `;

        // Base rate
        if (quote.base_rate) {
            html += `
                <div class="mpc-quote-line">
                    <span class="mpc-quote-label">${mpcPricing.strings.baseRate}</span>
                    <span class="mpc-quote-amount">${quote.formatted_price || this.formatPrice(quote.base_rate, quote.currency || 'JPY')}</span>
                </div>
            `;
        }

        // Cleaning fee
        if (quote.cleaning_fee) {
            html += `
                <div class="mpc-quote-line">
                    <span class="mpc-quote-label">${mpcPricing.strings.cleaningFee}</span>
                    <span class="mpc-quote-amount">${this.formatPrice(quote.cleaning_fee, quote.currency || 'JPY')}</span>
                </div>
            `;
        }

        // Service fee
        if (quote.service_fee) {
            html += `
                <div class="mpc-quote-line">
                    <span class="mpc-quote-label">${mpcPricing.strings.serviceFee}</span>
                    <span class="mpc-quote-amount">${this.formatPrice(quote.service_fee, quote.currency || 'JPY')}</span>
                </div>
            `;
        }

        // Taxes and fees
        if (quote.taxes_fees) {
            html += `
                <div class="mpc-quote-line">
                    <span class="mpc-quote-label">${mpcPricing.strings.taxesFees}</span>
                    <span class="mpc-quote-amount">${this.formatPrice(quote.taxes_fees, quote.currency || 'JPY')}</span>
                </div>
            `;
        }

        // Total
        html += `
                <div class="mpc-quote-line">
                    <span class="mpc-quote-label">${mpcPricing.strings.total}</span>
                    <span class="mpc-quote-amount">${quote.formatted_price || this.formatPrice(quote.total_incl_tax, quote.currency || 'JPY')}</span>
                </div>
            </div>
        `;

        body.innerHTML = html;
    }

    /**
     * Show modal error
     */
    showModalError(modal, error) {
        const body = modal.querySelector('.mpc-quote-modal-body');
        body.innerHTML = `
            <div class="mpc-quote-error">
                <p>${error}</p>
                <button class="mpc-quote-retry" onclick="this.closest('.mpc-quote-modal').remove()">
                    ${mpcPricing.strings.retry}
                </button>
            </div>
        `;
    }

    /**
     * Close modal
     */
    closeModal(modal) {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
    }

    /**
     * Handle quick quote button click
     */
    async handleQuickQuoteClick(e) {
        e.preventDefault();
        const button = e.currentTarget;
        const propertyId = button.dataset.propertyId;
        const nights = parseInt(button.dataset.nights) || 2;

        if (!propertyId) return;

        const checkin = this.addDays(new Date(), 1);
        const checkout = this.addDays(new Date(), nights + 1);

        this.showPriceModal(propertyId, this.formatDateYMD(checkin), this.formatDateYMD(checkout));
    }

    /**
     * Get property ID from element context
     */
    getPropertyId(element) {
        // Try to find property ID from various contexts
        const calendar = element.closest('.mpc-calendar-container');
        if (calendar && calendar.dataset.propertyId) {
            return calendar.dataset.propertyId;
        }

        const propertyCard = element.closest('.mpc-property-card');
        if (propertyCard && propertyCard.dataset.propertyId) {
            return propertyCard.dataset.propertyId;
        }

        // Check if element itself has property ID
        if (element.dataset.propertyId) {
            return element.dataset.propertyId;
        }

        // Try to extract from URL or form
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('property_id') || null;
    }

    /**
     * Format price
     */
    formatPrice(amount, currency = 'JPY') {
        const number = new Intl.NumberFormat('ja-JP', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: currency === 'JPY' ? 0 : 2
        }).format(amount);

        return number;
    }

    /**
     * Format date for display
     */
    formatDate(dateStr) {
        const date = new Date(dateStr);
        return new Intl.DateTimeFormat('ja-JP', {
            month: 'short',
            day: 'numeric'
        }).format(date);
    }

    /**
     * Format date as Y-m-d
     */
    formatDateYMD(date) {
        return date.toISOString().split('T')[0];
    }

    /**
     * Add days to date
     */
    addDays(date, days) {
        const result = new Date(date);
        result.setDate(result.getDate() + days);
        return result;
    }

    /**
     * Delay helper
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Initialize when DOM is ready
jQuery(document).ready(function() {
    if (typeof mpcPricing !== 'undefined') {
        window.mpcPriceManager = new MPCPriceManager();

        // Initialize calendar price loading
        window.mpcPriceManager.initCalendar = function(selector) {
            const calendar = document.querySelector(selector);
            if (!calendar) return;

            const cells = calendar.querySelectorAll('.mpc-calendar-day:not(.past-date, .other-month)');
            cells.forEach(cell => {
                if (this.observer) {
                    this.observer.observe(cell);
                }
            });
        };

        // Initialize property card price loading
        window.mpcPriceManager.initPropertyCard = function(selector) {
            const card = document.querySelector(selector);
            if (!card) return;

            const priceElement = card.querySelector('.mpc-property-quick-price');
            if (priceElement && priceElement.classList.contains('loading')) {
                this.loadQuickPrice(card);
            }
        };

        // Initialize property grid
        window.mpcPriceManager.initPropertyGrid = function(selector) {
            const grid = document.querySelector(selector);
            if (!grid) return;

            const cards = grid.querySelectorAll('.mpc-property-card');
            cards.forEach(card => {
                this.initPropertyCard('#' + card.id);
            });
        };

        // Load quick price for property card
        window.mpcPriceManager.loadQuickPrice = async function(card) {
            const priceElement = card.querySelector('.mpc-property-quick-price');
            const propertyId = card.dataset.propertyId;
            const nights = parseInt(card.dataset.priceNights) || 2;
            const adults = parseInt(card.dataset.adults) || 2;
            const children = parseInt(card.dataset.children) || 0;
            const infants = parseInt(card.dataset.infants) || 0;
            const currency = card.dataset.currency || 'JPY';

            if (!propertyId || !priceElement) return;

            try {
                const checkin = this.addDays(new Date(), 1);
                const checkout = this.addDays(new Date(), nights + 1);

                const result = await this.getQuote(
                    propertyId,
                    this.formatDateYMD(checkin),
                    this.formatDateYMD(checkout),
                    adults,
                    children,
                    infants,
                    currency
                );

                if (result.success && result.data.total_incl_tax) {
                    priceElement.className = 'mpc-property-quick-price';
                    priceElement.textContent = mpcPricing.strings.fromPrice + ' ' +
                        (result.data.formatted_price || this.formatPrice(result.data.total_incl_tax, currency));
                } else {
                    priceElement.className = 'mpc-property-quick-price error';
                    priceElement.textContent = mpcPricing.strings.error;
                }
            } catch (error) {
                priceElement.className = 'mpc-property-quick-price error';
                priceElement.textContent = mpcPricing.strings.error;
                console.warn('Failed to load quick price for property:', propertyId, error);
            }
        };
    }
});