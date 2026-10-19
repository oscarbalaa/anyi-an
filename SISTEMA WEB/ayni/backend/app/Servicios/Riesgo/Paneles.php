<?php

namespace App\Servicios\Riesgo;

use App\Models\Usuario;
use App\Support\AulasDelUsuario;
use App\Support\Calendario;
use App\Support\Cursos;
use App\Support\PerfilUsuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** CU-26: panel de riesgo del colegio (RF-57) y de coordinación de un ámbito (RF-58) */
class Paneles
{
    /** Estudiantes que se listan en "requieren atención primero" */
    private const ATENCION = 10;

    public function __construct(private readonly AlertasAdministrativas $administrativas) {}

    /** Aulas del panel: el administrador elige nivel o aula; el docente ve solo lo que coordina */
    public function aulas(Usuario $usuario, int $periodoId, array $f): Collection
    {
        if ($usuario->rol->codigo === 'ADMIN') {
            return DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->where('aula.periodo_id', $periodoId)
                ->when($f['nivel'] ?? null, fn ($q, $n) => $q->where('grado.nivel', $n))
                ->when($f['aula_id'] ?? null, fn ($q, $a) => $q->where('aula.id', $a))
                ->pluck('aula.id');
        }
        $docente = Cursos::docenteDe($usuario);
        $coordinadas = $docente ? AulasDelUsuario::coordinadas($docente, $periodoId) : collect();

        return ($f['aula_id'] ?? null) ? $coordinadas->intersect([(int) $f['aula_id']])->values() : $coordinadas;
    }

    public function panel(Usuario $usuario, array $f): array
    {
        $periodo = Calendario::periodoEnCurso();
        $bimestres = $periodo ? Calendario::bimestres($periodo->id) : collect();
        $bimestre = $bimestres->firstWhere('id', (int) ($f['bimestre_id'] ?? 0)) ?? Calendario::bimestreVigente($bimestres);
        $esAdmin = $usuario->rol->codigo === 'ADMIN';
        $docente = Cursos::docenteDe($usuario);
        $vacio = ['bimestre' => null, 'bimestres' => [], 'ambito' => null, 'es_coordinador' => false];
        if (! $periodo || ! $bimestre) {
            return $vacio;
        }
        $todasDelAmbito = $this->aulas($usuario, $periodo->id, []);
        if (! $esAdmin && $todasDelAmbito->isEmpty()) {
            return $vacio;
        }
        if ($esAdmin) {
            $this->administrativas->sincronizar();
        }
        $aulas = $this->aulas($usuario, $periodo->id, $f);
        $modelo = DB::table('modelo_ml')->where('activo', true)->first(['id', 'nombre', 'version']);
        $actuales = $this->predicciones($aulas->all(), $bimestre->id, $modelo?->id);

        $anterior = $bimestres->filter(fn ($b) => $b->numero < $bimestre->numero)->last();
        $previas = $anterior ? $this->predicciones($aulas->all(), $anterior->id, $modelo?->id) : collect();
        $cuenta = fn (Collection $p, string $n) => $p->where('nivel_riesgo', $n)->count();

        $alertas = DB::table('alerta_estudiantil as al')->join('matricula as m', 'm.id', '=', 'al.matricula_id')
            ->whereIn('m.aula_id', $aulas)->where('al.subperiodo_id', $bimestre->id);
        $sinAtender = (clone $alertas)->where('al.estado', 'NUEVA');
        $abiertas = (clone $alertas)->whereIn('al.estado', ['NUEVA', 'EN_SEGUIMIENTO'])->pluck('al.id', 'al.matricula_id');

        return [
            'bimestre' => ['id' => $bimestre->id, 'nombre' => $bimestre->nombre, 'estado' => $bimestre->estado],
            'bimestres' => $bimestres->filter(fn ($b) => $b->fecha_inicio <= today()->toDateString())->map(fn ($b) => ['id' => $b->id, 'nombre' => $b->nombre])->values()->all(),
            'ambito' => $esAdmin ? 'Toda la institución' : AulasDelUsuario::nombreAmbito($docente, $periodo->id),
            'es_coordinador' => ! $esAdmin,
            'modelo' => $modelo ? "{$modelo->nombre} {$modelo->version}" : null,
            'actualizado' => ($u = $actuales->max('fecha')) ? Carbon::parse($u)->toIso8601String() : null,
            'kpis' => [
                'ALTO' => $cuenta($actuales, 'ALTO'),
                'MEDIO' => $cuenta($actuales, 'MEDIO'),
                'BAJO' => $cuenta($actuales, 'BAJO'),
                'evaluados' => $actuales->count(),
                'estudiantes' => DB::table('matricula')->whereIn('aula_id', $aulas)->where('estado', 'ACTIVA')->count(),
                'sin_atender' => (clone $sinAtender)->count(),
                'mas_antigua' => ($v = (clone $sinAtender)->min('al.fecha_creacion')) ? Carbon::parse($v)->toIso8601String() : null,
                'abiertas' => $abiertas->count(),
            ],
            'anterior' => $anterior && $previas->isNotEmpty() ? ['bimestre' => $anterior->nombre, 'ALTO' => $cuenta($previas, 'ALTO'), 'MEDIO' => $cuenta($previas, 'MEDIO')] : null,
            'por_aula' => $this->porAula($aulas, $actuales),
            'factores' => $this->factores($actuales),
            'atencion' => $this->atencion($actuales, $abiertas),
            'aulas' => $this->nombresAulas($todasDelAmbito),
        ];
    }

    /** La última predicción de cada matrícula activa del bimestre */
    private function predicciones(array $aulas, int $bimestreId, ?int $modelo): Collection
    {
        if (! $aulas || ! $modelo) {
            return collect();
        }
        $ultimas = DB::table('prediccion_riesgo as p')->join('matricula as m', 'm.id', '=', 'p.matricula_id')
            ->where('p.subperiodo_id', $bimestreId)->where('p.modelo_id', $modelo)->whereIn('m.aula_id', $aulas)->where('m.estado', 'ACTIVA')
            ->selectRaw('MAX(p.id) as id')->groupBy('p.matricula_id');

        return DB::table('prediccion_riesgo as p')->join('matricula as m', 'm.id', '=', 'p.matricula_id')
            ->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as pe', 'pe.id', '=', 'e.persona_id')
            ->whereIn('p.id', $ultimas)
            ->get(['p.id', 'p.matricula_id', 'p.nivel_riesgo', 'p.probabilidad', 'p.fecha', 'm.aula_id', 'pe.nombres', 'pe.apellidos']);
    }

    private function porAula(Collection $aulas, Collection $actuales): array
    {
        $porAula = $actuales->groupBy('aula_id');

        return collect($this->nombresAulas($aulas))->map(function ($a) use ($porAula) {
            $p = $porAula->get($a['id'], collect());

            return $a + ['ALTO' => $p->where('nivel_riesgo', 'ALTO')->count(), 'MEDIO' => $p->where('nivel_riesgo', 'MEDIO')->count(), 'BAJO' => $p->where('nivel_riesgo', 'BAJO')->count()];
        })->sortByDesc(fn ($a) => [$a['ALTO'] + $a['MEDIO'], $a['ALTO']])->values()->all();
    }

    /** Qué reglas se activan más entre los estudiantes en riesgo medio o alto */
    private function factores(Collection $actuales): array
    {
        $enRiesgo = $actuales->whereIn('nivel_riesgo', ['ALTO', 'MEDIO']);
        if ($enRiesgo->isEmpty()) {
            return [];
        }
        $conteo = DB::table('factor_riesgo')->whereIn('prediccion_id', $enRiesgo->pluck('id'))
            ->selectRaw('variable, COUNT(DISTINCT prediccion_id) as estudiantes')->groupBy('variable')->pluck('estudiantes', 'variable');

        return collect(MotorReglas::TITULOS)->map(fn ($titulo, $variable) => [
            'variable' => $variable,
            'titulo' => $titulo,
            'estudiantes' => (int) ($conteo[$variable] ?? 0),
            'porcentaje' => (int) round(100 * ($conteo[$variable] ?? 0) / $enRiesgo->count()),
        ])->sortByDesc('estudiantes')->values()->all();
    }

    private function atencion(Collection $actuales, Collection $abiertas): array
    {
        $lista = $actuales->whereIn('nivel_riesgo', ['ALTO', 'MEDIO'])
            ->sortBy([fn ($a, $b) => MotorReglas::NIVELES[$b->nivel_riesgo] <=> MotorReglas::NIVELES[$a->nivel_riesgo], fn ($a, $b) => $b->probabilidad <=> $a->probabilidad])
            ->take(self::ATENCION)->values();
        $principales = DB::table('factor_riesgo')->whereIn('prediccion_id', $lista->pluck('id'))->orderByDesc('contribucion')->orderBy('id')
            ->get(['prediccion_id', 'descripcion'])->groupBy('prediccion_id')->map(fn ($g) => $g->first()->descripcion);
        $nombres = collect($this->nombresAulas($lista->pluck('aula_id')->unique()))->pluck('nombre', 'id');

        return $lista->map(fn ($p) => [
            'matricula_id' => $p->matricula_id,
            'nombre' => "{$p->nombres} {$p->apellidos}",
            'aula' => $nombres[$p->aula_id] ?? '',
            'nivel' => $p->nivel_riesgo,
            'probabilidad' => (int) round($p->probabilidad * 100),
            'factor_principal' => $principales[$p->id] ?? null,
            'alerta_id' => $abiertas[$p->matricula_id] ?? null,
        ])->all();
    }

    private function nombresAulas(Collection $ids): array
    {
        return DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->whereIn('aula.id', $ids)->orderBy('grado.orden')->orderBy('seccion.nombre')
            ->get(['aula.id', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion'])
            ->map(fn ($a) => ['id' => $a->id, 'nombre' => PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion), 'nivel' => $a->nivel])->all();
    }
}
