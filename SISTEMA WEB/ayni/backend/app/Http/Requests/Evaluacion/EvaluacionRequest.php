<?php

namespace App\Http\Requests\Evaluacion;

use App\Support\Reglas;
use Illuminate\Foundation\Http\FormRequest;

/** Evaluación de un curso (docs/validaciones.md, "Evaluaciones, entregas y notas") */
class EvaluacionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $indicaciones = $this->input('indicaciones');
        if (is_string($indicaciones)) {
            // Se respetan los saltos de línea; se quitan los espacios sobrantes de cada línea
            $lineas = array_map(fn ($l) => Reglas::limpiar($l), preg_split('/\R/u', $indicaciones));
            $indicaciones = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lineas))) ?: null;
        }
        $this->merge(['titulo' => Reglas::limpiar($this->input('titulo')), 'indicaciones' => $indicaciones]);
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'min:3', 'max:150'],
            'tipo_evaluacion_id' => ['required', 'integer', 'exists:tipo_evaluacion,id'],
            'subperiodo_id' => ['required', 'integer'],
            'fecha_asignacion' => ['required', 'date_format:Y-m-d'],
            'fecha_limite' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_asignacion'],
            'peso' => ['required', 'integer', 'between:1,10'],
            'indicaciones' => ['nullable', 'string', 'max:2000'],
            'requiere_entrega' => ['required', 'boolean'],
            'estado' => ['sometimes', 'string', 'in:BORRADOR,PUBLICADA'],
            'puntaje_maximo' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'Escribe el título de la evaluación.',
            'titulo.*' => 'El título debe tener de 3 a 150 caracteres.',
            'tipo_evaluacion_id.*' => 'Elige el tipo de evaluación.',
            'subperiodo_id.*' => 'Elige el bimestre.',
            'fecha_asignacion.*' => 'Ingresa una fecha de asignación válida.',
            'fecha_limite.after_or_equal' => 'La fecha límite no puede ser anterior a la de asignación.',
            'fecha_limite.*' => 'Ingresa una fecha límite válida.',
            'peso.*' => 'El peso es un número entero de 1 a 10.',
            'indicaciones.*' => 'Las indicaciones no deben tener más de 2000 caracteres.',
            'requiere_entrega.*' => 'Indica si los estudiantes entregan un archivo.',
            'estado.*' => 'Elige si la evaluación queda en borrador o publicada.',
            'puntaje_maximo.*' => 'El puntaje máximo es un número entero de 1 a 100.',
        ];
    }
}
