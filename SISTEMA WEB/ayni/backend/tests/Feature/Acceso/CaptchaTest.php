<?php

use App\Servicios\Captcha;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// M4 - RF-04, RN-18: captcha propio

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(CatalogosSeeder::class));

/** Genera un captcha conociendo su texto (se reemplaza el azar del servicio) */
function captchaConocido(string $texto = 'K7MP2'): string
{
    $id = app(Captcha::class)->generar()['id'];
    DB::table('captcha_desafio')->where('id', $id)->update([
        'respuesta_hash' => hash_hmac('sha256', $texto, (string) config('app.key')),
    ]);

    return $id;
}

it('entrega una imagen PNG y guarda solo la huella de la respuesta', function () {
    $respuesta = $this->postJson('/api/v1/captcha')->assertCreated();

    expect($respuesta->json('imagen'))->toStartWith('data:image/png;base64,')
        ->and($respuesta->json('id'))->toBeString();
    $fila = DB::table('captcha_desafio')->find($respuesta->json('id'));
    expect($fila->respuesta_hash)->toHaveLength(64)
        ->and(strtotime($fila->expira_en) - time())->toBeGreaterThan(4 * 60)->toBeLessThanOrEqual(5 * 60 + 5);
});

it('acepta la respuesta correcta sin distinguir mayúsculas', function () {
    expect(app(Captcha::class)->verificar(captchaConocido(), 'k7mp2'))->toBeTrue();
});

it('sirve una sola vez, incluso si la primera respuesta fue incorrecta', function () {
    $captcha = app(Captcha::class);
    $id = captchaConocido();
    expect($captcha->verificar($id, 'ZZZZZ'))->toBeFalse()
        ->and($captcha->verificar($id, 'K7MP2'))->toBeFalse();

    $id = captchaConocido();
    expect($captcha->verificar($id, 'K7MP2'))->toBeTrue()
        ->and($captcha->verificar($id, 'K7MP2'))->toBeFalse();
});

it('vence a los 5 minutos', function () {
    $id = captchaConocido();
    $this->travel(6)->minutes();

    expect(app(Captcha::class)->verificar($id, 'K7MP2'))->toBeFalse();
});

it('rechaza identificadores inexistentes o mal formados', function (?string $id) {
    expect(app(Captcha::class)->verificar($id, 'K7MP2'))->toBeFalse();
})->with([null, 'no-es-uuid', '00000000-0000-0000-0000-000000000000']);

it('no usa caracteres que se confunden', function () {
    expect(config('ayni.captcha.caracteres'))->not->toMatch('/[01OIL]/');
});

it('usa una imagen SVG si el servidor no puede dibujar con GD', function () {
    config(['ayni.captcha.fuente' => '/no/existe.ttf']);

    expect(app(Captcha::class)->generar()['imagen'])->toStartWith('data:image/svg+xml;base64,');
});

it('el secreto de pruebas E2E solo funciona fuera de producción y si está configurado', function () {
    $solicitud = Request::create('/', 'POST');
    $solicitud->headers->set('X-Ayni-E2E', 'secreto-de-prueba');
    $captcha = app(Captcha::class);

    config(['ayni.secreto_e2e' => null]);
    expect($captcha->verificarSolicitud($solicitud))->toBeFalse();

    config(['ayni.secreto_e2e' => 'secreto-de-prueba']);
    expect($captcha->verificarSolicitud($solicitud))->toBeTrue();

    app()['env'] = 'production';
    expect($captcha->verificarSolicitud($solicitud))->toBeFalse();
});
