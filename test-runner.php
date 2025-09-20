<?php
/**
 * Simple test runner to verify integration tests can be instantiated
 */

// Include bootstrap
require_once __DIR__ . '/tests/bootstrap.php';

echo "MinPaku Suite Integration Tests - Verification\n";
echo "==============================================\n\n";

$test_classes = [
    'IcalSyncTest' => 'tests/Integration/IcalSyncTest.php',
    'RuleEngineTest' => 'tests/Integration/RuleEngineTest.php',
    'RateResolverTest' => 'tests/Integration/RateResolverTest.php',
    'OwnerSubscriptionTest' => 'tests/Integration/OwnerSubscriptionTest.php',
    'UiEndpointsTest' => 'tests/Integration/UiEndpointsTest.php'
];

$results = [];

foreach ($test_classes as $class_name => $file_path) {
    echo "Testing $class_name... ";

    try {
        // Create mock TestCase if PHPUnit is not available
        if (!class_exists('PHPUnit\Framework\TestCase')) {
            namespace PHPUnit\Framework {
                class TestCase {
                    protected function setUp(): void {}
                    protected function tearDown(): void {}
                    public function assertEquals($expected, $actual, $message = '') {}
                    public function assertTrue($condition, $message = '') {}
                    public function assertFalse($condition, $message = '') {}
                    public function assertIsArray($actual, $message = '') {}
                    public function assertArrayHasKey($key, $array, $message = '') {}
                    public function assertGreaterThan($expected, $actual, $message = '') {}
                    public function assertLessThan($expected, $actual, $message = '') {}
                    public function assertCount($expectedCount, $haystack, $message = '') {}
                    public function assertNotEmpty($actual, $message = '') {}
                    public function assertEmpty($actual, $message = '') {}
                    public function assertStringContainsString($needle, $haystack, $message = '') {}
                    public function assertNotEquals($expected, $actual, $message = '') {}
                }
            }
        }

        // Include the test file
        require_once __DIR__ . '/' . $file_path;

        // Check if class exists
        if (class_exists($class_name)) {
            echo "✅ Class exists\n";
            $results[$class_name] = 'success';

            // Try to instantiate (this tests constructor and dependencies)
            try {
                $reflection = new ReflectionClass($class_name);
                if ($reflection->hasMethod('setUp')) {
                    echo "  - setUp method found\n";
                }
                if ($reflection->hasMethod('tearDown')) {
                    echo "  - tearDown method found\n";
                }

                // Count test methods
                $test_methods = array_filter(
                    $reflection->getMethods(),
                    function($method) {
                        return strpos($method->getName(), 'test') === 0;
                    }
                );

                echo "  - " . count($test_methods) . " test methods found\n";
                $results[$class_name] = [
                    'status' => 'success',
                    'test_methods' => count($test_methods)
                ];

            } catch (Exception $e) {
                echo "  ⚠️ Warning: " . $e->getMessage() . "\n";
            }

        } else {
            echo "❌ Class not found\n";
            $results[$class_name] = 'class_not_found';
        }

    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $results[$class_name] = 'error';
    }

    echo "\n";
}

// Test demo seeder
echo "Testing Demo Seeder... ";
try {
    require_once __DIR__ . '/tools/seed-demo.php';

    if (class_exists('MinPakuDemoSeeder')) {
        echo "✅ MinPakuDemoSeeder class exists\n";

        $seeder = new MinPakuDemoSeeder();
        $reflection = new ReflectionClass('MinPakuDemoSeeder');

        $seed_methods = array_filter(
            $reflection->getMethods(),
            function($method) {
                return strpos($method->getName(), 'seed_') === 0;
            }
        );

        echo "  - " . count($seed_methods) . " seeding methods found\n";
        $results['MinPakuDemoSeeder'] = [
            'status' => 'success',
            'seed_methods' => count($seed_methods)
        ];

    } else {
        echo "❌ MinPakuDemoSeeder class not found\n";
        $results['MinPakuDemoSeeder'] = 'class_not_found';
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    $results['MinPakuDemoSeeder'] = 'error';
}

echo "\n";

// Summary
echo "Summary:\n";
echo "========\n";

$successful = 0;
$total_test_methods = 0;

foreach ($results as $class => $result) {
    if (is_array($result) && $result['status'] === 'success') {
        $successful++;
        if (isset($result['test_methods'])) {
            $total_test_methods += $result['test_methods'];
            echo "✅ $class: {$result['test_methods']} test methods\n";
        } else {
            echo "✅ $class: Ready\n";
        }
    } else {
        echo "❌ $class: " . (is_string($result) ? $result : 'unknown error') . "\n";
    }
}

echo "\nResults:\n";
echo "- Classes verified: $successful/" . count($results) . "\n";
echo "- Total test methods: $total_test_methods\n";

if ($successful === count($results)) {
    echo "\n🎉 All integration tests are ready for PHPUnit execution!\n";
    echo "\nTo run tests with PHPUnit (if installed):\n";
    echo "  vendor/bin/phpunit --configuration phpunit.xml\n";
    echo "\nOr run individual test classes directly with PHPUnit.\n";
} else {
    echo "\n⚠️ Some tests have issues and may not run properly.\n";
}

echo "\nNote: These tests require WordPress test environment for full functionality.\n";
echo "Install WordPress test suite with: bash bin/install-wp-tests.sh\n";
?>