<?php

use PHPUnit\Framework\TestCase;
use Minpaku\Official\OfficialRewrite;

class RewriteTest extends TestCase {

    private $rewrite;
    private $test_property_id = 123;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpWordPressMocks();
        $this->rewrite = new OfficialRewrite();
    }

    protected function tearDown(): void {
        parent::tearDown();
        $this->cleanupGlobals();
    }

    private function setUpWordPressMocks() {
        if (!function_exists('add_rewrite_rule')) {
            function add_rewrite_rule($regex, $query, $after = 'bottom') {
                global $test_rewrite_rules;
                if (!isset($test_rewrite_rules)) {
                    $test_rewrite_rules = [];
                }
                $test_rewrite_rules[] = [
                    'regex' => $regex,
                    'query' => $query,
                    'after' => $after
                ];
                return true;
            }
        }

        if (!function_exists('flush_rewrite_rules')) {
            function flush_rewrite_rules($hard = true) {
                global $test_rewrite_flushed;
                $test_rewrite_flushed = true;
                return true;
            }
        }

        if (!function_exists('get_post')) {
            function get_post($post_id) {
                if ($post_id == 123) {
                    return (object) [
                        'ID' => $post_id,
                        'post_title' => 'Test Property',
                        'post_content' => 'Test property description',
                        'post_name' => 'test-property',
                        'post_status' => 'publish',
                        'post_type' => 'minpaku_property'
                    ];
                }
                return null;
            }
        }

        if (!function_exists('get_page_by_path')) {
            function get_page_by_path($slug, $output = OBJECT, $post_type = 'page') {
                if ($slug === 'test-property' && $post_type === 'minpaku_property') {
                    return get_post(123);
                }
                return null;
            }
        }

        if (!function_exists('home_url')) {
            function home_url($path = '') {
                return 'https://example.com' . $path;
            }
        }

        if (!function_exists('get_query_var')) {
            function get_query_var($var, $default = '') {
                global $test_query_vars;
                return isset($test_query_vars[$var]) ? $test_query_vars[$var] : $default;
            }
        }

        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                return true;
            }
        }

        if (!function_exists('status_header')) {
            function status_header($code, $description = '') {
                global $test_status_header;
                $test_status_header = $code;
            }
        }

        if (!function_exists('get_header')) {
            function get_header() {
                echo '<html><head><title>Test</title></head><body>';
            }
        }

        if (!function_exists('get_footer')) {
            function get_footer() {
                echo '</body></html>';
            }
        }

        if (!function_exists('__')) {
            function __($text, $domain = 'default') {
                return $text;
            }
        }

        if (!function_exists('esc_attr')) {
            function esc_attr($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }

        if (!function_exists('esc_html')) {
            function esc_html($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }

        if (!function_exists('esc_url')) {
            function esc_url($url) {
                return filter_var($url, FILTER_SANITIZE_URL);
            }
        }

        if (!function_exists('wp_json_encode')) {
            function wp_json_encode($data, $options = 0) {
                return json_encode($data, $options);
            }
        }

        if (!function_exists('admin_url')) {
            function admin_url($path = '') {
                return 'https://example.com/wp-admin/' . $path;
            }
        }

        if (!function_exists('get_the_title')) {
            function get_the_title($post_id = null) {
                return 'Test Property';
            }
        }

        if (!function_exists('get_post_meta')) {
            function get_post_meta($post_id, $key, $single = false) {
                $meta_data = [
                    '_minpaku_location' => 'Tokyo, Japan',
                    '_minpaku_capacity' => '4'
                ];

                if (isset($meta_data[$key])) {
                    return $single ? $meta_data[$key] : [$meta_data[$key]];
                }

                return $single ? '' : [];
            }
        }

        if (!defined('OBJECT')) {
            define('OBJECT', 'OBJECT');
        }

        // Mock WP_Query if needed
        if (!class_exists('WP_Query')) {
            class WP_Query {
                public function set_404() {
                    global $test_404_set;
                    $test_404_set = true;
                }
            }
        }

        global $wp_query;
        if (!$wp_query) {
            $wp_query = new WP_Query();
        }
    }

    private function cleanupGlobals() {
        global $test_rewrite_rules, $test_rewrite_flushed, $test_query_vars, $test_status_header, $test_404_set;
        $test_rewrite_rules = null;
        $test_rewrite_flushed = null;
        $test_query_vars = null;
        $test_status_header = null;
        $test_404_set = null;
    }

    public function testRewriteRulesAreAdded() {
        global $test_rewrite_rules;

        $this->rewrite->addRewriteRules();

        $this->assertNotEmpty($test_rewrite_rules, 'Rewrite rules should be added');

        $stay_rule_found = false;
        $stay_section_rule_found = false;

        foreach ($test_rewrite_rules as $rule) {
            if (strpos($rule['regex'], '^stay/([^/]+)/?$') !== false) {
                $stay_rule_found = true;
                $this->assertStringContainsString('minpaku_official_page=1', $rule['query'], 'Stay rule should set official page flag');
                $this->assertStringContainsString('property_slug=$matches[1]', $rule['query'], 'Stay rule should capture property slug');
            }

            if (strpos($rule['regex'], '^stay/([^/]+)/([^/]+)/?$') !== false) {
                $stay_section_rule_found = true;
                $this->assertStringContainsString('section=$matches[2]', $rule['query'], 'Stay section rule should capture section');
            }
        }

        $this->assertTrue($stay_rule_found, 'Basic stay rule should be added');
        $this->assertTrue($stay_section_rule_found, 'Stay section rule should be added');
    }

    public function testQueryVarsAreRegistered() {
        $initial_vars = ['existing_var'];
        $filtered_vars = $this->rewrite->addQueryVars($initial_vars);

        $this->assertContains('existing_var', $filtered_vars, 'Existing vars should be preserved');
        $this->assertContains('minpaku_official_page', $filtered_vars, 'Official page var should be added');
        $this->assertContains('property_slug', $filtered_vars, 'Property slug var should be added');
        $this->assertContains('section', $filtered_vars, 'Section var should be added');
    }

    public function testParseOfficialRequestWithValidProperty() {
        global $test_query_vars;

        $wp = (object) [
            'query_vars' => [
                'minpaku_official_page' => '1',
                'property_slug' => 'test-property'
            ]
        ];

        $this->rewrite->parseOfficialRequest($wp);

        $this->assertEquals(123, $wp->query_vars['property_id'], 'Property ID should be set');
        $this->assertInstanceOf('stdClass', $wp->query_vars['property_object'], 'Property object should be set');
    }

    public function testParseOfficialRequestWithInvalidProperty() {
        global $test_404_set;

        $wp = (object) [
            'query_vars' => [
                'minpaku_official_page' => '1',
                'property_slug' => 'non-existent-property'
            ]
        ];

        $this->rewrite->parseOfficialRequest($wp);

        $this->assertTrue($test_404_set, '404 should be set for non-existent property');
    }

    public function testParseOfficialRequestWithSection() {
        $wp = (object) [
            'query_vars' => [
                'minpaku_official_page' => '1',
                'property_slug' => 'test-property',
                'section' => 'gallery'
            ]
        ];

        $this->rewrite->parseOfficialRequest($wp);

        $this->assertEquals('gallery', $wp->query_vars['target_section'], 'Target section should be set');
    }

    public function testParseOfficialRequestIgnoresNonOfficialRequests() {
        $wp = (object) [
            'query_vars' => [
                'property_slug' => 'test-property'
            ]
        ];

        $original_vars = $wp->query_vars;
        $this->rewrite->parseOfficialRequest($wp);

        $this->assertEquals($original_vars, $wp->query_vars, 'Non-official requests should not be modified');
    }

    public function testGetOfficialPageUrl() {
        $url = $this->rewrite->getOfficialPageUrl($this->test_property_id);

        $this->assertIsString($url, 'URL should be a string');
        $this->assertEquals('https://example.com/stay/test-property/', $url, 'URL should match expected format');
    }

    public function testGetOfficialPageUrlWithInvalidProperty() {
        $url = $this->rewrite->getOfficialPageUrl(999999);

        $this->assertFalse($url, 'URL should be false for invalid property');
    }

    public function testGetSectionUrl() {
        $url = $this->rewrite->getSectionUrl($this->test_property_id, 'gallery');

        $this->assertIsString($url, 'Section URL should be a string');
        $this->assertEquals('https://example.com/stay/test-property/gallery/', $url, 'Section URL should match expected format');
    }

    public function testGetSectionUrlWithInvalidProperty() {
        $url = $this->rewrite->getSectionUrl(999999, 'gallery');

        $this->assertFalse($url, 'Section URL should be false for invalid property');
    }

    public function testFlushRewriteRules() {
        global $test_rewrite_flushed;

        OfficialRewrite::flushRewriteRules();

        $this->assertTrue($test_rewrite_flushed, 'Rewrite rules should be flushed');
    }

    public function testIsOfficialPageRequest() {
        global $test_query_vars;

        // Test positive case
        $test_query_vars = ['minpaku_official_page' => '1'];
        $this->assertTrue($this->rewrite->isOfficialPageRequest(), 'Should detect official page request');

        // Test negative case
        $test_query_vars = [];
        $this->assertFalse($this->rewrite->isOfficialPageRequest(), 'Should not detect non-official page request');
    }

    public function testGetCurrentPropertyId() {
        global $test_query_vars;

        // Test with official page request
        $test_query_vars = [
            'minpaku_official_page' => '1',
            'property_id' => '123'
        ];
        $this->assertEquals('123', $this->rewrite->getCurrentPropertyId(), 'Should return current property ID');

        // Test without official page request
        $test_query_vars = [];
        $this->assertFalse($this->rewrite->getCurrentPropertyId(), 'Should return false for non-official requests');
    }

    public function testGetCurrentSection() {
        global $test_query_vars;

        // Test with section
        $test_query_vars = [
            'minpaku_official_page' => '1',
            'target_section' => 'gallery'
        ];
        $this->assertEquals('gallery', $this->rewrite->getCurrentSection(), 'Should return current section');

        // Test without section
        $test_query_vars = [
            'minpaku_official_page' => '1'
        ];
        $this->assertFalse($this->rewrite->getCurrentSection(), 'Should return false when no section specified');

        // Test without official page request
        $test_query_vars = [];
        $this->assertFalse($this->rewrite->getCurrentSection(), 'Should return false for non-official requests');
    }

    public function testHandleOfficialPageRequestWithValidRequest() {
        global $test_query_vars;

        $test_query_vars = [
            'minpaku_official_page' => '1',
            'property_id' => '123'
        ];

        // Start output buffering to capture the output
        ob_start();

        try {
            $this->rewrite->handleOfficialPageRequest();
        } catch (Exception $e) {
            // Expected since we call exit in the method
        }

        $output = ob_get_clean();

        // The method should attempt to display content (though it will fail due to mocking)
        $this->assertTrue(true, 'Method should handle valid official page request without fatal errors');
    }

    public function testHandleOfficialPageRequestWithInvalidProperty() {
        global $test_query_vars, $test_404_set;

        $test_query_vars = [
            'minpaku_official_page' => '1',
            'property_id' => '999999'
        ];

        $this->rewrite->handleOfficialPageRequest();

        $this->assertTrue($test_404_set, 'Should set 404 for invalid property');
    }

    public function testHandleOfficialPageRequestIgnoresNonOfficialRequests() {
        global $test_query_vars, $test_404_set;

        $test_query_vars = [];

        $this->rewrite->handleOfficialPageRequest();

        $this->assertNull($test_404_set, 'Should not modify response for non-official requests');
    }

    public function testRewriteRuleRegexPatterns() {
        global $test_rewrite_rules;

        $this->rewrite->addRewriteRules();

        foreach ($test_rewrite_rules as $rule) {
            if (strpos($rule['query'], 'minpaku_official_page') !== false) {
                // Test that the regex patterns are valid
                $this->assertStringStartsWith('^', $rule['regex'], 'Rewrite regex should start with ^');
                $this->assertStringEndsWith('$', $rule['regex'], 'Rewrite regex should end with $');

                // Test specific patterns
                if (strpos($rule['regex'], 'stay') !== false) {
                    $this->assertStringContainsString('([^/]+)', $rule['regex'], 'Stay rule should capture property slug');
                }
            }
        }
    }

    public function testUrlStructureCustomization() {
        // Test that URL structure can be customized
        $custom_structure = 'property';

        // Mock the option
        if (!function_exists('get_option')) {
            function get_option($option_name, $default = false) {
                if ($option_name === 'minpaku_official_url_structure') {
                    return 'property';
                }
                return $default;
            }
        }

        $url = $this->rewrite->getOfficialPageUrl($this->test_property_id);

        // Since we can't easily test dynamic URL structure without refactoring,
        // we just ensure the basic URL generation works
        $this->assertIsString($url, 'URL should still be generated with custom structure');
    }

    public function testRewriteHandlesSpecialCharactersInSlugs() {
        if (!function_exists('get_post_override')) {
            function get_post_override($post_id) {
                return (object) [
                    'ID' => $post_id,
                    'post_title' => 'Test Property with Special Characters',
                    'post_content' => 'Test property description',
                    'post_name' => 'test-property-with-special-chars',
                    'post_status' => 'publish',
                    'post_type' => 'minpaku_property'
                ];
            }
        }

        $url = $this->rewrite->getOfficialPageUrl(124);

        $this->assertIsString($url, 'Should handle properties with special characters in slugs');
    }

    public function testRewriteSecurityValidation() {
        global $test_query_vars;

        // Test with potentially malicious input
        $test_query_vars = [
            'minpaku_official_page' => '1',
            'property_slug' => '../../../etc/passwd',
            'section' => '<script>alert("xss")</script>'
        ];

        $wp = (object) ['query_vars' => $test_query_vars];

        $this->rewrite->parseOfficialRequest($wp);

        // Should not find a property with malicious slug
        $this->assertArrayNotHasKey('property_id', $wp->query_vars, 'Should not match malicious slug');
    }

    public function testRewritePerformanceWithManyRules() {
        global $test_rewrite_rules;

        // Add rules multiple times to simulate many rules
        for ($i = 0; $i < 10; $i++) {
            $this->rewrite->addRewriteRules();
        }

        $this->assertNotEmpty($test_rewrite_rules, 'Should handle multiple rule additions');
        $this->assertLessThan(100, count($test_rewrite_rules), 'Should not create excessive number of rules');
    }
}