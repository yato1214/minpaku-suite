<?php
/**
 * Official Site Template - Calendar Section
 *
 * @var int $property_id
 * @var WP_Post $property
 */

if (!defined('ABSPATH')) {
    exit;
}

$min_stay = get_post_meta($property_id, '_minpaku_min_stay', true) ?: 1;
$max_stay = get_post_meta($property_id, '_minpaku_max_stay', true) ?: 30;
$price_per_night = get_post_meta($property_id, '_minpaku_price_per_night', true);
?>

<section class="minpaku-calendar-section" data-section="calendar" id="calendar">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php _e('Availability & Pricing', 'minpaku-suite'); ?></h2>
            <p class="section-subtitle"><?php _e('Check available dates and book your stay', 'minpaku-suite'); ?></p>
        </div>

        <div class="calendar-wrapper">
            <div class="calendar-sidebar">
                <div class="pricing-info">
                    <h3 class="pricing-title"><?php _e('Pricing', 'minpaku-suite'); ?></h3>

                    <?php if ($price_per_night): ?>
                        <div class="price-display">
                            <span class="price-amount">¥<?php echo number_format($price_per_night); ?></span>
                            <span class="price-period"><?php _e('per night', 'minpaku-suite'); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="stay-requirements">
                        <div class="requirement-item">
                            <span class="requirement-label"><?php _e('Minimum stay:', 'minpaku-suite'); ?></span>
                            <span class="requirement-value"><?php echo esc_html($min_stay); ?> <?php echo _n('night', 'nights', $min_stay, 'minpaku-suite'); ?></span>
                        </div>

                        <?php if ($max_stay < 365): ?>
                            <div class="requirement-item">
                                <span class="requirement-label"><?php _e('Maximum stay:', 'minpaku-suite'); ?></span>
                                <span class="requirement-value"><?php echo esc_html($max_stay); ?> <?php echo _n('night', 'nights', $max_stay, 'minpaku-suite'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="booking-summary" id="bookingSummary" style="display: none;">
                    <h3 class="summary-title"><?php _e('Booking Summary', 'minpaku-suite'); ?></h3>
                    <div class="summary-content">
                        <div class="summary-item">
                            <span class="summary-label"><?php _e('Check-in:', 'minpaku-suite'); ?></span>
                            <span class="summary-value" id="checkInDate">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label"><?php _e('Check-out:', 'minpaku-suite'); ?></span>
                            <span class="summary-value" id="checkOutDate">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label"><?php _e('Nights:', 'minpaku-suite'); ?></span>
                            <span class="summary-value" id="totalNights">0</span>
                        </div>
                        <?php if ($price_per_night): ?>
                            <div class="summary-item total">
                                <span class="summary-label"><?php _e('Total:', 'minpaku-suite'); ?></span>
                                <span class="summary-value" id="totalPrice">¥0</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="calendar-legend">
                    <h4 class="legend-title"><?php _e('Legend', 'minpaku-suite'); ?></h4>
                    <div class="legend-items">
                        <div class="legend-item">
                            <span class="legend-color available"></span>
                            <span class="legend-text"><?php _e('Available', 'minpaku-suite'); ?></span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color booked"></span>
                            <span class="legend-text"><?php _e('Booked', 'minpaku-suite'); ?></span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color blocked"></span>
                            <span class="legend-text"><?php _e('Blocked', 'minpaku-suite'); ?></span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color selected"></span>
                            <span class="legend-text"><?php _e('Selected', 'minpaku-suite'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="calendar-main">
                <div class="calendar-container">
                    <div class="calendar-header">
                        <button class="calendar-nav prev" id="prevMonth">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                            </svg>
                        </button>
                        <h3 class="calendar-month" id="currentMonth"></h3>
                        <button class="calendar-nav next" id="nextMonth">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                            </svg>
                        </button>
                    </div>

                    <div class="calendar-grid" id="calendarGrid">
                        <!-- Calendar will be generated by JavaScript -->
                    </div>
                </div>

                <div class="calendar-actions">
                    <button class="btn btn-secondary" id="clearSelection">
                        <?php _e('Clear Selection', 'minpaku-suite'); ?>
                    </button>
                    <a href="#quote" class="btn btn-primary" id="proceedToQuote" style="display: none;">
                        <?php _e('Get Quote', 'minpaku-suite'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Calendar Shortcode Integration -->
        <div class="calendar-shortcode-wrapper">
            <?php echo do_shortcode('[minpaku_calendar property_id="' . $property_id . '"]'); ?>
        </div>
    </div>
</section>

<style>
.minpaku-calendar-section {
    padding: 4rem 0;
    background: #f8f9fa;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 0.5rem;
}

.section-subtitle {
    font-size: 1.1rem;
    color: #666;
    margin: 0;
}

.calendar-wrapper {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2rem;
    max-width: 1000px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.calendar-sidebar {
    background: #f8f9fa;
    padding: 2rem;
    border-right: 1px solid #e9ecef;
}

.pricing-info {
    margin-bottom: 2rem;
}

.pricing-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1rem;
}

.price-display {
    text-align: center;
    padding: 1.5rem;
    background: linear-gradient(135deg, #ff6b6b, #ff8e53);
    border-radius: 8px;
    color: white;
    margin-bottom: 1.5rem;
}

.price-amount {
    display: block;
    font-size: 2rem;
    font-weight: 700;
}

.price-period {
    font-size: 1rem;
    opacity: 0.9;
}

.stay-requirements {
    space-y: 0.5rem;
}

.requirement-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e9ecef;
}

.requirement-item:last-child {
    border-bottom: none;
}

.requirement-label {
    font-size: 0.9rem;
    color: #666;
}

.requirement-value {
    font-weight: 600;
    color: #333;
}

.booking-summary {
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem;
    border: 2px solid #ff6b6b;
    margin-bottom: 2rem;
}

.summary-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1rem;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-item.total {
    font-weight: 600;
    font-size: 1.1rem;
    border-top: 2px solid #ff6b6b;
    margin-top: 0.5rem;
    padding-top: 1rem;
}

.summary-label {
    font-size: 0.9rem;
    color: #666;
}

.summary-value {
    font-weight: 600;
    color: #333;
}

.calendar-legend {
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem;
}

.legend-title {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1rem;
}

.legend-items {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    flex-shrink: 0;
}

.legend-color.available {
    background: #28a745;
}

.legend-color.booked {
    background: #dc3545;
}

.legend-color.blocked {
    background: #6c757d;
}

.legend-color.selected {
    background: #ff6b6b;
}

.legend-text {
    font-size: 0.85rem;
    color: #666;
}

.calendar-main {
    padding: 2rem;
}

.calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.calendar-nav {
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.calendar-nav:hover {
    background: #f0f0f0;
    color: #333;
}

.calendar-month {
    font-size: 1.5rem;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 2rem;
}

.calendar-day {
    background: white;
    padding: 0.75rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    position: relative;
}

.calendar-day.header {
    background: #f8f9fa;
    font-weight: 600;
    color: #666;
    cursor: default;
}

.calendar-day.other-month {
    color: #ccc;
    cursor: not-allowed;
}

.calendar-day.available:hover {
    background: #e8f5e8;
}

.calendar-day.booked {
    background: #ffe6e6;
    color: #dc3545;
    cursor: not-allowed;
}

.calendar-day.blocked {
    background: #f0f0f0;
    color: #6c757d;
    cursor: not-allowed;
}

.calendar-day.selected {
    background: #ff6b6b;
    color: white;
}

.calendar-day.in-range {
    background: #ffe6e6;
    color: #333;
}

.calendar-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: #ff6b6b;
    color: white;
}

.btn-primary:hover {
    background: #ff5252;
    transform: translateY(-2px);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.calendar-shortcode-wrapper {
    margin-top: 3rem;
    padding: 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .calendar-wrapper {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .calendar-sidebar {
        border-right: none;
        border-bottom: 1px solid #e9ecef;
    }

    .calendar-grid {
        font-size: 0.8rem;
    }

    .calendar-day {
        padding: 0.5rem;
    }

    .section-title {
        font-size: 2rem;
    }
}

@media (max-width: 480px) {
    .minpaku-calendar-section {
        padding: 2rem 0;
    }

    .container {
        padding: 0 15px;
    }

    .calendar-main,
    .calendar-sidebar {
        padding: 1.5rem;
    }

    .calendar-actions {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const propertyId = <?php echo json_encode($property_id); ?>;
    const pricePerNight = <?php echo json_encode($price_per_night ? intval($price_per_night) : 0); ?>;
    const minStay = <?php echo json_encode(intval($min_stay)); ?>;

    let currentDate = new Date();
    let selectedCheckIn = null;
    let selectedCheckOut = null;

    const calendarGrid = document.getElementById('calendarGrid');
    const currentMonthElement = document.getElementById('currentMonth');
    const prevMonthBtn = document.getElementById('prevMonth');
    const nextMonthBtn = document.getElementById('nextMonth');
    const bookingSummary = document.getElementById('bookingSummary');
    const clearSelectionBtn = document.getElementById('clearSelection');
    const proceedToQuoteBtn = document.getElementById('proceedToQuote');

    // Sample availability data - in real implementation, this would come from an API
    const availabilityData = {
        // Format: 'YYYY-MM-DD': 'available|booked|blocked'
    };

    function generateCalendar(year, month) {
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Clear calendar
        calendarGrid.innerHTML = '';

        // Update header
        const monthNames = [
            '<?php _e("January", "minpaku-suite"); ?>',
            '<?php _e("February", "minpaku-suite"); ?>',
            '<?php _e("March", "minpaku-suite"); ?>',
            '<?php _e("April", "minpaku-suite"); ?>',
            '<?php _e("May", "minpaku-suite"); ?>',
            '<?php _e("June", "minpaku-suite"); ?>',
            '<?php _e("July", "minpaku-suite"); ?>',
            '<?php _e("August", "minpaku-suite"); ?>',
            '<?php _e("September", "minpaku-suite"); ?>',
            '<?php _e("October", "minpaku-suite"); ?>',
            '<?php _e("November", "minpaku-suite"); ?>',
            '<?php _e("December", "minpaku-suite"); ?>'
        ];

        currentMonthElement.textContent = `${monthNames[month]} ${year}`;

        // Add day headers
        const dayHeaders = ['<?php _e("Su", "minpaku-suite"); ?>', '<?php _e("Mo", "minpaku-suite"); ?>', '<?php _e("Tu", "minpaku-suite"); ?>', '<?php _e("We", "minpaku-suite"); ?>', '<?php _e("Th", "minpaku-suite"); ?>', '<?php _e("Fr", "minpaku-suite"); ?>', '<?php _e("Sa", "minpaku-suite"); ?>'];
        dayHeaders.forEach(header => {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day header';
            dayElement.textContent = header;
            calendarGrid.appendChild(dayElement);
        });

        // Add empty cells for days before month starts
        const startPadding = firstDay.getDay();
        for (let i = 0; i < startPadding; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day other-month';
            calendarGrid.appendChild(emptyDay);
        }

        // Add days of the month
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const dateString = formatDate(date);

            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            dayElement.textContent = day;
            dayElement.dataset.date = dateString;

            // Determine availability
            if (date < today) {
                dayElement.classList.add('other-month');
            } else {
                const availability = getAvailability(dateString);
                dayElement.classList.add(availability);

                if (availability === 'available') {
                    dayElement.addEventListener('click', () => selectDate(date));
                }
            }

            // Mark selected dates
            if (selectedCheckIn && formatDate(selectedCheckIn) === dateString) {
                dayElement.classList.add('selected');
            }

            if (selectedCheckOut && formatDate(selectedCheckOut) === dateString) {
                dayElement.classList.add('selected');
            }

            // Mark dates in range
            if (selectedCheckIn && selectedCheckOut && date > selectedCheckIn && date < selectedCheckOut) {
                dayElement.classList.add('in-range');
            }

            calendarGrid.appendChild(dayElement);
        }
    }

    function formatDate(date) {
        return date.getFullYear() + '-' +
               String(date.getMonth() + 1).padStart(2, '0') + '-' +
               String(date.getDate()).padStart(2, '0');
    }

    function getAvailability(dateString) {
        // In real implementation, this would query the server
        // For demo purposes, randomly mark some dates as booked
        const random = Math.random();
        if (random < 0.1) return 'booked';
        if (random < 0.15) return 'blocked';
        return 'available';
    }

    function selectDate(date) {
        if (!selectedCheckIn || (selectedCheckIn && selectedCheckOut)) {
            // Select check-in date
            selectedCheckIn = date;
            selectedCheckOut = null;
        } else if (date > selectedCheckIn) {
            // Select check-out date
            selectedCheckOut = date;
        } else {
            // Reset selection
            selectedCheckIn = date;
            selectedCheckOut = null;
        }

        updateCalendarDisplay();
        updateBookingSummary();
    }

    function updateCalendarDisplay() {
        generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
    }

    function updateBookingSummary() {
        if (selectedCheckIn) {
            document.getElementById('checkInDate').textContent = selectedCheckIn.toLocaleDateString();

            if (selectedCheckOut) {
                document.getElementById('checkOutDate').textContent = selectedCheckOut.toLocaleDateString();

                const nights = Math.ceil((selectedCheckOut - selectedCheckIn) / (1000 * 60 * 60 * 24));
                document.getElementById('totalNights').textContent = nights;

                if (pricePerNight > 0) {
                    const total = nights * pricePerNight;
                    document.getElementById('totalPrice').textContent = '¥' + total.toLocaleString();
                }

                bookingSummary.style.display = 'block';
                proceedToQuoteBtn.style.display = 'inline-flex';

                // Store selection in URL parameters for quote section
                proceedToQuoteBtn.href = '#quote?checkin=' + formatDate(selectedCheckIn) + '&checkout=' + formatDate(selectedCheckOut);
            } else {
                document.getElementById('checkOutDate').textContent = '-';
                document.getElementById('totalNights').textContent = '0';
                document.getElementById('totalPrice').textContent = '¥0';
                proceedToQuoteBtn.style.display = 'none';
            }
        } else {
            bookingSummary.style.display = 'none';
            proceedToQuoteBtn.style.display = 'none';
        }
    }

    function clearSelection() {
        selectedCheckIn = null;
        selectedCheckOut = null;
        updateCalendarDisplay();
        updateBookingSummary();
    }

    // Event listeners
    prevMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        updateCalendarDisplay();
    });

    nextMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        updateCalendarDisplay();
    });

    clearSelectionBtn.addEventListener('click', clearSelection);

    // Initialize calendar
    generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
});
</script>