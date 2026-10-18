<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Reglas de "Personas" de docs/validaciones.md. Las usan los formularios de docentes y estudiantes
 * y la importación de Excel, así el mensaje es el mismo en las tres partes.
 */
class ReglasPersona
{
    public const DOCUMENTOS = [
        'DNI' => ['/^\d{8}$/D', 'El DNI debe tener 8 dígitos.'],
        'CE' => ['/^[A-Z0-9]{9,12}$/D', 'El CE tiene de 9 a 12 caracteres: letras mayúsculas y números.'],
        'PASAPORTE' => ['/^[A-Z0-9]{6,12}$/D', 'El pasaporte tiene de 6 a 12 caracteres: letras mayúsculas y números.'],
    ];

    public const DIRECCION = '/^[\p{L}\d .,#\-\/°]+$/uD';

    /** RN-23: edades coherentes */
    public const EDADES = ['PRIMARIA' => [5, 14], 'SECUNDARIA' => [11, 20], 'PERSONAL' => [18, 75]];

    /**
     * @param  string|null  $tipo  tipo de documento enviado (para elegir el formato del número)
     * @param  bool  $soloDni  los docentes ingresan con su DNI (D5)
     */
    public static function reglas(?string $tipo, ?int $personaId, bool $estudiante, bool $soloDni = false): array
    {
        [$formato] = self::DOCUMENTOS[$tipo] ?? self::DOCUMENTOS['DNI'];

        return [
            'tipo_documento' => ['required', Rule::in($soloDni ? ['DNI'] : array_keys(self::DOCUMENTOS))],
            'numero_documento' => [
                'required', 'string', 'regex:'.$formato,
                Rule::unique('persona', 'numero_documento')->where('tipo_documento', $tipo)->ignore($personaId),
            ],
            'nombres' => ['required', 'string', 'min:2', 'max:100', 'regex:'.Reglas::NOMBRE],
            'apellidos' => ['required', 'string', 'min:2', 'max:100', 'regex:'.Reglas::NOMBRE],
            'fecha_nacimiento' => [$estudiante ? 'required' : 'nullable', 'date_format:Y-m-d', 'before:today'],
            'sexo' => [$estudiante ? 'required' : 'nullable', Rule::in(['M', 'F'])],
            'direccion' => ['nullable', 'string', 'min:5', 'max:200', 'regex:'.self::DIRECCION],
        ];
    }

    public static function mensajes(?string $tipo, bool $soloDni = false): array
    {
        $formato = (self::DOCUMENTOS[$tipo] ?? self::DOCUMENTOS['DNI'])[1];

        return [
            'tipo_documento.*' => $soloDni ? 'Los docentes se registran con su DNI.' : 'El tipo de documento es DNI, CE o PASAPORTE.',
            'numero_documento.required' => 'Ingresa el número de documento.',
            'numero_documento.unique' => 'Ya hay una persona registrada con ese documento.',
            'numero_documento.*' => $formato,
            'nombres.required' => 'Ingresa los nombres.',
            'apellidos.required' => 'Ingresa los apellidos.',
            'nombres.min' => 'Los nombres deben tener al menos 2 letras.',
            'apellidos.min' => 'Los apellidos deben tener al menos 2 letras.',
            'nombres.max' => 'Los nombres no deben tener más de 100 caracteres.',
            'apellidos.max' => 'Los apellidos no deben tener más de 100 caracteres.',
            'nombres.regex' => Reglas::MENSAJES['nombre'],
            'apellidos.regex' => Reglas::MENSAJES['nombre'],
            'fecha_nacimiento.required' => 'Ingresa la fecha de nacimiento.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento no puede ser de hoy ni futura.',
            'fecha_nacimiento.*' => 'La fecha de nacimiento no es válida.',
            'sexo.required' => 'Elige el sexo.',
            'sexo.*' => 'El sexo es M o F.',
            'direccion.min' => 'La dirección tiene de 5 a 200 caracteres: letras, números, espacios y . , # - / °',
            'direccion.max' => 'La dirección tiene de 5 a 200 caracteres: letras, números, espacios y . , # - / °',
            'direccion.*' => 'La dirección tiene de 5 a 200 caracteres: letras, números, espacios y . , # - / °',
        ];
    }

    /** Edad cumplida a una fecha */
    public static function edad(string $nacimiento, ?string $alDia = null): int
    {
        return Carbon::parse($nacimiento)->diffInYears(Carbon::parse($alDia ?? today()));
    }

    /** RN-23: null si la edad es coherente; si no, el mensaje */
    public static function edadIncoherente(?string $nacimiento, string $grupo, ?string $alDia = null): ?string
    {
        if (! $nacimiento) {
            return null;
        }
        [$min, $max] = self::EDADES[$grupo];
        $edad = self::edad($nacimiento, $alDia);
        if ($edad >= $min && $edad <= $max) {
            return null;
        }

        return match ($grupo) {
            'PERSONAL' => "La edad ({$edad} años) no es coherente: el personal tiene de {$min} a {$max} años (RN-23).",
            default => "La edad ({$edad} años) no es coherente con ".mb_strtolower($grupo === 'PRIMARIA' ? 'primaria' : 'secundaria')." (de {$min} a {$max} años, RN-23).",
        };
    }
}
