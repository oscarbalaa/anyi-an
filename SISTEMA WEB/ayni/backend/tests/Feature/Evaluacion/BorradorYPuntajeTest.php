<?php

use App\Servicios\Evaluacion\Calificaciones;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Fabrica;

// Revisión del grupo: evaluaciones en borrador o publicadas (RF-38) y puntaje propio de cada una (RN-08, RN-09)

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Storage::fake('local');
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $this->tipo = fn (string $n) => DB::table('tipo_evaluacion')->where('nombre', $n)->value('id');
    $this->valeria = $this->e->alumnos['Quispe Mendoza'];
    Fabrica::como($this->e->profe);
});

afterEach(fn () => Carbon::setTestNow());

function evaluacionDelGrupo(array $cambios = [])
{
    $e = test()->e;

    return test()->postJson("/api/v1/docente/cursos/{$e->mat}/evaluaciones", $cambios + [
        'titulo' => 'Examen parcial',
        'tipo_evaluacion_id' => (test()->tipo)('Examen'),
        'subperiodo_id' => $e->b2,
        'fecha_asignacion' => '2026-06-01',
        'fecha_limite' => '2026-06-05',
        'peso' => 1,
        'requiere_entrega' => false,
    ]);
}

function notasDe(int $evaluacion, int $matricula, mixed $nota)
{
    return test()->putJson("/api/v1/docente/evaluaciones/{$evaluacion}/notas", ['registros' => [['matricula_id' => $matricula, 'nota' => $nota, 'estado_entrega' => 'ENTREGADO']]]);
}

describe('borrador y publicada', function () {
    it('una evaluación se crea publicada si no se indica; en borrador solo la ve el docente', function () {
        $publicada = evaluacionDelGrupo()->assertCreated()->json('id');
        $borrador = evaluacionDelGrupo(['titulo' => 'Proyecto final', 'estado' => 'BORRADOR', 'requiere_entrega' => true])->assertCreated()->json('id');
        expect(DB::table('evaluacion')->find($publicada)->estado)->toBe('PUBLICADA');

        $r = $this->getJson("/api/v1/docente/cursos/{$this->e->mat}/evaluaciones?bimestre_id={$this->e->b2}")->assertOk();
        expect(collect($r->json('evaluaciones'))->pluck('estado', 'titulo')->all())->toEqual(['Examen parcial' => 'PUBLICADA', 'Proyecto final' => 'BORRADOR'])
            ->and($r->json('resumen.borradores'))->toBe(1);

        Fabrica::como($this->valeria->usuario);
        expect(collect($this->getJson('/api/v1/estudiante/actividades')->json('actividades'))->pluck('titulo')->all())->toBe(['Examen parcial']);
        $this->getJson("/api/v1/estudiante/actividades/{$borrador}")->assertNotFound();
    });

    it('en borrador no se registran notas ni cuenta en el promedio; al publicarla sí', function () {
        $examen = evaluacionDelGrupo()->json('id');
        $borrador = evaluacionDelGrupo(['titulo' => 'Proyecto final', 'estado' => 'BORRADOR'])->json('id');
        notasDe($examen, $this->valeria->matricula, 14)->assertOk();
        notasDe($borrador, $this->valeria->matricula, 18)->assertStatus(422)
            ->assertJsonPath('mensaje', 'La evaluación está en borrador: publícala antes de registrar notas.');
        expect($this->getJson("/api/v1/docente/evaluaciones/{$borrador}/notas")->json('editable'))->toBeFalse()
            ->and(DB::table('calificacion_periodo')->where('matricula_id', $this->valeria->matricula)->value('promedio'))->toBe('14.00');

        $datos = (array) DB::table('evaluacion')->select('titulo', 'tipo_evaluacion_id', 'subperiodo_id', 'fecha_asignacion', 'fecha_limite', 'peso', 'requiere_entrega')->find($borrador);
        $this->putJson("/api/v1/docente/evaluaciones/{$borrador}", ['estado' => 'PUBLICADA'] + $datos)
            ->assertOk();
        notasDe($borrador, $this->valeria->matricula, 18)->assertOk();
        expect(DB::table('calificacion_periodo')->where('matricula_id', $this->valeria->matricula)->value('promedio'))->toBe('16.00');
    });

    it('una evaluación con notas no vuelve a borrador', function () {
        $examen = evaluacionDelGrupo()->json('id');
        notasDe($examen, $this->valeria->matricula, 14)->assertOk();
        evaluacionDelGrupo()->assertCreated();
        $this->putJson("/api/v1/docente/evaluaciones/{$examen}", [
            'titulo' => 'Examen parcial', 'tipo_evaluacion_id' => ($this->tipo)('Examen'), 'subperiodo_id' => $this->e->b2,
            'fecha_asignacion' => '2026-06-01', 'fecha_limite' => '2026-06-05', 'peso' => 1, 'requiere_entrega' => false, 'estado' => 'BORRADOR',
        ])->assertStatus(422)->assertJsonPath('errores.estado.0', 'La evaluación ya tiene entregas o notas: no puede volver a borrador.');
    });

    it('rechaza un estado desconocido', function () {
        evaluacionDelGrupo(['estado' => 'OCULTA'])->assertStatus(422)->assertJsonPath('errores.estado.0', 'Elige si la evaluación queda en borrador o publicada.');
    });
});

describe('puntaje máximo de cada evaluación', function () {
    it('un examen sobre 50 se califica de 0 a 50 y en el promedio cuenta sobre 20', function () {
        $examen = evaluacionDelGrupo(['puntaje_maximo' => 50])->json('id');
        $tarea = evaluacionDelGrupo(['titulo' => 'Tarea 1', 'puntaje_maximo' => 10, 'peso' => 1])->json('id');

        notasDe($examen, $this->valeria->matricula, 51)->assertStatus(422)->assertJsonPath('errores', ['registros.0.nota' => ['La nota debe estar entre 0 y 50.']]);
        notasDe($examen, $this->valeria->matricula, '40,5')->assertOk();
        notasDe($tarea, $this->valeria->matricula, 7)->assertOk();

        // 40,5 de 50 → 16,2; 7 de 10 → 14; con el mismo peso: 15,1
        expect(DB::table('calificacion_periodo')->where('matricula_id', $this->valeria->matricula)->value('promedio'))->toBe('15.10');

        $p = $this->getJson("/api/v1/docente/evaluaciones/{$examen}/notas")->assertOk();
        // La nota aprobatoria del colegio (11 de 20) en un examen sobre 50 es 27,5
        expect($p->json('evaluacion.puntaje_maximo'))->toEqual(50)
            ->and($p->json('aprobatoria_evaluacion'))->toEqual(27.5);

        $c = $this->getJson("/api/v1/docente/cursos/{$this->e->mat}/calificaciones?bimestre_id={$this->e->b2}")->assertOk();
        expect(collect($c->json('evaluaciones'))->pluck('puntaje_maximo', 'titulo')->all())->toEqual(['Examen parcial' => 50, 'Tarea 1' => 10])
            ->and($c->json('escala'))->toBe(20);
    });

    it('sin puntaje se califica sobre 20 y el puntaje va de 1 a 100', function () {
        $id = evaluacionDelGrupo()->json('id');
        expect((float) DB::table('evaluacion')->find($id)->puntaje_maximo)->toBe(20.0);
        evaluacionDelGrupo(['puntaje_maximo' => 0])->assertStatus(422)->assertJsonPath('errores.puntaje_maximo.0', 'El puntaje máximo es un número entero de 1 a 100.');
        evaluacionDelGrupo(['puntaje_maximo' => 101])->assertStatus(422);
    });

    it('con notas registradas el puntaje máximo ya no cambia', function () {
        $examen = evaluacionDelGrupo(['puntaje_maximo' => 50])->json('id');
        notasDe($examen, $this->valeria->matricula, 45)->assertOk();
        $this->putJson("/api/v1/docente/evaluaciones/{$examen}", [
            'titulo' => 'Examen parcial', 'tipo_evaluacion_id' => ($this->tipo)('Examen'), 'subperiodo_id' => $this->e->b2,
            'fecha_asignacion' => '2026-06-01', 'fecha_limite' => '2026-06-05', 'peso' => 1, 'requiere_entrega' => false, 'puntaje_maximo' => 20,
        ])->assertStatus(422)->assertJsonPath('errores.puntaje_maximo.0', 'Ya hay notas registradas sobre 50 puntos: el puntaje máximo no se puede cambiar.');
    });

    it('lleva cualquier puntaje a la escala del curso', function () {
        expect(Calificaciones::aEscala(40, 50))->toBe(16.0)
            ->and(Calificaciones::aEscala(7, 10))->toBe(14.0)
            ->and(Calificaciones::aEscala(13.5, 20))->toBe(13.5)
            ->and(Calificaciones::aEscala(null, 50))->toBeNull();
    });
});
