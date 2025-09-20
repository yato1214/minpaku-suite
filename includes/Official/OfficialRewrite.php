<?php

namespace Minpaku\Official;

class OfficialRewrite {

    public function __construct() {
        add_action('init', [$this, 'addRewriteRules']);
        add_filter('query_vars', [$this, 'addQueryVars']);
        add_action('template_redirect', [$this, 'handleOfficialPageRequest']);
        add_action('parse_request', [$this, 'parseOfficialRequest']);
    }

    public function addRewriteRules() {
        add_rewrite_rule(
            '^stay/([^/]+)/?$',
            'index.php?minpaku_official_page=1&property_slug=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^stay/([^/]+)/([^/]+)/?$',
            'index.php?minpaku_official_page=1&property_slug=$matches[1]&section=$matches[2]',
            'top'
        );
    }

    public function addQueryVars($vars) {
        $vars[] = 'minpaku_official_page';
        $vars[] = 'property_slug';
        $vars[] = 'section';
        return $vars;
    }

    public function parseOfficialRequest($wp) {
        if (!isset($wp->query_vars['minpaku_official_page']) ||
            !$wp->query_vars['minpaku_official_page']) {
            return;
        }

        $property_slug = isset($wp->query_vars['property_slug']) ? $wp->query_vars['property_slug'] : '';
        $section = isset($wp->query_vars['section']) ? $wp->query_vars['section'] : '';

        if (empty($property_slug)) {
            return;
        }

        $property = get_page_by_path($property_slug, OBJECT, 'minpaku_property');

        if (!$property) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        $wp->query_vars['property_id'] = $property->ID;
        $wp->query_vars['property_object'] = $property;

        if (!empty($section)) {
            $wp->query_vars['target_section'] = $section;
        }
    }

    public function handleOfficialPageRequest() {
        global $wp_query, $wp;

        if (!get_query_var('minpaku_official_page')) {
            return;
        }

        $property_id = get_query_var('property_id');
        $section = get_query_var('target_section');

        if (!$property_id) {
            $wp_query->set_404();
            status_header(404);
            return;
        }

        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'minpaku_property') {
            $wp_query->set_404();
            status_header(404);
            return;
        }

        if ($property->post_status !== 'publish') {
            if (!current_user_can('read_private_posts')) {
                $wp_query->set_404();
                status_header(404);
                return;
            }
        }

        $generator = new OfficialSiteGenerator();
        $official_page_id = $generator->getOfficialPageId($property_id);

        if (!$official_page_id) {
            if (current_user_can('edit_posts')) {
                $this->showOfficialPageNotFound($property);
                exit;
            } else {
                $wp_query->set_404();
                status_header(404);
                return;
            }
        }

        $official_page = get_post($official_page_id);
        if (!$official_page) {
            $wp_query->set_404();
            status_header(404);
            return;
        }

        if ($official_page->post_status !== 'publish') {
            if (!current_user_can('read_private_posts')) {
                $wp_query->set_404();
                status_header(404);
                return;
            }
        }

        $this->displayOfficialPage($property, $official_page, $section);
        exit;
    }

    private function displayOfficialPage($property, $official_page, $target_section = '') {
        $template = new OfficialTemplate();

        get_header();

        echo '<div class="minpaku-official-page-wrapper">';

        echo $template->getTemplateStyles();

        if (!empty($target_section)) {
            echo '<div id="section-' . esc_attr($target_section) . '">';
            echo $template->renderSection($target_section, $property->ID, $property);
            echo '</div>';
        } else {
            echo $template->renderPage($property->ID);
        }

        $this->addStructuredData($property);

        echo '</div>';

        get_footer();
    }

    private function showOfficialPageNotFound($property) {
        get_header();
        ?>
        <div class="minpaku-official-not-found">
            <div class="container">
                <h1><?php _e('Official Page Not Found', 'minpaku-suite'); ?></h1>
                <p><?php printf(__('The official page for "%s" has not been generated yet.', 'minpaku-suite'), esc_html($property->post_title)); ?></p>

                <?php if (current_user_can('edit_post', $property->ID)): ?>
                    <div class="admin-actions">
                        <p><?php _e('As an administrator, you can generate the official page:', 'minpaku-suite'); ?></p>
                        <a href="<?php echo admin_url('post.php?post=' . $property->ID . '&action=edit'); ?>" class="button button-primary">
                            <?php _e('Edit Property & Generate Page', 'minpaku-suite'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
        .minpaku-official-not-found {
            padding: 60px 20px;
            text-align: center;
            min-height: 400px;
        }

        .minpaku-official-not-found .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .minpaku-official-not-found h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .minpaku-official-not-found p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            color: #666;
        }

        .admin-actions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }

        .admin-actions p {
            margin-bottom: 15px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .button:hover {
            background: #005a87;
            color: white;
        }
        </style>
        <?php
        get_footer();
    }

    private function addStructuredData($property) {
        $location = get_post_meta($property->ID, '_minpaku_location', true);
        $capacity = get_post_meta($property->ID, '_minpaku_capacity', true);
        $bedrooms = get_post_meta($property->ID, '_minpaku_bedrooms', true);
        $bathrooms = get_post_meta($property->ID, '_minpaku_bathrooms', true);
        $amenities = get_post_meta($property->ID, '_minpaku_amenities', true);
        $featured_image = get_the_post_thumbnail_url($property->ID, 'full');

        $structured_data = [
            "@context" => "https://schema.org",
            "@type" => "LodgingBusiness",
            "name" => $property->post_title,
            "description" => wp_strip_all_tags($property->post_content),
            "url" => home_url('/stay/' . $property->post_name . '/'),
        ];

        if ($location) {
            $structured_data["address"] = [
                "@type" => "PostalAddress",
                "addressLocality" => $location
            ];
        }

        if ($featured_image) {
            $structured_data["image"] = $featured_image;
        }

        if ($capacity || $bedrooms || $bathrooms) {
            $structured_data["amenityFeature"] = [];

            if ($capacity) {
                $structured_data["amenityFeature"][] = [
                    "@type" => "LocationFeatureSpecification",
                    "name" => "Maximum Occupancy",
                    "value" => $capacity
                ];
            }

            if ($bedrooms) {
                $structured_data["amenityFeature"][] = [
                    "@type" => "LocationFeatureSpecification",
                    "name" => "Number of Bedrooms",
                    "value" => $bedrooms
                ];
            }

            if ($bathrooms) {
                $structured_data["amenityFeature"][] = [
                    "@type" => "LocationFeatureSpecification",
                    "name" => "Number of Bathrooms",
                    "value" => $bathrooms
                ];
            }
        }

        if ($amenities) {
            $amenity_list = array_map('trim', explode(',', $amenities));
            foreach ($amenity_list as $amenity) {
                $structured_data["amenityFeature"][] = [
                    "@type" => "LocationFeatureSpecification",
                    "name" => $amenity
                ];
            }
        }

        echo '<script type="application/ld+json">' . wp_json_encode($structured_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }

    public function getOfficialPageUrl($property_id) {
        $property = get_post($property_id);
        if (!$property || $property->post_type !== 'minpaku_property') {
            return false;
        }

        return home_url('/stay/' . $property->post_name . '/');
    }

    public function getSectionUrl($property_id, $section) {
        $base_url = $this->getOfficialPageUrl($property_id);
        if (!$base_url) {
            return false;
        }

        return rtrim($base_url, '/') . '/' . $section . '/';
    }

    public static function flushRewriteRules() {
        $rewrite = new self();
        $rewrite->addRewriteRules();
        flush_rewrite_rules();
    }

    public function isOfficialPageRequest() {
        return get_query_var('minpaku_official_page') ? true : false;
    }

    public function getCurrentPropertyId() {
        if (!$this->isOfficialPageRequest()) {
            return false;
        }

        return get_query_var('property_id');
    }

    public function getCurrentSection() {
        if (!$this->isOfficialPageRequest()) {
            return false;
        }

        return get_query_var('target_section');
    }
}