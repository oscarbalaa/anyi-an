# AYNI — Especificación de requisitos (Unidad 1)

| | |
|---|---|
| **Proyecto** | AYNI — Plataforma web de gestión académica y seguimiento del estudiante |
| **Institución caso** | IEP El Señor es mi Pastor (Nuevo Chimbote) |
| **Equipo** | Grupo 02 — Curso Aplicaciones en la Nube |
| **Versión** | 1.0 — 16/10/2026 |
| **Estado** | Aprobado (M0) |

---

## 1. Propósito y alcance

AYNI administra la información académica de un colegio de primaria y secundaria y la usa para detectar a tiempo a los estudiantes que necesitan acompañamiento.

**Incluido en la Unidad 1:**
- usuarios y seguridad;
- estructura académica;
- docentes, estudiantes y matrículas;
- asistencia por clase;
- evaluaciones con entregas;
- calificaciones;
- incidencias y notificaciones;
- alertas calculadas con **reglas** (sin IA);
- dashboards y reportes.

Funciona en la web (hosting del curso) y en local (panel `AYNI.bat` / `AYNI.sh`).

**Fuera de alcance de la Unidad 1:**

| Tema | Cuándo |
|---|---|
| Predicción con Machine Learning y explicabilidad con SHAP | Unidad 2 |
| Despliegue en Google Cloud Platform | Unidad 3 |
| Portal de padres de familia (los apoderados se registran como datos del estudiante) | Trabajo futuro |
| Registro de ingreso y salida del colegio (la asistencia es por clase) | Trabajo futuro |
| Simulacros de admisión y puntajes brutos | Trabajo futuro |
| Nivel inicial | Trabajo futuro |
| Varios colegios en una misma instalación (multi-colegio completo) | Trabajo futuro |
| Aplicación móvil nativa (la web se adapta al celular) | No previsto |

---

## 2. Actores

| Actor | Descripción | Cómo ingresa |
|---|---|---|
| **Visitante** | Persona sin sesión. Ve la landing y el selector de ingreso | — |
| **Administrador** | Personal administrativo que configura el colegio, gestiona usuarios, personas, matrículas y cierres | Acceso "Administración" con nombre de usuario |
| **Coordinador académico** | Docente con un ámbito de coordinación: un aula, un grado/nivel o toda la institución. Además de lo del docente, ve el panel y las alertas de su ámbito | Acceso de docente con su DNI |
| **Docente** | Registra asistencia, evaluaciones y notas de sus cursos; atiende las alertas que se le asignan | Acceso de docente con su DNI |
| **Estudiante** | Consulta sus notas, asistencia, horario y actividades, y entrega sus trabajos | Acceso de estudiante con su código |
| **Sistema** | Procesos automáticos: cálculo de riesgo, cierre de bimestre por fecha, notificaciones, correos | — |

---

## 3. Decisiones adoptadas

| # | Tema | Decisión |
|---|---|---|
| D1 | Coordinador académico | Docente con ámbito de coordinación: aula, grado/nivel o institución |
| D2 | Apoderados | Solo como datos del estudiante, sin acceso al sistema |
| D3 | Colegio | Nombre real "IEP El Señor es mi Pastor". Las personas de los datos demo son **ficticias** |
| D4 | Escala | Vigesimal 0–20, con equivalencia literal AD/A/B/C solo para mostrar |
| D5 | Usuario de ingreso | Estudiante: código. Docente: DNI. Administrador: nombre de usuario |
| D6 | Niveles | Primaria (1.º–6.º) y secundaria (1.º–5.º). Sección "Única" o A, B, C… por grado |
| D7 | Asistencia | Por clase (curso y fecha) |
| D8 | Correo | SMTP de Gmail (cuenta del proyecto). En local, bandeja de prueba |
| D9 | Captcha | Propio, generado por el servidor, sin servicios externos |

---

## 4. Requisitos funcionales

**Cómo leer las tablas:**
- **Prioridad:** **A** = imprescindible para la Unidad 1; **M** = importante, se hace después del hito del M9.
- **Módulo:** el módulo del plan en que se construye.

### 4.1 Acceso público (M3)

| Código | Requisito | Actor | Prioridad |
|---|---|---|---|
| RF-01 | Mostrar la landing con las secciones "Descubre Ayni", "¿Qué es Ayni?", "Piki y Tambo" y "Contacto" (datos de contacto, sin formulario) | Visitante | M |
| RF-02 | Mostrar el selector "¿Cómo deseas ingresar?" con acceso de estudiante, de docente y el enlace "Administración" | Visitante | A |

### 4.2 Autenticación y seguridad (M4)

| Código | Requisito | Actor | Prioridad |
|---|---|---|---|
| RF-03 | Iniciar sesión con usuario (según D5), contraseña y captcha, en la pantalla propia de cada acceso | Todos | A |
| RF-04 | Generar un captcha propio en imagen, con botón para cambiarlo; cada captcha sirve una sola vez y vence a los 5 minutos | Sistema | A |
| RF-05 | Bloquear la cuenta tras 5 intentos fallidos. Estudiantes y docentes: hasta que el admin desbloquee. Administradores: 15 minutos. Avisar por correo si el usuario tiene uno | Sistema | A |
| RF-06 | Recuperar la contraseña con un enlace enviado al correo registrado, de un solo uso y válido 30 minutos | Todos | M |
| RF-07 | Obligar a cambiar la contraseña temporal en el primer ingreso | Todos | A |
| RF-08 | Cerrar sesión a pedido y cerrarla automáticamente tras 1 hora de inactividad | Todos | A |
| RF-09 | Restringir cada pantalla y cada operación según el rol y el ámbito del usuario | Sistema | A |
| RF-10 | Registrar la fecha y hora del último acceso | Sistema | M |

### 4.3 Usuarios y perfil (M5)

| Código | Requisito | Actor | Prioridad |
|---|---|---|---|
| RF-11 | Listar usuarios con búsqueda, filtros por rol y estado, y paginación | Administrador | A |
| RF-12 | Crear, editar, activar e inactivar usuarios | Administrador | A |
| RF-13 | Desbloquear cuentas y generar contraseña temporal, enviándola por correo si el usuario tiene uno | Administrador | A |
| RF-14 | Ver los datos de mi perfil y editar mi celular y correo | Todos | M |
| RF-15 | Cambiar mi contraseña indicando la actual | Todos | A |
| RF-16 | Registrar quién y cuándo creó o modificó cada usuario | Sistema | M |

### 4.4 Estructura académica y configuración (M6)

| Código | Requisito | Actor | Prioridad |
|---|---|---|---|
| RF-17 | Gestionar periodos académicos (planificado, en curso, cerrado) | Administrador | A |
| RF-18 | Gestionar los bimestres de cada periodo con sus fechas y su **fecha de cierre de notas** | Administrador | A |
| RF-19 | Gestionar grados de primaria y secundaria y sus secciones ("Única" o A, B, C…) | Administrador | A |
| RF-20 | Gestionar aulas (grado + sección + periodo), con capacidad y tutor | Administrador | A |
| RF-21 | Gestionar asignaturas (código, nombre, horas semanales, estado) | Administrador | A |
| RF-22 | Gestionar los tipos de evaluación (Práctica, Proyecto, Examen, Semanal, Parcial…) | Administrador | M |
| RF-23 | Asignar cada curso de un aula a un docente (carga académica) | Administrador | A |
| RF-24 | Designar coordinadores académicos y su ámbito | Administrador | A |
| RF-25 | Gestionar el horario semanal de cada aula, impidiendo cruces de docente y de aula | Administrador | A |
| RF-26 | Configurar los datos del colegio: nombre, nota aprobatoria, umbrales de riesgo y equivalencias literales | Administrador | M |

### 4.5 Personas y matrículas (M7)

| Código | Requisito | Actor | Prioridad |
|---|---|---|---|
| RF-27 | Gestionar docentes, creando su usuario automáticamente | Administrador | A |
| RF-28 | Gestionar estudiantes, creando su usuario y su código automáticamente | Administrador | A |
| RF-29 | Gestionar los apoderados de cada estudiante, con uno solo como principal | Administrador | A |
| RF-30 | Importar estudiantes desde Excel: plantilla, vista previa, errores por fila, descarga de filas con error, e importar solo las válidas (opcionalmente matriculándolos y creando su usuario) | Administrador | A |
| RF-31 | Matricular estudiantes en un aula del periodo | Administrador | A |
| RF-32 | Retirar o anular una matrícula indicando el motivo | Administrador | M |

### 4.6 Asistencia (M8)

| Código | Requisito | Actor | Prioridad |
|---|---|---|---|
| RF-33 | Registrar la asistencia de una clase (P/F/T/J), con todos presentes por defecto, observación por estudiante, y opción de guardar borrador o guardar definitivo | Docente | A |
| RF-34 | Corregir la asistencia registrada hasta las 11:59 p. m. del mismo día | Docente | A |
| RF-35 | Corregir asistencias pasadas y justificar faltas, dejando registro de quién lo hizo | Administrador | A |
| RF-36 | Consultar mi asistencia: porcentaje, faltas y tardanzas por curso, y calendario | Estudiante | A |
| RF-37 | Mostrar al docente un aviso cuando un estudiante acumula faltas en la semana | Sistema | M |

### 4.7 Evaluaciones, entregas y calificaciones (M9)

| Código | Requisito | Actor | Prioridad |
|---|---|---|---|
| RF-38 | Crear y editar evaluaciones: título, tipo, fecha de asignación, fecha límite, peso relativo e indicaciones | Docente | A |
| RF-39 | Adjuntar material de apoyo a una evaluación | Docente | M |
| RF-40 | Consultar mis actividades (pendientes y entregadas) y su detalle, con el peso en % del bimestre | Estudiante | A |
| RF-41 | Subir mi entrega (PDF), guardar borrador, entregar y ver el historial de la actividad | Estudiante | A |
| RF-42 | Calcular el estado de entrega (entregado, tardío, no entregado) según la fecha límite, permitiendo que el docente lo corrija | Sistema / Docente | A |
| RF-43 | Registrar notas de 0 a 20 por evaluación, con aviso de cambios sin guardar, y exportarlas a Excel | Docente | A |
| RF-44 | Calcular el promedio ponderado de cada bimestre por curso | Sistema | A |
| RF-45 | Publicar los promedios del curso, mostrando antes a quiénes se les asignará 0 por evaluaciones sin nota | Docente | A |
| RF-46 | Cerrar un bimestre manualmente, y cerrarlo automáticamente al llegar su fecha de cierre | Administrador / Sistema | A |
| RF-47 | Consultar mis notas publicadas por curso y su detalle, con la equivalencia literal | Estudiante | A |
| RF-48 | Consultar mi horario semanal | Estudiante | A |

### 4.8 Incidencias y notificaciones (M10)

| Código | Requisito | Actor | Prioridad |
|---|---|---|---|
| RF-49 | Registrar incidencias de un estudiante: tipo, gravedad, fecha y descripción | Docente / Administrador | A |
| RF-50 | Consultar y filtrar el historial de incidencias | Docente / Administrador | M |
| RF-51 | Notificar dentro del sistema (campana) los siguientes eventos, con contador y marca de leída: nota publicada, falta registrada, actividad por vencer, alerta asignada y cierre de bimestre próximo | Sistema | M |

### 4.9 Alertas y seguimiento — reglas, sin IA (M11)

| Código | Requisito | Actor | Prioridad |
|---|---|---|---|
| RF-52 | Calcular el nivel de riesgo (BAJO, MEDIO, ALTO) de cada estudiante con reglas, guardando qué reglas se activaron | Sistema | A |
| RF-53 | Generar recomendaciones de intervención según las reglas activadas | Sistema | M |
| RF-54 | Crear alertas para los estudiantes en riesgo MEDIO o ALTO y asignarlas a un responsable | Sistema | A |
| RF-55 | Gestionar la alerta: cambiar su estado con justificación, registrar intervenciones y ver su historial | Docente / Coordinador / Administrador | A |
| RF-56 | Generar alertas administrativas: usuarios bloqueados, estudiantes sin matrícula, docentes sin registrar asistencia, cursos o aulas sin docente o tutor, y bimestre por cerrar con notas pendientes | Sistema | M |
| RF-57 | Mostrar el panel de riesgo del colegio | Administrador | A |
| RF-58 | Mostrar el panel de coordinación de su ámbito | Coordinador | A |

### 4.10 Dashboards y reportes (M12)

| Código | Requisito | Actor | Prioridad |
|---|---|---|---|
| RF-59 | Mostrar el inicio de cada rol con indicadores, pendientes, horario del día y fecha de cierre del bimestre | Todos | A |
| RF-60 | Mostrar la ficha 360° del estudiante: datos, notas, asistencia, entregas, incidencias, alertas y apoderados | Docente / Coordinador / Administrador | A |
| RF-61 | Listar mis estudiantes con sus indicadores | Docente | M |
| RF-62 | Generar la boleta de notas en PDF | Administrador / Estudiante | M |
| RF-63 | Exportar la asistencia por aula a Excel | Administrador / Docente | M |
| RF-64 | Exportar el consolidado de notas por bimestre | Administrador | M |

### 4.11 Instalación y operación (M1, M14)

| Código | Requisito | Actor | Prioridad |
|---|---|---|---|
| RF-65 | Instalar, verificar, encender, pausar y reiniciar el sistema local desde un panel con menú (`AYNI.bat` en Windows, `AYNI.sh` en Linux) | Profesor / Equipo | A |
| RF-66 | Crear y restaurar respaldos de la base de datos desde el panel | Profesor / Equipo | M |
| RF-67 | Cargar datos de demostración con fechas relativas al día de instalación | Sistema | A |
| RF-68 | Mostrar en local los correos que el sistema enviaría (bandeja de prueba) | Profesor / Equipo | M |

---

## 5. Requisitos no funcionales

| Código | Categoría | Requisito |
|---|---|---|
| RNF-01 | Compatibilidad (hosting) | Funciona en el hosting del curso: **PHP 8.1**, MySQL 8 o MariaDB 10.6+, solo FTP, **sin SSH, sin cron y sin procesos en segundo plano** |
| RNF-02 | Portabilidad (local) | Se instala en Windows 10 22H2+/11 y en Linux usando solo Docker; la PC no necesita PHP, Node ni MySQL |
| RNF-03 | Compatibilidad (clientes) | Funciona en las 2 últimas versiones de Chrome, Edge, Firefox y Safari, y se adapta a pantallas desde 360 px de ancho |
| RNF-04 | Rendimiento | Con los datos demo: la API responde en < 300 ms (p95), la carga inicial en 4G es < 3 s y Lighthouse da ≥ 90 en rendimiento |
| RNF-05 | Seguridad | Contraseñas con hash bcrypt; HTTPS en la web; token por sesión; validación en front **y** back; protección contra inyección SQL, XSS y CSRF; cabeceras de seguridad; código, configuración y archivos subidos inaccesibles desde el navegador |
| RNF-06 | Privacidad | Los datos personales de menores solo los ve quien los necesita según su rol; los datos demo usan personas ficticias; se aplican los principios de la Ley 29733 de protección de datos personales |
| RNF-07 | Usabilidad | Interfaz en español, mensajes de error claros junto a cada campo, confirmación antes de acciones destructivas y accesibilidad básica (contraste, teclado, etiquetas) |
| RNF-08 | Mantenibilidad | Código por capas, formateado automáticamente (Pint, ESLint, Prettier), con pruebas automáticas y ≥ 80 % de cobertura en la lógica de negocio |
| RNF-09 | Auditoría | Se registra quién y cuándo modificó usuarios, asistencia, notas, cierres y alertas |
| RNF-10 | Datos | Codificación `utf8mb4` (ñ y tildes). Fechas y horas en hora de Lima (UTC−5, sin horario de verano); la API las envía en ISO 8601 con su desfase (`-05:00`) |
| RNF-11 | Preparación para la nube | Aplicación sin estado local: configuración por variables de entorno, archivos a través de un disco configurable y logs por salida estándar (para la Unidad 3) |
| RNF-12 | Correo | Si el envío de correo falla, la operación principal no se interrumpe y el error queda registrado |
| RNF-13 | Identidad visual | Respeta los mockups, la paleta (`palette.svg`), los logos y las mascotas Piki y Tambo |
| RNF-14 | Calidad de entradas | Todo campo valida tipo, formato, longitud y rango según `docs/validaciones.md` |

---

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RN-01 | Solo puede haber **un periodo académico en curso**. |
| RN-02 | Un periodo tiene hasta 4 bimestres, dentro de sus fechas y sin cruzarse. La **fecha de cierre de notas** de un bimestre no puede ser anterior a su inicio ni posterior al fin del periodo. Por defecto es el fin del bimestre + 7 días, sin pasar el fin del periodo (por eso el fin del periodo incluye el cierre de notas del IV bimestre). |
| RN-03 | Un grado con sección **"Única"** no puede tener otras secciones en el mismo periodo, y viceversa. |
| RN-04 | Un estudiante tiene **una sola matrícula por periodo**, en un aula de ese periodo, sin superar la capacidad del aula. |
| RN-05 | Un docente o un aula no pueden tener dos clases que se crucen en horario. |
| RN-06 | La asistencia solo se registra en días en que el curso tiene clase según el horario, dentro del bimestre y nunca en fechas futuras. |
| RN-07 | El docente puede corregir la asistencia hasta las **11:59 p. m. del mismo día** de la clase; después solo el administrador, y queda registrado quién la corrigió. Una clase pasada que nunca se registró (o quedó en borrador) el docente aún puede registrarla una vez. |
| RN-08 | Las notas van de **0 a 20**, con hasta 2 decimales; se aceptan con punto o coma. |
| RN-09 | **Promedio del bimestre** = Σ(nota × peso) / Σ(peso) de las evaluaciones del curso en el bimestre. Las evaluaciones **sin nota cuentan como 0**. Se redondea a 1 decimal (0,05 sube). |
| RN-10 | Aprobado si el promedio es ≥ la nota aprobatoria (11 por defecto). |
| RN-11 | **Equivalencia literal** por defecto: AD = 18–20, A = 14–17,99, B = 11–13,99, C = 0–10,99. Es configurable. |
| RN-12 | Una entrega es **tardía** si se envía después de las 11:59 p. m. del día límite. Sin entrega al pasar la fecha límite = **no entregado**. |
| RN-13 | Estados de la calificación: **BORRADOR → PUBLICADA → CERRADA**. El estudiante solo ve las notas y el promedio de un curso cuando su calificación está publicada o cerrada; después de publicar ve los cambios que el docente guarde. Una calificación cerrada no se modifica. |
| RN-14 | Al llegar la fecha de cierre (o al cerrar el admin), los promedios pendientes se calculan con 0 en lo que falte y pasan a **CERRADA**. Se verifica en cada uso del sistema, sin cron. |
| RN-15 | Tras **5 intentos fallidos** se bloquea la cuenta. Estudiante/docente: hasta que el admin la desbloquee. Administrador: 15 minutos. |
| RN-16 | **Contraseñas** — docente y administrador: ≥ 8 caracteres con mayúscula, minúscula, número y símbolo. Estudiante: ≥ 8 caracteres con letras y números. |
| RN-17 | La sesión se cierra tras **1 hora sin actividad**. |
| RN-18 | El captcha es de un solo uso, vence a los 5 minutos, no distingue mayúsculas y evita caracteres confusos (0/O, 1/l/I). |
| RN-19 | **Riesgo ALTO:** asistencia < 85 %, o promedio < 11 con ≥ 3 entregas pendientes. **Riesgo MEDIO:** asistencia < 90 %, o promedio < 12, o notas en descenso en dos evaluaciones seguidas. **BAJO:** en otro caso. Umbrales configurables. |
| RN-20 | Hay **una sola alerta activa por estudiante y bimestre**; si el nivel cambia, se actualiza y queda en el historial. |
| RN-21 | La alerta se asigna al **tutor del aula**; si no hay, al coordinador del ámbito; si tampoco hay, se genera una alerta administrativa "aula sin tutor". |
| RN-22 | El **código de estudiante** se genera como `E-AAAA-NNN` (año de ingreso + correlativo) si no se indica. |
| RN-23 | **Edad coherente:** estudiantes de primaria entre 5 y 14 años y de secundaria entre 11 y 20, a la fecha de matrícula; docentes y personal entre 18 y 75 años. |
| RN-24 | Cada estudiante tiene **a lo sumo un apoderado principal**. |
| RN-25 | El enlace de recuperación de contraseña es de un solo uso y vence a los 30 minutos. La respuesta es la misma exista o no la cuenta. |
| RN-26 | Los archivos de entrega son **PDF de hasta 10 MB** (o el límite menor del hosting); solo los ven el estudiante dueño, el docente del curso y el administrador. |
| RN-27 | **% de asistencia** = (clases − faltas) ÷ clases × 100, con 1 decimal. Tardanzas (T) y faltas justificadas (J) no restan. Solo cuentan los registros **definitivos**; los borradores no los ve el estudiante. |
| RN-28 | Una evaluación **en clase** (por defecto, el examen) no pide archivo; las demás aceptan **una entrega** por estudiante, que puede guardarse como borrador y, ya entregada, no se cambia (el docente corrige su estado si hace falta). |

---

## 7. Casos de uso

### 7.1 Lista

| Código | Caso de uso | Actor principal | RF |
|---|---|---|---|
| CU-00 | Visitar la landing y elegir acceso | Visitante | RF-01, RF-02 |
| CU-01 | Iniciar sesión | Todos | RF-03, RF-04, RF-05, RF-07, RF-09, RF-10 |
| CU-02 | Recuperar contraseña | Todos | RF-06 |
| CU-03 | Cerrar sesión | Todos | RF-08 |
| CU-04 | Gestionar usuarios | Administrador | RF-11, RF-12, RF-13, RF-16 |
| CU-05 | Gestionar mi perfil | Todos | RF-14, RF-15 |
| CU-06 | Configurar periodos, bimestres y datos del colegio | Administrador | RF-17, RF-18, RF-26 |
| CU-07 | Configurar grados, secciones, aulas y asignaturas | Administrador | RF-19, RF-20, RF-21, RF-22 |
| CU-08 | Asignar carga académica, coordinadores y horarios | Administrador | RF-23, RF-24, RF-25 |
| CU-09 | Gestionar docentes | Administrador | RF-27 |
| CU-10 | Gestionar estudiantes y apoderados | Administrador | RF-28, RF-29 |
| CU-11 | Importar estudiantes desde Excel | Administrador | RF-30 |
| CU-12 | Gestionar matrículas | Administrador | RF-31, RF-32 |
| CU-13 | Registrar asistencia | Docente | RF-33, RF-34, RF-37 |
| CU-14 | Corregir y justificar asistencia | Administrador | RF-35 |
| CU-15 | Consultar mi asistencia | Estudiante | RF-36 |
| CU-16 | Gestionar evaluaciones y material | Docente | RF-38, RF-39 |
| CU-17 | Entregar actividad | Estudiante | RF-40, RF-41, RF-42 |
| CU-18 | Registrar notas | Docente | RF-42, RF-43 |
| CU-19 | Publicar promedios del bimestre | Docente | RF-44, RF-45 |
| CU-20 | Cerrar bimestre | Administrador / Sistema | RF-46 |
| CU-21 | Consultar mis notas y horario | Estudiante | RF-47, RF-48 |
| CU-22 | Registrar y consultar incidencias | Docente / Administrador | RF-49, RF-50 |
| CU-23 | Revisar notificaciones | Todos | RF-51 |
| CU-24 | Calcular riesgo y generar alertas | Sistema | RF-52, RF-53, RF-54, RF-56 |
| CU-25 | Atender alerta estudiantil | Docente / Coordinador / Administrador | RF-55 |
| CU-26 | Consultar paneles de riesgo y coordinación | Administrador / Coordinador | RF-57, RF-58 |
| CU-27 | Consultar inicio y ficha 360° | Todos | RF-59, RF-60, RF-61 |
| CU-28 | Generar reportes | Administrador / Docente / Estudiante | RF-62, RF-63, RF-64 |
| CU-29 | Instalar y operar AYNI en local | Profesor / Equipo | RF-65, RF-66, RF-67, RF-68 |

### 7.2 Especificación de los casos principales

#### CU-01 — Iniciar sesión
- **Actor:** Estudiante, Docente, Coordinador o Administrador.
- **Precondición:** el usuario existe y está activo.

**Flujo principal:**
1. El usuario elige su acceso en el selector.
2. El sistema muestra el formulario con un captcha nuevo.
3. El usuario ingresa su usuario, su contraseña y el texto del captcha.
4. El sistema valida el formato de los campos y el captcha.
5. El sistema verifica las credenciales, reinicia el contador de intentos y registra el último acceso.
6. El sistema abre el inicio correspondiente al rol.

**Flujos alternativos:**
- **4a.** Captcha incorrecto o vencido: muestra el error y genera un captcha nuevo.
- **5a.** Credenciales incorrectas: suma un intento fallido y muestra un mensaje genérico. Al 5.º intento bloquea la cuenta (RN-15).
- **5b.** Cuenta bloqueada o inactiva: informa y no permite el ingreso.
- **5c.** Contraseña temporal: exige cambiarla antes de continuar (RF-07).
- **5d.** El usuario ingresó por un acceso que no corresponde a su rol: lo indica y no ingresa.

**Postcondición:** sesión activa con vigencia de 1 hora sin actividad.

#### CU-02 — Recuperar contraseña
- **Actor:** cualquier usuario.

**Flujo principal:**
1. Pulsa "¿Olvidaste tu contraseña?".
2. Ingresa su usuario y el captcha.
3. El sistema muestra siempre el mismo mensaje: "Si la cuenta tiene correo registrado, te enviamos un enlace".
4. Si la cuenta existe y tiene correo, envía un enlace válido 30 minutos.
5. El usuario abre el enlace y define una nueva contraseña que cumpla RN-16.
6. El sistema la guarda e invalida el enlace.

**Flujos alternativos:**
- **4a.** Sin correo registrado: el mensaje sugiere acudir al administrador.
- **5a.** Enlace vencido o ya usado: informa y ofrece pedir otro.

#### CU-11 — Importar estudiantes desde Excel
- **Actor:** Administrador.
- **Precondición:** existe el periodo en curso con sus aulas.

**Flujo principal:**
1. Descarga la plantilla.
2. Sube el archivo `.xlsx` y marca las opciones "Matricular en el periodo" y "Crear usuario con contraseña temporal".
3. El sistema valida cada fila contra `docs/validaciones.md` y la BD (DNI repetido, sección inexistente, fecha inválida…).
4. Muestra el total de filas, las válidas y las que tienen error, con el motivo de cada una.
5. El administrador confirma "Importar N estudiantes".
6. El sistema registra solo las filas válidas, en una sola transacción.

**Flujos alternativos:**
- **3a.** El archivo no es Excel o supera el tamaño: se rechaza.
- **4a.** El administrador descarga las filas con error para corregirlas.

**Postcondición:** estudiantes creados (y matriculados, y con usuario, si se eligió).

#### CU-13 — Registrar asistencia
- **Actor:** Docente.
- **Precondición:** el docente tiene el curso asignado y la fecha es un día de clase del curso dentro del bimestre (RN-06).

**Flujo principal:**
1. Elige curso y fecha.
2. El sistema lista a los estudiantes matriculados, todos como "Presente".
3. El docente marca las excepciones (F, T, J) y sus observaciones.
4. Pulsa "Guardar asistencia".
5. El sistema guarda, notifica las faltas a los estudiantes y recalcula su riesgo.

**Flujos alternativos:**
- **4a.** "Guardar borrador": guarda sin notificar ni recalcular.
- **1a.** La fecha ya tiene registro: lo carga para corregirlo hasta las 11:59 p. m. del mismo día (RN-07); después queda de solo lectura.

#### CU-17 — Entregar actividad
- **Actor:** Estudiante.
- **Precondición:** el estudiante está matriculado en el aula de la evaluación.

**Flujo principal:**
1. Abre la actividad en "Actividades".
2. Lee las indicaciones y descarga el material de apoyo.
3. Sube su archivo PDF.
4. Pulsa "Entregar actividad".
5. El sistema guarda la entrega con su fecha y hora, la marca como entregada o tardía (RN-12) y la registra en el historial.

**Flujos alternativos:**
- **3a.** El archivo no es PDF o supera el límite (RN-26): se rechaza.
- **4a.** "Guardar borrador": se guarda sin entregar.

#### CU-19 — Publicar promedios del bimestre
- **Actor:** Docente.
- **Precondición:** el bimestre no está cerrado.

**Flujo principal:**
1. Abre "Calificaciones" y elige curso y bimestre.
2. El sistema muestra las notas y el promedio ponderado de cada estudiante (RN-09).
3. Pulsa "Publicar promedios".
4. El sistema advierte qué evaluaciones sin nota se contarán como 0 y a quiénes afecta.
5. El docente confirma.
6. El sistema publica, notifica a los estudiantes y recalcula el riesgo.

**Postcondición:** las calificaciones quedan **PUBLICADAS** y visibles para los estudiantes.

#### CU-20 — Cerrar bimestre
- **Actor:** Administrador o Sistema.

**Flujo principal:**
1. El administrador pulsa "Cerrar bimestre", o el sistema detecta que pasó la fecha de cierre (RN-14).
2. El sistema calcula los promedios pendientes con 0 en lo que falte.
3. Marca todas las calificaciones del bimestre como **CERRADAS**.
4. Deja registro del cierre en la auditoría.

**Postcondición:** no se pueden modificar notas, entregas ni asistencia de ese bimestre.

#### CU-25 — Atender alerta estudiantil
- **Actor:** Docente responsable, Coordinador o Administrador.

**Flujo principal:**
1. Abre la alerta.
2. El sistema muestra el nivel, las reglas activadas con sus valores y las recomendaciones.
3. El usuario la pasa a "En seguimiento".
4. Registra una o más intervenciones (tipo, descripción, resultado).
5. La cierra o la descarta con una justificación.

**Postcondición:** todo queda en el historial de la alerta con usuario y fecha.

---

## 8. Matriz de trazabilidad (se completa en cada módulo)

| RF | Módulo | Pruebas automáticas | Resultado |
|---|---|---|---|
| RF-01 – RF-02 | M3 | E2E `m3-diseno.spec.ts` (landing, selector, ingreso, layouts, errores; PC y celular); Vitest `paginas.test.tsx`, `componentes.test.tsx`, `validaciones.test.ts`; Lighthouse (rendimiento y accesibilidad ≥ 90) | ✅ Pasan (M3) |
| RF-03 – RF-10 | M4 | Pest `Acceso/IngresoTest` (ingreso por rol, mensajes genéricos, límites, bloqueo RN-15, inactividad RN-17, permisos por rol, política RN-16), `Acceso/CaptchaTest` (RN-18), `Acceso/RecuperacionTest` (RN-25); Vitest `acceso.test.tsx`, `sesion.test.ts`, `validaciones.test.ts`; E2E `m4-acceso.spec.ts` (ingreso y salida de cada rol, captcha real, rutas protegidas, recuperación con Mailpit; PC y celular) | ✅ Pasan (M4) |
| RF-11 – RF-16 | M5 | Pest `Usuarios/UsuariosTest` (lista, filtros, búsqueda sin tildes, sin N+1, permisos, alta, validaciones e inyección CRLF en correo, edición con auditoría, activar/inactivar, desbloqueo con contraseña temporal y correo), `Usuarios/PerfilTest`; Vitest `usuarios.test.tsx`, `validaciones.test.ts`; E2E `m5-usuarios.spec.ts` (PC y celular, desbloqueo con Mailpit) | ✅ Pasan (M5) |
| RF-17 – RF-26 | M6 | Pest `Estructura/PeriodosTest` (RN-01, RN-02, cierre automático RN-14), `AulasTest` (RN-03, tutor, capacidad, coordinadores), `HorariosTest` (carga, cruces de aula y de docente RN-05), `CatalogosTest` (asignaturas, tipos de evaluación, colegio y escala RN-11, opciones), `CalendarioActualTest`; Vitest `estructura.test.tsx`, `validaciones.test.ts`; E2E `m6-estructura.spec.ts` (aula completa con cursos y horario; PC y celular) | ✅ Pasan (M6) |
| RF-27 – RF-32 | M7 | Pest `Personas/DocentesTest` (usuario = DNI, RN-23, inactivo desactiva la cuenta), `EstudiantesTest` (código RN-22, apoderado principal RN-24), `MatriculasTest` (RN-04, edad RN-23, retiro y anulación), `ImportacionTest` (plantilla, revisión por fila, filas con error, importación en una transacción); Vitest `personas.test.tsx`, `validaciones.test.ts`; E2E `m7-personas.spec.ts` (importar un Excel con una fila errónea y matricular; PC y celular) | ✅ Pasan (M7) |
| RF-33 – RF-37 | M8 | Pest `Asistencia/AsistenciaTest` (401/403 por rol y solo el docente del curso, todos presentes por defecto, excepciones, aviso de falta al estudiante, sin duplicados, borrador → definitivo, fechas RN-06, medianoche RN-07 y corrección del administrador, validaciones por campo, faltas de la semana RF-37, % RN-27, resumen por aula y clases sin registrar, borrado con motivo, retirados), `DatosDemostracionTest` (clase pendiente del docente demo); Vitest `asistencia.test.tsx` (registro, solo lectura, errores del servidor, mi asistencia, corrección); E2E `m8-asistencia.spec.ts` (el docente registra, la estudiante lo ve, el administrador justifica; PC y celular) | ✅ Pasan (M8) |
| RF-38 – RF-48 | M9 | Pest `Evaluacion/EvaluacionesTest` (permisos, validaciones por campo, fechas dentro del bimestre, edición con recálculo, borrado protegido, material: formatos, archivos disfrazados y 10 MB), `EntregasTest` (actividades asignadas con peso en %, borrador → entrega, tardía tras las 11:59 p. m. RN-12, solo PDF real RN-26, examen en clase, bimestre cerrado, retirado, descargas con permiso), `CalificacionesTest` (notas con coma y rechazo de 20,5 / -1 / abc / 15,555 RN-08, estado de entrega corregido, Excel, promedio ponderado a mano RN-09, sin nota = 0, literal RN-11, publicar con aviso RN-13, cierre automático y manual RN-14, horario), `DatosDemostracionTest`; Vitest `evaluaciones.test.tsx`; E2E `m9-evaluaciones.spec.ts` (evaluación → entrega → nota → publicar → el estudiante la ve; PC y celular) | ⏳ Escritas (falta la corrida completa) |
| RF-49 – RF-51 | M10 | — | Pendiente |
| RF-52 – RF-58 | M11 | — | Pendiente |
| RF-59 – RF-64 | M12 | — | Pendiente |
| RF-65, RF-66, RF-68 | M1 | Pester `scripts/pruebas/ayni.Tests.ps1` (13); E2E `m1-esqueleto.spec.ts` (17 × PC y celular); Pest `SaludTest`, `ErroresJsonTest`, `PlataformaTest` (24) | ✅ Pasan (M1) |
| RF-67 | M2 | Pest `DatosDemostracionTest` (29), `CalendarioDemoTest` (10), `RestriccionesTest` (32) | ✅ Pasan (M2) |

En el M13 esta tabla se desglosa a una fila por RF, con el nombre de cada prueba.

---

## 9. Glosario

| Término | Significado |
|---|---|
| **Periodo** | Año escolar (por ejemplo, 2026) |
| **Bimestre** | Cada una de las 4 partes del periodo |
| **Aula** | Grado + sección en un periodo (por ejemplo, 3.º B Secundaria 2026) |
| **Curso del aula** | Una asignatura dictada en un aula por un docente |
| **Evaluación / actividad** | Tarea calificable de un curso (práctica, proyecto, examen…); "actividad" es el nombre que ve el estudiante |
| **Peso** | Importancia relativa de una evaluación dentro del promedio |
| **Ámbito de coordinación** | Conjunto de aulas que supervisa un coordinador |
| **Alerta estudiantil** | Aviso de riesgo académico de un estudiante, con seguimiento |
| **Alerta administrativa** | Aviso operativo para el administrador (bloqueos, datos faltantes…) |
| **Reglas v0** | Cálculo de riesgo por umbrales usado en la Unidad 1; la IA lo reemplaza en la Unidad 2 |
