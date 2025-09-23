<?php
/**
 * Fees and Charges for Property Pricing
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Pricing;

if (!defined('ABSPATH')) {
    exit;
}

class Fees
{
    public float $cleaning_fee;
    public float $service_fee_percent;
    public float $service_fee_fixed;
    public string $service_fee_type; // 'percent' or 'fixed'
    public float $extra_guest_fee;
    public int $extra_guest_threshold;
    public array $taxes; // [name, rate, inclusive, taxable_items]

    public function __construct(int $property_id)
    {
        $this->loadFromProperty($property_id);
    }

    private function loadFromProperty(int $property_id): void
    {
        // Load cleaning fee
        $this->cleaning_fee = (float) get_post_meta($property_id, 'cleaning_fee', true) ?: 0.0;

        // Load service fee
        $this->service_fee_percent = (float) get_post_meta($property_id, 'service_fee_percent', true) ?: 0.0;
        $this->service_fee_fixed = (float) get_post_meta($property_id, 'service_fee_fixed', true) ?: 0.0;
        $this->service_fee_type = get_post_meta($property_id, 'service_fee_type', true) ?: 'percent';

        // Load extra guest fee
        $this->extra_guest_fee = (float) get_post_meta($property_id, 'extra_guest_fee', true) ?: 0.0;
        $this->extra_guest_threshold = (int) get_post_meta($property_id, 'extra_guest_threshold', true) ?: 0;

        // Load taxes
        $taxes = get_post_meta($property_id, 'taxes', true) ?: [];
        $this->taxes = [];

        if (is_array($taxes)) {
            foreach ($taxes as $tax) {
                if (isset($tax['name'], $tax['rate'])) {
                    $this->taxes[] = [
                        'name' => $tax['name'],
                        'rate' => (float) $tax['rate'],
                        'inclusive' => (bool) ($tax['inclusive'] ?? false),
                        'taxable_items' => $tax['taxable_items'] ?? ['accommodation', 'cleaning', 'service', 'extra_guest']
                    ];
                }
            }
        }

        // Add default consumption tax if no taxes configured
        if (empty($this->taxes)) {
            $this->taxes[] = [
                'name' => __('Consumption Tax (10%)', 'minpaku-suite'),
                'rate' => 10.0,
                'inclusive' => false,
                'taxable_items' => ['accommodation', 'cleaning', 'service', 'extra_guest']
            ];
        }
    }

    public function calculateServiceFee(float $subtotal): float
    {
        if ($this->service_fee_type === 'percent') {
            return $subtotal * ($this->service_fee_percent / 100);
        } else {
            return $this->service_fee_fixed;
        }
    }

    public function calculateExtraGuestFee(int $total_guests, int $nights): float
    {
        if ($this->extra_guest_threshold <= 0 || $total_guests <= $this->extra_guest_threshold) {
            return 0.0;
        }

        $extra_guests = $total_guests - $this->extra_guest_threshold;
        return $extra_guests * $this->extra_guest_fee * $nights;
    }

    public function calculateTaxes(array $line_items, float $subtotal): array
    {
        $calculated_taxes = [];

        foreach ($this->taxes as $tax) {
            $taxable_amount = 0.0;

            // Calculate taxable amount based on taxable items
            foreach ($line_items as $item) {
                if (in_array($item['code'], $tax['taxable_items'])) {
                    $taxable_amount += $item['subtotal'];
                }
            }

            if ($taxable_amount > 0) {
                if ($tax['inclusive']) {
                    // Tax is already included in the price
                    $tax_amount = $taxable_amount - ($taxable_amount / (1 + $tax['rate'] / 100));
                } else {
                    // Tax is additional
                    $tax_amount = $taxable_amount * ($tax['rate'] / 100);
                }

                $calculated_taxes[] = [
                    'label' => $tax['name'],
                    'rate' => $tax['rate'],
                    'taxable_amount' => $taxable_amount,
                    'amount' => $this->roundAmount($tax_amount),
                    'inclusive' => $tax['inclusive']
                ];
            }
        }

        return $calculated_taxes;
    }

    public function getServiceFeeLabel(): string
    {
        if ($this->service_fee_type === 'percent') {
            return sprintf(
                __('Service Fee (%s%%)', 'minpaku-suite'),
                number_format($this->service_fee_percent, 1)
            );
        } else {
            return __('Service Fee', 'minpaku-suite');
        }
    }

    private function roundAmount(float $amount): float
    {
        // Use banker's rounding (round half to even) for currency calculations
        return round($amount, 0, PHP_ROUND_HALF_EVEN);
    }

    public function toArray(): array
    {
        return [
            'cleaning_fee' => $this->cleaning_fee,
            'service_fee_percent' => $this->service_fee_percent,
            'service_fee_fixed' => $this->service_fee_fixed,
            'service_fee_type' => $this->service_fee_type,
            'extra_guest_fee' => $this->extra_guest_fee,
            'extra_guest_threshold' => $this->extra_guest_threshold,
            'taxes' => $this->taxes
        ];
    }
}