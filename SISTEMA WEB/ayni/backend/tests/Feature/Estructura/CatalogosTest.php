<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Fabrica;

// M6 - CU-06 y CU-07: RF-21, RF-22, RF-26, RN-11

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Fabrica::comoAdmin();
});

function asignatura(array $cambios = []): array
{
    return $cambios + ['codigo' => 'raz-v', 'nombre' => 'Razonamiento Verbal', 'horas_semanales' => 2, 'estado' => 'ACTIVO'];
}

describe('asignaturas (RF-21)', function () {
    it('crea con el código en mayúsculas, edita y lista con su uso', function () {
        $id = $this->postJson('/api/v1/admin/asignaturas', asignatura())->assertCreated()->json('id');
        expect(DB::table('asignatura')->find($id)->codigo)->toBe('RAZ-V');

        $this->putJson("/api/v1/admin/asignaturas/{$id}", asignatura(['estado' => 'INACTIVO']))->assertOk();
        expect(collect($this->getJson('/api/v1/admin/asignaturas')->json('asignaturas'))->firstWhere('id', $id))
            ->toMatchArray(['estado' => 'INACTIVO', 'cursos' => 0]);
    });

    it('valida los campos', function (array $cambios, string $campo, string $mensaje) {
        $this->postJson('/api/v1/admin/asignaturas', asignatura($cambios))->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
    })->with([
        'código de 1' => [['codigo' => 'M'], 'codigo', 'El código tiene de 2 a 20 caracteres: letras, números y guion.'],
        'código con espacio' => [['codigo' => 'mat 3'], 'codigo', 'El código tiene de 2 a 20 caracteres: letras, números y guion.'],
        'nombre corto' => [['nombre' => 'Ma'], 'nombre', 'El nombre tiene de 3 a 100 caracteres: letras, números y espacios.'],
        'nombre con símbolos' => [['nombre' => 'C++'], 'nombre', 'El nombre tiene de 3 a 100 caracteres: letras, números y espacios.'],
        'horas 0' => [['horas_semanales' => 0], 'horas_semanales', 'Las horas semanales son un número entero de 1 a 20.'],
        'horas 40' => [['horas_semanales' => 40], 'horas_semanales', 'Las horas semanales son un número entero de 1 a 20.'],
    ]);

    it('no repite el código (sin importar mayúsculas)', function () {
        $this->postJson('/api/v1/admin/asignaturas', asignatura())->assertCreated();
        $this->postJson('/api/v1/admin/asignaturas', asignatura(['codigo' => 'RAZ-V']))->assertStatus(422)->assertJsonPath('errores.codigo.0', 'Ya existe una asignatura con ese código.');
    });

    it('no elimina una asignatura que se dicta en algún aula', function () {
        $p = Fabrica::periodo();
        $curso = Fabrica::curso(Fabrica::aula($p));
        $id = DB::table('curso_aula')->where('id', $curso)->value('asignatura_id');

        $this->deleteJson("/api/v1/admin/asignaturas/{$id}")->assertStatus(422)
            ->assertJsonPath('mensaje', 'La asignatura se dicta en algún aula: no se puede eliminar. Puedes inactivarla.');
        $libre = $this->postJson('/api/v1/admin/asignaturas', asignatura())->json('id');
        $this->deleteJson("/api/v1/admin/asignaturas/{$libre}")->assertOk();
    });
});

describe('tipos de evaluación (RF-22)', function () {
    it('lista los del catálogo, crea, renombra e inactiva', function () {
        expect(collect($this->getJson('/api/v1/admin/tipos-evaluacion')->json('tipos'))->pluck('nombre'))->toContain('Práctica', 'Examen');

        $id = $this->postJson('/api/v1/admin/tipos-evaluacion', ['nombre' => 'Exposición', 'estado' => 'ACTIVO'])->assertCreated()->json('id');
        $this->putJson("/api/v1/admin/tipos-evaluacion/{$id}", ['nombre' => 'Exposición oral', 'estado' => 'INACTIVO'])->assertOk();
        $this->postJson('/api/v1/admin/tipos-evaluacion', ['nombre' => 'Práctica', 'estado' => 'ACTIVO'])->assertStatus(422)->assertJsonPath('errores.nombre.0', 'Ese tipo de evaluación ya existe.');
        $this->postJson('/api/v1/admin/tipos-evaluacion', ['nombre' => 'Ex1', 'estado' => 'ACTIVO'])->assertStatus(422)->assertJsonPath('errores.nombre.0', 'El nombre tiene de 3 a 40 letras.');
    });

    it('no elimina un tipo con evaluaciones', function () {
        $e = Fabrica::escenario();
        $tipo = DB::table('evaluacion')->where('id', $e->evaluacion)->value('tipo_evaluacion_id');
        $this->deleteJson("/api/v1/admin/tipos-evaluacion/{$tipo}")->assertStatus(422);
    });
});

describe('datos del colegio (RF-26)', function () {
    function colegio(array $cambios = []): array
    {
        return array_replace_recursive([
            'nombre' => 'IEP El Señor es mi Pastor',
            'codigo_modular' => '1234567',
            'ugel' => 'UGEL Santa',
            'direccion' => 'Av. Pacífico 123, Nuevo Chimbote',
            'telefono' => '043312345',
            'correo' => 'informes@ayni.test',
            'nota_aprobatoria' => 11,
            'umbral_asistencia_alto' => 85,
            'umbral_asistencia_medio' => 90,
            'umbral_promedio_alto' => 11,
            'umbral_promedio_medio' => 12,
            'umbral_pendientes_alto' => 3,
            'dias_cierre_bimestre' => 7,
            'escala' => [
                ['codigo' => 'AD', 'nota_minima' => 18, 'nota_maxima' => 20],
                ['codigo' => 'A', 'nota_minima' => 14, 'nota_maxima' => 17.99],
                ['codigo' => 'B', 'nota_minima' => 11, 'nota_maxima' => 13.99],
                ['codigo' => 'C', 'nota_minima' => 0, 'nota_maxima' => 10.99],
            ],
        ], $cambios);
    }

    it('muestra y guarda los datos, los umbrales y la escala', function () {
        $this->getJson('/api/v1/admin/institucion')->assertOk()->assertJsonPath('institucion.nombre', 'IEP El Señor es mi Pastor')->assertJsonCount(4, 'escala');

        $this->putJson('/api/v1/admin/institucion', colegio(['nota_aprobatoria' => 12, 'escala' => [1 => ['nota_minima' => 13, 'nota_maxima' => 17.99], 2 => ['nota_minima' => 11, 'nota_maxima' => 12.99]]]))
            ->assertOk()->assertJsonPath('mensaje', 'Datos del colegio guardados.');
        expect((float) DB::table('institucion')->value('nota_aprobatoria'))->toBe(12.0)
            ->and((float) DB::table('escala_literal')->where('codigo', 'A')->value('nota_minima'))->toBe(13.0);
    });

    it('valida los umbrales y los datos', function (array $cambios, string $campo, string $mensaje) {
        $this->putJson('/api/v1/admin/institucion', colegio($cambios))->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
    })->with([
        'nota mayor a 20' => [['nota_aprobatoria' => 21], 'nota_aprobatoria', 'La nota aprobatoria es un número de 0 a 20.'],
        'asistencia alta mayor que media' => [['umbral_asistencia_alto' => 92], 'umbral_asistencia_alto', 'El umbral de asistencia ALTO debe ser menor que el MEDIO.'],
        'promedio alto mayor que medio' => [['umbral_promedio_alto' => 13], 'umbral_promedio_alto', 'El umbral de promedio ALTO no puede ser mayor que el MEDIO.'],
        'porcentaje 120' => [['umbral_asistencia_medio' => 120], 'umbral_asistencia_medio', 'El umbral es un porcentaje de 0 a 100.'],
        'código modular' => [['codigo_modular' => '12345'], 'codigo_modular', 'El código modular tiene 7 dígitos.'],
        'correo' => [['correo' => 'informes@ayni'], 'correo', 'El correo debe tener el formato usuario@dominio.com.'],
        'dirección corta' => [['direccion' => 'Av'], 'direccion', 'La dirección tiene de 5 a 200 caracteres: letras, números, espacios y . , # - / °'],
    ]);

    it('la escala va de 0 a 20 sin huecos ni cruces (RN-11)', function (array $escala) {
        $this->putJson('/api/v1/admin/institucion', colegio(['escala' => $escala]))
            ->assertStatus(422)->assertJsonPath('errores.escala.0', 'Los rangos deben ir de 0 a 20, de C a AD, sin huecos ni cruces (cada uno empieza 0,01 después del anterior).');
    })->with([
        'hueco entre B y A' => [[1 => ['nota_minima' => 15]]],
        'cruce entre C y B' => [[3 => ['nota_maxima' => 11.5]]],
        'AD no llega a 20' => [[0 => ['nota_maxima' => 19.99]]],
    ]);
});

describe('opciones para los desplegables', function () {
    it('trae periodos, grados, docentes activos, asignaturas activas y las aulas del periodo en curso', function () {
        $p = Fabrica::periodo(['nombre' => '2026', 'estado' => 'EN_CURSO']);
        Fabrica::aula($p);
        $luis = Fabrica::docenteLlamado('Luis', 'Paredes Aranda');
        $inactivo = Fabrica::docenteLlamado('Otro', 'Docente Inactivo');
        DB::table('docente')->where('id', $inactivo)->update(['estado' => 'INACTIVO']);
        DB::table('asignatura')->insert([['codigo' => 'MAT', 'nombre' => 'Matemática', 'estado' => 'ACTIVO'], ['codigo' => 'RAZ', 'nombre' => 'Razonamiento', 'estado' => 'INACTIVO']]);

        $r = $this->getJson('/api/v1/admin/estructura/opciones')->assertOk();
        expect($r->json('periodo_id'))->toBe($p)
            ->and($r->json('grados'))->toHaveCount(11)
            ->and($r->json('grados.0.nombre'))->toBe('1.º primaria')
            ->and(collect($r->json('docentes'))->pluck('id')->all())->toContain($luis)->not->toContain($inactivo)
            ->and(collect($r->json('asignaturas'))->pluck('codigo')->all())->toBe(['MAT'])
            ->and($r->json('aulas.0.nombre'))->toBe('3.º A');
    });
});
