<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Personas\DocenteRequest;
use App\Servicios\Personas\Docentes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CU-09: gestionar docentes */
class DocenteController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Docentes $docentes) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['ACTIVO', 'INACTIVO', 'LICENCIA'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ], ['estado.*' => 'El estado no es válido.']);

        return $this->json($this->docentes->listar($f));
    }

    public function show(int $docente): JsonResponse
    {
        return $this->json(['docente' => $this->docentes->detalle($docente)]);
    }

    public function store(DocenteRequest $request): JsonResponse
    {
        $r = $this->docentes->crear($request->validated(), $request->user()->id);

        return $this->json(['mensaje' => 'Docente registrado. Su usuario es su DNI.'] + $r, 201);
    }

    public function update(DocenteRequest $request, int $docente): JsonResponse
    {
        return $this->json([
            'mensaje' => 'Datos del docente guardados.',
            'docente' => $this->docentes->actualizar($docente, $request->validated(), $request->user()->id),
        ]);
    }
}
