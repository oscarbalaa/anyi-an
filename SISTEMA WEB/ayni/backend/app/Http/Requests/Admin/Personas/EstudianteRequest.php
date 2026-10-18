<?php

namespace App\Http\Requests\Admin\Personas;

use App\Http\Requests\LimpiaDatos;
use App\Servicios\Personas\Estudiantes;
use App\Support\ReglasPersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** RF-28: estudiantes. El código (y usuario) se genera solo (RN-22) */
class EstudianteRequest extends FormRequest
{
    use LimpiaDatos;

    private function estudiante(): ?object
    {
        return $this->route('estudiante') ? DB::table('estudiante')->find($this->route('estudiante')) : null;
    }

    public function rules(): array
    {
        $e = $this->estudiante();
        $nuevo = $this->isMethod('post');
        $tipo = is_string($this->input('tipo_documento')) ? $this->input('tipo_documento') : null;

        return ReglasPersona::reglas($tipo, $e?->persona_id, true) + $this->reglasContacto($e?->usuario_id) + [
            'aula_id' => [$nuevo ? 'nullable' : 'prohibited', 'integer', 'exists:aula,id'],
            'estado' => [$nuevo ? 'prohibited' : 'required', Rule::in(Estudiantes::ESTADOS)],
            'fecha_ingreso' => [$nuevo ? 'prohibited' : 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        $tipo = is_string($this->input('tipo_documento')) ? $this->input('tipo_documento') : null;

        return ReglasPersona::mensajes($tipo) + $this->mensajesContacto() + [
            'aula_id.*' => 'Elige un aula válida.',
            'estado.*' => 'El estado es activo, retirado, trasladado o egresado.',
            'fecha_ingreso.*' => 'La fecha de ingreso no es válida ni puede ser futura.',
        ];
    }
}
