<?php
/**
 * Booking Admin New Preset
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Booking;

if (!defined('ABSPATH')) {
    exit;
}

class AdminNewPreset
{
    private static $preset_property_id = null;
    private static $preset_checkin = null;
    private static $preset_checkout = null;
    private static $preset_guests = null;

    public static function init(): void
    {
        add_action('load-post-new.php', [__CLASS__, 'handle_property_preset']);
        add_action('admin_notices', [__CLASS__, 'show_property_notice']);
        add_action('admin_footer', [__CLASS__, 'add_preset_script']);
    }

    /**
     * Handle property preset from URL parameters
     */
    public static function handle_property_preset(): void
    {
        global $typenow;

        if ($typenow !== 'mcs_booking') {
            return;
        }

        $property_id = isset($_GET['property_id']) ? absint($_GET['property_id']) : 0;
        $nonce = isset($_GET['_mcs_nonce']) ? sanitize_text_field($_GET['_mcs_nonce']) : '';

        if (!$property_id || !wp_verify_nonce($nonce, 'mcs_quick_booking')) {
            return;
        }

        // Verify property exists
        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'mcs_property') {
            return;
        }

        // Store preset values
        self::$preset_property_id = $property_id;

        // Handle date presets
        if (isset($_GET['checkin'])) {
            $checkin = sanitize_text_field($_GET['checkin']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin)) {
                self::$preset_checkin = $checkin;
            }
        }

        if (isset($_GET['checkout'])) {
            $checkout = sanitize_text_field($_GET['checkout']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout)) {
                self::$preset_checkout = $checkout;
            }
        }

        // Handle guests preset
        if (isset($_GET['guests'])) {
            $guests = absint($_GET['guests']);
            if ($guests > 0 && $guests <= 50) {
                self::$preset_guests = $guests;
            }
        }

        // Add action to preset the form values
        add_action('add_meta_boxes', [__CLASS__, 'modify_metabox_callback'], 20);
    }

    /**
     * Modify the metabox callback to include preset values
     */
    public static function modify_metabox_callback(): void
    {
        if (self::$preset_property_id) {
            // Remove the original metabox
            remove_meta_box('mcs_booking_details', 'mcs_booking', 'normal');

            // Add our modified metabox
            add_meta_box(
                'mcs_booking_details',
                __('Reservation Details', 'minpaku-suite'),
                [__CLASS__, 'render_preset_metabox'],
                'mcs_booking',
                'normal',
                'high'
            );
        }
    }

    /**
     * Render metabox with preset property
     */
    public static function render_preset_metabox($post): void
    {
        // Get the original AdminMetabox class and modify the property_id
        if (class_exists('MinpakuSuite\Booking\AdminMetabox')) {
            // Temporarily set the property_id in GET to preset the form
            $_GET['property_id'] = self::$preset_property_id;

            // Call the original metabox render function
            \MinpakuSuite\Booking\AdminMetabox::render_metabox($post);

            // Clean up
            unset($_GET['property_id']);
        }
    }

    /**
     * Show admin notice for preset property
     */
    public static function show_property_notice(): void
    {
        global $pagenow, $typenow;

        if ($pagenow === 'post-new.php' && $typenow === 'mcs_booking' && self::$preset_property_id) {
            $property = get_post(self::$preset_property_id);
            if ($property) {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p><strong>' . __('Creating booking from calendar selection:', 'minpaku-suite') . '</strong><br>';
                echo '<strong>' . __('Property:', 'minpaku-suite') . '</strong> ' . esc_html($property->post_title) . ' (ID: ' . self::$preset_property_id . ')';

                if (self::$preset_checkin && self::$preset_checkout) {
                    echo '<br><strong>' . __('Dates:', 'minpaku-suite') . '</strong> ' .
                         esc_html(self::$preset_checkin) . ' → ' . esc_html(self::$preset_checkout);
                }

                if (self::$preset_guests) {
                    echo '<br><strong>' . __('Guests:', 'minpaku-suite') . '</strong> ' . esc_html(self::$preset_guests);
                }

                echo '</p>';
                echo '</div>';
            }
        }
    }

    /**
     * Add JavaScript to preset form values
     */
    public static function add_preset_script(): void
    {
        global $pagenow, $typenow;

        if ($pagenow === 'post-new.php' && $typenow === 'mcs_booking' && self::$preset_property_id) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Set the property dropdown to the preset value
                $('#mcs_property_id').val('<?php echo esc_js(self::$preset_property_id); ?>').trigger('change');

                <?php if (self::$preset_checkin): ?>
                // Set check-in date
                $('#mcs_check_in_date, input[name="mcs_check_in_date"]').val('<?php echo esc_js(self::$preset_checkin); ?>');
                <?php endif; ?>

                <?php if (self::$preset_checkout): ?>
                // Set check-out date
                $('#mcs_check_out_date, input[name="mcs_check_out_date"]').val('<?php echo esc_js(self::$preset_checkout); ?>');
                <?php endif; ?>

                <?php if (self::$preset_guests): ?>
                // Set number of guests
                $('#mcs_guests, input[name="mcs_guests"], #mcs_adults, input[name="mcs_adults"]').val('<?php echo esc_js(self::$preset_guests); ?>');
                <?php endif; ?>

                // Add hidden fields to preserve the preset values
                if (!$('input[name="_mcs_property_id"]').length) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: '_mcs_property_id',
                        value: '<?php echo esc_js(self::$preset_property_id); ?>'
                    }).appendTo('#post');
                }

                // Make the property field readonly to prevent changes
                $('#mcs_property_id').attr('readonly', true).css({
                    'background-color': '#f0f0f1',
                    'border': '1px solid #c3c4c7'
                });

                // Add a note about the preset property
                $('#mcs_property_id').after('<p class="description" style="color: #135e96; font-style: italic;"><?php echo esc_js(__('Property and booking details have been pre-filled from calendar selection.', 'minpaku-suite')); ?></p>');

                <?php if (self::$preset_checkin && self::$preset_checkout): ?>
                // Make date fields readonly if preset
                $('#mcs_check_in_date, input[name="mcs_check_in_date"], #mcs_check_out_date, input[name="mcs_check_out_date"]').attr('readonly', true).css({
                    'background-color': '#f0f0f1',
                    'border': '1px solid #c3c4c7'
                });
                <?php endif; ?>

                // Focus on the first editable field (guest name)
                $('#mcs_guest_name').focus();
            });
            </script>
            <?php
        }
    }

    /**
     * Get preset property ID (for use by other components)
     */
    public static function getPresetPropertyId(): ?int
    {
        return self::$preset_property_id;
    }

    /**
     * Get preset check-in date
     */
    public static function getPresetCheckin(): ?string
    {
        return self::$preset_checkin;
    }

    /**
     * Get preset check-out date
     */
    public static function getPresetCheckout(): ?string
    {
        return self::$preset_checkout;
    }

    /**
     * Get preset number of guests
     */
    public static function getPresetGuests(): ?int
    {
        return self::$preset_guests;
    }
}