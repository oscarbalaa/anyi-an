<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Soporte\Fabrica;

// M13 - cabeceras HTTP y CORS de la API (las de React las pone el .htaccess; las revisa la prueba E2E del M1)

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(CatalogosSeeder::class));

it('toda respuesta de la API lleva las cabeceras de seguridad', function () {
    $r = $this->getJson('/api/v1/health')->assertOk();

    expect($r->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($r->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($r->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($r->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; frame-ancestors 'none'")
        ->and($r->headers->get('Permissions-Policy'))->toContain('camera=()')
        // Sin HTTPS no se fuerza HSTS (en local no hay certificado)
        ->and($r->headers->has('Strict-Transport-Security'))->toBeFalse();

    // También en los errores
    expect($this->getJson('/api/v1/no-existe')->assertNotFound()->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('con sesión las respuestas no se guardan en la caché del navegador', function () {
    Fabrica::como(Fabrica::cuenta('ADMIN', 'admin.prueba'));
    $r = $this->getJson('/api/v1/perfil', ['Authorization' => 'Bearer x'])->assertOk();

    expect($r->headers->get('Cache-Control'))->toContain('no-store');
});

it('con HTTPS pide al navegador usar siempre HTTPS (HSTS)', function () {
    $r = $this->get('https://localhost/api/v1/health')->assertOk();

    expect($r->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000');
});

it('CORS: otro sitio no puede llamar a la API desde el navegador', function () {
    $r = $this->call('OPTIONS', '/api/v1/auth/ingresar', server: [
        'HTTP_ORIGIN' => 'https://malo.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);
    expect($r->headers->has('Access-Control-Allow-Origin'))->toBeFalse();

    $r = $this->getJson('/api/v1/health', ['Origin' => 'https://malo.example']);
    expect($r->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});

it('CORS: solo los orígenes configurados en AYNI_ORIGENES_CORS', function () {
    config(['cors.allowed_origins' => ['https://colegio.example']]);

    $r = $this->getJson('/api/v1/health', ['Origin' => 'https://colegio.example']);
    expect($r->headers->get('Access-Control-Allow-Origin'))->toBe('https://colegio.example');

    $r = $this->getJson('/api/v1/health', ['Origin' => 'https://malo.example']);
    expect($r->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://malo.example');
});

it('los errores no muestran detalles técnicos fuera de depuración', function () {
    config(['app.debug' => false]);
    $r = $this->postJson('/api/v1/auth/ingresar', ['acceso' => ['no', 'es', 'texto'], 'usuario' => ['x']]);

    expect($r->status())->toBe(422)
        ->and($r->getContent())->not->toContain('vendor/')
        ->and($r->getContent())->not->toContain('Stack trace');
});
