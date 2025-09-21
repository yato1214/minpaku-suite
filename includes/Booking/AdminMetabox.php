<?php
/**
 * Booking Admin Metabox
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Booking;

if (!defined('ABSPATH')) {
    exit;
}

class AdminMetabox
{
    public static function init(): void
    {
        add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);
        add_action('save_post_mcs_booking', [__CLASS__, 'save_booking_meta']);
        add_action('pre_get_posts', [__CLASS__, 'set_booking_title']);
    }

    public static function add_metaboxes(): void
    {
        add_meta_box(
            'mcs_booking_details',
            __('Reservation Details', 'minpaku-suite'),
            [__CLASS__, 'render_metabox'],
            'mcs_booking',
            'normal',
            'high'
        );
    }

    public static function render_metabox($post): void
    {
        wp_nonce_field('mcs_booking_meta', 'mcs_booking_nonce');

        $property_id = get_post_meta($post->ID, '_mcs_property_id', true);
        $guest_name = get_post_meta($post->ID, '_mcs_guest_name', true);
        $guest_email = get_post_meta($post->ID, '_mcs_guest_email', true);
        $guest_phone = get_post_meta($post->ID, '_mcs_guest_phone', true);
        $checkin = get_post_meta($post->ID, '_mcs_checkin', true);
        $checkout = get_post_meta($post->ID, '_mcs_checkout', true);
        $guests = get_post_meta($post->ID, '_mcs_guests', true) ?: 1;
        $status = get_post_meta($post->ID, '_mcs_status', true) ?: 'CONFIRMED';
        $notes = get_post_meta($post->ID, '_mcs_notes', true);

        // Get property from URL if creating new booking
        if (empty($property_id) && isset($_GET['property_id'])) {
            $property_id = intval($_GET['property_id']);
        }

        // Get properties for dropdown
        $properties = get_posts([
            'post_type' => 'mcs_property',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="mcs_property_id"><?php _e('Property', 'minpaku-suite'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select name="mcs_property_id" id="mcs_property_id" class="regular-text" required>
                        <option value=""><?php _e('Select Property', 'minpaku-suite'); ?></option>
                        <?php foreach ($properties as $property): ?>
                            <option value="<?php echo esc_attr($property->ID); ?>" <?php selected($property_id, $property->ID); ?>>
                                <?php echo esc_html($property->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mcs_guest_name"><?php _e('Guest Name', 'minpaku-suite'); ?></label>
                </th>
                <td>
                    <input type="text" name="mcs_guest_name" id="mcs_guest_name" value="<?php echo esc_attr($guest_name); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mcs_guest_email"><?php _e('Guest Email', 'minpaku-suite'); ?></label>
                </th>
                <td>
                    <input type="email" name="mcs_guest_email" id="mcs_guest_email" value="<?php echo esc_attr($guest_email); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mcs_guest_phone"><?php _e('Guest Phone', 'minpaku-suite'); ?></label>
                </th>
                <td>
                    <input type="tel" name="mcs_guest_phone" id="mcs_guest_phone" value="<?php echo esc_attr($guest_phone); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mcs_checkin"><?php _e('Check-in Date', 'minpaku-suite'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="date" name="mcs_checkin" id="mcs_checkin" value="<?php echo esc_attr($checkin); ?>" class="regular-text" required />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mcs_checkout"><?php _e('Check-out Date', 'minpaku-suite'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="date" name="mcs_checkout" id="mcs_checkout" value="<?php echo esc_attr($checkout); ?>" class="regular-text" required />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mcs_guests"><?php _e('Number of Guests', 'minpaku-suite'); ?></label>
                </th>
                <td>
                    <input type="number" name="mcs_guests" id="mcs_guests" value="<?php echo esc_attr($guests); ?>" class="small-text" min="1" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mcs_status"><?php _e('Status', 'minpaku-suite'); ?></label>
                </th>
                <td>
                    <select name="mcs_status" id="mcs_status" class="regular-text">
                        <option value="CONFIRMED" <?php selected($status, 'CONFIRMED'); ?>><?php _e('Confirmed', 'minpaku-suite'); ?></option>
                        <option value="PENDING" <?php selected($status, 'PENDING'); ?>><?php _e('Pending', 'minpaku-suite'); ?></option>
                        <option value="CANCELLED" <?php selected($status, 'CANCELLED'); ?>><?php _e('Cancelled', 'minpaku-suite'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mcs_notes"><?php _e('Notes', 'minpaku-suite'); ?></label>
                </th>
                <td>
                    <textarea name="mcs_notes" id="mcs_notes" rows="4" class="large-text"><?php echo esc_textarea($notes); ?></textarea>
                </td>
            </tr>
        </table>

        <script>
        jQuery(document).ready(function($) {
            function validateDates() {
                var checkin = $('#mcs_checkin').val();
                var checkout = $('#mcs_checkout').val();

                if (checkin && checkout && checkin >= checkout) {
                    alert('<?php echo esc_js(__('Check-out date must be after check-in date.', 'minpaku-suite')); ?>');
                    $('#mcs_checkout').focus();
                    return false;
                }
                return true;
            }

            $('#mcs_checkin, #mcs_checkout').on('change', validateDates);

            $('#post').on('submit', function(e) {
                if (!validateDates()) {
                    e.preventDefault();
                }
            });
        });
        </script>

        <style>
        .required { color: #d63384; }
        .form-table th { width: 200px; }
        </style>
        <?php
    }

    public static function save_booking_meta($post_id): void
    {
        if (!isset($_POST['mcs_booking_nonce']) || !wp_verify_nonce($_POST['mcs_booking_nonce'], 'mcs_booking_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Validate required fields
        $property_id = intval($_POST['mcs_property_id'] ?? 0);
        $checkin = sanitize_text_field($_POST['mcs_checkin'] ?? '');
        $checkout = sanitize_text_field($_POST['mcs_checkout'] ?? '');

        if (!$property_id || !$checkin || !$checkout) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Property, check-in and check-out dates are required.', 'minpaku-suite') . '</p></div>';
            });
            return;
        }

        // Validate dates
        $checkin_date = DateTime::createFromFormat('Y-m-d', $checkin);
        $checkout_date = DateTime::createFromFormat('Y-m-d', $checkout);

        if (!$checkin_date || !$checkout_date || $checkin_date >= $checkout_date) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Check-out date must be after check-in date.', 'minpaku-suite') . '</p></div>';
            });
            return;
        }

        // Save meta fields
        update_post_meta($post_id, '_mcs_property_id', $property_id);
        update_post_meta($post_id, '_mcs_guest_name', sanitize_text_field($_POST['mcs_guest_name'] ?? ''));
        update_post_meta($post_id, '_mcs_guest_email', sanitize_email($_POST['mcs_guest_email'] ?? ''));
        update_post_meta($post_id, '_mcs_guest_phone', sanitize_text_field($_POST['mcs_guest_phone'] ?? ''));
        update_post_meta($post_id, '_mcs_checkin', $checkin);
        update_post_meta($post_id, '_mcs_checkout', $checkout);
        update_post_meta($post_id, '_mcs_guests', intval($_POST['mcs_guests'] ?? 1));
        update_post_meta($post_id, '_mcs_status', sanitize_text_field($_POST['mcs_status'] ?? 'CONFIRMED'));
        update_post_meta($post_id, '_mcs_notes', sanitize_textarea_field($_POST['mcs_notes'] ?? ''));

        // Update post title
        self::update_booking_title($post_id);

        // Clear availability cache for this property
        self::clear_availability_cache($property_id);
    }

    public static function set_booking_title($query): void
    {
        if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'mcs_booking') {
            add_action('wp_insert_post', [__CLASS__, 'update_booking_title'], 10, 1);
        }
    }

    public static function update_booking_title($post_id): void
    {
        if (get_post_type($post_id) !== 'mcs_booking') {
            return;
        }

        $property_id = get_post_meta($post_id, '_mcs_property_id', true);
        $checkin = get_post_meta($post_id, '_mcs_checkin', true);
        $checkout = get_post_meta($post_id, '_mcs_checkout', true);
        $status = get_post_meta($post_id, '_mcs_status', true);

        if (!$property_id || !$checkin || !$checkout) {
            return;
        }

        $property = get_post($property_id);
        if (!$property) {
            return;
        }

        $title = sprintf(
            '%s | %s→%s | %s',
            $property->post_title,
            $checkin,
            $checkout,
            $status
        );

        // Prevent infinite loop
        remove_action('save_post_mcs_booking', [__CLASS__, 'save_booking_meta']);

        wp_update_post([
            'ID' => $post_id,
            'post_title' => $title
        ]);

        add_action('save_post_mcs_booking', [__CLASS__, 'save_booking_meta']);
    }

    private static function clear_availability_cache($property_id): void
    {
        if (class_exists('MinpakuSuite\Availability\AvailabilityService')) {
            \MinpakuSuite\Availability\AvailabilityService::clearCache($property_id);
        }
    }
}