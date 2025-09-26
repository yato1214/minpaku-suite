# MinPaku Suite WordPress Plugin Build Script v0.5.7
# 安定版復旧・カスタムEXCERPT対応

param(
    [string]$Version = "auto",
    [switch]$IncludeConnector = $true
)

# Set error action preference
$ErrorActionPreference = "Stop"

Write-Host "=== MinPaku Suite Plugin Builder v0.5.7 ===" -ForegroundColor Green
Write-Host "安定版復旧・カスタムEXCERPT対応版をビルドします" -ForegroundColor Yellow

try {
    # Define paths
    $RootPath = $PSScriptRoot
    $SourcePath = $RootPath
    $BuildPath = Join-Path $RootPath "build"
    $TempPath = Join-Path $BuildPath "temp"
    $OutputPath = Join-Path $BuildPath "releases"
    $Timestamp = Get-Date -Format "yyyyMMdd-HHmm"

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

    # Create main plugin ZIP file
    $ZipFileName = "minpaku-suite-v$Version-$Timestamp.zip"
    $ZipFilePath = Join-Path $OutputPath $ZipFileName

    Write-Host "Creating main plugin ZIP file: $ZipFileName" -ForegroundColor Yellow

    # Use Compress-Archive to create ZIP
    Compress-Archive -Path "$TempPath\*" -DestinationPath $ZipFilePath -CompressionLevel Optimal -Force

    # Get main plugin file size
    $MainFileSize = (Get-Item $ZipFilePath).Length
    $MainFileSizeKB = [math]::Round($MainFileSize / 1KB, 2)

    Write-Host "✓ Main plugin created: $MainFileSizeKB KB" -ForegroundColor Green

    # Build connector plugin if requested
    if ($IncludeConnector -and (Test-Path "connectors/wp-minpaku-connector")) {
        Write-Host "`nBuilding connector plugin..." -ForegroundColor Yellow

        $ConnectorTempPath = Join-Path $BuildPath "temp-connector"
        New-Item -ItemType Directory -Path $ConnectorTempPath -Force | Out-Null

        # Copy connector files
        $ConnectorSourcePath = "connectors/wp-minpaku-connector"
        Copy-Item -Path $ConnectorSourcePath -Destination $ConnectorTempPath -Recurse -Force

        # Create connector ZIP
        $ConnectorZipFileName = "wp-minpaku-connector-v$Version-$Timestamp.zip"
        $ConnectorZipFilePath = Join-Path $OutputPath $ConnectorZipFileName

        Compress-Archive -Path "$ConnectorTempPath\*" -DestinationPath $ConnectorZipFilePath -CompressionLevel Optimal -Force
        Remove-Item $ConnectorTempPath -Recurse -Force

        $ConnectorFileSize = (Get-Item $ConnectorZipFilePath).Length
        $ConnectorFileSizeKB = [math]::Round($ConnectorFileSize / 1KB, 2)
        Write-Host "✓ Connector plugin created: $ConnectorFileSizeKB KB" -ForegroundColor Green
    }

    # Clean up temp directory
    Remove-Item $TempPath -Recurse -Force

    Write-Host ""
    Write-Host "=== Build Complete v$Version ===" -ForegroundColor Green
    Write-Host "ビルド日時: $Timestamp" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "v0.5.7 の主な変更点:" -ForegroundColor Yellow
    Write-Host "  🔄 安定版復旧 - v0.5.3の動作状態に完全ロールバック" -ForegroundColor Green
    Write-Host "  ❌ 問題メソッド削除 - get_property_excerpt()を完全に除去" -ForegroundColor Green
    Write-Host "  ✅ カスタムEXCERPT対応 - ACF安全チェック付きで直接実装" -ForegroundColor Green
    Write-Host "  🛡️ 堅牢性確保 - function_exists()チェックでACF未対応環境もサポート" -ForegroundColor Green
    Write-Host "  📦 シンプル設計 - 複雑な処理を排除し、確実な動作を優先" -ForegroundColor Green
    Write-Host ""
    Write-Host "作成されたファイル:" -ForegroundColor Yellow
    Write-Host "  📦 $ZipFileName ($MainFileSizeKB KB)" -ForegroundColor Green
    if ($IncludeConnector -and (Test-Path (Join-Path $OutputPath $ConnectorZipFileName))) {
        Write-Host "  📦 $ConnectorZipFileName ($ConnectorFileSizeKB KB)" -ForegroundColor Green
    }
    Write-Host ""
    Write-Host "🎉 Plugins ready for deployment!" -ForegroundColor Green

} catch {
    Write-Host "Build failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}