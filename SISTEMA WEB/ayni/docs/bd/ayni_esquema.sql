-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: ayni
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `alerta_administrativa`
--

DROP TABLE IF EXISTS `alerta_administrativa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alerta_administrativa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'USUARIO_BLOQUEADO, ESTUDIANTE_SIN_MATRICULA, DOCENTE_SIN_ASISTENCIA, CURSO_SIN_DOCENTE, AULA_SIN_TUTOR, NOTAS_PENDIENTES_CIERRE',
  `severidad` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CRITICO, ATENCION o PENDIENTE',
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entidad` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entidad_id` bigint unsigned DEFAULT NULL,
  `clave` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Evita duplicar la misma alerta abierta',
  `estado` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ABIERTA' COMMENT 'ABIERTA o RESUELTA',
  `abierta_unica` tinyint unsigned GENERATED ALWAYS AS (if((`estado` = _utf8mb4'ABIERTA'),1,NULL)) STORED,
  `resuelta_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_alerta_admin_abierta` (`clave`,`abierta_unica`),
  CONSTRAINT `ck_alerta_admin_estado` CHECK ((`estado` in (_utf8mb4'ABIERTA',_utf8mb4'RESUELTA'))),
  CONSTRAINT `ck_alerta_admin_severidad` CHECK ((`severidad` in (_utf8mb4'CRITICO',_utf8mb4'ATENCION',_utf8mb4'PENDIENTE'))),
  CONSTRAINT `ck_alerta_admin_tipo` CHECK ((`tipo` in (_utf8mb4'USUARIO_BLOQUEADO',_utf8mb4'ESTUDIANTE_SIN_MATRICULA',_utf8mb4'DOCENTE_SIN_ASISTENCIA',_utf8mb4'CURSO_SIN_DOCENTE',_utf8mb4'AULA_SIN_TUTOR',_utf8mb4'NOTAS_PENDIENTES_CIERRE')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Avisos operativos para el administrador';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alerta_estudiantil`
--

DROP TABLE IF EXISTS `alerta_estudiantil`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alerta_estudiantil` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prediccion_id` bigint unsigned NOT NULL,
  `matricula_id` bigint unsigned NOT NULL,
  `subperiodo_id` bigint unsigned NOT NULL,
  `responsable_docente_id` bigint unsigned NOT NULL,
  `estado` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NUEVA' COMMENT 'NUEVA, EN_SEGUIMIENTO, CERRADA o DESCARTADA',
  `justificacion_cambio` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_cierre` timestamp NULL DEFAULT NULL,
  `activa_unica` tinyint unsigned GENERATED ALWAYS AS (if((`estado` in (_utf8mb4'NUEVA',_utf8mb4'EN_SEGUIMIENTO')),1,NULL)) STORED COMMENT 'Calculada: una alerta activa por estudiante y bimestre',
  PRIMARY KEY (`id`),
  UNIQUE KEY `alerta_estudiantil_prediccion_id_unique` (`prediccion_id`),
  UNIQUE KEY `ux_alerta_activa` (`matricula_id`,`subperiodo_id`,`activa_unica`),
  KEY `alerta_estudiantil_subperiodo_id_foreign` (`subperiodo_id`),
  KEY `ix_alerta_responsable` (`responsable_docente_id`,`estado`),
  CONSTRAINT `alerta_estudiantil_matricula_id_foreign` FOREIGN KEY (`matricula_id`) REFERENCES `matricula` (`id`),
  CONSTRAINT `alerta_estudiantil_prediccion_id_foreign` FOREIGN KEY (`prediccion_id`) REFERENCES `prediccion_riesgo` (`id`),
  CONSTRAINT `alerta_estudiantil_responsable_docente_id_foreign` FOREIGN KEY (`responsable_docente_id`) REFERENCES `docente` (`id`),
  CONSTRAINT `alerta_estudiantil_subperiodo_id_foreign` FOREIGN KEY (`subperiodo_id`) REFERENCES `subperiodo` (`id`),
  CONSTRAINT `ck_alerta_cierre` CHECK (((`fecha_cierre` is null) or (`fecha_cierre` >= `fecha_creacion`))),
  CONSTRAINT `ck_alerta_estado` CHECK ((`estado` in (_utf8mb4'NUEVA',_utf8mb4'EN_SEGUIMIENTO',_utf8mb4'CERRADA',_utf8mb4'DESCARTADA')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alerta de riesgo académico con seguimiento';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `apoderado`
--

DROP TABLE IF EXISTS `apoderado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `apoderado` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `estudiante_id` bigint unsigned NOT NULL,
  `nombres` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre completo',
  `celular` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parentesco` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Padre, Madre, Tutor legal, Abuelo(a), Tío(a), Hermano(a), Otro',
  `es_principal` tinyint(1) NOT NULL DEFAULT '0',
  `principal_unico` tinyint unsigned GENERATED ALWAYS AS (if(`es_principal`,1,NULL)) STORED COMMENT 'Calculada: 1 si es principal',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_apoderado_principal` (`estudiante_id`,`principal_unico`),
  CONSTRAINT `apoderado_estudiante_id_foreign` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiante` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=247 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Apoderados del estudiante (sin acceso al sistema en la Unidad 1)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `archivo`
--

DROP TABLE IF EXISTS `archivo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `archivo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `disco` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `ruta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_mime` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamano_bytes` int unsigned NOT NULL,
  `hash_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subido_por` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `archivo_subido_por_foreign` (`subido_por`),
  CONSTRAINT `archivo_subido_por_foreign` FOREIGN KEY (`subido_por`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archivos subidos (entregas y material de apoyo); se guardan fuera de la carpeta pública';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asignatura`
--

DROP TABLE IF EXISTS `asignatura`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignatura` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `horas_semanales` smallint unsigned DEFAULT NULL,
  `estado` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id`),
  UNIQUE KEY `asignatura_codigo_unique` (`codigo`),
  CONSTRAINT `ck_asignatura_estado` CHECK ((`estado` in (_utf8mb4'ACTIVO',_utf8mb4'INACTIVO'))),
  CONSTRAINT `ck_asignatura_horas` CHECK (((`horas_semanales` is null) or (`horas_semanales` between 1 and 20)))
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asignaturas o cursos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asistencia`
--

DROP TABLE IF EXISTS `asistencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asistencia` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sesion_clase_id` bigint unsigned NOT NULL,
  `matricula_id` bigint unsigned NOT NULL,
  `estado` char(1) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'P presente, F falta, T tardanza, J justificada',
  `observacion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registrado_por` bigint unsigned DEFAULT NULL,
  `modificado_por` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_asistencia` (`sesion_clase_id`,`matricula_id`),
  KEY `asistencia_registrado_por_foreign` (`registrado_por`),
  KEY `asistencia_modificado_por_foreign` (`modificado_por`),
  KEY `ix_asistencia_matricula` (`matricula_id`),
  CONSTRAINT `asistencia_matricula_id_foreign` FOREIGN KEY (`matricula_id`) REFERENCES `matricula` (`id`),
  CONSTRAINT `asistencia_modificado_por_foreign` FOREIGN KEY (`modificado_por`) REFERENCES `usuario` (`id`),
  CONSTRAINT `asistencia_registrado_por_foreign` FOREIGN KEY (`registrado_por`) REFERENCES `usuario` (`id`),
  CONSTRAINT `asistencia_sesion_clase_id_foreign` FOREIGN KEY (`sesion_clase_id`) REFERENCES `sesion_clase` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_asistencia_estado` CHECK ((`estado` in (_utf8mb4'P',_utf8mb4'F',_utf8mb4'T',_utf8mb4'J')))
) ENGINE=InnoDB AUTO_INCREMENT=57425 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asistencia de cada estudiante en cada clase';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned DEFAULT NULL,
  `accion` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CREAR, ACTUALIZAR, ELIMINAR, DESBLOQUEAR, CERRAR_BIMESTRE…',
  `entidad` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tabla afectada',
  `entidad_id` bigint unsigned DEFAULT NULL,
  `detalle` json DEFAULT NULL COMMENT 'Valores antes/después',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `auditoria_usuario_id_foreign` (`usuario_id`),
  KEY `ix_auditoria_entidad` (`entidad`,`entidad_id`),
  CONSTRAINT `auditoria_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de quién cambió qué y cuándo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `aula`
--

DROP TABLE IF EXISTS `aula`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aula` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `periodo_id` bigint unsigned NOT NULL,
  `grado_id` bigint unsigned NOT NULL,
  `seccion_id` bigint unsigned NOT NULL,
  `tutor_docente_id` bigint unsigned DEFAULT NULL,
  `capacidad` smallint unsigned NOT NULL DEFAULT '30',
  `ambiente` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ej. Aula 12',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_aula_grado_seccion` (`periodo_id`,`grado_id`,`seccion_id`),
  UNIQUE KEY `ux_aula_periodo` (`id`,`periodo_id`),
  KEY `aula_grado_id_foreign` (`grado_id`),
  KEY `aula_seccion_id_foreign` (`seccion_id`),
  KEY `aula_tutor_docente_id_foreign` (`tutor_docente_id`),
  CONSTRAINT `aula_grado_id_foreign` FOREIGN KEY (`grado_id`) REFERENCES `grado` (`id`),
  CONSTRAINT `aula_periodo_id_foreign` FOREIGN KEY (`periodo_id`) REFERENCES `periodo_academico` (`id`),
  CONSTRAINT `aula_seccion_id_foreign` FOREIGN KEY (`seccion_id`) REFERENCES `seccion` (`id`),
  CONSTRAINT `aula_tutor_docente_id_foreign` FOREIGN KEY (`tutor_docente_id`) REFERENCES `docente` (`id`),
  CONSTRAINT `ck_aula_capacidad` CHECK ((`capacidad` between 1 and 60))
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grado + sección en un periodo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calificacion_periodo`
--

DROP TABLE IF EXISTS `calificacion_periodo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calificacion_periodo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `matricula_id` bigint unsigned NOT NULL,
  `curso_aula_id` bigint unsigned NOT NULL,
  `subperiodo_id` bigint unsigned NOT NULL,
  `promedio` decimal(4,2) DEFAULT NULL,
  `estado` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BORRADOR' COMMENT 'BORRADOR, PUBLICADA o CERRADA',
  `publicado_en` timestamp NULL DEFAULT NULL,
  `publicado_por` bigint unsigned DEFAULT NULL,
  `cerrado_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_calificacion` (`matricula_id`,`curso_aula_id`,`subperiodo_id`),
  KEY `calificacion_periodo_curso_aula_id_foreign` (`curso_aula_id`),
  KEY `calificacion_periodo_subperiodo_id_foreign` (`subperiodo_id`),
  KEY `calificacion_periodo_publicado_por_foreign` (`publicado_por`),
  CONSTRAINT `calificacion_periodo_curso_aula_id_foreign` FOREIGN KEY (`curso_aula_id`) REFERENCES `curso_aula` (`id`),
  CONSTRAINT `calificacion_periodo_matricula_id_foreign` FOREIGN KEY (`matricula_id`) REFERENCES `matricula` (`id`),
  CONSTRAINT `calificacion_periodo_publicado_por_foreign` FOREIGN KEY (`publicado_por`) REFERENCES `usuario` (`id`),
  CONSTRAINT `calificacion_periodo_subperiodo_id_foreign` FOREIGN KEY (`subperiodo_id`) REFERENCES `subperiodo` (`id`),
  CONSTRAINT `ck_calificacion_estado` CHECK ((`estado` in (_utf8mb4'BORRADOR',_utf8mb4'PUBLICADA',_utf8mb4'CERRADA'))),
  CONSTRAINT `ck_calificacion_promedio` CHECK (((`promedio` is null) or (`promedio` between 0 and 20)))
) ENGINE=InnoDB AUTO_INCREMENT=2665 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Promedio del estudiante por curso y bimestre';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `captcha_desafio`
--

DROP TABLE IF EXISTS `captcha_desafio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `captcha_desafio` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `respuesta_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Respuesta cifrada; nunca se guarda en claro',
  `expira_en` timestamp NOT NULL,
  `usado_en` timestamp NULL DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_captcha_expira` (`expira_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Captchas propios del login (un solo uso, 5 minutos)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `coordinacion`
--

DROP TABLE IF EXISTS `coordinacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coordinacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `docente_id` bigint unsigned NOT NULL,
  `periodo_id` bigint unsigned NOT NULL,
  `ambito` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'AULA, GRADO, NIVEL o INSTITUCION',
  `aula_id` bigint unsigned DEFAULT NULL,
  `grado_id` bigint unsigned DEFAULT NULL,
  `nivel` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  KEY `coordinacion_periodo_id_foreign` (`periodo_id`),
  KEY `coordinacion_aula_id_foreign` (`aula_id`),
  KEY `coordinacion_grado_id_foreign` (`grado_id`),
  KEY `ix_coordinacion_docente` (`docente_id`,`periodo_id`),
  CONSTRAINT `coordinacion_aula_id_foreign` FOREIGN KEY (`aula_id`) REFERENCES `aula` (`id`),
  CONSTRAINT `coordinacion_docente_id_foreign` FOREIGN KEY (`docente_id`) REFERENCES `docente` (`id`),
  CONSTRAINT `coordinacion_grado_id_foreign` FOREIGN KEY (`grado_id`) REFERENCES `grado` (`id`),
  CONSTRAINT `coordinacion_periodo_id_foreign` FOREIGN KEY (`periodo_id`) REFERENCES `periodo_academico` (`id`),
  CONSTRAINT `ck_coordinacion_ambito` CHECK ((((`ambito` = _utf8mb4'AULA') and (`aula_id` is not null) and (`grado_id` is null) and (`nivel` is null)) or ((`ambito` = _utf8mb4'GRADO') and (`grado_id` is not null) and (`aula_id` is null) and (`nivel` is null)) or ((`ambito` = _utf8mb4'NIVEL') and (`nivel` in (_utf8mb4'PRIMARIA',_utf8mb4'SECUNDARIA')) and (`aula_id` is null) and (`grado_id` is null)) or ((`ambito` = _utf8mb4'INSTITUCION') and (`aula_id` is null) and (`grado_id` is null) and (`nivel` is null))))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Docentes que coordinan un aula, un grado, un nivel o todo el colegio';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `curso_aula`
--

DROP TABLE IF EXISTS `curso_aula`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `curso_aula` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aula_id` bigint unsigned NOT NULL,
  `asignatura_id` bigint unsigned NOT NULL,
  `docente_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_curso_aula` (`aula_id`,`asignatura_id`),
  KEY `curso_aula_asignatura_id_foreign` (`asignatura_id`),
  KEY `ix_curso_aula_docente` (`docente_id`),
  CONSTRAINT `curso_aula_asignatura_id_foreign` FOREIGN KEY (`asignatura_id`) REFERENCES `asignatura` (`id`),
  CONSTRAINT `curso_aula_aula_id_foreign` FOREIGN KEY (`aula_id`) REFERENCES `aula` (`id`),
  CONSTRAINT `curso_aula_docente_id_foreign` FOREIGN KEY (`docente_id`) REFERENCES `docente` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Carga académica: asignatura dictada en un aula por un docente';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `docente`
--

DROP TABLE IF EXISTS `docente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `docente` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint unsigned NOT NULL,
  `usuario_id` bigint unsigned NOT NULL,
  `titulo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `especialidad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_inicio_contrato` date DEFAULT NULL,
  `fecha_fin_contrato` date DEFAULT NULL,
  `estado` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO' COMMENT 'ACTIVO, INACTIVO o LICENCIA',
  PRIMARY KEY (`id`),
  UNIQUE KEY `docente_persona_id_unique` (`persona_id`),
  UNIQUE KEY `docente_usuario_id_unique` (`usuario_id`),
  CONSTRAINT `docente_persona_id_foreign` FOREIGN KEY (`persona_id`) REFERENCES `persona` (`id`),
  CONSTRAINT `docente_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`),
  CONSTRAINT `ck_docente_contrato` CHECK (((`fecha_fin_contrato` is null) or (`fecha_fin_contrato` >= `fecha_inicio_contrato`))),
  CONSTRAINT `ck_docente_estado` CHECK ((`estado` in (_utf8mb4'ACTIVO',_utf8mb4'INACTIVO',_utf8mb4'LICENCIA')))
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Docentes (rol DOCENTE; algunos también coordinan)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `entrega`
--

DROP TABLE IF EXISTS `entrega`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `entrega` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `evaluacion_id` bigint unsigned NOT NULL,
  `matricula_id` bigint unsigned NOT NULL,
  `archivo_id` bigint unsigned DEFAULT NULL,
  `estado` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BORRADOR' COMMENT 'BORRADOR o ENTREGADA',
  `entregada_en` timestamp NULL DEFAULT NULL,
  `es_tardia` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_entrega` (`evaluacion_id`,`matricula_id`),
  KEY `entrega_matricula_id_foreign` (`matricula_id`),
  KEY `entrega_archivo_id_foreign` (`archivo_id`),
  CONSTRAINT `entrega_archivo_id_foreign` FOREIGN KEY (`archivo_id`) REFERENCES `archivo` (`id`),
  CONSTRAINT `entrega_evaluacion_id_foreign` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluacion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `entrega_matricula_id_foreign` FOREIGN KEY (`matricula_id`) REFERENCES `matricula` (`id`),
  CONSTRAINT `ck_entrega_estado` CHECK (((`estado` in (_utf8mb4'BORRADOR',_utf8mb4'ENTREGADA')) and ((`estado` = _utf8mb4'BORRADOR') or (`entregada_en` is not null))))
) ENGINE=InnoDB AUTO_INCREMENT=10720 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entrega de un estudiante para una evaluación';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `escala_literal`
--

DROP TABLE IF EXISTS `escala_literal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `escala_literal` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'AD, A, B o C',
  `descripcion` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota_minima` decimal(4,2) NOT NULL,
  `nota_maxima` decimal(4,2) NOT NULL,
  `orden` tinyint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `escala_literal_codigo_unique` (`codigo`),
  CONSTRAINT `ck_escala_rango` CHECK (((`nota_minima` >= 0) and (`nota_maxima` <= 20) and (`nota_minima` <= `nota_maxima`)))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Equivalencia de la nota vigesimal a AD/A/B/C (solo para mostrar)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estudiante`
--

DROP TABLE IF EXISTS `estudiante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estudiante` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint unsigned NOT NULL,
  `usuario_id` bigint unsigned NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'E-AAAA-NNN; también es su usuario',
  `fecha_ingreso` date DEFAULT NULL,
  `estado` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO' COMMENT 'ACTIVO, RETIRADO, TRASLADADO o EGRESADO',
  `origen` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL' COMMENT 'MANUAL o EXCEL',
  PRIMARY KEY (`id`),
  UNIQUE KEY `estudiante_persona_id_unique` (`persona_id`),
  UNIQUE KEY `estudiante_usuario_id_unique` (`usuario_id`),
  UNIQUE KEY `estudiante_codigo_unique` (`codigo`),
  CONSTRAINT `estudiante_persona_id_foreign` FOREIGN KEY (`persona_id`) REFERENCES `persona` (`id`),
  CONSTRAINT `estudiante_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`),
  CONSTRAINT `ck_estudiante_estado` CHECK ((`estado` in (_utf8mb4'ACTIVO',_utf8mb4'RETIRADO',_utf8mb4'TRASLADADO',_utf8mb4'EGRESADO'))),
  CONSTRAINT `ck_estudiante_origen` CHECK ((`origen` in (_utf8mb4'MANUAL',_utf8mb4'EXCEL')))
) ENGINE=InnoDB AUTO_INCREMENT=153 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Estudiantes (rol ESTUDIANTE)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluacion`
--

DROP TABLE IF EXISTS `evaluacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evaluacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `curso_aula_id` bigint unsigned NOT NULL,
  `subperiodo_id` bigint unsigned NOT NULL,
  `tipo_evaluacion_id` bigint unsigned NOT NULL,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `indicaciones` text COLLATE utf8mb4_unicode_ci,
  `fecha_asignacion` date NOT NULL,
  `fecha_limite` date NOT NULL COMMENT 'Se puede entregar hasta las 11:59 p. m. de este día',
  `peso` tinyint unsigned NOT NULL DEFAULT '1' COMMENT 'Peso relativo (1 a 10) en el promedio',
  `requiere_entrega` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'El estudiante sube un archivo',
  `creado_por` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  KEY `evaluacion_tipo_evaluacion_id_foreign` (`tipo_evaluacion_id`),
  KEY `evaluacion_creado_por_foreign` (`creado_por`),
  KEY `ix_evaluacion_curso` (`curso_aula_id`,`subperiodo_id`),
  KEY `ix_evaluacion_subperiodo` (`subperiodo_id`),
  CONSTRAINT `evaluacion_creado_por_foreign` FOREIGN KEY (`creado_por`) REFERENCES `usuario` (`id`),
  CONSTRAINT `evaluacion_curso_aula_id_foreign` FOREIGN KEY (`curso_aula_id`) REFERENCES `curso_aula` (`id`),
  CONSTRAINT `evaluacion_subperiodo_id_foreign` FOREIGN KEY (`subperiodo_id`) REFERENCES `subperiodo` (`id`),
  CONSTRAINT `evaluacion_tipo_evaluacion_id_foreign` FOREIGN KEY (`tipo_evaluacion_id`) REFERENCES `tipo_evaluacion` (`id`),
  CONSTRAINT `ck_evaluacion_fechas` CHECK ((`fecha_limite` >= `fecha_asignacion`)),
  CONSTRAINT `ck_evaluacion_peso` CHECK ((`peso` between 1 and 10))
) ENGINE=InnoDB AUTO_INCREMENT=1441 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Evaluaciones o actividades de un curso en un bimestre';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluacion_material`
--

DROP TABLE IF EXISTS `evaluacion_material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evaluacion_material` (
  `evaluacion_id` bigint unsigned NOT NULL,
  `archivo_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`evaluacion_id`,`archivo_id`),
  KEY `evaluacion_material_archivo_id_foreign` (`archivo_id`),
  CONSTRAINT `evaluacion_material_archivo_id_foreign` FOREIGN KEY (`archivo_id`) REFERENCES `archivo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluacion_material_evaluacion_id_foreign` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluacion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Material de apoyo de una evaluación';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `factor_riesgo`
--

DROP TABLE IF EXISTS `factor_riesgo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `factor_riesgo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prediccion_id` bigint unsigned NOT NULL,
  `variable` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contribucion` decimal(8,4) NOT NULL COMMENT 'Positiva sube el riesgo; negativa lo baja',
  `descripcion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_factor_prediccion` (`prediccion_id`),
  CONSTRAINT `factor_riesgo_prediccion_id_foreign` FOREIGN KEY (`prediccion_id`) REFERENCES `prediccion_riesgo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Factores que explican la predicción (reglas activadas o valores SHAP)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `grado`
--

DROP TABLE IF EXISTS `grado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grado` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nivel` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PRIMARIA o SECUNDARIA',
  `numero` tinyint unsigned NOT NULL,
  `nombre` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej. Tercero',
  `orden` tinyint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_grado_nivel_numero` (`nivel`,`numero`),
  CONSTRAINT `ck_grado_nivel` CHECK ((`nivel` in (_utf8mb4'PRIMARIA',_utf8mb4'SECUNDARIA'))),
  CONSTRAINT `ck_grado_numero` CHECK (((`numero` >= 1) and (((`nivel` = _utf8mb4'PRIMARIA') and (`numero` <= 6)) or ((`nivel` = _utf8mb4'SECUNDARIA') and (`numero` <= 5)))))
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grados de primaria (1.º-6.º) y secundaria (1.º-5.º)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `historial_alerta`
--

DROP TABLE IF EXISTS `historial_alerta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_alerta` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `alerta_id` bigint unsigned NOT NULL,
  `accion` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` bigint unsigned DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `historial_alerta_usuario_id_foreign` (`usuario_id`),
  KEY `ix_historial_alerta` (`alerta_id`,`fecha`),
  CONSTRAINT `historial_alerta_alerta_id_foreign` FOREIGN KEY (`alerta_id`) REFERENCES `alerta_estudiantil` (`id`) ON DELETE CASCADE,
  CONSTRAINT `historial_alerta_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`),
  CONSTRAINT `ck_historial_accion` CHECK ((`accion` in (_utf8mb4'GENERADA',_utf8mb4'ASIGNADA_DOCENTE_AULA',_utf8mb4'REASIGNADA',_utf8mb4'NIVEL_ACTUALIZADO',_utf8mb4'INTERVENCION',_utf8mb4'ESTADO_CAMBIADO',_utf8mb4'CERRADA')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de cambios de una alerta';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `horario`
--

DROP TABLE IF EXISTS `horario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `horario` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `curso_aula_id` bigint unsigned NOT NULL,
  `dia_semana` tinyint unsigned NOT NULL COMMENT '1 = lunes … 7 = domingo',
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `ambiente` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `horario_curso_aula_id_foreign` (`curso_aula_id`),
  CONSTRAINT `horario_curso_aula_id_foreign` FOREIGN KEY (`curso_aula_id`) REFERENCES `curso_aula` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_horario_dia` CHECK ((`dia_semana` between 1 and 7)),
  CONSTRAINT `ck_horario_horas` CHECK ((`hora_fin` > `hora_inicio`))
) ENGINE=InnoDB AUTO_INCREMENT=157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bloques semanales de cada curso';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `incidencia`
--

DROP TABLE IF EXISTS `incidencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `incidencia` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `matricula_id` bigint unsigned NOT NULL,
  `tipo_incidencia_id` bigint unsigned NOT NULL,
  `registrado_por` bigint unsigned NOT NULL,
  `fecha` date NOT NULL,
  `gravedad` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'LEVE, MODERADA o GRAVE',
  `descripcion` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  KEY `incidencia_tipo_incidencia_id_foreign` (`tipo_incidencia_id`),
  KEY `incidencia_registrado_por_foreign` (`registrado_por`),
  KEY `ix_incidencia_matricula` (`matricula_id`),
  CONSTRAINT `incidencia_matricula_id_foreign` FOREIGN KEY (`matricula_id`) REFERENCES `matricula` (`id`),
  CONSTRAINT `incidencia_registrado_por_foreign` FOREIGN KEY (`registrado_por`) REFERENCES `usuario` (`id`),
  CONSTRAINT `incidencia_tipo_incidencia_id_foreign` FOREIGN KEY (`tipo_incidencia_id`) REFERENCES `tipo_incidencia` (`id`),
  CONSTRAINT `ck_incidencia_gravedad` CHECK ((`gravedad` in (_utf8mb4'LEVE',_utf8mb4'MODERADA',_utf8mb4'GRAVE')))
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Incidencias registradas a un estudiante';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `institucion`
--

DROP TABLE IF EXISTS `institucion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `institucion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_modular` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ugel` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nota_aprobatoria` decimal(4,2) NOT NULL DEFAULT '11.00' COMMENT 'Promedio mínimo para aprobar',
  `umbral_asistencia_alto` tinyint unsigned NOT NULL DEFAULT '85' COMMENT '% de asistencia por debajo del cual el riesgo es ALTO',
  `umbral_asistencia_medio` tinyint unsigned NOT NULL DEFAULT '90' COMMENT '% de asistencia por debajo del cual el riesgo es MEDIO',
  `umbral_promedio_alto` decimal(4,2) NOT NULL DEFAULT '11.00' COMMENT 'Promedio por debajo del cual (con pendientes) el riesgo es ALTO',
  `umbral_promedio_medio` decimal(4,2) NOT NULL DEFAULT '12.00' COMMENT 'Promedio por debajo del cual el riesgo es MEDIO',
  `umbral_pendientes_alto` tinyint unsigned NOT NULL DEFAULT '3' COMMENT 'Entregas pendientes que, con promedio bajo, dan riesgo ALTO',
  `dias_cierre_bimestre` tinyint unsigned NOT NULL DEFAULT '7' COMMENT 'Días después del fin del bimestre para la fecha de cierre por defecto',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  CONSTRAINT `ck_institucion_asistencia` CHECK (((`umbral_asistencia_alto` < `umbral_asistencia_medio`) and (`umbral_asistencia_medio` <= 100))),
  CONSTRAINT `ck_institucion_nota` CHECK ((`nota_aprobatoria` between 0 and 20)),
  CONSTRAINT `ck_institucion_promedio` CHECK (((`umbral_promedio_alto` <= `umbral_promedio_medio`) and (`umbral_promedio_medio` <= 20)))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Datos y parámetros del colegio (una sola fila)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `intervencion`
--

DROP TABLE IF EXISTS `intervencion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `intervencion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `alerta_id` bigint unsigned NOT NULL,
  `responsable_id` bigint unsigned NOT NULL,
  `tipo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` date NOT NULL,
  `resultado` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `intervencion_alerta_id_foreign` (`alerta_id`),
  KEY `intervencion_responsable_id_foreign` (`responsable_id`),
  CONSTRAINT `intervencion_alerta_id_foreign` FOREIGN KEY (`alerta_id`) REFERENCES `alerta_estudiantil` (`id`) ON DELETE CASCADE,
  CONSTRAINT `intervencion_responsable_id_foreign` FOREIGN KEY (`responsable_id`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Acciones realizadas para atender una alerta';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `matricula`
--

DROP TABLE IF EXISTS `matricula`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matricula` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `estudiante_id` bigint unsigned NOT NULL,
  `aula_id` bigint unsigned NOT NULL,
  `periodo_id` bigint unsigned NOT NULL,
  `fecha` date NOT NULL,
  `estado` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA' COMMENT 'ACTIVA, RETIRADA o ANULADA',
  `motivo` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Motivo del retiro o la anulación',
  `registrado_por` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_matricula_estudiante_periodo` (`estudiante_id`,`periodo_id`),
  KEY `matricula_registrado_por_foreign` (`registrado_por`),
  KEY `ix_matricula_aula` (`aula_id`),
  KEY `fk_matricula_aula_periodo` (`aula_id`,`periodo_id`),
  KEY `matricula_periodo_id_foreign` (`periodo_id`),
  CONSTRAINT `fk_matricula_aula_periodo` FOREIGN KEY (`aula_id`, `periodo_id`) REFERENCES `aula` (`id`, `periodo_id`),
  CONSTRAINT `matricula_estudiante_id_foreign` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiante` (`id`),
  CONSTRAINT `matricula_periodo_id_foreign` FOREIGN KEY (`periodo_id`) REFERENCES `periodo_academico` (`id`),
  CONSTRAINT `matricula_registrado_por_foreign` FOREIGN KEY (`registrado_por`) REFERENCES `usuario` (`id`),
  CONSTRAINT `ck_matricula_estado` CHECK ((`estado` in (_utf8mb4'ACTIVA',_utf8mb4'RETIRADA',_utf8mb4'ANULADA')))
) ENGINE=InnoDB AUTO_INCREMENT=149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Matrícula del estudiante en un aula del periodo (una por año)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `modelo_ml`
--

DROP TABLE IF EXISTS `modelo_ml`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modelo_ml` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `algoritmo` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metricas` json DEFAULT NULL,
  `fecha_entrenamiento` timestamp NULL DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '0',
  `activo_unico` tinyint unsigned GENERATED ALWAYS AS (if(`activo`,1,NULL)) STORED COMMENT 'Calculada: un solo modelo activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_modelo_version` (`nombre`,`version`),
  UNIQUE KEY `ux_modelo_activo` (`activo_unico`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Modelo que calcula el riesgo: reglas-v0 (Unidad 1) o ML (Unidad 2)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nota_evaluacion`
--

DROP TABLE IF EXISTS `nota_evaluacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nota_evaluacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `evaluacion_id` bigint unsigned NOT NULL,
  `matricula_id` bigint unsigned NOT NULL,
  `nota` decimal(4,2) DEFAULT NULL COMMENT '0 a 20; NULL = aún sin calificar',
  `estado_entrega` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE, ENTREGADO, TARDIO o NO_ENTREGADO',
  `observacion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registrado_por` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_nota_evaluacion` (`evaluacion_id`,`matricula_id`),
  KEY `nota_evaluacion_registrado_por_foreign` (`registrado_por`),
  KEY `ix_nota_matricula` (`matricula_id`),
  CONSTRAINT `nota_evaluacion_evaluacion_id_foreign` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluacion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nota_evaluacion_matricula_id_foreign` FOREIGN KEY (`matricula_id`) REFERENCES `matricula` (`id`),
  CONSTRAINT `nota_evaluacion_registrado_por_foreign` FOREIGN KEY (`registrado_por`) REFERENCES `usuario` (`id`),
  CONSTRAINT `ck_nota_estado_entrega` CHECK ((`estado_entrega` in (_utf8mb4'PENDIENTE',_utf8mb4'ENTREGADO',_utf8mb4'TARDIO',_utf8mb4'NO_ENTREGADO'))),
  CONSTRAINT `ck_nota_rango` CHECK (((`nota` is null) or (`nota` between 0 and 20)))
) ENGINE=InnoDB AUTO_INCREMENT=14209 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nota de cada estudiante en cada evaluación';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificacion`
--

DROP TABLE IF EXISTS `notificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'NOTA_PUBLICADA, FALTA_REGISTRADA, ACTIVIDAD_POR_VENCER, ALERTA_ASIGNADA, CIERRE_PROXIMO…',
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` varchar(400) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enlace` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pantalla a la que lleva',
  `leida_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_notificacion_usuario` (`usuario_id`,`leida_en`),
  CONSTRAINT `notificacion_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notificaciones de la campana';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `periodo_academico`
--

DROP TABLE IF EXISTS `periodo_academico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodo_academico` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej. 2026',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLANIFICADO' COMMENT 'PLANIFICADO, EN_CURSO o CERRADO',
  `en_curso_unico` tinyint unsigned GENERATED ALWAYS AS (if((`estado` = _utf8mb4'EN_CURSO'),1,NULL)) STORED COMMENT 'Calculada: garantiza un solo periodo en curso',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `periodo_academico_nombre_unique` (`nombre`),
  UNIQUE KEY `ux_periodo_en_curso` (`en_curso_unico`),
  CONSTRAINT `ck_periodo_estado` CHECK ((`estado` in (_utf8mb4'PLANIFICADO',_utf8mb4'EN_CURSO',_utf8mb4'CERRADO'))),
  CONSTRAINT `ck_periodo_fechas` CHECK ((`fecha_fin` > `fecha_inicio`))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Año escolar';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `persona`
--

DROP TABLE IF EXISTS `persona`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo_documento` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DNI' COMMENT 'DNI, CE o PASAPORTE',
  `numero_documento` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `sexo` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'M o F',
  `celular` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_persona_documento` (`tipo_documento`,`numero_documento`),
  KEY `ix_persona_nombre` (`apellidos`,`nombres`),
  CONSTRAINT `ck_persona_sexo` CHECK (((`sexo` is null) or (`sexo` in (_utf8mb4'M',_utf8mb4'F')))),
  CONSTRAINT `ck_persona_tipo_documento` CHECK ((`tipo_documento` in (_utf8mb4'DNI',_utf8mb4'CE',_utf8mb4'PASAPORTE')))
) ENGINE=InnoDB AUTO_INCREMENT=170 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Datos personales comunes de administrativos, docentes y estudiantes';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_administrativo`
--

DROP TABLE IF EXISTS `personal_administrativo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_administrativo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint unsigned NOT NULL,
  `usuario_id` bigint unsigned NOT NULL,
  `cargo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_administrativo_persona_id_unique` (`persona_id`),
  UNIQUE KEY `personal_administrativo_usuario_id_unique` (`usuario_id`),
  CONSTRAINT `personal_administrativo_persona_id_foreign` FOREIGN KEY (`persona_id`) REFERENCES `persona` (`id`),
  CONSTRAINT `personal_administrativo_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`),
  CONSTRAINT `ck_administrativo_estado` CHECK ((`estado` in (_utf8mb4'ACTIVO',_utf8mb4'INACTIVO')))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Personal administrativo (rol ADMIN)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `prediccion_riesgo`
--

DROP TABLE IF EXISTS `prediccion_riesgo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prediccion_riesgo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `matricula_id` bigint unsigned NOT NULL,
  `subperiodo_id` bigint unsigned NOT NULL,
  `modelo_id` bigint unsigned NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `probabilidad` decimal(5,4) NOT NULL COMMENT '0 a 1',
  `nivel_riesgo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'BAJO, MEDIO o ALTO',
  `features` json NOT NULL COMMENT 'Valores que vio el modelo',
  `resumen_texto` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `prediccion_riesgo_modelo_id_foreign` (`modelo_id`),
  KEY `ix_prediccion_matricula` (`matricula_id`,`fecha`),
  KEY `ix_prediccion_nivel` (`subperiodo_id`,`nivel_riesgo`),
  CONSTRAINT `prediccion_riesgo_matricula_id_foreign` FOREIGN KEY (`matricula_id`) REFERENCES `matricula` (`id`),
  CONSTRAINT `prediccion_riesgo_modelo_id_foreign` FOREIGN KEY (`modelo_id`) REFERENCES `modelo_ml` (`id`),
  CONSTRAINT `prediccion_riesgo_subperiodo_id_foreign` FOREIGN KEY (`subperiodo_id`) REFERENCES `subperiodo` (`id`),
  CONSTRAINT `ck_prediccion_nivel` CHECK ((`nivel_riesgo` in (_utf8mb4'BAJO',_utf8mb4'MEDIO',_utf8mb4'ALTO'))),
  CONSTRAINT `ck_prediccion_probabilidad` CHECK ((`probabilidad` between 0 and 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nivel de riesgo calculado para un estudiante en un bimestre';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recomendacion`
--

DROP TABLE IF EXISTS `recomendacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recomendacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prediccion_id` bigint unsigned NOT NULL,
  `descripcion` varchar(400) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prioridad` tinyint unsigned NOT NULL DEFAULT '2' COMMENT '1 alta, 2 media, 3 baja',
  PRIMARY KEY (`id`),
  KEY `recomendacion_prediccion_id_foreign` (`prediccion_id`),
  CONSTRAINT `recomendacion_prediccion_id_foreign` FOREIGN KEY (`prediccion_id`) REFERENCES `prediccion_riesgo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_recomendacion_prioridad` CHECK ((`prioridad` between 1 and 3))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recomendaciones de intervención de una predicción';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recuperacion_password`
--

DROP TABLE IF EXISTS `recuperacion_password`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recuperacion_password` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 del token enviado por correo',
  `expira_en` timestamp NOT NULL,
  `usado_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recuperacion_password_token_hash_unique` (`token_hash`),
  KEY `recuperacion_password_usuario_id_foreign` (`usuario_id`),
  CONSTRAINT `recuperacion_password_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Enlaces de recuperación de contraseña (un solo uso, 30 minutos)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rol`
--

DROP TABLE IF EXISTS `rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rol` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ADMIN, DOCENTE o ESTUDIANTE',
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rol_codigo_unique` (`codigo`),
  CONSTRAINT `ck_rol_codigo` CHECK ((`codigo` in (_utf8mb4'ADMIN',_utf8mb4'DOCENTE',_utf8mb4'ESTUDIANTE')))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Roles de acceso. El coordinador es un docente con ámbito de coordinación.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `seccion`
--

DROP TABLE IF EXISTS `seccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seccion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seccion_nombre_unique` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secciones: "Única" o letras A, B, C…';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sesion_clase`
--

DROP TABLE IF EXISTS `sesion_clase`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sesion_clase` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `curso_aula_id` bigint unsigned NOT NULL,
  `subperiodo_id` bigint unsigned NOT NULL,
  `fecha` date NOT NULL,
  `tema` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BORRADOR' COMMENT 'BORRADOR o DEFINITIVO',
  `registrado_por` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_sesion_clase` (`curso_aula_id`,`fecha`),
  KEY `sesion_clase_subperiodo_id_foreign` (`subperiodo_id`),
  KEY `sesion_clase_registrado_por_foreign` (`registrado_por`),
  CONSTRAINT `sesion_clase_curso_aula_id_foreign` FOREIGN KEY (`curso_aula_id`) REFERENCES `curso_aula` (`id`),
  CONSTRAINT `sesion_clase_registrado_por_foreign` FOREIGN KEY (`registrado_por`) REFERENCES `usuario` (`id`),
  CONSTRAINT `sesion_clase_subperiodo_id_foreign` FOREIGN KEY (`subperiodo_id`) REFERENCES `subperiodo` (`id`),
  CONSTRAINT `ck_sesion_estado` CHECK ((`estado` in (_utf8mb4'BORRADOR',_utf8mb4'DEFINITIVO')))
) ENGINE=InnoDB AUTO_INCREMENT=4657 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clase dictada en una fecha (una por curso y día)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subperiodo`
--

DROP TABLE IF EXISTS `subperiodo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subperiodo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `periodo_id` bigint unsigned NOT NULL,
  `numero` tinyint unsigned NOT NULL COMMENT '1 a 4',
  `nombre` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej. III Bimestre',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `fecha_cierre_notas` date NOT NULL COMMENT 'Al llegar esta fecha el bimestre se cierra solo',
  `estado` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ABIERTO' COMMENT 'ABIERTO o CERRADO',
  `cerrado_en` timestamp NULL DEFAULT NULL,
  `cerrado_por` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_subperiodo_numero` (`periodo_id`,`numero`),
  KEY `subperiodo_cerrado_por_foreign` (`cerrado_por`),
  CONSTRAINT `subperiodo_cerrado_por_foreign` FOREIGN KEY (`cerrado_por`) REFERENCES `usuario` (`id`),
  CONSTRAINT `subperiodo_periodo_id_foreign` FOREIGN KEY (`periodo_id`) REFERENCES `periodo_academico` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_subperiodo_estado` CHECK ((`estado` in (_utf8mb4'ABIERTO',_utf8mb4'CERRADO'))),
  CONSTRAINT `ck_subperiodo_fechas` CHECK (((`fecha_fin` > `fecha_inicio`) and (`fecha_cierre_notas` >= `fecha_inicio`))),
  CONSTRAINT `ck_subperiodo_numero` CHECK ((`numero` between 1 and 4))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bimestres del periodo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipo_evaluacion`
--

DROP TABLE IF EXISTS `tipo_evaluacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipo_evaluacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Práctica, Proyecto, Examen, Semanal, Parcial…',
  `estado` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipo_evaluacion_nombre_unique` (`nombre`),
  CONSTRAINT `ck_tipo_evaluacion_estado` CHECK ((`estado` in (_utf8mb4'ACTIVO',_utf8mb4'INACTIVO')))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos de evaluación configurables';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipo_incidencia`
--

DROP TABLE IF EXISTS `tipo_incidencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipo_incidencia` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipo_incidencia_nombre_unique` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos de incidencia';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rol_id` bigint unsigned NOT NULL,
  `username` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Admin: nombre de usuario; docente: DNI; estudiante: código',
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Correo para recuperar la contraseña',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Contraseña cifrada con bcrypt',
  `estado` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO' COMMENT 'ACTIVO, INACTIVO o BLOQUEADO',
  `intentos_fallidos` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Intentos de ingreso fallidos seguidos',
  `bloqueado_hasta` timestamp NULL DEFAULT NULL COMMENT 'Fin del bloqueo temporal; NULL con estado BLOQUEADO = hasta que el admin desbloquee',
  `debe_cambiar_password` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Contraseña temporal: debe cambiarla al ingresar',
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `creado_por` bigint unsigned DEFAULT NULL,
  `actualizado_por` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_username_unique` (`username`),
  UNIQUE KEY `usuario_email_unique` (`email`),
  KEY `usuario_creado_por_foreign` (`creado_por`),
  KEY `usuario_actualizado_por_foreign` (`actualizado_por`),
  KEY `ix_usuario_rol` (`rol_id`),
  CONSTRAINT `usuario_actualizado_por_foreign` FOREIGN KEY (`actualizado_por`) REFERENCES `usuario` (`id`),
  CONSTRAINT `usuario_creado_por_foreign` FOREIGN KEY (`creado_por`) REFERENCES `usuario` (`id`),
  CONSTRAINT `usuario_rol_id_foreign` FOREIGN KEY (`rol_id`) REFERENCES `rol` (`id`),
  CONSTRAINT `ck_usuario_estado` CHECK ((`estado` in (_utf8mb4'ACTIVO',_utf8mb4'INACTIVO',_utf8mb4'BLOQUEADO')))
) ENGINE=InnoDB AUTO_INCREMENT=170 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuentas de acceso al sistema';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'ayni'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
