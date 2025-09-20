<?php
/**
 * Booking Service
 * Provides transaction-like operations for booking management
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../Booking/Booking.php';
require_once __DIR__ . '/../Booking/BookingRepository.php';
require_once __DIR__ . '/../Booking/BookingLedger.php';

class BookingService {

    /**
     * Booking repository
     */
    private $repository;

    /**
     * Booking ledger
     */
    private $ledger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->repository = new BookingRepository();
        $this->ledger = new BookingLedger();
    }

    /**
     * Create a draft booking
     *
     * @param array $booking_data Booking data
     * @return Booking|WP_Error Booking on success, WP_Error on failure
     */
    public function createDraft($booking_data) {
        // Validate required fields
        $validation_result = $this->validateBookingData($booking_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Check for overlapping bookings
        if (!empty($booking_data['property_id']) && !empty($booking_data['checkin']) && !empty($booking_data['checkout'])) {
            $overlapping = $this->repository->findOverlapping(
                $booking_data['property_id'],
                $booking_data['checkin'],
                $booking_data['checkout']
            );

            if (!empty($overlapping)) {
                return new WP_Error(
                    'booking_overlap',
                    __('Selected dates overlap with existing booking', 'minpaku-suite'),
                    ['overlapping_bookings' => array_map(function($b) { return $b->getId(); }, $overlapping)]
                );
            }
        }

        try {
            // Create booking
            $booking = new Booking(array_merge($booking_data, [
                'state' => Booking::STATE_DRAFT
            ]));

            // Save to repository
            $save_result = $this->repository->save($booking);
            if (is_wp_error($save_result)) {
                return $save_result;
            }

            // Add initial ledger entry
            $this->ledger->append(
                $booking->getId(),
                BookingLedger::EVENT_RESERVE,
                0.0,
                $booking_data['currency'] ?? 'JPY',
                [
                    'action' => 'draft_created',
                    'created_by' => get_current_user_id(),
                    'booking_data' => $this->sanitizeBookingDataForLedger($booking_data)
                ]
            );

            // Log creation
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Draft booking created', [
                    'booking_id' => $booking->getId(),
                    'property_id' => $booking->getPropertyId(),
                    'checkin' => $booking->getCheckin(),
                    'checkout' => $booking->getCheckout()
                ]);
            }

            return $booking;

        } catch (Exception $e) {
            return new WP_Error(
                'booking_creation_failed',
                sprintf(__('Failed to create booking: %s', 'minpaku-suite'), $e->getMessage()),
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Confirm a booking
     *
     * @param int $booking_id Booking ID
     * @param array $meta Additional metadata
     * @return Booking|WP_Error Booking on success, WP_Error on failure
     */
    public function confirm($booking_id, $meta = []) {
        return $this->performTransition($booking_id, Booking::STATE_CONFIRMED, BookingLedger::EVENT_CONFIRM, $meta);
    }

    /**
     * Cancel a booking
     *
     * @param int $booking_id Booking ID
     * @param array $meta Additional metadata
     * @return Booking|WP_Error Booking on success, WP_Error on failure
     */
    public function cancel($booking_id, $meta = []) {
        return $this->performTransition($booking_id, Booking::STATE_CANCELLED, BookingLedger::EVENT_CANCEL, $meta);
    }

    /**
     * Complete a booking
     *
     * @param int $booking_id Booking ID
     * @param array $meta Additional metadata
     * @return Booking|WP_Error Booking on success, WP_Error on failure
     */
    public function complete($booking_id, $meta = []) {
        return $this->performTransition($booking_id, Booking::STATE_COMPLETED, BookingLedger::EVENT_COMPLETE, $meta);
    }

    /**
     * Move booking to pending state
     *
     * @param int $booking_id Booking ID
     * @param array $meta Additional metadata
     * @return Booking|WP_Error Booking on success, WP_Error on failure
     */
    public function makePending($booking_id, $meta = []) {
        // For pending state, we don't create a separate ledger event
        // as it's typically an intermediate state
        $booking = $this->repository->findById($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'minpaku-suite'));
        }

        // Check permissions
        if (!current_user_can('manage_minpaku')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions', 'minpaku-suite'));
        }

        $transition_result = $booking->transitionTo(Booking::STATE_PENDING, $meta);
        if (!$transition_result->isSuccess()) {
            return new WP_Error(
                $transition_result->getErrorCode(),
                $transition_result->getErrorMessage()
            );
        }

        // Save booking
        $save_result = $this->repository->save($booking);
        if (is_wp_error($save_result)) {
            return $save_result;
        }

        return $booking;
    }

    /**
     * Add payment to booking
     *
     * @param int $booking_id Booking ID
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param array $meta Payment metadata
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function addPayment($booking_id, $amount, $currency = 'JPY', $meta = []) {
        $booking = $this->repository->findById($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'minpaku-suite'));
        }

        // Check permissions
        if (!current_user_can('manage_minpaku')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions', 'minpaku-suite'));
        }

        // Add payment to ledger
        $entry_id = $this->ledger->append(
            $booking_id,
            BookingLedger::EVENT_PAYMENT,
            $amount,
            $currency,
            array_merge($meta, [
                'action' => 'payment_added',
                'processed_by' => get_current_user_id(),
                'processed_at' => current_time('mysql')
            ])
        );

        if (!$entry_id) {
            return new WP_Error('payment_failed', __('Failed to record payment', 'minpaku-suite'));
        }

        // Log payment
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Payment added to booking', [
                'booking_id' => $booking_id,
                'amount' => $amount,
                'currency' => $currency,
                'ledger_entry_id' => $entry_id
            ]);
        }

        return true;
    }

    /**
     * Add refund to booking
     *
     * @param int $booking_id Booking ID
     * @param float $amount Refund amount (positive number)
     * @param string $currency Currency code
     * @param array $meta Refund metadata
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function addRefund($booking_id, $amount, $currency = 'JPY', $meta = []) {
        $booking = $this->repository->findById($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'minpaku-suite'));
        }

        // Check permissions
        if (!current_user_can('manage_minpaku')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions', 'minpaku-suite'));
        }

        // Add refund to ledger (as negative amount)
        $entry_id = $this->ledger->append(
            $booking_id,
            BookingLedger::EVENT_REFUND,
            -abs($amount), // Ensure negative amount for refunds
            $currency,
            array_merge($meta, [
                'action' => 'refund_added',
                'processed_by' => get_current_user_id(),
                'processed_at' => current_time('mysql')
            ])
        );

        if (!$entry_id) {
            return new WP_Error('refund_failed', __('Failed to record refund', 'minpaku-suite'));
        }

        // Log refund
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Refund added to booking', [
                'booking_id' => $booking_id,
                'amount' => $amount,
                'currency' => $currency,
                'ledger_entry_id' => $entry_id
            ]);
        }

        return true;
    }

    /**
     * Add note to booking ledger
     *
     * @param int $booking_id Booking ID
     * @param string $note Note text
     * @param array $meta Additional metadata
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function addNote($booking_id, $note, $meta = []) {
        $booking = $this->repository->findById($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'minpaku-suite'));
        }

        // Check permissions
        if (!current_user_can('manage_minpaku')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions', 'minpaku-suite'));
        }

        // Add note to ledger
        $entry_id = $this->ledger->append(
            $booking_id,
            BookingLedger::EVENT_NOTE,
            0.0,
            'JPY',
            array_merge($meta, [
                'note' => sanitize_textarea_field($note),
                'added_by' => get_current_user_id(),
                'added_at' => current_time('mysql')
            ])
        );

        if (!$entry_id) {
            return new WP_Error('note_failed', __('Failed to add note', 'minpaku-suite'));
        }

        return true;
    }

    /**
     * Get booking with ledger data
     *
     * @param int $booking_id Booking ID
     * @return array|WP_Error Booking data with ledger on success, WP_Error on failure
     */
    public function getBookingWithLedger($booking_id) {
        $booking = $this->repository->findById($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'minpaku-suite'));
        }

        $ledger_entries = $this->ledger->list($booking_id);
        $ledger_summary = $this->ledger->getSummary($booking_id);

        return [
            'booking' => $booking->toArray(),
            'ledger' => [
                'entries' => $ledger_entries,
                'summary' => $ledger_summary
            ]
        ];
    }

    /**
     * Perform state transition with ledger recording
     *
     * @param int $booking_id Booking ID
     * @param string $target_state Target state
     * @param string $ledger_event Ledger event type
     * @param array $meta Additional metadata
     * @return Booking|WP_Error Booking on success, WP_Error on failure
     */
    private function performTransition($booking_id, $target_state, $ledger_event, $meta = []) {
        $booking = $this->repository->findById($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'minpaku-suite'));
        }

        // Check permissions
        if (!current_user_can('manage_minpaku')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions', 'minpaku-suite'));
        }

        // Attempt transition
        $transition_result = $booking->transitionTo($target_state, $meta);
        if (!$transition_result->isSuccess()) {
            return new WP_Error(
                $transition_result->getErrorCode(),
                $transition_result->getErrorMessage()
            );
        }

        try {
            // Save booking
            $save_result = $this->repository->save($booking);
            if (is_wp_error($save_result)) {
                return $save_result;
            }

            // Add ledger entry
            $this->ledger->append(
                $booking_id,
                $ledger_event,
                $meta['amount'] ?? 0.0,
                $meta['currency'] ?? 'JPY',
                array_merge($meta, [
                    'state_transition' => [
                        'from' => $transition_result->getMetaValue('from'),
                        'to' => $transition_result->getMetaValue('to')
                    ],
                    'processed_by' => get_current_user_id(),
                    'processed_at' => current_time('mysql')
                ])
            );

            // Log transition
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Booking state transition completed', [
                    'booking_id' => $booking_id,
                    'from' => $transition_result->getMetaValue('from'),
                    'to' => $transition_result->getMetaValue('to'),
                    'ledger_event' => $ledger_event
                ]);
            }

            // Apply filter to allow webhook hooks to capture the result
            $filter_name = 'minpaku_booking_service_' . $this->getFilterNameFromLedgerEvent($ledger_event) . '_result';
            $filtered_result = apply_filters($filter_name, $booking, $meta);

            return $filtered_result;

        } catch (Exception $e) {
            return new WP_Error(
                'transition_failed',
                sprintf(__('Failed to complete transition: %s', 'minpaku-suite'), $e->getMessage()),
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Validate booking data
     *
     * @param array $booking_data Booking data
     * @return true|WP_Error True if valid, WP_Error if invalid
     */
    private function validateBookingData($booking_data) {
        if (empty($booking_data['property_id'])) {
            return new WP_Error('missing_property_id', __('Property ID is required', 'minpaku-suite'));
        }

        if (empty($booking_data['checkin'])) {
            return new WP_Error('missing_checkin', __('Check-in date is required', 'minpaku-suite'));
        }

        if (empty($booking_data['checkout'])) {
            return new WP_Error('missing_checkout', __('Check-out date is required', 'minpaku-suite'));
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_data['checkin'])) {
            return new WP_Error('invalid_checkin_format', __('Check-in date must be in Y-m-d format', 'minpaku-suite'));
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_data['checkout'])) {
            return new WP_Error('invalid_checkout_format', __('Check-out date must be in Y-m-d format', 'minpaku-suite'));
        }

        // Validate date order
        if ($booking_data['checkin'] >= $booking_data['checkout']) {
            return new WP_Error('invalid_date_order', __('Check-out date must be after check-in date', 'minpaku-suite'));
        }

        // Validate guest count
        $adults = intval($booking_data['adults'] ?? 1);
        if ($adults < 1) {
            return new WP_Error('invalid_adults', __('At least one adult guest is required', 'minpaku-suite'));
        }

        return true;
    }

    /**
     * Sanitize booking data for ledger storage
     *
     * @param array $booking_data Raw booking data
     * @return array Sanitized data
     */
    private function sanitizeBookingDataForLedger($booking_data) {
        return [
            'property_id' => intval($booking_data['property_id'] ?? 0),
            'checkin' => sanitize_text_field($booking_data['checkin'] ?? ''),
            'checkout' => sanitize_text_field($booking_data['checkout'] ?? ''),
            'adults' => intval($booking_data['adults'] ?? 1),
            'children' => intval($booking_data['children'] ?? 0)
        ];
    }

    /**
     * Get repository instance
     *
     * @return BookingRepository
     */
    public function getRepository() {
        return $this->repository;
    }

    /**
     * Get ledger instance
     *
     * @return BookingLedger
     */
    public function getLedger() {
        return $this->ledger;
    }

    /**
     * Get filter name from ledger event
     *
     * @param string $ledger_event Ledger event type
     * @return string Filter name component
     */
    private function getFilterNameFromLedgerEvent($ledger_event) {
        $mapping = [
            BookingLedger::EVENT_CONFIRM => 'confirm',
            BookingLedger::EVENT_CANCEL => 'cancel',
            BookingLedger::EVENT_COMPLETE => 'complete'
        ];

        return $mapping[$ledger_event] ?? 'unknown';
    }
}