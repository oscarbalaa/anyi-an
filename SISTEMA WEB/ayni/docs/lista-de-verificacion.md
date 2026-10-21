# AYNI — Lista de verificación final

> Módulo 13. Imita lo que revisará el profesor: se recorre de arriba abajo, en una PC con el sistema recién instalado, y cada punto se marca cuando se ve el resultado esperado. Tiempo aproximado: 60 a 90 minutos.
> Lo que se comprueba aquí a mano también lo comprueban las pruebas automáticas (opción 10 del panel); esta lista sirve para verlo con los propios ojos.

Cuentas: las de `1. INICIO/CREDENCIALES.txt` (`cmedina` / `Ayni#2026`, `41234567` / `Ayni#2026`, `42345678` / `Ayni#2026`, `E-2026-001` / `piki2026`).

## 1. Instalación y panel

- [ ] Doble clic en `1. INICIO/AYNI.bat` (o `./AYNI.sh` en Linux): el panel abre y la opción **Verificar** marca todo en verde.
- [ ] **Instalar** termina sin errores y abre el sistema en el navegador.
- [ ] **Pausar** y **Encender**: los datos siguen ahí.
- [ ] **Crear respaldo**: aparece un `.sql` con fecha en `respaldos/`. Cambia algo en el sistema y **Restaurar**: el cambio desaparece.
- [ ] **Pruebas (opción 10)**: el reporte HTML queda todo en verde (backend, frontend, punta a punta y Lighthouse).
- [ ] `1. INICIO/CREDENCIALES.txt` tiene las direcciones y los usuarios de demostración.

## 2. Página pública e ingreso

- [ ] La portada muestra las cuatro secciones y se ve bien en el celular (desde la dirección Wi-Fi de las credenciales).
- [ ] **Ingresar** → ¿Cómo deseas ingresar? → estudiante y docente; **Administración** abre el acceso administrativo.
- [ ] Un DNI con letras no se puede escribir; un código mal escrito muestra "El código tiene el formato E-AAAA-NNN".
- [ ] Una contraseña incorrecta dice "Usuario o contraseña incorrectos" (sin decir cuál falló).
- [ ] Cinco intentos fallidos bloquean la cuenta; la administración lo ve en **Alertas administrativas** y la desbloquea en **Usuarios**.
- [ ] **¿Olvidaste tu contraseña?** → el correo llega a **Correos de prueba** (`http://localhost:8025`) y el enlace sirve una sola vez.
- [ ] Tras 1 hora sin usar el sistema, pide ingresar de nuevo.

## 3. Administración (`cmedina`)

- [ ] **Inicio**: los números coinciden con phpMyAdmin (ver el manual, sección "Prueba tú mismo" de Inicio y reportes).
- [ ] **Estructura académica**: crear un aula con cursos y horario; un cruce de horario del mismo docente se rechaza (RN-05).
- [ ] **Estudiantes → Importar Excel**: la plantilla se descarga; un archivo con una fila errónea explica el error por fila e importa solo las válidas.
- [ ] **Matrículas**: no deja matricular dos veces al mismo estudiante en el periodo ni pasar la capacidad del aula (RN-04).
- [ ] **Asistencia**: la corrección de una clase pasada pide motivo.
- [ ] **Panel de riesgo** y **Alertas**: se ve la distribución por aula; **Recalcular** avisa cuántos estudiantes evaluó.
- [ ] **Reportes**: el consolidado y la asistencia se abren en Excel con los datos del aula; la ficha 360° de un estudiante descarga su boleta en PDF con el nombre del colegio.
- [ ] **Usuarios**: crear una cuenta administrativa; el correo con su contraseña temporal llega a la bandeja de prueba.

## 4. Docente (`41234567`, coordina 3.º A)

- [ ] **Inicio**: lista lo pendiente (asistencia sin registrar, notas por poner, alertas) y la coordinación de su ámbito.
- [ ] **Asistencia**: todos aparecen presentes; marcar una falta y guardar como definitiva; el estudiante recibe el aviso.
- [ ] **Evaluaciones**: crear una práctica con entrega; fechas fuera del bimestre muestran el error junto al campo.
- [ ] **Notas**: escribir `15,5` funciona; `20,5` o `abc` se rechazan con su mensaje (RN-08).
- [ ] **Calificaciones → Publicar promedios**: el estudiante recibe el aviso y ya ve su promedio (RN-13).
- [ ] **Mis estudiantes → Ver ficha**: la ficha 360° muestra notas, asistencia, riesgo, incidencias y apoderados.
- [ ] Cambiar en la URL el número de un curso o evaluación por el de otro docente: la pantalla no muestra sus datos (en F12 → Red, la API responde 403).

## 5. Tutora (`42345678`, tutora de 3.º B)

- [ ] **Alertas asignadas**: abrir una, pasarla a seguimiento, registrar una intervención y cerrarla con justificación.
- [ ] Una alerta nueva no se puede cerrar directamente (primero a seguimiento).
- [ ] **Incidencias**: registrar una incidencia grave; la administración recibe el aviso.

## 6. Estudiante (`E-2026-001`)

- [ ] **Inicio**: promedio publicado, asistencia, lo que debe entregar y las clases de hoy.
- [ ] **Actividades**: entregar un PDF; un `.exe` renombrado a `.pdf` se rechaza (RN-26).
- [ ] **Mis notas**: solo aparecen los promedios publicados; **Descargar mi boleta** baja el PDF.
- [ ] **Notificaciones**: la campana muestra los avisos sin leer y se marcan como leídos.

## 7. Seguridad (lo que un profesor intentaría)

- [ ] Abrir `http://localhost:5173/.env`, `/backend/`, `/1. INICIO/CREDENCIALES_PRIVADAS.txt`: ninguno muestra contenido del proyecto.
- [ ] Escribir `' OR '1'='1` en cualquier buscador: no muestra todos los registros ni da error.
- [ ] Registrar una incidencia con `<script>alert(1)</script>` en la descripción: se ve como texto y no aparece ninguna ventana.
- [ ] Con la sesión de estudiante, abrir `/admin` o `/docente`: muestra "No tienes permiso" o vuelve a su inicio.
- [ ] En las herramientas del navegador (F12 → Red), las respuestas de `/api` llevan `X-Frame-Options`, `X-Content-Type-Options` y `Content-Security-Policy`.

## 8. Celular y accesibilidad

- [ ] En el celular, cada pantalla del menú se ve sin desplazamiento horizontal y el menú se abre con el botón ☰.
- [ ] Con el teclado (Tab), "Saltar al contenido" aparece primero y cada enlace muestra un contorno al recibir el foco.
- [ ] Las tablas largas se ven como tarjetas en el celular y conservan la búsqueda y la paginación.

## 9. Documentación

- [ ] `docs/requisitos.md`, `docs/validaciones.md`, `docs/seguridad.md` y `docs/trazabilidad.md` están al día (la CI revisa la matriz de trazabilidad).
- [ ] `1. INICIO/MANUAL` tiene el manual de usuario y el de instalación en Word y PDF (opción `8` del panel), con capturas de PC y celular.
