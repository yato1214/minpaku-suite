<?php
/**
 * Official Site Template - Quote Section
 *
 * @var int $property_id
 * @var WP_Post $property
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<section class="minpaku-quote-section" data-section="quote" id="quote">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php _e('Get Quote & Book', 'minpaku-suite'); ?></h2>
            <p class="section-subtitle"><?php _e('Request a personalized quote for your stay', 'minpaku-suite'); ?></p>
        </div>

        <div class="quote-wrapper">
            <div class="quote-container">
                <?php echo do_shortcode('[minpaku_quote property_id="' . $property_id . '"]'); ?>
            </div>
        </div>
    </div>
</section>