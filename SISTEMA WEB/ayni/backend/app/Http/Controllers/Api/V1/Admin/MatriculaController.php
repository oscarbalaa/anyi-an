<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Servicios\Personas\Matriculas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CU-12: matrículas */
class MatriculaController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Matriculas $matriculas) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'periodo_id' => ['required', 'integer', 'exists:periodo_academico,id'],
            'aula_id' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::in(['ACTIVA', 'RETIRADA', 'ANULADA'])],
            'buscar' => ['nullable', 'string', 'max:100'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ], ['periodo_id.*' => 'Elige un periodo válido.', 'estado.*' => 'El estado no es válido.']);

        return $this->json($this->matriculas->listar($f) + ['aulas' => $this->matriculas->aulas((int) $f['periodo_id'])]);
    }

    /** Estudiantes que se pueden matricular en el periodo */
    public function candidatos(Request $request): JsonResponse
    {
        $f = $request->validate([
            'periodo_id' => ['required', 'integer', 'exists:periodo_academico,id'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->json(['estudiantes' => $this->matriculas->candidatos((int) $f['periodo_id'], $f['buscar'] ?? '')]);
    }

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate([
            'estudiante_id' => ['required', 'integer', 'exists:estudiante,id'],
            'aula_id' => ['required', 'integer', 'exists:aula,id'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ], [
            'estudiante_id.*' => 'Elige un estudiante.',
            'aula_id.*' => 'Elige un aula.',
            'fecha.*' => 'Ingresa una fecha de matrícula válida.',
        ]);
        $id = $this->matriculas->matricular((int) $d['estudiante_id'], (int) $d['aula_id'], $d['fecha'], $request->user()->id);

        return $this->json(['mensaje' => 'Matrícula registrada.', 'id' => $id], 201);
    }

    public function retirar(Request $request, int $matricula): JsonResponse
    {
        $this->matriculas->cerrar($matricula, 'RETIRADA', $this->motivo($request));

        return $this->json(['mensaje' => 'Matrícula retirada.']);
    }

    public function anular(Request $request, int $matricula): JsonResponse
    {
        $this->matriculas->cerrar($matricula, 'ANULADA', $this->motivo($request));

        return $this->json(['mensaje' => 'Matrícula anulada.']);
    }

    private function motivo(Request $request): string
    {
        $motivo = trim(preg_replace('/\s+/u', ' ', (string) $request->input('motivo')));
        $request->merge(['motivo' => $motivo]);
        $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:300']], ['motivo.*' => 'Explica el motivo en 10 a 300 caracteres.']);

        return $motivo;
    }
}
