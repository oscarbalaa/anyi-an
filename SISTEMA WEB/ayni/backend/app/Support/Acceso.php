<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/** Los tres accesos del sistema (D5) y sus reglas */
class Acceso
{
    public const ROLES = ['estudiante' => 'ESTUDIANTE', 'docente' => 'DOCENTE', 'admin' => 'ADMIN'];

    public const NOMBRES = ['ESTUDIANTE' => 'estudiante', 'DOCENTE' => 'docente', 'ADMIN' => 'administración'];

    /** Formato del usuario de cada acceso (docs/validaciones.md) */
    public const FORMATO_USUARIO = [
        'estudiante' => ['/^E-\d{4}-\d{3}$/D', 'El código tiene el formato E-AAAA-NNN (por ejemplo, E-2026-001).'],
        'docente' => ['/^\d{8}$/D', 'El DNI debe tener 8 dígitos.'],
        'admin' => ['/^[A-Za-z][A-Za-z0-9._]{3,29}$/D', 'El usuario tiene de 4 a 30 caracteres, empieza con letra y solo usa letras, números, punto o guion bajo.'],
    ];

    public static function desdeRol(string $rol): string
    {
        return array_search($rol, self::ROLES, true) ?: 'estudiante';
    }

    /** RN-16: política de contraseñas por rol */
    public static function reglasContrasena(string $rol, string $usuario): array
    {
        $politica = Password::min(8)->letters()->numbers();
        if ($rol !== 'ESTUDIANTE') {
            $politica = Password::min(8)->mixedCase()->numbers()->symbols();
        }

        return [
            'required',
            'string',
            'max:64',
            $politica,
            function (string $atributo, mixed $valor, \Closure $falla) use ($usuario) {
                if (mb_strtolower((string) $valor) === mb_strtolower($usuario)) {
                    $falla('La contraseña no puede ser igual a tu usuario.');
                }
            },
        ];
    }
}
