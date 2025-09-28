<?php
/**
 * Shared Quote Panel Template
 * Used by both portal and connector for consistent UI/UX
 * Mirrors connector quote-panel.php structure
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
    'error_title' => __('エラー', 'minpaku-suite'),
    'get_quote' => __('見積を取得', 'minpaku-suite'),
    'adults' => __('大人', 'minpaku-suite'),
    'children' => __('子供', 'minpaku-suite'),
    'guests' => __('名', 'minpaku-suite')
];

$texts = array_merge($default_texts, $texts);
?>

<!-- Modern Quote Panel with Unified Structure (Portal) -->
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
                class="mcs-clear-selection-btn mpc-clear-selection-btn"
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
                    <span class="mcs-checkin-date mpc-checkin-date"></span>
                    <span class="mcs-date-separator mpc-date-separator">〜</span>
                    <span class="mcs-checkout-date mpc-checkout-date"></span>
                    <span class="mcs-nights-count mpc-nights-count"></span>
                </div>
            </div>

            <!-- Guest Form -->
            <div class="mcs-guest-form mpc-guest-form">
                <div class="mcs-guest-input-group mpc-guest-input-group">
                    <label for="quote-adults" class="mcs-guest-label mpc-guest-label"><?php echo esc_html($texts['adults']); ?></label>
                    <select id="quote-adults" class="mcs-guest-select mpc-guest-select" name="adults">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php selected($i, 2); ?>><?php echo $i . $texts['guests']; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="mcs-guest-input-group mpc-guest-input-group">
                    <label for="quote-children" class="mcs-guest-label mpc-guest-label"><?php echo esc_html($texts['children']); ?></label>
                    <select id="quote-children" class="mcs-guest-select mpc-guest-select" name="children">
                        <?php for ($i = 0; $i <= 8; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i . $texts['guests']; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mcs-quote-actions wpmc-quote-actions">
                <button type="button" class="mcs-get-quote wpmc-get-quote mcs-btn-primary wpmc-btn-primary">
                    <?php echo esc_html($texts['get_quote']); ?>
                </button>
                <button type="button" class="mcs-clear-selection-btn wpmc-clear-selection-btn mcs-btn-secondary wpmc-btn-secondary">
                    <?php echo esc_html($texts['clear_selection']); ?>
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
                        <tr class="mcs-breakdown-accommodation mpc-breakdown-accommodation" style="display: none;">
                            <td class="mcs-breakdown-label mpc-breakdown-label">
                                <span class="mcs-accommodation-label mpc-accommodation-label"></span>
                            </td>
                            <td class="mcs-breakdown-amount mpc-breakdown-amount mcs-accommodation-amount mpc-accommodation-amount"></td>
                        </tr>

                        <!-- Cleaning Fee Row -->
                        <tr class="mcs-breakdown-cleaning mpc-breakdown-cleaning" style="display: none;">
                            <td class="mcs-breakdown-label mpc-breakdown-label"><?php echo esc_html($texts['cleaning_fee']); ?></td>
                            <td class="mcs-breakdown-amount mpc-breakdown-amount mcs-cleaning-amount mpc-cleaning-amount"></td>
                        </tr>

                        <!-- Additional Fees (will be populated dynamically) -->
                        <tr class="mcs-breakdown-additional mpc-breakdown-additional" style="display: none;">
                            <td class="mcs-breakdown-label mpc-breakdown-label mcs-additional-label mpc-additional-label"></td>
                            <td class="mcs-breakdown-amount mpc-breakdown-amount mcs-additional-amount mpc-additional-amount"></td>
                        </tr>

                        <!-- Total Row -->
                        <tr class="mcs-breakdown-total mpc-breakdown-total mcs-total-row mpc-total-row">
                            <td class="mcs-breakdown-label mpc-breakdown-label">
                                <strong><?php echo esc_html($texts['total_amount']); ?></strong>
                            </td>
                            <td class="mcs-breakdown-amount mpc-breakdown-amount mcs-total-amount mpc-total-amount">
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
        </div>

        <!-- Error State (hidden by default) -->
        <div class="mcs-quote-error wpmc-quote-error" style="display: none;" role="alert">
            <h4 class="mcs-error-title mpc-error-title"><?php echo esc_html($texts['error_title']); ?></h4>
            <p class="mcs-error-message mpc-error-message"></p>
        </div>

    </div>
</div>