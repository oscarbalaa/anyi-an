<?php

namespace App\Http\Requests\Incidencias;

use App\Http\Requests\LimpiaDatos;
use App\Servicios\Incidencias\Incidencias;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Incidencia de un estudiante (docs/validaciones.md, "Asistencia, incidencias y alertas") */
class IncidenciaRequest extends FormRequest
{
    use LimpiaDatos;

    public function rules(): array
    {
        return [
            'matricula_id' => ['required', 'integer'],
            'tipo_incidencia_id' => ['required', 'integer', 'exists:tipo_incidencia,id'],
            'gravedad' => ['required', Rule::in(Incidencias::GRAVEDADES)],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'descripcion' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'matricula_id.*' => 'Elige al estudiante.',
            'tipo_incidencia_id.*' => 'Elige el tipo de incidencia.',
            'gravedad.*' => 'Elige la gravedad: leve, moderada o grave.',
            'fecha.*' => 'Ingresa una fecha válida.',
            'descripcion.required' => 'Describe lo ocurrido.',
            'descripcion.*' => 'Describe lo ocurrido en 10 a 500 caracteres.',
        ];
    }
}
