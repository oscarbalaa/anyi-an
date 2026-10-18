# Base de datos de AYNI

| Archivo | Contenido |
|---|---|
| `diagrama-er.md` | Diagrama entidad-relación por grupos de tablas (Mermaid; GitHub y VS Code lo muestran como imagen) |
| `diccionario.md` | Diccionario de datos: cada tabla con sus campos, tipos, claves, valores por defecto, descripciones y restricciones |
| `ayni_esquema.sql` | Script de creación de todas las tablas (sin datos) |
| `ayni_con_datos_demo.sql` | Script con tablas y datos de demostración, para importar en phpMyAdmin |

Los cuatro archivos se generan con `scripts/equipo/exportar-bd.ps1` a partir de la base real, así nunca quedan desactualizados respecto del código.

## Cómo está hecha

- **Fuente oficial:** las migraciones de Laravel en `backend/database/migrations`. Son el script v1 del grupo, pasado de PostgreSQL a MySQL con las decisiones del Módulo 0.
- **Datos por defecto:**
  - catálogos (`CatalogosSeeder`);
  - colegio de demostración con personas ficticias (`DemostracionSeeder`). Sus fechas se calculan a partir del día en que se instala, así siempre hay un bimestre en curso.
- **Reglas que la propia BD hace cumplir** (además del backend):
  - notas de 0 a 20;
  - un solo periodo en curso;
  - una matrícula por estudiante y año, en un aula de ese mismo año;
  - un solo apoderado principal;
  - una sola alerta activa por estudiante y bimestre;
  - estados válidos, fechas coherentes, etc.

## Cambios respecto del script v1 (PostgreSQL)

| Script v1 | MySQL / AYNI |
|---|---|
| `GENERATED ALWAYS AS IDENTITY` | `BIGINT UNSIGNED AUTO_INCREMENT` |
| `TIMESTAMPTZ` | `TIMESTAMP` en hora de Lima (UTC−5) |
| `JSONB` | `JSON` |
| Índices únicos parciales (`WHERE estado = 'EN_CURSO'`, `WHERE es_principal`, `WHERE activo`) | Columna calculada (`IF(..., 1, NULL)`) + índice único |
| `rol`: ADMIN, DOCENTE, ESTUDIANTE | Igual; el coordinador es un docente con registro en `coordinacion` (D1) |
| `grado.nivel` con INICIAL | Solo PRIMARIA y SECUNDARIA (D6) |
| `evaluacion.orden` (máximo 8) y `peso` decimal | Sin límite de evaluaciones; `peso` entero relativo 1-10 |
| — | **Tablas nuevas:** `auditoria`, `recuperacion_password`, `captcha_desafio`, `institucion`, `escala_literal`, `coordinacion`, `archivo`, `evaluacion_material`, `entrega`, `alerta_administrativa`, `notificacion` |
| — | **Columnas nuevas:** bloqueo y contraseña temporal en `usuario`; fecha de cierre y estado en `subperiodo`; motivo en `matricula`; estado en `sesion_clase`; publicación en `calificacion_periodo` |
