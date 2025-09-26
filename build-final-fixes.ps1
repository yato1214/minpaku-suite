# Final Fixes Build Script for MinPaku Suite & Connector
# Includes: Unified pricing, amenities display, calendar modal support

# Set error action preference
$ErrorActionPreference = "Stop"

Write-Host "=== Final Fixes Build Script ===" -ForegroundColor Green
Write-Host "Building plugins with all latest improvements..." -ForegroundColor Cyan

$BuildDate = Get-Date -Format "yyyyMMdd-HHmm"
$FixVersion = "0.4.2-final-$BuildDate"

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
        $NewZip = "build\releases\minpaku-suite-final-$BuildDate.zip"
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
        $NewZip = "dist\wp-minpaku-connector-final-$BuildDate.zip"
        if (Test-Path $OriginalZip) {
            Move-Item $OriginalZip $NewZip -Force
            Write-Host "✓ WP MinPaku Connector built: $NewZip" -ForegroundColor Green
        }
    }

    Write-Host "`n=== Final Build Summary ===" -ForegroundColor Green
    Write-Host "Final Fix Version: $FixVersion" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "✓ All Improvements Included:" -ForegroundColor Green
    Write-Host "  • Unified pricing: Calendar shows Accommodation Rate + Cleaning Fee" -ForegroundColor White
    Write-Host "  • Quote calculation: Accommodation Rate × nights + Cleaning Fee (once)" -ForegroundColor White
    Write-Host "  • Property list shows amenities instead of availability legend" -ForegroundColor White
    Write-Host "  • Calendar modal option: [mcs_availability modal=`"true`"]" -ForegroundColor White
    Write-Host "  • Enhanced calendar buttons with icons and names" -ForegroundColor White
    Write-Host "  • Backward compatibility with legacy pricing fields" -ForegroundColor White
    Write-Host ""
    Write-Host "Usage Examples:" -ForegroundColor Yellow
    Write-Host "  Portal side modal: [mcs_availability modal=`"true`" id=`"17`"]" -ForegroundColor Gray
    Write-Host "  Portal side full calendar: [mcs_availability id=`"17`"]" -ForegroundColor Gray
    Write-Host "  Connector properties: [minpaku_connector type=`"properties`" limit=`"12`" columns=`"3`"]" -ForegroundColor Gray
    Write-Host "  Connector single property: [minpaku_connector type=`"property`" property_id=`"17`"]" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Ready for final deployment!" -ForegroundColor Green

} catch {
    Write-Host "Build failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}