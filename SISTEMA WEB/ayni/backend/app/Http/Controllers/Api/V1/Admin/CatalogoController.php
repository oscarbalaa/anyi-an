<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ErrorRegla;
use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Estructura\AsignaturaRequest;
use App\Http\Requests\Admin\Estructura\TipoEvaluacionRequest;
use App\Support\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** CU-07: asignaturas (RF-21) y tipos de evaluación (RF-22) */
class CatalogoController extends Controller
{
    use RespondeJson;

    public function asignaturas(): JsonResponse
    {
        $uso = DB::table('curso_aula')->selectRaw('asignatura_id, COUNT(*) as total')->groupBy('asignatura_id')->pluck('total', 'asignatura_id');

        return $this->json(['asignaturas' => DB::table('asignatura')->orderBy('nombre')->get()
            ->map(fn ($a) => (array) $a + ['cursos' => (int) ($uso[$a->id] ?? 0)])]);
    }

    public function crearAsignatura(AsignaturaRequest $request): JsonResponse
    {
        $id = DB::table('asignatura')->insertGetId($request->validated());
        Auditoria::registrar('CREAR', 'asignatura', $id, $request->validated());

        return $this->json(['mensaje' => 'Asignatura creada.', 'id' => $id], 201);
    }

    public function actualizarAsignatura(AsignaturaRequest $request, int $asignatura): JsonResponse
    {
        abort_unless(DB::table('asignatura')->where('id', $asignatura)->exists(), 404);
        DB::table('asignatura')->where('id', $asignatura)->update($request->validated());
        Auditoria::registrar('ACTUALIZAR', 'asignatura', $asignatura, $request->validated());

        return $this->json(['mensaje' => 'Asignatura actualizada.']);
    }

    public function eliminarAsignatura(int $asignatura): JsonResponse
    {
        abort_unless(DB::table('asignatura')->where('id', $asignatura)->exists(), 404);
        if (DB::table('curso_aula')->where('asignatura_id', $asignatura)->exists()) {
            throw new ErrorRegla('La asignatura se dicta en algún aula: no se puede eliminar. Puedes inactivarla.');
        }
        DB::table('asignatura')->where('id', $asignatura)->delete();
        Auditoria::registrar('ELIMINAR', 'asignatura', $asignatura);

        return $this->json(['mensaje' => 'Asignatura eliminada.']);
    }

    public function tipos(): JsonResponse
    {
        $uso = DB::table('evaluacion')->selectRaw('tipo_evaluacion_id, COUNT(*) as total')->groupBy('tipo_evaluacion_id')->pluck('total', 'tipo_evaluacion_id');

        return $this->json(['tipos' => DB::table('tipo_evaluacion')->orderBy('nombre')->get()
            ->map(fn ($t) => (array) $t + ['evaluaciones' => (int) ($uso[$t->id] ?? 0)])]);
    }

    public function crearTipo(TipoEvaluacionRequest $request): JsonResponse
    {
        $id = DB::table('tipo_evaluacion')->insertGetId($request->validated());
        Auditoria::registrar('CREAR', 'tipo_evaluacion', $id, $request->validated());

        return $this->json(['mensaje' => 'Tipo de evaluación creado.', 'id' => $id], 201);
    }

    public function actualizarTipo(TipoEvaluacionRequest $request, int $tipo): JsonResponse
    {
        abort_unless(DB::table('tipo_evaluacion')->where('id', $tipo)->exists(), 404);
        DB::table('tipo_evaluacion')->where('id', $tipo)->update($request->validated());
        Auditoria::registrar('ACTUALIZAR', 'tipo_evaluacion', $tipo, $request->validated());

        return $this->json(['mensaje' => 'Tipo de evaluación actualizado.']);
    }

    public function eliminarTipo(int $tipo): JsonResponse
    {
        abort_unless(DB::table('tipo_evaluacion')->where('id', $tipo)->exists(), 404);
        if (DB::table('evaluacion')->where('tipo_evaluacion_id', $tipo)->exists()) {
            throw new ErrorRegla('Hay evaluaciones de este tipo: no se puede eliminar. Puedes inactivarlo.');
        }
        DB::table('tipo_evaluacion')->where('id', $tipo)->delete();
        Auditoria::registrar('ELIMINAR', 'tipo_evaluacion', $tipo);

        return $this->json(['mensaje' => 'Tipo de evaluación eliminado.']);
    }
}
