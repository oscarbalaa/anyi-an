<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Servicios\Reportes\Reportes;
use App\Support\Excel;
use App\Support\MiMatricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** CU-28: boleta en PDF y reportes en Excel (RF-62 a RF-64) */
class ReporteController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly Reportes $reportes) {}

    public function opciones(Request $request): JsonResponse
    {
        return $this->json($this->reportes->opciones($request->user()->load('rol')));
    }

    /** La administración, de cualquier matrícula */
    public function boleta(int $matricula): Response
    {
        return $this->pdf(...$this->reportes->boleta($matricula));
    }

    /** El estudiante, solo la suya (por su sesión) */
    public function miBoleta(Request $request): Response
    {
        $m = MiMatricula::de($request->user()) ?? abort(404, 'No tienes una matrícula en el periodo en curso.');

        return $this->pdf(...$this->reportes->boleta($m->id));
    }

    public function asistencia(Request $request): BinaryFileResponse
    {
        $f = $this->filtros($request);

        return $this->excel(...$this->reportes->asistencia($f['aula_id'], $f['bimestre_id'], $request->user()->load('rol')));
    }

    public function consolidado(Request $request): BinaryFileResponse
    {
        $f = $this->filtros($request);

        return $this->excel(...$this->reportes->consolidado($f['aula_id'], $f['bimestre_id'], $request->user()->load('rol')));
    }

    private function filtros(Request $request): array
    {
        $f = $request->validate([
            'aula_id' => ['required', 'integer'],
            'bimestre_id' => ['nullable', 'integer'],
        ], ['aula_id.*' => 'Elige el aula.', 'bimestre_id.*' => 'Elige un bimestre del periodo del aula.']);

        return ['aula_id' => (int) $f['aula_id'], 'bimestre_id' => isset($f['bimestre_id']) ? (int) $f['bimestre_id'] : null];
    }

    private function pdf(string $contenido, string $nombre): Response
    {
        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function excel(string $ruta, string $nombre): BinaryFileResponse
    {
        return response()->download($ruta, $nombre, ['Content-Type' => Excel::TIPO])->deleteFileAfterSend();
    }
}
