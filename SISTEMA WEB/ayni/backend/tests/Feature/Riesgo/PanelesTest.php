<?php

use App\Servicios\Riesgo\Riesgo;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\DatosRiesgo;
use Tests\Soporte\Fabrica;

// M11 - CU-26: RF-57, RF-58

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    DatosRiesgo::tutor($this->e, $this->e->otroProfe);
    // 3.º B: Kevin 70 % (alto), Diego 85 % (medio) y Valeria 100 % (bajo). 2.º A: dos estudiantes sin faltas
    DatosRiesgo::clases($this->e->mat, $this->e->b2, 20, [$this->e->alumnos['Aranda Ruiz']->matricula => 6, $this->e->alumnos['Ramírez Torres']->matricula => 3]);
    $this->segundo = Fabrica::aula($this->e->periodo, ['grado_id' => Fabrica::grado('SECUNDARIA', 2), 'seccion_id' => Fabrica::seccion('A')]);
    Fabrica::matricula($this->segundo, $this->e->periodo);
    Fabrica::matricula($this->segundo, $this->e->periodo);
    DatosRiesgo::clases(Fabrica::curso($this->segundo), $this->e->b2, 5);
    app(Riesgo::class)->recalcular();
    Fabrica::como($this->e->admin);
});

afterEach(fn () => Carbon::setTestNow());

describe('panel de riesgo del colegio (RF-57)', function () {
    it('cuenta los niveles, las alertas sin atender, la distribución por aula y los factores más frecuentes', function () {
        $r = $this->getJson('/api/v1/admin/riesgo')->assertOk();

        expect($r->json('kpis'))->toMatchArray(['ALTO' => 1, 'MEDIO' => 1, 'BAJO' => 3, 'evaluados' => 5, 'estudiantes' => 5, 'sin_atender' => 2, 'abiertas' => 2])
            ->and($r->json('ambito'))->toBe('Toda la institución')
            ->and($r->json('modelo'))->toBe('reglas v0')
            ->and($r->json('bimestre.nombre'))->toBe('II Bimestre')
            ->and($r->json('anterior'))->toBeNull()
            ->and($r->json('por_aula'))->toBe([
                ['id' => $this->e->aula, 'nombre' => '3.º B', 'nivel' => 'SECUNDARIA', 'ALTO' => 1, 'MEDIO' => 1, 'BAJO' => 1],
                ['id' => $this->segundo, 'nombre' => '2.º A', 'nivel' => 'SECUNDARIA', 'ALTO' => 0, 'MEDIO' => 0, 'BAJO' => 2],
            ])
            ->and($r->json('factores.0'))->toMatchArray(['variable' => 'asistencia', 'titulo' => 'Faltas frecuentes', 'estudiantes' => 2, 'porcentaje' => 100]);

        $atencion = $r->json('atencion');
        expect(array_column($atencion, 'nombre'))->toBe(['Alumno Aranda Ruiz', 'Alumno Ramírez Torres'])
            ->and($atencion[0])->toMatchArray(['aula' => '3.º B', 'nivel' => 'ALTO', 'probabilidad' => 60])
            ->and($atencion[0]['alerta_id'])->toBe(DB::table('alerta_estudiantil')->where('matricula_id', $this->e->alumnos['Aranda Ruiz']->matricula)->value('id'))
            ->and($atencion[1]['factor_principal'])->toBe('Asistencia de 85 % (menos de 90 %): 3 faltas en 20 clases.');
    });

    it('filtra por nivel y por aula', function () {
        expect($this->getJson('/api/v1/admin/riesgo?nivel=PRIMARIA')->json('kpis.evaluados'))->toBe(0)
            ->and($this->getJson("/api/v1/admin/riesgo?aula_id={$this->segundo}")->json('kpis'))->toMatchArray(['BAJO' => 2, 'evaluados' => 2, 'sin_atender' => 0]);
        $this->getJson('/api/v1/admin/riesgo?nivel=INICIAL')->assertStatus(422);
    });

    it('recalcula con el botón y lo cuenta', function () {
        $this->postJson('/api/v1/admin/riesgo/recalcular')->assertOk()
            ->assertJsonPath('mensaje', 'Riesgo recalculado para 5 estudiantes: 1 en riesgo alto y 1 en riesgo medio (0 alertas nuevas).');
    });
});

describe('panel de coordinación (RF-58)', function () {
    it('el coordinador solo ve su ámbito', function () {
        $coordinador = Fabrica::cuenta('DOCENTE', '47777777');
        DB::table('coordinacion')->insert(['docente_id' => DB::table('docente')->where('usuario_id', $coordinador)->value('id'), 'periodo_id' => $this->e->periodo, 'ambito' => 'AULA', 'aula_id' => $this->segundo]);
        Fabrica::como($coordinador);

        $r = $this->getJson('/api/v1/docente/coordinacion')->assertOk();
        expect($r->json('ambito'))->toBe('2.º A')
            ->and($r->json('es_coordinador'))->toBeTrue()
            ->and($r->json('kpis'))->toMatchArray(['ALTO' => 0, 'MEDIO' => 0, 'BAJO' => 2, 'estudiantes' => 2])
            ->and(array_column($r->json('por_aula'), 'nombre'))->toBe(['2.º A'])
            ->and($r->json('aulas'))->toHaveCount(1);
        // Pedir otra aula no amplía el ámbito
        expect($this->getJson("/api/v1/docente/coordinacion?aula_id={$this->e->aula}")->json('kpis.evaluados'))->toBe(0);

        $this->postJson('/api/v1/docente/coordinacion/recalcular')->assertOk()
            ->assertJsonPath('mensaje', 'Riesgo recalculado para 2 estudiantes: 0 en riesgo alto y 0 en riesgo medio (0 alertas nuevas).');
    });

    it('un docente que no coordina no tiene panel ni puede recalcular; el estudiante no entra', function () {
        Fabrica::como($this->e->profe);
        $this->getJson('/api/v1/docente/coordinacion')->assertOk()->assertJsonPath('bimestre', null)->assertJsonPath('es_coordinador', false);
        $this->postJson('/api/v1/docente/coordinacion/recalcular')->assertForbidden();

        Fabrica::como($this->e->alumnos['Quispe Mendoza']->usuario);
        $this->getJson('/api/v1/docente/coordinacion')->assertForbidden();
        $this->getJson('/api/v1/admin/riesgo')->assertForbidden();
    });
});
