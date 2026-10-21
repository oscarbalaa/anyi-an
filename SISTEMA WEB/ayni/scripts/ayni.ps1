#Requires -Version 5.1
# Panel de control de AYNI para Windows. Se abre con "1. INICIO\AYNI.bat".
# También acepta una acción directa:  ayni.ps1 -Accion encender  [-Si]
param(
    [string]$Accion = '',
    [switch]$Si
)

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { }

$script:Version = '0.1.0'
$script:Raiz = Split-Path -Parent $PSScriptRoot
$script:Inicio = Join-Path $script:Raiz '1. INICIO'
$script:ArchivoEnv = Join-Path $script:Raiz '.env'
$script:ArchivoEnvEjemplo = Join-Path $script:Raiz '.env.example'
$script:AutoSi = [bool]$Si
$script:UrlHosting = 'https://aplicacionenlanube.cetivirgendelapuerta.com/grupo02/'

# ---------------------------------------------------------------- salida

function Escribir-Ok([string]$Texto) { Write-Host "  [OK] $Texto" -ForegroundColor Green }
function Escribir-Error([string]$Texto) { Write-Host "  [X]  $Texto" -ForegroundColor Red }
function Escribir-Aviso([string]$Texto) { Write-Host "  [!]  $Texto" -ForegroundColor Yellow }
function Escribir-Info([string]$Texto) { Write-Host "       $Texto" -ForegroundColor Gray }
function Escribir-Paso([string]$Texto) { Write-Host ''; Write-Host ">> $Texto" -ForegroundColor Cyan }

function Pausa {
    if ($script:AutoSi -or $Accion) { return }
    Write-Host ''
    [void](Read-Host 'Presiona Enter para volver al menú')
}

function Confirmar([string]$Pregunta) {
    if ($script:AutoSi) { return $true }
    $r = Read-Host "$Pregunta (S/N)"
    return ($r -match '^(s|si|sí|y|yes)$')
}

# ---------------------------------------------------------------- configuración (.env)

function Leer-Env([string]$Ruta = $script:ArchivoEnv) {
    $valores = [ordered]@{}
    if (-not (Test-Path -LiteralPath $Ruta)) { return $valores }
    foreach ($linea in Get-Content -LiteralPath $Ruta -Encoding UTF8) {
        if ($linea -match '^\s*([A-Z0-9_]+)\s*=\s*(.*)$') { $valores[$Matches[1]] = $Matches[2].Trim() }
    }
    return $valores
}

function Escribir-ValorEnv([string]$Clave, [string]$Valor, [string]$Ruta = $script:ArchivoEnv) {
    $lineas = @()
    if (Test-Path -LiteralPath $Ruta) { $lineas = @(Get-Content -LiteralPath $Ruta -Encoding UTF8) }
    $encontrada = $false
    for ($i = 0; $i -lt $lineas.Count; $i++) {
        if ($lineas[$i] -match "^\s*$Clave\s*=") { $lineas[$i] = "$Clave=$Valor"; $encontrada = $true }
    }
    if (-not $encontrada) { $lineas += "$Clave=$Valor" }
    Guardar-Texto -Ruta $Ruta -Contenido (($lineas -join "`n") + "`n") -SinBom
}

function Guardar-Texto([string]$Ruta, [string]$Contenido, [switch]$SinBom, [switch]$Crlf) {
    if ($Crlf) { $Contenido = ($Contenido -replace "`r?`n", "`r`n") }
    $codificacion = New-Object System.Text.UTF8Encoding (-not $SinBom)
    [System.IO.File]::WriteAllText($Ruta, $Contenido, $codificacion)
}

function Nueva-ClaveApp {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $rng.GetBytes($bytes)
    $rng.Dispose()
    return 'base64:' + [Convert]::ToBase64String($bytes)
}

# Las pruebas E2E lo envían para saltar el captcha (nunca funciona en el hosting)
function Nuevo-SecretoE2E {
    $bytes = New-Object byte[] 20
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $rng.GetBytes($bytes)
    $rng.Dispose()
    return (($bytes | ForEach-Object { $_.ToString('x2') }) -join '')
}

function Asegurar-Env {
    if (-not (Test-Path -LiteralPath $script:ArchivoEnv)) {
        Copy-Item -LiteralPath $script:ArchivoEnvEjemplo -Destination $script:ArchivoEnv
        Escribir-Ok 'Configuración local creada (.env)'
    }
    $cfg = Leer-Env
    if (-not $cfg['AYNI_APP_KEY']) {
        Escribir-ValorEnv 'AYNI_APP_KEY' (Nueva-ClaveApp)
        Escribir-Ok 'Clave de la aplicación generada'
    }
    if (-not $cfg['AYNI_SECRETO_E2E']) {
        Escribir-ValorEnv 'AYNI_SECRETO_E2E' (Nuevo-SecretoE2E)
    }
}

function Puertos {
    $cfg = Leer-Env
    $defecto = @{ AYNI_PUERTO_WEB = 5173; AYNI_PUERTO_PMA = 8080; AYNI_PUERTO_CORREO = 8025; AYNI_PUERTO_BD = 3307 }
    $p = @{}
    foreach ($k in $defecto.Keys) {
        if ($cfg[$k]) { $p[$k] = [int]$cfg[$k] } else { $p[$k] = $defecto[$k] }
    }
    return $p
}

function Url-Web { return "http://localhost:$((Puertos).AYNI_PUERTO_WEB)" }

# ---------------------------------------------------------------- Docker

function Rutas-DockerDesktop {
    return @(
        (Join-Path $env:ProgramFiles 'Docker\Docker'),
        (Join-Path $env:LOCALAPPDATA 'Programs\DockerDesktop')
    )
}

function Test-DockerInstalado {
    if (Get-Command docker -ErrorAction SilentlyContinue) { return $true }
    foreach ($base in Rutas-DockerDesktop) {
        $cli = Join-Path $base 'resources\bin'
        if (Test-Path -LiteralPath (Join-Path $cli 'docker.exe')) {
            $env:Path = "$cli;$env:Path"
            return $true
        }
    }
    return $false
}

function Test-DockerCorriendo {
    if (-not (Test-DockerInstalado)) { return $false }
    & docker info *> $null
    return ($LASTEXITCODE -eq 0)
}

function Iniciar-DockerDesktop {
    $exe = $null
    foreach ($base in Rutas-DockerDesktop) {
        $candidato = Join-Path $base 'Docker Desktop.exe'
        if (Test-Path -LiteralPath $candidato) { $exe = $candidato; break }
    }
    if (-not $exe) { return $false }

    Escribir-Info 'Abriendo Docker Desktop (puede tardar hasta 2 minutos)...'
    Start-Process -FilePath $exe | Out-Null
    for ($i = 0; $i -lt 60; $i++) {
        Start-Sleep -Seconds 3
        if (Test-DockerCorriendo) { return $true }
        Write-Host '.' -NoNewline
    }
    Write-Host ''
    return $false
}

function Asegurar-Docker {
    if (-not (Test-DockerInstalado)) {
        Escribir-Error 'Docker Desktop no está instalado. Usa la opción [1] para instalarlo.'
        return $false
    }
    if (Test-DockerCorriendo) { return $true }
    Escribir-Aviso 'Docker Desktop está cerrado.'
    if (Iniciar-DockerDesktop) { Escribir-Ok 'Docker Desktop listo'; return $true }
    Escribir-Error 'Docker Desktop no respondió. Ábrelo manualmente, acepta sus términos si los pide y vuelve a intentar.'
    return $false
}

# Muestra en pantalla la salida de docker compose (no la devuelve como resultado)
function Compose {
    Push-Location -LiteralPath $script:Raiz
    try { & docker compose @args | Out-Host } finally { Pop-Location }
}

# Devuelve la salida de docker compose para usarla en el script
function Compose-Salida {
    Push-Location -LiteralPath $script:Raiz
    try { & docker compose @args 2> $null } finally { Pop-Location }
}

function Artisan {
    Compose exec -T -w /var/www/ayni/backend -u www-data app php artisan @args
}

function Servicios-Corriendo {
    $salida = Compose-Salida ps --status running --services
    if ($LASTEXITCODE -ne 0 -or -not $salida) { return @() }
    return @($salida | Where-Object { $_ })
}

function Test-Instalado {
    if (-not (Test-Path -LiteralPath $script:ArchivoEnv)) { return $false }
    & docker image inspect ayni-app:local *> $null
    return ($LASTEXITCODE -eq 0)
}

function Estado-Ayni {
    if (-not (Test-DockerCorriendo)) {
        if (Test-Path -LiteralPath $script:ArchivoEnv) { return 'APAGADO' }
        return 'NO INSTALADO'
    }
    if (-not (Test-Instalado)) { return 'NO INSTALADO' }
    if ((Servicios-Corriendo) -contains 'app') { return 'ENCENDIDO' }
    return 'APAGADO'
}

function Esperar-Api([int]$Segundos = 180) {
    $url = "$(Url-Web)/api/v1/health"
    Escribir-Info "Esperando a que AYNI responda en $url"
    $limite = (Get-Date).AddSeconds($Segundos)
    while ((Get-Date) -lt $limite) {
        try {
            $r = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5
            if ($r.StatusCode -eq 200) { Write-Host ''; return $true }
        } catch { }
        Write-Host '.' -NoNewline
        Start-Sleep -Seconds 3
    }
    Write-Host ''
    return $false
}

# ---------------------------------------------------------------- puertos

function Test-PuertoLibre([int]$Puerto) {
    foreach ($ip in @([System.Net.IPAddress]::Any, [System.Net.IPAddress]::Loopback)) {
        $escucha = $null
        try {
            $escucha = New-Object System.Net.Sockets.TcpListener($ip, $Puerto)
            $escucha.ExclusiveAddressUse = $true
            $escucha.Start()
        } catch {
            return $false
        } finally {
            if ($escucha) { $escucha.Stop() }
        }
    }
    return $true
}

function Buscar-PuertoLibre([int]$Desde) {
    for ($p = $Desde + 1; $p -lt $Desde + 200; $p++) {
        if (Test-PuertoLibre $p) { return $p }
    }
    return $null
}

function Programa-EnPuerto([int]$Puerto) {
    try {
        $con = Get-NetTCPConnection -LocalPort $Puerto -State Listen -ErrorAction Stop | Select-Object -First 1
        return (Get-Process -Id $con.OwningProcess -ErrorAction Stop).ProcessName
    } catch { return 'otro programa' }
}

function Revisar-Puertos {
    if ((Servicios-Corriendo).Count -gt 0) { return $true }
    $nombres = @{ AYNI_PUERTO_WEB = 'web'; AYNI_PUERTO_PMA = 'phpMyAdmin'; AYNI_PUERTO_CORREO = 'correos'; AYNI_PUERTO_BD = 'base de datos' }
    $puertos = Puertos
    $todoBien = $true
    foreach ($clave in @('AYNI_PUERTO_WEB', 'AYNI_PUERTO_PMA', 'AYNI_PUERTO_CORREO', 'AYNI_PUERTO_BD')) {
        $puerto = $puertos[$clave]
        if (Test-PuertoLibre $puerto) { continue }
        $programa = Programa-EnPuerto $puerto
        Escribir-Aviso "El puerto $puerto ($($nombres[$clave])) lo está usando: $programa"
        $alternativo = Buscar-PuertoLibre $puerto
        if ($alternativo -and (Confirmar "  ¿Usar el puerto $alternativo para $($nombres[$clave])?")) {
            Escribir-ValorEnv $clave $alternativo
            Escribir-Ok "$($nombres[$clave]) usará el puerto $alternativo"
        } else {
            Escribir-Error "Cierra el programa que usa el puerto $puerto y vuelve a intentar."
            $todoBien = $false
        }
    }
    if ($todoBien) { Actualizar-Credenciales }
    return $todoBien
}

# ---------------------------------------------------------------- requisitos

function Requisitos-Sistema {
    $res = @()
    $so = Get-CimInstance Win32_OperatingSystem
    $build = [int]$so.BuildNumber
    $res += [pscustomobject]@{ Nombre = "Windows ($($so.Caption), compilación $build)"; Ok = ($build -ge 19045 -and [Environment]::Is64BitOperatingSystem); Arreglo = 'Se necesita Windows 10 22H2 o Windows 11 de 64 bits.' }

    $ramGb = [math]::Round((Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory / 1GB, 1)
    $res += [pscustomobject]@{ Nombre = "Memoria RAM: $ramGb GB"; Ok = ($ramGb -ge 3.5); Arreglo = 'Se necesitan al menos 4 GB (8 GB recomendado).'; Aviso = ($ramGb -lt 7.5) }

    $unidad = (Get-Item -LiteralPath $script:Raiz).PSDrive
    $libreGb = [math]::Round($unidad.Free / 1GB, 1)
    $res += [pscustomobject]@{ Nombre = "Espacio libre en ${unidad}: $libreGb GB"; Ok = ($libreGb -ge 8); Arreglo = 'Libera espacio: se necesitan al menos 8 GB.' }

    $cs = Get-CimInstance Win32_ComputerSystem
    $cpu = Get-CimInstance Win32_Processor | Select-Object -First 1
    $virt = ($cs.HypervisorPresent -or $cpu.VirtualizationFirmwareEnabled)
    $res += [pscustomobject]@{ Nombre = 'Virtualización activada'; Ok = [bool]$virt; Arreglo = 'Actívala en la BIOS/UEFI (Intel VT-x o AMD-V / SVM).' }

    & wsl.exe --status *> $null
    $res += [pscustomobject]@{ Nombre = 'WSL2 instalado'; Ok = ($LASTEXITCODE -eq 0); Arreglo = 'Usa la opción [1]: instala WSL2 (pide reiniciar).' }
    return $res
}

function Mostrar-Resultado($Item) {
    $aviso = $false
    if ($Item.PSObject.Properties['Aviso']) { $aviso = [bool]$Item.Aviso }
    if (-not $Item.Ok) { Escribir-Error $Item.Nombre; Escribir-Info $Item.Arreglo }
    elseif ($aviso) { Escribir-Aviso "$($Item.Nombre) (funciona, pero irá lento)" }
    else { Escribir-Ok $Item.Nombre }
}

# ---------------------------------------------------------------- credenciales

function Ip-Local {
    # La IP de la red real (la que tiene puerta de enlace), no la de adaptadores virtuales
    try {
        $config = Get-NetIPConfiguration -ErrorAction Stop |
            Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' -and $_.InterfaceAlias -notmatch 'vEthernet|WSL|VirtualBox|VMware|Docker' } |
            Select-Object -First 1
        if ($config) { return @($config.IPv4Address)[0].IPAddress }
    } catch { }
    return 'IP-de-esta-PC'
}

function Actualizar-Credenciales {
    $p = Puertos
    $cfg = Leer-Env
    $usuarioBd = if ($cfg['AYNI_BD_USUARIO']) { $cfg['AYNI_BD_USUARIO'] } else { 'ayni' }
    $claveBd = if ($cfg['AYNI_BD_CLAVE']) { $cfg['AYNI_BD_CLAVE'] } else { 'AyniLocal2026' }
    $claveRoot = if ($cfg['AYNI_BD_ROOT_CLAVE']) { $cfg['AYNI_BD_ROOT_CLAVE'] } else { 'AyniRoot2026' }
    $nombreBd = if ($cfg['AYNI_BD_NOMBRE']) { $cfg['AYNI_BD_NOMBRE'] } else { 'ayni' }
    $ip = Ip-Local
    $tablaLh = ''
    if ($Lighthouse) {
        $filasLh = ''
        foreach ($m in $Lighthouse) {
            $celdas = foreach ($c in @('performance', 'accessibility', 'best-practices', 'seo')) {
                $v = [int]$m.$c
                $clase = if ($v -ge 90) { 'ok' } else { 'mal' }
                "<td class=`"$clase`">$v</td>"
            }
            $filasLh += "<tr><td>$($m.pagina)</td><td>$($m.dispositivo)</td>$($celdas -join '')</tr>`n"
        }
        $tablaLh = "<h2>Lighthouse (meta: 90 o más)</h2><table><thead><tr><th>Página</th><th>Dispositivo</th><th>Rendimiento</th><th>Accesibilidad</th><th>Buenas prácticas</th><th>SEO</th></tr></thead><tbody>`n$filasLh</tbody></table>"
    }
    $fecha = Get-Date -Format 'dd/MM/yyyy HH:mm'

    $texto = @"
==============================================================
  AYNI - CREDENCIALES (versión local)
  Actualizado por el panel: $fecha
==============================================================

DIRECCIONES
  Sistema AYNI ............. http://localhost:$($p.AYNI_PUERTO_WEB)
  Desde un celular (Wi-Fi) . http://${ip}:$($p.AYNI_PUERTO_WEB)
  phpMyAdmin (ver la BD) ... http://localhost:$($p.AYNI_PUERTO_PMA)
  Correos de prueba ........ http://localhost:$($p.AYNI_PUERTO_CORREO)
  Versión web (hosting) .... $script:UrlHosting
                             (los mismos usuarios de demostración)

BASE DE DATOS LOCAL (para phpMyAdmin)
  Usuario .......... $usuarioBd
  Contraseña ....... $claveBd
  Base de datos .... $nombreBd
  Administrador .... root / $claveRoot
  Desde otro programa (HeidiSQL, Workbench): 127.0.0.1, puerto $($p.AYNI_PUERTO_BD)

USUARIOS DE DEMOSTRACIÓN (entran por "Ingresar" o "Administración")
  Rol                    Usuario      Contraseña  Persona
  Administrador ........ cmedina      Ayni#2026   Carlos Medina
  Administrador ........ promero      Ayni#2026   Patricia Romero
  Coordinador/docente .. 41234567     Ayni#2026   Luis Paredes (coordina 3.º A)
  Docente/tutora ....... 42345678     Ayni#2026   Carmen Rivas (tutora 3.º B)
  Estudiante secundaria  E-2026-001   piki2026    Valeria Quispe (3.º B)
  Estudiante primaria .. E-2026-002   piki2026    Mateo Flores (4.º primaria)
  Los demás docentes entran con su DNI y Ayni#2026; los demás
  estudiantes con su código y piki2026 (ver phpMyAdmin).

  Al ingresar se pide el código de la imagen (captcha).
  Tras 5 intentos fallidos la cuenta se bloquea: las de administración
  se liberan solas a los 15 minutos; para las demás, la opción 14 del
  panel restaura los datos de demostración.
  Los correos de recuperación de contraseña llegan a "Correos de prueba".

--------------------------------------------------------------
Estas claves son solo de la instalación local: la base de datos
no se expone fuera de esta PC.
"@
    $ruta = Join-Path $script:Inicio 'CREDENCIALES.txt'
    Guardar-Texto -Ruta $ruta -Contenido $texto -Crlf
}

# ---------------------------------------------------------------- acciones

function Accion-Verificar {
    Escribir-Paso 'Verificando requisitos y dependencias'
    $fallas = 0
    foreach ($r in Requisitos-Sistema) { Mostrar-Resultado $r; if (-not $r.Ok) { $fallas++ } }

    if (Test-DockerInstalado) { Escribir-Ok 'Docker Desktop instalado' }
    else { Escribir-Error 'Docker Desktop no está instalado'; Escribir-Info 'Usa la opción [1].'; return $false }

    if (Test-DockerCorriendo) { Escribir-Ok 'Docker Desktop corriendo' }
    else { Escribir-Error 'Docker Desktop está cerrado'; Escribir-Info 'Ábrelo o usa la opción [3], que lo abre solo.'; return $false }

    $corriendo = Servicios-Corriendo
    $p = Puertos
    $nombres = @{ AYNI_PUERTO_WEB = 'web'; AYNI_PUERTO_PMA = 'phpMyAdmin'; AYNI_PUERTO_CORREO = 'correos'; AYNI_PUERTO_BD = 'base de datos' }
    foreach ($k in @('AYNI_PUERTO_WEB', 'AYNI_PUERTO_PMA', 'AYNI_PUERTO_CORREO', 'AYNI_PUERTO_BD')) {
        if ($corriendo.Count -gt 0) { Escribir-Ok "Puerto $($p[$k]) ($($nombres[$k])) usado por AYNI" }
        elseif (Test-PuertoLibre $p[$k]) { Escribir-Ok "Puerto $($p[$k]) ($($nombres[$k])) libre" }
        else { Escribir-Error "Puerto $($p[$k]) ($($nombres[$k])) ocupado por $(Programa-EnPuerto $p[$k])"; Escribir-Info 'Al encender, el panel ofrece usar otro.'; $fallas++ }
    }

    if (Test-Instalado) { Escribir-Ok 'Contenedores de AYNI construidos' }
    else { Escribir-Error 'AYNI aún no está instalado'; Escribir-Info 'Usa la opción [1].'; return $false }

    if ($corriendo -notcontains 'app') {
        Escribir-Aviso 'AYNI está apagado: enciéndelo con [3] para verificar API, base de datos y web.'
        return ($fallas -eq 0)
    }

    foreach ($s in @('mysql', 'app', 'phpmyadmin', 'mailpit')) {
        if ($corriendo -contains $s) { Escribir-Ok "Servicio $s en marcha" } else { Escribir-Error "Servicio $s detenido"; $fallas++ }
    }

    try {
        $salud = Invoke-RestMethod -Uri "$(Url-Web)/api/v1/health" -TimeoutSec 10
        Escribir-Ok "La API responde (AYNI $($salud.version))"
        if ($salud.base_de_datos.conectada) { Escribir-Ok "La base de datos conecta ($($salud.base_de_datos.motor) $($salud.base_de_datos.version))" }
        else { Escribir-Error 'La base de datos no conecta'; $fallas++ }
    } catch { Escribir-Error 'La API no responde'; Escribir-Info 'Revisa los registros con la opción [12].'; $fallas++ }

    try {
        $web = Invoke-WebRequest -Uri "$(Url-Web)/" -UseBasicParsing -TimeoutSec 10
        if ($web.Content -match '<div id="root">') { Escribir-Ok 'La web carga' } else { Escribir-Error 'La web no devolvió la página esperada'; $fallas++ }
    } catch { Escribir-Error 'La web no carga'; $fallas++ }

    Write-Host ''
    if ($fallas -eq 0) { Escribir-Ok 'Todo en orden.' } else { Escribir-Aviso "Hay $fallas punto(s) por revisar." }
    return ($fallas -eq 0)
}

function Accion-Instalar {
    Escribir-Paso 'Paso 1 de 6: requisitos del equipo'
    $bloqueante = $false
    foreach ($r in Requisitos-Sistema) {
        Mostrar-Resultado $r
        if (-not $r.Ok -and $r.Nombre -notmatch 'WSL2') { $bloqueante = $true }
    }
    if ($bloqueante) { Escribir-Error 'Corrige lo marcado con [X] y vuelve a ejecutar la opción [1].'; return $false }

    Escribir-Paso 'Paso 2 de 6: WSL2 y Docker Desktop'
    & wsl.exe --status *> $null
    if ($LASTEXITCODE -ne 0) {
        Escribir-Aviso 'Falta WSL2 (lo necesita Docker Desktop).'
        if (Confirmar '  ¿Instalar WSL2 ahora? Pedirá permiso de administrador') {
            Start-Process -FilePath 'wsl.exe' -ArgumentList '--install', '--no-distribution' -Verb RunAs -Wait
            Escribir-Aviso 'Reinicia la PC y vuelve a ejecutar la opción [1].'
        }
        return $false
    }
    Escribir-Ok 'WSL2 instalado'

    if (-not (Test-DockerInstalado)) {
        Escribir-Aviso 'Falta Docker Desktop.'
        if (Get-Command winget -ErrorAction SilentlyContinue) {
            if (Confirmar '  ¿Instalar Docker Desktop desde la fuente oficial (winget)?') {
                & winget install -e --id Docker.DockerDesktop --accept-package-agreements --accept-source-agreements
                Escribir-Aviso 'Al terminar: abre Docker Desktop, acepta sus términos y vuelve a ejecutar la opción [1].'
                Escribir-Info 'Si el instalador lo pide, reinicia la PC.'
            }
        } else {
            Escribir-Info 'Abriendo la página oficial de descarga de Docker Desktop...'
            Start-Process 'https://www.docker.com/products/docker-desktop/'
            Escribir-Aviso 'Instálalo, ábrelo, acepta sus términos y vuelve a ejecutar la opción [1].'
        }
        return $false
    }
    Escribir-Ok 'Docker Desktop instalado'
    if (-not (Asegurar-Docker)) { return $false }

    Escribir-Paso 'Paso 3 de 6: configuración local'
    Asegurar-Env
    if (-not (Revisar-Puertos)) { return $false }
    Actualizar-Credenciales
    Escribir-Ok 'Credenciales actualizadas en 1. INICIO\CREDENCIALES.txt'

    Escribir-Paso 'Paso 4 de 6: construir AYNI (la primera vez tarda entre 5 y 15 minutos)'
    Compose build app
    if ($LASTEXITCODE -ne 0) { Escribir-Error 'No se pudo construir AYNI. Revisa tu conexión a internet y vuelve a intentar.'; return $false }
    Escribir-Ok 'AYNI construido'

    Escribir-Paso 'Paso 5 de 6: encender y preparar la base de datos'
    Compose up -d mysql app phpmyadmin mailpit
    if (-not (Esperar-Api)) { Escribir-Error 'AYNI no respondió a tiempo. Revisa los registros con la opción [12].'; return $false }
    Artisan ayni:preparar-bd
    if ($LASTEXITCODE -ne 0) { Escribir-Error 'No se pudo preparar la base de datos.'; return $false }
    Escribir-Ok 'Base de datos lista con los datos por defecto'

    Escribir-Paso 'Paso 6 de 6: verificación final'
    $ok = Accion-Verificar
    if ($ok) {
        Write-Host ''
        Escribir-Ok "AYNI está instalado y encendido en $(Url-Web)"
        Escribir-Info 'Las credenciales están en la opción [7].'
    }
    return $ok
}

function Accion-Encender {
    if (-not (Asegurar-Docker)) { return $false }
    if (-not (Test-Instalado)) {
        Escribir-Aviso 'AYNI aún no está instalado.'
        if (Confirmar '  ¿Instalarlo ahora (opción 1)?') { return (Accion-Instalar) }
        return $false
    }
    if (-not (Revisar-Puertos)) { return $false }
    Escribir-Paso 'Encendiendo AYNI'
    # Si la carpeta se actualizó a una versión nueva, se reconstruye; sin cambios Docker reutiliza lo construido
    Compose build app
    if ($LASTEXITCODE -ne 0) { Escribir-Aviso 'No se pudo actualizar AYNI (¿sin internet?): se enciende la versión ya instalada.' }
    Compose up -d mysql app phpmyadmin mailpit
    if (-not (Esperar-Api)) { Escribir-Error 'AYNI no respondió a tiempo. Revisa los registros con la opción [12].'; return $false }
    # Aplica las tablas nuevas de una versión más reciente (no borra datos)
    Artisan ayni:preparar-bd | Out-Null
    if ($LASTEXITCODE -ne 0) { Escribir-Aviso 'No se pudo actualizar la base de datos: usa la opción [14].' }
    Actualizar-Credenciales
    Escribir-Ok "AYNI encendido en $(Url-Web)"
    Start-Process (Url-Web)
    return $true
}

function Accion-Pausar {
    if (-not (Test-DockerCorriendo)) { Escribir-Ok 'AYNI ya está apagado.'; return $true }
    Escribir-Paso 'Pausando AYNI (los datos se conservan)'
    Compose stop
    Escribir-Ok 'AYNI pausado. Para volver a usarlo: opción [3].'
    return $true
}

function Accion-Reiniciar {
    if (-not (Asegurar-Docker)) { return $false }
    if ((Servicios-Corriendo) -notcontains 'app') { return (Accion-Encender) }
    Escribir-Paso 'Reiniciando AYNI'
    Compose restart
    if (Esperar-Api) { Escribir-Ok 'AYNI reiniciado'; return $true }
    Escribir-Error 'AYNI no respondió después de reiniciar.'
    return $false
}

function Requiere-Encendido {
    if ((Estado-Ayni) -eq 'ENCENDIDO') { return $true }
    Escribir-Aviso 'AYNI está apagado. Enciéndelo primero con la opción [3].'
    return $false
}

function Accion-AbrirWeb { if (Requiere-Encendido) { Start-Process (Url-Web); Escribir-Ok "Abriendo $(Url-Web)" } }

function Accion-Credenciales {
    $ruta = Join-Path $script:Inicio 'CREDENCIALES.txt'
    if (-not (Test-Path -LiteralPath $ruta)) { Actualizar-Credenciales }
    Start-Process notepad.exe -ArgumentList "`"$ruta`""
}

function Accion-Manual {
    $carpeta = Join-Path $script:Inicio 'MANUAL'
    $pdfs = @('Manual de usuario.pdf', 'Manual de instalación.pdf') | ForEach-Object { Join-Path $carpeta $_ } | Where-Object { Test-Path -LiteralPath $_ }
    if ($pdfs) {
        foreach ($pdf in $pdfs) { Start-Process $pdf }
        Escribir-Info "Los manuales (Word y PDF) están en $carpeta"
        return
    }
    Escribir-Aviso 'Esta copia no trae los manuales en PDF.'
    Escribir-Info 'Se abre su texto en docs\manual.'
    Start-Process explorer.exe -ArgumentList "`"$(Join-Path $script:Raiz 'docs\manual')`""
}

function Accion-PhpMyAdmin {
    if (Requiere-Encendido) {
        Start-Process "http://localhost:$((Puertos).AYNI_PUERTO_PMA)"
        Escribir-Info 'Usuario y contraseña: opción [7].'
    }
}

function Accion-Correos { if (Requiere-Encendido) { Start-Process "http://localhost:$((Puertos).AYNI_PUERTO_CORREO)" } }

function Accion-Estado {
    if (-not (Asegurar-Docker)) { return }
    Escribir-Paso 'Servicios de AYNI'
    Compose ps --format 'table {{.Service}}\t{{.State}}\t{{.Status}}\t{{.Ports}}'
}

function Accion-Logs {
    if (-not (Asegurar-Docker)) { return }
    $cmd = "Set-Location -LiteralPath '$($script:Raiz)'; Write-Host 'Registros de AYNI. Cierra esta ventana para salir.' -ForegroundColor Cyan; docker compose logs -f --tail 100"
    Start-Process powershell.exe -ArgumentList '-NoExit', '-NoProfile', '-Command', $cmd
    Escribir-Ok 'Los registros se abrieron en otra ventana.'
}

function Accion-ReiniciarBd {
    if (-not (Requiere-Encendido)) { return $false }
    Escribir-Aviso 'Se BORRARÁN todos los datos actuales y se cargarán los datos por defecto.'
    if (-not (Confirmar '  ¿Continuar?')) { Escribir-Info 'Cancelado.'; return $false }
    Artisan migrate:fresh --seed --force
    if ($LASTEXITCODE -eq 0) { Escribir-Ok 'Base de datos reiniciada con los datos por defecto'; return $true }
    Escribir-Error 'No se pudo reiniciar la base de datos.'
    return $false
}

# Ejecuta un script de shell dentro del contenedor de MySQL. Se pasa como archivo
# para no depender de cómo PowerShell 5.1 entrega las comillas a los programas.
function Ejecutar-EnMysql([string]$Script) {
    $temporal = Join-Path $env:TEMP 'ayni-tarea.sh'
    [System.IO.File]::WriteAllText($temporal, ($Script -replace "`r`n", "`n"), (New-Object System.Text.UTF8Encoding $false))
    Compose cp $temporal mysql:/tmp/ayni-tarea.sh
    Compose exec -T mysql sh /tmp/ayni-tarea.sh
    $codigo = $LASTEXITCODE
    Remove-Item -LiteralPath $temporal -ErrorAction SilentlyContinue
    $global:LASTEXITCODE = $codigo
}

function Accion-Respaldo {
    if (-not (Requiere-Encendido)) { return $null }
    $carpeta = Join-Path $script:Raiz 'respaldos'
    New-Item -ItemType Directory -Force -Path $carpeta | Out-Null
    $nombre = "ayni_$(Get-Date -Format 'yyyy-MM-dd_HHmmss').sql"
    Escribir-Paso "Creando respaldo $nombre"
    Ejecutar-EnMysql 'mysqldump -uroot -p$MYSQL_ROOT_PASSWORD --single-transaction --routines --triggers --default-character-set=utf8mb4 $MYSQL_DATABASE > /tmp/respaldo.sql 2>/dev/null'
    if ($LASTEXITCODE -ne 0) { Escribir-Error 'No se pudo crear el respaldo.'; return $null }
    $destino = Join-Path $carpeta $nombre
    Compose cp mysql:/tmp/respaldo.sql $destino
    Escribir-Ok "Respaldo guardado en respaldos\$nombre"
    return $destino
}

function Accion-Restaurar {
    if (-not (Requiere-Encendido)) { return $false }
    $archivos = @(Get-ChildItem -LiteralPath (Join-Path $script:Raiz 'respaldos') -Filter '*.sql' -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending)
    if ($archivos.Count -eq 0) { Escribir-Aviso 'No hay respaldos. Crea uno con la opción [14].'; return $false }

    Escribir-Paso 'Respaldos disponibles'
    for ($i = 0; $i -lt $archivos.Count; $i++) {
        Write-Host ("  [{0}] {1}  ({2:N0} KB)" -f ($i + 1), $archivos[$i].Name, ($archivos[$i].Length / 1KB))
    }
    $eleccion = 1
    if (-not $script:AutoSi) { $eleccion = Read-Host 'Número del respaldo a restaurar (Enter para cancelar)' }
    if (-not ($eleccion -as [int]) -or [int]$eleccion -lt 1 -or [int]$eleccion -gt $archivos.Count) { Escribir-Info 'Cancelado.'; return $false }
    $archivo = $archivos[[int]$eleccion - 1]

    Escribir-Aviso "Los datos actuales se reemplazarán por los de $($archivo.Name)."
    if (-not (Confirmar '  ¿Continuar?')) { Escribir-Info 'Cancelado.'; return $false }

    Compose cp $archivo.FullName mysql:/tmp/restaurar.sql
    Ejecutar-EnMysql @'
set -e
mysql -uroot -p$MYSQL_ROOT_PASSWORD 2>/dev/null <<SQL
DROP DATABASE IF EXISTS $MYSQL_DATABASE;
CREATE DATABASE $MYSQL_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON $MYSQL_DATABASE.* TO '$MYSQL_USER'@'%';
SQL
mysql -uroot -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE < /tmp/restaurar.sql 2>/dev/null
'@
    if ($LASTEXITCODE -eq 0) { Escribir-Ok 'Respaldo restaurado'; return $true }
    Escribir-Error 'No se pudo restaurar el respaldo.'
    return $false
}

# ---------------------------------------------------------------- pruebas

function Resumen-Junit([string]$Ruta) {
    if (-not (Test-Path -LiteralPath $Ruta)) { return $null }
    [xml]$x = Get-Content -LiteralPath $Ruta -Raw -Encoding UTF8
    $casos = @($x.SelectNodes('//testcase'))
    $fallidas = @($x.SelectNodes('//testcase[failure or error]')).Count
    $omitidas = @($x.SelectNodes('//testcase[skipped]')).Count
    return [pscustomobject]@{ Total = $casos.Count; Fallidas = $fallidas; Omitidas = $omitidas }
}

function Accion-Pruebas {
    if (-not (Asegurar-Docker)) { return $false }
    if (-not (Test-Instalado)) { Escribir-Error 'AYNI aún no está instalado. Usa la opción [1].'; return $false }
    Asegurar-Env

    # Se prueba el código actual: si algo cambió, se reconstruye antes
    Escribir-Paso 'Preparando AYNI para las pruebas'
    Compose build app
    Compose up -d mysql app phpmyadmin mailpit
    if (-not (Esperar-Api)) { Escribir-Error 'AYNI no respondió.'; return $false }

    $actual = Join-Path $script:Raiz 'reportes\actual'
    if (Test-Path -LiteralPath $actual) { Remove-Item -LiteralPath $actual -Recurse -Force }
    New-Item -ItemType Directory -Force -Path $actual | Out-Null

    $suites = @(
        @{ Nombre = 'Backend (API, validaciones, plataforma PHP 8.1)'; Servicio = 'pruebas-backend'; Junit = 'backend.xml' },
        @{ Nombre = 'Frontend (componentes y formularios)'; Servicio = 'pruebas-frontend'; Junit = 'frontend.xml' },
        @{ Nombre = 'Punta a punta en navegador (PC y celular)'; Servicio = 'pruebas-e2e'; Junit = 'e2e.xml' }
    )
    $resultados = @()
    foreach ($s in $suites) {
        Escribir-Paso "Pruebas: $($s.Nombre)"
        if ($s.Servicio -eq 'pruebas-backend') { Compose --profile pruebas build pruebas-backend }
        Compose --profile pruebas run --rm $s.Servicio
        $codigo = $LASTEXITCODE
        $resultados += [pscustomobject]@{ Nombre = $s.Nombre; Ok = ($codigo -eq 0); Resumen = (Resumen-Junit (Join-Path $actual $s.Junit)) }
    }

    Escribir-Paso 'Pruebas: panel de control (Pester)'
    $panel = Pruebas-Panel (Join-Path $actual 'panel.xml')
    $resultados += $panel

    $carpeta = Join-Path $script:Raiz ("reportes\pruebas_{0}" -f (Get-Date -Format 'yyyy-MM-dd_HHmmss'))
    Copy-Item -LiteralPath $actual -Destination $carpeta -Recurse
    $lighthouse = $null
    $resumenLh = Join-Path $carpeta 'lighthouse\resumen.json'
    if (Test-Path -LiteralPath $resumenLh) { $lighthouse = Get-Content -LiteralPath $resumenLh -Raw -Encoding UTF8 | ConvertFrom-Json }
    $html = Reporte-Html $resultados $lighthouse
    Guardar-Texto -Ruta (Join-Path $carpeta 'index.html') -Contenido $html

    Write-Host ''
    foreach ($r in $resultados) {
        $detalle = ''
        if ($r.Resumen) { $detalle = " - $($r.Resumen.Total - $r.Resumen.Fallidas) de $($r.Resumen.Total) pasaron" }
        if ($r.Ok) { Escribir-Ok "$($r.Nombre)$detalle" } else { Escribir-Error "$($r.Nombre)$detalle" }
    }
    $todo = -not ($resultados | Where-Object { -not $_.Ok })
    Write-Host ''
    if ($todo) { Escribir-Ok 'TODAS LAS PRUEBAS PASARON' } else { Escribir-Error 'Hay pruebas que fallaron (detalle en el reporte)' }
    if (-not $script:AutoSi) { Start-Process (Join-Path $carpeta 'index.html') }
    Escribir-Info "Reporte: $carpeta\index.html"
    return $todo
}

function Pruebas-Panel([string]$Salida) {
    $nombre = 'Panel de control (Windows)'
    $modulo = Get-Module -ListAvailable Pester | Where-Object { $_.Version.Major -lt 5 } | Sort-Object Version -Descending | Select-Object -First 1
    if (-not $modulo) {
        Escribir-Aviso 'Pester 3/4 no está disponible; se omiten las pruebas del panel.'
        return [pscustomobject]@{ Nombre = $nombre; Ok = $true; Resumen = $null }
    }
    $codigo = "Import-Module '$($modulo.Path)'; `$r = Invoke-Pester -Path '$(Join-Path $PSScriptRoot 'pruebas')' -OutputFile '$Salida' -OutputFormat NUnitXml -PassThru; exit `$r.FailedCount"
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -Command $codigo | Out-Host
    $fallas = $LASTEXITCODE
    $resumen = $null
    if (Test-Path -LiteralPath $Salida) {
        [xml]$x = Get-Content -LiteralPath $Salida -Raw
        $resumen = [pscustomobject]@{ Total = [int]$x.'test-results'.total; Fallidas = [int]$x.'test-results'.failures + [int]$x.'test-results'.errors; Omitidas = 0 }
    }
    return [pscustomobject]@{ Nombre = $nombre; Ok = ($fallas -eq 0); Resumen = $resumen }
}

function Reporte-Html($Resultados, $Lighthouse = $null) {
    $filas = ''
    $total = 0; $fallidas = 0
    foreach ($r in $Resultados) {
        $t = '-'; $p = '-'; $fa = '-'
        if ($r.Resumen) { $t = $r.Resumen.Total; $fa = $r.Resumen.Fallidas; $p = $r.Resumen.Total - $r.Resumen.Fallidas; $total += $t; $fallidas += $fa }
        $estado = if ($r.Ok) { '<span class="ok">Pasó</span>' } else { '<span class="mal">Falló</span>' }
        $filas += "<tr><td>$([System.Net.WebUtility]::HtmlEncode($r.Nombre))</td><td>$t</td><td>$p</td><td>$fa</td><td>$estado</td></tr>`n"
    }
    $general = if ($fallidas -eq 0 -and -not ($Resultados | Where-Object { -not $_.Ok })) { '<p class="ok grande">Todas las pruebas pasaron</p>' } else { '<p class="mal grande">Hay pruebas que fallaron</p>' }
    $tablaLh = ''
    if ($Lighthouse) {
        $filasLh = ''
        foreach ($m in $Lighthouse) {
            $celdas = foreach ($c in @('performance', 'accessibility', 'best-practices', 'seo')) {
                $v = [int]$m.$c
                $clase = if ($v -ge 90) { 'ok' } else { 'mal' }
                "<td class=`"$clase`">$v</td>"
            }
            $filasLh += "<tr><td>$($m.pagina)</td><td>$($m.dispositivo)</td>$($celdas -join '')</tr>`n"
        }
        $tablaLh = "<h2>Lighthouse (meta: 90 o más)</h2><table><thead><tr><th>Página</th><th>Dispositivo</th><th>Rendimiento</th><th>Accesibilidad</th><th>Buenas prácticas</th><th>SEO</th></tr></thead><tbody>`n$filasLh</tbody></table>"
    }
    $fecha = Get-Date -Format 'dd/MM/yyyy HH:mm'
    return @"
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>AYNI - Reporte de pruebas</title>
<style>
body{font-family:'Segoe UI',system-ui,sans-serif;background:#fdf3e4;color:#1f2a37;margin:0;padding:24px}
main{max-width:900px;margin:auto;background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 4px #0001}
h1{color:#346952;margin-top:0}h2{color:#346952;margin-top:28px}table{width:100%;border-collapse:collapse;margin:16px 0}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}th{background:#f7efe2}
.ok{color:#2f7d4f;font-weight:700}.mal{color:#b42318;font-weight:700}.grande{font-size:1.3em}
a{color:#c7613b}
</style></head><body><main>
<h1>AYNI - Reporte de pruebas</h1>
<p>Versión $script:Version &middot; $fecha &middot; $total pruebas automáticas</p>
$general
<table><thead><tr><th>Grupo</th><th>Total</th><th>Pasaron</th><th>Fallaron</th><th>Estado</th></tr></thead>
<tbody>
$filas</tbody></table>
$tablaLh
<p><a href="e2e/index.html">Ver el detalle de las pruebas en navegador (Playwright)</a> · <a href="lighthouse/">Informes completos de Lighthouse</a></p>
<p>Archivos JUnit/NUnit con el detalle de cada prueba: backend.xml, frontend.xml, e2e.xml, panel.xml.</p>
</main></body></html>
"@
}

# ---------------------------------------------------------------- menú

function Mostrar-Menu {
    Clear-Host
    $estado = Estado-Ayni
    $p = Puertos
    $color = switch ($estado) { 'ENCENDIDO' { 'Green' } 'APAGADO' { 'Yellow' } default { 'Red' } }
    Write-Host '=============================================='
    Write-Host "        AYNI - Panel de control      v$script:Version"
    Write-Host '=============================================='
    Write-Host ' Estado: ' -NoNewline; Write-Host $estado -ForegroundColor $color
    Write-Host " Web: http://localhost:$($p.AYNI_PUERTO_WEB)   phpMyAdmin: http://localhost:$($p.AYNI_PUERTO_PMA)"
    Write-Host '----------------------------------------------'
    Write-Host ' PRIMERA VEZ' -ForegroundColor Cyan
    Write-Host '  [1]  Instalar lo necesario (Docker, dependencias y BD)'
    Write-Host '  [2]  Verificar requisitos y dependencias'
    Write-Host ' USO DIARIO' -ForegroundColor Cyan
    Write-Host '  [3]  Encender AYNI y abrir el navegador'
    Write-Host '  [4]  Pausar (detener sin borrar datos)'
    Write-Host '  [5]  Reiniciar'
    Write-Host '  [6]  Abrir AYNI en el navegador'
    Write-Host '  [7]  Ver credenciales (Bloc de notas)'
    Write-Host '  [8]  Abrir los manuales (usuario e instalación)'
    Write-Host ' REVISIÓN' -ForegroundColor Cyan
    Write-Host '  [9]  Abrir phpMyAdmin (ver la base de datos)'
    Write-Host '  [10] Ejecutar TODAS las pruebas + abrir reporte'
    Write-Host '  [11] Ver estado de los servicios'
    Write-Host '  [12] Ver registros (logs) en vivo'
    Write-Host '  [13] Ver correos enviados (bandeja de prueba local)'
    Write-Host ' DATOS' -ForegroundColor Cyan
    Write-Host '  [14] Reiniciar la BD con datos por defecto   (pide confirmar S/N)'
    Write-Host '  [15] Crear respaldo de la BD (.sql con fecha)'
    Write-Host '  [16] Restaurar un respaldo                   (pide confirmar S/N)'
    Write-Host '  [0]  Salir'
    Write-Host '=============================================='
}

function Ejecutar-Accion([string]$Opcion) {
    switch ($Opcion) {
        { $_ -in '1', 'instalar' } { return (Accion-Instalar) }
        { $_ -in '2', 'verificar' } { return (Accion-Verificar) }
        { $_ -in '3', 'encender' } { return (Accion-Encender) }
        { $_ -in '4', 'pausar' } { return (Accion-Pausar) }
        { $_ -in '5', 'reiniciar' } { return (Accion-Reiniciar) }
        { $_ -in '6', 'abrir' } { Accion-AbrirWeb; return $true }
        { $_ -in '7', 'credenciales' } { Accion-Credenciales; return $true }
        { $_ -in '8', 'manual' } { Accion-Manual; return $true }
        { $_ -in '9', 'phpmyadmin' } { Accion-PhpMyAdmin; return $true }
        { $_ -in '10', 'pruebas' } { return (Accion-Pruebas) }
        { $_ -in '11', 'estado' } { Accion-Estado; return $true }
        { $_ -in '12', 'logs' } { Accion-Logs; return $true }
        { $_ -in '13', 'correos' } { Accion-Correos; return $true }
        { $_ -in '14', 'reiniciar-bd' } { return (Accion-ReiniciarBd) }
        { $_ -in '15', 'respaldo' } { return [bool](Accion-Respaldo) }
        { $_ -in '16', 'restaurar' } { return (Accion-Restaurar) }
        default { Escribir-Aviso 'Opción no válida.'; return $false }
    }
}

function Desbloquear-Archivos {
    # Si el proyecto llegó como .zip descargado, Windows marca los scripts como bloqueados
    Get-ChildItem -LiteralPath $script:Raiz -Recurse -File -Include '*.ps1', '*.bat', '*.sh' -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -notmatch '\\node_modules\\|\\vendor\\' } |
        Unblock-File -ErrorAction SilentlyContinue
}

function Main {
    Set-Location -LiteralPath $script:Raiz
    Desbloquear-Archivos
    Test-DockerInstalado | Out-Null

    if ($Accion) {
        $ok = @(Ejecutar-Accion $Accion)[-1]
        if ($ok -ne $true) { exit 1 }
        exit 0
    }

    while ($true) {
        Mostrar-Menu
        $opcion = (Read-Host 'Elige una opción').Trim()
        if ($opcion -eq '0') { break }
        if ($opcion -eq '') { continue }
        Ejecutar-Accion $opcion | Out-Null
        Pausa
    }
}

# Al cargarlo con ". .\ayni.ps1" (pruebas) solo se definen las funciones
if ($MyInvocation.InvocationName -ne '.') { Main }
