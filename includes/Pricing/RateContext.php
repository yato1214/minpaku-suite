<?php
/**
 * Rate Context for Pricing Calculations
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Pricing;

if (!defined('ABSPATH')) {
    exit;
}

class RateContext
{
    public int $property_id;
    public \DateTime $checkin;
    public \DateTime $checkout;
    public int $adults;
    public int $children;
    public int $infants;
    public string $currency;
    public int $nights;

    public function __construct(
        int $property_id,
        string $checkin,
        string $checkout,
        int $adults = 2,
        int $children = 0,
        int $infants = 0,
        string $currency = 'JPY'
    ) {
        $this->property_id = $property_id;
        $this->checkin = new \DateTime($checkin);
        $this->checkout = new \DateTime($checkout);
        $this->adults = max(1, $adults);
        $this->children = max(0, $children);
        $this->infants = max(0, $infants);
        $this->currency = $currency;
        $this->nights = $this->calculateNights();
    }

    private function calculateNights(): int
    {
        $diff = $this->checkout->diff($this->checkin);
        return max(0, $diff->days);
    }

    public function getTotalGuests(): int
    {
        return $this->adults + $this->children;
    }

    public function getAllGuests(): int
    {
        return $this->adults + $this->children + $this->infants;
    }

    public function getDateRange(): array
    {
        $dates = [];
        $current = clone $this->checkin;

        while ($current < $this->checkout) {
            $dates[] = clone $current;
            $current->modify('+1 day');
        }

        return $dates;
    }

    public function getCheckinDay(): int
    {
        return (int) $this->checkin->format('w'); // 0=Sunday, 6=Saturday
    }

    public function getCheckoutDay(): int
    {
        return (int) $this->checkout->format('w');
    }

    public function getCacheKey(): string
    {
        return sprintf(
            'quote_%d_%s_%s_%d_%d_%d_%s',
            $this->property_id,
            $this->checkin->format('Y-m-d'),
            $this->checkout->format('Y-m-d'),
            $this->adults,
            $this->children,
            $this->infants,
            $this->currency
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->property_id <= 0) {
            $errors[] = __('Invalid property ID', 'minpaku-suite');
        }

        if ($this->nights <= 0) {
            $errors[] = __('Check-out date must be after check-in date', 'minpaku-suite');
        }

        if ($this->nights > 366) {
            $errors[] = __('Maximum stay period is 366 days', 'minpaku-suite');
        }

        if ($this->adults < 1) {
            $errors[] = __('At least one adult is required', 'minpaku-suite');
        }

        if ($this->adults > 50) {
            $errors[] = __('Maximum 50 adults allowed', 'minpaku-suite');
        }

        if ($this->children > 20) {
            $errors[] = __('Maximum 20 children allowed', 'minpaku-suite');
        }

        if ($this->infants > 10) {
            $errors[] = __('Maximum 10 infants allowed', 'minpaku-suite');
        }

        // Check if check-in is in the past
        $today = new \DateTime('today');
        if ($this->checkin < $today) {
            $errors[] = __('Check-in date cannot be in the past', 'minpaku-suite');
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'property_id' => $this->property_id,
            'checkin' => $this->checkin->format('Y-m-d'),
            'checkout' => $this->checkout->format('Y-m-d'),
            'nights' => $this->nights,
            'adults' => $this->adults,
            'children' => $this->children,
            'infants' => $this->infants,
            'currency' => $this->currency,
            'total_guests' => $this->getTotalGuests(),
            'all_guests' => $this->getAllGuests()
        ];
    }
}