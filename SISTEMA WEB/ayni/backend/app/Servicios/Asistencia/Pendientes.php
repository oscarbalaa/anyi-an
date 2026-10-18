<?php

namespace App\Servicios\Asistencia;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Clases ya dictadas (hasta ayer) que no tienen asistencia definitiva: insumo de las alertas del M11 */
class Pendientes
{
    /** @return array<int, list<string>> curso_id => fechas sin registro, de la más antigua a la más reciente */
    public function porCurso(array $cursoIds, object $bimestre): array
    {
        $hasta = min(today()->subDay()->toDateString(), $bimestre->fecha_fin);
        if (! $cursoIds || $hasta < $bimestre->fecha_inicio) {
            return [];
        }

        $dias = (new Asistencias)->diasDeClase($cursoIds);
        $registradas = DB::table('sesion_clase')->whereIn('curso_aula_id', $cursoIds)->where('estado', 'DEFINITIVO')
            ->whereBetween('fecha', [$bimestre->fecha_inicio, $hasta])->get(['curso_aula_id', 'fecha'])
            ->groupBy('curso_aula_id')->map(fn ($g) => array_flip($g->pluck('fecha')->all()));

        $fechasPorDia = [];
        foreach (Asistencias::recorrer($bimestre->fecha_inicio, $hasta) as $f) {
            $fechasPorDia[Carbon::parse($f)->dayOfWeekIso][] = $f;
        }

        $pendientes = [];
        foreach ($dias as $cursoId => $diasCurso) {
            foreach ($diasCurso as $dia) {
                foreach ($fechasPorDia[$dia] ?? [] as $f) {
                    if (! isset($registradas[$cursoId][$f])) {
                        $pendientes[$cursoId][] = $f;
                    }
                }
            }
            if (isset($pendientes[$cursoId])) {
                sort($pendientes[$cursoId]);
            }
        }

        return $pendientes;
    }
}
