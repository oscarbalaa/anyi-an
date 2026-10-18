# AYNI — Matriz de validaciones

Cada campo se valida en **dos capas**:

1. **Frontend (Zod):** bloquea la entrada inválida mientras se escribe y muestra el error junto al campo.
2. **Backend (Form Requests):** es la validación real. Si falla, responde 422 con el mensaje en español por campo.

Las pruebas automáticas de cada módulo recorren esta tabla con valores válidos e inválidos.

## Reglas generales

- **Limpieza:** se quitan espacios al inicio y al final, y los espacios dobles se reducen a uno.
- **Longitud:** la máxima siempre coincide con la de la BD.
- **Campos numéricos:** abren teclado numérico en celular y no aceptan letras ni al escribir ni al pegar.
- **Campos de solo letras:** no aceptan dígitos ni al escribir ni al pegar.
- **Listas cerradas:** se eligen de un desplegable y el backend verifica que el valor exista.
- **Salida:** todo texto se muestra escapado (sin HTML), para evitar XSS.
- **Mensajes:** concretos, por ejemplo "El DNI debe tener 8 dígitos". Nunca un genérico "dato inválido".

## Personas

| Campo | Regla | Ejemplo válido | Ejemplos inválidos |
|---|---|---|---|
| Tipo de documento | DNI, CE o PASAPORTE | DNI | "LIBRETA" |
| Número — DNI | Exactamente 8 dígitos; único | 78123456 | 7812345, 7812345A |
| Número — CE | 9 a 12 caracteres alfanuméricos, en mayúsculas | 001234567 | 12345, 00-123 |
| Número — Pasaporte | 6 a 12 caracteres alfanuméricos, en mayúsculas | PE1234567 | 123, P#12345 |
| Nombres / apellidos | 2 a 100 caracteres. Solo letras (incluye á é í ó ú ü ñ), espacios, apóstrofo y guion. Se guarda con mayúscula inicial | María José, D'Angelo, Pérez-Gómez | Juan2, @na, "J" |
| Fecha de nacimiento | Fecha real en formato DD/MM/AAAA, no futura, con la edad coherente según RN-23 | 14/05/2012 | 31/02/2012, 01/01/2030 |
| Sexo | M o F | M | X |
| Correo | Formato usuario@dominio.ext, con @ y una extensión de 2 letras o más. Máximo 120. Se guarda en minúsculas. Único entre usuarios | dramirez@mail.com | dramirez@mail, dramirez.com, a@b.c |
| Celular | 9 dígitos y empieza con 9 | 987654321 | 87654321, 98765432a |
| Dirección | 5 a 200 caracteres: letras, números, espacios y . , # - / ° | Av. Pacífico 123 | "Av", <script> |

## Usuarios y acceso

| Campo | Regla | Ejemplo válido | Ejemplos inválidos |
|---|---|---|---|
| Usuario (administrador) | 4 a 30 caracteres; empieza con letra; solo letras, números, punto y guion bajo; sin espacios; único | c.medina | 1admin, c medina |
| Usuario (docente) | Su DNI (8 dígitos) | 41234567 | 4123456 |
| Usuario (estudiante) | Su código `E-AAAA-NNN` | E-2026-010 | E2026010, E-26-10 |
| Contraseña (docente / admin) | 8 a 64 caracteres, con mayúscula, minúscula, número y símbolo; distinta del usuario | Ayni#2026 | ayni2026, AYNI#2026 |
| Contraseña (estudiante) | 8 a 64 caracteres, con letras y números; distinta del usuario | piki2026 | pikipiki, 12345678 |
| Confirmar contraseña | Igual a la nueva contraseña | — | — |
| Captcha | 5 caracteres, sin distinguir mayúsculas | k7Mp2 | (vacío), vencido o ya usado |

## Estructura académica

| Campo | Regla | Ejemplo válido | Ejemplos inválidos |
|---|---|---|---|
| Nombre del periodo | Año de 4 dígitos entre 2020 y 2100; único | 2026 | 26, 2026-I |
| Fechas de periodo y bimestre | Inicio < fin; los bimestres dentro del periodo y sin cruzarse (RN-02) | 03/03/2026 – 08/05/2026 | fin antes del inicio |
| Fecha de cierre de notas | Ni antes del inicio del bimestre ni después del fin del periodo (RN-02) | 15/05/2026 | anterior al inicio del bimestre |
| Sección | "Única" o de 1 a 3 letras mayúsculas (RN-03) | Única, A, B | 1, a-b |
| Capacidad del aula | Entero de 1 a 60 | 30 | 0, 100, 25.5 |
| Código de asignatura | 2 a 20 caracteres: letras mayúsculas, números y guion; único | MAT-3 | mat 3, M |
| Nombre de asignatura | 3 a 100 caracteres: letras, números y espacios | Ciencia y Tecnología | "Ma", C++ |
| Horas semanales | Entero de 1 a 20 | 6 | 0, 40 |
| Hora de inicio / fin | Formato HH:MM entre 06:00 y 20:00; fin > inicio; mínimo 30 minutos; sin cruces (RN-05) | 08:00 – 09:30 | 09:30 – 08:00 |
| Ambiente | 1 a 40 caracteres: letras, números, espacios, punto y guion | Lab. 1 | (más de 40) |

## Evaluaciones, entregas y notas

| Campo | Regla | Ejemplo válido | Ejemplos inválidos |
|---|---|---|---|
| Título | 3 a 150 caracteres | Práctica 7 · Inecuaciones | "P7" |
| Tipo | Uno de los tipos configurados | Práctica | — |
| Fecha de asignación / límite | Dentro del bimestre; límite ≥ asignación | 02/10 – 06/10 | límite antes de la asignación |
| Peso | Entero de 1 a 10 | 2 | 0, 1.5, "dos" |
| Indicaciones | Hasta 2000 caracteres | — | — |
| Nota | De 0 a 20, hasta 2 decimales; acepta punto o coma; letras bloqueadas | 15, 15.5, 15,5 | 20,5, -1, abc, 15.555 |
| Observación (nota / asistencia) | Hasta 300 caracteres | Entregó 26 sep | — |
| Archivo de entrega | Solo PDF, verificado por su contenido real (no solo por la extensión); hasta 10 MB o el límite del hosting | tarea.pdf | tarea.exe renombrado a .pdf, 15 MB |
| Material de apoyo | PDF, DOCX, PPTX, XLSX, JPG o PNG, verificado por su contenido; hasta 10 MB y 5 archivos por evaluación | guia.pdf | script.php, falso.pdf |
| Estado de entrega | PENDIENTE, ENTREGADO, TARDIO o NO_ENTREGADO; se calcula solo y el docente lo corrige | TARDIO | PERDIDO |
| Entrega | Solo si la evaluación pide archivo, el bimestre está abierto, la matrícula está activa y aún no se entregó | — | examen en clase, segunda entrega |
| Archivo de importación | Solo `.xlsx`; hasta 5 MB y 1000 filas; las columnas deben coincidir con la plantilla | matricula_3B.xlsx | alumnos.csv, archivo sin encabezados |

## Asistencia, incidencias y alertas

| Campo | Regla | Ejemplo válido | Ejemplos inválidos |
|---|---|---|---|
| Estado de asistencia | P, F, T o J | P | X |
| Fecha de asistencia | Día de clase del curso, dentro del bimestre, no futura (RN-06); fecha real | — | sábado sin clase, mañana, 31/02 |
| Observación de asistencia | Hasta 300 caracteres. **Obligatoria si el estado es J** (motivo de la justificación) | Cita médica con constancia | J sin motivo |
| Tema de la clase | Opcional, hasta 200 caracteres | Ecuaciones lineales | — |
| Lista de la clase | Solo estudiantes matriculados en el aula, sin repetir; hasta 60 | — | un estudiante de otra aula |
| Motivo para eliminar una clase | 10 a 300 caracteres | No hubo clase por simulacro de sismo | "feriado" |
| Tipo de incidencia | Uno de la lista | Conducta | — |
| Gravedad | LEVE, MODERADA o GRAVE | LEVE | — |
| Fecha de incidencia | No futura y dentro del periodo | — | — |
| Descripción de incidencia | 10 a 500 caracteres | — | "mal" |
| Justificación (alerta o retiro) | 10 a 400 caracteres | — | "ok" |
| Intervención | Tipo de una lista; descripción de 10 a 500 caracteres; resultado de hasta 300 | — | — |

## Apoderados y docentes

| Campo | Regla | Ejemplo válido | Ejemplos inválidos |
|---|---|---|---|
| Nombre del apoderado | Igual que nombres; de 3 a 150 caracteres | Rosa Torres Vega | Rosa1 |
| Parentesco | Padre, Madre, Tutor legal, Abuelo(a), Tío(a), Hermano(a), Otro | Madre | — |
| Título / especialidad del docente | Hasta 120 / 100 caracteres: letras, números, espacios y . , - ( ) | Licenciado(a) en Educación | `<b>` |
| Fechas de contrato | La de fin puede ir vacía o debe ser ≥ la de inicio | — | fin antes del inicio |

## Configuración del colegio

| Campo | Regla |
|---|---|
| Nota aprobatoria | Número de 0 a 20 |
| Umbrales de asistencia | Porcentaje de 0 a 100. El umbral ALTO debe ser menor que el MEDIO |
| Equivalencia literal | Rangos de 0 a 20, sin huecos ni cruces, de C a AD |
