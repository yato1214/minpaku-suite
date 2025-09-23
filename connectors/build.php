<?php
/**
 * Build Script for WP Minpaku Connector
 * Generates distribution ZIP file for external WordPress sites
 */

// Security check
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

// Configuration
$source_dir = __DIR__ . '/wp-minpaku-connector';
$dist_dir = __DIR__ . '/dist';
$zip_filename = 'wp-minpaku-connector.zip';
$zip_path = $dist_dir . '/' . $zip_filename;

// Files and directories to include
$include_patterns = [
    'wp-minpaku-connector.php',
    'readme.txt',
    'includes/**',
    'assets/css/**',
    'assets/js/**',
    'assets/img/**',
    'languages/**'
];

// Files and directories to exclude
$exclude_patterns = [
    'node_modules',
    '.git',
    '.github',
    '.claude',
    'tests',
    '.DS_Store',
    '*.map',
    'package.json',
    'package-lock.json',
    'composer.json',
    'composer.lock',
    '.gitignore'
];

/**
 * Log message with timestamp
 */
function log_message($message) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

/**
 * Check if path should be excluded
 */
function should_exclude($path, $exclude_patterns) {
    $relative_path = str_replace('\\', '/', $path);

    foreach ($exclude_patterns as $pattern) {
        if (strpos($relative_path, $pattern) !== false) {
            return true;
        }

        // Check for glob patterns
        if (fnmatch($pattern, basename($relative_path))) {
            return true;
        }
    }

    return false;
}

/**
 * Get all files recursively
 */
function get_files_recursive($directory, $exclude_patterns = []) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        $file_path = $file->getRealPath();
        $relative_path = str_replace($directory . DIRECTORY_SEPARATOR, '', $file_path);

        if (!should_exclude($relative_path, $exclude_patterns)) {
            $files[] = $file_path;
        }
    }

    return $files;
}

/**
 * Verify plugin file and extract version
 */
function verify_plugin_file($plugin_file) {
    if (!file_exists($plugin_file)) {
        throw new Exception("Plugin file not found: $plugin_file");
    }

    $content = file_get_contents($plugin_file);

    // Extract plugin information
    $plugin_info = [];

    if (preg_match('/Plugin Name:\s*(.+)/', $content, $matches)) {
        $plugin_info['name'] = trim($matches[1]);
    }

    if (preg_match('/Version:\s*(.+)/', $content, $matches)) {
        $plugin_info['version'] = trim($matches[1]);
    }

    if (preg_match('/Text Domain:\s*(.+)/', $content, $matches)) {
        $plugin_info['text_domain'] = trim($matches[1]);
    }

    if (empty($plugin_info['name']) || empty($plugin_info['version'])) {
        throw new Exception("Invalid plugin file: missing name or version");
    }

    return $plugin_info;
}

try {
    log_message("Starting WP Minpaku Connector build process...");

    // Verify source directory exists
    if (!is_dir($source_dir)) {
        throw new Exception("Source directory not found: $source_dir");
    }

    // Verify plugin file and get version
    $plugin_file = $source_dir . '/wp-minpaku-connector.php';
    $plugin_info = verify_plugin_file($plugin_file);

    log_message("Plugin: {$plugin_info['name']} v{$plugin_info['version']}");

    // Create dist directory if it doesn't exist
    if (!is_dir($dist_dir)) {
        if (!mkdir($dist_dir, 0755, true)) {
            throw new Exception("Failed to create dist directory: $dist_dir");
        }
    }

    // Remove existing ZIP file
    if (file_exists($zip_path)) {
        if (!unlink($zip_path)) {
            throw new Exception("Failed to remove existing ZIP file: $zip_path");
        }
        log_message("Removed existing ZIP file");
    }

    // Create ZIP archive
    $zip = new ZipArchive();
    $result = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    if ($result !== true) {
        throw new Exception("Failed to create ZIP file: " . $zip->getStatusString());
    }

    log_message("Created new ZIP archive");

    // Get all files to include
    $files = get_files_recursive($source_dir, $exclude_patterns);
    $file_count = 0;

    foreach ($files as $file_path) {
        $relative_path = str_replace($source_dir . DIRECTORY_SEPARATOR, '', $file_path);
        $relative_path = str_replace('\\', '/', $relative_path);

        // Add to ZIP with wp-minpaku-connector/ prefix
        $zip_path_in_archive = 'wp-minpaku-connector/' . $relative_path;

        if ($zip->addFile($file_path, $zip_path_in_archive)) {
            $file_count++;
        } else {
            log_message("Warning: Failed to add file: $relative_path");
        }
    }

    log_message("Added $file_count files to ZIP");

    // Close ZIP file
    if (!$zip->close()) {
        throw new Exception("Failed to close ZIP file");
    }

    // Verify ZIP file was created successfully
    if (!file_exists($zip_path)) {
        throw new Exception("ZIP file was not created");
    }

    $zip_size = filesize($zip_path);
    $zip_size_mb = round($zip_size / 1024 / 1024, 2);

    log_message("Build completed successfully!");
    log_message("Output: $zip_path");
    log_message("Size: {$zip_size_mb} MB ($file_count files)");

    // Verification: Open ZIP and check contents
    $verify_zip = new ZipArchive();
    if ($verify_zip->open($zip_path) === true) {
        $entry_count = $verify_zip->numFiles;
        $first_entry = $verify_zip->getNameIndex(0);

        log_message("Verification: ZIP contains $entry_count entries");
        log_message("First entry: $first_entry");

        // Check that first entry starts with wp-minpaku-connector/
        if (strpos($first_entry, 'wp-minpaku-connector/') === 0) {
            log_message("✓ ZIP structure is correct");
        } else {
            log_message("✗ Warning: ZIP structure may be incorrect");
        }

        // Check for main plugin file
        $main_plugin_found = false;
        for ($i = 0; $i < $entry_count; $i++) {
            $name = $verify_zip->getNameIndex($i);
            if ($name === 'wp-minpaku-connector/wp-minpaku-connector.php') {
                $main_plugin_found = true;
                break;
            }
        }

        if ($main_plugin_found) {
            log_message("✓ Main plugin file found in ZIP");
        } else {
            log_message("✗ Warning: Main plugin file not found in ZIP");
        }

        $verify_zip->close();
    }

    log_message("");
    log_message("Distribution package ready for upload!");
    log_message("");
    log_message("Installation instructions:");
    log_message("1. Go to WordPress Admin > Plugins > Add New > Upload Plugin");
    log_message("2. Choose file: " . basename($zip_path));
    log_message("3. Click 'Install Now' and then 'Activate Plugin'");
    log_message("4. Configure settings at Settings > Minpaku Connector");

} catch (Exception $e) {
    log_message("Error: " . $e->getMessage());
    exit(1);
}