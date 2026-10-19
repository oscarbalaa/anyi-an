<?php

namespace App\Servicios;

use App\Models\Usuario;
use App\Support\Calendario;
use App\Support\Cursos;
use App\Support\MiMatricula;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CU-23: campana de notificaciones (RF-51). Las de un evento se crean en el momento;
 * "actividad por vencer" y "cierre de bimestre próximo" se calculan al abrir la campana (sin cron).
 */
class Notificaciones
{
    public const POR_PAGINA = 15;

    /** Días antes de la fecha límite en que se avisa al estudiante */
    private const DIAS_POR_VENCER = 2;

    /** Días antes del cierre de notas en que se avisa al docente */
    private const DIAS_CIERRE = 7;

    /** @param iterable<int> $usuarios */
    public static function enviar(iterable $usuarios, string $tipo, string $titulo, string $mensaje, ?string $enlace = null): void
    {
        $filas = [];
        foreach (array_unique(array_filter(is_array($usuarios) ? $usuarios : iterator_to_array($usuarios))) as $usuarioId) {
            $filas[] = [
                'usuario_id' => $usuarioId,
                'tipo' => $tipo,
                'titulo' => mb_substr($titulo, 0, 150),
                'mensaje' => mb_substr($mensaje, 0, 400),
                'enlace' => $enlace,
            ];
        }
        if ($filas) {
            DB::table('notificacion')->insert($filas);
        }
    }

    public function listar(Usuario $usuario, string $filtro, int $pagina): array
    {
        $this->sincronizar($usuario);
        $base = DB::table('notificacion')->where('usuario_id', $usuario->id);
        $pendientes = (clone $base)->whereNull('leida_en')->count();
        $todas = (clone $base)->count();

        $lista = (clone $base)
            ->when($filtro === 'pendientes', fn ($q) => $q->whereNull('leida_en'))
            ->when($filtro === 'leidas', fn ($q) => $q->whereNotNull('leida_en'))
            ->orderByRaw('leida_en IS NOT NULL')->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::POR_PAGINA, ['*'], 'pagina', $pagina);

        return [
            'datos' => collect($lista->items())->map(fn ($n) => self::datos($n))->all(),
            'pagina' => $lista->currentPage(),
            'por_pagina' => $lista->perPage(),
            'total' => $lista->total(),
            'ultima_pagina' => $lista->lastPage(),
            'conteo' => ['todas' => $todas, 'pendientes' => $pendientes, 'leidas' => $todas - $pendientes],
        ];
    }

    /** Para la campana: cuántas sin leer y las últimas 5 */
    public function resumen(Usuario $usuario): array
    {
        $this->sincronizar($usuario);

        return [
            'pendientes' => DB::table('notificacion')->where('usuario_id', $usuario->id)->whereNull('leida_en')->count(),
            'recientes' => DB::table('notificacion')->where('usuario_id', $usuario->id)
                ->orderByRaw('leida_en IS NOT NULL')->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get()
                ->map(fn ($n) => self::datos($n))->all(),
        ];
    }

    /** Solo las propias: la de otro usuario responde 404 */
    public function leer(int $id, Usuario $usuario): void
    {
        $cambiadas = DB::table('notificacion')->where('id', $id)->where('usuario_id', $usuario->id)->whereNull('leida_en')->update(['leida_en' => now()]);
        abort_unless($cambiadas || DB::table('notificacion')->where('id', $id)->where('usuario_id', $usuario->id)->exists(), 404);
    }

    public function leerTodas(Usuario $usuario): int
    {
        return DB::table('notificacion')->where('usuario_id', $usuario->id)->whereNull('leida_en')->update(['leida_en' => now()]);
    }

    /** Avisos que dependen de la fecha: se crean una sola vez, cuando corresponde */
    public function sincronizar(Usuario $usuario): void
    {
        match ($usuario->rol->codigo) {
            'ESTUDIANTE' => $this->porVencer($usuario),
            'DOCENTE' => $this->cierreProximo($usuario),
            default => null,
        };
    }

    private function porVencer(Usuario $usuario): void
    {
        $m = MiMatricula::de($usuario);
        if (! $m || $m->estado !== 'ACTIVA') {
            return;
        }
        $hoy = today();
        $evaluaciones = DB::table('evaluacion as ev')->join('curso_aula as c', 'c.id', '=', 'ev.curso_aula_id')
            ->join('asignatura as a', 'a.id', '=', 'c.asignatura_id')->join('subperiodo as b', 'b.id', '=', 'ev.subperiodo_id')
            ->where('c.aula_id', $m->aula_id)->where('b.estado', 'ABIERTO')->where('ev.requiere_entrega', true)
            ->where('ev.fecha_asignacion', '<=', $hoy->toDateString())
            ->whereBetween('ev.fecha_limite', [$hoy->toDateString(), $hoy->copy()->addDays(self::DIAS_POR_VENCER)->toDateString()])
            ->whereNotExists(fn ($q) => $q->from('entrega')->whereColumn('entrega.evaluacion_id', 'ev.id')->where('entrega.matricula_id', $m->id)->where('entrega.estado', 'ENTREGADA'))
            ->get(['ev.id', 'ev.titulo', 'ev.fecha_limite', 'a.nombre as curso']);
        $avisadas = DB::table('notificacion')->where('usuario_id', $usuario->id)->where('tipo', 'ACTIVIDAD_POR_VENCER')->pluck('enlace')->flip();

        foreach ($evaluaciones as $ev) {
            $enlace = "/estudiante/actividades?id={$ev->id}";
            if (! $avisadas->has($enlace)) {
                $cuando = Carbon::parse($ev->fecha_limite)->isSameDay($hoy) ? 'hoy' : 'el '.Carbon::parse($ev->fecha_limite)->locale('es')->isoFormat('dddd D [de] MMMM');
                self::enviar([$usuario->id], 'ACTIVIDAD_POR_VENCER', 'Actividad por vencer', "{$ev->titulo} ({$ev->curso}) vence {$cuando} a las 11:59 p. m.", $enlace);
            }
        }
    }

    private function cierreProximo(Usuario $usuario): void
    {
        $periodo = Calendario::periodoEnCurso();
        $docenteId = Cursos::docenteDe($usuario);
        $bimestre = $periodo ? Calendario::bimestreVigente(Calendario::bimestres($periodo->id)) : null;
        if (! $bimestre || ! $docenteId || $bimestre->estado !== 'ABIERTO') {
            return;
        }
        $dias = (int) today()->diffInDays(Carbon::parse($bimestre->fecha_cierre_notas), false);
        if ($dias < 0 || $dias > self::DIAS_CIERRE) {
            return;
        }
        $enlace = "/docente/calificaciones?bimestre={$bimestre->id}";
        if (DB::table('notificacion')->where('usuario_id', $usuario->id)->where('tipo', 'CIERRE_PROXIMO')->where('enlace', $enlace)->exists()) {
            return;
        }
        // Cursos del docente que aún no publican sus promedios del bimestre
        $sinPublicar = DB::table('curso_aula as c')->join('aula', 'aula.id', '=', 'c.aula_id')
            ->where('c.docente_id', $docenteId)->where('aula.periodo_id', $periodo->id)
            ->whereNotExists(fn ($q) => $q->from('calificacion_periodo')->whereColumn('calificacion_periodo.curso_aula_id', 'c.id')
                ->where('calificacion_periodo.subperiodo_id', $bimestre->id)->where('calificacion_periodo.estado', '!=', 'BORRADOR'))
            ->count();
        $fecha = Carbon::parse($bimestre->fecha_cierre_notas)->locale('es')->isoFormat('dddd D [de] MMMM');
        self::enviar([$usuario->id], 'CIERRE_PROXIMO', "Cierre del {$bimestre->nombre}", "Las notas del {$bimestre->nombre} se cierran el {$fecha}."
            .($sinPublicar ? " Tienes {$sinPublicar} ".($sinPublicar === 1 ? 'curso' : 'cursos').' sin publicar.' : ''), $enlace);
    }

    private static function datos(object $n): array
    {
        return [
            'id' => $n->id,
            'tipo' => $n->tipo,
            'titulo' => $n->titulo,
            'mensaje' => $n->mensaje,
            'enlace' => $n->enlace,
            'leida' => $n->leida_en !== null,
            'fecha' => Carbon::parse($n->created_at)->toIso8601String(),
        ];
    }
}
