# Diccionario de datos de AYNI

Generado desde la base de datos con `php artisan ayni:documentar-bd`. **No editar a mano.**

42 tablas · MySQL 8 / MariaDB 10.6+ · `utf8mb4`

## Seguridad

### `rol`

Roles de acceso. El coordinador es un docente con ámbito de coordinación.

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `codigo` | varchar(20) | No | Única |  | ADMIN, DOCENTE o ESTUDIANTE |
| `nombre` | varchar(60) | No |  |  |  |

**Restricciones:**

- `ck_rol_codigo`: `(codigo in ('ADMIN','DOCENTE','ESTUDIANTE'))`

### `usuario`

Cuentas de acceso al sistema

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `rol_id` | bigint unsigned | No | FK → rol |  |  |
| `username` | varchar(60) | No | Única |  | Admin: nombre de usuario; docente: DNI; estudiante: código |
| `email` | varchar(120) | Sí | Única |  | Correo para recuperar la contraseña |
| `password_hash` | varchar(255) | No |  |  | Contraseña cifrada con bcrypt |
| `estado` | varchar(15) | No |  | `ACTIVO` | ACTIVO, INACTIVO o BLOQUEADO |
| `intentos_fallidos` | tinyint unsigned | No |  | `0` | Intentos de ingreso fallidos seguidos |
| `bloqueado_hasta` | timestamp | Sí |  |  | Fin del bloqueo temporal; NULL con estado BLOQUEADO = hasta que el admin desbloquee |
| `debe_cambiar_password` | tinyint(1) | No |  | `0` | Contraseña temporal: debe cambiarla al ingresar |
| `ultimo_acceso` | timestamp | Sí |  |  |  |
| `creado_por` | bigint unsigned | Sí | FK → usuario |  |  |
| `actualizado_por` | bigint unsigned | Sí | FK → usuario |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_usuario_estado`: `(estado in ('ACTIVO','INACTIVO','BLOQUEADO'))`

### `auditoria`

Registro de quién cambió qué y cuándo

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `usuario_id` | bigint unsigned | Sí | FK → usuario |  |  |
| `accion` | varchar(40) | No |  |  | CREAR, ACTUALIZAR, ELIMINAR, DESBLOQUEAR, CERRAR_BIMESTRE… |
| `entidad` | varchar(60) | No |  |  | Tabla afectada |
| `entidad_id` | bigint unsigned | Sí |  |  |  |
| `detalle` | json | Sí |  |  | Valores antes/después |
| `ip` | varchar(45) | Sí |  |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` |  |

### `recuperacion_password`

Enlaces de recuperación de contraseña (un solo uso, 30 minutos)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `usuario_id` | bigint unsigned | No | FK → usuario |  |  |
| `token_hash` | char(64) | No | Única |  | SHA-256 del token enviado por correo |
| `expira_en` | timestamp | No |  |  |  |
| `usado_en` | timestamp | Sí |  |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` |  |

### `captcha_desafio`

Captchas propios del login (un solo uso, 5 minutos)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | char(36) | No | PK |  |  |
| `respuesta_hash` | varchar(255) | No |  |  | Respuesta cifrada; nunca se guarda en claro |
| `expira_en` | timestamp | No |  |  |  |
| `usado_en` | timestamp | Sí |  |  |  |
| `ip` | varchar(45) | Sí |  |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` |  |

## Personas

### `persona`

Datos personales comunes de administrativos, docentes y estudiantes

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `tipo_documento` | varchar(15) | No |  | `DNI` | DNI, CE o PASAPORTE |
| `numero_documento` | varchar(20) | No |  |  |  |
| `nombres` | varchar(100) | No |  |  |  |
| `apellidos` | varchar(100) | No |  |  |  |
| `fecha_nacimiento` | date | Sí |  |  |  |
| `sexo` | char(1) | Sí |  |  | M o F |
| `celular` | varchar(20) | Sí |  |  |  |
| `correo` | varchar(120) | Sí |  |  |  |
| `direccion` | varchar(200) | Sí |  |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_persona_sexo`: `((sexo is null) or (sexo in ('M','F')))`
- `ck_persona_tipo_documento`: `(tipo_documento in ('DNI','CE','PASAPORTE'))`

### `personal_administrativo`

Personal administrativo (rol ADMIN)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `persona_id` | bigint unsigned | No | Única FK → persona |  |  |
| `usuario_id` | bigint unsigned | No | Única FK → usuario |  |  |
| `cargo` | varchar(80) | Sí |  |  |  |
| `estado` | varchar(10) | No |  | `ACTIVO` |  |

**Restricciones:**

- `ck_administrativo_estado`: `(estado in ('ACTIVO','INACTIVO'))`

### `docente`

Docentes (rol DOCENTE; algunos también coordinan)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `persona_id` | bigint unsigned | No | Única FK → persona |  |  |
| `usuario_id` | bigint unsigned | No | Única FK → usuario |  |  |
| `titulo` | varchar(120) | Sí |  |  |  |
| `especialidad` | varchar(100) | Sí |  |  |  |
| `fecha_inicio_contrato` | date | Sí |  |  |  |
| `fecha_fin_contrato` | date | Sí |  |  |  |
| `estado` | varchar(10) | No |  | `ACTIVO` | ACTIVO, INACTIVO o LICENCIA |

**Restricciones:**

- `ck_docente_contrato`: `((fecha_fin_contrato is null) or (fecha_fin_contrato >= fecha_inicio_contrato))`
- `ck_docente_estado`: `(estado in ('ACTIVO','INACTIVO','LICENCIA'))`

### `estudiante`

Estudiantes (rol ESTUDIANTE)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `persona_id` | bigint unsigned | No | Única FK → persona |  |  |
| `usuario_id` | bigint unsigned | No | Única FK → usuario |  |  |
| `codigo` | varchar(30) | No | Única |  | E-AAAA-NNN; también es su usuario |
| `fecha_ingreso` | date | Sí |  |  |  |
| `estado` | varchar(15) | No |  | `ACTIVO` | ACTIVO, RETIRADO, TRASLADADO o EGRESADO |
| `origen` | varchar(10) | No |  | `MANUAL` | MANUAL o EXCEL |

**Restricciones:**

- `ck_estudiante_estado`: `(estado in ('ACTIVO','RETIRADO','TRASLADADO','EGRESADO'))`
- `ck_estudiante_origen`: `(origen in ('MANUAL','EXCEL'))`

### `apoderado`

Apoderados del estudiante (sin acceso al sistema en la Unidad 1)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `estudiante_id` | bigint unsigned | No | FK → estudiante |  |  |
| `nombres` | varchar(150) | No |  |  | Nombre completo |
| `celular` | varchar(20) | Sí |  |  |  |
| `correo` | varchar(120) | Sí |  |  |  |
| `parentesco` | varchar(40) | Sí |  |  | Padre, Madre, Tutor legal, Abuelo(a), Tío(a), Hermano(a), Otro |
| `es_principal` | tinyint(1) | No |  | `0` |  |
| `principal_unico` | tinyint unsigned | Sí |  |  | Calculada: 1 si es principal |

## Configuración

### `institucion`

Datos y parámetros del colegio (una sola fila)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `nombre` | varchar(150) | No |  |  |  |
| `codigo_modular` | varchar(20) | Sí |  |  |  |
| `ugel` | varchar(80) | Sí |  |  |  |
| `direccion` | varchar(200) | Sí |  |  |  |
| `telefono` | varchar(20) | Sí |  |  |  |
| `correo` | varchar(120) | Sí |  |  |  |
| `nota_aprobatoria` | decimal(4,2) | No |  | `11.00` | Promedio mínimo para aprobar |
| `umbral_asistencia_alto` | tinyint unsigned | No |  | `85` | % de asistencia por debajo del cual el riesgo es ALTO |
| `umbral_asistencia_medio` | tinyint unsigned | No |  | `90` | % de asistencia por debajo del cual el riesgo es MEDIO |
| `umbral_promedio_alto` | decimal(4,2) | No |  | `11.00` | Promedio por debajo del cual (con pendientes) el riesgo es ALTO |
| `umbral_promedio_medio` | decimal(4,2) | No |  | `12.00` | Promedio por debajo del cual el riesgo es MEDIO |
| `umbral_pendientes_alto` | tinyint unsigned | No |  | `3` | Entregas pendientes que, con promedio bajo, dan riesgo ALTO |
| `dias_cierre_bimestre` | tinyint unsigned | No |  | `7` | Días después del fin del bimestre para la fecha de cierre por defecto |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_institucion_asistencia`: `((umbral_asistencia_alto < umbral_asistencia_medio) and (umbral_asistencia_medio <= 100))`
- `ck_institucion_nota`: `(nota_aprobatoria between 0 and 20)`
- `ck_institucion_promedio`: `((umbral_promedio_alto <= umbral_promedio_medio) and (umbral_promedio_medio <= 20))`

### `escala_literal`

Equivalencia de la nota vigesimal a AD/A/B/C (solo para mostrar)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `codigo` | varchar(3) | No | Única |  | AD, A, B o C |
| `descripcion` | varchar(80) | No |  |  |  |
| `nota_minima` | decimal(4,2) | No |  |  |  |
| `nota_maxima` | decimal(4,2) | No |  |  |  |
| `orden` | tinyint unsigned | No |  |  |  |

**Restricciones:**

- `ck_escala_rango`: `((nota_minima >= 0) and (nota_maxima <= 20) and (nota_minima <= nota_maxima))`

## Estructura académica

### `periodo_academico`

Año escolar

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `nombre` | varchar(60) | No | Única |  | Ej. 2026 |
| `fecha_inicio` | date | No |  |  |  |
| `fecha_fin` | date | No |  |  |  |
| `estado` | varchar(15) | No |  | `PLANIFICADO` | PLANIFICADO, EN_CURSO o CERRADO |
| `en_curso_unico` | tinyint unsigned | Sí | Única |  | Calculada: garantiza un solo periodo en curso |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_periodo_estado`: `(estado in ('PLANIFICADO','EN_CURSO','CERRADO'))`
- `ck_periodo_fechas`: `(fecha_fin > fecha_inicio)`

### `subperiodo`

Bimestres del periodo

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `periodo_id` | bigint unsigned | No | FK → periodo_academico |  |  |
| `numero` | tinyint unsigned | No |  |  | 1 a 4 |
| `nombre` | varchar(40) | No |  |  | Ej. III Bimestre |
| `fecha_inicio` | date | No |  |  |  |
| `fecha_fin` | date | No |  |  |  |
| `fecha_cierre_notas` | date | No |  |  | Al llegar esta fecha el bimestre se cierra solo |
| `estado` | varchar(10) | No |  | `ABIERTO` | ABIERTO o CERRADO |
| `cerrado_en` | timestamp | Sí |  |  |  |
| `cerrado_por` | bigint unsigned | Sí | FK → usuario |  |  |

**Restricciones:**

- `ck_subperiodo_estado`: `(estado in ('ABIERTO','CERRADO'))`
- `ck_subperiodo_fechas`: `((fecha_fin > fecha_inicio) and (fecha_cierre_notas >= fecha_inicio))`
- `ck_subperiodo_numero`: `(numero between 1 and 4)`

### `grado`

Grados de primaria (1.º-6.º) y secundaria (1.º-5.º)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `nivel` | varchar(15) | No |  |  | PRIMARIA o SECUNDARIA |
| `numero` | tinyint unsigned | No |  |  |  |
| `nombre` | varchar(40) | No |  |  | Ej. Tercero |
| `orden` | tinyint unsigned | No |  |  |  |

**Restricciones:**

- `ck_grado_nivel`: `(nivel in ('PRIMARIA','SECUNDARIA'))`
- `ck_grado_numero`: `((numero >= 1) and (((nivel = 'PRIMARIA') and (numero <= 6)) or ((nivel = 'SECUNDARIA') and (numero <= 5))))`

### `seccion`

Secciones: "Única" o letras A, B, C…

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `nombre` | varchar(10) | No | Única |  |  |

### `aula`

Grado + sección en un periodo

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `periodo_id` | bigint unsigned | No | FK → periodo_academico |  |  |
| `grado_id` | bigint unsigned | No | FK → grado |  |  |
| `seccion_id` | bigint unsigned | No | FK → seccion |  |  |
| `tutor_docente_id` | bigint unsigned | Sí | FK → docente |  |  |
| `capacidad` | smallint unsigned | No |  | `30` |  |
| `ambiente` | varchar(40) | Sí |  |  | Ej. Aula 12 |

**Restricciones:**

- `ck_aula_capacidad`: `(capacidad between 1 and 60)`

### `coordinacion`

Docentes que coordinan un aula, un grado, un nivel o todo el colegio

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `docente_id` | bigint unsigned | No | FK → docente |  |  |
| `periodo_id` | bigint unsigned | No | FK → periodo_academico |  |  |
| `ambito` | varchar(15) | No |  |  | AULA, GRADO, NIVEL o INSTITUCION |
| `aula_id` | bigint unsigned | Sí | FK → aula |  |  |
| `grado_id` | bigint unsigned | Sí | FK → grado |  |  |
| `nivel` | varchar(15) | Sí |  |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_coordinacion_ambito`: `(((ambito = 'AULA') and (aula_id is not null) and (grado_id is null) and (nivel is null)) or ((ambito = 'GRADO') and (grado_id is not null) and (aula_id is null) and (nivel is null)) or ((ambito = 'NIVEL') and (nivel in ('PRIMARIA','SECUNDARIA')) and (aula_id is null) and (grado_id is null)) or ((ambito = 'INSTITUCION') and (aula_id is null) and (grado_id is null) and (nivel is null)))`

### `matricula`

Matrícula del estudiante en un aula del periodo (una por año)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `estudiante_id` | bigint unsigned | No | FK → estudiante |  |  |
| `aula_id` | bigint unsigned | No | FK → aula |  |  |
| `periodo_id` | bigint unsigned | No | FK → periodo_academico |  |  |
| `fecha` | date | No |  |  |  |
| `estado` | varchar(10) | No |  | `ACTIVA` | ACTIVA, RETIRADA o ANULADA |
| `motivo` | varchar(300) | Sí |  |  | Motivo del retiro o la anulación |
| `registrado_por` | bigint unsigned | Sí | FK → usuario |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_matricula_estado`: `(estado in ('ACTIVA','RETIRADA','ANULADA'))`

### `asignatura`

Asignaturas o cursos

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `codigo` | varchar(20) | Sí | Única |  |  |
| `nombre` | varchar(100) | No |  |  |  |
| `horas_semanales` | smallint unsigned | Sí |  |  |  |
| `estado` | varchar(10) | No |  | `ACTIVO` |  |

**Restricciones:**

- `ck_asignatura_estado`: `(estado in ('ACTIVO','INACTIVO'))`
- `ck_asignatura_horas`: `((horas_semanales is null) or (horas_semanales between 1 and 20))`

### `curso_aula`

Carga académica: asignatura dictada en un aula por un docente

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `aula_id` | bigint unsigned | No | FK → aula |  |  |
| `asignatura_id` | bigint unsigned | No | FK → asignatura |  |  |
| `docente_id` | bigint unsigned | No | FK → docente |  |  |

### `horario`

Bloques semanales de cada curso

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `curso_aula_id` | bigint unsigned | No | FK → curso_aula |  |  |
| `dia_semana` | tinyint unsigned | No |  |  | 1 = lunes … 7 = domingo |
| `hora_inicio` | time | No |  |  |  |
| `hora_fin` | time | No |  |  |  |
| `ambiente` | varchar(40) | Sí |  |  |  |

**Restricciones:**

- `ck_horario_dia`: `(dia_semana between 1 and 7)`
- `ck_horario_horas`: `(hora_fin > hora_inicio)`

## Seguimiento académico

### `tipo_evaluacion`

Tipos de evaluación configurables

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `nombre` | varchar(40) | No | Única |  | Práctica, Proyecto, Examen, Semanal, Parcial… |
| `estado` | varchar(10) | No |  | `ACTIVO` |  |

**Restricciones:**

- `ck_tipo_evaluacion_estado`: `(estado in ('ACTIVO','INACTIVO'))`

### `archivo`

Archivos subidos (entregas y material de apoyo); se guardan fuera de la carpeta pública

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `disco` | varchar(20) | No |  | `local` |  |
| `ruta` | varchar(255) | No |  |  |  |
| `nombre_original` | varchar(255) | No |  |  |  |
| `tipo_mime` | varchar(100) | No |  |  |  |
| `tamano_bytes` | int unsigned | No |  |  |  |
| `hash_sha256` | char(64) | No |  |  |  |
| `subido_por` | bigint unsigned | Sí | FK → usuario |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` |  |

### `evaluacion`

Evaluaciones o actividades de un curso en un bimestre

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `curso_aula_id` | bigint unsigned | No | FK → curso_aula |  |  |
| `subperiodo_id` | bigint unsigned | No | FK → subperiodo |  |  |
| `tipo_evaluacion_id` | bigint unsigned | No | FK → tipo_evaluacion |  |  |
| `titulo` | varchar(150) | No |  |  |  |
| `indicaciones` | text | Sí |  |  |  |
| `fecha_asignacion` | date | No |  |  |  |
| `fecha_limite` | date | No |  |  | Se puede entregar hasta las 11:59 p. m. de este día |
| `peso` | tinyint unsigned | No |  | `1` | Peso relativo (1 a 10) en el promedio |
| `creado_por` | bigint unsigned | Sí | FK → usuario |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_evaluacion_fechas`: `(fecha_limite >= fecha_asignacion)`
- `ck_evaluacion_peso`: `(peso between 1 and 10)`

### `evaluacion_material`

Material de apoyo de una evaluación

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `evaluacion_id` | bigint unsigned | No | PK FK → evaluacion |  |  |
| `archivo_id` | bigint unsigned | No | PK FK → archivo |  |  |

### `entrega`

Entrega de un estudiante para una evaluación

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `evaluacion_id` | bigint unsigned | No | FK → evaluacion |  |  |
| `matricula_id` | bigint unsigned | No | FK → matricula |  |  |
| `archivo_id` | bigint unsigned | Sí | FK → archivo |  |  |
| `estado` | varchar(10) | No |  | `BORRADOR` | BORRADOR o ENTREGADA |
| `entregada_en` | timestamp | Sí |  |  |  |
| `es_tardia` | tinyint(1) | No |  | `0` |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_entrega_estado`: `((estado in ('BORRADOR','ENTREGADA')) and ((estado = 'BORRADOR') or (entregada_en is not null)))`

### `nota_evaluacion`

Nota de cada estudiante en cada evaluación

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `evaluacion_id` | bigint unsigned | No | FK → evaluacion |  |  |
| `matricula_id` | bigint unsigned | No | FK → matricula |  |  |
| `nota` | decimal(4,2) | Sí |  |  | 0 a 20; NULL = aún sin calificar |
| `estado_entrega` | varchar(15) | No |  | `PENDIENTE` | PENDIENTE, ENTREGADO, TARDIO o NO_ENTREGADO |
| `observacion` | varchar(300) | Sí |  |  |  |
| `registrado_por` | bigint unsigned | Sí | FK → usuario |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_nota_estado_entrega`: `(estado_entrega in ('PENDIENTE','ENTREGADO','TARDIO','NO_ENTREGADO'))`
- `ck_nota_rango`: `((nota is null) or (nota between 0 and 20))`

### `calificacion_periodo`

Promedio del estudiante por curso y bimestre

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `matricula_id` | bigint unsigned | No | FK → matricula |  |  |
| `curso_aula_id` | bigint unsigned | No | FK → curso_aula |  |  |
| `subperiodo_id` | bigint unsigned | No | FK → subperiodo |  |  |
| `promedio` | decimal(4,2) | Sí |  |  |  |
| `estado` | varchar(15) | No |  | `BORRADOR` | BORRADOR, PUBLICADA o CERRADA |
| `publicado_en` | timestamp | Sí |  |  |  |
| `publicado_por` | bigint unsigned | Sí | FK → usuario |  |  |
| `cerrado_en` | timestamp | Sí |  |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_calificacion_estado`: `(estado in ('BORRADOR','PUBLICADA','CERRADA'))`
- `ck_calificacion_promedio`: `((promedio is null) or (promedio between 0 and 20))`

### `sesion_clase`

Clase dictada en una fecha (una por curso y día)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `curso_aula_id` | bigint unsigned | No | FK → curso_aula |  |  |
| `subperiodo_id` | bigint unsigned | No | FK → subperiodo |  |  |
| `fecha` | date | No |  |  |  |
| `tema` | varchar(200) | Sí |  |  |  |
| `estado` | varchar(10) | No |  | `BORRADOR` | BORRADOR o DEFINITIVO |
| `registrado_por` | bigint unsigned | Sí | FK → usuario |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_sesion_estado`: `(estado in ('BORRADOR','DEFINITIVO'))`

### `asistencia`

Asistencia de cada estudiante en cada clase

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `sesion_clase_id` | bigint unsigned | No | FK → sesion_clase |  |  |
| `matricula_id` | bigint unsigned | No | FK → matricula |  |  |
| `estado` | char(1) | No |  |  | P presente, F falta, T tardanza, J justificada |
| `observacion` | varchar(300) | Sí |  |  |  |
| `registrado_por` | bigint unsigned | Sí | FK → usuario |  |  |
| `modificado_por` | bigint unsigned | Sí | FK → usuario |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_asistencia_estado`: `(estado in ('P','F','T','J'))`

### `tipo_incidencia`

Tipos de incidencia

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `nombre` | varchar(60) | No | Única |  |  |
| `descripcion` | varchar(250) | Sí |  |  |  |

### `incidencia`

Incidencias registradas a un estudiante

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `matricula_id` | bigint unsigned | No | FK → matricula |  |  |
| `tipo_incidencia_id` | bigint unsigned | No | FK → tipo_incidencia |  |  |
| `registrado_por` | bigint unsigned | No | FK → usuario |  |  |
| `fecha` | date | No |  |  |  |
| `gravedad` | varchar(10) | No |  |  | LEVE, MODERADA o GRAVE |
| `descripcion` | varchar(500) | No |  |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_incidencia_gravedad`: `(gravedad in ('LEVE','MODERADA','GRAVE'))`

## Riesgo, alertas y notificaciones

### `modelo_ml`

Modelo que calcula el riesgo: reglas-v0 (Unidad 1) o ML (Unidad 2)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `nombre` | varchar(80) | No |  |  |  |
| `version` | varchar(30) | No |  |  |  |
| `algoritmo` | varchar(60) | Sí |  |  |  |
| `metricas` | json | Sí |  |  |  |
| `fecha_entrenamiento` | timestamp | Sí |  |  |  |
| `activo` | tinyint(1) | No |  | `0` |  |
| `activo_unico` | tinyint unsigned | Sí | Única |  | Calculada: un solo modelo activo |

### `prediccion_riesgo`

Nivel de riesgo calculado para un estudiante en un bimestre

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `matricula_id` | bigint unsigned | No | FK → matricula |  |  |
| `subperiodo_id` | bigint unsigned | No | FK → subperiodo |  |  |
| `modelo_id` | bigint unsigned | No | FK → modelo_ml |  |  |
| `fecha` | timestamp | No |  | `CURRENT_TIMESTAMP` |  |
| `probabilidad` | decimal(5,4) | No |  |  | 0 a 1 |
| `nivel_riesgo` | varchar(10) | No |  |  | BAJO, MEDIO o ALTO |
| `features` | json | No |  |  | Valores que vio el modelo |
| `resumen_texto` | text | Sí |  |  |  |

**Restricciones:**

- `ck_prediccion_nivel`: `(nivel_riesgo in ('BAJO','MEDIO','ALTO'))`
- `ck_prediccion_probabilidad`: `(probabilidad between 0 and 1)`

### `factor_riesgo`

Factores que explican la predicción (reglas activadas o valores SHAP)

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `prediccion_id` | bigint unsigned | No | FK → prediccion_riesgo |  |  |
| `variable` | varchar(80) | No |  |  |  |
| `valor` | varchar(80) | Sí |  |  |  |
| `contribucion` | decimal(8,4) | No |  |  | Positiva sube el riesgo; negativa lo baja |
| `descripcion` | varchar(300) | Sí |  |  |  |

### `recomendacion`

Recomendaciones de intervención de una predicción

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `prediccion_id` | bigint unsigned | No | FK → prediccion_riesgo |  |  |
| `descripcion` | varchar(400) | No |  |  |  |
| `prioridad` | tinyint unsigned | No |  | `2` | 1 alta, 2 media, 3 baja |

**Restricciones:**

- `ck_recomendacion_prioridad`: `(prioridad between 1 and 3)`

### `alerta_estudiantil`

Alerta de riesgo académico con seguimiento

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `prediccion_id` | bigint unsigned | No | Única FK → prediccion_riesgo |  |  |
| `matricula_id` | bigint unsigned | No | FK → matricula |  |  |
| `subperiodo_id` | bigint unsigned | No | FK → subperiodo |  |  |
| `responsable_docente_id` | bigint unsigned | No | FK → docente |  |  |
| `estado` | varchar(15) | No |  | `NUEVA` | NUEVA, EN_SEGUIMIENTO, CERRADA o DESCARTADA |
| `justificacion_cambio` | varchar(400) | Sí |  |  |  |
| `fecha_creacion` | timestamp | No |  | `CURRENT_TIMESTAMP` |  |
| `fecha_cierre` | timestamp | Sí |  |  |  |
| `activa_unica` | tinyint unsigned | Sí |  |  | Calculada: una alerta activa por estudiante y bimestre |

**Restricciones:**

- `ck_alerta_cierre`: `((fecha_cierre is null) or (fecha_cierre >= fecha_creacion))`
- `ck_alerta_estado`: `(estado in ('NUEVA','EN_SEGUIMIENTO','CERRADA','DESCARTADA'))`

### `intervencion`

Acciones realizadas para atender una alerta

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `alerta_id` | bigint unsigned | No | FK → alerta_estudiantil |  |  |
| `responsable_id` | bigint unsigned | No | FK → usuario |  |  |
| `tipo` | varchar(60) | No |  |  |  |
| `descripcion` | varchar(500) | Sí |  |  |  |
| `fecha` | date | No |  |  |  |
| `resultado` | varchar(300) | Sí |  |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` |  |

### `historial_alerta`

Historial de cambios de una alerta

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `alerta_id` | bigint unsigned | No | FK → alerta_estudiantil |  |  |
| `accion` | varchar(30) | No |  |  |  |
| `detalle` | varchar(400) | Sí |  |  |  |
| `usuario_id` | bigint unsigned | Sí | FK → usuario |  |  |
| `fecha` | timestamp | No |  | `CURRENT_TIMESTAMP` |  |

**Restricciones:**

- `ck_historial_accion`: `(accion in ('GENERADA','ASIGNADA_DOCENTE_AULA','REASIGNADA','NIVEL_ACTUALIZADO','INTERVENCION','ESTADO_CAMBIADO','CERRADA'))`

### `alerta_administrativa`

Avisos operativos para el administrador

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `tipo` | varchar(40) | No |  |  | USUARIO_BLOQUEADO, ESTUDIANTE_SIN_MATRICULA, DOCENTE_SIN_ASISTENCIA, CURSO_SIN_DOCENTE, AULA_SIN_TUTOR, NOTAS_PENDIENTES_CIERRE |
| `severidad` | varchar(10) | No |  |  | CRITICO, ATENCION o PENDIENTE |
| `titulo` | varchar(150) | No |  |  |  |
| `detalle` | varchar(300) | Sí |  |  |  |
| `entidad` | varchar(60) | Sí |  |  |  |
| `entidad_id` | bigint unsigned | Sí |  |  |  |
| `clave` | varchar(120) | No |  |  | Evita duplicar la misma alerta abierta |
| `estado` | varchar(10) | No |  | `ABIERTA` | ABIERTA o RESUELTA |
| `abierta_unica` | tinyint unsigned | Sí |  |  | (columna calculada) |
| `resuelta_en` | timestamp | Sí |  |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Fecha de registro |
| `updated_at` | timestamp | No |  | `CURRENT_TIMESTAMP` | Última modificación |

**Restricciones:**

- `ck_alerta_admin_estado`: `(estado in ('ABIERTA','RESUELTA'))`
- `ck_alerta_admin_severidad`: `(severidad in ('CRITICO','ATENCION','PENDIENTE'))`
- `ck_alerta_admin_tipo`: `(tipo in ('USUARIO_BLOQUEADO','ESTUDIANTE_SIN_MATRICULA','DOCENTE_SIN_ASISTENCIA','CURSO_SIN_DOCENTE','AULA_SIN_TUTOR','NOTAS_PENDIENTES_CIERRE'))`

### `notificacion`

Notificaciones de la campana

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `id` | bigint unsigned | No | PK |  |  |
| `usuario_id` | bigint unsigned | No | FK → usuario |  |  |
| `tipo` | varchar(40) | No |  |  | NOTA_PUBLICADA, FALTA_REGISTRADA, ACTIVIDAD_POR_VENCER, ALERTA_ASIGNADA, CIERRE_PROXIMO… |
| `titulo` | varchar(150) | No |  |  |  |
| `mensaje` | varchar(400) | No |  |  |  |
| `enlace` | varchar(255) | Sí |  |  | Pantalla a la que lleva |
| `leida_en` | timestamp | Sí |  |  |  |
| `created_at` | timestamp | No |  | `CURRENT_TIMESTAMP` |  |

