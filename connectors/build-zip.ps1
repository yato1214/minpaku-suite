# Build WP Minpaku Connector Distribution Zip
# Usage: .\build-zip.ps1 [-Version "1.0.1"]

param(
    [string]$Version = "1.0.4"
)

# Set script directory as working directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ScriptDir

Write-Host "Building WP Minpaku Connector v$Version..." -ForegroundColor Green

# Define paths
$SourceDir = "wp-minpaku-connector"
$DistDir = Join-Path $ScriptDir "dist"
$TempDir = Join-Path $ScriptDir "temp-build"
$ZipName = "wp-minpaku-connector.zip"
$ZipPath = Join-Path $DistDir $ZipName

# Clean and create directories
if (Test-Path $TempDir) {
    Remove-Item -Recurse -Force $TempDir
}
if (!(Test-Path $DistDir)) {
    New-Item -ItemType Directory -Path $DistDir | Out-Null
}
New-Item -ItemType Directory -Path $TempDir | Out-Null
New-Item -ItemType Directory -Path (Join-Path $TempDir $SourceDir) | Out-Null

$TargetDir = Join-Path $TempDir $SourceDir

Write-Host "Copying plugin files..." -ForegroundColor Yellow

# Copy main plugin file
Copy-Item "$SourceDir\wp-minpaku-connector.php" $TargetDir -Force
Write-Host "✓ Copied wp-minpaku-connector.php" -ForegroundColor Green

# Copy includes directory
if (Test-Path "$SourceDir\includes") {
    Copy-Item "$SourceDir\includes" $TargetDir -Recurse -Force
    Write-Host "✓ Copied includes/" -ForegroundColor Green
} else {
    Write-Host "⚠ includes/ directory not found" -ForegroundColor Yellow
}

# Copy assets directory
if (Test-Path "$SourceDir\assets") {
    Copy-Item "$SourceDir\assets" $TargetDir -Recurse -Force
    Write-Host "✓ Copied assets/" -ForegroundColor Green
} else {
    Write-Host "⚠ assets/ directory not found" -ForegroundColor Yellow
}

# Copy languages directory
if (Test-Path "$SourceDir\languages") {
    Copy-Item "$SourceDir\languages" $TargetDir -Recurse -Force
    Write-Host "✓ Copied languages/ (including .po/.mo files)" -ForegroundColor Green
} else {
    Write-Host "⚠ languages/ directory not found" -ForegroundColor Yellow
}

# Create readme.txt
$ReadmeContent = @"
=== WP Minpaku Connector ===
Contributors: yato1214
Tags: minpaku, vacation rental, property management, booking
Requires at least: 5.0
Tested up to: 6.3
Requires PHP: 7.4
Stable tag: $Version
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to Minpaku Suite portal to display property listings and availability calendars.

== Description ==

WP Minpaku Connector allows you to connect your WordPress site to a Minpaku Suite portal to display:

* Property listings with details and images
* Availability calendars for specific properties
* Property details and amenities
* Booking quotes and pricing

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-minpaku-connector/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > Minpaku Connector to configure the connection
4. Get your API credentials from your Minpaku Suite portal
5. Test the connection and start using shortcodes

== Shortcodes ==

**Basic Shortcodes:**
* `[minpaku_connector type="properties"]` - Display property listings
* `[minpaku_connector type="calendar" property_id="123"]` - Show availability calendar
* `[minpaku_connector type="property" property_id="123"]` - Display property details

**New Pricing Shortcodes:**
* `[minpaku_calendar property_id="123" show_prices="true"]` - Calendar with price badges
* `[minpaku_property_card property_id="123" show_price="true"]` - Single property card with quick quote
* `[minpaku_property_list limit="12" columns="3" show_prices="true"]` - Property grid with pricing

== Changelog ==

= $Version =
* NEW: Enhanced calendar availability visualization with color-coded status
* NEW: Improved price badge positioning at bottom of calendar cells
* NEW: Modern calendar design with gradient backgrounds and hover effects
* NEW: Enhanced quote modal with improved usability and design
* NEW: Availability indicators with status tooltips (Available/Partial/Full)
* NEW: Mobile-responsive calendar improvements
* FIXED: Calendar cell layout and price display positioning
* Enhanced: Visual feedback for booking status and pricing
* Updated: Portal calendar with same modern design improvements

= 1.0.2 =
* NEW: Complete pricing integration with calendar price badges and quote modals
* NEW: Property card shortcodes with quick pricing display
* NEW: Advanced caching system (memory, WordPress transients, session storage)
* NEW: HMAC authenticated QuoteApi client for secure pricing requests
* NEW: Intersection Observer for lazy loading price badges
* NEW: Mobile-responsive design with dark mode support
* Enhanced: Multi-level request coalescing and error handling
* Enhanced: Accessibility features with ARIA support and keyboard navigation
* Enhanced: Internationalization support for pricing strings
* Updated: Modern UI design with animations and loading states

= 1.0.0 =
* Initial release
* Basic connector functionality
* Property listings and calendar display
* HMAC authentication
"@

$ReadmeContent | Out-File -FilePath (Join-Path $TargetDir "readme.txt") -Encoding UTF8
Write-Host "✓ Created readme.txt" -ForegroundColor Green

# Create admin.css if it doesn't exist
$AdminCssPath = Join-Path $TargetDir "assets\admin.css"
if (!(Test-Path $AdminCssPath)) {
    $AdminCssContent = @"
/* WP Minpaku Connector Admin Styles */
.wmc-error {
    color: #d63638;
    font-weight: bold;
}

.wmc-success {
    color: #00a32a;
    font-weight: bold;
}

.wmc-testing {
    color: #646970;
    font-style: italic;
}

#test-result {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
}

#test-result.success {
    background-color: #d1e7dd;
    border: 1px solid #badbcc;
    color: #0f5132;
}

#test-result.error {
    background-color: #f8d7da;
    border: 1px solid #f5c2c7;
    color: #842029;
}

.error-field {
    border-color: #d63638 !important;
    box-shadow: 0 0 0 1px #d63638 !important;
}

.validation-errors {
    margin: 1em 0;
}

.validation-errors ul {
    margin: 0.5em 0;
}
"@
    if (!(Test-Path (Join-Path $TargetDir "assets"))) {
        New-Item -ItemType Directory -Path (Join-Path $TargetDir "assets") | Out-Null
    }
    $AdminCssContent | Out-File -FilePath $AdminCssPath -Encoding UTF8
    Write-Host "✓ Created assets\admin.css" -ForegroundColor Green
}

# Display plugin contents
Write-Host "`nPlugin contents:" -ForegroundColor Cyan
Get-ChildItem $TargetDir -Recurse | ForEach-Object {
    $RelativePath = $_.FullName.Replace($TargetDir, "").TrimStart('\')
    $Size = if ($_.PSIsContainer) { "" } else { " ({0:N1} KB)" -f ($_.Length / 1KB) }
    $Icon = if ($_.PSIsContainer) { "📁" } else { "📄" }
    Write-Host "  $Icon $RelativePath$Size" -ForegroundColor White
}

# Create zip file
Write-Host "`nCreating zip file..." -ForegroundColor Yellow
if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
}

# Use .NET compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($TempDir, $ZipPath)

# Clean up temp directory
Remove-Item -Recurse -Force $TempDir

# Get final zip size
$ZipSize = (Get-Item $ZipPath).Length / 1KB
Write-Host ("✓ Created: {0} ({1:N1} KB)" -f $ZipPath, $ZipSize) -ForegroundColor Green

Write-Host "`n🎉 Build completed successfully!" -ForegroundColor Green
Write-Host "Distribution zip: $ZipPath" -ForegroundColor Cyan

Write-Host "`nTo install:" -ForegroundColor Yellow
Write-Host "1. Upload the zip file to WordPress admin > Plugins > Add New > Upload Plugin"
Write-Host "2. Activate the plugin"
Write-Host "3. Configure in Settings > Minpaku Connector"