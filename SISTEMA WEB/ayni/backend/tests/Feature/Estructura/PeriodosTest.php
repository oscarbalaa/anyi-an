<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Fabrica;

// M6 - CU-06: RF-17, RF-18, RN-01, RN-02, RN-14

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    $this->admin = Fabrica::comoAdmin();
    Carbon::setTestNow('2026-06-10');
});

afterEach(fn () => Carbon::setTestNow());

function periodo2027(array $cambios = []): array
{
    return $cambios + ['nombre' => '2027', 'fecha_inicio' => '2027-03-01', 'fecha_fin' => '2027-12-17'];
}

/** Periodo 2026 en curso con el I bimestre (cierra el 15 de mayo) y el II (en curso) */
function periodoEnCurso(): int
{
    $p = Fabrica::periodo(['nombre' => '2026', 'estado' => 'EN_CURSO', 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-12-18']);
    Fabrica::bimestre($p, ['numero' => 1, 'nombre' => 'I Bimestre', 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-05-08', 'fecha_cierre_notas' => '2026-05-15']);
    Fabrica::bimestre($p, ['numero' => 2, 'nombre' => 'II Bimestre', 'fecha_inicio' => '2026-05-18', 'fecha_fin' => '2026-07-17', 'fecha_cierre_notas' => '2026-07-24']);

    return $p;
}

describe('periodos (RF-17)', function () {
    it('lista los periodos con sus 4 lugares de bimestre y la situación de cada uno', function () {
        periodoEnCurso();

        $p = $this->getJson('/api/v1/admin/periodos')->assertOk()->json('periodos.0');
        expect($p['nombre'])->toBe('2026')
            ->and(collect($p['bimestres'])->pluck('situacion')->all())->toBe(['CERRADO', 'EN_CURSO', 'SIN_DEFINIR', 'SIN_DEFINIR'])
            // El I bimestre pasó su fecha de cierre: se cerró solo
            ->and($p['bimestres'][0]['cerrado_automatico'])->toBeTrue();
    });

    it('crea un periodo planificado y lo valida', function () {
        $this->postJson('/api/v1/admin/periodos', periodo2027())->assertCreated();
        expect(DB::table('periodo_academico')->where('nombre', '2027')->value('estado'))->toBe('PLANIFICADO');

        $this->postJson('/api/v1/admin/periodos', periodo2027())->assertStatus(422)
            ->assertJsonPath('errores.nombre.0', 'Ya existe un periodo con ese año.');
    });

    it('rechaza datos inválidos', function (array $cambios, string $campo, string $mensaje) {
        $this->postJson('/api/v1/admin/periodos', periodo2027($cambios))->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
    })->with([
        'año de 2 dígitos' => [['nombre' => '27'], 'nombre', 'El periodo es un año de 4 dígitos entre 2020 y 2100.'],
        'año con texto' => [['nombre' => '2026-I'], 'nombre', 'El periodo es un año de 4 dígitos entre 2020 y 2100.'],
        'año fuera de rango' => [['nombre' => '2101'], 'nombre', 'El periodo es un año de 4 dígitos entre 2020 y 2100.'],
        'fin antes del inicio' => [['fecha_fin' => '2027-02-01'], 'fecha_fin', 'La fecha de fin debe ser posterior a la de inicio.'],
        'fecha imposible' => [['fecha_inicio' => '2027-02-31'], 'fecha_inicio', 'Ingresa una fecha de inicio válida.'],
    ]);

    it('solo un periodo en curso a la vez (RN-01)', function () {
        periodoEnCurso();
        $id = $this->postJson('/api/v1/admin/periodos', periodo2027())->json('id');
        $this->postJson("/api/v1/admin/periodos/{$id}/bimestres", ['numero' => 1, 'fecha_inicio' => '2027-03-01', 'fecha_fin' => '2027-05-07'])->assertCreated();

        $this->patchJson("/api/v1/admin/periodos/{$id}/estado", ['estado' => 'EN_CURSO'])
            ->assertStatus(422)->assertJsonPath('mensaje', 'Ya hay un periodo en curso (2026). Ciérralo antes de iniciar otro (RN-01).');
    });

    it('para iniciar un periodo necesita al menos un bimestre', function () {
        $id = $this->postJson('/api/v1/admin/periodos', periodo2027())->json('id');
        $this->patchJson("/api/v1/admin/periodos/{$id}/estado", ['estado' => 'EN_CURSO'])
            ->assertStatus(422)->assertJsonPath('mensaje', 'Define al menos el I Bimestre antes de iniciar el periodo.');
    });

    it('al cerrar el periodo se cierran sus bimestres abiertos y ya no se reabre', function () {
        $p = periodoEnCurso();
        $this->patchJson("/api/v1/admin/periodos/{$p}/estado", ['estado' => 'CERRADO'])->assertOk();

        expect(DB::table('subperiodo')->where('periodo_id', $p)->pluck('estado')->unique()->all())->toBe(['CERRADO'])
            ->and(DB::table('subperiodo')->where('numero', 2)->value('cerrado_por'))->toBe($this->admin);
        $this->patchJson("/api/v1/admin/periodos/{$p}/estado", ['estado' => 'EN_CURSO'])
            ->assertStatus(422)->assertJsonPath('mensaje', 'Un periodo cerrado no se vuelve a abrir.');
        $this->putJson("/api/v1/admin/periodos/{$p}", periodo2027(['nombre' => '2026']))->assertStatus(422);
    });

    it('no deja las fechas del periodo fuera de sus bimestres', function () {
        $p = periodoEnCurso();
        $this->putJson("/api/v1/admin/periodos/{$p}", ['nombre' => '2026', 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-12-18'])
            ->assertStatus(422)->assertJsonPath('errores.fecha_fin.0', 'El I Bimestre quedaría fuera del periodo. Cambia primero sus fechas.');
    });

    it('solo elimina periodos planificados y sin aulas', function () {
        $p = periodoEnCurso();
        $this->deleteJson("/api/v1/admin/periodos/{$p}")->assertStatus(422);

        $id = $this->postJson('/api/v1/admin/periodos', periodo2027())->json('id');
        Fabrica::aula($id);
        $this->deleteJson("/api/v1/admin/periodos/{$id}")->assertStatus(422)->assertJsonPath('mensaje', 'El periodo ya tiene aulas: elimínalas primero.');
        DB::table('aula')->where('periodo_id', $id)->delete();
        $this->deleteJson("/api/v1/admin/periodos/{$id}")->assertOk();
    });
});

describe('bimestres y fecha de cierre (RF-18, RN-02)', function () {
    beforeEach(function () {
        $this->p = $this->postJson('/api/v1/admin/periodos', periodo2027())->json('id');
        $this->postJson("/api/v1/admin/periodos/{$this->p}/bimestres", ['numero' => 1, 'fecha_inicio' => '2027-03-01', 'fecha_fin' => '2027-05-07'])->assertCreated();
    });

    it('pone por defecto el cierre al fin del bimestre + 7 días', function () {
        expect(DB::table('subperiodo')->where('numero', 1)->first())
            ->nombre->toBe('I Bimestre')
            ->fecha_cierre_notas->toBe('2027-05-14');
    });

    it('el cierre por defecto no pasa del fin del periodo', function () {
        $this->postJson("/api/v1/admin/periodos/{$this->p}/bimestres", ['numero' => 4, 'fecha_inicio' => '2027-10-04', 'fecha_fin' => '2027-12-17'])->assertCreated();
        expect(DB::table('subperiodo')->where('numero', 4)->value('fecha_cierre_notas'))->toBe('2027-12-17');
    });

    it('rechaza bimestres fuera del periodo, cruzados o desordenados', function (array $datos, string $campo, string $mensaje) {
        $this->postJson("/api/v1/admin/periodos/{$this->p}/bimestres", $datos)->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
    })->with([
        'antes del periodo' => [['numero' => 2, 'fecha_inicio' => '2027-02-01', 'fecha_fin' => '2027-07-16'], 'fecha_inicio', 'El bimestre debe estar dentro del periodo (01/03/2027 – 17/12/2027).'],
        'cruzado con el I' => [['numero' => 2, 'fecha_inicio' => '2027-05-03', 'fecha_fin' => '2027-07-16'], 'fecha_inicio', 'Debe empezar después del I Bimestre (termina el 07/05/2027).'],
        'repetido' => [['numero' => 1, 'fecha_inicio' => '2027-08-02', 'fecha_fin' => '2027-10-01'], 'numero', 'Ese bimestre ya está definido.'],
        'número 5' => [['numero' => 5, 'fecha_inicio' => '2027-08-02', 'fecha_fin' => '2027-10-01'], 'numero', 'El bimestre es del I al IV.'],
        'fin antes del inicio' => [['numero' => 2, 'fecha_inicio' => '2027-07-16', 'fecha_fin' => '2027-05-17'], 'fecha_fin', 'La fecha de fin debe ser posterior a la de inicio.'],
        'cierre antes del inicio' => [['numero' => 2, 'fecha_inicio' => '2027-05-17', 'fecha_fin' => '2027-07-16', 'fecha_cierre_notas' => '2027-05-10'], 'fecha_cierre_notas', 'La fecha de cierre de notas no puede ser anterior al inicio del bimestre (RN-02).'],
        'cierre después del periodo' => [['numero' => 2, 'fecha_inicio' => '2027-05-17', 'fecha_fin' => '2027-07-16', 'fecha_cierre_notas' => '2027-12-20'], 'fecha_cierre_notas', 'La fecha de cierre de notas no puede ser anterior al inicio del bimestre ni posterior al fin del periodo (RN-02).'],
    ]);

    it('un bimestre posterior no puede quedar antes de uno anterior', function () {
        $this->postJson("/api/v1/admin/periodos/{$this->p}/bimestres", ['numero' => 3, 'fecha_inicio' => '2027-08-02', 'fecha_fin' => '2027-10-01'])->assertCreated();
        $this->postJson("/api/v1/admin/periodos/{$this->p}/bimestres", ['numero' => 2, 'fecha_inicio' => '2027-05-17', 'fecha_fin' => '2027-08-06'])
            ->assertStatus(422)->assertJsonPath('errores.fecha_fin.0', 'Debe terminar antes del III Bimestre (empieza el 02/08/2027).');
    });

    it('edita las fechas y la de cierre de un bimestre abierto', function () {
        $id = DB::table('subperiodo')->where('numero', 1)->value('id');
        $this->putJson("/api/v1/admin/bimestres/{$id}", ['fecha_inicio' => '2027-03-01', 'fecha_fin' => '2027-05-07', 'fecha_cierre_notas' => '2027-05-21'])->assertOk();
        expect(DB::table('subperiodo')->find($id)->fecha_cierre_notas)->toBe('2027-05-21');
        $this->putJson("/api/v1/admin/bimestres/{$id}", ['numero' => 2, 'fecha_inicio' => '2027-03-01', 'fecha_fin' => '2027-05-07'])->assertStatus(422);
    });
});

describe('cierre de bimestres (RN-14)', function () {
    it('se cierra solo al pasar la fecha de cierre, sin usuario', function () {
        $p = periodoEnCurso();
        $this->getJson('/api/v1/admin/periodos')->assertOk();

        expect(DB::table('subperiodo')->where('periodo_id', $p)->where('numero', 1)->first())
            ->estado->toBe('CERRADO')->cerrado_por->toBeNull()
            ->and(DB::table('subperiodo')->where('numero', 2)->value('estado'))->toBe('ABIERTO')
            ->and(DB::table('auditoria')->where('accion', 'CERRAR_BIMESTRE')->whereNull('usuario_id')->exists())->toBeTrue();
    });

    it('el administrador puede cerrarlo antes, y ya no se editan sus fechas', function () {
        periodoEnCurso();
        $id = DB::table('subperiodo')->where('numero', 2)->value('id');

        $this->postJson("/api/v1/admin/bimestres/{$id}/cerrar")->assertOk();
        expect(DB::table('subperiodo')->find($id)->cerrado_por)->toBe($this->admin);
        $this->postJson("/api/v1/admin/bimestres/{$id}/cerrar")->assertStatus(422)->assertJsonPath('mensaje', 'El bimestre ya está cerrado.');
        $this->putJson("/api/v1/admin/bimestres/{$id}", ['fecha_inicio' => '2026-05-18', 'fecha_fin' => '2026-07-17'])
            ->assertStatus(422)->assertJsonPath('mensaje', 'El bimestre ya está cerrado: sus fechas no se cambian.');
    });

    it('no se elimina un bimestre en uso', function () {
        $e = Fabrica::escenario();
        $this->deleteJson("/api/v1/admin/bimestres/{$e->bimestre}")->assertStatus(422);
    });
});

it('solo la administración gestiona periodos', function () {
    $docente = Fabrica::cuenta('DOCENTE', '41234567');
    app('auth')->forgetGuards();
    \Laravel\Sanctum\Sanctum::actingAs(\App\Models\Usuario::find($docente));

    $this->getJson('/api/v1/admin/periodos')->assertForbidden();
    $this->postJson('/api/v1/admin/periodos', periodo2027())->assertForbidden();
});
