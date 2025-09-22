# Build WP Minpaku Connector distribution zip
# Usage: .\build-zip.ps1

param(
    [string]$Version = "1.0.0"
)

# Script directory
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$sourceDir = Join-Path $scriptDir "wp-minpaku-connector"
$distDir = Join-Path $scriptDir "dist"
$tempDir = Join-Path $distDir "temp"
$pluginDir = Join-Path $tempDir "wp-minpaku-connector"

Write-Host "Building WP Minpaku Connector v$Version..." -ForegroundColor Green

# Check if source directory exists
if (-not (Test-Path $sourceDir)) {
    Write-Error "Source directory not found: $sourceDir"
    exit 1
}

# Create directories
if (Test-Path $distDir) {
    Remove-Item $distDir -Recurse -Force
}
New-Item -ItemType Directory -Path $distDir -Force | Out-Null
New-Item -ItemType Directory -Path $tempDir -Force | Out-Null
New-Item -ItemType Directory -Path $pluginDir -Force | Out-Null

Write-Host "Copying plugin files..." -ForegroundColor Yellow

# Copy main plugin file
Copy-Item (Join-Path $sourceDir "wp-minpaku-connector.php") $pluginDir

# Copy includes directory
$includesSource = Join-Path $sourceDir "includes"
if (Test-Path $includesSource) {
    $includesTarget = Join-Path $pluginDir "includes"
    Copy-Item $includesSource $includesTarget -Recurse
    Write-Host "✓ Copied includes/" -ForegroundColor Green
}

# Copy assets directory
$assetsSource = Join-Path $sourceDir "assets"
if (Test-Path $assetsSource) {
    $assetsTarget = Join-Path $pluginDir "assets"
    Copy-Item $assetsSource $assetsTarget -Recurse
    Write-Host "✓ Copied assets/" -ForegroundColor Green
} else {
    # Create minimal assets directory with connector.css
    $assetsTarget = Join-Path $pluginDir "assets"
    New-Item -ItemType Directory -Path $assetsTarget -Force | Out-Null

    # Create basic connector.css
    $cssContent = @"
/* WP Minpaku Connector Styles */
.wmc-properties {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin: 20px 0;
}

.wmc-grid.wmc-columns-2 .wmc-property-card { width: calc(50% - 10px); }
.wmc-grid.wmc-columns-3 .wmc-property-card { width: calc(33.333% - 14px); }
.wmc-grid.wmc-columns-4 .wmc-property-card { width: calc(25% - 15px); }

.wmc-property-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}

.wmc-property-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.wmc-property-image img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.wmc-property-content {
    padding: 15px;
}

.wmc-property-title {
    font-size: 1.2em;
    font-weight: bold;
    margin: 0 0 8px 0;
    color: #333;
}

.wmc-property-excerpt {
    color: #666;
    font-size: 0.9em;
    margin: 0 0 12px 0;
    line-height: 1.4;
}

.wmc-property-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 0.85em;
}

.wmc-meta-item {
    display: flex;
    align-items: center;
    color: #555;
}

.wmc-meta-label {
    font-weight: 500;
    margin-right: 4px;
}

.wmc-price .wmc-meta-value {
    font-weight: bold;
    color: #2271b1;
}

.wmc-error {
    background: #fbeaea;
    border: 1px solid #dc3232;
    color: #dc3232;
    padding: 12px;
    border-radius: 4px;
    margin: 10px 0;
}

.wmc-no-content {
    background: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
    padding: 12px;
    border-radius: 4px;
    margin: 10px 0;
    text-align: center;
}

.wmc-availability {
    margin: 20px 0;
}

.wmc-availability-title {
    font-size: 1.3em;
    margin: 0 0 10px 0;
    color: #333;
}

.wmc-availability-period {
    color: #666;
    margin: 0 0 15px 0;
    font-style: italic;
}

.wmc-calendar {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
}

.wmc-calendar-month {
    margin-bottom: 25px;
}

.wmc-month-title {
    font-size: 1.1em;
    font-weight: bold;
    margin: 0 0 10px 0;
    text-align: center;
    color: #333;
}

.wmc-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    max-width: 100%;
}

.wmc-day-header {
    text-align: center;
    font-weight: bold;
    padding: 8px 4px;
    background: #f0f0f0;
    color: #555;
    font-size: 0.85em;
}

.wmc-calendar-day {
    text-align: center;
    padding: 8px 4px;
    border: 1px solid #eee;
    min-height: 35px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    font-size: 0.8em;
}

.wmc-day-available {
    background: #d4edda;
    color: #155724;
}

.wmc-day-unavailable {
    background: #f8d7da;
    color: #721c24;
}

.wmc-day-number {
    font-weight: bold;
}

.wmc-day-price {
    font-size: 0.7em;
    opacity: 0.8;
}

.wmc-calendar-legend {
    margin-top: 15px;
    display: flex;
    justify-content: center;
    gap: 20px;
    font-size: 0.9em;
}

.wmc-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.wmc-legend-color {
    width: 16px;
    height: 16px;
    border: 1px solid #ddd;
    border-radius: 2px;
}

.wmc-legend-color.wmc-available {
    background: #d4edda;
}

.wmc-legend-color.wmc-unavailable {
    background: #f8d7da;
}

/* Responsive design */
@media (max-width: 768px) {
    .wmc-grid .wmc-property-card {
        width: 100% !important;
    }

    .wmc-property-meta {
        flex-direction: column;
        gap: 5px;
    }

    .wmc-calendar-legend {
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
}
"@

    Set-Content -Path (Join-Path $assetsTarget "connector.css") -Value $cssContent
    Write-Host "✓ Created assets/connector.css" -ForegroundColor Green
}

# Copy languages directory (must include .po and .mo files)
$languagesSource = Join-Path $sourceDir "languages"
if (Test-Path $languagesSource) {
    $languagesTarget = Join-Path $pluginDir "languages"
    Copy-Item $languagesSource $languagesTarget -Recurse
    Write-Host "✓ Copied languages/ (including .po/.mo files)" -ForegroundColor Green
} else {
    Write-Warning "Languages directory not found: $languagesSource"
}

# Copy README.txt if exists
$readmeSource = Join-Path $sourceDir "readme.txt"
if (Test-Path $readmeSource) {
    Copy-Item $readmeSource $pluginDir
    Write-Host "✓ Copied readme.txt" -ForegroundColor Green
}

# Create README.txt if it doesn't exist
$readmeTarget = Join-Path $pluginDir "readme.txt"
if (-not (Test-Path $readmeTarget)) {
    $readmeContent = @"
=== WP Minpaku Connector ===
Contributors: yato1214
Tags: minpaku, vacation rental, property listings, availability calendar, connector
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: $Version
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to Minpaku Suite portal to display property listings and availability calendars.

== Description ==

WP Minpaku Connector allows you to connect your WordPress site to a Minpaku Suite portal and display property listings and availability calendars using simple shortcodes.

**Features:**
* Easy connection to Minpaku Suite portal
* Display property listings with customizable grid layout
* Show availability calendars for specific properties
* Multilingual support (English/Japanese)
* Responsive design
* Simple shortcode-based implementation

**Shortcodes:**
* `[minpaku_connector type="properties" limit="12" columns="3"]` - Display property listings
* `[minpaku_connector type="availability" property_id="123" months="2"]` - Show availability calendar
* `[minpaku_connector type="property" property_id="123"]` - Display single property details

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-minpaku-connector/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the connection in Settings > Minpaku Connector
4. Get your API credentials from your Minpaku Suite portal admin
5. Test the connection and start using shortcodes

== Configuration ==

1. Go to Settings > Minpaku Connector
2. Enter your portal connection details:
   * Portal Base URL (e.g., https://yourportal.com)
   * Site ID (from portal connector settings)
   * API Key (from portal connector settings)
   * Secret (from portal connector settings)
3. Click "Test Connection" to verify
4. Use shortcodes on your pages and posts

== Frequently Asked Questions ==

= How do I get API credentials? =

Log in to your Minpaku Suite portal admin, go to Minpaku > Settings > Connector, enable the connector, add your domain to allowed domains, and generate API keys for your site.

= What if the connection test fails? =

Check that:
* All credentials are entered correctly
* Your domain is added to allowed domains in the portal
* The portal URL is accessible
* Your server time is synchronized

== Changelog ==

= $Version =
* Initial release
* Property listings display
* Availability calendar integration
* Connection testing
* Multilingual support (EN/JA)
* Responsive design

== Support ==

For support, please visit: https://github.com/yato1214/minpaku-suite/issues
"@

    Set-Content -Path $readmeTarget -Value $readmeContent
    Write-Host "✓ Created readme.txt" -ForegroundColor Green
}

# Display file summary
Write-Host "`nPlugin contents:" -ForegroundColor Cyan
Get-ChildItem $pluginDir -Recurse | ForEach-Object {
    $relativePath = $_.FullName.Substring($pluginDir.Length + 1)
    if ($_.PSIsContainer) {
        Write-Host "  📁 $relativePath/" -ForegroundColor Blue
    } else {
        $size = [math]::Round($_.Length / 1KB, 1)
        Write-Host "  📄 $relativePath ($size KB)" -ForegroundColor Gray
    }
}

# Create zip file
$zipPath = Join-Path $distDir "wp-minpaku-connector.zip"
Write-Host "`nCreating zip file..." -ForegroundColor Yellow

try {
    # Use PowerShell's Compress-Archive
    Compress-Archive -Path $pluginDir -DestinationPath $zipPath -Force

    $zipSize = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)
    Write-Host "✓ Created: $zipPath ($zipSize KB)" -ForegroundColor Green

} catch {
    Write-Error "Failed to create zip file: $_"
    exit 1
}

# Cleanup temp directory
Remove-Item $tempDir -Recurse -Force

# Final summary
Write-Host "`n🎉 Build completed successfully!" -ForegroundColor Green
Write-Host "Distribution zip: $zipPath" -ForegroundColor Cyan
Write-Host "`nTo install:" -ForegroundColor Yellow
Write-Host "1. Upload the zip file to WordPress admin > Plugins > Add New > Upload Plugin" -ForegroundColor White
Write-Host "2. Activate the plugin" -ForegroundColor White
Write-Host "3. Configure in Settings > Minpaku Connector" -ForegroundColor White