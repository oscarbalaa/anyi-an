<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Servicios\Estructura\CierreBimestres;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CalendarioController extends Controller
{
    /** Periodo en curso y el bimestre de hoy (o el próximo, si hoy cae en vacaciones) */
    public function actual(CierreBimestres $cierre): JsonResponse
    {
        // Cada pantalla pide este dato: aquí se revisa el cierre automático (RN-14)
        $cierre->revisar();

        $periodo = DB::table('periodo_academico')
            ->where('estado', 'EN_CURSO')
            ->first(['id', 'nombre', 'fecha_inicio', 'fecha_fin']);

        $bimestre = null;
        if ($periodo) {
            $hoy = today()->toDateString();
            $bimestres = DB::table('subperiodo')
                ->where('periodo_id', $periodo->id)
                ->orderBy('numero')
                ->get(['id', 'numero', 'nombre', 'fecha_inicio', 'fecha_fin', 'fecha_cierre_notas', 'estado']);

            $bimestre = $bimestres->first(fn ($b) => $b->fecha_inicio <= $hoy && $hoy <= $b->fecha_fin)
                // Clases terminadas pero notas aún abiertas: sigue siendo "el bimestre" para los docentes
                ?? $bimestres->first(fn ($b) => $b->estado === 'ABIERTO' && $b->fecha_inicio <= $hoy)
                ?? $bimestres->first(fn ($b) => $b->fecha_inicio > $hoy)
                ?? $bimestres->last();

            if ($bimestre) {
                $bimestre->dias_para_cierre = $bimestre->estado === 'ABIERTO'
                    ? (int) today()->diffInDays(Carbon::parse($bimestre->fecha_cierre_notas), false)
                    : null;
            }
        }

        return response()->json(['periodo' => $periodo, 'bimestre' => $bimestre], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
