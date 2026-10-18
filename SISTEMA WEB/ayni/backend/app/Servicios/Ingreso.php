<?php

namespace App\Servicios;

use App\Exceptions\ErrorAcceso;
use App\Mail\CorreoAyni;
use App\Models\Usuario;
use App\Support\Acceso;
use App\Support\Auditoria;
use App\Support\PerfilUsuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** CU-01: iniciar sesión (RF-03, RF-05, RF-07, RF-10) */
class Ingreso
{
    public const CREDENCIALES_INCORRECTAS = 'Usuario o contraseña incorrectos.';

    /** @return array{token: string, usuario: array} */
    public function ingresar(string $acceso, string $username, string $contrasena): array
    {
        $usuario = Usuario::with('rol')->where('username', $username)->first();

        // Sin cuenta: mismo mensaje que una contraseña errada, para no revelar qué usuarios existen
        if (! $usuario) {
            throw new ErrorAcceso(self::CREDENCIALES_INCORRECTAS, 422);
        }

        $this->verificarBloqueo($usuario);

        if (! Hash::check($contrasena, $usuario->password_hash)) {
            $this->registrarFallo($usuario);
        }

        if ($usuario->estado === 'INACTIVO') {
            throw new ErrorAcceso('Tu cuenta está inactiva. Comunícate con la administración del colegio.', 403);
        }

        $rolEsperado = Acceso::ROLES[$acceso];
        if ($usuario->rol->codigo !== $rolEsperado) {
            $correcto = Acceso::desdeRol($usuario->rol->codigo);
            throw new ErrorAcceso('Esta cuenta es de '.Acceso::NOMBRES[$usuario->rol->codigo].'. Ingresa desde el acceso que le corresponde.', 403, ['acceso_correcto' => $correcto]);
        }

        $usuario->forceFill([
            'intentos_fallidos' => 0,
            'estado' => 'ACTIVO',
            'bloqueado_hasta' => null,
            'ultimo_acceso' => now(),
        ])->save();
        Auditoria::registrar('INGRESO', 'usuario', $usuario->id, [], $usuario->id);

        return [
            'token' => $usuario->createToken('web', [$acceso])->plainTextToken,
            'usuario' => PerfilUsuario::de($usuario),
        ];
    }

    private function verificarBloqueo(Usuario $usuario): void
    {
        if ($usuario->estado !== 'BLOQUEADO') {
            return;
        }

        // Bloqueo temporal (administradores): se libera solo
        if ($usuario->bloqueado_hasta && now()->gte($usuario->bloqueado_hasta)) {
            $usuario->forceFill(['estado' => 'ACTIVO', 'intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();

            return;
        }

        if ($usuario->bloqueado_hasta) {
            $minutos = max(1, (int) ceil(now()->diffInSeconds($usuario->bloqueado_hasta) / 60));
            throw new ErrorAcceso("Tu cuenta está bloqueada temporalmente. Inténtalo de nuevo en {$minutos} minuto(s).", 423, ['bloqueada' => true, 'minutos' => $minutos]);
        }

        throw new ErrorAcceso('Tu cuenta está bloqueada por intentos fallidos. Comunícate con la administración para desbloquearla.', 423, ['bloqueada' => true]);
    }

    /** RN-15: al quinto intento fallido se bloquea la cuenta */
    private function registrarFallo(Usuario $usuario): never
    {
        $maximo = config('ayni.seguridad.intentos_maximos');
        $intentos = $usuario->intentos_fallidos + 1;
        $usuario->forceFill(['intentos_fallidos' => $intentos])->save();

        if ($intentos < $maximo) {
            $restantes = $maximo - $intentos;
            $extra = $restantes <= 2 ? ['intentos_restantes' => $restantes] : [];
            throw new ErrorAcceso(self::CREDENCIALES_INCORRECTAS, 422, $extra);
        }

        $temporal = $usuario->rol->codigo === 'ADMIN';
        $minutos = config('ayni.seguridad.bloqueo_admin_minutos');
        $usuario->forceFill([
            'estado' => 'BLOQUEADO',
            'bloqueado_hasta' => $temporal ? now()->addMinutes($minutos) : null,
        ])->save();

        Auditoria::registrar('BLOQUEO', 'usuario', $usuario->id, ['intentos' => $intentos, 'temporal' => $temporal], $usuario->id);
        $this->alertarBloqueo($usuario);
        $this->avisarPorCorreo($usuario, $intentos, $temporal, $minutos);

        $mensaje = $temporal
            ? "Tu cuenta se bloqueó por {$minutos} minutos tras {$intentos} intentos fallidos."
            : "Tu cuenta se bloqueó tras {$intentos} intentos fallidos. Comunícate con la administración para desbloquearla.";

        throw new ErrorAcceso($mensaje, 423, ['bloqueada' => true]);
    }

    private function alertarBloqueo(Usuario $usuario): void
    {
        DB::table('alerta_administrativa')->insertOrIgnore([
            'tipo' => 'USUARIO_BLOQUEADO',
            'severidad' => 'CRITICO',
            'titulo' => "Cuenta bloqueada: {$usuario->username}",
            'detalle' => 'Bloqueada por intentos fallidos de ingreso. Solo la administración puede desbloquearla.',
            'entidad' => 'usuario',
            'entidad_id' => $usuario->id,
            'clave' => "usuario-bloqueado:{$usuario->id}",
        ]);
    }

    private function avisarPorCorreo(Usuario $usuario, int $intentos, bool $temporal, int $minutos): void
    {
        if (! $usuario->email) {
            return;
        }
        // RNF-12: si el correo falla, el bloqueo igual queda registrado
        try {
            Mail::to($usuario->email)->send(new CorreoAyni('Tu cuenta se bloqueó', 'cuenta-bloqueada', [
                'nombre' => PerfilUsuario::de($usuario)['nombre'],
                'usuario' => $usuario->username,
                'intentos' => $intentos,
                'fecha' => now()->format('d/m/Y H:i'),
                'temporal' => $temporal,
                'minutos' => $minutos,
            ]));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el aviso de bloqueo', ['usuario' => $usuario->id, 'error' => $e->getMessage()]);
        }
    }
}
