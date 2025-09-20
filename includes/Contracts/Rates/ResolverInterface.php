<?php
/**
 * Rate Resolver Interface Contract
 * Defines the contract for rate calculation and resolution
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

interface ResolverInterface {

    /**
     * Resolve rate for a given booking period
     *
     * @param array $booking_data ['start_date', 'end_date', 'property_id', 'guests', etc.]
     * @param array $context Additional context for rate calculation
     * @return array ['base_rate' => float, 'adjustments' => array, 'total_rate' => float, 'currency' => string, 'breakdown' => array]
     */
    public function resolveRate(array $booking_data, array $context = []): array;

    /**
     * Check if this resolver can handle the given booking
     *
     * @param array $booking_data
     * @param array $context
     * @return bool True if resolver can calculate rates for this booking
     */
    public function canResolve(array $booking_data, array $context = []): bool;

    /**
     * Get the priority of this resolver
     * Lower numbers = higher priority (checked first)
     *
     * @return int Priority value
     */
    public function getPriority(): int;

    /**
     * Get human-readable name of this resolver
     *
     * @return string Resolver name
     */
    public function getName(): string;

    /**
     * Get description of what this resolver does
     *
     * @return string Resolver description
     */
    public function getDescription(): string;

    /**
     * Get available rate types this resolver can handle
     *
     * @return array ['seasonal', 'dow', 'los', 'guest_based', etc.]
     */
    public function getSupportedRateTypes(): array;

    /**
     * Configure the resolver with settings
     *
     * @param array $config Configuration array
     * @return void
     */
    public function configure(array $config): void;

    /**
     * Get current resolver configuration
     *
     * @return array Current configuration
     */
    public function getConfig(): array;

    /**
     * Validate rate data for this resolver
     *
     * @param array $rate_data
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    public function validateRateData(array $rate_data): array;

    /**
     * Get rate breakdown for transparency
     *
     * @param array $booking_data
     * @param array $context
     * @return array Detailed breakdown of how rate was calculated
     */
    public function getRateBreakdown(array $booking_data, array $context = []): array;

    /**
     * Check if resolver is enabled
     *
     * @return bool True if enabled
     */
    public function isEnabled(): bool;

    /**
     * Enable or disable the resolver
     *
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void;
}