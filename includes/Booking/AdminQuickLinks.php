<?php
/**
 * Booking Admin Quick Links
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Booking;

if (!defined('ABSPATH')) {
    exit;
}

class AdminQuickLinks
{
    public static function init(): void
    {
        add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);
        add_filter('post_row_actions', [__CLASS__, 'add_row_actions'], 10, 2);
        add_action('admin_bar_menu', [__CLASS__, 'add_admin_bar_menu'], 100);
    }

    /**
     * Add Quick Actions metabox to property edit screen
     */
    public static function add_metaboxes(): void
    {
        add_meta_box(
            'mcs_property_quick_booking',
            __('Quick Actions', 'minpaku-suite'),
            [__CLASS__, 'render_quick_actions_metabox'],
            'mcs_property',
            'side',
            'default'
        );
    }

    /**
     * Render Quick Actions metabox
     */
    public static function render_quick_actions_metabox($post): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $property_id = $post->ID;
        $nonce = wp_create_nonce('mcs_quick_booking');
        $booking_url = add_query_arg([
            'post_type' => 'mcs_booking',
            'property_id' => $property_id,
            '_mcs_nonce' => $nonce
        ], admin_url('post-new.php'));

        ?>
        <div class="mcs-quick-actions">
            <p>
                <a href="<?php echo esc_url($booking_url); ?>" class="button button-primary button-large" style="width: 100%; text-align: center;">
                    <span class="dashicons dashicons-calendar-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php _e('Add Booking for this Property', 'minpaku-suite'); ?>
                </a>
            </p>

            <p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=mcs_booking&mcs_property_filter=' . $property_id)); ?>" class="button button-secondary" style="width: 100%; text-align: center;">
                    <span class="dashicons dashicons-list-view" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php _e('View All Bookings', 'minpaku-suite'); ?>
                </a>
            </p>

            <?php if (has_shortcode(get_post_field('post_content', $property_id), 'portal_calendar')): ?>
            <p>
                <a href="<?php echo esc_url(get_permalink($property_id)); ?>" class="button button-secondary" style="width: 100%; text-align: center;" target="_blank">
                    <span class="dashicons dashicons-calendar" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php _e('View Availability Calendar', 'minpaku-suite'); ?>
                </a>
            </p>
            <?php endif; ?>

            <hr style="margin: 15px 0;">

            <div class="mcs-booking-stats">
                <?php echo self::render_booking_stats($property_id); ?>
            </div>
        </div>

        <style>
        .mcs-quick-actions .button {
            margin-bottom: 10px;
        }
        .mcs-booking-stats {
            font-size: 12px;
            color: #666;
        }
        .mcs-booking-stats .stat-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .mcs-booking-stats .stat-value {
            font-weight: bold;
        }
        </style>
        <?php
    }

    /**
     * Render booking statistics for the property
     */
    private static function render_booking_stats($property_id): string
    {
        $stats = self::get_booking_stats($property_id);

        $output = '<h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase;">' . __('Booking Stats', 'minpaku-suite') . '</h4>';

        foreach ($stats as $label => $value) {
            $output .= sprintf(
                '<div class="stat-item"><span>%s:</span> <span class="stat-value">%s</span></div>',
                esc_html($label),
                esc_html($value)
            );
        }

        return $output;
    }

    /**
     * Get booking statistics for property
     */
    private static function get_booking_stats($property_id): array
    {
        $args = [
            'post_type' => 'mcs_booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => '_mcs_property_id',
            'meta_value' => $property_id,
            'fields' => 'ids'
        ];

        $booking_ids = get_posts($args);
        $total_bookings = count($booking_ids);

        $confirmed = 0;
        $pending = 0;
        $cancelled = 0;

        foreach ($booking_ids as $booking_id) {
            $status = get_post_meta($booking_id, '_mcs_status', true);
            switch ($status) {
                case 'CONFIRMED':
                    $confirmed++;
                    break;
                case 'PENDING':
                    $pending++;
                    break;
                case 'CANCELLED':
                    $cancelled++;
                    break;
            }
        }

        return [
            __('Total', 'minpaku-suite') => $total_bookings,
            __('Confirmed', 'minpaku-suite') => $confirmed,
            __('Pending', 'minpaku-suite') => $pending,
            __('Cancelled', 'minpaku-suite') => $cancelled,
        ];
    }

    /**
     * Add row actions to property list
     */
    public static function add_row_actions($actions, $post): array
    {
        if ($post->post_type === 'mcs_property' && current_user_can('edit_posts')) {
            $nonce = wp_create_nonce('mcs_quick_booking');
            $booking_url = add_query_arg([
                'post_type' => 'mcs_booking',
                'property_id' => $post->ID,
                '_mcs_nonce' => $nonce
            ], admin_url('post-new.php'));

            $actions['add_booking'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($booking_url),
                __('Add Booking', 'minpaku-suite')
            );
        }

        return $actions;
    }

    /**
     * Add booking link to admin bar when editing property
     */
    public static function add_admin_bar_menu($wp_admin_bar): void
    {
        global $pagenow, $typenow, $post;

        if ($pagenow === 'post.php' && $typenow === 'mcs_property' && $post && current_user_can('edit_posts')) {
            $nonce = wp_create_nonce('mcs_quick_booking');
            $booking_url = add_query_arg([
                'post_type' => 'mcs_booking',
                'property_id' => $post->ID,
                '_mcs_nonce' => $nonce
            ], admin_url('post-new.php'));

            $wp_admin_bar->add_menu([
                'id' => 'mcs-add-booking',
                'title' => '<span class="ab-icon dashicons dashicons-plus"></span> ' . __('Booking', 'minpaku-suite'),
                'href' => $booking_url,
                'meta' => [
                    'title' => __('Add Booking for this Property', 'minpaku-suite')
                ]
            ]);
        }
    }
}