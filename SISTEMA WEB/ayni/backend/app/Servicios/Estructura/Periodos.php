<?php

namespace App\Servicios\Estructura;

use App\Exceptions\ErrorRegla;
use App\Support\Auditoria;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** CU-06: periodos y bimestres (RF-17, RF-18, RN-01, RN-02) */
class Periodos
{
    public const ROMANOS = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];

    public function __construct(private readonly CierreBimestres $cierre) {}

    public function listar(): array
    {
        $this->cierre->revisar();
        $bimestres = DB::table('subperiodo')->orderBy('numero')->get()->groupBy('periodo_id');
        $aulas = DB::table('aula')->selectRaw('periodo_id, COUNT(*) as total')->groupBy('periodo_id')->pluck('total', 'periodo_id');

        return DB::table('periodo_academico')->orderByDesc('fecha_inicio')->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'fecha_inicio' => $p->fecha_inicio,
                'fecha_fin' => $p->fecha_fin,
                'estado' => $p->estado,
                'aulas' => (int) ($aulas[$p->id] ?? 0),
                'bimestres' => $this->bimestres($bimestres[$p->id] ?? collect()),
            ])->all();
    }

    /** Los 4 lugares de bimestre; los que faltan van como "sin definir" */
    private function bimestres($existentes): array
    {
        $hoy = today()->toDateString();

        return collect(range(1, 4))->map(function ($n) use ($existentes, $hoy) {
            $b = $existentes->firstWhere('numero', $n);
            if (! $b) {
                return ['numero' => $n, 'nombre' => self::ROMANOS[$n].' Bimestre', 'id' => null, 'situacion' => 'SIN_DEFINIR'];
            }

            return [
                'id' => $b->id,
                'numero' => $b->numero,
                'nombre' => $b->nombre,
                'fecha_inicio' => $b->fecha_inicio,
                'fecha_fin' => $b->fecha_fin,
                'fecha_cierre_notas' => $b->fecha_cierre_notas,
                'estado' => $b->estado,
                'cerrado_automatico' => $b->estado === 'CERRADO' && $b->cerrado_por === null,
                // Lo que ve la pantalla: cerrado, en curso, por cerrar (clases terminadas, notas abiertas) o pendiente
                'situacion' => match (true) {
                    $b->estado === 'CERRADO' => 'CERRADO',
                    $hoy < $b->fecha_inicio => 'PENDIENTE',
                    $hoy <= $b->fecha_fin => 'EN_CURSO',
                    default => 'POR_CERRAR',
                },
            ];
        })->all();
    }

    public function crear(array $datos): int
    {
        $id = DB::table('periodo_academico')->insertGetId($datos + ['estado' => 'PLANIFICADO', 'created_at' => now(), 'updated_at' => now()]);
        Auditoria::registrar('CREAR', 'periodo_academico', $id, $datos);

        return $id;
    }

    public function actualizar(int $id, array $datos): void
    {
        $periodo = $this->periodo($id);
        if ($periodo->estado === 'CERRADO') {
            throw new ErrorRegla('Un periodo cerrado ya no se modifica.');
        }
        // Sus bimestres deben seguir dentro de las nuevas fechas
        $fuera = DB::table('subperiodo')->where('periodo_id', $id)
            ->where(fn ($q) => $q->where('fecha_inicio', '<', $datos['fecha_inicio'])
                ->orWhere('fecha_fin', '>', $datos['fecha_fin'])
                ->orWhere('fecha_cierre_notas', '>', $datos['fecha_fin']))
            ->value('nombre');
        if ($fuera) {
            throw new ErrorRegla("El {$fuera} quedaría fuera del periodo. Cambia primero sus fechas.", 'fecha_fin');
        }

        DB::table('periodo_academico')->where('id', $id)->update($datos + ['updated_at' => now()]);
        Auditoria::registrar('ACTUALIZAR', 'periodo_academico', $id, $datos);
    }

    /** PLANIFICADO → EN_CURSO → CERRADO */
    public function cambiarEstado(int $id, string $estado, int $adminId): void
    {
        $periodo = $this->periodo($id);
        $permitido = ['PLANIFICADO' => 'EN_CURSO', 'EN_CURSO' => 'CERRADO'][$periodo->estado] ?? null;
        if ($permitido !== $estado) {
            throw new ErrorRegla($periodo->estado === 'CERRADO'
                ? 'Un periodo cerrado no se vuelve a abrir.'
                : 'Un periodo planificado primero se pone en curso y después se cierra.');
        }

        if ($estado === 'EN_CURSO') {
            $actual = DB::table('periodo_academico')->where('estado', 'EN_CURSO')->value('nombre');
            if ($actual) {
                throw new ErrorRegla("Ya hay un periodo en curso ({$actual}). Ciérralo antes de iniciar otro (RN-01).");
            }
            if (! DB::table('subperiodo')->where('periodo_id', $id)->exists()) {
                throw new ErrorRegla('Define al menos el I Bimestre antes de iniciar el periodo.');
            }
        }

        DB::transaction(function () use ($id, $estado, $adminId) {
            if ($estado === 'CERRADO') {
                DB::table('subperiodo')->where('periodo_id', $id)->where('estado', 'ABIERTO')->pluck('id')
                    ->each(fn ($b) => $this->cierre->cerrar($b, $adminId));
            }
            DB::table('periodo_academico')->where('id', $id)->update(['estado' => $estado, 'updated_at' => now()]);
            Auditoria::registrar($estado === 'EN_CURSO' ? 'INICIAR_PERIODO' : 'CERRAR_PERIODO', 'periodo_academico', $id);
        });
    }

    public function eliminar(int $id): void
    {
        $periodo = $this->periodo($id);
        if ($periodo->estado !== 'PLANIFICADO') {
            throw new ErrorRegla('Solo se puede eliminar un periodo planificado.');
        }
        if (DB::table('aula')->where('periodo_id', $id)->exists()) {
            throw new ErrorRegla('El periodo ya tiene aulas: elimínalas primero.');
        }
        DB::table('periodo_academico')->where('id', $id)->delete();
        Auditoria::registrar('ELIMINAR', 'periodo_academico', $id, ['nombre' => $periodo->nombre]);
    }

    /** RN-02: dentro del periodo, sin cruzarse y en orden; cierre entre el inicio del bimestre y el fin del periodo */
    public function guardarBimestre(int $periodoId, array $datos, ?int $bimestreId = null): int
    {
        $periodo = $this->periodo($periodoId);
        if ($periodo->estado === 'CERRADO') {
            throw new ErrorRegla('El periodo está cerrado.');
        }
        $actual = $bimestreId ? DB::table('subperiodo')->find($bimestreId) : null;
        if ($actual?->estado === 'CERRADO') {
            throw new ErrorRegla('El bimestre ya está cerrado: sus fechas no se cambian.');
        }

        $n = $actual?->numero ?? (int) $datos['numero'];
        if (! $actual && DB::table('subperiodo')->where('periodo_id', $periodoId)->where('numero', $n)->exists()) {
            throw new ErrorRegla('Ese bimestre ya está definido.', 'numero');
        }
        if ($datos['fecha_inicio'] < $periodo->fecha_inicio || $datos['fecha_fin'] > $periodo->fecha_fin) {
            throw new ErrorRegla('El bimestre debe estar dentro del periodo ('.$this->fecha($periodo->fecha_inicio).' – '.$this->fecha($periodo->fecha_fin).').', 'fecha_inicio');
        }

        foreach (DB::table('subperiodo')->where('periodo_id', $periodoId)->where('numero', '!=', $n)->get() as $otro) {
            if ($otro->numero < $n && $otro->fecha_fin >= $datos['fecha_inicio']) {
                throw new ErrorRegla("Debe empezar después del {$otro->nombre} (termina el ".$this->fecha($otro->fecha_fin).').', 'fecha_inicio');
            }
            if ($otro->numero > $n && $otro->fecha_inicio <= $datos['fecha_fin']) {
                throw new ErrorRegla("Debe terminar antes del {$otro->nombre} (empieza el ".$this->fecha($otro->fecha_inicio).').', 'fecha_fin');
            }
        }

        // Por defecto: fin del bimestre + días configurados, sin pasar el fin del periodo
        $cierre = $datos['fecha_cierre_notas'] ?? null;
        if (! $cierre) {
            $dias = (int) DB::table('institucion')->value('dias_cierre_bimestre') ?: 7;
            $cierre = min(Carbon::parse($datos['fecha_fin'])->addDays($dias)->toDateString(), $periodo->fecha_fin);
        }
        if ($cierre < $datos['fecha_inicio'] || $cierre > $periodo->fecha_fin) {
            throw new ErrorRegla('La fecha de cierre de notas no puede ser anterior al inicio del bimestre ni posterior al fin del periodo (RN-02).', 'fecha_cierre_notas');
        }

        $fila = [
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin' => $datos['fecha_fin'],
            'fecha_cierre_notas' => $cierre,
        ];
        if ($actual) {
            DB::table('subperiodo')->where('id', $actual->id)->update($fila);
            Auditoria::registrar('ACTUALIZAR', 'subperiodo', $actual->id, $fila);

            return $actual->id;
        }

        $id = DB::table('subperiodo')->insertGetId($fila + ['periodo_id' => $periodoId, 'numero' => $n, 'nombre' => self::ROMANOS[$n].' Bimestre']);
        Auditoria::registrar('CREAR', 'subperiodo', $id, $fila);

        return $id;
    }

    public function cerrarBimestre(int $id, int $adminId): void
    {
        $b = DB::table('subperiodo')->find($id) ?? abort(404);
        if ($b->estado === 'CERRADO') {
            throw new ErrorRegla('El bimestre ya está cerrado.');
        }
        if (today()->toDateString() < $b->fecha_inicio) {
            throw new ErrorRegla('El bimestre aún no empieza.');
        }
        $this->cierre->cerrar($id, $adminId);
    }

    public function eliminarBimestre(int $id): void
    {
        $b = DB::table('subperiodo')->find($id) ?? abort(404);
        $usado = DB::table('evaluacion')->where('subperiodo_id', $id)->exists()
            || DB::table('sesion_clase')->where('subperiodo_id', $id)->exists()
            || DB::table('calificacion_periodo')->where('subperiodo_id', $id)->exists();
        if ($usado || $b->estado === 'CERRADO') {
            throw new ErrorRegla('El bimestre ya tiene evaluaciones, asistencia o notas: no se puede eliminar.');
        }
        DB::table('subperiodo')->where('id', $id)->delete();
        Auditoria::registrar('ELIMINAR', 'subperiodo', $id, ['nombre' => $b->nombre]);
    }

    private function periodo(int $id): object
    {
        return DB::table('periodo_academico')->find($id) ?? abort(404);
    }

    private function fecha(string $fecha): string
    {
        return Carbon::parse($fecha)->format('d/m/Y');
    }
}
