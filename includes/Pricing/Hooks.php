<?php
/**
 * Pricing Engine Hooks and Extensibility
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Pricing;

if (!defined('ABSPATH')) {
    exit;
}

class Hooks
{
    public static function init()
    {
        // Example hooks for extensibility
        add_filter('mcs_rate_overrides', [__CLASS__, 'example_rate_override'], 10, 2);
        add_filter('mcs_quote_mutate', [__CLASS__, 'example_quote_mutation'], 10, 2);
        add_filter('mcs_calculate_discounts', [__CLASS__, 'example_custom_discount'], 10, 3);
    }

    /**
     * Example rate override hook
     * Allows plugins to modify daily rates at calculation time
     *
     * @param array $line_items Current line items
     * @param RateContext $context Booking context
     * @return array Modified line items
     */
    public static function example_rate_override($line_items, $context)
    {
        // Example: Add early bird discount for bookings made 30+ days in advance
        $booking_date = new \DateTime();
        $days_in_advance = $booking_date->diff($context->checkin)->days;

        if ($days_in_advance >= 30) {
            // Find accommodation line item
            foreach ($line_items as &$item) {
                if ($item['code'] === 'base') {
                    $early_bird_discount = $item['subtotal'] * 0.05; // 5% discount

                    $line_items[] = [
                        'code' => 'early_bird',
                        'label' => __('Early Bird Discount (5%)', 'minpaku-suite'),
                        'subtotal' => -$early_bird_discount
                    ];
                    break;
                }
            }
        }

        return $line_items;
    }

    /**
     * Example quote mutation hook
     * Allows plugins to modify the final quote response
     *
     * @param array $quote Complete quote data
     * @param RateContext $context Booking context
     * @return array Modified quote
     */
    public static function example_quote_mutation($quote, $context)
    {
        // Example: Add metadata to quote
        $quote['metadata'] = [
            'generated_at' => current_time('mysql'),
            'generator' => 'MinpakuSuite Pricing Engine v1.0',
            'booking_window_days' => (new \DateTime())->diff($context->checkin)->days
        ];

        // Example: Add promotional messages
        if ($context->nights >= 7) {
            $quote['promotional_message'] = __('Great choice! You\'re getting our weekly discount.', 'minpaku-suite');
        }

        return $quote;
    }

    /**
     * Example custom discount hook
     * Allows plugins to add custom discount logic
     *
     * @param array $discounts Current discounts
     * @param int $nights Number of nights
     * @param float $accommodation_subtotal Accommodation subtotal
     * @return array Modified discounts
     */
    public static function example_custom_discount($discounts, $nights, $accommodation_subtotal)
    {
        // Example: Add last-minute discount for bookings within 3 days
        $today = new \DateTime();
        $checkin = new \DateTime(); // In real implementation, you'd get this from context

        // This is just an example - in real usage you'd need the context
        // $days_until_checkin = $today->diff($checkin)->days;
        //
        // if ($days_until_checkin <= 3) {
        //     $discount_amount = $accommodation_subtotal * 0.10; // 10% discount
        //
        //     $discounts[] = [
        //         'code' => 'last_minute',
        //         'label' => __('Last Minute Discount (10%)', 'minpaku-suite'),
        //         'type' => 'percent',
        //         'rate' => 10.0,
        //         'subtotal' => -$discount_amount
        //     ];
        // }

        return $discounts;
    }
}