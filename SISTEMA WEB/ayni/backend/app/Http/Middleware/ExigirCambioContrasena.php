<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** RF-07: con contraseña temporal solo se puede cambiar la contraseña */
class ExigirCambioContrasena
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->debe_cambiar_password) {
            return response()->json([
                'mensaje' => 'Debes cambiar tu contraseña temporal antes de continuar.',
                'codigo' => 'DEBE_CAMBIAR_CONTRASENA',
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }

        return $next($request);
    }
}
