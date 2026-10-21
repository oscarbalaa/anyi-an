<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Fabrica;
use Tests\Soporte\Leer;

// M13 - recorrido de un bimestre entero con los tres roles, por la API, hasta el cierre automático (RN-14).
// El reloj avanza día a día (Carbon::setTestNow): así se prueba lo que en el navegador tomaría diez semanas.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
});

afterEach(fn () => Carbon::setTestNow());

/** Pone el reloj en esa fecha y entra con esa cuenta */
function el(string $fecha, int $usuario): void
{
    Carbon::setTestNow($fecha);
    Fabrica::como($usuario);
}

it('un II Bimestre completo: asistencia, evaluaciones, entregas, notas, alerta, publicación y cierre automático', function () {
    // Aula 3.º B: Matemática (lunes y miércoles, docente 41234567) y Comunicación (lunes, docente 42345678, tutora)
    $e = Fabrica::aulaConCursos();
    DB::table('aula')->where('id', $e->aula)->update(['tutor_docente_id' => DB::table('docente')->where('usuario_id', $e->otroProfe)->value('id')]);
    $kevin = $e->alumnos['Aranda Ruiz'];
    $valeria = $e->alumnos['Quispe Mendoza'];
    $diego = $e->alumnos['Ramírez Torres'];
    $practica = DB::table('tipo_evaluacion')->where('nombre', 'Práctica')->value('id');
    $examen = DB::table('tipo_evaluacion')->where('nombre', 'Examen')->value('id');

    // 1. Diez semanas de clases: cada docente registra la asistencia el mismo día. Kevin falta a 6 de las 10 de Comunicación
    $faltas = 0;
    for ($lunes = Carbon::parse('2026-05-18'); $lunes->lte('2026-07-20'); $lunes->addWeek()) {
        $dia = $lunes->toDateString();
        $miercoles = $lunes->copy()->addDays(2)->toDateString();
        $falta = $faltas < 6 && $lunes->weekOfYear % 3 !== 0;
        $faltas += $falta ? 1 : 0;

        el("{$dia} 12:00", $e->otroProfe);
        $this->putJson("/api/v1/docente/asistencia/cursos/{$e->com}/{$dia}", ['estado' => 'DEFINITIVO', 'registros' => $falta ? [['matricula_id' => $kevin->matricula, 'estado' => 'F']] : []])->assertOk();
        el("{$dia} 12:00", $e->profe);
        $this->putJson("/api/v1/docente/asistencia/cursos/{$e->mat}/{$dia}", ['estado' => 'DEFINITIVO', 'registros' => []])->assertOk();
        el("{$miercoles} 12:00", $e->profe);
        $this->putJson("/api/v1/docente/asistencia/cursos/{$e->mat}/{$miercoles}", ['estado' => 'DEFINITIVO', 'registros' => []])->assertOk();
    }
    expect($faltas)->toBe(6)->and(DB::table('sesion_clase')->where('estado', 'DEFINITIVO')->count())->toBe(30);

    // 2. El docente de Matemática crea una práctica con entrega y un examen en clase
    el('2026-05-25 09:00', $e->profe);
    $p1 = $this->postJson("/api/v1/docente/cursos/{$e->mat}/evaluaciones", ['titulo' => 'Práctica 1', 'tipo_evaluacion_id' => $practica, 'subperiodo_id' => $e->b2, 'fecha_asignacion' => '2026-05-25', 'fecha_limite' => '2026-05-29', 'peso' => 1, 'indicaciones' => 'Resuelve la guía.', 'requiere_entrega' => true])
        ->assertCreated()->json('id');
    $ex = $this->postJson("/api/v1/docente/cursos/{$e->mat}/evaluaciones", ['titulo' => 'Examen bimestral', 'tipo_evaluacion_id' => $examen, 'subperiodo_id' => $e->b2, 'fecha_asignacion' => '2026-05-25', 'fecha_limite' => '2026-07-15', 'peso' => 2, 'indicaciones' => '', 'requiere_entrega' => false])
        ->assertCreated()->json('id');
    el('2026-06-15 09:00', $e->otroProfe);
    $expo = $this->postJson("/api/v1/docente/cursos/{$e->com}/evaluaciones", ['titulo' => 'Exposición', 'tipo_evaluacion_id' => $practica, 'subperiodo_id' => $e->b2, 'fecha_asignacion' => '2026-06-15', 'fecha_limite' => '2026-06-15', 'peso' => 1, 'indicaciones' => '', 'requiere_entrega' => false])
        ->assertCreated()->json('id');

    // 3. Valeria y Diego entregan su PDF a tiempo; Kevin no
    foreach ([$valeria, $diego] as $alumno) {
        el('2026-05-27 18:00', $alumno->usuario);
        $this->post("/api/v1/estudiante/actividades/{$p1}/entrega", ['archivo' => Fabrica::pdf(), 'accion' => 'ENTREGAR'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('estado', 'ENTREGADO');
    }

    // 4. Los docentes registran las notas cuando vence cada evaluación
    $notas = fn (array $porAlumno) => ['registros' => collect($porAlumno)->map(fn ($n, $m) => ['matricula_id' => $m, 'nota' => $n, 'estado_entrega' => $n === null ? 'NO_ENTREGADO' : 'ENTREGADO'])->values()->all()];
    el('2026-06-01 15:00', $e->profe);
    $this->putJson("/api/v1/docente/evaluaciones/{$p1}/notas", $notas([$valeria->matricula => '17', $diego->matricula => '14', $kevin->matricula => null]))->assertOk();
    el('2026-06-16 15:00', $e->otroProfe);
    $this->putJson("/api/v1/docente/evaluaciones/{$expo}/notas", $notas([$valeria->matricula => '16', $diego->matricula => '15', $kevin->matricula => '10']))->assertOk();
    el('2026-07-16 15:00', $e->profe);
    $this->putJson("/api/v1/docente/evaluaciones/{$ex}/notas", $notas([$valeria->matricula => '18', $diego->matricula => '13', $kevin->matricula => '9']))->assertOk();

    // 5. Con faltas y notas bajas, el sistema ya creó la alerta de Kevin para su tutora, con aviso en la campana (RN-19 a RN-21)
    el('2026-07-16 16:00', $e->otroProfe);
    $alertas = $this->getJson('/api/v1/docente/alertas')->assertOk()->json('datos');
    $alerta = collect($alertas)->firstWhere('estudiante.matricula_id', $kevin->matricula);
    expect($alerta)->not->toBeNull()
        ->and($alerta['nivel'])->toBe('ALTO')
        ->and(DB::table('notificacion')->where('usuario_id', $e->otroProfe)->where('tipo', 'like', 'ALERTA%')->exists())->toBeTrue();

    // 6. La tutora la atiende: seguimiento, una intervención y cierre con justificación
    $this->postJson("/api/v1/docente/alertas/{$alerta['id']}/estado", ['estado' => 'EN_SEGUIMIENTO'])->assertOk();
    $this->postJson("/api/v1/docente/alertas/{$alerta['id']}/intervenciones", ['tipo' => 'Reunión con el apoderado', 'fecha' => '2026-07-16', 'descripcion' => 'Reunión con la madre sobre las faltas de los lunes.', 'resultado' => 'Se compromete a acompañarlo.'])->assertCreated();
    $this->postJson("/api/v1/docente/alertas/{$alerta['id']}/estado", ['estado' => 'CERRADA', 'justificacion' => 'La familia se comprometió y ya no falta.'])->assertOk();
    expect(DB::table('alerta_estudiantil')->where('id', $alerta['id'])->value('estado'))->toBe('CERRADA');

    // 7. Último día de clases: cada docente publica sus promedios y los estudiantes reciben el aviso (RN-13)
    el('2026-07-24 17:00', $e->profe);
    $this->postJson("/api/v1/docente/cursos/{$e->mat}/calificaciones/publicar", ['bimestre_id' => $e->b2])->assertOk();
    el('2026-07-24 17:00', $e->otroProfe);
    $this->postJson("/api/v1/docente/cursos/{$e->com}/calificaciones/publicar", ['bimestre_id' => $e->b2])->assertOk();

    el('2026-07-24 19:00', $valeria->usuario);
    $mias = $this->getJson('/api/v1/estudiante/notas')->assertOk();
    // Matemática: (17 + 18×2) / 3 = 17,67 → 17,7; Comunicación: 16
    expect(collect($mias->json('cursos'))->firstWhere('asignatura', 'Matemática'))->toMatchArray(['publicado' => true, 'promedio' => 17.7])
        ->and(collect($mias->json('cursos'))->firstWhere('asignatura', 'Comunicación'))->toMatchArray(['publicado' => true, 'promedio' => 16])
        ->and(DB::table('notificacion')->where('usuario_id', $valeria->usuario)->where('tipo', 'NOTA_PUBLICADA')->count())->toBe(2);

    // 8. Pasa la fecha de cierre (31/07): la primera petición del día cierra el bimestre solo, sin cron (RN-14)
    Carbon::setTestNow('2026-08-03 08:00');
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/calendario/actual')->assertOk();
    expect(DB::table('subperiodo')->where('id', $e->b2)->value('estado'))->toBe('CERRADO')
        ->and(DB::table('subperiodo')->where('id', $e->b2)->value('cerrado_por'))->toBeNull()
        ->and(DB::table('calificacion_periodo')->where('subperiodo_id', $e->b2)->pluck('estado')->unique()->values()->all())->toBe(['CERRADA']);

    // Ya nadie cambia notas ni asistencia del bimestre cerrado
    Fabrica::como($e->profe);
    $this->putJson("/api/v1/docente/evaluaciones/{$ex}/notas", $notas([$kevin->matricula => '20']))->assertStatus(422);
    $this->putJson("/api/v1/docente/asistencia/cursos/{$e->mat}/2026-07-22", ['estado' => 'DEFINITIVO', 'registros' => [['matricula_id' => $kevin->matricula, 'estado' => 'F']]])->assertStatus(422);
    expect(DB::table('nota_evaluacion')->where('evaluacion_id', $ex)->where('matricula_id', $kevin->matricula)->value('nota'))->toEqual(9);

    // 9. Valeria descarga su boleta con el bimestre cerrado, y la administración el consolidado
    Fabrica::como($valeria->usuario);
    $boleta = $this->get('/api/v1/estudiante/boleta')->assertOk()->getContent();
    expect($boleta)->toContain('(17,7)')->toContain('(16)')->toContain('(100 %)');

    Fabrica::como($e->admin);
    $filas = Leer::excel($this->get("/api/v1/admin/reportes/consolidado?aula_id={$e->aula}&bimestre_id={$e->b2}")->assertOk());
    $fila = collect($filas)->firstWhere(0, 'E-2026-002');
    expect($fila[1])->toBe('Quispe Mendoza, Alumno')
        ->and((float) $fila[2])->toEqual(16.0)
        ->and((float) $fila[3])->toEqual(17.7)
        ->and((float) $fila[5])->toEqual(100.0);
});
