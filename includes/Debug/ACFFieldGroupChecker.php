<?php
/**
 * ACF Field Group Debug Checker
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Debug;

if (!defined('ABSPATH')) {
    exit;
}

class ACFFieldGroupChecker
{
    public static function init()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('admin_notices', [__CLASS__, 'show_acf_debug_info']);
            add_action('wp_ajax_check_acf_groups', [__CLASS__, 'ajax_check_acf_groups']);
        }
    }

    public static function show_acf_debug_info()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Only show on property edit screens
        $screen = get_current_screen();
        if (!$screen || ($screen->post_type !== 'mcs_property' && $screen->id !== 'edit-mcs_property')) {
            return;
        }

        echo '<div class="notice notice-info">';
        echo '<p><strong>ACF Debug Info:</strong></p>';
        echo '<ul>';

        // Check if ACF is active
        if (function_exists('acf_add_local_field_group')) {
            echo '<li>✓ ACF is active and acf_add_local_field_group function exists</li>';
        } else {
            echo '<li>✗ ACF not available - acf_add_local_field_group function missing</li>';
        }

        // Check if our field groups are registered
        if (function_exists('acf_get_field_groups')) {
            $groups = acf_get_field_groups();
            $our_groups = [];

            foreach ($groups as $group) {
                if (strpos($group['key'], 'group_mcs') !== false ||
                    strpos($group['key'], 'group_pricing') !== false ||
                    strpos($group['key'], 'group_simple_pricing') !== false) {
                    $our_groups[] = $group['title'] . ' (key: ' . $group['key'] . ')';
                }
            }

            if (!empty($our_groups)) {
                echo '<li>✓ Found ' . count($our_groups) . ' MinpakuSuite field groups:</li>';
                foreach ($our_groups as $group_info) {
                    echo '<li style="margin-left: 20px;">• ' . esc_html($group_info) . '</li>';
                }
            } else {
                echo '<li>✗ No MinpakuSuite field groups found</li>';
            }

            echo '<li>Total ACF field groups: ' . count($groups) . '</li>';
        } else {
            echo '<li>✗ acf_get_field_groups function not available</li>';
        }

        // Check post type
        if (isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
            $post_type = get_post_type($post_id);
            echo '<li>Current post type: ' . esc_html($post_type) . '</li>';
        } else {
            echo '<li>Current post type: ' . esc_html(get_post_type()) . ' (new post)</li>';
        }

        echo '</ul>';
        echo '<p><button type="button" onclick="checkACFGroups()" class="button">Refresh ACF Info</button></p>';
        echo '</div>';

        // Add JavaScript for refresh functionality
        echo '<script>
        function checkACFGroups() {
            jQuery.post(ajaxurl, {
                action: "check_acf_groups",
                nonce: "' . wp_create_nonce('check_acf_groups') . '"
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert("Error: " + response.data);
                }
            });
        }
        </script>';
    }

    public static function ajax_check_acf_groups()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'check_acf_groups')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Force re-registration of field groups
        do_action('acf/init');

        wp_send_json_success('ACF field groups refreshed');
    }
}