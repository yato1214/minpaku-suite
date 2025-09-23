<?php
/**
 * Discounts for Property Pricing
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Pricing;

if (!defined('ABSPATH')) {
    exit;
}

class Discounts
{
    public float $weekly_discount_percent;
    public int $weekly_discount_threshold;
    public float $monthly_discount_percent;
    public int $monthly_discount_threshold;
    public array $coupon_codes; // Future extensibility

    public function __construct(int $property_id)
    {
        $this->loadFromProperty($property_id);
    }

    private function loadFromProperty(int $property_id): void
    {
        // Load weekly discount
        $this->weekly_discount_percent = (float) get_post_meta($property_id, 'weekly_discount_percent', true) ?: 0.0;
        $this->weekly_discount_threshold = (int) get_post_meta($property_id, 'weekly_discount_threshold', true) ?: 7;

        // Load monthly discount
        $this->monthly_discount_percent = (float) get_post_meta($property_id, 'monthly_discount_percent', true) ?: 0.0;
        $this->monthly_discount_threshold = (int) get_post_meta($property_id, 'monthly_discount_threshold', true) ?: 28;

        // Load coupon codes (for future extension)
        $this->coupon_codes = get_post_meta($property_id, 'coupon_codes', true) ?: [];
    }

    public function calculateDiscounts(int $nights, float $accommodation_subtotal): array
    {
        $discounts = [];

        // Monthly discount (higher priority)
        if ($this->monthly_discount_percent > 0 && $nights >= $this->monthly_discount_threshold) {
            $discount_amount = $accommodation_subtotal * ($this->monthly_discount_percent / 100);
            $discounts[] = [
                'code' => 'monthly',
                'label' => sprintf(
                    __('Monthly Discount (%s%%)', 'minpaku-suite'),
                    number_format($this->monthly_discount_percent, 1)
                ),
                'type' => 'percent',
                'rate' => $this->monthly_discount_percent,
                'subtotal' => -$this->roundAmount($discount_amount),
                'threshold' => $this->monthly_discount_threshold
            ];
        }
        // Weekly discount (if monthly discount not applied)
        elseif ($this->weekly_discount_percent > 0 && $nights >= $this->weekly_discount_threshold) {
            $discount_amount = $accommodation_subtotal * ($this->weekly_discount_percent / 100);
            $discounts[] = [
                'code' => 'weekly',
                'label' => sprintf(
                    __('Weekly Discount (%s%%)', 'minpaku-suite'),
                    number_format($this->weekly_discount_percent, 1)
                ),
                'type' => 'percent',
                'rate' => $this->weekly_discount_percent,
                'subtotal' => -$this->roundAmount($discount_amount),
                'threshold' => $this->weekly_discount_threshold
            ];
        }

        // Apply hook for custom discounts
        $discounts = apply_filters('mcs_calculate_discounts', $discounts, $nights, $accommodation_subtotal);

        return $discounts;
    }

    public function hasDiscounts(): bool
    {
        return $this->weekly_discount_percent > 0 || $this->monthly_discount_percent > 0;
    }

    private function roundAmount(float $amount): float
    {
        // Use banker's rounding (round half to even) for currency calculations
        return round($amount, 0, PHP_ROUND_HALF_EVEN);
    }

    public function toArray(): array
    {
        return [
            'weekly_discount_percent' => $this->weekly_discount_percent,
            'weekly_discount_threshold' => $this->weekly_discount_threshold,
            'monthly_discount_percent' => $this->monthly_discount_percent,
            'monthly_discount_threshold' => $this->monthly_discount_threshold,
            'coupon_codes' => $this->coupon_codes
        ];
    }
}