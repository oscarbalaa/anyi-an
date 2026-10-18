<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Personas\ApoderadoRequest;
use App\Http\Requests\Admin\Personas\EstudianteRequest;
use App\Http\Requests\Admin\Personas\ImportacionRequest;
use App\Servicios\Personas\Estudiantes;
use App\Servicios\Personas\ImportacionEstudiantes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** CU-10 y CU-11: estudiantes, apoderados e importación desde Excel */
class EstudianteController extends Controller
{
    use RespondeJson;

    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(private readonly Estudiantes $estudiantes, private readonly ImportacionEstudiantes $importacion) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(Estudiantes::ESTADOS)],
            'aula' => ['nullable', 'regex:/^(sin|\d+)$/D'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ], ['estado.*' => 'El estado no es válido.', 'aula.*' => 'El aula no es válida.']);

        return $this->json($this->estudiantes->listar($f));
    }

    public function show(int $estudiante): JsonResponse
    {
        return $this->json(['estudiante' => $this->estudiantes->detalle($estudiante)]);
    }

    public function store(EstudianteRequest $request): JsonResponse
    {
        $r = $this->estudiantes->crear($request->validated(), $request->user()->id);

        return $this->json(['mensaje' => "Estudiante registrado con el código {$r['estudiante']['codigo']}."] + $r, 201);
    }

    public function update(EstudianteRequest $request, int $estudiante): JsonResponse
    {
        return $this->json([
            'mensaje' => 'Datos del estudiante guardados.',
            'estudiante' => $this->estudiantes->actualizar($estudiante, $request->validated(), $request->user()->id),
        ]);
    }

    public function crearApoderado(ApoderadoRequest $request, int $estudiante): JsonResponse
    {
        abort_unless(DB::table('estudiante')->where('id', $estudiante)->exists(), 404);
        $this->estudiantes->guardarApoderado($estudiante, $request->validated());

        return $this->json(['mensaje' => 'Apoderado agregado.'], 201);
    }

    public function actualizarApoderado(ApoderadoRequest $request, int $apoderado): JsonResponse
    {
        $estudiante = DB::table('apoderado')->where('id', $apoderado)->value('estudiante_id') ?? abort(404);
        $this->estudiantes->guardarApoderado($estudiante, $request->validated(), $apoderado);

        return $this->json(['mensaje' => 'Apoderado actualizado.']);
    }

    public function eliminarApoderado(int $apoderado): JsonResponse
    {
        $this->estudiantes->eliminarApoderado($apoderado);

        return $this->json(['mensaje' => 'Apoderado quitado.']);
    }

    public function plantilla(): BinaryFileResponse
    {
        return response()->download($this->importacion->plantilla(), 'plantilla_estudiantes.xlsx', ['Content-Type' => self::XLSX])->deleteFileAfterSend();
    }

    /** Paso 3: revisión sin guardar nada */
    public function revisar(ImportacionRequest $request): JsonResponse
    {
        $r = $this->importacion->revisar($request->file('archivo')->getRealPath(), $request->boolean('matricular'));

        return $this->json([
            'filas' => $r['filas'],
            'validas' => count($r['validas']),
            'periodo' => $r['periodo'],
            'errores' => array_map(fn ($e) => ['fila' => $e['fila'], 'mensajes' => $e['mensajes']], $r['errores']),
        ]);
    }

    public function importar(ImportacionRequest $request): JsonResponse
    {
        $r = $this->importacion->importar(
            $request->file('archivo')->getRealPath(),
            $request->boolean('matricular'),
            $request->boolean('crear_usuario'),
            $request->user()->id,
        );

        $mensaje = $r['importados'] === 1 ? 'Se importó 1 estudiante.' : "Se importaron {$r['importados']} estudiantes.";

        return $this->json(['mensaje' => $mensaje] + $r);
    }

    public function errores(ImportacionRequest $request): BinaryFileResponse
    {
        $ruta = $this->importacion->archivoDeErrores($request->file('archivo')->getRealPath(), $request->boolean('matricular'));

        return response()->download($ruta, 'filas_con_error.xlsx', ['Content-Type' => self::XLSX])->deleteFileAfterSend();
    }
}
