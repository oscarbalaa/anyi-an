# Borra contenedores, imágenes y volúmenes de AYNI (incluida la base de datos local).
# No toca el código ni los respaldos.
$raiz = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location -LiteralPath $raiz

Write-Host 'Esto BORRA la base de datos local, los archivos subidos y las imágenes de AYNI.' -ForegroundColor Red
$r = Read-Host 'Escribe BORRAR para continuar'
if ($r -cne 'BORRAR') {
    Write-Host 'Cancelado.'
    exit 1
}

& docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile pruebas down -v --rmi local --remove-orphans
Write-Host 'Listo. Para volver a instalar: 1. INICIO\AYNI.bat, opción 1.' -ForegroundColor Green
