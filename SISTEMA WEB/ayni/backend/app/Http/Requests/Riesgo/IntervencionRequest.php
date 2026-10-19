<?php

namespace App\Http\Requests\Riesgo;

use App\Http\Requests\LimpiaDatos;
use App\Servicios\Riesgo\Alertas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Intervención de una alerta (docs/validaciones.md, "Asistencia, incidencias y alertas") */
class IntervencionRequest extends FormRequest
{
    use LimpiaDatos;

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(Alertas::TIPOS_INTERVENCION)],
            'descripcion' => ['required', 'string', 'min:10', 'max:500'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'resultado' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.*' => 'Elige el tipo de intervención.',
            'descripcion.required' => 'Describe la intervención.',
            'descripcion.*' => 'Describe la intervención en 10 a 500 caracteres.',
            'fecha.*' => 'Ingresa una fecha válida.',
            'resultado.*' => 'El resultado no debe tener más de 300 caracteres.',
        ];
    }
}
