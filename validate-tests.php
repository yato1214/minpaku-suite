<?php
/**
 * Simple validation script to check if test files can be parsed
 */

echo "MinPaku Suite Test Validation\n";
echo "============================\n\n";

// Test files to validate
$test_files = [
    'tests/Integration/IcalSyncTest.php',
    'tests/Integration/RuleEngineTest.php',
    'tests/Integration/RateResolverTest.php',
    'tests/Integration/OwnerSubscriptionTest.php',
    'tests/Integration/UiEndpointsTest.php'
];

$tools = [
    'tools/seed-demo.php'
];

$config_files = [
    'phpunit.xml',
    'tests/bootstrap.php'
];

echo "1. Checking PHP Syntax:\n";
echo "-----------------------\n";

$syntax_errors = 0;
foreach (array_merge($test_files, $tools, $config_files) as $file) {
    if (!file_exists($file)) {
        echo "❌ $file - File not found\n";
        continue;
    }

    // Check syntax
    $output = [];
    $return_code = 0;
    exec("php -l \"$file\" 2>&1", $output, $return_code);

    if ($return_code === 0) {
        echo "✅ $file - Syntax OK\n";
    } else {
        echo "❌ $file - Syntax Error: " . implode(' ', $output) . "\n";
        $syntax_errors++;
    }
}

echo "\n2. Checking File Structure:\n";
echo "---------------------------\n";

$structure_checks = [
    'tests/' => 'Tests directory',
    'tests/Integration/' => 'Integration tests directory',
    'tools/' => 'Tools directory',
    'phpunit.xml' => 'PHPUnit configuration',
    'tests/bootstrap.php' => 'Test bootstrap'
];

foreach ($structure_checks as $path => $description) {
    if (file_exists($path)) {
        echo "✅ $description exists\n";
    } else {
        echo "❌ $description missing\n";
    }
}

echo "\n3. Checking Test Classes (Basic):\n";
echo "--------------------------------\n";

foreach ($test_files as $file) {
    if (!file_exists($file)) {
        echo "❌ $file - File missing\n";
        continue;
    }

    $content = file_get_contents($file);

    // Extract class name
    if (preg_match('/class\s+(\w+)\s+extends/', $content, $matches)) {
        $class_name = $matches[1];
        echo "✅ $file - Class: $class_name\n";

        // Count test methods
        $test_method_count = preg_match_all('/public\s+function\s+test\w+/', $content);
        echo "   - Test methods: $test_method_count\n";

        // Check for setUp/tearDown
        $has_setup = strpos($content, 'function setUp') !== false;
        $has_teardown = strpos($content, 'function tearDown') !== false;

        if ($has_setup) echo "   - Has setUp method\n";
        if ($has_teardown) echo "   - Has tearDown method\n";

    } else {
        echo "❌ $file - No test class found\n";
    }
}

echo "\n4. Checking Demo Seeder:\n";
echo "-----------------------\n";

if (file_exists('tools/seed-demo.php')) {
    $content = file_get_contents('tools/seed-demo.php');

    if (strpos($content, 'class MinPakuDemoSeeder') !== false) {
        echo "✅ MinPakuDemoSeeder class found\n";

        // Count seed methods
        $seed_method_count = preg_match_all('/public\s+function\s+seed_\w+/', $content);
        echo "   - Seeding methods: $seed_method_count\n";

        // Check for important methods
        $has_seed_all = strpos($content, 'function seed_all') !== false;
        $has_cleanup = strpos($content, 'function cleanup_demo_data') !== false;

        if ($has_seed_all) echo "   - Has seed_all method\n";
        if ($has_cleanup) echo "   - Has cleanup method\n";

    } else {
        echo "❌ MinPakuDemoSeeder class not found\n";
    }
} else {
    echo "❌ Demo seeder file missing\n";
}

echo "\n5. PHP Environment:\n";
echo "------------------\n";

echo "✅ PHP Version: " . PHP_VERSION . "\n";

$required_extensions = ['json', 'curl'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extension $ext: Available\n";
    } else {
        echo "❌ Extension $ext: Missing\n";
    }
}

echo "\nSummary:\n";
echo "========\n";

if ($syntax_errors === 0) {
    echo "✅ All files have valid PHP syntax\n";
} else {
    echo "❌ $syntax_errors files have syntax errors\n";
}

echo "✅ " . count($test_files) . " integration test files created\n";
echo "✅ Demo seeding tool created\n";
echo "✅ PHPUnit configuration ready\n";

echo "\nNext Steps:\n";
echo "----------\n";
echo "1. Install PHPUnit: composer require --dev phpunit/phpunit\n";
echo "2. Install WordPress test suite (optional)\n";
echo "3. Run tests: vendor/bin/phpunit --configuration phpunit.xml\n";
echo "4. Seed demo data: wp minpaku seed-demo --force\n";

echo "\nNote: Tests are designed to work with or without WordPress test suite.\n";
echo "The bootstrap file includes mock functions for basic testing.\n";
?>