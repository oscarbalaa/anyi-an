<?php

namespace App\Http\Requests\Admin\Estructura;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CoordinacionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'periodo_id' => ['required', 'integer', 'exists:periodo_academico,id'],
            'docente_id' => ['required', 'integer', Rule::exists('docente', 'id')->where('estado', 'ACTIVO')],
            'ambito' => ['required', Rule::in(['GRADO', 'NIVEL', 'INSTITUCION'])],
            'grado_id' => ['required_if:ambito,GRADO', 'nullable', 'integer', 'exists:grado,id'],
            'nivel' => ['required_if:ambito,NIVEL', 'nullable', Rule::in(['PRIMARIA', 'SECUNDARIA'])],
        ];
    }

    public function messages(): array
    {
        return [
            'periodo_id.*' => 'Elige un periodo válido.',
            'docente_id.*' => 'Elige un docente activo.',
            'ambito.*' => 'El ámbito es un grado, un nivel o todo el colegio.',
            'grado_id.*' => 'Elige el grado que coordinará.',
            'nivel.*' => 'Elige el nivel que coordinará.',
        ];
    }
}
