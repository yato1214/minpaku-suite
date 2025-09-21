<?php
/**
 * Connector Settings Template
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if (isset($_POST['submit']) && check_admin_referer('mcs_connector_settings')) {
    $enabled = isset($_POST['mcs_connector_enabled']);
    $domains = array_filter(array_map('trim', explode("\n", $_POST['mcs_connector_allowed_domains'] ?? '')));

    update_option('mcs_connector_enabled', $enabled);
    update_option('mcs_connector_allowed_domains', MinpakuSuite\Connector\ConnectorSettings::sanitize_domains($domains));

    echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'minpaku-suite') . '</p></div>';

    // Refresh data
    $is_enabled = MinpakuSuite\Connector\ConnectorSettings::is_enabled();
    $allowed_domains = MinpakuSuite\Connector\ConnectorSettings::get_allowed_domains();
    $api_keys = MinpakuSuite\Connector\ConnectorSettings::get_api_keys();
}

if (isset($_POST['delete_key']) && check_admin_referer('mcs_connector_settings')) {
    $site_id = sanitize_text_field($_POST['site_id']);
    if (MinpakuSuite\Connector\ConnectorSettings::delete_api_keys($site_id)) {
        echo '<div class="notice notice-success"><p>' . esc_html__('API keys deleted successfully.', 'minpaku-suite') . '</p></div>';
        $api_keys = MinpakuSuite\Connector\ConnectorSettings::get_api_keys();
    }
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('Connector Settings', 'minpaku-suite'); ?></h1>

    <p><?php echo esc_html__('Configure external WordPress connector for embedding property listings and availability calendars.', 'minpaku-suite'); ?></p>

    <form method="post" action="">
        <?php wp_nonce_field('mcs_connector_settings'); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php echo esc_html__('Enable Connector', 'minpaku-suite'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="mcs_connector_enabled" value="1" <?php checked($is_enabled); ?>>
                        <?php echo esc_html__('Enable external WordPress connector API', 'minpaku-suite'); ?>
                    </label>
                    <p class="description"><?php echo esc_html__('When enabled, external WordPress sites can connect to display your properties.', 'minpaku-suite'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php echo esc_html__('Allowed Domains', 'minpaku-suite'); ?></th>
                <td>
                    <textarea name="mcs_connector_allowed_domains" rows="5" cols="50" class="large-text"><?php echo esc_textarea(implode("\n", $allowed_domains)); ?></textarea>
                    <p class="description">
                        <?php echo esc_html__('Enter one domain per line (e.g., example.com). Only these domains can access the connector API.', 'minpaku-suite'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <hr>

    <h2><?php echo esc_html__('API Keys', 'minpaku-suite'); ?></h2>

    <p><?php echo esc_html__('Generate API keys for external WordPress sites. Each site needs its own API key and secret.', 'minpaku-suite'); ?></p>

    <div id="mcs-connector-keys">
        <?php if (empty($api_keys)): ?>
            <p><?php echo esc_html__('No API keys generated yet.', 'minpaku-suite'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Site Name', 'minpaku-suite'); ?></th>
                        <th><?php echo esc_html__('Site ID', 'minpaku-suite'); ?></th>
                        <th><?php echo esc_html__('API Key', 'minpaku-suite'); ?></th>
                        <th><?php echo esc_html__('Created', 'minpaku-suite'); ?></th>
                        <th><?php echo esc_html__('Last Used', 'minpaku-suite'); ?></th>
                        <th><?php echo esc_html__('Actions', 'minpaku-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($api_keys as $site_id => $data): ?>
                        <tr>
                            <td><strong><?php echo esc_html($data['site_name']); ?></strong></td>
                            <td><code><?php echo esc_html($data['site_id']); ?></code></td>
                            <td><code><?php echo esc_html(substr($data['api_key'], 0, 20) . '...'); ?></code></td>
                            <td><?php echo esc_html($data['created_at'] ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($data['created_at'])) : '—'); ?></td>
                            <td><?php echo esc_html($data['last_used'] ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($data['last_used'])) : __('Never', 'minpaku-suite')); ?></td>
                            <td>
                                <button type="button" class="button" onclick="showKeyDetails('<?php echo esc_js($site_id); ?>')"><?php echo esc_html__('Show Details', 'minpaku-suite'); ?></button>
                                <button type="button" class="button" onclick="rotateKeys('<?php echo esc_js($site_id); ?>')"><?php echo esc_html__('Rotate', 'minpaku-suite'); ?></button>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('mcs_connector_settings'); ?>
                                    <input type="hidden" name="site_id" value="<?php echo esc_attr($site_id); ?>">
                                    <button type="submit" name="delete_key" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete these API keys?', 'minpaku-suite')); ?>')"><?php echo esc_html__('Delete', 'minpaku-suite'); ?></button>
                                </form>
                            </td>
                        </tr>

                        <!-- Hidden details row -->
                        <tr id="details-<?php echo esc_attr($site_id); ?>" style="display: none;">
                            <td colspan="6">
                                <div style="background: #f0f0f0; padding: 15px; margin: 10px 0;">
                                    <h4><?php echo esc_html__('Connection Details for:', 'minpaku-suite'); ?> <?php echo esc_html($data['site_name']); ?></h4>
                                    <table class="form-table">
                                        <tr>
                                            <th><?php echo esc_html__('Portal Base URL:', 'minpaku-suite'); ?></th>
                                            <td><code><?php echo esc_html(home_url()); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th><?php echo esc_html__('Site ID:', 'minpaku-suite'); ?></th>
                                            <td><code><?php echo esc_html($data['site_id']); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th><?php echo esc_html__('API Key:', 'minpaku-suite'); ?></th>
                                            <td><code><?php echo esc_html($data['api_key']); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th><?php echo esc_html__('Secret:', 'minpaku-suite'); ?></th>
                                            <td><code><?php echo esc_html($data['secret']); ?></code></td>
                                        </tr>
                                    </table>
                                    <p><strong><?php echo esc_html__('Note:', 'minpaku-suite'); ?></strong> <?php echo esc_html__('Copy these values to your external WordPress connector plugin settings.', 'minpaku-suite'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <p>
        <input type="text" id="new-site-name" placeholder="<?php echo esc_attr__('Site name (optional)', 'minpaku-suite'); ?>" style="width: 300px;">
        <button type="button" id="generate-keys" class="button button-secondary"><?php echo esc_html__('Generate New API Keys', 'minpaku-suite'); ?></button>
    </p>

    <hr>

    <h2><?php echo esc_html__('API Endpoints', 'minpaku-suite'); ?></h2>

    <p><?php echo esc_html__('The following endpoints are available for external WordPress connectors:', 'minpaku-suite'); ?></p>

    <table class="wp-list-table widefat">
        <thead>
            <tr>
                <th><?php echo esc_html__('Endpoint', 'minpaku-suite'); ?></th>
                <th><?php echo esc_html__('Method', 'minpaku-suite'); ?></th>
                <th><?php echo esc_html__('Description', 'minpaku-suite'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code><?php echo esc_html(rest_url('minpaku/v1/connector/verify')); ?></code></td>
                <td><code>GET</code></td>
                <td><?php echo esc_html__('Verify connection and test authentication', 'minpaku-suite'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('minpaku/v1/connector/properties')); ?></code></td>
                <td><code>GET</code></td>
                <td><?php echo esc_html__('Get list of properties', 'minpaku-suite'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('minpaku/v1/connector/availability')); ?></code></td>
                <td><code>GET</code></td>
                <td><?php echo esc_html__('Get availability calendar for a property', 'minpaku-suite'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('minpaku/v1/connector/quote')); ?></code></td>
                <td><code>POST</code></td>
                <td><?php echo esc_html__('Generate price quote for a booking', 'minpaku-suite'); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    const nonce = '<?php echo wp_create_nonce('mcs_connector_nonce'); ?>';

    $('#generate-keys').on('click', function() {
        const siteName = $('#new-site-name').val();
        const button = $(this);

        button.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'minpaku-suite')); ?>');

        $.post(ajaxurl, {
            action: 'mcs_connector_generate_keys',
            site_name: siteName,
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || '<?php echo esc_js(__('Error generating keys.', 'minpaku-suite')); ?>');
            }
        })
        .fail(function() {
            alert('<?php echo esc_js(__('Error generating keys.', 'minpaku-suite')); ?>');
        })
        .always(function() {
            button.prop('disabled', false).text('<?php echo esc_js(__('Generate New API Keys', 'minpaku-suite')); ?>');
        });
    });

    window.showKeyDetails = function(siteId) {
        $('#details-' + siteId).toggle();
    };

    window.rotateKeys = function(siteId) {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to rotate these API keys? The old keys will stop working immediately.', 'minpaku-suite')); ?>')) {
            return;
        }

        $.post(ajaxurl, {
            action: 'mcs_connector_rotate_keys',
            site_id: siteId,
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || '<?php echo esc_js(__('Error rotating keys.', 'minpaku-suite')); ?>');
            }
        })
        .fail(function() {
            alert('<?php echo esc_js(__('Error rotating keys.', 'minpaku-suite')); ?>');
        });
    };
});
</script>

<style>
.wp-list-table th,
.wp-list-table td {
    padding: 8px 10px;
}

.wp-list-table code {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}

#mcs-connector-keys .button {
    margin-right: 5px;
}

.form-table th {
    width: 200px;
}
</style>