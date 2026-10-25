<?php

namespace App\Http\Controllers\Api\V1\Docente;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluacion\ArchivoRequest;
use App\Http\Requests\Evaluacion\EvaluacionRequest;
use App\Http\Requests\Evaluacion\NotasRequest;
use App\Servicios\Evaluacion\Calificaciones;
use App\Servicios\Evaluacion\Evaluaciones;
use App\Servicios\Evaluacion\Notas;
use App\Support\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** CU-16, CU-18 y CU-19: evaluaciones, notas y calificaciones de los cursos del docente */
class EvaluacionController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Evaluaciones $evaluaciones) {}

    public function index(Request $request, int $curso): JsonResponse
    {
        return $this->json($this->evaluaciones->listar($curso, $this->bimestre($request), $request->user()->load('rol')));
    }

    public function store(EvaluacionRequest $request, int $curso): JsonResponse
    {
        $id = $this->evaluaciones->guardar($request->validated(), $request->user()->load('rol'), $curso);

        return $this->json(['mensaje' => 'Evaluación creada.', 'id' => $id], 201);
    }

    public function update(EvaluacionRequest $request, int $evaluacion): JsonResponse
    {
        $this->evaluaciones->guardar($request->validated(), $request->user()->load('rol'), null, $evaluacion);

        return $this->json(['mensaje' => 'Evaluación actualizada.']);
    }

    public function destroy(Request $request, int $evaluacion): JsonResponse
    {
        $this->evaluaciones->eliminar($evaluacion, $request->user()->load('rol'));

        return $this->json(['mensaje' => 'Evaluación eliminada.']);
    }

    public function subirMaterial(ArchivoRequest $request, int $evaluacion): JsonResponse
    {
        $id = $this->evaluaciones->subirMaterial($evaluacion, $request->file('archivo'), $request->user()->load('rol'));

        return $this->json(['mensaje' => 'Material de apoyo agregado.', 'id' => $id], 201);
    }

    public function quitarMaterial(Request $request, int $evaluacion, int $archivo): JsonResponse
    {
        $this->evaluaciones->quitarMaterial($evaluacion, $archivo, $request->user()->load('rol'));

        return $this->json(['mensaje' => 'Material de apoyo quitado.']);
    }

    public function notas(Request $request, Notas $notas, int $evaluacion): JsonResponse
    {
        return $this->json($notas->planilla($evaluacion, $request->user()->load('rol')));
    }

    public function guardarNotas(NotasRequest $request, Notas $notas, int $evaluacion): JsonResponse
    {
        return $this->json($notas->guardar($evaluacion, $request->registros(), $request->user()->load('rol')));
    }

    public function excelNotas(Request $request, Notas $notas, int $evaluacion): BinaryFileResponse
    {
        [$ruta, $nombre] = $notas->excel($evaluacion, $request->user()->load('rol'));

        return response()->download($ruta, $nombre, ['Content-Type' => Excel::TIPO])->deleteFileAfterSend();
    }

    public function calificaciones(Request $request, Calificaciones $calificaciones, int $curso): JsonResponse
    {
        return $this->json($calificaciones->vista($curso, $this->bimestre($request), $request->user()->load('rol')));
    }

    public function publicar(Request $request, Calificaciones $calificaciones, int $curso): JsonResponse
    {
        $d = $request->validate(['bimestre_id' => ['required', 'integer']], ['bimestre_id.*' => 'Elige el bimestre.']);

        return $this->json($calificaciones->publicar($curso, (int) $d['bimestre_id'], $request->user()->load('rol')));
    }

    public function excelCalificaciones(Request $request, Calificaciones $calificaciones, int $curso): BinaryFileResponse
    {
        $v = $calificaciones->vista($curso, $this->bimestre($request), $request->user()->load('rol'));
        $filas = [['Estudiante', ...array_map(fn ($e) => "{$e['titulo']} (sobre ".Calificaciones::texto($e['puntaje_maximo']).", peso {$e['peso']})", $v['evaluaciones']), 'Promedio (0-20)', 'Literal']];
        foreach ($v['estudiantes'] as $e) {
            $filas[] = [$e['nombre'], ...array_map(fn ($ev) => $e['notas'][$ev['id']], $v['evaluaciones']), $e['promedio'], $e['literal']];
        }
        $nombre = Excel::nombreArchivo("calificaciones {$v['curso']['nombre']} {$v['bimestre']['nombre']}");

        return response()->download(Excel::escribir($filas, [34, ...array_fill(0, count($v['evaluaciones']), 16), 12, 10]), $nombre, ['Content-Type' => Excel::TIPO])
            ->deleteFileAfterSend();
    }

    private function bimestre(Request $request): ?int
    {
        $f = $request->validate(['bimestre_id' => ['nullable', 'integer']], ['bimestre_id.*' => 'Elige un bimestre válido.']);

        return isset($f['bimestre_id']) ? (int) $f['bimestre_id'] : null;
    }
}
