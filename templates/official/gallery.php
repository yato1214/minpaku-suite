<?php
/**
 * Official Site Template - Gallery Section
 *
 * @var int $property_id
 * @var WP_Post $property
 */

if (!defined('ABSPATH')) {
    exit;
}

$gallery_images = get_post_meta($property_id, '_minpaku_gallery', true);

if (empty($gallery_images)) {
    return;
}

$images = array_slice(explode(',', $gallery_images), 0, 8);
$image_count = count($images);
?>

<section class="minpaku-gallery-section" data-section="gallery" id="gallery">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php _e('Gallery', 'minpaku-suite'); ?></h2>
            <p class="section-subtitle">
                <?php printf(
                    _n('Explore %d beautiful photo of this property', 'Explore %d beautiful photos of this property', $image_count, 'minpaku-suite'),
                    $image_count
                ); ?>
            </p>
        </div>

        <div class="gallery-grid">
            <?php foreach ($images as $index => $image_id): ?>
                <?php
                $image_id = trim($image_id);
                $image_url = wp_get_attachment_image_url($image_id, 'full');
                $image_thumb = wp_get_attachment_image_url($image_id, 'large');
                $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                $image_caption = wp_get_attachment_caption($image_id);

                if (!$image_url) continue;

                $grid_class = '';
                if ($index === 0) {
                    $grid_class = 'gallery-featured';
                } elseif ($index === 1 || $index === 2) {
                    $grid_class = 'gallery-large';
                } else {
                    $grid_class = 'gallery-small';
                }
                ?>

                <div class="gallery-item <?php echo esc_attr($grid_class); ?>" data-index="<?php echo $index; ?>">
                    <div class="gallery-image-wrapper">
                        <img src="<?php echo esc_url($image_thumb); ?>"
                             data-full="<?php echo esc_url($image_url); ?>"
                             alt="<?php echo esc_attr($image_alt ?: sprintf(__('Property image %d', 'minpaku-suite'), $index + 1)); ?>"
                             loading="<?php echo $index < 3 ? 'eager' : 'lazy'; ?>"
                             class="gallery-image">

                        <div class="gallery-overlay">
                            <button class="gallery-zoom-btn" data-image-index="<?php echo $index; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                                    <path d="M12 10h-2v2H9v-2H7V9h2V7h1v2h2v1z"/>
                                </svg>
                            </button>

                            <?php if ($image_caption): ?>
                                <div class="gallery-caption"><?php echo esc_html($image_caption); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($image_count > 8): ?>
                <div class="gallery-item gallery-more">
                    <div class="gallery-more-content">
                        <span class="more-count">+<?php echo $image_count - 8; ?></span>
                        <span class="more-text"><?php _e('More Photos', 'minpaku-suite'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lightbox Modal -->
    <div class="gallery-lightbox" id="galleryLightbox">
        <div class="lightbox-overlay"></div>
        <div class="lightbox-content">
            <button class="lightbox-close">&times;</button>
            <button class="lightbox-prev">‹</button>
            <button class="lightbox-next">›</button>
            <div class="lightbox-image-container">
                <img class="lightbox-image" src="" alt="">
                <div class="lightbox-caption"></div>
            </div>
            <div class="lightbox-counter">
                <span class="current-image">1</span> / <span class="total-images"><?php echo $image_count; ?></span>
            </div>
        </div>
    </div>
</section>

<style>
.minpaku-gallery-section {
    padding: 4rem 0;
    background: #fafafa;
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

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    grid-template-rows: repeat(3, 200px);
    gap: 15px;
    max-width: 1000px;
    margin: 0 auto;
}

.gallery-item {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.gallery-item:hover {
    transform: scale(1.02);
}

.gallery-featured {
    grid-column: span 6;
    grid-row: span 2;
}

.gallery-large {
    grid-column: span 3;
    grid-row: span 1;
}

.gallery-small {
    grid-column: span 3;
    grid-row: span 1;
}

.gallery-more {
    grid-column: span 3;
    grid-row: span 1;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.gallery-more-content {
    text-align: center;
}

.more-count {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.more-text {
    font-size: 1rem;
    opacity: 0.9;
}

.gallery-image-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
}

.gallery-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.gallery-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.3);
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

.gallery-item:hover .gallery-overlay {
    opacity: 1;
}

.gallery-zoom-btn {
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #333;
    cursor: pointer;
    transition: all 0.3s ease;
    transform: scale(0.8);
}

.gallery-item:hover .gallery-zoom-btn {
    transform: scale(1);
}

.gallery-zoom-btn:hover {
    background: white;
    transform: scale(1.1);
}

.gallery-caption {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
    color: white;
    padding: 20px 15px 15px;
    font-size: 0.9rem;
}

/* Lightbox Styles */
.gallery-lightbox {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    display: none;
    background: rgba(0, 0, 0, 0.95);
}

.gallery-lightbox.active {
    display: block;
}

.lightbox-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
}

.lightbox-content {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: none;
    border: none;
    color: white;
    font-size: 3rem;
    cursor: pointer;
    z-index: 10;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.3s ease;
}

.lightbox-close:hover {
    background: rgba(255, 255, 255, 0.1);
}

.lightbox-prev,
.lightbox-next {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    font-size: 2rem;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.lightbox-prev {
    left: 20px;
}

.lightbox-next {
    right: 20px;
}

.lightbox-prev:hover,
.lightbox-next:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-50%) scale(1.1);
}

.lightbox-image-container {
    max-width: 90%;
    max-height: 90%;
    text-align: center;
}

.lightbox-image {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
    border-radius: 8px;
}

.lightbox-caption {
    color: white;
    margin-top: 1rem;
    font-size: 1.1rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.lightbox-counter {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    color: white;
    background: rgba(0, 0, 0, 0.5);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .gallery-grid {
        grid-template-columns: repeat(6, 1fr);
        grid-template-rows: repeat(6, 150px);
        gap: 10px;
    }

    .gallery-featured {
        grid-column: span 6;
        grid-row: span 2;
    }

    .gallery-large,
    .gallery-small {
        grid-column: span 3;
        grid-row: span 1;
    }

    .section-title {
        font-size: 2rem;
    }

    .lightbox-prev,
    .lightbox-next {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }

    .lightbox-prev {
        left: 10px;
    }

    .lightbox-next {
        right: 10px;
    }
}

@media (max-width: 480px) {
    .gallery-grid {
        grid-template-columns: repeat(4, 1fr);
        grid-template-rows: repeat(8, 120px);
    }

    .gallery-featured {
        grid-column: span 4;
        grid-row: span 2;
    }

    .gallery-large,
    .gallery-small {
        grid-column: span 2;
        grid-row: span 1;
    }

    .minpaku-gallery-section {
        padding: 2rem 0;
    }

    .container {
        padding: 0 15px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const galleryItems = document.querySelectorAll('.gallery-item:not(.gallery-more)');
    const lightbox = document.getElementById('galleryLightbox');
    const lightboxImage = lightbox.querySelector('.lightbox-image');
    const lightboxCaption = lightbox.querySelector('.lightbox-caption');
    const lightboxClose = lightbox.querySelector('.lightbox-close');
    const lightboxPrev = lightbox.querySelector('.lightbox-prev');
    const lightboxNext = lightbox.querySelector('.lightbox-next');
    const currentImageSpan = lightbox.querySelector('.current-image');

    let currentImageIndex = 0;
    const images = [];

    // Collect all images data
    galleryItems.forEach((item, index) => {
        const img = item.querySelector('.gallery-image');
        const caption = item.querySelector('.gallery-caption');

        if (img) {
            images.push({
                full: img.dataset.full || img.src,
                alt: img.alt,
                caption: caption ? caption.textContent : ''
            });
        }
    });

    // Open lightbox
    function openLightbox(index) {
        currentImageIndex = index;
        updateLightboxImage();
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Close lightbox
    function closeLightbox() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Update lightbox image
    function updateLightboxImage() {
        const imageData = images[currentImageIndex];
        lightboxImage.src = imageData.full;
        lightboxImage.alt = imageData.alt;
        lightboxCaption.textContent = imageData.caption;
        currentImageSpan.textContent = currentImageIndex + 1;
    }

    // Navigate to previous image
    function prevImage() {
        currentImageIndex = (currentImageIndex - 1 + images.length) % images.length;
        updateLightboxImage();
    }

    // Navigate to next image
    function nextImage() {
        currentImageIndex = (currentImageIndex + 1) % images.length;
        updateLightboxImage();
    }

    // Event listeners
    galleryItems.forEach((item, index) => {
        item.addEventListener('click', () => openLightbox(index));
    });

    lightboxClose.addEventListener('click', closeLightbox);
    lightboxPrev.addEventListener('click', prevImage);
    lightboxNext.addEventListener('click', nextImage);

    // Close on overlay click
    lightbox.querySelector('.lightbox-overlay').addEventListener('click', closeLightbox);

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (!lightbox.classList.contains('active')) return;

        switch(e.key) {
            case 'Escape':
                closeLightbox();
                break;
            case 'ArrowLeft':
                prevImage();
                break;
            case 'ArrowRight':
                nextImage();
                break;
        }
    });

    // Touch/swipe support for mobile
    let touchStartX = 0;
    let touchEndX = 0;

    lightbox.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    });

    lightbox.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });

    function handleSwipe() {
        const swipeThreshold = 50;
        const diff = touchStartX - touchEndX;

        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                nextImage(); // Swipe left - next image
            } else {
                prevImage(); // Swipe right - previous image
            }
        }
    }
});
</script>