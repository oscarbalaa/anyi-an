<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Servicios\Riesgo\AlertasAdministrativas;
use App\Servicios\Riesgo\Paneles;
use App\Servicios\Riesgo\Riesgo;
use App\Support\Calendario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** CU-26: panel de riesgo (administrador) y de coordinación (docente coordinador), con "Recalcular" */
class PanelRiesgoController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Paneles $paneles) {}

    public function show(Request $request): JsonResponse
    {
        $f = $request->validate([
            'nivel' => ['nullable', Rule::in(['PRIMARIA', 'SECUNDARIA'])],
            'aula_id' => ['nullable', 'integer'],
            'bimestre_id' => ['nullable', 'integer'],
        ], ['*' => 'El filtro no es válido.']);

        return $this->json($this->paneles->panel($request->user()->load('rol'), $f));
    }

    public function recalcular(Request $request, Riesgo $riesgo, AlertasAdministrativas $administrativas): JsonResponse
    {
        $usuario = $request->user()->load('rol');
        $periodo = Calendario::periodoEnCurso();
        $aulas = $periodo ? $this->paneles->aulas($usuario, $periodo->id, []) : collect();
        abort_if($usuario->rol->codigo !== 'ADMIN' && $aulas->isEmpty(), 403);

        $matriculas = $usuario->rol->codigo === 'ADMIN' ? null : DB::table('matricula')->whereIn('aula_id', $aulas)->where('estado', 'ACTIVA')->pluck('id')->all();
        $r = $riesgo->recalcular($matriculas);
        if ($usuario->rol->codigo === 'ADMIN') {
            $administrativas->sincronizar();
        }
        $nuevas = $r['alertas'] === 1 ? '1 alerta nueva' : "{$r['alertas']} alertas nuevas";

        return $this->json([
            'mensaje' => $r['evaluados']
                ? "Riesgo recalculado para {$r['evaluados']} estudiantes: {$r['ALTO']} en riesgo alto y {$r['MEDIO']} en riesgo medio ({$nuevas})."
                : 'No hay estudiantes con matrícula activa para calcular.',
            'resultado' => $r,
        ]);
    }
}
