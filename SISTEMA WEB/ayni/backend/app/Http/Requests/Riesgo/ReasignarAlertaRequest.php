<?php

namespace App\Http\Requests\Riesgo;

use App\Http\Requests\LimpiaDatos;
use Illuminate\Foundation\Http\FormRequest;

/** Cambio del encargado de una alerta, con justificación */
class ReasignarAlertaRequest extends FormRequest
{
    use LimpiaDatos;

    public function rules(): array
    {
        return [
            'docente_id' => ['required', 'integer'],
            'justificacion' => ['required', 'string', 'min:10', 'max:400'],
        ];
    }

    public function messages(): array
    {
        return [
            'docente_id.*' => 'Elige al docente encargado.',
            'justificacion.required' => 'Escribe la justificación.',
            'justificacion.*' => 'La justificación debe tener de 10 a 400 caracteres.',
        ];
    }
}
