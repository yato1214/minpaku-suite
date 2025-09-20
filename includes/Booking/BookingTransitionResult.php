<?php
/**
 * Booking Transition Result
 * Represents the result of a state transition attempt
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class BookingTransitionResult {

    /**
     * Whether the transition was successful
     */
    private $success;

    /**
     * The new state after transition (null if failed)
     */
    private $new_state;

    /**
     * Error code if transition failed
     */
    private $error_code;

    /**
     * Human-readable error message
     */
    private $error_message;

    /**
     * Additional metadata about the transition
     */
    private $meta;

    /**
     * Constructor
     *
     * @param bool $success Whether transition succeeded
     * @param string|null $new_state New state if successful
     * @param string|null $error_code Error code if failed
     * @param string|null $error_message Error message if failed
     * @param array $meta Additional metadata
     */
    public function __construct($success, $new_state = null, $error_code = null, $error_message = null, $meta = []) {
        $this->success = $success;
        $this->new_state = $new_state;
        $this->error_code = $error_code;
        $this->error_message = $error_message;
        $this->meta = $meta;
    }

    /**
     * Create successful transition result
     *
     * @param string $new_state The new state
     * @param array $meta Additional metadata
     * @return BookingTransitionResult
     */
    public static function success($new_state, $meta = []) {
        return new self(true, $new_state, null, null, $meta);
    }

    /**
     * Create failed transition result
     *
     * @param string $error_code Error code
     * @param string $error_message Error message
     * @param array $meta Additional metadata
     * @return BookingTransitionResult
     */
    public static function failure($error_code, $error_message, $meta = []) {
        return new self(false, null, $error_code, $error_message, $meta);
    }

    /**
     * Check if transition was successful
     *
     * @return bool
     */
    public function isSuccess() {
        return $this->success;
    }

    /**
     * Get new state (null if failed)
     *
     * @return string|null
     */
    public function getNewState() {
        return $this->new_state;
    }

    /**
     * Get error code (null if successful)
     *
     * @return string|null
     */
    public function getErrorCode() {
        return $this->error_code;
    }

    /**
     * Get error message (null if successful)
     *
     * @return string|null
     */
    public function getErrorMessage() {
        return $this->error_message;
    }

    /**
     * Get metadata
     *
     * @return array
     */
    public function getMeta() {
        return $this->meta;
    }

    /**
     * Get specific meta value
     *
     * @param string $key Meta key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function getMetaValue($key, $default = null) {
        return $this->meta[$key] ?? $default;
    }
}