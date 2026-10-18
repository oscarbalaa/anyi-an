<?php

namespace App\Servicios\Personas;

use App\Exceptions\ErrorRegla;
use App\Support\Auditoria;
use App\Support\PerfilUsuario;
use App\Support\ReglasPersona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** CU-12: matrículas (RF-31, RF-32, RN-04, RN-23) */
class Matriculas
{
    public const POR_PAGINA = 10;

    /** Tablas con datos del año del estudiante: si hay alguno, la matrícula ya no se anula (se retira) */
    private const CON_DATOS = ['asistencia', 'nota_evaluacion', 'entrega', 'calificacion_periodo', 'incidencia'];

    public function listar(array $f): array
    {
        $q = DB::table('matricula as m')
            ->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')
            ->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->join('aula as a', 'a.id', '=', 'm.aula_id')
            ->join('grado as g', 'g.id', '=', 'a.grado_id')
            ->join('seccion as s', 's.id', '=', 'a.seccion_id')
            ->where('m.periodo_id', $f['periodo_id'])
            ->when($f['aula_id'] ?? null, fn ($q, $aula) => $q->where('m.aula_id', $aula))
            ->when($f['estado'] ?? null, fn ($q, $estado) => $q->where('m.estado', $estado));
        if ($t = trim($f['buscar'] ?? '')) {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('e.codigo', 'like', $like)->orWhere('p.numero_documento', 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.nombres, ' ', p.apellidos)"), 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.apellidos, ' ', p.nombres)"), 'like', $like));
        }
        $pagina = $q->orderBy('g.orden')->orderBy('s.nombre')->orderBy('p.apellidos')->orderBy('p.nombres')
            ->paginate(self::POR_PAGINA, [
                'm.id', 'm.fecha', 'm.estado', 'm.motivo', 'e.id as estudiante_id', 'e.codigo',
                'p.nombres', 'p.apellidos', 'g.numero', 'g.nivel', 's.nombre as seccion',
            ], 'pagina', (int) ($f['pagina'] ?? 1));

        return [
            'datos' => collect($pagina->items())->map(fn ($m) => [
                'id' => $m->id,
                'estudiante' => ['id' => $m->estudiante_id, 'nombre' => "{$m->nombres} {$m->apellidos}", 'codigo' => $m->codigo],
                'aula' => PerfilUsuario::nombreAula($m->numero, $m->nivel, $m->seccion),
                'fecha' => $m->fecha,
                'estado' => $m->estado,
                'motivo' => $m->motivo,
            ])->all(),
            'pagina' => $pagina->currentPage(),
            'por_pagina' => $pagina->perPage(),
            'total' => $pagina->total(),
            'ultima_pagina' => $pagina->lastPage(),
        ];
    }

    /** Aulas del periodo con sus cupos */
    public function aulas(int $periodoId): array
    {
        $ocupados = DB::table('matricula')->where('periodo_id', $periodoId)->where('estado', 'ACTIVA')
            ->selectRaw('aula_id, COUNT(*) as total')->groupBy('aula_id')->pluck('total', 'aula_id');

        return DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('aula.periodo_id', $periodoId)->orderBy('grado.orden')->orderBy('seccion.nombre')
            ->get(['aula.id', 'aula.capacidad', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'nombre' => PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion),
                'nivel' => $a->nivel,
                'capacidad' => $a->capacidad,
                'matriculados' => (int) ($ocupados[$a->id] ?? 0),
            ])->all();
    }

    /** Estudiantes activos que aún no tienen matrícula activa en el periodo */
    public function candidatos(int $periodoId, string $buscar): array
    {
        $q = DB::table('estudiante as e')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->where('e.estado', 'ACTIVO')
            ->whereNotExists(fn ($s) => $s->from('matricula')->whereColumn('matricula.estudiante_id', 'e.id')
                ->where('matricula.periodo_id', $periodoId)->where('matricula.estado', 'ACTIVA'));
        if ($t = trim($buscar)) {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('e.codigo', 'like', $like)->orWhere('p.numero_documento', 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.nombres, ' ', p.apellidos)"), 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.apellidos, ' ', p.nombres)"), 'like', $like));
        }

        return $q->orderBy('p.apellidos')->orderBy('p.nombres')->limit(20)
            ->get(['e.id', 'e.codigo', 'p.nombres', 'p.apellidos'])
            ->map(fn ($e) => ['id' => $e->id, 'nombre' => "{$e->apellidos}, {$e->nombres}", 'codigo' => $e->codigo])->all();
    }

    /**
     * RN-04: una matrícula por periodo y sin pasar la capacidad del aula. RN-23: edad coherente con el nivel.
     * Si el estudiante tuvo una matrícula retirada o anulada en el periodo, se reactiva esa misma.
     */
    public function matricular(int $estudianteId, int $aulaId, string $fecha, ?int $adminId): int
    {
        $aula = DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->join('periodo_academico as p', 'p.id', '=', 'aula.periodo_id')->where('aula.id', $aulaId)
            ->first(['aula.id', 'aula.periodo_id', 'aula.capacidad', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion', 'p.estado as periodo_estado', 'p.fecha_fin', 'p.nombre as periodo'])
            ?? abort(404);
        $nombreAula = PerfilUsuario::nombreAula($aula->numero, $aula->nivel, $aula->seccion);
        $estudiante = DB::table('estudiante')->join('persona', 'persona.id', '=', 'estudiante.persona_id')
            ->where('estudiante.id', $estudianteId)->first(['estudiante.estado', 'persona.fecha_nacimiento']) ?? abort(404);

        if ($aula->periodo_estado === 'CERRADO') {
            throw new ErrorRegla('El periodo está cerrado.', 'aula_id');
        }
        if ($estudiante->estado !== 'ACTIVO') {
            throw new ErrorRegla('Solo se matriculan estudiantes activos.', 'estudiante_id');
        }
        if ($fecha > today()->toDateString() || $fecha > $aula->fecha_fin) {
            throw new ErrorRegla('La fecha de matrícula no puede ser futura ni posterior al fin del periodo.', 'fecha');
        }
        if ($error = ReglasPersona::edadIncoherente($estudiante->fecha_nacimiento, $aula->nivel, $fecha)) {
            throw new ErrorRegla($error, 'aula_id');
        }

        return DB::transaction(function () use ($estudianteId, $aula, $fecha, $adminId, $nombreAula) {
            $previa = DB::table('matricula')->where('estudiante_id', $estudianteId)->where('periodo_id', $aula->periodo_id)->lockForUpdate()->first();
            if ($previa?->estado === 'ACTIVA') {
                $actual = PerfilUsuario::aulas([$estudianteId])[$estudianteId] ?? 'otra aula';
                throw new ErrorRegla("El estudiante ya está matriculado en {$actual} en {$aula->periodo} (RN-04).", 'estudiante_id');
            }
            $ocupados = DB::table('matricula')->where('aula_id', $aula->id)->where('estado', 'ACTIVA')->lockForUpdate()->count();
            if ($ocupados >= $aula->capacidad) {
                throw new ErrorRegla("El aula {$nombreAula} está llena ({$ocupados}/{$aula->capacidad}).", 'aula_id');
            }

            $fila = ['aula_id' => $aula->id, 'fecha' => $fecha, 'estado' => 'ACTIVA', 'motivo' => null, 'registrado_por' => $adminId, 'updated_at' => now()];
            if ($previa) {
                DB::table('matricula')->where('id', $previa->id)->update($fila);
                Auditoria::registrar('REACTIVAR', 'matricula', $previa->id, ['aula' => $nombreAula, 'antes' => $previa->estado]);

                return $previa->id;
            }
            $id = DB::table('matricula')->insertGetId($fila + ['estudiante_id' => $estudianteId, 'periodo_id' => $aula->periodo_id, 'created_at' => now()]);
            Auditoria::registrar('CREAR', 'matricula', $id, ['aula' => $nombreAula, 'fecha' => $fecha]);

            return $id;
        });
    }

    /** RF-32: retirar (dejó de asistir, traslado) o anular (se registró por error) */
    public function cerrar(int $id, string $estado, string $motivo): void
    {
        $m = DB::table('matricula')->find($id) ?? abort(404);
        if ($m->estado !== 'ACTIVA') {
            throw new ErrorRegla('Solo se retira o anula una matrícula activa.');
        }
        if ($estado === 'ANULADA') {
            foreach (self::CON_DATOS as $tabla) {
                if (DB::table($tabla)->where('matricula_id', $id)->exists()) {
                    throw new ErrorRegla('La matrícula ya tiene asistencia, notas o incidencias: no se anula, se retira.');
                }
            }
        }
        DB::table('matricula')->where('id', $id)->update(['estado' => $estado, 'motivo' => $motivo, 'updated_at' => now()]);
        Auditoria::registrar($estado === 'RETIRADA' ? 'RETIRAR' : 'ANULAR', 'matricula', $id, ['motivo' => $motivo]);
    }

    public static function hoy(): string
    {
        return Carbon::today()->toDateString();
    }
}
