<?php

namespace Minpaku\Official;

use Minpaku\Services\PropertyService;

class OfficialTemplate {

    private $property_service;

    public function __construct() {
        $this->property_service = new PropertyService();
    }

    public function renderPage($property_id) {
        $property = $this->property_service->getProperty($property_id);
        if (!$property) {
            return __('Property not found.', 'minpaku-suite');
        }

        $sections = $this->getSections($property_id);
        $output = '';

        foreach ($sections as $section) {
            if (!$section['enabled']) {
                continue;
            }

            $output .= $this->renderSection($section['type'], $property_id, $property);
        }

        return $output;
    }

    public function getSections($property_id) {
        $default_sections = [
            [
                'type' => 'hero',
                'enabled' => true,
                'order' => 10
            ],
            [
                'type' => 'gallery',
                'enabled' => true,
                'order' => 20
            ],
            [
                'type' => 'features',
                'enabled' => true,
                'order' => 30
            ],
            [
                'type' => 'calendar',
                'enabled' => true,
                'order' => 40
            ],
            [
                'type' => 'quote',
                'enabled' => true,
                'order' => 50
            ],
            [
                'type' => 'access',
                'enabled' => true,
                'order' => 60
            ]
        ];

        $sections = apply_filters('minpaku_official_sections', $default_sections, $property_id);

        usort($sections, function($a, $b) {
            return $a['order'] - $b['order'];
        });

        return $sections;
    }

    public function renderSection($type, $property_id, $property) {
        $method = 'render' . ucfirst($type) . 'Section';

        if (method_exists($this, $method)) {
            return $this->$method($property_id, $property);
        }

        return apply_filters('minpaku_official_render_section', '', $type, $property_id, $property);
    }

    private function renderHeroSection($property_id, $property) {
        $title = get_the_title($property_id);
        $location = get_post_meta($property_id, '_minpaku_location', true);
        $featured_image = get_the_post_thumbnail_url($property_id, 'full');

        ob_start();
        ?>
        <section class="minpaku-hero" style="background-image: url('<?php echo esc_url($featured_image); ?>');">
            <div class="hero-overlay">
                <div class="hero-content">
                    <h1 class="property-title"><?php echo esc_html($title); ?></h1>
                    <?php if ($location): ?>
                        <p class="property-location">
                            <span class="location-icon">📍</span>
                            <?php echo esc_html($location); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function renderGallerySection($property_id, $property) {
        $gallery_images = get_post_meta($property_id, '_minpaku_gallery', true);

        if (empty($gallery_images)) {
            return '';
        }

        $images = array_slice(explode(',', $gallery_images), 0, 8);

        ob_start();
        ?>
        <section class="minpaku-gallery">
            <h2><?php _e('Gallery', 'minpaku-suite'); ?></h2>
            <div class="gallery-grid">
                <?php foreach ($images as $image_id): ?>
                    <?php
                    $image_url = wp_get_attachment_image_url(trim($image_id), 'large');
                    $image_thumb = wp_get_attachment_image_url(trim($image_id), 'medium');
                    if ($image_url):
                    ?>
                        <div class="gallery-item">
                            <img src="<?php echo esc_url($image_thumb); ?>"
                                 data-full="<?php echo esc_url($image_url); ?>"
                                 alt="<?php echo esc_attr(get_post_meta(trim($image_id), '_wp_attachment_image_alt', true)); ?>"
                                 loading="lazy">
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function renderFeaturesSection($property_id, $property) {
        $amenities = get_post_meta($property_id, '_minpaku_amenities', true);
        $capacity = get_post_meta($property_id, '_minpaku_capacity', true);
        $bedrooms = get_post_meta($property_id, '_minpaku_bedrooms', true);
        $bathrooms = get_post_meta($property_id, '_minpaku_bathrooms', true);

        ob_start();
        ?>
        <section class="minpaku-features">
            <h2><?php _e('Features & Amenities', 'minpaku-suite'); ?></h2>

            <div class="property-specs">
                <?php if ($capacity): ?>
                    <div class="spec-item">
                        <span class="spec-icon">👥</span>
                        <span class="spec-label"><?php _e('Capacity', 'minpaku-suite'); ?>:</span>
                        <span class="spec-value"><?php echo esc_html($capacity); ?> <?php _e('guests', 'minpaku-suite'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($bedrooms): ?>
                    <div class="spec-item">
                        <span class="spec-icon">🛏️</span>
                        <span class="spec-label"><?php _e('Bedrooms', 'minpaku-suite'); ?>:</span>
                        <span class="spec-value"><?php echo esc_html($bedrooms); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($bathrooms): ?>
                    <div class="spec-item">
                        <span class="spec-icon">🚿</span>
                        <span class="spec-label"><?php _e('Bathrooms', 'minpaku-suite'); ?>:</span>
                        <span class="spec-value"><?php echo esc_html($bathrooms); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($amenities): ?>
                <div class="amenities-list">
                    <h3><?php _e('Amenities', 'minpaku-suite'); ?></h3>
                    <ul>
                        <?php
                        $amenity_list = explode(',', $amenities);
                        foreach ($amenity_list as $amenity):
                        ?>
                            <li><?php echo esc_html(trim($amenity)); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private function renderCalendarSection($property_id, $property) {
        ob_start();
        ?>
        <section class="minpaku-calendar">
            <h2><?php _e('Availability Calendar', 'minpaku-suite'); ?></h2>
            <div class="calendar-container">
                <?php echo do_shortcode('[minpaku_calendar property_id="' . $property_id . '"]'); ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function renderQuoteSection($property_id, $property) {
        ob_start();
        ?>
        <section class="minpaku-quote">
            <h2><?php _e('Get Quote & Book', 'minpaku-suite'); ?></h2>
            <div class="quote-container">
                <?php echo do_shortcode('[minpaku_quote property_id="' . $property_id . '"]'); ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function renderAccessSection($property_id, $property) {
        $location = get_post_meta($property_id, '_minpaku_location', true);
        $access_info = get_post_meta($property_id, '_minpaku_access_info', true);

        ob_start();
        ?>
        <section class="minpaku-access">
            <h2><?php _e('Access & Location', 'minpaku-suite'); ?></h2>

            <?php if ($location): ?>
                <div class="location-info">
                    <h3><?php _e('Address', 'minpaku-suite'); ?></h3>
                    <p class="address">
                        <span class="location-icon">📍</span>
                        <?php echo esc_html($location); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($access_info): ?>
                <div class="access-info">
                    <h3><?php _e('Access Information', 'minpaku-suite'); ?></h3>
                    <div class="access-content">
                        <?php echo wp_kses_post(wpautop($access_info)); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="map-placeholder">
                <div class="map-container">
                    <p class="map-note"><?php _e('Interactive map will be displayed here', 'minpaku-suite'); ?></p>
                    <?php
                    // Hook for map integration
                    do_action('minpaku_official_map', $property_id, $location);
                    ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public function getTemplateStyles() {
        return "
        <style>
        .minpaku-hero {
            position: relative;
            height: 400px;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-content {
            text-align: center;
            color: white;
            z-index: 1;
        }

        .property-title {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        .property-location {
            font-size: 1.2rem;
            margin: 0;
        }

        .location-icon {
            margin-right: 0.5rem;
        }

        .minpaku-gallery,
        .minpaku-features,
        .minpaku-calendar,
        .minpaku-quote,
        .minpaku-access {
            margin-bottom: 3rem;
            padding: 2rem;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .gallery-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .gallery-item img:hover {
            transform: scale(1.05);
        }

        .property-specs {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .spec-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .spec-icon {
            font-size: 1.2rem;
        }

        .spec-label {
            font-weight: 600;
        }

        .amenities-list ul {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
        }

        .amenities-list li {
            padding: 0.5rem;
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .amenities-list li:before {
            content: '✓';
            color: #4CAF50;
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .calendar-container,
        .quote-container {
            background: white;
            padding: 1.5rem;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .location-info,
        .access-info {
            margin-bottom: 2rem;
        }

        .address {
            font-size: 1.1rem;
            margin: 0.5rem 0;
        }

        .map-placeholder {
            margin-top: 2rem;
        }

        .map-container {
            height: 300px;
            background: #e0e0e0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }

        @media (max-width: 768px) {
            .property-title {
                font-size: 2rem;
            }

            .property-specs {
                flex-direction: column;
            }

            .gallery-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .amenities-list ul {
                grid-template-columns: 1fr;
            }
        }
        </style>
        ";
    }
}