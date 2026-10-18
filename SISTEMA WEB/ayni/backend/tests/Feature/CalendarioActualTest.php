<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Fabrica;

// M3 - Periodo y bimestre que se muestran en el pie del menú

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    $periodo = Fabrica::periodo(['nombre' => '2026', 'estado' => 'EN_CURSO', 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-12-18']);
    Fabrica::bimestre($periodo, ['numero' => 1, 'nombre' => 'I Bimestre', 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-05-08', 'fecha_cierre_notas' => '2026-05-15']);
    Fabrica::bimestre($periodo, ['numero' => 2, 'nombre' => 'II Bimestre', 'fecha_inicio' => '2026-05-18', 'fecha_fin' => '2026-07-17', 'fecha_cierre_notas' => '2026-07-24']);
});

afterEach(fn () => Carbon::setTestNow());

it('devuelve el periodo en curso y el bimestre de hoy', function () {
    Carbon::setTestNow('2026-06-10');

    $this->getJson('/api/v1/calendario/actual')
        ->assertOk()
        ->assertJsonPath('periodo.nombre', '2026')
        ->assertJsonPath('bimestre.nombre', 'II Bimestre');
});

it('en vacaciones, mientras las notas del bimestre siguen abiertas, muestra ese bimestre y los días que faltan', function () {
    Carbon::setTestNow('2026-05-12');

    $this->getJson('/api/v1/calendario/actual')
        ->assertJsonPath('bimestre.numero', 1)
        ->assertJsonPath('bimestre.dias_para_cierre', 3);
});

it('en vacaciones, con el bimestre ya cerrado, devuelve el próximo', function () {
    Carbon::setTestNow('2026-05-16');

    // M6 - RN-14: la fecha de cierre pasó y el bimestre se cierra solo al consultar
    $this->getJson('/api/v1/calendario/actual')->assertJsonPath('bimestre.numero', 2);
    expect(DB::table('subperiodo')->where('numero', 1)->first())
        ->estado->toBe('CERRADO')
        ->cerrado_por->toBeNull();
});

it('responde vacío si no hay periodo en curso', function () {
    DB::table('periodo_academico')->update(['estado' => 'CERRADO']);

    $this->getJson('/api/v1/calendario/actual')
        ->assertOk()
        ->assertExactJson(['periodo' => null, 'bimestre' => null]);
});
