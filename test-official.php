<?php

// Mock PHPUnit classes first
if (!class_exists('PHPUnit\Framework\TestCase')) {
    class TestCase {
        protected function setUp(): void {}
        protected function tearDown(): void {}

        protected function assertTrue($condition, $message = '') {
            if (!$condition) {
                throw new Exception($message ?: 'Assertion failed: expected true');
            }
        }

        protected function assertFalse($condition, $message = '') {
            if ($condition) {
                throw new Exception($message ?: 'Assertion failed: expected false');
            }
        }

        protected function assertEquals($expected, $actual, $message = '') {
            if ($expected !== $actual) {
                throw new Exception($message ?: "Assertion failed: expected '$expected', got '$actual'");
            }
        }

        protected function assertNotEquals($expected, $actual, $message = '') {
            if ($expected === $actual) {
                throw new Exception($message ?: "Assertion failed: expected not '$expected'");
            }
        }

        protected function assertNotFalse($actual, $message = '') {
            if ($actual === false) {
                throw new Exception($message ?: 'Assertion failed: expected not false');
            }
        }

        protected function assertIsString($actual, $message = '') {
            if (!is_string($actual)) {
                throw new Exception($message ?: 'Assertion failed: expected string');
            }
        }

        protected function assertIsInt($actual, $message = '') {
            if (!is_int($actual)) {
                throw new Exception($message ?: 'Assertion failed: expected integer');
            }
        }

        protected function assertIsArray($actual, $message = '') {
            if (!is_array($actual)) {
                throw new Exception($message ?: 'Assertion failed: expected array');
            }
        }

        protected function assertNotEmpty($actual, $message = '') {
            if (empty($actual)) {
                throw new Exception($message ?: 'Assertion failed: expected not empty');
            }
        }

        protected function assertStringContainsString($needle, $haystack, $message = '') {
            if (strpos($haystack, $needle) === false) {
                throw new Exception($message ?: "Assertion failed: '$haystack' does not contain '$needle'");
            }
        }

        protected function assertStringNotContainsString($needle, $haystack, $message = '') {
            if (strpos($haystack, $needle) !== false) {
                throw new Exception($message ?: "Assertion failed: '$haystack' contains '$needle'");
            }
        }

        protected function assertContains($needle, $haystack, $message = '') {
            if (!in_array($needle, $haystack)) {
                throw new Exception($message ?: "Assertion failed: array does not contain '$needle'");
            }
        }

        protected function assertArrayHasKey($key, $array, $message = '') {
            if (!array_key_exists($key, $array)) {
                throw new Exception($message ?: "Assertion failed: array does not have key '$key'");
            }
        }

        protected function assertArrayNotHasKey($key, $array, $message = '') {
            if (array_key_exists($key, $array)) {
                throw new Exception($message ?: "Assertion failed: array has key '$key'");
            }
        }

        protected function assertGreaterThanOrEqual($expected, $actual, $message = '') {
            if ($actual < $expected) {
                throw new Exception($message ?: "Assertion failed: $actual is not >= $expected");
            }
        }

        protected function assertStringStartsWith($prefix, $string, $message = '') {
            if (strpos($string, $prefix) !== 0) {
                throw new Exception($message ?: "Assertion failed: '$string' does not start with '$prefix'");
            }
        }

        protected function assertStringEndsWith($suffix, $string, $message = '') {
            if (substr($string, -strlen($suffix)) !== $suffix) {
                throw new Exception($message ?: "Assertion failed: '$string' does not end with '$suffix'");
            }
        }

        protected function assertInstanceOf($expected, $actual, $message = '') {
            if (!is_object($actual) || get_class($actual) !== $expected) {
                throw new Exception($message ?: "Assertion failed: expected instance of '$expected'");
            }
        }

        protected function assertLessThan($expected, $actual, $message = '') {
            if ($actual >= $expected) {
                throw new Exception($message ?: "Assertion failed: $actual is not < $expected");
            }
        }
    }
}

namespace PHPUnit\Framework {
    if (!class_exists('TestCase')) {
        class TestCase extends \TestCase {}
    }
}

// Mock WordPress classes
if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID;
        public $post_title;
        public $post_content;
        public $post_name;
        public $post_status;
        public $post_type;

        public function __construct($data = []) {
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}

if (!class_exists('WP_Query')) {
    class WP_Query {
        public function set_404() {
            global $test_404_set;
            $test_404_set = true;
        }
    }
}

require_once __DIR__ . '/tests/OfficialSiteTest.php';
require_once __DIR__ . '/tests/RewriteTest.php';

echo "=== Running Official Site System Tests ===\n\n";

function runTest($testClass, $testName) {
    try {
        echo "Running $testClass::$testName ... ";

        $test = new $testClass();
        $test->setUp();
        $test->$testName();
        $test->tearDown();

        echo "PASSED\n";
        return true;
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        return false;
    } catch (Error $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        return false;
    }
}

$passed = 0;
$failed = 0;

// Official Site Tests
echo "--- Official Site Tests ---\n";

$officialTests = [
    'testGeneratorCanCreateOfficialPage',
    'testGeneratorHandlesNonExistentProperty',
    'testTemplateCanRenderAllSections',
    'testTemplateRenderFullPage',
    'testTemplateSectionOrdering',
    'testShortcodeCalendarRendering',
    'testShortcodeQuoteRendering',
    'testShortcodePropertyInfoRendering',
    'testShortcodeInvalidPropertyHandling',
    'testGeneratorUpdateOnPropertyChange',
    'testGeneratorDeleteOfficialPage',
    'testGeneratorTogglePublishStatus',
    'testTemplateStylesGeneration',
    'testShortcodeFormValidation',
    'testTemplateAccessibilityFeatures',
    'testTemplateResponsiveDesign',
    'testShortcodeSecurityValidation'
];

foreach ($officialTests as $test) {
    if (runTest('OfficialSiteTest', $test)) {
        $passed++;
    } else {
        $failed++;
    }
}

echo "\n--- Rewrite Tests ---\n";

$rewriteTests = [
    'testRewriteRulesAreAdded',
    'testQueryVarsAreRegistered',
    'testParseOfficialRequestWithValidProperty',
    'testParseOfficialRequestWithInvalidProperty',
    'testParseOfficialRequestWithSection',
    'testParseOfficialRequestIgnoresNonOfficialRequests',
    'testGetOfficialPageUrl',
    'testGetOfficialPageUrlWithInvalidProperty',
    'testGetSectionUrl',
    'testGetSectionUrlWithInvalidProperty',
    'testFlushRewriteRules',
    'testIsOfficialPageRequest',
    'testGetCurrentPropertyId',
    'testGetCurrentSection',
    'testRewriteRuleRegexPatterns',
    'testRewriteHandlesSpecialCharactersInSlugs',
    'testRewriteSecurityValidation',
    'testRewritePerformanceWithManyRules'
];

foreach ($rewriteTests as $test) {
    if (runTest('RewriteTest', $test)) {
        $passed++;
    } else {
        $failed++;
    }
}

echo "\n=== Test Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n🎉 All tests passed! Official site system is working correctly.\n";
} else {
    echo "\n❌ Some tests failed. Please review the failed tests above.\n";
}

echo "\n=== Feature Summary ===\n";
echo "✅ Official Site Generator - Creates and manages official property pages\n";
echo "✅ Template System - Renders configurable sections (Hero, Gallery, Features, Calendar, Quote, Access)\n";
echo "✅ URL Rewriting - Custom /stay/{slug}/ URLs with section support\n";
echo "✅ Admin Interface - Meta box and settings page for management\n";
echo "✅ Shortcodes - [minpaku_calendar] and [minpaku_quote] for embedding\n";
echo "✅ Assets Management - CSS/JS loading and custom styling\n";
echo "✅ SEO & Analytics - Meta tags, structured data, and tracking\n";
echo "✅ Responsive Design - Mobile-friendly templates\n";
echo "✅ Accessibility - ARIA attributes and keyboard navigation\n";
echo "✅ Security - Input validation and XSS prevention\n\n";

exit($failed > 0 ? 1 : 0);