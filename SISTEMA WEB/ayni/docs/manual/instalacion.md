# Manual de instalación de AYNI

## 1. Formas de usar AYNI

| Forma | Para quién | Qué se necesita |
|---|---|---|
| **Web** | Cualquier usuario | Un navegador. Dirección: https://aplicacionenlanube.cetivirgendelapuerta.com/grupo02/ (desde el Módulo 14) |
| **Local** | Revisión del sistema en una PC | Descargar la carpeta del proyecto y usar el panel `1. INICIO/AYNI.bat` (Windows) o `AYNI.sh` (Linux) |

La versión local usa el mismo código y las mismas reglas de seguridad que la versión web.

## 2. Requisitos de la PC (versión local)

| Requisito | Mínimo | Recomendado |
|---|---|---|
| Sistema | Windows 10 22H2 de 64 bits · Linux de 64 bits | Windows 11 |
| Memoria RAM | 4 GB | 8 GB |
| Disco libre | 8 GB | 15 GB |
| Virtualización | Activada en la BIOS (Intel VT-x / AMD-V) | — |
| Internet | Solo en la primera instalación (~2 GB) | — |

No hace falta instalar PHP, MySQL, Node ni ningún otro programa: el sistema corre dentro de **Docker** y el panel lo instala si falta.

## 3. Instalación en Windows

1. Copia o descarga la carpeta `ayni` en cualquier ubicación (por ejemplo, Documentos). La ruta puede tener espacios o tildes.
2. Abre la carpeta **`1. INICIO`** y haz doble clic en **`AYNI.bat`**.
   - Si aparece **"Windows protegió su PC"**, pulsa **Más información → Ejecutar de todas formas**. Ocurre porque el archivo viene de otra PC.
3. En el menú escribe **`1`** y presiona Enter. El panel:
   1. revisa la PC (Windows, RAM, disco, virtualización);
   2. instala **WSL2** si falta (pide permiso de administrador y reiniciar);
   3. instala **Docker Desktop** si falta, desde la fuente oficial. Al terminar, **abre Docker Desktop y acepta sus términos**; luego vuelve a elegir `1`;
   4. crea la configuración local y las credenciales;
   5. construye AYNI (la primera vez tarda entre 5 y 15 minutos);
   6. prepara la base de datos con los datos por defecto;
   7. verifica que todo funcione y lo muestra con `[OK]`.
4. Abre el sistema con la opción **`3`** o **`6`**: http://localhost:5173.

## 4. Instalación en Linux

```bash
cd ruta/de/ayni
bash "1. INICIO/AYNI.sh"
```

Elige **`1`**. Si falta Docker, el panel ofrece instalar Docker Engine con el instalador oficial (pide la contraseña de `sudo`). Después de instalarlo, cierra sesión y vuelve a entrar para usar Docker sin `sudo`.

Cada opción también se puede ejecutar directamente:

```bash
bash "1. INICIO/AYNI.sh" encender
```

## 5. El panel de control, opción por opción

| N.° | Opción | Qué hace |
|---|---|---|
| 1 | Instalar lo necesario | Instalación completa (sección 3). Se puede repetir sin perder datos |
| 2 | Verificar requisitos y dependencias | Revisa PC, Docker, puertos, servicios, API, base de datos y web. Cada punto sale con `[OK]` o `[X]` y la forma de arreglarlo |
| 3 | Encender AYNI | Abre Docker Desktop si está cerrado, enciende los servicios, espera a que respondan y abre el navegador |
| 4 | Pausar | Detiene AYNI sin borrar datos |
| 5 | Reiniciar | Reinicia todos los servicios |
| 6 | Abrir AYNI en el navegador | Abre http://localhost:5173 |
| 7 | Ver credenciales | Abre `CREDENCIALES.txt` en el Bloc de notas |
| 8 | Abrir manual de usuario | Abre el manual en PDF |
| 9 | Abrir phpMyAdmin | Abre la base de datos en http://localhost:8080 (usuario y clave en la opción 7) |
| 10 | Ejecutar todas las pruebas | Corre las pruebas automáticas (backend, frontend, navegador en PC y celular, y panel) y abre un reporte HTML |
| 11 | Ver estado de los servicios | Lista los servicios y si están en marcha |
| 12 | Ver registros en vivo | Abre otra ventana con los registros de los servicios |
| 13 | Ver correos enviados | Abre la bandeja de prueba, donde llegan los correos que el sistema envía en local |
| 14 | Reiniciar la BD con datos por defecto | Borra los datos y carga los datos de demostración. Pide confirmar |
| 15 | Crear respaldo | Guarda la base de datos en `respaldos/ayni_AAAA-MM-DD_HHMMSS.sql` |
| 16 | Restaurar un respaldo | Reemplaza la base de datos por un respaldo elegido. Pide confirmar |

**Servicios que levanta el panel:**

| Servicio | Dirección | Acceso desde |
|---|---|---|
| AYNI (web + API) | http://localhost:5173 | Esta PC y la red local (celular en la misma Wi-Fi) |
| phpMyAdmin | http://localhost:8080 | Solo esta PC |
| Bandeja de correos | http://localhost:8025 | Solo esta PC |
| MySQL | 127.0.0.1:3307 | Solo esta PC |

Si un puerto está ocupado por otro programa, el panel propone uno libre y actualiza `CREDENCIALES.txt`.

## 6. Datos por defecto

Al instalar, AYNI carga un **colegio de demostración** ("IEP El Señor es mi Pastor") con personas ficticias:
- 2 administradores, 15 docentes (uno coordina el 3.º A y otra coordina primaria) y unos 150 estudiantes;
- 12 aulas (primaria 1.º–6.º de sección única; secundaria 1.º A, 2.º A, 3.º A, 3.º B, 4.º A y 5.º A);
- horarios sin cruces, asistencia, evaluaciones, entregas, notas e incidencias.

Las fechas se calculan a partir del **día de la instalación**: siempre hay un bimestre en curso y los anteriores ya están cerrados. Los usuarios y contraseñas están en `CREDENCIALES.txt` (opción `7`).

La opción `14` vuelve a cargar estos datos desde cero, con las fechas de ese día.

## 7. Respaldo y restauración

- **Crear:** opción `15`. El archivo `.sql` queda en la carpeta `respaldos`.
- **Restaurar:** opción `16`. Muestra la lista de respaldos (el más reciente primero), pide el número y confirmación.
- **Usar un respaldo de otra PC:** copia el `.sql` dentro de la carpeta `respaldos` y usa la opción `16`.

## 8. Problemas frecuentes

| Problema | Solución |
|---|---|
| "Windows protegió su PC" al abrir `AYNI.bat` | Más información → Ejecutar de todas formas |
| "Docker Desktop está cerrado" | La opción `3` lo abre. Si no responde, ábrelo desde el menú Inicio y espera a ver *Engine running* |
| Docker Desktop pide aceptar términos o iniciar sesión | Acepta los términos; iniciar sesión **no** es necesario (*Skip*) |
| "Virtualización activada: [X]" | Entra a la BIOS/UEFI y activa Intel VT-x o AMD-V (SVM) |
| "El puerto … lo está usando …" | Acepta el puerto alternativo que propone el panel |
| La instalación se corta descargando | Revisa la conexión y vuelve a elegir `1`: continúa donde se quedó |
| La página no carga | Opción `2` para ver qué falla, luego la opción `12` para ver los registros |
| Después de actualizar AYNI a una versión nueva, faltan datos o hay errores | Opción `1` (actualiza AYNI) y luego la opción `14` (reinicia la BD con datos por defecto) |
| "Memoria RAM: … irá lento" | Funciona, pero cierra otros programas pesados mientras usas AYNI |
| Una cuenta de demostración quedó bloqueada (5 intentos fallidos) | Las de administración se liberan solas en 15 minutos. Para las demás, opción `14` (reinicia la BD con los datos de demostración) |
| No llega el correo de recuperación de contraseña | En la instalación local los correos no salen a internet: ábrelos con la opción `13` (Correos de prueba) |
