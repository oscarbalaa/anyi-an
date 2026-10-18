<?php

namespace App\Http\Requests\Admin\Personas;

use App\Http\Requests\LimpiaDatos;
use App\Support\ReglasPersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** RF-27: docentes. Se registran con su DNI, que también es su usuario (D5) */
class DocenteRequest extends FormRequest
{
    use LimpiaDatos;

    private function docente(): ?object
    {
        return $this->route('docente') ? DB::table('docente')->find($this->route('docente')) : null;
    }

    public function rules(): array
    {
        $docente = $this->docente();
        $nuevo = $this->isMethod('post');
        $texto = 'regex:/^[\p{L}\d .,()-]+$/uD';

        return ReglasPersona::reglas('DNI', $docente?->persona_id, false, true) + $this->reglasContacto($docente?->usuario_id) + [
            'titulo' => ['nullable', 'string', 'max:120', $texto],
            'especialidad' => ['nullable', 'string', 'max:100', $texto],
            'fecha_inicio_contrato' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin_contrato' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio_contrato'],
            'estado' => [$nuevo ? 'prohibited' : 'required', Rule::in(['ACTIVO', 'INACTIVO', 'LICENCIA'])],
            'coordina_aula_id' => [$nuevo ? 'nullable' : 'prohibited', 'integer', Rule::exists('aula', 'id')->whereIn(
                'periodo_id', DB::table('periodo_academico')->where('estado', 'EN_CURSO')->pluck('id')->all(),
            )],
        ];
    }

    /** RN-23: el personal tiene de 18 a 75 años */
    public function after(): array
    {
        return [function ($validator) {
            $nacimiento = $this->input('fecha_nacimiento');
            if ($validator->errors()->has('fecha_nacimiento') || ! $nacimiento) {
                return;
            }
            if ($error = ReglasPersona::edadIncoherente($nacimiento, 'PERSONAL')) {
                $validator->errors()->add('fecha_nacimiento', $error);
            }
        }];
    }

    public function messages(): array
    {
        return ReglasPersona::mensajes('DNI', true) + $this->mensajesContacto() + [
            'titulo.*' => 'El título tiene hasta 120 caracteres: letras, números, espacios y . , - ( )',
            'especialidad.*' => 'La especialidad tiene hasta 100 caracteres: letras, números, espacios y . , - ( )',
            'fecha_inicio_contrato.*' => 'La fecha de inicio del contrato no es válida.',
            'fecha_fin_contrato.after_or_equal' => 'El fin del contrato no puede ser anterior a su inicio.',
            'fecha_fin_contrato.*' => 'La fecha de fin del contrato no es válida.',
            'estado.*' => 'El estado es activo, inactivo o de licencia.',
            'coordina_aula_id.*' => 'Elige un aula del periodo en curso.',
        ];
    }
}
