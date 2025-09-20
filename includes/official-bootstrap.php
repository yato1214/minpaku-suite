<?php

use Minpaku\Official\OfficialSiteGenerator;
use Minpaku\Official\OfficialTemplate;
use Minpaku\Official\OfficialMetaBox;
use Minpaku\Official\OfficialRewrite;
use Minpaku\Official\OfficialAssets;
use Minpaku\Official\OfficialShortcodes;
use Minpaku\Admin\OfficialSettings;

function minpaku_init_official_system() {
    if (!get_option('minpaku_official_enabled', true)) {
        return;
    }

    new OfficialSiteGenerator();
    new OfficialTemplate();
    new OfficialMetaBox();
    new OfficialRewrite();
    new OfficialAssets();
    new OfficialShortcodes();

    if (is_admin()) {
        new OfficialSettings();
    }

    add_action('init', 'minpaku_register_official_hooks');
    add_action('after_switch_theme', 'minpaku_flush_official_rewrite_rules');
    add_action('minpaku_property_saved', 'minpaku_handle_property_update');

    register_activation_hook(MINPAKU_PLUGIN_FILE, 'minpaku_official_activation');
    register_deactivation_hook(MINPAKU_PLUGIN_FILE, 'minpaku_official_deactivation');
}

function minpaku_register_official_hooks() {
    add_filter('minpaku_official_sections', 'minpaku_apply_default_sections', 10, 2);
    add_action('wp_head', 'minpaku_add_official_meta_tags');
    add_action('wp_footer', 'minpaku_add_official_analytics');
    add_action('wp_enqueue_scripts', 'minpaku_enqueue_official_custom_css');
}

function minpaku_apply_default_sections($sections, $property_id) {
    $default_sections = get_option('minpaku_official_default_sections', ['hero', 'gallery', 'features', 'calendar', 'quote', 'access']);

    $custom_sections = [];
    foreach ($default_sections as $index => $section_type) {
        $enabled_key = "_minpaku_section_{$section_type}_enabled";
        $order_key = "_minpaku_section_{$section_type}_order";

        $enabled = get_post_meta($property_id, $enabled_key, true);
        $order = get_post_meta($property_id, $order_key, true);

        if ($enabled === '' || $enabled === '1') {
            $custom_sections[] = [
                'type' => $section_type,
                'enabled' => true,
                'order' => $order ?: (($index + 1) * 10)
            ];
        }
    }

    if (!empty($custom_sections)) {
        usort($custom_sections, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        return $custom_sections;
    }

    return $sections;
}

function minpaku_add_official_meta_tags() {
    $rewrite = new OfficialRewrite();

    if (!$rewrite->isOfficialPageRequest()) {
        return;
    }

    if (!get_option('minpaku_official_seo_enabled', true)) {
        return;
    }

    $property_id = $rewrite->getCurrentPropertyId();
    if (!$property_id) {
        return;
    }

    $property = get_post($property_id);
    $location = get_post_meta($property_id, '_minpaku_location', true);
    $capacity = get_post_meta($property_id, '_minpaku_capacity', true);
    $featured_image = get_the_post_thumbnail_url($property_id, 'full');

    echo "<!-- Minpaku Official Site Meta Tags -->\n";
    echo '<meta property="og:title" content="' . esc_attr($property->post_title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr(wp_trim_words($property->post_content, 30)) . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($rewrite->getOfficialPageUrl($property_id)) . '">' . "\n";

    if ($featured_image) {
        echo '<meta property="og:image" content="' . esc_url($featured_image) . '">' . "\n";
    }

    if ($location) {
        echo '<meta name="geo.placename" content="' . esc_attr($location) . '">' . "\n";
    }

    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($property->post_title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr(wp_trim_words($property->post_content, 30)) . '">' . "\n";

    if ($featured_image) {
        echo '<meta name="twitter:image" content="' . esc_url($featured_image) . '">' . "\n";
    }

    echo "<!-- End Minpaku Official Site Meta Tags -->\n";
}

function minpaku_add_official_analytics() {
    $rewrite = new OfficialRewrite();

    if (!$rewrite->isOfficialPageRequest()) {
        return;
    }

    $analytics_code = get_option('minpaku_official_analytics_code', '');
    if (empty($analytics_code)) {
        return;
    }

    ?>
    <!-- Minpaku Official Site Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($analytics_code); ?>"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo esc_js($analytics_code); ?>', {
        'custom_map': {
            'custom_parameter_1': 'property_id'
        }
    });

    // Track property view
    gtag('event', 'view_property', {
        'property_id': <?php echo json_encode($rewrite->getCurrentPropertyId()); ?>,
        'property_type': 'minpaku'
    });
    </script>
    <!-- End Minpaku Official Site Analytics -->
    <?php
}

function minpaku_enqueue_official_custom_css() {
    $rewrite = new OfficialRewrite();

    if (!$rewrite->isOfficialPageRequest()) {
        return;
    }

    $custom_css = get_option('minpaku_official_custom_css', '');
    if (empty($custom_css)) {
        return;
    }

    wp_add_inline_style('minpaku-official-styles', $custom_css);
}

function minpaku_handle_property_update($property_id) {
    if (get_post_type($property_id) !== 'minpaku_property') {
        return;
    }

    $auto_generate = get_post_meta($property_id, '_minpaku_auto_generate_official', true);
    $global_auto_generate = get_option('minpaku_official_auto_generate', true);

    if ($auto_generate === '1' || ($auto_generate === '' && $global_auto_generate)) {
        $generator = new OfficialSiteGenerator();
        $generator->updateOnPropertyChange($property_id);
    }
}

function minpaku_flush_official_rewrite_rules() {
    OfficialRewrite::flushRewriteRules();
}

function minpaku_official_activation() {
    minpaku_init_official_system();

    flush_rewrite_rules();

    $assets = new OfficialAssets();
    $assets->createAssetFiles();

    if (!wp_next_scheduled('minpaku_daily_official_maintenance')) {
        wp_schedule_event(time(), 'daily', 'minpaku_daily_official_maintenance');
    }

    add_option('minpaku_official_enabled', true);
    add_option('minpaku_official_auto_generate', true);
    add_option('minpaku_official_url_structure', 'stay');
    add_option('minpaku_official_default_sections', ['hero', 'gallery', 'features', 'calendar', 'quote', 'access']);
    add_option('minpaku_official_seo_enabled', true);
    add_option('minpaku_official_analytics_code', '');
    add_option('minpaku_official_custom_css', '');

    do_action('minpaku_official_activated');
}

function minpaku_official_deactivation() {
    flush_rewrite_rules();

    wp_clear_scheduled_hook('minpaku_daily_official_maintenance');

    do_action('minpaku_official_deactivated');
}

add_action('minpaku_daily_official_maintenance', 'minpaku_run_official_maintenance');

function minpaku_run_official_maintenance() {
    global $wpdb;

    $orphaned_pages = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, pm.meta_value as property_id
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        LEFT JOIN {$wpdb->posts} prop ON pm.meta_value = prop.ID
        WHERE p.post_type = %s
        AND pm.meta_key = %s
        AND (prop.ID IS NULL OR prop.post_status = %s)
    ", 'page', '_minpaku_source_property_id', 'trash'));

    foreach ($orphaned_pages as $page) {
        wp_trash_post($page->ID);

        if ($page->property_id) {
            delete_post_meta($page->property_id, '_minpaku_official_page_id');
        }
    }

    $count = count($orphaned_pages);
    if ($count > 0) {
        error_log("Minpaku Official: Cleaned up {$count} orphaned official pages");
    }

    do_action('minpaku_official_maintenance_completed', $count);
}

function minpaku_get_official_page_url($property_id) {
    $rewrite = new OfficialRewrite();
    return $rewrite->getOfficialPageUrl($property_id);
}

function minpaku_render_official_section($type, $property_id, $property = null) {
    $template = new OfficialTemplate();

    if (!$property) {
        $property = get_post($property_id);
    }

    return $template->renderSection($type, $property_id, $property);
}

function minpaku_is_official_page() {
    $rewrite = new OfficialRewrite();
    return $rewrite->isOfficialPageRequest();
}

function minpaku_get_current_official_property() {
    $rewrite = new OfficialRewrite();
    $property_id = $rewrite->getCurrentPropertyId();

    return $property_id ? get_post($property_id) : null;
}

minpaku_init_official_system();