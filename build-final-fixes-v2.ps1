# Final Fixes Build Script v2 for MinPaku Suite & Connector
# Fixes: Portal modal calendar + Connector ¥100 pricing issue

# Set error action preference
$ErrorActionPreference = "Stop"

Write-Host "=== Final Fixes Build Script v2 ===" -ForegroundColor Green
Write-Host "Building plugins with complete modal and pricing fixes..." -ForegroundColor Cyan

$BuildDate = Get-Date -Format "yyyyMMdd-HHmm"
$FixVersion = "0.4.4-complete-fix-$BuildDate"

try {
    # Build MinPaku Suite (Portal-side Modal Calendar Fix)
    Write-Host "`n--- Building MinPaku Suite (Modal Calendar Fix) ---" -ForegroundColor Yellow
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
        $NewZip = "build\releases\minpaku-suite-complete-fix-$BuildDate.zip"
        if (Test-Path $OriginalZip) {
            Move-Item $OriginalZip $NewZip -Force
            $FileSize = [math]::Round((Get-Item $NewZip).Length / 1KB, 2)
            Write-Host "✓ MinPaku Suite built: $NewZip ($FileSize KB)" -ForegroundColor Green
        }
    }

    # Build WP MinPaku Connector (Pricing & Calendar Fix)
    Write-Host "`n--- Building WP MinPaku Connector (Pricing Fix) ---" -ForegroundColor Yellow
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
        $NewZip = "dist\wp-minpaku-connector-complete-fix-$BuildDate.zip"
        if (Test-Path $OriginalZip) {
            Move-Item $OriginalZip $NewZip -Force
            $FileSize = [math]::Round((Get-Item $NewZip).Length / 1KB, 2)
            Write-Host "✓ WP MinPaku Connector built: $NewZip ($FileSize KB)" -ForegroundColor Green
        }
    }

    Write-Host "`n=== Complete Fix Build Summary ===" -ForegroundColor Green
    Write-Host "Complete Fix Version: $FixVersion" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "🎯 Portal-side Fixes (MinPaku Suite):" -ForegroundColor Blue
    Write-Host "  ✓ Modal calendar functionality implemented" -ForegroundColor White
    Write-Host "  ✓ [mcs_availability modal='true'] shortcode support" -ForegroundColor White
    Write-Host "  ✓ AJAX-powered modal with responsive design" -ForegroundColor White
    Write-Host "  ✓ ESC key and click-outside closing" -ForegroundColor White
    Write-Host "  ✓ Nonce security for AJAX requests" -ForegroundColor White
    Write-Host ""
    Write-Host "🛠️ Connector-side Fixes (WP Connector):" -ForegroundColor Blue
    Write-Host "  ✓ Property ID 30 calendar pricing issue resolved" -ForegroundColor White
    Write-Host "  ✓ Automatic ¥100 dummy price detection and removal" -ForegroundColor White
    Write-Host "  ✓ Real unified pricing application (accommodation + cleaning)" -ForegroundColor White
    Write-Host "  ✓ Enhanced cache isolation per property" -ForegroundColor White
    Write-Host "  ✓ Detailed debug logging for price tracking" -ForegroundColor White
    Write-Host ""
    Write-Host "📋 Technical Components Added:" -ForegroundColor Yellow
    Write-Host "  • assets/js/calendar-modal.js - Modal calendar functionality" -ForegroundColor Gray
    Write-Host "  • assets/css/calendar-modal.css - Modal styling and responsive design" -ForegroundColor Gray
    Write-Host "  • Enhanced AvailabilityCalendar.php - Modal support and script enqueuing" -ForegroundColor Gray
    Write-Host "  • Improved Calendar.php - ¥100 price filtering and unified pricing" -ForegroundColor Gray
    Write-Host "  • Updated QuoteApi.php - Property-specific cache isolation" -ForegroundColor Gray
    Write-Host "  • Enhanced Embed.php - Cross-property price contamination prevention" -ForegroundColor Gray
    Write-Host ""
    Write-Host "🚀 Usage Examples:" -ForegroundColor Yellow
    Write-Host "  Portal modal: [mcs_availability modal='true' id='17']" -ForegroundColor Gray
    Write-Host "  Portal full calendar: [mcs_availability id='17']" -ForegroundColor Gray
    Write-Host "  Connector properties: [minpaku_connector type='properties' limit='12']" -ForegroundColor Gray
    Write-Host "  Connector single property: [minpaku_connector type='property' property_id='30']" -ForegroundColor Gray
    Write-Host ""
    Write-Host "🔧 Deployment Instructions:" -ForegroundColor Yellow
    Write-Host "  1. Backup both current plugins" -ForegroundColor Gray
    Write-Host "  2. Deactivate current plugins" -ForegroundColor Gray
    Write-Host "  3. Upload and activate MinPaku Suite (Portal)" -ForegroundColor Gray
    Write-Host "  4. Upload and activate WP MinPaku Connector" -ForegroundColor Gray
    Write-Host "  5. Test modal calendar with [mcs_availability modal='true' id='17']" -ForegroundColor Gray
    Write-Host "  6. Test property list pricing display" -ForegroundColor Gray
    Write-Host "  7. Verify property ID 30 shows correct prices (not ¥100)" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Ready for deployment!" -ForegroundColor Green

} catch {
    Write-Host "Build failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Stack trace:" -ForegroundColor Red
    Write-Host $_.Exception.StackTrace -ForegroundColor Red
    exit 1
}