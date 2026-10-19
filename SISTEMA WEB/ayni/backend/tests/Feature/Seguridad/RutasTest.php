<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Tests\Soporte\Fabrica;

// M13 - RF-09 en toda la API: se recorren TODAS las rutas registradas, así una ruta nueva no queda sin revisar

uses(RefreshDatabase::class);

/** Rutas de /api/v1 con su URL de ejemplo y sus métodos (sin HEAD) */
function rutasApi(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (Ruta $r) => str_starts_with($r->uri(), 'api/v1/'))
        ->flatMap(fn (Ruta $r) => collect(array_diff($r->methods(), ['HEAD']))->map(fn ($m) => [
            'metodo' => $m,
            'url' => '/'.preg_replace('/\{[a-z]+\}/', '1', str_replace('{fecha}', '2026-06-10', $r->uri())),
            'middleware' => $r->gatherMiddleware(),
        ]))->values()->all();
}

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    $this->withoutMiddleware(ThrottleRequests::class);
});

it('sin sesión, toda ruta protegida responde 401 y solo quedan abiertas las públicas', function () {
    $publicas = [];
    foreach (rutasApi() as $r) {
        if (! in_array('auth:sanctum', $r['middleware'], true)) {
            $publicas[] = "{$r['metodo']} {$r['url']}";

            continue;
        }
        expect($this->json($r['metodo'], $r['url'])->status())->toBe(401, "{$r['metodo']} {$r['url']}");
    }

    sort($publicas);
    expect($publicas)->toBe([
        'GET /api/v1/calendario/actual',
        'GET /api/v1/health',
        'POST /api/v1/auth/ingresar',
        'POST /api/v1/auth/recuperar',
        'POST /api/v1/auth/restablecer',
        'POST /api/v1/captcha',
    ]);
});

it('cada ruta de un rol responde 403 a los otros dos, antes de buscar el registro', function () {
    $cuentas = [
        'ADMIN' => Fabrica::cuenta('ADMIN', 'admin.prueba'),
        'DOCENTE' => Fabrica::cuenta('DOCENTE', '41234567'),
        'ESTUDIANTE' => Fabrica::cuenta('ESTUDIANTE', 'E-2026-001'),
    ];
    $revisadas = 0;
    foreach (rutasApi() as $r) {
        $rol = collect($r['middleware'])->first(fn ($m) => str_starts_with($m, 'rol:'));
        if (! $rol) {
            continue;
        }
        $permitidos = explode(',', substr($rol, 4));
        foreach (array_diff(array_keys($cuentas), $permitidos) as $otro) {
            Fabrica::como($cuentas[$otro]);
            expect($this->json($r['metodo'], $r['url'])->status())->toBe(403, "{$otro} → {$r['metodo']} {$r['url']}");
            $revisadas++;
        }
    }

    // Más de doscientas combinaciones: si alguien quita un "rol:" de una ruta, esta prueba lo detecta
    expect($revisadas)->toBeGreaterThan(200);
});

it('las rutas por rol siguen el prefijo de su rol', function () {
    foreach (rutasApi() as $r) {
        $rol = collect($r['middleware'])->first(fn ($m) => str_starts_with($m, 'rol:'));
        $prefijo = explode('/', $r['url'])[3];
        $esperado = ['admin' => 'rol:ADMIN', 'docente' => 'rol:DOCENTE', 'estudiante' => 'rol:ESTUDIANTE'][$prefijo] ?? null;
        expect($rol)->toBe($esperado, "{$r['metodo']} {$r['url']}");
    }
});
