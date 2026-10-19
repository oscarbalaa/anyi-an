<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Riesgo\EstadoAlertaRequest;
use App\Http\Requests\Riesgo\IntervencionRequest;
use App\Http\Requests\Riesgo\ReasignarAlertaRequest;
use App\Servicios\Riesgo\Alertas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CU-25: lo usan el docente (alertas de sus aulas) y el administrador (todas) */
class AlertaController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Alertas $alertas) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'nivel' => ['nullable', Rule::in(['ALTO', 'MEDIO', 'BAJO'])],
            'estado' => ['nullable', Rule::in(['abiertas', 'todas', ...Alertas::ESTADOS])],
            'encargado' => ['nullable', Rule::in(['mias', 'todas'])],
            'aula_id' => ['nullable', 'integer'],
            'bimestre_id' => ['nullable', 'integer'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ], ['*' => 'El filtro no es válido.']);

        return $this->json($this->alertas->listar($request->user()->load('rol'), $f));
    }

    public function show(Request $request, int $alerta): JsonResponse
    {
        return $this->json($this->alertas->detalle($alerta, $request->user()->load('rol')));
    }

    public function estado(EstadoAlertaRequest $request, int $alerta): JsonResponse
    {
        $d = $request->validated();

        return $this->json($this->alertas->cambiarEstado($alerta, $d['estado'], $d['justificacion'] ?? null, $request->user()->load('rol')));
    }

    public function intervencion(IntervencionRequest $request, int $alerta): JsonResponse
    {
        return $this->json($this->alertas->registrarIntervencion($alerta, $request->validated(), $request->user()->load('rol')), 201);
    }

    public function responsable(ReasignarAlertaRequest $request, int $alerta): JsonResponse
    {
        $d = $request->validated();

        return $this->json($this->alertas->reasignar($alerta, (int) $d['docente_id'], $d['justificacion'], $request->user()->load('rol')));
    }
}
