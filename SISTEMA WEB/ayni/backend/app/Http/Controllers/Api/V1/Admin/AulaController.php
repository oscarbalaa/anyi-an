<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Estructura\AulaRequest;
use App\Http\Requests\Admin\Estructura\CoordinacionRequest;
use App\Servicios\Estructura\Aulas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CU-07 y CU-08: aulas y coordinadores */
class AulaController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Aulas $aulas) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'periodo_id' => ['required', 'integer', 'exists:periodo_academico,id'],
            'nivel' => ['nullable', Rule::in(array_keys(Aulas::NIVELES))],
        ], ['periodo_id.*' => 'Elige un periodo válido.', 'nivel.*' => 'El nivel es primaria o secundaria.']);

        return $this->json([
            'aulas' => $this->aulas->listar((int) $filtros['periodo_id'], $filtros['nivel'] ?? null),
            'coordinaciones' => $this->aulas->coordinaciones((int) $filtros['periodo_id']),
        ]);
    }

    public function store(AulaRequest $request): JsonResponse
    {
        $id = $this->aulas->crear($request->validated());

        return $this->json(['mensaje' => 'Aula creada.', 'id' => $id], 201);
    }

    public function update(AulaRequest $request, int $aula): JsonResponse
    {
        $this->aulas->actualizar($aula, $request->validated());

        return $this->json(['mensaje' => 'Aula actualizada.']);
    }

    public function destroy(int $aula): JsonResponse
    {
        $this->aulas->eliminar($aula);

        return $this->json(['mensaje' => 'Aula eliminada.']);
    }

    public function crearCoordinacion(CoordinacionRequest $request): JsonResponse
    {
        $id = $this->aulas->crearCoordinacion($request->validated());

        return $this->json(['mensaje' => 'Coordinador asignado.', 'id' => $id], 201);
    }

    public function eliminarCoordinacion(int $coordinacion): JsonResponse
    {
        $this->aulas->eliminarCoordinacion($coordinacion);

        return $this->json(['mensaje' => 'Coordinador quitado.']);
    }
}
