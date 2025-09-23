<?php
/**
 * Main Pricing Engine
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Pricing;

if (!defined('ABSPATH')) {
    exit;
}

class PricingEngine
{
    private RateContext $context;
    private RateRules $rules;
    private Fees $fees;
    private Discounts $discounts;
    private array $availability;

    public function __construct(RateContext $context)
    {
        $this->context = $context;
        $this->rules = new RateRules($context->property_id);
        $this->fees = new Fees($context->property_id);
        $this->discounts = new Discounts($context->property_id);
        $this->availability = $this->loadAvailability();
    }

    private function loadAvailability(): array
    {
        // Check availability using existing system
        try {
            if (class_exists('MinpakuSuite\Availability\AvailabilityService')) {
                $availability_map = \MinpakuSuite\Availability\AvailabilityService::getPropertyOccupancyMap(
                    $this->context->property_id,
                    $this->context->checkin,
                    $this->context->checkout
                );

                return $availability_map;
            }
        } catch (\Exception $e) {
            error_log('PricingEngine: Failed to load availability: ' . $e->getMessage());
        }

        // Fallback: assume all dates are available
        $availability = [];
        foreach ($this->context->getDateRange() as $date) {
            $availability[$date->format('Y-m-d')] = \MinpakuSuite\Availability\AvailabilityService::STATUS_VACANT ?? 'vacant';
        }

        return $availability;
    }

    public function calculateQuote(): array
    {
        // Validate context
        $context_errors = $this->context->validate();
        if (!empty($context_errors)) {
            throw new \InvalidArgumentException(implode(', ', $context_errors));
        }

        // Validate constraints
        $constraint_errors = $this->rules->validateConstraints($this->context);
        if (!empty($constraint_errors)) {
            throw new \DomainException(implode(', ', $constraint_errors));
        }

        // Check availability
        $availability_errors = $this->validateAvailability();
        if (!empty($availability_errors)) {
            throw new \DomainException(implode(', ', $availability_errors));
        }

        // Calculate line items
        $line_items = [];

        // 1. Accommodation charges (daily rates)
        $accommodation_subtotal = $this->calculateAccommodationCharges($line_items);

        // 2. Extra guest fees
        $extra_guest_fee = $this->fees->calculateExtraGuestFee(
            $this->context->getTotalGuests(),
            $this->context->nights
        );
        if ($extra_guest_fee > 0) {
            $line_items[] = [
                'code' => 'extra_guest',
                'label' => sprintf(
                    __('Extra Guest Fee (%d guests × %d nights)', 'minpaku-suite'),
                    $this->context->getTotalGuests() - $this->rules->base_capacity,
                    $this->context->nights
                ),
                'guests' => $this->context->getTotalGuests() - $this->rules->base_capacity,
                'nights' => $this->context->nights,
                'unit' => $this->fees->extra_guest_fee,
                'subtotal' => $extra_guest_fee
            ];
        }

        // 3. Cleaning fee
        if ($this->fees->cleaning_fee > 0) {
            $line_items[] = [
                'code' => 'cleaning',
                'label' => __('Cleaning Fee', 'minpaku-suite'),
                'subtotal' => $this->fees->cleaning_fee
            ];
        }

        // Calculate subtotal before discounts
        $subtotal_before_discounts = array_sum(array_column($line_items, 'subtotal'));

        // 4. Apply discounts
        $discounts = $this->discounts->calculateDiscounts($this->context->nights, $accommodation_subtotal);
        foreach ($discounts as $discount) {
            $line_items[] = $discount;
        }

        // Calculate subtotal after discounts
        $subtotal_after_discounts = array_sum(array_column($line_items, 'subtotal'));

        // 5. Service fee (applied after discounts)
        $service_fee = $this->fees->calculateServiceFee($subtotal_after_discounts);
        if ($service_fee > 0) {
            $line_items[] = [
                'code' => 'service',
                'label' => $this->fees->getServiceFeeLabel(),
                'subtotal' => $service_fee
            ];
        }

        // Final subtotal (before tax)
        $final_subtotal = array_sum(array_column($line_items, 'subtotal'));

        // 6. Calculate taxes
        $taxes = $this->fees->calculateTaxes($line_items, $final_subtotal);

        // Calculate totals
        $total_tax = array_sum(array_column($taxes, 'amount'));
        $total_excl_tax = $final_subtotal;
        $total_incl_tax = $total_excl_tax + $total_tax;

        // Apply rate override hooks
        $line_items = apply_filters('mcs_rate_overrides', $line_items, $this->context);

        // Prepare quote response
        $quote = [
            'property_id' => $this->context->property_id,
            'currency' => $this->context->currency,
            'nights' => $this->context->nights,
            'guests' => [
                'adults' => $this->context->adults,
                'children' => $this->context->children,
                'infants' => $this->context->infants,
                'total' => $this->context->getTotalGuests()
            ],
            'dates' => [
                'checkin' => $this->context->checkin->format('Y-m-d'),
                'checkout' => $this->context->checkout->format('Y-m-d')
            ],
            'line_items' => $line_items,
            'taxes' => $taxes,
            'totals' => [
                'subtotal_before_discounts' => $subtotal_before_discounts,
                'subtotal_after_discounts' => $subtotal_after_discounts,
                'total_excl_tax' => $total_excl_tax,
                'total_tax' => $total_tax,
                'total_incl_tax' => $total_incl_tax
            ],
            'constraints' => [
                'min_nights' => $this->rules->min_nights,
                'max_nights' => $this->rules->max_nights,
                'checkin_days' => $this->rules->checkin_days,
                'checkout_days' => $this->rules->checkout_days
            ]
        ];

        // Apply quote mutation hook
        $quote = apply_filters('mcs_quote_mutate', $quote, $this->context);

        // Log calculation trace if debugging is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->logCalculationTrace($quote);
        }

        return $quote;
    }

    private function calculateAccommodationCharges(array &$line_items): float
    {
        $total = 0.0;
        $dates = $this->context->getDateRange();

        // Log first 3 days for debugging
        $debug_count = 0;
        $daily_breakdown = [];

        foreach ($dates as $date) {
            $daily_rate = $this->rules->getDailyRate($date);
            $total += $daily_rate;

            if ($debug_count < 3) {
                $daily_breakdown[] = sprintf(
                    '%s: %s (%s)',
                    $date->format('Y-m-d'),
                    number_format($daily_rate),
                    $date->format('l')
                );
                $debug_count++;
            }
        }

        $line_items[] = [
            'code' => 'base',
            'label' => __('Accommodation', 'minpaku-suite'),
            'nights' => $this->context->nights,
            'unit' => $total / $this->context->nights, // Average daily rate
            'subtotal' => $total,
            'daily_breakdown' => $daily_breakdown
        ];

        return $total;
    }

    private function validateAvailability(): array
    {
        $errors = [];
        $unavailable_dates = [];

        foreach ($this->context->getDateRange() as $date) {
            $date_string = $date->format('Y-m-d');
            $status = $this->availability[$date_string] ?? 'vacant';

            if ($status === 'full' || $status === \MinpakuSuite\Availability\AvailabilityService::STATUS_FULL) {
                $unavailable_dates[] = $date_string;
            }
        }

        if (!empty($unavailable_dates)) {
            $errors[] = sprintf(
                __('The following dates are not available: %s', 'minpaku-suite'),
                implode(', ', $unavailable_dates)
            );
        }

        return $errors;
    }

    private function logCalculationTrace(array $quote): void
    {
        $trace = [
            'property_id' => $this->context->property_id,
            'checkin' => $this->context->checkin->format('Y-m-d'),
            'checkout' => $this->context->checkout->format('Y-m-d'),
            'nights' => $this->context->nights,
            'guests' => $quote['guests'],
            'line_items_summary' => array_map(function($item) {
                return [
                    'code' => $item['code'],
                    'label' => $item['label'],
                    'subtotal' => $item['subtotal']
                ];
            }, $quote['line_items']),
            'totals' => $quote['totals']
        ];

        error_log('[minpaku-suite] Pricing calculation trace: ' . json_encode($trace, JSON_PRETTY_PRINT));
    }

    public function getCacheKey(): string
    {
        return $this->context->getCacheKey();
    }

    public function getContext(): RateContext
    {
        return $this->context;
    }

    public function getRules(): RateRules
    {
        return $this->rules;
    }

    public function getFees(): Fees
    {
        return $this->fees;
    }

    public function getDiscounts(): Discounts
    {
        return $this->discounts;
    }
}