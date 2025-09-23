# Build script for WP Minpaku Connector plugin
# This script creates a clean distribution zip file

param(
    [string]$Version = "1.0.0"
)

$PluginName = "wp-minpaku-connector"
$BuildDir = ".\build"
$DistDir = ".\dist"

Write-Host "Building WP Minpaku Connector v$Version..." -ForegroundColor Green

# Clean up previous builds
if (Test-Path $BuildDir) {
    Remove-Item $BuildDir -Recurse -Force
}

if (Test-Path $DistDir) {
    Remove-Item $DistDir -Recurse -Force
}

# Create build directory
New-Item -ItemType Directory -Path $BuildDir -Force | Out-Null
New-Item -ItemType Directory -Path $DistDir -Force | Out-Null

# Copy plugin files
$PluginBuildDir = Join-Path $BuildDir $PluginName

Write-Host "Copying plugin files..." -ForegroundColor Yellow

# Create plugin directory structure in build
New-Item -ItemType Directory -Path $PluginBuildDir -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PluginBuildDir "includes") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PluginBuildDir "includes\Admin") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PluginBuildDir "includes\Client") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PluginBuildDir "includes\Shortcodes") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PluginBuildDir "assets") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PluginBuildDir "languages") -Force | Out-Null

# Copy main plugin file
Copy-Item ".\wp-minpaku-connector.php" $PluginBuildDir

# Copy includes
Copy-Item ".\includes\Admin\*.php" (Join-Path $PluginBuildDir "includes\Admin\")
Copy-Item ".\includes\Client\*.php" (Join-Path $PluginBuildDir "includes\Client\")
Copy-Item ".\includes\Shortcodes\*.php" (Join-Path $PluginBuildDir "includes\Shortcodes\")

# Copy assets
Copy-Item ".\assets\*" (Join-Path $PluginBuildDir "assets\")

# Copy language files
Copy-Item ".\languages\*.pot" (Join-Path $PluginBuildDir "languages\")

# Copy documentation
Copy-Item ".\README.md" $PluginBuildDir -ErrorAction SilentlyContinue

# Update version in main plugin file
$MainFile = Join-Path $PluginBuildDir "wp-minpaku-connector.php"
$Content = Get-Content $MainFile -Raw
$Content = $Content -replace "Version: 1\.0\.0", "Version: $Version"
$Content = $Content -replace "WP_MINPAKU_CONNECTOR_VERSION', '1\.0\.0'", "WP_MINPAKU_CONNECTOR_VERSION', '$Version'"
Set-Content $MainFile $Content

Write-Host "Creating zip file..." -ForegroundColor Yellow

# Create zip file
$ZipFile = Join-Path $DistDir "$PluginName-$Version.zip"

if (Get-Command Compress-Archive -ErrorAction SilentlyContinue) {
    # PowerShell 5.0+ method
    Compress-Archive -Path $PluginBuildDir -DestinationPath $ZipFile -Force
} else {
    # Fallback for older PowerShell versions
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($PluginBuildDir, $ZipFile)
}

Write-Host "Build completed!" -ForegroundColor Green
Write-Host "Zip file created: $ZipFile" -ForegroundColor Cyan

# Show file size
$FileSize = (Get-Item $ZipFile).Length / 1KB
Write-Host "File size: $([math]::Round($FileSize, 2)) KB" -ForegroundColor Gray

# Clean up build directory
Write-Host "Cleaning up..." -ForegroundColor Yellow
Remove-Item $BuildDir -Recurse -Force

Write-Host "Done!" -ForegroundColor Green