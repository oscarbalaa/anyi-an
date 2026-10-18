<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    // Solo hay API: nunca se redirige, se responde 401 en JSON
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
