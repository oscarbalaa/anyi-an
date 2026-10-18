<?php

namespace App\Servicios\Evaluacion;

use App\Exceptions\ErrorRegla;
use App\Models\Usuario;
use App\Servicios\Archivos;
use App\Support\Auditoria;
use App\Support\Calendario;
use App\Support\Cursos;
use App\Support\MiMatricula;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** CU-17: actividades del estudiante y su entrega (RF-40 a RF-42, RN-12, RN-26) */
class Entregas
{
    public function __construct(private readonly Archivos $archivos) {}

    public function actividades(Usuario $usuario, ?int $bimestreId): array
    {
        $m = MiMatricula::de($usuario);
        if (! $m) {
            return ['bimestre' => null, 'bimestres' => [], 'resumen' => null, 'actividades' => []];
        }
        $bimestres = Calendario::bimestres($m->periodo_id);
        $bimestre = $bimestreId ? $bimestres->firstWhere('id', $bimestreId) : Calendario::bimestreVigente($bimestres);
        if (! $bimestre) {
            throw new ErrorRegla('Elige un bimestre del periodo en curso.', 'bimestre_id');
        }

        $filas = $this->evaluaciones($m, $bimestre->id)->map(fn ($ev) => $this->datos($ev))->values();
        $pendientes = $filas->filter(fn ($a) => in_array($a['estado'], ['PENDIENTE', 'EN_PROGRESO'], true));
        $limite = today()->addDays(3)->toDateString();

        return [
            'bimestre' => Cursos::datosBimestre($bimestre),
            'bimestres' => $bimestres->map(fn ($b) => Cursos::datosBimestre($b))->all(),
            'resumen' => [
                'pendientes' => $pendientes->count(),
                'por_vencer' => $pendientes->filter(fn ($a) => $a['fecha_limite'] <= $limite)->count(),
                'completadas' => $filas->filter(fn ($a) => in_array($a['estado'], ['ENTREGADO', 'TARDIO'], true))->count(),
            ],
            'primera' => $pendientes->sortBy('fecha_limite')->first(),
            'actividades' => $filas->all(),
        ];
    }

    public function detalle(int $evaluacionId, Usuario $usuario): array
    {
        [$m, $ev] = $this->miEvaluacion($evaluacionId, $usuario);
        $entrega = $ev->entrega;
        $material = DB::table('evaluacion_material as em')->join('archivo as a', 'a.id', '=', 'em.archivo_id')
            ->where('em.evaluacion_id', $ev->id)->get(['a.*']);
        $bimestre = DB::table('subperiodo')->find($ev->subperiodo_id);
        $archivo = $entrega?->archivo_id ? DB::table('archivo')->find($entrega->archivo_id) : null;
        [$puede, $aviso] = $this->permiso($m, $ev, $bimestre);

        return $this->datos($ev) + [
            'indicaciones' => $ev->indicaciones,
            'bimestre' => Cursos::datosBimestre($bimestre),
            'material' => $material->map(fn ($a) => Archivos::datos($a))->all(),
            'entrega' => $entrega ? [
                'estado' => $entrega->estado,
                'entregada_en' => $entrega->entregada_en ? Carbon::parse($entrega->entregada_en)->toIso8601String() : null,
                'es_tardia' => (bool) $entrega->es_tardia,
                'archivo' => Archivos::datos($archivo),
            ] : null,
            'historial' => $this->historial($ev, $entrega),
            'puede_entregar' => $puede,
            'aviso' => $aviso,
            'limite_archivo' => Archivos::limiteTexto(),
        ];
    }

    /** "BORRADOR" guarda sin entregar; "ENTREGAR" entrega (tardía si ya pasó la fecha límite, RN-12) */
    public function guardar(int $evaluacionId, Usuario $usuario, ?UploadedFile $archivo, string $accion): array
    {
        [$m, $ev] = $this->miEvaluacion($evaluacionId, $usuario);
        [$puede, $aviso] = $this->permiso($m, $ev, DB::table('subperiodo')->find($ev->subperiodo_id));
        if (! $puede) {
            throw new ErrorRegla($aviso);
        }
        if ($archivo) {
            $this->archivos->validarPdf($archivo);
        }
        $previa = $ev->entrega;
        if (! $archivo && ! $previa?->archivo_id) {
            throw new ErrorRegla('Adjunta tu archivo PDF.', 'archivo');
        }

        $nuevoArchivo = $archivo ? $this->archivos->guardar($archivo, "entregas/{$ev->id}", $usuario->id) : null;
        $anterior = $nuevoArchivo ? $previa?->archivo_id : null;
        $tardia = today()->toDateString() > $ev->fecha_limite;

        DB::transaction(function () use ($ev, $m, $previa, $nuevoArchivo, $accion, $tardia, $usuario) {
            $fila = ['archivo_id' => $nuevoArchivo ?? $previa->archivo_id, 'updated_at' => now()];
            if ($accion === 'ENTREGAR') {
                $fila += ['estado' => 'ENTREGADA', 'entregada_en' => now(), 'es_tardia' => $tardia];
            }
            if ($previa) {
                DB::table('entrega')->where('id', $previa->id)->update($fila);
                $id = $previa->id;
            } else {
                $id = DB::table('entrega')->insertGetId($fila + ['evaluacion_id' => $ev->id, 'matricula_id' => $m->id, 'estado' => 'BORRADOR']);
            }

            if ($accion === 'ENTREGAR') {
                // El estado de entrega se calcula solo; el docente puede corregirlo después (RF-42)
                $estado = $tardia ? 'TARDIO' : 'ENTREGADO';
                $nota = DB::table('nota_evaluacion')->where('evaluacion_id', $ev->id)->where('matricula_id', $m->id)->first();
                if ($nota) {
                    DB::table('nota_evaluacion')->where('id', $nota->id)->update(['estado_entrega' => $estado, 'updated_at' => now()]);
                } else {
                    DB::table('nota_evaluacion')->insert(['evaluacion_id' => $ev->id, 'matricula_id' => $m->id, 'estado_entrega' => $estado]);
                }
            }
            Auditoria::registrar($accion === 'ENTREGAR' ? 'ENTREGAR' : 'GUARDAR_BORRADOR', 'entrega', $id, ['tardia' => $accion === 'ENTREGAR' ? $tardia : null], $usuario->id);
        });
        if ($anterior) {
            $this->archivos->borrar($anterior);
        }

        $mensaje = match (true) {
            $accion === 'BORRADOR' => 'Borrador guardado. Aún no lo ve tu docente: recuerda entregarlo.',
            $tardia => 'Actividad entregada fuera de plazo: queda como tardía.',
            default => '¡Actividad entregada!',
        };

        return ['mensaje' => $mensaje] + $this->detalle($evaluacionId, $usuario);
    }

    /** @return array{object, object} matrícula y evaluación (con su entrega) de un curso de su aula, ya asignada */
    private function miEvaluacion(int $evaluacionId, Usuario $usuario): array
    {
        $m = MiMatricula::de($usuario) ?? abort(404);
        $ev = $this->evaluaciones($m, null)->firstWhere('id', $evaluacionId) ?? abort(404);

        return [$m, $ev];
    }

    /** @return array{bool, string} */
    private function permiso(object $m, object $ev, object $bimestre): array
    {
        return match (true) {
            ! $ev->requiere_entrega => [false, 'Esta evaluación se rinde en clase: no se entrega un archivo.'],
            $bimestre->estado === 'CERRADO' => [false, "El {$bimestre->nombre} está cerrado: ya no se reciben entregas."],
            $m->estado !== 'ACTIVA' => [false, 'Tu matrícula no está activa: no puedes entregar.'],
            $ev->entrega?->estado === 'ENTREGADA' => [false, 'Ya entregaste esta actividad. Si necesitas cambiarla, habla con tu docente.'],
            today()->toDateString() > $ev->fecha_limite => [true, 'La fecha límite ya pasó: si entregas ahora, quedará como tardía.'],
            default => [true, 'Puedes entregar hasta las 11:59 p. m. del día límite.'],
        };
    }

    /** Evaluaciones ya asignadas de los cursos del aula, con su entrega, su nota y si el promedio del curso está publicado */
    public function evaluaciones(object $m, ?int $bimestreId): Collection
    {
        $evaluaciones = DB::table('evaluacion as ev')
            ->join('tipo_evaluacion as t', 't.id', '=', 'ev.tipo_evaluacion_id')
            ->join('curso_aula as c', 'c.id', '=', 'ev.curso_aula_id')
            ->join('asignatura as asig', 'asig.id', '=', 'c.asignatura_id')
            ->join('docente as d', 'd.id', '=', 'c.docente_id')->join('persona as p', 'p.id', '=', 'd.persona_id')
            ->where('c.aula_id', $m->aula_id)->where('ev.fecha_asignacion', '<=', today()->toDateString())
            ->when($bimestreId, fn ($q) => $q->where('ev.subperiodo_id', $bimestreId))
            ->orderBy('ev.fecha_limite')->orderBy('ev.id')
            ->get(['ev.*', 't.nombre as tipo', 'asig.nombre as asignatura', 'p.nombres', 'p.apellidos']);

        $ids = $evaluaciones->pluck('id');
        $entregas = DB::table('entrega')->where('matricula_id', $m->id)->whereIn('evaluacion_id', $ids)->get()->keyBy('evaluacion_id');
        $notas = DB::table('nota_evaluacion')->where('matricula_id', $m->id)->whereIn('evaluacion_id', $ids)->get()->keyBy('evaluacion_id');
        $publicados = DB::table('calificacion_periodo')->where('matricula_id', $m->id)->where('estado', '!=', 'BORRADOR')
            ->get(['curso_aula_id', 'subperiodo_id'])->map(fn ($c) => "{$c->curso_aula_id}-{$c->subperiodo_id}")->flip();
        $pesos = DB::table('evaluacion')->whereIn('curso_aula_id', $evaluaciones->pluck('curso_aula_id')->unique())
            ->selectRaw('curso_aula_id, subperiodo_id, SUM(peso) as total')->groupBy('curso_aula_id', 'subperiodo_id')->get()
            ->mapWithKeys(fn ($p) => ["{$p->curso_aula_id}-{$p->subperiodo_id}" => (int) $p->total]);

        return $evaluaciones->map(function ($ev) use ($entregas, $notas, $publicados, $pesos) {
            $clave = "{$ev->curso_aula_id}-{$ev->subperiodo_id}";
            $ev->entrega = $entregas->get($ev->id);
            $ev->nota_registro = $notas->get($ev->id);
            $ev->publicada = $publicados->has($clave);
            $ev->peso_total = $pesos[$clave] ?? (int) $ev->peso;

            return $ev;
        });
    }

    public function datos(object $ev): array
    {
        $registrado = $ev->nota_registro?->estado_entrega;
        $estado = match (true) {
            in_array($registrado, ['ENTREGADO', 'TARDIO', 'NO_ENTREGADO'], true) => $registrado,
            $ev->entrega?->estado === 'BORRADOR' => 'EN_PROGRESO',
            ! $ev->requiere_entrega => 'EN_CLASE',
            default => Evaluaciones::estadoSugerido($ev->entrega, $ev->fecha_limite, true),
        };

        return [
            'id' => $ev->id,
            'titulo' => $ev->titulo,
            'tipo' => $ev->tipo,
            'curso' => $ev->asignatura,
            'curso_id' => $ev->curso_aula_id,
            'docente' => Cursos::nombreCorto($ev->nombres, $ev->apellidos),
            'fecha_asignacion' => $ev->fecha_asignacion,
            'fecha_limite' => $ev->fecha_limite,
            'peso' => (int) $ev->peso,
            'peso_porcentaje' => (int) round(100 * $ev->peso / max(1, $ev->peso_total)),
            'requiere_entrega' => (bool) $ev->requiere_entrega,
            'estado' => $estado,
            // RN-13: el estudiante solo ve notas de cursos con el promedio publicado
            'nota' => $ev->publicada ? Calificaciones::numero($ev->nota_registro?->nota) : null,
        ];
    }

    private function historial(object $ev, ?object $entrega): array
    {
        $eventos = [['fecha' => Carbon::parse($ev->fecha_asignacion)->toIso8601String(), 'texto' => 'Actividad asignada']];
        if ($entrega) {
            $registros = DB::table('auditoria')->where('entidad', 'entrega')->where('entidad_id', $entrega->id)->orderBy('id')->get(['accion', 'detalle', 'created_at']);
            foreach ($registros as $r) {
                $tardia = json_decode($r->detalle ?? '{}', true)['tardia'] ?? false;
                $eventos[] = [
                    'fecha' => Carbon::parse($r->created_at)->toIso8601String(),
                    'texto' => match ($r->accion) {
                        'ENTREGAR' => $tardia ? 'Entregada fuera de plazo (tardía)' : 'Actividad entregada',
                        default => 'Borrador guardado',
                    },
                ];
            }
            // Entregas cargadas sin auditoría (datos de demostración)
            if ($entrega->entregada_en && ! $registros->contains('accion', 'ENTREGAR')) {
                $eventos[] = ['fecha' => Carbon::parse($entrega->entregada_en)->toIso8601String(), 'texto' => $entrega->es_tardia ? 'Entregada fuera de plazo (tardía)' : 'Actividad entregada'];
            }
        }
        if ($ev->publicada && $ev->nota_registro?->nota !== null) {
            $eventos[] = ['fecha' => Carbon::parse($ev->nota_registro->updated_at)->toIso8601String(), 'texto' => 'Calificada: '.rtrim(rtrim(number_format((float) $ev->nota_registro->nota, 2, ',', ''), '0'), ',')];
        }

        return $eventos;
    }
}
