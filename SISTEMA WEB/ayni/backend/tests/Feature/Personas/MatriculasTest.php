<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Soporte\Fabrica;

// M7 - CU-12: RF-31, RF-32, RN-04, RN-23

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Mail::fake();
    Carbon::setTestNow('2026-06-10');
    Fabrica::comoAdmin();
    $this->p = Fabrica::periodo(['nombre' => '2026', 'estado' => 'EN_CURSO', 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-12-18']);
    $this->a3a = Fabrica::aula($this->p, ['capacidad' => 2]);
    $this->a3b = Fabrica::aula($this->p, ['seccion_id' => Fabrica::seccion('B')]);
    $this->alumno = fn (string $dni, string $nacimiento = '2012-05-14') => $this->postJson('/api/v1/admin/estudiantes', [
        'tipo_documento' => 'DNI', 'numero_documento' => $dni, 'nombres' => 'Ana', 'apellidos' => 'Prueba Torres',
        'fecha_nacimiento' => $nacimiento, 'sexo' => 'F',
    ])->json('estudiante.id');
});

afterEach(fn () => Carbon::setTestNow());

function matricular(int $estudiante, int $aula, string $fecha = '2026-06-10')
{
    return test()->postJson('/api/v1/admin/matriculas', ['estudiante_id' => $estudiante, 'aula_id' => $aula, 'fecha' => $fecha]);
}

it('matricula, lista por aula y muestra los cupos (RF-31)', function () {
    $e = ($this->alumno)('78000001');
    matricular($e, $this->a3a)->assertCreated()->assertJsonPath('mensaje', 'Matrícula registrada.');

    $r = $this->getJson("/api/v1/admin/matriculas?periodo_id={$this->p}&aula_id={$this->a3a}")->assertOk();
    expect($r->json('datos.0'))->toMatchArray(['aula' => '3.º A', 'fecha' => '2026-06-10', 'estado' => 'ACTIVA'])
        ->and(collect($r->json('aulas'))->firstWhere('id', $this->a3a))->toMatchArray(['capacidad' => 2, 'matriculados' => 1]);
});

it('una sola matrícula por estudiante y periodo (RN-04)', function () {
    $e = ($this->alumno)('78000001');
    matricular($e, $this->a3a)->assertCreated();
    matricular($e, $this->a3b)->assertStatus(422)->assertJsonPath('errores.estudiante_id.0', 'El estudiante ya está matriculado en 3.º A en 2026 (RN-04).');
});

it('no pasa la capacidad del aula (RN-04)', function () {
    matricular(($this->alumno)('78000001'), $this->a3a)->assertCreated();
    matricular(($this->alumno)('78000002'), $this->a3a)->assertCreated();
    matricular(($this->alumno)('78000003'), $this->a3a)->assertStatus(422)->assertJsonPath('errores.aula_id.0', 'El aula 3.º A está llena (2/2).');
});

it('revisa la edad con el nivel y la fecha (RN-23)', function () {
    $nino = ($this->alumno)('78000009', '2019-01-01');
    matricular($nino, $this->a3a)->assertStatus(422)->assertJsonPath('errores.aula_id.0', 'La edad (7 años) no es coherente con secundaria (de 11 a 20 años, RN-23).');
    matricular(($this->alumno)('78000001'), $this->a3a, '2026-07-01')->assertStatus(422)->assertJsonPath('errores.fecha.0', 'La fecha de matrícula no puede ser futura ni posterior al fin del periodo.');
});

it('lista solo a quienes aún no tienen matrícula como candidatos', function () {
    $con = ($this->alumno)('78000001');
    $sin = ($this->alumno)('78000002');
    matricular($con, $this->a3a);

    $ids = collect($this->getJson("/api/v1/admin/matriculas/candidatos?periodo_id={$this->p}")->json('estudiantes'))->pluck('id')->all();
    expect($ids)->toBe([$sin]);
});

it('retira con motivo y luego puede volver a matricularse en otra aula (RF-32)', function () {
    $e = ($this->alumno)('78000001');
    $m = matricular($e, $this->a3a)->json('id');

    $this->postJson("/api/v1/admin/matriculas/{$m}/retirar", ['motivo' => 'corto'])->assertStatus(422)->assertJsonPath('errores.motivo.0', 'Explica el motivo en 10 a 300 caracteres.');
    $this->postJson("/api/v1/admin/matriculas/{$m}/retirar", ['motivo' => 'Se mudó a otra ciudad con su familia.'])->assertOk();
    expect(DB::table('matricula')->find($m))->estado->toBe('RETIRADA')->motivo->toBe('Se mudó a otra ciudad con su familia.');
    $this->postJson("/api/v1/admin/matriculas/{$m}/anular", ['motivo' => 'Registrada por error.'])->assertStatus(422);

    matricular($e, $this->a3b)->assertCreated()->assertJsonPath('id', $m);
    expect(DB::table('matricula')->find($m))->estado->toBe('ACTIVA')->aula_id->toBe($this->a3b)->motivo->toBeNull();
});

it('no anula una matrícula que ya tiene notas o asistencia', function () {
    $e = Fabrica::escenario();
    $ok = matricular(($this->alumno)('78000001'), $this->a3b)->json('id');
    DB::table('incidencia')->insert([
        'matricula_id' => $e->matricula, 'tipo_incidencia_id' => DB::table('tipo_incidencia')->value('id'),
        'fecha' => '2026-06-01', 'gravedad' => 'LEVE', 'descripcion' => 'Llegó tarde varias veces.', 'registrado_por' => DB::table('usuario')->value('id'),
    ]);

    $this->postJson("/api/v1/admin/matriculas/{$e->matricula}/anular", ['motivo' => 'Registrada por error.'])
        ->assertStatus(422)->assertJsonPath('mensaje', 'La matrícula ya tiene asistencia, notas o incidencias: no se anula, se retira.');
    $this->postJson("/api/v1/admin/matriculas/{$ok}/anular", ['motivo' => 'Registrada por error.'])->assertOk();
});
