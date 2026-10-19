<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Cabeceras de seguridad de la API; las de React y los archivos estáticos las pone el .htaccess */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);
        $h = $respuesta->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // La API solo devuelve JSON y archivos: no carga nada ni se muestra en un marco
        $h->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // Datos personales: el navegador no los guarda en caché
        if ($request->bearerToken()) {
            $h->set('Cache-Control', 'no-store, private');
        }
        if ($request->secure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $respuesta;
    }
}
