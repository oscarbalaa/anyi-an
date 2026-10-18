<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\AsistenciaRequest;
use App\Servicios\Asistencia\Asistencias;
use App\Servicios\Asistencia\Pendientes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-13 y CU-14: lo usan el docente (sus cursos) y el administrador (todos) */
class AsistenciaController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Asistencias $asistencias) {}

    public function misCursos(Request $request, Pendientes $pendientes): JsonResponse
    {
        return $this->json($this->asistencias->cursosDelDocente($request->user()->load('rol'), $pendientes));
    }

    public function fechas(Request $request, int $curso): JsonResponse
    {
        $f = $request->validate(['bimestre_id' => ['nullable', 'integer']], ['bimestre_id.*' => 'Elige un bimestre válido.']);

        return $this->json($this->asistencias->fechas($curso, $request->user()->load('rol'), isset($f['bimestre_id']) ? (int) $f['bimestre_id'] : null));
    }

    public function show(Request $request, int $curso, string $fecha): JsonResponse
    {
        return $this->json($this->asistencias->detalle($curso, $fecha, $request->user()->load('rol')));
    }

    public function guardar(AsistenciaRequest $request, int $curso, string $fecha): JsonResponse
    {
        return $this->json($this->asistencias->guardar($curso, $fecha, $request->validated(), $request->user()->load('rol')));
    }
}
