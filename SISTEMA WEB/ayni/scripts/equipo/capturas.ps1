# Regenera las capturas del manual (docs/manual/capturas) con las pruebas E2E.
$raiz = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location -LiteralPath $raiz
$env:AYNI_CAPTURAS = '/capturas'
& docker compose --profile pruebas run --rm pruebas-e2e sh -c 'npm ci --no-audit --no-fund && npx playwright test' | Out-Host
Remove-Item Env:\AYNI_CAPTURAS
Write-Host 'Capturas actualizadas en docs\manual\capturas' -ForegroundColor Green
