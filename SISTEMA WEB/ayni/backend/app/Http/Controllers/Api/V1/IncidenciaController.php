<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incidencias\IncidenciaRequest;
use App\Servicios\Incidencias\Incidencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CU-22: lo usan el docente (sus estudiantes) y el administrador (todos) */
class IncidenciaController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Incidencias $incidencias) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'tipo_id' => ['nullable', 'integer'],
            'gravedad' => ['nullable', Rule::in(Incidencias::GRAVEDADES)],
            'aula_id' => ['nullable', 'integer'],
            'matricula_id' => ['nullable', 'integer'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ], ['gravedad.*' => 'La gravedad no es válida.', '*' => 'El filtro no es válido.']);

        return $this->json($this->incidencias->listar($request->user()->load('rol'), $f));
    }

    public function estudiantes(Request $request): JsonResponse
    {
        $f = $request->validate(['buscar' => ['nullable', 'string', 'max:100']]);

        return $this->json(['estudiantes' => $this->incidencias->estudiantes($request->user()->load('rol'), $f['buscar'] ?? '')]);
    }

    public function store(IncidenciaRequest $request): JsonResponse
    {
        $id = $this->incidencias->registrar($request->validated(), $request->user()->load('rol'));

        return $this->json(['mensaje' => 'Incidencia registrada.', 'id' => $id], 201);
    }
}
