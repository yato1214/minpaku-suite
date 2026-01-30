<?php
/**
 * Rate Resolver for Season/DOW/LOS composition
 * Handles complex rate calculations with seasonal, day-of-week, and length-of-stay adjustments
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class RateResolver implements ResolverInterface {

    private $config = [];
    private $enabled = true;
    private $priority = 10;

    /**
     * Constructor
     */
    public function __construct() {
        $this->config = [
            'base_rate' => 100.0,
            'currency' => 'USD',
            'seasonal_rates' => [],
            'dow_adjustments' => [],
            'los_adjustments' => [],
            'guest_adjustments' => [],
            'min_stay_rates' => []
        ];
    }

    /**
     * Resolve rate for a given booking period
     *
     * @param array $booking_data
     * @param array $context
     * @return array
     */
    public function resolveRate(array $booking_data, array $context = []): array {
        if (!$this->enabled || !$this->canResolve($booking_data, $context)) {
            return [
                'base_rate' => 0,
                'adjustments' => [],
                'total_rate' => 0,
                'currency' => $this->config['currency'],
                'breakdown' => [],
                'error' => 'Resolver not enabled or cannot resolve rate'
            ];
        }

        $start_date = $booking_data['start_date'] ?? null;
        $end_date = $booking_data['end_date'] ?? null;
        $property_id = $booking_data['property_id'] ?? 0;
        $guests = $booking_data['guests'] ?? 1;

        if (!$start_date || !$end_date) {
            return [
                'base_rate' => 0,
                'adjustments' => [],
                'total_rate' => 0,
                'currency' => $this->config['currency'],
                'breakdown' => [],
                'error' => 'Missing start_date or end_date'
            ];
        }

        $start = is_string($start_date) ? new DateTime($start_date) : $start_date;
        $end = is_string($end_date) ? new DateTime($end_date) : $end_date;

        $nights = $start->diff($end)->days;

        if ($nights <= 0) {
            return [
                'base_rate' => 0,
                'adjustments' => [],
                'total_rate' => 0,
                'currency' => $this->config['currency'],
                'breakdown' => [],
                'error' => 'Invalid date range'
            ];
        }

        // Calculate rate per night for each night
        $nightly_rates = [];
        $total_base = 0;
        $adjustments = [];
        $breakdown = [];

        $current_date = clone $start;

        for ($i = 0; $i < $nights; $i++) {
            $nightly_rate = $this->calculateNightlyRate($current_date, $property_id, $context);

            $nightly_rates[] = [
                'date' => $current_date->format('Y-m-d'),
                'base_rate' => $nightly_rate['base'],
                'seasonal_rate' => $nightly_rate['seasonal'],
                'dow_adjustment' => $nightly_rate['dow_adjustment'],
                'final_rate' => $nightly_rate['final']
            ];

            $total_base += $nightly_rate['final'];
            $current_date->add(new DateInterval('P1D'));
        }

        // Apply length-of-stay adjustments
        $los_adjustment = $this->calculateLosAdjustment($nights);
        if ($los_adjustment !== 0) {
            $los_amount = $total_base * ($los_adjustment / 100);
            $adjustments[] = [
                'type' => 'length_of_stay',
                'description' => sprintf('%d nights - %+.1f%%', $nights, $los_adjustment),
                'percentage' => $los_adjustment,
                'amount' => $los_amount
            ];
            $total_base += $los_amount;
        }

        // Apply guest adjustments
        $guest_adjustment = $this->calculateGuestAdjustment($guests);
        if ($guest_adjustment !== 0) {
            $guest_amount = $total_base * ($guest_adjustment / 100);
            $adjustments[] = [
                'type' => 'guest_count',
                'description' => sprintf('%d guests - %+.1f%%', $guests, $guest_adjustment),
                'percentage' => $guest_adjustment,
                'amount' => $guest_amount
            ];
            $total_base += $guest_amount;
        }

        // Apply minimum stay rate if applicable
        $min_stay_adjustment = $this->calculateMinStayAdjustment($nights, $start);
        if ($min_stay_adjustment !== 0) {
            $min_stay_amount = $total_base * ($min_stay_adjustment / 100);
            $adjustments[] = [
                'type' => 'minimum_stay',
                'description' => sprintf('Min stay bonus - %+.1f%%', $min_stay_adjustment),
                'percentage' => $min_stay_adjustment,
                'amount' => $min_stay_amount
            ];
            $total_base += $min_stay_amount;
        }

        return [
            'base_rate' => array_sum(array_column($nightly_rates, 'base_rate')),
            'adjustments' => $adjustments,
            'total_rate' => round($total_base, 2),
            'currency' => $this->config['currency'],
            'breakdown' => [
                'nights' => $nights,
                'nightly_rates' => $nightly_rates,
                'average_nightly_rate' => round($total_base / $nights, 2),
                'calculation_date' => current_time('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Calculate rate for a single night
     *
     * @param DateTime $date
     * @param int $property_id
     * @param array $context
     * @return array
     */
    private function calculateNightlyRate(DateTime $date, int $property_id, array $context): array {
        $base_rate = $this->getBaseRate($property_id);

        // Apply seasonal rate
        $seasonal_rate = $this->getSeasonalRate($date, $base_rate);

        // Apply day-of-week adjustment
        $dow_adjustment = $this->getDowAdjustment($date);
        $dow_adjusted_rate = $seasonal_rate * (1 + ($dow_adjustment / 100));

        return [
            'base' => $base_rate,
            'seasonal' => $seasonal_rate,
            'dow_adjustment' => $dow_adjustment,
            'final' => round($dow_adjusted_rate, 2)
        ];
    }

    /**
     * Get base rate for property
     *
     * @param int $property_id
     * @return float
     */
    private function getBaseRate(int $property_id): float {
        if ($property_id > 0) {
            $property_rate = get_post_meta($property_id, 'mcs_base_rate', true);
            if ($property_rate && is_numeric($property_rate)) {
                return (float) $property_rate;
            }
        }

        return (float) $this->config['base_rate'];
    }

    /**
     * Get seasonal rate for date
     *
     * @param DateTime $date
     * @param float $base_rate
     * @return float
     */
    private function getSeasonalRate(DateTime $date, float $base_rate): float {
        $seasons = $this->config['seasonal_rates'] ?? [];

        foreach ($seasons as $season) {
            if ($this->isDateInSeason($date, $season)) {
                if (isset($season['rate'])) {
                    return (float) $season['rate'];
                } elseif (isset($season['multiplier'])) {
                    return $base_rate * (float) $season['multiplier'];
                } elseif (isset($season['adjustment'])) {
                    return $base_rate * (1 + ((float) $season['adjustment'] / 100));
                }
            }
        }

        return $base_rate;
    }

    /**
     * Check if date falls within season
     *
     * @param DateTime $date
     * @param array $season
     * @return bool
     */
    private function isDateInSeason(DateTime $date, array $season): bool {
        if (!isset($season['start']) || !isset($season['end'])) {
            return false;
        }

        $year = $date->format('Y');
        $month_day = $date->format('m-d');

        $start = $season['start'];
        $end = $season['end'];

        // Handle year-crossing seasons (e.g., Dec 15 - Jan 15)
        if ($start > $end) {
            return $month_day >= $start || $month_day <= $end;
        } else {
            return $month_day >= $start && $month_day <= $end;
        }
    }

    /**
     * Get day-of-week adjustment
     *
     * @param DateTime $date
     * @return float
     */
    private function getDowAdjustment(DateTime $date): float {
        $dow = $date->format('w'); // 0 = Sunday, 6 = Saturday
        $adjustments = $this->config['dow_adjustments'] ?? [];

        return isset($adjustments[$dow]) ? (float) $adjustments[$dow] : 0.0;
    }

    /**
     * Calculate length-of-stay adjustment
     *
     * @param int $nights
     * @return float
     */
    private function calculateLosAdjustment(int $nights): float {
        $adjustments = $this->config['los_adjustments'] ?? [];

        // Sort by nights (descending) to find the highest applicable tier
        uksort($adjustments, function($a, $b) {
            return $b - $a;
        });

        foreach ($adjustments as $min_nights => $adjustment) {
            if ($nights >= $min_nights) {
                return (float) $adjustment;
            }
        }

        return 0.0;
    }

    /**
     * Calculate guest count adjustment
     *
     * @param int $guests
     * @return float
     */
    private function calculateGuestAdjustment(int $guests): float {
        $adjustments = $this->config['guest_adjustments'] ?? [];

        // Find exact match first
        if (isset($adjustments[$guests])) {
            return (float) $adjustments[$guests];
        }

        // Find range match
        foreach ($adjustments as $guest_spec => $adjustment) {
            if (strpos($guest_spec, '-') !== false) {
                list($min, $max) = explode('-', $guest_spec);
                if ($guests >= (int) $min && $guests <= (int) $max) {
                    return (float) $adjustment;
                }
            } elseif (strpos($guest_spec, '+') !== false) {
                $min = (int) str_replace('+', '', $guest_spec);
                if ($guests >= $min) {
                    return (float) $adjustment;
                }
            }
        }

        return 0.0;
    }

    /**
     * Calculate minimum stay adjustment
     *
     * @param int $nights
     * @param DateTime $start_date
     * @return float
     */
    private function calculateMinStayAdjustment(int $nights, DateTime $start_date): float {
        $min_stay_rates = $this->config['min_stay_rates'] ?? [];

        foreach ($min_stay_rates as $rule) {
            if (!isset($rule['min_nights']) || $nights < $rule['min_nights']) {
                continue;
            }

            // Check if date range applies
            if (isset($rule['applies_to'])) {
                $applies = false;

                foreach ($rule['applies_to'] as $period) {
                    if ($this->isDateInPeriod($start_date, $period)) {
                        $applies = true;
                        break;
                    }
                }

                if (!$applies) {
                    continue;
                }
            }

            return isset($rule['adjustment']) ? (float) $rule['adjustment'] : 0.0;
        }

        return 0.0;
    }

    /**
     * Check if date is in specified period
     *
     * @param DateTime $date
     * @param array $period
     * @return bool
     */
    private function isDateInPeriod(DateTime $date, array $period): bool {
        if (isset($period['start']) && isset($period['end'])) {
            return $this->isDateInSeason($date, $period);
        }

        if (isset($period['dow'])) {
            $dow_list = is_array($period['dow']) ? $period['dow'] : [$period['dow']];
            return in_array($date->format('w'), $dow_list);
        }

        return true;
    }

    /**
     * Check if this resolver can handle the given booking
     *
     * @param array $booking_data
     * @param array $context
     * @return bool
     */
    public function canResolve(array $booking_data, array $context = []): bool {
        return isset($booking_data['start_date']) && isset($booking_data['end_date']);
    }

    /**
     * Get the priority of this resolver
     *
     * @return int
     */
    public function getPriority(): int {
        return $this->priority;
    }

    /**
     * Get human-readable name of this resolver
     *
     * @return string
     */
    public function getName(): string {
        return 'Season/DOW/LOS Rate Resolver';
    }

    /**
     * Get description of what this resolver does
     *
     * @return string
     */
    public function getDescription(): string {
        return 'Calculates rates based on seasonal pricing, day-of-week adjustments, length-of-stay discounts, and guest count modifications';
    }

    /**
     * Get available rate types this resolver can handle
     *
     * @return array
     */
    public function getSupportedRateTypes(): array {
        return ['seasonal', 'dow', 'los', 'guest_based', 'min_stay'];
    }

    /**
     * Configure the resolver with settings
     *
     * @param array $config
     * @return void
     */
    public function configure(array $config): void {
        $this->config = array_merge($this->config, $config);

        if (isset($config['enabled'])) {
            $this->setEnabled((bool) $config['enabled']);
        }

        if (isset($config['priority'])) {
            $this->priority = (int) $config['priority'];
        }
    }

    /**
     * Get current resolver configuration
     *
     * @return array
     */
    public function getConfig(): array {
        return array_merge($this->config, [
            'enabled' => $this->enabled,
            'priority' => $this->priority
        ]);
    }

    /**
     * Validate rate data for this resolver
     *
     * @param array $rate_data
     * @return array
     */
    public function validateRateData(array $rate_data): array {
        $errors = [];
        $warnings = [];

        // Validate base rate
        if (isset($rate_data['base_rate'])) {
            if (!is_numeric($rate_data['base_rate']) || $rate_data['base_rate'] < 0) {
                $errors[] = 'Base rate must be a positive number';
            }
        }

        // Validate seasonal rates
        if (isset($rate_data['seasonal_rates']) && is_array($rate_data['seasonal_rates'])) {
            foreach ($rate_data['seasonal_rates'] as $season) {
                if (!isset($season['start']) || !isset($season['end'])) {
                    $errors[] = 'Seasonal rate missing start or end date';
                }

                if (!isset($season['rate']) && !isset($season['multiplier']) && !isset($season['adjustment'])) {
                    $errors[] = 'Seasonal rate missing rate, multiplier, or adjustment';
                }
            }
        }

        // Validate DOW adjustments
        if (isset($rate_data['dow_adjustments']) && is_array($rate_data['dow_adjustments'])) {
            foreach ($rate_data['dow_adjustments'] as $dow => $adjustment) {
                if (!is_numeric($dow) || $dow < 0 || $dow > 6) {
                    $errors[] = 'Invalid day of week: ' . $dow;
                }

                if (!is_numeric($adjustment)) {
                    $errors[] = 'DOW adjustment must be numeric';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Get rate breakdown for transparency
     *
     * @param array $booking_data
     * @param array $context
     * @return array
     */
    public function getRateBreakdown(array $booking_data, array $context = []): array {
        return $this->resolveRate($booking_data, $context);
    }

    /**
     * Check if resolver is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }

    /**
     * Enable or disable the resolver
     *
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }
}