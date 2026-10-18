<?php

namespace App\Http\Requests\Admin\Estructura;

use App\Http\Requests\LimpiaDatos;
use Illuminate\Foundation\Http\FormRequest;

/** Un bloque del horario: de 06:00 a 20:00, mínimo 30 minutos (docs/validaciones.md) */
class BloqueRequest extends FormRequest
{
    use LimpiaDatos;

    public function rules(): array
    {
        $hora = ['required', 'date_format:H:i', 'after_or_equal:06:00', 'before_or_equal:20:00'];

        return [
            'curso_id' => ['required', 'integer', 'exists:curso_aula,id'],
            'dia_semana' => ['required', 'integer', 'between:1,6'],
            'hora_inicio' => $hora,
            'hora_fin' => [...$hora, 'after:hora_inicio'],
            'ambiente' => ['nullable', 'string', 'max:40', 'regex:/^[\p{L}\d .-]+$/uD'],
        ];
    }

    public function messages(): array
    {
        return [
            'curso_id.*' => 'Elige el curso.',
            'dia_semana.*' => 'Elige un día de lunes a sábado.',
            'hora_inicio.date_format' => 'La hora tiene el formato HH:MM.',
            'hora_fin.date_format' => 'La hora tiene el formato HH:MM.',
            'hora_inicio.*' => 'La hora debe estar entre 06:00 y 20:00.',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la de inicio.',
            'hora_fin.*' => 'La hora debe estar entre 06:00 y 20:00.',
            'ambiente.max' => 'El ambiente no debe tener más de 40 caracteres.',
            'ambiente.*' => 'El ambiente solo puede tener letras, números, espacios, punto y guion.',
        ];
    }
}
