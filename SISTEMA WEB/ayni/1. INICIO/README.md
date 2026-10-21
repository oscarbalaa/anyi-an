# AYNI — Cómo iniciar el sistema

Hay dos formas de usar AYNI.

## 1. En la web (sin instalar nada)

Abre https://aplicacionenlanube.cetivirgendelapuerta.com/grupo02/ (también desde el celular). Los usuarios de demostración están en `CREDENCIALES.txt`.

Si descargaste esta carpeta desde el FTP del curso, puedes instalarla tal cual en tu PC con los pasos de abajo: el panel ignora la configuración del servidor que viene incluida.

## 2. En tu computadora (local)

### Requisitos
- **Sistema:** Windows 10 (22H2 o superior) / Windows 11 de 64 bits, o Linux de 64 bits.
- **RAM:** 4 GB como mínimo; recomendado 8 GB.
- **Disco:** 8 GB libres.
- **Virtualización:** activada en la BIOS. Casi todas las PC la traen activada.
- **Internet:** solo la primera vez, para descargar ~2 GB.

**No hace falta instalar PHP, MySQL ni Node:** todo corre dentro de Docker, y el panel lo instala si falta.

### Windows

1. Doble clic en **`AYNI.bat`** (esta carpeta).
   - Si aparece "Windows protegió su PC", pulsa **Más información → Ejecutar de todas formas**.
2. Elige **`1`** (Instalar lo necesario).
   - Si falta Docker Desktop, el panel ofrece instalarlo. Al terminar, ábrelo, acepta sus términos y vuelve a elegir `1`.
   - La primera instalación tarda entre 5 y 15 minutos.
3. Al terminar se abre el sistema en http://localhost:5173.
4. Las credenciales están en **`CREDENCIALES.txt`** (opción `7`).

### Linux

```bash
bash "1. INICIO/AYNI.sh"
```

Elige **`1`**. Si falta Docker, el panel ofrece instalarlo con el instalador oficial; pide `sudo`.

## El menú del panel

| Opción | Para qué sirve |
|---|---|
| 1 | Instalar lo necesario (Docker, dependencias y base de datos) |
| 2 | Verificar requisitos y dependencias: muestra [OK] o [X] y cómo arreglarlo |
| 3 | Encender AYNI y abrir el navegador |
| 4 | Pausar: detiene sin borrar datos |
| 5 | Reiniciar |
| 6 | Abrir AYNI en el navegador |
| 7 | Ver credenciales |
| 8 | Abrir los manuales de usuario e instalación (carpeta `MANUAL`) |
| 9 | Abrir phpMyAdmin para ver la base de datos |
| 10 | Ejecutar todas las pruebas automáticas y abrir el reporte |
| 11 | Ver el estado de los servicios |
| 12 | Ver los registros (logs) en vivo |
| 13 | Ver los correos que el sistema enviaría (bandeja de prueba) |
| 14 | Reiniciar la base de datos con los datos por defecto (pide confirmar) |
| 15 | Crear un respaldo de la base de datos (carpeta `respaldos`) |
| 16 | Restaurar un respaldo (pide confirmar) |

## Si algo falla

| Problema | Solución |
|---|---|
| "Docker Desktop está cerrado" | La opción `3` lo abre sola. Si no responde, ábrelo desde el menú Inicio y espera a que diga *Engine running* |
| Un puerto está ocupado | El panel ofrece usar otro puerto y actualiza `CREDENCIALES.txt` |
| "Virtualización desactivada" | Actívala en la BIOS/UEFI (Intel VT-x o AMD-V / SVM) |
| La página no carga | Opción `2` para ver qué falla, y opción `12` para ver los registros |

El detalle completo está en `MANUAL/Manual de instalación`.
