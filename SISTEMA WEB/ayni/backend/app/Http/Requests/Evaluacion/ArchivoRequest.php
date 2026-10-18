<?php

namespace App\Http\Requests\Evaluacion;

use App\Servicios\Archivos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Entrega (PDF) o material de apoyo; el tipo real y el tamaño se revisan en el servicio (RN-26) */
class ArchivoRequest extends FormRequest
{
    public function rules(): array
    {
        $entrega = $this->routeIs('estudiante.*');

        return [
            'archivo' => [$entrega ? 'nullable' : 'required', 'file'],
            'accion' => $entrega ? ['required', Rule::in(['BORRADOR', 'ENTREGAR'])] : ['prohibited'],
        ];
    }

    public function messages(): array
    {
        $limite = Archivos::limiteTexto();

        return [
            'archivo.required' => 'Elige el archivo.',
            'archivo.uploaded' => "No se pudo recibir el archivo: puede que pese más de {$limite}.",
            'archivo.*' => 'No se pudo recibir el archivo. Vuelve a subirlo.',
            'accion.*' => 'Elige si guardas un borrador o entregas la actividad.',
        ];
    }
}
