<?php

use App\Mail\CorreoAyni;
use App\Servicios\Recuperacion;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Soporte\Fabrica;

// M4 - CU-02: RF-06, RN-25

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    config(['ayni.secreto_e2e' => 'secreto', 'app.url' => 'http://localhost:5173']);
    Mail::fake();
});

function solicitar(string $acceso, string $usuario)
{
    return test()->withHeader('X-Ayni-E2E', 'secreto')->postJson('/api/v1/auth/recuperar', compact('acceso', 'usuario'));
}

/** Pide la recuperación y devuelve el token que llegó por correo */
function tokenPorCorreo(string $correo): string
{
    $token = null;
    Mail::assertSent(CorreoAyni::class, function ($m) use ($correo, &$token) {
        if ($m->hasTo($correo) && $m->vista === 'recuperar-contrasena') {
            parse_str(parse_url($m->datos['enlace'], PHP_URL_QUERY), $consulta);
            $token = $consulta['token'];

            return true;
        }

        return false;
    });

    return $token;
}

it('envía un enlace de un solo uso al correo registrado', function () {
    Fabrica::cuenta('DOCENTE', '41234567', correo: 'luis@ayni.test');

    solicitar('docente', '41234567')->assertOk()->assertExactJson(['mensaje' => Recuperacion::RESPUESTA]);

    $token = tokenPorCorreo('luis@ayni.test');
    expect($token)->toHaveLength(64)
        ->and(DB::table('recuperacion_password')->value('token_hash'))->toBe(hash('sha256', $token));
    Mail::assertSent(CorreoAyni::class, fn ($m) => str_starts_with($m->datos['enlace'], 'http://localhost:5173/restablecer?token='));
});

it('responde lo mismo si la cuenta no existe, es de otro acceso o no tiene correo', function () {
    Fabrica::cuenta('DOCENTE', '41234567', correo: 'luis@ayni.test');
    Fabrica::cuenta('ESTUDIANTE', 'E-2026-002', 'piki2026');

    solicitar('docente', '49999999')->assertOk()->assertExactJson(['mensaje' => Recuperacion::RESPUESTA]);
    solicitar('estudiante', 'E-2026-002')->assertOk()->assertExactJson(['mensaje' => Recuperacion::RESPUESTA]);
    Mail::assertNothingSent();
});

it('exige el captcha', function () {
    $this->postJson('/api/v1/auth/recuperar', ['acceso' => 'docente', 'usuario' => '41234567'])
        ->assertStatus(422)
        ->assertJsonPath('errores.captcha.0', fn ($m) => str_contains($m, 'código de verificación'));
});

it('restablece la contraseña una sola vez y cierra las sesiones abiertas', function () {
    $id = Fabrica::cuenta('DOCENTE', '41234567', 'Vieja#2026', 'luis@ayni.test');
    $sesion = test()->withHeader('X-Ayni-E2E', 'secreto')->postJson('/api/v1/auth/ingresar', ['acceso' => 'docente', 'usuario' => '41234567', 'contrasena' => 'Vieja#2026'])->json('token');
    solicitar('docente', '41234567');
    $token = tokenPorCorreo('luis@ayni.test');

    $this->postJson('/api/v1/auth/restablecer', ['token' => $token, 'contrasena' => 'Nueva#2027', 'contrasena_confirmation' => 'Nueva#2027'])->assertOk();
    $this->postJson('/api/v1/auth/restablecer', ['token' => $token, 'contrasena' => 'Otra#2028', 'contrasena_confirmation' => 'Otra#2028'])
        ->assertStatus(422)->assertJsonPath('errores.token.0', 'El enlace no es válido o ya venció. Solicita uno nuevo.');

    expect(DB::table('personal_access_tokens')->where('tokenable_id', $id)->count())->toBe(0);
    app('auth')->forgetGuards();
    $this->withToken($sesion)->getJson('/api/v1/auth/yo')->assertUnauthorized();
    test()->withHeader('X-Ayni-E2E', 'secreto')->postJson('/api/v1/auth/ingresar', ['acceso' => 'docente', 'usuario' => '41234567', 'contrasena' => 'Nueva#2027'])->assertOk();
});

it('el enlace vence a los 30 minutos', function () {
    Fabrica::cuenta('DOCENTE', '41234567', correo: 'luis@ayni.test');
    solicitar('docente', '41234567');
    $token = tokenPorCorreo('luis@ayni.test');

    $this->travel(31)->minutes();
    $this->postJson('/api/v1/auth/restablecer', ['token' => $token, 'contrasena' => 'Nueva#2027', 'contrasena_confirmation' => 'Nueva#2027'])->assertStatus(422);
});

it('aplica la política de contraseñas del rol al restablecer', function () {
    Fabrica::cuenta('DOCENTE', '41234567', correo: 'luis@ayni.test');
    solicitar('docente', '41234567');
    $token = tokenPorCorreo('luis@ayni.test');

    $this->postJson('/api/v1/auth/restablecer', ['token' => $token, 'contrasena' => 'debil123', 'contrasena_confirmation' => 'debil123'])
        ->assertStatus(422)
        ->assertJsonPath('errores.contrasena.0', fn ($m) => str_contains($m, 'mayúscula'));
});

it('rechaza tokens inventados', function () {
    $this->postJson('/api/v1/auth/restablecer', ['token' => 'corto', 'contrasena' => 'Nueva#2027'])->assertStatus(422);
    $this->postJson('/api/v1/auth/restablecer', ['token' => str_repeat('a', 64), 'contrasena' => 'Nueva#2027', 'contrasena_confirmation' => 'Nueva#2027'])->assertStatus(422);
});
