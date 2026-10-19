<?php

namespace App\Servicios\Asistencia;

use App\Exceptions\ErrorRegla;
use App\Models\Usuario;
use App\Servicios\Notificaciones;
use App\Servicios\Riesgo\Riesgo;
use App\Support\Auditoria;
use App\Support\Calendario;
use App\Support\Cursos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** CU-13 y CU-14: registro y corrección de la asistencia por clase (RF-33 a RF-35, RF-37, RN-06, RN-07) */
class Asistencias
{
    public const ESTADOS = ['P', 'F', 'T', 'J'];

    /** Cursos del docente en el periodo en curso, con las clases que aún no registra */
    public function cursosDelDocente(Usuario $usuario, Pendientes $pendientes): array
    {
        $periodo = Calendario::periodoEnCurso();
        $docenteId = Cursos::docenteDe($usuario);
        if (! $periodo || ! $docenteId) {
            return ['cursos' => [], 'bimestre' => null];
        }
        $bimestre = Calendario::bimestreVigente(Calendario::bimestres($periodo->id));
        $cursos = Cursos::consulta()->where('aula.periodo_id', $periodo->id)->where('curso_aula.docente_id', $docenteId)->get();
        $dias = $this->diasDeClase($cursos->pluck('id')->all());
        $atrasadas = $bimestre ? $pendientes->porCurso($cursos->pluck('id')->all(), $bimestre) : [];

        return [
            'bimestre' => $bimestre ? ['id' => $bimestre->id, 'nombre' => $bimestre->nombre, 'estado' => $bimestre->estado] : null,
            'cursos' => $cursos->map(fn ($c) => Cursos::datos($c) + [
                'dias' => $dias[$c->id] ?? [],
                'pendientes' => count($atrasadas[$c->id] ?? []),
            ])->all(),
        ];
    }

    /** Para el administrador: cursos del periodo agrupados por aula */
    public function cursosDelPeriodo(int $periodoId): array
    {
        return Cursos::consulta()->where('aula.periodo_id', $periodoId)->get()
            ->map(fn ($c) => Cursos::datos($c))
            ->groupBy('aula_id')
            ->map(fn ($cursos, $aulaId) => ['id' => $aulaId, 'nombre' => $cursos[0]['aula'], 'cursos' => $cursos->values()->all()])
            ->values()->all();
    }

    /** Días de clase del curso en el bimestre hasta hoy (RN-06), con el estado de cada registro */
    public function fechas(int $cursoId, Usuario $usuario, ?int $bimestreId): array
    {
        $curso = $this->curso($cursoId, $usuario);
        $bimestres = Calendario::bimestres($curso->periodo_id);
        $bimestre = $bimestreId ? $bimestres->firstWhere('id', $bimestreId) : Calendario::bimestreVigente($bimestres);
        if (! $bimestre) {
            throw new ErrorRegla('Elige un bimestre del periodo del curso.', 'bimestre_id');
        }

        $hoy = today()->toDateString();
        $hasta = min($hoy, $bimestre->fecha_fin);
        $dias = $this->diasDeClase([$cursoId])[$cursoId] ?? [];
        $sesiones = DB::table('sesion_clase')->where('curso_aula_id', $cursoId)
            ->whereBetween('fecha', [$bimestre->fecha_inicio, $bimestre->fecha_fin])->pluck('estado', 'fecha')->all();

        $fechas = array_fill_keys(array_keys($sesiones), true);
        foreach (self::recorrer($bimestre->fecha_inicio, $hasta) as $f) {
            if (in_array(Carbon::parse($f)->dayOfWeekIso, $dias, true)) {
                $fechas[$f] = true;
            }
        }
        krsort($fechas);

        return [
            'curso' => Cursos::datos($curso),
            'bimestre' => Cursos::datosBimestre($bimestre),
            'bimestres' => $bimestres->map(fn ($b) => Cursos::datosBimestre($b))->all(),
            'fechas' => array_map(fn ($f) => [
                'fecha' => $f,
                'estado' => $sesiones[$f] ?? 'SIN_REGISTRO',
                'hoy' => $f === $hoy,
            ], array_keys($fechas)),
        ];
    }

    /** Lista de la clase: lo registrado o, si aún no hay registro, todos presentes */
    public function detalle(int $cursoId, string $fecha, Usuario $usuario): array
    {
        $curso = $this->curso($cursoId, $usuario);
        $bimestre = $this->validarFecha($curso, $fecha);
        $sesion = DB::table('sesion_clase')->where('curso_aula_id', $cursoId)->where('fecha', $fecha)->first();
        [$editable, $aviso] = $this->permiso($sesion, $fecha, $bimestre, $usuario);

        $filas = $sesion ? DB::table('asistencia')->where('sesion_clase_id', $sesion->id)->get()->keyBy('matricula_id') : collect();
        $alumnos = $this->alumnos($curso->aula_id, $fecha, $sesion?->id);
        $faltas = $this->faltasDeLaSemana($alumnos->pluck('matricula_id')->all(), $fecha, $sesion?->id);
        $nombres = $this->nombresDeUsuarios([$sesion?->registrado_por, ...$filas->pluck('modificado_por')->all()]);

        $estudiantes = $alumnos->map(function ($a) use ($filas, $faltas, $nombres) {
            $fila = $filas->get($a->matricula_id);

            return [
                'matricula_id' => $a->matricula_id,
                'nombre' => "{$a->apellidos}, {$a->nombres}",
                'codigo' => $a->codigo,
                'estado' => $fila->estado ?? 'P',
                'observacion' => $fila->observacion ?? null,
                'faltas_semana' => (int) ($faltas[$a->matricula_id] ?? 0),
                'corregido_por' => $fila?->modificado_por ? ($nombres[$fila->modificado_por] ?? null) : null,
            ];
        })->values();

        return [
            'curso' => Cursos::datos($curso),
            'fecha' => $fecha,
            'bimestre' => Cursos::datosBimestre($bimestre),
            'sesion' => [
                'estado' => $sesion->estado ?? 'SIN_REGISTRO',
                'tema' => $sesion->tema ?? null,
                'registrado_por' => $sesion?->registrado_por ? ($nombres[$sesion->registrado_por] ?? null) : null,
                'actualizado_en' => $sesion ? Carbon::parse($sesion->updated_at)->toIso8601String() : null,
                'editable' => $editable,
                'aviso' => $aviso,
            ],
            'estudiantes' => $estudiantes->all(),
            'resumen' => array_merge(array_fill_keys(self::ESTADOS, 0), $estudiantes->countBy('estado')->all()),
            'aviso_faltas' => (int) config('ayni.asistencia.aviso_faltas_semana'),
        ];
    }

    /**
     * Guarda la clase. Lo que no viene en $registros queda como estaba o, si es nuevo, presente.
     * Una corrección (la clase ya era definitiva) deja en cada fila quién la cambió.
     */
    public function guardar(int $cursoId, string $fecha, array $datos, Usuario $usuario): array
    {
        $curso = $this->curso($cursoId, $usuario);
        $bimestre = $this->validarFecha($curso, $fecha);
        $estado = $datos['estado'];

        // 3 intentos: si dos guardan a la vez una clase nueva, el que choca se reintenta y ya la encuentra
        $cambios = DB::transaction(function () use ($curso, $fecha, $datos, $estado, $bimestre, $usuario) {
            $sesion = DB::table('sesion_clase')->where('curso_aula_id', $curso->id)->where('fecha', $fecha)->lockForUpdate()->first();
            [$editable, $aviso] = $this->permiso($sesion, $fecha, $bimestre, $usuario);
            if (! $editable) {
                throw new ErrorRegla($aviso);
            }
            $eraDefinitiva = $sesion?->estado === 'DEFINITIVO';
            if ($eraDefinitiva && $estado === 'BORRADOR') {
                throw new ErrorRegla('Esta asistencia ya es definitiva: puedes corregirla, pero no volver a borrador.', 'estado');
            }

            $alumnos = $this->alumnos($curso->aula_id, $fecha, $sesion?->id)->keyBy('matricula_id');
            $enviados = collect($datos['registros'] ?? [])->keyBy('matricula_id');
            if ($enviados->keys()->diff($alumnos->keys())->isNotEmpty()) {
                throw new ErrorRegla('Hay estudiantes que no pertenecen a esta clase.', 'registros');
            }

            $ahora = now();
            if ($sesion) {
                DB::table('sesion_clase')->where('id', $sesion->id)->update([
                    'estado' => $estado,
                    'tema' => array_key_exists('tema', $datos) ? $datos['tema'] : $sesion->tema,
                    'registrado_por' => $sesion->registrado_por ?? $usuario->id,
                    'updated_at' => $ahora,
                ]);
                $sesionId = $sesion->id;
            } else {
                $sesionId = DB::table('sesion_clase')->insertGetId([
                    'curso_aula_id' => $curso->id,
                    'subperiodo_id' => $bimestre->id,
                    'fecha' => $fecha,
                    'tema' => $datos['tema'] ?? null,
                    'estado' => $estado,
                    'registrado_por' => $usuario->id,
                ]);
            }

            $previas = DB::table('asistencia')->where('sesion_clase_id', $sesionId)->get()->keyBy('matricula_id');
            $cambios = [];
            $faltasNuevas = [];
            foreach ($alumnos->keys() as $matriculaId) {
                $previa = $previas->get($matriculaId);
                $nuevo = $enviados->get($matriculaId);
                $estadoAlumno = $nuevo['estado'] ?? $previa->estado ?? 'P';
                $observacion = $nuevo ? ($nuevo['observacion'] ?? null) : ($previa->observacion ?? null);
                // Lo de un borrador aún no se avisó: al pasar a definitivo cuenta como nuevo
                if ($estadoAlumno === 'F' && ! ($eraDefinitiva && $previa?->estado === 'F')) {
                    $faltasNuevas[] = $matriculaId;
                }

                if (! $previa) {
                    DB::table('asistencia')->insert([
                        'sesion_clase_id' => $sesionId, 'matricula_id' => $matriculaId, 'estado' => $estadoAlumno,
                        'observacion' => $observacion, 'registrado_por' => $usuario->id,
                        'modificado_por' => $eraDefinitiva ? $usuario->id : null,
                    ]);
                } elseif ($previa->estado !== $estadoAlumno || $previa->observacion !== $observacion) {
                    DB::table('asistencia')->where('id', $previa->id)->update([
                        'estado' => $estadoAlumno,
                        'observacion' => $observacion,
                        'modificado_por' => $eraDefinitiva ? $usuario->id : $previa->modificado_por,
                        'updated_at' => $ahora,
                    ]);
                } else {
                    continue;
                }
                $cambios[$matriculaId] = ['antes' => $previa->estado ?? null, 'despues' => $estadoAlumno];
            }

            $accion = $eraDefinitiva ? 'CORREGIR_ASISTENCIA' : ($estado === 'DEFINITIVO' ? 'REGISTRAR_ASISTENCIA' : 'BORRADOR_ASISTENCIA');
            if (! $eraDefinitiva || $cambios) {
                Auditoria::registrar($accion, 'sesion_clase', $sesionId, ['fecha' => $fecha, 'curso' => $curso->asignatura, 'cambios' => $eraDefinitiva ? $cambios : count($cambios)]);
            }

            // CU-13: el estudiante se entera de cada falta nueva
            if ($estado === 'DEFINITIVO') {
                $this->avisarFaltas($faltasNuevas, $curso, $fecha);
            }

            return $cambios;
        }, 3);
        // RF-52: el riesgo se recalcula al registrar asistencia
        if ($estado === 'DEFINITIVO') {
            app(Riesgo::class)->recalcularAula($curso->aula_id, $bimestre->id);
        }

        $mensaje = match (true) {
            $estado === 'BORRADOR' => 'Borrador guardado. Aún no lo ven los estudiantes.',
            $usuario->rol->codigo === 'ADMIN' => 'Asistencia corregida. El cambio queda registrado con tu nombre.',
            default => 'Asistencia guardada.',
        };

        return ['mensaje' => $mensaje, 'cambios' => count($cambios)] + $this->detalle($cursoId, $fecha, $usuario);
    }

    /** Solo el administrador: borra el registro de una clase que no se dictó */
    public function eliminar(int $cursoId, string $fecha, string $motivo, Usuario $usuario): void
    {
        $curso = $this->curso($cursoId, $usuario);
        $bimestre = $this->validarFecha($curso, $fecha, false);
        if ($bimestre->estado === 'CERRADO') {
            throw new ErrorRegla("El {$bimestre->nombre} está cerrado: su asistencia ya no se modifica.");
        }
        $sesion = DB::table('sesion_clase')->where('curso_aula_id', $cursoId)->where('fecha', $fecha)->first() ?? abort(404);
        $resumen = DB::table('asistencia')->where('sesion_clase_id', $sesion->id)->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado');

        DB::table('sesion_clase')->where('id', $sesion->id)->delete();
        Auditoria::registrar('ELIMINAR_ASISTENCIA', 'sesion_clase', $sesion->id, ['fecha' => $fecha, 'curso' => $curso->asignatura, 'motivo' => $motivo, 'estados' => $resumen]);
    }

    // ------------------------------------------------------------ reglas

    /** RN-06: día de clase del curso, dentro de un bimestre y no futuro */
    private function validarFecha(object $curso, string $fecha, bool $exigirClase = true): object
    {
        [$a, $m, $d] = array_map('intval', explode('-', $fecha));
        if (! checkdate($m, $d, $a)) {
            throw new ErrorRegla('La fecha no es válida.', 'fecha');
        }
        if ($fecha > today()->toDateString()) {
            throw new ErrorRegla('No se registra asistencia de una fecha futura (RN-06).', 'fecha');
        }
        $bimestre = DB::table('subperiodo')->where('periodo_id', $curso->periodo_id)
            ->where('fecha_inicio', '<=', $fecha)->where('fecha_fin', '>=', $fecha)->first();
        if (! $bimestre) {
            throw new ErrorRegla('La fecha está fuera de los bimestres del periodo (RN-06).', 'fecha');
        }

        $dia = Carbon::parse($fecha)->dayOfWeekIso;
        $tieneClase = DB::table('horario')->where('curso_aula_id', $curso->id)->where('dia_semana', $dia)->exists();
        // Si el horario cambió después, la clase ya registrada se puede seguir consultando
        $registrada = DB::table('sesion_clase')->where('curso_aula_id', $curso->id)->where('fecha', $fecha)->exists();
        if ($exigirClase && ! $tieneClase && ! $registrada) {
            $nombreDia = Carbon::parse($fecha)->locale('es')->dayName;
            throw new ErrorRegla("{$curso->asignatura} no tiene clase los {$nombreDia} según el horario (RN-06).", 'fecha');
        }

        return $bimestre;
    }

    /**
     * RN-07: el docente corrige hasta las 11:59 p. m. del día de la clase; después, solo el administrador.
     * Una clase pasada sin registro (o en borrador) el docente aún la puede registrar una vez.
     *
     * @return array{bool, string}
     */
    private function permiso(?object $sesion, string $fecha, object $bimestre, Usuario $usuario): array
    {
        if ($bimestre->estado === 'CERRADO') {
            return [false, "El {$bimestre->nombre} está cerrado: su asistencia ya no se modifica."];
        }
        if ($usuario->rol->codigo === 'ADMIN') {
            return [true, 'Como administrador puedes corregir este registro y justificar faltas. El cambio queda registrado con tu nombre.'];
        }

        $esHoy = $fecha === today()->toDateString();
        if ($sesion?->estado === 'DEFINITIVO' && ! $esHoy) {
            return [false, 'Ya pasó la medianoche del día de la clase: solo el administrador puede corregir esta asistencia (RN-07).'];
        }

        return [true, $esHoy
            ? 'Puedes corregir este registro hasta las 11:59 p. m. de hoy.'
            : 'Clase pasada sin registrar. Cuando guardes la asistencia definitiva ya no podrás corregirla: solo el administrador (RN-07).'];
    }

    /** Solo el docente del curso (o el administrador) ve y registra su asistencia */
    private function curso(int $id, Usuario $usuario): object
    {
        return Cursos::autorizado($id, $usuario);
    }

    // ------------------------------------------------------------ consultas

    /** [curso_id => [1, 3]] días de la semana con clase */
    public function diasDeClase(array $cursoIds): array
    {
        return DB::table('horario')->whereIn('curso_aula_id', $cursoIds)->distinct()->orderBy('dia_semana')
            ->get(['curso_aula_id', 'dia_semana'])->groupBy('curso_aula_id')
            ->map(fn ($g) => $g->pluck('dia_semana')->map(fn ($d) => (int) $d)->all())->all();
    }

    /** Matriculados en el aula a esa fecha, más quienes ya figuran en el registro (aunque luego se hayan retirado) */
    private function alumnos(int $aulaId, string $fecha, ?int $sesionId)
    {
        return DB::table('matricula as m')
            ->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')
            ->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->where('m.aula_id', $aulaId)
            ->where(function ($q) use ($fecha, $sesionId) {
                $q->where(fn ($a) => $a->where('m.estado', 'ACTIVA')->where('m.fecha', '<=', $fecha));
                if ($sesionId) {
                    $q->orWhereExists(fn ($s) => $s->from('asistencia')->whereColumn('asistencia.matricula_id', 'm.id')->where('asistencia.sesion_clase_id', $sesionId));
                }
            })
            ->orderBy('p.apellidos')->orderBy('p.nombres')
            ->get(['m.id as matricula_id', 'e.codigo', 'e.usuario_id', 'p.nombres', 'p.apellidos']);
    }

    /** RF-37: faltas (F) ya registradas en la semana de la fecha, en cualquier curso, sin contar esta clase */
    private function faltasDeLaSemana(array $matriculas, string $fecha, ?int $sesionId): array
    {
        $dia = Carbon::parse($fecha);

        return DB::table('asistencia as a')->join('sesion_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->whereIn('a.matricula_id', $matriculas)
            ->where('a.estado', 'F')->where('s.estado', 'DEFINITIVO')
            ->whereBetween('s.fecha', [$dia->copy()->startOfWeek(Carbon::MONDAY)->toDateString(), $dia->copy()->endOfWeek(Carbon::SUNDAY)->toDateString()])
            ->when($sesionId, fn ($q) => $q->where('s.id', '!=', $sesionId))
            ->selectRaw('a.matricula_id, COUNT(*) as total')->groupBy('a.matricula_id')->pluck('total', 'matricula_id')->all();
    }

    /** [usuario_id => "Luis Paredes"] de docentes y administrativos */
    private function nombresDeUsuarios(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (! $ids) {
            return [];
        }

        return DB::table('usuario as u')
            ->leftJoin('docente as d', 'd.usuario_id', '=', 'u.id')
            ->leftJoin('personal_administrativo as pa', 'pa.usuario_id', '=', 'u.id')
            ->leftJoin('persona as p', 'p.id', '=', DB::raw('COALESCE(d.persona_id, pa.persona_id)'))
            ->whereIn('u.id', $ids)
            ->get(['u.id', 'u.username', 'p.nombres', 'p.apellidos'])
            ->mapWithKeys(fn ($u) => [$u->id => $u->nombres ? explode(' ', $u->nombres)[0].' '.explode(' ', $u->apellidos)[0] : $u->username])
            ->all();
    }

    private function avisarFaltas(array $matriculas, object $curso, string $fecha): void
    {
        if (! $matriculas) {
            return;
        }
        $dia = Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM');
        $usuarios = DB::table('matricula')->join('estudiante', 'estudiante.id', '=', 'matricula.estudiante_id')
            ->whereIn('matricula.id', $matriculas)->whereNotNull('estudiante.usuario_id')->pluck('estudiante.usuario_id');

        Notificaciones::enviar($usuarios, 'FALTA_REGISTRADA', 'Falta registrada', "Se registró una falta en {$curso->asignatura} el {$dia}.", '/estudiante/asistencia');
    }

    /** Fechas (Y-m-d) de $desde a $hasta, ambas incluidas */
    public static function recorrer(string $desde, string $hasta): \Generator
    {
        for ($f = Carbon::parse($desde); $f->toDateString() <= $hasta; $f->addDay()) {
            yield $f->toDateString();
        }
    }
}
