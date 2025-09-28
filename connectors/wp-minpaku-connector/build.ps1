# WP Minpaku Connector Build Script
# This script creates a deployable ZIP file of the plugin

param(
    [string]$Version = "0.5.0",
    [string]$OutputDir = ".\dist",
    [switch]$Clean = $false
)

Write-Host "Building WP Minpaku Connector v$Version" -ForegroundColor Green

# Set script location and plugin directory
$ScriptPath = $PSScriptRoot
$PluginDir = $ScriptPath
$PluginName = "wp-minpaku-connector"

# Create output directory if it doesn't exist
if (!(Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
    Write-Host "Created output directory: $OutputDir" -ForegroundColor Yellow
}

# Clean previous builds if requested
if ($Clean -and (Test-Path "$OutputDir\$PluginName-$Version.zip")) {
    Remove-Item "$OutputDir\$PluginName-$Version.zip" -Force
    Write-Host "Cleaned previous build" -ForegroundColor Yellow
}

# Files and directories to include in the build
$IncludeFiles = @(
    "wp-minpaku-connector.php",
    "readme.txt",
    "includes\**",
    "assets\**",
    "languages\**"
)

# Files and directories to exclude from the build
$ExcludePatterns = @(
    "*.ps1",
    "*.md",
    ".git*",
    "node_modules\**",
    "src\**",
    "*.tmp",
    "*.log",
    "dist\**",
    "tests\**",
    ".vscode\**",
    ".idea\**",
    "composer.json",
    "composer.lock",
    "package.json",
    "package-lock.json",
    "webpack.config.js",
    "gulpfile.js",
    "*.map"
)

Write-Host "Preparing files for packaging..." -ForegroundColor Yellow

# Create temporary directory for staging
$TempDir = Join-Path $env:TEMP "wp-minpaku-connector-build"
$StagingDir = Join-Path $TempDir $PluginName

if (Test-Path $TempDir) {
    Remove-Item $TempDir -Recurse -Force
}
New-Item -ItemType Directory -Path $StagingDir -Force | Out-Null

# Copy files to staging directory
foreach ($Pattern in $IncludeFiles) {
    $SourcePath = Join-Path $PluginDir $Pattern

    # Handle wildcard patterns
    if ($Pattern.Contains("**")) {
        $BasePath = $Pattern.Split("**")[0].TrimEnd("\")
        $SourceBasePath = Join-Path $PluginDir $BasePath

        if (Test-Path $SourceBasePath) {
            $DestPath = Join-Path $StagingDir $BasePath
            if (!(Test-Path $DestPath)) {
                New-Item -ItemType Directory -Path $DestPath -Force | Out-Null
            }

            # Copy directory contents recursively
            Get-ChildItem $SourceBasePath -Recurse | ForEach-Object {
                $RelativePath = $_.FullName.Substring($SourceBasePath.Length + 1)
                $DestFile = Join-Path $DestPath $RelativePath

                # Skip excluded files
                $ShouldExclude = $false
                foreach ($ExcludePattern in $ExcludePatterns) {
                    if ($RelativePath -like $ExcludePattern -or $_.Name -like $ExcludePattern) {
                        $ShouldExclude = $true
                        break
                    }
                }

                if (-not $ShouldExclude) {
                    if ($_.PSIsContainer) {
                        if (!(Test-Path $DestFile)) {
                            New-Item -ItemType Directory -Path $DestFile -Force | Out-Null
                        }
                    } else {
                        $DestDir = Split-Path $DestFile -Parent
                        if (!(Test-Path $DestDir)) {
                            New-Item -ItemType Directory -Path $DestDir -Force | Out-Null
                        }
                        Copy-Item $_.FullName $DestFile -Force
                    }
                }
            }
        }
    } else {
        # Handle single files
        if (Test-Path $SourcePath) {
            $DestPath = Join-Path $StagingDir $Pattern
            $DestDir = Split-Path $DestPath -Parent
            if (!(Test-Path $DestDir)) {
                New-Item -ItemType Directory -Path $DestDir -Force | Out-Null
            }
            Copy-Item $SourcePath $DestPath -Force
        }
    }
}

# Verify main plugin file exists
$MainPluginFile = Join-Path $StagingDir "wp-minpaku-connector.php"
if (!(Test-Path $MainPluginFile)) {
    Write-Host "ERROR: Main plugin file not found!" -ForegroundColor Red
    exit 1
}

# Update version in plugin file if different
$PluginContent = Get-Content $MainPluginFile -Raw
if ($PluginContent -match " \* Version:\s*([0-9\.]+)") {
    $CurrentVersion = $Matches[1]
    if ($CurrentVersion -ne $Version) {
        Write-Host "Updating version from $CurrentVersion to $Version" -ForegroundColor Yellow
        $PluginContent = $PluginContent -replace " \* Version:\s*[0-9\.]+", " * Version: $Version"
        $PluginContent = $PluginContent -replace "WP_MINPAKU_CONNECTOR_VERSION',\s*'[^']*'", "WP_MINPAKU_CONNECTOR_VERSION', '$Version'"
        Set-Content $MainPluginFile $PluginContent -NoNewline
    }
}

Write-Host "Creating ZIP package..." -ForegroundColor Yellow

# Create ZIP file
$ZipPath = Join-Path $OutputDir "$PluginName-$Version.zip"

# Use .NET compression if available (Windows 10+), otherwise use Compress-Archive
try {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($TempDir, $ZipPath, "Optimal", $false)
    Write-Host "Package created using .NET compression" -ForegroundColor Green
} catch {
    # Fallback to PowerShell Compress-Archive
    Compress-Archive -Path "$TempDir\*" -DestinationPath $ZipPath -Force
    Write-Host "Package created using PowerShell compression" -ForegroundColor Green
}

# Clean up temporary directory
Remove-Item $TempDir -Recurse -Force

# Display results
$ZipInfo = Get-Item $ZipPath
$SizeKB = [math]::Round($ZipInfo.Length / 1KB, 2)
$SizeMB = [math]::Round($ZipInfo.Length / 1MB, 2)

Write-Host "`nBuild completed successfully!" -ForegroundColor Green
Write-Host "Package: $($ZipInfo.Name)" -ForegroundColor White
Write-Host "Size: $SizeKB KB ($SizeMB MB)" -ForegroundColor White
Write-Host "Location: $($ZipInfo.FullName)" -ForegroundColor White

# Optional: Open the output directory
if ($PSVersionTable.PSVersion.Major -ge 3) {
    $OpenChoice = Read-Host "`nOpen output directory? (y/N)"
    if ($OpenChoice -eq "y" -or $OpenChoice -eq "Y") {
        Invoke-Item $OutputDir
    }
}

Write-Host "`nBuild script completed." -ForegroundColor Green
