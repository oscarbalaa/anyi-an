<?php

namespace App\Http\Requests\Riesgo;

use App\Http\Requests\LimpiaDatos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cambio de estado de una alerta: cerrarla o descartarla pide justificación (RF-55) */
class EstadoAlertaRequest extends FormRequest
{
    use LimpiaDatos;

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(['EN_SEGUIMIENTO', 'CERRADA', 'DESCARTADA'])],
            'justificacion' => ['nullable', 'required_unless:estado,EN_SEGUIMIENTO', 'string', 'min:10', 'max:400'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.*' => 'Elige el nuevo estado de la alerta.',
            'justificacion.required_unless' => 'Escribe la justificación.',
            'justificacion.*' => 'La justificación debe tener de 10 a 400 caracteres.',
        ];
    }
}
