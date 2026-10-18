# Modo desarrollo (solo el equipo): React con recarga en caliente en :5173 y API en :8000.
# Uso: powershell -ExecutionPolicy Bypass -File scripts\equipo\modo-desarrollo.ps1 [-Detener]
param([switch]$Detener)

$raiz = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location -LiteralPath $raiz
$archivos = @('-f', 'docker-compose.yml', '-f', 'docker-compose.dev.yml')

if ($Detener) {
    & docker compose @archivos stop
    exit $LASTEXITCODE
}

# El modo normal usa el mismo puerto web
& docker compose stop app | Out-Host
& docker compose @archivos up -d --build mysql app phpmyadmin mailpit frontend | Out-Host
Write-Host ''
Write-Host 'Modo desarrollo en marcha:' -ForegroundColor Green
Write-Host '  Web (recarga en caliente) ... http://localhost:5173'
Write-Host '  API ......................... http://localhost:8000/api/v1/health'
Write-Host '  Detener ..................... scripts\equipo\modo-desarrollo.ps1 -Detener'
Write-Host 'La primera vez instala dependencias dentro de los contenedores (unos minutos).'
