# Exporta la base de datos local a docs/bd: script .sql (estructura y con datos),
# diccionario de datos y diagrama ER. Requiere AYNI encendido.
$raiz = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location -LiteralPath $raiz
$destino = Join-Path $raiz 'docs\bd'
New-Item -ItemType Directory -Force -Path $destino | Out-Null

$tarea = @'
set -e
opciones="--single-transaction --routines --triggers --default-character-set=utf8mb4 --skip-dump-date"
mysqldump -uroot -p$MYSQL_ROOT_PASSWORD $opciones --no-data $MYSQL_DATABASE > /tmp/ayni_esquema.sql 2>/dev/null
mysqldump -uroot -p$MYSQL_ROOT_PASSWORD $opciones $MYSQL_DATABASE > /tmp/ayni_con_datos_demo.sql 2>/dev/null
'@
$temporal = Join-Path $env:TEMP 'ayni-exportar.sh'
[IO.File]::WriteAllText($temporal, ($tarea -replace "`r`n", "`n"), (New-Object Text.UTF8Encoding $false))
& docker compose cp $temporal mysql:/tmp/ayni-exportar.sh | Out-Host
& docker compose exec -T mysql sh /tmp/ayni-exportar.sh | Out-Host
if ($LASTEXITCODE -ne 0) { Write-Host 'No se pudo exportar. ¿AYNI está encendido?' -ForegroundColor Red; exit 1 }
& docker compose cp mysql:/tmp/ayni_esquema.sql (Join-Path $destino 'ayni_esquema.sql') | Out-Host
& docker compose cp mysql:/tmp/ayni_con_datos_demo.sql (Join-Path $destino 'ayni_con_datos_demo.sql') | Out-Host

& docker compose --profile pruebas run --rm -v "${destino}:/salida" pruebas-backend php artisan ayni:documentar-bd --salida=/salida | Out-Host

Write-Host 'Exportado en docs\bd: ayni_esquema.sql, ayni_con_datos_demo.sql, diccionario.md y diagrama-er.md' -ForegroundColor Green
