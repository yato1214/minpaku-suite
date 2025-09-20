<?php
/**
 * Official Site Template - Features & Amenities Section
 *
 * @var int $property_id
 * @var WP_Post $property
 */

if (!defined('ABSPATH')) {
    exit;
}

$amenities = get_post_meta($property_id, '_minpaku_amenities', true);
$capacity = get_post_meta($property_id, '_minpaku_capacity', true);
$bedrooms = get_post_meta($property_id, '_minpaku_bedrooms', true);
$bathrooms = get_post_meta($property_id, '_minpaku_bathrooms', true);
$area = get_post_meta($property_id, '_minpaku_area', true);
$check_in = get_post_meta($property_id, '_minpaku_check_in', true);
$check_out = get_post_meta($property_id, '_minpaku_check_out', true);

$amenity_list = $amenities ? array_map('trim', explode(',', $amenities)) : [];

// Feature icons mapping
$feature_icons = [
    'capacity' => '👥',
    'bedrooms' => '🛏️',
    'bathrooms' => '🚿',
    'area' => '📐',
    'check_in' => '🔑',
    'check_out' => '🚪'
];

// Common amenity icons
$amenity_icons = [
    'wifi' => '📶',
    'kitchen' => '🍳',
    'parking' => '🚗',
    'pool' => '🏊',
    'gym' => '🏋️',
    'spa' => '🧖',
    'laundry' => '🧺',
    'balcony' => '🌅',
    'garden' => '🌺',
    'fireplace' => '🔥',
    'tv' => '📺',
    'air conditioning' => '❄️',
    'heating' => '🔥',
    'elevator' => '🛗',
    'security' => '🔒',
    'wheelchair accessible' => '♿',
    'pet friendly' => '🐕',
    'smoking allowed' => '🚬',
    'family friendly' => '👨‍👩‍👧‍👦',
    'business center' => '💼'
];
?>

<section class="minpaku-features-section" data-section="features" id="features">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php _e('Features & Amenities', 'minpaku-suite'); ?></h2>
            <p class="section-subtitle"><?php _e('Everything you need for a comfortable stay', 'minpaku-suite'); ?></p>
        </div>

        <div class="features-content">
            <!-- Property Specifications -->
            <div class="property-specs">
                <h3 class="specs-title"><?php _e('Property Details', 'minpaku-suite'); ?></h3>
                <div class="specs-grid">
                    <?php if ($capacity): ?>
                        <div class="spec-item">
                            <div class="spec-icon"><?php echo $feature_icons['capacity']; ?></div>
                            <div class="spec-content">
                                <span class="spec-label"><?php _e('Capacity', 'minpaku-suite'); ?></span>
                                <span class="spec-value"><?php echo esc_html($capacity); ?> <?php _e('guests', 'minpaku-suite'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($bedrooms): ?>
                        <div class="spec-item">
                            <div class="spec-icon"><?php echo $feature_icons['bedrooms']; ?></div>
                            <div class="spec-content">
                                <span class="spec-label"><?php _e('Bedrooms', 'minpaku-suite'); ?></span>
                                <span class="spec-value"><?php echo esc_html($bedrooms); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($bathrooms): ?>
                        <div class="spec-item">
                            <div class="spec-icon"><?php echo $feature_icons['bathrooms']; ?></div>
                            <div class="spec-content">
                                <span class="spec-label"><?php _e('Bathrooms', 'minpaku-suite'); ?></span>
                                <span class="spec-value"><?php echo esc_html($bathrooms); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($area): ?>
                        <div class="spec-item">
                            <div class="spec-icon"><?php echo $feature_icons['area']; ?></div>
                            <div class="spec-content">
                                <span class="spec-label"><?php _e('Area', 'minpaku-suite'); ?></span>
                                <span class="spec-value"><?php echo esc_html($area); ?> m²</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($check_in): ?>
                        <div class="spec-item">
                            <div class="spec-icon"><?php echo $feature_icons['check_in']; ?></div>
                            <div class="spec-content">
                                <span class="spec-label"><?php _e('Check-in', 'minpaku-suite'); ?></span>
                                <span class="spec-value"><?php echo esc_html($check_in); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($check_out): ?>
                        <div class="spec-item">
                            <div class="spec-icon"><?php echo $feature_icons['check_out']; ?></div>
                            <div class="spec-content">
                                <span class="spec-label"><?php _e('Check-out', 'minpaku-suite'); ?></span>
                                <span class="spec-value"><?php echo esc_html($check_out); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Amenities List -->
            <?php if (!empty($amenity_list)): ?>
                <div class="amenities-section">
                    <h3 class="amenities-title"><?php _e('Amenities', 'minpaku-suite'); ?></h3>
                    <div class="amenities-grid">
                        <?php foreach ($amenity_list as $amenity): ?>
                            <?php
                            $amenity_lower = strtolower($amenity);
                            $icon = '✓';

                            // Try to find matching icon
                            foreach ($amenity_icons as $key => $emoji) {
                                if (strpos($amenity_lower, $key) !== false) {
                                    $icon = $emoji;
                                    break;
                                }
                            }
                            ?>
                            <div class="amenity-item">
                                <span class="amenity-icon"><?php echo $icon; ?></span>
                                <span class="amenity-text"><?php echo esc_html($amenity); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Additional Information -->
            <?php
            $description = get_post_field('post_content', $property_id);
            if ($description):
            ?>
                <div class="property-description">
                    <h3 class="description-title"><?php _e('About This Property', 'minpaku-suite'); ?></h3>
                    <div class="description-content">
                        <?php echo wp_kses_post(wpautop($description)); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
.minpaku-features-section {
    padding: 4rem 0;
    background: white;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 0.5rem;
}

.section-subtitle {
    font-size: 1.1rem;
    color: #666;
    margin: 0;
}

.features-content {
    max-width: 1000px;
    margin: 0 auto;
}

/* Property Specifications */
.property-specs {
    margin-bottom: 3rem;
}

.specs-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1.5rem;
    text-align: center;
}

.specs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.spec-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 12px;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.spec-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: #ff6b6b;
}

.spec-icon {
    font-size: 2rem;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #ff6b6b, #ff8e53);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.spec-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.spec-label {
    font-size: 0.9rem;
    color: #666;
    font-weight: 500;
}

.spec-value {
    font-size: 1.1rem;
    color: #333;
    font-weight: 600;
}

/* Amenities Section */
.amenities-section {
    margin-bottom: 3rem;
}

.amenities-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1.5rem;
    text-align: center;
}

.amenities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.amenity-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: #f8f9fa;
    border-radius: 8px;
    transition: all 0.3s ease;
    border-left: 4px solid #ff6b6b;
}

.amenity-item:hover {
    background: #fff;
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.amenity-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.amenity-text {
    font-size: 1rem;
    color: #333;
    font-weight: 500;
}

/* Property Description */
.property-description {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 12px;
    border-left: 5px solid #ff6b6b;
}

.description-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1rem;
}

.description-content {
    font-size: 1rem;
    line-height: 1.7;
    color: #555;
}

.description-content p {
    margin-bottom: 1rem;
}

.description-content p:last-child {
    margin-bottom: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .minpaku-features-section {
        padding: 2rem 0;
    }

    .section-title {
        font-size: 2rem;
    }

    .specs-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .spec-item {
        padding: 1.25rem;
    }

    .spec-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }

    .amenities-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .amenity-item {
        padding: 0.875rem 1rem;
    }

    .property-description {
        padding: 1.5rem;
    }

    .container {
        padding: 0 15px;
    }
}

@media (max-width: 480px) {
    .section-title {
        font-size: 1.75rem;
    }

    .specs-title,
    .amenities-title,
    .description-title {
        font-size: 1.25rem;
    }

    .spec-item {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .spec-content {
        align-items: center;
    }

    .property-description {
        padding: 1.25rem;
    }
}

/* Animation for when section comes into view */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.minpaku-features-section.animate-in .spec-item,
.minpaku-features-section.animate-in .amenity-item {
    animation: fadeInUp 0.6s ease-out;
}

.minpaku-features-section.animate-in .spec-item:nth-child(2) {
    animation-delay: 0.1s;
}

.minpaku-features-section.animate-in .spec-item:nth-child(3) {
    animation-delay: 0.2s;
}

.minpaku-features-section.animate-in .amenity-item:nth-child(even) {
    animation-delay: 0.1s;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Intersection Observer for animation
    const featuresSection = document.querySelector('.minpaku-features-section');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '50px'
    });

    if (featuresSection) {
        observer.observe(featuresSection);
    }

    // Add hover effect for spec items
    const specItems = document.querySelectorAll('.spec-item');
    specItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.background = 'linear-gradient(135deg, #fff5f5, #fff0f0)';
        });

        item.addEventListener('mouseleave', function() {
            this.style.background = '#f8f9fa';
        });
    });

    // Add stagger animation for amenities
    const amenityItems = document.querySelectorAll('.amenity-item');
    amenityItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.05}s`;
    });
});
</script>