<?php

namespace Minpaku\Official;

class OfficialMetaBox {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post', [$this, 'saveMetaBox']);
        add_action('wp_ajax_toggle_official_page', [$this, 'handleToggleOfficialPage']);
        add_action('wp_ajax_generate_official_page', [$this, 'handleGenerateOfficialPage']);
        add_action('wp_ajax_preview_official_page', [$this, 'handlePreviewOfficialPage']);
    }

    public function addMetaBox() {
        add_meta_box(
            'minpaku_official_page',
            __('Official Site Page', 'minpaku-suite'),
            [$this, 'renderMetaBox'],
            'minpaku_property',
            'side',
            'high'
        );
    }

    public function renderMetaBox($post) {
        wp_nonce_field('minpaku_official_meta', 'minpaku_official_nonce');

        $generator = new OfficialSiteGenerator();
        $official_page_id = $generator->getOfficialPageId($post->ID);
        $has_official_page = !empty($official_page_id);

        $auto_generate = get_post_meta($post->ID, '_minpaku_auto_generate_official', true);
        $is_published = false;
        $page_url = '';

        if ($has_official_page) {
            $official_page = get_post($official_page_id);
            $is_published = $official_page && $official_page->post_status === 'publish';
            if ($is_published) {
                $page_url = get_permalink($official_page_id);
            }
        }

        ?>
        <div class="minpaku-official-meta">
            <div class="official-status">
                <?php if ($has_official_page): ?>
                    <div class="status-indicator status-<?php echo $is_published ? 'published' : 'draft'; ?>">
                        <span class="dashicons dashicons-<?php echo $is_published ? 'visibility' : 'hidden'; ?>"></span>
                        <?php echo $is_published ? __('Published', 'minpaku-suite') : __('Draft', 'minpaku-suite'); ?>
                    </div>

                    <?php if ($is_published): ?>
                        <div class="page-link">
                            <a href="<?php echo esc_url($page_url); ?>" target="_blank" class="button button-secondary">
                                <span class="dashicons dashicons-external"></span>
                                <?php _e('View Page', 'minpaku-suite'); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="page-actions">
                        <button type="button" class="button button-secondary" id="regenerate-official-page"
                                data-property-id="<?php echo $post->ID; ?>">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Regenerate', 'minpaku-suite'); ?>
                        </button>

                        <button type="button" class="button button-secondary" id="preview-official-page"
                                data-property-id="<?php echo $post->ID; ?>">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php _e('Preview', 'minpaku-suite'); ?>
                        </button>

                        <button type="button" class="button <?php echo $is_published ? 'button-secondary' : 'button-primary'; ?>"
                                id="toggle-official-page" data-property-id="<?php echo $post->ID; ?>"
                                data-current-status="<?php echo $is_published ? 'published' : 'draft'; ?>">
                            <span class="dashicons dashicons-<?php echo $is_published ? 'hidden' : 'visibility'; ?>"></span>
                            <?php echo $is_published ? __('Unpublish', 'minpaku-suite') : __('Publish', 'minpaku-suite'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="status-indicator status-none">
                        <span class="dashicons dashicons-minus"></span>
                        <?php _e('No Official Page', 'minpaku-suite'); ?>
                    </div>

                    <div class="page-actions">
                        <button type="button" class="button button-primary" id="generate-official-page"
                                data-property-id="<?php echo $post->ID; ?>">
                            <span class="dashicons dashicons-plus"></span>
                            <?php _e('Generate Page', 'minpaku-suite'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="auto-generate-setting">
                <label>
                    <input type="checkbox" name="minpaku_auto_generate_official" value="1"
                           <?php checked($auto_generate, '1'); ?>>
                    <?php _e('Auto-generate page on property updates', 'minpaku-suite'); ?>
                </label>
                <p class="description">
                    <?php _e('Automatically update the official page when this property is modified.', 'minpaku-suite'); ?>
                </p>
            </div>

            <div class="official-sections">
                <h4><?php _e('Template Sections', 'minpaku-suite'); ?></h4>
                <?php $this->renderSectionControls($post->ID); ?>
            </div>
        </div>

        <style>
        .minpaku-official-meta {
            font-size: 13px;
        }

        .official-status {
            margin-bottom: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .status-published {
            color: #46b450;
        }

        .status-draft {
            color: #ffb900;
        }

        .status-none {
            color: #646970;
        }

        .page-link {
            margin-bottom: 10px;
        }

        .page-actions {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .page-actions .button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 12px;
            height: auto;
            padding: 6px 12px;
        }

        .auto-generate-setting {
            margin: 15px 0;
            padding: 10px;
            background: #f0f0f1;
            border-radius: 4px;
        }

        .auto-generate-setting label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .auto-generate-setting .description {
            margin: 5px 0 0 0;
            font-style: italic;
            color: #646970;
        }

        .official-sections h4 {
            margin: 15px 0 10px 0;
            font-size: 13px;
        }

        .section-control {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .section-control label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .section-order {
            width: 40px;
            padding: 2px 4px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 2px;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
        }

        .spinner {
            visibility: visible;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            const $metaBox = $('.minpaku-official-meta');

            function showLoading() {
                $metaBox.css('position', 'relative');
                $metaBox.append('<div class="loading-overlay"><span class="spinner is-active"></span></div>');
            }

            function hideLoading() {
                $metaBox.find('.loading-overlay').remove();
            }

            function showMessage(message, type = 'success') {
                const messageClass = type === 'success' ? 'notice-success' : 'notice-error';
                const $notice = $('<div class="notice ' + messageClass + ' is-dismissible"><p>' + message + '</p></div>');
                $metaBox.prepend($notice);

                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 3000);
            }

            $('#generate-official-page, #regenerate-official-page').on('click', function() {
                const propertyId = $(this).data('property-id');
                const isRegenerate = $(this).attr('id') === 'regenerate-official-page';

                showLoading();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'generate_official_page',
                        property_id: propertyId,
                        regenerate: isRegenerate ? 1 : 0,
                        nonce: $('#minpaku_official_nonce').val()
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            showMessage(response.data.message);
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showMessage(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        showMessage('<?php _e("An error occurred. Please try again.", "minpaku-suite"); ?>', 'error');
                    }
                });
            });

            $('#toggle-official-page').on('click', function() {
                const propertyId = $(this).data('property-id');
                const currentStatus = $(this).data('current-status');

                showLoading();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'toggle_official_page',
                        property_id: propertyId,
                        nonce: $('#minpaku_official_nonce').val()
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            showMessage(response.data.message);
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showMessage(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        showMessage('<?php _e("An error occurred. Please try again.", "minpaku-suite"); ?>', 'error');
                    }
                });
            });

            $('#preview-official-page').on('click', function() {
                const propertyId = $(this).data('property-id');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'preview_official_page',
                        property_id: propertyId,
                        nonce: $('#minpaku_official_nonce').val()
                    },
                    success: function(response) {
                        if (response.success && response.data.preview_url) {
                            window.open(response.data.preview_url, '_blank');
                        } else {
                            showMessage(response.data.message || '<?php _e("Preview not available", "minpaku-suite"); ?>', 'error');
                        }
                    },
                    error: function() {
                        showMessage('<?php _e("An error occurred. Please try again.", "minpaku-suite"); ?>', 'error');
                    }
                });
            });
        });
        </script>
        <?php
    }

    private function renderSectionControls($property_id) {
        $template = new OfficialTemplate();
        $sections = $template->getSections($property_id);

        foreach ($sections as $section) {
            $section_key = 'minpaku_section_' . $section['type'];
            $enabled = isset($section['enabled']) ? $section['enabled'] : true;
            $order = isset($section['order']) ? $section['order'] : 10;

            ?>
            <div class="section-control">
                <label>
                    <input type="checkbox" name="<?php echo $section_key; ?>_enabled" value="1"
                           <?php checked($enabled, true); ?>>
                    <span><?php echo esc_html($this->getSectionLabel($section['type'])); ?></span>
                </label>
                <input type="number" name="<?php echo $section_key; ?>_order"
                       value="<?php echo esc_attr($order); ?>"
                       class="section-order" min="1" max="999" step="1">
            </div>
            <?php
        }
    }

    private function getSectionLabel($type) {
        $labels = [
            'hero' => __('Hero Section', 'minpaku-suite'),
            'gallery' => __('Gallery', 'minpaku-suite'),
            'features' => __('Features & Amenities', 'minpaku-suite'),
            'calendar' => __('Calendar', 'minpaku-suite'),
            'quote' => __('Quote & Booking', 'minpaku-suite'),
            'access' => __('Access & Location', 'minpaku-suite')
        ];

        return isset($labels[$type]) ? $labels[$type] : ucfirst($type);
    }

    public function saveMetaBox($post_id) {
        if (!isset($_POST['minpaku_official_nonce']) ||
            !wp_verify_nonce($_POST['minpaku_official_nonce'], 'minpaku_official_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'minpaku_property') {
            return;
        }

        $auto_generate = isset($_POST['minpaku_auto_generate_official']) ? '1' : '0';
        update_post_meta($post_id, '_minpaku_auto_generate_official', $auto_generate);

        $template = new OfficialTemplate();
        $sections = $template->getSections($post_id);

        foreach ($sections as $section) {
            $section_key = 'minpaku_section_' . $section['type'];

            $enabled = isset($_POST[$section_key . '_enabled']) ? '1' : '0';
            $order = isset($_POST[$section_key . '_order']) ? intval($_POST[$section_key . '_order']) : 10;

            update_post_meta($post_id, '_' . $section_key . '_enabled', $enabled);
            update_post_meta($post_id, '_' . $section_key . '_order', $order);
        }

        if ($auto_generate === '1') {
            $generator = new OfficialSiteGenerator();
            $generator->updateOnPropertyChange($post_id);
        }
    }

    public function handleGenerateOfficialPage() {
        check_ajax_referer('minpaku_official_meta', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions.', 'minpaku-suite'));
        }

        $property_id = intval($_POST['property_id']);
        $regenerate = isset($_POST['regenerate']) && $_POST['regenerate'] === '1';

        if (!$property_id || get_post_type($property_id) !== 'minpaku_property') {
            wp_send_json_error(['message' => __('Invalid property ID.', 'minpaku-suite')]);
        }

        $generator = new OfficialSiteGenerator();

        try {
            if ($regenerate) {
                $result = $generator->updateOnPropertyChange($property_id);
                $message = __('Official page regenerated successfully.', 'minpaku-suite');
            } else {
                $result = $generator->generate($property_id);
                $message = __('Official page generated successfully.', 'minpaku-suite');
            }

            if ($result) {
                wp_send_json_success(['message' => $message, 'page_id' => $result]);
            } else {
                wp_send_json_error(['message' => __('Failed to generate official page.', 'minpaku-suite')]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handleToggleOfficialPage() {
        check_ajax_referer('minpaku_official_meta', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions.', 'minpaku-suite'));
        }

        $property_id = intval($_POST['property_id']);

        if (!$property_id || get_post_type($property_id) !== 'minpaku_property') {
            wp_send_json_error(['message' => __('Invalid property ID.', 'minpaku-suite')]);
        }

        $generator = new OfficialSiteGenerator();

        try {
            $result = $generator->togglePublishStatus($property_id);

            if ($result) {
                $official_page_id = $generator->getOfficialPageId($property_id);
                $page = get_post($official_page_id);
                $is_published = $page && $page->post_status === 'publish';

                $message = $is_published
                    ? __('Official page published successfully.', 'minpaku-suite')
                    : __('Official page unpublished successfully.', 'minpaku-suite');

                wp_send_json_success(['message' => $message, 'status' => $page->post_status]);
            } else {
                wp_send_json_error(['message' => __('Failed to toggle page status.', 'minpaku-suite')]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handlePreviewOfficialPage() {
        check_ajax_referer('minpaku_official_meta', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions.', 'minpaku-suite'));
        }

        $property_id = intval($_POST['property_id']);

        if (!$property_id || get_post_type($property_id) !== 'minpaku_property') {
            wp_send_json_error(['message' => __('Invalid property ID.', 'minpaku-suite')]);
        }

        $generator = new OfficialSiteGenerator();
        $official_page_id = $generator->getOfficialPageId($property_id);

        if ($official_page_id) {
            $preview_url = add_query_arg('preview', 'true', get_permalink($official_page_id));
            wp_send_json_success(['preview_url' => $preview_url]);
        } else {
            wp_send_json_error(['message' => __('No official page found for preview.', 'minpaku-suite')]);
        }
    }
}