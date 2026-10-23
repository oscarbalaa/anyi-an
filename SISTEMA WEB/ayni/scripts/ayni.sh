#!/usr/bin/env bash
# Panel de control de AYNI para Linux. Se abre con "1. INICIO/AYNI.sh".
# También acepta una acción directa:  ayni.sh encender [--si]

VERSION="0.1.0"
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
INICIO="$RAIZ/1. INICIO"
ENV="$RAIZ/.env"
URL_HOSTING="https://ayni.ruta-practica.com/"
AUTO_SI=0
DOCKER=(docker)

# ---------------------------------------------------------------- salida

ok()    { printf '  \033[32m[OK]\033[0m %s\n' "$*"; }
error() { printf '  \033[31m[X]\033[0m  %s\n' "$*"; }
aviso() { printf '  \033[33m[!]\033[0m  %s\n' "$*"; }
info()  { printf '       %s\n' "$*"; }
paso()  { printf '\n\033[36m>> %s\033[0m\n' "$*"; }

confirmar() {
    [ "$AUTO_SI" = 1 ] && return 0
    local r
    read -r -p "$1 (S/N) " r
    [[ "$r" =~ ^([sSyY]|si|sí|SI)$ ]]
}

pausa() {
    [ "$AUTO_SI" = 1 ] && return
    echo
    read -r -p 'Presiona Enter para volver al menú ' _
}

abrir() {
    if command -v xdg-open >/dev/null 2>&1; then xdg-open "$1" >/dev/null 2>&1 &
    else info "Abre en tu navegador: $1"; fi
}

# ---------------------------------------------------------------- configuración

valor_env() {
    [ -f "$ENV" ] || return
    grep -E "^$1=" "$ENV" | tail -1 | cut -d= -f2-
}

escribir_env() {
    if grep -qE "^$1=" "$ENV" 2>/dev/null; then
        sed -i "s|^$1=.*|$1=$2|" "$ENV"
    else
        echo "$1=$2" >> "$ENV"
    fi
}

nueva_clave_app() {
    echo "base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
}

# Las pruebas E2E lo envían para saltar el captcha (nunca funciona en el hosting)
nuevo_secreto_e2e() {
    head -c 20 /dev/urandom | od -An -tx1 | tr -d ' \n'
}

asegurar_env() {
    if [ ! -f "$ENV" ]; then
        cp "$RAIZ/.env.example" "$ENV"
        ok 'Configuración local creada (.env)'
    fi
    if [ -z "$(valor_env AYNI_APP_KEY)" ]; then
        escribir_env AYNI_APP_KEY "$(nueva_clave_app)"
        ok 'Clave de la aplicación generada'
    fi
    if [ -z "$(valor_env AYNI_SECRETO_E2E)" ]; then
        escribir_env AYNI_SECRETO_E2E "$(nuevo_secreto_e2e)"
    fi
}

puerto() {
    local v
    v="$(valor_env "$1")"
    echo "${v:-$2}"
}
puerto_web()    { puerto AYNI_PUERTO_WEB 5173; }
puerto_pma()    { puerto AYNI_PUERTO_PMA 8080; }
puerto_correo() { puerto AYNI_PUERTO_CORREO 8025; }
puerto_bd()     { puerto AYNI_PUERTO_BD 3307; }
url_web()       { echo "http://localhost:$(puerto_web)"; }

# ---------------------------------------------------------------- Docker

docker_instalado() { command -v docker >/dev/null 2>&1; }

docker_corriendo() {
    docker_instalado || return 1
    if docker info >/dev/null 2>&1; then DOCKER=(docker); return 0; fi
    # Sin permisos sobre Docker: se usa sudo
    if command -v sudo >/dev/null 2>&1 && sudo -n docker info >/dev/null 2>&1; then DOCKER=(sudo docker); return 0; fi
    return 1
}

compose() { (cd "$RAIZ" && "${DOCKER[@]}" compose "$@"); }
artisan() { compose exec -T -w /var/www/ayni/backend -u www-data app php artisan "$@"; }

asegurar_docker() {
    if ! docker_instalado; then error 'Docker no está instalado. Usa la opción [1].'; return 1; fi
    docker_corriendo && return 0
    aviso 'Docker no está corriendo. Intentando iniciarlo...'
    if command -v systemctl >/dev/null 2>&1; then sudo systemctl start docker 2>/dev/null; fi
    sleep 3
    if docker_corriendo; then ok 'Docker listo'; return 0; fi
    error 'Docker no responde. Inícialo (sudo systemctl start docker) y vuelve a intentar.'
    info "Si es un problema de permisos: sudo usermod -aG docker $USER y vuelve a iniciar sesión."
    return 1
}

servicios_corriendo() { compose ps --status running --services 2>/dev/null; }

instalado() {
    [ -f "$ENV" ] && "${DOCKER[@]}" image inspect ayni-app:local >/dev/null 2>&1
}

estado_ayni() {
    if ! docker_corriendo; then
        if [ -f "$ENV" ]; then echo 'APAGADO'; else echo 'NO INSTALADO'; fi
        return
    fi
    if ! instalado; then echo 'NO INSTALADO'; return; fi
    if servicios_corriendo | grep -qx app; then echo 'ENCENDIDO'; else echo 'APAGADO'; fi
}

esperar_api() {
    local url fin
    url="$(url_web)/api/v1/health"
    info "Esperando a que AYNI responda en $url"
    fin=$((SECONDS + ${1:-180}))
    while [ $SECONDS -lt $fin ]; do
        if curl -fsS -m 5 "$url" >/dev/null 2>&1; then echo; return 0; fi
        printf '.'
        sleep 3
    done
    echo
    return 1
}

# ---------------------------------------------------------------- puertos

puerto_libre() {
    if command -v ss >/dev/null 2>&1; then
        ! ss -ltnH "( sport = :$1 )" 2>/dev/null | grep -q .
    else
        ! (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null
    fi
}

buscar_puerto_libre() {
    local p
    for ((p = $1 + 1; p < $1 + 200; p++)); do
        if puerto_libre "$p"; then echo "$p"; return; fi
    done
}

revisar_puertos() {
    [ -n "$(servicios_corriendo)" ] && return 0
    local todo_bien=0 clave nombre actual alternativo
    for clave in AYNI_PUERTO_WEB:web:5173 AYNI_PUERTO_PMA:phpMyAdmin:8080 AYNI_PUERTO_CORREO:correos:8025 AYNI_PUERTO_BD:base_de_datos:3307; do
        IFS=: read -r clave nombre defecto <<< "$clave"
        actual="$(puerto "$clave" "$defecto")"
        puerto_libre "$actual" && continue
        aviso "El puerto $actual ($nombre) lo está usando otro programa."
        alternativo="$(buscar_puerto_libre "$actual")"
        if [ -n "$alternativo" ] && confirmar "  ¿Usar el puerto $alternativo para $nombre?"; then
            escribir_env "$clave" "$alternativo"
            ok "$nombre usará el puerto $alternativo"
        else
            error "Cierra el programa que usa el puerto $actual y vuelve a intentar."
            todo_bien=1
        fi
    done
    [ $todo_bien = 0 ] && actualizar_credenciales
    return $todo_bien
}

# ---------------------------------------------------------------- credenciales

ip_local() {
    local ip
    ip="$(hostname -I 2>/dev/null | awk '{print $1}')"
    echo "${ip:-IP-de-esta-PC}"
}

actualizar_credenciales() {
    local usuario clave root nombre
    usuario="$(valor_env AYNI_BD_USUARIO)"; usuario="${usuario:-ayni}"
    clave="$(valor_env AYNI_BD_CLAVE)"; clave="${clave:-AyniLocal2026}"
    root="$(valor_env AYNI_BD_ROOT_CLAVE)"; root="${root:-AyniRoot2026}"
    nombre="$(valor_env AYNI_BD_NOMBRE)"; nombre="${nombre:-ayni}"
    cat > "$INICIO/CREDENCIALES.txt" <<EOF
==============================================================
  AYNI - CREDENCIALES (versión local)
  Actualizado por el panel: $(date '+%d/%m/%Y %H:%M')
==============================================================

DIRECCIONES
  Sistema AYNI ............. http://localhost:$(puerto_web)
  Desde un celular (Wi-Fi) . http://$(ip_local):$(puerto_web)
  phpMyAdmin (ver la BD) ... http://localhost:$(puerto_pma)
  Correos de prueba ........ http://localhost:$(puerto_correo)
  Versión web (hosting) .... $URL_HOSTING
                             (los mismos usuarios de demostración)

BASE DE DATOS LOCAL (para phpMyAdmin)
  Usuario .......... $usuario
  Contraseña ....... $clave
  Base de datos .... $nombre
  Administrador .... root / $root
  Desde otro programa (HeidiSQL, Workbench): 127.0.0.1, puerto $(puerto_bd)

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
EOF
}

# ---------------------------------------------------------------- acciones

requisitos() {
    local fallas=0 ram libre
    if [ "$(uname -s)" = Linux ] && { [ "$(uname -m)" = x86_64 ] || [ "$(uname -m)" = aarch64 ]; }; then
        ok "Linux $(uname -m) ($(. /etc/os-release 2>/dev/null && echo "$PRETTY_NAME"))"
    else error "Sistema no compatible: $(uname -s) $(uname -m)"; fallas=1; fi

    ram=$(awk '/MemTotal/ {printf "%.1f", $2/1024/1024}' /proc/meminfo)
    # AYNI_OMITIR_RAM=1 solo para entornos de prueba automatizados (CI, contenedores)
    if awk "BEGIN{exit !($ram < 3.5)}" && [ "${AYNI_OMITIR_RAM:-0}" != 1 ]; then error "Memoria RAM: $ram GB (se necesitan al menos 4 GB)"; fallas=1
    elif awk "BEGIN{exit !($ram < 7.5)}"; then aviso "Memoria RAM: $ram GB (funciona, pero irá lento)"
    else ok "Memoria RAM: $ram GB"; fi

    libre=$(df -Pk "$RAIZ" | awk 'NR==2 {printf "%.1f", $4/1024/1024}')
    if awk "BEGIN{exit !($libre < 8)}"; then error "Espacio libre: $libre GB (se necesitan al menos 8 GB)"; fallas=1
    else ok "Espacio libre: $libre GB"; fi

    if command -v curl >/dev/null 2>&1; then ok 'curl disponible'
    else error 'Falta curl (sudo apt install curl)'; fallas=1; fi
    return $fallas
}

accion_verificar() {
    paso 'Verificando requisitos y dependencias'
    local fallas=0 s corriendo salud
    requisitos || fallas=1
    if docker_instalado; then ok 'Docker instalado'; else error 'Docker no está instalado'; info 'Usa la opción [1].'; return 1; fi
    if docker_corriendo; then ok 'Docker corriendo'; else error 'Docker no está corriendo'; info 'sudo systemctl start docker'; return 1; fi
    if "${DOCKER[@]}" compose version >/dev/null 2>&1; then ok 'Docker Compose disponible'; else error 'Falta el plugin Docker Compose'; return 1; fi

    corriendo="$(servicios_corriendo)"
    for s in "$(puerto_web):web" "$(puerto_pma):phpMyAdmin" "$(puerto_correo):correos" "$(puerto_bd):base de datos"; do
        if [ -n "$corriendo" ]; then ok "Puerto ${s%%:*} (${s#*:}) usado por AYNI"
        elif puerto_libre "${s%%:*}"; then ok "Puerto ${s%%:*} (${s#*:}) libre"
        else error "Puerto ${s%%:*} (${s#*:}) ocupado"; info 'Al encender, el panel ofrece usar otro.'; fallas=1; fi
    done

    if instalado; then ok 'Contenedores de AYNI construidos'; else error 'AYNI aún no está instalado'; info 'Usa la opción [1].'; return 1; fi
    if ! grep -qx app <<< "$corriendo"; then
        aviso 'AYNI está apagado: enciéndelo con [3] para verificar API, base de datos y web.'
        return $fallas
    fi
    for s in mysql app phpmyadmin mailpit; do
        if grep -qx "$s" <<< "$corriendo"; then ok "Servicio $s en marcha"; else error "Servicio $s detenido"; fallas=1; fi
    done
    if salud="$(curl -fsS -m 10 "$(url_web)/api/v1/health")"; then
        ok "La API responde"
        if grep -q '"conectada":true' <<< "$salud"; then ok 'La base de datos conecta'; else error 'La base de datos no conecta'; fallas=1; fi
    else error 'La API no responde'; info 'Revisa los registros con la opción [12].'; fallas=1; fi
    if curl -fsS -m 10 "$(url_web)/" | grep -q '<div id="root">'; then ok 'La web carga'; else error 'La web no carga'; fallas=1; fi

    echo
    if [ $fallas = 0 ]; then ok 'Todo en orden.'; else aviso 'Hay puntos por revisar.'; fi
    return $fallas
}

accion_instalar() {
    paso 'Paso 1 de 6: requisitos del equipo'
    requisitos || { error 'Corrige lo marcado con [X] y vuelve a ejecutar la opción [1].'; return 1; }

    paso 'Paso 2 de 6: Docker'
    if ! docker_instalado; then
        aviso 'Falta Docker.'
        if confirmar '  ¿Instalar Docker Engine con el instalador oficial (get.docker.com)? Pedirá sudo'; then
            curl -fsSL https://get.docker.com | sudo sh || { error 'No se pudo instalar Docker.'; return 1; }
            sudo usermod -aG docker "$USER" 2>/dev/null
            sudo systemctl enable --now docker 2>/dev/null
            ok 'Docker instalado'
        else
            info 'Guía oficial: https://docs.docker.com/engine/install/'
            return 1
        fi
    fi
    asegurar_docker || return 1
    ok 'Docker listo'

    paso 'Paso 3 de 6: configuración local'
    asegurar_env
    revisar_puertos || return 1
    actualizar_credenciales
    ok 'Credenciales actualizadas en 1. INICIO/CREDENCIALES.txt'

    paso 'Paso 4 de 6: construir AYNI (la primera vez tarda entre 5 y 15 minutos)'
    compose build app || { error 'No se pudo construir AYNI. Revisa tu conexión a internet.'; return 1; }
    ok 'AYNI construido'

    paso 'Paso 5 de 6: encender y preparar la base de datos'
    compose up -d mysql app phpmyadmin mailpit
    esperar_api || { error 'AYNI no respondió a tiempo. Revisa los registros con la opción [12].'; return 1; }
    artisan ayni:preparar-bd || { error 'No se pudo preparar la base de datos.'; return 1; }
    ok 'Base de datos lista con los datos por defecto'

    paso 'Paso 6 de 6: verificación final'
    accion_verificar || return 1
    echo
    ok "AYNI está instalado y encendido en $(url_web)"
    info 'Las credenciales están en la opción [7].'
}

accion_encender() {
    asegurar_docker || return 1
    if ! instalado; then
        aviso 'AYNI aún no está instalado.'
        confirmar '  ¿Instalarlo ahora (opción 1)?' && { accion_instalar; return; }
        return 1
    fi
    revisar_puertos || return 1
    paso 'Encendiendo AYNI'
    # Si la carpeta se actualizó a una versión nueva, se reconstruye; sin cambios Docker reutiliza lo construido
    compose build app || aviso 'No se pudo actualizar AYNI (¿sin internet?): se enciende la versión ya instalada.'
    compose up -d mysql app phpmyadmin mailpit
    esperar_api || { error 'AYNI no respondió a tiempo. Revisa los registros con la opción [12].'; return 1; }
    # Aplica las tablas nuevas de una versión más reciente (no borra datos)
    artisan ayni:preparar-bd >/dev/null || aviso 'No se pudo actualizar la base de datos: usa la opción [14].'
    actualizar_credenciales
    ok "AYNI encendido en $(url_web)"
    abrir "$(url_web)"
}

accion_pausar() {
    docker_corriendo || { ok 'AYNI ya está apagado.'; return 0; }
    paso 'Pausando AYNI (los datos se conservan)'
    compose stop
    ok 'AYNI pausado. Para volver a usarlo: opción [3].'
}

accion_reiniciar() {
    asegurar_docker || return 1
    servicios_corriendo | grep -qx app || { accion_encender; return; }
    paso 'Reiniciando AYNI'
    compose restart
    esperar_api && { ok 'AYNI reiniciado'; return 0; }
    error 'AYNI no respondió después de reiniciar.'
    return 1
}

requiere_encendido() {
    [ "$(estado_ayni)" = ENCENDIDO ] && return 0
    aviso 'AYNI está apagado. Enciéndelo primero con la opción [3].'
    return 1
}

accion_credenciales() {
    [ -f "$INICIO/CREDENCIALES.txt" ] || actualizar_credenciales
    if command -v xdg-open >/dev/null 2>&1 && [ -n "${DISPLAY:-}${WAYLAND_DISPLAY:-}" ]; then abrir "$INICIO/CREDENCIALES.txt"
    else cat "$INICIO/CREDENCIALES.txt"; fi
}

accion_manual() {
    local pdf abiertos=0
    for pdf in "$INICIO/MANUAL/Manual de usuario.pdf" "$INICIO/MANUAL/Manual de instalación.pdf"; do
        if [ -f "$pdf" ]; then abrir "$pdf"; abiertos=1; fi
    done
    if [ "$abiertos" = 1 ]; then info "Los manuales (Word y PDF) están en: $INICIO/MANUAL"; return; fi
    aviso 'Esta copia no trae los manuales en PDF.'
    info "Su texto está en: $RAIZ/docs/manual"
    abrir "$RAIZ/docs/manual"
}

accion_estado() {
    asegurar_docker || return 1
    paso 'Servicios de AYNI'
    compose ps --format 'table {{.Service}}\t{{.State}}\t{{.Status}}\t{{.Ports}}'
}

accion_logs() {
    asegurar_docker || return 1
    info 'Registros en vivo. Presiona Ctrl+C para volver al menú.'
    trap ':' INT
    compose logs -f --tail 100
    trap - INT
}

accion_reiniciar_bd() {
    requiere_encendido || return 1
    aviso 'Se BORRARÁN todos los datos actuales y se cargarán los datos por defecto.'
    confirmar '  ¿Continuar?' || { info 'Cancelado.'; return 1; }
    artisan migrate:fresh --seed --force && { ok 'Base de datos reiniciada con los datos por defecto'; return 0; }
    error 'No se pudo reiniciar la base de datos.'
    return 1
}

accion_respaldo() {
    requiere_encendido || return 1
    local nombre
    mkdir -p "$RAIZ/respaldos"
    nombre="ayni_$(date '+%Y-%m-%d_%H%M%S').sql"
    paso "Creando respaldo $nombre"
    # shellcheck disable=SC2016
    compose exec -T mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --default-character-set=utf8mb4 "$MYSQL_DATABASE" 2>/dev/null' > "$RAIZ/respaldos/$nombre" \
        && [ -s "$RAIZ/respaldos/$nombre" ] && { ok "Respaldo guardado en respaldos/$nombre"; return 0; }
    rm -f "$RAIZ/respaldos/$nombre"
    error 'No se pudo crear el respaldo.'
    return 1
}

accion_restaurar() {
    requiere_encendido || return 1
    local archivos eleccion=1 i archivo
    mapfile -t archivos < <(ls -1t "$RAIZ"/respaldos/*.sql 2>/dev/null)
    [ ${#archivos[@]} -gt 0 ] || { aviso 'No hay respaldos. Crea uno con la opción [15].'; return 1; }
    paso 'Respaldos disponibles'
    for i in "${!archivos[@]}"; do echo "  [$((i + 1))] $(basename "${archivos[$i]}")"; done
    [ "$AUTO_SI" = 1 ] || read -r -p 'Número del respaldo a restaurar (Enter para cancelar): ' eleccion
    [[ "$eleccion" =~ ^[0-9]+$ ]] && [ "$eleccion" -ge 1 ] && [ "$eleccion" -le ${#archivos[@]} ] || { info 'Cancelado.'; return 1; }
    archivo="${archivos[$((eleccion - 1))]}"
    aviso "Los datos actuales se reemplazarán por los de $(basename "$archivo")."
    confirmar '  ¿Continuar?' || { info 'Cancelado.'; return 1; }
    # shellcheck disable=SC2016
    compose exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS $MYSQL_DATABASE; CREATE DATABASE $MYSQL_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON $MYSQL_DATABASE.* TO \"$MYSQL_USER\"@\"%\";" 2>/dev/null && mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" 2>/dev/null' < "$archivo" \
        && { ok 'Respaldo restaurado'; return 0; }
    error 'No se pudo restaurar el respaldo.'
    return 1
}

contar_junit() {
    # total y fallidas de un archivo JUnit
    local total fallidas
    [ -f "$1" ] || { echo '- -'; return; }
    total=$(grep -o '<testcase' "$1" | wc -l)
    fallidas=$(grep -oE '<(failure|error)[ >/]' "$1" | wc -l)
    echo "$total $fallidas"
}

accion_pruebas() {
    asegurar_docker || return 1
    instalado || { error 'AYNI aún no está instalado. Usa la opción [1].'; return 1; }
    asegurar_env
    paso 'Preparando AYNI para las pruebas'
    compose build app
    compose up -d mysql app phpmyadmin mailpit
    esperar_api || { error 'AYNI no respondió.'; return 1; }

    local actual="$RAIZ/reportes/actual" carpeta resultado=0 filas='' nombre servicio junit codigo t f
    rm -rf "$actual"; mkdir -p "$actual"
    for suite in 'Backend (API, validaciones, plataforma PHP 8.1)|pruebas-backend|backend.xml' \
                 'Frontend (componentes y formularios)|pruebas-frontend|frontend.xml' \
                 'Punta a punta en navegador (PC y celular)|pruebas-e2e|e2e.xml'; do
        IFS='|' read -r nombre servicio junit <<< "$suite"
        paso "Pruebas: $nombre"
        [ "$servicio" = pruebas-backend ] && compose --profile pruebas build pruebas-backend
        compose --profile pruebas run --rm "$servicio"; codigo=$?
        read -r t f <<< "$(contar_junit "$actual/$junit")"
        if [ $codigo = 0 ]; then estado='<span class="ok">Pasó</span>'; ok "$nombre - $t pruebas"
        else estado='<span class="mal">Falló</span>'; error "$nombre - $f de $t fallaron"; resultado=1; fi
        filas+="<tr><td>$nombre</td><td>$t</td><td>$f</td><td>$estado</td></tr>"
    done

    carpeta="$RAIZ/reportes/pruebas_$(date '+%Y-%m-%d_%H%M%S')"
    cp -r "$actual" "$carpeta"
    cat > "$carpeta/index.html" <<EOF
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>AYNI - Reporte de pruebas</title>
<style>body{font-family:system-ui,sans-serif;background:#fdf3e4;color:#1f2a37;padding:24px}main{max-width:900px;margin:auto;background:#fff;border-radius:16px;padding:24px}
h1{color:#346952}table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}.ok{color:#2f7d4f;font-weight:700}.mal{color:#b42318;font-weight:700}a{color:#c7613b}</style>
</head><body><main><h1>AYNI - Reporte de pruebas</h1><p>Versión $VERSION · $(date '+%d/%m/%Y %H:%M') · Linux</p>
<p class="$([ $resultado = 0 ] && echo ok || echo mal)">$([ $resultado = 0 ] && echo 'Todas las pruebas pasaron' || echo 'Hay pruebas que fallaron')</p>
<table><thead><tr><th>Grupo</th><th>Total</th><th>Fallaron</th><th>Estado</th></tr></thead><tbody>$filas</tbody></table>
<p><a href="e2e/index.html">Ver el detalle de las pruebas en navegador (Playwright)</a> · <a href="lighthouse/">Informes de Lighthouse</a></p></main></body></html>
EOF
    echo
    if [ $resultado = 0 ]; then ok 'TODAS LAS PRUEBAS PASARON'; else error 'Hay pruebas que fallaron (detalle en el reporte)'; fi
    info "Reporte: $carpeta/index.html"
    [ "$AUTO_SI" = 1 ] || abrir "$carpeta/index.html"
    return $resultado
}

# ---------------------------------------------------------------- menú

mostrar_menu() {
    local estado color
    estado="$(estado_ayni)"
    case "$estado" in ENCENDIDO) color=32 ;; APAGADO) color=33 ;; *) color=31 ;; esac
    clear 2>/dev/null
    echo '=============================================='
    echo "        AYNI - Panel de control      v$VERSION"
    echo '=============================================='
    printf ' Estado: \033[%sm%s\033[0m\n' "$color" "$estado"
    echo " Web: http://localhost:$(puerto_web)   phpMyAdmin: http://localhost:$(puerto_pma)"
    echo '----------------------------------------------'
    echo ' PRIMERA VEZ'
    echo '  [1]  Instalar lo necesario (Docker, dependencias y BD)'
    echo '  [2]  Verificar requisitos y dependencias'
    echo ' USO DIARIO'
    echo '  [3]  Encender AYNI y abrir el navegador'
    echo '  [4]  Pausar (detener sin borrar datos)'
    echo '  [5]  Reiniciar'
    echo '  [6]  Abrir AYNI en el navegador'
    echo '  [7]  Ver credenciales'
    echo '  [8]  Abrir los manuales (usuario e instalación)'
    echo ' REVISIÓN'
    echo '  [9]  Abrir phpMyAdmin (ver la base de datos)'
    echo '  [10] Ejecutar TODAS las pruebas + abrir reporte'
    echo '  [11] Ver estado de los servicios'
    echo '  [12] Ver registros (logs) en vivo'
    echo '  [13] Ver correos enviados (bandeja de prueba local)'
    echo ' DATOS'
    echo '  [14] Reiniciar la BD con datos por defecto   (pide confirmar S/N)'
    echo '  [15] Crear respaldo de la BD (.sql con fecha)'
    echo '  [16] Restaurar un respaldo                   (pide confirmar S/N)'
    echo '  [0]  Salir'
    echo '=============================================='
}

ejecutar() {
    case "$1" in
        1 | instalar) accion_instalar ;;
        2 | verificar) accion_verificar ;;
        3 | encender) accion_encender ;;
        4 | pausar) accion_pausar ;;
        5 | reiniciar) accion_reiniciar ;;
        6 | abrir) requiere_encendido && abrir "$(url_web)" ;;
        7 | credenciales) accion_credenciales ;;
        8 | manual) accion_manual ;;
        9 | phpmyadmin) requiere_encendido && { abrir "http://localhost:$(puerto_pma)"; info 'Usuario y contraseña: opción [7].'; } ;;
        10 | pruebas) accion_pruebas ;;
        11 | estado) accion_estado ;;
        12 | logs) accion_logs ;;
        13 | correos) requiere_encendido && abrir "http://localhost:$(puerto_correo)" ;;
        14 | reiniciar-bd) accion_reiniciar_bd ;;
        15 | respaldo) accion_respaldo ;;
        16 | restaurar) accion_restaurar ;;
        *) aviso 'Opción no válida.'; return 1 ;;
    esac
}

main() {
    cd "$RAIZ" || exit 1
    local accion=''
    for arg in "$@"; do
        case "$arg" in --si | -s) AUTO_SI=1 ;; *) accion="$arg" ;; esac
    done
    docker_corriendo >/dev/null 2>&1

    if [ -n "$accion" ]; then
        AUTO_SI=1
        ejecutar "$accion"
        exit $?
    fi

    while true; do
        mostrar_menu
        read -r -p 'Elige una opción: ' opcion || break
        [ "$opcion" = 0 ] && break
        [ -z "$opcion" ] && continue
        ejecutar "$opcion"
        pausa
    done
}

main "$@"
