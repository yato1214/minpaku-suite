<?php
/**
 * Property Card Template
 *
 * Available variables:
 * - $property: WP_Post object for the property
 * - $property_id: Property ID
 * - $booking_counts: Array of booking counts by status
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Force fresh data from database
wp_cache_delete($property_id, 'posts');
clean_post_cache($property_id);

// Get fresh data directly from database
global $wpdb;
$fresh_property = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'mcs_property'",
    $property_id
));

if (!$fresh_property) {
    return;
}

// Convert to WP_Post object
$property = new WP_Post($fresh_property);

// Debug: Add timestamp to verify fresh data
$debug_info = '<!-- Fresh DB Query - Property ID: ' . $property_id . ', Title: ' . $property->post_title . ', Modified: ' . $property->post_modified . ' -->';

$thumbnail = get_the_post_thumbnail($property_id, 'medium', ['class' => 'mcs-property-thumbnail']);
$edit_link = get_edit_post_link($property_id);
$view_link = get_permalink($property_id);
$status_class = 'mcs-status--' . $property->post_status;

?>
<?php echo $debug_info; ?>
<div class="mcs-property-card">
    <?php if ($thumbnail) : ?>
        <div class="mcs-property-image">
            <a href="<?php echo esc_url($view_link); ?>" target="_blank">
                <?php echo $thumbnail; ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="mcs-property-content">
        <h4 class="mcs-property-title">
            <a href="<?php echo esc_url($view_link); ?>" target="_blank">
                <?php echo esc_html($property->post_title); ?>
            </a>
        </h4>

        <div class="mcs-property-meta">
            <span class="mcs-property-status <?php echo esc_attr($status_class); ?>">
                <?php
                $status_labels = [
                    'publish' => __('Published', 'minpaku-suite'),
                    'draft' => __('Draft', 'minpaku-suite'),
                    'private' => __('Private', 'minpaku-suite'),
                    'pending' => __('Pending Review', 'minpaku-suite'),
                ];
                echo esc_html($status_labels[$property->post_status] ?? ucfirst($property->post_status));
                ?>
            </span>

            <?php
            $property_capacity = get_post_meta($property_id, 'capacity', true);
            if ($property_capacity) :
            ?>
                <span class="mcs-property-capacity">
                    <?php echo esc_html(sprintf(__('%d guests', 'minpaku-suite'), $property_capacity)); ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Booking Statistics -->
        <div class="mcs-booking-stats">
            <div class="mcs-booking-stat mcs-booking-stat--confirmed">
                <span class="mcs-stat-number"><?php echo esc_html($booking_counts['confirmed']); ?></span>
                <span class="mcs-stat-label"><?php esc_html_e('Confirmed', 'minpaku-suite'); ?></span>
            </div>

            <div class="mcs-booking-stat mcs-booking-stat--pending">
                <span class="mcs-stat-number"><?php echo esc_html($booking_counts['pending']); ?></span>
                <span class="mcs-stat-label"><?php esc_html_e('Pending', 'minpaku-suite'); ?></span>
            </div>

            <?php if ($booking_counts['cancelled'] > 0) : ?>
                <div class="mcs-booking-stat mcs-booking-stat--cancelled">
                    <span class="mcs-stat-number"><?php echo esc_html($booking_counts['cancelled']); ?></span>
                    <span class="mcs-stat-label"><?php esc_html_e('Cancelled', 'minpaku-suite'); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Property Actions -->
        <div class="mcs-property-actions">
            <?php if ($edit_link && current_user_can('edit_post', $property_id)) : ?>
                <a href="<?php echo esc_url($edit_link); ?>" class="mcs-action-link mcs-action-link--edit">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e('Edit', 'minpaku-suite'); ?>
                </a>
            <?php endif; ?>

            <a href="<?php echo esc_url($view_link); ?>" class="mcs-action-link mcs-action-link--view" target="_blank">
                <span class="dashicons dashicons-visibility"></span>
                <?php esc_html_e('View', 'minpaku-suite'); ?>
            </a>

            <?php if (current_user_can('edit_mcs_bookings')) : ?>
                <span class="mcs-action-link mcs-action-link--booking mcs-action-disabled" title="<?php esc_attr_e('Use calendar quote panel to create bookings', 'minpaku-suite'); ?>">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e('Add Booking', 'minpaku-suite'); ?> (<?php esc_html_e('Use Calendar', 'minpaku-suite'); ?>)
                </span>
            <?php endif; ?>

            <?php
            // External detail URL button (if configured)
            $external_detail_url = '';
            if (class_exists('MinpakuSuite\Admin\PropertyExternalMetabox')) {
                $external_detail_url = \MinpakuSuite\Admin\PropertyExternalMetabox::get_external_detail_url($property_id);
            }
            ?>
            <?php if (!empty($external_detail_url)) : ?>
                <a href="<?php echo esc_url($external_detail_url); ?>" class="mcs-action-link mcs-action-link--external" target="_blank" rel="noopener noreferrer">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e('外部サイトで見る', 'minpaku-suite'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

