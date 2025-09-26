# Calendar Price Fix Build Script for MinPaku Suite & Connector

# Set error action preference
$ErrorActionPreference = "Stop"

Write-Host "=== Calendar Price Fix Build Script ===" -ForegroundColor Green
Write-Host "Building plugins with unified pricing calendar display..." -ForegroundColor Cyan

$BuildDate = Get-Date -Format "yyyyMMdd-HHmm"
$FixVersion = "0.4.1-calendar-fix-$BuildDate"

try {
    # Build MinPaku Suite
    Write-Host "`n--- Building MinPaku Suite ---" -ForegroundColor Yellow
    $SuiteDir = "C:\Users\user\.config\manicode\projects\minpaku-suite"

    if (Test-Path $SuiteDir) {
        Set-Location $SuiteDir

        # Update version in main file temporarily
        $MainFile = Join-Path $SuiteDir "minpaku-suite.php"
        if (Test-Path $MainFile) {
            $Content = Get-Content $MainFile -Raw
            $Content = $Content -replace "Version:\s*[\d\.-]+", "Version: $FixVersion"
            $Content = $Content -replace "define\s*\(\s*['`"]MINPAKU_SUITE_VERSION['`"]\s*,\s*['`"][\d\.-]+['`"]\s*\)", "define('MINPAKU_SUITE_VERSION', '$FixVersion')"
            Set-Content -Path $MainFile -Value $Content -NoNewline
        }

        # Run build
        powershell -ExecutionPolicy Bypass -File build.ps1

        # Rename the output file
        $OriginalZip = "build\releases\minpaku-suite-v$FixVersion.zip"
        $NewZip = "build\releases\minpaku-suite-calendar-fix-$BuildDate.zip"
        if (Test-Path $OriginalZip) {
            Move-Item $OriginalZip $NewZip -Force
            Write-Host "✓ MinPaku Suite built: $NewZip" -ForegroundColor Green
        }
    }

    # Build WP MinPaku Connector
    Write-Host "`n--- Building WP MinPaku Connector ---" -ForegroundColor Yellow
    $ConnectorDir = "C:\Users\user\.config\manicode\projects\minpaku-suite\connectors\wp-minpaku-connector"

    if (Test-Path $ConnectorDir) {
        Set-Location $ConnectorDir

        # Update version in main file temporarily
        $MainFile = Join-Path $ConnectorDir "wp-minpaku-connector.php"
        if (Test-Path $MainFile) {
            $Content = Get-Content $MainFile -Raw
            $Content = $Content -replace "Version:\s*[\d\.-]+", "Version: $FixVersion"
            $Content = $Content -replace "define\s*\(\s*['`"]WP_MINPAKU_CONNECTOR_VERSION['`"]\s*,\s*['`"][\d\.-]+['`"]\s*\)", "define('WP_MINPAKU_CONNECTOR_VERSION', '$FixVersion')"
            Set-Content -Path $MainFile -Value $Content -NoNewline
        }

        # Run build
        powershell -ExecutionPolicy Bypass -File build.ps1 -Version $FixVersion

        # Rename the output file
        $OriginalZip = "dist\wp-minpaku-connector-v$FixVersion.zip"
        $NewZip = "dist\wp-minpaku-connector-calendar-fix-$BuildDate.zip"
        if (Test-Path $OriginalZip) {
            Move-Item $OriginalZip $NewZip -Force
            Write-Host "✓ WP MinPaku Connector built: $NewZip" -ForegroundColor Green
        }
    }

    Write-Host "`n=== Build Summary ===" -ForegroundColor Green
    Write-Host "Calendar Price Fix Version: $FixVersion" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "✓ Fixed Issues:" -ForegroundColor Green
    Write-Host "  • Calendar shows correct unified pricing (Accommodation Rate + Cleaning Fee)" -ForegroundColor White
    Write-Host "  • Booking quotes use Accommodation Rate × nights + Cleaning Fee (one time)" -ForegroundColor White
    Write-Host "  • Both portal and connector side calendars synchronized" -ForegroundColor White
    Write-Host "  • Backward compatibility with legacy pricing fields maintained" -ForegroundColor White
    Write-Host ""
    Write-Host "Ready for deployment!" -ForegroundColor Green

} catch {
    Write-Host "Build failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}