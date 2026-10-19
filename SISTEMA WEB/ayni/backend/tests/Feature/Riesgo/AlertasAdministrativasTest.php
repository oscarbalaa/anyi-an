<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\DatosRiesgo;
use Tests\Soporte\Fabrica;

// M11 - RF-56: se generan al abrir las alertas (sin cron) y se resuelven solas cuando la situación desaparece

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    // Lunes 8 de junio: las clases de Matemática (lunes y miércoles) hasta ayer están sin registrar
    Carbon::setTestNow('2026-06-08 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    DatosRiesgo::tutor($this->e, $this->e->otroProfe);
    Fabrica::como($this->e->admin);
});

afterEach(fn () => Carbon::setTestNow());

function abiertas(): array
{
    return collect(test()->getJson('/api/v1/admin/alertas-administrativas')->assertOk()->json('datos'))->where('estado', 'ABIERTA')->pluck('titulo', 'tipo')->all();
}

it('avisa de cuentas bloqueadas, estudiantes sin matrícula y docentes con asistencia sin registrar', function () {
    $bloqueado = Fabrica::cuenta('DOCENTE', '46666666');
    DB::table('usuario')->where('id', $bloqueado)->update(['estado' => 'BLOQUEADO']);
    Fabrica::estudiante();

    $r = $this->getJson('/api/v1/admin/alertas-administrativas')->assertOk();
    $datos = collect($r->json('datos'));
    $porTipo = $datos->keyBy('tipo');

    expect($porTipo['USUARIO_BLOQUEADO'])->toMatchArray(['titulo' => 'Cuenta bloqueada: 46666666', 'severidad' => 'CRITICO', 'categoria' => 'Acceso', 'enlace' => '/admin/usuarios'])
        ->and($porTipo['ESTUDIANTE_SIN_MATRICULA'])->toMatchArray(['titulo' => '1 estudiante sin matrícula en 2026', 'detalle' => 'Nombre Apellido Prueba', 'severidad' => 'PENDIENTE'])
        // Matemática (lunes y miércoles) y Comunicación (lunes) desde el 18 de mayo hasta ayer
        ->and($datos->where('tipo', 'DOCENTE_SIN_ASISTENCIA')->pluck('titulo', 'detalle')->all())->toEqual([
            'Ana Torres · Matemática · 3.º B' => '6 clases sin registrar asistencia',
            'Ana Torres · Comunicación · 3.º B' => '3 clases sin registrar asistencia',
        ])
        ->and($r->json('conteo.abiertas'))->toBe(4)
        ->and($r->json('datos.0.severidad'))->toBe('CRITICO');
});

it('avisa de cursos sin docente activo y de aulas sin tutor', function () {
    DB::table('docente')->where('usuario_id', $this->e->otroProfe)->update(['estado' => 'LICENCIA']);
    $porTipo = collect($this->getJson('/api/v1/admin/alertas-administrativas')->json('datos'))->keyBy('tipo');

    expect($porTipo['CURSO_SIN_DOCENTE'])->toMatchArray(['detalle' => 'Comunicación · 3.º B: su docente está de licencia.', 'enlace' => '/admin/estructura?pestana=carga'])
        // La tutora está de licencia: el aula queda sin tutor activo
        ->and($porTipo['AULA_SIN_TUTOR']['detalle'])->toStartWith('3.º B:');
});

it('no repite la alerta y la marca resuelta cuando ya no ocurre', function () {
    $bloqueado = Fabrica::cuenta('DOCENTE', '46666666');
    DB::table('usuario')->where('id', $bloqueado)->update(['estado' => 'BLOQUEADO']);
    abiertas();
    abiertas();
    expect(DB::table('alerta_administrativa')->where('tipo', 'USUARIO_BLOQUEADO')->count())->toBe(1);

    DB::table('usuario')->where('id', $bloqueado)->update(['estado' => 'ACTIVO']);
    expect(abiertas())->not->toHaveKey('USUARIO_BLOQUEADO')
        ->and(DB::table('alerta_administrativa')->where('tipo', 'USUARIO_BLOQUEADO')->value('estado'))->toBe('RESUELTA');

    $r = $this->getJson('/api/v1/admin/alertas-administrativas?estado=RESUELTA')->assertOk();
    expect($r->json('datos.0.tipo'))->toBe('USUARIO_BLOQUEADO')->and($r->json('datos.0.resuelta'))->not->toBeNull();

    // Si vuelve a bloquearse, se abre una nueva
    DB::table('usuario')->where('id', $bloqueado)->update(['estado' => 'BLOQUEADO']);
    expect(abiertas())->toHaveKey('USUARIO_BLOQUEADO')
        ->and(DB::table('alerta_administrativa')->where('tipo', 'USUARIO_BLOQUEADO')->count())->toBe(2);
});

it('actualiza el conteo de clases sin registrar a medida que pasan los días', function () {
    $matematica = fn () => DB::table('alerta_administrativa')->where('tipo', 'DOCENTE_SIN_ASISTENCIA')->where('detalle', 'like', '%Matemática%')->where('estado', 'ABIERTA')->value('titulo');
    abiertas();
    expect($matematica())->toBe('6 clases sin registrar asistencia');

    Carbon::setTestNow('2026-06-11 10:00:00');
    abiertas();
    expect($matematica())->toBe('8 clases sin registrar asistencia')
        ->and(DB::table('alerta_administrativa')->where('tipo', 'DOCENTE_SIN_ASISTENCIA')->count())->toBe(2);
});

it('a una semana del cierre avisa de los cursos que no publicaron sus promedios', function () {
    DB::table('subperiodo')->where('id', $this->e->b2)->update(['fecha_cierre_notas' => '2026-06-12']);

    expect(abiertas()['NOTAS_PENDIENTES_CIERRE'])->toBe('2 cursos sin publicar promedios del II Bimestre')
        ->and(DB::table('alerta_administrativa')->where('tipo', 'NOTAS_PENDIENTES_CIERRE')->value('detalle'))
        ->toBe('Las notas se cierran el viernes 12 de junio: Comunicación · 3.º B, Matemática · 3.º B');
});

it('filtra por tipo y solo la ve la administración', function () {
    expect(collect($this->getJson('/api/v1/admin/alertas-administrativas?tipo=DOCENTE_SIN_ASISTENCIA')->json('datos'))->pluck('tipo')->unique()->all())->toBe(['DOCENTE_SIN_ASISTENCIA']);
    $this->getJson('/api/v1/admin/alertas-administrativas?tipo=OTRO')->assertStatus(422);

    Fabrica::como($this->e->profe);
    $this->getJson('/api/v1/admin/alertas-administrativas')->assertForbidden();
});
