<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\DatosRiesgo;
use Tests\Soporte\Fabrica;
use Tests\Soporte\Leer;

// M12 - CU-28: RF-62 (boleta en PDF), RF-63 (asistencia por aula) y RF-64 (consolidado del bimestre)

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $this->kevin = $this->e->alumnos['Aranda Ruiz'];
    DB::table('institucion')->update(['nombre' => 'IEP El Señor es mi Pastor']);
    DatosRiesgo::tutor($this->e, $this->e->otroProfe);
    DatosRiesgo::clases($this->e->com, $this->e->b2, 10, [$this->kevin->matricula => 3]);
    DB::table('calificacion_periodo')->insert([
        ['matricula_id' => $this->kevin->matricula, 'curso_aula_id' => $this->e->mat, 'subperiodo_id' => $this->e->b1, 'promedio' => 14, 'estado' => 'CERRADA'],
        ['matricula_id' => $this->kevin->matricula, 'curso_aula_id' => $this->e->mat, 'subperiodo_id' => $this->e->b2, 'promedio' => 17.25, 'estado' => 'BORRADOR'],
        ['matricula_id' => $this->kevin->matricula, 'curso_aula_id' => $this->e->com, 'subperiodo_id' => $this->e->b2, 'promedio' => 10, 'estado' => 'PUBLICADA'],
    ]);
});

afterEach(fn () => Carbon::setTestNow());

describe('boleta de notas en PDF (RF-62)', function () {
    it('el estudiante descarga la suya, con el colegio y solo los promedios publicados o cerrados', function () {
        Fabrica::como($this->kevin->usuario);
        $r = $this->get('/api/v1/estudiante/boleta')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $r->getContent();

        expect($r->headers->get('Content-Disposition'))->toContain('boleta_e_2026_001.pdf')
            ->and($pdf)->toStartWith('%PDF-1.4')->toEndWith("%%EOF\n")
            ->and($pdf)->toContain(Leer::textoPdf('IEP EL SEÑOR ES MI PASTOR'))
            ->and($pdf)->toContain(Leer::textoPdf('Aranda Ruiz, Alumno'))
            ->and($pdf)->toContain(Leer::textoPdf('(Matemática)'))
            ->and($pdf)->toContain('(14)')
            ->and($pdf)->toContain('(10)')
            // El borrador del II bimestre no aparece (RN-13)
            ->and($pdf)->not->toContain('(17,3)')
            ->and($pdf)->toContain('(70 %)');
    });

    it('la administración descarga la de cualquier estudiante; el docente no', function () {
        Fabrica::como($this->e->admin);
        $this->get("/api/v1/admin/fichas/{$this->kevin->matricula}/boleta")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/api/v1/admin/fichas/999999/boleta')->assertNotFound();

        Fabrica::como($this->e->profe);
        $this->get("/api/v1/admin/fichas/{$this->kevin->matricula}/boleta")->assertForbidden();
        Fabrica::como($this->e->otroProfe);
        $this->get('/api/v1/estudiante/boleta')->assertForbidden();
    });
});

describe('reportes en Excel', function () {
    it('asistencia por aula (RF-63): clases, faltas y porcentaje de cada estudiante', function () {
        Fabrica::como($this->e->admin);
        $r = $this->get("/api/v1/admin/reportes/asistencia?aula_id={$this->e->aula}&bimestre_id={$this->e->b2}")->assertOk()
            ->assertDownload('asistencia_3_b_ii_bimestre.xlsx');
        $filas = Leer::excel($r);

        expect($filas[0])->toBe(['Código', 'Estudiante', 'Clases', 'Presentes', 'Faltas', 'Tardanzas', 'Justificadas', '% asistencia'])
            ->and($filas)->toHaveCount(4)
            ->and(array_slice($filas[1], 0, 7))->toEqual(['E-2026-001', 'Aranda Ruiz, Alumno', 10, 7, 3, 0, 0])
            ->and((float) $filas[1][7])->toBe(70.0);
    });

    it('consolidado del bimestre (RF-64): promedio de cada curso, el general y la asistencia', function () {
        Fabrica::como($this->e->admin);
        $filas = Leer::excel($this->get("/api/v1/admin/reportes/consolidado?aula_id={$this->e->aula}&bimestre_id={$this->e->b2}")->assertOk());

        expect($filas[0])->toBe(['Código', 'Estudiante', 'Comunicación', 'Matemática', 'Promedio general', '% asistencia'])
            // (10 + 17,25) / 2 = 13,625 → 13,6
            ->and($filas[1])->toEqual(['E-2026-001', 'Aranda Ruiz, Alumno', 10, 17.25, 13.6, 70]);
    });

    it('el docente exporta la asistencia solo de sus aulas; el consolidado es de la administración', function () {
        Fabrica::como($this->e->profe);
        $this->get("/api/v1/docente/reportes/asistencia?aula_id={$this->e->aula}")->assertOk();
        expect($this->getJson('/api/v1/docente/reportes/opciones')->json('aulas'))->toBe([['id' => $this->e->aula, 'nombre' => '3.º B']]);
        $this->get("/api/v1/admin/reportes/consolidado?aula_id={$this->e->aula}")->assertForbidden();

        Fabrica::como(Fabrica::cuenta('DOCENTE', '43333333'));
        $this->get("/api/v1/docente/reportes/asistencia?aula_id={$this->e->aula}")->assertForbidden();
        $this->getJson('/api/v1/docente/reportes/asistencia')->assertStatus(422)->assertJsonPath('errores.aula_id.0', 'Elige el aula.');
    });
});
