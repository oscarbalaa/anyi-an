<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Servicios\Asistencia\Asistencias;
use App\Servicios\Asistencia\ReportesAsistencia;
use App\Support\Calendario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-14: indicadores, cursos para corregir y borrado de una clase que no se dictó */
class AsistenciaController extends Controller
{
    use RespondeJson;

    public function resumen(Request $request, ReportesAsistencia $reportes): JsonResponse
    {
        $f = $request->validate(['bimestre_id' => ['nullable', 'integer']], ['bimestre_id.*' => 'Elige un bimestre válido.']);

        return $this->json($reportes->resumenAdmin(isset($f['bimestre_id']) ? (int) $f['bimestre_id'] : null));
    }

    public function cursos(Asistencias $asistencias): JsonResponse
    {
        $periodo = Calendario::periodoEnCurso();

        return $this->json(['periodo' => $periodo?->nombre, 'aulas' => $periodo ? $asistencias->cursosDelPeriodo($periodo->id) : []]);
    }

    public function eliminar(Request $request, Asistencias $asistencias, int $curso, string $fecha): JsonResponse
    {
        $motivo = trim(preg_replace('/\s+/u', ' ', (string) $request->input('motivo')));
        $request->merge(['motivo' => $motivo]);
        $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:300']], ['motivo.*' => 'Explica el motivo en 10 a 300 caracteres.']);
        $asistencias->eliminar($curso, $fecha, $motivo, $request->user()->load('rol'));

        return $this->json(['mensaje' => 'Registro de la clase eliminado.']);
    }
}
