<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Servicios\Estructura\CierreBimestres;
use App\Support\Calendario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class CalendarioController extends Controller
{
    /** Periodo en curso y el bimestre de hoy (o el próximo, si hoy cae en vacaciones) */
    public function actual(CierreBimestres $cierre): JsonResponse
    {
        // Cada pantalla pide este dato: aquí se revisa el cierre automático (RN-14)
        $cierre->revisar();

        $periodo = Calendario::periodoEnCurso();
        $bimestre = $periodo ? Calendario::bimestreVigente(Calendario::bimestres($periodo->id)) : null;
        if ($bimestre) {
            $bimestre->dias_para_cierre = $bimestre->estado === 'ABIERTO'
                ? (int) today()->diffInDays(Carbon::parse($bimestre->fecha_cierre_notas), false)
                : null;
        }

        return response()->json(['periodo' => $periodo, 'bimestre' => $bimestre], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
