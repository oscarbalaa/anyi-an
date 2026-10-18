<?php

namespace Database\Seeders\Demo;

use RuntimeException;

/**
 * Arma el horario semanal sin cruces: un aula y un docente no pueden estar en dos
 * bloques a la vez, y un curso no se repite el mismo día. Búsqueda con retroceso.
 */
final class Horario
{
    /** Bloques de 90 minutos: 3 por día, de lunes a viernes */
    public const BLOQUES = [['08:00', '09:30'], ['09:45', '11:15'], ['11:30', '13:00']];

    private array $aulaOcupada = [];

    private array $docenteOcupado = [];

    private array $cursoDia = [];

    private array $asignado = [];

    private int $pasos = 0;

    public function __construct(private readonly Azar $azar) {}

    /**
     * @param  list<array{curso:int, aula:int, docente:int, bloques:int}>  $cursos
     * @return array<int, list<array{dia:int, inicio:string, fin:string}>> curso => bloques
     */
    public function armar(array $cursos): array
    {
        $carga = [];
        foreach ($cursos as $c) {
            $carga[$c['docente']] = ($carga[$c['docente']] ?? 0) + $c['bloques'];
        }

        // Primero los docentes más cargados: son los más difíciles de ubicar
        $unidades = [];
        foreach ($cursos as $c) {
            for ($k = 0; $k < $c['bloques']; $k++) {
                $unidades[] = $c;
            }
        }
        usort($unidades, fn ($a, $b) => [$carga[$b['docente']], $b['curso']] <=> [$carga[$a['docente']], $a['curso']]);

        if (! $this->ubicar($unidades, 0)) {
            throw new RuntimeException('No se pudo armar un horario sin cruces.');
        }

        $resultado = [];
        foreach ($this->asignado as [$curso, $slot]) {
            $resultado[$curso][] = [
                'dia' => intdiv($slot, 3) + 1,
                'inicio' => self::BLOQUES[$slot % 3][0],
                'fin' => self::BLOQUES[$slot % 3][1],
            ];
        }

        return $resultado;
    }

    private function ubicar(array $unidades, int $i): bool
    {
        if ($i === count($unidades)) {
            return true;
        }
        if (++$this->pasos > 2_000_000) {
            return false;
        }

        $u = $unidades[$i];
        foreach ($this->azar->mezclar(range(0, 14)) as $slot) {
            $dia = intdiv($slot, 3);
            if (isset($this->aulaOcupada[$u['aula']][$slot])
                || isset($this->docenteOcupado[$u['docente']][$slot])
                || isset($this->cursoDia[$u['curso']][$dia])) {
                continue;
            }

            $this->aulaOcupada[$u['aula']][$slot] = true;
            $this->docenteOcupado[$u['docente']][$slot] = true;
            $this->cursoDia[$u['curso']][$dia] = true;
            $this->asignado[] = [$u['curso'], $slot];

            if ($this->ubicar($unidades, $i + 1)) {
                return true;
            }

            array_pop($this->asignado);
            unset($this->aulaOcupada[$u['aula']][$slot], $this->docenteOcupado[$u['docente']][$slot], $this->cursoDia[$u['curso']][$dia]);
        }

        return false;
    }
}
