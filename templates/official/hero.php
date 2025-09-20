<?php
/**
 * Official Site Template - Hero Section
 *
 * @var int $property_id
 * @var WP_Post $property
 */

if (!defined('ABSPATH')) {
    exit;
}

$title = get_the_title($property_id);
$location = get_post_meta($property_id, '_minpaku_location', true);
$featured_image = get_the_post_thumbnail_url($property_id, 'full');
$description = wp_trim_words(get_post_field('post_content', $property_id), 30);
?>

<section class="minpaku-hero-section" data-section="hero">
    <div class="hero-background" style="background-image: url('<?php echo esc_url($featured_image); ?>');">
        <div class="hero-overlay"></div>
    </div>

    <div class="hero-content-wrapper">
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title"><?php echo esc_html($title); ?></h1>

                <?php if ($location): ?>
                    <div class="hero-location">
                        <svg class="location-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        <span><?php echo esc_html($location); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($description): ?>
                    <p class="hero-description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>

                <div class="hero-actions">
                    <a href="#calendar" class="btn btn-primary hero-cta">
                        <?php _e('Check Availability', 'minpaku-suite'); ?>
                    </a>
                    <a href="#gallery" class="btn btn-secondary hero-gallery-btn">
                        <?php _e('View Gallery', 'minpaku-suite'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.minpaku-hero-section {
    position: relative;
    height: 60vh;
    min-height: 500px;
    max-height: 800px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.6));
}

.hero-content-wrapper {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.hero-content {
    text-align: center;
    color: white;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    line-height: 1.2;
}

.hero-location {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 1.2rem;
    margin-bottom: 1.5rem;
    opacity: 0.95;
}

.location-icon {
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
}

.hero-description {
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto 2rem;
    line-height: 1.6;
    opacity: 0.95;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.hero-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
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
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
}

.btn-primary:hover {
    background: #ff5252;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
    color: white;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(10px);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
    color: white;
}

@media (max-width: 768px) {
    .minpaku-hero-section {
        height: 50vh;
        min-height: 400px;
    }

    .hero-title {
        font-size: 2.5rem;
    }

    .hero-location {
        font-size: 1rem;
    }

    .hero-description {
        font-size: 1rem;
    }

    .hero-actions {
        flex-direction: column;
        align-items: center;
    }

    .btn {
        width: 100%;
        max-width: 280px;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 2rem;
    }

    .hero-content-wrapper {
        padding: 0 15px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scroll for CTA buttons
    const ctaButtons = document.querySelectorAll('.hero-cta, .hero-gallery-btn');

    ctaButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // Parallax effect for hero background
    const heroSection = document.querySelector('.minpaku-hero-section');
    const heroBackground = document.querySelector('.hero-background');

    if (heroSection && heroBackground) {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const parallax = scrolled * 0.5;
            heroBackground.style.transform = `translate3d(0, ${parallax}px, 0)`;
        });
    }
});
</script>