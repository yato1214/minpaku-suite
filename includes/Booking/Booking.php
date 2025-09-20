<?php
/**
 * Booking Entity
 * Represents a booking with state machine functionality
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/BookingTransitionResult.php';

class Booking {

    /**
     * Booking states
     */
    const STATE_DRAFT = 'draft';
    const STATE_PENDING = 'pending';
    const STATE_CONFIRMED = 'confirmed';
    const STATE_CANCELLED = 'cancelled';
    const STATE_COMPLETED = 'completed';

    /**
     * Allowed state transitions
     */
    private static $allowed_transitions = [
        self::STATE_DRAFT => [self::STATE_PENDING],
        self::STATE_PENDING => [self::STATE_CONFIRMED, self::STATE_CANCELLED],
        self::STATE_CONFIRMED => [self::STATE_CANCELLED, self::STATE_COMPLETED],
        self::STATE_CANCELLED => [], // Terminal state
        self::STATE_COMPLETED => [], // Terminal state
    ];

    /**
     * Booking properties
     */
    private $id;
    private $property_id;
    private $checkin;
    private $checkout;
    private $adults;
    private $children;
    private $state;
    private $created_at;
    private $updated_at;
    private $meta_data = [];

    /**
     * Constructor
     *
     * @param array $data Booking data
     */
    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->property_id = $data['property_id'] ?? null;
        $this->checkin = $data['checkin'] ?? null;
        $this->checkout = $data['checkout'] ?? null;
        $this->adults = $data['adults'] ?? 1;
        $this->children = $data['children'] ?? 0;
        $this->state = $data['state'] ?? self::STATE_DRAFT;
        $this->created_at = $data['created_at'] ?? current_time('mysql');
        $this->updated_at = $data['updated_at'] ?? current_time('mysql');
        $this->meta_data = $data['meta_data'] ?? [];
    }

    /**
     * Get all valid states
     *
     * @return array
     */
    public static function getValidStates() {
        return [
            self::STATE_DRAFT,
            self::STATE_PENDING,
            self::STATE_CONFIRMED,
            self::STATE_CANCELLED,
            self::STATE_COMPLETED
        ];
    }

    /**
     * Get allowed transitions map
     *
     * @return array
     */
    public static function getAllowedTransitions() {
        return self::$allowed_transitions;
    }

    /**
     * Check if a transition is allowed
     *
     * @param string $from Source state
     * @param string $to Target state
     * @return bool
     */
    public static function canTransition($from, $to) {
        if (!isset(self::$allowed_transitions[$from])) {
            return false;
        }

        return in_array($to, self::$allowed_transitions[$from]);
    }

    /**
     * Get transition failure reason
     *
     * @param string $from Source state
     * @param string $to Target state
     * @return string
     */
    public static function getTransitionFailureReason($from, $to) {
        if (!in_array($from, self::getValidStates())) {
            return sprintf(__('Invalid source state: %s', 'minpaku-suite'), $from);
        }

        if (!in_array($to, self::getValidStates())) {
            return sprintf(__('Invalid target state: %s', 'minpaku-suite'), $to);
        }

        if ($from === $to) {
            return __('Source and target states are the same', 'minpaku-suite');
        }

        if (in_array($from, [self::STATE_CANCELLED, self::STATE_COMPLETED])) {
            return sprintf(__('Cannot transition from terminal state: %s', 'minpaku-suite'), $from);
        }

        if (!isset(self::$allowed_transitions[$from]) || !in_array($to, self::$allowed_transitions[$from])) {
            return sprintf(__('Transition from %s to %s is not allowed', 'minpaku-suite'), $from, $to);
        }

        return '';
    }

    /**
     * Attempt to transition to a new state
     *
     * @param string $to Target state
     * @param array $meta Additional metadata for the transition
     * @return BookingTransitionResult
     */
    public function transitionTo($to, array $meta = []) {
        $from = $this->state;

        // Validate the transition
        if (!self::canTransition($from, $to)) {
            $reason = self::getTransitionFailureReason($from, $to);
            return BookingTransitionResult::failure('invalid_transition', $reason, [
                'from' => $from,
                'to' => $to,
                'booking_id' => $this->id
            ]);
        }

        // Perform additional validation based on target state
        $validation_result = $this->validateStateTransition($to, $meta);
        if (!$validation_result['valid']) {
            return BookingTransitionResult::failure(
                $validation_result['error_code'],
                $validation_result['error_message'],
                array_merge($meta, ['from' => $from, 'to' => $to])
            );
        }

        // Update state and timestamp
        $old_state = $this->state;
        $this->state = $to;
        $this->updated_at = current_time('mysql');

        // Log transition if logger available
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Booking state transition', [
                'booking_id' => $this->id,
                'from' => $old_state,
                'to' => $to,
                'meta' => $meta
            ]);
        }

        return BookingTransitionResult::success($to, array_merge($meta, [
            'from' => $old_state,
            'to' => $to,
            'booking_id' => $this->id,
            'transitioned_at' => $this->updated_at
        ]));
    }

    /**
     * Validate specific state transition requirements
     *
     * @param string $to Target state
     * @param array $meta Transition metadata
     * @return array Validation result
     */
    private function validateStateTransition($to, array $meta) {
        // Basic booking data validation
        if (empty($this->property_id)) {
            return [
                'valid' => false,
                'error_code' => 'missing_property',
                'error_message' => __('Property ID is required', 'minpaku-suite')
            ];
        }

        if (empty($this->checkin) || empty($this->checkout)) {
            return [
                'valid' => false,
                'error_code' => 'missing_dates',
                'error_message' => __('Check-in and check-out dates are required', 'minpaku-suite')
            ];
        }

        // Validate date order
        $checkin_date = new DateTime($this->checkin);
        $checkout_date = new DateTime($this->checkout);
        if ($checkin_date >= $checkout_date) {
            return [
                'valid' => false,
                'error_code' => 'invalid_date_order',
                'error_message' => __('Check-out date must be after check-in date', 'minpaku-suite')
            ];
        }

        // Validate guest count
        if ($this->adults < 1) {
            return [
                'valid' => false,
                'error_code' => 'invalid_guest_count',
                'error_message' => __('At least one adult guest is required', 'minpaku-suite')
            ];
        }

        // State-specific validations
        switch ($to) {
            case self::STATE_PENDING:
                // Could add rate calculation validation here
                break;

            case self::STATE_CONFIRMED:
                // Could add payment authorization validation here
                if (empty($meta['payment_method'])) {
                    return [
                        'valid' => false,
                        'error_code' => 'missing_payment_method',
                        'error_message' => __('Payment method is required for confirmation', 'minpaku-suite')
                    ];
                }
                break;

            case self::STATE_CANCELLED:
                // Could add cancellation policy validation here
                break;

            case self::STATE_COMPLETED:
                // Validate that check-out date has passed
                $today = new DateTime();
                if ($checkout_date > $today) {
                    return [
                        'valid' => false,
                        'error_code' => 'premature_completion',
                        'error_message' => __('Cannot complete booking before check-out date', 'minpaku-suite')
                    ];
                }
                break;
        }

        return ['valid' => true];
    }

    /**
     * Get booking ID
     *
     * @return int|null
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set booking ID
     *
     * @param int $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * Get property ID
     *
     * @return int|null
     */
    public function getPropertyId() {
        return $this->property_id;
    }

    /**
     * Set property ID
     *
     * @param int $property_id
     */
    public function setPropertyId($property_id) {
        $this->property_id = $property_id;
        $this->updated_at = current_time('mysql');
    }

    /**
     * Get check-in date
     *
     * @return string|null
     */
    public function getCheckin() {
        return $this->checkin;
    }

    /**
     * Set check-in date
     *
     * @param string $checkin Date in Y-m-d format
     */
    public function setCheckin($checkin) {
        $this->checkin = $checkin;
        $this->updated_at = current_time('mysql');
    }

    /**
     * Get check-out date
     *
     * @return string|null
     */
    public function getCheckout() {
        return $this->checkout;
    }

    /**
     * Set check-out date
     *
     * @param string $checkout Date in Y-m-d format
     */
    public function setCheckout($checkout) {
        $this->checkout = $checkout;
        $this->updated_at = current_time('mysql');
    }

    /**
     * Get number of adult guests
     *
     * @return int
     */
    public function getAdults() {
        return $this->adults;
    }

    /**
     * Set number of adult guests
     *
     * @param int $adults
     */
    public function setAdults($adults) {
        $this->adults = max(1, intval($adults));
        $this->updated_at = current_time('mysql');
    }

    /**
     * Get number of child guests
     *
     * @return int
     */
    public function getChildren() {
        return $this->children;
    }

    /**
     * Set number of child guests
     *
     * @param int $children
     */
    public function setChildren($children) {
        $this->children = max(0, intval($children));
        $this->updated_at = current_time('mysql');
    }

    /**
     * Get total guest count
     *
     * @return int
     */
    public function getTotalGuests() {
        return $this->adults + $this->children;
    }

    /**
     * Get current state
     *
     * @return string
     */
    public function getState() {
        return $this->state;
    }

    /**
     * Get created timestamp
     *
     * @return string
     */
    public function getCreatedAt() {
        return $this->created_at;
    }

    /**
     * Get updated timestamp
     *
     * @return string
     */
    public function getUpdatedAt() {
        return $this->updated_at;
    }

    /**
     * Get all meta data
     *
     * @return array
     */
    public function getMetaData() {
        return $this->meta_data;
    }

    /**
     * Get specific meta value
     *
     * @param string $key Meta key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getMetaValue($key, $default = null) {
        return $this->meta_data[$key] ?? $default;
    }

    /**
     * Set meta value
     *
     * @param string $key Meta key
     * @param mixed $value Meta value
     */
    public function setMetaValue($key, $value) {
        $this->meta_data[$key] = $value;
        $this->updated_at = current_time('mysql');
    }

    /**
     * Set multiple meta values
     *
     * @param array $meta_data
     */
    public function setMetaData(array $meta_data) {
        $this->meta_data = array_merge($this->meta_data, $meta_data);
        $this->updated_at = current_time('mysql');
    }

    /**
     * Calculate number of nights
     *
     * @return int
     */
    public function getNights() {
        if (!$this->checkin || !$this->checkout) {
            return 0;
        }

        $checkin_date = new DateTime($this->checkin);
        $checkout_date = new DateTime($this->checkout);
        $diff = $checkin_date->diff($checkout_date);

        return $diff->days;
    }

    /**
     * Check if booking is in a terminal state
     *
     * @return bool
     */
    public function isTerminal() {
        return in_array($this->state, [self::STATE_CANCELLED, self::STATE_COMPLETED]);
    }

    /**
     * Check if booking can be modified
     *
     * @return bool
     */
    public function canBeModified() {
        return !$this->isTerminal();
    }

    /**
     * Convert to array representation
     *
     * @return array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'checkin' => $this->checkin,
            'checkout' => $this->checkout,
            'adults' => $this->adults,
            'children' => $this->children,
            'total_guests' => $this->getTotalGuests(),
            'nights' => $this->getNights(),
            'state' => $this->state,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'meta_data' => $this->meta_data,
            'is_terminal' => $this->isTerminal(),
            'can_be_modified' => $this->canBeModified()
        ];
    }
}