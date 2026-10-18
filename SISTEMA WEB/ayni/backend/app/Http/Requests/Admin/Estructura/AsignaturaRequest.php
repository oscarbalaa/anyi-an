<?php

namespace App\Http\Requests\Admin\Estructura;

use App\Http\Requests\LimpiaDatos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignaturaRequest extends FormRequest
{
    use LimpiaDatos {
        prepareForValidation as limpiar;
    }

    protected function prepareForValidation(): void
    {
        $this->limpiar();
        if (is_string($this->input('codigo'))) {
            $this->merge(['codigo' => mb_strtoupper($this->input('codigo'))]);
        }
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'regex:/^[A-Z0-9-]{2,20}$/D', Rule::unique('asignatura', 'codigo')->ignore($this->route('asignatura'))],
            'nombre' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\p{L}\d ]+$/uD'],
            'horas_semanales' => ['required', 'integer', 'between:1,20'],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'Ingresa el código.',
            'codigo.unique' => 'Ya existe una asignatura con ese código.',
            'codigo.*' => 'El código tiene de 2 a 20 caracteres: letras, números y guion.',
            'nombre.required' => 'Ingresa el nombre.',
            'nombre.*' => 'El nombre tiene de 3 a 100 caracteres: letras, números y espacios.',
            'horas_semanales.required' => 'Ingresa las horas semanales.',
            'horas_semanales.*' => 'Las horas semanales son un número entero de 1 a 20.',
            'estado.*' => 'El estado es activo o inactivo.',
        ];
    }
}
