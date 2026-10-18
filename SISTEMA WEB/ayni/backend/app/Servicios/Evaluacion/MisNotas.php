<?php

namespace App\Servicios\Evaluacion;

use App\Models\Usuario;
use App\Support\Calendario;
use App\Support\Cursos;
use App\Support\MiMatricula;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** RF-47: notas del estudiante. Solo se ven las calificaciones publicadas o cerradas (RN-13) */
class MisNotas
{
    public function __construct(private readonly Entregas $entregas) {}

    public function resumen(Usuario $usuario, ?int $bimestreId): array
    {
        $m = MiMatricula::de($usuario);
        if (! $m) {
            return ['aula' => null, 'bimestre' => null, 'bimestres' => [], 'cursos' => [], 'promedio' => null, 'anterior' => null, 'ultimas' => []];
        }
        $bimestres = Calendario::bimestres($m->periodo_id);
        $publicadas = $this->publicadas($m->id);
        $bimestre = $this->bimestre($bimestres, $publicadas, $bimestreId);
        $escala = Calificaciones::escala();

        $cursos = DB::table('curso_aula as c')->join('asignatura as a', 'a.id', '=', 'c.asignatura_id')
            ->join('docente as d', 'd.id', '=', 'c.docente_id')->join('persona as p', 'p.id', '=', 'd.persona_id')
            ->where('c.aula_id', $m->aula_id)->orderBy('a.nombre')
            ->get(['c.id', 'a.nombre as asignatura', 'p.nombres', 'p.apellidos']);
        $delBimestre = $bimestre ? $publicadas->where('subperiodo_id', $bimestre->id)->keyBy('curso_aula_id') : collect();
        $anterior = $bimestre ? $bimestres->firstWhere('numero', $bimestre->numero - 1) : null;

        return [
            'aula' => MiMatricula::nombreAula($m),
            'bimestre' => $bimestre ? Cursos::datosBimestre($bimestre) : null,
            'bimestres' => $bimestres->map(fn ($b) => Cursos::datosBimestre($b))->all(),
            'cursos' => $cursos->map(function ($c) use ($delBimestre, $escala) {
                $promedio = Calificaciones::numero($delBimestre->get($c->id)?->promedio);

                return [
                    'id' => $c->id,
                    'asignatura' => $c->asignatura,
                    'docente' => Cursos::nombreCorto($c->nombres, $c->apellidos),
                    'publicado' => $delBimestre->has($c->id),
                    'promedio' => $promedio,
                    'literal' => Calificaciones::literal($promedio, $escala),
                ];
            })->all(),
            'promedio' => $this->media($delBimestre),
            'anterior' => $anterior ? ['nombre' => $anterior->nombre, 'promedio' => $this->media($publicadas->where('subperiodo_id', $anterior->id))] : null,
            'ultimas' => $this->ultimas($m->id, $publicadas),
            'escala' => $escala->map(fn ($e) => ['codigo' => $e->codigo, 'desde' => (float) $e->nota_minima])->all(),
        ];
    }

    public function curso(int $cursoId, Usuario $usuario, ?int $bimestreId): array
    {
        $m = MiMatricula::de($usuario) ?? abort(404);
        $curso = Cursos::consulta()->where('curso_aula.id', $cursoId)->where('curso_aula.aula_id', $m->aula_id)->first() ?? abort(404);
        $bimestres = Calendario::bimestres($m->periodo_id);
        $publicadas = $this->publicadas($m->id)->where('curso_aula_id', $cursoId);
        $bimestre = $this->bimestre($bimestres, $publicadas, $bimestreId) ?? abort(404);
        $escala = Calificaciones::escala();

        $actividades = $this->entregas->evaluaciones($m, $bimestre->id)->where('curso_aula_id', $cursoId)
            ->map(fn ($ev) => $this->entregas->datos($ev))->values();
        $conEntrega = $actividades->filter(fn ($a) => $a['requiere_entrega'] && ($a['estado'] !== 'PENDIENTE' && $a['estado'] !== 'EN_PROGRESO'));
        $promedio = Calificaciones::numero($publicadas->firstWhere('subperiodo_id', $bimestre->id)?->promedio);

        return [
            'curso' => Cursos::datos($curso),
            'bimestre' => Cursos::datosBimestre($bimestre),
            'bimestres' => $bimestres->map(fn ($b) => Cursos::datosBimestre($b))->all(),
            'publicado' => $publicadas->contains('subperiodo_id', $bimestre->id),
            'promedio' => $promedio,
            'literal' => Calificaciones::literal($promedio, $escala),
            'evaluaciones' => $actividades->all(),
            'resumen' => [
                'calificadas' => $actividades->filter(fn ($a) => $a['nota'] !== null)->count(),
                'entregadas' => $conEntrega->filter(fn ($a) => in_array($a['estado'], ['ENTREGADO', 'TARDIO'], true))->count(),
                'por_entregar' => $conEntrega->count(),
            ],
            'evolucion' => $bimestres->map(fn ($b) => [
                'bimestre' => $b->nombre,
                'promedio' => Calificaciones::numero($publicadas->firstWhere('subperiodo_id', $b->id)?->promedio),
            ])->all(),
            'proxima' => $actividades->filter(fn ($a) => $a['fecha_limite'] >= today()->toDateString() && ! in_array($a['estado'], ['ENTREGADO', 'TARDIO'], true))
                ->sortBy('fecha_limite')->first(),
        ];
    }

    private function publicadas(int $matriculaId): Collection
    {
        return DB::table('calificacion_periodo')->where('matricula_id', $matriculaId)->where('estado', '!=', 'BORRADOR')
            ->get(['curso_aula_id', 'subperiodo_id', 'promedio', 'publicado_en']);
    }

    /** El pedido; si no, el último bimestre con notas publicadas; si no hay, el de hoy */
    private function bimestre(Collection $bimestres, Collection $publicadas, ?int $bimestreId): ?object
    {
        if ($bimestreId) {
            return $bimestres->firstWhere('id', $bimestreId) ?? abort(404);
        }
        $conNotas = $bimestres->filter(fn ($b) => $publicadas->contains('subperiodo_id', $b->id))->last();

        return $conNotas ?? Calendario::bimestreVigente($bimestres);
    }

    private function media(Collection $calificaciones): ?float
    {
        $valores = $calificaciones->pluck('promedio')->filter(fn ($p) => $p !== null)->map(fn ($p) => (float) $p);

        return $valores->isEmpty() ? null : round($valores->avg(), 1, PHP_ROUND_HALF_UP);
    }

    private function ultimas(int $matriculaId, Collection $publicadas): array
    {
        $claves = $publicadas->map(fn ($c) => "{$c->curso_aula_id}-{$c->subperiodo_id}")->flip();

        return DB::table('nota_evaluacion as n')->join('evaluacion as ev', 'ev.id', '=', 'n.evaluacion_id')
            ->join('curso_aula as c', 'c.id', '=', 'ev.curso_aula_id')->join('asignatura as a', 'a.id', '=', 'c.asignatura_id')
            ->where('n.matricula_id', $matriculaId)->whereNotNull('n.nota')
            ->orderByDesc('n.updated_at')->orderByDesc('n.id')->limit(40)
            ->get(['ev.id', 'ev.titulo', 'ev.curso_aula_id', 'ev.subperiodo_id', 'a.nombre as curso', 'n.nota', 'n.updated_at'])
            ->filter(fn ($n) => $claves->has("{$n->curso_aula_id}-{$n->subperiodo_id}"))
            ->take(4)
            ->map(fn ($n) => ['id' => $n->id, 'titulo' => $n->titulo, 'curso' => $n->curso, 'nota' => (float) $n->nota, 'fecha' => substr($n->updated_at, 0, 10)])
            ->values()->all();
    }
}
