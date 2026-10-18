<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Fabrica;

// M6 - CU-07 y CU-08: RF-19, RF-20, RF-24, RN-03

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Fabrica::comoAdmin();
    $this->p = Fabrica::periodo(['nombre' => '2026', 'estado' => 'EN_CURSO']);
    $this->tercero = Fabrica::grado('SECUNDARIA', 3);
    $this->luis = Fabrica::docenteLlamado('Luis', 'Paredes Aranda');
    $this->carmen = Fabrica::docenteLlamado('Carmen', 'Rivas Mendoza');
});

function nuevaAula(object $t, array $cambios = []): array
{
    return $cambios + ['periodo_id' => $t->p, 'grado_id' => $t->tercero, 'seccion' => 'A', 'capacidad' => 30];
}

describe('aulas (RF-19, RF-20)', function () {
    it('crea el aula con tutor, coordinador y ambiente, y la lista con sus datos', function () {
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this, ['tutor_docente_id' => $this->luis, 'coordinador_docente_id' => $this->carmen, 'ambiente' => 'Aula 12']))
            ->assertCreated();

        $aula = $this->getJson("/api/v1/admin/aulas?periodo_id={$this->p}")->assertOk()->json('aulas.0');
        expect($aula)->toMatchArray([
            'nombre' => '3.º A',
            'nivel' => 'SECUNDARIA',
            'capacidad' => 30,
            'matriculados' => 0,
            'ambiente' => 'Aula 12',
            'tutor' => ['id' => $this->luis, 'nombre' => 'Luis Paredes'],
        ])->and($aula['coordinador'])->toMatchArray(['nombre' => 'Carmen Rivas', 'ambito' => 'AULA', 'propio' => true]);
    });

    it('crea secciones nuevas (D, E…) si no existen', function () {
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this, ['seccion' => 'D']))->assertCreated();
        expect(DB::table('seccion')->where('nombre', 'D')->exists())->toBeTrue();
    });

    it('un grado con sección Única no tiene otras secciones, y viceversa (RN-03)', function () {
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this, ['seccion' => 'Única']))->assertCreated();
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this, ['seccion' => 'A']))
            ->assertStatus(422)->assertJsonPath('errores.seccion.0', 'Este grado tiene sección Única: no puede tener otras secciones (RN-03).');

        $cuarto = Fabrica::grado('SECUNDARIA', 4);
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this, ['grado_id' => $cuarto, 'seccion' => 'A']))->assertCreated();
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this, ['grado_id' => $cuarto, 'seccion' => 'Única']))
            ->assertStatus(422)->assertJsonPath('errores.seccion.0', 'Este grado ya tiene secciones (A): no puede tener además la sección Única (RN-03).');
    });

    it('no repite grado y sección en el mismo periodo', function () {
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this))->assertCreated();
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this))->assertStatus(422)->assertJsonPath('errores.seccion.0', 'Ya existe el aula 3.º A en este periodo.');
    });

    it('valida los campos', function (array $cambios, string $campo, string $mensaje) {
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this, $cambios))->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
    })->with([
        'sección con número' => [['seccion' => '1'], 'seccion', 'La sección es "Única" o de 1 a 3 letras mayúsculas (A, B, C…).'],
        'sección en minúscula' => [['seccion' => 'a'], 'seccion', 'La sección es "Única" o de 1 a 3 letras mayúsculas (A, B, C…).'],
        'capacidad 0' => [['capacidad' => 0], 'capacidad', 'La capacidad es un número entero de 1 a 60.'],
        'capacidad 100' => [['capacidad' => 100], 'capacidad', 'La capacidad es un número entero de 1 a 60.'],
        'capacidad decimal' => [['capacidad' => 25.5], 'capacidad', 'La capacidad es un número entero de 1 a 60.'],
        'tutor inexistente' => [['tutor_docente_id' => 99999], 'tutor_docente_id', 'Elige un docente activo como tutor.'],
        'ambiente con etiquetas' => [['ambiente' => '<b>Lab</b>'], 'ambiente', 'El ambiente solo puede tener letras, números, espacios, punto y guion.'],
    ]);

    it('un docente es tutor de un solo aula por periodo', function () {
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this, ['tutor_docente_id' => $this->luis]))->assertCreated();
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this, ['seccion' => 'B', 'tutor_docente_id' => $this->luis]))
            ->assertStatus(422)->assertJsonPath('errores.tutor_docente_id.0', 'Ese docente ya es tutor de 3.º A.');
    });

    it('la capacidad no baja de los matriculados', function () {
        $id = $this->postJson('/api/v1/admin/aulas', nuevaAula($this))->json('id');
        Fabrica::matricula($id, $this->p);
        Fabrica::matricula($id, $this->p);

        $this->putJson("/api/v1/admin/aulas/{$id}", ['capacidad' => 1])
            ->assertStatus(422)->assertJsonPath('errores.capacidad.0', 'El aula ya tiene 2 matriculados: la capacidad no puede ser menor.');
        $this->putJson("/api/v1/admin/aulas/{$id}", ['capacidad' => 2, 'coordinador_docente_id' => $this->luis])->assertOk();
        expect($this->getJson("/api/v1/admin/aulas?periodo_id={$this->p}")->json('aulas.0.matriculados'))->toBe(2);
    });

    it('no cambia grado ni sección al editar', function () {
        $id = $this->postJson('/api/v1/admin/aulas', nuevaAula($this))->json('id');
        $this->putJson("/api/v1/admin/aulas/{$id}", ['capacidad' => 30, 'seccion' => 'B'])->assertStatus(422);
    });

    it('no se elimina un aula con matrículas o cursos', function () {
        $id = $this->postJson('/api/v1/admin/aulas', nuevaAula($this))->json('id');
        Fabrica::curso($id);
        $this->deleteJson("/api/v1/admin/aulas/{$id}")->assertStatus(422)->assertJsonPath('mensaje', 'El aula ya tiene matrículas o cursos: no se puede eliminar.');

        $vacia = $this->postJson('/api/v1/admin/aulas', nuevaAula($this, ['seccion' => 'B']))->json('id');
        $this->deleteJson("/api/v1/admin/aulas/{$vacia}")->assertOk();
    });

    it('un periodo cerrado ya no se modifica', function () {
        DB::table('periodo_academico')->where('id', $this->p)->update(['estado' => 'CERRADO']);
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this))->assertStatus(422)->assertJsonPath('mensaje', 'El periodo está cerrado: su estructura ya no se modifica.');
    });

    it('filtra por nivel', function () {
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this))->assertCreated();
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this, ['grado_id' => Fabrica::grado('PRIMARIA', 2), 'seccion' => 'Única']))->assertCreated();

        expect($this->getJson("/api/v1/admin/aulas?periodo_id={$this->p}&nivel=PRIMARIA")->json('aulas'))->toHaveCount(1)
            ->and($this->getJson("/api/v1/admin/aulas?periodo_id={$this->p}&nivel=PRIMARIA")->json('aulas.0.nombre'))->toBe('2.º primaria');
    });
});

describe('coordinadores (RF-24)', function () {
    it('el aula sin coordinador propio toma el de su grado, su nivel o el del colegio', function () {
        $this->postJson('/api/v1/admin/aulas', nuevaAula($this))->assertCreated();
        $aula = fn () => $this->getJson("/api/v1/admin/aulas?periodo_id={$this->p}")->json('aulas.0.coordinador');
        expect($aula())->toBeNull();

        $this->postJson('/api/v1/admin/coordinaciones', ['periodo_id' => $this->p, 'docente_id' => $this->carmen, 'ambito' => 'INSTITUCION'])->assertCreated();
        expect($aula())->toMatchArray(['nombre' => 'Carmen Rivas', 'ambito' => 'INSTITUCION', 'propio' => false]);

        $this->postJson('/api/v1/admin/coordinaciones', ['periodo_id' => $this->p, 'docente_id' => $this->luis, 'ambito' => 'GRADO', 'grado_id' => $this->tercero])->assertCreated();
        expect($aula())->toMatchArray(['nombre' => 'Luis Paredes', 'ambito' => 'GRADO']);

        $lista = $this->getJson("/api/v1/admin/aulas?periodo_id={$this->p}")->json('coordinaciones');
        expect(collect($lista)->pluck('alcance')->all())->toBe(['Todo el colegio', '3.º secundaria']);
    });

    it('un ámbito tiene un solo coordinador', function () {
        $datos = ['periodo_id' => $this->p, 'docente_id' => $this->luis, 'ambito' => 'NIVEL', 'nivel' => 'SECUNDARIA'];
        $id = $this->postJson('/api/v1/admin/coordinaciones', $datos)->assertCreated()->json('id');
        $this->postJson('/api/v1/admin/coordinaciones', ['docente_id' => $this->carmen] + $datos)
            ->assertStatus(422)->assertJsonPath('errores.ambito.0', 'Ese ámbito ya tiene coordinador. Quítalo primero para asignar otro.');

        $this->deleteJson("/api/v1/admin/coordinaciones/{$id}")->assertOk();
        $this->postJson('/api/v1/admin/coordinaciones', ['docente_id' => $this->carmen] + $datos)->assertCreated();
    });

    it('pide el grado o el nivel según el ámbito', function () {
        $this->postJson('/api/v1/admin/coordinaciones', ['periodo_id' => $this->p, 'docente_id' => $this->luis, 'ambito' => 'GRADO'])
            ->assertStatus(422)->assertJsonPath('errores.grado_id.0', 'Elige el grado que coordinará.');
        $this->postJson('/api/v1/admin/coordinaciones', ['periodo_id' => $this->p, 'docente_id' => $this->luis, 'ambito' => 'AULA'])
            ->assertStatus(422)->assertJsonPath('errores.ambito.0', 'El ámbito es un grado, un nivel o todo el colegio.');
    });
});
