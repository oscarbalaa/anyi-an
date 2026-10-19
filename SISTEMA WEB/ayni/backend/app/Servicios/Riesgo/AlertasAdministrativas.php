<?php

namespace App\Servicios\Riesgo;

use App\Servicios\Asistencia\ReportesAsistencia;
use App\Support\Calendario;
use App\Support\Cursos;
use App\Support\PerfilUsuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RF-56: avisos de la operación del colegio. Sin cron: se revisan al abrir las alertas o el panel de riesgo,
 * y una vez al día. Cada situación tiene una clave: si sigue, se actualiza; si ya no ocurre, se marca resuelta.
 */
class AlertasAdministrativas
{
    public const TIPOS = [
        'USUARIO_BLOQUEADO' => 'Acceso',
        'ESTUDIANTE_SIN_MATRICULA' => 'Matrícula',
        'DOCENTE_SIN_ASISTENCIA' => 'Registro pendiente',
        'CURSO_SIN_DOCENTE' => 'Carga académica',
        'AULA_SIN_TUTOR' => 'Carga académica',
        'NOTAS_PENDIENTES_CIERRE' => 'Cierre de bimestre',
    ];

    private const ENLACES = [
        'USUARIO_BLOQUEADO' => '/admin/usuarios',
        'ESTUDIANTE_SIN_MATRICULA' => '/admin/estudiantes?pestana=matriculas',
        'DOCENTE_SIN_ASISTENCIA' => '/admin/asistencia',
        'CURSO_SIN_DOCENTE' => '/admin/estructura?pestana=carga',
        'AULA_SIN_TUTOR' => '/admin/estructura?pestana=aulas',
        'NOTAS_PENDIENTES_CIERRE' => '/admin/estructura?pestana=periodos',
    ];

    /** Días antes del cierre de notas en que se avisa de los cursos sin publicar */
    private const DIAS_CIERRE = 7;

    public function __construct(private readonly ReportesAsistencia $asistencia) {}

    public function sincronizar(): void
    {
        $vigentes = array_merge($this->bloqueados(), $this->sinMatricula(), $this->sinAsistencia(), $this->sinDocente(), $this->sinTutor(), $this->notasPendientes());
        $porClave = collect($vigentes)->keyBy('clave');

        DB::transaction(function () use ($porClave) {
            $abiertas = DB::table('alerta_administrativa')->where('estado', 'ABIERTA')->get()->keyBy('clave');
            foreach ($porClave as $clave => $a) {
                if ($previa = $abiertas->get($clave)) {
                    if ($previa->titulo !== $a['titulo'] || $previa->detalle !== $a['detalle'] || $previa->severidad !== $a['severidad']) {
                        DB::table('alerta_administrativa')->where('id', $previa->id)->update(['titulo' => $a['titulo'], 'detalle' => $a['detalle'], 'severidad' => $a['severidad'], 'updated_at' => now()]);
                    }
                } else {
                    DB::table('alerta_administrativa')->insertOrIgnore($a + ['estado' => 'ABIERTA']);
                }
            }
            // Lo que ya no ocurre queda resuelto
            $resueltas = $abiertas->keys()->diff($porClave->keys());
            if ($resueltas->isNotEmpty()) {
                DB::table('alerta_administrativa')->where('estado', 'ABIERTA')->whereIn('clave', $resueltas)->update(['estado' => 'RESUELTA', 'resuelta_en' => now(), 'updated_at' => now()]);
            }
        });
    }

    /** RN-21: un estudiante en riesgo cuya aula no tiene tutor ni coordinador */
    public function aulaSinTutor(int $aulaId): void
    {
        foreach ($this->sinTutor([$aulaId]) as $a) {
            DB::table('alerta_administrativa')->insertOrIgnore($a + ['estado' => 'ABIERTA']);
        }
    }

    public function listar(array $f): array
    {
        $this->sincronizar();
        $estado = $f['estado'] ?? 'ABIERTA';
        $q = DB::table('alerta_administrativa')
            ->when($estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->when($f['tipo'] ?? null, fn ($q, $t) => $q->where('tipo', $t));
        $conteo = DB::table('alerta_administrativa')->selectRaw("SUM(estado = 'ABIERTA') as abiertas, SUM(estado = 'RESUELTA') as resueltas")->first();

        return [
            'datos' => $q->orderByRaw("estado = 'RESUELTA'")->orderByRaw("FIELD(severidad, 'CRITICO', 'ATENCION', 'PENDIENTE')")->orderByDesc('created_at')->orderByDesc('id')
                ->limit(100)->get()->map(fn ($a) => [
                    'id' => $a->id,
                    'tipo' => $a->tipo,
                    'categoria' => self::TIPOS[$a->tipo],
                    'severidad' => $a->severidad,
                    'titulo' => $a->titulo,
                    'detalle' => $a->detalle,
                    'estado' => $a->estado,
                    'creada' => Carbon::parse($a->created_at)->toIso8601String(),
                    'resuelta' => $a->resuelta_en ? Carbon::parse($a->resuelta_en)->toIso8601String() : null,
                    'enlace' => self::ENLACES[$a->tipo],
                ])->all(),
            'conteo' => ['abiertas' => (int) $conteo->abiertas, 'resueltas' => (int) $conteo->resueltas],
            'tipos' => collect(self::TIPOS)->map(fn ($c, $t) => ['valor' => $t, 'texto' => self::nombreTipo($t)])->values()->all(),
        ];
    }

    public static function nombreTipo(string $tipo): string
    {
        return [
            'USUARIO_BLOQUEADO' => 'Cuentas bloqueadas',
            'ESTUDIANTE_SIN_MATRICULA' => 'Estudiantes sin matrícula',
            'DOCENTE_SIN_ASISTENCIA' => 'Asistencia sin registrar',
            'CURSO_SIN_DOCENTE' => 'Cursos sin docente',
            'AULA_SIN_TUTOR' => 'Aulas sin tutor',
            'NOTAS_PENDIENTES_CIERRE' => 'Notas pendientes al cierre',
        ][$tipo];
    }

    private function bloqueados(): array
    {
        return DB::table('usuario')->where('estado', 'BLOQUEADO')->orderBy('username')->get(['id', 'username'])
            ->map(fn ($u) => $this->alerta('USUARIO_BLOQUEADO', "usuario-bloqueado:{$u->id}", 'CRITICO', "Cuenta bloqueada: {$u->username}", 'Bloqueada por intentos fallidos de ingreso. Solo la administración puede desbloquearla.', 'usuario', $u->id))
            ->all();
    }

    private function sinMatricula(): array
    {
        $periodo = Calendario::periodoEnCurso();
        if (! $periodo) {
            return [];
        }
        $nombres = DB::table('estudiante as e')->join('persona as p', 'p.id', '=', 'e.persona_id')->where('e.estado', 'ACTIVO')
            ->whereNotExists(fn ($q) => $q->from('matricula')->whereColumn('matricula.estudiante_id', 'e.id')->where('matricula.periodo_id', $periodo->id)->where('matricula.estado', 'ACTIVA'))
            ->orderBy('p.apellidos')->get(['p.nombres', 'p.apellidos'])->map(fn ($p) => "{$p->nombres} {$p->apellidos}");
        if ($nombres->isEmpty()) {
            return [];
        }
        $n = $nombres->count();

        return [$this->alerta('ESTUDIANTE_SIN_MATRICULA', "sin-matricula:{$periodo->id}", 'PENDIENTE', $n === 1 ? "1 estudiante sin matrícula en {$periodo->nombre}" : "{$n} estudiantes sin matrícula en {$periodo->nombre}", self::lista($nombres->all()))];
    }

    private function sinAsistencia(): array
    {
        $resumen = $this->asistencia->resumenAdmin(null);
        if (! $resumen['bimestre']) {
            return [];
        }

        return collect($resumen['sin_registro'])->map(fn ($d) => $this->alerta('DOCENTE_SIN_ASISTENCIA', "sin-asistencia:{$d['docente_id']}:{$resumen['bimestre']['id']}", $d['total'] >= 3 ? 'CRITICO' : 'ATENCION', $d['total'] === 1 ? '1 clase sin registrar asistencia' : "{$d['total']} clases sin registrar asistencia", "{$d['docente']} · ".self::lista(array_column($d['cursos'], 'nombre')), 'docente', $d['docente_id']))->all();
    }

    /** Cursos cuyo docente está inactivo o de licencia */
    private function sinDocente(): array
    {
        $periodo = Calendario::periodoEnCurso();
        if (! $periodo) {
            return [];
        }

        return Cursos::consulta()->addSelect('docente.estado as estado_docente')->where('aula.periodo_id', $periodo->id)->where('docente.estado', '!=', 'ACTIVO')->get()
            ->map(fn ($c) => $this->alerta('CURSO_SIN_DOCENTE', "curso-sin-docente:{$c->id}", 'ATENCION', 'Curso sin docente', "{$c->asignatura} · ".PerfilUsuario::nombreAula($c->numero, $c->nivel, $c->seccion).': su docente está '.($c->estado_docente === 'LICENCIA' ? 'de licencia' : 'inactivo').'.', 'curso_aula', $c->id))
            ->all();
    }

    /** @param  list<int>|null  $soloAulas */
    private function sinTutor(?array $soloAulas = null): array
    {
        $periodo = Calendario::periodoEnCurso();
        if (! $periodo) {
            return [];
        }

        return DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->leftJoin('docente as d', 'd.id', '=', 'aula.tutor_docente_id')
            ->where('aula.periodo_id', $periodo->id)->where(fn ($q) => $q->whereNull('aula.tutor_docente_id')->orWhere('d.estado', '!=', 'ACTIVO'))
            ->when($soloAulas !== null, fn ($q) => $q->whereIn('aula.id', $soloAulas))
            ->orderBy('grado.orden')->orderBy('seccion.nombre')
            ->get(['aula.id', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion'])
            ->map(fn ($a) => $this->alerta('AULA_SIN_TUTOR', "aula-sin-tutor:{$a->id}", 'ATENCION', 'Aula sin tutor', PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion).': sus alertas de riesgo van a la coordinación o quedan sin encargado.', 'aula', $a->id))
            ->all();
    }

    /** Bimestre por cerrar con cursos que aún no publican sus promedios */
    private function notasPendientes(): array
    {
        $periodo = Calendario::periodoEnCurso();
        $b = $periodo ? Calendario::bimestreVigente(Calendario::bimestres($periodo->id)) : null;
        if (! $b || $b->estado !== 'ABIERTO') {
            return [];
        }
        $dias = (int) today()->diffInDays(Carbon::parse($b->fecha_cierre_notas), false);
        if ($dias < 0 || $dias > self::DIAS_CIERRE) {
            return [];
        }
        $cursos = Cursos::consulta()->where('aula.periodo_id', $periodo->id)
            ->whereNotExists(fn ($q) => $q->from('calificacion_periodo')->whereColumn('calificacion_periodo.curso_aula_id', 'curso_aula.id')
                ->where('calificacion_periodo.subperiodo_id', $b->id)->where('calificacion_periodo.estado', '!=', 'BORRADOR'))
            ->get()
            ->map(fn ($c) => "{$c->asignatura} · ".PerfilUsuario::nombreAula($c->numero, $c->nivel, $c->seccion));
        if ($cursos->isEmpty()) {
            return [];
        }
        $n = $cursos->count();
        $fecha = Carbon::parse($b->fecha_cierre_notas)->locale('es')->isoFormat('dddd D [de] MMMM');

        return [$this->alerta('NOTAS_PENDIENTES_CIERRE', "notas-pendientes:{$b->id}", $dias <= 2 ? 'CRITICO' : 'ATENCION', ($n === 1 ? '1 curso sin publicar promedios' : "{$n} cursos sin publicar promedios")." del {$b->nombre}", "Las notas se cierran el {$fecha}: ".self::lista($cursos->all()), 'subperiodo', $b->id)];
    }

    private function alerta(string $tipo, string $clave, string $severidad, string $titulo, ?string $detalle, ?string $entidad = null, ?int $entidadId = null): array
    {
        return [
            'tipo' => $tipo,
            'clave' => $clave,
            'severidad' => $severidad,
            'titulo' => mb_substr($titulo, 0, 150),
            'detalle' => $detalle === null ? null : mb_substr($detalle, 0, 300),
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
        ];
    }

    /** "A, B, C y 4 más" */
    private static function lista(array $nombres, int $max = 3): string
    {
        $resto = count($nombres) - $max;

        return implode(', ', array_slice($nombres, 0, $max)).($resto > 0 ? " y {$resto} más" : '');
    }
}
