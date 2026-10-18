<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Estructura\BimestreRequest;
use App\Http\Requests\Admin\Estructura\PeriodoRequest;
use App\Servicios\Estructura\Periodos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** CU-06: periodos y bimestres */
class PeriodoController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Periodos $periodos) {}

    public function index(): JsonResponse
    {
        return $this->json(['periodos' => $this->periodos->listar()]);
    }

    public function store(PeriodoRequest $request): JsonResponse
    {
        $id = $this->periodos->crear($request->validated());

        return $this->json(['mensaje' => 'Periodo creado. Ahora define sus bimestres.', 'id' => $id], 201);
    }

    public function update(PeriodoRequest $request, int $periodo): JsonResponse
    {
        $this->periodos->actualizar($periodo, $request->validated());

        return $this->json(['mensaje' => 'Periodo actualizado.']);
    }

    public function estado(Request $request, int $periodo): JsonResponse
    {
        $estado = $request->validate(['estado' => ['required', Rule::in(['EN_CURSO', 'CERRADO'])]], ['estado.*' => 'El estado debe ser EN_CURSO o CERRADO.'])['estado'];
        $this->periodos->cambiarEstado($periodo, $estado, $request->user()->id);

        return $this->json(['mensaje' => $estado === 'EN_CURSO' ? 'El periodo está en curso.' : 'Periodo cerrado. Sus bimestres abiertos también se cerraron.']);
    }

    public function destroy(int $periodo): JsonResponse
    {
        $this->periodos->eliminar($periodo);

        return $this->json(['mensaje' => 'Periodo eliminado.']);
    }

    public function guardarBimestre(BimestreRequest $request, int $periodo): JsonResponse
    {
        $id = $this->periodos->guardarBimestre($periodo, $request->validated());

        return $this->json(['mensaje' => 'Bimestre guardado.', 'id' => $id], 201);
    }

    public function actualizarBimestre(BimestreRequest $request, int $bimestre): JsonResponse
    {
        $periodo = DB::table('subperiodo')->where('id', $bimestre)->value('periodo_id') ?? abort(404);
        $this->periodos->guardarBimestre($periodo, $request->validated(), $bimestre);

        return $this->json(['mensaje' => 'Bimestre guardado.']);
    }

    public function cerrarBimestre(Request $request, int $bimestre): JsonResponse
    {
        $this->periodos->cerrarBimestre($bimestre, $request->user()->id);

        return $this->json(['mensaje' => 'Bimestre cerrado.']);
    }

    public function eliminarBimestre(int $bimestre): JsonResponse
    {
        $this->periodos->eliminarBimestre($bimestre);

        return $this->json(['mensaje' => 'Bimestre eliminado.']);
    }
}
