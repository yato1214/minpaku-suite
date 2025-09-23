# WordPress Minpaku Connector Plugin Build Script
# 民泊コネクタプラグイン ビルドスクリプト

param(
    [string]$Version = "1.0.0",
    [string]$OutputDir = "dist",
    [switch]$Clean = $false
)

# Set error action preference
$ErrorActionPreference = "Stop"

# Colors for output
$Green = "Green"
$Yellow = "Yellow"
$Red = "Red"
$Cyan = "Cyan"

Write-Host "=== WordPress Minpaku Connector Plugin Build Script ===" -ForegroundColor $Cyan
Write-Host "Version: $Version" -ForegroundColor $Yellow
Write-Host ""

# Get script directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$PluginDir = Join-Path $ScriptDir "wp-minpaku-connector"
$OutputPath = Join-Path $ScriptDir $OutputDir

# Verify plugin directory exists
if (-not (Test-Path $PluginDir)) {
    Write-Host "ERROR: Plugin directory not found: $PluginDir" -ForegroundColor $Red
    exit 1
}

Write-Host "Plugin Directory: $PluginDir" -ForegroundColor $Green
Write-Host "Output Directory: $OutputPath" -ForegroundColor $Green
Write-Host ""

# Clean output directory if requested
if ($Clean -and (Test-Path $OutputPath)) {
    Write-Host "Cleaning output directory..." -ForegroundColor $Yellow
    Remove-Item $OutputPath -Recurse -Force
}

# Create output directory
if (-not (Test-Path $OutputPath)) {
    New-Item -ItemType Directory -Path $OutputPath -Force | Out-Null
    Write-Host "Created output directory: $OutputPath" -ForegroundColor $Green
}

# Define files and directories to exclude from the build
$ExcludePatterns = @(
    "*.log",
    "*.tmp",
    ".DS_Store",
    "Thumbs.db",
    "node_modules",
    ".git",
    ".gitignore",
    ".vscode",
    "*.md",
    "composer.json",
    "composer.lock",
    "package.json",
    "package-lock.json",
    "webpack.config.js",
    "gulpfile.js",
    "Gruntfile.js",
    ".eslintrc*",
    ".stylelintrc*",
    "phpcs.xml*",
    "phpunit.xml*",
    "tests",
    "test",
    "spec",
    "docs",
    "documentation",
    "*.zip",
    "build.ps1"
)

# Define required files for WordPress plugin
$RequiredFiles = @(
    "wp-minpaku-connector.php",
    "includes",
    "assets"
)

Write-Host "Validating plugin structure..." -ForegroundColor $Yellow

# Check for required files
$MissingFiles = @()
foreach ($file in $RequiredFiles) {
    $filePath = Join-Path $PluginDir $file
    if (-not (Test-Path $filePath)) {
        $MissingFiles += $file
    }
}

if ($MissingFiles.Count -gt 0) {
    Write-Host "ERROR: Missing required files/directories:" -ForegroundColor $Red
    $MissingFiles | ForEach-Object { Write-Host "  - $_" -ForegroundColor $Red }
    exit 1
}

Write-Host "Plugin structure validation passed!" -ForegroundColor $Green
Write-Host ""

# Create temporary build directory
$TempBuildDir = Join-Path $env:TEMP "wp-minpaku-connector-build-$(Get-Date -Format 'yyyyMMddHHmmss')"
$TempPluginDir = Join-Path $TempBuildDir "wp-minpaku-connector"

Write-Host "Creating temporary build directory: $TempBuildDir" -ForegroundColor $Yellow
New-Item -ItemType Directory -Path $TempPluginDir -Force | Out-Null

try {
    # Copy plugin files to temp directory, excluding unwanted files
    Write-Host "Copying plugin files..." -ForegroundColor $Yellow

    # Get all files and directories, excluding patterns
    $AllItems = Get-ChildItem -Path $PluginDir -Recurse
    $ItemsToCopy = @()

    foreach ($item in $AllItems) {
        $relativePath = $item.FullName.Substring($PluginDir.Length + 1)
        $shouldExclude = $false

        foreach ($pattern in $ExcludePatterns) {
            if ($relativePath -like $pattern -or $item.Name -like $pattern) {
                $shouldExclude = $true
                break
            }
        }

        if (-not $shouldExclude) {
            $ItemsToCopy += $item
        }
    }

    # Copy files
    foreach ($item in $ItemsToCopy) {
        $relativePath = $item.FullName.Substring($PluginDir.Length + 1)
        $destinationPath = Join-Path $TempPluginDir $relativePath

        if ($item.PSIsContainer) {
            # Create directory
            if (-not (Test-Path $destinationPath)) {
                New-Item -ItemType Directory -Path $destinationPath -Force | Out-Null
            }
        } else {
            # Copy file
            $destinationDir = Split-Path $destinationPath -Parent
            if (-not (Test-Path $destinationDir)) {
                New-Item -ItemType Directory -Path $destinationDir -Force | Out-Null
            }
            Copy-Item $item.FullName $destinationPath -Force
        }
    }

    Write-Host "Copied $($ItemsToCopy.Count) items" -ForegroundColor $Green

    # Update version in main plugin file if version parameter is provided
    if ($Version -ne "1.0.0") {
        Write-Host "Updating version to $Version..." -ForegroundColor $Yellow
        $mainPluginFile = Join-Path $TempPluginDir "wp-minpaku-connector.php"
        if (Test-Path $mainPluginFile) {
            $content = Get-Content $mainPluginFile -Raw
            $content = $content -replace "Version:\s*[\d\.]+", "Version: $Version"
            $content = $content -replace "define\s*\(\s*['\`"]WP_MINPAKU_CONNECTOR_VERSION['\`"],\s*['\`"][\d\.]+['\`"]", "define('WP_MINPAKU_CONNECTOR_VERSION', '$Version')"
            Set-Content $mainPluginFile $content -Encoding UTF8
            Write-Host "Updated version in plugin file" -ForegroundColor $Green
        }
    }

    # Create ZIP file
    $ZipFileName = "wp-minpaku-connector-v$Version.zip"
    $ZipFilePath = Join-Path $OutputPath $ZipFileName

    Write-Host "Creating ZIP archive: $ZipFileName" -ForegroundColor $Yellow

    # Remove existing ZIP if it exists
    if (Test-Path $ZipFilePath) {
        Remove-Item $ZipFilePath -Force
    }

    # Create ZIP using PowerShell Compress-Archive
    Compress-Archive -Path $TempPluginDir -DestinationPath $ZipFilePath -CompressionLevel Optimal

    # Also create a generic filename for easy deployment
    $GenericZipPath = Join-Path $OutputPath "wp-minpaku-connector.zip"
    Copy-Item $ZipFilePath $GenericZipPath -Force

    # Get file size
    $FileSize = (Get-Item $ZipFilePath).Length
    $FileSizeKB = [math]::Round($FileSize / 1024, 2)

    Write-Host ""
    Write-Host "=== BUILD SUCCESSFUL ===" -ForegroundColor $Green
    Write-Host "Plugin Version: $Version" -ForegroundColor $Green
    Write-Host "ZIP File: $ZipFileName" -ForegroundColor $Green
    Write-Host "File Size: $FileSizeKB KB" -ForegroundColor $Green
    Write-Host "Location: $ZipFilePath" -ForegroundColor $Green
    Write-Host "Generic: $GenericZipPath" -ForegroundColor $Green
    Write-Host ""
    Write-Host "Ready for WordPress installation!" -ForegroundColor $Cyan

} catch {
    Write-Host "ERROR during build process:" -ForegroundColor $Red
    Write-Host $_.Exception.Message -ForegroundColor $Red
    exit 1
} finally {
    # Clean up temporary directory
    if (Test-Path $TempBuildDir) {
        Write-Host "Cleaning up temporary files..." -ForegroundColor $Yellow
        Remove-Item $TempBuildDir -Recurse -Force
    }
}

Write-Host ""
Write-Host "Build completed successfully!" -ForegroundColor $Green
Write-Host "You can now upload '$ZipFileName' to WordPress admin." -ForegroundColor $Cyan