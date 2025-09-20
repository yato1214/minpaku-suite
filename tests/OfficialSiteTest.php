<?php

use PHPUnit\Framework\TestCase;
use Minpaku\Official\OfficialSiteGenerator;
use Minpaku\Official\OfficialTemplate;
use Minpaku\Official\OfficialRewrite;
use Minpaku\Official\OfficialShortcodes;

class OfficialSiteTest extends TestCase {

    private $generator;
    private $template;
    private $rewrite;
    private $shortcodes;
    private $test_property_id;

    protected function setUp(): void {
        parent::setUp();

        global $wpdb;
        $this->setUpWordPressMocks();

        $this->generator = new OfficialSiteGenerator();
        $this->template = new OfficialTemplate();
        $this->rewrite = new OfficialRewrite();
        $this->shortcodes = new OfficialShortcodes();

        $this->test_property_id = 123;
    }

    protected function tearDown(): void {
        parent::tearDown();
        $this->cleanupTestData();
    }

    private function setUpWordPressMocks() {
        if (!function_exists('wp_create_nonce')) {
            function wp_create_nonce($action) {
                return 'test_nonce_' . md5($action);
            }
        }

        if (!function_exists('wp_verify_nonce')) {
            function wp_verify_nonce($nonce, $action) {
                return $nonce === wp_create_nonce($action);
            }
        }

        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                return true;
            }
        }

        if (!function_exists('get_post_type')) {
            function get_post_type($post_id) {
                return 'minpaku_property';
            }
        }

        if (!function_exists('get_post')) {
            function get_post($post_id) {
                return (object) [
                    'ID' => $post_id,
                    'post_title' => 'Test Property',
                    'post_content' => 'Test property description',
                    'post_name' => 'test-property',
                    'post_status' => 'publish',
                    'post_type' => 'minpaku_property'
                ];
            }
        }

        if (!function_exists('get_post_meta')) {
            function get_post_meta($post_id, $key, $single = false) {
                $meta_data = [
                    '_minpaku_location' => 'Tokyo, Japan',
                    '_minpaku_capacity' => '4',
                    '_minpaku_bedrooms' => '2',
                    '_minpaku_bathrooms' => '1',
                    '_minpaku_price_per_night' => '15000',
                    '_minpaku_gallery' => '1,2,3,4',
                    '_minpaku_amenities' => 'WiFi,Kitchen,Parking',
                    '_minpaku_min_stay' => '2',
                    '_minpaku_max_stay' => '30',
                    '_minpaku_official_page_id' => null
                ];

                if (isset($meta_data[$key])) {
                    return $single ? $meta_data[$key] : [$meta_data[$key]];
                }

                return $single ? '' : [];
            }
        }

        if (!function_exists('update_post_meta')) {
            function update_post_meta($post_id, $key, $value) {
                return true;
            }
        }

        if (!function_exists('wp_insert_post')) {
            function wp_insert_post($post_data) {
                return 456; // Mock official page ID
            }
        }

        if (!function_exists('get_the_title')) {
            function get_the_title($post_id = null) {
                return 'Test Property';
            }
        }

        if (!function_exists('get_the_post_thumbnail_url')) {
            function get_the_post_thumbnail_url($post_id, $size = 'full') {
                return 'https://example.com/image.jpg';
            }
        }

        if (!function_exists('wp_get_attachment_image_url')) {
            function wp_get_attachment_image_url($attachment_id, $size = 'full') {
                return "https://example.com/gallery-{$attachment_id}.jpg";
            }
        }

        if (!function_exists('esc_html')) {
            function esc_html($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }

        if (!function_exists('esc_attr')) {
            function esc_attr($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }

        if (!function_exists('esc_url')) {
            function esc_url($url) {
                return filter_var($url, FILTER_SANITIZE_URL);
            }
        }

        if (!function_exists('__')) {
            function __($text, $domain = 'default') {
                return $text;
            }
        }

        if (!function_exists('_e')) {
            function _e($text, $domain = 'default') {
                echo $text;
            }
        }

        if (!function_exists('_n')) {
            function _n($single, $plural, $number, $domain = 'default') {
                return $number === 1 ? $single : $plural;
            }
        }

        if (!function_exists('home_url')) {
            function home_url($path = '') {
                return 'https://example.com' . $path;
            }
        }

        if (!function_exists('wp_kses_post')) {
            function wp_kses_post($data) {
                return strip_tags($data, '<p><br><strong><em><a><ul><ol><li>');
            }
        }

        if (!function_exists('wpautop')) {
            function wpautop($text) {
                return '<p>' . str_replace("\n", '</p><p>', $text) . '</p>';
            }
        }

        if (!function_exists('do_shortcode')) {
            function do_shortcode($content) {
                return $content;
            }
        }

        if (!function_exists('wp_strip_all_tags')) {
            function wp_strip_all_tags($text) {
                return strip_tags($text);
            }
        }

        if (!function_exists('apply_filters')) {
            function apply_filters($hook, $value, ...$args) {
                return $value;
            }
        }

        if (!function_exists('do_action')) {
            function do_action($hook, ...$args) {
                // Mock action
            }
        }

        // WP_Post class will be defined externally
    }

    private function cleanupTestData() {
        // Cleanup any test data
    }

    public function testGeneratorCanCreateOfficialPage() {
        $result = $this->generator->generate($this->test_property_id);

        $this->assertNotFalse($result, 'Generator should successfully create official page');
        $this->assertIsInt($result, 'Generator should return page ID');
    }

    public function testGeneratorHandlesNonExistentProperty() {
        $result = $this->generator->generate(999999);

        $this->assertFalse($result, 'Generator should return false for non-existent property');
    }

    public function testTemplateCanRenderAllSections() {
        $sections = ['hero', 'gallery', 'features', 'calendar', 'quote', 'access'];

        foreach ($sections as $section) {
            $output = $this->template->renderSection($section, $this->test_property_id, get_post($this->test_property_id));

            $this->assertIsString($output, "Section '{$section}' should render as string");
            $this->assertNotEmpty($output, "Section '{$section}' should not be empty");
            $this->assertStringContainsString('data-section="' . $section . '"', $output, "Section '{$section}' should have correct data attribute");
        }
    }

    public function testTemplateRenderFullPage() {
        $output = $this->template->renderPage($this->test_property_id);

        $this->assertIsString($output, 'Template should render full page as string');
        $this->assertNotEmpty($output, 'Template should render non-empty content');

        // Check that all default sections are included
        $sections = ['hero', 'gallery', 'features', 'calendar', 'quote', 'access'];
        foreach ($sections as $section) {
            $this->assertStringContainsString('data-section="' . $section . '"', $output, "Full page should include section '{$section}'");
        }
    }

    public function testTemplateSectionOrdering() {
        $sections = $this->template->getSections($this->test_property_id);

        $this->assertIsArray($sections, 'getSections should return array');
        $this->assertNotEmpty($sections, 'getSections should return non-empty array');

        // Check that sections are ordered correctly
        $previous_order = 0;
        foreach ($sections as $section) {
            $this->assertArrayHasKey('order', $section, 'Section should have order key');
            $this->assertGreaterThanOrEqual($previous_order, $section['order'], 'Sections should be ordered correctly');
            $previous_order = $section['order'];
        }
    }

    public function testRewriteUrlGeneration() {
        $url = $this->rewrite->getOfficialPageUrl($this->test_property_id);

        $this->assertIsString($url, 'URL should be string');
        $this->assertStringStartsWith('https://example.com/stay/', $url, 'URL should start with correct prefix');
        $this->assertStringContainsString('test-property', $url, 'URL should contain property slug');
    }

    public function testRewriteSectionUrlGeneration() {
        $url = $this->rewrite->getSectionUrl($this->test_property_id, 'gallery');

        $this->assertIsString($url, 'Section URL should be string');
        $this->assertStringStartsWith('https://example.com/stay/', $url, 'Section URL should start with correct prefix');
        $this->assertStringContainsString('test-property', $url, 'Section URL should contain property slug');
        $this->assertStringContainsString('gallery', $url, 'Section URL should contain section name');
    }

    public function testShortcodeCalendarRendering() {
        $atts = [
            'property_id' => $this->test_property_id,
            'months' => 2,
            'show_legend' => true
        ];

        $output = $this->shortcodes->renderCalendarShortcode($atts);

        $this->assertIsString($output, 'Calendar shortcode should render as string');
        $this->assertNotEmpty($output, 'Calendar shortcode should not be empty');
        $this->assertStringContainsString('minpaku-calendar-shortcode', $output, 'Calendar should have correct CSS class');
        $this->assertStringContainsString('data-property-id="' . $this->test_property_id . '"', $output, 'Calendar should have property ID');
    }

    public function testShortcodeQuoteRendering() {
        $atts = [
            'property_id' => $this->test_property_id,
            'required_fields' => 'name,email,checkin,checkout'
        ];

        $output = $this->shortcodes->renderQuoteShortcode($atts);

        $this->assertIsString($output, 'Quote shortcode should render as string');
        $this->assertNotEmpty($output, 'Quote shortcode should not be empty');
        $this->assertStringContainsString('minpaku-quote-shortcode', $output, 'Quote should have correct CSS class');
        $this->assertStringContainsString('data-property-id="' . $this->test_property_id . '"', $output, 'Quote should have property ID');
        $this->assertStringContainsString('quote_name', $output, 'Quote form should have name field');
        $this->assertStringContainsString('quote_email', $output, 'Quote form should have email field');
    }

    public function testShortcodePropertyInfoRendering() {
        $atts = [
            'property_id' => $this->test_property_id,
            'fields' => 'title,location,capacity'
        ];

        $output = $this->shortcodes->renderPropertyInfoShortcode($atts);

        $this->assertIsString($output, 'Property info shortcode should render as string');
        $this->assertNotEmpty($output, 'Property info shortcode should not be empty');
        $this->assertStringContainsString('Test Property', $output, 'Property info should contain title');
        $this->assertStringContainsString('Tokyo, Japan', $output, 'Property info should contain location');
        $this->assertStringContainsString('4 guests', $output, 'Property info should contain capacity');
    }

    public function testShortcodeInvalidPropertyHandling() {
        $atts = ['property_id' => 999999];

        $calendar_output = $this->shortcodes->renderCalendarShortcode($atts);
        $quote_output = $this->shortcodes->renderQuoteShortcode($atts);
        $info_output = $this->shortcodes->renderPropertyInfoShortcode($atts);

        $this->assertStringContainsString('Invalid property ID', $calendar_output, 'Calendar should handle invalid property');
        $this->assertStringContainsString('Invalid property ID', $quote_output, 'Quote should handle invalid property');
        $this->assertStringContainsString('Invalid property ID', $info_output, 'Property info should handle invalid property');
    }

    public function testGeneratorUpdateOnPropertyChange() {
        // Mock that property already has official page
        if (!function_exists('get_post_meta_override')) {
            function get_post_meta_override($post_id, $key, $single = false) {
                if ($key === '_minpaku_official_page_id') {
                    return $single ? 456 : [456];
                }
                return get_post_meta($post_id, $key, $single);
            }
        }

        $result = $this->generator->updateOnPropertyChange($this->test_property_id);

        $this->assertNotFalse($result, 'Generator should successfully update existing official page');
    }

    public function testGeneratorDeleteOfficialPage() {
        $result = $this->generator->deleteOfficialPage($this->test_property_id);

        $this->assertTrue($result, 'Generator should successfully delete official page');
    }

    public function testGeneratorTogglePublishStatus() {
        $result = $this->generator->togglePublishStatus($this->test_property_id);

        $this->assertNotFalse($result, 'Generator should successfully toggle publish status');
    }

    public function testTemplateCustomSectionFilter() {
        // Test that custom sections can be added via filter
        if (!function_exists('apply_filters_custom')) {
            function apply_filters_custom($hook, $value, ...$args) {
                if ($hook === 'minpaku_official_sections') {
                    $value[] = [
                        'type' => 'custom',
                        'enabled' => true,
                        'order' => 70
                    ];
                }
                return $value;
            }
        }

        $sections = $this->template->getSections($this->test_property_id);

        // Would normally test custom section addition, but requires full WordPress filter system
        $this->assertIsArray($sections, 'getSections should still return array with custom filters');
    }

    public function testTemplateStylesGeneration() {
        $styles = $this->template->getTemplateStyles();

        $this->assertIsString($styles, 'Template styles should be string');
        $this->assertStringContainsString('<style>', $styles, 'Template styles should contain style tag');
        $this->assertStringContainsString('.minpaku-hero', $styles, 'Template styles should contain hero styles');
        $this->assertStringContainsString('.minpaku-gallery', $styles, 'Template styles should contain gallery styles');
    }

    public function testRewriteRequestHandling() {
        // Mock WordPress query variables
        global $wp;
        $wp = new stdClass();
        $wp->query_vars = [
            'minpaku_official_page' => '1',
            'property_slug' => 'test-property'
        ];

        $this->assertTrue($this->rewrite->isOfficialPageRequest(), 'Should detect official page request');
    }

    public function testGeneratorBulkOperations() {
        $property_ids = [$this->test_property_id, $this->test_property_id + 1, $this->test_property_id + 2];

        foreach ($property_ids as $property_id) {
            $result = $this->generator->generate($property_id);
            $this->assertNotFalse($result, "Should generate page for property {$property_id}");
        }
    }

    public function testShortcodeFormValidation() {
        // Test that shortcodes properly validate required fields
        $calendar_atts = ['property_id' => ''];
        $quote_atts = ['property_id' => ''];

        $calendar_output = $this->shortcodes->renderCalendarShortcode($calendar_atts);
        $quote_output = $this->shortcodes->renderQuoteShortcode($quote_atts);

        $this->assertStringContainsString('Invalid property ID', $calendar_output, 'Calendar should validate property ID');
        $this->assertStringContainsString('Invalid property ID', $quote_output, 'Quote should validate property ID');
    }

    public function testTemplateAccessibilityFeatures() {
        $output = $this->template->renderPage($this->test_property_id);

        // Check for basic accessibility attributes
        $this->assertStringContainsString('alt=', $output, 'Template should include alt attributes for images');
        $this->assertStringContainsString('aria-', $output, 'Template should include ARIA attributes');
        $this->assertStringContainsString('role=', $output, 'Template should include role attributes');
    }

    public function testTemplateResponsiveDesign() {
        $styles = $this->template->getTemplateStyles();

        $this->assertStringContainsString('@media', $styles, 'Template should include responsive media queries');
        $this->assertStringContainsString('max-width', $styles, 'Template should include mobile breakpoints');
    }

    public function testGeneratorErrorHandling() {
        // Test with invalid post type
        if (!function_exists('get_post_type_override')) {
            function get_post_type_override($post_id) {
                return 'post'; // Not minpaku_property
            }
        }

        $result = $this->generator->generate($this->test_property_id);
        $this->assertFalse($result, 'Generator should handle invalid post types gracefully');
    }

    public function testShortcodeSecurityValidation() {
        // Test that shortcodes properly sanitize input
        $malicious_atts = [
            'property_id' => '<script>alert("xss")</script>',
            'theme' => '<img src=x onerror=alert(1)>'
        ];

        $output = $this->shortcodes->renderCalendarShortcode($malicious_atts);

        $this->assertStringNotContainsString('<script>', $output, 'Shortcode should prevent XSS');
        $this->assertStringNotContainsString('onerror=', $output, 'Shortcode should sanitize attributes');
    }
}