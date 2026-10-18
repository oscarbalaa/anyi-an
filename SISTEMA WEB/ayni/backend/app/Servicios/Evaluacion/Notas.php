<?php

namespace App\Servicios\Evaluacion;

use App\Exceptions\ErrorRegla;
use App\Models\Usuario;
use App\Servicios\Archivos;
use App\Support\Auditoria;
use App\Support\Cursos;
use App\Support\Excel;
use Illuminate\Support\Facades\DB;

/** CU-18: notas de una evaluación (RF-42, RF-43, RN-08) */
class Notas
{
    public const ESTADOS = ['PENDIENTE', 'ENTREGADO', 'TARDIO', 'NO_ENTREGADO'];

    private const NOMBRE_ESTADO = ['PENDIENTE' => 'Pendiente', 'ENTREGADO' => 'Entregado', 'TARDIO' => 'Tardío', 'NO_ENTREGADO' => 'No entregado'];

    public function __construct(private readonly Evaluaciones $evaluaciones, private readonly Calificaciones $calificaciones) {}

    public function planilla(int $evaluacionId, Usuario $usuario): array
    {
        $ev = $this->evaluaciones->evaluacion($evaluacionId, $usuario, false);
        $curso = Cursos::autorizado($ev->curso_aula_id, $usuario);
        $bimestre = DB::table('subperiodo')->find($ev->subperiodo_id);
        $tipo = DB::table('tipo_evaluacion')->where('id', $ev->tipo_evaluacion_id)->value('nombre');
        $filas = $this->filas($ev, $curso->aula_id);
        $aprobatoria = (float) DB::table('institucion')->value('nota_aprobatoria');
        $conNota = $filas->pluck('nota')->filter(fn ($n) => $n !== null);
        $publicada = DB::table('calificacion_periodo')->where('curso_aula_id', $curso->id)->where('subperiodo_id', $bimestre->id)
            ->where('estado', '!=', 'BORRADOR')->exists();

        return [
            'evaluacion' => [
                'id' => $ev->id, 'titulo' => $ev->titulo, 'tipo' => $tipo, 'peso' => (int) $ev->peso,
                'fecha_asignacion' => $ev->fecha_asignacion, 'fecha_limite' => $ev->fecha_limite,
                'requiere_entrega' => (bool) $ev->requiere_entrega,
            ],
            'curso' => Cursos::datos($curso),
            'bimestre' => Cursos::datosBimestre($bimestre),
            'nota_aprobatoria' => $aprobatoria,
            'publicada' => $publicada,
            'editable' => $bimestre->estado === 'ABIERTO',
            'resumen' => [
                'promedio' => $conNota->isEmpty() ? null : round($conNota->avg(), 1, PHP_ROUND_HALF_UP),
                'desaprobados' => $conNota->filter(fn ($n) => $n < $aprobatoria)->count(),
                'sin_entregar' => $filas->where('estado_entrega', 'NO_ENTREGADO')->count(),
            ],
            'estudiantes' => $filas->values()->all(),
        ];
    }

    /** Guarda las notas que llegan; un estudiante que no viene queda como estaba */
    public function guardar(int $evaluacionId, array $registros, Usuario $usuario): array
    {
        $ev = $this->evaluaciones->evaluacion($evaluacionId, $usuario);
        $aulaId = DB::table('curso_aula')->where('id', $ev->curso_aula_id)->value('aula_id');
        $alumnos = $this->filas($ev, $aulaId)->keyBy('matricula_id');
        $enviados = collect($registros)->keyBy('matricula_id');
        if ($enviados->keys()->diff($alumnos->keys())->isNotEmpty()) {
            throw new ErrorRegla('Hay estudiantes que no pertenecen a esta evaluación.', 'registros');
        }

        $cambios = DB::transaction(function () use ($ev, $enviados, $usuario) {
            $previas = DB::table('nota_evaluacion')->where('evaluacion_id', $ev->id)->lockForUpdate()->get()->keyBy('matricula_id');
            $cambios = [];
            foreach ($enviados as $matriculaId => $r) {
                $fila = ['nota' => $r['nota'], 'estado_entrega' => $r['estado_entrega'], 'observacion' => $r['observacion'] ?? null];
                $previa = $previas->get($matriculaId);
                if ($previa && Calificaciones::numero($previa->nota) === $fila['nota'] && $previa->estado_entrega === $fila['estado_entrega'] && $previa->observacion === $fila['observacion']) {
                    continue;
                }
                if ($previa) {
                    DB::table('nota_evaluacion')->where('id', $previa->id)->update($fila + ['registrado_por' => $usuario->id, 'updated_at' => now()]);
                } else {
                    DB::table('nota_evaluacion')->insert($fila + ['evaluacion_id' => $ev->id, 'matricula_id' => $matriculaId, 'registrado_por' => $usuario->id]);
                }
                $cambios[$matriculaId] = ['antes' => Calificaciones::numero($previa?->nota), 'despues' => $fila['nota']];
            }
            if ($cambios) {
                Auditoria::registrar('REGISTRAR_NOTAS', 'evaluacion', $ev->id, ['cambios' => $cambios]);
                $this->calificaciones->recalcular($ev->curso_aula_id, $ev->subperiodo_id);
            }

            return $cambios;
        });

        return ['mensaje' => $cambios ? 'Notas guardadas.' : 'No había cambios por guardar.'] + $this->planilla($evaluacionId, $usuario);
    }

    /** RF-43: planilla en Excel */
    public function excel(int $evaluacionId, Usuario $usuario): array
    {
        $p = $this->planilla($evaluacionId, $usuario);
        $filas = [['Código', 'Estudiante', 'Estado de entrega', 'Nota (0-20)', 'Observación']];
        foreach ($p['estudiantes'] as $e) {
            $filas[] = [$e['codigo'], $e['nombre'], self::NOMBRE_ESTADO[$e['estado_entrega']], $e['nota'], $e['observacion'] ?? ''];
        }
        $nombre = Excel::nombreArchivo("notas {$p['evaluacion']['titulo']} {$p['curso']['aula']}");

        return [Excel::escribir($filas, [12, 34, 18, 12, 40]), $nombre];
    }

    /** Matriculados (y quienes ya tienen nota) con su nota, su entrega y el estado de entrega */
    private function filas(object $ev, int $aulaId)
    {
        $alumnos = DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->where('m.aula_id', $aulaId)
            ->where(fn ($q) => $q->where('m.estado', 'ACTIVA')
                ->orWhereExists(fn ($s) => $s->from('nota_evaluacion')->whereColumn('nota_evaluacion.matricula_id', 'm.id')->where('nota_evaluacion.evaluacion_id', $ev->id)))
            ->orderBy('p.apellidos')->orderBy('p.nombres')
            ->get(['m.id as matricula_id', 'e.codigo', 'p.nombres', 'p.apellidos']);
        $notas = DB::table('nota_evaluacion')->where('evaluacion_id', $ev->id)->get()->keyBy('matricula_id');
        $entregas = DB::table('entrega as en')->leftJoin('archivo as a', 'a.id', '=', 'en.archivo_id')->where('en.evaluacion_id', $ev->id)
            ->get(['en.*', 'a.nombre_original', 'a.tamano_bytes', 'a.tipo_mime'])->keyBy('matricula_id');

        return $alumnos->map(function ($a) use ($ev, $notas, $entregas) {
            $nota = $notas->get($a->matricula_id);
            $entrega = $entregas->get($a->matricula_id);
            $sugerido = Evaluaciones::estadoSugerido($entrega, $ev->fecha_limite, (bool) $ev->requiere_entrega);

            return [
                'matricula_id' => $a->matricula_id,
                'nombre' => "{$a->apellidos}, {$a->nombres}",
                'codigo' => $a->codigo,
                'nota' => Calificaciones::numero($nota?->nota),
                'estado_entrega' => $nota->estado_entrega ?? $sugerido,
                'estado_sugerido' => $sugerido,
                'observacion' => $nota?->observacion,
                'entrega' => $entrega?->estado === 'ENTREGADA' ? [
                    'entregada_en' => $entrega->entregada_en,
                    'es_tardia' => (bool) $entrega->es_tardia,
                    'archivo' => $entrega->archivo_id ? ['id' => $entrega->archivo_id, 'nombre' => $entrega->nombre_original, 'tamano' => (int) $entrega->tamano_bytes, 'tipo' => $entrega->tipo_mime] : null,
                ] : null,
            ];
        });
    }
}
