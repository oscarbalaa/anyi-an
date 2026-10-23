# AYNI — Guion de la demostración (10 minutos)

> Módulo 15. Recorrido para presentar AYNI con los datos de demostración. Cada bloque dice quién habla, qué se muestra y qué frase lo cierra.

## Antes de empezar (5 minutos antes)

- [ ] AYNI encendido (opción `3`) **o** la versión web abierta: https://ayni.ruta-practica.com/
- [ ] Datos de demostración recién cargados (opción `14` en local): así las fechas, alertas y pendientes son los de hoy.
- [ ] Tres ventanas listas: una normal y dos de incógnito (para tener administración, docente y estudiante abiertos a la vez).
- [ ] Un celular en la misma Wi-Fi con la dirección "Desde un celular" de `CREDENCIALES.txt` (o la web con datos móviles).
- [ ] Un PDF cualquiera en el escritorio (para la entrega del estudiante).

| Rol | Usuario | Contraseña |
|---|---|---|
| Administrador | `cmedina` | `Ayni#2026` |
| Docente que coordina el 3.º A | `41234567` | `Ayni#2026` |
| Tutora del 3.º B | `42345678` | `Ayni#2026` |
| Estudiante (3.º B) | `E-2026-001` | `piki2026` |

## Recorrido

| Minuto | Bloque | Qué se muestra |
|---|---|---|
| 0:00 – 1:00 | **El problema** | La portada: AYNI junta asistencia, notas e incidencias para **detectar a tiempo** al estudiante en riesgo, en lugar de enterarse al cerrar el bimestre. |
| 1:00 – 2:00 | **Ingreso seguro** | Ingresar → ¿Cómo deseas ingresar? → Docente. Captcha propio, mensaje genérico ante una contraseña incorrecta, bloqueo a los 5 intentos y recuperación por correo. |
| 2:00 – 4:00 | **Docente** (`41234567`) | **Inicio** con lo pendiente del día. **Asistencia**: marcar una falta y guardar como definitiva. **Notas**: escribir `15,5` (se acepta) y `20,5` (se rechaza con su mensaje). **Calificaciones → Publicar promedios**. |
| 4:00 – 5:30 | **Estudiante** (`E-2026-001`, en la otra ventana) | La campana ya trae el aviso de la falta y de los promedios publicados. **Mis notas**, **Mi asistencia**, **Actividades** (entregar el PDF) y **Descargar mi boleta**. |
| 5:30 – 7:30 | **Administración** (`cmedina`) | **Panel de riesgo**: distribución por aula y nivel. Abrir una **alerta** del 3.º B (van a su tutora, `42345678`) y la **ficha 360°** del estudiante (notas, asistencia, incidencias, factores del riesgo y recomendación). **Reportes**: consolidado en Excel. |
| 7:30 – 8:30 | **Seguimiento** (`42345678`) | **Alertas asignadas**: pasar una alerta a seguimiento, registrar la intervención y cerrarla con justificación. |
| 8:30 – 9:15 | **Celular** | La misma pantalla en el celular: menú ☰, tablas como tarjetas, sin desplazamiento horizontal. |
| 9:15 – 10:00 | **Cómo se instala y se prueba** | El panel (`AYNI.bat`): opción `1` instala todo, `10` ejecuta las pruebas automáticas (backend, frontend, recorridos en PC y celular) y `8` abre los manuales. Cierre: *"AYNI está en la web y se instala en cualquier PC con un doble clic."* |

## Si algo falla durante la demo

| Pasa | Qué hacer |
|---|---|
| Una cuenta quedó bloqueada | Usar otra cuenta de la tabla; en local, la opción `14` restaura los datos (1 minuto) |
| La web no responde | Seguir con la versión local (o al revés) |
| La alerta o el pendiente ya no aparece (se cerró en un ensayo) | Opción `14` antes de empezar, o elegir otra alerta del panel de riesgo |
| El celular no abre la dirección local | Mostrar la vista de celular del navegador (F12 → icono de dispositivo) |

## Datos de demostración que conviene conocer

- **Colegio:** IEP El Señor es mi Pastor; 12 aulas, 15 docentes y unos 150 estudiantes ficticios.
- **Fechas:** se calculan desde el día de la instalación: siempre hay un bimestre en curso y los anteriores cerrados.
- **Riesgo:** el riesgo se calcula al cargar los datos (bimestre cerrado y bimestre en curso), así que el panel de riesgo y las alertas ya tienen casos abiertos para mostrar.
- **Pendientes del docente:** el docente `41234567` siempre tiene sin registrar la asistencia de su última clase de cada curso.
- **Para la administración:** cuatro estudiantes importados sin matrícula, dos cuentas de estudiantes bloqueadas y dos docentes que dejaron de registrar asistencia (aparecen en **Alertas administrativas**).
