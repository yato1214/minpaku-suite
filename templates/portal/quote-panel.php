<?php
/**
 * Portal Quote Panel Template
 * Standard quote panel structure for modern calendar interactions
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Default values for template variables
$calendar_id = $calendar_id ?? uniqid('mcs-cal-');
$texts = $texts ?? [];

// Default text values with i18n support
$default_texts = [
    'quote_title' => __('見積り', 'minpaku-suite'),
    'clear_selection' => __('選択をクリア', 'minpaku-suite'),
    'select_dates_placeholder' => __('日程を選択すると見積が表示されます', 'minpaku-suite'),
    'loading' => __('読み込み中...', 'minpaku-suite'),
    'breakdown_title' => __('内訳', 'minpaku-suite'),
    'accommodation_fee' => __('宿泊料金', 'minpaku-suite'),
    'cleaning_fee' => __('清掃料金', 'minpaku-suite'),
    'total_amount' => __('合計金額', 'minpaku-suite'),
    'final_notice' => __('※ 最終合計は予約時に確定します', 'minpaku-suite'),
    'error_title' => __('エラー', 'minpaku-suite')
];

$texts = array_merge($default_texts, $texts);
?>

<!-- Modern Quote Panel with Unified Structure -->
<div class="mcs-quote-panel"
     data-calendar-id="<?php echo esc_attr($calendar_id); ?>"
     role="region"
     aria-label="<?php echo esc_attr($texts['quote_title']); ?>"
     aria-live="polite"
     style="display: none;">

    <!-- Quote Panel Header -->
    <div class="mcs-quote-header">
        <h3 class="mcs-quote-title"><?php echo esc_html($texts['quote_title']); ?></h3>
        <button type="button"
                class="mcs-clear-selection-btn"
                aria-label="<?php echo esc_attr($texts['clear_selection']); ?>"
                title="<?php echo esc_attr($texts['clear_selection']); ?>">
            <span aria-hidden="true">×</span>
        </button>
    </div>

    <!-- Quote Panel Content -->
    <div class="mcs-quote-content">

        <!-- Placeholder State -->
        <div class="mcs-quote-placeholder">
            <p><?php echo esc_html($texts['select_dates_placeholder']); ?></p>
        </div>

        <!-- Loading State (hidden by default) -->
        <div class="mcs-quote-loading" style="display: none;" aria-hidden="true">
            <span class="mcs-loading-spinner" aria-hidden="true"></span>
            <span class="mcs-loading-text"><?php echo esc_html($texts['loading']); ?></span>
        </div>

        <!-- Quote Result (populated by JS) -->
        <div class="mcs-quote-result" style="display: none;" role="region" aria-label="見積結果">

            <!-- Date Range Summary -->
            <div class="mcs-quote-summary">
                <div class="mcs-quote-dates" aria-label="選択された日程">
                    <span class="mcs-checkin-date"></span>
                    <span class="mcs-date-separator">〜</span>
                    <span class="mcs-checkout-date"></span>
                    <span class="mcs-nights-count"></span>
                </div>
            </div>

            <!-- Price Breakdown Table -->
            <div class="mcs-quote-breakdown">
                <table class="mcs-quote-table" role="table" aria-label="<?php echo esc_attr($texts['breakdown_title']); ?>">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo esc_html($texts['breakdown_title']); ?></th>
                            <th scope="col">金額</th>
                        </tr>
                    </thead>
                    <tbody class="mcs-quote-breakdown-content">
                        <!-- Accommodation Fee Row -->
                        <tr class="mcs-breakdown-accommodation" style="display: none;">
                            <td class="mcs-breakdown-label">
                                <span class="mcs-accommodation-label"></span>
                            </td>
                            <td class="mcs-breakdown-amount mcs-accommodation-amount"></td>
                        </tr>

                        <!-- Cleaning Fee Row -->
                        <tr class="mcs-breakdown-cleaning" style="display: none;">
                            <td class="mcs-breakdown-label"><?php echo esc_html($texts['cleaning_fee']); ?></td>
                            <td class="mcs-breakdown-amount mcs-cleaning-amount"></td>
                        </tr>

                        <!-- Additional Fees (will be populated dynamically) -->
                        <tr class="mcs-breakdown-additional" style="display: none;">
                            <td class="mcs-breakdown-label mcs-additional-label"></td>
                            <td class="mcs-breakdown-amount mcs-additional-amount"></td>
                        </tr>

                        <!-- Total Row -->
                        <tr class="mcs-breakdown-total mcs-total-row">
                            <td class="mcs-breakdown-label">
                                <strong><?php echo esc_html($texts['total_amount']); ?></strong>
                            </td>
                            <td class="mcs-breakdown-amount mcs-total-amount">
                                <strong>¥0</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Final Notice -->
            <div class="mcs-quote-notice">
                <p><?php echo esc_html($texts['final_notice']); ?></p>
            </div>

            <!-- Booking Actions (if enabled) -->
            <div class="mcs-quote-actions" style="display: none;">
                <button type="button" class="mcs-book-now-btn mcs-btn-primary" disabled>
                    <?php _e('予約機能は準備中です', 'minpaku-suite'); ?>
                </button>
            </div>
        </div>

        <!-- Error State (hidden by default) -->
        <div class="mcs-quote-error" style="display: none;" role="alert">
            <h4 class="mcs-error-title"><?php echo esc_html($texts['error_title']); ?></h4>
            <p class="mcs-error-message"></p>
        </div>

    </div>
</div>