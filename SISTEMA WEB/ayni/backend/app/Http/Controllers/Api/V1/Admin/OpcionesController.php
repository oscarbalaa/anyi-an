<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Support\PerfilUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Listas para los desplegables de la estructura académica */
class OpcionesController extends Controller
{
    use RespondeJson;

    public function __invoke(Request $request): JsonResponse
    {
        $periodoId = (int) $request->query('periodo_id') ?: DB::table('periodo_academico')->where('estado', 'EN_CURSO')->value('id');

        $aulas = $periodoId ? DB::table('aula')
            ->join('grado', 'grado.id', '=', 'aula.grado_id')
            ->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('aula.periodo_id', $periodoId)
            ->orderBy('grado.orden')->orderBy('seccion.nombre')
            ->get(['aula.id', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion'])
            ->map(fn ($a) => ['id' => $a->id, 'nombre' => PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion), 'nivel' => $a->nivel]) : [];

        return $this->json([
            'periodo_id' => $periodoId,
            'periodos' => DB::table('periodo_academico')->orderByDesc('fecha_inicio')->get(['id', 'nombre', 'estado']),
            'grados' => DB::table('grado')->orderBy('orden')->get(['id', 'numero', 'nivel'])
                ->map(fn ($g) => ['id' => $g->id, 'nivel' => $g->nivel, 'nombre' => "{$g->numero}.º ".mb_strtolower($g->nivel === 'PRIMARIA' ? 'Primaria' : 'Secundaria')]),
            'docentes' => DB::table('docente')->join('persona', 'persona.id', '=', 'docente.persona_id')
                ->where('docente.estado', 'ACTIVO')->orderBy('persona.apellidos')->orderBy('persona.nombres')
                ->get(['docente.id', 'persona.nombres', 'persona.apellidos', 'docente.especialidad'])
                ->map(fn ($d) => ['id' => $d->id, 'nombre' => "{$d->apellidos}, {$d->nombres}", 'especialidad' => $d->especialidad]),
            'asignaturas' => DB::table('asignatura')->where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'horas_semanales']),
            'aulas' => $aulas,
        ]);
    }
}
