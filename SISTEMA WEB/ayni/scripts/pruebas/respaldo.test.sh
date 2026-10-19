#!/usr/bin/env bash
# M13 - RF-66: respaldo y restauración de la BD con el panel de Linux (las mismas acciones que el de Windows).
# Se ejecuta con AYNI encendido, desde "SISTEMA WEB/ayni": bash scripts/pruebas/respaldo.test.sh
set -u
cd "$(dirname "$0")/../.." || exit 1

fallidas=0
prueba() {
    local nombre="$1"
    shift
    if "$@"; then echo "  [OK] $nombre"; else echo "  [X]  $nombre"; fallidas=$((fallidas + 1)); fi
}

sql() {
    # shellcheck disable=SC2016
    docker compose exec -T mysql sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" 2>/dev/null' <<<"$1"
}

usuarios_antes="$(sql 'SELECT COUNT(*) FROM usuario;')"
antes="$(ls respaldos/*.sql 2>/dev/null | wc -l)"

crea_respaldo() {
    ./scripts/ayni.sh respaldo >/dev/null && [ "$(ls respaldos/*.sql | wc -l)" -gt "$antes" ] && grep -q 'CREATE TABLE `usuario`' "$(ls -1t respaldos/*.sql | head -1)"
}
prueba 'RF-66: crea un respaldo .sql con fecha y toda la estructura' crea_respaldo

# Un cambio posterior al respaldo: debe desaparecer al restaurar
sql 'CREATE TABLE IF NOT EXISTS prueba_respaldo (id INT);' >/dev/null

restaura() {
    ./scripts/ayni.sh restaurar >/dev/null \
        && [ -z "$(sql "SHOW TABLES LIKE 'prueba_respaldo';")" ] \
        && [ "$(sql 'SELECT COUNT(*) FROM usuario;')" = "$usuarios_antes" ]
}
prueba 'RF-66: restaura el último respaldo y deja los datos como estaban' restaura

sin_respaldos() {
    local carpeta
    carpeta="$(mktemp -d)"
    mv respaldos/*.sql "$carpeta"/
    ./scripts/ayni.sh restaurar >/dev/null 2>&1
    local estado=$?
    mv "$carpeta"/*.sql respaldos/
    rmdir "$carpeta"
    [ "$estado" -ne 0 ]
}
prueba 'RF-66: sin respaldos, restaurar avisa y no toca la BD' sin_respaldos

[ "$fallidas" -eq 0 ] && echo 'Respaldo y restauración: todo bien.' || echo "Respaldo y restauración: $fallidas pruebas fallaron."
exit "$fallidas"
