<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Qué aulas ve cada usuario (incidencias, alertas y paneles) y quién coordina cada aula */
class AulasDelUsuario
{
    /** El administrador, todas; el docente, donde es tutor, dicta algún curso o coordina */
    public static function permitidas(Usuario $usuario, int $periodoId): Collection
    {
        $aulas = self::delPeriodo($periodoId);
        if ($usuario->rol->codigo === 'ADMIN') {
            return $aulas->pluck('aula.id');
        }
        $docente = Cursos::docenteDe($usuario);
        abort_unless($usuario->rol->codigo === 'DOCENTE' && $docente, 403);
        $coordina = self::coordinaciones($docente, $periodoId);
        if ($coordina->contains('ambito', 'INSTITUCION')) {
            return $aulas->pluck('aula.id');
        }

        return self::filtrarPorCoordinacion($aulas, $coordina, fn (Builder $q) => $q
            ->orWhere('aula.tutor_docente_id', $docente)
            ->orWhereExists(fn ($s) => $s->from('curso_aula')->whereColumn('curso_aula.aula_id', 'aula.id')->where('curso_aula.docente_id', $docente)))
            ->pluck('aula.id');
    }

    /** Solo las aulas que el docente coordina (panel de coordinación, RF-58) */
    public static function coordinadas(int $docenteId, int $periodoId): Collection
    {
        $coordina = self::coordinaciones($docenteId, $periodoId);
        if ($coordina->isEmpty()) {
            return collect();
        }
        $aulas = self::delPeriodo($periodoId);
        if ($coordina->contains('ambito', 'INSTITUCION')) {
            return $aulas->pluck('aula.id');
        }

        return self::filtrarPorCoordinacion($aulas, $coordina)->pluck('aula.id');
    }

    /** RN-21: el coordinador más cercano al aula (aula, grado, nivel y, por último, el colegio) */
    public static function coordinadorDe(int $aulaId): ?int
    {
        $aula = DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->where('aula.id', $aulaId)
            ->first(['aula.id', 'aula.periodo_id', 'aula.grado_id', 'grado.nivel']);
        if (! $aula) {
            return null;
        }
        $candidatos = DB::table('coordinacion as c')->join('docente as d', 'd.id', '=', 'c.docente_id')
            ->where('c.periodo_id', $aula->periodo_id)->where('d.estado', 'ACTIVO')
            ->where(fn ($q) => $q->where('c.aula_id', $aula->id)->orWhere('c.grado_id', $aula->grado_id)
                ->orWhere('c.nivel', $aula->nivel)->orWhere('c.ambito', 'INSTITUCION'))
            ->orderBy('c.id')->get(['c.docente_id', 'c.ambito']);

        foreach (['AULA', 'GRADO', 'NIVEL', 'INSTITUCION'] as $ambito) {
            if ($c = $candidatos->firstWhere('ambito', $ambito)) {
                return $c->docente_id;
            }
        }

        return null;
    }

    /** Quién coordina el aula, para que el estudiante sepa a quién acudir: "Luis Paredes" */
    public static function nombreCoordinador(int $aulaId): ?string
    {
        $docente = self::coordinadorDe($aulaId);
        $p = $docente ? DB::table('docente')->join('persona', 'persona.id', '=', 'docente.persona_id')->where('docente.id', $docente)->first(['persona.nombres', 'persona.apellidos']) : null;

        return $p ? Cursos::nombreCorto($p->nombres, $p->apellidos) : null;
    }

    /** Nombre del ámbito de un coordinador: "3.º A", "Nivel primaria", "Todo el colegio" */
    public static function nombreAmbito(int $docenteId, int $periodoId): ?string
    {
        $nombres = DB::table('coordinacion as c')
            ->leftJoin('aula', 'aula.id', '=', 'c.aula_id')->leftJoin('grado as ga', 'ga.id', '=', 'aula.grado_id')
            ->leftJoin('seccion', 'seccion.id', '=', 'aula.seccion_id')->leftJoin('grado as g', 'g.id', '=', 'c.grado_id')
            ->where('c.docente_id', $docenteId)->where('c.periodo_id', $periodoId)->orderBy('c.id')
            ->get(['c.ambito', 'c.nivel', 'ga.numero', 'ga.nivel as nivel_aula', 'seccion.nombre as seccion', 'g.numero as grado', 'g.nivel as nivel_grado'])
            ->map(fn ($c) => match ($c->ambito) {
                'AULA' => PerfilUsuario::nombreAula($c->numero, $c->nivel_aula, $c->seccion),
                'GRADO' => "{$c->grado}.º de ".mb_strtolower($c->nivel_grado),
                'NIVEL' => 'Nivel '.mb_strtolower($c->nivel),
                default => 'Todo el colegio',
            });

        return $nombres->isEmpty() ? null : ($nombres->contains('Todo el colegio') ? 'Todo el colegio' : $nombres->implode(', '));
    }

    private static function coordinaciones(int $docenteId, int $periodoId): Collection
    {
        return DB::table('coordinacion')->where('docente_id', $docenteId)->where('periodo_id', $periodoId)->get();
    }

    private static function delPeriodo(int $periodoId): Builder
    {
        return DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->where('aula.periodo_id', $periodoId);
    }

    private static function filtrarPorCoordinacion(Builder $aulas, Collection $coordina, ?callable $ademas = null): Builder
    {
        return $aulas->where(function (Builder $q) use ($coordina, $ademas) {
            $q->whereIn('aula.id', $coordina->where('ambito', 'AULA')->pluck('aula_id'))
                ->orWhereIn('aula.grado_id', $coordina->where('ambito', 'GRADO')->pluck('grado_id'))
                ->orWhereIn('grado.nivel', $coordina->where('ambito', 'NIVEL')->pluck('nivel'));
            if ($ademas) {
                $ademas($q);
            }
        });
    }
}
