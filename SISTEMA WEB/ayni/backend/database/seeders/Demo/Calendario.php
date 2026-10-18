<?php

namespace Database\Seeders\Demo;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calendario del periodo de demostración, armado alrededor de "hoy" para que el
 * sistema siempre tenga un bimestre en curso: hoy cae en la tercera semana del
 * IV bimestre y los otros tres ya están cerrados. En octubre-noviembre esto da un
 * año escolar realista (marzo a diciembre).
 *
 * Semanas (desde el lunes de inicio): I 0-8 · descanso 9 · II 10-18 · vacaciones 19-20
 * · III 21-29 · descanso 30 · IV 31-39.
 */
final class Calendario
{
    private const BIMESTRES = [1 => [0, 8], 2 => [10, 18], 3 => [21, 29], 4 => [31, 39]];

    private const SEMANA_DE_HOY = 33;

    private const ROMANOS = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];

    public readonly CarbonImmutable $hoy;

    public readonly CarbonImmutable $inicio;

    public readonly CarbonImmutable $fin;

    /** @var array<int, array{numero:int, nombre:string, inicio:CarbonImmutable, fin:CarbonImmutable, cierre:CarbonImmutable}> */
    public readonly array $bimestres;

    public function __construct(CarbonInterface $hoy, int $diasCierre = 7)
    {
        $this->hoy = CarbonImmutable::parse($hoy)->startOfDay();
        $this->inicio = $this->hoy->startOfWeek(CarbonInterface::MONDAY)->subWeeks(self::SEMANA_DE_HOY);

        $bimestres = [];
        foreach (self::BIMESTRES as $numero => [$desde, $hasta]) {
            $fin = $this->inicio->addWeeks($hasta)->addDays(4);
            $bimestres[$numero] = [
                'numero' => $numero,
                'nombre' => self::ROMANOS[$numero].' Bimestre',
                'inicio' => $this->inicio->addWeeks($desde),
                'fin' => $fin,
                'cierre' => $fin->addDays($diasCierre),
            ];
        }
        $this->bimestres = $bimestres;
        // RN-02: el periodo incluye el cierre de notas del último bimestre
        $this->fin = $bimestres[4]['cierre'];
    }

    public function nombrePeriodo(): string
    {
        return (string) $this->inicio->year;
    }

    public function bimestreDeHoy(): int
    {
        foreach ($this->bimestres as $numero => $b) {
            if ($this->hoy->betweenIncluded($b['inicio'], $b['fin'])) {
                return $numero;
            }
        }

        return 4;
    }

    public function cerrado(int $numero): bool
    {
        return $this->bimestres[$numero]['cierre']->lt($this->hoy);
    }

    /**
     * Fechas de un día de la semana (1 = lunes) dentro del bimestre, sin pasar del límite.
     *
     * @return list<CarbonImmutable>
     */
    public function diasDeClase(int $numero, int $diaSemana, CarbonImmutable $hasta): array
    {
        $b = $this->bimestres[$numero];
        $fecha = $b['inicio']->addDays($diaSemana - 1);
        $limite = $b['fin']->min($hasta);
        $fechas = [];
        while ($fecha->lte($limite)) {
            $fechas[] = $fecha;
            $fecha = $fecha->addWeek();
        }

        return $fechas;
    }

    /** Día N del bimestre (0 = lunes de la primera semana), saltando al viernes si cae en fin de semana. */
    public function dia(int $numero, int $offset): CarbonImmutable
    {
        $fecha = $this->bimestres[$numero]['inicio']->addDays($offset);

        return $fecha->isWeekend() ? $fecha->previous(CarbonInterface::FRIDAY) : $fecha;
    }
}
