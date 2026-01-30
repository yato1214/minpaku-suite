<?php
/**
 * Rule Interface Contract
 * Defines the contract for all booking and pricing rules
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

interface RuleInterface {

    /**
     * Check if this rule applies to the given context
     *
     * @param array $context Context data (property_id, dates, guest_count, etc.)
     * @return bool True if rule applies, false otherwise
     */
    public function applies(array $context): bool;

    /**
     * Validate the booking against this rule
     *
     * @param array $booking_data Booking data to validate
     * @param array $context Additional context
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    public function validate(array $booking_data, array $context = []): array;

    /**
     * Get the priority of this rule for execution order
     * Lower numbers = higher priority (executed first)
     *
     * @return int Priority value (0-1000)
     */
    public function getPriority(): int;

    /**
     * Get human-readable name for this rule
     *
     * @return string Rule name
     */
    public function getName(): string;

    /**
     * Get description of what this rule does
     *
     * @return string Rule description
     */
    public function getDescription(): string;

    /**
     * Get rule configuration schema
     * Used for admin interface and validation
     *
     * @return array Configuration schema
     */
    public function getConfigSchema(): array;

    /**
     * Configure the rule with settings
     *
     * @param array $config Configuration array
     * @return void
     */
    public function configure(array $config): void;

    /**
     * Get current rule configuration
     *
     * @return array Current configuration
     */
    public function getConfig(): array;

    /**
     * Check if rule is enabled
     *
     * @return bool True if enabled
     */
    public function isEnabled(): bool;

    /**
     * Enable or disable the rule
     *
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void;
}