<?php

namespace App\Servicios\Evaluacion;

use App\Exceptions\ErrorRegla;
use App\Models\Usuario;
use App\Servicios\Notificaciones;
use App\Support\Auditoria;
use App\Support\Calendario;
use App\Support\Cursos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** CU-19 y CU-20: promedio ponderado del bimestre, publicación y cierre (RF-44 a RF-46, RN-09 a RN-14) */
class Calificaciones
{
    /** RN-09: Σ(nota × peso) / Σ(peso); sin nota cuenta 0; 1 decimal y 0,05 sube */
    public static function promedio(array $notasYPesos): ?float
    {
        $pesos = array_sum(array_column($notasYPesos, 1));
        if (! $pesos) {
            return null;
        }
        $suma = array_sum(array_map(fn ($x) => ((float) ($x[0] ?? 0)) * $x[1], $notasYPesos));

        return round($suma / $pesos, 1, PHP_ROUND_HALF_UP);
    }

    public static function escala(): Collection
    {
        return DB::table('escala_literal')->orderByDesc('nota_minima')->get(['codigo', 'nota_minima']);
    }

    /** RN-11: equivalencia literal para mostrar */
    public static function literal(?float $nota, Collection $escala): ?string
    {
        return $nota === null ? null : $escala->first(fn ($e) => $nota >= (float) $e->nota_minima)?->codigo;
    }

    /**
     * Notas y promedio de cada estudiante del curso en el bimestre.
     *
     * @return array{evaluaciones: Collection, alumnos: Collection, notas: Collection, promedios: array<int, ?float>}
     */
    public function calcular(int $cursoId, int $bimestreId, int $aulaId): array
    {
        $evaluaciones = DB::table('evaluacion as ev')->join('tipo_evaluacion as t', 't.id', '=', 'ev.tipo_evaluacion_id')
            ->where('ev.curso_aula_id', $cursoId)->where('ev.subperiodo_id', $bimestreId)
            ->orderBy('ev.fecha_limite')->orderBy('ev.id')
            ->get(['ev.id', 'ev.titulo', 'ev.peso', 'ev.fecha_limite', 't.nombre as tipo']);
        $alumnos = $this->alumnos($aulaId, $evaluaciones->pluck('id')->all());
        $notas = DB::table('nota_evaluacion')->whereIn('evaluacion_id', $evaluaciones->pluck('id'))
            ->get(['evaluacion_id', 'matricula_id', 'nota'])->keyBy(fn ($n) => "{$n->evaluacion_id}-{$n->matricula_id}");

        $promedios = [];
        foreach ($alumnos as $a) {
            $promedios[$a->matricula_id] = self::promedio($evaluaciones->map(fn ($ev) => [
                $notas->get("{$ev->id}-{$a->matricula_id}")?->nota, (int) $ev->peso,
            ])->all());
        }

        return compact('evaluaciones', 'alumnos', 'notas', 'promedios');
    }

    /** Guarda el promedio vigente de cada estudiante; el estado (borrador, publicada) no cambia */
    public function recalcular(int $cursoId, int $bimestreId): void
    {
        $aulaId = DB::table('curso_aula')->where('id', $cursoId)->value('aula_id');
        ['promedios' => $promedios] = $this->calcular($cursoId, $bimestreId, $aulaId);
        $existentes = DB::table('calificacion_periodo')->where('curso_aula_id', $cursoId)->where('subperiodo_id', $bimestreId)
            ->get()->keyBy('matricula_id');

        foreach ($promedios as $matriculaId => $promedio) {
            $fila = $existentes->get($matriculaId);
            if ($fila?->estado === 'CERRADA') {
                continue;
            }
            if ($fila) {
                if ($fila->promedio === null ? $promedio !== null : (float) $fila->promedio !== $promedio) {
                    DB::table('calificacion_periodo')->where('id', $fila->id)->update(['promedio' => $promedio, 'updated_at' => now()]);
                }
            } else {
                DB::table('calificacion_periodo')->insert([
                    'matricula_id' => $matriculaId, 'curso_aula_id' => $cursoId, 'subperiodo_id' => $bimestreId, 'promedio' => $promedio,
                ]);
            }
        }
    }

    /** Pantalla de calificaciones del docente (mockup docente 08) */
    public function vista(int $cursoId, ?int $bimestreId, Usuario $usuario): array
    {
        $curso = Cursos::autorizado($cursoId, $usuario);
        $bimestres = Calendario::bimestres($curso->periodo_id);
        $bimestre = $this->bimestre($bimestres, $bimestreId);
        ['evaluaciones' => $evaluaciones, 'alumnos' => $alumnos, 'notas' => $notas, 'promedios' => $promedios] = $this->calcular($curso->id, $bimestre->id, $curso->aula_id);
        $estados = DB::table('calificacion_periodo')->where('curso_aula_id', $curso->id)->where('subperiodo_id', $bimestre->id)->get()->keyBy('matricula_id');
        $aprobatoria = (float) DB::table('institucion')->value('nota_aprobatoria');
        $escala = self::escala();

        $sinNota = $evaluaciones->map(fn ($ev) => [
            'id' => $ev->id,
            'titulo' => $ev->titulo,
            'fecha_limite' => $ev->fecha_limite,
            'estudiantes' => $alumnos->filter(fn ($a) => $notas->get("{$ev->id}-{$a->matricula_id}")?->nota === null)
                ->map(fn ($a) => "{$a->apellidos}, {$a->nombres}")->values()->all(),
        ])->filter(fn ($x) => $x['estudiantes'])->values();

        $validos = array_filter($promedios, fn ($p) => $p !== null);
        $publicada = $estados->first(fn ($e) => $e->estado !== 'BORRADOR');

        return [
            'curso' => Cursos::datos($curso),
            'bimestre' => Cursos::datosBimestre($bimestre),
            'bimestres' => $bimestres->map(fn ($b) => Cursos::datosBimestre($b))->all(),
            'estado' => $bimestre->estado === 'CERRADO' ? 'CERRADA' : ($publicada ? 'PUBLICADA' : 'BORRADOR'),
            'publicado_en' => $publicada?->publicado_en,
            'nota_aprobatoria' => $aprobatoria,
            'evaluaciones' => $evaluaciones->map(fn ($ev) => ['id' => $ev->id, 'titulo' => $ev->titulo, 'tipo' => $ev->tipo, 'peso' => (int) $ev->peso, 'fecha_limite' => $ev->fecha_limite])->all(),
            'estudiantes' => $alumnos->map(fn ($a) => [
                'matricula_id' => $a->matricula_id,
                'nombre' => "{$a->apellidos}, {$a->nombres}",
                'notas' => $evaluaciones->mapWithKeys(fn ($ev) => [$ev->id => self::numero($notas->get("{$ev->id}-{$a->matricula_id}")?->nota)])->all(),
                'promedio' => $promedios[$a->matricula_id],
                'literal' => self::literal($promedios[$a->matricula_id], $escala),
            ])->values()->all(),
            'resumen' => [
                'estudiantes' => $alumnos->count(),
                'promedio' => $validos ? round(array_sum($validos) / count($validos), 1, PHP_ROUND_HALF_UP) : null,
                'desaprobados' => count(array_filter($validos, fn ($p) => $p < $aprobatoria)),
            ],
            'sin_nota' => $sinNota->all(),
            'editable' => $bimestre->estado === 'ABIERTO',
        ];
    }

    /** RF-45: el docente publica; lo que no tiene nota se cuenta como 0 */
    public function publicar(int $cursoId, int $bimestreId, Usuario $usuario): array
    {
        $curso = Cursos::autorizado($cursoId, $usuario);
        $bimestre = DB::table('subperiodo')->where('id', $bimestreId)->where('periodo_id', $curso->periodo_id)->first()
            ?? throw new ErrorRegla('Elige un bimestre del periodo del curso.', 'bimestre_id');
        Evaluaciones::exigirAbierto($bimestre);
        if (! DB::table('evaluacion')->where('curso_aula_id', $cursoId)->where('subperiodo_id', $bimestreId)->exists()) {
            throw new ErrorRegla('El curso aún no tiene evaluaciones en este bimestre.');
        }

        $nuevas = DB::transaction(function () use ($curso, $bimestre, $usuario) {
            $this->recalcular($curso->id, $bimestre->id);
            $antes = DB::table('calificacion_periodo')->where('curso_aula_id', $curso->id)->where('subperiodo_id', $bimestre->id)
                ->where('estado', 'BORRADOR')->pluck('matricula_id');
            DB::table('calificacion_periodo')->where('curso_aula_id', $curso->id)->where('subperiodo_id', $bimestre->id)->where('estado', 'BORRADOR')
                ->update(['estado' => 'PUBLICADA', 'publicado_en' => now(), 'publicado_por' => $usuario->id, 'updated_at' => now()]);
            Auditoria::registrar('PUBLICAR_CALIFICACIONES', 'curso_aula', $curso->id, ['bimestre' => $bimestre->nombre, 'estudiantes' => $antes->count()]);

            return $antes;
        });

        // Cada estudiante se entera la primera vez que se publican sus notas del curso
        $usuarios = DB::table('matricula')->join('estudiante', 'estudiante.id', '=', 'matricula.estudiante_id')
            ->whereIn('matricula.id', $nuevas)->whereNotNull('estudiante.usuario_id')->pluck('estudiante.usuario_id');
        Notificaciones::enviar($usuarios, 'NOTA_PUBLICADA', 'Notas publicadas', "Se publicaron tus notas de {$curso->asignatura} del {$bimestre->nombre}.", "/estudiante/notas?curso={$curso->id}&bimestre={$bimestre->id}");

        return ['mensaje' => 'Promedios publicados. Los estudiantes ya pueden ver sus notas.'] + $this->vista($cursoId, $bimestreId, $usuario);
    }

    /** RN-14: al cerrar el bimestre, lo pendiente se calcula con 0 y todo queda cerrado */
    public function cerrarBimestre(int $bimestreId): void
    {
        $periodoId = DB::table('subperiodo')->where('id', $bimestreId)->value('periodo_id');
        $cursos = DB::table('curso_aula')->join('aula', 'aula.id', '=', 'curso_aula.aula_id')->where('aula.periodo_id', $periodoId)
            ->whereExists(fn ($q) => $q->from('evaluacion')->whereColumn('evaluacion.curso_aula_id', 'curso_aula.id')->where('evaluacion.subperiodo_id', $bimestreId))
            ->pluck('curso_aula.id');
        foreach ($cursos as $cursoId) {
            $this->recalcular($cursoId, $bimestreId);
        }
        DB::table('calificacion_periodo')->where('subperiodo_id', $bimestreId)->where('estado', '!=', 'CERRADA')->update([
            'estado' => 'CERRADA',
            'cerrado_en' => now(),
            'publicado_en' => DB::raw('COALESCE(publicado_en, NOW())'),
            'updated_at' => now(),
        ]);
    }

    /** Matriculados del aula, más quienes ya tienen notas en el bimestre aunque luego se hayan retirado */
    private function alumnos(int $aulaId, array $evaluaciones): Collection
    {
        return DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->where('m.aula_id', $aulaId)
            ->where(fn ($q) => $q->where('m.estado', 'ACTIVA')
                ->orWhereExists(fn ($s) => $s->from('nota_evaluacion')->whereColumn('nota_evaluacion.matricula_id', 'm.id')->whereIn('nota_evaluacion.evaluacion_id', $evaluaciones)->whereNotNull('nota_evaluacion.nota')))
            ->orderBy('p.apellidos')->orderBy('p.nombres')
            ->get(['m.id as matricula_id', 'e.codigo', 'e.usuario_id', 'p.nombres', 'p.apellidos']);
    }

    private function bimestre(Collection $bimestres, ?int $bimestreId): object
    {
        $bimestre = $bimestreId ? $bimestres->firstWhere('id', $bimestreId) : Calendario::bimestreVigente($bimestres);

        return $bimestre ?? throw new ErrorRegla('Elige un bimestre del periodo del curso.', 'bimestre_id');
    }

    public static function numero(mixed $nota): ?float
    {
        return $nota === null ? null : (float) $nota;
    }
}
