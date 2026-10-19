<?php

namespace App\Servicios\Incidencias;

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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** CU-22: incidencias de los estudiantes (RF-49, RF-50). Las ven el administrador y los docentes del estudiante */
class Incidencias
{
    public const POR_PAGINA = 10;

    public const GRAVEDADES = ['LEVE', 'MODERADA', 'GRAVE'];

    public function listar(Usuario $usuario, array $f): array
    {
        $periodo = Calendario::periodoEnCurso();
        $aulas = $periodo ? AulasDelUsuario::permitidas($usuario, $periodo->id) : collect();

        $q = $this->consulta($aulas)
            ->when($f['tipo_id'] ?? null, fn ($q, $tipo) => $q->where('i.tipo_incidencia_id', $tipo))
            ->when($f['gravedad'] ?? null, fn ($q, $g) => $q->where('i.gravedad', $g))
            ->when($f['aula_id'] ?? null, fn ($q, $a) => $q->where('m.aula_id', $a))
            ->when($f['matricula_id'] ?? null, fn ($q, $m) => $q->where('i.matricula_id', $m));
        if ($t = trim($f['buscar'] ?? '')) {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('e.codigo', 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.nombres, ' ', p.apellidos)"), 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.apellidos, ' ', p.nombres)"), 'like', $like));
        }
        $resumen = (clone $q)->selectRaw('i.gravedad, COUNT(*) as total')->groupBy('i.gravedad')->pluck('total', 'gravedad');
        $pagina = $q->orderByDesc('i.fecha')->orderByDesc('i.id')->paginate(self::POR_PAGINA, [
            'i.id', 'i.fecha', 'i.gravedad', 'i.descripcion', 'i.matricula_id', 't.nombre as tipo', 'e.codigo', 'p.nombres', 'p.apellidos',
            'g.numero', 'g.nivel', 's.nombre as seccion', 'rp.nombres as autor_nombres', 'rp.apellidos as autor_apellidos', 'ru.username as autor_usuario',
        ], 'pagina', (int) ($f['pagina'] ?? 1));

        return [
            'datos' => collect($pagina->items())->map(fn ($i) => [
                'id' => $i->id,
                'fecha' => $i->fecha,
                'gravedad' => $i->gravedad,
                'tipo' => $i->tipo,
                'descripcion' => $i->descripcion,
                'estudiante' => ['matricula_id' => $i->matricula_id, 'nombre' => "{$i->nombres} {$i->apellidos}", 'codigo' => $i->codigo],
                'aula' => PerfilUsuario::nombreAula($i->numero, $i->nivel, $i->seccion),
                'registrado_por' => $i->autor_nombres ? Cursos::nombreCorto($i->autor_nombres, $i->autor_apellidos) : $i->autor_usuario,
            ])->all(),
            'pagina' => $pagina->currentPage(),
            'por_pagina' => $pagina->perPage(),
            'total' => $pagina->total(),
            'ultima_pagina' => $pagina->lastPage(),
            'resumen' => array_merge(array_fill_keys(self::GRAVEDADES, 0), $resumen->map(fn ($n) => (int) $n)->all()),
            'tipos' => DB::table('tipo_incidencia')->orderBy('nombre')->get(['id', 'nombre', 'descripcion'])->all(),
            'aulas' => $this->nombresAulas($aulas),
        ];
    }

    /** Estudiantes con matrícula activa en las aulas que el usuario puede ver */
    public function estudiantes(Usuario $usuario, string $buscar): array
    {
        $periodo = Calendario::periodoEnCurso();
        if (! $periodo) {
            return [];
        }
        $q = DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->join('aula as a', 'a.id', '=', 'm.aula_id')->join('grado as g', 'g.id', '=', 'a.grado_id')->join('seccion as s', 's.id', '=', 'a.seccion_id')
            ->where('m.estado', 'ACTIVA')->whereIn('m.aula_id', AulasDelUsuario::permitidas($usuario, $periodo->id));
        if ($t = trim($buscar)) {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('e.codigo', 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.nombres, ' ', p.apellidos)"), 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.apellidos, ' ', p.nombres)"), 'like', $like));
        }

        return $q->orderBy('p.apellidos')->orderBy('p.nombres')->limit(20)
            ->get(['m.id', 'e.codigo', 'p.nombres', 'p.apellidos', 'g.numero', 'g.nivel', 's.nombre as seccion'])
            ->map(fn ($e) => [
                'matricula_id' => $e->id,
                'nombre' => "{$e->apellidos}, {$e->nombres}",
                'codigo' => $e->codigo,
                'aula' => PerfilUsuario::nombreAula($e->numero, $e->nivel, $e->seccion),
            ])->all();
    }

    public function registrar(array $d, Usuario $usuario): int
    {
        $periodo = Calendario::periodoEnCurso() ?? throw new ErrorRegla('No hay un periodo en curso.');
        $m = DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->where('m.id', $d['matricula_id'])->where('m.periodo_id', $periodo->id)->where('m.estado', 'ACTIVA')
            ->first(['m.id', 'm.aula_id', 'e.usuario_id', 'p.nombres', 'p.apellidos'])
            ?? throw new ErrorRegla('Elige un estudiante con matrícula activa.', 'matricula_id');
        abort_unless(AulasDelUsuario::permitidas($usuario, $periodo->id)->contains($m->aula_id), 403);
        if ($d['fecha'] > today()->toDateString() || $d['fecha'] < $periodo->fecha_inicio) {
            throw new ErrorRegla('La fecha no puede ser futura ni anterior al inicio del periodo.', 'fecha');
        }

        $id = DB::table('incidencia')->insertGetId([
            'matricula_id' => $m->id,
            'tipo_incidencia_id' => $d['tipo_incidencia_id'],
            'registrado_por' => $usuario->id,
            'fecha' => $d['fecha'],
            'gravedad' => $d['gravedad'],
            'descripcion' => $d['descripcion'],
        ]);
        Auditoria::registrar('CREAR', 'incidencia', $id, ['matricula_id' => $m->id, 'gravedad' => $d['gravedad']]);

        $tipo = DB::table('tipo_incidencia')->where('id', $d['tipo_incidencia_id'])->value('nombre');
        $gravedad = mb_strtolower($d['gravedad']);
        $dia = Carbon::parse($d['fecha'])->locale('es')->isoFormat('D [de] MMMM');
        Notificaciones::enviar([$m->usuario_id], 'INCIDENCIA_REGISTRADA', 'Incidencia registrada', "Se registró una incidencia de tipo {$tipo} ({$gravedad}) el {$dia}. Conversa con tu tutor si tienes dudas.");
        // El tutor del aula se entera; si es grave, también la administración
        $nombre = "{$m->nombres} {$m->apellidos}";
        $tutor = DB::table('aula')->join('docente', 'docente.id', '=', 'aula.tutor_docente_id')->where('aula.id', $m->aula_id)->value('docente.usuario_id');
        if ($tutor && $tutor !== $usuario->id) {
            Notificaciones::enviar([$tutor], 'INCIDENCIA_REGISTRADA', 'Incidencia en tu tutoría', "{$nombre}: {$tipo} ({$gravedad}) el {$dia}.", "/docente/incidencias?matricula={$m->id}");
        }
        if ($d['gravedad'] === 'GRAVE') {
            $admins = DB::table('usuario')->join('rol', 'rol.id', '=', 'usuario.rol_id')->where('rol.codigo', 'ADMIN')->where('usuario.estado', 'ACTIVO')
                ->where('usuario.id', '!=', $usuario->id)->pluck('usuario.id');
            Notificaciones::enviar($admins, 'INCIDENCIA_GRAVE', 'Incidencia grave', "{$nombre}: {$tipo} el {$dia}.", "/admin/incidencias?matricula={$m->id}");
        }

        return $id;
    }

    /** Aulas del periodo que ve el usuario: todas (admin) o donde el docente dicta, es tutor o coordina */
    private function consulta(Collection $aulas): Builder
    {
        return DB::table('incidencia as i')
            ->join('tipo_incidencia as t', 't.id', '=', 'i.tipo_incidencia_id')
            ->join('matricula as m', 'm.id', '=', 'i.matricula_id')
            ->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->join('aula as a', 'a.id', '=', 'm.aula_id')->join('grado as g', 'g.id', '=', 'a.grado_id')->join('seccion as s', 's.id', '=', 'a.seccion_id')
            ->join('usuario as ru', 'ru.id', '=', 'i.registrado_por')
            ->leftJoin('docente as rd', 'rd.usuario_id', '=', 'ru.id')
            ->leftJoin('personal_administrativo as ra', 'ra.usuario_id', '=', 'ru.id')
            ->leftJoin('persona as rp', 'rp.id', '=', DB::raw('COALESCE(rd.persona_id, ra.persona_id)'))
            ->whereIn('m.aula_id', $aulas);
    }

    private function nombresAulas(Collection $aulas): array
    {
        return DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->whereIn('aula.id', $aulas)->orderBy('grado.orden')->orderBy('seccion.nombre')
            ->get(['aula.id', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion'])
            ->map(fn ($a) => ['id' => $a->id, 'nombre' => PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion)])->all();
    }
}
