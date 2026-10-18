<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ErrorAcceso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CambioContrasenaRequest;
use App\Http\Requests\Auth\IngresoRequest;
use App\Http\Requests\Auth\RecuperarRequest;
use App\Servicios\Captcha;
use App\Servicios\Ingreso;
use App\Servicios\Recuperacion;
use App\Support\Auditoria;
use App\Support\PerfilUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const CAPTCHA_INCORRECTO = 'El código de verificación no es correcto o ya venció. Escribe el de la nueva imagen.';

    public function captcha(Request $request, Captcha $captcha): JsonResponse
    {
        return $this->json($captcha->generar($request->ip()), 201);
    }

    public function ingresar(IngresoRequest $request, Captcha $captcha, Ingreso $ingreso): JsonResponse
    {
        $this->exigirCaptcha($request, $captcha);

        return $this->json($ingreso->ingresar($request->input('acceso'), $request->input('usuario'), $request->input('contrasena')));
    }

    public function yo(Request $request): JsonResponse
    {
        return $this->json(['usuario' => PerfilUsuario::de($request->user()->load('rol'))]);
    }

    public function salir(Request $request): JsonResponse
    {
        Auditoria::registrar('SALIDA', 'usuario', $request->user()->id);
        $request->user()->currentAccessToken()->delete();

        return $this->json(['mensaje' => 'Cerraste sesión.']);
    }

    public function cambiarContrasena(CambioContrasenaRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $usuario->forceFill(['password_hash' => Hash::make($request->input('contrasena')), 'debe_cambiar_password' => false])->save();
        // Se cierran las demás sesiones; la actual sigue abierta
        $usuario->tokens()->where('id', '!=', $usuario->currentAccessToken()->id)->delete();
        Auditoria::registrar('CAMBIAR_CONTRASENA', 'usuario', $usuario->id);

        return $this->json(['mensaje' => 'Tu contraseña se actualizó.', 'usuario' => PerfilUsuario::de($usuario->load('rol'))]);
    }

    public function recuperar(RecuperarRequest $request, Captcha $captcha, Recuperacion $recuperacion): JsonResponse
    {
        $this->exigirCaptcha($request, $captcha);
        $recuperacion->solicitar($request->input('acceso'), $request->input('usuario'));

        return $this->json(['mensaje' => Recuperacion::RESPUESTA]);
    }

    public function restablecer(Request $request, Recuperacion $recuperacion): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:64']], ['token.*' => 'El enlace no es válido o ya venció. Solicita uno nuevo.']);
        $recuperacion->restablecer($request->input('token'), (string) $request->input('contrasena'), $request->input('contrasena_confirmation'));

        return $this->json(['mensaje' => 'Tu contraseña se actualizó. Ya puedes ingresar.']);
    }

    private function exigirCaptcha(Request $request, Captcha $captcha): void
    {
        if (! $captcha->verificarSolicitud($request)) {
            throw new ErrorAcceso(self::CAPTCHA_INCORRECTO, 422, ['errores' => ['captcha' => [self::CAPTCHA_INCORRECTO]]]);
        }
    }

    private function json(array $datos, int $estado = 200): JsonResponse
    {
        return response()->json($datos, $estado, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
