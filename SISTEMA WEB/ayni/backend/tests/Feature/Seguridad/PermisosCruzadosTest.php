<?php

use App\Servicios\Riesgo\Riesgo;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\DatosRiesgo;
use Tests\Soporte\Fabrica;

// M13 - permisos cruzados: un docente intenta ver o cambiar lo de otro docente (mismo colegio, incluso la misma aula)

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Storage::fake('local');
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $this->kevin = $this->e->alumnos['Aranda Ruiz']->matricula;
    // La Práctica 1 es de Matemática (docente 41234567); el "intruso" dicta Comunicación en la misma aula
    $this->practica = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Práctica 1', 'fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-12']);
    DB::table('nota_evaluacion')->insert(['evaluacion_id' => $this->practica, 'matricula_id' => $this->kevin, 'nota' => 15, 'estado_entrega' => 'ENTREGADO', 'registrado_por' => $this->e->profe]);
});

afterEach(fn () => Carbon::setTestNow());

/** Lo que el docente de Matemática puede hacer en su curso; nadie más debe poder */
function accionesDelCurso(object $e, int $practica, int $matricula): array
{
    $notas = ['registros' => [['matricula_id' => $matricula, 'nota' => '0', 'estado_entrega' => 'ENTREGADO']]];
    $evaluacion = ['titulo' => 'Robada', 'tipo_evaluacion_id' => DB::table('tipo_evaluacion')->value('id'), 'subperiodo_id' => $e->b2, 'fecha_asignacion' => '2026-06-10', 'fecha_limite' => '2026-06-12', 'peso' => 1, 'requiere_entrega' => false];

    return [
        ['GET', "/api/v1/docente/asistencia/cursos/{$e->mat}/fechas", []],
        ['GET', "/api/v1/docente/asistencia/cursos/{$e->mat}/2026-06-10", []],
        ['PUT', "/api/v1/docente/asistencia/cursos/{$e->mat}/2026-06-10", ['estado' => 'DEFINITIVO', 'registros' => []]],
        ['GET', "/api/v1/docente/cursos/{$e->mat}/evaluaciones", []],
        ['POST', "/api/v1/docente/cursos/{$e->mat}/evaluaciones", $evaluacion],
        ['PUT', "/api/v1/docente/evaluaciones/{$practica}", $evaluacion],
        ['DELETE', "/api/v1/docente/evaluaciones/{$practica}", []],
        ['GET', "/api/v1/docente/evaluaciones/{$practica}/notas", []],
        ['PUT', "/api/v1/docente/evaluaciones/{$practica}/notas", $notas],
        ['GET', "/api/v1/docente/evaluaciones/{$practica}/notas/excel", []],
        ['GET', "/api/v1/docente/cursos/{$e->mat}/calificaciones", []],
        ['POST', "/api/v1/docente/cursos/{$e->mat}/calificaciones/publicar", []],
        ['GET', "/api/v1/docente/cursos/{$e->mat}/calificaciones/excel", []],
    ];
}

it('el docente de otro curso de la misma aula no ve ni cambia asistencia, evaluaciones, notas ni promedios', function () {
    Fabrica::como($this->e->otroProfe);
    foreach (accionesDelCurso($this->e, $this->practica, $this->kevin) as [$metodo, $url, $datos]) {
        expect($this->json($metodo, $url, $datos)->status())->toBeIn([403, 404], "{$metodo} {$url}");
    }

    // Nada cambió
    expect(DB::table('nota_evaluacion')->where('evaluacion_id', $this->practica)->value('nota'))->toEqual(15)
        ->and(DB::table('evaluacion')->where('id', $this->practica)->value('titulo'))->toBe('Práctica 1')
        ->and(DB::table('evaluacion')->count())->toBe(1)
        ->and(DB::table('sesion_clase')->count())->toBe(0)
        ->and(DB::table('calificacion_periodo')->count())->toBe(0);

    // El dueño sí puede (la lista no está mal armada)
    Fabrica::como($this->e->profe);
    $this->getJson("/api/v1/docente/evaluaciones/{$this->practica}/notas")->assertOk();
});

it('un docente de otra aula tampoco, ni con la ficha, las alertas, las incidencias o los reportes', function () {
    $intruso = Fabrica::cuenta('DOCENTE', '43333333');
    DatosRiesgo::tutor($this->e, $this->e->otroProfe);
    DatosRiesgo::clases($this->e->com, $this->e->b2, 10, [$this->kevin => 4]);
    app(Riesgo::class)->recalcular();
    $alerta = DB::table('alerta_estudiantil')->value('id');
    expect($alerta)->not->toBeNull();

    Fabrica::como($intruso);
    foreach ([
        ...accionesDelCurso($this->e, $this->practica, $this->kevin),
        ['GET', "/api/v1/docente/fichas/{$this->kevin}", []],
        ['GET', "/api/v1/docente/alertas/{$alerta}", []],
        ['POST', "/api/v1/docente/alertas/{$alerta}/estado", ['estado' => 'DESCARTADA', 'justificacion' => 'No corresponde seguirlo.']],
        ['POST', "/api/v1/docente/alertas/{$alerta}/intervenciones", ['tipo' => 'Otro', 'fecha' => '2026-06-10', 'descripcion' => 'Intervención falsa de prueba.']],
        ['POST', '/api/v1/docente/incidencias', ['matricula_id' => $this->kevin, 'tipo_incidencia_id' => DB::table('tipo_incidencia')->value('id'), 'gravedad' => 'LEVE', 'fecha' => '2026-06-10', 'descripcion' => 'Incidencia de alguien sin permiso.']],
        ['GET', "/api/v1/docente/reportes/asistencia?aula_id={$this->e->aula}", []],
    ] as [$metodo, $url, $datos]) {
        expect($this->json($metodo, $url, $datos)->status())->toBeIn([403, 404], "{$metodo} {$url}");
    }

    expect(DB::table('alerta_estudiantil')->where('id', $alerta)->value('estado'))->toBe('NUEVA')
        ->and(DB::table('intervencion')->count())->toBe(0)
        ->and(DB::table('incidencia')->count())->toBe(0);
});

it('el docente no cuela en su planilla de notas a un estudiante de otra aula', function () {
    $otraAula = Fabrica::aula($this->e->periodo, ['seccion_id' => Fabrica::seccion('C')]);
    $ajeno = Fabrica::matricula($otraAula, $this->e->periodo);

    Fabrica::como($this->e->profe);
    $this->putJson("/api/v1/docente/evaluaciones/{$this->practica}/notas", ['registros' => [['matricula_id' => $ajeno, 'nota' => '20', 'estado_entrega' => 'ENTREGADO']]])
        ->assertStatus(422)->assertJsonPath('errores.registros.0', 'Hay estudiantes que no pertenecen a esta evaluación.');
    $this->putJson("/api/v1/docente/asistencia/cursos/{$this->e->mat}/2026-06-10", ['estado' => 'DEFINITIVO', 'registros' => [['matricula_id' => $ajeno, 'estado' => 'F']]])
        ->assertStatus(422);

    expect(DB::table('nota_evaluacion')->where('matricula_id', $ajeno)->exists())->toBeFalse()
        ->and(DB::table('asistencia')->where('matricula_id', $ajeno)->exists())->toBeFalse();
});

it('un estudiante no ve las notas, actividades ni la boleta de otra aula', function () {
    $otraAula = Fabrica::aula($this->e->periodo, ['seccion_id' => Fabrica::seccion('C')]);
    $cursoAjeno = Fabrica::curso($otraAula);
    $actividadAjena = Fabrica::evaluacion($cursoAjeno, $this->e->b2, ['fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-12']);

    Fabrica::como($this->e->alumnos['Aranda Ruiz']->usuario);
    foreach ([
        ['GET', "/api/v1/estudiante/notas/cursos/{$cursoAjeno}"],
        ['GET', "/api/v1/estudiante/actividades/{$actividadAjena}"],
        ['POST', "/api/v1/estudiante/actividades/{$actividadAjena}/entrega"],
        ['GET', "/api/v1/admin/fichas/{$this->e->alumnos['Quispe Mendoza']->matricula}/boleta"],
    ] as [$metodo, $url]) {
        expect($this->json($metodo, $url, ['archivo' => Fabrica::pdf(), 'accion' => 'ENTREGAR'])->status())->toBeIn([403, 404, 422], "{$metodo} {$url}");
    }
    expect(DB::table('entrega')->count())->toBe(0);
});
