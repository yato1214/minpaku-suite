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

        // Store for later use
        self::$preset_property_id = $property_id;

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
                echo '<p><strong>' . __('Creating booking for property:', 'minpaku-suite') . '</strong> ';
                echo esc_html($property->post_title) . ' (ID: ' . self::$preset_property_id . ')';
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

                // Add a hidden field to preserve the property_id
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
                $('#mcs_property_id').after('<p class="description" style="color: #135e96; font-style: italic;"><?php echo esc_js(__('Property has been pre-selected and cannot be changed.', 'minpaku-suite')); ?></p>');

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
}