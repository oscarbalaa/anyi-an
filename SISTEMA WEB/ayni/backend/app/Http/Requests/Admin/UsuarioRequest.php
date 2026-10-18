<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\LimpiaDatos;
use App\Models\Usuario;
use App\Support\Acceso;
use App\Support\Reglas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Crear una cuenta administrativa o editar cualquier cuenta (RF-12) */
class UsuarioRequest extends FormRequest
{
    use LimpiaDatos;

    public function rules(): array
    {
        /** @var Usuario|null $usuario */
        $usuario = $this->route('usuario');
        $reglas = $this->reglasContacto($usuario?->id);

        // De docentes y estudiantes aquí solo se cambia el contacto (sus datos están en el M7)
        if ($usuario && $usuario->rol->codigo !== 'ADMIN') {
            return $reglas;
        }

        $personaId = $usuario?->id
            ? DB::table('personal_administrativo')->where('usuario_id', $usuario->id)->value('persona_id')
            : null;

        return $reglas + [
            'nombres' => ['required', 'string', 'min:2', 'max:100', 'regex:'.Reglas::NOMBRE],
            'apellidos' => ['required', 'string', 'min:2', 'max:100', 'regex:'.Reglas::NOMBRE],
            'dni' => ['required', 'string', 'regex:'.Reglas::DNI, Rule::unique('persona', 'numero_documento')->where('tipo_documento', 'DNI')->ignore($personaId)],
            'usuario' => ['required', 'string', 'regex:'.Acceso::FORMATO_USUARIO['admin'][0], Rule::unique('usuario', 'username')->ignore($usuario?->id)],
            'cargo' => ['required', 'string', 'min:2', 'max:80', 'regex:'.Reglas::CARGO],
        ];
    }

    public function messages(): array
    {
        return $this->mensajesContacto() + [
            'nombres.required' => 'Ingresa los nombres.',
            'apellidos.required' => 'Ingresa los apellidos.',
            'nombres.min' => 'Los nombres deben tener al menos 2 letras.',
            'apellidos.min' => 'Los apellidos deben tener al menos 2 letras.',
            'nombres.max' => 'Los nombres no deben tener más de 100 caracteres.',
            'apellidos.max' => 'Los apellidos no deben tener más de 100 caracteres.',
            'nombres.regex' => Reglas::MENSAJES['nombre'],
            'apellidos.regex' => Reglas::MENSAJES['nombre'],
            'dni.required' => 'Ingresa el DNI.',
            'dni.regex' => Reglas::MENSAJES['dni'],
            'dni.unique' => 'Ya hay una persona registrada con ese DNI.',
            'usuario.required' => 'Ingresa el nombre de usuario.',
            'usuario.regex' => Acceso::FORMATO_USUARIO['admin'][1],
            'usuario.unique' => 'Ese nombre de usuario ya existe.',
            'cargo.required' => 'Ingresa el cargo.',
            'cargo.min' => 'El cargo debe tener al menos 2 caracteres.',
            'cargo.max' => 'El cargo no debe tener más de 80 caracteres.',
            'cargo.regex' => Reglas::MENSAJES['cargo'],
        ];
    }
}
