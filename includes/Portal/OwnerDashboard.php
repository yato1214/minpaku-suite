<?php
/**
 * Owner Dashboard
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Portal;

if (!defined('ABSPATH')) {
    exit;
}

class OwnerDashboard
{
    public static function init(): void
    {
        add_shortcode('mcs_owner_dashboard', [__CLASS__, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
        add_action('wp_ajax_mcs_owner_summary', [__CLASS__, 'ajax_get_summary']);
        add_action('wp_ajax_nopriv_mcs_owner_summary', [__CLASS__, 'ajax_get_summary']);
    }

    /**
     * Render owner dashboard shortcode
     */
    public static function render_shortcode($atts): string
    {
        $atts = shortcode_atts([
            'period' => '30'
        ], $atts);

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return self::render_login_message();
        }

        $current_user_id = get_current_user_id();

        // Check if user has portal access
        if (!OwnerRoles::user_can_access_portal($current_user_id)) {
            return self::render_access_denied_message();
        }

        // Allow period override from URL parameter
        $url_period = isset($_GET['period']) ? absint($_GET['period']) : 0;
        $period = $url_period > 0 ? max(1, min(365, $url_period)) : max(1, min(365, absint($atts['period'])));

        try {
            // Clear cache for fresh data on dashboard load
            if (isset($_GET['refresh']) || is_admin()) {
                wp_cache_flush();
            }

            return self::render_dashboard($current_user_id, $period);
        } catch (Exception $e) {
            error_log('Minpaku Suite Dashboard Error: ' . $e->getMessage());
            return '<p class="mcs-error">' . __('Unable to load dashboard.', 'minpaku-suite') . '</p>';
        }
    }

    /**
     * Render login message for non-logged-in users
     */
    private static function render_login_message(): string
    {
        $login_url = wp_login_url(get_permalink());
        return sprintf(
            '<div class="mcs-portal-notice mcs-portal-notice--info">
                <p>%s</p>
                <p><a href="%s" class="mcs-button mcs-button--primary">%s</a></p>
            </div>',
            esc_html(__('Please log in to access the owner dashboard.', 'minpaku-suite')),
            esc_url($login_url),
            esc_html(__('Log In', 'minpaku-suite'))
        );
    }

    /**
     * Render access denied message
     */
    private static function render_access_denied_message(): string
    {
        return sprintf(
            '<div class="mcs-portal-notice mcs-portal-notice--error">
                <p>%s</p>
            </div>',
            esc_html(__('You do not have permission to access the owner dashboard.', 'minpaku-suite'))
        );
    }

    /**
     * Render the main dashboard
     */
    private static function render_dashboard(int $user_id, int $period): string
    {
        $template_path = self::get_template_path('dashboard.php');
        if (!$template_path) {
            return self::render_fallback_dashboard($user_id, $period);
        }

        ob_start();

        // Pass variables to template
        $dashboard_data = [
            'user_id' => $user_id,
            'period' => $period,
            'summary' => OwnerHelpers::get_owner_summary($user_id, $period),
            'properties_query' => OwnerHelpers::get_user_properties($user_id, [
                'posts_per_page' => 20,
                'paged' => get_query_var('paged') ?: 1,
                'cache_results' => false,
                'no_found_rows' => false  // We need pagination
            ])
        ];

        extract($dashboard_data);
        include $template_path;

        return ob_get_clean();
    }

    /**
     * Render fallback dashboard when template is not found
     */
    private static function render_fallback_dashboard(int $user_id, int $period): string
    {
        $summary = OwnerHelpers::get_owner_summary($user_id, $period);
        $properties_query = OwnerHelpers::get_user_properties($user_id, [
            'posts_per_page' => 20,
            'paged' => get_query_var('paged') ?: 1,
            'cache_results' => false,
            'no_found_rows' => false  // We need pagination
        ]);

        $output = '';

        // Overview Cards (using admin card UI style)
        $output .= '<div class="mcs-overview">';
        $output .= '<h2>' . esc_html(__('Overview', 'minpaku-suite')) . '</h2>';
        $output .= '<div class="mcs-cards">';

        // Properties Card
        $output .= '<div class="mcs-card">';
        $output .= '<div class="mcs-card-header">';
        $output .= '<div class="mcs-card-icon mcs-card-icon--properties"><span class="dashicons dashicons-admin-multisite"></span></div>';
        $output .= '<h3 class="mcs-card-title">' . esc_html(__('Properties', 'minpaku-suite')) . '</h3>';
        $output .= '</div>';
        $output .= '<div class="mcs-card-value">' . esc_html(number_format($summary['properties_count'] ?? 0)) . '</div>';
        $output .= '<p class="mcs-card-label">' . esc_html(__('Total properties', 'minpaku-suite')) . '</p>';
        $output .= '</div>';

        // Confirmed Bookings Card
        $output .= '<div class="mcs-card">';
        $output .= '<div class="mcs-card-header">';
        $output .= '<div class="mcs-card-icon mcs-card-icon--bookings"><span class="dashicons dashicons-calendar-alt"></span></div>';
        $output .= '<h3 class="mcs-card-title">' . esc_html(__('Confirmed Bookings', 'minpaku-suite')) . '</h3>';
        $output .= '</div>';
        $output .= '<div class="mcs-card-value">' . esc_html(number_format($summary['confirmed_bookings'] ?? 0)) . '</div>';
        $output .= '<p class="mcs-card-label">' . esc_html(sprintf(__('Last %d days', 'minpaku-suite'), $period)) . '</p>';
        $output .= '</div>';

        // Total Bookings Card
        $output .= '<div class="mcs-card">';
        $output .= '<div class="mcs-card-header">';
        $output .= '<div class="mcs-card-icon mcs-card-icon--occupancy"><span class="dashicons dashicons-chart-line"></span></div>';
        $output .= '<h3 class="mcs-card-title">' . esc_html(__('Total Bookings', 'minpaku-suite')) . '</h3>';
        $output .= '</div>';
        $output .= '<div class="mcs-card-value">' . esc_html(number_format($summary['total_bookings'] ?? 0)) . '</div>';
        $output .= '<p class="mcs-card-label">' . esc_html(sprintf(__('Last %d days', 'minpaku-suite'), $period)) . '</p>';
        $output .= '</div>';

        $output .= '</div>';
        $output .= '</div>';

        // Properties Section (using admin section style)
        $output .= '<div class="mcs-section">';
        $output .= '<div class="mcs-section-header">';
        $output .= '<h2 class="mcs-section-title">' . esc_html(__('My Properties', 'minpaku-suite')) . '</h2>';
        $output .= '</div>';
        $output .= '<div class="mcs-section-content">';

        if ($properties_query->have_posts()) {
            $output .= '<div class="mcs-properties-grid">';
            while ($properties_query->have_posts()) {
                $properties_query->the_post();
                $output .= self::render_property_card(get_the_ID(), $period);
            }
            $output .= '</div>';

            // Pagination
            $output .= self::render_pagination($properties_query);
        } else {
            $output .= '<div class="mcs-empty">';
            $output .= '<div class="mcs-empty-icon"><span class="dashicons dashicons-admin-multisite"></span></div>';
            $output .= '<h3 class="mcs-empty-title">' . esc_html(__('No properties found.', 'minpaku-suite')) . '</h3>';
            $output .= '<p class="mcs-empty-text">' . esc_html(__('Start by adding your first property to get started.', 'minpaku-suite')) . '</p>';
            $output .= '<a href="' . esc_url(admin_url('post-new.php?post_type=mcs_property')) . '" class="mcs-empty-action">' . esc_html(__('Add Your First Property', 'minpaku-suite')) . '</a>';
            $output .= '</div>';
        }

        $output .= '</div>';
        $output .= '</div>';

        wp_reset_postdata();
        return $output;
    }

    /**
     * Render period filter
     */
    private static function render_period_filter(int $current_period): string
    {
        $periods = [
            30 => __('Next 30 days', 'minpaku-suite'),
            90 => __('Next 90 days', 'minpaku-suite'),
            365 => __('Next year', 'minpaku-suite')
        ];

        $output = '<div class="mcs-period-filter">';
        $output .= '<label for="mcs-period-select">' . esc_html(__('Period:', 'minpaku-suite')) . '</label>';
        $output .= '<select id="mcs-period-select" class="mcs-period-select">';

        foreach ($periods as $days => $label) {
            $selected = $days === $current_period ? 'selected' : '';
            $output .= sprintf(
                '<option value="%d" %s>%s</option>',
                $days,
                $selected,
                esc_html($label)
            );
        }

        $output .= '</select>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render summary statistics
     */
    private static function render_summary_stats(array $summary, int $period): string
    {
        $output = '<div class="mcs-summary-stats">';

        $stats = [
            'properties_count' => __('Properties', 'minpaku-suite'),
            'confirmed_bookings' => __('Confirmed Bookings', 'minpaku-suite'),
            'pending_bookings' => __('Pending Bookings', 'minpaku-suite'),
            'total_bookings' => __('Total Bookings', 'minpaku-suite')
        ];

        foreach ($stats as $key => $label) {
            $value = $summary[$key] ?? 0;
            $output .= sprintf(
                '<div class="mcs-stat-card mcs-stat-card--%s">
                    <div class="mcs-stat-value">%s</div>
                    <div class="mcs-stat-label">%s</div>
                </div>',
                esc_attr($key),
                esc_html(number_format($value)),
                esc_html($label)
            );
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render individual property card
     */
    private static function render_property_card(int $property_id, int $period): string
    {
        $template_path = self::get_template_path('property-card.php');
        if ($template_path) {
            ob_start();

            // Set up global post data for the template
            global $post;
            $original_post = $post;

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
                return '';
            }

            $property = new WP_Post($fresh_property);

            if (!$property) {
                return '';
            }

            $post = $property;
            setup_postdata($property);

            $booking_counts = OwnerHelpers::get_property_booking_counts($property_id, $period);
            include $template_path;

            // Restore original post data
            $post = $original_post;
            if ($original_post) {
                setup_postdata($original_post);
            }

            return ob_get_clean();
        }

        // Fallback card rendering using admin card classes
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
            return '';
        }

        $property = new WP_Post($fresh_property);

        $booking_counts = OwnerHelpers::get_property_booking_counts($property_id, $period);
        $thumbnail = get_the_post_thumbnail_url($property_id, 'medium');

        $output = '<div class="mcs-property-card">';

        if ($thumbnail) {
            $output .= '<img src="' . esc_url($thumbnail) . '" alt="' . esc_attr($property->post_title) . '" class="mcs-property-thumbnail" loading="lazy">';
        } else {
            $output .= '<div class="mcs-property-thumbnail">';
            $output .= '<span class="dashicons dashicons-camera"></span>';
            $output .= esc_html(__('No image', 'minpaku-suite'));
            $output .= '</div>';
        }

        $output .= '<div class="mcs-property-content">';
        $output .= '<h3 class="mcs-property-title">' . esc_html($property->post_title) . '</h3>';

        $output .= '<div class="mcs-property-meta">';
        $output .= '<span class="mcs-status mcs-status--' . esc_attr($property->post_status) . '">' . esc_html(ucfirst($property->post_status)) . '</span>';

        // Add capacity if available
        $capacity = get_post_meta($property_id, 'capacity', true);
        if ($capacity > 0) {
            $output .= '<span><span class="dashicons dashicons-groups"></span>' . esc_html(sprintf(__('%d guests', 'minpaku-suite'), $capacity)) . '</span>';
        }
        $output .= '</div>';

        // Action links using admin action classes
        $output .= '<div class="mcs-property-actions">';
        $edit_link = get_edit_post_link($property_id);
        if ($edit_link) {
            $output .= '<a href="' . esc_url($edit_link) . '" class="mcs-action--edit">';
            $output .= '<span class="dashicons dashicons-edit"></span>' . esc_html(__('Edit', 'minpaku-suite'));
            $output .= '</a>';
        }

        $view_link = get_permalink($property_id);
        if ($view_link) {
            $output .= '<a href="' . esc_url($view_link) . '" class="mcs-action--view" target="_blank">';
            $output .= '<span class="dashicons dashicons-visibility"></span>' . esc_html(__('View', 'minpaku-suite'));
            $output .= '</a>';
        }
        $output .= '</div>';

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render pagination
     */
    private static function render_pagination(\WP_Query $query): string
    {
        if ($query->max_num_pages <= 1) {
            return '';
        }

        $pagination = paginate_links([
            'total' => $query->max_num_pages,
            'current' => max(1, get_query_var('paged')),
            'format' => '?paged=%#%',
            'show_all' => false,
            'type' => 'array',
            'prev_text' => __('Previous', 'minpaku-suite'),
            'next_text' => __('Next', 'minpaku-suite'),
        ]);

        if (!$pagination) {
            return '';
        }

        $output = '<nav class="mcs-pagination">';
        $output .= '<ul class="mcs-pagination-list">';
        foreach ($pagination as $link) {
            $output .= '<li class="mcs-pagination-item">' . $link . '</li>';
        }
        $output .= '</ul>';
        $output .= '</nav>';

        return $output;
    }

    /**
     * Get template path
     */
    private static function get_template_path(string $template_name): string
    {
        $template_path = MCS_PATH . 'templates/portal/' . $template_name;
        return file_exists($template_path) ? $template_path : '';
    }

    /**
     * Ajax handler for summary updates
     */
    public static function ajax_get_summary(): void
    {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 403);
        }

        $user_id = get_current_user_id();
        if (!OwnerRoles::user_can_access_portal($user_id)) {
            wp_die('Forbidden', 403);
        }

        $period = max(1, min(365, absint($_POST['period'] ?? 30)));
        $summary = OwnerHelpers::get_owner_summary($user_id, $period);

        wp_send_json_success($summary);
    }

    /**
     * Enqueue dashboard styles
     */
    public static function enqueue_styles(): void
    {
        if (self::should_enqueue_styles()) {
            wp_add_inline_style('wp-block-library', self::get_dashboard_css());
        }
    }

    /**
     * Check if we should enqueue styles
     */
    private static function should_enqueue_styles(): bool
    {
        global $post;

        if (!$post) {
            return false;
        }

        return has_shortcode($post->post_content, 'mcs_owner_dashboard');
    }

    /**
     * Get dashboard CSS
     */
    private static function get_dashboard_CSS(): string
    {
        return '
            .mcs-owner-dashboard {
                max-width: 1200px;
                margin: 20px 0;
            }

            .mcs-dashboard-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                padding-bottom: 15px;
                border-bottom: 2px solid #e1e5e9;
            }

            .mcs-dashboard-header h2 {
                margin: 0;
                color: #2c3e50;
            }

            .mcs-period-filter {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .mcs-period-select {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: white;
            }

            .mcs-summary-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 40px;
            }

            .mcs-stat-card {
                background: white;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #e1e5e9;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .mcs-stat-value {
                font-size: 2em;
                font-weight: bold;
                color: #2980b9;
                margin-bottom: 5px;
            }

            .mcs-stat-label {
                color: #7f8c8d;
                font-size: 0.9em;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .mcs-properties-section h3 {
                margin-bottom: 20px;
                color: #2c3e50;
            }

            .mcs-properties-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .mcs-property-card {
                background: white;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                transition: transform 0.2s ease;
            }

            .mcs-property-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            }

            .mcs-property-image img {
                width: 100%;
                height: 200px;
                object-fit: cover;
            }

            .mcs-property-content {
                padding: 15px;
            }

            .mcs-property-title {
                margin: 0 0 10px 0;
                color: #2c3e50;
                font-size: 1.1em;
            }

            .mcs-property-status {
                color: #7f8c8d;
                font-size: 0.9em;
                margin-bottom: 10px;
            }

            .mcs-booking-stats {
                display: flex;
                gap: 15px;
                margin-bottom: 15px;
            }

            .mcs-booking-stat {
                font-size: 0.9em;
                color: #34495e;
            }

            .mcs-property-actions {
                display: flex;
                gap: 10px;
            }

            .mcs-action-link {
                padding: 6px 12px;
                background: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-size: 0.9em;
                transition: background 0.2s ease;
            }

            .mcs-action-link:hover {
                background: #2980b9;
                color: white;
            }

            .mcs-pagination {
                margin-top: 30px;
                text-align: center;
            }

            .mcs-pagination-list {
                display: inline-flex;
                list-style: none;
                margin: 0;
                padding: 0;
                gap: 5px;
            }

            .mcs-pagination-item a,
            .mcs-pagination-item span {
                display: block;
                padding: 8px 12px;
                border: 1px solid #ddd;
                text-decoration: none;
                color: #2980b9;
                border-radius: 4px;
            }

            .mcs-pagination-item .current {
                background: #2980b9;
                color: white;
                border-color: #2980b9;
            }

            .mcs-portal-notice {
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }

            .mcs-portal-notice--info {
                background: #e8f4fd;
                border: 1px solid #bee5eb;
                color: #0c5460;
            }

            .mcs-portal-notice--error {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }

            .mcs-button {
                display: inline-block;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 4px;
                transition: all 0.2s ease;
            }

            .mcs-button--primary {
                background: #2980b9;
                color: white;
            }

            .mcs-button--primary:hover {
                background: #21618c;
                color: white;
            }

            .mcs-no-properties {
                text-align: center;
                color: #7f8c8d;
                font-style: italic;
                padding: 40px 20px;
            }

            .mcs-error {
                color: #d63384;
                background: #f8d7da;
                padding: 15px;
                border-radius: 4px;
                border: 1px solid #f5c6cb;
            }

            @media (max-width: 768px) {
                .mcs-dashboard-header {
                    flex-direction: column;
                    gap: 15px;
                    text-align: center;
                }

                .mcs-summary-stats {
                    grid-template-columns: repeat(2, 1fr);
                }

                .mcs-properties-grid {
                    grid-template-columns: 1fr;
                }

                .mcs-booking-stats {
                    flex-direction: column;
                    gap: 5px;
                }
            }
        ';
    }
}