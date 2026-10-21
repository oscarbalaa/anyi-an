# AYNI — Plan de construcción por módulos (Unidad 1)

**Grupo 02:**
- Balarezo Castañeda Oscar Isaac (coordinador)
- Camargo Herrera Lucciano Sebastian
- Flores Garcia Sofia Sharon
- Nuñez Chavez Jose Gerardo
- Ramirez Aranda Jerson

**Stack:**
- **Frontend:** React 19 + Vite + TypeScript + Tailwind + shadcn/ui.
- **Backend:** Laravel 10 API + Sanctum sobre **PHP 8.1**, la misma versión del hosting del profe.
- **BD:** MySQL o MariaDB (el mismo motor que tenga el hosting), en `utf8mb4` para ñ y tildes, con zona horaria `America/Lima`.
- **Arranque local:** Docker Compose.
- **Correo:** SMTP de Gmail.

**Alcance de la Unidad 1:**
- Sistema académico completo y funcionando, **sin IA**.
- Debe funcionar de **dos formas**: en la web (`https://aplicacionenlanube.cetivirgendelapuerta.com/grupo02/`) y en local (descargando la carpeta).
- La IA (Unidad 2) y GCP (Unidad 3) están solo bosquejadas al final.
- **Informe: en pausa** hasta que lo indiques (ver el final).

**Prioridad:** presentarlo lo antes posible. Los módulos se hacen en orden y el ★ del M9 marca el mínimo funcional.

---

## Cómo vamos a trabajar

1. Me pides un módulo: **"Avanza con el Módulo N"**. Todo el código se hace desde Claude Code, desde cero.
2. Lo construyo completo (BD → API → pantallas), escribo sus pruebas y las ejecuto. Solo lo entrego si **todas pasan**.
3. Te entrego:
   - un resumen de lo hecho;
   - el resultado de las pruebas;
   - la lista **"Prueba tú mismo"** con los pasos y lo que debes ver;
   - las credenciales actualizadas en `1. INICIO/CREDENCIALES.txt`.
4. Tú lo pruebas con `1. INICIO/AYNI.bat`. Si algo falla, me lo dices y lo corrijo dentro del mismo módulo.
5. Cuando das el OK, hago commit **sin coautoría** y lo marco con una etiqueta de git (`m1`, `m2`, …) para poder volver a cualquier punto. Recién entonces pasamos al siguiente módulo.

> Ningún módulo empieza si el anterior no está aprobado por ti.

**Repositorio:** GitHub privado, solo para el grupo; no se comparte con el docente.

**Estilo del código:**
- Comentarios cortos y directos, solo donde aportan; que no se note que los escribió una IA.
- Dominio, BD, mensajes y comentarios en español (Estudiante, Matricula…).
- Estructura propia de Laravel y React (controllers, services, hooks) en inglés.

### Estructura del proyecto

```
SISTEMA WEB/ayni/
├─ README.md                   ← corto: "ejecuta 1. INICIO/AYNI.bat para iniciar" + dónde está el manual
├─ 1. INICIO/                  ← TODO lo que se necesita para empezar
│  ├─ AYNI.bat                 ← Windows: doble clic, panel de control
│  ├─ AYNI.sh                  ← Linux: el mismo panel en terminal
│  ├─ CREDENCIALES.txt         ← usuarios demo LOCALES, URLs, BD local (se puede compartir)
│  ├─ CREDENCIALES_PRIVADAS.txt← FTP, BD del hosting, SMTP (NO se sube a git NI al FTP)
│  ├─ README.md                ← instalación paso a paso (Windows y Linux, local y web)
│  └─ MANUAL/
│     ├─ Manual de usuario.pdf / .docx
│     └─ Manual de instalación.pdf / .docx   (los .docx se borran en la entrega final)
├─ CLAUDE.md                   ← convenciones de código del proyecto
├─ docker-compose.yml          ← modo normal (el que usa el panel)
├─ docker-compose.dev.yml      ← modo desarrollo (recarga en caliente)
├─ backend/                    ← Laravel 10 / PHP 8.1 (API REST /api/v1)
├─ frontend/                   ← React + Vite
├─ e2e/                        ← pruebas de punta a punta (Playwright)
├─ scripts/                    ← lógica del panel (ayni.ps1 / ayni.sh)
│  └─ equipo/                  ← solo para el equipo (publicar en hosting, limpieza, modo desarrollo)
└─ docs/
   ├─ requisitos.md            ← RF/RNF con código + casos de uso + trazabilidad
   ├─ validaciones.md          ← matriz de reglas de TODOS los campos
   ├─ bd/                      ← script .sql exportado + diagrama ER
   └─ manual/                  ← fuente del manual (Markdown + capturas automáticas)
```

### Pruebas: qué lleva cada módulo

| Tipo | Herramienta | Qué valida |
|---|---|---|
| **API (feature)** | Pest 2 (compatible con PHP 8.1) | Que cada endpoint responda bien, que devuelva 401 sin sesión y 403 con un rol que no corresponde |
| **Validaciones** | Pest + Zod | Cada campo rechaza lo que no debe (letras en el DNI, números en los nombres, correos sin @…). Devuelve 422 con el mensaje en español |
| **Reglas de negocio** | Pest (unitarias) | Cálculos y restricciones: promedios, cruces de horario, una matrícula por año, cierres de bimestre… |
| **Componentes y formularios** | Vitest + Testing Library | Que los formularios bloqueen la entrada inválida y muestren el error |
| **Punta a punta (E2E)** | Playwright (en su contenedor oficial) | El flujo real en el navegador, con cada rol, en PC y en celular |
| **Calidad del código** | Laravel Pint + ESLint + Prettier | Formato y estilo uniformes |
| **Manual** | Lista "Prueba tú mismo" | Tu verificación visual y funcional |

- **Ejecución:** todas las pruebas se corren dentro de Docker con la **opción 10 del panel** (la PC no necesita PHP ni Node) y generan un reporte HTML.
- **Trazabilidad:** cada prueba indica qué requisito valida (RF-xx).
- **Meta:** la lógica de negocio del backend con **≥ 80 % de cobertura**.

### Manuales: se escriben en cada módulo, no al final

Cada módulo deja escrita su sección en `docs/manual/`, con **capturas generadas automáticamente por Playwright** (en PC y celular), así siempre están al día con el sistema real. En el Módulo 15 se exportan a Word y PDF en `1. INICIO/MANUAL/`.

---

## Panel de control (se crea en el Módulo 1 y crece con cada módulo)

**Windows:** doble clic en `1. INICIO/AYNI.bat`. Por dentro ejecuta `scripts/ayni.ps1`, ubicando la raíz del proyecto aunque el `.bat` esté en `1. INICIO`, sin pedir cambios de permisos.

**Linux:** `1. INICIO/AYNI.sh` muestra el mismo menú en la terminal.

```
==============================================
        AYNI - Panel de control      v0.x
==============================================
 Estado: ENCENDIDO / APAGADO / NO INSTALADO
 Web: http://localhost:5173   phpMyAdmin: http://localhost:8080
----------------------------------------------
 PRIMERA VEZ
  [1]  Instalar lo necesario (Docker, dependencias y BD)
  [2]  Verificar requisitos y dependencias
 USO DIARIO
  [3]  Encender AYNI y abrir el navegador
  [4]  Pausar (detener sin borrar datos)
  [5]  Reiniciar
  [6]  Abrir AYNI en el navegador
  [7]  Ver credenciales (Bloc de notas)
  [8]  Abrir manual de usuario
 REVISIÓN
  [9]  Abrir phpMyAdmin (ver la base de datos)
  [10] Ejecutar TODAS las pruebas + abrir reporte
  [11] Ver estado de los servicios
  [12] Ver registros (logs) en vivo
  [13] Ver correos enviados (bandeja de prueba local)
 DATOS
  [14] Reiniciar la BD con datos por defecto   (pide confirmar S/N)
  [15] Crear respaldo de la BD (.sql con fecha)
  [16] Restaurar un respaldo                   (pide confirmar S/N)
  [0]  Salir
==============================================
```

**[1] Instalar lo necesario.** Se puede ejecutar varias veces sin romper nada:
1. Revisa el sistema operativo (Windows 10 22H2+/11 de 64 bits, o Linux), RAM, espacio en disco y virtualización activada.
2. **Windows:** si falta **WSL2** o **Docker Desktop**, ofrece instalarlos desde la fuente oficial (`winget`).
   - Pide permiso de administrador y avisa si hay que reiniciar la PC.
   - La primera vez que se abre Docker Desktop hay que aceptar sus términos a mano; el panel lo explica en pantalla.
3. **Linux:** si falta Docker, ofrece instalar Docker Engine con el instalador oficial (pide `sudo`).
4. Crea la configuración local y genera las claves de la aplicación. **Ignora la configuración del hosting**, aunque la copia venga del FTP.
5. Construye los contenedores e instala las dependencias de Laravel (Composer) y de React (npm) **dentro de Docker**: la PC no necesita PHP, Node ni MySQL.
6. Crea la BD y carga los datos por defecto.
7. Al final ejecuta la verificación [2] y muestra el resumen.

**[2] Verificar requisitos y dependencias.** Lista cada punto con `[OK]` o `[X]` y, si falla, dice cómo arreglarlo:
- Sistema operativo, RAM, disco, virtualización, WSL2 (Windows).
- Docker instalado y corriendo.
- Puertos 5173, 8080, 8025 y 3307 libres (o usados por AYNI).
- Contenedores construidos y sanos.
- La API responde, la BD conecta y la web carga.

**[13] Correos de prueba.** En local los correos no salen a internet: llegan a una bandeja de prueba (Mailpit, `http://localhost:8025`) para ver qué se enviaría, por ejemplo la recuperación de contraseña. En la web los correos salen por el SMTP de Gmail.

**Comportamiento general:**
- **[3] Encender** detecta si AYNI aún no está instalado y ofrece ejecutar [1].
- **Al encender:** espera a que la API responda y recién entonces abre el navegador.
- **Opciones destructivas:** [14] y [16] reemplazan los datos actuales, por eso piden confirmación.
- **Puertos:**
  - MySQL usa el 3307 para no chocar con un XAMPP instalado.
  - Si un puerto está ocupado por otro programa, el panel ofrece uno alternativo y actualiza `CREDENCIALES.txt`.
- **Una sola dirección:** la web y la API salen por el mismo puerto (5173). La API vive en `/api` detrás de un *proxy*, igual que en el hosting, y por eso también funciona desde un celular de la misma red.
- **Modo normal vs. desarrollo:**
  - el **modo normal** (profesor) usa el código ya construido dentro de los contenedores, con OPcache: rápido incluso en Windows;
  - el **modo desarrollo** (equipo) usa recarga en caliente, con `vendor/` y `node_modules/` en volúmenes de Docker para que no se vuelva lento.
- **Robustez en Windows:**
  - funciona desde cualquier carpeta, aunque la ruta tenga espacios o tildes (como `1. INICIO`);
  - el script se guarda en UTF-8 con BOM y usa símbolos simples (`[OK]`, `[X]`);
  - al primer uso desbloquea los archivos si el proyecto llegó descargado. Si Windows muestra "Windows protegió su PC", el manual explica: "Más información → Ejecutar de todas formas".
- **Manual [8]:** abre `1. INICIO/MANUAL/`. Hasta el Módulo 15 muestra la versión parcial que se va armando.
- **Requisitos para la primera instalación:**
  - internet: ~2 GB para lo básico, más ~2 GB opcionales que se descargan la primera vez que se ejecutan las pruebas E2E;
  - idealmente 8 GB de RAM (con 4 GB funciona, pero lento).

**Scripts del equipo, fuera del menú** (en `scripts/equipo/`):
- `publicar-hosting.ps1`: prepara y sube el proyecto al FTP (Módulo 14). **Nunca se ejecuta sin tu OK.**
- `limpiar-todo.ps1`: borra contenedores, volúmenes y datos; pide escribir BORRAR.
- `modo-desarrollo.ps1`: levanta el modo con recarga en caliente.

## Credenciales en bloc de notas

- **`1. INICIO/CREDENCIALES.txt`:**
  - URLs locales y web;
  - usuario y clave de cada rol demo: 2 administradores, coordinador, docente, estudiante de primaria y de secundaria;
  - acceso a phpMyAdmin y a la bandeja de correos de prueba.
  - Se abre con la opción 7 y se actualiza en cada módulo.
- **`1. INICIO/CREDENCIALES_PRIVADAS.txt`:** FTP, BD del hosting, SMTP de Gmail.
  - Está en `.gitignore` y **no se sube ni a GitHub ni al FTP**.
- **Contraseñas del código:** ninguna va escrita en el código; todo se lee del `.env`.
- **Cambio de contraseña:** los usuarios demo **no** piden cambiarla al entrar, para que el bloc siempre sirva. El cambio obligatorio aplica solo a los usuarios que crea el admin o que llegan por importación.
- **⚠️ Gmail:** el FTP lo comparten todos los grupos, así que la configuración del servidor (con la clave de aplicación de Gmail) puede ser leída por otros. **Usa una cuenta Gmail creada solo para el proyecto**, no la personal, con verificación en 2 pasos y una *contraseña de aplicación*.

---

# Módulos

## Módulo 0 — Requisitos y preparación *(sin código)*
**Objetivo:** cerrar lo que define la BD antes de construir.

**Decisiones adoptadas:**

| # | Tema | Decisión |
|---|---|---|
| D1 | Coordinador académico | Docente con un **ámbito de coordinación**: un aula, un grado/nivel o toda la institución. Ingresa por el acceso de docente. Tabla `coordinacion` |
| D2 | Padres / apoderados | Solo como datos del estudiante (sin acceso al sistema) en la Unidad 1 |
| D3 | Colegio | Tabla `institucion` con el nombre real "**IEP El Señor es mi Pastor**" y su configuración (nota aprobatoria 11, umbrales). **Personas siempre ficticias** |
| D4 | Escala | Nota vigesimal 0–20, con conversión a AD/A/B/C solo para mostrarla |
| D5 | Usuario para ingresar | Estudiantes con su **código de estudiante**, docentes con su **DNI** y admin con nombre de usuario |
| D6 | Niveles | Solo **primaria (1.º–6.º) y secundaria (1.º–5.º)**, sin inicial. Las secciones se eligen por grado: **"Única"** o A, B, C… |
| D7 | Asistencia | Solo **por clase**. El ingreso/salida del colegio queda como trabajo futuro |
| D8 | Correo | SMTP de Gmail (cuenta propia del proyecto) para recuperar contraseñas y enviar avisos de cuenta (ver M4 y M5) |
| D9 | Captcha | **Propio, interno**, sin servicios externos (ver M4) |

**Valores por defecto** (el admin puede cambiarlos después):

| Parámetro | Valor |
|---|---|
| Bloqueo de cuenta | Tras 5 intentos fallidos |
| Sesión | Se cierra tras **1 hora** de inactividad |
| Nota aprobatoria | 11 |
| Riesgo **ALTO** | Asistencia < 85 %, o promedio < 11 con ≥ 3 entregas pendientes |
| Riesgo **MEDIO** | Asistencia < 90 %, o promedio < 12, o notas en descenso dos evaluaciones seguidas |
| Corrección de asistencia por el docente | Hasta las 11:59 p. m. del mismo día; después solo el admin |
| Archivo de entrega | PDF de hasta 10 MB, o lo que permita el hosting si es menos |

**Entregable del módulo:** `docs/requisitos.md` con:
- requisitos funcionales y no funcionales con código (RF-01…, RNF-01…);
- actores y casos de uso.

Es la base de las pruebas.

**Lo que ya sabemos del hosting** (revisado el 16/10/2026):
- **Es un servidor web real:** LiteSpeed con PHP. Nuestra carpeta es `grupo02/` y se ve en `https://aplicacionenlanube.cetivirgendelapuerta.com/grupo02/`.
- **Corre PHP 8.1.34**, por eso usamos **Laravel 10**.
- **Solo tenemos FTP:** no hay SSH, cron ni procesos en segundo plano. El sistema se diseña para **no depender de ninguno de ellos**.
- **Seguridad:** el listado de carpetas está activado y una sola cuenta FTP es compartida por todos los grupos.

**Pendiente de pedirle al profe** (por WhatsApp): la base de datos del grupo 02 (nombre, usuario, contraseña, motor/versión) y si él importa nuestro `.sql` o nos da acceso a phpMyAdmin.

**Además:**
- crear el repositorio privado en GitHub;
- crear la cuenta Gmail del proyecto con su contraseña de aplicación.

**Terminado cuando:** `docs/requisitos.md` está aprobado por ti.

---

## Módulo 1 — Esqueleto del proyecto + panel de control
**Objetivo:** proyecto vacío pero completo, que arranca con un doble clic en Windows y con un comando en Linux.

- **Backend:** Laravel 10 API sobre PHP 8.1, Sanctum, endpoint `/api/v1/health`, errores en formato JSON y en español, zona horaria `America/Lima`.
- **Compatibilidad con el hosting (blindaje):**
  - el contenedor local usa **exactamente PHP 8.1**, igual que el servidor;
  - `composer.json` fija la plataforma en PHP 8.1.34, así Composer nunca instala una librería que exija una versión mayor;
  - el esquema de BD usa solo funciones que existen en MySQL 8 y MariaDB 10.6+;
  - una prueba automática (`composer check-platform-reqs` en PHP 8.1) falla si algo exige más;
  - colas en modo síncrono: nada depende de procesos en segundo plano.
- **Frontend:** React + Vite + TS, Tailwind, shadcn/ui, React Router, TanStack Query, Zod + React Hook Form, cliente HTTP con manejo de errores. La API se llama con la ruta relativa `/api/v1`, lo que sirve igual en local y en `grupo02/`.
- **Docker Compose:**
  - **modo normal:** servicios `mysql`, `backend`, `web` (sirve React y hace de *proxy* a la API), `phpmyadmin` y `mailpit`;
  - **modo desarrollo** con recarga en caliente.
- **Carpeta `1. INICIO`:** `AYNI.bat`, `AYNI.sh`, `CREDENCIALES.txt`, `CREDENCIALES_PRIVADAS.txt`, `README.md` y `MANUAL/` (vacía al inicio).
- **Raíz:** `README.md` corto, `CLAUDE.md` y los scripts del equipo.
- **Pruebas:** Pest 2, Vitest, Playwright, Pint, ESLint y Prettier configurados. GitHub Actions ejecuta todas las pruebas en cada `push`.

**Pruebas:**
- `/health` responde 200 y confirma la conexión a la BD.
- La página carga sin errores en consola.
- La plataforma es compatible con PHP 8.1.
- El panel detecta la ausencia de Docker o un puerto ocupado.
- `AYNI.sh` se prueba en Linux (Ubuntu dentro de WSL).

**Prueba tú mismo:**
1. Doble clic en `1. INICIO/AYNI.bat`, luego `1` (instalar): termina con el resumen en `[OK]`.
2. Opción `2`: la verificación sale toda en `[OK]`.
3. Opción `3`: se abre el navegador con la página base.
4. Opción `4`: la página deja de responder. Opción `3` otra vez: vuelve.
5. Opción `9`: phpMyAdmin abre con las credenciales del bloc (opción `7`).
6. Opción `10`: todas las pruebas en verde.
7. Copia la carpeta `ayni` a otra ubicación y repite el paso 3.

---

## Módulo 2 — Base de datos
**Objetivo:** BD definitiva en MySQL/MariaDB, reproducible y con datos realistas.

- **Migraciones:** pasar el script v1 de PostgreSQL a **migraciones de Laravel para MySQL**, con estos cambios:
  - **nuevas tablas:**
    - `institucion`, `coordinacion`, `escala_literal`;
    - notificaciones, alertas administrativas;
    - tokens de recuperación de contraseña y desafíos de captcha;
    - **entregas** del estudiante y **archivos** (entregas y material de apoyo);
  - **`usuario`:** `intentos_fallidos`, `bloqueado_hasta`, `debe_cambiar_password`;
  - **`seccion`:** admite "Única";
  - **`tipo_evaluacion`:** lista configurable (Práctica, Proyecto, Examen, Semanal, Parcial);
  - **`evaluacion`:** indicaciones, fecha de asignación y peso **relativo**, sin el límite de 8 por bimestre;
  - **`subperiodo` (bimestre):** **fecha de cierre de notas** para el cierre automático;
  - **`grado.nivel`:** solo PRIMARIA y SECUNDARIA.
- **Índices parciales:** los de Postgres se reemplazan por columnas generadas con índice único, más la validación en el backend:
  - un solo periodo en curso;
  - un solo apoderado principal;
  - un solo modelo activo.
- **Formato:** todas las tablas en `utf8mb4`; fechas y horas en hora de Lima (UTC−5, sin horario de verano). La API las envía con su desfase.
- **Seeders de demostración:**
  - el colegio con su nombre real, periodo con 4 bimestres, primaria y secundaria con secciones (algunos grados con sección "Única"), ~12 docentes, ~150 estudiantes **ficticios**, horarios;
  - historial de asistencia, notas y entregas **con patrones variados**, que sirven para los dashboards y luego para entrenar la IA;
  - **las fechas se generan relativas al día en que se ejecuta**: el periodo en curso siempre contiene "hoy". El sistema se ve vivo sin importar cuándo lo pruebe el profesor.
- **Exportes:** `docs/bd/ayni_mysql.sql` y el diagrama ER.

**Pruebas:**
- `migrate:fresh --seed` corre sin errores.
- Cada restricción funciona: únicos, CHECK, claves foráneas, matrícula única por año, periodo del aula consistente, notas entre 0 y 20.
- Las cantidades de datos sembrados coinciden con lo esperado.
- Sembrar con una fecha simulada distinta sigue dejando el periodo en curso correcto.

**Prueba tú mismo:**
1. Opción `14` (reiniciar la BD con datos por defecto).
2. En phpMyAdmin revisa las tablas y los datos.
3. Intenta insertar una nota de 25: la BD la rechaza.

---

## Módulo 3 — Sistema visual, landing y selector de ingreso
**Objetivo:** la identidad de AYNI aplicada a todo el sistema, en PC y celular.

- **Tema y recursos:** colores sacados de `palette.svg`, tipografías, íconos. Las imágenes de `NUBE RECURSOS` se convierten a **WebP** (de ~1,4 MB a ~150 KB).
- **Layouts por rol:**
  - barra lateral, barra superior con campana y menú de usuario, pie con "Periodo · Bimestre";
  - **versión celular**: menú lateral plegable y tablas que se convierten en tarjetas.
- **Componentes base:** tablas con búsqueda y paginación, formularios con errores, badges de estado, tarjetas KPI, diálogos de confirmación, notificaciones tipo *toast*, estados vacíos y de carga.
- **Páginas públicas:**
  - landing con las secciones "Descubre Ayni", "¿Qué es Ayni?", "Piki y Tambo" y "Contacto" (solo datos, sin formulario). **Los textos los redacto yo**;
  - "¿Cómo deseas ingresar?" con Piki y Tambo;
  - las animaciones (React Bits) **solo aquí** y cargadas de forma diferida.
- **Fidelidad a los mockups:** fiel en estructura, colores y contenido, con mejoras menores de usabilidad.
- **Errores:** páginas 404 y 403.

**Pruebas:**
- E2E de navegación landing → selector → login de cada rol.
- Vista en celular (375 px) sin scroll horizontal.
- Lighthouse ≥ 90 en rendimiento y accesibilidad.

**Prueba tú mismo:** abre la landing en la PC y en tu celular y compárala con los mockups.
- Desde el celular: misma red Wi-Fi y la URL del bloc.
- Windows puede pedir permiso en el firewall la primera vez.

---

## Módulo 4 — Autenticación y seguridad
**Objetivo:** login seguro para los 3 accesos: estudiante, docente (incluye coordinadores) y administración.

- **Login por rol:** con los fondos y bordes propios de cada rol (`extras/03-05`), ingresando con el usuario definido en D5.
- **Captcha propio:**
  - imagen con caracteres distorsionados y ruido, generada en el servidor;
  - botón para cambiarla;
  - de un solo uso y vence en 5 minutos;
  - si el hosting no tiene la extensión GD, se genera como SVG con trazos;
  - no depende de internet ni de servicios externos.
- **Protección del login:**
  - límite de intentos por IP;
  - **bloqueo tras 5 intentos fallidos**: estudiantes y docentes quedan bloqueados hasta que el admin los desbloquee;
  - **administradores con bloqueo temporal (15 min)**, para que nunca quede el sistema sin nadie que pueda desbloquear;
  - aviso por correo cuando una cuenta se bloquea.
- **Contraseñas:**
  - **docentes y admin:** mínimo 8 caracteres, con mayúscula, minúscula, número y símbolo;
  - **estudiantes:** mínimo 8 caracteres, con letras y números;
  - contraseña temporal con **cambio obligatorio** en el primer ingreso (no aplica a usuarios demo).
- **"¿Olvidaste tu contraseña?":**
  - envía un enlace por correo, de un solo uso, que vence en 30 minutos;
  - si el usuario no tiene correo registrado, el mensaje indica que se acerque al administrador;
  - la respuesta es igual exista o no la cuenta, para no revelar qué usuarios existen.
- **Sesión:** token de Sanctum, cierre de sesión, expiración tras **1 hora de inactividad**.
- **Rutas:** protegidas por rol en la API y en el front.
- **Registro:** guarda el último acceso.

**Pruebas:**
- Login correcto e incorrecto.
- Captcha incorrecto, vencido o reutilizado devuelve 422.
- Bloqueo al 5.º intento, y bloqueo temporal del admin que se libera solo.
- Recuperación de contraseña: el correo llega a la bandeja de prueba; el enlace vencido o usado no sirve.
- Un estudiante que entra a una ruta de admin recibe 403.
- Sesión inactiva más de 1 hora devuelve 401.
- Política de contraseñas distinta por rol.
- E2E: login y logout de cada rol.

**Prueba tú mismo:**
1. Entra con cada usuario del bloc, resolviendo el captcha.
2. Falla 5 veces con el estudiante: la cuenta queda bloqueada.
3. Usa "¿Olvidaste tu contraseña?" y abre el correo con la opción `13`.
4. Como estudiante, escribe a mano la URL del admin: debe salir 403.

**Despliegue de prueba temprano:** al cerrar este módulo subimos la landing y el login a `grupo02/` (con tu OK). Así detectamos a tiempo y no en el Módulo 14:
- versión de PHP;
- **extensiones de PHP** (pdo_mysql, mbstring, zip, gd, xml, fileinfo…) y **límite de subida de archivos**;
- motor y versión de la BD;
- **si el hosting permite enviar correo por el SMTP de Gmail** (algunos hostings lo bloquean);
- reglas de `.htaccess` en LiteSpeed;
- que `.env`, el código y `1. INICIO` **no** se puedan abrir desde el navegador.

La página de diagnóstico se borra al terminar. Si el profe aún no entrega la BD, subimos solo la landing.

**Manual:** inicio de sesión, captcha, recuperación de contraseña, cierre de sesión.

---

## Módulo 5 — Usuarios, perfil y administración de cuentas
Mockups: admin `02`, `04`; docente `02`; estudiante `02`.

- **Admin:**
  - lista de usuarios con filtros (rol, estado) y búsqueda;
  - crear, editar y activar/inactivar usuarios;
  - **desbloquear** cuentas y **generar contraseña temporal**, que además se envía por correo si el usuario tiene uno.
- **Mi perfil (todos los roles):** ver datos, editar celular y correo, cambiar contraseña.
- **Auditoría:** se registra quién creó o modificó cada usuario.

**Pruebas:**
- CRUD de usuarios y permisos (solo el admin).
- El nombre de usuario no se repite.
- El usuario desbloqueado vuelve a entrar.
- El correo con la contraseña temporal se envía.
- Validaciones: correo, celular peruano de 9 dígitos que empieza con 9, contraseña.

**Prueba tú mismo:** bloquea un estudiante desde el login, desbloquéalo como admin, revisa el correo en la opción `13` y entra con la contraseña temporal.

**Manual:** Administrador → Usuarios; Perfil.

---

## Módulo 6 — Estructura académica
Mockups: admin `05`, `06`, `07`, `12`.

- **Periodos y bimestres:**
  - fechas que no se cruzan, un solo periodo en curso, abrir y cerrar;
  - **fecha de cierre de notas de cada bimestre**: al llegar, el bimestre se **cierra solo** (ver M9).
  - Los docentes ven esa fecha y un aviso cuando faltan pocos días.
- **Grados, secciones y aulas:**
  - por cada grado se elige sección **"Única"** o varias (A, B, C…);
  - capacidad y tutor de cada aula.
- **Asignaturas:** código, horas semanales, estado.
- **Tipos de evaluación:** lista configurable.
- **Carga académica:** asignar un curso del aula a un docente y definir coordinadores con su ámbito (D1).
- **Horarios:** grilla semanal que **detecta cruces**: un docente o un aula no pueden tener dos clases a la misma hora.

**Pruebas:**
- Fechas inválidas son rechazadas, incluida una fecha de cierre anterior al inicio del bimestre o posterior al fin del periodo (RN-02).
- Un segundo periodo en curso es rechazado.
- Un grado no puede tener a la vez la sección "Única" y otras secciones.
- Un cruce de horario es rechazado.
- No se puede eliminar algo que ya está en uso.
- E2E: armar un aula completa con cursos y horario.

**Prueba tú mismo:** crea un aula de sección única, asígnale 3 cursos, intenta poner al mismo docente dos veces a las 8:00 del lunes y define la fecha de cierre del bimestre.

**Manual:** Administrador → Estructura académica.

---

## Módulo 7 — Docentes, estudiantes, apoderados, importación Excel y matrículas
Mockups: admin `08`, `09`, `10`, `11`.

- **Docentes y estudiantes:** CRUD con creación automática de su usuario. Datos de persona: DNI, CE o pasaporte, nombres, fecha de nacimiento, etc.
- **Apoderados:** varios por estudiante, uno solo principal.
- **Importación de estudiantes desde Excel**, con la plantilla del mockup 10:
  - plantilla descargable;
  - vista previa;
  - **reporte de errores por fila** (DNI repetido, fecha inválida, sección inexistente…), con descarga de las filas con error;
  - solo se importan las filas válidas;
  - opciones para matricular y para crear el usuario con contraseña temporal.
- **Matrículas:**
  - una por estudiante y año;
  - respeta la capacidad del aula;
  - retirar o anular indicando el motivo.

**Pruebas:**
- Validaciones de cada campo de persona: DNI de 8 dígitos, CE alfanumérico, nombres solo con letras, tildes y ñ, edad coherente con el grado.
- Importación con un archivo que mezcla filas válidas e inválidas.
- Rechazo de archivos que no son Excel.
- Doble matrícula rechazada.
- Aula llena rechazada.

**Prueba tú mismo:** descarga la plantilla, llénala con 1 fila errónea a propósito, impórtala y matricula a los nuevos estudiantes.

**Manual:** Administrador → Docentes, Estudiantes, Importar, Matrículas.

---

## Módulo 8 — Asistencia (por clase)
Mockups: docente `05`; estudiante `06`.

- **Docente:**
  - elige su curso y una fecha en que tenga clase según el horario;
  - registra P/F/T/J con "todos presentes" por defecto + excepciones;
  - agrega observaciones;
  - guarda borrador o guarda definitivo;
  - aviso de faltas acumuladas en la semana;
  - puede corregir **hasta las 11:59 p. m. del mismo día**.
- **Admin:** corrige registros pasados y justifica faltas, quedando registrado quién lo hizo.
- **Estudiante:** ve su % de asistencia, sus faltas y tardanzas por curso, y su calendario.
- **Indicadores:** % de asistencia por aula y aviso si un docente no registra (insumo del M11).

**Pruebas:**
- Solo el docente del curso puede registrar.
- No se registra en una fecha fuera del bimestre, en el futuro ni en un día sin clase de ese curso.
- El docente no puede editar pasada la medianoche; el admin sí.
- No se duplica el registro de una misma sesión.
- El % de asistencia se calcula bien.
- E2E: el docente registra y el estudiante lo ve.

**Prueba tú mismo:** como docente registra la asistencia de tu **última clase** con 2 faltas, entra como uno de esos estudiantes y verifica su %.

**Manual:** Docente → Asistencia; Estudiante → Mi asistencia; Administrador → corrección de asistencia.

---

## Módulo 9 — Evaluaciones, entregas, notas y calificaciones bimestrales
Mockups: docente `06`, `07`, `08`; estudiante `04`, `05`, `07`, `08`, `09`.

- **Docente — evaluaciones:**
  - crea evaluaciones con título, tipo, fecha de asignación, fecha límite, **peso relativo** (1, 2, 3…) e indicaciones;
  - sube **material de apoyo**.
- **Estudiante — entregas:**
  - sube su archivo (PDF hasta 10 MB), puede guardar borrador y entregar;
  - ve el historial de la actividad;
  - el estado (**entregado / tardío**) se calcula solo con la fecha límite, y el docente puede corregirlo;
  - los archivos se descargan solo con permiso.
- **Docente — notas:**
  - registra notas de 0 a 20 con el estado de entrega;
  - acepta decimales con punto o coma (`15.5` o `15,5`);
  - aviso de "cambios sin guardar";
  - exportar a Excel.
- **Calificaciones bimestrales:**
  - **promedio ponderado** automático por peso;
  - las evaluaciones **sin nota cuentan como 0**, así el promedio siempre se puede publicar; antes de publicar se muestra a quiénes se les pondrá 0;
  - el **docente publica** los promedios de su curso;
  - el **admin cierra el bimestre**, a mano o **automáticamente en la fecha de cierre**. Al cerrarse, lo pendiente se calcula con 0 y queda publicado y bloqueado.
  - El cierre se verifica cada vez que alguien usa el sistema, sin cron.
- **Estudiante:**
  - mis notas por curso, con el detalle del curso;
  - actividades pendientes y su detalle, con el peso mostrado en % del bimestre;
  - mi horario.
  - Solo ve las calificaciones **publicadas**.
- **Equivalencia literal** AD/A/B/C para mostrar (D4).

**Pruebas:**
- La nota 20,5, una nota negativa o con letras son rechazadas.
- El promedio ponderado coincide con cálculos hechos a mano (varios casos, incluidos ceros por no entregar).
- Entrega después de la fecha límite queda como tardía.
- Archivo que no es PDF, o que pesa más del límite, es rechazado.
- Un estudiante no puede descargar la entrega de otro.
- El cierre automático ocurre al pasar la fecha de cierre y bloquea los cambios.
- El estudiante no ve borradores.
- E2E: evaluación → entrega del estudiante → nota → publicar → el estudiante la ve.

**Prueba tú mismo:** crea la "Práctica 7", entrégala como estudiante con un PDF, ponle nota (prueba escribir "abc" y "25"), publica y entra como estudiante.

**Manual:** Docente → Evaluaciones, Notas, Calificaciones; Estudiante → Mis notas, Actividades, Horario; Administrador → cierre de bimestre.

> ### ★ Hito: aquí el sistema académico ya es funcional (lo mínimo de la Unidad 1)

---

## Módulo 10 — Incidencias y notificaciones
Mockups: admin `18`, `03`; docente `03`; estudiante `03`.

- **Incidencias:**
  - el docente o el admin registran tipo, gravedad y descripción;
  - historial por estudiante;
  - filtros.
- **Notificaciones dentro del sistema (campana):**
  - nueva nota publicada;
  - falta registrada;
  - actividad por vencer;
  - alerta asignada;
  - cierre de bimestre próximo (docentes);
  - con contador, marcar como leída y "ver todas".
- **Sin cron:**
  - las notificaciones por evento se crean en el momento (al publicar, al registrar);
  - las de "por vencer" se calculan al abrir la campana.

**Pruebas:**
- Los permisos de registro.
- Validaciones.
- Se genera la notificación correcta para el destinatario correcto, y nadie ve las notificaciones de otro.

**Prueba tú mismo:** registra una incidencia y publica una nota: el estudiante ve las 2 notificaciones.

**Manual:** Incidencias, Notificaciones (los 3 roles).

---

## Módulo 11 — Alertas y seguimiento (motor de reglas, **sin IA**)
Mockups: admin `13`, `14`, `15`, `16`; docente `09`, `10`, `12`.

- **Motor de reglas v0**, con los umbrales del M0 configurables en `institucion`:
  - Calcula el nivel BAJO/MEDIO/ALTO y **guarda qué reglas se activaron**: esa es la "explicación".
  - Agrega **recomendaciones de intervención** predefinidas por regla (por ejemplo, faltas frecuentes → contactar al apoderado).
  - Se guarda en las tablas de predicción con el modelo `reglas-v0`, así en la Unidad 2 solo se reemplaza por el modelo de ML.
- **Cuándo se ejecuta (sin cron):**
  - automáticamente al registrar asistencia o notas;
  - con un botón "Recalcular";
  - una vez al día, en el primer ingreso del día.
- **Alertas estudiantiles:**
  - asignación automática al tutor o coordinador del aula;
  - estados nueva → en seguimiento → cerrada o descartada, con justificación;
  - intervenciones e historial.
- **Alertas administrativas:**
  - usuarios bloqueados;
  - estudiantes sin matrícula;
  - docentes sin registrar asistencia;
  - cursos o aulas sin docente o tutor;
  - bimestre por cerrar con notas pendientes.
- **Paneles:** panel de riesgo (admin) y panel de coordinación según el ámbito del coordinador.

**Pruebas:**
- Cada regla con datos armados a propósito (estudiante con 30 % de faltas → ALTO).
- Las recomendaciones correctas para cada regla.
- Las transiciones de estado válidas e inválidas.
- El coordinador solo ve su ámbito.
- No se duplica la alerta de un mismo estudiante en el mismo bimestre.

**Prueba tú mismo:** pon varias faltas a un estudiante, pulsa "Recalcular" y sigue la alerta hasta cerrarla.

**Manual:** Alertas, Panel de riesgo, Coordinación.

---

## Módulo 12 — Dashboards de inicio, ficha 360° y reportes
Mockups: admin `01`, `17`; docente `01`, `04`, `11`; estudiante `01`.

- **Inicio de cada rol:** KPIs, "Necesitan su atención", avance de registro del bimestre, "Hoy" (horario), "Por entregar" y fecha de cierre del bimestre.
- **Ficha del estudiante 360°:** datos, notas, asistencia, entregas, incidencias, alertas y apoderados.
- **Mis estudiantes (docente):** lista con indicadores.
- **Reportes exportables:**
  - boleta de notas en PDF con **diseño propio** y el nombre del colegio;
  - asistencia por aula en Excel;
  - consolidado por bimestre.

**Pruebas:**
- Los KPIs coinciden con consultas directas a la BD.
- Cada rol ve solo lo suyo.
- El PDF y el Excel se generan y contienen los datos correctos.
- Ninguna pantalla hace consultas N+1.

**Prueba tú mismo:** compara los números del inicio con phpMyAdmin y descarga una boleta.

**Manual:** Navegación principal (inicio de cada rol), Reportes.

---

## Módulo 13 — Calidad y seguridad final
- **Auditoría completa de validaciones** contra `docs/validaciones.md`: cada campo de cada formulario, en el front y en el back.
- **Seguridad:**
  - intentos de inyección SQL y XSS;
  - CORS y cabeceras HTTP;
  - permisos cruzados: el docente A intenta editar notas del docente B;
  - subida de archivos maliciosos (importador y entregas).
- **Rendimiento:** tiempos de respuesta de la API < 300 ms con los datos demo, índices, paginación, bundle del front liviano.
- **Accesibilidad y celular:** todas las pantallas en celular y con teclado.
- **Prueba completa:** E2E del recorrido de un bimestre entero con todos los roles, hasta el cierre automático.
- **Matriz de trazabilidad:** cada RF con sus pruebas y resultado.

**Prueba tú mismo:** recorre la lista de verificación final (la armo en este módulo), que imita lo que revisará el profesor.

---

## Módulo 14 — Publicación en el hosting (web + descarga)
**Objetivo:** que en `grupo02/` funcione la web **y** esté el proyecto completo para que el profe lo descargue y lo use en local.

- **Qué hay en `grupo02/` (FTP):** el **mismo proyecto que tienes en local**, con `1. INICIO/` arriba para que el profe lo vea primero. Además:
  - el build de React y `vendor/` de Laravel ya preparados, porque no hay SSH;
  - la configuración del servidor (BD del hosting, SMTP, `APP_DEBUG=false`).
- **Qué NO se sube:** `CREDENCIALES_PRIVADAS.txt`, `.git/`, `node_modules/`, configuración local, respaldos.
- **`.htaccess` de seguridad y rutas:**
  - `grupo02/` muestra la web y `grupo02/api/` responde la API;
  - recargar cualquier pantalla no da 404;
  - **el código, `.env`, `vendor/`, `storage/`, `1. INICIO/` y los manuales NO se abren desde el navegador**; solo se descargan por FTP;
  - sin listado de carpetas;
  - redirección a HTTPS.
- **`publicar-hosting.ps1`:**
  - sube por **FTPS** solo los archivos que cambiaron desde la última vez;
  - antes de subir muestra el resumen y pide tu OK;
  - al terminar ejecuta el *smoke test*.
- **BD del hosting:** el profe la crea; nosotros le pasamos el `.sql` o lo importamos por phpMyAdmin.
- **Plan B:** VPS propio con el mismo Docker Compose, si el hosting no cumple algún requisito detectado en el M4.

**Pruebas:**
- *Smoke test* de Playwright contra la URL pública: login de cada rol y recorrido de **solo lectura** por cada módulo, para no ensuciar los datos.
- `.env`, `vendor/`, `storage/` y `1. INICIO/` responden 403 desde el navegador.
- Al forzar un error no se muestran detalles técnicos.
- **Descarga completa por FTP en una carpeta nueva + instalación con `AYNI.bat`**: funciona en local sin tocar nada.

**Prueba tú mismo:**
1. Entra a la URL del hosting desde tu celular con datos móviles.
2. Intenta abrir `/grupo02/1. INICIO/` en el navegador: debe salir prohibido.
3. Descarga `grupo02/` con FileZilla e instálalo con `AYNI.bat`.

**Manual:** sección "Uso en la web" y "Uso en local descargando desde el FTP".

---

## Módulo 15 — Manuales finales
Se guardan en **`1. INICIO/MANUAL/`**, en **Word y PDF**. Los Word se borran en la entrega final.

- **Manual de usuario.** Se arma con lo que dejó cada módulo, con capturas en PC y celular actualizadas automáticamente:
  1. Requisitos de acceso (web y local);
  2. Inicio de sesión, captcha y recuperación de contraseña;
  3. Navegación principal;
  4. Funciones por rol (Administrador, Coordinador, Docente, Estudiante);
  5. Cierre de sesión;
  6. Solución de problemas frecuentes.
- **Manual de instalación:**
  - uso en la **web** (URL y usuarios);
  - instalación **local en Windows** con `AYNI.bat`, explicando cada opción del menú;
  - instalación **local en Linux** con `AYNI.sh`;
  - alternativa sin Docker (solo documentada);
  - respaldo y restauración;
  - aviso de "Windows protegió su PC";
  - preguntas frecuentes.
- **`README.md` de la raíz:** "Para iniciar, ejecuta `1. INICIO/AYNI.bat` (Windows) o `1. INICIO/AYNI.sh` (Linux). El sistema está explicado en `1. INICIO/MANUAL/Manual de usuario.pdf`".
- **Demo:** guion de presentación de 10 minutos y datos demo listos.

**Terminado cuando:** ambos manuales están completos, coinciden con el sistema publicado y se abren desde la opción `8`.

---

## Informe — **en pausa**

No se trabaja hasta que lo indiques. Cuando lo retomes, ya tendremos los insumos:
- `docs/requisitos.md` (actores, casos de uso, RF/RNF);
- `docs/bd/` (modelo y diccionario de datos);
- reportes de pruebas y matriz de trazabilidad;
- capturas;
- fechas reales de cada módulo para el cronograma.

**Pendientes anotados para entonces:**
- Alinear la problemática con la detección de riesgo académico.
- Declarar en "Limitaciones" la versión Laravel 10 / PHP 8.1, la asistencia solo por clase y los padres sin acceso.
- Revisar los nombres de la portada: allí dice "Blarezo" y "Luciano"; en la lista del grupo, "Balarezo" y "Lucciano".

---

## Después de la Unidad 1 (solo bosquejo)

**Unidad 2 — IA:**
- Servicio aparte en **Python + FastAPI** con scikit-learn y SHAP.
- Lee los datos académicos y escribe en `prediccion_riesgo`, `factor_riesgo` y `recomendacion`.
- Reemplaza `reglas-v0` sin tocar las pantallas, porque ya muestran nivel, factores y recomendaciones.

**Unidad 3 — GCP:**

| Pieza | Destino en GCP |
|---|---|
| API Laravel | Cloud Run (contenedor) |
| Servicio de IA | Cloud Run |
| BD | Cloud SQL for MySQL |
| Archivos (entregas, material) | Cloud Storage |
| Frontend | Firebase Hosting o Cloud Storage + CDN |
| Credenciales | Secret Manager |
| Despliegue automático | Cloud Build |

- En GCP nosotros elegimos la versión de PHP, así que ahí se puede actualizar a Laravel 12 (10 → 11 → 12) si conviene.
- Lo dejamos preparado desde la Unidad 1: sin estado local, archivos con `Storage::disk()`, configuración por `.env` y logs a la salida estándar.

---

## Resumen de avance

| Módulo | Contenido | Estado |
|---|---|---|
| M0 | Requisitos y preparación | ✅ aprobado |
| M1 | Esqueleto + panel (Windows y Linux) + credenciales | ✅ aprobado |
| M2 | Base de datos MySQL + datos demo | ✅ aprobado |
| M3 | Sistema visual + landing + selector | ✅ aprobado |
| M4 | Autenticación, captcha propio, recuperación + despliegue de prueba | ✅ aprobado (despliegue de prueba pendiente) |
| M5 | Usuarios y perfil | ✅ aprobado |
| M6 | Estructura académica + fechas de cierre | ✅ aprobado |
| M7 | Personas, importación Excel y matrículas | ✅ aprobado |
| M8 | Asistencia por clase | ✅ aprobado |
| M9 | Evaluaciones, entregas y notas ★ | ✅ aprobado |
| M10 | Incidencias y notificaciones | ✅ aprobado |
| M11 | Alertas por reglas (sin IA) | ✅ aprobado |
| M12 | Dashboards, ficha 360° y reportes | ✅ aprobado |
| M13 | Calidad y seguridad final | ✅ aprobado |
| M14 | Publicación en el hosting (web + descarga) | ✅ aprobado |
| M15 | Manuales (usuario e instalación) | ⬜ |
| — | Informe | ⏸ en pausa |
