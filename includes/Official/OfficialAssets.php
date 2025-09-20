<?php

namespace Minpaku\Official;

class OfficialAssets {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_head', [$this, 'addInlineStyles']);
        add_action('wp_footer', [$this, 'addInlineScripts']);
    }

    public function enqueueAssets() {
        $rewrite = new OfficialRewrite();

        if (!$rewrite->isOfficialPageRequest()) {
            return;
        }

        wp_enqueue_style(
            'minpaku-official-styles',
            $this->getAssetUrl('official-styles.css'),
            [],
            $this->getAssetVersion()
        );

        wp_enqueue_script(
            'minpaku-official-scripts',
            $this->getAssetUrl('official-scripts.js'),
            ['jquery'],
            $this->getAssetVersion(),
            true
        );

        $property_id = $rewrite->getCurrentPropertyId();

        wp_localize_script('minpaku-official-scripts', 'minpakuOfficial', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('minpaku_official_nonce'),
            'propertyId' => $property_id,
            'i18n' => [
                'loading' => __('Loading...', 'minpaku-suite'),
                'error' => __('An error occurred. Please try again.', 'minpaku-suite'),
                'success' => __('Success!', 'minpaku-suite'),
                'noAvailability' => __('No availability data found.', 'minpaku-suite'),
                'selectDates' => __('Please select check-in and check-out dates.', 'minpaku-suite'),
                'minStayRequired' => __('Minimum stay requirement not met.', 'minpaku-suite'),
                'maxStayExceeded' => __('Maximum stay limit exceeded.', 'minpaku-suite'),
                'invalidDates' => __('Invalid date selection.', 'minpaku-suite'),
                'quoteRequested' => __('Quote request sent successfully.', 'minpaku-suite'),
                'months' => [
                    __('January', 'minpaku-suite'),
                    __('February', 'minpaku-suite'),
                    __('March', 'minpaku-suite'),
                    __('April', 'minpaku-suite'),
                    __('May', 'minpaku-suite'),
                    __('June', 'minpaku-suite'),
                    __('July', 'minpaku-suite'),
                    __('August', 'minpaku-suite'),
                    __('September', 'minpaku-suite'),
                    __('October', 'minpaku-suite'),
                    __('November', 'minpaku-suite'),
                    __('December', 'minpaku-suite')
                ],
                'days' => [
                    __('Sunday', 'minpaku-suite'),
                    __('Monday', 'minpaku-suite'),
                    __('Tuesday', 'minpaku-suite'),
                    __('Wednesday', 'minpaku-suite'),
                    __('Thursday', 'minpaku-suite'),
                    __('Friday', 'minpaku-suite'),
                    __('Saturday', 'minpaku-suite')
                ],
                'daysShort' => [
                    __('Su', 'minpaku-suite'),
                    __('Mo', 'minpaku-suite'),
                    __('Tu', 'minpaku-suite'),
                    __('We', 'minpaku-suite'),
                    __('Th', 'minpaku-suite'),
                    __('Fr', 'minpaku-suite'),
                    __('Sa', 'minpaku-suite')
                ]
            ]
        ]);

        wp_enqueue_script('wp-util');
    }

    public function addInlineStyles() {
        $rewrite = new OfficialRewrite();

        if (!$rewrite->isOfficialPageRequest()) {
            return;
        }

        ?>
        <style id="minpaku-official-inline-styles">
        :root {
            --minpaku-primary: #ff6b6b;
            --minpaku-primary-light: #ff8e53;
            --minpaku-primary-dark: #ff5252;
            --minpaku-secondary: #667eea;
            --minpaku-secondary-dark: #764ba2;
            --minpaku-success: #28a745;
            --minpaku-danger: #dc3545;
            --minpaku-warning: #ffc107;
            --minpaku-info: #17a2b8;
            --minpaku-light: #f8f9fa;
            --minpaku-dark: #333333;
            --minpaku-border: #e9ecef;
            --minpaku-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            --minpaku-shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.15);
            --minpaku-radius: 8px;
            --minpaku-radius-lg: 12px;
            --minpaku-transition: all 0.3s ease;
        }

        .minpaku-official-page-wrapper {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: var(--minpaku-dark);
        }

        .minpaku-official-page-wrapper * {
            box-sizing: border-box;
        }

        .minpaku-official-page-wrapper h1,
        .minpaku-official-page-wrapper h2,
        .minpaku-official-page-wrapper h3,
        .minpaku-official-page-wrapper h4,
        .minpaku-official-page-wrapper h5,
        .minpaku-official-page-wrapper h6 {
            line-height: 1.2;
            color: var(--minpaku-dark);
        }

        .minpaku-official-page-wrapper img {
            max-width: 100%;
            height: auto;
        }

        .minpaku-official-page-wrapper .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .minpaku-official-page-wrapper .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 12px 24px;
            border-radius: var(--minpaku-radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--minpaku-transition);
            cursor: pointer;
            border: none;
            white-space: nowrap;
        }

        .minpaku-official-page-wrapper .btn:focus {
            outline: 2px solid var(--minpaku-primary);
            outline-offset: 2px;
        }

        .minpaku-official-page-wrapper .btn-primary {
            background: var(--minpaku-primary);
            color: white;
        }

        .minpaku-official-page-wrapper .btn-primary:hover {
            background: var(--minpaku-primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--minpaku-shadow);
            color: white;
        }

        .minpaku-official-page-wrapper .btn-secondary {
            background: var(--minpaku-secondary);
            color: white;
        }

        .minpaku-official-page-wrapper .btn-secondary:hover {
            background: var(--minpaku-secondary-dark);
            transform: translateY(-2px);
            color: white;
        }

        .minpaku-official-page-wrapper .btn-outline {
            background: transparent;
            color: var(--minpaku-primary);
            border: 2px solid var(--minpaku-primary);
        }

        .minpaku-official-page-wrapper .btn-outline:hover {
            background: var(--minpaku-primary);
            color: white;
        }

        .minpaku-official-page-wrapper .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--minpaku-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .minpaku-official-page-wrapper .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .minpaku-official-page-wrapper .slide-up {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Accessibility improvements */
        .minpaku-official-page-wrapper .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .minpaku-official-page-wrapper {
                --minpaku-primary: #000080;
                --minpaku-primary-dark: #000066;
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .minpaku-official-page-wrapper *,
            .minpaku-official-page-wrapper *::before,
            .minpaku-official-page-wrapper *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .minpaku-official-page-wrapper {
                --minpaku-dark: #ffffff;
                --minpaku-light: #1a1a1a;
                --minpaku-border: #333333;
            }

            .minpaku-official-page-wrapper .hero-overlay {
                background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.8));
            }
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .minpaku-official-page-wrapper .container {
                padding: 0 15px;
            }

            .minpaku-official-page-wrapper .btn {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }

        /* Print styles */
        @media print {
            .minpaku-official-page-wrapper .btn,
            .minpaku-official-page-wrapper .calendar-container,
            .minpaku-official-page-wrapper .quote-container {
                display: none;
            }

            .minpaku-official-page-wrapper {
                font-size: 12pt;
                line-height: 1.4;
            }

            .minpaku-official-page-wrapper .hero-background {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
        </style>
        <?php
    }

    public function addInlineScripts() {
        $rewrite = new OfficialRewrite();

        if (!$rewrite->isOfficialPageRequest()) {
            return;
        }

        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize smooth scrolling for anchor links
            const anchorLinks = document.querySelectorAll('a[href^="#"]');
            anchorLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#') return;

                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });

                        // Update URL without jumping
                        if (history.pushState) {
                            history.pushState(null, null, href);
                        }
                    }
                });
            });

            // Initialize intersection observer for animations
            const observerOptions = {
                root: null,
                rootMargin: '0px 0px -100px 0px',
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                    }
                });
            }, observerOptions);

            // Observe all sections
            const sections = document.querySelectorAll('[data-section]');
            sections.forEach(section => {
                observer.observe(section);
            });

            // Initialize lazy loading for images
            const images = document.querySelectorAll('img[loading="lazy"]');
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                            }
                            imageObserver.unobserve(img);
                        }
                    });
                });

                images.forEach(img => {
                    imageObserver.observe(img);
                });
            }

            // Add loading states to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                const originalClick = button.onclick;
                button.addEventListener('click', function(e) {
                    if (this.classList.contains('loading')) {
                        e.preventDefault();
                        return false;
                    }

                    // Add loading state if it's an async action
                    if (this.dataset.async === 'true') {
                        this.classList.add('loading');
                        const originalText = this.innerHTML;
                        this.innerHTML = '<span class="loading-spinner"></span> ' + minpakuOfficial.i18n.loading;

                        // Remove loading state after 3 seconds if not manually removed
                        setTimeout(() => {
                            if (this.classList.contains('loading')) {
                                this.classList.remove('loading');
                                this.innerHTML = originalText;
                            }
                        }, 3000);
                    }
                });
            });

            // Initialize form validation helpers
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;

                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.classList.add('error');
                            isValid = false;
                        } else {
                            field.classList.remove('error');
                        }
                    });

                    if (!isValid) {
                        e.preventDefault();
                        const firstError = form.querySelector('.error');
                        if (firstError) {
                            firstError.focus();
                            firstError.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                    }
                });
            });

            // Initialize accessibility enhancements
            const focusableElements = document.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            // Add focus visible polyfill for older browsers
            focusableElements.forEach(element => {
                element.addEventListener('mousedown', () => {
                    element.classList.add('mouse-focus');
                });

                element.addEventListener('keydown', () => {
                    element.classList.remove('mouse-focus');
                });
            });

            // Initialize skip links
            const skipLink = document.createElement('a');
            skipLink.href = '#main-content';
            skipLink.textContent = minpakuOfficial.i18n.skipToContent || 'Skip to main content';
            skipLink.className = 'sr-only skip-link';
            skipLink.style.cssText = `
                position: absolute;
                top: -40px;
                left: 6px;
                background: var(--minpaku-primary);
                color: white;
                padding: 8px;
                text-decoration: none;
                z-index: 100;
                border-radius: 4px;
            `;

            skipLink.addEventListener('focus', function() {
                this.style.top = '6px';
            });

            skipLink.addEventListener('blur', function() {
                this.style.top = '-40px';
            });

            document.body.insertBefore(skipLink, document.body.firstChild);

            // Add main content ID if it doesn't exist
            const mainContent = document.querySelector('.minpaku-official-page-wrapper');
            if (mainContent && !mainContent.id) {
                mainContent.id = 'main-content';
            }

            console.log('Minpaku Official Site initialized');
        });

        // Utility functions
        window.MinpakuOfficial = {
            showNotification: function(message, type = 'success') {
                const notification = document.createElement('div');
                notification.className = `minpaku-notification minpaku-notification-${type}`;
                notification.textContent = message;
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${type === 'success' ? 'var(--minpaku-success)' : 'var(--minpaku-danger)'};
                    color: white;
                    padding: 12px 20px;
                    border-radius: var(--minpaku-radius);
                    box-shadow: var(--minpaku-shadow);
                    z-index: 1000;
                    max-width: 300px;
                    opacity: 0;
                    transform: translateX(100%);
                    transition: var(--minpaku-transition);
                `;

                document.body.appendChild(notification);

                // Animate in
                setTimeout(() => {
                    notification.style.opacity = '1';
                    notification.style.transform = 'translateX(0)';
                }, 100);

                // Animate out and remove
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }, 3000);
            },

            toggleLoading: function(element, loading = true) {
                if (loading) {
                    element.classList.add('loading');
                    element.disabled = true;
                    const originalText = element.innerHTML;
                    element.dataset.originalText = originalText;
                    element.innerHTML = '<span class="loading-spinner"></span> ' + minpakuOfficial.i18n.loading;
                } else {
                    element.classList.remove('loading');
                    element.disabled = false;
                    if (element.dataset.originalText) {
                        element.innerHTML = element.dataset.originalText;
                        delete element.dataset.originalText;
                    }
                }
            }
        };
        </script>
        <?php
    }

    private function getAssetUrl($filename) {
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        return $plugin_url . 'assets/official/' . $filename;
    }

    private function getAssetVersion() {
        return defined('WP_DEBUG') && WP_DEBUG ? time() : MINPAKU_VERSION;
    }

    public function createAssetFiles() {
        $assets_dir = dirname(dirname(__DIR__)) . '/assets/official';

        if (!file_exists($assets_dir)) {
            wp_mkdir_p($assets_dir);
        }

        $this->createCSSFile($assets_dir);
        $this->createJSFile($assets_dir);
    }

    private function createCSSFile($assets_dir) {
        $css_content = "/* Minpaku Official Site Styles - Generated by OfficialAssets */\n";
        $css_content .= "/* Additional styles can be added here */\n";

        file_put_contents($assets_dir . '/official-styles.css', $css_content);
    }

    private function createJSFile($assets_dir) {
        $js_content = "/* Minpaku Official Site Scripts - Generated by OfficialAssets */\n";
        $js_content .= "/* Additional JavaScript can be added here */\n";

        file_put_contents($assets_dir . '/official-scripts.js', $js_content);
    }
}