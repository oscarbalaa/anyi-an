<?php

namespace App\Http\Requests\Auth;

use App\Support\Acceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngresoRequest extends FormRequest
{
    public function rules(): array
    {
        [$formato] = Acceso::FORMATO_USUARIO[$this->acceso()] ?? ['/.*/'];

        return [
            'acceso' => ['required', Rule::in(array_keys(Acceso::ROLES))],
            'usuario' => ['required', 'string', 'max:60', 'regex:'.$formato],
            'contrasena' => ['required', 'string', 'max:64'],
            'captcha_id' => ['nullable', 'string', 'max:36'],
            'captcha' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario.regex' => Acceso::FORMATO_USUARIO[$this->acceso()][1] ?? 'El usuario no tiene un formato válido.',
            'usuario.required' => 'Ingresa tu usuario.',
            'contrasena.required' => 'Ingresa tu contraseña.',
            'acceso.in' => 'El tipo de acceso no es válido.',
        ];
    }

    public function attributes(): array
    {
        return ['contrasena' => 'la contraseña'];
    }

    /** Si llega como lista u otro tipo no es un acceso válido (422, no un error del servidor) */
    private function acceso(): string
    {
        return is_string($this->input('acceso')) ? $this->input('acceso') : '';
    }
}
