<?php
/**
 * MinPaku Quote Calculator Template
 * Default template for quote calculation display
 *
 * Available variables:
 * @var int    $property_id - Property ID
 * @var array  $options - Quote calculator options
 * @var string $quote_id - Unique quote calculator ID
 * @var array  $property - Property data
 * @var string $checkin - Check-in date (if preset)
 * @var string $checkout - Check-out date (if preset)
 * @var int    $guests - Number of guests (if preset)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Default options
$default_options = [
    'show_inputs' => true,
    'show_breakdown' => true,
    'show_property_info' => true,
    'currency' => 'USD',
    'currency_symbol' => '$',
    'auto_calculate' => true,
    'min_guests' => 1,
    'max_guests' => 10,
    'min_stay' => 1,
    'max_stay' => 365,
    'responsive' => true
];

$options = array_merge($default_options, $options);

// Pre-fill values
$preset_checkin = $checkin ?? '';
$preset_checkout = $checkout ?? '';
$preset_guests = $guests ?? $options['min_guests'];
?>

<div class="minpaku-quote-wrapper <?php echo $options['responsive'] ? 'responsive' : ''; ?>"
     id="<?php echo esc_attr($quote_id); ?>"
     data-minpaku-quote="true"
     data-property-id="<?php echo esc_attr($property_id); ?>"
     data-auto-calculate="<?php echo $options['auto_calculate'] ? 'true' : 'false'; ?>"
     data-currency="<?php echo esc_attr($options['currency']); ?>"
     data-currency-symbol="<?php echo esc_attr($options['currency_symbol']); ?>"
     data-min-guests="<?php echo esc_attr($options['min_guests']); ?>"
     data-max-guests="<?php echo esc_attr($options['max_guests']); ?>"
     data-min-stay="<?php echo esc_attr($options['min_stay']); ?>"
     data-max-stay="<?php echo esc_attr($options['max_stay']); ?>">

    <?php if ($options['show_property_info'] && !empty($property)): ?>
    <div class="quote-header">
        <h3 class="property-title"><?php echo esc_html($property['title']); ?></h3>
        <?php if (!empty($property['address'])): ?>
        <p class="property-address"><?php echo esc_html($property['address']); ?></p>
        <?php endif; ?>
        <?php if (!empty($property['base_rate'])): ?>
        <p class="base-rate">
            <?php _e('Starting from', 'minpaku-suite'); ?>
            <strong><?php echo esc_html($options['currency_symbol'] . number_format($property['base_rate'], 2)); ?></strong>
            <?php _e('per night', 'minpaku-suite'); ?>
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($options['show_inputs']): ?>
    <div class="quote-inputs">
        <div class="input-row">
            <div class="input-group">
                <label for="checkin-<?php echo esc_attr($quote_id); ?>">
                    <?php _e('Check-in', 'minpaku-suite'); ?>
                    <span class="required">*</span>
                </label>
                <input type="date"
                       id="checkin-<?php echo esc_attr($quote_id); ?>"
                       class="quote-checkin"
                       value="<?php echo esc_attr($preset_checkin); ?>"
                       min="<?php echo esc_attr(date('Y-m-d')); ?>"
                       required />
            </div>
            <div class="input-group">
                <label for="checkout-<?php echo esc_attr($quote_id); ?>">
                    <?php _e('Check-out', 'minpaku-suite'); ?>
                    <span class="required">*</span>
                </label>
                <input type="date"
                       id="checkout-<?php echo esc_attr($quote_id); ?>"
                       class="quote-checkout"
                       value="<?php echo esc_attr($preset_checkout); ?>"
                       min="<?php echo esc_attr(date('Y-m-d', strtotime('+1 day'))); ?>"
                       required />
            </div>
        </div>

        <div class="input-row">
            <div class="input-group">
                <label for="guests-<?php echo esc_attr($quote_id); ?>">
                    <?php _e('Guests', 'minpaku-suite'); ?>
                    <span class="required">*</span>
                </label>
                <select id="guests-<?php echo esc_attr($quote_id); ?>"
                        class="quote-guests"
                        required>
                    <?php for ($i = $options['min_guests']; $i <= $options['max_guests']; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php selected($i, $preset_guests); ?>>
                        <?php echo $i; ?> <?php echo $i === 1 ? __('Guest', 'minpaku-suite') : __('Guests', 'minpaku-suite'); ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="input-group">
                <button type="button" class="calculate-quote button button-primary">
                    <?php _e('Calculate Quote', 'minpaku-suite'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="quote-results" style="display: none;">
        <div class="quote-loading" style="display: none;">
            <div class="loader-spinner">
                <div class="spinner"></div>
            </div>
            <span class="loader-text"><?php _e('Calculating quote...', 'minpaku-suite'); ?></span>
        </div>

        <div class="quote-content">
            <div class="quote-summary">
                <div class="summary-header">
                    <h4><?php _e('Your Quote', 'minpaku-suite'); ?></h4>
                    <div class="date-range">
                        <span class="dates"></span>
                        <span class="nights"></span>
                    </div>
                </div>

                <div class="total-price">
                    <span class="currency"><?php echo esc_html($options['currency_symbol']); ?></span>
                    <span class="amount">0.00</span>
                    <span class="total-label"><?php _e('Total', 'minpaku-suite'); ?></span>
                </div>
            </div>

            <?php if ($options['show_breakdown']): ?>
            <div class="quote-breakdown">
                <h5><?php _e('Price Breakdown', 'minpaku-suite'); ?></h5>

                <div class="breakdown-section accommodation">
                    <h6><?php _e('Accommodation', 'minpaku-suite'); ?></h6>
                    <div class="breakdown-items">
                        <!-- Accommodation items will be populated by JavaScript -->
                    </div>
                </div>

                <div class="breakdown-section fees" style="display: none;">
                    <h6><?php _e('Fees & Taxes', 'minpaku-suite'); ?></h6>
                    <div class="breakdown-items">
                        <!-- Fee items will be populated by JavaScript -->
                    </div>
                </div>

                <div class="breakdown-section adjustments" style="display: none;">
                    <h6><?php _e('Adjustments', 'minpaku-suite'); ?></h6>
                    <div class="breakdown-items">
                        <!-- Adjustment items will be populated by JavaScript -->
                    </div>
                </div>

                <div class="breakdown-total">
                    <div class="breakdown-item total">
                        <span class="item-label"><?php _e('Total Amount', 'minpaku-suite'); ?></span>
                        <span class="item-value">
                            <span class="currency"><?php echo esc_html($options['currency_symbol']); ?></span>
                            <span class="amount">0.00</span>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="quote-actions">
                <button type="button" class="book-now button button-primary">
                    <?php _e('Book Now', 'minpaku-suite'); ?>
                </button>
                <button type="button" class="save-quote button">
                    <?php _e('Save Quote', 'minpaku-suite'); ?>
                </button>
                <button type="button" class="share-quote button">
                    <?php _e('Share Quote', 'minpaku-suite'); ?>
                </button>
            </div>
        </div>

        <div class="quote-error" style="display: none;">
            <div class="error-icon">⚠️</div>
            <div class="error-content">
                <h5><?php _e('Unable to Calculate Quote', 'minpaku-suite'); ?></h5>
                <p class="error-message"></p>
                <button type="button" class="retry-quote button">
                    <?php _e('Try Again', 'minpaku-suite'); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="quote-notes">
        <div class="note">
            <strong><?php _e('Note:', 'minpaku-suite'); ?></strong>
            <?php _e('Prices are estimates and may vary based on availability and seasonal rates.', 'minpaku-suite'); ?>
        </div>
        <div class="policies">
            <a href="#" class="policy-link"><?php _e('Cancellation Policy', 'minpaku-suite'); ?></a>
            <span class="separator">|</span>
            <a href="#" class="policy-link"><?php _e('House Rules', 'minpaku-suite'); ?></a>
        </div>
    </div>

</div>

<style>
.minpaku-quote-wrapper {
    max-width: 600px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.minpaku-quote-wrapper.responsive {
    width: 100%;
}

.quote-header {
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
    text-align: center;
}

.property-title {
    margin: 0 0 8px 0;
    font-size: 1.4em;
    color: #333;
    font-weight: 600;
}

.property-address {
    margin: 0 0 12px 0;
    color: #666;
    font-size: 0.9em;
}

.base-rate {
    margin: 0;
    color: #007cba;
    font-size: 1.1em;
}

.quote-inputs {
    padding: 20px;
    background: white;
}

.input-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.input-row:last-child {
    margin-bottom: 0;
}

.input-group {
    display: flex;
    flex-direction: column;
}

.input-group label {
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
    font-size: 0.9em;
}

.required {
    color: #d63384;
}

.input-group input,
.input-group select {
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    background: white;
}

.input-group input:focus,
.input-group select:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
}

.calculate-quote {
    background: #007cba;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    height: fit-content;
    align-self: end;
}

.calculate-quote:hover {
    background: #005a87;
}

.calculate-quote:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.quote-results {
    border-top: 1px solid #eee;
}

.quote-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
    padding: 40px 20px;
}

.loader-spinner .spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loader-text {
    color: #666;
    font-size: 16px;
}

.quote-content {
    padding: 20px;
}

.quote-summary {
    background: #e3f2fd;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.summary-header h4 {
    margin: 0;
    color: #1565c0;
    font-size: 1.2em;
}

.date-range {
    text-align: right;
    font-size: 0.9em;
    color: #666;
}

.date-range .dates {
    display: block;
    font-weight: 600;
    color: #333;
}

.date-range .nights {
    display: block;
    margin-top: 2px;
}

.total-price {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 5px;
}

.total-price .currency {
    font-size: 1.2em;
    color: #1565c0;
}

.total-price .amount {
    font-size: 2.5em;
    font-weight: bold;
    color: #1565c0;
}

.total-price .total-label {
    font-size: 1em;
    color: #666;
    margin-left: 10px;
}

.quote-breakdown {
    background: #f8f9fa;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.quote-breakdown h5 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 1.1em;
    border-bottom: 1px solid #ddd;
    padding-bottom: 8px;
}

.breakdown-section {
    margin-bottom: 20px;
}

.breakdown-section:last-child {
    margin-bottom: 0;
}

.breakdown-section h6 {
    margin: 0 0 10px 0;
    color: #555;
    font-size: 1em;
    font-weight: 600;
}

.breakdown-items {
    margin-left: 15px;
}

.breakdown-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.breakdown-item:last-child {
    border-bottom: none;
}

.breakdown-item.total {
    border-top: 2px solid #ddd;
    padding-top: 15px;
    margin-top: 10px;
    font-weight: bold;
    font-size: 1.1em;
}

.item-label {
    color: #333;
}

.item-value {
    color: #007cba;
    font-weight: 600;
}

.item-details {
    font-size: 0.85em;
    color: #666;
    margin-top: 2px;
}

.quote-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.quote-actions .button {
    flex: 1;
    text-align: center;
    padding: 12px 20px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid #ddd;
    text-decoration: none;
    display: inline-block;
}

.book-now {
    background: #28a745;
    color: white;
    border-color: #28a745;
}

.book-now:hover {
    background: #218838;
    border-color: #218838;
    color: white;
}

.save-quote {
    background: white;
    color: #007cba;
    border-color: #007cba;
}

.save-quote:hover {
    background: #007cba;
    color: white;
}

.share-quote {
    background: white;
    color: #6c757d;
    border-color: #6c757d;
}

.share-quote:hover {
    background: #6c757d;
    color: white;
}

.quote-error {
    padding: 20px;
    text-align: center;
}

.error-icon {
    font-size: 2em;
    margin-bottom: 10px;
}

.error-content h5 {
    margin: 0 0 10px 0;
    color: #dc3545;
}

.error-message {
    margin: 0 0 15px 0;
    color: #666;
}

.retry-quote {
    background: #dc3545;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
}

.retry-quote:hover {
    background: #c82333;
}

.quote-notes {
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
    font-size: 0.85em;
    color: #666;
}

.note {
    margin-bottom: 10px;
}

.policies {
    text-align: center;
}

.policy-link {
    color: #007cba;
    text-decoration: none;
}

.policy-link:hover {
    text-decoration: underline;
}

.separator {
    margin: 0 8px;
    color: #ccc;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .input-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .summary-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }

    .date-range {
        text-align: center;
    }

    .total-price {
        flex-direction: column;
        gap: 0;
    }

    .total-price .total-label {
        margin-left: 0;
        margin-top: 5px;
    }

    .quote-actions {
        flex-direction: column;
    }

    .quote-actions .button {
        flex: none;
    }
}

@media (max-width: 480px) {
    .minpaku-quote-wrapper {
        margin: 0;
        border-radius: 0;
        border-left: none;
        border-right: none;
    }

    .quote-header,
    .quote-inputs,
    .quote-content {
        padding: 15px;
    }

    .quote-summary {
        padding: 15px;
    }

    .quote-breakdown {
        padding: 15px;
    }

    .total-price .amount {
        font-size: 2em;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quoteWrappers = document.querySelectorAll('[data-minpaku-quote]');

    quoteWrappers.forEach(wrapper => {
        const calculator = new MinPakuQuoteCalculator(wrapper);
    });
});

class MinPakuQuoteCalculator {
    constructor(wrapper) {
        this.wrapper = wrapper;
        this.propertyId = wrapper.dataset.propertyId;
        this.autoCalculate = wrapper.dataset.autoCalculate === 'true';
        this.currency = wrapper.dataset.currency || 'USD';
        this.currencySymbol = wrapper.dataset.currencySymbol || '$';

        this.checkinInput = wrapper.querySelector('.quote-checkin');
        this.checkoutInput = wrapper.querySelector('.quote-checkout');
        this.guestsInput = wrapper.querySelector('.quote-guests');
        this.calculateBtn = wrapper.querySelector('.calculate-quote');
        this.resultsDiv = wrapper.querySelector('.quote-results');

        this.init();
    }

    init() {
        this.attachEvents();

        // Auto-calculate if dates are pre-filled
        if (this.autoCalculate && this.checkinInput?.value && this.checkoutInput?.value) {
            this.calculateQuote();
        }
    }

    attachEvents() {
        if (this.calculateBtn) {
            this.calculateBtn.addEventListener('click', () => this.calculateQuote());
        }

        if (this.autoCalculate) {
            [this.checkinInput, this.checkoutInput, this.guestsInput].forEach(input => {
                if (input) {
                    input.addEventListener('change', () => {
                        if (this.checkinInput?.value && this.checkoutInput?.value) {
                            this.calculateQuote();
                        }
                    });
                }
            });
        }

        // Book now button
        this.wrapper.addEventListener('click', (e) => {
            if (e.target.matches('.book-now')) {
                this.handleBookNow();
            }
            if (e.target.matches('.save-quote')) {
                this.handleSaveQuote();
            }
            if (e.target.matches('.share-quote')) {
                this.handleShareQuote();
            }
            if (e.target.matches('.retry-quote')) {
                this.calculateQuote();
            }
        });
    }

    async calculateQuote() {
        const checkin = this.checkinInput?.value;
        const checkout = this.checkoutInput?.value;
        const guests = this.guestsInput?.value || 1;

        if (!checkin || !checkout) {
            this.showError('Please select check-in and check-out dates.');
            return;
        }

        this.showLoading();

        try {
            const response = await fetch(minpaku_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'minpaku_calculate_quote',
                    property_id: this.propertyId,
                    checkin: checkin,
                    checkout: checkout,
                    guests: guests,
                    nonce: minpaku_ajax.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                this.displayQuote(data.data);
            } else {
                this.showError(data.data.message || 'Failed to calculate quote.');
            }
        } catch (error) {
            console.error('Quote calculation error:', error);
            this.showError('Network error occurred. Please try again.');
        }
    }

    showLoading() {
        this.resultsDiv.style.display = 'block';
        this.wrapper.querySelector('.quote-loading').style.display = 'flex';
        this.wrapper.querySelector('.quote-content').style.display = 'none';
        this.wrapper.querySelector('.quote-error').style.display = 'none';
    }

    displayQuote(quoteData) {
        this.wrapper.querySelector('.quote-loading').style.display = 'none';
        this.wrapper.querySelector('.quote-content').style.display = 'block';
        this.wrapper.querySelector('.quote-error').style.display = 'none';

        // Update summary
        this.updateSummary(quoteData);
        this.updateBreakdown(quoteData);
    }

    updateSummary(quoteData) {
        const checkinDate = new Date(this.checkinInput.value);
        const checkoutDate = new Date(this.checkoutInput.value);
        const nights = Math.ceil((checkoutDate - checkinDate) / (1000 * 60 * 60 * 24));

        // Update dates and nights
        const datesSpan = this.wrapper.querySelector('.date-range .dates');
        const nightsSpan = this.wrapper.querySelector('.date-range .nights');

        if (datesSpan) {
            datesSpan.textContent = `${checkinDate.toLocaleDateString()} - ${checkoutDate.toLocaleDateString()}`;
        }
        if (nightsSpan) {
            nightsSpan.textContent = `${nights} ${nights === 1 ? 'night' : 'nights'}`;
        }

        // Update total price
        const totalAmount = this.wrapper.querySelector('.total-price .amount');
        if (totalAmount) {
            totalAmount.textContent = parseFloat(quoteData.total).toFixed(2);
        }
    }

    updateBreakdown(quoteData) {
        if (!quoteData.breakdown) return;

        this.updateBreakdownSection('accommodation', quoteData.breakdown.accommodation || []);
        this.updateBreakdownSection('fees', quoteData.breakdown.fees || []);
        this.updateBreakdownSection('adjustments', quoteData.breakdown.adjustments || []);

        // Update total
        const totalAmountSpan = this.wrapper.querySelector('.breakdown-total .amount');
        if (totalAmountSpan) {
            totalAmountSpan.textContent = parseFloat(quoteData.total).toFixed(2);
        }
    }

    updateBreakdownSection(sectionName, items) {
        const section = this.wrapper.querySelector(`.breakdown-section.${sectionName}`);
        if (!section) return;

        const itemsContainer = section.querySelector('.breakdown-items');
        if (!itemsContainer) return;

        if (items.length === 0) {
            section.style.display = 'none';
            return;
        }

        section.style.display = 'block';
        itemsContainer.innerHTML = '';

        items.forEach(item => {
            const itemDiv = document.createElement('div');
            itemDiv.className = 'breakdown-item';

            const labelSpan = document.createElement('span');
            labelSpan.className = 'item-label';
            labelSpan.textContent = item.label;

            const valueSpan = document.createElement('span');
            valueSpan.className = 'item-value';
            valueSpan.innerHTML = `<span class="currency">${this.currencySymbol}</span>${parseFloat(item.amount).toFixed(2)}`;

            itemDiv.appendChild(labelSpan);
            itemDiv.appendChild(valueSpan);

            if (item.details) {
                const detailsDiv = document.createElement('div');
                detailsDiv.className = 'item-details';
                detailsDiv.textContent = item.details;
                labelSpan.appendChild(detailsDiv);
            }

            itemsContainer.appendChild(itemDiv);
        });
    }

    showError(message) {
        this.wrapper.querySelector('.quote-loading').style.display = 'none';
        this.wrapper.querySelector('.quote-content').style.display = 'none';
        this.wrapper.querySelector('.quote-error').style.display = 'block';

        const errorMessage = this.wrapper.querySelector('.error-message');
        if (errorMessage) {
            errorMessage.textContent = message;
        }

        this.resultsDiv.style.display = 'block';
    }

    handleBookNow() {
        // Trigger custom event for booking
        const event = new CustomEvent('minpaku:booknow', {
            detail: {
                propertyId: this.propertyId,
                checkin: this.checkinInput?.value,
                checkout: this.checkoutInput?.value,
                guests: this.guestsInput?.value
            }
        });
        this.wrapper.dispatchEvent(event);
    }

    handleSaveQuote() {
        // Implement save quote functionality
        console.log('Save quote functionality not implemented yet');
    }

    handleShareQuote() {
        // Implement share quote functionality
        const url = new URL(window.location);
        url.searchParams.set('property_id', this.propertyId);
        url.searchParams.set('checkin', this.checkinInput?.value);
        url.searchParams.set('checkout', this.checkoutInput?.value);
        url.searchParams.set('guests', this.guestsInput?.value);

        if (navigator.share) {
            navigator.share({
                title: 'Property Quote',
                url: url.toString()
            });
        } else {
            navigator.clipboard.writeText(url.toString()).then(() => {
                alert('Quote URL copied to clipboard!');
            });
        }
    }
}

window.MinPakuQuoteCalculator = MinPakuQuoteCalculator;
</script>