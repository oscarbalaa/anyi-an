<?php

namespace App\Http\Requests\Admin\Personas;

use App\Http\Requests\LimpiaDatos;
use App\Servicios\Personas\Estudiantes;
use App\Support\Reglas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF-29: apoderados (docs/validaciones.md, "Apoderados y docentes") */
class ApoderadoRequest extends FormRequest
{
    use LimpiaDatos {
        prepareForValidation as limpiar;
    }

    protected function prepareForValidation(): void
    {
        $this->limpiar();
        if (is_string($this->input('nombres'))) {
            $this->merge(['nombres' => Reglas::nombrePropio($this->input('nombres'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'min:3', 'max:150', 'regex:'.Reglas::NOMBRE],
            'celular' => ['nullable', 'string', 'regex:'.Reglas::CELULAR],
            'correo' => ['nullable', 'string', 'max:120', 'regex:'.Reglas::CORREO],
            'parentesco' => ['required', Rule::in(Estudiantes::PARENTESCOS)],
            'es_principal' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombres.required' => 'Ingresa el nombre del apoderado.',
            'nombres.*' => 'El nombre tiene de 3 a 150 caracteres: letras, espacios, apóstrofo y guion.',
            'celular.*' => Reglas::MENSAJES['celular'],
            'correo.*' => Reglas::MENSAJES['correo'],
            'parentesco.*' => 'Elige el parentesco.',
            'es_principal.*' => 'Indica si es el apoderado principal.',
        ];
    }
}
