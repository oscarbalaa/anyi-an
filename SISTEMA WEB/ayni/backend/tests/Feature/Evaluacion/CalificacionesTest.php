<?php

use App\Servicios\Estructura\CierreBimestres;
use App\Servicios\Evaluacion\Calificaciones;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Fabrica;

// M9 - CU-18, CU-19 y CU-20: RF-42 a RF-48, RN-08 a RN-14

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Storage::fake('local');
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $tipo = fn (string $n) => DB::table('tipo_evaluacion')->where('nombre', $n)->value('id');
    $this->p5 = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Práctica 5', 'tipo_evaluacion_id' => $tipo('Práctica'), 'fecha_asignacion' => '2026-05-25', 'fecha_limite' => '2026-05-29', 'peso' => 1]);
    $this->p6 = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Práctica 6', 'tipo_evaluacion_id' => $tipo('Práctica'), 'fecha_asignacion' => '2026-06-01', 'fecha_limite' => '2026-06-05', 'peso' => 1]);
    $this->proy = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Proyecto', 'tipo_evaluacion_id' => $tipo('Proyecto'), 'fecha_asignacion' => '2026-05-20', 'fecha_limite' => '2026-06-08', 'peso' => 2]);
    $this->m = fn (string $apellidos) => $this->e->alumnos[$apellidos]->matricula;
    Fabrica::como($this->e->profe);
});

afterEach(fn () => Carbon::setTestNow());

function guardarNotas(int $evaluacion, array $registros)
{
    return test()->putJson("/api/v1/docente/evaluaciones/{$evaluacion}/notas", ['registros' => $registros]);
}

function nota(int $matricula, mixed $nota, string $estado = 'ENTREGADO', ?string $observacion = null): array
{
    return ['matricula_id' => $matricula, 'nota' => $nota, 'estado_entrega' => $estado, 'observacion' => $observacion];
}

describe('notas de una evaluación (RF-42, RF-43, RN-08)', function () {
    it('muestra el estado de entrega calculado y guarda notas con punto o coma', function () {
        $p = $this->getJson("/api/v1/docente/evaluaciones/{$this->p6}/notas")->assertOk();
        expect(collect($p->json('estudiantes'))->pluck('estado_entrega')->unique()->all())->toBe(['NO_ENTREGADO'])
            ->and($p->json('evaluacion'))->toMatchArray(['titulo' => 'Práctica 6', 'peso' => 1])
            ->and($p->json('editable'))->toBeTrue();

        $r = guardarNotas($this->p6, [
            nota(($this->m)('Aranda Ruiz'), '15,5'),
            nota(($this->m)('Quispe Mendoza'), 18),
            nota(($this->m)('Ramírez Torres'), '', 'NO_ENTREGADO', '  No   entregó '),
        ])->assertOk()->assertJsonPath('mensaje', 'Notas guardadas.');

        expect(collect($r->json('estudiantes'))->pluck('nota', 'codigo')->all())->toBe(['E-2026-001' => 15.5, 'E-2026-002' => 18, 'E-2026-003' => null])
            ->and($r->json('resumen'))->toBe(['promedio' => 16.8, 'desaprobados' => 0, 'sin_entregar' => 1])
            ->and(DB::table('nota_evaluacion')->where('matricula_id', ($this->m)('Ramírez Torres'))->value('observacion'))->toBe('No entregó')
            ->and(DB::table('auditoria')->where('accion', 'REGISTRAR_NOTAS')->count())->toBe(1);

        guardarNotas($this->p6, [nota(($this->m)('Quispe Mendoza'), '18.00')])->assertOk()->assertJsonPath('mensaje', 'No había cambios por guardar.');
    });

    it('rechaza notas fuera de 0 a 20, negativas, con letras o con más de 2 decimales', function (mixed $valor, string $mensaje) {
        guardarNotas($this->p6, [nota(($this->m)('Quispe Mendoza'), $valor)])->assertStatus(422);
        expect(guardarNotas($this->p6, [nota(($this->m)('Quispe Mendoza'), $valor)])->json('errores')['registros.0.nota'][0])->toBe($mensaje);
        expect(DB::table('nota_evaluacion')->count())->toBe(0);
    })->with([
        '20,5' => ['20,5', 'La nota debe estar entre 0 y 20.'],
        '25' => [25, 'La nota debe estar entre 0 y 20.'],
        'negativa' => ['-1', 'La nota debe ser un número con hasta 2 decimales.'],
        'letras' => ['abc', 'La nota debe ser un número con hasta 2 decimales.'],
        'tres decimales' => ['15.555', 'La nota debe ser un número con hasta 2 decimales.'],
    ]);

    it('el docente corrige el estado de entrega y solo califica a su aula', function () {
        guardarNotas($this->p6, [nota(($this->m)('Aranda Ruiz'), 14, 'TARDIO', 'Entregó 6 jun')])->assertOk();
        expect(DB::table('nota_evaluacion')->value('estado_entrega'))->toBe('TARDIO');

        $ajeno = Fabrica::matricula(Fabrica::aula($this->e->periodo), $this->e->periodo);
        guardarNotas($this->p6, [nota($ajeno, 14)])->assertStatus(422)->assertJsonPath('errores.registros.0', 'Hay estudiantes que no pertenecen a esta evaluación.');
        guardarNotas($this->p6, [nota(($this->m)('Aranda Ruiz'), 14, 'PERDIDO')])->assertStatus(422);

        Fabrica::como($this->e->otroProfe);
        guardarNotas($this->p6, [nota(($this->m)('Aranda Ruiz'), 20)])->assertForbidden();
    });

    it('exporta la planilla a Excel', function () {
        guardarNotas($this->p6, [nota(($this->m)('Quispe Mendoza'), 18)]);
        $this->get("/api/v1/docente/evaluaciones/{$this->p6}/notas/excel")->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('notas_practica_6_3_b.xlsx');
    });
});

describe('promedio ponderado (RF-44, RN-09)', function () {
    it('coincide con el cálculo a mano', function (array $notasYPesos, ?float $esperado) {
        expect(Calificaciones::promedio($notasYPesos))->toBe($esperado);
    })->with([
        'pesos 1, 1 y 2' => [[[11, 1], [16, 1], [14, 2]], 13.8],
        'sin nota cuenta 0' => [[[18, 1], [12, 2], [null, 3]], 7.0],
        '0,05 sube' => [[[11, 1], [0, 1], [10, 2]], 7.8],
        'decimales' => [[[15.5, 1], [14, 1]], 14.8],
        'todo 20' => [[[20, 3], [20, 1]], 20.0],
        'sin evaluaciones' => [[], null],
    ]);

    it('calcula la planilla del bimestre, avisa qué falta y convierte a literal (RN-11)', function () {
        guardarNotas($this->p5, [nota(($this->m)('Aranda Ruiz'), 11), nota(($this->m)('Quispe Mendoza'), 17), nota(($this->m)('Ramírez Torres'), 10)]);
        guardarNotas($this->p6, [nota(($this->m)('Aranda Ruiz'), null, 'NO_ENTREGADO'), nota(($this->m)('Quispe Mendoza'), 18), nota(($this->m)('Ramírez Torres'), 0, 'NO_ENTREGADO')]);
        guardarNotas($this->proy, [nota(($this->m)('Aranda Ruiz'), 10), nota(($this->m)('Quispe Mendoza'), 16), nota(($this->m)('Ramírez Torres'), 13)]);

        $v = $this->getJson("/api/v1/docente/cursos/{$this->e->mat}/calificaciones")->assertOk();
        $filas = collect($v->json('estudiantes'))->keyBy('nombre');
        // Aranda: (11 + 0 + 10×2) / 4 = 7,75 → 7,8 · Quispe: (17 + 18 + 32) / 4 = 16,75 → 16,8 · Ramírez: (10 + 0 + 26) / 4 = 9
        expect($filas['Aranda Ruiz, Alumno'])->toMatchArray(['promedio' => 7.8, 'literal' => 'C'])
            ->and($filas['Quispe Mendoza, Alumno'])->toMatchArray(['promedio' => 16.8, 'literal' => 'A'])
            ->and($filas['Ramírez Torres, Alumno'])->toMatchArray(['promedio' => 9, 'literal' => 'C'])
            ->and($v->json('estado'))->toBe('BORRADOR')
            ->and($v->json('resumen'))->toBe(['estudiantes' => 3, 'promedio' => 11.2, 'desaprobados' => 2])
            ->and($v->json('sin_nota'))->toBe([['id' => $this->p6, 'titulo' => 'Práctica 6', 'fecha_limite' => '2026-06-05', 'estudiantes' => ['Aranda Ruiz, Alumno']]])
            ->and((float) DB::table('calificacion_periodo')->where('matricula_id', ($this->m)('Quispe Mendoza'))->value('promedio'))->toBe(16.8);

        $this->get("/api/v1/docente/cursos/{$this->e->mat}/calificaciones/excel")->assertOk()->assertDownload('calificaciones_matematica_3_b_ii_bimestre.xlsx');
    });
});

describe('publicar y ver mis notas (RF-45, RF-47, RN-13)', function () {
    beforeEach(function () {
        guardarNotas($this->p5, [nota(($this->m)('Quispe Mendoza'), 17)]);
        guardarNotas($this->proy, [nota(($this->m)('Quispe Mendoza'), 16)]);
    });

    it('el estudiante no ve nada en borrador; al publicar ve sus notas, su promedio y recibe un aviso', function () {
        $valeria = $this->e->alumnos['Quispe Mendoza'];
        Fabrica::como($valeria->usuario);
        $antes = $this->getJson('/api/v1/estudiante/notas')->assertOk();
        expect(collect($antes->json('cursos'))->firstWhere('asignatura', 'Matemática'))->toMatchArray(['publicado' => false, 'promedio' => null])
            ->and($this->getJson('/api/v1/estudiante/actividades')->json('actividades.0.nota'))->toBeNull();

        Fabrica::como($this->e->profe);
        $this->postJson("/api/v1/docente/cursos/{$this->e->mat}/calificaciones/publicar", ['bimestre_id' => $this->e->b2])->assertOk()
            ->assertJsonPath('mensaje', 'Promedios publicados. Los estudiantes ya pueden ver sus notas.')
            ->assertJsonPath('estado', 'PUBLICADA');
        expect(DB::table('calificacion_periodo')->where('curso_aula_id', $this->e->mat)->pluck('estado')->unique()->all())->toBe(['PUBLICADA'])
            ->and(DB::table('notificacion')->where('tipo', 'NOTA_PUBLICADA')->count())->toBe(3);

        Fabrica::como($valeria->usuario);
        $r = $this->getJson('/api/v1/estudiante/notas')->assertOk();
        // (17 + 0 + 16×2) / 4 = 12,25 → 12,3
        expect(collect($r->json('cursos'))->firstWhere('asignatura', 'Matemática'))->toMatchArray(['publicado' => true, 'promedio' => 12.3, 'literal' => 'B'])
            ->and(collect($r->json('cursos'))->firstWhere('asignatura', 'Comunicación'))->toMatchArray(['publicado' => false])
            ->and($r->json('promedio'))->toBe(12.3)
            ->and($r->json('bimestre.nombre'))->toBe('II Bimestre')
            ->and(collect($r->json('ultimas'))->pluck('titulo')->all())->toContain('Práctica 5', 'Proyecto');

        $c = $this->getJson("/api/v1/estudiante/notas/cursos/{$this->e->mat}")->assertOk();
        expect($c->json('promedio'))->toBe(12.3)
            ->and(collect($c->json('evaluaciones'))->pluck('nota', 'titulo')->all())->toBe(['Práctica 5' => 17, 'Práctica 6' => null, 'Proyecto' => 16])
            ->and($c->json('evolucion.1'))->toBe(['bimestre' => 'II Bimestre', 'promedio' => 12.3])
            // El docente registró como entregadas la Práctica 5 y el Proyecto
            ->and($c->json('resumen'))->toBe(['calificadas' => 2, 'entregadas' => 2, 'por_entregar' => 3]);

        $ajeno = Fabrica::curso(Fabrica::aula($this->e->periodo));
        $this->getJson("/api/v1/estudiante/notas/cursos/{$ajeno}")->assertNotFound();
    });

    it('un curso sin evaluaciones no se publica y otro docente no publica cursos ajenos', function () {
        Fabrica::como($this->e->otroProfe);
        $this->postJson("/api/v1/docente/cursos/{$this->e->com}/calificaciones/publicar", ['bimestre_id' => $this->e->b2])->assertStatus(422)
            ->assertJsonPath('mensaje', 'El curso aún no tiene evaluaciones publicadas en este bimestre.');
        $this->postJson("/api/v1/docente/cursos/{$this->e->mat}/calificaciones/publicar", ['bimestre_id' => $this->e->b2])->assertForbidden();
    });
});

describe('cierre del bimestre (RF-46, RN-14)', function () {
    it('se cierra solo al pasar la fecha de cierre: lo pendiente cuenta 0 y ya no se modifica', function () {
        guardarNotas($this->p5, [nota(($this->m)('Quispe Mendoza'), 20)]);

        Carbon::setTestNow('2026-08-01 08:00:00');
        $this->getJson('/api/v1/calendario/actual')->assertOk();
        expect(DB::table('subperiodo')->find($this->e->b2)->estado)->toBe('CERRADO');

        $filas = DB::table('calificacion_periodo')->where('curso_aula_id', $this->e->mat)->get()->keyBy('matricula_id');
        expect($filas->pluck('estado')->unique()->all())->toBe(['CERRADA'])
            // 20 / 4 = 5 · los demás, 0
            ->and((float) $filas[($this->m)('Quispe Mendoza')]->promedio)->toBe(5.0)
            ->and((float) $filas[($this->m)('Aranda Ruiz')]->promedio)->toBe(0.0)
            ->and($filas[($this->m)('Aranda Ruiz')]->publicado_en)->not->toBeNull();

        guardarNotas($this->p5, [nota(($this->m)('Quispe Mendoza'), 10)])->assertStatus(422)
            ->assertJsonPath('mensaje', 'El II Bimestre está cerrado: sus evaluaciones y notas ya no se modifican.');
        $this->postJson("/api/v1/docente/cursos/{$this->e->mat}/calificaciones/publicar", ['bimestre_id' => $this->e->b2])->assertStatus(422);
        expect($this->getJson("/api/v1/docente/cursos/{$this->e->mat}/calificaciones?bimestre_id={$this->e->b2}")->json('estado'))->toBe('CERRADA');

        // El estudiante ya ve las notas cerradas
        Fabrica::como($this->e->alumnos['Quispe Mendoza']->usuario);
        expect(collect($this->getJson("/api/v1/estudiante/notas?bimestre_id={$this->e->b2}")->json('cursos'))->firstWhere('asignatura', 'Matemática')['promedio'])->toBe(5);
    });

    it('el administrador lo cierra a mano y queda en la auditoría', function () {
        Fabrica::como($this->e->admin);
        $this->postJson("/api/v1/admin/bimestres/{$this->e->b2}/cerrar")->assertOk();
        expect(DB::table('calificacion_periodo')->where('subperiodo_id', $this->e->b2)->pluck('estado')->unique()->all())->toBe(['CERRADA'])
            ->and(DB::table('auditoria')->where('accion', 'CERRAR_BIMESTRE')->where('usuario_id', $this->e->admin)->exists())->toBeTrue();
        app(CierreBimestres::class)->cerrar($this->e->b2, null);
        expect(DB::table('calificacion_periodo')->where('subperiodo_id', $this->e->b2)->count())->toBe(3);
    });
});

describe('mi horario (RF-48)', function () {
    it('muestra las clases de la semana y la próxima', function () {
        Fabrica::como($this->e->alumnos['Quispe Mendoza']->usuario);
        $r = $this->getJson('/api/v1/estudiante/horario')->assertOk();

        expect($r->json('aula'))->toBe('3.º B')
            ->and($r->json('clases'))->toHaveCount(3)
            ->and($r->json('clases.0'))->toMatchArray(['dia' => 1, 'inicio' => '08:00', 'curso' => 'Comunicación', 'ambiente' => 'Biblioteca'])
            ->and($r->json('clases.2'))->toMatchArray(['dia' => 3, 'curso' => 'Matemática', 'ambiente' => 'Aula 12'])
            ->and($r->json('bloques'))->toBe([['inicio' => '08:00', 'fin' => '09:30'], ['inicio' => '09:45', 'fin' => '11:15']])
            // Miércoles 10:00: la siguiente es el lunes 15 a las 8:00
            ->and($r->json('proxima'))->toMatchArray(['curso' => 'Comunicación', 'fecha' => '2026-06-15', 'inicio' => '08:00']);

        Fabrica::como($this->e->profe);
        $this->getJson('/api/v1/estudiante/horario')->assertForbidden();
    });
});
