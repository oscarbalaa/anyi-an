<?php

namespace App\Servicios\Ficha;

use App\Models\Usuario;
use App\Servicios\Asistencia\ReportesAsistencia;
use App\Servicios\Evaluacion\Calificaciones;
use App\Servicios\Riesgo\MotorReglas;
use App\Support\AulasDelUsuario;
use App\Support\Calendario;
use App\Support\Cursos;
use App\Support\PerfilUsuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** CU-27: ficha del estudiante 360° (RF-60). La ven la administración y los docentes de su aula */
class Ficha360
{
    public function detalle(int $matriculaId, Usuario $usuario): array
    {
        $m = DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->join('aula as a', 'a.id', '=', 'm.aula_id')->join('grado as g', 'g.id', '=', 'a.grado_id')->join('seccion as s', 's.id', '=', 'a.seccion_id')
            ->leftJoin('docente as td', 'td.id', '=', 'a.tutor_docente_id')->leftJoin('persona as tp', 'tp.id', '=', 'td.persona_id')
            ->where('m.id', $matriculaId)
            ->first(['m.id', 'm.estudiante_id', 'm.aula_id', 'm.periodo_id', 'm.estado', 'e.codigo', 'p.nombres', 'p.apellidos', 'p.fecha_nacimiento',
                'g.numero', 'g.nivel', 's.nombre as seccion', 'tp.nombres as t_nombres', 'tp.apellidos as t_apellidos']);
        abort_unless($m, 404);
        abort_unless(AulasDelUsuario::permitidas($usuario, $m->periodo_id)->contains($m->aula_id), 403);

        $bimestres = Calendario::bimestres($m->periodo_id);
        $vigente = Calendario::bimestreVigente($bimestres);
        $cursos = DB::table('curso_aula as c')->join('asignatura as a', 'a.id', '=', 'c.asignatura_id')->where('c.aula_id', $m->aula_id)->orderBy('a.nombre')->pluck('a.nombre', 'c.id');
        $notas = DB::table('calificacion_periodo')->where('matricula_id', $m->id)->get(['curso_aula_id', 'subperiodo_id', 'promedio', 'estado'])
            ->keyBy(fn ($c) => "{$c->curso_aula_id}-{$c->subperiodo_id}");
        $delVigente = $vigente ? $notas->where('subperiodo_id', $vigente->id)->pluck('promedio')->filter(fn ($p) => $p !== null)->map(fn ($p) => (float) $p) : collect();

        $asistencia = $vigente ? DB::table('asistencia as a')->join('sesion_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->where('a.matricula_id', $m->id)->where('s.subperiodo_id', $vigente->id)->where('s.estado', 'DEFINITIVO')
            ->selectRaw("COUNT(*) as clases, SUM(a.estado = 'F') as faltas")->first() : null;
        $pendientes = $vigente ? $this->pendientes($m, $vigente->id) : [];
        $incidencias = DB::table('incidencia as i')->join('tipo_incidencia as t', 't.id', '=', 'i.tipo_incidencia_id')
            ->join('usuario as u', 'u.id', '=', 'i.registrado_por')->leftJoin('docente as d', 'd.usuario_id', '=', 'u.id')->leftJoin('personal_administrativo as pa', 'pa.usuario_id', '=', 'u.id')
            ->leftJoin('persona as rp', 'rp.id', '=', DB::raw('COALESCE(d.persona_id, pa.persona_id)'))
            ->where('i.matricula_id', $m->id)->orderByDesc('i.fecha')->orderByDesc('i.id')
            ->get(['i.id', 'i.fecha', 'i.gravedad', 'i.descripcion', 't.nombre as tipo', 'rp.nombres', 'rp.apellidos', 'u.username']);
        $predicciones = DB::table('prediccion_riesgo as p')->whereIn('p.id', DB::table('prediccion_riesgo')->where('matricula_id', $m->id)->selectRaw('MAX(id)')->groupBy('subperiodo_id'))
            ->get(['p.id', 'p.subperiodo_id', 'p.nivel_riesgo', 'p.probabilidad', 'p.fecha'])->keyBy('subperiodo_id');
        $actual = $vigente ? $predicciones->get($vigente->id) : null;
        $alerta = DB::table('alerta_estudiantil as al')->join('docente as d', 'd.id', '=', 'al.responsable_docente_id')->join('persona as p', 'p.id', '=', 'd.persona_id')
            ->where('al.matricula_id', $m->id)->orderByRaw("al.estado IN ('CERRADA', 'DESCARTADA')")->orderByDesc('al.id')
            ->first(['al.id', 'al.estado', 'al.fecha_creacion', 'p.nombres', 'p.apellidos']);
        $escala = Calificaciones::escala();

        return [
            'estudiante' => [
                'matricula_id' => $m->id,
                'nombre' => "{$m->nombres} {$m->apellidos}",
                'codigo' => $m->codigo,
                'aula' => PerfilUsuario::nombreAula($m->numero, $m->nivel, $m->seccion),
                'nivel_educativo' => $m->nivel === 'PRIMARIA' ? 'Primaria' : 'Secundaria',
                'edad' => $m->fecha_nacimiento ? Carbon::parse($m->fecha_nacimiento)->age : null,
                'tutor' => $m->t_nombres ? Cursos::nombreCorto($m->t_nombres, $m->t_apellidos) : null,
                'estado' => $m->estado,
            ],
            'bimestre' => $vigente ? ['id' => $vigente->id, 'nombre' => $vigente->nombre] : null,
            'riesgo' => $actual ? ['nivel' => $actual->nivel_riesgo, 'probabilidad' => (int) round($actual->probabilidad * 100), 'fecha' => Carbon::parse($actual->fecha)->toIso8601String()] : null,
            'kpis' => [
                'promedio' => $delVigente->isEmpty() ? null : round($delVigente->avg(), 1, PHP_ROUND_HALF_UP),
                'asistencia' => $asistencia ? ReportesAsistencia::porcentaje((int) $asistencia->clases, (int) $asistencia->faltas) : null,
                'pendientes' => count($pendientes),
                'incidencias' => $incidencias->count(),
            ],
            'alerta' => $alerta ? [
                'id' => $alerta->id,
                'estado' => $alerta->estado,
                'creada' => Carbon::parse($alerta->fecha_creacion)->toIso8601String(),
                'responsable' => Cursos::nombreCorto($alerta->nombres, $alerta->apellidos),
                'historial' => DB::table('historial_alerta')->where('alerta_id', $alerta->id)->orderByDesc('fecha')->orderByDesc('id')->limit(4)->get(['accion', 'detalle', 'fecha'])
                    ->map(fn ($h) => ['accion' => $h->accion, 'detalle' => $h->detalle, 'fecha' => Carbon::parse($h->fecha)->toIso8601String()])->reverse()->values()->all(),
            ] : null,
            'bimestres' => $bimestres->filter(fn ($b) => $b->fecha_inicio <= today()->toDateString())->map(fn ($b) => ['id' => $b->id, 'nombre' => $b->nombre])->values()->all(),
            'notas' => $cursos->map(fn ($nombre, $id) => [
                'curso' => $nombre,
                'promedios' => $bimestres->mapWithKeys(fn ($b) => [$b->id => Calificaciones::numero($notas->get("{$id}-{$b->id}")?->promedio)])->all(),
            ])->values()->all(),
            'evolucion' => $bimestres->filter(fn ($b) => $predicciones->has($b->id))->map(fn ($b) => [
                'bimestre' => $b->nombre,
                'nivel' => $predicciones[$b->id]->nivel_riesgo,
                'probabilidad' => (int) round($predicciones[$b->id]->probabilidad * 100),
            ])->values()->all(),
            'pendientes' => $pendientes,
            'incidencias' => $incidencias->map(fn ($i) => [
                'id' => $i->id, 'fecha' => $i->fecha, 'tipo' => $i->tipo, 'gravedad' => $i->gravedad, 'descripcion' => $i->descripcion,
                'registrado_por' => $i->nombres ? Cursos::nombreCorto($i->nombres, $i->apellidos) : $i->username,
            ])->all(),
            'apoderados' => DB::table('apoderado')->where('estudiante_id', $m->estudiante_id)->orderByDesc('es_principal')->orderBy('id')
                ->get(['nombres', 'parentesco', 'celular', 'correo', 'es_principal'])
                ->map(fn ($a) => ['nombre' => $a->nombres, 'parentesco' => $a->parentesco, 'celular' => $a->celular, 'correo' => $a->correo, 'principal' => (bool) $a->es_principal])->all(),
            'escala' => $escala->map(fn ($e) => ['codigo' => $e->codigo, 'desde' => (float) $e->nota_minima])->all(),
            'factores' => $actual ? DB::table('factor_riesgo')->where('prediccion_id', $actual->id)->orderByDesc('contribucion')->get(['variable', 'descripcion'])
                ->map(fn ($f) => ['titulo' => MotorReglas::TITULOS[$f->variable] ?? $f->variable, 'descripcion' => $f->descripcion])->all() : [],
        ];
    }

    /** Actividades con archivo ya vencidas que no entregó */
    private function pendientes(object $m, int $bimestreId): array
    {
        return DB::table('evaluacion as ev')->join('curso_aula as c', 'c.id', '=', 'ev.curso_aula_id')->join('asignatura as a', 'a.id', '=', 'c.asignatura_id')
            ->where('c.aula_id', $m->aula_id)->where('ev.subperiodo_id', $bimestreId)->where('ev.requiere_entrega', true)->where('ev.estado', 'PUBLICADA')->where('ev.fecha_limite', '<', today()->toDateString())
            ->whereNotExists(fn ($q) => $q->from('entrega')->whereColumn('entrega.evaluacion_id', 'ev.id')->where('entrega.matricula_id', $m->id)->where('entrega.estado', 'ENTREGADA'))
            ->whereNotExists(fn ($q) => $q->from('nota_evaluacion')->whereColumn('nota_evaluacion.evaluacion_id', 'ev.id')->where('nota_evaluacion.matricula_id', $m->id)->whereIn('nota_evaluacion.estado_entrega', ['ENTREGADO', 'TARDIO']))
            ->orderBy('ev.fecha_limite')->get(['ev.id', 'ev.titulo', 'ev.fecha_limite', 'a.nombre as curso'])
            ->map(fn ($e) => ['id' => $e->id, 'titulo' => $e->titulo, 'curso' => $e->curso, 'fecha_limite' => $e->fecha_limite])->all();
    }
}
