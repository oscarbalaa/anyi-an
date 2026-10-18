<?php

namespace App\Servicios\Asistencia;

use App\Exceptions\ErrorRegla;
use App\Models\Usuario;
use App\Support\Calendario;
use App\Support\PerfilUsuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** RF-36 (mi asistencia) e indicadores por aula para el administrador. Solo cuentan los registros definitivos. */
class ReportesAsistencia
{
    public function __construct(private readonly Pendientes $pendientes) {}

    /** RN-27: % de asistencia = (clases − faltas) / clases. Tardanzas y justificadas no restan. */
    public static function porcentaje(int $clases, int $faltas): ?float
    {
        return $clases ? round(100 * ($clases - $faltas) / $clases, 1) : null;
    }

    public function delEstudiante(Usuario $usuario, ?int $bimestreId): array
    {
        $periodo = Calendario::periodoEnCurso();
        $matricula = $periodo ? DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')
            ->where('e.usuario_id', $usuario->id)->where('m.periodo_id', $periodo->id)->where('m.estado', '!=', 'ANULADA')
            ->first(['m.id', 'm.aula_id']) : null;
        $vacio = ['periodo' => $periodo?->nombre, 'aula' => null, 'bimestre' => null, 'bimestres' => [], 'resumen' => null, 'por_curso' => [], 'por_mes' => [], 'registros' => []];
        if (! $matricula) {
            return $vacio;
        }

        $bimestres = Calendario::bimestres($periodo->id);
        if ($bimestres->isEmpty()) {
            return ['aula' => null] + $vacio;
        }
        $bimestre = $this->bimestre($bimestres, $bimestreId);
        $filas = DB::table('asistencia as a')
            ->join('sesion_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->join('curso_aula as c', 'c.id', '=', 's.curso_aula_id')
            ->join('asignatura as asig', 'asig.id', '=', 'c.asignatura_id')
            ->where('a.matricula_id', $matricula->id)->where('s.estado', 'DEFINITIVO')->where('s.subperiodo_id', $bimestre->id)
            ->orderByDesc('s.fecha')->orderBy('asig.nombre')
            ->get(['s.fecha', 's.curso_aula_id', 'asig.nombre as asignatura', 'a.estado', 'a.observacion']);

        $horas = DB::table('horario')->whereIn('curso_aula_id', $filas->pluck('curso_aula_id')->unique())
            ->selectRaw('curso_aula_id, dia_semana, MIN(hora_inicio) as hora')->groupBy('curso_aula_id', 'dia_semana')->get()
            ->mapWithKeys(fn ($h) => ["{$h->curso_aula_id}-{$h->dia_semana}" => substr($h->hora, 0, 5)]);
        $aula = DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('aula.id', $matricula->aula_id)->first(['grado.numero', 'grado.nivel', 'seccion.nombre as seccion']);
        $cursos = DB::table('curso_aula')->join('asignatura', 'asignatura.id', '=', 'curso_aula.asignatura_id')
            ->where('curso_aula.aula_id', $matricula->aula_id)->orderBy('asignatura.nombre')->pluck('asignatura.nombre', 'curso_aula.id');

        return [
            'periodo' => $periodo->nombre,
            'aula' => PerfilUsuario::nombreAula($aula->numero, $aula->nivel, $aula->seccion),
            'bimestre' => $this->datosBimestre($bimestre),
            'bimestres' => $bimestres->map(fn ($b) => $this->datosBimestre($b))->all(),
            'meta' => (int) DB::table('institucion')->value('umbral_asistencia_medio'),
            'resumen' => $this->conteo($filas),
            'por_curso' => $cursos->map(fn ($nombre, $id) => ['curso_id' => $id, 'asignatura' => $nombre] + $this->conteo($filas->where('curso_aula_id', $id)))
                ->values()->all(),
            'por_mes' => $filas->groupBy(fn ($f) => substr($f->fecha, 0, 7))->sortKeys()
                ->map(fn ($g, $mes) => ['mes' => $mes] + $this->conteo($g))->values()->all(),
            'registros' => $filas->map(fn ($f) => [
                'fecha' => $f->fecha,
                'curso' => $f->asignatura,
                'hora' => $horas[$f->curso_aula_id.'-'.Carbon::parse($f->fecha)->dayOfWeekIso] ?? null,
                'estado' => $f->estado,
                'observacion' => $f->observacion,
            ])->all(),
        ];
    }

    /** Indicadores del bimestre: % por aula y docentes con clases sin registrar */
    public function resumenAdmin(?int $bimestreId): array
    {
        $periodo = Calendario::periodoEnCurso();
        if (! $periodo) {
            return ['bimestre' => null, 'bimestres' => [], 'aulas' => [], 'sin_registro' => []];
        }
        $bimestres = Calendario::bimestres($periodo->id);
        if ($bimestres->isEmpty()) {
            return ['bimestre' => null, 'bimestres' => [], 'aulas' => [], 'sin_registro' => []];
        }
        $bimestre = $this->bimestre($bimestres, $bimestreId);

        $totales = DB::table('asistencia as a')
            ->join('sesion_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->join('curso_aula as c', 'c.id', '=', 's.curso_aula_id')
            ->where('s.subperiodo_id', $bimestre->id)->where('s.estado', 'DEFINITIVO')
            ->groupBy('c.aula_id')
            ->selectRaw("c.aula_id, COUNT(*) as registros, COUNT(DISTINCT s.id) as clases, SUM(a.estado = 'F') as faltas, SUM(a.estado = 'T') as tardanzas, SUM(a.estado = 'J') as justificadas")
            ->get()->keyBy('aula_id');
        $matriculados = DB::table('matricula')->where('periodo_id', $periodo->id)->where('estado', 'ACTIVA')
            ->selectRaw('aula_id, COUNT(*) as total')->groupBy('aula_id')->pluck('total', 'aula_id');

        $aulas = DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('aula.periodo_id', $periodo->id)->orderBy('grado.orden')->orderBy('seccion.nombre')
            ->get(['aula.id', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion'])
            ->map(function ($a) use ($totales, $matriculados) {
                $t = $totales->get($a->id);

                return [
                    'id' => $a->id,
                    'nombre' => PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion),
                    'estudiantes' => (int) ($matriculados[$a->id] ?? 0),
                    'clases' => (int) ($t->clases ?? 0),
                    'faltas' => (int) ($t->faltas ?? 0),
                    'tardanzas' => (int) ($t->tardanzas ?? 0),
                    'justificadas' => (int) ($t->justificadas ?? 0),
                    'porcentaje' => self::porcentaje((int) ($t->registros ?? 0), (int) ($t->faltas ?? 0)),
                ];
            });

        return [
            'bimestre' => $this->datosBimestre($bimestre),
            'bimestres' => $bimestres->map(fn ($b) => $this->datosBimestre($b))->all(),
            'meta' => (int) DB::table('institucion')->value('umbral_asistencia_medio'),
            'aulas' => $aulas->all(),
            'sin_registro' => $this->sinRegistro($periodo->id, $bimestre),
        ];
    }

    /** Docentes con clases pasadas sin asistencia definitiva, del que más debe al que menos */
    private function sinRegistro(int $periodoId, object $bimestre): array
    {
        $cursos = collect((new Asistencias)->cursosDelPeriodo($periodoId))->flatMap(fn ($a) => $a['cursos'])->keyBy('id');
        $pendientes = $this->pendientes->porCurso($cursos->keys()->all(), $bimestre);
        $docentes = DB::table('curso_aula')->whereIn('id', array_keys($pendientes))->pluck('docente_id', 'id');

        return collect($pendientes)
            ->map(fn ($fechas, $cursoId) => ['docente_id' => $docentes[$cursoId], 'curso' => $cursos[$cursoId], 'fechas' => $fechas])
            ->groupBy('docente_id')
            ->map(fn ($g) => [
                'docente_id' => $g[0]['docente_id'],
                'docente' => $g[0]['curso']['docente'],
                'total' => $g->sum(fn ($x) => count($x['fechas'])),
                'desde' => $g->map(fn ($x) => $x['fechas'][0])->min(),
                'cursos' => $g->map(fn ($x) => ['id' => $x['curso']['id'], 'nombre' => $x['curso']['nombre'], 'fechas' => $x['fechas']])->values()->all(),
            ])
            ->sortByDesc('total')->values()->all();
    }

    private function conteo($filas): array
    {
        $porEstado = $filas->countBy('estado');
        $clases = $filas->count();

        return [
            'clases' => $clases,
            'presentes' => (int) ($porEstado['P'] ?? 0),
            'faltas' => (int) ($porEstado['F'] ?? 0),
            'tardanzas' => (int) ($porEstado['T'] ?? 0),
            'justificadas' => (int) ($porEstado['J'] ?? 0),
            'porcentaje' => self::porcentaje($clases, (int) ($porEstado['F'] ?? 0)),
        ];
    }

    private function bimestre($bimestres, ?int $bimestreId): object
    {
        $bimestre = $bimestreId ? $bimestres->firstWhere('id', $bimestreId) : Calendario::bimestreVigente($bimestres);

        return $bimestre ?? throw new ErrorRegla('Elige un bimestre del periodo en curso.', 'bimestre_id');
    }

    private function datosBimestre(object $b): array
    {
        return ['id' => $b->id, 'nombre' => $b->nombre, 'estado' => $b->estado, 'fecha_inicio' => $b->fecha_inicio, 'fecha_fin' => $b->fecha_fin];
    }
}
