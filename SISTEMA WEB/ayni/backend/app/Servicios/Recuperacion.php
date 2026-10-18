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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/** CU-02: recuperar contraseña (RF-06, RN-25). Token propio guardado en BD, nunca URL firmada. */
class Recuperacion
{
    public const RESPUESTA = 'Si la cuenta tiene un correo registrado, te enviamos un enlace para restablecer tu contraseña. Si no tienes correo, acude a la administración del colegio.';

    public function solicitar(string $acceso, string $username): void
    {
        $usuario = Usuario::with('rol')
            ->where('username', $username)
            ->whereHas('rol', fn ($q) => $q->where('codigo', Acceso::ROLES[$acceso]))
            ->first();

        if (! $usuario?->email || $usuario->estado === 'INACTIVO') {
            return;
        }

        // Un solo enlace vigente por usuario
        DB::table('recuperacion_password')->where('usuario_id', $usuario->id)->whereNull('usado_en')->delete();

        $token = Str::random(64);
        $minutos = config('ayni.seguridad.recuperacion_minutos');
        DB::table('recuperacion_password')->insert([
            'usuario_id' => $usuario->id,
            'token_hash' => hash('sha256', $token),
            'expira_en' => now()->addMinutes($minutos),
        ]);
        Auditoria::registrar('SOLICITAR_RECUPERACION', 'usuario', $usuario->id, [], $usuario->id);

        try {
            Mail::to($usuario->email)->send(new CorreoAyni('Restablecer tu contraseña', 'recuperar-contrasena', [
                'nombre' => PerfilUsuario::de($usuario)['nombre'],
                'usuario' => $usuario->username,
                'enlace' => rtrim(config('app.url'), '/').'/restablecer?token='.$token.'&acceso='.$acceso,
                'minutos' => $minutos,
            ]));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el correo de recuperación', ['usuario' => $usuario->id, 'error' => $e->getMessage()]);
        }
    }

    public function restablecer(string $token, string $nueva, ?string $confirmacion): void
    {
        $registro = DB::table('recuperacion_password')->where('token_hash', hash('sha256', $token))->first();
        if (! $registro || $registro->usado_en || now()->gt($registro->expira_en)) {
            throw new ErrorAcceso('El enlace no es válido o ya venció. Solicita uno nuevo.', 422, ['errores' => ['token' => ['El enlace no es válido o ya venció. Solicita uno nuevo.']]]);
        }

        $usuario = Usuario::with('rol')->findOrFail($registro->usuario_id);
        Validator::make(
            ['contrasena' => $nueva, 'contrasena_confirmation' => $confirmacion],
            ['contrasena' => [...Acceso::reglasContrasena($usuario->rol->codigo, $usuario->username), 'confirmed']],
            [],
            ['contrasena' => 'la contraseña'],
        )->validate();

        DB::transaction(function () use ($usuario, $nueva, $registro) {
            $usuario->forceFill(['password_hash' => Hash::make($nueva), 'debe_cambiar_password' => false])->save();
            DB::table('recuperacion_password')->where('id', $registro->id)->update(['usado_en' => now()]);
            // Se cierran las sesiones abiertas con la contraseña anterior
            $usuario->tokens()->delete();
        });
        Auditoria::registrar('RESTABLECER_CONTRASENA', 'usuario', $usuario->id, [], $usuario->id);
    }
}
