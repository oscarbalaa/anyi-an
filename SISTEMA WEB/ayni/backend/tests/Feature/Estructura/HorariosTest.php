<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Fabrica;

// M6 - CU-08: RF-23, RF-25, RN-05

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Fabrica::comoAdmin();
    $this->p = Fabrica::periodo(['nombre' => '2026', 'estado' => 'EN_CURSO']);
    $this->a3a = Fabrica::aula($this->p, ['grado_id' => Fabrica::grado('SECUNDARIA', 3), 'seccion_id' => Fabrica::seccion('A'), 'ambiente' => 'Aula 12']);
    $this->a3b = Fabrica::aula($this->p, ['grado_id' => Fabrica::grado('SECUNDARIA', 3), 'seccion_id' => Fabrica::seccion('B')]);
    $this->luis = Fabrica::docenteLlamado('Luis', 'Paredes Aranda');
    $this->carmen = Fabrica::docenteLlamado('Carmen', 'Rivas Mendoza');
    $this->mat = DB::table('asignatura')->insertGetId(['codigo' => 'MAT', 'nombre' => 'Matemática', 'horas_semanales' => 6]);
    $this->com = DB::table('asignatura')->insertGetId(['codigo' => 'COM', 'nombre' => 'Comunicación', 'horas_semanales' => 5]);
});

function asignar(int $aula, int $asignatura, int $docente)
{
    return test()->postJson('/api/v1/admin/cursos', ['aula_id' => $aula, 'asignatura_id' => $asignatura, 'docente_id' => $docente]);
}

function bloque(int $curso, int $dia, string $inicio, string $fin, array $extra = [])
{
    return test()->postJson('/api/v1/admin/horarios', ['curso_id' => $curso, 'dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin] + $extra);
}

describe('carga académica (RF-23)', function () {
    it('asigna cursos con su docente y muestra la carga del aula', function () {
        $curso = asignar($this->a3a, $this->mat, $this->luis)->assertCreated()->json('id');
        bloque($curso, 1, '08:00', '09:30')->assertCreated();
        bloque($curso, 3, '08:00', '09:30')->assertCreated();

        $carga = $this->getJson("/api/v1/admin/aulas/{$this->a3a}/carga")->assertOk();
        expect($carga->json('aula.nombre'))->toBe('3.º A')
            ->and($carga->json('cursos.0'))->toMatchArray([
                'asignatura' => ['id' => $this->mat, 'codigo' => 'MAT', 'nombre' => 'Matemática', 'horas_semanales' => 6],
                'docente' => ['id' => $this->luis, 'nombre' => 'Luis Paredes'],
                'horas_programadas' => 3.0,
            ])
            ->and($carga->json('horario.0'))->toMatchArray(['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '09:30', 'ambiente' => 'Aula 12']);
    });

    it('no repite la asignatura en el aula ni usa una inactiva', function () {
        asignar($this->a3a, $this->mat, $this->luis)->assertCreated();
        asignar($this->a3a, $this->mat, $this->carmen)->assertStatus(422)->assertJsonPath('errores.asignatura_id.0', 'Esa asignatura ya está en el aula.');

        DB::table('asignatura')->where('id', $this->com)->update(['estado' => 'INACTIVO']);
        asignar($this->a3a, $this->com, $this->luis)->assertStatus(422)->assertJsonPath('errores.asignatura_id.0', 'La asignatura está inactiva.');
    });

    it('no se quita un curso que ya tiene evaluaciones', function () {
        $curso = asignar($this->a3a, $this->mat, $this->luis)->json('id');
        Fabrica::evaluacion($curso, Fabrica::bimestre($this->p));

        $this->deleteJson("/api/v1/admin/cursos/{$curso}")->assertStatus(422);
        $libre = asignar($this->a3a, $this->com, $this->luis)->json('id');
        bloque($libre, 2, '10:00', '11:00');
        $this->deleteJson("/api/v1/admin/cursos/{$libre}")->assertOk();
        expect(DB::table('horario')->where('curso_aula_id', $libre)->exists())->toBeFalse();
    });
});

describe('horario sin cruces (RF-25, RN-05)', function () {
    beforeEach(function () {
        $this->matA = asignar($this->a3a, $this->mat, $this->luis)->json('id');
        $this->comA = asignar($this->a3a, $this->com, $this->carmen)->json('id');
        $this->matB = asignar($this->a3b, $this->mat, $this->luis)->json('id');
        bloque($this->matA, 1, '08:00', '09:30')->assertCreated();
    });

    it('el aula no puede tener dos clases a la vez', function () {
        bloque($this->comA, 1, '09:00', '10:00')
            ->assertStatus(422)->assertJsonPath('errores.hora_inicio.0', 'Cruce de horario: 3.º A ya tiene Matemática el lunes de 08:00 a 09:30.');
    });

    it('el docente no puede dictar en dos aulas a la vez', function () {
        bloque($this->matB, 1, '08:30', '09:00')
            ->assertStatus(422)->assertJsonPath('errores.hora_inicio.0', 'Cruce de horario: Luis Paredes ya dicta Matemática en 3.º A el lunes de 08:00 a 09:30.');
    });

    it('acepta clases seguidas, sin cruce, y el mismo horario otro día', function () {
        // Luis termina en 3.º A a las 09:30 y puede empezar en 3.º B a esa hora
        bloque($this->comA, 1, '09:30', '11:00')->assertCreated();
        bloque($this->matB, 1, '09:30', '11:00')->assertCreated();
        bloque($this->matB, 2, '08:00', '09:30')->assertCreated();
    });

    it('al editar un bloque no choca consigo mismo', function () {
        $id = DB::table('horario')->value('id');
        $this->putJson("/api/v1/admin/horarios/{$id}", ['curso_id' => $this->matA, 'dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00'])->assertOk();
    });

    it('cambiar el docente revisa sus cruces', function () {
        bloque($this->matB, 1, '10:00', '11:00')->assertCreated();
        // Carmen en 3.º B a las 10:00 está libre; Luis en 3.º A a las 08:00 sí choca si toma Comunicación del lunes 08:00 de 3.º A
        bloque($this->comA, 2, '08:00', '09:00')->assertCreated();
        $this->putJson("/api/v1/admin/cursos/{$this->matB}", ['docente_id' => $this->carmen])->assertOk();
        bloque($this->matA, 2, '08:00', '09:00')->assertStatus(422);
        $this->putJson("/api/v1/admin/cursos/{$this->comA}", ['docente_id' => $this->luis])->assertOk();
    });

    it('valida horas y días', function (array $datos, string $campo, string $mensaje) {
        bloque($this->comA, ...$datos)->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
    })->with([
        'antes de las 6' => [[2, '05:30', '07:00'], 'hora_inicio', 'La hora debe estar entre 06:00 y 20:00.'],
        'después de las 20' => [[2, '19:00', '20:30'], 'hora_fin', 'La hora debe estar entre 06:00 y 20:00.'],
        'fin antes del inicio' => [[2, '09:30', '08:00'], 'hora_fin', 'La hora de fin debe ser posterior a la de inicio.'],
        'menos de 30 minutos' => [[2, '08:00', '08:20'], 'hora_fin', 'La clase debe durar al menos 30 minutos y terminar después de empezar.'],
        'formato' => [[2, '8', '9'], 'hora_inicio', 'La hora tiene el formato HH:MM.'],
        'domingo' => [[7, '08:00', '09:00'], 'dia_semana', 'Elige un día de lunes a sábado.'],
    ]);
});

it('solo la administración arma la carga', function () {
    $docente = Fabrica::cuenta('DOCENTE', '41234567');
    app('auth')->forgetGuards();
    \Laravel\Sanctum\Sanctum::actingAs(\App\Models\Usuario::find($docente));
    asignar($this->a3a, $this->mat, $this->luis)->assertForbidden();
    $this->getJson("/api/v1/admin/aulas/{$this->a3a}/carga")->assertForbidden();
});
