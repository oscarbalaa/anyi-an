<?php

namespace App\Support;

/**
 * Reglas de docs/validaciones.md que se repiten en varios formularios.
 * Las expresiones usan /D para que "$" no acepte un salto de línea al final.
 */
class Reglas
{
    // No se usa la regla "email" de Laravel (ver docs/seguridad.md)
    public const CORREO = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/D';

    public const CELULAR = '/^9\d{8}$/D';

    public const DNI = '/^\d{8}$/D';

    public const NOMBRE = "/^[\p{L}' -]+$/uD";

    public const CARGO = '/^[\p{L}\d .,-]+$/uD';

    public const MENSAJES = [
        'correo' => 'El correo debe tener el formato usuario@dominio.com.',
        'celular' => 'El celular debe tener 9 dígitos y empezar con 9.',
        'dni' => 'El DNI debe tener 8 dígitos.',
        'nombre' => 'Solo se permiten letras, espacios, apóstrofo y guion.',
        'cargo' => 'El cargo solo puede tener letras, números, espacios y . , -',
    ];

    /** Quita espacios al inicio y al final y reduce los dobles */
    public static function limpiar(mixed $valor): mixed
    {
        return is_string($valor) ? preg_replace('/\s+/u', ' ', trim($valor)) : $valor;
    }

    /** "maría  JOSÉ" → "María José" (también después de guion y apóstrofo) */
    public static function nombrePropio(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        return preg_replace_callback(
            "/(^|[ '-])(\p{L})/u",
            fn ($m) => $m[1].mb_strtoupper($m[2]),
            mb_strtolower(self::limpiar($valor)),
        );
    }
}
