<?php

namespace App\Http\Requests\Auth;

use App\Support\Acceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/** RF-07 y RF-15: cambiar la contraseña indicando la actual */
class CambioContrasenaRequest extends FormRequest
{
    public function rules(): array
    {
        $usuario = $this->user();

        return [
            'actual' => [
                'required',
                'string',
                function (string $atributo, mixed $valor, \Closure $falla) use ($usuario) {
                    if (! Hash::check((string) $valor, $usuario->password_hash)) {
                        $falla('La contraseña actual es incorrecta.');
                    }
                },
            ],
            'contrasena' => [...Acceso::reglasContrasena($usuario->rol->codigo, $usuario->username), 'confirmed', 'different:actual'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual.required' => 'Ingresa tu contraseña actual.',
            'contrasena.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ];
    }

    public function attributes(): array
    {
        return ['contrasena' => 'la nueva contraseña'];
    }
}
