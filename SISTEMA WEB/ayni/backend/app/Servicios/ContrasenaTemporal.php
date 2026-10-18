<?php

namespace App\Servicios;

use App\Mail\CorreoAyni;
use App\Models\Usuario;
use App\Support\Auditoria;
use App\Support\PerfilUsuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** RF-13: desbloquea la cuenta y le asigna una contraseña temporal */
class ContrasenaTemporal
{
    // Sin letras ni números que se confunden al dictarla (l, I, O, 0, 1)
    private const MINUSCULAS = 'abcdefghjkmnpqrstuvwxyz';

    private const MAYUSCULAS = 'ABCDEFGHJKMNPQRSTUVWXYZ';

    private const DIGITOS = '23456789';

    private const SIMBOLOS = '#$%&*';

    /** Cumple RN-16: estudiante "kmpa4827"; docente y admin "Tqmz#4821" */
    public static function generar(string $rol): string
    {
        $tomar = fn (string $de, int $n) => implode('', array_map(fn () => $de[random_int(0, strlen($de) - 1)], range(1, $n)));

        return $rol === 'ESTUDIANTE'
            ? $tomar(self::MINUSCULAS, 4).$tomar(self::DIGITOS, 4)
            : $tomar(self::MAYUSCULAS, 1).$tomar(self::MINUSCULAS, 3).$tomar(self::SIMBOLOS, 1).$tomar(self::DIGITOS, 4);
    }

    /** Devuelve la contraseña para que el administrador la entregue */
    public function asignar(Usuario $usuario, int $adminId): array
    {
        $usuario->loadMissing('rol');
        $clave = self::generar($usuario->rol->codigo);
        $estabaBloqueado = $usuario->estado === 'BLOQUEADO';

        DB::transaction(function () use ($usuario, $clave, $adminId, $estabaBloqueado) {
            $usuario->forceFill([
                'password_hash' => Hash::make($clave),
                'debe_cambiar_password' => true,
                'estado' => $usuario->estado === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO',
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
                'actualizado_por' => $adminId,
            ])->save();
            // Las sesiones abiertas con la contraseña anterior dejan de servir
            $usuario->tokens()->delete();
            DB::table('alerta_administrativa')
                ->where('clave', "usuario-bloqueado:{$usuario->id}")
                ->where('estado', 'ABIERTA')
                ->update(['estado' => 'RESUELTA', 'resuelta_en' => now(), 'updated_at' => now()]);
            Auditoria::registrar($estabaBloqueado ? 'DESBLOQUEAR' : 'CONTRASENA_TEMPORAL', 'usuario', $usuario->id);
        });

        return ['contrasena' => $clave, 'correo_enviado' => $this->avisar($usuario, $clave)];
    }

    /** Envía la contraseña al correo de la cuenta, si tiene uno */
    public function avisar(Usuario $usuario, string $clave): bool
    {
        if (! $usuario->email) {
            return false;
        }

        try {
            Mail::to($usuario->email)->send(new CorreoAyni('Tu contraseña temporal de Ayni', 'contrasena-temporal', [
                'nombre' => PerfilUsuario::de($usuario)['nombre'],
                'usuario' => $usuario->username,
                'clave' => $clave,
                'enlace' => rtrim(config('app.url'), '/').'/ingresar',
            ]));

            return true;
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar la contraseña temporal', ['usuario' => $usuario->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
