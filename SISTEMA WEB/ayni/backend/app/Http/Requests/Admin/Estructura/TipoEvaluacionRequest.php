<?php

namespace App\Http\Requests\Admin\Estructura;

use App\Http\Requests\LimpiaDatos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoEvaluacionRequest extends FormRequest
{
    use LimpiaDatos;

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[\p{L} ]+$/uD', Rule::unique('tipo_evaluacion', 'nombre')->ignore($this->route('tipo'))],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre del tipo.',
            'nombre.unique' => 'Ese tipo de evaluación ya existe.',
            'nombre.*' => 'El nombre tiene de 3 a 40 letras.',
            'estado.*' => 'El estado es activo o inactivo.',
        ];
    }
}
