<?php

namespace App\Servicios\Riesgo;

use App\Servicios\Asistencia\ReportesAsistencia;
use App\Servicios\Evaluacion\Calificaciones;
use App\Support\Calendario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CU-24: calcula el riesgo de los estudiantes con el modelo activo (reglas v0) y genera sus alertas.
 * Sin cron: se ejecuta al registrar asistencia o notas, con el botón "Recalcular" y una vez al día en el
 * primer ingreso. Todo se consulta por lotes: un recálculo del colegio son pocas consultas, no una por estudiante.
 */
class Riesgo
{
    public function __construct(private readonly MotorReglas $motor, private readonly AlertasAdministrativas $administrativas) {}

    /** Una vez al día, en el primer ingreso (RF-52) */
    public function diario(): void
    {
        if (Cache::add('ayni:riesgo-diario:'.today()->toDateString(), true, 86_400)) {
            $this->recalcular();
            $this->administrativas->sincronizar();
        }
    }

    /** Recalcula las matrículas de un aula (al guardar asistencia o notas) */
    public function recalcularAula(int $aulaId, int $bimestreId): void
    {
        $this->recalcular(DB::table('matricula')->where('aula_id', $aulaId)->where('estado', 'ACTIVA')->pluck('id')->all(), $bimestreId);
    }

    /**
     * @param  list<int>|null  $matriculas  null = todo el colegio
     * @return array{evaluados: int, ALTO: int, MEDIO: int, BAJO: int, alertas: int}
     */
    public function recalcular(?array $matriculas = null, ?int $bimestreId = null): array
    {
        $resultado = ['evaluados' => 0, 'ALTO' => 0, 'MEDIO' => 0, 'BAJO' => 0, 'alertas' => 0];
        $bimestre = $this->bimestre($bimestreId);
        $modelo = DB::table('modelo_ml')->where('activo', true)->value('id');
        if (! $bimestre || ! $modelo || $matriculas === []) {
            return $resultado;
        }

        $alumnos = DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->where('m.periodo_id', $bimestre->periodo_id)->where('m.estado', 'ACTIVA')
            ->when($matriculas !== null, fn ($q) => $q->whereIn('m.id', $matriculas))
            ->get(['m.id', 'm.aula_id', 'p.nombres', 'p.apellidos'])->keyBy('id');
        if ($alumnos->isEmpty()) {
            return $resultado;
        }

        $umbrales = DB::table('institucion')->first() ?? (object) [
            'umbral_asistencia_alto' => 85, 'umbral_asistencia_medio' => 90, 'umbral_promedio_alto' => 11, 'umbral_promedio_medio' => 12, 'umbral_pendientes_alto' => 3,
        ];
        $indicadores = $this->indicadores($alumnos, $bimestre);
        $ultimas = $this->ultimasPredicciones($alumnos->keys()->all(), $bimestre->id, $modelo);
        $conAlertas = $bimestre->estado === 'ABIERTO';

        DB::transaction(function () use ($alumnos, $indicadores, $ultimas, $umbrales, $bimestre, $modelo, $conAlertas, &$resultado) {
            $gestor = $conAlertas ? new GestorAlertas($alumnos, $bimestre) : null;
            foreach ($alumnos as $id => $alumno) {
                $f = $indicadores[$id];
                $r = $this->motor->evaluar($f, $umbrales);
                $resumen = $this->resumen($f);
                $previa = $ultimas->get($id);
                $cambio = ! $previa || $previa->nivel_riesgo !== $r['nivel'] || (float) $previa->probabilidad !== $r['probabilidad'] || $previa->resumen_texto !== $resumen;

                if ($cambio) {
                    $prediccion = $this->guardar($id, $bimestre->id, $modelo, $f, $r, $resumen);
                } else {
                    DB::table('prediccion_riesgo')->where('id', $previa->id)->update(['fecha' => now()]);
                    $prediccion = $previa->id;
                }
                $resultado['evaluados']++;
                $resultado[$r['nivel']]++;
                if ($gestor && $gestor->procesar($id, $prediccion, $r, $previa?->nivel_riesgo, $cambio)) {
                    $resultado['alertas']++;
                }
            }
        });

        return $resultado;
    }

    private function bimestre(?int $bimestreId): ?object
    {
        if ($bimestreId) {
            return DB::table('subperiodo')->where('id', $bimestreId)->first(['id', 'periodo_id', 'nombre', 'estado', 'fecha_inicio', 'fecha_fin']);
        }
        $periodo = Calendario::periodoEnCurso();
        $vigente = $periodo ? Calendario::bimestreVigente(Calendario::bimestres($periodo->id)) : null;

        return $vigente ? (object) ((array) $vigente + ['periodo_id' => $periodo->id]) : null;
    }

    /**
     * Indicadores del bimestre por matrícula: asistencia (RN-27), promedio con las notas ya registradas,
     * entregas vencidas sin entregar y notas en descenso (tres seguidas que bajan en un mismo curso).
     */
    private function indicadores(Collection $alumnos, object $b): array
    {
        $ids = $alumnos->keys()->all();
        $asistencia = DB::table('asistencia as a')->join('sesion_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->whereIn('a.matricula_id', $ids)->where('s.subperiodo_id', $b->id)->where('s.estado', 'DEFINITIVO')
            ->groupBy('a.matricula_id')->selectRaw("a.matricula_id, COUNT(*) as clases, SUM(a.estado = 'F') as faltas")
            ->get()->keyBy('matricula_id');

        $notas = DB::table('nota_evaluacion as n')->join('evaluacion as ev', 'ev.id', '=', 'n.evaluacion_id')
            ->join('curso_aula as c', 'c.id', '=', 'ev.curso_aula_id')->join('asignatura as asig', 'asig.id', '=', 'c.asignatura_id')
            ->whereIn('n.matricula_id', $ids)->where('ev.subperiodo_id', $b->id)->whereNotNull('n.nota')
            ->orderBy('ev.fecha_limite')->orderBy('ev.id')
            ->get(['n.matricula_id', 'c.id as curso_id', 'asig.nombre as curso', 'n.nota', 'ev.peso'])
            ->groupBy('matricula_id');

        // Entregas vencidas: actividades con archivo cuya fecha límite ya pasó, sin entrega ni estado "entregado"
        $vencidas = DB::table('evaluacion as ev')->join('curso_aula as c', 'c.id', '=', 'ev.curso_aula_id')
            ->whereIn('c.aula_id', $alumnos->pluck('aula_id')->unique()->all())->where('ev.subperiodo_id', $b->id)
            ->where('ev.requiere_entrega', true)->where('ev.fecha_limite', '<', today()->toDateString())
            ->get(['ev.id', 'c.aula_id'])->groupBy('aula_id');
        $evIds = $vencidas->flatten()->pluck('id')->all();
        $hechas = $evIds ? DB::table('entrega')->whereIn('evaluacion_id', $evIds)->whereIn('matricula_id', $ids)->where('estado', 'ENTREGADA')
            ->get(['evaluacion_id', 'matricula_id'])
            ->concat(DB::table('nota_evaluacion')->whereIn('evaluacion_id', $evIds)->whereIn('matricula_id', $ids)->whereIn('estado_entrega', ['ENTREGADO', 'TARDIO'])->get(['evaluacion_id', 'matricula_id']))
            ->map(fn ($x) => "{$x->matricula_id}-{$x->evaluacion_id}")->flip() : collect();

        $resultado = [];
        foreach ($alumnos as $id => $alumno) {
            $a = $asistencia->get($id);
            $clases = (int) ($a->clases ?? 0);
            $faltas = (int) ($a->faltas ?? 0);

            $porCurso = ($notas[$id] ?? collect())->groupBy('curso_id');
            $promedios = [];
            $descenso = null;
            foreach ($porCurso as $filas) {
                $nombre = $filas[0]->curso;
                $promedios[$nombre] = Calificaciones::promedio($filas->map(fn ($n) => [(float) $n->nota, (int) $n->peso])->all());
                $ultimas = $filas->slice(-3)->pluck('nota')->map(fn ($n) => (float) $n)->values()->all();
                if (! $descenso && count($ultimas) === 3 && $ultimas[0] > $ultimas[1] && $ultimas[1] > $ultimas[2]) {
                    $descenso = ['curso' => $nombre, 'notas' => $ultimas];
                }
            }
            asort($promedios);
            $bajo = array_key_first($promedios);

            $pendientes = ($vencidas[$alumno->aula_id] ?? collect())->filter(fn ($ev) => ! $hechas->has("{$id}-{$ev->id}"))->count();

            $resultado[$id] = [
                'asistencia' => ReportesAsistencia::porcentaje($clases, $faltas),
                'clases' => $clases,
                'faltas' => $faltas,
                'promedio' => $promedios ? round(array_sum($promedios) / count($promedios), 1, PHP_ROUND_HALF_UP) : null,
                'curso_bajo' => $bajo !== null ? ['nombre' => $bajo, 'promedio' => $promedios[$bajo]] : null,
                'pendientes' => $pendientes,
                'descenso' => $descenso,
            ];
        }

        return $resultado;
    }

    private function ultimasPredicciones(array $matriculas, int $bimestreId, int $modelo): Collection
    {
        $ultimas = DB::table('prediccion_riesgo')->where('subperiodo_id', $bimestreId)->where('modelo_id', $modelo)
            ->whereIn('matricula_id', $matriculas)->selectRaw('MAX(id) as id')->groupBy('matricula_id');

        return DB::table('prediccion_riesgo')->whereIn('id', $ultimas)->get()->keyBy('matricula_id');
    }

    private function guardar(int $matricula, int $bimestre, int $modelo, array $f, array $r, string $resumen): int
    {
        $id = DB::table('prediccion_riesgo')->insertGetId([
            'matricula_id' => $matricula,
            'subperiodo_id' => $bimestre,
            'modelo_id' => $modelo,
            'fecha' => now(),
            'probabilidad' => $r['probabilidad'],
            'nivel_riesgo' => $r['nivel'],
            'features' => json_encode($f, JSON_UNESCAPED_UNICODE),
            'resumen_texto' => $resumen,
        ]);
        if ($r['factores']) {
            DB::table('factor_riesgo')->insert(array_map(fn ($x) => [
                'prediccion_id' => $id,
                'variable' => $x['variable'],
                'valor' => mb_substr($x['valor'], 0, 80),
                'contribucion' => $x['contribucion'],
                'descripcion' => mb_substr($x['descripcion'], 0, 300),
            ], $r['factores']));
        }
        if ($r['recomendaciones']) {
            DB::table('recomendacion')->insert(array_map(fn ($x) => ['prediccion_id' => $id] + $x, $r['recomendaciones']));
        }

        return $id;
    }

    /** Texto con los indicadores: si no cambia, la predicción tampoco */
    private function resumen(array $f): string
    {
        $partes = [$f['asistencia'] === null ? 'Sin clases registradas' : 'Asistencia '.MotorReglas::porcentaje($f['asistencia'])." ({$f['faltas']} de {$f['clases']} faltas)"];
        $partes[] = $f['promedio'] === null ? 'sin notas' : 'promedio '.MotorReglas::nota($f['promedio']).($f['curso_bajo'] ? " (más bajo: {$f['curso_bajo']['nombre']} ".MotorReglas::nota($f['curso_bajo']['promedio']).')' : '');
        $partes[] = "{$f['pendientes']} ".($f['pendientes'] === 1 ? 'entrega pendiente' : 'entregas pendientes');
        if ($f['descenso']) {
            $partes[] = "descenso en {$f['descenso']['curso']}";
        }

        return implode(' · ', $partes);
    }
}
