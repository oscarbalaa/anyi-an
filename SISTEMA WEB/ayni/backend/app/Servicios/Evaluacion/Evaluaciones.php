<?php

namespace App\Servicios\Evaluacion;

use App\Exceptions\ErrorRegla;
use App\Models\Usuario;
use App\Servicios\Archivos;
use App\Support\Auditoria;
use App\Support\Calendario;
use App\Support\Cursos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** CU-16: evaluaciones del curso y su material de apoyo (RF-38, RF-39) */
class Evaluaciones
{
    public function __construct(private readonly Archivos $archivos, private readonly Calificaciones $calificaciones) {}

    /** RN-13: con el bimestre cerrado no se cambian evaluaciones, entregas ni notas */
    public static function exigirAbierto(object $bimestre): void
    {
        if ($bimestre->estado === 'CERRADO') {
            throw new ErrorRegla("El {$bimestre->nombre} está cerrado: sus evaluaciones y notas ya no se modifican.");
        }
    }

    /** RF-42 / RN-12: estado de entrega según la entrega y la fecha límite */
    public static function estadoSugerido(?object $entrega, string $fechaLimite, bool $requiereEntrega): string
    {
        if ($entrega?->estado === 'ENTREGADA') {
            return $entrega->es_tardia ? 'TARDIO' : 'ENTREGADO';
        }
        if (! $requiereEntrega) {
            return 'PENDIENTE';
        }

        return $fechaLimite < today()->toDateString() ? 'NO_ENTREGADO' : 'PENDIENTE';
    }

    public function listar(int $cursoId, ?int $bimestreId, Usuario $usuario): array
    {
        $curso = Cursos::autorizado($cursoId, $usuario);
        $bimestres = Calendario::bimestres($curso->periodo_id);
        $bimestre = $bimestreId ? $bimestres->firstWhere('id', $bimestreId) : Calendario::bimestreVigente($bimestres);
        if (! $bimestre) {
            throw new ErrorRegla('Elige un bimestre del periodo del curso.', 'bimestre_id');
        }

        $evaluaciones = DB::table('evaluacion as ev')->join('tipo_evaluacion as t', 't.id', '=', 'ev.tipo_evaluacion_id')
            ->where('ev.curso_aula_id', $cursoId)->where('ev.subperiodo_id', $bimestre->id)
            ->orderByDesc('ev.fecha_limite')->orderByDesc('ev.id')
            ->get(['ev.*', 't.nombre as tipo']);
        $ids = $evaluaciones->pluck('id');
        $activos = DB::table('matricula')->where('aula_id', $curso->aula_id)->where('estado', 'ACTIVA')->pluck('id');
        $entregas = DB::table('entrega')->whereIn('evaluacion_id', $ids)->whereIn('matricula_id', $activos)->where('estado', 'ENTREGADA')
            ->selectRaw('evaluacion_id, COUNT(*) as total')->groupBy('evaluacion_id')->pluck('total', 'evaluacion_id');
        $calificadas = DB::table('nota_evaluacion')->whereIn('evaluacion_id', $ids)->whereIn('matricula_id', $activos)->whereNotNull('nota')
            ->selectRaw('evaluacion_id, COUNT(*) as total')->groupBy('evaluacion_id')->pluck('total', 'evaluacion_id');
        $material = DB::table('evaluacion_material as em')->join('archivo as a', 'a.id', '=', 'em.archivo_id')->whereIn('em.evaluacion_id', $ids)
            ->get(['em.evaluacion_id', 'a.*'])->groupBy('evaluacion_id');

        $hoy = today()->toDateString();
        $total = $activos->count();
        $filas = $evaluaciones->map(fn ($ev) => [
            'id' => $ev->id,
            'titulo' => $ev->titulo,
            'tipo' => $ev->tipo,
            'tipo_evaluacion_id' => $ev->tipo_evaluacion_id,
            'peso' => (int) $ev->peso,
            'fecha_asignacion' => $ev->fecha_asignacion,
            'fecha_limite' => $ev->fecha_limite,
            'indicaciones' => $ev->indicaciones,
            'requiere_entrega' => (bool) $ev->requiere_entrega,
            'momento' => $ev->fecha_asignacion > $hoy ? 'POR_ASIGNAR' : ($ev->fecha_limite < $hoy ? 'VENCIDA' : 'EN_CURSO'),
            'entregas' => (int) ($entregas[$ev->id] ?? 0),
            'calificadas' => (int) ($calificadas[$ev->id] ?? 0),
            'estudiantes' => $total,
            'material' => ($material[$ev->id] ?? collect())->map(fn ($a) => Archivos::datos($a))->values()->all(),
        ]);
        $conEntrega = $filas->filter(fn ($f) => $f['requiere_entrega'] && $f['momento'] !== 'POR_ASIGNAR');
        $esperadas = $conEntrega->count() * $total;

        return [
            'curso' => Cursos::datos($curso),
            'bimestre' => Cursos::datosBimestre($bimestre),
            'bimestres' => $bimestres->map(fn ($b) => Cursos::datosBimestre($b))->all(),
            'tipos' => DB::table('tipo_evaluacion')->where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre'])->all(),
            'resumen' => [
                'evaluaciones' => $filas->count(),
                'entregas' => $esperadas ? (int) round(100 * $conEntrega->sum('entregas') / $esperadas) : null,
                'por_calificar' => $filas->filter(fn ($f) => $f['momento'] === 'VENCIDA' && $f['calificadas'] < $total)->count(),
            ],
            'evaluaciones' => $filas->values()->all(),
            'editable' => $bimestre->estado === 'ABIERTO',
            'limite_archivo' => Archivos::limiteTexto(),
        ];
    }

    public function guardar(array $d, Usuario $usuario, ?int $cursoId = null, ?int $evaluacionId = null): int
    {
        $previa = $evaluacionId ? (DB::table('evaluacion')->find($evaluacionId) ?? abort(404)) : null;
        $curso = Cursos::autorizado($previa->curso_aula_id ?? $cursoId, $usuario);
        $bimestre = DB::table('subperiodo')->where('id', $d['subperiodo_id'])->where('periodo_id', $curso->periodo_id)->first()
            ?? throw new ErrorRegla('Elige un bimestre del periodo del curso.', 'subperiodo_id');
        self::exigirAbierto($bimestre);
        if ($previa && $previa->subperiodo_id !== $bimestre->id) {
            self::exigirAbierto(DB::table('subperiodo')->find($previa->subperiodo_id));
            if ($this->tieneTrabajo($previa->id)) {
                throw new ErrorRegla('La evaluación ya tiene entregas o notas: no se cambia de bimestre.', 'subperiodo_id');
            }
        }
        if ($d['fecha_asignacion'] < $bimestre->fecha_inicio || $d['fecha_limite'] > $bimestre->fecha_fin) {
            $rango = date('d/m', strtotime($bimestre->fecha_inicio)).' – '.date('d/m', strtotime($bimestre->fecha_fin));
            throw new ErrorRegla("Las fechas deben estar dentro del {$bimestre->nombre} ({$rango}).", $d['fecha_asignacion'] < $bimestre->fecha_inicio ? 'fecha_asignacion' : 'fecha_limite');
        }
        if (DB::table('tipo_evaluacion')->where('id', $d['tipo_evaluacion_id'])->value('estado') !== 'ACTIVO') {
            throw new ErrorRegla('Elige un tipo de evaluación activo.', 'tipo_evaluacion_id');
        }

        $fila = [
            'subperiodo_id' => $bimestre->id,
            'tipo_evaluacion_id' => $d['tipo_evaluacion_id'],
            'titulo' => $d['titulo'],
            'indicaciones' => $d['indicaciones'] ?? null,
            'fecha_asignacion' => $d['fecha_asignacion'],
            'fecha_limite' => $d['fecha_limite'],
            'peso' => $d['peso'],
            'requiere_entrega' => (bool) $d['requiere_entrega'],
        ];

        $id = DB::transaction(function () use ($previa, $fila, $curso, $usuario) {
            if ($previa) {
                DB::table('evaluacion')->where('id', $previa->id)->update($fila + ['updated_at' => now()]);
                $cambios = collect($fila)->filter(fn ($v, $k) => (string) $previa->{$k} !== (string) $v)
                    ->map(fn ($v, $k) => ['antes' => $previa->{$k}, 'despues' => $v])->all();
                Auditoria::registrar('ACTUALIZAR', 'evaluacion', $previa->id, $cambios);
                $id = $previa->id;
            } else {
                $id = DB::table('evaluacion')->insertGetId($fila + ['curso_aula_id' => $curso->id, 'creado_por' => $usuario->id]);
                Auditoria::registrar('CREAR', 'evaluacion', $id, ['titulo' => $fila['titulo'], 'peso' => $fila['peso']]);
            }
            // El peso o el bimestre cambian los promedios (RN-09)
            $this->calificaciones->recalcular($curso->id, $fila['subperiodo_id']);
            if ($previa && $previa->subperiodo_id !== $fila['subperiodo_id']) {
                $this->calificaciones->recalcular($curso->id, $previa->subperiodo_id);
            }

            return $id;
        });

        return $id;
    }

    public function eliminar(int $evaluacionId, Usuario $usuario): void
    {
        $ev = $this->evaluacion($evaluacionId, $usuario);
        if ($this->tieneTrabajo($ev->id)) {
            throw new ErrorRegla('La evaluación ya tiene entregas o notas: no se puede eliminar.');
        }
        $material = DB::table('evaluacion_material')->where('evaluacion_id', $ev->id)->pluck('archivo_id');
        DB::transaction(function () use ($ev) {
            DB::table('nota_evaluacion')->where('evaluacion_id', $ev->id)->delete();
            DB::table('evaluacion')->where('id', $ev->id)->delete();
            Auditoria::registrar('ELIMINAR', 'evaluacion', $ev->id, ['titulo' => $ev->titulo]);
            $this->calificaciones->recalcular($ev->curso_aula_id, $ev->subperiodo_id);
        });
        foreach ($material as $archivoId) {
            $this->archivos->borrar($archivoId);
        }
    }

    public function subirMaterial(int $evaluacionId, UploadedFile $archivo, Usuario $usuario): int
    {
        $ev = $this->evaluacion($evaluacionId, $usuario);
        $this->archivos->validarMaterial($archivo);
        if (DB::table('evaluacion_material')->where('evaluacion_id', $ev->id)->count() >= 5) {
            throw new ErrorRegla('Cada evaluación tiene hasta 5 archivos de apoyo.', 'archivo');
        }
        $archivoId = $this->archivos->guardar($archivo, "material/{$ev->id}", $usuario->id);
        DB::table('evaluacion_material')->insert(['evaluacion_id' => $ev->id, 'archivo_id' => $archivoId]);
        Auditoria::registrar('SUBIR_MATERIAL', 'evaluacion', $ev->id, ['archivo' => $archivo->getClientOriginalName()]);

        return $archivoId;
    }

    public function quitarMaterial(int $evaluacionId, int $archivoId, Usuario $usuario): void
    {
        $ev = $this->evaluacion($evaluacionId, $usuario);
        abort_unless(DB::table('evaluacion_material')->where('evaluacion_id', $ev->id)->where('archivo_id', $archivoId)->delete(), 404);
        $this->archivos->borrar($archivoId);
        Auditoria::registrar('QUITAR_MATERIAL', 'evaluacion', $ev->id, ['archivo_id' => $archivoId]);
    }

    /** La evaluación de un curso del docente, con su bimestre abierto */
    public function evaluacion(int $id, Usuario $usuario, bool $exigirAbierto = true): object
    {
        $ev = DB::table('evaluacion')->find($id) ?? abort(404);
        Cursos::autorizado($ev->curso_aula_id, $usuario);
        if ($exigirAbierto) {
            self::exigirAbierto(DB::table('subperiodo')->find($ev->subperiodo_id));
        }

        return $ev;
    }

    private function tieneTrabajo(int $evaluacionId): bool
    {
        return DB::table('entrega')->where('evaluacion_id', $evaluacionId)->exists()
            || DB::table('nota_evaluacion')->where('evaluacion_id', $evaluacionId)->whereNotNull('nota')->exists();
    }
}
