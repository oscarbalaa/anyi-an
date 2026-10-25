<?php

use App\Servicios\Riesgo\Riesgo;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\DatosRiesgo;
use Tests\Soporte\Fabrica;

// M12 - CU-27: RF-59 (inicio de cada rol)

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    // Miércoles 10 de junio: Matemática tiene clase hoy a las 8:00 (y los lunes); Comunicación, los lunes
    Carbon::setTestNow('2026-06-10 07:00:00');
    $this->e = Fabrica::aulaConCursos();
    $this->kevin = $this->e->alumnos['Aranda Ruiz']->matricula;
    $this->valeria = $this->e->alumnos['Quispe Mendoza'];
});

afterEach(fn () => Carbon::setTestNow());

function consultas(callable $accion): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $accion();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

function masEstudiantes(object $e, int $cantidad): void
{
    for ($i = 0; $i < $cantidad; $i++) {
        $m = Fabrica::matricula($e->aula, $e->periodo);
        DB::table('incidencia')->insert(['matricula_id' => $m, 'tipo_incidencia_id' => DB::table('tipo_incidencia')->value('id'), 'registrado_por' => $e->profe, 'fecha' => '2026-06-01', 'gravedad' => 'LEVE', 'descripcion' => 'Llegó tarde a la formación.']);
    }
}

describe('administración', function () {
    it('los KPIs coinciden con consultas directas a la BD', function () {
        Fabrica::estudiante();
        DatosRiesgo::tutor($this->e, $this->e->otroProfe);
        DatosRiesgo::clases($this->e->com, $this->e->b2, 10, [$this->kevin => 3]);
        app(Riesgo::class)->recalcular();
        Fabrica::como($this->e->admin);

        $r = $this->getJson('/api/v1/admin/inicio')->assertOk();
        expect($r->json('kpis'))->toBe([
            'estudiantes' => (int) DB::table('matricula')->where('estado', 'ACTIVA')->count(),
            'docentes' => (int) DB::table('docente')->where('estado', 'ACTIVO')->count(),
            'coordinadores' => 0,
            'alertas_estudiantiles' => (int) DB::table('alerta_estudiantil')->whereIn('estado', ['NUEVA', 'EN_SEGUIMIENTO'])->count(),
            'alertas_administrativas' => (int) DB::table('alerta_administrativa')->where('estado', 'ABIERTA')->count(),
        ])
            ->and($r->json('kpis.estudiantes'))->toBe(3)
            ->and($r->json('kpis.alertas_estudiantiles'))->toBe(1)
            ->and($r->json('nombre'))->toBe('Ana')
            ->and($r->json('bimestre.nombre'))->toBe('II Bimestre')
            ->and(collect($r->json('atencion'))->pluck('tipo'))->toContain('ESTUDIANTE_SIN_MATRICULA', 'DOCENTE_SIN_ASISTENCIA');
    });

    it('el avance de registro cuenta clases, notas vencidas y cursos publicados', function () {
        DB::table('sesion_clase')->insert(['curso_aula_id' => $this->e->mat, 'subperiodo_id' => $this->e->b2, 'fecha' => '2026-05-18', 'estado' => 'DEFINITIVO']);
        $vencida = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['fecha_asignacion' => '2026-06-01', 'fecha_limite' => '2026-06-05']);
        Fabrica::evaluacion($this->e->mat, $this->e->b2, ['fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-20']);
        DB::table('nota_evaluacion')->insert(['evaluacion_id' => $vencida, 'matricula_id' => $this->kevin, 'nota' => 14]);
        DB::table('calificacion_periodo')->insert(['matricula_id' => $this->kevin, 'curso_aula_id' => $this->e->mat, 'subperiodo_id' => $this->e->b2, 'promedio' => 14, 'estado' => 'PUBLICADA']);
        Fabrica::como($this->e->admin);

        // Matemática: lunes y miércoles; Comunicación: lunes. Del 18 de mayo al 9 de junio hay 11 clases y se registró 1
        expect($this->getJson('/api/v1/admin/inicio')->json('avance'))->toBe([
            'asistencia' => ['hechas' => 1, 'total' => 11, 'porcentaje' => 9],
            'notas' => ['hechas' => 1, 'total' => 3, 'porcentaje' => 33],
            'promedios' => ['hechas' => 1, 'total' => 2, 'porcentaje' => 50],
        ]);
    });
});

describe('docente', function () {
    it('lista lo que tiene pendiente: asistencia sin registrar, notas por poner y seguimientos a su cargo', function () {
        DatosRiesgo::tutor($this->e, $this->e->profe);
        DatosRiesgo::clases($this->e->com, $this->e->b2, 10, [$this->kevin => 3]);
        app(Riesgo::class)->recalcular();
        Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Práctica 6', 'fecha_asignacion' => '2026-06-01', 'fecha_limite' => '2026-06-05']);
        Fabrica::como($this->e->profe);

        $r = $this->getJson('/api/v1/docente/inicio')->assertOk();
        $tareas = collect($r->json('tareas'));
        expect($tareas->where('tipo', 'asistencia')->values()->all())->toBe([
            ['tipo' => 'asistencia', 'titulo' => 'Registrar asistencia', 'detalle' => 'Matemática · 3.º B', 'extra' => '7 clases sin registrar', 'enlace' => "/docente/asistencia?curso={$this->e->mat}&fecha=2026-06-08"],
            ['tipo' => 'asistencia', 'titulo' => 'Registrar asistencia', 'detalle' => 'Matemática · 3.º B', 'extra' => 'Hoy, 08:00', 'enlace' => "/docente/asistencia?curso={$this->e->mat}&fecha=2026-06-10"],
        ])
            ->and($tareas->firstWhere('tipo', 'calificar'))->toMatchArray(['titulo' => 'Calificar Práctica 6', 'extra' => '3 sin nota'])
            ->and($tareas->firstWhere('tipo', 'seguimiento'))->toMatchArray(['titulo' => 'Seguimiento de Alumno Aranda Ruiz', 'detalle' => '3.º B'])
            ->and($r->json('hoy'))->toBe([['curso_id' => $this->e->mat, 'curso' => 'Matemática · 3.º B', 'inicio' => '08:00', 'fin' => '09:30', 'ambiente' => 'Aula 12', 'registrada' => false]])
            ->and($r->json('coordinacion'))->toBeNull();
    });

    it('si coordina, ve el resumen de riesgo de su ámbito', function () {
        DatosRiesgo::tutor($this->e, $this->e->otroProfe);
        DatosRiesgo::clases($this->e->com, $this->e->b2, 10, [$this->kevin => 3]);
        app(Riesgo::class)->recalcular();
        DB::table('coordinacion')->insert(['docente_id' => DB::table('docente')->where('usuario_id', $this->e->profe)->value('id'), 'periodo_id' => $this->e->periodo, 'ambito' => 'AULA', 'aula_id' => $this->e->aula]);
        Fabrica::como($this->e->profe);

        expect($this->getJson('/api/v1/docente/inicio')->json('coordinacion'))->toMatchArray(['ambito' => '3.º B', 'ALTO' => 1, 'MEDIO' => 0, 'BAJO' => 2])
            ->and($this->getJson('/api/v1/docente/inicio')->json('coordinacion.destacado.nombre'))->toBe('Alumno Aranda Ruiz');
    });
});

describe('estudiante', function () {
    it('sabe quién es su tutor y quién coordina su aula (en el inicio y en su perfil)', function () {
        DatosRiesgo::tutor($this->e, $this->e->otroProfe);
        Fabrica::como($this->valeria->usuario);
        expect($this->getJson('/api/v1/estudiante/inicio')->json('coordinador'))->toBeNull();

        // La coordinación más cercana: aquí, la del nivel secundaria
        DB::table('coordinacion')->insert(['docente_id' => DB::table('docente')->where('usuario_id', $this->e->otroProfe)->value('id'), 'periodo_id' => $this->e->periodo, 'ambito' => 'NIVEL', 'nivel' => 'SECUNDARIA']);
        expect($this->getJson('/api/v1/estudiante/inicio')->json())->toMatchArray(['tutor' => 'Ana Torres', 'coordinador' => 'Ana Torres'])
            ->and($this->getJson('/api/v1/perfil')->json('perfil'))->toMatchArray(['tutor' => 'Ana Torres', 'coordinador' => 'Ana Torres']);
    });

    it('ve su promedio publicado, su asistencia, lo que debe entregar y sus clases de hoy', function () {
        DatosRiesgo::tutor($this->e, $this->e->otroProfe);
        DB::table('calificacion_periodo')->insert([
            ['matricula_id' => $this->valeria->matricula, 'curso_aula_id' => $this->e->mat, 'subperiodo_id' => $this->e->b2, 'promedio' => 15.5, 'estado' => 'PUBLICADA'],
            ['matricula_id' => $this->valeria->matricula, 'curso_aula_id' => $this->e->com, 'subperiodo_id' => $this->e->b2, 'promedio' => 9, 'estado' => 'BORRADOR'],
        ]);
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 4, [$this->valeria->matricula => 1]);
        Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Práctica 7', 'fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-12', 'requiere_entrega' => true]);
        Fabrica::como($this->valeria->usuario);

        $r = $this->getJson('/api/v1/estudiante/inicio')->assertOk();
        expect($r->json())->toMatchArray(['aula' => '3.º B', 'tutor' => 'Ana Torres', 'pendientes' => 1])
            // El borrador de Comunicación no se ve (RN-13)
            ->and((float) $r->json('promedio'))->toBe(15.5)
            ->and(collect($r->json('cursos'))->pluck('promedio', 'asignatura')->all())->toEqual(['Comunicación' => null, 'Matemática' => 15.5])
            ->and((float) $r->json('asistencia'))->toBe(75.0)
            ->and($r->json('por_entregar.0'))->toMatchArray(['titulo' => 'Práctica 7', 'curso' => 'Matemática', 'fecha_limite' => '2026-06-12'])
            ->and(collect($r->json('hoy'))->pluck('curso')->all())->toBe(['Matemática']);
    });
});

describe('permisos y consultas', function () {
    it('cada rol ve solo su inicio', function () {
        Fabrica::como($this->valeria->usuario);
        $this->getJson('/api/v1/admin/inicio')->assertForbidden();
        $this->getJson('/api/v1/docente/inicio')->assertForbidden();
        Fabrica::como($this->e->profe);
        $this->getJson('/api/v1/estudiante/inicio')->assertForbidden();
        $this->getJson('/api/v1/admin/inicio')->assertForbidden();
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/admin/inicio')->assertUnauthorized();
    });

    it('no hace una consulta por cada estudiante (sin N+1)', function () {
        DatosRiesgo::tutor($this->e, $this->e->profe);
        Fabrica::como($this->e->admin);
        $this->getJson('/api/v1/admin/inicio');
        $admin = consultas(fn () => $this->getJson('/api/v1/admin/inicio')->assertOk());
        Fabrica::como($this->e->profe);
        $docente = consultas(fn () => $this->getJson('/api/v1/docente/inicio')->assertOk());

        masEstudiantes($this->e, 10);
        Fabrica::como($this->e->admin);
        $this->getJson('/api/v1/admin/inicio');
        expect(consultas(fn () => $this->getJson('/api/v1/admin/inicio')->assertOk()))->toBe($admin);
        Fabrica::como($this->e->profe);
        expect(consultas(fn () => $this->getJson('/api/v1/docente/inicio')->assertOk()))->toBe($docente);
    });
});
