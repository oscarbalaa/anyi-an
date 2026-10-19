<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Servicios\Ficha\Ficha360;
use App\Servicios\Ficha\MisEstudiantes;
use App\Servicios\Inicio\Inicio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-27: inicio de cada rol, ficha 360° y "Mis estudiantes" (RF-59 a RF-61) */
class InicioController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Inicio $inicio) {}

    public function admin(Request $request): JsonResponse
    {
        return $this->json($this->inicio->admin($request->user()->load('rol')));
    }

    public function docente(Request $request): JsonResponse
    {
        return $this->json($this->inicio->docente($request->user()->load('rol')));
    }

    public function estudiante(Request $request): JsonResponse
    {
        return $this->json($this->inicio->estudiante($request->user()->load('rol')));
    }

    public function ficha(Request $request, Ficha360 $ficha, int $matricula): JsonResponse
    {
        return $this->json($ficha->detalle($matricula, $request->user()->load('rol')));
    }

    public function misEstudiantes(Request $request, MisEstudiantes $estudiantes): JsonResponse
    {
        $f = $request->validate(['curso_id' => ['nullable', 'integer']], ['*' => 'El curso no es válido.']);

        return $this->json($estudiantes->listar($request->user()->load('rol'), isset($f['curso_id']) ? (int) $f['curso_id'] : null));
    }
}
