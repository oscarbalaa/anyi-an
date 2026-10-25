<?php

namespace App\Servicios\Ficha;

use App\Models\Usuario;
use App\Servicios\Asistencia\ReportesAsistencia;
use App\Servicios\Riesgo\MotorReglas;
use App\Support\Calendario;
use App\Support\Cursos;
use App\Support\PerfilUsuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RF-61: los estudiantes del docente con su promedio, asistencia y nivel de riesgo.
 * Por curso (sus notas y sus clases) o, si es tutor, toda su aula (todos los cursos, como el riesgo).
 */
class MisEstudiantes
{
    public function listar(Usuario $usuario, ?int $cursoId, bool $tutoria = false): array
    {
        $periodo = Calendario::periodoEnCurso();
        $docente = Cursos::docenteDe($usuario);
        $cursos = $periodo && $docente ? Cursos::consulta()->where('curso_aula.docente_id', $docente)->where('aula.periodo_id', $periodo->id)->get() : collect();
        $aulaTutor = $periodo && $docente ? DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('aula.tutor_docente_id', $docente)->where('aula.periodo_id', $periodo->id)
            ->first(['aula.id', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion']) : null;
        $datosTutoria = $aulaTutor ? ['aula_id' => $aulaTutor->id, 'aula' => PerfilUsuario::nombreAula($aulaTutor->numero, $aulaTutor->nivel, $aulaTutor->seccion)] : null;
        if ($cursos->isEmpty() && ! $aulaTutor) {
            return ['cursos' => [], 'curso' => null, 'tutoria' => null, 'modo' => 'CURSO', 'bimestre' => null, 'estudiantes' => [], 'total' => 0];
        }

        $modo = $aulaTutor && ($tutoria || $cursos->isEmpty()) ? 'TUTORIA' : 'CURSO';
        $curso = $modo === 'CURSO' ? ($cursos->firstWhere('id', $cursoId) ?? $cursos->first()) : null;
        $aulaId = $curso->aula_id ?? $aulaTutor->id;
        $bimestre = Calendario::bimestreVigente(Calendario::bimestres($periodo->id));
        $aulas = $cursos->pluck('aula_id')->push($aulaTutor?->id)->filter()->unique();
        $total = DB::table('matricula')->whereIn('aula_id', $aulas)->where('estado', 'ACTIVA')->count();

        $alumnos = DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->where('m.aula_id', $aulaId)->where('m.estado', 'ACTIVA')->orderBy('p.apellidos')->orderBy('p.nombres')
            ->get(['m.id', 'e.codigo', 'p.nombres', 'p.apellidos']);
        $ids = $alumnos->pluck('id')->all();
        // Del curso elegido o, en la tutoría, de todos los cursos del aula
        $cursosDelAula = $curso ? [$curso->id] : DB::table('curso_aula')->where('aula_id', $aulaId)->pluck('id')->all();
        $promedios = $bimestre ? DB::table('calificacion_periodo')->whereIn('curso_aula_id', $cursosDelAula)->where('subperiodo_id', $bimestre->id)
            ->whereIn('matricula_id', $ids)->whereNotNull('promedio')->groupBy('matricula_id')
            ->selectRaw('matricula_id, AVG(promedio) as promedio')->pluck('promedio', 'matricula_id') : collect();
        $asistencia = $bimestre ? DB::table('asistencia as a')->join('sesion_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->whereIn('s.curso_aula_id', $cursosDelAula)->where('s.subperiodo_id', $bimestre->id)->where('s.estado', 'DEFINITIVO')->whereIn('a.matricula_id', $ids)
            ->groupBy('a.matricula_id')->selectRaw("a.matricula_id, COUNT(*) as clases, SUM(a.estado = 'F') as faltas")->get()->keyBy('matricula_id') : collect();
        [$riesgo, $motivos] = $bimestre ? $this->riesgo($ids, $bimestre->id) : [collect(), collect()];

        return [
            'cursos' => $cursos->map(fn ($c) => Cursos::datos($c))->values()->all(),
            'curso' => $curso ? Cursos::datos($curso) : null,
            'tutoria' => $datosTutoria,
            'modo' => $modo,
            'bimestre' => $bimestre ? ['id' => $bimestre->id, 'nombre' => $bimestre->nombre] : null,
            'total' => $total,
            'estudiantes' => $alumnos->map(function ($a) use ($promedios, $asistencia, $riesgo, $motivos) {
                $x = $asistencia->get($a->id);
                $promedio = $promedios[$a->id] ?? null;

                return [
                    'matricula_id' => $a->id,
                    'nombre' => "{$a->apellidos}, {$a->nombres}",
                    'codigo' => $a->codigo,
                    'promedio' => $promedio === null ? null : round((float) $promedio, 1, PHP_ROUND_HALF_UP),
                    'asistencia' => $x ? ReportesAsistencia::porcentaje((int) $x->clases, (int) $x->faltas) : null,
                    'nivel' => $riesgo[$a->id] ?? null,
                    // Por qué tiene ese nivel: las reglas que se activaron (con la asistencia y el promedio de todos los cursos)
                    'motivos' => $motivos[$a->id] ?? [],
                ];
            })->all(),
        ];
    }

    /** Último nivel de riesgo del bimestre y la explicación de cada estudiante */
    private function riesgo(array $ids, int $bimestreId): array
    {
        $ultimas = DB::table('prediccion_riesgo')->whereIn('id', DB::table('prediccion_riesgo')->where('subperiodo_id', $bimestreId)->whereIn('matricula_id', $ids)->selectRaw('MAX(id)')->groupBy('matricula_id'))
            ->get(['id', 'matricula_id', 'nivel_riesgo']);
        $factores = DB::table('factor_riesgo')->whereIn('prediccion_id', $ultimas->pluck('id'))->orderByDesc('contribucion')->orderBy('id')
            ->get(['prediccion_id', 'variable', 'descripcion'])->groupBy('prediccion_id');

        return [
            $ultimas->pluck('nivel_riesgo', 'matricula_id'),
            $ultimas->mapWithKeys(fn ($p) => [$p->matricula_id => ($factores[$p->id] ?? new Collection)
                ->map(fn ($f) => ['titulo' => MotorReglas::TITULOS[$f->variable] ?? $f->variable, 'descripcion' => $f->descripcion])->values()->all()]),
        ];
    }
}
