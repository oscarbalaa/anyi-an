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

    'captcha' => [
        'caracteres' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
        'longitud' => 5,
        'minutos' => 5,
        'fuente' => resource_path('fuentes/DejaVuSans-Bold.ttf'),
    ],

    // Solo en local: las pruebas E2E envían este secreto para no tener que leer el captcha.
    // En el hosting no se define y el captcha siempre se exige.
    'secreto_e2e' => env('AYNI_SECRETO_E2E'),

];
