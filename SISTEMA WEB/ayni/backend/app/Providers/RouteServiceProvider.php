<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Límites del ingreso y la recuperación (además del bloqueo por cuenta).
        // Por IP el margen es amplio: en el colegio toda un aula sale por la misma IP.
        $porUsuario = fn (string $nombre, int $limite) => fn (Request $request) => [
            Limit::perMinute($limite)->by("{$nombre}|".$request->ip().'|'.mb_strtolower((string) $request->input('usuario'))),
            Limit::perMinute($limite * 6)->by("{$nombre}|".$request->ip()),
        ];
        RateLimiter::for('ingreso', $porUsuario('ingreso', 10));
        RateLimiter::for('recuperacion', $porUsuario('recuperacion', 5));
        RateLimiter::for('captcha', fn (Request $request) => Limit::perMinute(120)->by('captcha|'.$request->ip()));

        // Por cuenta (el token Bearer se lee con el guard de Sanctum: el de "web" no lo ve).
        // Sin sesión, por IP con margen para un aula que sale por la misma IP.
        RateLimiter::for('api', function (Request $request) {
            $usuario = $request->user('sanctum');

            return $usuario
                ? Limit::perMinute(config('ayni.limite_api.cuenta'))->by("api|{$usuario->id}")
                : Limit::perMinute(config('ayni.limite_api.ip'))->by('api|'.$request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
