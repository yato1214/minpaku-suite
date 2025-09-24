# WordPress Minpaku Connector Plugin - Final ZIP Builder
# 民泊コネクタプラグイン 最終版ZIPビルダー

param(
    [string]$Version = "1.1.1",
    [string]$OutputName = "wp-minpaku-connector-final-v111.zip",
    [switch]$Clean = $false
)

$ErrorActionPreference = "Stop"

# Colors
$Green = "Green"
$Yellow = "Yellow"
$Red = "Red"
$Cyan = "Cyan"
$Blue = "Blue"

Write-Host "=== Minpaku Connector Plugin FINAL ZIP Builder ===" -ForegroundColor $Cyan
Write-Host "Version: $Version" -ForegroundColor $Yellow
Write-Host "Output: $OutputName" -ForegroundColor $Yellow
Write-Host ""

# Get current directory (plugin root)
$PluginDir = $PWD.Path
$ParentDir = Split-Path $PluginDir -Parent
$OutputPath = Join-Path $ParentDir $OutputName

# Verify plugin directory
$MainPluginFile = Join-Path $PluginDir "wp-minpaku-connector.php"
if (-not (Test-Path $MainPluginFile)) {
    Write-Host "ERROR: Not in plugin directory!" -ForegroundColor $Red
    exit 1
}

Write-Host "Plugin Directory: $PluginDir" -ForegroundColor $Green
Write-Host "Output Location: $OutputPath" -ForegroundColor $Green
Write-Host ""

# Remove existing ZIP if Clean specified
if ($Clean -and (Test-Path $OutputPath)) {
    Write-Host "Removing existing ZIP..." -ForegroundColor $Yellow
    Remove-Item $OutputPath -Force
}

# Files to EXCLUDE
$ExcludePatterns = @(
    "*.log", "*.tmp", "*.bak", ".DS_Store", "Thumbs.db", "desktop.ini",
    "node_modules", ".git*", ".vscode", ".idea", "*.md", "README.txt",
    "composer.*", "package*.json", "yarn.lock", "webpack.config.js",
    "gulpfile.js", "Gruntfile.js", ".eslintrc*", ".stylelintrc*",
    "phpcs.xml*", "phpunit.xml*", "tests", "test", "spec", "docs",
    "documentation", "build-zip.ps1", "*.zip"
)

# Create temp build directory
$TempDir = Join-Path $env:TEMP "wp-minpaku-connector-final-$(Get-Date -Format 'yyyyMMddHHmmss')"
$TempPluginDir = Join-Path $TempDir "wp-minpaku-connector"

Write-Host "Creating temporary build directory..." -ForegroundColor $Yellow
New-Item -ItemType Directory -Path $TempPluginDir -Force | Out-Null

try {
    Write-Host "Copying plugin files..." -ForegroundColor $Yellow

    # Get all items
    $AllItems = Get-ChildItem -Path $PluginDir -Recurse -Force
    $ItemsToCopy = @()
    $ExcludedCount = 0

    foreach ($item in $AllItems) {
        $relativePath = $item.FullName.Substring($PluginDir.Length + 1)
        $shouldExclude = $false

        # Check exclude patterns
        foreach ($pattern in $ExcludePatterns) {
            if ($relativePath -like $pattern -or $item.Name -like $pattern) {
                $shouldExclude = $true
                $ExcludedCount++
                break
            }
        }

        # Exclude hidden files (except .htaccess)
        if ($item.Name.StartsWith('.') -and $item.Name -ne '.htaccess') {
            $shouldExclude = $true
            $ExcludedCount++
        }

        if (-not $shouldExclude) {
            $ItemsToCopy += $item
        }
    }

    Write-Host "Items to copy: $($ItemsToCopy.Count)" -ForegroundColor $Blue
    Write-Host "Items excluded: $ExcludedCount" -ForegroundColor $Blue

    # Copy files
    foreach ($item in $ItemsToCopy) {
        $relativePath = $item.FullName.Substring($PluginDir.Length + 1)
        $destinationPath = Join-Path $TempPluginDir $relativePath

        if ($item.PSIsContainer) {
            if (-not (Test-Path $destinationPath)) {
                New-Item -ItemType Directory -Path $destinationPath -Force | Out-Null
            }
        } else {
            $destinationDir = Split-Path $destinationPath -Parent
            if (-not (Test-Path $destinationDir)) {
                New-Item -ItemType Directory -Path $destinationDir -Force | Out-Null
            }
            Copy-Item $item.FullName $destinationPath -Force
        }
    }

    Write-Host "Files copied successfully!" -ForegroundColor $Green

    # Update version
    Write-Host "Updating plugin version to $Version..." -ForegroundColor $Yellow
    $mainPluginFile = Join-Path $TempPluginDir "wp-minpaku-connector.php"
    if (Test-Path $mainPluginFile) {
        $content = Get-Content $mainPluginFile -Raw -Encoding UTF8
        $content = $content -replace "Version:\s*[\d\.]+", "Version: $Version"
        $content = $content -replace "define\s*\(\s*['\`"]WP_MINPAKU_CONNECTOR_VERSION['\`"],\s*['\`"][\d\.]+['\`"]\s*\)", "define('WP_MINPAKU_CONNECTOR_VERSION', '$Version')"
        Set-Content $mainPluginFile $content -Encoding UTF8
        Write-Host "Plugin version updated!" -ForegroundColor $Green
    }

    # Create ZIP
    Write-Host "Creating ZIP archive: $OutputName" -ForegroundColor $Yellow
    Compress-Archive -Path $TempPluginDir -DestinationPath $OutputPath -CompressionLevel Optimal -Force

    # File info
    $zipInfo = Get-Item $OutputPath
    $fileSizeKB = [math]::Round($zipInfo.Length / 1024, 2)
    $fileSizeMB = [math]::Round($zipInfo.Length / 1024 / 1024, 2)

    Write-Host ""
    Write-Host "=== BUILD COMPLETED SUCCESSFULLY ===" -ForegroundColor $Green
    Write-Host "Plugin Version: $Version" -ForegroundColor $Green
    Write-Host "ZIP File: $OutputName" -ForegroundColor $Green
    Write-Host "Size: $fileSizeKB KB ($fileSizeMB MB)" -ForegroundColor $Green
    Write-Host "Path: $OutputPath" -ForegroundColor $Green
    Write-Host ""
    Write-Host "✅ Ready for WordPress installation!" -ForegroundColor $Cyan
    Write-Host ""
    Write-Host "🎉 FEATURES INCLUDED IN v1.1.1 (最終修正版):" -ForegroundColor $Yellow
    Write-Host "✅ Modal calendar popup with enhanced AJAX loading" -ForegroundColor $Green
    Write-Host "✅ Fixed booking page redirect to admin interface" -ForegroundColor $Green
    Write-Host "✅ 🔧 FIXED: Price display now shows actual data instead of ¥100" -ForegroundColor $Green
    Write-Host "✅ Property listing modal calendar buttons" -ForegroundColor $Green
    Write-Host "✅ Property detail page modal calendar integration" -ForegroundColor $Green
    Write-Host "✅ 🔧 FIXED: Completely removed all legacy calendar remnants" -ForegroundColor $Green
    Write-Host "✅ 🔧 ENHANCED: Multi-layer CSS with aggressive loading and fallbacks" -ForegroundColor $Green
    Write-Host "✅ Enhanced debug logging for price data analysis" -ForegroundColor $Green
    Write-Host "✅ Improved AJAX error handling and recovery" -ForegroundColor $Green
    Write-Host "✅ Maximum compatibility with themes and plugins" -ForegroundColor $Green
    Write-Host "✅ 🔧 NEW: Property meta fallback for accurate pricing" -ForegroundColor $Green
    Write-Host "✅ 🔧 NEW: Legacy calendar element suppression system" -ForegroundColor $Green

} catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor $Red
    exit 1
} finally {
    if (Test-Path $TempDir) {
        Write-Host "Cleaning up..." -ForegroundColor $Yellow
        Remove-Item $TempDir -Recurse -Force
    }
}

Write-Host ""
Write-Host "🎉 FINAL PLUGIN ZIP CREATED!" -ForegroundColor $Cyan
Write-Host "Location: $OutputPath" -ForegroundColor $Green