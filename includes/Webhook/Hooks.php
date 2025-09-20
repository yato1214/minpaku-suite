<?php
/**
 * Webhook Hooks
 * Integrates webhook dispatching with BookingService and payment events
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/WebhookDispatcher.php';

class WebhookHooks {

    /**
     * Webhook dispatcher instance
     */
    private $dispatcher;

    /**
     * Constructor
     */
    public function __construct() {
        $this->dispatcher = new WebhookDispatcher();
        $this->init();
    }

    /**
     * Initialize webhook hooks
     */
    public function init() {
        // Booking state transition hooks
        add_action('minpaku_booking_confirmed', [$this, 'onBookingConfirmed'], 10, 2);
        add_action('minpaku_booking_cancelled', [$this, 'onBookingCancelled'], 10, 2);
        add_action('minpaku_booking_completed', [$this, 'onBookingCompleted'], 10, 2);

        // Payment hooks (for future payment provider integration)
        add_action('minpaku_payment_authorized', [$this, 'onPaymentAuthorized'], 10, 2);
        add_action('minpaku_payment_captured', [$this, 'onPaymentCaptured'], 10, 2);
        add_action('minpaku_payment_refunded', [$this, 'onPaymentRefunded'], 10, 2);

        // Hook into BookingService transitions to trigger webhooks
        add_action('plugins_loaded', [$this, 'hookBookingService'], 25);

        // Log hooks initialization
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'Webhook hooks initialized', [
                'booking_hooks' => ['confirmed', 'cancelled', 'completed'],
                'payment_hooks' => ['authorized', 'captured', 'refunded']
            ]);
        }
    }

    /**
     * Hook into BookingService to add webhook triggers
     */
    public function hookBookingService() {
        // We'll monkey-patch the BookingService methods to trigger webhooks
        // This is done by hooking into the transition completion

        // Check if BookingService exists
        if (!class_exists('BookingService')) {
            return;
        }

        // Add filter to capture booking service results
        add_filter('minpaku_booking_service_confirm_result', [$this, 'handleBookingServiceConfirm'], 10, 2);
        add_filter('minpaku_booking_service_cancel_result', [$this, 'handleBookingServiceCancel'], 10, 2);
        add_filter('minpaku_booking_service_complete_result', [$this, 'handleBookingServiceComplete'], 10, 2);
    }

    /**
     * Handle booking confirmation from BookingService
     *
     * @param mixed $result BookingService result
     * @param array $context Operation context
     * @return mixed Unmodified result
     */
    public function handleBookingServiceConfirm($result, $context) {
        if (is_wp_error($result)) {
            return $result;
        }

        // Trigger booking confirmed webhook
        do_action('minpaku_booking_confirmed', $result, $context);

        return $result;
    }

    /**
     * Handle booking cancellation from BookingService
     *
     * @param mixed $result BookingService result
     * @param array $context Operation context
     * @return mixed Unmodified result
     */
    public function handleBookingServiceCancel($result, $context) {
        if (is_wp_error($result)) {
            return $result;
        }

        // Trigger booking cancelled webhook
        do_action('minpaku_booking_cancelled', $result, $context);

        return $result;
    }

    /**
     * Handle booking completion from BookingService
     *
     * @param mixed $result BookingService result
     * @param array $context Operation context
     * @return mixed Unmodified result
     */
    public function handleBookingServiceComplete($result, $context) {
        if (is_wp_error($result)) {
            return $result;
        }

        // Trigger booking completed webhook
        do_action('minpaku_booking_completed', $result, $context);

        return $result;
    }

    /**
     * Handle booking confirmed event
     *
     * @param Booking $booking Booking object
     * @param array $context Additional context
     */
    public function onBookingConfirmed($booking, $context = []) {
        try {
            $payload = $this->createBookingPayload($booking, $context);

            // Add quote information if available
            if (!empty($context['quote'])) {
                $payload['quote'] = $context['quote'];
            } else {
                // Generate quote data if not provided
                $payload['quote'] = $this->generateQuoteData($booking);
            }

            $delivery_keys = $this->dispatcher->dispatch('booking.confirmed', $payload);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Booking confirmed webhook dispatched', [
                    'booking_id' => $booking->getId(),
                    'delivery_keys' => $delivery_keys
                ]);
            }

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to dispatch booking confirmed webhook', [
                    'booking_id' => $booking->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle booking cancelled event
     *
     * @param Booking $booking Booking object
     * @param array $context Additional context
     */
    public function onBookingCancelled($booking, $context = []) {
        try {
            $payload = $this->createBookingPayload($booking, $context);

            // Add cancellation specific data
            $payload['cancelled_at'] = current_time('c');
            if (!empty($context['reason'])) {
                $payload['reason'] = $context['reason'];
            }
            if (!empty($context['refund_amount'])) {
                $payload['refund_amount'] = $context['refund_amount'];
                $payload['currency'] = $context['currency'] ?? 'JPY';
            }

            $delivery_keys = $this->dispatcher->dispatch('booking.cancelled', $payload);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Booking cancelled webhook dispatched', [
                    'booking_id' => $booking->getId(),
                    'delivery_keys' => $delivery_keys
                ]);
            }

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to dispatch booking cancelled webhook', [
                    'booking_id' => $booking->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle booking completed event
     *
     * @param Booking $booking Booking object
     * @param array $context Additional context
     */
    public function onBookingCompleted($booking, $context = []) {
        try {
            $payload = $this->createBookingPayload($booking, $context);

            // Add completion specific data
            $payload['completed_at'] = current_time('c');
            if (!empty($context['review_requested'])) {
                $payload['review_requested'] = true;
            }

            $delivery_keys = $this->dispatcher->dispatch('booking.completed', $payload);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Booking completed webhook dispatched', [
                    'booking_id' => $booking->getId(),
                    'delivery_keys' => $delivery_keys
                ]);
            }

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to dispatch booking completed webhook', [
                    'booking_id' => $booking->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle payment authorized event
     *
     * @param array $payment_data Payment data
     * @param array $context Additional context
     */
    public function onPaymentAuthorized($payment_data, $context = []) {
        try {
            $payload = array_merge($payment_data, [
                'authorized_at' => current_time('c')
            ]);

            $delivery_keys = $this->dispatcher->dispatch('payment.authorized', $payload);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Payment authorized webhook dispatched', [
                    'booking_id' => $payment_data['booking_id'] ?? null,
                    'amount' => $payment_data['amount'] ?? null,
                    'delivery_keys' => $delivery_keys
                ]);
            }

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to dispatch payment authorized webhook', [
                    'payment_data' => $payment_data,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle payment captured event
     *
     * @param array $payment_data Payment data
     * @param array $context Additional context
     */
    public function onPaymentCaptured($payment_data, $context = []) {
        try {
            $payload = array_merge($payment_data, [
                'captured_at' => current_time('c')
            ]);

            $delivery_keys = $this->dispatcher->dispatch('payment.captured', $payload);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Payment captured webhook dispatched', [
                    'booking_id' => $payment_data['booking_id'] ?? null,
                    'amount' => $payment_data['amount'] ?? null,
                    'delivery_keys' => $delivery_keys
                ]);
            }

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to dispatch payment captured webhook', [
                    'payment_data' => $payment_data,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle payment refunded event
     *
     * @param array $payment_data Payment data
     * @param array $context Additional context
     */
    public function onPaymentRefunded($payment_data, $context = []) {
        try {
            $payload = array_merge($payment_data, [
                'refunded_at' => current_time('c')
            ]);

            $delivery_keys = $this->dispatcher->dispatch('payment.refunded', $payload);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('INFO', 'Payment refunded webhook dispatched', [
                    'booking_id' => $payment_data['booking_id'] ?? null,
                    'amount' => $payment_data['amount'] ?? null,
                    'delivery_keys' => $delivery_keys
                ]);
            }

        } catch (Exception $e) {
            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('ERROR', 'Failed to dispatch payment refunded webhook', [
                    'payment_data' => $payment_data,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Create standardized booking payload
     *
     * @param Booking $booking Booking object
     * @param array $context Additional context
     * @return array Booking payload
     */
    private function createBookingPayload($booking, $context = []) {
        $payload = [
            'booking' => [
                'id' => $booking->getId(),
                'property_id' => $booking->getPropertyId(),
                'checkin' => $booking->getCheckin(),
                'checkout' => $booking->getCheckout(),
                'guests' => [
                    'adults' => $booking->getAdults(),
                    'children' => $booking->getChildren(),
                    'total' => $booking->getTotalGuests()
                ],
                'nights' => $booking->getNights(),
                'state' => $booking->getState(),
                'created_at' => $this->formatDateTime($booking->getCreatedAt()),
                'updated_at' => $this->formatDateTime($booking->getUpdatedAt())
            ]
        ];

        // Add property information if available
        $property_title = get_the_title($booking->getPropertyId());
        if ($property_title) {
            $payload['booking']['property_title'] = $property_title;
        }

        // Add booking metadata
        $meta_data = $booking->getMetaData();
        if (!empty($meta_data)) {
            $payload['booking']['metadata'] = $meta_data;
        }

        // Add context data
        if (!empty($context)) {
            $payload = array_merge($payload, $context);
        }

        return $payload;
    }

    /**
     * Generate quote data for booking
     *
     * @param Booking $booking Booking object
     * @return array Quote data
     */
    private function generateQuoteData($booking) {
        // Try to use RateResolver if available
        if (class_exists('RateResolver')) {
            try {
                $rate_resolver = new RateResolver();
                $booking_data = [
                    'property_id' => $booking->getPropertyId(),
                    'checkin' => $booking->getCheckin(),
                    'checkout' => $booking->getCheckout(),
                    'guests' => $booking->getTotalGuests(),
                    'adults' => $booking->getAdults(),
                    'children' => $booking->getChildren()
                ];

                $rate_result = $rate_resolver->resolveRate($booking_data);

                return [
                    'base' => $rate_result['base_rate'] * $booking->getNights(),
                    'taxes' => $rate_result['taxes_total'] ?? 0,
                    'fees' => $rate_result['fees_total'] ?? 0,
                    'total' => $rate_result['total_rate'],
                    'currency' => $rate_result['currency'] ?? 'JPY',
                    'breakdown' => $rate_result['breakdown'] ?? []
                ];

            } catch (Exception $e) {
                // Fall through to basic calculation
            }
        }

        // Basic fallback calculation
        $base_rate = floatval(get_post_meta($booking->getPropertyId(), 'base_rate', true)) ?: 10000;
        $currency = get_post_meta($booking->getPropertyId(), 'currency', true) ?: 'JPY';
        $tax_rate = floatval(get_post_meta($booking->getPropertyId(), 'tax_rate', true)) ?: 10.0;

        $base_total = $base_rate * $booking->getNights();
        $fees_total = 5000; // Fixed cleaning fee
        $taxes_total = ($base_total + $fees_total) * ($tax_rate / 100);
        $grand_total = $base_total + $fees_total + $taxes_total;

        return [
            'base' => $base_total,
            'taxes' => $taxes_total,
            'fees' => $fees_total,
            'total' => $grand_total,
            'currency' => $currency
        ];
    }

    /**
     * Format datetime to ISO 8601
     *
     * @param string $datetime MySQL datetime
     * @return string ISO 8601 datetime
     */
    private function formatDateTime($datetime) {
        return date('c', strtotime($datetime));
    }

    /**
     * Manually trigger booking webhook (for testing/admin)
     *
     * @param string $event Event name
     * @param int $booking_id Booking ID
     * @param array $context Additional context
     * @return array Delivery keys
     */
    public function triggerBookingWebhook($event, $booking_id, $context = []) {
        // Load booking
        if (class_exists('BookingRepository')) {
            $repository = new BookingRepository();
            $booking = $repository->findById($booking_id);

            if (!$booking) {
                throw new Exception(__('Booking not found', 'minpaku-suite'));
            }

            // Trigger appropriate webhook
            switch ($event) {
                case 'booking.confirmed':
                    $this->onBookingConfirmed($booking, $context);
                    break;
                case 'booking.cancelled':
                    $this->onBookingCancelled($booking, $context);
                    break;
                case 'booking.completed':
                    $this->onBookingCompleted($booking, $context);
                    break;
                default:
                    throw new Exception(__('Invalid event type', 'minpaku-suite'));
            }
        }
    }

    /**
     * Manually trigger payment webhook (for testing/admin)
     *
     * @param string $event Event name
     * @param array $payment_data Payment data
     * @param array $context Additional context
     * @return array Delivery keys
     */
    public function triggerPaymentWebhook($event, $payment_data, $context = []) {
        // Trigger appropriate webhook
        switch ($event) {
            case 'payment.authorized':
                $this->onPaymentAuthorized($payment_data, $context);
                break;
            case 'payment.captured':
                $this->onPaymentCaptured($payment_data, $context);
                break;
            case 'payment.refunded':
                $this->onPaymentRefunded($payment_data, $context);
                break;
            default:
                throw new Exception(__('Invalid event type', 'minpaku-suite'));
        }
    }

    /**
     * Get dispatcher instance
     *
     * @return WebhookDispatcher
     */
    public function getDispatcher() {
        return $this->dispatcher;
    }
}