# Seguridad — avisos conocidos y mitigaciones

## Por qué Laravel 10
El hosting del curso usa **PHP 8.1.34**, y Laravel 11 y 12 exigen PHP 8.2 o superior. Laravel 10 es la última versión compatible, pero ya no recibe parches de seguridad. Composer muestra estos avisos para la versión instalada (`laravel/framework` 10.50.3). Se permiten de forma explícita en `composer.json` (`config.policy.advisories.ignore`) con las mitigaciones de esta tabla:

| Aviso | Gravedad | ¿Nos afecta? | Mitigación |
|---|---|---|---|
| GHSA-5vg9-5847-vvmq — Inyección CRLF en la regla `email` por defecto | Alta | Solo si se usa la regla `email` de Laravel | **No se usa.** Los correos se validan con una expresión propia (`App\Support\Reglas::CORREO`, con `/D` para que `$` no acepte un salto de línea final). Lo verifica `Usuarios/UsuariosTest` |
| GHSA-crmm-hgp2-wgrp — Confusión de ruta en URLs firmadas temporales | Media | Solo si se usan URLs firmadas temporales | **No se usan.** La recuperación de contraseña y las descargas usan tokens propios guardados en BD |
| GHSA-jh5r-qr3c-85q8 / CVE-2026-102279 — XSS en la página de depuración | Baja | Solo con `APP_DEBUG=true` | En local (panel) y en la web, `APP_DEBUG=false`. Solo el modo desarrollo del equipo lo activa |
| GHSA-vvj3-c3rp-c85p — PHPUnit, deserialización insegura en cobertura PHPT | Alta | No: PHPUnit solo se usa para pruebas y no se instala en el hosting (`--no-dev`) | — |

## Otras medidas
- **Archivos internos:** el `.htaccess` de la raíz manda todo a `backend/public`. El código, `.env`, `vendor/`, `storage/`, `1. INICIO/` y la documentación no se pueden abrir desde el navegador. Las pruebas E2E lo verifican.
- **Cabeceras:** `X-Content-Type-Options`, `X-Frame-Options` y `Referrer-Policy`. Apache no revela su versión.
- **Errores:** los de la API no muestran detalles técnicos fuera de modo depuración.
- **Puertos locales:** la BD, phpMyAdmin y la bandeja de correos solo escuchan en `127.0.0.1`. La web se abre a la red local para probarla desde un celular.
- **Credenciales:** `CREDENCIALES_PRIVADAS.txt` y `.env` no se suben a GitHub ni al FTP.

## Acceso (Módulo 4)
- **Contraseñas:** bcrypt. Política por rol (RN-16) validada en el front y en el back; no puede ser igual al usuario.
- **Sesión:** tokens de Sanctum (sin cookies, así no hay CSRF). El token se guarda en `localStorage`, se envía como `Authorization: Bearer` y vence tras **1 hora sin peticiones** (también a las 12 h, aunque haya actividad). Al cerrar sesión se borra en el servidor. Al cambiar o restablecer la contraseña se cierran las demás sesiones.
  - Riesgo de `localStorage`: un XSS podría leer el token. Mitigación: React escapa todo el contenido, no se usa `dangerouslySetInnerHTML` y el token caduca por inactividad.
- **Captcha propio (RN-18):** imagen PNG dibujada con GD (respaldo SVG si GD no está). En la BD solo se guarda la huella HMAC de la respuesta. Sirve una vez, vence a los 5 minutos y se consume aunque la respuesta sea incorrecta.
- **Fuerza bruta:**
  - bloqueo de la cuenta tras 5 intentos fallidos (RN-15), con auditoría, alerta para la administración y correo al usuario;
  - límite de 10 intentos por minuto por usuario e IP, y de 60 por IP (el colegio comparte una IP pública; con un límite menor un aula no podría ingresar a la vez). La recuperación tiene la mitad.
- **Límite de la API:** 300 peticiones por minuto **por cuenta** (`AYNI_LIMITE_API`; se identifica por el token de Sanctum, no por la IP, para que un aula entera no comparta el cupo) y 600 por IP sin sesión (`AYNI_LIMITE_API_IP`). Cada pantalla hace de 5 a 10 peticiones. Si el servidor responde 429 o falla al confirmar la sesión, la app reintenta antes de pedir ingresar: solo un 401 la cierra.
- **Mensajes genéricos:** "Usuario o contraseña incorrectos" tanto si el usuario no existe como si la clave está mal. La recuperación responde lo mismo exista o no la cuenta.
- **Recuperación:** token aleatorio de 64 caracteres; en la BD solo su SHA-256. Un solo uso y 30 minutos (RN-25). No se usan URLs firmadas de Laravel (ver tabla de arriba).
- **Secreto E2E (`AYNI_SECRETO_E2E`):** las pruebas automáticas envían la cabecera `X-Ayni-E2E` para no tener que leer el captcha.
  - Solo funciona si el secreto está configurado **y** `APP_ENV` no es `production`.
  - El panel lo genera al azar en `.env` (que no se sube a GitHub). En el hosting no existe.
  - Una prueba E2E sin la cabecera comprueba que el captcha real sí se exige.

## Para la Unidad 3
En GCP sí se controla la versión de PHP. Allí conviene actualizar a Laravel 12 (10 → 11 → 12) para recuperar los parches oficiales.

## Usuarios y perfil (Módulo 5)
- **Solo la administración** usa `/api/v1/admin/*` (middleware `rol:ADMIN`). El rol se revisa **antes** de buscar el registro de la ruta (prioridad de middleware en `app/Http/Kernel.php`): otro rol recibe 403 y no puede averiguar qué ids existen.
- **Expresiones regulares con `/D`:** sin ese modificador, `$` en PHP acepta un salto de línea al final (`"41234567\n"` pasaba la validación del DNI). Se corrigió también en las reglas de usuario del M4.
- **Contraseña temporal:** aleatoria (`random_int`), cumple RN-16 y se muestra **una sola vez** al administrador; en la BD solo queda su hash. Si la cuenta tiene correo, se envía allí. Al generarla se cierran las sesiones de esa cuenta.
- **Inactivar** cierra las sesiones abiertas. No se puede inactivar la propia cuenta ni la última cuenta administrativa activa.
- **Auditoría (RF-16):** crear, editar (con valores antes y después, nunca contraseñas), activar, inactivar, desbloquear y editar el perfil quedan en `auditoria`, además de `creado_por` y `actualizado_por` en la cuenta.
- **Búsqueda:** `%` y `_` se escapan para que no funcionen como comodines.

## Estructura académica (Módulo 6)
- Todas las rutas van en `/api/v1/admin/*` con `rol:ADMIN`. Las reglas de negocio (RN-01, RN-02, RN-03, RN-05) se revisan en el servidor; el navegador solo adelanta los mensajes.
- **Cierre automático sin cron (RN-14):** el hosting no tiene tareas programadas. Al usar el sistema (cada consulta del calendario o de periodos) se cierran los bimestres cuya fecha de cierre ya pasó; se revisa como máximo una vez por hora (caché). En la auditoría queda sin usuario (`Auditoria::sistema`), aunque ocurra durante la petición de alguien.
- **Integridad:** no se elimina nada que esté en uso (periodo con aulas, aula con matrículas o cursos, asignatura dictada, curso con evaluaciones o asistencia, tipo de evaluación usado, bimestre con datos). Un periodo cerrado ya no se modifica.
- Cada alta, cambio o baja de la estructura queda en `auditoria`.

## Docentes, estudiantes e importación (Módulo 7)
- **Subida de archivos (RF-30):** solo `.xlsx` de hasta 5 MB. El archivo **no se guarda**: se lee desde el temporal de PHP en cada paso (revisar, descargar errores, importar), y los .xlsx que genera el sistema (plantilla, filas con error) se borran después de enviarse. Un archivo dañado o que no es Excel da un error claro, no un error 500. Como máximo 1000 filas por archivo.
- **OpenSpout 4** lee y escribe los Excel. No ejecuta fórmulas ni macros: solo lee los valores de las celdas. Necesita las extensiones `zip` y `xmlreader` de PHP; están en la imagen de Docker y **hay que confirmarlas en el hosting** antes de desplegar (Módulo 14).
- **Importación atómica:** las filas válidas se guardan en una sola transacción. Si una falla, no se guarda ninguna. Cada fila se valida con las mismas reglas que el formulario, más las repeticiones dentro del archivo.
- **Credenciales:** la contraseña temporal de cada estudiante importado se muestra una sola vez (y en el CSV que se arma en el navegador); en la BD solo queda su hash. Sin "crear usuario", las cuentas se crean desactivadas.
- **Estado y acceso:** un docente inactivo o un estudiante retirado, trasladado o egresado pierde el acceso (su cuenta se desactiva y se cierran sus sesiones).
- **Auditoría:** altas, cambios (con antes y después), apoderados, matrículas, retiros, anulaciones e importaciones quedan en `auditoria`.

## Asistencia (Módulo 8)
- **Por rol:** `/api/v1/docente/*` (`rol:DOCENTE`), `/api/v1/estudiante/*` (`rol:ESTUDIANTE`) y `/api/v1/admin/*` (`rol:ADMIN`). El docente solo ve y registra **sus** cursos: si pide el de otro docente recibe 403. El estudiante solo ve su propia asistencia (se busca por su sesión, nunca por un id que llegue en la petición).
- **Reglas en el servidor:** RN-06 (día de clase según el horario, dentro del bimestre, nunca en el futuro) y RN-07 (el docente corrige hasta las 11:59 p. m. del día de la clase) se revisan siempre en el backend con la hora de Lima; la interfaz solo las anticipa. Un bimestre cerrado no admite cambios de nadie.
- **Sin duplicados:** una sola clase por curso y fecha (índice único `ux_sesion_clase` y un estudiante por clase `ux_asistencia`). El guardado va en una transacción con bloqueo de la fila; si dos personas guardan a la vez una clase nueva, la segunda se reintenta y la encuentra.
- **Solo estudiantes de la clase:** se rechaza cualquier matrícula que no sea del aula (no se puede marcar a un estudiante de otra aula cambiando el id).
- **Quién cambió qué:** cada fila guarda quién la registró y, si se corrigió después de ser definitiva, quién la corrigió (`modificado_por`). En `auditoria` quedan el registro, cada corrección con los estados antes y después, y el borrado de una clase con su motivo.

## Evaluaciones, entregas y notas (Módulo 9)
- **Archivos fuera de lo público:** entregas y material se guardan en `storage/app` (que no se sirve por web) con un nombre aleatorio; el nombre original solo se usa al descargar. La descarga pasa siempre por `/api/v1/archivos/{id}`, que revisa el permiso: el administrador todo; el docente, lo de sus cursos; el estudiante, **solo su propia entrega** y el material ya asignado en su aula. Se responde con `X-Content-Type-Options: nosniff`.
- **Tipo real del archivo (RN-26):** la entrega debe empezar con la firma `%PDF-` y `fileinfo` debe reconocerla como `application/pdf`; un `.exe` renombrado a `.pdf` se rechaza. El material se revisa igual (firma de PDF, ZIP de Office o tipo de imagen). El límite es 10 MB o el menor que permita el servidor (`upload_max_filesize` / `post_max_size`).
- **Notas:** de 0 a 20 con hasta 2 decimales en el servidor (expresión regular + rango), aunque la pantalla también lo bloquee. Cada guardado queda en `auditoria` con las notas antes y después.
- **El estudiante no ve borradores (RN-13):** sus notas y su promedio de un curso solo se devuelven si la calificación está PUBLICADA o CERRADA. Todo se busca por su sesión, nunca por un id de estudiante que llegue en la petición.
- **Cierre (RN-14):** con el bimestre cerrado el servidor rechaza crear o editar evaluaciones, subir material, entregar, guardar notas y publicar.

## Incidencias y notificaciones (Módulo 10)
- **Quién ve una incidencia:** el administrador, todas; el docente, solo las de las aulas donde es tutor, dicta algún curso o coordina (aula, grado, nivel o colegio). La lista, el buscador de estudiantes y el registro usan la misma regla en el servidor: registrar una incidencia de otra aula responde 403. El estudiante no tiene acceso (`rol:DOCENTE` / `rol:ADMIN`).
- **Sin datos ajenos en la campana:** las notificaciones se buscan siempre por el usuario de la sesión; marcar como leída la de otro responde 404 y no la cambia.
- **Sin cron:** los avisos por fecha (actividad por vencer, cierre próximo) se crean al abrir la campana y se revisa que no existan antes, para no repetirlos.
- **Auditoría:** cada incidencia guarda quién la registró (`registrado_por`) y queda en `auditoria`. No se editan ni se eliminan.

## Alertas y riesgo (Módulo 11)
- **Quién ve una alerta:** la administración, todas; el docente, las de las aulas donde es tutor, dicta un curso o coordina (la misma regla que las incidencias, en `AulasDelUsuario`). Otra aula responde 403; una alerta que no existe, 404.
- **Quién la gestiona:** cambiar su estado y registrar intervenciones, el encargado, el tutor, la coordinación del aula y la administración. Cambiar de encargado, solo la coordinación y la administración, y solo a un docente activo del estudiante. Todo se revisa en el servidor, aunque la pantalla oculte los botones.
- **Paneles:** el de riesgo es solo de la administración (`rol:ADMIN`); el de coordinación muestra únicamente las aulas que el docente coordina: pedir otra aula por la URL no amplía el ámbito. «Recalcular» del coordinador solo recalcula su ámbito.
- **Sin duplicados:** una alerta activa por estudiante y bimestre está garantizada por el índice único `ux_alerta_activa`; las administrativas, por `ux_alerta_admin_abierta`.
- **Rastro:** cada cambio de estado, intervención y reasignación queda en `historial_alerta` con usuario y fecha, y en `auditoria`. Las acciones automáticas aparecen como «Sistema».
- **Sin cron:** el cálculo diario se dispara en el primer ingreso del día; si fallara, el ingreso sigue funcionando (se registra en el log).


## Inicio, ficha 360° y reportes (Módulo 12)
- **Cada rol ve solo lo suyo:** `/admin/inicio`, `/docente/inicio` y `/estudiante/inicio` van con su `rol:`; el estudiante solo recibe sus propias notas publicadas, su asistencia y sus entregas.
- **Ficha 360°:** la administración ve cualquiera; el docente, solo las de las aulas donde es tutor, dicta un curso o coordina (`AulasDelUsuario`, la misma regla de incidencias y alertas). Otra aula responde 403 y una matrícula que no existe, 404. El estudiante no tiene ficha.
- **Mis estudiantes:** pedir un `curso_id` ajeno por la URL devuelve el primer curso propio; nunca lista a los estudiantes de otro docente.
- **Boleta en PDF:** el estudiante solo descarga la suya (`/estudiante/boleta`, sin parámetros); la de cualquier estudiante es solo de la administración. Lleva únicamente promedios publicados o cerrados (RN-13), para no adelantar borradores.
- **Excel:** el consolidado es solo de la administración; el docente exporta la asistencia únicamente de sus aulas (403 en otra). El aula se valida en el servidor (422 «Elige el aula.»).
- **PDF propio:** se escribe con `App\Support\Pdf` (sin librerías); el texto se escapa (`\`, `(`, `)`) y se convierte a WinAnsi, así que un nombre con paréntesis o tildes no rompe ni inyecta contenido en el PDF.
- **Sin N+1:** inicio, ficha y mis estudiantes tienen pruebas que cuentan las consultas al agregar más estudiantes, incidencias y apoderados.

## Calidad y seguridad final (Módulo 13)
- **Cabeceras:** la API (Laravel, `CabecerasSeguridad`) envía `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy` y `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'`; con sesión, `Cache-Control: no-store` (los datos personales no quedan en la caché del navegador); con HTTPS, `Strict-Transport-Security`. React y los archivos estáticos las reciben del `.htaccess`, con una CSP que solo permite recursos propios (sin scripts externos ni `eval`; Zod valida en modo `jitless`). Cada cabecera llega una sola vez: Apache no las repite en la API.
- **CORS cerrado:** React y la API están en el mismo sitio, así que ningún otro origen puede llamar a la API desde un navegador. Si algún día hiciera falta, se listan en `AYNI_ORIGENES_CORS`.
- **Permisos en TODAS las rutas:** `Seguridad/RutasTest` recorre las rutas registradas: sin sesión, todas menos las 6 públicas responden 401; cada ruta de un rol responde 403 a los otros dos (más de 200 combinaciones), y el prefijo (`/admin`, `/docente`, `/estudiante`) siempre coincide con su rol. Una ruta nueva sin protección hace fallar la prueba.
- **Permisos cruzados:** `Seguridad/PermisosCruzadosTest`: el docente de otro curso de la misma aula, y uno de otra aula, no ven ni cambian asistencia, evaluaciones, notas, promedios, fichas, alertas ni reportes ajenos; no se puede colar en una planilla a un estudiante de otra aula; un estudiante no ve lo de otra aula.
- **Inyección SQL:** todas las consultas usan parámetros (`?`), incluidas las búsquedas con `LIKE`. `Seguridad/InyeccionTest` prueba 7 ataques típicos (`' OR '1'='1`, `UNION SELECT`, `SLEEP`, `DROP TABLE`…) en los buscadores de 9 listas: responden sin resultados, sin error y sin tardar; los filtros numéricos y de lista responden 422.
- **XSS:** el texto se guarda tal cual y se devuelve como JSON; React lo muestra como texto (no se usa `dangerouslySetInnerHTML`). Los nombres de personas no aceptan `<`, `>` ni símbolos de código.
- **Inyección en planillas:** un texto que empieza con `=` (por ejemplo el título de una evaluación) se escribe en Excel como texto, nunca como fórmula. El PDF escapa `(`, `)` y `\`.
- **Archivos maliciosos:** entregas solo PDF real; material solo PDF, Office real (un `.zip` renombrado a `.docx` no pasa), JPG o PNG; importador solo `.xlsx` legible. Se guardan fuera de la carpeta pública con nombre aleatorio y una extensión de la lista (un `.php` se guarda como `.bin`); se descargan como adjunto con `nosniff`.
- **Errores de tipo:** enviar una lista donde va un texto (por ejemplo `acceso[]=x` al ingresar) responde 422 y no un error 500.
- **Rendimiento:** `RendimientoTest` mide con los datos de demostración las pantallas de los tres roles: cada una responde en menos de 300 ms; y comprueba los índices de las columnas que más se consultan. El bundle tiene presupuesto (`npm run presupuesto`): carga inicial ≤ 160 kB y cada pantalla ≤ 60 kB comprimidos.
- **Accesibilidad y celular:** `m13-pantallas.spec.ts` abre todas las pantallas del menú de cada rol en PC y celular, sin desborde horizontal, sin errores de JavaScript ni de la CSP y sin problemas graves de axe (WCAG 2.1 A y AA); además revisa el foco visible y "Saltar al contenido" con el teclado.

## Publicación en el hosting (Módulo 14)
- **Lo privado responde 403:** el `.htaccess` de la raíz prohíbe los archivos ocultos (`.env`, `.git`, `.htaccess`), `vendor/`, `storage/` y **cualquier archivo o carpeta real** del proyecto fuera de `backend/public` (`1. INICIO/`, `docs/`, `scripts/`, `backend/`, `docker-compose.yml`…). Las pantallas de React y `/api` siguen funcionando. Se probó en Apache bajo `/grupo02/` (la CI arma un hosting simulado con PHP 8.1 y MariaDB 10.6) y lo revisa la prueba de humo contra la URL real.
- **Sin listado de carpetas:** `Options -Indexes` (el hosting lo trae activado).
- **HTTPS obligatorio:** en el dominio del hosting, `http://` redirige a `https://` (301). En local no aplica: no hay certificado.
- **Qué viaja al FTP:** solo los archivos que están en git (con `git ls-files`), más el `vendor/` y la compilación de React, y el `backend/.env` del servidor (`APP_ENV=production`, `APP_DEBUG=false`). Nunca viajan los `.env` locales, `CREDENCIALES_PRIVADAS.txt`, `node_modules`, respaldos ni reportes.
- **FTPS:** la subida usa FTP con TLS explícito y valida el certificado. Si el del hosting no coincide con su nombre, solo se acepta el de la huella SHA-1 configurada (`FTP_HUELLA_CERTIFICADO`); nunca se desactiva la verificación.
- **El `.env` del servidor está en el FTP compartido:** el profesor y otros grupos pueden leerlo por FTP (no desde el navegador). Por eso la cuenta Gmail es exclusiva del proyecto, con contraseña de aplicación, y la contraseña de la BD es la que entregó el profesor solo para el grupo 02.
- **Captcha en la prueba de humo:** en producción el secreto de pruebas solo funciona si mide 32 caracteres o más **y** tiene vencimiento (`AYNI_SECRETO_E2E_HASTA`, como máximo 2 horas). El script lo genera al azar, lo sube solo durante la prueba (30 minutos) y lo quita al terminar aunque la prueba falle (`CaptchaTest`).
- **Contraseñas con símbolos:** el script escapa `\`, `"` y `$` al escribir el `.env`, para que una clave con esos caracteres no rompa la configuración.
