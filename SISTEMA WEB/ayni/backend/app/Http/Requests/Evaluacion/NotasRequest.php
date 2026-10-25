<?php

namespace App\Http\Requests\Evaluacion;

use App\Servicios\Evaluacion\Notas;
use App\Support\Reglas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RN-08: notas de 0 al puntaje máximo de la evaluación (20 si no se cambió), con hasta 2 decimales, con punto o coma */
class NotasRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $registros = $this->input('registros');
        if (is_array($registros)) {
            $registros = array_map(function ($r) {
                if (! is_array($r)) {
                    return $r;
                }
                if (array_key_exists('nota', $r) && (is_string($r['nota']) || is_numeric($r['nota']))) {
                    $nota = str_replace(',', '.', trim((string) $r['nota']));
                    $r['nota'] = $nota === '' ? null : $nota;
                }
                if (isset($r['observacion']) && is_string($r['observacion'])) {
                    $r['observacion'] = Reglas::limpiar($r['observacion']) ?: null;
                }

                return $r;
            }, $registros);
        }
        $this->merge(['registros' => $registros]);
    }

    public function rules(): array
    {
        return [
            'registros' => ['required', 'array', 'max:60'],
            'registros.*' => ['array'],
            'registros.*.matricula_id' => ['required', 'integer', 'distinct'],
            // El tope de cada evaluación (su puntaje máximo) lo revisa Notas::guardar
            'registros.*.nota' => ['nullable', 'regex:/^\d{1,3}(\.\d{1,2})?$/D', 'numeric', 'between:0,100'],
            'registros.*.estado_entrega' => ['required', Rule::in(Notas::ESTADOS)],
            'registros.*.observacion' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'registros.required' => 'No hay notas para guardar.',
            'registros.*' => 'La lista de notas no es válida.',
            'registros.*.matricula_id.distinct' => 'Un estudiante aparece dos veces en la lista.',
            'registros.*.matricula_id.*' => 'La lista de notas no es válida.',
            'registros.*.nota.between' => 'La nota no puede pasar el puntaje máximo de la evaluación.',
            'registros.*.nota.*' => 'La nota debe ser un número con hasta 2 decimales.',
            'registros.*.estado_entrega.*' => 'Elige el estado de entrega.',
            'registros.*.observacion.*' => 'La observación no debe tener más de 300 caracteres.',
        ];
    }

    /** Registros listos para guardar: la nota como número */
    public function registros(): array
    {
        return array_map(fn ($r) => [
            'matricula_id' => (int) $r['matricula_id'],
            'nota' => isset($r['nota']) ? (float) $r['nota'] : null,
            'estado_entrega' => $r['estado_entrega'],
            'observacion' => $r['observacion'] ?? null,
        ], $this->validated()['registros']);
    }
}
