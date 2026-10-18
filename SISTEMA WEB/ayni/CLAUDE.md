# AYNI — Convenciones del proyecto

Sistema de gestión académica del Grupo 02. El plan y el estado de cada módulo están en `../../PLAN_DE_MODULOS.md`; los requisitos, en `docs/requisitos.md`, y las reglas de cada campo, en `docs/validaciones.md`.

## Stack (no cambiar sin acordarlo)
- **Backend:** Laravel 10 sobre **PHP 8.1**, que es la versión del hosting del curso. `composer.json` fija `platform.php = 8.1.34`.
- **Frontend:** React 19 + Vite + TypeScript + Tailwind 4 + shadcn/ui, React Router, TanStack Query, Zod + React Hook Form.
- **BD:** MySQL 8.4 en local; debe funcionar también en MariaDB 10.6+.
- **Local:** Docker Compose, con `1. INICIO/AYNI.bat` (Windows) y `AYNI.sh` (Linux).

## Hosting (solo FTP)
- **Sin SSH, sin cron, sin colas:** nada puede depender de procesos en segundo plano. Lo "automático" se calcula al guardar datos o en la primera petición del día.
- **Mismo `.htaccess` en local y en el hosting:** `.htaccess` de la raíz → `backend/public` (React + `/api`). Lo demás no es accesible por web.
- **Rutas relativas:** React usa `import.meta.env.BASE_URL`, porque en el hosting vive en `/grupo02/`.

## Código
- **Idioma:**
  - Dominio, BD, mensajes y comentarios en **español** (Estudiante, Matricula, `registrarAsistencia`).
  - Estructura del framework en inglés (Controller, Request, Service, hooks `useX`).
- **Comentarios:** cortos y solo donde aportan. Sin párrafos explicativos.
- **Commits:** en español, sin línea de coautoría.
- **Validación en dos capas** (Zod en el front + Form Request en el back), siguiendo `docs/validaciones.md`. Los mensajes van en español y por campo.
- **Respuestas de la API:** JSON con `mensaje` y, si aplica, `errores`.
- **Consultas:** sin N+1. Usar `with()` y paginar las listas.
- **Formato:** `pint` (PHP) y `prettier` + `oxlint` (TS). Los archivos de código van en LF.

## Seguridad con Laravel 10 (sin soporte oficial)
Hay avisos conocidos de seguridad sin parche para la rama 10 (ver `docs/seguridad.md`). Por eso:
- **No usar la regla `email` de Laravel:** validar correos con la regex de `docs/validaciones.md`.
- **No usar URLs firmadas temporales** (`URL::temporarySignedRoute`). Usar tokens propios guardados en BD.
- **`APP_DEBUG=false`** siempre fuera de desarrollo.

## Pruebas
Cada módulo trae pruebas Pest (API, validaciones, reglas), Vitest (componentes) y Playwright (E2E en PC y celular). Las del panel van con Pester 3.4. Todo se ejecuta con la opción 10 del panel y debe pasar antes de entregar el módulo.
