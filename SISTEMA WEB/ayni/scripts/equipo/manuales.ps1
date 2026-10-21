# Arma los manuales finales en 1. INICIO\MANUAL (Word y PDF) desde docs\manual (Módulo 15).
#   powershell -ExecutionPolicy Bypass -File scripts\equipo\manuales.ps1               (antes regenera las capturas)
#   powershell -ExecutionPolicy Bypass -File scripts\equipo\manuales.ps1 -SinCapturas  (usa las capturas que ya hay)
# AYNI debe estar encendido para las capturas. La CI hace lo mismo en los commits con [manuales].
param([switch]$SinCapturas)
$raiz = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location -LiteralPath $raiz
if (-not $SinCapturas) {
    & (Join-Path $PSScriptRoot 'capturas.ps1')
    if ($LASTEXITCODE) { Write-Host 'Las pruebas E2E fallaron: revisa las capturas antes de armar los manuales.' -ForegroundColor Red; exit 1 }
}
& docker compose --profile pruebas run --rm --no-deps manuales | Out-Host
if ($LASTEXITCODE) { Write-Host 'No se pudieron armar los manuales.' -ForegroundColor Red; exit 1 }
Write-Host 'Manuales listos en 1. INICIO\MANUAL' -ForegroundColor Green
