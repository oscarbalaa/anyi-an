<?php

namespace App\Http\Controllers\Api\V1\Estudiante;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Servicios\Asistencia\ReportesAsistencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-15: mi asistencia */
class AsistenciaController extends Controller
{
    use RespondeJson;

    public function __invoke(Request $request, ReportesAsistencia $reportes): JsonResponse
    {
        $f = $request->validate(['bimestre_id' => ['nullable', 'integer']], ['bimestre_id.*' => 'Elige un bimestre válido.']);

        return $this->json($reportes->delEstudiante($request->user(), isset($f['bimestre_id']) ? (int) $f['bimestre_id'] : null));
    }
}
