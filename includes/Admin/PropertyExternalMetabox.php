<?php
/**
 * Property External Integration Metabox
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyExternalMetabox {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post', [__CLASS__, 'save_metabox']);
        add_action('admin_notices', [__CLASS__, 'show_validation_errors']);
    }

    /**
     * Add external integration metabox to property post type
     */
    public static function add_metabox() {
        add_meta_box(
            'mcs-property-external',
            __('外部連携', 'minpaku-suite'),
            [__CLASS__, 'render_metabox'],
            'mcs_property',
            'normal',
            'high'
        );
    }

    /**
     * Render external integration metabox
     */
    public static function render_metabox($post) {
        wp_nonce_field('mcs_property_external_nonce', 'mcs_property_external_nonce');

        $external_detail_url = get_post_meta($post->ID, '_mcs_external_detail_url', true);
        $external_button_text = get_post_meta($post->ID, '_mcs_external_button_text', true);
        ?>
        <div id="mcs-property-external-container">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="external_detail_url"><?php echo esc_html__('外部物件詳細URL', 'minpaku-suite'); ?></label>
                        </th>
                        <td>
                            <input type="url"
                                   id="external_detail_url"
                                   name="mcs_external_detail_url"
                                   value="<?php echo esc_attr($external_detail_url); ?>"
                                   class="regular-text"
                                   placeholder="https://example.com/stays/○○" />
                            <p class="description">
                                <?php echo esc_html__('この物件の外部詳細ページのURLを入力してください。設定すると物件一覧カードに「外部サイトで見る」ボタンが表示されます。', 'minpaku-suite'); ?>
                            </p>
                            <p class="description">
                                <?php echo esc_html__('※ HTTP/HTTPSで始まるURLのみ有効です。', 'minpaku-suite'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="external_button_text"><?php echo esc_html__('外部リンクボタン文言', 'minpaku-suite'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="external_button_text"
                                   name="mcs_external_button_text"
                                   value="<?php echo esc_attr($external_button_text); ?>"
                                   class="regular-text"
                                   placeholder="外部サイトで見る" />
                            <p class="description">
                                <?php echo esc_html__('外部リンクボタンに表示する文言をカスタマイズできます。空白の場合は「外部サイトで見る」が表示されます。', 'minpaku-suite'); ?>
                            </p>
                            <p class="description">
                                <?php echo esc_html__('例: 「公式サイトで予約」「詳細をチェック」「より詳しく見る」など', 'minpaku-suite'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <style>
        #mcs-property-external-container .form-table th {
            width: 180px;
        }
        #mcs-property-external-container .form-table input[type="url"] {
            width: 100%;
            max-width: 500px;
        }
        </style>
        <?php
    }

    /**
     * Save metabox data
     */
    public static function save_metabox($post_id) {
        // Check nonce
        if (!isset($_POST['mcs_property_external_nonce']) ||
            !wp_verify_nonce($_POST['mcs_property_external_nonce'], 'mcs_property_external_nonce')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check post type
        if (get_post_type($post_id) !== 'mcs_property') {
            return;
        }

        // Save external detail URL
        if (isset($_POST['mcs_external_detail_url'])) {
            $external_url = trim($_POST['mcs_external_detail_url']);

            if (!empty($external_url)) {
                // Validate URL scheme
                if (!preg_match('/^https?:\/\//', $external_url)) {
                    // Store error in transient for display
                    set_transient('mcs_external_url_error_' . $post_id,
                        __('外部物件詳細URLが不正です（http/httpsのみ）。', 'minpaku-suite'),
                        60);
                    return;
                }

                // Additional URL validation
                if (!filter_var($external_url, FILTER_VALIDATE_URL)) {
                    set_transient('mcs_external_url_error_' . $post_id,
                        __('外部物件詳細URLが不正です（http/httpsのみ）。', 'minpaku-suite'),
                        60);
                    return;
                }

                // Sanitize and save
                $sanitized_url = esc_url_raw($external_url);
                update_post_meta($post_id, '_mcs_external_detail_url', $sanitized_url);
            } else {
                // Remove meta if empty
                delete_post_meta($post_id, '_mcs_external_detail_url');
            }
        }

        // Save external button text
        if (isset($_POST['mcs_external_button_text'])) {
            $button_text = trim($_POST['mcs_external_button_text']);

            if (!empty($button_text)) {
                // Sanitize button text
                $sanitized_text = sanitize_text_field($button_text);

                // Length validation (最大50文字)
                if (mb_strlen($sanitized_text) > 50) {
                    set_transient('mcs_external_button_text_error_' . $post_id,
                        __('外部リンクボタン文言は50文字以内で入力してください。', 'minpaku-suite'),
                        60);
                    return;
                }

                update_post_meta($post_id, '_mcs_external_button_text', $sanitized_text);
            } else {
                // Remove meta if empty - will use default text
                delete_post_meta($post_id, '_mcs_external_button_text');
            }
        }
    }

    /**
     * Show validation errors
     */
    public static function show_validation_errors() {
        global $post;

        if (!$post || get_post_type($post) !== 'mcs_property') {
            return;
        }

        $error_message = get_transient('mcs_external_url_error_' . $post->ID);
        if ($error_message) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($error_message); ?></p>
            </div>
            <?php
            // Delete the transient so it doesn't show again
            delete_transient('mcs_external_url_error_' . $post->ID);
        }

        $button_text_error = get_transient('mcs_external_button_text_error_' . $post->ID);
        if ($button_text_error) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($button_text_error); ?></p>
            </div>
            <?php
            // Delete the transient so it doesn't show again
            delete_transient('mcs_external_button_text_error_' . $post->ID);
        }
    }

    /**
     * Get external detail URL for a property
     */
    public static function get_external_detail_url($property_id) {
        $url = get_post_meta($property_id, '_mcs_external_detail_url', true);
        return !empty($url) ? esc_url($url) : '';
    }

    /**
     * Get external button text for a property
     */
    public static function get_external_button_text($property_id) {
        $text = get_post_meta($property_id, '_mcs_external_button_text', true);
        return !empty($text) ? sanitize_text_field($text) : __('外部サイトで見る', 'minpaku-suite');
    }
}