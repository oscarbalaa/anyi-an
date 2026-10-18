<?php

namespace App\Servicios\Personas;

use App\Servicios\ContrasenaTemporal;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Lo común a docentes y estudiantes: su persona y su cuenta de acceso */
class Personas
{
    public const CAMPOS = ['tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'celular', 'correo', 'direccion'];

    public function crearPersona(array $d): int
    {
        return DB::table('persona')->insertGetId(array_intersect_key($d, array_flip(self::CAMPOS)));
    }

    /** Devuelve los cambios {campo: {antes, despues}} para la auditoría */
    public function actualizarPersona(int $personaId, array $d): array
    {
        $antes = (array) DB::table('persona')->find($personaId);
        $nuevos = array_intersect_key($d, array_flip(self::CAMPOS));
        $cambios = [];
        foreach ($nuevos as $campo => $valor) {
            if (($antes[$campo] ?? null) != $valor) {
                $cambios[$campo] = ['antes' => $antes[$campo] ?? null, 'despues' => $valor];
            }
        }
        if ($cambios) {
            DB::table('persona')->where('id', $personaId)->update($nuevos + ['updated_at' => now()]);
        }

        return $cambios;
    }

    /** Cuenta con contraseña temporal; la clave se devuelve para entregarla (y se envía si hay correo) */
    public function crearCuenta(string $rol, string $usuario, ?string $correo, int $adminId, bool $activa = true): array
    {
        $clave = ContrasenaTemporal::generar($rol);
        $id = DB::table('usuario')->insertGetId([
            'rol_id' => DB::table('rol')->where('codigo', $rol)->value('id'),
            'username' => $usuario,
            'email' => $correo,
            'password_hash' => Hash::make($clave),
            'estado' => $activa ? 'ACTIVO' : 'INACTIVO',
            'debe_cambiar_password' => true,
            'creado_por' => $adminId,
            'actualizado_por' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Auditoria::registrar('CREAR', 'usuario', $id, ['usuario' => $usuario, 'rol' => $rol, 'activa' => $activa]);

        return ['id' => $id, 'contrasena' => $clave];
    }

    /** Nombre corto "Luis Paredes" */
    public static function corto(?string $nombres, ?string $apellidos): ?string
    {
        return $nombres ? explode(' ', $nombres)[0].' '.explode(' ', (string) $apellidos)[0] : null;
    }
}
