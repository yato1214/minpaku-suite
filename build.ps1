# MinPaku Suite WordPress Plugin Build Script

param(
    [string]$Version = "auto"
)

# Set error action preference
$ErrorActionPreference = "Stop"

Write-Host "=== MinPaku Suite Plugin Builder ===" -ForegroundColor Green

try {
    # Define paths
    $RootPath = $PSScriptRoot
    $SourcePath = $RootPath
    $BuildPath = Join-Path $RootPath "build"
    $TempPath = Join-Path $BuildPath "temp"
    $OutputPath = Join-Path $BuildPath "releases"

    # Clean and create build directories
    if (Test-Path $BuildPath) {
        Remove-Item $BuildPath -Recurse -Force
    }
    New-Item -ItemType Directory -Path $BuildPath -Force | Out-Null
    New-Item -ItemType Directory -Path $TempPath -Force | Out-Null
    New-Item -ItemType Directory -Path $OutputPath -Force | Out-Null

    Write-Host "Created build directories" -ForegroundColor Yellow

    # Determine version
    $MainPluginFile = Join-Path $SourcePath "minpaku-suite.php"
    if ($Version -eq "auto" -and (Test-Path $MainPluginFile)) {
        $content = Get-Content $MainPluginFile -Raw
        if ($content -match "Version:\s*(.+)") {
            $Version = $matches[1].Trim()
        } else {
            $Version = "1.0.0"
        }
    } elseif ($Version -eq "auto") {
        $Version = "1.0.0"
    }

    Write-Host "Building version: $Version" -ForegroundColor Cyan

    # Define files to exclude
    $ExcludePatterns = @(
        "build\*",
        "*.ps1",
        "*.md",
        "*.git*",
        "*.log",
        "*~",
        "*.tmp",
        "node_modules\*",
        "vendor\*",
        ".DS_Store",
        "Thumbs.db",
        "*.zip"
    )

    # Copy source files to temp directory
    Write-Host "Copying source files..." -ForegroundColor Yellow

    # Get all files in source directory
    $AllFiles = Get-ChildItem -Path $SourcePath -Recurse -File

    foreach ($File in $AllFiles) {
        $RelativePath = $File.FullName.Substring($SourcePath.Length + 1)
        $ShouldExclude = $false

        # Check against exclude patterns
        foreach ($Pattern in $ExcludePatterns) {
            $Pattern = $Pattern.Replace("\", [System.IO.Path]::DirectorySeparatorChar)
            if ($RelativePath -like $Pattern) {
                $ShouldExclude = $true
                break
            }
        }

        if (-not $ShouldExclude) {
            $DestFile = Join-Path $TempPath $RelativePath
            $DestDir = Split-Path $DestFile -Parent

            if (-not (Test-Path $DestDir)) {
                New-Item -ItemType Directory -Path $DestDir -Force | Out-Null
            }

            Copy-Item -Path $File.FullName -Destination $DestFile -Force
        }
    }

    Write-Host "Copied source files to temp directory" -ForegroundColor Green

    # Update version in main plugin file if needed
    $TempMainFile = Join-Path $TempPath "minpaku-suite.php"
    if (Test-Path $TempMainFile) {
        $content = Get-Content $TempMainFile -Raw
        $content = $content -replace "Version:\s*.+", "Version: $Version"
        Set-Content -Path $TempMainFile -Value $content -Encoding UTF8
        Write-Host "Updated version in main plugin file" -ForegroundColor Green
    }

    # Create ZIP file
    $ZipFileName = "minpaku-suite-v$Version.zip"
    $ZipFilePath = Join-Path $OutputPath $ZipFileName

    Write-Host "Creating ZIP file: $ZipFileName" -ForegroundColor Yellow

    # Use Compress-Archive to create ZIP
    Compress-Archive -Path "$TempPath\*" -DestinationPath $ZipFilePath -CompressionLevel Optimal -Force

    # Clean up temp directory
    Remove-Item $TempPath -Recurse -Force

    Write-Host ""
    Write-Host "=== Build Complete ===" -ForegroundColor Green
    Write-Host "Version: $Version" -ForegroundColor Cyan
    Write-Host "Output: $ZipFilePath" -ForegroundColor Cyan

    # Get file size
    $FileSize = (Get-Item $ZipFilePath).Length
    $FileSizeKB = [math]::Round($FileSize / 1KB, 2)
    Write-Host "Size: $FileSizeKB KB" -ForegroundColor Cyan

    Write-Host ""
    Write-Host "Plugin ready for deployment!" -ForegroundColor Green

} catch {
    Write-Host "Build failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}