<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Servicios\Riesgo\AlertasAdministrativas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** RF-56: alertas de la operación del colegio */
class AlertaAdministrativaController extends Controller
{
    use RespondeJson;

    public function index(Request $request, AlertasAdministrativas $alertas): JsonResponse
    {
        $f = $request->validate([
            'tipo' => ['nullable', Rule::in(array_keys(AlertasAdministrativas::TIPOS))],
            'estado' => ['nullable', Rule::in(['ABIERTA', 'RESUELTA', 'todas'])],
        ], ['*' => 'El filtro no es válido.']);

        return $this->json($alertas->listar($f));
    }
}
