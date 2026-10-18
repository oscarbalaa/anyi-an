<?php

namespace App\Http\Requests\Perfil;

use App\Http\Requests\LimpiaDatos;
use Illuminate\Foundation\Http\FormRequest;

/** RF-14: cada usuario edita su celular y su correo */
class PerfilRequest extends FormRequest
{
    use LimpiaDatos;

    public function rules(): array
    {
        return $this->reglasContacto($this->user()->id);
    }

    public function messages(): array
    {
        return $this->mensajesContacto();
    }
}
