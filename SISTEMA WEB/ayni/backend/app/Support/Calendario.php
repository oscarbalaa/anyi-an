<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Periodo en curso y el bimestre "de hoy" (lo usan el calendario y la asistencia) */
class Calendario
{
    public static function periodoEnCurso(): ?object
    {
        return DB::table('periodo_academico')->where('estado', 'EN_CURSO')->first(['id', 'nombre', 'fecha_inicio', 'fecha_fin']);
    }

    public static function bimestres(int $periodoId): Collection
    {
        return DB::table('subperiodo')->where('periodo_id', $periodoId)->orderBy('numero')
            ->get(['id', 'numero', 'nombre', 'fecha_inicio', 'fecha_fin', 'fecha_cierre_notas', 'estado']);
    }

    /** El bimestre de hoy o, si hoy cae en vacaciones, el último ya iniciado con notas abiertas o el próximo */
    public static function bimestreVigente(Collection $bimestres): ?object
    {
        $hoy = today()->toDateString();

        return $bimestres->first(fn ($b) => $b->fecha_inicio <= $hoy && $hoy <= $b->fecha_fin)
            // Clases terminadas pero notas aún abiertas: sigue siendo "el bimestre" para los docentes
            ?? $bimestres->first(fn ($b) => $b->estado === 'ABIERTO' && $b->fecha_inicio <= $hoy)
            ?? $bimestres->first(fn ($b) => $b->fecha_inicio > $hoy)
            ?? $bimestres->last();
    }
}
