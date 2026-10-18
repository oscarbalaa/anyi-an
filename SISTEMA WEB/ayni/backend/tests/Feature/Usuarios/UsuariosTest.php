<?php

use App\Mail\CorreoAyni;
use App\Models\Usuario;
use App\Servicios\ContrasenaTemporal;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Soporte\Fabrica;

// M5 - CU-04: RF-11, RF-12, RF-13, RF-16

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    config(['ayni.secreto_e2e' => 'secreto']);
    Mail::fake();
    $this->admin = Fabrica::cuenta('ADMIN', 'cmedina', correo: 'carlos@ayni.test');
    DB::table('personal_administrativo')->where('usuario_id', $this->admin)->update(['cargo' => 'Director']);
    DB::table('persona')->where('id', DB::table('personal_administrativo')->where('usuario_id', $this->admin)->value('persona_id'))
        ->update(['nombres' => 'Carlos', 'apellidos' => 'Medina Rojas']);
    Sanctum::actingAs(Usuario::find($this->admin));
});

function nuevoAdmin(array $cambios = []): array
{
    return $cambios + [
        'nombres' => 'patricia  ROMERO',
        'apellidos' => "d'angelo pérez-gómez",
        'dni' => '45678912',
        'usuario' => 'promero',
        'correo' => 'Patricia.Romero@Ayni.TEST ',
        'celular' => '987 654 321',
        'cargo' => 'Secretaría académica',
    ];
}

function cuentaCon(string $rol, string $usuario, string $nombres, string $apellidos, array $extra = []): int
{
    $id = Fabrica::cuenta($rol, $usuario, $rol === 'ESTUDIANTE' ? 'piki2026' : 'Ayni#2026', extra: $extra);
    $tabla = ['ADMIN' => 'personal_administrativo', 'DOCENTE' => 'docente', 'ESTUDIANTE' => 'estudiante'][$rol];
    DB::table('persona')->where('id', DB::table($tabla)->where('usuario_id', $id)->value('persona_id'))->update(compact('nombres', 'apellidos'));

    return $id;
}

describe('lista de usuarios (RF-11)', function () {
    beforeEach(function () {
        cuentaCon('DOCENTE', '41234567', 'Luis', 'Paredes Aranda');
        cuentaCon('ESTUDIANTE', 'E-2026-001', 'Valeria', 'Quispe Mendoza');
        cuentaCon('ESTUDIANTE', 'E-2026-002', 'Andrés', 'Mori Lazo', ['estado' => 'BLOQUEADO', 'intentos_fallidos' => 5]);
        cuentaCon('DOCENTE', '42345678', 'Carmen', 'Rivas Soto', ['estado' => 'INACTIVO']);
    });

    it('lista con paginación, resumen por estado y las bloqueadas primero', function () {
        $r = $this->getJson('/api/v1/admin/usuarios')->assertOk();

        expect($r->json('total'))->toBe(5)
            ->and($r->json('por_pagina'))->toBe(10)
            ->and($r->json('datos.0.usuario'))->toBe('E-2026-002')
            ->and($r->json('datos.0.estado'))->toBe('BLOQUEADO')
            ->and($r->json('resumen'))->toMatchArray(['ACTIVO' => 3, 'BLOQUEADO' => 1, 'INACTIVO' => 1]);
        expect(collect($r->json('datos'))->firstWhere('usuario', 'cmedina'))
            ->toMatchArray(['nombre' => 'Carlos Medina Rojas', 'rol' => 'ADMIN', 'rol_nombre' => 'Administrador', 'detalle' => 'Director']);
    });

    it('busca por nombre o usuario sin importar tildes ni mayúsculas', function (string $termino, string $esperado) {
        $r = $this->getJson('/api/v1/admin/usuarios?buscar='.urlencode($termino))->assertOk();
        expect(collect($r->json('datos'))->pluck('usuario')->all())->toBe([$esperado]);
    })->with([
        'sin tilde' => ['andres', 'E-2026-002'],
        'apellido y nombre' => ['quispe mendoza', 'E-2026-001'],
        'mayúsculas' => ['LUIS PAREDES', '41234567'],
        'usuario' => ['cmed', 'cmedina'],
    ]);

    it('no trata % ni _ como comodines', function () {
        $this->getJson('/api/v1/admin/usuarios?buscar=%25')->assertOk()->assertJsonPath('total', 0);
    });

    it('filtra por rol y por estado', function () {
        expect($this->getJson('/api/v1/admin/usuarios?rol=DOCENTE')->json('total'))->toBe(2)
            ->and($this->getJson('/api/v1/admin/usuarios?estado=INACTIVO')->json('datos.0.usuario'))->toBe('42345678')
            ->and($this->getJson('/api/v1/admin/usuarios?rol=DOCENTE&estado=ACTIVO')->json('total'))->toBe(1);
    });

    it('rechaza filtros inválidos', function () {
        $this->getJson('/api/v1/admin/usuarios?rol=SUPER')->assertStatus(422)->assertJsonPath('errores.rol.0', 'El rol no es válido.');
        $this->getJson('/api/v1/admin/usuarios?estado=X')->assertStatus(422);
    });

    it('pagina de 10 en 10', function () {
        foreach (range(10, 20) as $n) {
            Fabrica::cuenta('ESTUDIANTE', "E-2026-0{$n}", 'piki2026');
        }
        $r = $this->getJson('/api/v1/admin/usuarios?pagina=2')->assertOk();
        expect($r->json('ultima_pagina'))->toBe(2)->and($r->json('datos'))->toHaveCount(6);
    });

    it('no hace una consulta por cada fila', function () {
        foreach (range(10, 19) as $n) {
            Fabrica::cuenta('ESTUDIANTE', "E-2026-0{$n}", 'piki2026');
        }
        DB::enableQueryLog();
        $this->getJson('/api/v1/admin/usuarios')->assertOk();
        expect(count(DB::getQueryLog()))->toBeLessThan(12);
    });
});

describe('permisos', function () {
    it('solo la administración gestiona usuarios (RF-09)', function (string $metodo, string $ruta) {
        $docente = Fabrica::cuenta('DOCENTE', '41234567');
        app('auth')->forgetGuards();
        Sanctum::actingAs(Usuario::find($docente));

        $this->json($metodo, $ruta, nuevoAdmin())->assertForbidden();
    })->with([
        ['GET', '/api/v1/admin/usuarios'],
        ['POST', '/api/v1/admin/usuarios'],
        ['GET', '/api/v1/admin/usuarios/1'],
        ['PUT', '/api/v1/admin/usuarios/1'],
        ['PATCH', '/api/v1/admin/usuarios/1/estado'],
        ['POST', '/api/v1/admin/usuarios/1/contrasena-temporal'],
    ]);

    it('sin sesión responde 401', function () {
        app('auth')->forgetGuards();
        $this->app['auth']->guard('sanctum')->forgetUser();
        $this->withHeader('Authorization', 'Bearer inventado')->getJson('/api/v1/admin/usuarios')->assertUnauthorized();
    });

    it('un usuario inexistente da 404 en español', function () {
        $this->getJson('/api/v1/admin/usuarios/99999')->assertNotFound()->assertJsonStructure(['mensaje']);
    });
});

describe('crear cuenta administrativa (RF-12)', function () {
    it('crea la cuenta con contraseña temporal, limpia los datos y la envía por correo', function () {
        $r = $this->postJson('/api/v1/admin/usuarios', nuevoAdmin())->assertCreated();

        $clave = $r->json('contrasena');
        expect($clave)->toMatch('/^[A-Z][a-z]{3}[#$%&*]\d{4}$/')
            ->and($r->json('correo_enviado'))->toBeTrue()
            ->and($r->json('usuario'))->toMatchArray([
                'usuario' => 'promero',
                'nombre' => "Patricia Romero D'Angelo Pérez-Gómez",
                'correo' => 'patricia.romero@ayni.test',
                'celular' => '987654321',
                'rol' => 'ADMIN',
                'debe_cambiar_contrasena' => true,
                'creado_por' => 'Carlos Medina',
            ]);
        $usuario = Usuario::firstWhere('username', 'promero');
        expect(Hash::check($clave, $usuario->password_hash))->toBeTrue()
            ->and($usuario->creado_por)->toBe($this->admin);
        Mail::assertSent(CorreoAyni::class, fn ($m) => $m->hasTo('patricia.romero@ayni.test') && $m->vista === 'contrasena-temporal' && $m->datos['clave'] === $clave);
        expect(DB::table('auditoria')->where(['accion' => 'CREAR', 'entidad' => 'usuario', 'entidad_id' => $usuario->id, 'usuario_id' => $this->admin])->exists())->toBeTrue();
    });

    it('sin correo no envía nada y avisa que hay que entregar la clave', function () {
        $this->postJson('/api/v1/admin/usuarios', nuevoAdmin(['correo' => '']))->assertCreated()->assertJsonPath('correo_enviado', false);
        Mail::assertNothingSent();
    });

    it('la cuenta nueva entra con la temporal y debe cambiarla', function () {
        $clave = $this->postJson('/api/v1/admin/usuarios', nuevoAdmin())->json('contrasena');
        app('auth')->forgetGuards();

        $this->withHeader('X-Ayni-E2E', 'secreto')->postJson('/api/v1/auth/ingresar', ['acceso' => 'admin', 'usuario' => 'promero', 'contrasena' => $clave])
            ->assertOk()->assertJsonPath('usuario.debe_cambiar_contrasena', true);
    });

    it('valida cada campo con mensajes en español', function (array $cambios, string $campo, string $mensaje) {
        $this->postJson('/api/v1/admin/usuarios', nuevoAdmin($cambios))
            ->assertStatus(422)
            ->assertJsonPath("errores.{$campo}.0", $mensaje);
    })->with([
        'nombres con números' => [['nombres' => 'Juan2'], 'nombres', 'Solo se permiten letras, espacios, apóstrofo y guion.'],
        'nombres de una letra' => [['nombres' => 'J'], 'nombres', 'Los nombres deben tener al menos 2 letras.'],
        'apellidos vacíos' => [['apellidos' => '  '], 'apellidos', 'Ingresa los apellidos.'],
        'DNI corto' => [['dni' => '4567891'], 'dni', 'El DNI debe tener 8 dígitos.'],
        'DNI con letra' => [['dni' => '4567891A'], 'dni', 'El DNI debe tener 8 dígitos.'],
        'usuario con número inicial' => [['usuario' => '1admin'], 'usuario', 'El usuario tiene de 4 a 30 caracteres, empieza con letra y solo usa letras, números, punto o guion bajo.'],
        'usuario con espacio' => [['usuario' => 'p romero'], 'usuario', 'El usuario tiene de 4 a 30 caracteres, empieza con letra y solo usa letras, números, punto o guion bajo.'],
        'correo sin extensión' => [['correo' => 'dramirez@mail'], 'correo', 'El correo debe tener el formato usuario@dominio.com.'],
        'correo sin arroba' => [['correo' => 'dramirez.com'], 'correo', 'El correo debe tener el formato usuario@dominio.com.'],
        'correo con extensión de 1 letra' => [['correo' => 'a@b.c'], 'correo', 'El correo debe tener el formato usuario@dominio.com.'],
        'celular sin 9 inicial' => [['celular' => '87654321'], 'celular', 'El celular debe tener 9 dígitos y empezar con 9.'],
        'celular con letra' => [['celular' => '98765432a'], 'celular', 'El celular debe tener 9 dígitos y empezar con 9.'],
        'cargo con etiquetas' => [['cargo' => '<script>'], 'cargo', 'El cargo solo puede tener letras, números, espacios y . , -'],
    ]);

    it('rechaza saltos de línea dentro del correo (GHSA-5vg9-5847-vvmq)', function (string $correo) {
        $this->postJson('/api/v1/admin/usuarios', nuevoAdmin(['correo' => $correo]))->assertStatus(422)->assertJsonPath('errores.correo.0', 'El correo debe tener el formato usuario@dominio.com.');
    })->with(["ana@ayni.test\r\nBcc: x@y.com", "ana\n@ayni.test"]);

    it('un salto de línea al final se limpia y no llega a la BD', function () {
        $this->postJson('/api/v1/admin/usuarios', nuevoAdmin(['correo' => "ana@ayni.test\n"]))->assertCreated();
        expect(DB::table('usuario')->where('username', 'promero')->value('email'))->toBe('ana@ayni.test');
    });

    it('la expresión del correo no acepta un salto de línea final aunque llegue sin limpiar', function () {
        expect(preg_match(\App\Support\Reglas::CORREO, "ana@ayni.test\n"))->toBe(0)
            ->and(preg_match(\App\Support\Acceso::FORMATO_USUARIO['docente'][0], "41234567\n"))->toBe(0);
    });

    it('no repite usuario, DNI ni correo', function () {
        Fabrica::cuenta('DOCENTE', '41234567', correo: 'ocupado@ayni.test');
        DB::table('persona')->where('nombres', 'Ana María')->latest('id')->update(['numero_documento' => '45678912']);

        $r = $this->postJson('/api/v1/admin/usuarios', nuevoAdmin(['usuario' => 'CMEDINA', 'correo' => 'ocupado@ayni.test']))->assertStatus(422);
        expect($r->json('errores.usuario.0'))->toBe('Ese nombre de usuario ya existe.')
            ->and($r->json('errores.dni.0'))->toBe('Ya hay una persona registrada con ese DNI.')
            ->and($r->json('errores.correo.0'))->toBe('Ese correo ya lo usa otra cuenta.');
    });
});

describe('editar (RF-12, RF-16)', function () {
    it('edita una cuenta administrativa y registra antes y después', function () {
        $id = $this->postJson('/api/v1/admin/usuarios', nuevoAdmin())->json('usuario.id');

        $this->putJson("/api/v1/admin/usuarios/{$id}", nuevoAdmin(['cargo' => 'Subdirección', 'usuario' => 'p.romero', 'celular' => '']))
            ->assertOk()
            ->assertJsonPath('usuario.cargo', 'Subdirección')
            ->assertJsonPath('usuario.usuario', 'p.romero')
            ->assertJsonPath('usuario.celular', null)
            ->assertJsonPath('usuario.actualizado_por', 'Carlos Medina');

        $detalle = json_decode(DB::table('auditoria')->where(['accion' => 'ACTUALIZAR', 'entidad_id' => $id])->value('detalle'), true);
        expect($detalle)->toMatchArray([
            'cargo' => ['antes' => 'Secretaría académica', 'despues' => 'Subdirección'],
            'usuario' => ['antes' => 'promero', 'despues' => 'p.romero'],
            'celular' => ['antes' => '987654321', 'despues' => null],
        ]);
    });

    it('de docentes y estudiantes solo cambia el contacto', function () {
        $id = cuentaCon('DOCENTE', '41234567', 'Luis', 'Paredes Aranda');

        $this->putJson("/api/v1/admin/usuarios/{$id}", ['correo' => 'luis@ayni.test', 'celular' => '912345678', 'nombres' => 'Otro', 'usuario' => 'hacker'])
            ->assertOk()
            ->assertJsonPath('usuario.correo', 'luis@ayni.test')
            ->assertJsonPath('usuario.celular', '912345678')
            ->assertJsonPath('usuario.nombre', 'Luis Paredes Aranda')
            ->assertJsonPath('usuario.usuario', '41234567');
        expect(DB::table('persona')->where('nombres', 'Luis')->value('correo'))->toBe('luis@ayni.test');
    });

    it('sin cambios no deja registro en la auditoría', function () {
        $id = cuentaCon('DOCENTE', '41234567', 'Luis', 'Paredes Aranda');
        $this->putJson("/api/v1/admin/usuarios/{$id}", ['correo' => null, 'celular' => null])->assertOk();
        expect(DB::table('auditoria')->where(['accion' => 'ACTUALIZAR', 'entidad_id' => $id])->exists())->toBeFalse();
    });

    it('el detalle muestra quién creó la cuenta y su historial', function () {
        $id = $this->postJson('/api/v1/admin/usuarios', nuevoAdmin())->json('usuario.id');
        $this->patchJson("/api/v1/admin/usuarios/{$id}/estado", ['estado' => 'INACTIVO']);

        $r = $this->getJson("/api/v1/admin/usuarios/{$id}")->assertOk();
        expect($r->json('usuario.creado_por'))->toBe('Carlos Medina')
            ->and(collect($r->json('usuario.historial'))->pluck('accion')->all())->toBe(['INACTIVAR', 'CREAR'])
            ->and($r->json('usuario.historial.0.por'))->toBe('Carlos Medina');
    });
});

describe('activar e inactivar (RF-12)', function () {
    it('al inactivar cierra sus sesiones y ya no puede ingresar', function () {
        $id = Fabrica::cuenta('DOCENTE', '41234567');
        Usuario::find($id)->createToken('web');

        $this->patchJson("/api/v1/admin/usuarios/{$id}/estado", ['estado' => 'INACTIVO'])
            ->assertOk()->assertJsonPath('usuario.estado', 'INACTIVO');
        expect(DB::table('personal_access_tokens')->where('tokenable_id', $id)->count())->toBe(0);

        app('auth')->forgetGuards();
        $this->withHeader('X-Ayni-E2E', 'secreto')->postJson('/api/v1/auth/ingresar', ['acceso' => 'docente', 'usuario' => '41234567', 'contrasena' => 'Ayni#2026'])
            ->assertForbidden();
    });

    it('al activar vuelve a ingresar', function () {
        $id = Fabrica::cuenta('DOCENTE', '41234567', extra: ['estado' => 'INACTIVO']);
        $this->patchJson("/api/v1/admin/usuarios/{$id}/estado", ['estado' => 'ACTIVO'])->assertOk();

        app('auth')->forgetGuards();
        $this->withHeader('X-Ayni-E2E', 'secreto')->postJson('/api/v1/auth/ingresar', ['acceso' => 'docente', 'usuario' => '41234567', 'contrasena' => 'Ayni#2026'])->assertOk();
    });

    it('no permite desactivar la propia cuenta ni dejar el sistema sin administradores', function () {
        $this->patchJson("/api/v1/admin/usuarios/{$this->admin}/estado", ['estado' => 'INACTIVO'])
            ->assertStatus(422)->assertJsonPath('mensaje', 'No puedes cambiar el estado de tu propia cuenta.');

        $otro = Fabrica::cuenta('ADMIN', 'promero');
        DB::table('usuario')->where('id', $this->admin)->update(['estado' => 'INACTIVO']);
        $this->patchJson("/api/v1/admin/usuarios/{$otro}/estado", ['estado' => 'INACTIVO'])
            ->assertStatus(422)->assertJsonPath('mensaje', 'Debe quedar al menos una cuenta administrativa activa.');
    });

    it('una cuenta bloqueada se libera con la contraseña temporal, no con activar', function () {
        $id = Fabrica::cuenta('ESTUDIANTE', 'E-2026-001', 'piki2026', extra: ['estado' => 'BLOQUEADO']);
        $this->patchJson("/api/v1/admin/usuarios/{$id}/estado", ['estado' => 'ACTIVO'])->assertStatus(422);
        $this->patchJson("/api/v1/admin/usuarios/{$id}/estado", ['estado' => 'BLOQUEADO'])->assertStatus(422);
    });
});

describe('desbloquear y contraseña temporal (RF-13)', function () {
    it('desbloquea, envía la temporal por correo, resuelve la alerta y el usuario vuelve a entrar', function () {
        $id = Fabrica::cuenta('ESTUDIANTE', 'E-2026-001', 'piki2026', 'valeria@ayni.test', ['estado' => 'BLOQUEADO', 'intentos_fallidos' => 5]);
        DB::table('alerta_administrativa')->insert(['tipo' => 'USUARIO_BLOQUEADO', 'severidad' => 'CRITICO', 'titulo' => 'x', 'entidad' => 'usuario', 'entidad_id' => $id, 'clave' => "usuario-bloqueado:{$id}"]);
        Usuario::find($id)->createToken('web');

        $r = $this->postJson("/api/v1/admin/usuarios/{$id}/contrasena-temporal")->assertOk()
            ->assertJsonPath('correo_enviado', true)
            ->assertJsonPath('mensaje', 'Contraseña temporal generada y enviada por correo.')
            ->assertJsonPath('usuario.estado', 'ACTIVO');
        $clave = $r->json('contrasena');
        expect($clave)->toMatch('/^[a-z]{4}\d{4}$/');

        $usuario = Usuario::find($id);
        expect($usuario->intentos_fallidos)->toBe(0)
            ->and($usuario->debe_cambiar_password)->toBeTrue()
            ->and($usuario->tokens()->count())->toBe(0)
            ->and(DB::table('alerta_administrativa')->value('estado'))->toBe('RESUELTA')
            ->and(DB::table('auditoria')->where(['accion' => 'DESBLOQUEAR', 'entidad_id' => $id])->exists())->toBeTrue();
        Mail::assertSent(CorreoAyni::class, fn ($m) => $m->hasTo('valeria@ayni.test') && $m->datos['clave'] === $clave);

        app('auth')->forgetGuards();
        $this->withHeader('X-Ayni-E2E', 'secreto')->postJson('/api/v1/auth/ingresar', ['acceso' => 'estudiante', 'usuario' => 'E-2026-001', 'contrasena' => $clave])
            ->assertOk()->assertJsonPath('usuario.debe_cambiar_contrasena', true);
    });

    it('sin correo indica que hay que entregarla', function () {
        $id = Fabrica::cuenta('DOCENTE', '41234567');
        $this->postJson("/api/v1/admin/usuarios/{$id}/contrasena-temporal")->assertOk()
            ->assertJsonPath('correo_enviado', false)
            ->assertJsonPath('mensaje', 'Contraseña temporal generada. Entrégala al usuario.');
        Mail::assertNothingSent();
    });

    it('una cuenta inactiva sigue inactiva', function () {
        $id = Fabrica::cuenta('DOCENTE', '41234567', extra: ['estado' => 'INACTIVO']);
        $this->postJson("/api/v1/admin/usuarios/{$id}/contrasena-temporal")->assertOk()->assertJsonPath('usuario.estado', 'INACTIVO');
    });

    it('para la propia cuenta se usa Mi perfil', function () {
        $this->postJson("/api/v1/admin/usuarios/{$this->admin}/contrasena-temporal")->assertStatus(422);
    });

    it('las contraseñas generadas cumplen la política de cada rol (RN-16)', function () {
        foreach (range(1, 50) as $_) {
            expect(ContrasenaTemporal::generar('ESTUDIANTE'))->toMatch('/^[a-z]{4}[2-9]{4}$/')
                ->and(ContrasenaTemporal::generar('DOCENTE'))->toMatch('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[#$%&*]).{9}$/');
        }
        expect(ContrasenaTemporal::generar('ADMIN'))->not->toBe(ContrasenaTemporal::generar('ADMIN'))
            ->and(ContrasenaTemporal::generar('DOCENTE'))->not->toMatch('/[lIO01]/');
    });
});
