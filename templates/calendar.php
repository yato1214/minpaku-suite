<?php
/**
 * MinPaku Calendar Template
 * Default template for availability calendar display
 *
 * Available variables:
 * @var int    $property_id - Property ID
 * @var array  $options - Calendar options
 * @var string $calendar_id - Unique calendar ID
 * @var array  $property - Property data
 */

if (!defined('ABSPATH')) {
    exit;
}

// Default options
$default_options = [
    'months' => 2,
    'check_in_out' => false,
    'start_date' => date('Y-m-d'),
    'min_stay' => 1,
    'max_stay' => 365,
    'show_legend' => true,
    'show_navigation' => true,
    'responsive' => true
];

$options = array_merge($default_options, $options);
?>

<div class="minpaku-calendar-wrapper <?php echo $options['responsive'] ? 'responsive' : ''; ?>"
     id="<?php echo esc_attr($calendar_id); ?>"
     data-minpaku-calendar="true"
     data-property-id="<?php echo esc_attr($property_id); ?>"
     data-months="<?php echo esc_attr($options['months']); ?>"
     data-check-in-out="<?php echo $options['check_in_out'] ? 'true' : 'false'; ?>"
     data-start-date="<?php echo esc_attr($options['start_date']); ?>"
     data-min-stay="<?php echo esc_attr($options['min_stay']); ?>"
     data-max-stay="<?php echo esc_attr($options['max_stay']); ?>">

    <?php if (!empty($property)): ?>
    <div class="calendar-header">
        <h3 class="property-title"><?php echo esc_html($property['title']); ?></h3>
        <?php if (!empty($property['address'])): ?>
        <p class="property-address"><?php echo esc_html($property['address']); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($options['check_in_out']): ?>
    <div class="date-inputs">
        <div class="date-input-group">
            <label for="checkin-input-<?php echo esc_attr($calendar_id); ?>">
                <?php _e('Check-in', 'minpaku-suite'); ?>
            </label>
            <input type="date"
                   id="checkin-input-<?php echo esc_attr($calendar_id); ?>"
                   class="checkin-input"
                   min="<?php echo esc_attr(date('Y-m-d')); ?>" />
        </div>
        <div class="date-input-group">
            <label for="checkout-input-<?php echo esc_attr($calendar_id); ?>">
                <?php _e('Check-out', 'minpaku-suite'); ?>
            </label>
            <input type="date"
                   id="checkout-input-<?php echo esc_attr($calendar_id); ?>"
                   class="checkout-input"
                   min="<?php echo esc_attr(date('Y-m-d', strtotime('+1 day'))); ?>" />
        </div>
        <button class="clear-dates button">
            <?php _e('Clear Dates', 'minpaku-suite'); ?>
        </button>
    </div>
    <?php endif; ?>

    <div class="minpaku-calendar-container">
        <?php if ($options['show_navigation']): ?>
        <div class="calendar-navigation">
            <button class="nav-prev" data-direction="prev" aria-label="<?php esc_attr_e('Previous month', 'minpaku-suite'); ?>">
                <span class="nav-arrow">&larr;</span>
                <span class="nav-text"><?php _e('Previous', 'minpaku-suite'); ?></span>
            </button>
            <div class="current-months"></div>
            <button class="nav-next" data-direction="next" aria-label="<?php esc_attr_e('Next month', 'minpaku-suite'); ?>">
                <span class="nav-text"><?php _e('Next', 'minpaku-suite'); ?></span>
                <span class="nav-arrow">&rarr;</span>
            </button>
        </div>
        <?php endif; ?>

        <div class="calendar-grid-container">
            <!-- Calendar grid will be populated by JavaScript -->
        </div>

        <div class="calendar-loader" style="display: none;">
            <div class="loader-spinner">
                <div class="spinner"></div>
            </div>
            <span class="loader-text"><?php _e('Loading availability...', 'minpaku-suite'); ?></span>
        </div>
    </div>

    <?php if ($options['show_legend']): ?>
    <div class="calendar-legend">
        <div class="legend-item">
            <span class="legend-dot available"></span>
            <span class="legend-label"><?php _e('Available', 'minpaku-suite'); ?></span>
        </div>
        <div class="legend-item">
            <span class="legend-dot unavailable"></span>
            <span class="legend-label"><?php _e('Unavailable', 'minpaku-suite'); ?></span>
        </div>
        <div class="legend-item">
            <span class="legend-dot selected"></span>
            <span class="legend-label"><?php _e('Selected', 'minpaku-suite'); ?></span>
        </div>
        <?php if ($options['check_in_out']): ?>
        <div class="legend-item">
            <span class="legend-dot check-in"></span>
            <span class="legend-label"><?php _e('Check-in', 'minpaku-suite'); ?></span>
        </div>
        <div class="legend-item">
            <span class="legend-dot check-out"></span>
            <span class="legend-label"><?php _e('Check-out', 'minpaku-suite'); ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($options['check_in_out']): ?>
    <div class="selected-dates-display" style="display: none;">
        <div class="selected-dates-content">
            <h4><?php _e('Selected Dates', 'minpaku-suite'); ?></h4>
            <div class="dates-summary">
                <div class="check-in-date">
                    <strong><?php _e('Check-in:', 'minpaku-suite'); ?></strong>
                    <span class="date-value">-</span>
                </div>
                <div class="check-out-date">
                    <strong><?php _e('Check-out:', 'minpaku-suite'); ?></strong>
                    <span class="date-value">-</span>
                </div>
                <div class="nights-count">
                    <strong><?php _e('Nights:', 'minpaku-suite'); ?></strong>
                    <span class="nights-value">0</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<style>
.minpaku-calendar-wrapper {
    max-width: 100%;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

.minpaku-calendar-wrapper.responsive {
    width: 100%;
}

.calendar-header {
    margin-bottom: 20px;
    text-align: center;
}

.property-title {
    margin: 0 0 5px 0;
    font-size: 1.5em;
    color: #333;
}

.property-address {
    margin: 0;
    color: #666;
    font-size: 0.9em;
}

.date-inputs {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    align-items: end;
    flex-wrap: wrap;
}

.date-input-group {
    flex: 1;
    min-width: 140px;
}

.date-input-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
    font-size: 0.9em;
}

.date-input-group input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.date-input-group input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
}

.clear-dates {
    background: #f0f0f0;
    border: 1px solid #ccc;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    white-space: nowrap;
}

.clear-dates:hover {
    background: #e0e0e0;
}

.calendar-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 0 10px;
}

.nav-prev, .nav-next {
    background: #007cba;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
}

.nav-prev:hover, .nav-next:hover {
    background: #005a87;
}

.nav-prev:disabled, .nav-next:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.current-months {
    font-weight: 600;
    font-size: 1.1em;
    color: #333;
}

.calendar-grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.calendar-month {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    background: white;
}

.month-header {
    background: #007cba;
    color: white;
    text-align: center;
    padding: 15px;
    font-weight: 600;
    font-size: 1.1em;
}

.weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #f8f9fa;
}

.weekday {
    padding: 10px 5px;
    text-align: center;
    font-weight: 600;
    font-size: 0.9em;
    color: #666;
    border-right: 1px solid #eee;
}

.weekday:last-child {
    border-right: none;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-right: 1px solid #eee;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    position: relative;
    background: white;
    transition: all 0.2s ease;
}

.calendar-day:nth-child(7n) {
    border-right: none;
}

.calendar-day.other-month {
    color: #ccc;
    background: #fafafa;
}

.calendar-day.past {
    color: #ccc;
    cursor: not-allowed;
    background: #f5f5f5;
}

.calendar-day.today {
    background: #e3f2fd;
    font-weight: bold;
}

.calendar-day.available {
    background: #e8f5e8;
    color: #2e7d32;
}

.calendar-day.available:hover {
    background: #c8e6c9;
}

.calendar-day.unavailable {
    background: #ffebee;
    color: #c62828;
    cursor: not-allowed;
}

.calendar-day.selected {
    background: #007cba;
    color: white;
}

.calendar-day.check-in {
    background: #4caf50;
    color: white;
}

.calendar-day.check-out {
    background: #f44336;
    color: white;
}

.calendar-day.in-range {
    background: #bbdefb;
    color: #1565c0;
}

.day-number {
    position: relative;
    z-index: 1;
}

.calendar-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-top: 15px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9em;
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: block;
}

.legend-dot.available {
    background: #e8f5e8;
    border: 1px solid #2e7d32;
}

.legend-dot.unavailable {
    background: #ffebee;
    border: 1px solid #c62828;
}

.legend-dot.selected {
    background: #007cba;
}

.legend-dot.check-in {
    background: #4caf50;
}

.legend-dot.check-out {
    background: #f44336;
}

.calendar-loader {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.9);
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.loader-spinner .spinner {
    width: 30px;
    height: 30px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loader-text {
    color: #666;
    font-size: 14px;
}

.selected-dates-display {
    margin-top: 20px;
    padding: 15px;
    background: #e3f2fd;
    border-radius: 8px;
    border-left: 4px solid #007cba;
}

.selected-dates-content h4 {
    margin: 0 0 10px 0;
    color: #1565c0;
}

.dates-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
}

.check-in-date, .check-out-date, .nights-count {
    font-size: 0.9em;
}

.date-value, .nights-value {
    color: #1565c0;
    font-weight: 600;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .calendar-grid-container {
        grid-template-columns: 1fr;
    }

    .date-inputs {
        flex-direction: column;
        gap: 10px;
    }

    .calendar-navigation {
        flex-direction: column;
        gap: 10px;
    }

    .nav-prev, .nav-next {
        order: 2;
    }

    .current-months {
        order: 1;
    }

    .calendar-legend {
        gap: 10px;
    }

    .legend-item {
        font-size: 0.8em;
    }
}

@media (max-width: 480px) {
    .calendar-day {
        font-size: 0.9em;
    }

    .month-header {
        font-size: 1em;
        padding: 12px;
    }

    .weekday {
        padding: 8px 3px;
        font-size: 0.8em;
    }
}
</style>