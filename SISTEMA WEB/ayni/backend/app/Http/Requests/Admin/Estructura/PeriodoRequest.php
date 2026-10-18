<?php

namespace App\Http\Requests\Admin\Estructura;

use App\Http\Requests\LimpiaDatos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PeriodoRequest extends FormRequest
{
    use LimpiaDatos;

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'regex:/^\d{4}$/D', 'integer', 'between:2020,2100', Rule::unique('periodo_academico', 'nombre')->ignore($this->route('periodo'))],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after:fecha_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el año del periodo.',
            'nombre.unique' => 'Ya existe un periodo con ese año.',
            'nombre.*' => 'El periodo es un año de 4 dígitos entre 2020 y 2100.',
            'fecha_inicio.*' => 'Ingresa una fecha de inicio válida.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la de inicio.',
            'fecha_fin.*' => 'Ingresa una fecha de fin válida.',
        ];
    }
}
