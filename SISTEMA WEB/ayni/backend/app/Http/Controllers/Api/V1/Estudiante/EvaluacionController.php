<?php

namespace App\Http\Controllers\Api\V1\Estudiante;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluacion\ArchivoRequest;
use App\Servicios\Estructura\MiHorario;
use App\Servicios\Evaluacion\Entregas;
use App\Servicios\Evaluacion\MisNotas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-17 y RF-47, RF-48: mis notas, mis actividades y mi horario */
class EvaluacionController extends Controller
{
    use RespondeJson;

    public function notas(Request $request, MisNotas $notas): JsonResponse
    {
        return $this->json($notas->resumen($request->user(), $this->bimestre($request)));
    }

    public function curso(Request $request, MisNotas $notas, int $curso): JsonResponse
    {
        return $this->json($notas->curso($curso, $request->user(), $this->bimestre($request)));
    }

    public function actividades(Request $request, Entregas $entregas): JsonResponse
    {
        return $this->json($entregas->actividades($request->user(), $this->bimestre($request)));
    }

    public function actividad(Request $request, Entregas $entregas, int $evaluacion): JsonResponse
    {
        return $this->json($entregas->detalle($evaluacion, $request->user()));
    }

    public function entregar(ArchivoRequest $request, Entregas $entregas, int $evaluacion): JsonResponse
    {
        return $this->json($entregas->guardar($evaluacion, $request->user(), $request->file('archivo'), $request->input('accion')));
    }

    public function horario(Request $request, MiHorario $horario): JsonResponse
    {
        return $this->json($horario->de($request->user()));
    }

    private function bimestre(Request $request): ?int
    {
        $f = $request->validate(['bimestre_id' => ['nullable', 'integer']], ['bimestre_id.*' => 'Elige un bimestre válido.']);

        return isset($f['bimestre_id']) ? (int) $f['bimestre_id'] : null;
    }
}
