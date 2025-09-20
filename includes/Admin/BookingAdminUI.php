<?php
/**
 * Booking Admin UI
 * Handles admin interface for booking management
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../Booking/BookingRepository.php';
require_once __DIR__ . '/../Booking/BookingLedger.php';
require_once __DIR__ . '/../Services/BookingService.php';

class BookingAdminUI {

    /**
     * Booking service
     */
    private $booking_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->booking_service = new BookingService();
        add_action('init', [$this, 'init']);
    }

    /**
     * Initialize admin UI
     */
    public function init() {
        // Only load admin UI for users with proper permissions
        if (!current_user_can('manage_minpaku')) {
            return;
        }

        // Add admin hooks
        add_action('admin_init', [$this, 'admin_init']);
        add_filter('manage_minpaku_booking_posts_columns', [$this, 'add_state_column']);
        add_action('manage_minpaku_booking_posts_custom_column', [$this, 'display_state_column'], 10, 2);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_booking_transition', [$this, 'handle_transition_ajax']);
        add_action('admin_post_booking_transition', [$this, 'handle_transition_post']);
    }

    /**
     * Admin initialization
     */
    public function admin_init() {
        // Add admin styles
        add_action('admin_head', [$this, 'add_admin_styles']);
    }

    /**
     * Add state column to booking list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_state_column($columns) {
        // Insert state column after title
        $new_columns = [];
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ($key === 'title') {
                $new_columns['booking_state'] = __('State', 'minpaku-suite');
                $new_columns['booking_property'] = __('Property', 'minpaku-suite');
                $new_columns['booking_dates'] = __('Dates', 'minpaku-suite');
                $new_columns['booking_guests'] = __('Guests', 'minpaku-suite');
            }
        }

        return $new_columns;
    }

    /**
     * Display state column content
     *
     * @param string $column Column name
     * @param int $post_id Post ID
     */
    public function display_state_column($column, $post_id) {
        $booking = $this->booking_service->getRepository()->findById($post_id);
        if (!$booking) {
            return;
        }

        switch ($column) {
            case 'booking_state':
                echo $this->renderStateBadge($booking->getState());
                break;

            case 'booking_property':
                $property_id = $booking->getPropertyId();
                if ($property_id) {
                    $property_title = get_the_title($property_id);
                    if ($property_title) {
                        printf(
                            '<a href="%s">%s</a><br><small>ID: %d</small>',
                            get_edit_post_link($property_id),
                            esc_html($property_title),
                            $property_id
                        );
                    } else {
                        printf(__('Property #%d', 'minpaku-suite'), $property_id);
                    }
                }
                break;

            case 'booking_dates':
                printf(
                    '<strong>%s</strong><br>%s<br><small>%d %s</small>',
                    esc_html($booking->getCheckin()),
                    esc_html($booking->getCheckout()),
                    $booking->getNights(),
                    _n('night', 'nights', $booking->getNights(), 'minpaku-suite')
                );
                break;

            case 'booking_guests':
                printf(
                    '%d %s<br>%d %s',
                    $booking->getAdults(),
                    _n('adult', 'adults', $booking->getAdults(), 'minpaku-suite'),
                    $booking->getChildren(),
                    _n('child', 'children', $booking->getChildren(), 'minpaku-suite')
                );
                break;
        }
    }

    /**
     * Add meta boxes to booking edit screen
     */
    public function add_meta_boxes() {
        add_meta_box(
            'booking_details',
            __('Booking Details', 'minpaku-suite'),
            [$this, 'render_booking_details_metabox'],
            'minpaku_booking',
            'normal',
            'high'
        );

        add_meta_box(
            'booking_state_actions',
            __('State & Actions', 'minpaku-suite'),
            [$this, 'render_state_actions_metabox'],
            'minpaku_booking',
            'side',
            'high'
        );

        add_meta_box(
            'booking_ledger',
            __('Ledger', 'minpaku-suite'),
            [$this, 'render_ledger_metabox'],
            'minpaku_booking',
            'normal',
            'low'
        );
    }

    /**
     * Render booking details metabox
     *
     * @param WP_Post $post
     */
    public function render_booking_details_metabox($post) {
        $booking = $this->booking_service->getRepository()->findById($post->ID);
        if (!$booking) {
            echo '<p>' . __('Booking data not found.', 'minpaku-suite') . '</p>';
            return;
        }

        wp_nonce_field('booking_details_nonce', 'booking_details_nonce');

        ?>
        <table class="form-table">
            <tr>
                <th><label for="booking_property_id"><?php _e('Property', 'minpaku-suite'); ?></label></th>
                <td>
                    <?php
                    $property_id = $booking->getPropertyId();
                    $property_title = get_the_title($property_id);
                    if ($property_title) {
                        printf(
                            '<a href="%s" target="_blank">%s</a> (ID: %d)',
                            get_edit_post_link($property_id),
                            esc_html($property_title),
                            $property_id
                        );
                    } else {
                        printf(__('Property #%d', 'minpaku-suite'), $property_id);
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="booking_checkin"><?php _e('Check-in', 'minpaku-suite'); ?></label></th>
                <td>
                    <input type="date" id="booking_checkin" name="booking_checkin"
                           value="<?php echo esc_attr($booking->getCheckin()); ?>"
                           <?php echo $booking->isTerminal() ? 'readonly' : ''; ?> />
                </td>
            </tr>
            <tr>
                <th><label for="booking_checkout"><?php _e('Check-out', 'minpaku-suite'); ?></label></th>
                <td>
                    <input type="date" id="booking_checkout" name="booking_checkout"
                           value="<?php echo esc_attr($booking->getCheckout()); ?>"
                           <?php echo $booking->isTerminal() ? 'readonly' : ''; ?> />
                </td>
            </tr>
            <tr>
                <th><label for="booking_adults"><?php _e('Adults', 'minpaku-suite'); ?></label></th>
                <td>
                    <input type="number" id="booking_adults" name="booking_adults"
                           value="<?php echo esc_attr($booking->getAdults()); ?>"
                           min="1" <?php echo $booking->isTerminal() ? 'readonly' : ''; ?> />
                </td>
            </tr>
            <tr>
                <th><label for="booking_children"><?php _e('Children', 'minpaku-suite'); ?></label></th>
                <td>
                    <input type="number" id="booking_children" name="booking_children"
                           value="<?php echo esc_attr($booking->getChildren()); ?>"
                           min="0" <?php echo $booking->isTerminal() ? 'readonly' : ''; ?> />
                </td>
            </tr>
            <tr>
                <th><?php _e('Total Nights', 'minpaku-suite'); ?></th>
                <td><strong><?php echo $booking->getNights(); ?></strong></td>
            </tr>
            <tr>
                <th><?php _e('Total Guests', 'minpaku-suite'); ?></th>
                <td><strong><?php echo $booking->getTotalGuests(); ?></strong></td>
            </tr>
            <tr>
                <th><?php _e('Created', 'minpaku-suite'); ?></th>
                <td><?php echo mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $booking->getCreatedAt()); ?></td>
            </tr>
            <tr>
                <th><?php _e('Last Updated', 'minpaku-suite'); ?></th>
                <td><?php echo mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $booking->getUpdatedAt()); ?></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render state actions metabox
     *
     * @param WP_Post $post
     */
    public function render_state_actions_metabox($post) {
        $booking = $this->booking_service->getRepository()->findById($post->ID);
        if (!$booking) {
            echo '<p>' . __('Booking data not found.', 'minpaku-suite') . '</p>';
            return;
        }

        $current_state = $booking->getState();
        $allowed_transitions = Booking::getAllowedTransitions();
        $possible_transitions = $allowed_transitions[$current_state] ?? [];

        wp_nonce_field('booking_transition_nonce', 'booking_transition_nonce');

        ?>
        <div class="booking-state-info">
            <p><strong><?php _e('Current State:', 'minpaku-suite'); ?></strong></p>
            <p><?php echo $this->renderStateBadge($current_state); ?></p>
        </div>

        <?php if (!empty($possible_transitions) && !$booking->isTerminal()): ?>
        <div class="booking-transitions">
            <p><strong><?php _e('Available Actions:', 'minpaku-suite'); ?></strong></p>

            <?php foreach ($possible_transitions as $target_state): ?>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-bottom: 10px;">
                <input type="hidden" name="action" value="booking_transition">
                <input type="hidden" name="booking_id" value="<?php echo $post->ID; ?>">
                <input type="hidden" name="target_state" value="<?php echo esc_attr($target_state); ?>">
                <?php wp_nonce_field('booking_transition_' . $post->ID, 'transition_nonce'); ?>

                <button type="submit" class="button button-secondary booking-transition-btn"
                        data-state="<?php echo esc_attr($target_state); ?>">
                    <?php echo $this->getTransitionButtonText($current_state, $target_state); ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($booking->isTerminal()): ?>
        <p><em><?php _e('This booking is in a terminal state and cannot be modified.', 'minpaku-suite'); ?></em></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render ledger metabox
     *
     * @param WP_Post $post
     */
    public function render_ledger_metabox($post) {
        $ledger = new BookingLedger();
        $entries = $ledger->list($post->ID, ['limit' => 10]);

        if (empty($entries)) {
            echo '<p>' . __('No ledger entries found.', 'minpaku-suite') . '</p>';
            return;
        }

        ?>
        <div class="booking-ledger">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'minpaku-suite'); ?></th>
                        <th><?php _e('Event', 'minpaku-suite'); ?></th>
                        <th><?php _e('Amount', 'minpaku-suite'); ?></th>
                        <th><?php _e('Details', 'minpaku-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td>
                            <small><?php echo esc_html($entry['formatted_date']); ?></small>
                        </td>
                        <td>
                            <span class="ledger-event ledger-event-<?php echo esc_attr($entry['event']); ?>">
                                <?php echo esc_html($entry['event_label']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($entry['amount'] != 0): ?>
                                <span class="ledger-amount <?php echo $entry['amount'] > 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo esc_html($entry['formatted_amount']); ?>
                                </span>
                            <?php else: ?>
                                <span class="ledger-amount neutral">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $this->renderLedgerEntryDetails($entry); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (count($entries) >= 10): ?>
            <p><a href="#" class="load-more-ledger" data-booking-id="<?php echo $post->ID; ?>">
                <?php _e('Load more entries...', 'minpaku-suite'); ?>
            </a></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render state badge
     *
     * @param string $state Booking state
     * @return string HTML for state badge
     */
    private function renderStateBadge($state) {
        $label = BookingRepository::getStateLabel($state);
        $class = BookingRepository::getStateClass($state);

        return sprintf(
            '<span class="booking-state-badge %s">%s</span>',
            esc_attr($class),
            esc_html($label)
        );
    }

    /**
     * Get transition button text
     *
     * @param string $from Source state
     * @param string $to Target state
     * @return string Button text
     */
    private function getTransitionButtonText($from, $to) {
        $transitions = [
            Booking::STATE_DRAFT => [
                Booking::STATE_PENDING => __('Make Pending', 'minpaku-suite')
            ],
            Booking::STATE_PENDING => [
                Booking::STATE_CONFIRMED => __('Confirm', 'minpaku-suite'),
                Booking::STATE_CANCELLED => __('Cancel', 'minpaku-suite')
            ],
            Booking::STATE_CONFIRMED => [
                Booking::STATE_CANCELLED => __('Cancel', 'minpaku-suite'),
                Booking::STATE_COMPLETED => __('Complete', 'minpaku-suite')
            ]
        ];

        return $transitions[$from][$to] ?? sprintf(__('Move to %s', 'minpaku-suite'), BookingRepository::getStateLabel($to));
    }

    /**
     * Render ledger entry details
     *
     * @param array $entry Ledger entry
     */
    private function renderLedgerEntryDetails($entry) {
        $meta = $entry['meta_data'];

        if (!empty($meta['note'])) {
            echo '<div class="ledger-note">' . esc_html($meta['note']) . '</div>';
        }

        if (!empty($meta['state_transition'])) {
            printf(
                '<small>%s → %s</small>',
                esc_html(BookingRepository::getStateLabel($meta['state_transition']['from'])),
                esc_html(BookingRepository::getStateLabel($meta['state_transition']['to']))
            );
        }

        if (!empty($meta['processed_by'])) {
            $user = get_user_by('id', $meta['processed_by']);
            if ($user) {
                echo '<br><small>' . sprintf(__('by %s', 'minpaku-suite'), esc_html($user->display_name)) . '</small>';
            }
        }
    }

    /**
     * Handle transition via POST
     */
    public function handle_transition_post() {
        if (!current_user_can('manage_minpaku')) {
            wp_die(__('Insufficient permissions', 'minpaku-suite'));
        }

        $booking_id = intval($_POST['booking_id'] ?? 0);
        $target_state = sanitize_text_field($_POST['target_state'] ?? '');

        if (!wp_verify_nonce($_POST['transition_nonce'] ?? '', 'booking_transition_' . $booking_id)) {
            wp_die(__('Security check failed', 'minpaku-suite'));
        }

        $result = $this->performTransition($booking_id, $target_state);

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        wp_redirect(get_edit_post_link($booking_id, 'url'));
        exit;
    }

    /**
     * Handle transition via AJAX
     */
    public function handle_transition_ajax() {
        if (!current_user_can('manage_minpaku')) {
            wp_send_json_error(__('Insufficient permissions', 'minpaku-suite'));
        }

        $booking_id = intval($_POST['booking_id'] ?? 0);
        $target_state = sanitize_text_field($_POST['target_state'] ?? '');

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'booking_transition_' . $booking_id)) {
            wp_send_json_error(__('Security check failed', 'minpaku-suite'));
        }

        $result = $this->performTransition($booking_id, $target_state);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'new_state' => $result->getState(),
            'state_badge' => $this->renderStateBadge($result->getState())
        ]);
    }

    /**
     * Perform state transition
     *
     * @param int $booking_id
     * @param string $target_state
     * @return Booking|WP_Error
     */
    private function performTransition($booking_id, $target_state) {
        switch ($target_state) {
            case Booking::STATE_PENDING:
                return $this->booking_service->makePending($booking_id);
            case Booking::STATE_CONFIRMED:
                return $this->booking_service->confirm($booking_id);
            case Booking::STATE_CANCELLED:
                return $this->booking_service->cancel($booking_id);
            case Booking::STATE_COMPLETED:
                return $this->booking_service->complete($booking_id);
            default:
                return new WP_Error('invalid_state', __('Invalid target state', 'minpaku-suite'));
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php', 'edit.php'])) {
            return;
        }

        global $post_type;
        if ($post_type !== 'minpaku_booking') {
            return;
        }

        wp_enqueue_script('jquery');
    }

    /**
     * Add admin styles
     */
    public function add_admin_styles() {
        global $post_type;
        if ($post_type !== 'minpaku_booking') {
            return;
        }

        ?>
        <style>
        .booking-state-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: white;
        }

        .booking-state-draft { background-color: #666; }
        .booking-state-pending { background-color: #f39c12; }
        .booking-state-confirmed { background-color: #27ae60; }
        .booking-state-cancelled { background-color: #e74c3c; }
        .booking-state-completed { background-color: #3498db; }

        .booking-transitions { margin-top: 15px; }
        .booking-transition-btn { width: 100%; margin-bottom: 5px; }

        .ledger-event {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 2px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .ledger-event-reserve { background-color: #95a5a6; color: white; }
        .ledger-event-confirm { background-color: #27ae60; color: white; }
        .ledger-event-cancel { background-color: #e74c3c; color: white; }
        .ledger-event-complete { background-color: #3498db; color: white; }
        .ledger-event-payment { background-color: #2ecc71; color: white; }
        .ledger-event-refund { background-color: #e67e22; color: white; }
        .ledger-event-note { background-color: #9b59b6; color: white; }

        .ledger-amount.positive { color: #27ae60; font-weight: bold; }
        .ledger-amount.negative { color: #e74c3c; font-weight: bold; }
        .ledger-amount.neutral { color: #666; }

        .ledger-note {
            font-style: italic;
            background-color: #f8f9fa;
            padding: 4px 8px;
            border-left: 3px solid #dee2e6;
            margin: 4px 0;
        }
        </style>
        <?php
    }
}