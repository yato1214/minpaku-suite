# WordPress Plugin Build Script
# Creates a deployable ZIP package of the wp-minpaku-connector plugin

param(
    [string]$Version = "0.4.3",
    [string]$OutputDir = ".\dist"
)

Write-Host "Building wp-minpaku-connector plugin v$Version..." -ForegroundColor Green

# Create output directory
if (!(Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}

# Get plugin directory name
$PluginDir = "wp-minpaku-connector"
$ZipFileName = "$PluginDir-v$Version.zip"
$ZipPath = Join-Path $OutputDir $ZipFileName

# Remove existing ZIP if it exists
if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
    Write-Host "Removed existing ZIP file" -ForegroundColor Yellow
}

# Files and directories to include
$IncludeItems = @(
    "wp-minpaku-connector.php",
    "readme.txt",
    "includes\",
    "assets\",
    "languages\"
)

# Files and directories to exclude
$ExcludePatterns = @(
    "*.git*",
    "*.DS_Store",
    "Thumbs.db",
    "*.log",
    "node_modules\",
    "build.ps1",
    "dist\",
    "*.bak",
    "*.tmp"
)

Write-Host "Creating temporary staging directory..." -ForegroundColor Blue

# Create temporary staging directory
$TempDir = Join-Path $env:TEMP "wp-minpaku-connector-build-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
$StagingDir = Join-Path $TempDir $PluginDir

New-Item -ItemType Directory -Path $StagingDir -Force | Out-Null

try {
    # Copy files to staging directory
    foreach ($Item in $IncludeItems) {
        $SourcePath = Join-Path $PSScriptRoot $Item
        if (Test-Path $SourcePath) {
            $DestPath = Join-Path $StagingDir $Item

            if (Test-Path $SourcePath -PathType Container) {
                # Copy directory
                Write-Host "Copying directory: $Item" -ForegroundColor Cyan
                Copy-Item $SourcePath $DestPath -Recurse -Force
            } else {
                # Copy file
                Write-Host "Copying file: $Item" -ForegroundColor Cyan
                Copy-Item $SourcePath $DestPath -Force
            }
        } else {
            Write-Host "Warning: $Item not found, skipping..." -ForegroundColor Yellow
        }
    }

    # Remove excluded items from staging directory
    foreach ($Pattern in $ExcludePatterns) {
        $ItemsToRemove = Get-ChildItem $StagingDir -Recurse -Force | Where-Object { $_.Name -like $Pattern -or $_.FullName -like "*$Pattern*" }
        foreach ($Item in $ItemsToRemove) {
            Remove-Item $Item.FullName -Recurse -Force -ErrorAction SilentlyContinue
            Write-Host "Excluded: $($Item.FullName.Replace($StagingDir, ''))" -ForegroundColor DarkGray
        }
    }

    # Update version in main plugin file
    $MainFile = Join-Path $StagingDir "wp-minpaku-connector.php"
    if (Test-Path $MainFile) {
        $Content = Get-Content $MainFile -Raw
        $Content = $Content -replace "Version:\s*[\d\.]+", "Version: $Version"
        $Content = $Content -replace "define\s*\(\s*['""]WP_MINPAKU_CONNECTOR_VERSION['""],\s*['""][\d\.]+['""]", "define('WP_MINPAKU_CONNECTOR_VERSION', '$Version'"
        Set-Content $MainFile $Content -Encoding UTF8
        Write-Host "Updated version to $Version in main plugin file" -ForegroundColor Green
    }

    # Create readme.txt if it doesn't exist
    $ReadmeFile = Join-Path $StagingDir "readme.txt"
    if (!(Test-Path $ReadmeFile)) {
        $ReadmeContent = @"
=== WP Minpaku Connector ===
Contributors: minpaku-suite
Tags: booking, calendar, minpaku, vacation-rental
Requires at least: 5.0
Tested up to: 6.4
Stable tag: $Version
Requires PHP: 7.4
License: GPL v2 or later

WordPress connector plugin for Minpaku Suite booking system.

== Description ==

This plugin provides WordPress integration for the Minpaku Suite booking system, including:

* Live availability calendar with pricing
* Interactive booking forms
* Property listings with embedded calendars
* Real-time quote calculations
* Japanese holiday support
* Responsive design

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-minpaku-connector` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin settings under Settings > Minpaku Connector

== Changelog ==

= $Version =
* Fixed code output issue in property shortcode display
* Fixed pricing discrepancy between portal and connector calendars
* Added seasonal pricing and eve surcharge support matching portal side
* Fixed calendar click navigation to portal booking screen
* Added booking functionality with quote display and portal redirection
* Improved calendar interaction with checkin/checkout selection
* Enhanced CSS styling for quote display and booking buttons

"@
        Set-Content $ReadmeFile $ReadmeContent -Encoding UTF8
        Write-Host "Created readme.txt file" -ForegroundColor Green
    }

    # Create ZIP archive
    Write-Host "Creating ZIP archive..." -ForegroundColor Blue

    if (Get-Command "Compress-Archive" -ErrorAction SilentlyContinue) {
        # Use PowerShell 5.0+ Compress-Archive
        Compress-Archive -Path "$TempDir\*" -DestinationPath $ZipPath -Force
    } else {
        # Fallback to .NET compression
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        [System.IO.Compression.ZipFile]::CreateFromDirectory($TempDir, $ZipPath)
    }

    # Get file size
    $FileSize = [math]::Round((Get-Item $ZipPath).Length / 1MB, 2)

    Write-Host "Build completed successfully!" -ForegroundColor Green
    Write-Host "Package: $ZipPath" -ForegroundColor White
    Write-Host "Size: $FileSize MB" -ForegroundColor White

    # Show contents
    Write-Host "`nPackage contents:" -ForegroundColor Blue
    if (Get-Command "Expand-Archive" -ErrorAction SilentlyContinue) {
        $TempExtract = Join-Path $env:TEMP "wp-minpaku-connector-verify"
        if (Test-Path $TempExtract) { Remove-Item $TempExtract -Recurse -Force }
        Expand-Archive $ZipPath $TempExtract
        Get-ChildItem $TempExtract -Recurse | ForEach-Object {
            $RelativePath = $_.FullName.Replace($TempExtract, "").TrimStart("\")
            if ($_.PSIsContainer) {
                Write-Host "  📁 $RelativePath" -ForegroundColor Cyan
            } else {
                $Size = [math]::Round($_.Length / 1KB, 1)
                Write-Host "  📄 $RelativePath ($Size KB)" -ForegroundColor Gray
            }
        }
        Remove-Item $TempExtract -Recurse -Force
    }

} finally {
    # Clean up temporary directory
    if (Test-Path $TempDir) {
        Remove-Item $TempDir -Recurse -Force
        Write-Host "Cleaned up temporary files" -ForegroundColor DarkGray
    }
}

Write-Host "`nBuild script completed." -ForegroundColor Green
Write-Host "You can now upload $ZipFileName to WordPress sites." -ForegroundColor Yellow