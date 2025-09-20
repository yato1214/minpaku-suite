<?php
/**
 * Official Site Generator
 * Generates and manages official property pages
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

class OfficialSiteGenerator {

    /**
     * Post type for official pages
     */
    const OFFICIAL_POST_TYPE = 'page';

    /**
     * Meta key to identify official pages
     */
    const OFFICIAL_META_KEY = '_minpaku_official_property_id';

    /**
     * Meta key for auto-generated content marker
     */
    const AUTO_CONTENT_META_KEY = '_minpaku_official_auto_content';

    /**
     * Generate official page for property
     *
     * @param int $property_id Property ID
     * @return int|WP_Error Page ID on success, WP_Error on failure
     */
    public function generate($property_id) {
        if (!get_post($property_id) || get_post_type($property_id) !== 'property') {
            return new WP_Error('invalid_property', __('Invalid property ID', 'minpaku-suite'));
        }

        // Check if official page already exists
        $existing_page_id = $this->getOfficialPageId($property_id);
        if ($existing_page_id) {
            // Update existing page
            return $this->updateOfficialPage($existing_page_id, $property_id);
        }

        // Create new official page
        return $this->createOfficialPage($property_id);
    }

    /**
     * Update official page when property changes
     *
     * @param int $property_id Property ID
     */
    public function updateOnPropertyChange($property_id) {
        $page_id = $this->getOfficialPageId($property_id);
        if (!$page_id) {
            // Auto-generate if enabled
            $settings = get_option('minpaku_official_settings', []);
            if ($settings['auto_generate'] ?? true) {
                $this->generate($property_id);
            }
            return;
        }

        $this->updateOfficialPage($page_id, $property_id);
    }

    /**
     * Get official page ID for property
     *
     * @param int $property_id Property ID
     * @return int|null Page ID or null if not found
     */
    public function getOfficialPageId($property_id) {
        $pages = get_posts([
            'post_type' => self::OFFICIAL_POST_TYPE,
            'meta_query' => [
                [
                    'key' => self::OFFICIAL_META_KEY,
                    'value' => $property_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'post_status' => ['publish', 'draft', 'private']
        ]);

        return !empty($pages) ? $pages[0]->ID : null;
    }

    /**
     * Delete official page for property
     *
     * @param int $property_id Property ID
     * @return bool True on success
     */
    public function deleteOfficialPage($property_id) {
        $page_id = $this->getOfficialPageId($property_id);
        if (!$page_id) {
            return true;
        }

        $result = wp_delete_post($page_id, true);
        return $result !== false;
    }

    /**
     * Toggle official page publish status
     *
     * @param int $property_id Property ID
     * @param bool $publish Whether to publish or draft
     * @return bool True on success
     */
    public function togglePublishStatus($property_id, $publish = true) {
        $page_id = $this->getOfficialPageId($property_id);
        if (!$page_id) {
            if ($publish) {
                // Generate new page if publishing and none exists
                $result = $this->generate($property_id);
                return !is_wp_error($result);
            }
            return true;
        }

        $new_status = $publish ? 'publish' : 'draft';
        $result = wp_update_post([
            'ID' => $page_id,
            'post_status' => $new_status
        ]);

        return $result !== 0;
    }

    /**
     * Create new official page
     *
     * @param int $property_id Property ID
     * @return int|WP_Error Page ID on success
     */
    private function createOfficialPage($property_id) {
        $property = get_post($property_id);
        if (!$property) {
            return new WP_Error('property_not_found', __('Property not found', 'minpaku-suite'));
        }

        // Generate page slug
        $slug = $this->generatePageSlug($property_id);

        // Get property data
        $property_data = $this->getPropertyData($property_id);

        // Generate page content
        $content = $this->generatePageContent($property_id, $property_data);

        // Create page
        $page_data = [
            'post_title' => sprintf(__('%s - Official Site', 'minpaku-suite'), $property->post_title),
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => self::OFFICIAL_POST_TYPE,
            'post_name' => $slug,
            'post_parent' => 0,
            'meta_input' => [
                self::OFFICIAL_META_KEY => $property_id,
                self::AUTO_CONTENT_META_KEY => 1,
                '_wp_page_template' => 'page-official.php'
            ]
        ];

        $page_id = wp_insert_post($page_data);

        if (is_wp_error($page_id)) {
            return $page_id;
        }

        // Set additional meta
        update_post_meta($page_id, '_minpaku_official_generated_at', current_time('mysql'));
        update_post_meta($page_id, '_minpaku_official_version', '1.0');

        // Log page creation
        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Official page created', [
                'property_id' => $property_id,
                'page_id' => $page_id,
                'property_title' => $property->post_title,
                'page_slug' => $slug
            ]);
        }

        return $page_id;
    }

    /**
     * Update existing official page
     *
     * @param int $page_id Page ID
     * @param int $property_id Property ID
     * @return int Page ID
     */
    private function updateOfficialPage($page_id, $property_id) {
        $property = get_post($property_id);
        if (!$property) {
            return $page_id;
        }

        // Check if this is an auto-generated page
        $is_auto_content = get_post_meta($page_id, self::AUTO_CONTENT_META_KEY, true);

        $update_data = [
            'ID' => $page_id,
            'post_title' => sprintf(__('%s - Official Site', 'minpaku-suite'), $property->post_title)
        ];

        // Only update content if it's auto-generated
        if ($is_auto_content) {
            $property_data = $this->getPropertyData($property_id);
            $update_data['post_content'] = $this->generatePageContent($property_id, $property_data);
        }

        wp_update_post($update_data);

        // Update meta
        update_post_meta($page_id, '_minpaku_official_updated_at', current_time('mysql'));

        return $page_id;
    }

    /**
     * Generate page slug for property
     *
     * @param int $property_id Property ID
     * @return string Page slug
     */
    private function generatePageSlug($property_id) {
        $property = get_post($property_id);
        $settings = get_option('minpaku_official_settings', []);
        $base_slug = $settings['base_slug'] ?? 'stay';

        // Remove leading/trailing slashes from base
        $base_slug = trim($base_slug, '/');

        // Get property slug or generate from title
        $property_slug = $property->post_name ?: sanitize_title($property->post_title);

        return $base_slug . '/' . $property_slug;
    }

    /**
     * Get property data for page generation
     *
     * @param int $property_id Property ID
     * @return array Property data
     */
    private function getPropertyData($property_id) {
        $property = get_post($property_id);

        // Get property meta
        $location = get_post_meta($property_id, 'location', true) ?: '';
        $description = get_post_meta($property_id, 'description', true) ?: $property->post_excerpt ?: '';
        $features = get_post_meta($property_id, 'features', true) ?: [];
        $amenities = get_post_meta($property_id, 'amenities', true) ?: [];
        $capacity = get_post_meta($property_id, 'capacity', true) ?: '';
        $bedrooms = get_post_meta($property_id, 'bedrooms', true) ?: '';
        $bathrooms = get_post_meta($property_id, 'bathrooms', true) ?: '';

        // Get gallery images
        $gallery_images = $this->getPropertyGallery($property_id);

        return [
            'title' => $property->post_title,
            'description' => $description,
            'location' => $location,
            'features' => is_array($features) ? $features : [],
            'amenities' => is_array($amenities) ? $amenities : [],
            'capacity' => $capacity,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'gallery' => $gallery_images
        ];
    }

    /**
     * Get property gallery images
     *
     * @param int $property_id Property ID
     * @return array Array of image data
     */
    private function getPropertyGallery($property_id) {
        $gallery = [];
        $settings = get_option('minpaku_official_settings', []);
        $max_images = $settings['gallery_limit'] ?? 8;

        // Get featured image
        $featured_image_id = get_post_thumbnail_id($property_id);
        if ($featured_image_id) {
            $gallery[] = [
                'id' => $featured_image_id,
                'url' => wp_get_attachment_image_url($featured_image_id, 'large'),
                'thumb' => wp_get_attachment_image_url($featured_image_id, 'medium'),
                'alt' => get_post_meta($featured_image_id, '_wp_attachment_image_alt', true)
            ];
        }

        // Get gallery images from meta
        $gallery_meta = get_post_meta($property_id, 'gallery', true);
        if (is_array($gallery_meta)) {
            foreach ($gallery_meta as $image_id) {
                if (count($gallery) >= $max_images) {
                    break;
                }

                if ($image_id && $image_id != $featured_image_id) {
                    $gallery[] = [
                        'id' => $image_id,
                        'url' => wp_get_attachment_image_url($image_id, 'large'),
                        'thumb' => wp_get_attachment_image_url($image_id, 'medium'),
                        'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
                    ];
                }
            }
        }

        return $gallery;
    }

    /**
     * Generate page content with sections
     *
     * @param int $property_id Property ID
     * @param array $property_data Property data
     * @return string Generated content
     */
    private function generatePageContent($property_id, $property_data) {
        $settings = get_option('minpaku_official_settings', []);
        $sections = $settings['sections'] ?? $this->getDefaultSections();

        $content = '';

        // Apply filter for custom section order
        $sections = apply_filters('minpaku_official_sections', $sections, $property_id);

        foreach ($sections as $section => $enabled) {
            if (!$enabled) {
                continue;
            }

            switch ($section) {
                case 'hero':
                    $content .= $this->generateHeroSection($property_id, $property_data);
                    break;
                case 'gallery':
                    $content .= $this->generateGallerySection($property_id, $property_data);
                    break;
                case 'features':
                    $content .= $this->generateFeaturesSection($property_id, $property_data);
                    break;
                case 'calendar':
                    $content .= $this->generateCalendarSection($property_id);
                    break;
                case 'quote':
                    $content .= $this->generateQuoteSection($property_id);
                    break;
                case 'access':
                    $content .= $this->generateAccessSection($property_id, $property_data);
                    break;
            }
        }

        return $content;
    }

    /**
     * Get default section configuration
     *
     * @return array Default sections
     */
    private function getDefaultSections() {
        return [
            'hero' => true,
            'gallery' => true,
            'features' => true,
            'calendar' => true,
            'quote' => true,
            'access' => true
        ];
    }

    /**
     * Generate hero section
     *
     * @param int $property_id Property ID
     * @param array $property_data Property data
     * @return string Hero section HTML
     */
    private function generateHeroSection($property_id, $property_data) {
        return sprintf(
            '<div class="minpaku-hero-section">
                <h1>%s</h1>
                <p class="location">%s</p>
                <div class="property-basics">
                    %s
                </div>
            </div>',
            esc_html($property_data['title']),
            esc_html($property_data['location']),
            $this->generatePropertyBasics($property_data)
        );
    }

    /**
     * Generate property basics info
     *
     * @param array $property_data Property data
     * @return string Basics HTML
     */
    private function generatePropertyBasics($property_data) {
        $basics = [];

        if ($property_data['capacity']) {
            $basics[] = sprintf(__('Capacity: %s', 'minpaku-suite'), esc_html($property_data['capacity']));
        }
        if ($property_data['bedrooms']) {
            $basics[] = sprintf(__('Bedrooms: %s', 'minpaku-suite'), esc_html($property_data['bedrooms']));
        }
        if ($property_data['bathrooms']) {
            $basics[] = sprintf(__('Bathrooms: %s', 'minpaku-suite'), esc_html($property_data['bathrooms']));
        }

        return '<p>' . implode(' | ', $basics) . '</p>';
    }

    /**
     * Generate gallery section
     *
     * @param int $property_id Property ID
     * @param array $property_data Property data
     * @return string Gallery section HTML
     */
    private function generateGallerySection($property_id, $property_data) {
        if (empty($property_data['gallery'])) {
            return '';
        }

        $gallery_html = '<div class="minpaku-gallery-section">
            <h2>' . __('Gallery', 'minpaku-suite') . '</h2>
            <div class="minpaku-gallery">';

        foreach ($property_data['gallery'] as $image) {
            $gallery_html .= sprintf(
                '<div class="gallery-item">
                    <img src="%s" alt="%s" loading="lazy">
                </div>',
                esc_url($image['url']),
                esc_attr($image['alt'])
            );
        }

        $gallery_html .= '</div></div>';

        return $gallery_html;
    }

    /**
     * Generate features section
     *
     * @param int $property_id Property ID
     * @param array $property_data Property data
     * @return string Features section HTML
     */
    private function generateFeaturesSection($property_id, $property_data) {
        $content = '<div class="minpaku-features-section">';

        if ($property_data['description']) {
            $content .= '<div class="description">
                <h2>' . __('About This Property', 'minpaku-suite') . '</h2>
                <p>' . wp_kses_post($property_data['description']) . '</p>
            </div>';
        }

        if (!empty($property_data['features']) || !empty($property_data['amenities'])) {
            $content .= '<div class="features-amenities">
                <h3>' . __('Features & Amenities', 'minpaku-suite') . '</h3>
                <ul>';

            foreach (array_merge($property_data['features'], $property_data['amenities']) as $item) {
                $content .= '<li>' . esc_html($item) . '</li>';
            }

            $content .= '</ul></div>';
        }

        $content .= '</div>';

        return $content;
    }

    /**
     * Generate calendar section
     *
     * @param int $property_id Property ID
     * @return string Calendar section HTML
     */
    private function generateCalendarSection($property_id) {
        return sprintf(
            '<div class="minpaku-calendar-section">
                <h2>%s</h2>
                [minpaku_calendar property_id="%d"]
            </div>',
            __('Availability Calendar', 'minpaku-suite'),
            $property_id
        );
    }

    /**
     * Generate quote section
     *
     * @param int $property_id Property ID
     * @return string Quote section HTML
     */
    private function generateQuoteSection($property_id) {
        return sprintf(
            '<div class="minpaku-quote-section">
                <h2>%s</h2>
                [minpaku_quote property_id="%d"]
            </div>',
            __('Get Quote & Book', 'minpaku-suite'),
            $property_id
        );
    }

    /**
     * Generate access section
     *
     * @param int $property_id Property ID
     * @param array $property_data Property data
     * @return string Access section HTML
     */
    private function generateAccessSection($property_id, $property_data) {
        return sprintf(
            '<div class="minpaku-access-section">
                <h2>%s</h2>
                <p class="location">%s</p>
                <div class="map-placeholder">
                    <p>%s</p>
                </div>
            </div>',
            __('Location & Access', 'minpaku-suite'),
            esc_html($property_data['location']),
            __('Map will be displayed here', 'minpaku-suite')
        );
    }

    /**
     * Get official page URL
     *
     * @param int $property_id Property ID
     * @return string|null Page URL or null if page doesn't exist
     */
    public function getOfficialPageUrl($property_id) {
        $page_id = $this->getOfficialPageId($property_id);
        if (!$page_id) {
            return null;
        }

        return get_permalink($page_id);
    }

    /**
     * Check if property has official page
     *
     * @param int $property_id Property ID
     * @return bool True if has official page
     */
    public function hasOfficialPage($property_id) {
        return $this->getOfficialPageId($property_id) !== null;
    }

    /**
     * Get official page status
     *
     * @param int $property_id Property ID
     * @return array Status information
     */
    public function getOfficialPageStatus($property_id) {
        $page_id = $this->getOfficialPageId($property_id);

        if (!$page_id) {
            return [
                'exists' => false,
                'published' => false,
                'url' => null,
                'page_id' => null
            ];
        }

        $page = get_post($page_id);

        return [
            'exists' => true,
            'published' => $page->post_status === 'publish',
            'url' => get_permalink($page_id),
            'page_id' => $page_id,
            'status' => $page->post_status
        ];
    }
}