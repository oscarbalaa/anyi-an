# Diagrama entidad-relación de AYNI

Generado desde la base de datos con `php artisan ayni:documentar-bd`. GitHub y VS Code muestran el diagrama directamente.

## Seguridad

```mermaid
erDiagram
    usuario ||--o{ usuario : "actualizado_por"
    usuario ||--o{ usuario : "creado_por"
    rol ||--o{ usuario : "rol_id"
    usuario ||--o{ auditoria : "usuario_id"
    usuario ||--o{ recuperacion_password : "usuario_id"
    rol {
        bigint id PK
        varchar codigo UK
        varchar nombre
    }
    usuario {
        bigint id PK
        bigint rol_id FK
        varchar username UK
        varchar email UK
        varchar password_hash
        varchar estado
        tinyint intentos_fallidos
        timestamp bloqueado_hasta
        tinyint debe_cambiar_password
        timestamp ultimo_acceso
        bigint creado_por FK
        bigint actualizado_por FK
        timestamp created_at
        timestamp updated_at
    }
    auditoria {
        bigint id PK
        bigint usuario_id FK
        varchar accion
        varchar entidad
        bigint entidad_id
        json detalle
        varchar ip
        timestamp created_at
    }
    recuperacion_password {
        bigint id PK
        bigint usuario_id FK
        char token_hash UK
        timestamp expira_en
        timestamp usado_en
        timestamp created_at
    }
    captcha_desafio {
        char id PK
        varchar respuesta_hash
        timestamp expira_en
        timestamp usado_en
        varchar ip
        timestamp created_at
    }
```

## Personas

```mermaid
erDiagram
    persona ||--o{ personal_administrativo : "persona_id"
    usuario ||--o{ personal_administrativo : "usuario_id"
    persona ||--o{ docente : "persona_id"
    usuario ||--o{ docente : "usuario_id"
    persona ||--o{ estudiante : "persona_id"
    usuario ||--o{ estudiante : "usuario_id"
    estudiante ||--o{ apoderado : "estudiante_id"
    persona {
        bigint id PK
        varchar tipo_documento
        varchar numero_documento
        varchar nombres
        varchar apellidos
        date fecha_nacimiento
        char sexo
        varchar celular
        varchar correo
        varchar direccion
        timestamp created_at
        timestamp updated_at
    }
    personal_administrativo {
        bigint id PK
        bigint persona_id FK
        bigint usuario_id FK
        varchar cargo
        varchar estado
    }
    docente {
        bigint id PK
        bigint persona_id FK
        bigint usuario_id FK
        varchar titulo
        varchar especialidad
        date fecha_inicio_contrato
        date fecha_fin_contrato
        varchar estado
    }
    estudiante {
        bigint id PK
        bigint persona_id FK
        bigint usuario_id FK
        varchar codigo UK
        date fecha_ingreso
        varchar estado
        varchar origen
    }
    apoderado {
        bigint id PK
        bigint estudiante_id FK
        varchar nombres
        varchar celular
        varchar correo
        varchar parentesco
        tinyint es_principal
        tinyint principal_unico
    }
    usuario {
        bigint id PK
    }
```

## Configuración

```mermaid
erDiagram
    institucion {
        bigint id PK
        varchar nombre
        varchar codigo_modular
        varchar ugel
        varchar direccion
        varchar telefono
        varchar correo
        decimal nota_aprobatoria
        tinyint umbral_asistencia_alto
        tinyint umbral_asistencia_medio
        decimal umbral_promedio_alto
        decimal umbral_promedio_medio
        tinyint umbral_pendientes_alto
        tinyint dias_cierre_bimestre
        timestamp created_at
        timestamp updated_at
    }
    escala_literal {
        bigint id PK
        varchar codigo UK
        varchar descripcion
        decimal nota_minima
        decimal nota_maxima
        tinyint orden
    }
```

## Estructura académica

```mermaid
erDiagram
    usuario ||--o{ subperiodo : "cerrado_por"
    periodo_academico ||--o{ subperiodo : "periodo_id"
    grado ||--o{ aula : "grado_id"
    periodo_academico ||--o{ aula : "periodo_id"
    seccion ||--o{ aula : "seccion_id"
    docente ||--o{ aula : "tutor_docente_id"
    aula ||--o{ coordinacion : "aula_id"
    docente ||--o{ coordinacion : "docente_id"
    grado ||--o{ coordinacion : "grado_id"
    periodo_academico ||--o{ coordinacion : "periodo_id"
    aula ||--o{ matricula : "aula_id"
    periodo_academico ||--o{ matricula : "periodo_id"
    estudiante ||--o{ matricula : "estudiante_id"
    usuario ||--o{ matricula : "registrado_por"
    asignatura ||--o{ curso_aula : "asignatura_id"
    aula ||--o{ curso_aula : "aula_id"
    docente ||--o{ curso_aula : "docente_id"
    curso_aula ||--o{ horario : "curso_aula_id"
    periodo_academico {
        bigint id PK
        varchar nombre UK
        date fecha_inicio
        date fecha_fin
        varchar estado
        tinyint en_curso_unico UK
        timestamp created_at
        timestamp updated_at
    }
    subperiodo {
        bigint id PK
        bigint periodo_id FK
        tinyint numero
        varchar nombre
        date fecha_inicio
        date fecha_fin
        date fecha_cierre_notas
        varchar estado
        timestamp cerrado_en
        bigint cerrado_por FK
    }
    grado {
        bigint id PK
        varchar nivel
        tinyint numero
        varchar nombre
        tinyint orden
    }
    seccion {
        bigint id PK
        varchar nombre UK
    }
    aula {
        bigint id PK
        bigint periodo_id FK
        bigint grado_id FK
        bigint seccion_id FK
        bigint tutor_docente_id FK
        smallint capacidad
        varchar ambiente
    }
    coordinacion {
        bigint id PK
        bigint docente_id FK
        bigint periodo_id FK
        varchar ambito
        bigint aula_id FK
        bigint grado_id FK
        varchar nivel
        timestamp created_at
        timestamp updated_at
    }
    matricula {
        bigint id PK
        bigint estudiante_id FK
        bigint aula_id FK
        bigint periodo_id FK
        date fecha
        varchar estado
        varchar motivo
        bigint registrado_por FK
        timestamp created_at
        timestamp updated_at
    }
    asignatura {
        bigint id PK
        varchar codigo UK
        varchar nombre
        smallint horas_semanales
        varchar estado
    }
    curso_aula {
        bigint id PK
        bigint aula_id FK
        bigint asignatura_id FK
        bigint docente_id FK
    }
    horario {
        bigint id PK
        bigint curso_aula_id FK
        tinyint dia_semana
        time hora_inicio
        time hora_fin
        varchar ambiente
    }
    usuario {
        bigint id PK
    }
    docente {
        bigint id PK
    }
    estudiante {
        bigint id PK
    }
```

## Seguimiento académico

```mermaid
erDiagram
    usuario ||--o{ archivo : "subido_por"
    usuario ||--o{ evaluacion : "creado_por"
    curso_aula ||--o{ evaluacion : "curso_aula_id"
    subperiodo ||--o{ evaluacion : "subperiodo_id"
    tipo_evaluacion ||--o{ evaluacion : "tipo_evaluacion_id"
    archivo ||--o{ evaluacion_material : "archivo_id"
    evaluacion ||--o{ evaluacion_material : "evaluacion_id"
    archivo ||--o{ entrega : "archivo_id"
    evaluacion ||--o{ entrega : "evaluacion_id"
    matricula ||--o{ entrega : "matricula_id"
    evaluacion ||--o{ nota_evaluacion : "evaluacion_id"
    matricula ||--o{ nota_evaluacion : "matricula_id"
    usuario ||--o{ nota_evaluacion : "registrado_por"
    curso_aula ||--o{ calificacion_periodo : "curso_aula_id"
    matricula ||--o{ calificacion_periodo : "matricula_id"
    usuario ||--o{ calificacion_periodo : "publicado_por"
    subperiodo ||--o{ calificacion_periodo : "subperiodo_id"
    curso_aula ||--o{ sesion_clase : "curso_aula_id"
    usuario ||--o{ sesion_clase : "registrado_por"
    subperiodo ||--o{ sesion_clase : "subperiodo_id"
    matricula ||--o{ asistencia : "matricula_id"
    usuario ||--o{ asistencia : "modificado_por"
    usuario ||--o{ asistencia : "registrado_por"
    sesion_clase ||--o{ asistencia : "sesion_clase_id"
    matricula ||--o{ incidencia : "matricula_id"
    usuario ||--o{ incidencia : "registrado_por"
    tipo_incidencia ||--o{ incidencia : "tipo_incidencia_id"
    tipo_evaluacion {
        bigint id PK
        varchar nombre UK
        varchar estado
    }
    archivo {
        bigint id PK
        varchar disco
        varchar ruta
        varchar nombre_original
        varchar tipo_mime
        int tamano_bytes
        char hash_sha256
        bigint subido_por FK
        timestamp created_at
    }
    evaluacion {
        bigint id PK
        bigint curso_aula_id FK
        bigint subperiodo_id FK
        bigint tipo_evaluacion_id FK
        varchar titulo
        text indicaciones
        date fecha_asignacion
        date fecha_limite
        tinyint peso
        bigint creado_por FK
        timestamp created_at
        timestamp updated_at
    }
    evaluacion_material {
        bigint evaluacion_id PK
        bigint archivo_id PK
    }
    entrega {
        bigint id PK
        bigint evaluacion_id FK
        bigint matricula_id FK
        bigint archivo_id FK
        varchar estado
        timestamp entregada_en
        tinyint es_tardia
        timestamp created_at
        timestamp updated_at
    }
    nota_evaluacion {
        bigint id PK
        bigint evaluacion_id FK
        bigint matricula_id FK
        decimal nota
        varchar estado_entrega
        varchar observacion
        bigint registrado_por FK
        timestamp created_at
        timestamp updated_at
    }
    calificacion_periodo {
        bigint id PK
        bigint matricula_id FK
        bigint curso_aula_id FK
        bigint subperiodo_id FK
        decimal promedio
        varchar estado
        timestamp publicado_en
        bigint publicado_por FK
        timestamp cerrado_en
        timestamp created_at
        timestamp updated_at
    }
    sesion_clase {
        bigint id PK
        bigint curso_aula_id FK
        bigint subperiodo_id FK
        date fecha
        varchar tema
        varchar estado
        bigint registrado_por FK
        timestamp created_at
        timestamp updated_at
    }
    asistencia {
        bigint id PK
        bigint sesion_clase_id FK
        bigint matricula_id FK
        char estado
        varchar observacion
        bigint registrado_por FK
        bigint modificado_por FK
        timestamp created_at
        timestamp updated_at
    }
    tipo_incidencia {
        bigint id PK
        varchar nombre UK
        varchar descripcion
    }
    incidencia {
        bigint id PK
        bigint matricula_id FK
        bigint tipo_incidencia_id FK
        bigint registrado_por FK
        date fecha
        varchar gravedad
        varchar descripcion
        timestamp created_at
        timestamp updated_at
    }
    usuario {
        bigint id PK
    }
    curso_aula {
        bigint id PK
    }
    subperiodo {
        bigint id PK
    }
    matricula {
        bigint id PK
    }
```

## Riesgo, alertas y notificaciones

```mermaid
erDiagram
    matricula ||--o{ prediccion_riesgo : "matricula_id"
    modelo_ml ||--o{ prediccion_riesgo : "modelo_id"
    subperiodo ||--o{ prediccion_riesgo : "subperiodo_id"
    prediccion_riesgo ||--o{ factor_riesgo : "prediccion_id"
    prediccion_riesgo ||--o{ recomendacion : "prediccion_id"
    matricula ||--o{ alerta_estudiantil : "matricula_id"
    prediccion_riesgo ||--o{ alerta_estudiantil : "prediccion_id"
    docente ||--o{ alerta_estudiantil : "responsable_docente_id"
    subperiodo ||--o{ alerta_estudiantil : "subperiodo_id"
    alerta_estudiantil ||--o{ intervencion : "alerta_id"
    usuario ||--o{ intervencion : "responsable_id"
    alerta_estudiantil ||--o{ historial_alerta : "alerta_id"
    usuario ||--o{ historial_alerta : "usuario_id"
    usuario ||--o{ notificacion : "usuario_id"
    modelo_ml {
        bigint id PK
        varchar nombre
        varchar version
        varchar algoritmo
        json metricas
        timestamp fecha_entrenamiento
        tinyint activo
        tinyint activo_unico UK
    }
    prediccion_riesgo {
        bigint id PK
        bigint matricula_id FK
        bigint subperiodo_id FK
        bigint modelo_id FK
        timestamp fecha
        decimal probabilidad
        varchar nivel_riesgo
        json features
        text resumen_texto
    }
    factor_riesgo {
        bigint id PK
        bigint prediccion_id FK
        varchar variable
        varchar valor
        decimal contribucion
        varchar descripcion
    }
    recomendacion {
        bigint id PK
        bigint prediccion_id FK
        varchar descripcion
        tinyint prioridad
    }
    alerta_estudiantil {
        bigint id PK
        bigint prediccion_id FK
        bigint matricula_id FK
        bigint subperiodo_id FK
        bigint responsable_docente_id FK
        varchar estado
        varchar justificacion_cambio
        timestamp fecha_creacion
        timestamp fecha_cierre
        tinyint activa_unica
    }
    intervencion {
        bigint id PK
        bigint alerta_id FK
        bigint responsable_id FK
        varchar tipo
        varchar descripcion
        date fecha
        varchar resultado
        timestamp created_at
    }
    historial_alerta {
        bigint id PK
        bigint alerta_id FK
        varchar accion
        varchar detalle
        bigint usuario_id FK
        timestamp fecha
    }
    alerta_administrativa {
        bigint id PK
        varchar tipo
        varchar severidad
        varchar titulo
        varchar detalle
        varchar entidad
        bigint entidad_id
        varchar clave
        varchar estado
        tinyint abierta_unica
        timestamp resuelta_en
        timestamp created_at
        timestamp updated_at
    }
    notificacion {
        bigint id PK
        bigint usuario_id FK
        varchar tipo
        varchar titulo
        varchar mensaje
        varchar enlace
        timestamp leida_en
        timestamp created_at
    }
    matricula {
        bigint id PK
    }
    subperiodo {
        bigint id PK
    }
    docente {
        bigint id PK
    }
    usuario {
        bigint id PK
    }
```

