<?php

namespace App\Http\Requests;

use App\Support\Reglas;

/** Limpieza común antes de validar (docs/validaciones.md, "Reglas generales") */
trait LimpiaDatos
{
    protected function prepareForValidation(): void
    {
        $datos = array_map([Reglas::class, 'limpiar'], $this->all());

        foreach (['nombres', 'apellidos'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = Reglas::nombrePropio($datos[$campo]);
            }
        }
        // CE y pasaporte en mayúsculas, sin espacios
        if (isset($datos['numero_documento']) && is_string($datos['numero_documento'])) {
            $datos['numero_documento'] = mb_strtoupper(str_replace(' ', '', $datos['numero_documento']));
        }
        if (isset($datos['correo']) && is_string($datos['correo'])) {
            $datos['correo'] = mb_strtolower($datos['correo']);
        }
        if (isset($datos['celular']) && is_string($datos['celular'])) {
            $datos['celular'] = str_replace(' ', '', $datos['celular']);
        }
        // Un campo opcional vacío se guarda como NULL
        foreach (['correo', 'celular'] as $campo) {
            if (($datos[$campo] ?? null) === '') {
                $datos[$campo] = null;
            }
        }

        $this->replace($datos);
    }

    /** Reglas de correo y celular, que tienen todos los formularios de persona */
    protected function reglasContacto(?int $usuarioId): array
    {
        return [
            'correo' => ['nullable', 'string', 'max:120', 'regex:'.Reglas::CORREO, "unique:usuario,email,{$usuarioId}"],
            'celular' => ['nullable', 'string', 'regex:'.Reglas::CELULAR],
        ];
    }

    protected function mensajesContacto(): array
    {
        return [
            'correo.regex' => Reglas::MENSAJES['correo'],
            'correo.max' => 'El correo no debe tener más de 120 caracteres.',
            'correo.unique' => 'Ese correo ya lo usa otra cuenta.',
            'celular.regex' => Reglas::MENSAJES['celular'],
        ];
    }
}
