<?php
/**
 * Rule Engine with Pipeline Pattern
 * Manages and executes booking and pricing rules
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class RuleEngine {

    private $rules = [];
    private $enabled = true;
    private $context = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->loadDefaultRules();
    }

    /**
     * Add a rule to the engine
     *
     * @param RuleInterface $rule
     * @return void
     */
    public function addRule(RuleInterface $rule): void {
        $this->rules[] = $rule;
        $this->sortRulesByPriority();
    }

    /**
     * Remove a rule from the engine
     *
     * @param string $rule_name
     * @return bool True if removed, false if not found
     */
    public function removeRule(string $rule_name): bool {
        foreach ($this->rules as $index => $rule) {
            if ($rule->getName() === $rule_name) {
                unset($this->rules[$index]);
                $this->rules = array_values($this->rules); // Re-index
                return true;
            }
        }

        return false;
    }

    /**
     * Get all rules
     *
     * @return array
     */
    public function getRules(): array {
        return $this->rules;
    }

    /**
     * Get rule by name
     *
     * @param string $rule_name
     * @return RuleInterface|null
     */
    public function getRule(string $rule_name): ?RuleInterface {
        foreach ($this->rules as $rule) {
            if ($rule->getName() === $rule_name) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Set global context for all rule executions
     *
     * @param array $context
     * @return void
     */
    public function setContext(array $context): void {
        $this->context = $context;
    }

    /**
     * Validate booking data against all applicable rules
     *
     * @param array $booking_data
     * @param array $additional_context
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array, 'rule_results' => array]
     */
    public function validateBooking(array $booking_data, array $additional_context = []): array {
        if (!$this->enabled) {
            return [
                'valid' => true,
                'errors' => [],
                'warnings' => [],
                'rule_results' => []
            ];
        }

        $context = array_merge($this->context, $additional_context);
        $all_errors = [];
        $all_warnings = [];
        $rule_results = [];
        $overall_valid = true;

        foreach ($this->rules as $rule) {
            if (!$rule->isEnabled() || !$rule->applies($context)) {
                continue;
            }

            try {
                $result = $rule->validate($booking_data, $context);

                $rule_results[$rule->getName()] = $result;

                if (!$result['valid']) {
                    $overall_valid = false;
                }

                if (isset($result['errors']) && is_array($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $all_errors[] = [
                            'rule' => $rule->getName(),
                            'message' => $error
                        ];
                    }
                }

                if (isset($result['warnings']) && is_array($result['warnings'])) {
                    foreach ($result['warnings'] as $warning) {
                        $all_warnings[] = [
                            'rule' => $rule->getName(),
                            'message' => $warning
                        ];
                    }
                }

                // Log rule execution
                if (class_exists('MCS_Logger')) {
                    MCS_Logger::log('DEBUG', 'Rule executed', [
                        'rule' => $rule->getName(),
                        'valid' => $result['valid'],
                        'error_count' => count($result['errors'] ?? []),
                        'warning_count' => count($result['warnings'] ?? [])
                    ]);
                }

            } catch (Exception $e) {
                $overall_valid = false;
                $all_errors[] = [
                    'rule' => $rule->getName(),
                    'message' => 'Rule execution failed: ' . $e->getMessage()
                ];

                if (class_exists('MCS_Logger')) {
                    MCS_Logger::log('ERROR', 'Rule execution failed', [
                        'rule' => $rule->getName(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }

        return [
            'valid' => $overall_valid,
            'errors' => $all_errors,
            'warnings' => $all_warnings,
            'rule_results' => $rule_results
        ];
    }

    /**
     * Get applicable rules for a given context
     *
     * @param array $context
     * @return array
     */
    public function getApplicableRules(array $context = []): array {
        $merged_context = array_merge($this->context, $context);
        $applicable_rules = [];

        foreach ($this->rules as $rule) {
            if ($rule->isEnabled() && $rule->applies($merged_context)) {
                $applicable_rules[] = $rule;
            }
        }

        return $applicable_rules;
    }

    /**
     * Sort rules by priority (lower number = higher priority)
     *
     * @return void
     */
    private function sortRulesByPriority(): void {
        usort($this->rules, function(RuleInterface $a, RuleInterface $b) {
            return $a->getPriority() - $b->getPriority();
        });
    }

    /**
     * Enable or disable the entire rule engine
     *
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    /**
     * Check if rule engine is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }

    /**
     * Load default rules that are commonly used
     *
     * @return void
     */
    private function loadDefaultRules(): void {
        // Rules are loaded here or through configuration
        // This method can be extended to auto-load rule classes

        do_action('mcs/rule_engine/load_rules', $this);
    }

    /**
     * Configure rule engine from settings
     *
     * @param array $config
     * @return void
     */
    public function configure(array $config): void {
        if (isset($config['enabled'])) {
            $this->setEnabled((bool) $config['enabled']);
        }

        if (isset($config['rules']) && is_array($config['rules'])) {
            foreach ($config['rules'] as $rule_name => $rule_config) {
                $rule = $this->getRule($rule_name);
                if ($rule && is_array($rule_config)) {
                    $rule->configure($rule_config);
                }
            }
        }

        if (isset($config['context']) && is_array($config['context'])) {
            $this->setContext($config['context']);
        }
    }

    /**
     * Get current configuration
     *
     * @return array
     */
    public function getConfiguration(): array {
        $config = [
            'enabled' => $this->enabled,
            'context' => $this->context,
            'rules' => []
        ];

        foreach ($this->rules as $rule) {
            $config['rules'][$rule->getName()] = [
                'enabled' => $rule->isEnabled(),
                'priority' => $rule->getPriority(),
                'config' => $rule->getConfig()
            ];
        }

        return $config;
    }

    /**
     * Get rule statistics
     *
     * @return array
     */
    public function getStatistics(): array {
        $total_rules = count($this->rules);
        $enabled_rules = 0;
        $priorities = [];

        foreach ($this->rules as $rule) {
            if ($rule->isEnabled()) {
                $enabled_rules++;
            }
            $priorities[] = $rule->getPriority();
        }

        return [
            'total_rules' => $total_rules,
            'enabled_rules' => $enabled_rules,
            'disabled_rules' => $total_rules - $enabled_rules,
            'min_priority' => !empty($priorities) ? min($priorities) : null,
            'max_priority' => !empty($priorities) ? max($priorities) : null,
            'engine_enabled' => $this->enabled
        ];
    }

    /**
     * Run a dry run validation to test rules without side effects
     *
     * @param array $booking_data
     * @param array $context
     * @return array
     */
    public function dryRunValidation(array $booking_data, array $context = []): array {
        $original_context = $this->context;

        // Set test context
        $test_context = array_merge($this->context, $context, ['dry_run' => true]);
        $this->setContext($test_context);

        // Run validation
        $result = $this->validateBooking($booking_data);

        // Restore original context
        $this->setContext($original_context);

        $result['dry_run'] = true;

        return $result;
    }

    /**
     * Export rule configuration for backup/transfer
     *
     * @return array
     */
    public function exportConfiguration(): array {
        $export = [
            'engine' => $this->getConfiguration(),
            'rules' => []
        ];

        foreach ($this->rules as $rule) {
            $export['rules'][] = [
                'class' => get_class($rule),
                'name' => $rule->getName(),
                'description' => $rule->getDescription(),
                'config_schema' => $rule->getConfigSchema(),
                'config' => $rule->getConfig(),
                'enabled' => $rule->isEnabled(),
                'priority' => $rule->getPriority()
            ];
        }

        return $export;
    }

    /**
     * Import rule configuration
     *
     * @param array $config
     * @return bool Success status
     */
    public function importConfiguration(array $config): bool {
        try {
            if (isset($config['engine'])) {
                $this->configure($config['engine']);
            }

            if (isset($config['rules']) && is_array($config['rules'])) {
                foreach ($config['rules'] as $rule_data) {
                    if (isset($rule_data['name'])) {
                        $rule = $this->getRule($rule_data['name']);
                        if ($rule) {
                            if (isset($rule_data['config'])) {
                                $rule->configure($rule_data['config']);
                            }
                            if (isset($rule_data['enabled'])) {
                                $rule->setEnabled((bool) $rule_data['enabled']);
                            }
                        }
                    }
                }
            }

            // Re-sort after configuration changes
            $this->sortRulesByPriority();

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Rule engine configuration imported successfully');
            }

            return true;

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to import rule engine configuration', [
                    'error' => $e->getMessage()
                ]);
            }

            return false;
        }
    }

    /**
     * Reset rule engine to default state
     *
     * @return void
     */
    public function reset(): void {
        $this->rules = [];
        $this->enabled = true;
        $this->context = [];
        $this->loadDefaultRules();

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Rule engine reset to default state');
        }
    }
}