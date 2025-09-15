<?php
/**
 * Minpaku Suite Admin UI
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin management UI for Minpaku Suite
 */
class MCS_Admin {

    /**
     * Initialize the admin class
     */
    public static function init() {
        if (!is_admin()) {
            return;
        }

        $instance = new self();
        add_action('admin_menu', [$instance, 'add_admin_menu']);
        add_action('admin_init', [$instance, 'register_settings']);
        add_action('admin_notices', [$instance, 'show_notices']);
        add_action('admin_post_mcs_regen_mappings', [$instance, 'handle_regen_mappings']);
        add_action('admin_post_mcs_sync_now', [$instance, 'handle_sync_now']);
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Minpaku Suite', 'minpaku-suite'),
            __('Minpaku Suite', 'minpaku-suite'),
            'manage_options',
            'mcs-settings',
            [$this, 'render_settings_page'],
            'dashicons-calendar-alt',
            30
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'mcs_settings_group',
            'mcs_settings',
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->get_default_settings(),
            ]
        );

        add_settings_section(
            'mcs_general_section',
            __('General Settings', 'minpaku-suite'),
            [$this, 'render_general_section'],
            'mcs-settings'
        );

        add_settings_field(
            'export_disposition',
            __('Export Disposition', 'minpaku-suite'),
            [$this, 'render_export_disposition_field'],
            'mcs-settings',
            'mcs_general_section'
        );

        add_settings_field(
            'flush_on_save',
            __('Flush on Save', 'minpaku-suite'),
            [$this, 'render_flush_on_save_field'],
            'mcs-settings',
            'mcs_general_section'
        );

        add_settings_section(
            'mcs_alerts_section',
            __('Alert Settings', 'minpaku-suite'),
            [$this, 'render_alerts_section'],
            'mcs-settings'
        );

        add_settings_field(
            'alerts_enabled',
            __('Enable Alerts', 'minpaku-suite'),
            [$this, 'render_alerts_enabled_field'],
            'mcs-settings',
            'mcs_alerts_section'
        );

        add_settings_field(
            'alerts_threshold',
            __('Alert Threshold', 'minpaku-suite'),
            [$this, 'render_alerts_threshold_field'],
            'mcs-settings',
            'mcs_alerts_section'
        );

        add_settings_field(
            'alerts_cooldown_hours',
            __('Cooldown Hours', 'minpaku-suite'),
            [$this, 'render_alerts_cooldown_field'],
            'mcs-settings',
            'mcs_alerts_section'
        );

        add_settings_field(
            'alerts_recipient',
            __('Alert Recipient', 'minpaku-suite'),
            [$this, 'render_alerts_recipient_field'],
            'mcs-settings',
            'mcs_alerts_section'
        );
    }

    /**
     * Get default settings
     *
     * @return array
     */
    private function get_default_settings() {
        return [
            'export_disposition' => 'inline',
            'flush_on_save' => false,
            'alerts' => [
                'enabled' => false,
                'threshold' => 1,
                'cooldown_hours' => 24,
                'recipient' => get_option('admin_email'),
            ],
            'mappings' => [],
        ];
    }

    /**
     * Sanitize settings
     *
     * @param array $input
     * @return array
     */
    public function sanitize_settings($input) {
        $sanitized = [];

        // Sanitize export_disposition
        $sanitized['export_disposition'] = in_array($input['export_disposition'], ['inline', 'attachment'])
            ? $input['export_disposition']
            : 'inline';

        // Sanitize flush_on_save
        $sanitized['flush_on_save'] = !empty($input['flush_on_save']);

        // Sanitize alerts
        $sanitized['alerts'] = [
            'enabled' => !empty($input['alerts']['enabled']),
            'threshold' => max(1, intval($input['alerts']['threshold'])),
            'cooldown_hours' => max(1, intval($input['alerts']['cooldown_hours'])),
            'recipient' => sanitize_email($input['alerts']['recipient']),
        ];

        // Preserve existing mappings
        $existing_settings = get_option('mcs_settings', []);
        $sanitized['mappings'] = isset($existing_settings['mappings']) ? $existing_settings['mappings'] : [];

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'minpaku-suite'));
        }

        $settings = wp_parse_args(get_option('mcs_settings', []), $this->get_default_settings());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__('Minpaku Suite Settings', 'minpaku-suite')); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('mcs_settings_group');
                do_settings_sections('mcs-settings');
                submit_button(__('Save Settings', 'minpaku-suite'));
                ?>
            </form>

            <hr>

            <h2><?php echo esc_html(__('Property Mappings', 'minpaku-suite')); ?></h2>
            <p><?php echo esc_html(__('Read-only list of current property mappings. Use the regenerate button to update.', 'minpaku-suite')); ?></p>

            <?php $this->render_mappings_table($settings['mappings']); ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mcs_regen_mappings', 'mcs_regen_nonce'); ?>
                <input type="hidden" name="action" value="mcs_regen_mappings">
                <?php submit_button(__('Regenerate Mappings', 'minpaku-suite'), 'secondary'); ?>
            </form>

            <hr>

            <h2><?php echo esc_html(__('Sync Management', 'minpaku-suite')); ?></h2>
            <p><?php echo esc_html(__('Manually trigger synchronization for all configured mappings.', 'minpaku-suite')); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mcs_sync_now', 'mcs_sync_nonce'); ?>
                <input type="hidden" name="action" value="mcs_sync_now">
                <?php submit_button(__('Sync Now', 'minpaku-suite'), 'primary'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render mappings table
     *
     * @param array $mappings
     */
    private function render_mappings_table($mappings) {
        if (empty($mappings)) {
            echo '<p>' . esc_html(__('No mappings found. Click "Regenerate Mappings" to create them.', 'minpaku-suite')) . '</p>';
            return;
        }
        ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html(__('Post ID', 'minpaku-suite')); ?></th>
                    <th><?php echo esc_html(__('Property Title', 'minpaku-suite')); ?></th>
                    <th><?php echo esc_html(__('ICS URL', 'minpaku-suite')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mappings as $mapping): ?>
                    <tr>
                        <td><?php echo esc_html($mapping['post_id']); ?></td>
                        <td>
                            <?php
                            $post_title = get_the_title($mapping['post_id']);
                            echo esc_html($post_title ?: __('(Untitled)', 'minpaku-suite'));
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($mapping['url']); ?>" target="_blank">
                                <?php echo esc_html($mapping['url']); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Handle mapping regeneration
     */
    public function handle_regen_mappings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'minpaku-suite'));
        }

        if (!wp_verify_nonce($_POST['mcs_regen_nonce'], 'mcs_regen_mappings')) {
            wp_die(__('Security check failed.', 'minpaku-suite'));
        }

        $properties = get_posts([
            'post_type' => 'property',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        $mappings = [];

        foreach ($properties as $post_id) {
            $ics_key = get_post_meta($post_id, '_ics_key', true);

            if (empty($ics_key)) {
                $ics_key = wp_generate_password(24, false, false);
                update_post_meta($post_id, '_ics_key', $ics_key);
            }

            $ics_url = home_url("ics/property/{$post_id}/{$ics_key}.ics");

            $mappings[] = [
                'url' => $ics_url,
                'post_id' => $post_id,
            ];
        }

        $settings = wp_parse_args(get_option('mcs_settings', []), $this->get_default_settings());
        $settings['mappings'] = $mappings;
        update_option('mcs_settings', $settings);

        wp_redirect(admin_url('admin.php?page=mcs-settings&mcs_notice=regen_ok'));
        exit;
    }

    /**
     * Handle sync now request
     */
    public function handle_sync_now() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'minpaku-suite'));
        }

        if (!wp_verify_nonce($_POST['mcs_sync_nonce'], 'mcs_sync_now')) {
            wp_die(__('Security check failed.', 'minpaku-suite'));
        }

        if (!class_exists('MCS_Sync')) {
            wp_die(__('MCS_Sync class not found. Core sync functionality may not be loaded.', 'minpaku-suite'));
        }

        try {
            $results = MCS_Sync::run_all_mappings();

            // Build query string with results
            $query_args = [
                'page' => 'mcs-settings',
                'mcs_notice' => 'sync_completed',
                'added' => $results['added'],
                'updated' => $results['updated'],
                'skipped' => $results['skipped'],
                'skipped_not_modified' => $results['skipped_not_modified'],
                'errors' => $results['errors']
            ];

            wp_redirect(admin_url('admin.php?' . http_build_query($query_args)));
            exit;

        } catch (Exception $e) {
            $query_args = [
                'page' => 'mcs-settings',
                'mcs_notice' => 'sync_error',
                'error_message' => urlencode($e->getMessage())
            ];

            wp_redirect(admin_url('admin.php?' . http_build_query($query_args)));
            exit;
        }
    }

    /**
     * Show admin notices
     */
    public function show_notices() {
        if (!isset($_GET['mcs_notice'])) {
            return;
        }

        $notice_type = sanitize_text_field($_GET['mcs_notice']);

        switch ($notice_type) {
            case 'regen_ok':
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(__('Mappings regenerated successfully!', 'minpaku-suite')); ?></p>
                </div>
                <?php
                break;

            case 'sync_completed':
                $added = isset($_GET['added']) ? absint($_GET['added']) : 0;
                $updated = isset($_GET['updated']) ? absint($_GET['updated']) : 0;
                $skipped = isset($_GET['skipped']) ? absint($_GET['skipped']) : 0;
                $skipped_not_modified = isset($_GET['skipped_not_modified']) ? absint($_GET['skipped_not_modified']) : 0;
                $errors = isset($_GET['errors']) ? absint($_GET['errors']) : 0;

                $total_processed = $added + $updated + $skipped + $skipped_not_modified;
                $notice_class = $errors > 0 ? 'notice-warning' : 'notice-success';

                ?>
                <div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible">
                    <p>
                        <strong><?php echo esc_html(__('Sync Completed!', 'minpaku-suite')); ?></strong><br>
                        <?php
                        printf(
                            esc_html(__('Results: %d added, %d updated, %d skipped, %d not modified, %d errors', 'minpaku-suite')),
                            $added,
                            $updated,
                            $skipped,
                            $skipped_not_modified,
                            $errors
                        );
                        ?>
                        <?php if ($total_processed > 0): ?>
                            <br><em><?php printf(esc_html(__('Total processed: %d mappings', 'minpaku-suite')), $total_processed); ?></em>
                        <?php endif; ?>
                    </p>
                </div>
                <?php
                break;

            case 'sync_error':
                $error_message = isset($_GET['error_message']) ? urldecode(sanitize_text_field($_GET['error_message'])) : __('Unknown error occurred', 'minpaku-suite');
                ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <strong><?php echo esc_html(__('Sync Failed!', 'minpaku-suite')); ?></strong><br>
                        <?php echo esc_html($error_message); ?>
                    </p>
                </div>
                <?php
                break;
        }
    }

    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . esc_html(__('Configure general plugin settings.', 'minpaku-suite')) . '</p>';
    }

    /**
     * Render alerts section description
     */
    public function render_alerts_section() {
        echo '<p>' . esc_html(__('Configure alert notifications.', 'minpaku-suite')) . '</p>';
    }

    /**
     * Render export disposition field
     */
    public function render_export_disposition_field() {
        $settings = wp_parse_args(get_option('mcs_settings', []), $this->get_default_settings());
        $value = $settings['export_disposition'];
        ?>
        <select name="mcs_settings[export_disposition]" id="export_disposition">
            <option value="inline" <?php selected($value, 'inline'); ?>>
                <?php echo esc_html(__('Inline', 'minpaku-suite')); ?>
            </option>
            <option value="attachment" <?php selected($value, 'attachment'); ?>>
                <?php echo esc_html(__('Attachment', 'minpaku-suite')); ?>
            </option>
        </select>
        <p class="description"><?php echo esc_html(__('How to display exported content.', 'minpaku-suite')); ?></p>
        <?php
    }

    /**
     * Render flush on save field
     */
    public function render_flush_on_save_field() {
        $settings = wp_parse_args(get_option('mcs_settings', []), $this->get_default_settings());
        $value = $settings['flush_on_save'];
        ?>
        <label for="flush_on_save">
            <input type="checkbox" name="mcs_settings[flush_on_save]" id="flush_on_save" value="1" <?php checked($value); ?>>
            <?php echo esc_html(__('Flush cache on save', 'minpaku-suite')); ?>
        </label>
        <p class="description"><?php echo esc_html(__('Automatically flush cache when saving posts.', 'minpaku-suite')); ?></p>
        <?php
    }

    /**
     * Render alerts enabled field
     */
    public function render_alerts_enabled_field() {
        $settings = wp_parse_args(get_option('mcs_settings', []), $this->get_default_settings());
        $value = $settings['alerts']['enabled'];
        ?>
        <label for="alerts_enabled">
            <input type="checkbox" name="mcs_settings[alerts][enabled]" id="alerts_enabled" value="1" <?php checked($value); ?>>
            <?php echo esc_html(__('Enable alert notifications', 'minpaku-suite')); ?>
        </label>
        <p class="description"><?php echo esc_html(__('Enable or disable alert notifications.', 'minpaku-suite')); ?></p>
        <?php
    }

    /**
     * Render alerts threshold field
     */
    public function render_alerts_threshold_field() {
        $settings = wp_parse_args(get_option('mcs_settings', []), $this->get_default_settings());
        $value = $settings['alerts']['threshold'];
        ?>
        <input type="number" name="mcs_settings[alerts][threshold]" id="alerts_threshold" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
        <p class="description"><?php echo esc_html(__('Threshold value for triggering alerts (minimum 1).', 'minpaku-suite')); ?></p>
        <?php
    }

    /**
     * Render alerts cooldown field
     */
    public function render_alerts_cooldown_field() {
        $settings = wp_parse_args(get_option('mcs_settings', []), $this->get_default_settings());
        $value = $settings['alerts']['cooldown_hours'];
        ?>
        <input type="number" name="mcs_settings[alerts][cooldown_hours]" id="alerts_cooldown_hours" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
        <p class="description"><?php echo esc_html(__('Hours to wait between alerts (minimum 1).', 'minpaku-suite')); ?></p>
        <?php
    }

    /**
     * Render alerts recipient field
     */
    public function render_alerts_recipient_field() {
        $settings = wp_parse_args(get_option('mcs_settings', []), $this->get_default_settings());
        $value = $settings['alerts']['recipient'];
        ?>
        <input type="email" name="mcs_settings[alerts][recipient]" id="alerts_recipient" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php echo esc_html(__('Email address to receive alert notifications.', 'minpaku-suite')); ?></p>
        <?php
    }
}