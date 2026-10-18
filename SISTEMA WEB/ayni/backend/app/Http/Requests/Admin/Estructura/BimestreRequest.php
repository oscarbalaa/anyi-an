<?php

namespace App\Http\Requests\Admin\Estructura;

use Illuminate\Foundation\Http\FormRequest;

class BimestreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'numero' => [$this->isMethod('post') ? 'required' : 'prohibited', 'integer', 'between:1,4'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after:fecha_inicio'],
            'fecha_cierre_notas' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero.*' => 'El bimestre es del I al IV.',
            'fecha_inicio.*' => 'Ingresa una fecha de inicio válida.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la de inicio.',
            'fecha_fin.*' => 'Ingresa una fecha de fin válida.',
            'fecha_cierre_notas.after_or_equal' => 'La fecha de cierre de notas no puede ser anterior al inicio del bimestre (RN-02).',
            'fecha_cierre_notas.*' => 'Ingresa una fecha de cierre válida.',
        ];
    }
}
