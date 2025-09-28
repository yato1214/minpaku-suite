<?php
/**
 * Connector Quote Panel Template
 * Standard quote panel structure for modern calendar interactions
 * Mirrors portal template structure for consistent UI/UX
 *
 * @package WP_Minpaku_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

// Default values for template variables
$calendar_id = $calendar_id ?? uniqid('wpmc-cal-');
$texts = $texts ?? [];

// Default text values with i18n support
$default_texts = [
    'quote_title' => __('見積り', 'wp-minpaku-connector'),
    'clear_selection' => __('選択をクリア', 'wp-minpaku-connector'),
    'select_dates_placeholder' => __('日程を選択すると見積が表示されます', 'wp-minpaku-connector'),
    'loading' => __('読み込み中...', 'wp-minpaku-connector'),
    'breakdown_title' => __('内訳', 'wp-minpaku-connector'),
    'accommodation_fee' => __('宿泊料金', 'wp-minpaku-connector'),
    'cleaning_fee' => __('清掃料金', 'wp-minpaku-connector'),
    'total_amount' => __('合計金額', 'wp-minpaku-connector'),
    'final_notice' => __('※ 最終合計は予約時に確定します', 'wp-minpaku-connector'),
    'error_title' => __('エラー', 'wp-minpaku-connector')
];

$texts = array_merge($default_texts, $texts);
?>

<!-- Modern Quote Panel with Unified Structure (Connector) -->
<div class="mcs-quote-panel wpmc-quote-panel"
     data-calendar-id="<?php echo esc_attr($calendar_id); ?>"
     role="region"
     aria-label="<?php echo esc_attr($texts['quote_title']); ?>"
     aria-live="polite"
     style="display: none;">

    <!-- Quote Panel Header -->
    <div class="mcs-quote-header wpmc-quote-header">
        <h3 class="mcs-quote-title wpmc-quote-title"><?php echo esc_html($texts['quote_title']); ?></h3>
        <button type="button"
                class="mcs-clear-selection-btn wpmc-clear-selection-btn"
                aria-label="<?php echo esc_attr($texts['clear_selection']); ?>"
                title="<?php echo esc_attr($texts['clear_selection']); ?>">
            <span aria-hidden="true">×</span>
        </button>
    </div>

    <!-- Quote Panel Content -->
    <div class="mcs-quote-content wpmc-quote-content">

        <!-- Placeholder State -->
        <div class="mcs-quote-placeholder wpmc-quote-placeholder">
            <p><?php echo esc_html($texts['select_dates_placeholder']); ?></p>
        </div>

        <!-- Selection Summary & Guest Form (modern UI) -->
        <div class="mcs-quote-selection wpmc-quote-selection" style="display: none;">
            <!-- Date Range Summary -->
            <div class="mcs-quote-summary wpmc-quote-summary">
                <div class="mcs-quote-dates wpmc-quote-dates" aria-label="選択された日程">
                    <span class="mcs-checkin-date wpmc-checkin-date"></span>
                    <span class="mcs-date-separator wpmc-date-separator">〜</span>
                    <span class="mcs-checkout-date wpmc-checkout-date"></span>
                    <span class="mcs-nights-count wpmc-nights-count"></span>
                </div>
            </div>

            <!-- Guest Form -->
            <div class="mcs-guest-form wpmc-guest-form">
                <div class="mcs-guest-input-group wpmc-guest-input-group">
                    <label for="quote-adults" class="mcs-guest-label wpmc-guest-label"><?php echo esc_html__('大人', 'wp-minpaku-connector'); ?></label>
                    <select id="quote-adults" class="mcs-guest-select wpmc-guest-select" name="adults">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php selected($i, 2); ?>><?php echo $i . __('名', 'wp-minpaku-connector'); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="mcs-guest-input-group wpmc-guest-input-group">
                    <label for="quote-children" class="mcs-guest-label wpmc-guest-label"><?php echo esc_html__('子供', 'wp-minpaku-connector'); ?></label>
                    <select id="quote-children" class="mcs-guest-select wpmc-guest-select" name="children">
                        <?php for ($i = 0; $i <= 8; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i . __('名', 'wp-minpaku-connector'); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mcs-quote-actions wpmc-quote-actions">
                <button type="button" class="mcs-get-quote wpmc-get-quote mcs-btn-primary wpmc-btn-primary">
                    <?php echo esc_html__('見積を取得', 'wp-minpaku-connector'); ?>
                </button>
                <button type="button" class="mcs-clear-selection-btn wpmc-clear-selection-btn mcs-btn-secondary wpmc-btn-secondary">
                    <?php echo esc_html__('選択をクリア', 'wp-minpaku-connector'); ?>
                </button>
            </div>
        </div>

        <!-- Loading State (hidden by default) -->
        <div class="mcs-quote-loading wpmc-quote-loading" style="display: none;" aria-hidden="true">
            <span class="mcs-loading-spinner wpmc-loading-spinner" aria-hidden="true"></span>
            <span class="mcs-loading-text wpmc-loading-text"><?php echo esc_html($texts['loading']); ?></span>
        </div>

        <!-- Quote Result (populated by JS) -->
        <div class="mcs-quote-result wpmc-quote-result" style="display: none;" role="region" aria-label="見積結果">

            <!-- Date Range Summary -->
            <div class="mcs-quote-summary wpmc-quote-summary">
                <div class="mcs-quote-dates wpmc-quote-dates" aria-label="選択された日程">
                    <span class="mcs-checkin-date wpmc-checkin-date"></span>
                    <span class="mcs-date-separator wpmc-date-separator">〜</span>
                    <span class="mcs-checkout-date wpmc-checkout-date"></span>
                    <span class="mcs-nights-count wpmc-nights-count"></span>
                </div>
            </div>

            <!-- Price Breakdown Table -->
            <div class="mcs-quote-breakdown wpmc-quote-breakdown">
                <table class="mcs-quote-table wpmc-quote-table" role="table" aria-label="<?php echo esc_attr($texts['breakdown_title']); ?>">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo esc_html($texts['breakdown_title']); ?></th>
                            <th scope="col">金額</th>
                        </tr>
                    </thead>
                    <tbody class="mcs-quote-breakdown-content wpmc-quote-breakdown-content">
                        <!-- Accommodation Fee Row -->
                        <tr class="mcs-breakdown-accommodation wpmc-breakdown-accommodation" style="display: none;">
                            <td class="mcs-breakdown-label wpmc-breakdown-label">
                                <span class="mcs-accommodation-label wpmc-accommodation-label"></span>
                            </td>
                            <td class="mcs-breakdown-amount wpmc-breakdown-amount mcs-accommodation-amount wpmc-accommodation-amount"></td>
                        </tr>

                        <!-- Cleaning Fee Row -->
                        <tr class="mcs-breakdown-cleaning wpmc-breakdown-cleaning" style="display: none;">
                            <td class="mcs-breakdown-label wpmc-breakdown-label"><?php echo esc_html($texts['cleaning_fee']); ?></td>
                            <td class="mcs-breakdown-amount wpmc-breakdown-amount mcs-cleaning-amount wpmc-cleaning-amount"></td>
                        </tr>

                        <!-- Additional Fees (will be populated dynamically) -->
                        <tr class="mcs-breakdown-additional wpmc-breakdown-additional" style="display: none;">
                            <td class="mcs-breakdown-label wpmc-breakdown-label mcs-additional-label wpmc-additional-label"></td>
                            <td class="mcs-breakdown-amount wpmc-breakdown-amount mcs-additional-amount wpmc-additional-amount"></td>
                        </tr>

                        <!-- Total Row -->
                        <tr class="mcs-breakdown-total wpmc-breakdown-total mcs-total-row wpmc-total-row">
                            <td class="mcs-breakdown-label wpmc-breakdown-label">
                                <strong><?php echo esc_html($texts['total_amount']); ?></strong>
                            </td>
                            <td class="mcs-breakdown-amount wpmc-breakdown-amount mcs-total-amount wpmc-total-amount">
                                <strong>¥0</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Final Notice -->
            <div class="mcs-quote-notice wpmc-quote-notice">
                <p><?php echo esc_html($texts['final_notice']); ?></p>
            </div>

            <!-- Booking Actions (if enabled) -->
            <div class="mcs-quote-actions wpmc-quote-actions" style="display: none;">
                <button type="button" class="mcs-book-now-btn wpmc-book-now-btn mcs-btn-primary wpmc-btn-primary" disabled>
                    <?php _e('予約機能は準備中です', 'wp-minpaku-connector'); ?>
                </button>
            </div>
        </div>

        <!-- Error State (hidden by default) -->
        <div class="mcs-quote-error wpmc-quote-error" style="display: none;" role="alert">
            <h4 class="mcs-error-title wpmc-error-title"><?php echo esc_html($texts['error_title']); ?></h4>
            <p class="mcs-error-message wpmc-error-message"></p>
        </div>

    </div>
</div>