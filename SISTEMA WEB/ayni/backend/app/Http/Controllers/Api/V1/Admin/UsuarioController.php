<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ErrorAcceso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UsuarioRequest;
use App\Models\Usuario;
use App\Servicios\ContrasenaTemporal;
use App\Servicios\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CU-04: gestionar usuarios (solo administración) */
class UsuarioController extends Controller
{
    public function __construct(private readonly Usuarios $usuarios) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', Rule::in(array_keys(Usuarios::ROLES))],
            'estado' => ['nullable', Rule::in(['ACTIVO', 'INACTIVO', 'BLOQUEADO'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ], ['rol.in' => 'El rol no es válido.', 'estado.in' => 'El estado no es válido.']);

        return $this->json($this->usuarios->listar($filtros));
    }

    public function show(Usuario $usuario): JsonResponse
    {
        return $this->json(['usuario' => $this->usuarios->detalle($usuario->id)]);
    }

    public function store(UsuarioRequest $request): JsonResponse
    {
        $r = $this->usuarios->crearAdministrativo($request->validated(), $request->user()->id);

        return $this->json(['mensaje' => 'Cuenta creada.'] + $r, 201);
    }

    public function update(UsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        return $this->json([
            'mensaje' => 'Cambios guardados.',
            'usuario' => $this->usuarios->actualizar($usuario, $request->validated(), $request->user()->id),
        ]);
    }

    public function estado(Request $request, Usuario $usuario): JsonResponse
    {
        $request->validate(['estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])]], ['estado.*' => 'El estado debe ser ACTIVO o INACTIVO.']);
        $estado = $request->input('estado');

        return $this->json([
            'mensaje' => $estado === 'ACTIVO' ? 'Cuenta activada.' : 'Cuenta desactivada. Sus sesiones se cerraron.',
            'usuario' => $this->usuarios->cambiarEstado($usuario->load('rol'), $estado, $request->user()->id),
        ]);
    }

    /** RF-13: desbloquear y generar contraseña temporal */
    public function contrasenaTemporal(Request $request, Usuario $usuario, ContrasenaTemporal $temporal): JsonResponse
    {
        if ($usuario->id === $request->user()->id) {
            throw new ErrorAcceso('Para tu propia cuenta usa "Cambiar contraseña" en Mi perfil.', 422);
        }
        $r = $temporal->asignar($usuario, $request->user()->id);

        return $this->json([
            'mensaje' => $r['correo_enviado'] ? 'Contraseña temporal generada y enviada por correo.' : 'Contraseña temporal generada. Entrégala al usuario.',
            'usuario' => $this->usuarios->detalle($usuario->id),
        ] + $r);
    }

    private function json(array $datos, int $estado = 200): JsonResponse
    {
        return response()->json($datos, $estado, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
