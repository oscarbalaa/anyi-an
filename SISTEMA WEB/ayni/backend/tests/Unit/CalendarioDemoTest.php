<?php

use Carbon\CarbonImmutable;
use Database\Seeders\Demo\Azar;
use Database\Seeders\Demo\Calendario;
use Database\Seeders\Demo\Horario;

// M2 - El calendario de demostración funciona en cualquier fecha del año

it('siempre deja hoy dentro de un bimestre abierto', function (string $fecha) {
    $cal = new Calendario(CarbonImmutable::parse($fecha));
    $actual = $cal->bimestres[$cal->bimestreDeHoy()];

    expect($cal->hoy->betweenIncluded($actual['inicio'], $actual['fin']))->toBeTrue()
        ->and($cal->cerrado($cal->bimestreDeHoy()))->toBeFalse()
        ->and($cal->hoy->betweenIncluded($cal->inicio, $cal->fin))->toBeTrue();
})->with(['2026-01-01', '2026-03-02', '2026-07-28', '2026-10-16', '2026-12-31', '2027-02-10', '2028-02-29']);

it('arma 4 bimestres de lunes a viernes, en orden y sin cruces', function () {
    $cal = new Calendario(CarbonImmutable::parse('2026-10-16'));

    foreach ($cal->bimestres as $n => $b) {
        expect($b['inicio']->isMonday())->toBeTrue()
            ->and($b['fin']->isFriday())->toBeTrue()
            ->and($b['cierre']->gt($b['fin']))->toBeTrue();
        if ($n > 1) {
            expect($b['inicio']->gt($cal->bimestres[$n - 1]['fin']))->toBeTrue();
        }
    }
});

it('en octubre arma un año escolar realista (marzo a diciembre)', function () {
    $cal = new Calendario(CarbonImmutable::parse('2026-10-16'));

    expect($cal->inicio->month)->toBeIn([2, 3])
        ->and($cal->fin->month)->toBeIn([11, 12])
        ->and($cal->bimestreDeHoy())->toBe(4)
        ->and($cal->cerrado(3))->toBeTrue();
});

it('arma un horario sin cruces de aula, docente ni curso en el mismo día', function () {
    $cursos = [];
    $id = 1;
    foreach (range(1, 6) as $aula) {
        foreach ([3, 3, 2, 2, 2, 1] as $i => $bloques) {
            // los docentes 1-3 se reparten varias aulas, como los de secundaria
            $cursos[] = ['curso' => $id++, 'aula' => $aula, 'docente' => $i < 3 ? 100 + $i * 10 + intdiv($aula - 1, 3) : 200 + $i, 'bloques' => $bloques];
        }
    }

    $horario = (new Horario(new Azar(7)))->armar($cursos);

    $aula = [];
    $docente = [];
    foreach ($cursos as $c) {
        $dias = array_column($horario[$c['curso']], 'dia');
        expect($dias)->toHaveCount($c['bloques'])
            ->and(array_unique($dias))->toHaveCount($c['bloques']);
        foreach ($horario[$c['curso']] as $b) {
            $slot = $b['dia'].$b['inicio'];
            expect($aula[$c['aula']][$slot] ?? false)->toBeFalse()
                ->and($docente[$c['docente']][$slot] ?? false)->toBeFalse();
            $aula[$c['aula']][$slot] = $docente[$c['docente']][$slot] = true;
        }
    }
});
