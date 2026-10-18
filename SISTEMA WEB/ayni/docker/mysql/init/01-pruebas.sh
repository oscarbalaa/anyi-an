#!/bin/bash
# Base de datos separada para las pruebas automáticas (no toca los datos del sistema)
set -e
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS ayni_pruebas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON ayni_pruebas.* TO '$MYSQL_USER'@'%';
FLUSH PRIVILEGES;
SQL
