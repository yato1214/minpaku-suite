<?php
/**
 * Official Site Template - Access Section
 *
 * @var int $property_id
 * @var WP_Post $property
 */

if (!defined('ABSPATH')) {
    exit;
}

$location = get_post_meta($property_id, '_minpaku_location', true);
$access_info = get_post_meta($property_id, '_minpaku_access_info', true);
$latitude = get_post_meta($property_id, '_minpaku_latitude', true);
$longitude = get_post_meta($property_id, '_minpaku_longitude', true);
$transportation = get_post_meta($property_id, '_minpaku_transportation', true);
?>

<section class="minpaku-access-section" data-section="access" id="access">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php _e('Access & Location', 'minpaku-suite'); ?></h2>
            <p class="section-subtitle"><?php _e('How to reach this property', 'minpaku-suite'); ?></p>
        </div>

        <div class="access-content">
            <div class="access-grid">
                <!-- Location Information -->
                <div class="access-info-panel">
                    <?php if ($location): ?>
                        <div class="location-info">
                            <h3 class="info-title">
                                <svg class="title-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                                <?php _e('Address', 'minpaku-suite'); ?>
                            </h3>
                            <div class="address-display">
                                <p class="address-text"><?php echo esc_html($location); ?></p>
                                <?php if ($latitude && $longitude): ?>
                                    <p class="coordinates">
                                        <small><?php _e('Coordinates:', 'minpaku-suite'); ?> <?php echo esc_html($latitude); ?>, <?php echo esc_html($longitude); ?></small>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($transportation): ?>
                        <div class="transportation-info">
                            <h3 class="info-title">
                                <svg class="title-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM10 5.47l4 1.4v11.66l-4-1.4V5.47zm-5 .99l3-1.01v11.7l-3 1.16V6.46zm14 11.08l-3 1.01V6.86l3-1.16v11.84z"/>
                                </svg>
                                <?php _e('Transportation', 'minpaku-suite'); ?>
                            </h3>
                            <div class="transportation-content">
                                <?php echo wp_kses_post(wpautop($transportation)); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($access_info): ?>
                        <div class="access-instructions">
                            <h3 class="info-title">
                                <svg class="title-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                                </svg>
                                <?php _e('Access Information', 'minpaku-suite'); ?>
                            </h3>
                            <div class="access-content-text">
                                <?php echo wp_kses_post(wpautop($access_info)); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="nearby-info">
                        <h3 class="info-title">
                            <svg class="title-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            <?php _e('Nearby Attractions', 'minpaku-suite'); ?>
                        </h3>
                        <div class="nearby-placeholder">
                            <p><?php _e('Nearby attractions and points of interest will be displayed here.', 'minpaku-suite'); ?></p>
                            <div class="nearby-actions">
                                <?php if ($location): ?>
                                    <a href="https://www.google.com/maps/search/attractions+near+<?php echo urlencode($location); ?>"
                                       target="_blank" class="btn btn-outline">
                                        <?php _e('Find Attractions', 'minpaku-suite'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map Display -->
                <div class="map-panel">
                    <div class="map-container" id="propertyMap">
                        <?php if ($latitude && $longitude): ?>
                            <div class="interactive-map"
                                 data-lat="<?php echo esc_attr($latitude); ?>"
                                 data-lng="<?php echo esc_attr($longitude); ?>"
                                 data-address="<?php echo esc_attr($location); ?>">
                                <!-- Interactive map will be loaded here -->
                            </div>
                        <?php else: ?>
                            <div class="map-placeholder">
                                <div class="placeholder-content">
                                    <svg class="placeholder-icon" width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                    </svg>
                                    <h4><?php _e('Interactive Map', 'minpaku-suite'); ?></h4>
                                    <p><?php _e('Map will be displayed when location coordinates are available.', 'minpaku-suite'); ?></p>
                                    <?php if ($location): ?>
                                        <a href="https://www.google.com/maps/search/<?php echo urlencode($location); ?>"
                                           target="_blank" class="btn btn-primary">
                                            <?php _e('View on Google Maps', 'minpaku-suite'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php
                        // Hook for map integration plugins
                        do_action('minpaku_official_map', $property_id, $location, $latitude, $longitude);
                        ?>
                    </div>

                    <div class="map-actions">
                        <?php if ($location): ?>
                            <a href="https://www.google.com/maps/search/<?php echo urlencode($location); ?>"
                               target="_blank" class="map-action-btn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                                <?php _e('Open in Google Maps', 'minpaku-suite'); ?>
                            </a>

                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($location); ?>"
                               target="_blank" class="map-action-btn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M21.71 11.29l-9-9c-.39-.39-1.02-.39-1.41 0l-9 9c-.39.39-.39 1.02 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9c.39-.39.39-1.02 0-1.41zM14 14.5V12h-4v3H8v-4c0-.55.45-1 1-1h5V7.5L17 11l-3 3.5z"/>
                                </svg>
                                <?php _e('Get Directions', 'minpaku-suite'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.minpaku-access-section {
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

.access-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    max-width: 1000px;
    margin: 0 auto;
}

.access-info-panel {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.location-info,
.transportation-info,
.access-instructions,
.nearby-info {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 12px;
    border-left: 4px solid #ff6b6b;
}

.info-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1rem;
}

.title-icon {
    color: #ff6b6b;
    flex-shrink: 0;
}

.address-display {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.address-text {
    font-size: 1.1rem;
    color: #333;
    margin: 0 0 0.5rem 0;
    font-weight: 500;
}

.coordinates {
    margin: 0;
    color: #666;
}

.transportation-content,
.access-content-text {
    font-size: 1rem;
    line-height: 1.6;
    color: #555;
}

.transportation-content p,
.access-content-text p {
    margin-bottom: 1rem;
}

.transportation-content p:last-child,
.access-content-text p:last-child {
    margin-bottom: 0;
}

.nearby-placeholder {
    text-align: center;
    padding: 1.5rem;
    background: white;
    border-radius: 8px;
    border: 2px dashed #e9ecef;
}

.nearby-placeholder p {
    color: #666;
    margin-bottom: 1rem;
}

.nearby-actions {
    margin-top: 1rem;
}

.map-panel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.map-container {
    background: #f8f9fa;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    min-height: 400px;
    position: relative;
}

.interactive-map {
    width: 100%;
    height: 400px;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    font-size: 1.1rem;
}

.map-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 400px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.placeholder-content {
    text-align: center;
    padding: 2rem;
}

.placeholder-icon {
    margin-bottom: 1rem;
    opacity: 0.8;
}

.placeholder-content h4 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.placeholder-content p {
    margin-bottom: 1.5rem;
    opacity: 0.9;
}

.map-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.map-action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: #fff;
    color: #333;
    text-decoration: none;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.map-action-btn:hover {
    background: #ff6b6b;
    color: white;
    border-color: #ff6b6b;
    transform: translateY(-2px);
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: #ff6b6b;
    color: white;
}

.btn-primary:hover {
    background: #ff5252;
    transform: translateY(-2px);
    color: white;
}

.btn-outline {
    background: transparent;
    color: #ff6b6b;
    border: 2px solid #ff6b6b;
}

.btn-outline:hover {
    background: #ff6b6b;
    color: white;
}

/* Responsive Design */
@media (max-width: 768px) {
    .access-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }

    .location-info,
    .transportation-info,
    .access-instructions,
    .nearby-info {
        padding: 1.5rem;
    }

    .section-title {
        font-size: 2rem;
    }

    .map-actions {
        flex-direction: column;
    }

    .map-action-btn {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .minpaku-access-section {
        padding: 2rem 0;
    }

    .container {
        padding: 0 15px;
    }

    .location-info,
    .transportation-info,
    .access-instructions,
    .nearby-info {
        padding: 1.25rem;
    }

    .info-title {
        font-size: 1.1rem;
    }

    .map-container {
        min-height: 300px;
    }

    .interactive-map {
        height: 300px;
    }

    .map-placeholder .placeholder-content {
        padding: 1.5rem;
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

.minpaku-access-section.animate-in .location-info,
.minpaku-access-section.animate-in .transportation-info,
.minpaku-access-section.animate-in .access-instructions,
.minpaku-access-section.animate-in .nearby-info {
    animation: fadeInUp 0.6s ease-out;
}

.minpaku-access-section.animate-in .transportation-info {
    animation-delay: 0.1s;
}

.minpaku-access-section.animate-in .access-instructions {
    animation-delay: 0.2s;
}

.minpaku-access-section.animate-in .nearby-info {
    animation-delay: 0.3s;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Intersection Observer for animation
    const accessSection = document.querySelector('.minpaku-access-section');

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

    if (accessSection) {
        observer.observe(accessSection);
    }

    // Initialize map if coordinates are available
    const mapElement = document.querySelector('.interactive-map');
    if (mapElement) {
        const lat = parseFloat(mapElement.dataset.lat);
        const lng = parseFloat(mapElement.dataset.lng);
        const address = mapElement.dataset.address;

        if (lat && lng) {
            initializeMap(mapElement, lat, lng, address);
        }
    }

    function initializeMap(container, lat, lng, address) {
        // This is a placeholder for map initialization
        // In a real implementation, you would integrate with Google Maps, OpenStreetMap, etc.

        container.innerHTML = `
            <div style="
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100%;
                text-align: center;
                padding: 2rem;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            ">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor" style="margin-bottom: 1rem; opacity: 0.8;">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                </svg>
                <h4 style="margin-bottom: 0.5rem; font-weight: 600;"><?php _e('Interactive Map', 'minpaku-suite'); ?></h4>
                <p style="margin-bottom: 1.5rem; opacity: 0.9; max-width: 300px;"><?php _e('Map integration will be displayed here. Click below to view on external map service.', 'minpaku-suite'); ?></p>
                <p style="font-size: 0.9rem; opacity: 0.7;">
                    <strong><?php _e('Coordinates:', 'minpaku-suite'); ?></strong> ${lat}, ${lng}
                </p>
            </div>
        `;

        // Add click event to open in external map
        container.addEventListener('click', function() {
            window.open(`https://www.google.com/maps/search/${encodeURIComponent(address)}/@${lat},${lng},15z`, '_blank');
        });

        container.style.cursor = 'pointer';
    }

    // Copy coordinates to clipboard functionality
    const coordinatesElement = document.querySelector('.coordinates small');
    if (coordinatesElement) {
        coordinatesElement.style.cursor = 'pointer';
        coordinatesElement.title = '<?php _e('Click to copy coordinates', 'minpaku-suite'); ?>';

        coordinatesElement.addEventListener('click', function() {
            const coords = this.textContent.split(': ')[1];
            if (navigator.clipboard) {
                navigator.clipboard.writeText(coords).then(() => {
                    // Show temporary success message
                    const originalText = this.textContent;
                    this.textContent = '<?php _e('Coordinates copied!', 'minpaku-suite'); ?>';
                    this.style.color = '#28a745';

                    setTimeout(() => {
                        this.textContent = originalText;
                        this.style.color = '';
                    }, 2000);
                });
            }
        });
    }
});
</script>