<?php

use App\Servicios\Riesgo\Riesgo;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\DatosRiesgo;
use Tests\Soporte\Fabrica;

// M12 - CU-27: RF-60 (ficha 360°) y RF-61 (mis estudiantes)

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $this->kevin = $this->e->alumnos['Aranda Ruiz']->matricula;
    DatosRiesgo::tutor($this->e, $this->e->otroProfe);
    // Comunicación: 10 clases, Kevin faltó a 3 (70 %, riesgo alto)
    DatosRiesgo::clases($this->e->com, $this->e->b2, 10, [$this->kevin => 3]);
    DB::table('calificacion_periodo')->insert([
        ['matricula_id' => $this->kevin, 'curso_aula_id' => $this->e->mat, 'subperiodo_id' => $this->e->b1, 'promedio' => 14, 'estado' => 'CERRADA'],
        ['matricula_id' => $this->kevin, 'curso_aula_id' => $this->e->mat, 'subperiodo_id' => $this->e->b2, 'promedio' => 9, 'estado' => 'BORRADOR'],
        ['matricula_id' => $this->kevin, 'curso_aula_id' => $this->e->com, 'subperiodo_id' => $this->e->b2, 'promedio' => 12, 'estado' => 'PUBLICADA'],
    ]);
    DatosRiesgo::vencidas($this->e->mat, $this->e->b2, 2);
    DB::table('incidencia')->insert(['matricula_id' => $this->kevin, 'tipo_incidencia_id' => DB::table('tipo_incidencia')->where('nombre', 'Conducta')->value('id'), 'registrado_por' => $this->e->otroProfe, 'fecha' => '2026-06-02', 'gravedad' => 'LEVE', 'descripcion' => 'Interrumpe la clase de forma reiterada.']);
    DB::table('apoderado')->insert(['estudiante_id' => DB::table('matricula')->where('id', $this->kevin)->value('estudiante_id'), 'nombres' => 'Rosa Ruiz Vega', 'parentesco' => 'Madre', 'celular' => '987654321', 'es_principal' => true]);
    app(Riesgo::class)->recalcular();
});

afterEach(fn () => Carbon::setTestNow());

describe('ficha 360° (RF-60)', function () {
    it('reúne datos, notas, asistencia, pendientes, incidencias, riesgo, alerta y apoderados', function () {
        Fabrica::como($this->e->admin);
        $r = $this->getJson("/api/v1/admin/fichas/{$this->kevin}")->assertOk();

        expect($r->json('estudiante'))->toMatchArray(['nombre' => 'Alumno Aranda Ruiz', 'aula' => '3.º B', 'tutor' => 'Ana Torres', 'nivel_educativo' => 'Secundaria'])
            // Del bimestre en curso: (9 + 12) / 2 = 10,5; 7 de 10 clases; 2 tareas vencidas
            ->and($r->json('kpis'))->toEqual(['promedio' => 10.5, 'asistencia' => 70, 'pendientes' => 2, 'incidencias' => 1])
            ->and($r->json('riesgo.nivel'))->toBe('ALTO')
            ->and($r->json('alerta'))->toMatchArray(['estado' => 'NUEVA', 'responsable' => 'Ana Torres'])
            ->and(collect($r->json('alerta.historial'))->pluck('accion')->all())->toBe(['GENERADA', 'ASIGNADA_DOCENTE_AULA'])
            ->and($r->json('notas'))->toEqual([
                ['curso' => 'Comunicación', 'promedios' => [$this->e->b1 => null, $this->e->b2 => 12]],
                ['curso' => 'Matemática', 'promedios' => [$this->e->b1 => 14, $this->e->b2 => 9]],
            ])
            ->and($r->json('evolucion'))->toBe([['bimestre' => 'II Bimestre', 'nivel' => 'ALTO', 'probabilidad' => 60]])
            ->and(collect($r->json('pendientes'))->pluck('titulo')->all())->toBe(['Tarea 1', 'Tarea 2'])
            ->and($r->json('incidencias.0'))->toMatchArray(['tipo' => 'Conducta', 'gravedad' => 'LEVE', 'registrado_por' => 'Ana Torres'])
            ->and($r->json('apoderados'))->toBe([['nombre' => 'Rosa Ruiz Vega', 'parentesco' => 'Madre', 'celular' => '987654321', 'correo' => null, 'principal' => true]])
            ->and($r->json('factores.0.titulo'))->toBe('Faltas frecuentes');
    });

    it('la ven la administración y los docentes de su aula; nadie más', function () {
        Fabrica::como($this->e->profe);
        $this->getJson("/api/v1/docente/fichas/{$this->kevin}")->assertOk();
        Fabrica::como(Fabrica::cuenta('DOCENTE', '43333333'));
        $this->getJson("/api/v1/docente/fichas/{$this->kevin}")->assertForbidden();
        Fabrica::como($this->e->alumnos['Aranda Ruiz']->usuario);
        $this->getJson("/api/v1/docente/fichas/{$this->kevin}")->assertForbidden();
        Fabrica::como($this->e->admin);
        $this->getJson('/api/v1/admin/fichas/999999')->assertNotFound();
    });

    it('no hace una consulta por cada incidencia o apoderado (sin N+1)', function () {
        Fabrica::como($this->e->admin);
        $contar = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson("/api/v1/admin/fichas/{$this->kevin}")->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        // La primera petición calienta la sesión; se mide desde la segunda
        $contar();
        $antes = $contar();
        $estudiante = DB::table('matricula')->where('id', $this->kevin)->value('estudiante_id');
        for ($i = 0; $i < 5; $i++) {
            DB::table('incidencia')->insert(['matricula_id' => $this->kevin, 'tipo_incidencia_id' => DB::table('tipo_incidencia')->value('id'), 'registrado_por' => $this->e->profe, 'fecha' => '2026-06-03', 'gravedad' => 'LEVE', 'descripcion' => 'Llegó tarde a la formación.']);
            DB::table('apoderado')->insert(['estudiante_id' => $estudiante, 'nombres' => "Familiar {$i}", 'es_principal' => false]);
        }

        expect($contar())->toBe($antes);
    });
});

describe('mis estudiantes (RF-61)', function () {
    it('lista los estudiantes del curso con su promedio, su asistencia en el curso y su nivel de riesgo', function () {
        Fabrica::como($this->e->otroProfe);
        $r = $this->getJson('/api/v1/docente/estudiantes')->assertOk();

        expect($r->json('curso.nombre'))->toBe('Comunicación · 3.º B')
            ->and($r->json('total'))->toBe(3)
            ->and($r->json('estudiantes.0'))->toEqual(['matricula_id' => $this->kevin, 'nombre' => 'Aranda Ruiz, Alumno', 'codigo' => 'E-2026-001', 'promedio' => 12, 'asistencia' => 70, 'nivel' => 'ALTO'])
            ->and($r->json('estudiantes.1.nivel'))->toBe('BAJO');
    });

    it('solo muestra cursos propios aunque pidan otro', function () {
        Fabrica::como($this->e->profe);
        expect($this->getJson("/api/v1/docente/estudiantes?curso_id={$this->e->com}")->json('curso.nombre'))->toBe('Matemática · 3.º B')
            ->and($this->getJson("/api/v1/docente/estudiantes?curso_id={$this->e->com}")->json('cursos'))->toHaveCount(1);

        Fabrica::como(Fabrica::cuenta('DOCENTE', '43333333'));
        expect($this->getJson('/api/v1/docente/estudiantes')->json())->toMatchArray(['cursos' => [], 'estudiantes' => []]);
    });
});
