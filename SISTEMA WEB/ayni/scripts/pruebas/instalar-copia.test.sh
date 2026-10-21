#!/usr/bin/env bash
# M14: lo que hace el profesor. Copia la carpeta grupo02 del FTP (dist-hosting/grupo02) a una carpeta nueva,
# la instala con el panel de Linux sin tocar nada y comprueba que AYNI funciona en local.
# Ejecutar desde "SISTEMA WEB/ayni" con la copia ya preparada (publicar-hosting.ps1 -SoloPreparar).
set -u
cd "$(dirname "$0")/../.." || exit 1
[ -d dist-hosting/grupo02 ] || { echo 'Falta dist-hosting/grupo02: ejecuta publicar-hosting.ps1 -SoloPreparar'; exit 1; }

fallidas=0
prueba() {
    local nombre="$1"
    shift
    if "$@"; then echo "  [OK] $nombre"; else echo "  [X]  $nombre"; fallidas=$((fallidas + 1)); fi
}

# Como si se descargara con FileZilla: todo lo del FTP, en una carpeta con espacios en el nombre
copia="$(mktemp -d)/Descargas del FTP/grupo02"
mkdir -p "$(dirname "$copia")"
cp -a dist-hosting/grupo02 "$copia"
# El AYNI del equipo se apaga para liberar los puertos (los datos quedan en su volumen)
docker compose down >/dev/null 2>&1

instala() { (cd "$copia" && AYNI_OMITIR_RAM=1 bash "1. INICIO/AYNI.sh" instalar); }
prueba 'AYNI.sh instalar termina bien en la copia descargada' instala

responde() { curl -fsS --noproxy '*' http://localhost:5173/api/v1/health | grep -q '"estado":"ok"'; }
prueba 'la API y la base de datos responden en http://localhost:5173' responde

react() { curl -fsS --noproxy '*' http://localhost:5173/ingresar | grep -q 'id="root"' && curl -fsS --noproxy '*' http://localhost:5173/ | grep -q 'src="/assets/'; }
prueba 'React se sirve compilado para la raíz (no para /grupo02/)' react

local_env() { [ -f "$copia/.env" ] && grep -q '^AYNI_APP_KEY=base64:' "$copia/.env"; }
prueba 'el panel creó su configuración local (.env con clave propia)' local_env

ignora_hosting() {
    # La configuración del hosting (backend/.env de producción) no entra a la imagen local
    ! (cd "$copia" && docker compose exec -T app grep -q 'APP_ENV=production' backend/.env)
}
prueba 'ignora la configuración del hosting que vino en la copia' ignora_hosting

(cd "$copia" && docker compose down >/dev/null 2>&1)
[ "$fallidas" -eq 0 ] && echo 'Instalación desde la copia del FTP: todo bien.' || echo "Instalación desde la copia del FTP: $fallidas pruebas fallaron."
exit "$fallidas"
