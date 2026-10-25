<?php

namespace App\Servicios\Inicio;

use App\Models\Usuario;
use App\Servicios\Asistencia\Pendientes;
use App\Servicios\Asistencia\ReportesAsistencia;
use App\Servicios\Estructura\MiHorario;
use App\Servicios\Evaluacion\Entregas;
use App\Servicios\Evaluacion\MisNotas;
use App\Servicios\Riesgo\AlertasAdministrativas;
use App\Servicios\Riesgo\Paneles;
use App\Support\AulasDelUsuario;
use App\Support\Calendario;
use App\Support\Cursos;
use App\Support\MiMatricula;
use App\Support\PerfilUsuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** CU-27: el inicio de cada rol (RF-59) */
class Inicio
{
    public function __construct(
        private readonly AlertasAdministrativas $administrativas,
        private readonly Pendientes $pendientes,
        private readonly Paneles $paneles,
        private readonly MisNotas $notas,
        private readonly Entregas $entregas,
        private readonly MiHorario $horario,
        private readonly ReportesAsistencia $asistencia,
    ) {}

    public function admin(Usuario $usuario): array
    {
        [$periodo, $bimestre] = $this->calendario();
        $this->administrativas->sincronizar();
        $abiertas = DB::table('alerta_administrativa')->where('estado', 'ABIERTA');

        return $this->saludo($usuario, $periodo, $bimestre) + [
            'kpis' => [
                'estudiantes' => $periodo ? DB::table('matricula')->where('periodo_id', $periodo->id)->where('estado', 'ACTIVA')->count() : 0,
                'docentes' => DB::table('docente')->where('estado', 'ACTIVO')->count(),
                'coordinadores' => $periodo ? DB::table('coordinacion')->where('periodo_id', $periodo->id)->distinct()->count('docente_id') : 0,
                'alertas_estudiantiles' => DB::table('alerta_estudiantil')->whereIn('estado', ['NUEVA', 'EN_SEGUIMIENTO'])->count(),
                'alertas_administrativas' => (clone $abiertas)->count(),
            ],
            'atencion' => collect($this->administrativas->listar(['estado' => 'ABIERTA'])['datos'])->take(5)
                ->map(fn ($a) => array_intersect_key($a, array_flip(['id', 'tipo', 'severidad', 'titulo', 'detalle', 'enlace'])))->values()->all(),
            'avance' => $periodo && $bimestre ? $this->avance($periodo->id, $bimestre) : null,
        ];
    }

    public function docente(Usuario $usuario): array
    {
        [$periodo, $bimestre] = $this->calendario();
        $docente = Cursos::docenteDe($usuario);
        $vacio = $this->saludo($usuario, $periodo, $bimestre) + ['tareas' => [], 'coordinacion' => null, 'hoy' => []];
        if (! $periodo || ! $docente) {
            return $vacio;
        }
        $cursos = Cursos::consulta()->where('curso_aula.docente_id', $docente)->where('aula.periodo_id', $periodo->id)->get()->keyBy('id');
        $tareas = [];

        // Asistencia: clases pasadas sin registrar y las de hoy
        if ($bimestre && $bimestre->estado === 'ABIERTO') {
            foreach ($this->pendientes->porCurso($cursos->keys()->all(), $bimestre) as $cursoId => $fechas) {
                $n = count($fechas);
                $tareas[] = [
                    'tipo' => 'asistencia',
                    'titulo' => 'Registrar asistencia',
                    'detalle' => Cursos::datos($cursos[$cursoId])['nombre'],
                    'extra' => $n === 1 ? '1 clase sin registrar' : "{$n} clases sin registrar",
                    'enlace' => '/docente/asistencia?curso='.$cursoId.'&fecha='.end($fechas),
                ];
            }
        }
        $hoy = $this->clasesDeHoy($cursos->keys()->all(), $cursos);
        foreach ($hoy as $c) {
            if (! $c['registrada'] && $bimestre && $bimestre->estado === 'ABIERTO') {
                $tareas[] = ['tipo' => 'asistencia', 'titulo' => 'Registrar asistencia', 'detalle' => $c['curso'], 'extra' => "Hoy, {$c['inicio']}", 'enlace' => "/docente/asistencia?curso={$c['curso_id']}&fecha=".today()->toDateString()];
            }
        }

        // Evaluaciones vencidas con estudiantes sin nota
        if ($bimestre && $bimestre->estado === 'ABIERTO') {
            $sinNota = DB::table('evaluacion as ev')->join('curso_aula as c', 'c.id', '=', 'ev.curso_aula_id')
                ->join('matricula as m', fn ($j) => $j->on('m.aula_id', '=', 'c.aula_id')->where('m.estado', 'ACTIVA'))
                ->leftJoin('nota_evaluacion as n', fn ($j) => $j->on('n.evaluacion_id', '=', 'ev.id')->on('n.matricula_id', '=', 'm.id'))
                ->where('c.docente_id', $docente)->where('ev.subperiodo_id', $bimestre->id)->where('ev.estado', 'PUBLICADA')->where('ev.fecha_limite', '<', today()->toDateString())
                ->whereNull('n.nota')->groupBy('ev.id', 'ev.titulo', 'ev.curso_aula_id', 'ev.fecha_limite')
                ->orderBy('ev.fecha_limite')->limit(5)
                ->get(['ev.id', 'ev.titulo', 'ev.curso_aula_id', DB::raw('COUNT(*) as sin_nota')]);
            foreach ($sinNota as $ev) {
                $tareas[] = [
                    'tipo' => 'calificar',
                    'titulo' => "Calificar {$ev->titulo}",
                    'detalle' => Cursos::datos($cursos[$ev->curso_aula_id])['nombre'],
                    'extra' => "{$ev->sin_nota} sin nota",
                    'enlace' => "/docente/notas?evaluacion={$ev->id}",
                ];
            }
        }

        // Alertas a su cargo
        $alertas = DB::table('alerta_estudiantil as al')->join('prediccion_riesgo as p', 'p.id', '=', 'al.prediccion_id')
            ->join('matricula as m', 'm.id', '=', 'al.matricula_id')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as pe', 'pe.id', '=', 'e.persona_id')
            ->join('aula as a', 'a.id', '=', 'm.aula_id')->join('grado as g', 'g.id', '=', 'a.grado_id')->join('seccion as s', 's.id', '=', 'a.seccion_id')
            ->where('al.responsable_docente_id', $docente)->whereIn('al.estado', ['NUEVA', 'EN_SEGUIMIENTO'])
            ->orderByRaw("FIELD(p.nivel_riesgo, 'ALTO', 'MEDIO', 'BAJO')")->limit(5)
            ->get(['al.id', 'al.estado', 'p.nivel_riesgo', 'pe.nombres', 'pe.apellidos', 'g.numero', 'g.nivel', 's.nombre as seccion']);
        foreach ($alertas as $a) {
            $tareas[] = [
                'tipo' => 'seguimiento',
                'titulo' => "Seguimiento de {$a->nombres} {$a->apellidos}",
                'detalle' => PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion),
                'extra' => 'Riesgo '.mb_strtolower($a->nivel_riesgo).' · '.($a->estado === 'NUEVA' ? 'nueva' : 'en seguimiento'),
                'enlace' => "/docente/alertas?id={$a->id}",
            ];
        }

        $panel = AulasDelUsuario::coordinadas($docente, $periodo->id)->isNotEmpty() ? $this->paneles->panel($usuario, []) : null;

        return ['tareas' => $tareas, 'hoy' => $hoy, 'coordinacion' => $panel && $panel['kpis'] ? [
            'ambito' => $panel['ambito'],
            'ALTO' => $panel['kpis']['ALTO'],
            'MEDIO' => $panel['kpis']['MEDIO'],
            'BAJO' => $panel['kpis']['BAJO'],
            'destacado' => $panel['atencion'][0] ?? null,
        ] : null] + $vacio;
    }

    public function estudiante(Usuario $usuario): array
    {
        [$periodo, $bimestre] = $this->calendario();
        $m = MiMatricula::de($usuario);
        $base = $this->saludo($usuario, $periodo, $bimestre);
        if (! $m || ! $bimestre) {
            return $base + ['aula' => null, 'tutor' => null, 'coordinador' => null, 'promedio' => null, 'cursos' => [], 'asistencia' => null, 'pendientes' => 0, 'por_entregar' => [], 'hoy' => []];
        }
        $resumen = $this->notas->resumen($usuario, null);
        $actividades = $this->entregas->actividades($usuario, null);
        $tutor = DB::table('aula')->join('docente as d', 'd.id', '=', 'aula.tutor_docente_id')->join('persona as p', 'p.id', '=', 'd.persona_id')
            ->where('aula.id', $m->aula_id)->first(['p.nombres', 'p.apellidos']);
        $porEntregar = collect($actividades['actividades'])->filter(fn ($a) => in_array($a['estado'], ['PENDIENTE', 'EN_PROGRESO'], true))
            ->sortBy('fecha_limite')->take(5)->map(fn ($a) => ['id' => $a['id'], 'titulo' => $a['titulo'], 'curso' => $a['curso'], 'fecha_limite' => $a['fecha_limite']])->values();
        $dia = today()->dayOfWeekIso;

        return $base + [
            'aula' => MiMatricula::nombreAula($m),
            'tutor' => $tutor ? Cursos::nombreCorto($tutor->nombres, $tutor->apellidos) : null,
            'coordinador' => AulasDelUsuario::nombreCoordinador($m->aula_id),
            'promedio' => $resumen['promedio'],
            'promedio_bimestre' => $resumen['bimestre']['nombre'] ?? null,
            'cursos' => collect($resumen['cursos'])->map(fn ($c) => ['id' => $c['id'], 'asignatura' => $c['asignatura'], 'promedio' => $c['promedio']])->all(),
            'asistencia' => $this->asistencia->delEstudiante($usuario, null)['resumen']['porcentaje'] ?? null,
            'pendientes' => $actividades['resumen']['pendientes'] ?? 0,
            'por_entregar' => $porEntregar->all(),
            'hoy' => collect($this->horario->de($usuario)['clases'])->where('dia', $dia)->values()->all(),
        ];
    }

    /** Avance de registro del bimestre: asistencia, notas de lo ya vencido y promedios publicados */
    public function avance(int $periodoId, object $bimestre): array
    {
        $cursos = DB::table('curso_aula')->join('aula', 'aula.id', '=', 'curso_aula.aula_id')->where('aula.periodo_id', $periodoId)->pluck('curso_aula.id')->all();
        $sinRegistrar = array_sum(array_map('count', $this->pendientes->porCurso($cursos, $bimestre)));
        $registradas = DB::table('sesion_clase')->whereIn('curso_aula_id', $cursos)->where('subperiodo_id', $bimestre->id)->where('estado', 'DEFINITIVO')
            ->where('fecha', '<', today()->toDateString())->count();

        $esperadas = DB::table('evaluacion as ev')->join('curso_aula as c', 'c.id', '=', 'ev.curso_aula_id')
            ->join('matricula as m', fn ($j) => $j->on('m.aula_id', '=', 'c.aula_id')->where('m.estado', 'ACTIVA'))
            ->where('ev.subperiodo_id', $bimestre->id)->where('ev.estado', 'PUBLICADA')->where('ev.fecha_limite', '<', today()->toDateString());
        $puestas = (clone $esperadas)->join('nota_evaluacion as n', fn ($j) => $j->on('n.evaluacion_id', '=', 'ev.id')->on('n.matricula_id', '=', 'm.id'))->whereNotNull('n.nota')->count();
        $esperadas = $esperadas->count();

        $publicados = DB::table('calificacion_periodo')->whereIn('curso_aula_id', $cursos)->where('subperiodo_id', $bimestre->id)->where('estado', '!=', 'BORRADOR')
            ->distinct()->count('curso_aula_id');

        $pct = fn (int $hechas, int $total) => $total ? (int) round(100 * $hechas / $total) : null;

        return [
            'asistencia' => ['hechas' => $registradas, 'total' => $registradas + $sinRegistrar, 'porcentaje' => $pct($registradas, $registradas + $sinRegistrar)],
            'notas' => ['hechas' => $puestas, 'total' => $esperadas, 'porcentaje' => $pct($puestas, $esperadas)],
            'promedios' => ['hechas' => $publicados, 'total' => count($cursos), 'porcentaje' => $pct($publicados, count($cursos))],
        ];
    }

    /** Clases del docente hoy, con si ya tienen asistencia definitiva */
    private function clasesDeHoy(array $cursoIds, $cursos): array
    {
        if (! $cursoIds) {
            return [];
        }
        $registradas = DB::table('sesion_clase')->whereIn('curso_aula_id', $cursoIds)->where('fecha', today()->toDateString())->where('estado', 'DEFINITIVO')->pluck('curso_aula_id')->flip();

        return DB::table('horario as h')->join('curso_aula as c', 'c.id', '=', 'h.curso_aula_id')->join('aula as a', 'a.id', '=', 'c.aula_id')
            ->whereIn('h.curso_aula_id', $cursoIds)->where('h.dia_semana', today()->dayOfWeekIso)->orderBy('h.hora_inicio')
            ->get(['h.curso_aula_id', 'h.hora_inicio', 'h.hora_fin', 'h.ambiente', 'a.ambiente as ambiente_aula'])
            ->map(fn ($h) => [
                'curso_id' => $h->curso_aula_id,
                'curso' => Cursos::datos($cursos[$h->curso_aula_id])['nombre'],
                'inicio' => substr($h->hora_inicio, 0, 5),
                'fin' => substr($h->hora_fin, 0, 5),
                'ambiente' => $h->ambiente ?? $h->ambiente_aula,
                'registrada' => $registradas->has($h->curso_aula_id),
            ])->all();
    }

    private function calendario(): array
    {
        $periodo = Calendario::periodoEnCurso();
        $bimestre = $periodo ? Calendario::bimestreVigente(Calendario::bimestres($periodo->id)) : null;

        return [$periodo, $bimestre];
    }

    private function saludo(Usuario $usuario, ?object $periodo, ?object $bimestre): array
    {
        $perfil = PerfilUsuario::de($usuario);

        return [
            'nombre' => explode(' ', $perfil['nombre'])[0],
            'detalle' => $perfil['detalle'],
            'fecha' => today()->toDateString(),
            'periodo' => $periodo?->nombre,
            'bimestre' => $bimestre ? [
                'id' => $bimestre->id,
                'nombre' => $bimestre->nombre,
                'estado' => $bimestre->estado,
                'fecha_cierre_notas' => $bimestre->fecha_cierre_notas,
                'dias_para_cierre' => (int) today()->diffInDays(Carbon::parse($bimestre->fecha_cierre_notas), false),
            ] : null,
        ];
    }
}
