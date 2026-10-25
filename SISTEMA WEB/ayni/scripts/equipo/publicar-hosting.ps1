# Prepara AYNI para el hosting y lo sube por FTPS a la carpeta grupo02 (Módulo 14).
#
#   powershell -ExecutionPolicy Bypass -File scripts\equipo\publicar-hosting.ps1
#       1. arma la copia en dist-hosting\grupo02 (proyecto + React compilado para /grupo02/ + vendor + .env del servidor)
#       2. compara con lo que se subió la última vez y muestra el resumen
#       3. con tu OK, sube solo lo que cambió (y borra del FTP lo que ya no existe)
#       4. ejecuta la prueba de humo (solo lectura) contra la URL pública
#
#   -SoloPreparar   arma la copia y no sube nada (lo usa la CI)
#   -BaseDeDatos    además genera dist-hosting\ayni_hosting.sql (datos de demostración de hoy, compatible con MariaDB)
#   -SinHumo        no ejecuta la prueba de humo al final
#   -Paquete        arma dist-hosting\ayni.tar.gz para subirlo a mano a un VPS (CloudPanel) y no sube nada
#   -Credenciales   otro archivo de datos, por ejemplo "1. INICIO\CREDENCIALES_VPS.txt"
#
# Los datos del FTP, la BD del hosting y el correo se leen de "1. INICIO\CREDENCIALES_PRIVADAS.txt"
# (plantilla: "1. INICIO\CREDENCIALES_PRIVADAS.ejemplo.txt"). Ese archivo nunca se sube.
param(
    [switch]$SoloPreparar,
    [switch]$BaseDeDatos,
    [switch]$SinHumo,
    [switch]$Paquete,
    [string]$Credenciales = '',
    [string]$Carpeta = 'grupo02'
)

$ErrorActionPreference = 'Stop'
# git devuelve los nombres en UTF-8 (tildes y ñ)
try { [Console]::OutputEncoding = New-Object Text.UTF8Encoding $false } catch { }
$raiz = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location -LiteralPath $raiz
$salida = Join-Path $raiz 'dist-hosting'
$destino = Join-Path $salida $Carpeta
$manifiestoSubido = Join-Path $salida 'manifiesto-subido.json'

function Paso([string]$t) { Write-Host ''; Write-Host ">> $t" -ForegroundColor Cyan }
function Ok([string]$t) { Write-Host "  [OK] $t" -ForegroundColor Green }
function Aviso([string]$t) { Write-Host "  [!]  $t" -ForegroundColor Yellow }
function Fallo([string]$t) { Write-Host "  [X]  $t" -ForegroundColor Red; exit 1 }
function Ejecutar([string]$programa, [string[]]$argumentos) {
    & $programa @argumentos
    if ($LASTEXITCODE -ne 0) { Fallo "Falló: $programa $($argumentos -join ' ')" }
}
function Escribir-Utf8([string]$ruta, [string]$texto) {
    [IO.File]::WriteAllText($ruta, ($texto -replace "`r`n", "`n"), (New-Object Text.UTF8Encoding $false))
}

# ---------- Credenciales (CLAVE=valor; las líneas con # se ignoran) ----------
function Leer-Credenciales([string]$ruta) {
    if (-not (Test-Path -LiteralPath $ruta)) {
        Fallo "No existe $ruta. Copia CREDENCIALES_PRIVADAS.ejemplo.txt con ese nombre y complétalo."
    }
    $datos = @{}
    foreach ($linea in Get-Content -LiteralPath $ruta -Encoding UTF8) {
        if ($linea -match '^\s*([A-Z_]+)\s*=\s*(.*)$') {
            # Una clave repetida (la del ejemplo y la propia) haría ganar en silencio la de más abajo
            if ($datos.ContainsKey($Matches[1])) { Fallo "$($Matches[1]) aparece más de una vez en $ruta. Deja solo una línea." }
            $datos[$Matches[1]] = $Matches[2].Trim()
        }
    }
    foreach ($clave in 'URL', 'BD_NOMBRE', 'BD_USUARIO', 'BD_CLAVE') {
        if (-not $datos[$clave]) { Fallo "Falta $clave en $ruta" }
    }
    if (-not $datos['APP_KEY']) {
        # La clave de la aplicación se crea una sola vez y se guarda: si cambia, se invalidan los datos cifrados
        $bytes = New-Object byte[] 32
        [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
        $datos['APP_KEY'] = 'base64:' + [Convert]::ToBase64String($bytes)
        Add-Content -LiteralPath $ruta -Value "APP_KEY=$($datos['APP_KEY'])" -Encoding UTF8
        Aviso "Se creó APP_KEY y se guardó en $(Split-Path -Leaf $ruta) (no la cambies después)."
    }
    $datos['URL'] = $datos['URL'].TrimEnd('/') + '/'
    return $datos
}

# Valor entre comillas para el .env: escapa \ " y $ (una contraseña puede tenerlos)
function Comillas([string]$valor) {
    return '"' + (($valor -replace '\\', '\\') -replace '"', '\"' -replace '\$', '\$$') + '"'
}

function Env-Hosting($c, [string]$secreto = '', [long]$hasta = 0) {
    $v = { param($clave, $porDefecto) if ($c[$clave]) { $c[$clave] } else { $porDefecto } }
    $lineas = @(
        '# Configuración del servidor (la arma publicar-hosting.ps1). En local no se usa.'
        'APP_NAME=AYNI'
        'APP_ENV=production'
        "APP_KEY=$($c['APP_KEY'])"
        'APP_DEBUG=false'
        "APP_URL=$($c['URL'].TrimEnd('/'))"
        'LOG_CHANNEL=daily'
        'LOG_LEVEL=warning'
        'DB_CONNECTION=mysql'
        "DB_HOST=$(& $v 'BD_HOST' 'localhost')"
        "DB_PORT=$(& $v 'BD_PUERTO' '3306')"
        "DB_DATABASE=$($c['BD_NOMBRE'])"
        "DB_USERNAME=$($c['BD_USUARIO'])"
        "DB_PASSWORD=$(Comillas $c['BD_CLAVE'])"
        'CACHE_DRIVER=file'
        'FILESYSTEM_DISK=local'
        'QUEUE_CONNECTION=sync'
        'SESSION_DRIVER=file'
        'SESSION_LIFETIME=60'
        "MAIL_MAILER=$(if ($c['CORREO_USUARIO']) { 'smtp' } else { 'log' })"
        "MAIL_HOST=$(& $v 'CORREO_SERVIDOR' 'smtp.gmail.com')"
        "MAIL_PORT=$(& $v 'CORREO_PUERTO' '587')"
        "MAIL_USERNAME=$($c['CORREO_USUARIO'])"
        "MAIL_PASSWORD=$(Comillas $c['CORREO_CLAVE'])"
        'MAIL_ENCRYPTION=tls'
        "MAIL_FROM_ADDRESS=$(& $v 'CORREO_REMITENTE' $c['CORREO_USUARIO'])"
        'MAIL_FROM_NAME=AYNI'
    )
    # VPS con CloudPanel: Nginx se reenvía a sí mismo (127.0.0.1); así AYNI ve la IP real de cada visitante
    if ($c['PROXIES_CONFIABLES']) { $lineas += "AYNI_PROXIES_CONFIABLES=$($c['PROXIES_CONFIABLES'])" }
    # Solo mientras corre la prueba de humo (vence sola; ver docs/seguridad.md)
    if ($secreto) { $lineas += "AYNI_SECRETO_E2E=$secreto"; $lineas += "AYNI_SECRETO_E2E_HASTA=$hasta" }
    return ($lineas -join "`n") + "`n"
}

# ---------- 1. Preparar la copia ----------
function Preparar($c) {
    Paso 'Preparar la copia para el hosting'
    if (-not (Get-Command git -ErrorAction SilentlyContinue)) { Fallo 'Falta git.' }
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { Fallo 'Falta Docker: instala AYNI con el panel primero.' }

    if (Test-Path -LiteralPath $destino) { Remove-Item -LiteralPath $destino -Recurse -Force }
    New-Item -ItemType Directory -Force -Path $destino | Out-Null

    # Solo lo que está en git: así nunca viajan .env locales, CREDENCIALES_PRIVADAS, node_modules ni respaldos
    $archivos = (& git -c core.quotepath=off ls-files) | Where-Object { $_ -and $_ -notmatch '^\.github/' }
    if ($LASTEXITCODE -ne 0 -or -not $archivos) { Fallo 'No se pudo listar los archivos del proyecto con git.' }
    foreach ($a in $archivos) {
        $origen = Join-Path $raiz $a
        if (-not (Test-Path -LiteralPath $origen -PathType Leaf)) { continue }
        $copia = Join-Path $destino $a
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $copia) | Out-Null
        Copy-Item -LiteralPath $origen -Destination $copia
    }
    Ok "$(@($archivos).Count) archivos del proyecto"

    # React compilado para la carpeta de la URL (/grupo02/ en el hosting del curso, / en un dominio propio)
    # y vendor de Laravel sin dependencias de desarrollo (no hay SSH para Composer)
    $base = ([Uri]$c['URL']).AbsolutePath
    Ejecutar docker @('compose', 'build', 'app')
    Ejecutar docker @('build', '-q', '-f', 'docker/app/Dockerfile', '--target', 'frontend', '--build-arg', "AYNI_BASE=$base", '-t', 'ayni-frontend-hosting:local', '.')
    foreach ($par in @(@('ayni-frontend-hosting:local', '/app/dist/.', 'backend/public'), @('ayni-app:local', '/var/www/ayni/backend/vendor', 'backend'), @('ayni-app:local', '/var/www/ayni/backend/bootstrap/cache/.', 'backend/bootstrap/cache'))) {
        $id = (& docker create $par[0]).Trim()
        try { Ejecutar docker @('cp', "${id}:$($par[1])", (Join-Path $destino $par[2])) }
        finally { & docker rm $id | Out-Null }
    }
    Ok "React para $base, vendor/ y bootstrap/cache"

    Escribir-Utf8 (Join-Path $destino 'backend/.env') (Env-Hosting $c)
    Ok 'backend/.env del servidor (APP_ENV=production, APP_DEBUG=false)'
}

# ---------- Base de datos de demostración para importar en el hosting ----------
# Windows PowerShell 5.1 no escapa las comillas al pasar argumentos a un programa: el guion viaja en base64
function En-Mysql([string]$guion) {
    $b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes(($guion -replace "`r", '')))
    & docker compose exec -T mysql sh -c "echo $b64 | base64 -d | sh"
}

function Exportar-Bd {
    Paso 'Base de datos para el hosting (datos de demostración de hoy)'
    $tarea = @'
set -e
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS ayni_publicar; CREATE DATABASE ayni_publicar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON ayni_publicar.* TO '$MYSQL_USER'@'%';" 2>/dev/null
'@
    Ejecutar docker @('compose', 'up', '-d', '--wait', 'mysql')
    # El .ps1 se guarda con fin de línea de Windows: el sh del contenedor no acepta los \r
    En-Mysql $tarea
    if ($LASTEXITCODE -ne 0) { Fallo 'No se pudo crear la base de datos temporal ayni_publicar.' }
    # Los PDF de ejemplo de la demo también viajan (storage/app/demo)
    $archivosDemo = Join-Path $destino 'backend/storage/app'
    Ejecutar docker @('compose', 'run', '--rm', '--no-deps', '-e', 'DB_DATABASE=ayni_publicar', '-v', "${archivosDemo}:/salida", 'app', 'sh', '-c', 'php backend/artisan migrate:fresh --seed --force && mkdir -p /salida/demo && cp backend/storage/app/demo/*.pdf /salida/demo/ && chmod -R a+rwX /salida/demo')
    $archivo = Join-Path $salida 'ayni_hosting.sql'
    $dump = En-Mysql 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --default-character-set=utf8mb4 --skip-dump-date --no-tablespaces ayni_publicar 2>/dev/null'
    if ($LASTEXITCODE -ne 0 -or -not $dump) { Fallo 'No se pudo exportar la base de datos.' }
    # MariaDB ejecuta los comentarios /*!8xxxx … */ de MySQL 8 y no conoce algunos (DEFAULT ENCRYPTION): se quitan
    $texto = (($dump -join "`n") -replace '/\*!80\d{3}\s[^*]*\*/', '') + "`n"
    Escribir-Utf8 $archivo $texto
    En-Mysql 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE ayni_publicar" 2>/dev/null' | Out-Null
    Ok "dist-hosting\ayni_hosting.sql: impórtalo en el phpMyAdmin del hosting (o envíaselo al profesor)"
}

# ---------- 2. Comparar con lo subido ----------
function Manifiesto {
    $sha = [Security.Cryptography.SHA256]::Create()
    $m = @{}
    foreach ($f in Get-ChildItem -LiteralPath $destino -Recurse -File -Force) {
        $relativa = $f.FullName.Substring($destino.Length + 1).Replace('\', '/')
        $flujo = [IO.File]::OpenRead($f.FullName)
        try { $m[$relativa] = [BitConverter]::ToString($sha.ComputeHash($flujo)).Replace('-', '') }
        finally { $flujo.Dispose() }
    }
    return $m
}

function Leer-Manifiesto {
    $m = @{}
    if (Test-Path -LiteralPath $manifiestoSubido) {
        (Get-Content -LiteralPath $manifiestoSubido -Raw -Encoding UTF8 | ConvertFrom-Json).PSObject.Properties | ForEach-Object { $m[$_.Name] = $_.Value }
    }
    return $m
}

function Comparar($actual) {
    $anterior = Leer-Manifiesto
    $nuevos = @($actual.Keys | Where-Object { -not $anterior.ContainsKey($_) } | Sort-Object)
    $cambiados = @($actual.Keys | Where-Object { $anterior.ContainsKey($_) -and $anterior[$_] -ne $actual[$_] } | Sort-Object)
    $borrados = @($anterior.Keys | Where-Object { -not $actual.ContainsKey($_) } | Sort-Object)
    return @{ nuevos = $nuevos; cambiados = $cambiados; borrados = $borrados }
}

# ---------- 3. FTPS ----------
function Ftp-Solicitud($c, [string]$ruta, [string]$metodo, [switch]$DesdeLaRaiz) {
    $completa = if ($DesdeLaRaiz) { $ruta } else { "$($c['FTP_CARPETA'].Trim('/'))/$ruta" }
    $partes = ($completa.Trim('/') -split '/' | ForEach-Object { [Uri]::EscapeDataString($_) }) -join '/'
    $solicitud = [Net.FtpWebRequest]::Create("ftp://$($c['FTP_HOST'])/$partes")
    $solicitud.Method = $metodo
    $solicitud.Credentials = New-Object Net.NetworkCredential($c['FTP_USUARIO'], $c['FTP_CLAVE'])
    $solicitud.EnableSsl = $true
    $solicitud.UsePassive = $true
    $solicitud.UseBinary = $true
    $solicitud.KeepAlive = $true
    $solicitud.ConnectionGroupName = $script:conexionFtp
    return $solicitud
}

function Ftp-Carpeta($c, [string]$ruta, $creadas) {
    $acumulada = ''
    foreach ($parte in ($ruta -split '/')) {
        $acumulada = if ($acumulada) { "$acumulada/$parte" } else { $parte }
        if ($creadas.Contains($acumulada)) { continue }
        try { (Ftp-Solicitud $c $acumulada ([Net.WebRequestMethods+Ftp]::MakeDirectory)).GetResponse().Close() } catch { }
        [void]$creadas.Add($acumulada)
    }
}

$script:conexionFtp = 'ayni'

# Algunos FTP compartidos cortan la conexión TLS de vez en cuando (451, 425): se reintenta con una conexión nueva
function Ftp-Subir-Reintentando($c, [string]$relativa, $creadas) {
    for ($intento = 1; ; $intento++) {
        try { Ftp-Subir $c $relativa $creadas; return }
        catch {
            if ($intento -ge 4) { throw }
            $script:conexionFtp = [guid]::NewGuid().ToString()
            Start-Sleep -Seconds (2 * $intento)
        }
    }
}

function Ftp-Subir($c, [string]$relativa, $creadas) {
    $carpeta = Split-Path -Parent $relativa.Replace('/', [IO.Path]::DirectorySeparatorChar)
    if ($carpeta) { Ftp-Carpeta $c ($carpeta.Replace('\', '/')) $creadas }
    $solicitud = Ftp-Solicitud $c $relativa ([Net.WebRequestMethods+Ftp]::UploadFile)
    $bytes = [IO.File]::ReadAllBytes((Join-Path $destino $relativa))
    $solicitud.ContentLength = $bytes.Length
    $flujo = $solicitud.GetRequestStream()
    try { $flujo.Write($bytes, 0, $bytes.Length) } finally { $flujo.Close() }
    $solicitud.GetResponse().Close()
}

function Ftp-Configurar($c) {
    foreach ($clave in 'FTP_HOST', 'FTP_USUARIO', 'FTP_CLAVE') { if (-not $c[$clave]) { Fallo "Falta $clave en CREDENCIALES_PRIVADAS.txt" } }
    if (-not $c['FTP_CARPETA']) { $c['FTP_CARPETA'] = "/$Carpeta" }
    # Si el certificado del FTP no coincide con su nombre, se acepta solo el de esta huella (SHA-1)
    $huella = $c['FTP_HUELLA_CERTIFICADO']
    if ($huella) {
        $esperada = ($huella -replace '[^0-9A-Fa-f]', '').ToUpperInvariant()
        [Net.ServicePointManager]::ServerCertificateValidationCallback = {
            param($s, $certificado, $cadena, $errores)
            $errores -eq 'None' -or $certificado.GetCertHashString().ToUpperInvariant() -eq $esperada
        }.GetNewClosure()
    }
}

function Subir($c, $cambios, $actual) {
    Ftp-Configurar $c
    $creadas = New-Object 'System.Collections.Generic.HashSet[string]'
    # La carpeta del grupo (por si aún no existe)
    $acumulada = ''
    foreach ($parte in ($c['FTP_CARPETA'].Trim('/') -split '/')) {
        $acumulada = "$acumulada/$parte"
        try { (Ftp-Solicitud $c $acumulada ([Net.WebRequestMethods+Ftp]::MakeDirectory) -DesdeLaRaiz).GetResponse().Close() } catch { }
    }
    $lista = @($cambios.nuevos) + @($cambios.cambiados)
    # El .htaccess y el .env van al final: así la web no queda a medias con reglas nuevas
    $ultimos = @($lista | Where-Object { $_ -match '(^|/)\.(htaccess|env)$' })
    $orden = @($lista | Where-Object { $ultimos -notcontains $_ }) + $ultimos
    # El manifiesto guarda lo que ya subió: si algo se corta, la próxima vez sigue desde ahí
    $registro = Leer-Manifiesto
    $fallidos = @()
    $n = 0
    try {
        foreach ($relativa in $orden) {
            $n++
            Write-Progress -Activity 'Subiendo por FTPS' -Status "$n de $($orden.Count): $relativa" -PercentComplete ([int](100 * $n / [Math]::Max(1, $orden.Count)))
            try { Ftp-Subir-Reintentando $c $relativa $creadas; $registro[$relativa] = $actual[$relativa] }
            catch { $fallidos += $relativa; Aviso "No se pudo subir $relativa : $($_.Exception.Message)" }
            if ($n % 50 -eq 0) { Escribir-Utf8 $manifiestoSubido ($registro | ConvertTo-Json -Depth 2) }
        }
        Write-Progress -Activity 'Subiendo por FTPS' -Completed
        foreach ($relativa in $cambios.borrados) {
            try { (Ftp-Solicitud $c $relativa ([Net.WebRequestMethods+Ftp]::DeleteFile)).GetResponse().Close() }
            catch { Aviso "No se pudo borrar $relativa del FTP (ya no estaba)." }
            $registro.Remove($relativa)
        }
    }
    finally { Escribir-Utf8 $manifiestoSubido ($registro | ConvertTo-Json -Depth 2) }
    if ($fallidos.Count) { Fallo "Archivos sin subir: $($fallidos.Count). Vuelve a ejecutar el mismo comando: solo sube lo que falta." }
    Ok "Subidos $($orden.Count) archivos; borrados $(@($cambios.borrados).Count)"
}

# ---------- 4. Prueba de humo (solo lectura) ----------
function Humo($c) {
    Paso "Prueba de humo contra $($c['URL'])"
    # Secreto de un solo uso que vence en 30 minutos: permite ingresar sin leer el captcha mientras dura la prueba
    $bytes = New-Object byte[] 24
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $secreto = -join ($bytes | ForEach-Object { $_.ToString('x2') })
    $hasta = [DateTimeOffset]::UtcNow.AddMinutes(30).ToUnixTimeSeconds()
    $envDestino = Join-Path $destino 'backend/.env'
    $creadas = New-Object 'System.Collections.Generic.HashSet[string]'
    $resultado = 1
    try {
        Escribir-Utf8 $envDestino (Env-Hosting $c $secreto $hasta)
        Ftp-Subir-Reintentando $c 'backend/.env' $creadas
        & docker compose --profile pruebas run --rm --no-deps -e "AYNI_URL=$($c['URL'])" -e "AYNI_SECRETO_E2E=$secreto" pruebas-e2e sh -c 'npm ci --no-audit --no-fund && npx playwright test -c playwright.humo.config.ts'
        $resultado = $LASTEXITCODE
    }
    finally {
        # Siempre se vuelve a subir el .env sin el secreto
        Escribir-Utf8 $envDestino (Env-Hosting $c)
        Ftp-Subir-Reintentando $c 'backend/.env' $creadas
        Ok 'Se quitó el secreto de la prueba del servidor'
    }
    if ($resultado -ne 0) { Fallo 'La prueba de humo falló: revisa reportes\actual\humo.xml' }
    Ok 'Prueba de humo: todo bien'
}

# ---------- Programa ----------
$rutaCredenciales = if ($Credenciales) { $Credenciales } else { Join-Path $raiz '1. INICIO\CREDENCIALES_PRIVADAS.txt' }
$c = Leer-Credenciales $rutaCredenciales
Ok "Dirección pública: $($c['URL'])"
New-Item -ItemType Directory -Force -Path $salida | Out-Null
Preparar $c
if ($BaseDeDatos) { Exportar-Bd }
$actual = Manifiesto
$cambios = Comparar $actual

Paso 'Resumen'
Write-Host "  Destino: FTP $($c['FTP_HOST'])$($c['FTP_CARPETA'])  →  $($c['URL'])"
Write-Host "  Nuevos: $($cambios.nuevos.Count) · Cambiados: $($cambios.cambiados.Count) · Se borran del FTP: $($cambios.borrados.Count)"
foreach ($r in (@($cambios.cambiados) + @($cambios.nuevos) | Select-Object -First 15)) { Write-Host "    $r" }
if ($cambios.nuevos.Count + $cambios.cambiados.Count -gt 15) { Write-Host '    …' }

if ($SoloPreparar) { Ok "Copia lista en dist-hosting\$Carpeta (no se subió nada)"; exit 0 }
if ($Paquete) {
    # tar viene con Windows 10/11; en el VPS se descomprime con: tar -xzf ayni.tar.gz
    $tar = Join-Path $salida 'ayni.tar.gz'
    if (Test-Path -LiteralPath $tar) { Remove-Item -LiteralPath $tar -Force }
    # Rutas relativas: el tar de Git Bash (GNU) toma "C:\…" como un servidor remoto
    Push-Location -LiteralPath $salida
    try { Ejecutar tar @('-czf', 'ayni.tar.gz', '-C', $Carpeta, '.') } finally { Pop-Location }
    Ok "Paquete listo: dist-hosting\ayni.tar.gz ($([math]::Round((Get-Item -LiteralPath $tar).Length / 1MB, 1)) MB). No se subió nada."
    exit 0
}
if ($cambios.nuevos.Count + $cambios.cambiados.Count + $cambios.borrados.Count -eq 0) { Ok 'No hay cambios que subir.' }
else {
    $respuesta = Read-Host '  ¿Subir estos cambios al hosting? Escribe SI para continuar'
    if ($respuesta -ne 'SI') { Aviso 'Cancelado: no se subió nada.'; exit 1 }
    Paso 'Subir por FTPS'
    Subir $c $cambios $actual
}
if (-not $SinHumo) { Ftp-Configurar $c; Humo $c }
