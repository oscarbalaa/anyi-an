<?php

namespace App\Servicios\Riesgo;

use App\Exceptions\ErrorRegla;
use App\Models\Usuario;
use App\Servicios\Notificaciones;
use App\Support\Auditoria;
use App\Support\AulasDelUsuario;
use App\Support\Calendario;
use App\Support\Cursos;
use App\Support\PerfilUsuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** CU-25: seguimiento de las alertas de riesgo (RF-55) */
class Alertas
{
    public const POR_PAGINA = 10;

    public const ESTADOS = ['NUEVA', 'EN_SEGUIMIENTO', 'CERRADA', 'DESCARTADA'];

    public const TIPOS_INTERVENCION = ['Reunión con el apoderado', 'Entrevista con el estudiante', 'Reforzamiento académico', 'Llamada telefónica', 'Derivación a psicología', 'Otro'];

    /** Estados a los que se puede pasar desde cada uno; cerrada y descartada son finales */
    public const TRANSICIONES = ['NUEVA' => ['EN_SEGUIMIENTO', 'DESCARTADA'], 'EN_SEGUIMIENTO' => ['CERRADA', 'DESCARTADA'], 'CERRADA' => [], 'DESCARTADA' => []];

    private const TEXTO_ESTADO = ['NUEVA' => 'Nueva', 'EN_SEGUIMIENTO' => 'En seguimiento', 'CERRADA' => 'Cerrada', 'DESCARTADA' => 'Descartada'];

    public static function historial(int $alerta, string $accion, ?string $detalle, ?int $usuario): void
    {
        DB::table('historial_alerta')->insert([
            'alerta_id' => $alerta,
            'accion' => $accion,
            'detalle' => $detalle === null ? null : mb_substr($detalle, 0, 400),
            'usuario_id' => $usuario,
            'fecha' => now(),
        ]);
    }

    /** El docente ve las alertas de sus aulas; por defecto, solo las que tiene a cargo */
    public function listar(Usuario $usuario, array $f): array
    {
        $periodo = Calendario::periodoEnCurso();
        $aulas = $periodo ? AulasDelUsuario::permitidas($usuario, $periodo->id) : collect();
        $docente = Cursos::docenteDe($usuario);
        $mias = $usuario->rol->codigo === 'DOCENTE' && ($f['encargado'] ?? 'mias') === 'mias';

        $base = $this->consulta($aulas)->when($mias, fn ($q) => $q->where('al.responsable_docente_id', $docente));
        $conteo = (clone $base)->selectRaw("
            SUM(al.estado IN ('NUEVA', 'EN_SEGUIMIENTO')) as abiertas,
            SUM(al.estado = 'NUEVA') as nuevas,
            SUM(al.estado = 'EN_SEGUIMIENTO') as en_seguimiento,
            SUM(al.estado IN ('NUEVA', 'EN_SEGUIMIENTO') AND NOT EXISTS (SELECT 1 FROM intervencion i WHERE i.alerta_id = al.id)) as sin_intervencion,
            SUM(al.estado IN ('CERRADA', 'DESCARTADA')) as cerradas")->first();

        $estado = $f['estado'] ?? 'abiertas';
        $q = $base
            ->when($estado === 'abiertas', fn ($q) => $q->whereIn('al.estado', ['NUEVA', 'EN_SEGUIMIENTO']))
            ->when(in_array($estado, self::ESTADOS, true), fn ($q) => $q->where('al.estado', $estado))
            ->when($f['nivel'] ?? null, fn ($q, $n) => $q->where('p.nivel_riesgo', $n))
            ->when($f['aula_id'] ?? null, fn ($q, $a) => $q->where('m.aula_id', $a))
            ->when($f['bimestre_id'] ?? null, fn ($q, $b) => $q->where('al.subperiodo_id', $b));
        if ($t = trim($f['buscar'] ?? '')) {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('e.codigo', 'like', $like)
                ->orWhere(DB::raw("CONCAT(pe.nombres, ' ', pe.apellidos)"), 'like', $like)
                ->orWhere(DB::raw("CONCAT(pe.apellidos, ' ', pe.nombres)"), 'like', $like));
        }

        $pagina = $q->orderByRaw("al.estado IN ('CERRADA', 'DESCARTADA')")->orderByRaw("FIELD(p.nivel_riesgo, 'ALTO', 'MEDIO', 'BAJO')")
            ->orderByDesc('p.probabilidad')->orderBy('al.fecha_creacion')->orderBy('al.id')
            ->paginate(self::POR_PAGINA, [
                'al.id', 'al.estado', 'al.fecha_creacion', 'al.matricula_id', 'al.responsable_docente_id', 'p.id as prediccion_id', 'p.nivel_riesgo', 'p.probabilidad',
                'e.codigo', 'pe.nombres', 'pe.apellidos', 'g.numero', 'g.nivel', 's.nombre as seccion', 'sp.nombre as bimestre', 'rp.nombres as r_nombres', 'rp.apellidos as r_apellidos',
            ], 'pagina', (int) ($f['pagina'] ?? 1));

        $filas = collect($pagina->items());
        $principales = $this->factoresPrincipales($filas->pluck('prediccion_id')->all());
        $intervenciones = DB::table('intervencion')->whereIn('alerta_id', $filas->pluck('id'))->selectRaw('alerta_id, COUNT(*) as total')->groupBy('alerta_id')->pluck('total', 'alerta_id');

        return [
            'datos' => $filas->map(fn ($a) => [
                'id' => $a->id,
                'estudiante' => ['matricula_id' => $a->matricula_id, 'nombre' => "{$a->nombres} {$a->apellidos}", 'codigo' => $a->codigo],
                'aula' => PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion),
                'bimestre' => $a->bimestre,
                'nivel' => $a->nivel_riesgo,
                'probabilidad' => (int) round($a->probabilidad * 100),
                'estado' => $a->estado,
                'factor_principal' => $principales[$a->prediccion_id] ?? null,
                'responsable' => Cursos::nombreCorto($a->r_nombres, $a->r_apellidos),
                'a_mi_cargo' => $docente !== null && $a->responsable_docente_id === $docente,
                'intervenciones' => (int) ($intervenciones[$a->id] ?? 0),
                'creada' => Carbon::parse($a->fecha_creacion)->toIso8601String(),
            ])->all(),
            'pagina' => $pagina->currentPage(),
            'por_pagina' => $pagina->perPage(),
            'total' => $pagina->total(),
            'ultima_pagina' => $pagina->lastPage(),
            'resumen' => collect((array) $conteo)->map(fn ($n) => (int) $n)->all(),
            'aulas' => $periodo ? DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
                ->whereIn('aula.id', $aulas)->orderBy('grado.orden')->orderBy('seccion.nombre')
                ->get(['aula.id', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion'])
                ->map(fn ($x) => ['id' => $x->id, 'nombre' => PerfilUsuario::nombreAula($x->numero, $x->nivel, $x->seccion)])->all() : [],
        ];
    }

    public function detalle(int $id, Usuario $usuario): array
    {
        $a = $this->alerta($id, $usuario);
        $p = DB::table('prediccion_riesgo as p')->join('modelo_ml as mo', 'mo.id', '=', 'p.modelo_id')->where('p.id', $a->prediccion_id)
            ->first(['p.id', 'p.nivel_riesgo', 'p.probabilidad', 'p.fecha', 'p.resumen_texto', 'p.subperiodo_id', 'mo.nombre as modelo', 'mo.version']);
        $anterior = DB::table('prediccion_riesgo as p')->join('subperiodo as sp', 'sp.id', '=', 'p.subperiodo_id')
            ->where('p.matricula_id', $a->matricula_id)->where('sp.periodo_id', $a->periodo_id)->where('sp.numero', '<', $a->numero_bimestre)
            ->orderByDesc('sp.numero')->orderByDesc('p.id')->first(['p.nivel_riesgo', 'p.probabilidad', 'sp.nombre']);
        [$gestionar, $reasignar] = $this->permisos($a, $usuario);
        $intervenciones = DB::table('intervencion')->where('alerta_id', $a->id)->orderByDesc('fecha')->orderByDesc('id')->get();
        $historial = DB::table('historial_alerta')->where('alerta_id', $a->id)->orderBy('fecha')->orderBy('id')->get();
        $nombres = $this->nombresUsuarios($intervenciones->pluck('responsable_id')->concat($historial->pluck('usuario_id'))->filter()->unique()->all());

        return [
            'alerta' => [
                'id' => $a->id,
                'estado' => $a->estado,
                'creada' => Carbon::parse($a->fecha_creacion)->toIso8601String(),
                'cerrada' => $a->fecha_cierre ? Carbon::parse($a->fecha_cierre)->toIso8601String() : null,
                'justificacion' => $a->justificacion_cambio,
                'bimestre' => $a->bimestre,
            ],
            'estudiante' => [
                'matricula_id' => $a->matricula_id,
                'nombre' => "{$a->nombres} {$a->apellidos}",
                'codigo' => $a->codigo,
                'aula' => PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion),
                'nivel_educativo' => $a->nivel === 'PRIMARIA' ? 'Primaria' : 'Secundaria',
            ],
            'prediccion' => [
                'nivel' => $p->nivel_riesgo,
                'probabilidad' => (int) round($p->probabilidad * 100),
                'fecha' => Carbon::parse($p->fecha)->toIso8601String(),
                'modelo' => "{$p->modelo} {$p->version}",
                'resumen' => $p->resumen_texto,
                'factores' => DB::table('factor_riesgo')->where('prediccion_id', $p->id)->orderByDesc('contribucion')->orderBy('id')->get()
                    ->map(fn ($x) => [
                        'variable' => $x->variable,
                        'titulo' => MotorReglas::TITULOS[$x->variable] ?? $x->variable,
                        'valor' => $x->valor,
                        'contribucion' => (float) $x->contribucion,
                        'descripcion' => $x->descripcion,
                    ])->all(),
                'recomendaciones' => DB::table('recomendacion')->where('prediccion_id', $p->id)->orderBy('prioridad')->orderBy('id')->get(['descripcion', 'prioridad'])
                    ->map(fn ($x) => ['descripcion' => $x->descripcion, 'prioridad' => (int) $x->prioridad])->all(),
            ],
            'anterior' => $anterior ? ['bimestre' => $anterior->nombre, 'nivel' => $anterior->nivel_riesgo, 'probabilidad' => (int) round($anterior->probabilidad * 100)] : null,
            'responsable' => ['docente_id' => $a->responsable_docente_id, 'nombre' => Cursos::nombreCorto($a->r_nombres, $a->r_apellidos)],
            'permisos' => ['gestionar' => $gestionar, 'reasignar' => $reasignar && $this->abierta($a)],
            'transiciones' => $gestionar ? self::TRANSICIONES[$a->estado] : [],
            'candidatos' => $reasignar && $this->abierta($a) ? $this->candidatos($a->aula_id) : [],
            'intervenciones' => $intervenciones->map(fn ($i) => [
                'id' => $i->id, 'tipo' => $i->tipo, 'descripcion' => $i->descripcion, 'fecha' => $i->fecha, 'resultado' => $i->resultado,
                'registrado_por' => $nombres[$i->responsable_id] ?? 'Usuario',
            ])->all(),
            'historial' => $historial->map(fn ($h) => [
                'accion' => $h->accion,
                'detalle' => $h->detalle,
                'usuario' => $h->usuario_id ? ($nombres[$h->usuario_id] ?? 'Usuario') : 'Sistema',
                'fecha' => Carbon::parse($h->fecha)->toIso8601String(),
            ])->all(),
            'tipos_intervencion' => self::TIPOS_INTERVENCION,
        ];
    }

    public function cambiarEstado(int $id, string $estado, ?string $justificacion, Usuario $usuario): array
    {
        $a = $this->alerta($id, $usuario);
        abort_unless($this->permisos($a, $usuario)[0], 403);
        if (! in_array($estado, self::TRANSICIONES[$a->estado], true)) {
            throw new ErrorRegla(match (true) {
                ! $this->abierta($a) => 'La alerta ya está '.mb_strtolower(self::TEXTO_ESTADO[$a->estado]).': no cambia de estado.',
                $estado === 'CERRADA' => 'Primero pasa la alerta a seguimiento y registra una intervención.',
                default => 'La alerta ya está en ese estado.',
            }, 'estado');
        }
        if ($estado === 'CERRADA' && ! DB::table('intervencion')->where('alerta_id', $a->id)->exists()) {
            throw new ErrorRegla('Registra al menos una intervención antes de cerrar la alerta.', 'estado');
        }

        DB::transaction(function () use ($a, $estado, $justificacion, $usuario) {
            $final = in_array($estado, ['CERRADA', 'DESCARTADA'], true);
            DB::table('alerta_estudiantil')->where('id', $a->id)->update([
                'estado' => $estado,
                'justificacion_cambio' => $justificacion ?? $a->justificacion_cambio,
                'fecha_cierre' => $final ? now() : null,
            ]);
            $detalle = self::TEXTO_ESTADO[$a->estado].' → '.self::TEXTO_ESTADO[$estado].($justificacion ? ". {$justificacion}" : '.');
            self::historial($a->id, $estado === 'CERRADA' ? 'CERRADA' : 'ESTADO_CAMBIADO', $detalle, $usuario->id);
            Auditoria::registrar('CAMBIAR_ESTADO_ALERTA', 'alerta_estudiantil', $a->id, ['antes' => $a->estado, 'despues' => $estado]);
        });

        $mensaje = ['EN_SEGUIMIENTO' => 'La alerta pasó a seguimiento.', 'CERRADA' => 'Alerta cerrada.', 'DESCARTADA' => 'Alerta descartada.'][$estado];

        return ['mensaje' => $mensaje] + $this->detalle($id, $usuario);
    }

    public function registrarIntervencion(int $id, array $d, Usuario $usuario): array
    {
        $a = $this->alerta($id, $usuario);
        abort_unless($this->permisos($a, $usuario)[0], 403);
        if (! $this->abierta($a)) {
            throw new ErrorRegla('La alerta ya está cerrada: no admite intervenciones.');
        }
        if ($d['fecha'] > today()->toDateString() || $d['fecha'] < Carbon::parse($a->fecha_creacion)->toDateString()) {
            throw new ErrorRegla('La fecha debe estar entre la creación de la alerta y hoy.', 'fecha');
        }

        DB::transaction(function () use ($a, $d, $usuario) {
            $intervencion = DB::table('intervencion')->insertGetId([
                'alerta_id' => $a->id,
                'responsable_id' => $usuario->id,
                'tipo' => $d['tipo'],
                'descripcion' => $d['descripcion'],
                'fecha' => $d['fecha'],
                'resultado' => $d['resultado'] ?? null,
            ]);
            self::historial($a->id, 'INTERVENCION', "{$d['tipo']}: {$d['descripcion']}", $usuario->id);
            if ($a->estado === 'NUEVA') {
                DB::table('alerta_estudiantil')->where('id', $a->id)->update(['estado' => 'EN_SEGUIMIENTO']);
                self::historial($a->id, 'ESTADO_CAMBIADO', 'Nueva → En seguimiento, al registrar la primera intervención.', $usuario->id);
            }
            Auditoria::registrar('REGISTRAR_INTERVENCION', 'alerta_estudiantil', $a->id, ['intervencion_id' => $intervencion, 'tipo' => $d['tipo']]);
        });

        return ['mensaje' => 'Intervención registrada.'] + $this->detalle($id, $usuario);
    }

    /** Solo la administración y la coordinación del aula cambian al encargado, con justificación */
    public function reasignar(int $id, int $docenteId, string $justificacion, Usuario $usuario): array
    {
        $a = $this->alerta($id, $usuario);
        abort_unless($this->permisos($a, $usuario)[1], 403);
        if (! $this->abierta($a)) {
            throw new ErrorRegla('La alerta ya está cerrada: no cambia de encargado.');
        }
        if ($docenteId === $a->responsable_docente_id) {
            throw new ErrorRegla('La alerta ya está a cargo de ese docente.', 'docente_id');
        }
        $nuevo = collect($this->candidatos($a->aula_id))->firstWhere('docente_id', $docenteId)
            ?? throw new ErrorRegla('Elige a un docente del estudiante: su tutor, uno de sus profesores o la coordinación.', 'docente_id');

        DB::transaction(function () use ($a, $nuevo, $justificacion, $usuario) {
            DB::table('alerta_estudiantil')->where('id', $a->id)->update(['responsable_docente_id' => $nuevo['docente_id']]);
            self::historial($a->id, 'REASIGNADA', "{$this->nombresUsuarios([$usuario->id])[$usuario->id]} reasignó a {$nuevo['nombre']}: {$justificacion}", $usuario->id);
            Auditoria::registrar('REASIGNAR_ALERTA', 'alerta_estudiantil', $a->id, ['antes' => $a->responsable_docente_id, 'despues' => $nuevo['docente_id']]);
            $usuarioNuevo = DB::table('docente')->where('id', $nuevo['docente_id'])->value('usuario_id');
            Notificaciones::enviar([$usuarioNuevo], 'ALERTA_ASIGNADA', 'Se te asignó una alerta', "Ahora tienes a cargo el seguimiento de {$a->nombres} {$a->apellidos}. Motivo: {$justificacion}", "/docente/alertas?id={$a->id}");
        });

        return ['mensaje' => 'Encargado actualizado.'] + $this->detalle($id, $usuario);
    }

    private function consulta($aulas): Builder
    {
        return DB::table('alerta_estudiantil as al')
            ->join('prediccion_riesgo as p', 'p.id', '=', 'al.prediccion_id')
            ->join('subperiodo as sp', 'sp.id', '=', 'al.subperiodo_id')
            ->join('matricula as m', 'm.id', '=', 'al.matricula_id')
            ->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as pe', 'pe.id', '=', 'e.persona_id')
            ->join('aula as au', 'au.id', '=', 'm.aula_id')->join('grado as g', 'g.id', '=', 'au.grado_id')->join('seccion as s', 's.id', '=', 'au.seccion_id')
            ->join('docente as rd', 'rd.id', '=', 'al.responsable_docente_id')->join('persona as rp', 'rp.id', '=', 'rd.persona_id')
            ->whereIn('m.aula_id', $aulas);
    }

    /** La alerta, si el usuario puede verla (404 si no existe, 403 si es de otra aula) */
    private function alerta(int $id, Usuario $usuario): object
    {
        $a = DB::table('alerta_estudiantil as al')
            ->join('subperiodo as sp', 'sp.id', '=', 'al.subperiodo_id')
            ->join('matricula as m', 'm.id', '=', 'al.matricula_id')
            ->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as pe', 'pe.id', '=', 'e.persona_id')
            ->join('aula as au', 'au.id', '=', 'm.aula_id')->join('grado as g', 'g.id', '=', 'au.grado_id')->join('seccion as s', 's.id', '=', 'au.seccion_id')
            ->join('docente as rd', 'rd.id', '=', 'al.responsable_docente_id')->join('persona as rp', 'rp.id', '=', 'rd.persona_id')
            ->where('al.id', $id)
            ->first(['al.*', 'sp.nombre as bimestre', 'sp.numero as numero_bimestre', 'sp.periodo_id', 'm.aula_id', 'au.tutor_docente_id', 'e.codigo', 'pe.nombres', 'pe.apellidos',
                'g.numero', 'g.nivel', 's.nombre as seccion', 'rp.nombres as r_nombres', 'rp.apellidos as r_apellidos']);
        abort_unless($a, 404);
        abort_unless(AulasDelUsuario::permitidas($usuario, $a->periodo_id)->contains($a->aula_id), 403);

        return $a;
    }

    /** @return array{0: bool, 1: bool} [gestionar, reasignar] */
    private function permisos(object $a, Usuario $usuario): array
    {
        if ($usuario->rol->codigo === 'ADMIN') {
            return [true, true];
        }
        $docente = Cursos::docenteDe($usuario);
        $coordina = $docente && AulasDelUsuario::coordinadas($docente, $a->periodo_id)->contains($a->aula_id);

        return [$coordina || $docente === $a->responsable_docente_id || $docente === $a->tutor_docente_id, $coordina];
    }

    private function abierta(object $a): bool
    {
        return in_array($a->estado, ['NUEVA', 'EN_SEGUIMIENTO'], true);
    }

    /** Docentes activos del estudiante: tutor, profesores de sus cursos y coordinación del aula */
    private function candidatos(int $aulaId): array
    {
        $aula = DB::table('aula')->where('id', $aulaId)->first(['id', 'tutor_docente_id', 'periodo_id']);
        $roles = [];
        if ($aula->tutor_docente_id) {
            $roles[$aula->tutor_docente_id][] = 'Tutor(a)';
        }
        foreach (DB::table('curso_aula as c')->join('asignatura as a', 'a.id', '=', 'c.asignatura_id')->where('c.aula_id', $aulaId)->orderBy('a.nombre')->get(['c.docente_id', 'a.nombre']) as $c) {
            $roles[$c->docente_id][] = $c->nombre;
        }
        $coordinadores = DB::table('coordinacion')->where('periodo_id', $aula->periodo_id)->pluck('docente_id')->unique()
            ->filter(fn ($d) => AulasDelUsuario::coordinadas($d, $aula->periodo_id)->contains($aulaId));
        foreach ($coordinadores as $d) {
            $roles[$d][] = 'Coordinación';
        }

        return DB::table('docente as d')->join('persona as p', 'p.id', '=', 'd.persona_id')->whereIn('d.id', array_keys($roles))->where('d.estado', 'ACTIVO')
            ->orderBy('p.apellidos')->get(['d.id', 'p.nombres', 'p.apellidos'])
            ->map(fn ($d) => ['docente_id' => $d->id, 'nombre' => Cursos::nombreCorto($d->nombres, $d->apellidos), 'rol' => implode(', ', array_unique($roles[$d->id]))])
            ->all();
    }

    /** La descripción del factor que más pesa en cada predicción */
    private function factoresPrincipales(array $predicciones): array
    {
        return DB::table('factor_riesgo')->whereIn('prediccion_id', $predicciones)->orderByDesc('contribucion')->orderBy('id')->get(['prediccion_id', 'descripcion'])
            ->groupBy('prediccion_id')->map(fn ($g) => $g->first()->descripcion)->all();
    }

    /** @return array<int, string> nombre corto de cada usuario (docente o administrativo) o su usuario */
    private function nombresUsuarios(array $ids): array
    {
        return DB::table('usuario as u')->leftJoin('docente as d', 'd.usuario_id', '=', 'u.id')->leftJoin('personal_administrativo as pa', 'pa.usuario_id', '=', 'u.id')
            ->leftJoin('persona as p', 'p.id', '=', DB::raw('COALESCE(d.persona_id, pa.persona_id)'))
            ->whereIn('u.id', $ids)->get(['u.id', 'u.username', 'p.nombres', 'p.apellidos'])
            ->mapWithKeys(fn ($u) => [$u->id => $u->nombres ? Cursos::nombreCorto($u->nombres, $u->apellidos) : $u->username])->all();
    }
}
