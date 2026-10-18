<?php

namespace App\Http\Requests\Admin\Estructura;

use App\Http\Requests\LimpiaDatos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AulaRequest extends FormRequest
{
    use LimpiaDatos;

    public function rules(): array
    {
        $docenteActivo = Rule::exists('docente', 'id')->where('estado', 'ACTIVO');
        $nueva = $this->isMethod('post');

        return [
            'periodo_id' => [$nueva ? 'required' : 'prohibited', 'integer', 'exists:periodo_academico,id'],
            'grado_id' => [$nueva ? 'required' : 'prohibited', 'integer', 'exists:grado,id'],
            // RN-03: "Única" o de 1 a 3 letras mayúsculas
            'seccion' => [$nueva ? 'required' : 'prohibited', 'string', 'regex:/^(Única|[A-Z]{1,3})$/uD'],
            'capacidad' => ['required', 'integer', 'between:1,60'],
            'tutor_docente_id' => ['nullable', 'integer', $docenteActivo],
            'coordinador_docente_id' => ['nullable', 'integer', $docenteActivo],
            'ambiente' => ['nullable', 'string', 'max:40', 'regex:/^[\p{L}\d .-]+$/uD'],
        ];
    }

    public function messages(): array
    {
        return [
            'periodo_id.*' => 'Elige un periodo válido.',
            'grado_id.*' => 'Elige un grado válido.',
            'seccion.required' => 'Ingresa la sección.',
            'seccion.*' => 'La sección es "Única" o de 1 a 3 letras mayúsculas (A, B, C…).',
            'capacidad.required' => 'Ingresa la capacidad.',
            'capacidad.*' => 'La capacidad es un número entero de 1 a 60.',
            'tutor_docente_id.*' => 'Elige un docente activo como tutor.',
            'coordinador_docente_id.*' => 'Elige un docente activo como coordinador.',
            'ambiente.max' => 'El ambiente no debe tener más de 40 caracteres.',
            'ambiente.*' => 'El ambiente solo puede tener letras, números, espacios, punto y guion.',
        ];
    }
}
