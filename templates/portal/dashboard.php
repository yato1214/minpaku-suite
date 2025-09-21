<?php
/**
 * Owner Dashboard Template
 *
 * Available variables:
 * - $user_id: Current user ID
 * - $period: Selected period in days
 * - $summary: Summary statistics array
 * - $properties_query: WP_Query object with user properties
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="mcs-owner-dashboard">
    <!-- Dashboard Header -->
    <div class="mcs-dashboard-header">
        <h2><?php esc_html_e('My Properties Dashboard', 'minpaku-suite'); ?></h2>

        <!-- Period Filter -->
        <div class="mcs-period-filter">
            <label for="mcs-period-select"><?php esc_html_e('Period:', 'minpaku-suite'); ?></label>
            <select id="mcs-period-select" class="mcs-period-select" data-period="<?php echo esc_attr($period); ?>">
                <option value="30" <?php selected($period, 30); ?>><?php esc_html_e('Next 30 days', 'minpaku-suite'); ?></option>
                <option value="90" <?php selected($period, 90); ?>><?php esc_html_e('Next 90 days', 'minpaku-suite'); ?></option>
                <option value="365" <?php selected($period, 365); ?>><?php esc_html_e('Next year', 'minpaku-suite'); ?></option>
            </select>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="mcs-summary-stats" id="mcs-summary-stats">
        <div class="mcs-stat-card mcs-stat-card--properties">
            <div class="mcs-stat-value"><?php echo esc_html(number_format($summary['properties_count'])); ?></div>
            <div class="mcs-stat-label"><?php esc_html_e('Properties', 'minpaku-suite'); ?></div>
        </div>

        <div class="mcs-stat-card mcs-stat-card--confirmed">
            <div class="mcs-stat-value"><?php echo esc_html(number_format($summary['confirmed_bookings'])); ?></div>
            <div class="mcs-stat-label"><?php esc_html_e('Confirmed Bookings', 'minpaku-suite'); ?></div>
        </div>

        <div class="mcs-stat-card mcs-stat-card--pending">
            <div class="mcs-stat-value"><?php echo esc_html(number_format($summary['pending_bookings'])); ?></div>
            <div class="mcs-stat-label"><?php esc_html_e('Pending Bookings', 'minpaku-suite'); ?></div>
        </div>

        <div class="mcs-stat-card mcs-stat-card--total">
            <div class="mcs-stat-value"><?php echo esc_html(number_format($summary['total_bookings'])); ?></div>
            <div class="mcs-stat-label"><?php esc_html_e('Total Bookings', 'minpaku-suite'); ?></div>
        </div>
    </div>

    <!-- Properties Section -->
    <div class="mcs-properties-section">
        <h3><?php esc_html_e('My Properties', 'minpaku-suite'); ?></h3>

        <?php if ($properties_query->have_posts()) : ?>
            <div class="mcs-properties-grid">
                <?php while ($properties_query->have_posts()) : ?>
                    <?php $properties_query->the_post(); ?>
                    <?php
                    $property_id = get_the_ID();
                    $booking_counts = \MinpakuSuite\Portal\OwnerHelpers::get_property_booking_counts($property_id, $period);

                    // Include property card template
                    $card_template = MCS_PATH . 'templates/portal/property-card.php';
                    if (file_exists($card_template)) {
                        include $card_template;
                    }
                    ?>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if ($properties_query->max_num_pages > 1) : ?>
                <nav class="mcs-pagination">
                    <?php
                    $pagination = paginate_links([
                        'total' => $properties_query->max_num_pages,
                        'current' => max(1, get_query_var('paged')),
                        'format' => '?paged=%#%',
                        'show_all' => false,
                        'type' => 'array',
                        'prev_text' => __('Previous', 'minpaku-suite'),
                        'next_text' => __('Next', 'minpaku-suite'),
                    ]);

                    if ($pagination) :
                    ?>
                        <ul class="mcs-pagination-list">
                            <?php foreach ($pagination as $link) : ?>
                                <li class="mcs-pagination-item"><?php echo $link; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

        <?php else : ?>
            <div class="mcs-no-properties">
                <p><?php esc_html_e('No properties found.', 'minpaku-suite'); ?></p>
                <?php if (current_user_can('edit_mcs_properties')) : ?>
                    <p>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mcs_property')); ?>" class="mcs-button mcs-button--primary">
                            <?php esc_html_e('Add Your First Property', 'minpaku-suite'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php wp_reset_postdata(); ?>

<script>
(function() {
    const periodSelect = document.getElementById('mcs-period-select');
    if (!periodSelect) return;

    periodSelect.addEventListener('change', function() {
        const newPeriod = this.value;
        const currentUrl = new URL(window.location.href);

        // For frontend shortcode, we'll reload the page with a query parameter
        currentUrl.searchParams.set('period', newPeriod);
        window.location.href = currentUrl.toString();
    });
})();
</script>