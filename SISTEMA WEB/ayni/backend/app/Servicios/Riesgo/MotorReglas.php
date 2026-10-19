<?php

namespace App\Servicios\Riesgo;

/**
 * Reglas v0 (RN-19), sin IA. Recibe los indicadores del estudiante y los umbrales del colegio, y devuelve
 * el nivel, un puntaje de 0 a 1, las reglas activadas (la explicación) y las recomendaciones de cada regla.
 * En la Unidad 2 un modelo de ML reemplaza esta clase y guarda sus resultados en las mismas tablas.
 */
class MotorReglas
{
    public const NIVELES = ['BAJO' => 0, 'MEDIO' => 1, 'ALTO' => 2];

    public const TITULOS = [
        'asistencia' => 'Faltas frecuentes',
        'promedio_y_pendientes' => 'Promedio bajo con tareas pendientes',
        'promedio' => 'Promedio bajo',
        'descenso' => 'Notas en descenso',
    ];

    /** Peso de cada regla en el puntaje: una regla de riesgo alto pesa más que tres de riesgo medio */
    private const PESO = ['ALTO' => 0.5, 'MEDIO' => 0.15];

    private const BASE = 0.1;

    private const APODERADO_FALTAS = 'Contactar al apoderado para conversar sobre las faltas y acordar apoyos.';

    private const MOTIVOS_FALTAS = 'Conversar con el estudiante sobre los motivos de sus inasistencias.';

    /**
     * @param  array{asistencia: ?float, clases: int, faltas: int, promedio: ?float, curso_bajo: ?array{nombre: string, promedio: float}, pendientes: int, descenso: ?array{curso: string, notas: list<float>}}  $f
     * @param  object  $u  umbrales de `institucion`
     * @return array{nivel: string, probabilidad: float, factores: list<array>, recomendaciones: list<array>}
     */
    public function evaluar(array $f, object $u): array
    {
        $factores = [];
        $asistencia = $f['asistencia'];
        if ($asistencia !== null && $asistencia < (float) $u->umbral_asistencia_alto) {
            $factores[] = $this->factor('asistencia', 'ALTO', self::porcentaje($asistencia), 'Asistencia de '.self::porcentaje($asistencia)." (menos de {$u->umbral_asistencia_alto} %): {$f['faltas']} ".self::plural($f['faltas'], 'falta', 'faltas')." en {$f['clases']} clases.");
        } elseif ($asistencia !== null && $asistencia < (float) $u->umbral_asistencia_medio) {
            $factores[] = $this->factor('asistencia', 'MEDIO', self::porcentaje($asistencia), 'Asistencia de '.self::porcentaje($asistencia)." (menos de {$u->umbral_asistencia_medio} %): {$f['faltas']} ".self::plural($f['faltas'], 'falta', 'faltas')." en {$f['clases']} clases.");
        }

        $promedio = $f['promedio'];
        $curso = $f['curso_bajo'];
        $cursoTexto = $curso ? "; el curso más bajo es {$curso['nombre']} (".self::nota($curso['promedio']).')' : '';
        if ($promedio !== null && $promedio < (float) $u->umbral_promedio_alto && $f['pendientes'] >= (int) $u->umbral_pendientes_alto) {
            $factores[] = $this->factor('promedio_y_pendientes', 'ALTO', self::nota($promedio)." y {$f['pendientes']} pendientes", 'Promedio de '.self::nota($promedio).' (menos de '.self::nota((float) $u->umbral_promedio_alto).") con {$f['pendientes']} entregas pendientes{$cursoTexto}.");
        } elseif ($promedio !== null && $promedio < (float) $u->umbral_promedio_medio) {
            $factores[] = $this->factor('promedio', 'MEDIO', self::nota($promedio), 'Promedio de '.self::nota($promedio).' (menos de '.self::nota((float) $u->umbral_promedio_medio).")$cursoTexto.");
        }

        if ($d = $f['descenso']) {
            $serie = implode(' → ', array_map(self::nota(...), $d['notas']));
            $factores[] = $this->factor('descenso', 'MEDIO', $serie, "Notas en descenso en {$d['curso']}: {$serie}.");
        }

        $nivel = 'BAJO';
        foreach ($factores as $x) {
            if (self::NIVELES[$x['nivel']] > self::NIVELES[$nivel]) {
                $nivel = $x['nivel'];
            }
        }

        return [
            'nivel' => $nivel,
            'probabilidad' => round(min(0.99, self::BASE + array_sum(array_column($factores, 'contribucion'))), 4),
            'factores' => $factores,
            'recomendaciones' => $this->recomendaciones($factores, $f),
        ];
    }

    /** RF-53: acciones predefinidas por regla, sin repetir y de la más urgente a la menos */
    private function recomendaciones(array $factores, array $f): array
    {
        $curso = $f['curso_bajo']['nombre'] ?? null;
        $lista = [];
        foreach ($factores as $x) {
            $nuevas = match ($x['variable']) {
                'asistencia' => $x['nivel'] === 'ALTO' ? [[1, self::APODERADO_FALTAS], [2, self::MOTIVOS_FALTAS]] : [[2, self::MOTIVOS_FALTAS]],
                'promedio_y_pendientes' => array_filter([[1, "Acordar con el estudiante un plan para ponerse al día con sus {$f['pendientes']} entregas pendientes."], $curso ? [1, "Coordinar reforzamiento en {$curso} con su docente."] : null, [2, 'Informar al apoderado sobre las tareas pendientes.']]),
                'promedio' => [[2, $curso ? "Coordinar reforzamiento en {$curso} con su docente." : 'Coordinar reforzamiento en los cursos con nota baja.']],
                'descenso' => [[2, "Conversar con el docente de {$f['descenso']['curso']} sobre la baja de sus notas."]],
            };
            foreach ($nuevas as [$prioridad, $texto]) {
                $lista[$texto] = min($prioridad, $lista[$texto] ?? 3);
            }
        }
        asort($lista);

        return array_map(fn ($texto, $prioridad) => ['descripcion' => $texto, 'prioridad' => $prioridad], array_keys($lista), $lista);
    }

    private function factor(string $variable, string $nivel, string $valor, string $descripcion): array
    {
        return ['variable' => $variable, 'nivel' => $nivel, 'valor' => $valor, 'contribucion' => self::PESO[$nivel], 'descripcion' => $descripcion];
    }

    public static function porcentaje(float $p): string
    {
        return str_replace('.', ',', (string) round($p, 1)).' %';
    }

    /** 10.25 → "10,3"; 11.0 → "11" */
    public static function nota(float $n): string
    {
        $r = round($n, 1);

        return str_replace('.', ',', $r == (int) $r ? (string) (int) $r : (string) $r);
    }

    private static function plural(int $n, string $uno, string $varios): string
    {
        return $n === 1 ? $uno : $varios;
    }
}
