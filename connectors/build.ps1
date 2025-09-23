# Build Script for WP Minpaku Connector
# Generates distribution ZIP file for external WordPress sites

param(
    [string]$Version = ""
)

# Configuration
$SourceDir = Join-Path $PSScriptRoot "wp-minpaku-connector"
$DistDir = Join-Path $PSScriptRoot "dist"
$ZipFilename = "wp-minpaku-connector.zip"
$ZipPath = Join-Path $DistDir $ZipFilename

# Files and directories to exclude
$ExcludePatterns = @(
    "node_modules",
    ".git*",
    ".github",
    ".claude",
    "tests",
    ".DS_Store",
    "*.map",
    "package.json",
    "package-lock.json",
    "composer.json",
    "composer.lock"
)

function Write-Log {
    param([string]$Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$timestamp] $Message"
}

function Test-ShouldExclude {
    param(
        [string]$Path,
        [string[]]$ExcludePatterns
    )

    $relativePath = $Path.Replace('\', '/')
    $fileName = Split-Path $Path -Leaf

    foreach ($pattern in $ExcludePatterns) {
        if ($relativePath -like "*$pattern*" -or $fileName -like $pattern) {
            return $true
        }
    }

    return $false
}

function Get-PluginInfo {
    param([string]$PluginFile)

    if (-not (Test-Path $PluginFile)) {
        throw "Plugin file not found: $PluginFile"
    }

    $content = Get-Content $PluginFile -Raw
    $info = @{}

    if ($content -match 'Plugin Name:\s*(.+)') {
        $info.Name = $matches[1].Trim()
    }

    if ($content -match 'Version:\s*(.+)') {
        $info.Version = $matches[1].Trim()
    }

    if ($content -match 'Text Domain:\s*(.+)') {
        $info.TextDomain = $matches[1].Trim()
    }

    if (-not $info.Name -or -not $info.Version) {
        throw "Invalid plugin file: missing name or version"
    }

    return $info
}

try {
    Write-Log "Starting WP Minpaku Connector build process..."

    # Verify source directory exists
    if (-not (Test-Path $SourceDir)) {
        throw "Source directory not found: $SourceDir"
    }

    # Verify plugin file and get version
    $pluginFile = Join-Path $SourceDir "wp-minpaku-connector.php"
    $pluginInfo = Get-PluginInfo $pluginFile

    Write-Log "Plugin: $($pluginInfo.Name) v$($pluginInfo.Version)"

    # Create dist directory if it doesn't exist
    if (-not (Test-Path $DistDir)) {
        New-Item -ItemType Directory -Path $DistDir -Force | Out-Null
    }

    # Remove existing ZIP file
    if (Test-Path $ZipPath) {
        Remove-Item $ZipPath -Force
        Write-Log "Removed existing ZIP file"
    }

    # Get all files to include
    $allFiles = Get-ChildItem -Path $SourceDir -Recurse -File
    $filesToInclude = @()

    foreach ($file in $allFiles) {
        $relativePath = $file.FullName.Replace("$SourceDir\", "")

        if (-not (Test-ShouldExclude $relativePath $ExcludePatterns)) {
            $filesToInclude += $file
        }
    }

    Write-Log "Found $($filesToInclude.Count) files to include"

    # Create temporary directory for ZIP structure
    $tempDir = Join-Path $env:TEMP "wp-minpaku-connector-build"
    $tempPluginDir = Join-Path $tempDir "wp-minpaku-connector"

    if (Test-Path $tempDir) {
        Remove-Item $tempDir -Recurse -Force
    }
    New-Item -ItemType Directory -Path $tempPluginDir -Force | Out-Null

    # Copy files to temporary directory with correct structure
    foreach ($file in $filesToInclude) {
        $relativePath = $file.FullName.Replace("$SourceDir\", "")
        $destPath = Join-Path $tempPluginDir $relativePath
        $destDir = Split-Path $destPath -Parent

        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }

        Copy-Item $file.FullName $destPath -Force
    }

    Write-Log "Copied files to temporary structure"

    # Create ZIP file
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($tempDir, $ZipPath)

    # Clean up temporary directory
    Remove-Item $tempDir -Recurse -Force

    # Verify ZIP file was created successfully
    if (-not (Test-Path $ZipPath)) {
        throw "ZIP file was not created"
    }

    $zipSize = (Get-Item $ZipPath).Length
    $zipSizeMB = [math]::Round($zipSize / 1024 / 1024, 2)

    Write-Log "Build completed successfully!"
    Write-Log "Output: $ZipPath"
    Write-Log "Size: $zipSizeMB MB ($($filesToInclude.Count) files)"

    # Verification: Check ZIP contents
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
    $entryCount = $zip.Entries.Count
    $firstEntry = $zip.Entries[0].FullName

    Write-Log "Verification: ZIP contains $entryCount entries"
    Write-Log "First entry: $firstEntry"

    # Check that first entry starts with wp-minpaku-connector/
    if ($firstEntry.StartsWith("wp-minpaku-connector/")) {
        Write-Log "✓ ZIP structure is correct"
    } else {
        Write-Log "✗ Warning: ZIP structure may be incorrect"
    }

    # Check for main plugin file
    $mainPluginFound = $false
    foreach ($entry in $zip.Entries) {
        if ($entry.FullName -eq "wp-minpaku-connector/wp-minpaku-connector.php") {
            $mainPluginFound = $true
            break
        }
    }

    if ($mainPluginFound) {
        Write-Log "✓ Main plugin file found in ZIP"
    } else {
        Write-Log "✗ Warning: Main plugin file not found in ZIP"
    }

    $zip.Dispose()

    Write-Log ""
    Write-Log "Distribution package ready for upload!"
    Write-Log ""
    Write-Log "Installation instructions (Japanese):"
    Write-Log "1. WordPress管理画面 > プラグイン > 新規追加 > プラグインのアップロード"
    Write-Log "2. ファイルを選択: $(Split-Path $ZipPath -Leaf)"
    Write-Log "3. 「今すぐインストール」をクリック後、「プラグインを有効化」"
    Write-Log "4. 設定 > Minpaku Connector で接続設定を行ってください"
    Write-Log ""
    Write-Log "設定値は上書きされませんが、念のため再保存（接続テストA/B/Cを確認）してください。"

} catch {
    Write-Log "Error occurred during build process"
    Write-Log $_.Exception.Message
    exit 1
}