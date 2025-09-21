<?php
/**
 * Admin Dashboard Template
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get selected period from URL (support both 'days' and 'period' for compatibility)
$selected_days = isset($_GET['days']) ? absint($_GET['days']) : (isset($_GET['period']) ? absint($_GET['period']) : 30);
$allowed_days = [30, 90, 365];
if (!in_array($selected_days, $allowed_days)) {
    $selected_days = 30;
}

// Determine if this is for Owner Portal (filter by current user)
$is_owner_portal = (isset($_GET['page']) && $_GET['page'] === 'mcs-owner-portal');
$owner_user_id = $is_owner_portal ? get_current_user_id() : null;

// Get dashboard data with period and owner filtering
$counts = MinpakuSuite\Admin\AdminDashboardService::get_counts($selected_days, $owner_user_id);
$recent_bookings = MinpakuSuite\Admin\AdminDashboardService::get_recent_bookings(5, $selected_days, $owner_user_id);
$my_properties = MinpakuSuite\Admin\AdminDashboardService::get_my_properties();

?>
<div class="mcs-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="margin: 0;"><?php esc_html_e('Minpaku Suite', 'minpaku-suite'); ?></h1>

        <!-- Segmented Period Selector -->
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-weight: 500; color: #1d2327;">
                <?php esc_html_e('Period:', 'minpaku-suite'); ?>
            </span>
            <div class="mcs-segmented">
                <?php
                $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'minpaku-suite';
                $base = admin_url('admin.php?page=' . $current_page);
                foreach ([30, 90, 365] as $d) {
                    $active = ($selected_days === $d) ? ' is-active' : '';
                    $url = add_query_arg('days', $d, $base);
                    $label = sprintf(__('今後%d日', 'minpaku-suite'), $d);
                    echo '<a class="mcs-segmented__btn' . $active . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="mcs-overview">
        <h2><?php esc_html_e('Overview', 'minpaku-suite'); ?></h2>
        <div class="mcs-cards">
            <!-- Properties Card -->
            <div class="mcs-card" aria-label="<?php esc_attr_e('Properties count', 'minpaku-suite'); ?>">
                <div class="mcs-card-header">
                    <div class="mcs-card-icon mcs-card-icon--properties">
                        <span class="dashicons dashicons-admin-multisite"></span>
                    </div>
                    <h3 class="mcs-card-title"><?php esc_html_e('Properties', 'minpaku-suite'); ?></h3>
                </div>
                <div class="mcs-card-value" aria-label="<?php echo esc_attr(sprintf(__('%d properties total', 'minpaku-suite'), $counts['properties'])); ?>">
                    <?php echo esc_html(number_format($counts['properties'])); ?>
                </div>
                <p class="mcs-card-label"><?php esc_html_e('Total properties', 'minpaku-suite'); ?></p>
            </div>

            <!-- Confirmed Bookings Card -->
            <div class="mcs-card" aria-label="<?php esc_attr_e('Confirmed bookings count', 'minpaku-suite'); ?>">
                <div class="mcs-card-header">
                    <div class="mcs-card-icon mcs-card-icon--bookings">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <h3 class="mcs-card-title"><?php esc_html_e('Confirmed Bookings', 'minpaku-suite'); ?></h3>
                </div>
                <div class="mcs-card-value" aria-label="<?php echo esc_attr(sprintf(__('%d confirmed bookings in selected period', 'minpaku-suite'), $counts['confirmed_count'])); ?>">
                    <?php echo esc_html(number_format($counts['confirmed_count'])); ?>
                </div>
                <p class="mcs-card-label">
                    <?php
                    if ($selected_days == 30) {
                        esc_html_e('Next 30 days', 'minpaku-suite');
                    } elseif ($selected_days == 90) {
                        esc_html_e('Next 90 days', 'minpaku-suite');
                    } elseif ($selected_days == 365) {
                        esc_html_e('Next 365 days', 'minpaku-suite');
                    } else {
                        echo esc_html(sprintf(__('Next %d days', 'minpaku-suite'), $selected_days));
                    }
                    ?>
                </p>
            </div>

            <!-- Total Bookings Card -->
            <div class="mcs-card" aria-label="<?php esc_attr_e('Total bookings count', 'minpaku-suite'); ?>">
                <div class="mcs-card-header">
                    <div class="mcs-card-icon mcs-card-icon--occupancy">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <h3 class="mcs-card-title"><?php esc_html_e('Total Bookings', 'minpaku-suite'); ?></h3>
                </div>
                <div class="mcs-card-value" aria-label="<?php echo esc_attr(sprintf(__('%d total bookings in selected period', 'minpaku-suite'), $counts['total_count'])); ?>">
                    <?php echo esc_html(number_format($counts['total_count'])); ?>
                </div>
                <p class="mcs-card-label">
                    <?php
                    if ($selected_days == 30) {
                        esc_html_e('Next 30 days', 'minpaku-suite');
                    } elseif ($selected_days == 90) {
                        esc_html_e('Next 90 days', 'minpaku-suite');
                    } elseif ($selected_days == 365) {
                        esc_html_e('Next 365 days', 'minpaku-suite');
                    } else {
                        echo esc_html(sprintf(__('Next %d days', 'minpaku-suite'), $selected_days));
                    }
                    ?>
                </p>
            </div>

            <!-- Occupancy Card -->
            <div class="mcs-card" aria-label="<?php esc_attr_e('Occupancy rate', 'minpaku-suite'); ?>">
                <div class="mcs-card-header">
                    <div class="mcs-card-icon mcs-card-icon--occupancy">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <h3 class="mcs-card-title"><?php esc_html_e('Occupancy', 'minpaku-suite'); ?></h3>
                </div>
                <div class="mcs-card-value" aria-label="<?php echo esc_attr($counts['occupancy_pct'] !== null ? sprintf(__('%s percent occupancy rate', 'minpaku-suite'), $counts['occupancy_pct']) : __('Occupancy rate not available', 'minpaku-suite')); ?>">
                    <?php echo $counts['occupancy_pct'] !== null ? esc_html($counts['occupancy_pct'] . '%') : '—'; ?>
                </div>
                <p class="mcs-card-label"><?php esc_html_e('Estimated rate', 'minpaku-suite'); ?></p>
            </div>
        </div>
    </div>

    <!-- Recent Bookings Section -->
    <div class="mcs-section">
        <div class="mcs-section-header">
            <h2 class="mcs-section-title"><?php esc_html_e('Recent Bookings', 'minpaku-suite'); ?></h2>
        </div>
        <div class="mcs-section-content">
            <?php if (!empty($recent_bookings)) : ?>
                <table class="mcs-table" role="table" aria-label="<?php esc_attr_e('Recent bookings list', 'minpaku-suite'); ?>">
                    <thead>
                        <tr role="row">
                            <th scope="col"><?php esc_html_e('Property', 'minpaku-suite'); ?></th>
                            <th scope="col"><?php esc_html_e('Guest', 'minpaku-suite'); ?></th>
                            <th scope="col"><?php esc_html_e('Check-in', 'minpaku-suite'); ?></th>
                            <th scope="col"><?php esc_html_e('Nights', 'minpaku-suite'); ?></th>
                            <th scope="col"><?php esc_html_e('Status', 'minpaku-suite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bookings as $booking) : ?>
                            <tr role="row">
                                <td>
                                    <?php if ($booking['edit_link']) : ?>
                                        <a href="<?php echo esc_url($booking['edit_link']); ?>" aria-label="<?php echo esc_attr(sprintf(__('Edit booking for %s', 'minpaku-suite'), $booking['property_title'])); ?>">
                                            <?php echo esc_html($booking['property_title']); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html($booking['property_title']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($booking['guest_name']); ?></td>
                                <td>
                                    <?php if ($booking['check_in']) : ?>
                                        <time datetime="<?php echo esc_attr($booking['check_in']); ?>">
                                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking['check_in']))); ?>
                                        </time>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($booking['nights'] > 0) : ?>
                                        <?php echo esc_html(sprintf(_n('%d night', '%d nights', $booking['nights'], 'minpaku-suite'), $booking['nights'])); ?>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="mcs-status mcs-status--<?php echo esc_attr($booking['status']); ?>" aria-label="<?php echo esc_attr(sprintf(__('Status: %s', 'minpaku-suite'), MinpakuSuite\Admin\AdminDashboardService::get_status_label($booking['status']))); ?>">
                                        <?php echo esc_html(MinpakuSuite\Admin\AdminDashboardService::get_status_label($booking['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="mcs-empty">
                    <div class="mcs-empty-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <h3 class="mcs-empty-title"><?php esc_html_e('No recent bookings', 'minpaku-suite'); ?></h3>
                    <p class="mcs-empty-text"><?php esc_html_e('When you have bookings, they will appear here.', 'minpaku-suite'); ?></p>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mcs_booking')); ?>" class="mcs-empty-action">
                        <?php esc_html_e('Add First Booking', 'minpaku-suite'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Properties Section -->
    <div class="mcs-section">
        <div class="mcs-section-header">
            <h2 class="mcs-section-title"><?php esc_html_e('My Properties', 'minpaku-suite'); ?></h2>
        </div>
        <div class="mcs-section-content">
            <?php if (!empty($my_properties)) : ?>
                <div class="mcs-properties-grid" role="grid" aria-label="<?php esc_attr_e('Properties grid', 'minpaku-suite'); ?>">
                    <?php foreach ($my_properties as $property) : ?>
                        <div class="mcs-property-card" role="gridcell">
                            <?php if ($property['thumbnail']) : ?>
                                <img src="<?php echo esc_url($property['thumbnail']); ?>" alt="<?php echo esc_attr($property['title']); ?>" class="mcs-property-thumbnail" loading="lazy">
                            <?php else : ?>
                                <div class="mcs-property-thumbnail">
                                    <span class="dashicons dashicons-camera"></span>
                                    <?php esc_html_e('No image', 'minpaku-suite'); ?>
                                </div>
                            <?php endif; ?>

                            <div class="mcs-property-content">
                                <h3 class="mcs-property-title">
                                    <?php echo esc_html($property['title']); ?>
                                </h3>

                                <div class="mcs-property-meta">
                                    <span class="mcs-status mcs-status--<?php echo esc_attr($property['status']); ?>" aria-label="<?php echo esc_attr(sprintf(__('Status: %s', 'minpaku-suite'), MinpakuSuite\Admin\AdminDashboardService::get_property_status_label($property['status']))); ?>">
                                        <?php echo esc_html(MinpakuSuite\Admin\AdminDashboardService::get_property_status_label($property['status'])); ?>
                                    </span>
                                    <?php if ($property['capacity'] > 0) : ?>
                                        <span aria-label="<?php echo esc_attr(sprintf(__('Capacity: %d guests', 'minpaku-suite'), $property['capacity'])); ?>">
                                            <span class="dashicons dashicons-groups"></span>
                                            <?php echo esc_html(sprintf(__('%d guests', 'minpaku-suite'), $property['capacity'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="mcs-property-actions">
                                    <?php if ($property['edit_link']) : ?>
                                        <a href="<?php echo esc_url($property['edit_link']); ?>" class="mcs-action--edit" aria-label="<?php echo esc_attr(sprintf(__('Edit %s', 'minpaku-suite'), $property['title'])); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                            <?php esc_html_e('Edit', 'minpaku-suite'); ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($property['view_link']) : ?>
                                        <a href="<?php echo esc_url($property['view_link']); ?>" class="mcs-action--view" target="_blank" aria-label="<?php echo esc_attr(sprintf(__('View %s (opens in new tab)', 'minpaku-suite'), $property['title'])); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                            <?php esc_html_e('View', 'minpaku-suite'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="mcs-empty">
                    <div class="mcs-empty-icon">
                        <span class="dashicons dashicons-admin-multisite"></span>
                    </div>
                    <h3 class="mcs-empty-title"><?php esc_html_e('No properties yet', 'minpaku-suite'); ?></h3>
                    <p class="mcs-empty-text"><?php esc_html_e('Start by adding your first property to get started.', 'minpaku-suite'); ?></p>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mcs_property')); ?>" class="mcs-empty-action">
                        <?php esc_html_e('Add First Property', 'minpaku-suite'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="mcs-section">
        <div class="mcs-section-header">
            <h2 class="mcs-section-title"><?php esc_html_e('Quick Links', 'minpaku-suite'); ?></h2>
        </div>
        <div class="mcs-section-content">
            <div style="padding: 24px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=mcs_property')); ?>" class="mcs-empty-action" style="text-align: center; padding: 12px;">
                        <span class="dashicons dashicons-admin-multisite" style="display: block; font-size: 20px; margin-bottom: 8px;"></span>
                        <?php esc_html_e('Manage Properties', 'minpaku-suite'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=mcs_booking')); ?>" class="mcs-empty-action" style="text-align: center; padding: 12px;">
                        <span class="dashicons dashicons-calendar-alt" style="display: block; font-size: 20px; margin-bottom: 8px;"></span>
                        <?php esc_html_e('Manage Bookings', 'minpaku-suite'); ?>
                    </a>
                    <?php if (class_exists('MinpakuSuite\Portal\OwnerRoles') && MinpakuSuite\Portal\OwnerRoles::user_can_access_portal()) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=mcs-owner-portal')); ?>" class="mcs-empty-action" style="text-align: center; padding: 12px;">
                            <span class="dashicons dashicons-dashboard" style="display: block; font-size: 20px; margin-bottom: 8px;"></span>
                            <?php esc_html_e('Owner Portal', 'minpaku-suite'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>