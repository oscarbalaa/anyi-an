<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Servicios\Notificaciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CU-23: cada usuario ve y marca solo sus notificaciones */
class NotificacionController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Notificaciones $notificaciones) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'filtro' => ['nullable', Rule::in(['todas', 'pendientes', 'leidas'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ], ['filtro.*' => 'El filtro no es válido.', 'pagina.*' => 'La página no es válida.']);

        return $this->json($this->notificaciones->listar($request->user()->load('rol'), $f['filtro'] ?? 'todas', (int) ($f['pagina'] ?? 1)));
    }

    public function resumen(Request $request): JsonResponse
    {
        return $this->json($this->notificaciones->resumen($request->user()->load('rol')));
    }

    public function leer(Request $request, int $notificacion): JsonResponse
    {
        $this->notificaciones->leer($notificacion, $request->user());

        return $this->json(['mensaje' => 'Notificación marcada como leída.']);
    }

    public function leerTodas(Request $request): JsonResponse
    {
        $n = $this->notificaciones->leerTodas($request->user());

        return $this->json(['mensaje' => $n ? 'Todas las notificaciones quedaron como leídas.' : 'No tenías notificaciones sin leer.']);
    }
}
