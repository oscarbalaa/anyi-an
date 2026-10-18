<?php

namespace App\Http\Requests\Auth;

use App\Support\Acceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecuperarRequest extends FormRequest
{
    public function rules(): array
    {
        [$formato] = Acceso::FORMATO_USUARIO[$this->input('acceso')] ?? ['/.*/'];

        return [
            'acceso' => ['required', Rule::in(array_keys(Acceso::ROLES))],
            'usuario' => ['required', 'string', 'max:60', 'regex:'.$formato],
            'captcha_id' => ['nullable', 'string', 'max:36'],
            'captcha' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario.regex' => Acceso::FORMATO_USUARIO[$this->input('acceso')][1] ?? 'El usuario no tiene un formato válido.',
            'usuario.required' => 'Ingresa tu usuario.',
        ];
    }
}
