# WordPress Plugin Build Script
# Builds and packages the wp-minpaku-connector plugin

param(
    [string]$Version = "1.1.4",
    [string]$OutputDir = "dist",
    [switch]$Clean = $false
)

Write-Host "Building wp-minpaku-connector v$Version..." -ForegroundColor Green

# Get script directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $ScriptDir

# Clean output directory if requested
if ($Clean -and (Test-Path $OutputDir)) {
    Write-Host "Cleaning output directory..." -ForegroundColor Yellow
    Remove-Item $OutputDir -Recurse -Force
}

# Create output directory
if (!(Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir | Out-Null
}

# Define files and directories to include in the plugin
$IncludeItems = @(
    "wp-minpaku-connector.php",
    "includes",
    "assets",
    "languages",
    "readme.txt",
    "LICENSE"
)

# Define files and directories to exclude
$ExcludePatterns = @(
    "*.ps1",
    "*.md",
    ".git*",
    "node_modules",
    "dist",
    "src",
    "webpack.config.js",
    "package*.json",
    "composer.json",
    "composer.lock",
    "phpunit.xml",
    "tests",
    ".vscode",
    ".idea",
    "*.log",
    "*.tmp",
    ".DS_Store",
    "Thumbs.db"
)

# Create temporary build directory
$BuildDir = Join-Path $OutputDir "wp-minpaku-connector"
if (Test-Path $BuildDir) {
    Remove-Item $BuildDir -Recurse -Force
}
New-Item -ItemType Directory -Path $BuildDir | Out-Null

Write-Host "Copying plugin files..." -ForegroundColor Blue

# Copy included items
foreach ($Item in $IncludeItems) {
    if (Test-Path $Item) {
        if (Test-Path $Item -PathType Container) {
            # Directory
            Copy-Item $Item -Destination $BuildDir -Recurse -Force
        } else {
            # File
            Copy-Item $Item -Destination $BuildDir -Force
        }
        Write-Host "  ✓ $Item" -ForegroundColor Gray
    } else {
        Write-Host "  ! $Item (not found)" -ForegroundColor Yellow
    }
}

# Remove excluded files from build directory
Write-Host "Cleaning excluded files..." -ForegroundColor Blue
foreach ($Pattern in $ExcludePatterns) {
    Get-ChildItem $BuildDir -Recurse -Force | Where-Object {
        $_.Name -like $Pattern -or $_.FullName -like "*\$Pattern\*"
    } | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
}

# Update version in main plugin file if specified
$MainFile = Join-Path $BuildDir "wp-minpaku-connector.php"
if (Test-Path $MainFile) {
    Write-Host "Updating version to $Version..." -ForegroundColor Blue
    $Content = Get-Content $MainFile -Raw
    $Content = $Content -replace "Version:\s*[\d\.]+", "Version: $Version"
    $Content = $Content -replace "define\s*\(\s*['`"]WP_MINPAKU_CONNECTOR_VERSION['`"]\s*,\s*['`"][\d\.]+['`"]\s*\)", "define('WP_MINPAKU_CONNECTOR_VERSION', '$Version')"
    Set-Content $MainFile -Value $Content -NoNewline
}

# Create ZIP archive
$ZipPath = Join-Path $OutputDir "wp-minpaku-connector-v$Version.zip"
if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
}

Write-Host "Creating ZIP archive..." -ForegroundColor Blue
try {
    # Use .NET compression for better control
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($BuildDir, $ZipPath)
    Write-Host "✓ Created: $ZipPath" -ForegroundColor Green
} catch {
    # Fallback to PowerShell Compress-Archive
    Compress-Archive -Path "$BuildDir\*" -DestinationPath $ZipPath -Force
    Write-Host "✓ Created: $ZipPath" -ForegroundColor Green
}

# Clean up temporary build directory
Remove-Item $BuildDir -Recurse -Force

# Show file size
$ZipSize = (Get-Item $ZipPath).Length
$ZipSizeMB = [math]::Round($ZipSize / 1MB, 2)
Write-Host "Archive size: $ZipSizeMB MB" -ForegroundColor Cyan

# Verification
Write-Host "`nBuild completed successfully!" -ForegroundColor Green
Write-Host "Plugin archive: $ZipPath" -ForegroundColor White

# Optional: Show contents of ZIP
if ($VerbosePreference -eq 'Continue') {
    Write-Host "`nArchive contents:" -ForegroundColor Yellow
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
    $zip.Entries | ForEach-Object { Write-Host "  $($_.FullName)" }
    $zip.Dispose()
}

Write-Host "`nTo install:" -ForegroundColor Cyan
Write-Host "  1. Upload $ZipPath to WordPress admin > Plugins > Add New > Upload Plugin" -ForegroundColor Gray
Write-Host "  2. Or extract to wp-content/plugins/ directory" -ForegroundColor Gray