<?php

namespace App\Http\Requests\Admin\Estructura;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CursoRequest extends FormRequest
{
    public function rules(): array
    {
        $nuevo = $this->isMethod('post');

        return [
            'aula_id' => [$nuevo ? 'required' : 'prohibited', 'integer', 'exists:aula,id'],
            'asignatura_id' => [$nuevo ? 'required' : 'prohibited', 'integer', 'exists:asignatura,id'],
            'docente_id' => ['required', 'integer', Rule::exists('docente', 'id')->where('estado', 'ACTIVO')],
        ];
    }

    public function messages(): array
    {
        return [
            'aula_id.*' => 'Elige un aula válida.',
            'asignatura_id.*' => 'Elige una asignatura.',
            'docente_id.*' => 'Elige un docente activo.',
        ];
    }
}
