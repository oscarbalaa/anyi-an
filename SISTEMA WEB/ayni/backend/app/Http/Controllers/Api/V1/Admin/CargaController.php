<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Estructura\BloqueRequest;
use App\Http\Requests\Admin\Estructura\CursoRequest;
use App\Servicios\Estructura\Horarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** CU-08: carga académica y horario semanal de un aula */
class CargaController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Horarios $horarios) {}

    public function show(int $aula): JsonResponse
    {
        return $this->json($this->horarios->carga($aula));
    }

    public function asignar(CursoRequest $request): JsonResponse
    {
        $id = $this->horarios->asignarCurso($request->validated());

        return $this->json(['mensaje' => 'Curso asignado.', 'id' => $id], 201);
    }

    public function cambiarDocente(CursoRequest $request, int $curso): JsonResponse
    {
        $this->horarios->cambiarDocente($curso, (int) $request->validated()['docente_id']);

        return $this->json(['mensaje' => 'Docente del curso actualizado.']);
    }

    public function quitar(int $curso): JsonResponse
    {
        $this->horarios->quitarCurso($curso);

        return $this->json(['mensaje' => 'Curso quitado del aula.']);
    }

    public function crearBloque(BloqueRequest $request): JsonResponse
    {
        $id = $this->horarios->guardarBloque($request->validated());

        return $this->json(['mensaje' => 'Clase agregada al horario.', 'id' => $id], 201);
    }

    public function actualizarBloque(BloqueRequest $request, int $bloque): JsonResponse
    {
        abort_unless(DB::table('horario')->where('id', $bloque)->exists(), 404);
        $this->horarios->guardarBloque($request->validated(), $bloque);

        return $this->json(['mensaje' => 'Horario actualizado.']);
    }

    public function quitarBloque(int $bloque): JsonResponse
    {
        $this->horarios->quitarBloque($bloque);

        return $this->json(['mensaje' => 'Clase quitada del horario.']);
    }
}
