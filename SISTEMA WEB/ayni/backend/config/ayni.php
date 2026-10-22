<?php

return [

    'version' => '0.4.0',

    'colegio' => env('AYNI_COLEGIO', 'IEP El Señor es mi Pastor'),

    'seguridad' => [
        // RN-15: intentos fallidos antes del bloqueo
        'intentos_maximos' => 5,
        // Los administradores se bloquean por tiempo para que siempre haya quien desbloquee
        'bloqueo_admin_minutos' => 15,
        // RN-17: la sesión se cierra tras este tiempo sin actividad
        'sesion_inactividad_minutos' => 60,
        // RN-25: vigencia del enlace de recuperación
        'recuperacion_minutos' => 30,
    ],

    'asistencia' => [
        // RF-37: desde cuántas faltas en la semana se avisa al docente
        'aviso_faltas_semana' => 2,
    ],

    'archivos' => [
        // RN-26: tope de 10 MB (o el límite menor del hosting)
        'max_mb' => 10,
        'material' => ['pdf', 'docx', 'pptx', 'xlsx', 'jpg', 'jpeg', 'png'],
    ],

    'captcha' => [
        'caracteres' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
        'longitud' => 5,
        'minutos' => 5,
        'fuente' => resource_path('fuentes/DejaVuSans-Bold.ttf'),
    ],

    // Peticiones por minuto a la API: por cuenta, y por IP sin sesión (cada pantalla hace de 5 a 10)
    'limite_api' => [
        'cuenta' => (int) env('AYNI_LIMITE_API', 300),
        'ip' => (int) env('AYNI_LIMITE_API_IP', 600),
    ],

    // Las pruebas E2E envían este secreto para no tener que leer el captcha. En el hosting solo
    // existe mientras corre la prueba de humo, con vencimiento (marca de tiempo Unix, máximo 2 horas).
    'secreto_e2e' => env('AYNI_SECRETO_E2E'),
    'secreto_e2e_hasta' => env('AYNI_SECRETO_E2E_HASTA'),

    // IP de los proxies propios (separadas por comas) para leer la IP real del visitante. Vacío = ninguno
    'proxies_confiables' => env('AYNI_PROXIES_CONFIABLES', ''),

];
