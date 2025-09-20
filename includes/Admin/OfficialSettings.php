<?php

namespace Minpaku\Admin;

use Minpaku\Official\OfficialSiteGenerator;
use Minpaku\Official\OfficialRewrite;

class OfficialSettings {

    private $settings_page = 'minpaku-official-settings';
    private $settings_group = 'minpaku_official_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('wp_ajax_bulk_generate_official_pages', [$this, 'handleBulkGenerate']);
        add_action('wp_ajax_flush_official_rewrite_rules', [$this, 'handleFlushRewriteRules']);
        add_action('wp_ajax_test_official_template', [$this, 'handleTemplateTest']);
    }

    public function addSettingsPage() {
        add_submenu_page(
            'edit.php?post_type=minpaku_property',
            __('Official Site Settings', 'minpaku-suite'),
            __('Official Site', 'minpaku-suite'),
            'manage_options',
            $this->settings_page,
            [$this, 'renderSettingsPage']
        );
    }

    public function registerSettings() {
        register_setting($this->settings_group, 'minpaku_official_enabled', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);

        register_setting($this->settings_group, 'minpaku_official_auto_generate', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);

        register_setting($this->settings_group, 'minpaku_official_url_structure', [
            'type' => 'string',
            'default' => 'stay',
            'sanitize_callback' => [$this, 'sanitizeUrlStructure']
        ]);

        register_setting($this->settings_group, 'minpaku_official_default_sections', [
            'type' => 'array',
            'default' => ['hero', 'gallery', 'features', 'calendar', 'quote', 'access'],
            'sanitize_callback' => [$this, 'sanitizeSectionArray']
        ]);

        register_setting($this->settings_group, 'minpaku_official_seo_enabled', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);

        register_setting($this->settings_group, 'minpaku_official_analytics_code', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting($this->settings_group, 'minpaku_official_custom_css', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => [$this, 'sanitizeCSS']
        ]);

        add_settings_section(
            'minpaku_official_general',
            __('General Settings', 'minpaku-suite'),
            [$this, 'renderGeneralSection'],
            $this->settings_page
        );

        add_settings_section(
            'minpaku_official_template',
            __('Template Settings', 'minpaku-suite'),
            [$this, 'renderTemplateSection'],
            $this->settings_page
        );

        add_settings_section(
            'minpaku_official_advanced',
            __('Advanced Settings', 'minpaku-suite'),
            [$this, 'renderAdvancedSection'],
            $this->settings_page
        );

        add_settings_field(
            'minpaku_official_enabled',
            __('Enable Official Site', 'minpaku-suite'),
            [$this, 'renderEnabledField'],
            $this->settings_page,
            'minpaku_official_general'
        );

        add_settings_field(
            'minpaku_official_auto_generate',
            __('Auto-Generate Pages', 'minpaku-suite'),
            [$this, 'renderAutoGenerateField'],
            $this->settings_page,
            'minpaku_official_general'
        );

        add_settings_field(
            'minpaku_official_url_structure',
            __('URL Structure', 'minpaku-suite'),
            [$this, 'renderUrlStructureField'],
            $this->settings_page,
            'minpaku_official_general'
        );

        add_settings_field(
            'minpaku_official_default_sections',
            __('Default Sections', 'minpaku-suite'),
            [$this, 'renderDefaultSectionsField'],
            $this->settings_page,
            'minpaku_official_template'
        );

        add_settings_field(
            'minpaku_official_seo_enabled',
            __('SEO Enhancement', 'minpaku-suite'),
            [$this, 'renderSeoField'],
            $this->settings_page,
            'minpaku_official_advanced'
        );

        add_settings_field(
            'minpaku_official_analytics_code',
            __('Analytics Code', 'minpaku-suite'),
            [$this, 'renderAnalyticsField'],
            $this->settings_page,
            'minpaku_official_advanced'
        );

        add_settings_field(
            'minpaku_official_custom_css',
            __('Custom CSS', 'minpaku-suite'),
            [$this, 'renderCustomCssField'],
            $this->settings_page,
            'minpaku_official_advanced'
        );
    }

    public function renderSettingsPage() {
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'minpaku_official_messages',
                'minpaku_official_message',
                __('Settings saved successfully.', 'minpaku-suite'),
                'updated'
            );

            // Flush rewrite rules when URL structure changes
            if (isset($_POST['minpaku_official_url_structure'])) {
                OfficialRewrite::flushRewriteRules();
                add_settings_error(
                    'minpaku_official_messages',
                    'minpaku_official_rewrite_flushed',
                    __('URL rewrite rules have been refreshed.', 'minpaku-suite'),
                    'updated'
                );
            }
        }

        $stats = $this->getOfficialPageStats();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('minpaku_official_messages'); ?>

            <!-- Stats Dashboard -->
            <div class="official-stats-dashboard">
                <div class="stats-cards">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['total_properties']; ?></div>
                        <div class="stats-label"><?php _e('Total Properties', 'minpaku-suite'); ?></div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['official_pages']; ?></div>
                        <div class="stats-label"><?php _e('Official Pages', 'minpaku-suite'); ?></div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['published_pages']; ?></div>
                        <div class="stats-label"><?php _e('Published Pages', 'minpaku-suite'); ?></div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['coverage_percentage']; ?>%</div>
                        <div class="stats-label"><?php _e('Coverage', 'minpaku-suite'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="official-quick-actions">
                <h2><?php _e('Quick Actions', 'minpaku-suite'); ?></h2>
                <div class="action-buttons">
                    <button type="button" class="button button-primary" id="bulkGeneratePages">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Generate All Missing Pages', 'minpaku-suite'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="flushRewriteRules">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('Flush Rewrite Rules', 'minpaku-suite'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="testTemplate">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Test Template Rendering', 'minpaku-suite'); ?>
                    </button>
                </div>
            </div>

            <!-- Settings Form -->
            <form method="post" action="options.php" class="official-settings-form">
                <?php
                settings_fields($this->settings_group);
                do_settings_sections($this->settings_page);
                submit_button();
                ?>
            </form>

            <!-- Recent Activity -->
            <div class="official-recent-activity">
                <h2><?php _e('Recent Activity', 'minpaku-suite'); ?></h2>
                <?php $this->renderRecentActivity(); ?>
            </div>
        </div>

        <style>
        .official-stats-dashboard {
            margin: 20px 0;
            background: white;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            border-radius: 4px;
            padding: 20px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stats-card {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .official-quick-actions {
            margin: 30px 0;
            background: white;
            border: 1px solid #ccd0d4;
            padding: 20px;
            border-radius: 4px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-buttons .button {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .official-settings-form {
            background: white;
            border: 1px solid #ccd0d4;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
        }

        .form-table th {
            width: 200px;
        }

        .section-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .section-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }

        .section-item input[type="checkbox"] {
            margin: 0;
        }

        .section-item input[type="number"] {
            width: 60px;
            margin-left: auto;
        }

        .custom-css-editor {
            width: 100%;
            height: 200px;
            font-family: Consolas, Monaco, 'Courier New', monospace;
            font-size: 13px;
        }

        .official-recent-activity {
            background: white;
            border: 1px solid #ccd0d4;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
        }

        .activity-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-description {
            flex: 1;
        }

        .activity-time {
            color: #666;
            font-size: 0.9rem;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007cba;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            const $loadingOverlay = $('<div class="loading-overlay"><div class="loading-content"><div class="loading-spinner"></div><div class="loading-text"></div></div></div>');
            $('body').append($loadingOverlay);

            function showLoading(text) {
                $loadingOverlay.find('.loading-text').text(text);
                $loadingOverlay.addClass('active');
            }

            function hideLoading() {
                $loadingOverlay.removeClass('active');
            }

            function showNotice(message, type = 'success') {
                const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
                const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
                $('.wrap h1').after($notice);

                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 5000);
            }

            $('#bulkGeneratePages').on('click', function() {
                if (!confirm('<?php _e("This will generate official pages for all properties that don\'t have them. Continue?", "minpaku-suite"); ?>')) {
                    return;
                }

                showLoading('<?php _e("Generating official pages...", "minpaku-suite"); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bulk_generate_official_pages',
                        nonce: '<?php echo wp_create_nonce("bulk_generate_official_pages"); ?>'
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            showNotice(response.data.message);
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showNotice(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        showNotice('<?php _e("An error occurred. Please try again.", "minpaku-suite"); ?>', 'error');
                    }
                });
            });

            $('#flushRewriteRules').on('click', function() {
                showLoading('<?php _e("Flushing rewrite rules...", "minpaku-suite"); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'flush_official_rewrite_rules',
                        nonce: '<?php echo wp_create_nonce("flush_official_rewrite_rules"); ?>'
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            showNotice(response.data.message);
                        } else {
                            showNotice(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        showNotice('<?php _e("An error occurred. Please try again.", "minpaku-suite"); ?>', 'error');
                    }
                });
            });

            $('#testTemplate').on('click', function() {
                showLoading('<?php _e("Testing template rendering...", "minpaku-suite"); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_official_template',
                        nonce: '<?php echo wp_create_nonce("test_official_template"); ?>'
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            showNotice(response.data.message);
                        } else {
                            showNotice(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        showNotice('<?php _e("An error occurred. Please try again.", "minpaku-suite"); ?>', 'error');
                    }
                });
            });

            // Section ordering
            $('.section-controls').sortable({
                update: function() {
                    $(this).find('.section-item').each(function(index) {
                        $(this).find('input[type="number"]').val((index + 1) * 10);
                    });
                }
            });
        });
        </script>
        <?php
    }

    public function renderGeneralSection() {
        echo '<p>' . __('Configure basic settings for the official site feature.', 'minpaku-suite') . '</p>';
    }

    public function renderTemplateSection() {
        echo '<p>' . __('Customize the default template sections and their order.', 'minpaku-suite') . '</p>';
    }

    public function renderAdvancedSection() {
        echo '<p>' . __('Advanced configuration options for power users.', 'minpaku-suite') . '</p>';
    }

    public function renderEnabledField() {
        $value = get_option('minpaku_official_enabled', true);
        ?>
        <label>
            <input type="checkbox" name="minpaku_official_enabled" value="1" <?php checked($value, true); ?>>
            <?php _e('Enable official site pages for properties', 'minpaku-suite'); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, properties can have automatically generated official pages with custom URLs.', 'minpaku-suite'); ?>
        </p>
        <?php
    }

    public function renderAutoGenerateField() {
        $value = get_option('minpaku_official_auto_generate', true);
        ?>
        <label>
            <input type="checkbox" name="minpaku_official_auto_generate" value="1" <?php checked($value, true); ?>>
            <?php _e('Automatically generate official pages for new properties', 'minpaku-suite'); ?>
        </label>
        <p class="description">
            <?php _e('New properties will automatically have official pages created when published.', 'minpaku-suite'); ?>
        </p>
        <?php
    }

    public function renderUrlStructureField() {
        $value = get_option('minpaku_official_url_structure', 'stay');
        ?>
        <input type="text" name="minpaku_official_url_structure" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">
            <?php _e('URL prefix for official pages. Examples: "stay", "property", "accommodation"', 'minpaku-suite'); ?><br>
            <?php printf(__('URLs will be: %s', 'minpaku-suite'), '<code>' . home_url('/' . $value . '/property-name/') . '</code>'); ?>
        </p>
        <?php
    }

    public function renderDefaultSectionsField() {
        $sections = get_option('minpaku_official_default_sections', ['hero', 'gallery', 'features', 'calendar', 'quote', 'access']);

        $available_sections = [
            'hero' => __('Hero Section', 'minpaku-suite'),
            'gallery' => __('Gallery', 'minpaku-suite'),
            'features' => __('Features & Amenities', 'minpaku-suite'),
            'calendar' => __('Calendar', 'minpaku-suite'),
            'quote' => __('Quote & Booking', 'minpaku-suite'),
            'access' => __('Access & Location', 'minpaku-suite')
        ];

        ?>
        <div class="section-controls">
            <?php foreach ($available_sections as $key => $label): ?>
                <div class="section-item">
                    <input type="checkbox"
                           name="minpaku_official_default_sections[]"
                           value="<?php echo esc_attr($key); ?>"
                           <?php checked(in_array($key, $sections)); ?>>
                    <label><?php echo esc_html($label); ?></label>
                    <input type="number"
                           value="<?php echo (array_search($key, $sections) + 1) * 10; ?>"
                           min="1" max="999" step="1"
                           title="<?php _e('Order', 'minpaku-suite'); ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <p class="description">
            <?php _e('Select which sections to include by default and drag to reorder them.', 'minpaku-suite'); ?>
        </p>
        <?php
    }

    public function renderSeoField() {
        $value = get_option('minpaku_official_seo_enabled', true);
        ?>
        <label>
            <input type="checkbox" name="minpaku_official_seo_enabled" value="1" <?php checked($value, true); ?>>
            <?php _e('Enable SEO enhancements (structured data, meta tags)', 'minpaku-suite'); ?>
        </label>
        <p class="description">
            <?php _e('Adds structured data markup and optimized meta tags to official pages.', 'minpaku-suite'); ?>
        </p>
        <?php
    }

    public function renderAnalyticsField() {
        $value = get_option('minpaku_official_analytics_code', '');
        ?>
        <input type="text" name="minpaku_official_analytics_code" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">
            <?php _e('Google Analytics tracking ID (e.g., GA-XXXXXXXXX-X) for official pages.', 'minpaku-suite'); ?>
        </p>
        <?php
    }

    public function renderCustomCssField() {
        $value = get_option('minpaku_official_custom_css', '');
        ?>
        <textarea name="minpaku_official_custom_css" class="custom-css-editor"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php _e('Custom CSS that will be applied to all official pages.', 'minpaku-suite'); ?>
        </p>
        <?php
    }

    public function sanitizeUrlStructure($value) {
        $value = sanitize_title($value);
        return empty($value) ? 'stay' : $value;
    }

    public function sanitizeSectionArray($value) {
        if (!is_array($value)) {
            return ['hero', 'gallery', 'features', 'calendar', 'quote', 'access'];
        }

        $allowed_sections = ['hero', 'gallery', 'features', 'calendar', 'quote', 'access'];
        return array_intersect($value, $allowed_sections);
    }

    public function sanitizeCSS($value) {
        return wp_strip_all_tags($value);
    }

    private function getOfficialPageStats() {
        global $wpdb;

        $total_properties = wp_count_posts('minpaku_property')->publish;

        $official_pages = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p1.ID)
            FROM {$wpdb->posts} p1
            INNER JOIN {$wpdb->postmeta} pm ON p1.ID = pm.post_id
            WHERE p1.post_type = %s
            AND pm.meta_key = %s
            AND pm.meta_value != ''
        ", 'minpaku_property', '_minpaku_official_page_id'));

        $published_pages = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p1.ID)
            FROM {$wpdb->posts} p1
            INNER JOIN {$wpdb->postmeta} pm ON p1.ID = pm.post_id
            INNER JOIN {$wpdb->posts} p2 ON pm.meta_value = p2.ID
            WHERE p1.post_type = %s
            AND pm.meta_key = %s
            AND pm.meta_value != ''
            AND p2.post_status = %s
        ", 'minpaku_property', '_minpaku_official_page_id', 'publish'));

        $coverage_percentage = $total_properties > 0 ? round(($official_pages / $total_properties) * 100) : 0;

        return [
            'total_properties' => intval($total_properties),
            'official_pages' => intval($official_pages),
            'published_pages' => intval($published_pages),
            'coverage_percentage' => $coverage_percentage
        ];
    }

    private function renderRecentActivity() {
        global $wpdb;

        $activities = $wpdb->get_results($wpdb->prepare("
            SELECT p.post_title, p.post_modified, pm.meta_value as official_page_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND pm.meta_key = %s
            AND pm.meta_value != ''
            ORDER BY p.post_modified DESC
            LIMIT 10
        ", 'minpaku_property', '_minpaku_official_page_id'));

        if (empty($activities)) {
            echo '<p>' . __('No recent activity.', 'minpaku-suite') . '</p>';
            return;
        }

        echo '<div class="activity-list">';
        foreach ($activities as $activity) {
            $official_page = get_post($activity->official_page_id);
            $status = $official_page ? $official_page->post_status : 'deleted';

            echo '<div class="activity-item">';
            echo '<div class="activity-description">';
            echo sprintf(
                __('Official page for "%s" (%s)', 'minpaku-suite'),
                esc_html($activity->post_title),
                esc_html($status)
            );
            echo '</div>';
            echo '<div class="activity-time">';
            echo human_time_diff(strtotime($activity->post_modified), current_time('timestamp')) . ' ' . __('ago', 'minpaku-suite');
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    public function handleBulkGenerate() {
        check_ajax_referer('bulk_generate_official_pages', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'minpaku-suite')]);
        }

        $properties = get_posts([
            'post_type' => 'minpaku_property',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => '_minpaku_official_page_id',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);

        if (empty($properties)) {
            wp_send_json_success(['message' => __('All properties already have official pages.', 'minpaku-suite')]);
        }

        $generator = new OfficialSiteGenerator();
        $generated = 0;
        $errors = [];

        foreach ($properties as $property) {
            try {
                $result = $generator->generate($property->ID);
                if ($result) {
                    $generated++;
                } else {
                    $errors[] = $property->post_title;
                }
            } catch (Exception $e) {
                $errors[] = $property->post_title . ': ' . $e->getMessage();
            }
        }

        $message = sprintf(
            _n(
                'Generated %d official page successfully.',
                'Generated %d official pages successfully.',
                $generated,
                'minpaku-suite'
            ),
            $generated
        );

        if (!empty($errors)) {
            $message .= ' ' . sprintf(__('Errors: %s', 'minpaku-suite'), implode(', ', $errors));
        }

        wp_send_json_success(['message' => $message, 'generated' => $generated, 'errors' => count($errors)]);
    }

    public function handleFlushRewriteRules() {
        check_ajax_referer('flush_official_rewrite_rules', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'minpaku-suite')]);
        }

        OfficialRewrite::flushRewriteRules();

        wp_send_json_success(['message' => __('Rewrite rules flushed successfully.', 'minpaku-suite')]);
    }

    public function handleTemplateTest() {
        check_ajax_referer('test_official_template', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'minpaku-suite')]);
        }

        // Find a property to test with
        $property = get_posts([
            'post_type' => 'minpaku_property',
            'post_status' => 'publish',
            'numberposts' => 1
        ]);

        if (empty($property)) {
            wp_send_json_error(['message' => __('No properties found for testing.', 'minpaku-suite')]);
        }

        try {
            $generator = new OfficialSiteGenerator();
            $test_result = $generator->generate($property[0]->ID);

            if ($test_result) {
                wp_send_json_success(['message' => __('Template test completed successfully.', 'minpaku-suite')]);
            } else {
                wp_send_json_error(['message' => __('Template test failed.', 'minpaku-suite')]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Template test error: ', 'minpaku-suite') . $e->getMessage()]);
        }
    }
}