<?php

namespace App\Http\Requests\Admin\Personas;

use Illuminate\Foundation\Http\FormRequest;

/** Solo .xlsx de hasta 5 MB; el contenido real se comprueba al abrirlo */
class ImportacionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:5120', function ($atributo, $archivo, $falla) {
                if (mb_strtolower($archivo->getClientOriginalExtension()) !== 'xlsx') {
                    $falla('Sube un archivo de Excel (.xlsx). Otros formatos como .xls o .csv no se aceptan.');
                }
            }],
            'matricular' => ['sometimes', 'boolean'],
            'crear_usuario' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Elige el archivo de Excel.',
            'archivo.max' => 'El archivo pesa más de 5 MB.',
            'archivo.file' => 'No se pudo recibir el archivo. Vuelve a subirlo.',
            'archivo.uploaded' => 'No se pudo recibir el archivo. Vuelve a subirlo.',
            'matricular.*' => 'Indica si se matricula a los estudiantes.',
            'crear_usuario.*' => 'Indica si se crean los usuarios.',
        ];
    }
}
