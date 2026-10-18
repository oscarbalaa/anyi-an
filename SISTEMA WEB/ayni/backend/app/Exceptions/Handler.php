<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    private const MENSAJES = [
        401 => 'Tu sesión no es válida o expiró. Inicia sesión nuevamente.',
        403 => 'No tienes permiso para realizar esta acción.',
        404 => 'El recurso solicitado no existe.',
        405 => 'Método no permitido para esta ruta.',
        413 => 'El archivo o los datos enviados superan el tamaño permitido.',
        419 => 'La página expiró. Recarga e inténtalo de nuevo.',
        429 => 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.',
        500 => 'Ocurrió un error inesperado. Inténtalo más tarde.',
        503 => 'El servicio no está disponible en este momento.',
    ];

    public function register(): void
    {
        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $this->respuestaJson($e);
            }
        });
    }

    private function respuestaJson(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'mensaje' => 'Los datos enviados no son válidos.',
                'errores' => $e->errors(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $estado = match (true) {
            $e instanceof AuthenticationException => 401,
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            default => 500,
        };

        $cuerpo = ['mensaje' => self::MENSAJES[$estado] ?? self::MENSAJES[500]];

        if ($estado === 500 && config('app.debug')) {
            $cuerpo['detalle'] = $e->getMessage();
        }

        $cabeceras = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return response()->json($cuerpo, $estado, $cabeceras, JSON_UNESCAPED_UNICODE);
    }
}
