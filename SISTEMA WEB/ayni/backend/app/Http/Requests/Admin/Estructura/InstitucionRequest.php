<?php

namespace App\Http\Requests\Admin\Estructura;

use App\Http\Requests\LimpiaDatos;
use App\Support\Reglas;
use Illuminate\Foundation\Http\FormRequest;

/** RF-26: datos del colegio, nota aprobatoria, umbrales de riesgo y escala literal */
class InstitucionRequest extends FormRequest
{
    use LimpiaDatos;

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'codigo_modular' => ['nullable', 'regex:/^\d{7}$/D'],
            'ugel' => ['nullable', 'string', 'max:80'],
            'direccion' => ['nullable', 'string', 'min:5', 'max:200', 'regex:/^[\p{L}\d .,#\-\/°]+$/uD'],
            'telefono' => ['nullable', 'regex:/^\d{6,9}$/D'],
            'correo' => ['nullable', 'string', 'max:120', 'regex:'.Reglas::CORREO],
            'nota_aprobatoria' => ['required', 'numeric', 'between:0,20'],
            'umbral_asistencia_alto' => ['required', 'integer', 'between:0,100', 'lt:umbral_asistencia_medio'],
            'umbral_asistencia_medio' => ['required', 'integer', 'between:0,100'],
            'umbral_promedio_alto' => ['required', 'numeric', 'between:0,20', 'lte:umbral_promedio_medio'],
            'umbral_promedio_medio' => ['required', 'numeric', 'between:0,20'],
            'umbral_pendientes_alto' => ['required', 'integer', 'between:1,20'],
            'dias_cierre_bimestre' => ['required', 'integer', 'between:0,30'],
            'escala' => ['required', 'array', 'size:4'],
            'escala.*.codigo' => ['required', 'distinct', 'in:AD,A,B,C'],
            'escala.*.nota_minima' => ['required', 'numeric', 'between:0,20'],
            'escala.*.nota_maxima' => ['required', 'numeric', 'between:0,20', 'gte:escala.*.nota_minima'],
        ];
    }

    /** RN-11: los rangos cubren de 0 a 20 sin huecos ni cruces, de C (menor) a AD (mayor) */
    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $escala = collect($this->input('escala'))->keyBy('codigo');
            $orden = ['C', 'B', 'A', 'AD'];
            $esperado = 0.0;
            foreach ($orden as $i => $codigo) {
                $r = $escala[$codigo];
                $fin = $i === 3 ? 20.0 : null;
                if (abs((float) $r['nota_minima'] - $esperado) > 0.001 || ($fin !== null && abs((float) $r['nota_maxima'] - $fin) > 0.001)) {
                    $validator->errors()->add('escala', 'Los rangos deben ir de 0 a 20, de C a AD, sin huecos ni cruces (cada uno empieza 0,01 después del anterior).');

                    return;
                }
                $esperado = round((float) $r['nota_maxima'] + 0.01, 2);
            }
        }];
    }

    public function messages(): array
    {
        return $this->mensajesContacto() + [
            'nombre.*' => 'El nombre del colegio tiene de 3 a 150 caracteres.',
            'codigo_modular.*' => 'El código modular tiene 7 dígitos.',
            'ugel.*' => 'La UGEL no debe tener más de 80 caracteres.',
            'direccion.*' => 'La dirección tiene de 5 a 200 caracteres: letras, números, espacios y . , # - / °',
            'telefono.*' => 'El teléfono tiene de 6 a 9 dígitos.',
            'nota_aprobatoria.*' => 'La nota aprobatoria es un número de 0 a 20.',
            'umbral_asistencia_alto.lt' => 'El umbral de asistencia ALTO debe ser menor que el MEDIO.',
            'umbral_asistencia_alto.*' => 'El umbral es un porcentaje de 0 a 100.',
            'umbral_asistencia_medio.*' => 'El umbral es un porcentaje de 0 a 100.',
            'umbral_promedio_alto.lte' => 'El umbral de promedio ALTO no puede ser mayor que el MEDIO.',
            'umbral_promedio_alto.*' => 'El umbral de promedio es un número de 0 a 20.',
            'umbral_promedio_medio.*' => 'El umbral de promedio es un número de 0 a 20.',
            'umbral_pendientes_alto.*' => 'Las entregas pendientes son un número entero de 1 a 20.',
            'dias_cierre_bimestre.*' => 'Los días para el cierre son un número entero de 0 a 30.',
            // Lo específico primero: Laravel usa el primer patrón que coincide
            'escala.*.nota_maxima.gte' => 'En cada rango, la nota máxima no puede ser menor que la mínima.',
            'escala.*.codigo.*' => 'La escala tiene las 4 equivalencias: AD, A, B y C.',
            'escala.*.*' => 'Cada rango va de 0 a 20.',
            'escala.*' => 'La escala tiene las 4 equivalencias: AD, A, B y C.',
        ];
    }
}
