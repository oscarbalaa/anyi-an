<?php

use App\Mail\CorreoAyni;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Soporte\Fabrica;

// M4 - CU-01: RF-03, RF-05, RF-07, RF-08, RF-09, RF-10

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    config(['ayni.secreto_e2e' => 'secreto']);
    Mail::fake();
});

/** Ingreso con el captcha resuelto (vía secreto de pruebas) */
function ingresar(string $acceso, string $usuario, string $clave = 'Ayni#2026')
{
    return test()->withHeader('X-Ayni-E2E', 'secreto')->postJson('/api/v1/auth/ingresar', compact('acceso', 'usuario') + ['contrasena' => $clave]);
}

describe('ingreso correcto', function () {
    it('entrega un token y los datos del usuario según su acceso', function (string $rol, string $acceso, string $usuario, string $clave) {
        Fabrica::cuenta($rol, $usuario, $clave);

        ingresar($acceso, $usuario, $clave)
            ->assertOk()
            ->assertJsonStructure(['token', 'usuario' => ['id', 'usuario', 'rol', 'acceso', 'nombre', 'iniciales', 'detalle', 'debe_cambiar_contrasena']])
            ->assertJsonPath('usuario.rol', $rol)
            ->assertJsonPath('usuario.acceso', $acceso)
            ->assertJsonPath('usuario.nombre', 'Ana Torres')
            ->assertJsonPath('usuario.iniciales', 'AT');
    })->with([
        'administrador' => ['ADMIN', 'admin', 'cmedina', 'Ayni#2026'],
        'docente' => ['DOCENTE', 'docente', '41234567', 'Ayni#2026'],
        'estudiante' => ['ESTUDIANTE', 'estudiante', 'E-2026-001', 'piki2026'],
    ]);

    it('registra el último acceso, reinicia los intentos y deja auditoría', function () {
        $id = Fabrica::cuenta('DOCENTE', '41234567', extra: ['intentos_fallidos' => 3]);

        ingresar('docente', '41234567')->assertOk();

        $usuario = DB::table('usuario')->find($id);
        expect($usuario->intentos_fallidos)->toBe(0)
            ->and($usuario->ultimo_acceso)->not->toBeNull()
            ->and(DB::table('auditoria')->where('accion', 'INGRESO')->where('entidad_id', $id)->exists())->toBeTrue();
    });

    it('el token permite consultar /auth/yo y cerrar sesión lo invalida', function () {
        Fabrica::cuenta('ESTUDIANTE', 'E-2026-001', 'piki2026');
        $token = ingresar('estudiante', 'E-2026-001', 'piki2026')->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/yo')->assertOk()->assertJsonPath('usuario.usuario', 'E-2026-001');
        $this->withToken($token)->postJson('/api/v1/auth/salir')->assertOk();
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/yo')->assertUnauthorized();
    });
});

describe('datos incorrectos', function () {
    it('responde con un mensaje genérico si la contraseña es incorrecta', function () {
        $id = Fabrica::cuenta('DOCENTE', '41234567');

        ingresar('docente', '41234567', 'Mala#2026')
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Usuario o contraseña incorrectos.')
            ->assertJsonMissingPath('token');
        expect(DB::table('usuario')->find($id)->intentos_fallidos)->toBe(1);
    });

    it('responde igual si el usuario no existe (no revela cuentas)', function () {
        ingresar('docente', '49999999')
            ->assertStatus(422)
            ->assertExactJson(['mensaje' => 'Usuario o contraseña incorrectos.']);
    });

    it('avisa cuántos intentos quedan cuando faltan pocos', function () {
        Fabrica::cuenta('DOCENTE', '41234567');

        ingresar('docente', '41234567', 'x')->assertJsonMissingPath('intentos_restantes');
        ingresar('docente', '41234567', 'x')->assertJsonMissingPath('intentos_restantes');
        ingresar('docente', '41234567', 'x')->assertJsonPath('intentos_restantes', 2);
        ingresar('docente', '41234567', 'x')->assertJsonPath('intentos_restantes', 1);
    });

    it('valida el formato del usuario de cada acceso en español', function (string $acceso, string $usuario, string $mensaje) {
        ingresar($acceso, $usuario)->assertStatus(422)->assertJsonPath('errores.usuario.0', $mensaje);
    })->with([
        ['docente', '4123456a', 'El DNI debe tener 8 dígitos.'],
        ['docente', '1234567', 'El DNI debe tener 8 dígitos.'],
        ['estudiante', 'E2026001', 'El código tiene el formato E-AAAA-NNN (por ejemplo, E-2026-001).'],
        ['admin', '1admin', 'El usuario tiene de 4 a 30 caracteres, empieza con letra y solo usa letras, números, punto o guion bajo.'],
    ]);

    it('exige la contraseña y un tipo de acceso válido', function () {
        $this->postJson('/api/v1/auth/ingresar', ['acceso' => 'docente', 'usuario' => '41234567'])
            ->assertStatus(422)
            ->assertJsonPath('errores.contrasena.0', 'Ingresa tu contraseña.');
        $this->postJson('/api/v1/auth/ingresar', ['acceso' => 'padre', 'usuario' => 'x', 'contrasena' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('errores.acceso.0', 'El tipo de acceso no es válido.');
    });

    it('rechaza el ingreso sin captcha correcto y no cuenta el intento', function () {
        $id = Fabrica::cuenta('DOCENTE', '41234567');

        $this->postJson('/api/v1/auth/ingresar', ['acceso' => 'docente', 'usuario' => '41234567', 'contrasena' => 'Ayni#2026', 'captcha_id' => 'x', 'captcha' => 'ABCDE'])
            ->assertStatus(422)
            ->assertJsonPath('errores.captcha.0', 'El código de verificación no es correcto o ya venció. Escribe el de la nueva imagen.');
        expect(DB::table('usuario')->find($id)->intentos_fallidos)->toBe(0);
    });

    it('indica el acceso correcto si la cuenta es de otro tipo', function () {
        Fabrica::cuenta('ESTUDIANTE', 'E-2026-001', 'piki2026');
        Fabrica::cuenta('ADMIN', 'cmedina');

        ingresar('estudiante', 'E-2026-001', 'piki2026')->assertOk();
        ingresar('admin', 'cmedina')->assertOk();
        // El docente no puede entrar por administración, aunque la contraseña sea correcta
        Fabrica::cuenta('DOCENTE', 'docente1');
        ingresar('admin', 'docente1')
            ->assertForbidden()
            ->assertJsonPath('acceso_correcto', 'docente');
    });

    it('no deja entrar a una cuenta inactiva', function () {
        Fabrica::cuenta('DOCENTE', '41234567', extra: ['estado' => 'INACTIVO']);

        ingresar('docente', '41234567')->assertForbidden()->assertJsonPath('mensaje', 'Tu cuenta está inactiva. Comunícate con la administración del colegio.');
    });

    it('limita a 10 intentos por minuto por usuario desde una IP', function () {
        for ($i = 0; $i < 10; $i++) {
            ingresar('docente', '49999999')->assertStatus(422);
        }
        ingresar('docente', '49999999')->assertStatus(429);
        // Otro usuario desde la misma IP (otro alumno del aula) sí puede intentar
        ingresar('docente', '49999998')->assertStatus(422);
    });

    it('limita a 60 intentos por minuto por IP, sin importar el usuario', function () {
        for ($i = 0; $i < 60; $i++) {
            ingresar('docente', (string) (40000000 + $i))->assertStatus(422);
        }
        ingresar('docente', '49999999')->assertStatus(429);
    });
});

describe('bloqueo tras 5 intentos (RN-15)', function () {
    it('bloquea a un estudiante hasta que la administración lo desbloquee', function () {
        $id = Fabrica::cuenta('ESTUDIANTE', 'E-2026-001', 'piki2026', 'valeria@ayni.test');

        for ($i = 1; $i <= 4; $i++) {
            ingresar('estudiante', 'E-2026-001', 'mala1234')->assertStatus(422);
        }
        ingresar('estudiante', 'E-2026-001', 'mala1234')->assertStatus(423)->assertJsonPath('bloqueada', true);

        $usuario = DB::table('usuario')->find($id);
        expect($usuario->estado)->toBe('BLOQUEADO')->and($usuario->bloqueado_hasta)->toBeNull();

        // Aun con la contraseña correcta y pasado un día, sigue bloqueada
        $this->travel(1)->days();
        ingresar('estudiante', 'E-2026-001', 'piki2026')->assertStatus(423)
            ->assertJsonPath('mensaje', 'Tu cuenta está bloqueada por intentos fallidos. Comunícate con la administración para desbloquearla.');
    });

    it('avisa por correo y crea una alerta para la administración', function () {
        $id = Fabrica::cuenta('ESTUDIANTE', 'E-2026-001', 'piki2026', 'valeria@ayni.test');
        for ($i = 1; $i <= 5; $i++) {
            ingresar('estudiante', 'E-2026-001', 'mala1234');
        }

        Mail::assertSent(CorreoAyni::class, fn ($m) => $m->hasTo('valeria@ayni.test') && $m->vista === 'cuenta-bloqueada');
        expect(DB::table('alerta_administrativa')->where('tipo', 'USUARIO_BLOQUEADO')->where('entidad_id', $id)->count())->toBe(1)
            ->and(DB::table('auditoria')->where('accion', 'BLOQUEO')->where('entidad_id', $id)->exists())->toBeTrue();
    });

    it('bloquea al administrador solo 15 minutos', function () {
        Fabrica::cuenta('ADMIN', 'cmedina');
        for ($i = 1; $i <= 5; $i++) {
            ingresar('admin', 'cmedina', 'Mala#1234');
        }

        ingresar('admin', 'cmedina')->assertStatus(423)->assertJsonPath('minutos', 15);
        $this->travel(16)->minutes();
        ingresar('admin', 'cmedina')->assertOk();
    });
});

describe('sesión (RN-17)', function () {
    it('se cierra tras una hora sin actividad', function () {
        Fabrica::cuenta('DOCENTE', '41234567');
        $token = ingresar('docente', '41234567')->json('token');

        $this->travel(59)->minutes();
        $this->withToken($token)->getJson('/api/v1/auth/yo')->assertOk();
        app('auth')->forgetGuards();

        // Cada uso renueva el plazo
        $this->travel(59)->minutes();
        $this->withToken($token)->getJson('/api/v1/auth/yo')->assertOk();
        app('auth')->forgetGuards();

        $this->travel(61)->minutes();
        $this->withToken($token)->getJson('/api/v1/auth/yo')->assertUnauthorized()
            ->assertJsonPath('mensaje', 'Tu sesión no es válida o expiró. Inicia sesión nuevamente.');
    });

    it('rechaza las peticiones sin token', function () {
        $this->getJson('/api/v1/auth/yo')->assertUnauthorized();
        $this->get('/api/v1/auth/yo')->assertUnauthorized();
    });
});

describe('permisos por rol (RF-09)', function () {
    it('solo deja pasar al rol indicado', function () {
        Route::middleware(['auth:sanctum', 'rol:ADMIN'])->get('/api/v1/prueba-admin', fn () => ['ok' => true]);
        Fabrica::cuenta('ADMIN', 'cmedina');
        Fabrica::cuenta('ESTUDIANTE', 'E-2026-001', 'piki2026');

        $tokenEstudiante = ingresar('estudiante', 'E-2026-001', 'piki2026')->json('token');
        $tokenAdmin = ingresar('admin', 'cmedina')->json('token');

        $this->withToken($tokenEstudiante)->getJson('/api/v1/prueba-admin')->assertForbidden()
            ->assertJsonPath('mensaje', 'No tienes permiso para realizar esta acción.');
        app('auth')->forgetGuards();
        $this->withToken($tokenAdmin)->getJson('/api/v1/prueba-admin')->assertOk();
    });
});

describe('contraseña temporal y cambio de contraseña (RF-07, RF-15, RN-16)', function () {
    it('obliga a cambiar la contraseña temporal antes de usar el sistema', function () {
        Fabrica::cuenta('ESTUDIANTE', 'E-2026-050', 'Temp1234', extra: ['debe_cambiar_password' => true]);
        $login = ingresar('estudiante', 'E-2026-050', 'Temp1234')->assertOk()->assertJsonPath('usuario.debe_cambiar_contrasena', true);
        $token = $login->json('token');

        $this->withToken($token)->getJson('/api/v1/sesion/verificar')->assertForbidden()->assertJsonPath('codigo', 'DEBE_CAMBIAR_CONTRASENA');
        $this->withToken($token)->postJson('/api/v1/auth/cambiar-contrasena', ['actual' => 'Temp1234', 'contrasena' => 'piki2027x', 'contrasena_confirmation' => 'piki2027x'])
            ->assertOk()
            ->assertJsonPath('usuario.debe_cambiar_contrasena', false);
        $this->withToken($token)->getJson('/api/v1/sesion/verificar')->assertOk();
    });

    it('aplica la política de contraseñas según el rol', function (string $rol, string $usuario, string $nueva, ?string $error) {
        Fabrica::cuenta($rol, $usuario, 'Actual#2026');
        $acceso = ['ADMIN' => 'admin', 'DOCENTE' => 'docente', 'ESTUDIANTE' => 'estudiante'][$rol];
        $token = ingresar($acceso, $usuario, 'Actual#2026')->json('token');

        $respuesta = $this->withToken($token)->postJson('/api/v1/auth/cambiar-contrasena', ['actual' => 'Actual#2026', 'contrasena' => $nueva, 'contrasena_confirmation' => $nueva]);
        $error ? $respuesta->assertStatus(422)->assertJsonPath('errores.contrasena.0', $error) : $respuesta->assertOk();
    })->with([
        'docente sin símbolo' => ['DOCENTE', '41234567', 'Ayni2026x', 'La nueva contraseña debe contener al menos un símbolo.'],
        'docente sin mayúscula' => ['DOCENTE', '41234567', 'ayni#2026', 'La nueva contraseña debe contener al menos una mayúscula y una minúscula.'],
        'docente válida' => ['DOCENTE', '41234567', 'Nueva#2027', null],
        'admin corta' => ['ADMIN', 'cmedina', 'A#1b', 'La nueva contraseña debe tener al menos 8 caracteres.'],
        'estudiante sin números' => ['ESTUDIANTE', 'E-2026-001', 'pikipiki', 'La nueva contraseña debe contener al menos un número.'],
        'estudiante solo números' => ['ESTUDIANTE', 'E-2026-001', '12345678', 'La nueva contraseña debe contener al menos una letra.'],
        'estudiante válida' => ['ESTUDIANTE', 'E-2026-001', 'piki2027', null],
        'igual al usuario' => ['ESTUDIANTE', 'E-2026-001', 'e-2026-001', 'La contraseña no puede ser igual a tu usuario.'],
    ]);

    it('exige la contraseña actual correcta, la confirmación y una contraseña distinta', function () {
        Fabrica::cuenta('DOCENTE', '41234567', 'Actual#2026');
        $token = ingresar('docente', '41234567', 'Actual#2026')->json('token');
        $cambiar = fn (array $datos) => $this->withToken($token)->postJson('/api/v1/auth/cambiar-contrasena', $datos);

        $cambiar(['actual' => 'Otra#2026', 'contrasena' => 'Nueva#2027', 'contrasena_confirmation' => 'Nueva#2027'])
            ->assertStatus(422)->assertJsonPath('errores.actual.0', 'La contraseña actual es incorrecta.');
        $cambiar(['actual' => 'Actual#2026', 'contrasena' => 'Nueva#2027', 'contrasena_confirmation' => 'Nueva#2028'])
            ->assertStatus(422)->assertJsonPath('errores.contrasena.0', 'La confirmación de la nueva contraseña no coincide.');
        $cambiar(['actual' => 'Actual#2026', 'contrasena' => 'Actual#2026', 'contrasena_confirmation' => 'Actual#2026'])
            ->assertStatus(422)->assertJsonPath('errores.contrasena.0', 'La nueva contraseña debe ser distinta de la actual.');
    });

    it('al cambiar la contraseña cierra las otras sesiones', function () {
        Fabrica::cuenta('DOCENTE', '41234567', 'Actual#2026');
        $otra = ingresar('docente', '41234567', 'Actual#2026')->json('token');
        $actual = ingresar('docente', '41234567', 'Actual#2026')->json('token');

        $this->withToken($actual)->postJson('/api/v1/auth/cambiar-contrasena', ['actual' => 'Actual#2026', 'contrasena' => 'Nueva#2027', 'contrasena_confirmation' => 'Nueva#2027'])->assertOk();
        app('auth')->forgetGuards();

        $this->withToken($otra)->getJson('/api/v1/auth/yo')->assertUnauthorized();
        app('auth')->forgetGuards();
        $this->withToken($actual)->getJson('/api/v1/auth/yo')->assertOk();
    });
});
