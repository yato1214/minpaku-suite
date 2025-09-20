/**
 * MinPaku Calendar JavaScript
 * Handles AJAX availability fetching and calendar interactions
 */

class MinPakuCalendar {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('MinPaku Calendar: Container not found:', containerId);
            return;
        }

        this.options = {
            propertyId: null,
            startDate: null,
            endDate: null,
            months: 2,
            minStay: 1,
            maxStay: 365,
            checkInOut: false,
            ...options
        };

        this.selectedDates = {
            checkIn: null,
            checkOut: null
        };

        this.availabilityData = {};
        this.isLoading = false;

        this.init();
    }

    init() {
        this.render();
        this.attachEvents();
        this.loadAvailability();
    }

    render() {
        const currentDate = new Date();
        if (this.options.startDate) {
            currentDate.setTime(new Date(this.options.startDate).getTime());
        }

        let html = '<div class="minpaku-calendar-wrapper">';

        if (this.options.checkInOut) {
            html += this.renderDateInputs();
        }

        html += '<div class="minpaku-calendar-container">';
        html += '<div class="calendar-navigation">';
        html += '<button class="nav-prev" data-direction="prev">&lt;</button>';
        html += '<div class="current-months"></div>';
        html += '<button class="nav-next" data-direction="next">&gt;</button>';
        html += '</div>';

        html += '<div class="calendar-grid-container">';
        for (let i = 0; i < this.options.months; i++) {
            const monthDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + i, 1);
            html += this.renderMonth(monthDate);
        }
        html += '</div>';
        html += '</div>';

        html += '<div class="calendar-legend">';
        html += '<div class="legend-item"><span class="available"></span> Available</div>';
        html += '<div class="legend-item"><span class="unavailable"></span> Unavailable</div>';
        html += '<div class="legend-item"><span class="selected"></span> Selected</div>';
        html += '<div class="legend-item"><span class="check-in"></span> Check-in</div>';
        html += '<div class="legend-item"><span class="check-out"></span> Check-out</div>';
        html += '</div>';

        html += '</div>';

        this.container.innerHTML = html;
        this.updateMonthsDisplay();
    }

    renderDateInputs() {
        return `
            <div class="date-inputs">
                <div class="date-input-group">
                    <label for="checkin-input">Check-in</label>
                    <input type="date" id="checkin-input" class="checkin-input" />
                </div>
                <div class="date-input-group">
                    <label for="checkout-input">Check-out</label>
                    <input type="date" id="checkout-input" class="checkout-input" />
                </div>
                <button class="clear-dates">Clear Dates</button>
            </div>
        `;
    }

    renderMonth(date) {
        const year = date.getFullYear();
        const month = date.getMonth();
        const monthName = date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());

        let html = `<div class="calendar-month" data-year="${year}" data-month="${month}">`;
        html += `<div class="month-header">${monthName}</div>`;
        html += '<div class="weekdays">';
        const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        weekdays.forEach(day => {
            html += `<div class="weekday">${day}</div>`;
        });
        html += '</div>';

        html += '<div class="days-grid">';
        const currentDate = new Date(startDate);

        for (let week = 0; week < 6; week++) {
            for (let day = 0; day < 7; day++) {
                const dateStr = this.formatDate(currentDate);
                const isCurrentMonth = currentDate.getMonth() === month;
                const isToday = this.isToday(currentDate);
                const isPast = currentDate < new Date().setHours(0, 0, 0, 0);

                let classes = ['calendar-day'];
                if (!isCurrentMonth) classes.push('other-month');
                if (isToday) classes.push('today');
                if (isPast) classes.push('past');

                html += `<div class="${classes.join(' ')}" data-date="${dateStr}">`;
                html += `<span class="day-number">${currentDate.getDate()}</span>`;
                html += '</div>';

                currentDate.setDate(currentDate.getDate() + 1);
            }
            if (currentDate.getMonth() !== month) break;
        }
        html += '</div></div>';

        return html;
    }

    attachEvents() {
        // Navigation events
        this.container.addEventListener('click', (e) => {
            if (e.target.matches('.nav-prev') || e.target.matches('.nav-next')) {
                this.navigate(e.target.dataset.direction);
            }

            if (e.target.matches('.calendar-day:not(.past):not(.other-month)') ||
                e.target.closest('.calendar-day:not(.past):not(.other-month)')) {
                const dayEl = e.target.matches('.calendar-day') ? e.target : e.target.closest('.calendar-day');
                this.selectDate(dayEl.dataset.date);
            }

            if (e.target.matches('.clear-dates')) {
                this.clearSelection();
            }
        });

        // Date input events
        if (this.options.checkInOut) {
            const checkInInput = this.container.querySelector('.checkin-input');
            const checkOutInput = this.container.querySelector('.checkout-input');

            if (checkInInput) {
                checkInInput.addEventListener('change', (e) => {
                    this.selectDate(e.target.value, 'checkin');
                });
            }

            if (checkOutInput) {
                checkOutInput.addEventListener('change', (e) => {
                    this.selectDate(e.target.value, 'checkout');
                });
            }
        }
    }

    navigate(direction) {
        const months = this.container.querySelectorAll('.calendar-month');
        const firstMonth = months[0];
        const currentYear = parseInt(firstMonth.dataset.year);
        const currentMonth = parseInt(firstMonth.dataset.month);

        let newDate;
        if (direction === 'prev') {
            newDate = new Date(currentYear, currentMonth - 1, 1);
        } else {
            newDate = new Date(currentYear, currentMonth + 1, 1);
        }

        this.renderCalendarGrid(newDate);
        this.loadAvailability();
        this.updateMonthsDisplay();
        this.updateCalendarDays();
    }

    renderCalendarGrid(startDate) {
        const container = this.container.querySelector('.calendar-grid-container');
        let html = '';

        for (let i = 0; i < this.options.months; i++) {
            const monthDate = new Date(startDate.getFullYear(), startDate.getMonth() + i, 1);
            html += this.renderMonth(monthDate);
        }

        container.innerHTML = html;
    }

    updateMonthsDisplay() {
        const months = this.container.querySelectorAll('.calendar-month');
        const monthsDisplay = this.container.querySelector('.current-months');

        if (months.length === 0) return;

        const firstMonth = months[0];
        const lastMonth = months[months.length - 1];

        const firstDate = new Date(
            parseInt(firstMonth.dataset.year),
            parseInt(firstMonth.dataset.month),
            1
        );

        const lastDate = new Date(
            parseInt(lastMonth.dataset.year),
            parseInt(lastMonth.dataset.month),
            1
        );

        if (firstDate.getFullYear() === lastDate.getFullYear()) {
            if (firstDate.getMonth() === lastDate.getMonth()) {
                monthsDisplay.textContent = firstDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            } else {
                monthsDisplay.textContent = `${firstDate.toLocaleDateString('en-US', { month: 'long' })} - ${lastDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}`;
            }
        } else {
            monthsDisplay.textContent = `${firstDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })} - ${lastDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}`;
        }
    }

    selectDate(dateStr, type = null) {
        const date = new Date(dateStr);

        if (this.options.checkInOut) {
            if (type === 'checkin' || (!this.selectedDates.checkIn || (this.selectedDates.checkIn && this.selectedDates.checkOut))) {
                this.selectedDates.checkIn = date;
                this.selectedDates.checkOut = null;
            } else if (date > this.selectedDates.checkIn) {
                this.selectedDates.checkOut = date;
            } else {
                this.selectedDates.checkIn = date;
                this.selectedDates.checkOut = null;
            }

            this.updateDateInputs();
        } else {
            this.selectedDates.checkIn = date;
        }

        this.updateCalendarDays();
        this.triggerDateChange();
    }

    clearSelection() {
        this.selectedDates.checkIn = null;
        this.selectedDates.checkOut = null;
        this.updateDateInputs();
        this.updateCalendarDays();
        this.triggerDateChange();
    }

    updateDateInputs() {
        if (!this.options.checkInOut) return;

        const checkInInput = this.container.querySelector('.checkin-input');
        const checkOutInput = this.container.querySelector('.checkout-input');

        if (checkInInput) {
            checkInInput.value = this.selectedDates.checkIn ? this.formatDate(this.selectedDates.checkIn) : '';
        }

        if (checkOutInput) {
            checkOutInput.value = this.selectedDates.checkOut ? this.formatDate(this.selectedDates.checkOut) : '';
        }
    }

    updateCalendarDays() {
        const days = this.container.querySelectorAll('.calendar-day');

        days.forEach(day => {
            const dateStr = day.dataset.date;
            const date = new Date(dateStr);

            // Reset classes
            day.classList.remove('available', 'unavailable', 'selected', 'check-in', 'check-out', 'in-range');

            // Add availability class
            const availability = this.availabilityData[dateStr];
            if (availability !== undefined) {
                day.classList.add(availability ? 'available' : 'unavailable');
            }

            // Add selection classes
            if (this.selectedDates.checkIn && this.dateEquals(date, this.selectedDates.checkIn)) {
                day.classList.add('check-in', 'selected');
            }

            if (this.selectedDates.checkOut && this.dateEquals(date, this.selectedDates.checkOut)) {
                day.classList.add('check-out', 'selected');
            }

            // Add in-range class
            if (this.selectedDates.checkIn && this.selectedDates.checkOut &&
                date > this.selectedDates.checkIn && date < this.selectedDates.checkOut) {
                day.classList.add('in-range', 'selected');
            }
        });
    }

    async loadAvailability() {
        if (!this.options.propertyId || this.isLoading) return;

        this.isLoading = true;
        this.showLoading();

        try {
            const months = this.container.querySelectorAll('.calendar-month');
            if (months.length === 0) return;

            const firstMonth = months[0];
            const lastMonth = months[months.length - 1];

            const startDate = new Date(
                parseInt(firstMonth.dataset.year),
                parseInt(firstMonth.dataset.month),
                1
            );

            const endDate = new Date(
                parseInt(lastMonth.dataset.year),
                parseInt(lastMonth.dataset.month) + 1,
                0
            );

            const response = await fetch(minpaku_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'minpaku_get_availability',
                    property_id: this.options.propertyId,
                    start_date: this.formatDate(startDate),
                    end_date: this.formatDate(endDate),
                    nonce: minpaku_ajax.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                this.availabilityData = { ...this.availabilityData, ...data.data.availability };
                this.updateCalendarDays();
            } else {
                console.error('MinPaku Calendar: Failed to load availability:', data.data.message);
            }
        } catch (error) {
            console.error('MinPaku Calendar: Error loading availability:', error);
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }

    showLoading() {
        let loader = this.container.querySelector('.calendar-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'calendar-loader';
            loader.innerHTML = '<div class="loader-spinner"></div><span>Loading availability...</span>';
            this.container.appendChild(loader);
        }
        loader.style.display = 'flex';
    }

    hideLoading() {
        const loader = this.container.querySelector('.calendar-loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    triggerDateChange() {
        const event = new CustomEvent('minpaku:datechange', {
            detail: {
                checkIn: this.selectedDates.checkIn,
                checkOut: this.selectedDates.checkOut,
                propertyId: this.options.propertyId
            }
        });
        this.container.dispatchEvent(event);
    }

    // Utility methods
    formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    dateEquals(date1, date2) {
        return this.formatDate(date1) === this.formatDate(date2);
    }

    isToday(date) {
        const today = new Date();
        return this.dateEquals(date, today);
    }

    // Public API methods
    getSelectedDates() {
        return {
            checkIn: this.selectedDates.checkIn,
            checkOut: this.selectedDates.checkOut
        };
    }

    setSelectedDates(checkIn, checkOut = null) {
        this.selectedDates.checkIn = checkIn ? new Date(checkIn) : null;
        this.selectedDates.checkOut = checkOut ? new Date(checkOut) : null;
        this.updateDateInputs();
        this.updateCalendarDays();
        this.triggerDateChange();
    }

    refresh() {
        this.availabilityData = {};
        this.loadAvailability();
    }

    updateOptions(newOptions) {
        this.options = { ...this.options, ...newOptions };
        this.render();
        this.attachEvents();
        this.loadAvailability();
    }
}

// Auto-initialize calendars on page load
document.addEventListener('DOMContentLoaded', function() {
    const calendars = document.querySelectorAll('[data-minpaku-calendar]');

    calendars.forEach(calendar => {
        const options = {
            propertyId: calendar.dataset.propertyId,
            startDate: calendar.dataset.startDate,
            endDate: calendar.dataset.endDate,
            months: parseInt(calendar.dataset.months) || 2,
            minStay: parseInt(calendar.dataset.minStay) || 1,
            maxStay: parseInt(calendar.dataset.maxStay) || 365,
            checkInOut: calendar.dataset.checkInOut === 'true'
        };

        new MinPakuCalendar(calendar.id, options);
    });
});

// Export for manual initialization
window.MinPakuCalendar = MinPakuCalendar;