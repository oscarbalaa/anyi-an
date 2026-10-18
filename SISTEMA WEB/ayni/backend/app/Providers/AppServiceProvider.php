<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // RN-17: la sesión vence tras una hora sin actividad (además del máximo de 12 h)
        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $token, bool $valido) {
            $limite = now()->subMinutes(config('ayni.seguridad.sesion_inactividad_minutos'));
            $ultimaActividad = $token->last_used_at ?? $token->created_at;

            return $valido && $ultimaActividad->gt($limite);
        });
    }
}
