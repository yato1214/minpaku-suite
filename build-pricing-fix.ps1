# Pricing Fix Build Script for WP MinPaku Connector
# Fixes: Second property calendar pricing issue, Quote API cache isolation

# Set error action preference
$ErrorActionPreference = "Stop"

Write-Host "=== Pricing Fix Build Script ===" -ForegroundColor Green
Write-Host "Building connector with calendar pricing fixes..." -ForegroundColor Cyan

$BuildDate = Get-Date -Format "yyyyMMdd-HHmm"
$FixVersion = "1.1.5-pricing-fix-$BuildDate"

try {
    # Build WP MinPaku Connector with pricing fixes
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
        $NewZip = "dist\wp-minpaku-connector-pricing-fix-$BuildDate.zip"
        if (Test-Path $OriginalZip) {
            Move-Item $OriginalZip $NewZip -Force
            Write-Host "✓ WP MinPaku Connector (Pricing Fix) built: $NewZip" -ForegroundColor Green
        }
    }

    Write-Host "`n=== Pricing Fix Build Summary ===" -ForegroundColor Green
    Write-Host "Pricing Fix Version: $FixVersion" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "🔧 Fixed Issues:" -ForegroundColor Red
    Write-Host "  • Second property calendar pricing issue resolved" -ForegroundColor White
    Write-Host "  • Quote API cache isolation per property implemented" -ForegroundColor White
    Write-Host "  • Property-specific WordPress transient caching" -ForegroundColor White
    Write-Host "  • Enhanced debug logging for price tracking" -ForegroundColor White
    Write-Host "  • Cross-property cache contamination prevention" -ForegroundColor White
    Write-Host ""
    Write-Host "✅ Technical Improvements:" -ForegroundColor Green
    Write-Host "  • Each property gets independent QuoteApi instance" -ForegroundColor White
    Write-Host "  • Memory cache cleared per instance to prevent pollution" -ForegroundColor White
    Write-Host "  • Property ID included in transient cache keys" -ForegroundColor White
    Write-Host "  • Detailed cache hit/miss logging for debugging" -ForegroundColor White
    Write-Host "  • Quote API response validation enhanced" -ForegroundColor White
    Write-Host ""
    Write-Host "🚀 Deployment Instructions:" -ForegroundColor Yellow
    Write-Host "  1. Backup current wp-minpaku-connector plugin" -ForegroundColor Gray
    Write-Host "  2. Deactivate current plugin in WordPress admin" -ForegroundColor Gray
    Write-Host "  3. Upload new plugin ZIP via WordPress admin Plugins Add New Upload" -ForegroundColor Gray
    Write-Host "  4. Activate the new plugin version" -ForegroundColor Gray
    Write-Host "  5. Test property list page to verify pricing display" -ForegroundColor Gray
    Write-Host "  6. Check debug logs if WP_DEBUG_LOG is enabled" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Ready for deployment!" -ForegroundColor Green

} catch {
    Write-Host "Build failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}