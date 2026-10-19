<?php

use App\Servicios\Riesgo\MotorReglas;

// M11 - RN-19, RF-52, RF-53: cada regla con datos armados a propósito

function umbrales(array $cambios = []): object
{
    return (object) ($cambios + ['umbral_asistencia_alto' => 85, 'umbral_asistencia_medio' => 90, 'umbral_promedio_alto' => 11, 'umbral_promedio_medio' => 12, 'umbral_pendientes_alto' => 3]);
}

function indicadores(array $cambios = []): array
{
    return $cambios + ['asistencia' => 98.0, 'clases' => 40, 'faltas' => 1, 'promedio' => 15.0, 'curso_bajo' => ['nombre' => 'Matemática', 'promedio' => 13.0], 'pendientes' => 0, 'descenso' => null];
}

it('sin reglas activadas el riesgo es bajo', function () {
    $r = (new MotorReglas)->evaluar(indicadores(), umbrales());

    expect($r)->toMatchArray(['nivel' => 'BAJO', 'probabilidad' => 0.1, 'factores' => [], 'recomendaciones' => []]);
});

it('30 % de faltas es riesgo alto, con su explicación y sus recomendaciones', function () {
    $r = (new MotorReglas)->evaluar(indicadores(['asistencia' => 70.0, 'clases' => 20, 'faltas' => 6]), umbrales());

    expect($r['nivel'])->toBe('ALTO')
        ->and($r['probabilidad'])->toBe(0.6)
        ->and($r['factores'])->toHaveCount(1)
        ->and($r['factores'][0])->toMatchArray(['variable' => 'asistencia', 'nivel' => 'ALTO', 'valor' => '70 %', 'descripcion' => 'Asistencia de 70 % (menos de 85 %): 6 faltas en 20 clases.'])
        ->and(array_column($r['recomendaciones'], 'descripcion'))->toBe([
            'Contactar al apoderado para conversar sobre las faltas y acordar apoyos.',
            'Conversar con el estudiante sobre los motivos de sus inasistencias.',
        ])
        ->and($r['recomendaciones'][0]['prioridad'])->toBe(1);
});

it('aplica cada regla en su límite exacto', function (array $f, string $nivel, ?string $variable) {
    $r = (new MotorReglas)->evaluar(indicadores($f), umbrales());

    expect($r['nivel'])->toBe($nivel)->and($r['factores'][0]['variable'] ?? null)->toBe($variable);
})->with([
    'asistencia de 84,9 %: alto' => [['asistencia' => 84.9], 'ALTO', 'asistencia'],
    'asistencia de 85 %: medio' => [['asistencia' => 85.0], 'MEDIO', 'asistencia'],
    'asistencia de 89,9 %: medio' => [['asistencia' => 89.9], 'MEDIO', 'asistencia'],
    'asistencia de 90 %: bajo' => [['asistencia' => 90.0], 'BAJO', null],
    'sin clases registradas: no cuenta' => [['asistencia' => null, 'clases' => 0, 'faltas' => 0], 'BAJO', null],
    'promedio 10,9 con 3 pendientes: alto' => [['promedio' => 10.9, 'pendientes' => 3], 'ALTO', 'promedio_y_pendientes'],
    'promedio 10,9 con 2 pendientes: medio' => [['promedio' => 10.9, 'pendientes' => 2], 'MEDIO', 'promedio'],
    'promedio 11 con 5 pendientes: medio' => [['promedio' => 11.0, 'pendientes' => 5], 'MEDIO', 'promedio'],
    'promedio 11,9: medio' => [['promedio' => 11.9], 'MEDIO', 'promedio'],
    'promedio 12: bajo' => [['promedio' => 12.0], 'BAJO', null],
    'sin notas: no cuenta' => [['promedio' => null, 'curso_bajo' => null], 'BAJO', null],
    'notas en descenso: medio' => [['descenso' => ['curso' => 'Comunicación', 'notas' => [16.0, 14.0, 12.5]]], 'MEDIO', 'descenso'],
]);

it('el nivel es el de la regla más grave y el puntaje suma todas', function () {
    $r = (new MotorReglas)->evaluar(indicadores(['asistencia' => 88.0, 'promedio' => 10.0, 'pendientes' => 4, 'descenso' => ['curso' => 'Matemática', 'notas' => [14.0, 12.0, 9.0]]]), umbrales());

    expect($r['nivel'])->toBe('ALTO')
        ->and(array_column($r['factores'], 'variable'))->toBe(['asistencia', 'promedio_y_pendientes', 'descenso'])
        ->and($r['probabilidad'])->toBe(0.9)
        ->and($r['factores'][1]['descripcion'])->toBe('Promedio de 10 (menos de 11) con 4 entregas pendientes; el curso más bajo es Matemática (13).')
        ->and($r['factores'][2]['descripcion'])->toBe('Notas en descenso en Matemática: 14 → 12 → 9.');
});

it('tres reglas de riesgo medio pesan menos que una de riesgo alto', function () {
    $medio = (new MotorReglas)->evaluar(indicadores(['asistencia' => 88.0, 'promedio' => 11.5, 'descenso' => ['curso' => 'Arte', 'notas' => [15.0, 13.0, 12.0]]]), umbrales());
    $alto = (new MotorReglas)->evaluar(indicadores(['asistencia' => 80.0]), umbrales());

    expect($medio['nivel'])->toBe('MEDIO')->and($medio['probabilidad'])->toBeLessThan($alto['probabilidad']);
});

it('da las recomendaciones de cada regla, sin repetir y de la más urgente a la menos', function () {
    $r = (new MotorReglas)->evaluar(indicadores(['asistencia' => 88.0, 'promedio' => 10.0, 'pendientes' => 4, 'curso_bajo' => ['nombre' => 'Matemática', 'promedio' => 8.5], 'descenso' => ['curso' => 'Matemática', 'notas' => [14.0, 12.0, 9.0]]]), umbrales());

    expect($r['recomendaciones'])->toBe([
        ['descripcion' => 'Acordar con el estudiante un plan para ponerse al día con sus 4 entregas pendientes.', 'prioridad' => 1],
        ['descripcion' => 'Coordinar reforzamiento en Matemática con su docente.', 'prioridad' => 1],
        ['descripcion' => 'Conversar con el estudiante sobre los motivos de sus inasistencias.', 'prioridad' => 2],
        ['descripcion' => 'Informar al apoderado sobre las tareas pendientes.', 'prioridad' => 2],
        ['descripcion' => 'Conversar con el docente de Matemática sobre la baja de sus notas.', 'prioridad' => 2],
    ]);
});

it('usa los umbrales configurados por el colegio', function () {
    $f = indicadores(['asistencia' => 92.0]);

    expect((new MotorReglas)->evaluar($f, umbrales())['nivel'])->toBe('BAJO')
        ->and((new MotorReglas)->evaluar($f, umbrales(['umbral_asistencia_alto' => 90, 'umbral_asistencia_medio' => 95]))['nivel'])->toBe('MEDIO')
        ->and((new MotorReglas)->evaluar($f, umbrales(['umbral_asistencia_alto' => 93, 'umbral_asistencia_medio' => 95]))['nivel'])->toBe('ALTO');
});
