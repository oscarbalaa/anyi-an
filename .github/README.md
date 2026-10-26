# AYNI — Plataforma académica en la nube

**Curso:** Aplicaciones en la Nube · **Grupo:** 02 · **Docente:** Dr. Mirko Martín Manrique Ronceros
**Universidad Nacional del Santa** · Escuela Profesional de Ingeniería de Sistemas e Informática

AYNI es un sistema web de gestión académica para la IEP El Señor es mi Pastor (Nuevo Chimbote). Reúne notas, asistencia, incidencias y alertas de riesgo académico explicables, con perfiles de administrador, coordinador, docente y estudiante.

| | |
|---|---|
| **Repositorio (código fuente)** | https://github.com/oscarbalaa/anyi-an (público) |
| **Sistema publicado** | https://ayni.ruta-practica.com/ |
| **Hosting del curso** | https://aplicacionenlanube.cetivirgendelapuerta.com/grupo02/ |
| **Integración continua** | https://github.com/oscarbalaa/anyi-an/actions |

El sistema está en `SISTEMA WEB/ayni/`. Las contraseñas del servidor no se publican: se entregan al docente por privado.

---

## 1. Sistema web

| Dato | Valor |
|---|---|
| Dirección | https://ayni.ruta-practica.com/ |
| Puerto | **443** (HTTPS). Si se entra por http:// (puerto 80), el servidor redirige solo a https:// |
| Comprobar el servidor | https://ayni.ruta-practica.com/api/v1/health (debe responder `"estado": "ok"` con la base conectada) |
| Hosting del curso | https://aplicacionenlanube.cetivirgendelapuerta.com/grupo02/: la misma versión, en la carpeta `grupo02` del FTP del curso. No tiene base de datos: el sistema completo se revisa en el VPS |

### Usuarios de demostración (personas ficticias)

| Rol | Entra por | Usuario | Contraseña |
|---|---|---|---|
| Administrador | Administración | `cmedina` | `Ayni#2026` |
| Docente (coordina 3.º A) | Ingresar › Soy docente | `41234567` | `Ayni#2026` |
| Docente (tutora 3.º B) | Ingresar › Soy docente | `42345678` | `Ayni#2026` |
| Estudiante de secundaria | Ingresar › Soy estudiante | `E-2026-001` | `piki2026` |
| Estudiante de primaria | Ingresar › Soy estudiante | `E-2026-002` | `piki2026` |

- Al ingresar se pide el código de la imagen (captcha).
- Tras 5 intentos fallidos, la cuenta se bloquea:
  - Las cuentas de administración se liberan solas a los 15 minutos.
  - Las demás las desbloquea un administrador en **Usuarios**.

## 2. Archivos del sitio (SFTP)

| Dato | Valor |
|---|---|
| Programa | FileZilla u otro cliente SFTP |
| Protocolo | **SFTP** - SSH File Transfer Protocol (no "FTP") |
| Servidor | `ayni.ruta-practica.com` |
| Puerto | **22** |
| Usuario | `ayni` |
| Contraseña | entregada al docente por privado |
| Carpeta del sistema | `/home/ayni/htdocs/ayni.ruta-practica.com` |

La carpeta pública es `backend/public`. El código, el `.env`, `vendor/` y la documentación no se pueden abrir desde el navegador: responden 403 o 404.

## 3. Consola (SSH)

```bash
ssh ayni@ayni.ruta-practica.com -p 22      # usuario y contraseña del punto 2
cd ~/htdocs/ayni.ruta-practica.com/backend
php -v                                     # PHP 8.1
php artisan migrate:status                 # migraciones aplicadas
tail -n 50 storage/logs/laravel.log
```

## 4. Panel del servidor (CloudPanel)

| Dato | Valor |
|---|---|
| Dirección | https://2.25.104.128:8443 |
| Puerto | **8443** (HTTPS). El navegador avisa que el certificado no es de confianza: elija "Avanzado › Continuar" |
| Usuario | entregado al docente por privado |
| Contraseña | entregada al docente por privado |
| Uso | Sitio `ayni.ruta-practica.com`: archivos, base de datos (phpMyAdmin), registros, PHP y SSL |

## 5. Base de datos (MySQL 8)

| Dato | Valor |
|---|---|
| Base de datos | `ayni-db` |
| Usuario | `ayni-db` |
| Contraseña | entregada al docente por privado |
| Servidor y puerto | `127.0.0.1:3306`, solo dentro del servidor. El puerto 3306 no está abierto a Internet |

Cómo entrar:

1. **phpMyAdmin:** CloudPanel › Sites › ayni.ruta-practica.com › Databases › Manage, con el usuario y la contraseña de este punto.
2. **HeidiSQL o MySQL Workbench por túnel SSH:**
   - Tipo de conexión: MySQL (SSH tunnel).
   - SSH: `ayni.ruta-practica.com`, puerto 22, con el usuario y la contraseña del punto 2.
   - MySQL: `127.0.0.1`, puerto 3306, con el usuario y la contraseña de este punto.
3. **Por SSH:** `mysql -h 127.0.0.1 -u ayni-db -p ayni-db`

## 6. Código fuente y documentación

| Qué | Dónde (en el repositorio) |
|---|---|
| Código del sistema | `SISTEMA WEB/ayni/`: `backend/` (Laravel 10, PHP 8.1) y `frontend/` (React 19 + Vite + TypeScript) |
| Migraciones y seeders | `SISTEMA WEB/ayni/backend/database/` |
| Pruebas | `backend/tests` (Pest), `frontend/src/**/*.test.tsx` (Vitest) y `e2e/pruebas` (Playwright) |
| Manuales | `SISTEMA WEB/ayni/1. INICIO/MANUAL` (manual de usuario y de instalación, en Word y PDF) |
| Instalación local | `SISTEMA WEB/ayni/1. INICIO/AYNI.bat` (Windows) o `AYNI.sh` (Linux); ver `1. INICIO/README.md` |

## 7. Resumen de puertos

| Puerto | Servicio |
|---|---|
| 22 | SSH y SFTP |
| 80 | HTTP (redirige a 443) |
| 443 | HTTPS: sistema web y API (`/api/v1`) |
| 8443 | CloudPanel |
| 3306 | MySQL (solo local, por túnel SSH o phpMyAdmin) |

## 8. Equipo

| Integrante | Código | Rol |
|---|---|---|
| Balarezo Castañeda, Oscar Isaac | 02022140 | Coordinación, desarrollo y nube |
| Camargo Herrera, Lucciano Sebastián | 0202114023 | Casos de uso y modelado |
| Flores García, Sofía Sharon | 0202214007 | Diseño y pruebas |
| Núñez Chávez, José Gerardo | 0202214022 | Base de datos |
| Ramírez Aranda, Jerson | 0202214023 | Análisis e investigación |
