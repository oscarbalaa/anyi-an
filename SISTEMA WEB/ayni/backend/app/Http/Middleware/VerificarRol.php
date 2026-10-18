<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** RF-09: rol:ADMIN o rol:DOCENTE,ADMIN */
class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(in_array($request->user()?->rol?->codigo, $roles, true), 403);

        return $next($request);
    }
}
