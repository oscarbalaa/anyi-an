<?php

namespace App\Servicios\Ficha;

use App\Models\Usuario;
use App\Servicios\Asistencia\ReportesAsistencia;
use App\Servicios\Evaluacion\Calificaciones;
use App\Support\Calendario;
use App\Support\Cursos;
use Illuminate\Support\Facades\DB;

/** RF-61: los estudiantes de los cursos del docente con su promedio, asistencia y nivel de riesgo */
class MisEstudiantes
{
    public function listar(Usuario $usuario, ?int $cursoId): array
    {
        $periodo = Calendario::periodoEnCurso();
        $docente = Cursos::docenteDe($usuario);
        $cursos = $periodo && $docente ? Cursos::consulta()->where('curso_aula.docente_id', $docente)->where('aula.periodo_id', $periodo->id)->get() : collect();
        $vacio = ['cursos' => [], 'curso' => null, 'bimestre' => null, 'estudiantes' => [], 'total' => 0];
        if ($cursos->isEmpty()) {
            return $vacio;
        }
        $curso = $cursos->firstWhere('id', $cursoId) ?? $cursos->first();
        $bimestre = Calendario::bimestreVigente(Calendario::bimestres($periodo->id));
        $total = DB::table('matricula')->whereIn('aula_id', $cursos->pluck('aula_id')->unique())->where('estado', 'ACTIVA')->count();

        $alumnos = DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->where('m.aula_id', $curso->aula_id)->where('m.estado', 'ACTIVA')->orderBy('p.apellidos')->orderBy('p.nombres')
            ->get(['m.id', 'e.codigo', 'p.nombres', 'p.apellidos']);
        $ids = $alumnos->pluck('id')->all();
        $promedios = $bimestre ? DB::table('calificacion_periodo')->where('curso_aula_id', $curso->id)->where('subperiodo_id', $bimestre->id)->whereIn('matricula_id', $ids)->pluck('promedio', 'matricula_id') : collect();
        $asistencia = $bimestre ? DB::table('asistencia as a')->join('sesion_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->where('s.curso_aula_id', $curso->id)->where('s.subperiodo_id', $bimestre->id)->where('s.estado', 'DEFINITIVO')->whereIn('a.matricula_id', $ids)
            ->groupBy('a.matricula_id')->selectRaw("a.matricula_id, COUNT(*) as clases, SUM(a.estado = 'F') as faltas")->get()->keyBy('matricula_id') : collect();
        $riesgo = $bimestre ? DB::table('prediccion_riesgo')->whereIn('id', DB::table('prediccion_riesgo')->where('subperiodo_id', $bimestre->id)->whereIn('matricula_id', $ids)->selectRaw('MAX(id)')->groupBy('matricula_id'))
            ->pluck('nivel_riesgo', 'matricula_id') : collect();

        return [
            'cursos' => $cursos->map(fn ($c) => Cursos::datos($c))->values()->all(),
            'curso' => Cursos::datos($curso),
            'bimestre' => $bimestre ? ['id' => $bimestre->id, 'nombre' => $bimestre->nombre] : null,
            'total' => $total,
            'estudiantes' => $alumnos->map(function ($a) use ($promedios, $asistencia, $riesgo) {
                $x = $asistencia->get($a->id);

                return [
                    'matricula_id' => $a->id,
                    'nombre' => "{$a->apellidos}, {$a->nombres}",
                    'codigo' => $a->codigo,
                    'promedio' => Calificaciones::numero($promedios[$a->id] ?? null),
                    'asistencia' => $x ? ReportesAsistencia::porcentaje((int) $x->clases, (int) $x->faltas) : null,
                    'nivel' => $riesgo[$a->id] ?? null,
                ];
            })->all(),
        ];
    }
}
