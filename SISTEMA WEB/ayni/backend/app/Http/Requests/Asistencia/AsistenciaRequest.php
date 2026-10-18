<?php

namespace App\Http\Requests\Asistencia;

use App\Servicios\Asistencia\Asistencias;
use App\Support\Reglas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Asistencia de una clase (docs/validaciones.md, "Asistencia") */
class AsistenciaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $registros = $this->input('registros');
        if (is_array($registros)) {
            $registros = array_map(function ($r) {
                if (is_array($r) && isset($r['observacion']) && is_string($r['observacion'])) {
                    $r['observacion'] = Reglas::limpiar($r['observacion']) ?: null;
                }

                return $r;
            }, $registros);
        }
        $tema = Reglas::limpiar($this->input('tema'));
        $this->merge(['registros' => $registros ?? [], 'tema' => $tema === '' ? null : $tema]);
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(['BORRADOR', 'DEFINITIVO'])],
            'tema' => ['nullable', 'string', 'max:200'],
            'registros' => ['array', 'max:60'],
            'registros.*' => ['array'],
            'registros.*.matricula_id' => ['required', 'integer', 'distinct'],
            'registros.*.estado' => ['required', Rule::in(Asistencias::ESTADOS)],
            'registros.*.observacion' => ['nullable', 'string', 'max:300', 'required_if:registros.*.estado,J'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.*' => 'Elige si guardas un borrador o la asistencia definitiva.',
            'tema.max' => 'El tema no debe tener más de 200 caracteres.',
            'tema.*' => 'El tema debe ser un texto.',
            'registros.max' => 'La lista no puede tener más de 60 estudiantes.',
            'registros.*' => 'La lista de estudiantes no es válida.',
            'registros.*.matricula_id.distinct' => 'Un estudiante aparece dos veces en la lista.',
            'registros.*.matricula_id.*' => 'La lista de estudiantes no es válida.',
            'registros.*.estado.*' => 'El estado de asistencia debe ser P, F, T o J.',
            'registros.*.observacion.required_if' => 'Escribe el motivo de la justificación.',
            'registros.*.observacion.max' => 'La observación no debe tener más de 300 caracteres.',
            'registros.*.observacion.*' => 'La observación debe ser un texto.',
        ];
    }
}
