<?php
/**
 * Rate Rules for Property Pricing
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Pricing;

if (!defined('ABSPATH')) {
    exit;
}

class RateRules
{
    public float $base_rate;
    public array $weekday_overrides; // [0-6 => rate] where 0=Sunday
    public array $seasonal_overrides; // [start_date, end_date, rate, min_nights, max_nights, priority]
    public int $min_nights;
    public int $max_nights;
    public array $checkin_days; // [0,1,2,3,4,5,6] allowed days
    public array $checkout_days; // [0,1,2,3,4,5,6] allowed days
    public int $base_capacity;

    public function __construct(int $property_id)
    {
        $this->loadFromProperty($property_id);
    }

    private function loadFromProperty(int $property_id): void
    {
        // Load base rate
        $this->base_rate = (float) get_post_meta($property_id, 'base_rate', true) ?: 10000.0;

        // Load weekday overrides
        $weekday_rates = get_post_meta($property_id, 'weekday_rates', true) ?: [];
        $this->weekday_overrides = [];
        for ($i = 0; $i <= 6; $i++) {
            if (isset($weekday_rates[$i]) && $weekday_rates[$i] > 0) {
                $this->weekday_overrides[$i] = (float) $weekday_rates[$i];
            }
        }

        // Load seasonal overrides
        $seasonal_rates = get_post_meta($property_id, 'seasonal_rates', true) ?: [];
        $this->seasonal_overrides = [];

        if (is_array($seasonal_rates)) {
            foreach ($seasonal_rates as $season) {
                if (isset($season['start_date'], $season['end_date'], $season['rate'])) {
                    $this->seasonal_overrides[] = [
                        'start_date' => $season['start_date'],
                        'end_date' => $season['end_date'],
                        'rate' => (float) $season['rate'],
                        'min_nights' => (int) ($season['min_nights'] ?? 1),
                        'max_nights' => (int) ($season['max_nights'] ?? 0),
                        'priority' => (int) ($season['priority'] ?? 0)
                    ];
                }
            }
        }

        // Sort seasonal overrides by priority (higher first)
        usort($this->seasonal_overrides, function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        // Load constraints
        $this->min_nights = (int) get_post_meta($property_id, 'min_nights', true) ?: 1;
        $this->max_nights = (int) get_post_meta($property_id, 'max_nights', true) ?: 0;

        // Load allowed checkin/checkout days (default: all days allowed)
        $this->checkin_days = get_post_meta($property_id, 'checkin_days', true) ?: [0,1,2,3,4,5,6];
        $this->checkout_days = get_post_meta($property_id, 'checkout_days', true) ?: [0,1,2,3,4,5,6];

        // Load base capacity
        $this->base_capacity = (int) get_post_meta($property_id, 'capacity', true) ?: 2;
    }

    public function getDailyRate(\DateTime $date): float
    {
        $dateString = $date->format('Y-m-d');
        $dayOfWeek = (int) $date->format('w');

        // Check seasonal overrides (highest priority first)
        foreach ($this->seasonal_overrides as $season) {
            if ($dateString >= $season['start_date'] && $dateString <= $season['end_date']) {
                return $season['rate'];
            }
        }

        // Check weekday overrides
        if (isset($this->weekday_overrides[$dayOfWeek])) {
            return $this->weekday_overrides[$dayOfWeek];
        }

        // Return base rate
        return $this->base_rate;
    }

    public function getSeasonalConstraints(\DateTime $date): array
    {
        $dateString = $date->format('Y-m-d');

        foreach ($this->seasonal_overrides as $season) {
            if ($dateString >= $season['start_date'] && $dateString <= $season['end_date']) {
                return [
                    'min_nights' => $season['min_nights'],
                    'max_nights' => $season['max_nights']
                ];
            }
        }

        return [
            'min_nights' => $this->min_nights,
            'max_nights' => $this->max_nights
        ];
    }

    public function validateConstraints(RateContext $context): array
    {
        $errors = [];

        // Check minimum nights
        if ($context->nights < $this->min_nights) {
            $errors[] = sprintf(
                __('Minimum stay is %d nights', 'minpaku-suite'),
                $this->min_nights
            );
        }

        // Check maximum nights
        if ($this->max_nights > 0 && $context->nights > $this->max_nights) {
            $errors[] = sprintf(
                __('Maximum stay is %d nights', 'minpaku-suite'),
                $this->max_nights
            );
        }

        // Check checkin day
        if (!in_array($context->getCheckinDay(), $this->checkin_days)) {
            $allowed_days = array_map(function($day) {
                return $this->getDayName($day);
            }, $this->checkin_days);

            $errors[] = sprintf(
                __('Check-in is only allowed on: %s', 'minpaku-suite'),
                implode(', ', $allowed_days)
            );
        }

        // Check checkout day
        if (!in_array($context->getCheckoutDay(), $this->checkout_days)) {
            $allowed_days = array_map(function($day) {
                return $this->getDayName($day);
            }, $this->checkout_days);

            $errors[] = sprintf(
                __('Check-out is only allowed on: %s', 'minpaku-suite'),
                implode(', ', $allowed_days)
            );
        }

        // Check seasonal constraints
        $checkin_constraints = $this->getSeasonalConstraints($context->checkin);
        if ($context->nights < $checkin_constraints['min_nights']) {
            $errors[] = sprintf(
                __('Minimum stay for this period is %d nights', 'minpaku-suite'),
                $checkin_constraints['min_nights']
            );
        }

        if ($checkin_constraints['max_nights'] > 0 && $context->nights > $checkin_constraints['max_nights']) {
            $errors[] = sprintf(
                __('Maximum stay for this period is %d nights', 'minpaku-suite'),
                $checkin_constraints['max_nights']
            );
        }

        return $errors;
    }

    private function getDayName(int $day): string
    {
        $days = [
            __('Sunday', 'minpaku-suite'),
            __('Monday', 'minpaku-suite'),
            __('Tuesday', 'minpaku-suite'),
            __('Wednesday', 'minpaku-suite'),
            __('Thursday', 'minpaku-suite'),
            __('Friday', 'minpaku-suite'),
            __('Saturday', 'minpaku-suite')
        ];

        return $days[$day] ?? '';
    }

    public function toArray(): array
    {
        return [
            'base_rate' => $this->base_rate,
            'weekday_overrides' => $this->weekday_overrides,
            'seasonal_overrides' => $this->seasonal_overrides,
            'min_nights' => $this->min_nights,
            'max_nights' => $this->max_nights,
            'checkin_days' => $this->checkin_days,
            'checkout_days' => $this->checkout_days,
            'base_capacity' => $this->base_capacity
        ];
    }
}