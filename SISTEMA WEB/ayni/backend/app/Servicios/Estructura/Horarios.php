<?php

namespace App\Servicios\Estructura;

use App\Exceptions\ErrorRegla;
use App\Support\Auditoria;
use App\Support\PerfilUsuario;
use Illuminate\Support\Facades\DB;

/** CU-08: carga académica y horario semanal (RF-23, RF-25, RN-05) */
class Horarios
{
    public const DIAS = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado'];

    public function carga(int $aulaId): array
    {
        $aula = $this->aula($aulaId);
        $cursos = DB::table('curso_aula')
            ->join('asignatura', 'asignatura.id', '=', 'curso_aula.asignatura_id')
            ->join('docente', 'docente.id', '=', 'curso_aula.docente_id')
            ->join('persona', 'persona.id', '=', 'docente.persona_id')
            ->where('curso_aula.aula_id', $aulaId)
            ->orderBy('asignatura.nombre')
            ->get([
                'curso_aula.id', 'asignatura.id as asignatura_id', 'asignatura.codigo', 'asignatura.nombre',
                'asignatura.horas_semanales', 'docente.id as docente_id', 'persona.nombres', 'persona.apellidos',
            ]);
        $bloques = DB::table('horario')->whereIn('curso_aula_id', $cursos->pluck('id'))
            ->orderBy('dia_semana')->orderBy('hora_inicio')->get();
        $minutos = $bloques->groupBy('curso_aula_id')->map(fn ($b) => $b->sum(fn ($x) => $this->minutos($x->hora_inicio, $x->hora_fin)));

        return [
            'aula' => [
                'id' => $aula->id,
                'nombre' => PerfilUsuario::nombreAula($aula->numero, $aula->nivel, $aula->seccion),
                'nivel' => $aula->nivel,
                'periodo' => $aula->periodo,
                'periodo_cerrado' => $aula->periodo_estado === 'CERRADO',
                'ambiente' => $aula->ambiente,
            ],
            'cursos' => $cursos->map(fn ($c) => [
                'id' => $c->id,
                'asignatura' => ['id' => $c->asignatura_id, 'codigo' => $c->codigo, 'nombre' => $c->nombre, 'horas_semanales' => $c->horas_semanales],
                'docente' => ['id' => $c->docente_id, 'nombre' => explode(' ', $c->nombres)[0].' '.explode(' ', $c->apellidos)[0]],
                'horas_programadas' => round(($minutos[$c->id] ?? 0) / 60, 1),
            ])->all(),
            'horario' => $bloques->map(fn ($b) => [
                'id' => $b->id,
                'curso_id' => $b->curso_aula_id,
                'dia_semana' => $b->dia_semana,
                'hora_inicio' => substr($b->hora_inicio, 0, 5),
                'hora_fin' => substr($b->hora_fin, 0, 5),
                'ambiente' => $b->ambiente,
            ])->all(),
        ];
    }

    public function asignarCurso(array $d): int
    {
        $aula = $this->aula($d['aula_id']);
        $this->abierto($aula);
        if (DB::table('curso_aula')->where('aula_id', $aula->id)->where('asignatura_id', $d['asignatura_id'])->exists()) {
            throw new ErrorRegla('Esa asignatura ya está en el aula.', 'asignatura_id');
        }
        if (DB::table('asignatura')->where('id', $d['asignatura_id'])->value('estado') !== 'ACTIVO') {
            throw new ErrorRegla('La asignatura está inactiva.', 'asignatura_id');
        }
        $id = DB::table('curso_aula')->insertGetId(['aula_id' => $aula->id, 'asignatura_id' => $d['asignatura_id'], 'docente_id' => $d['docente_id']]);
        Auditoria::registrar('CREAR', 'curso_aula', $id, $d);

        return $id;
    }

    /** Cambiar el docente de un curso: sus clases no deben cruzarse con las que ya dicta */
    public function cambiarDocente(int $cursoId, int $docenteId): void
    {
        $curso = DB::table('curso_aula')->find($cursoId) ?? abort(404);
        $this->abierto($this->aula($curso->aula_id));
        foreach (DB::table('horario')->where('curso_aula_id', $cursoId)->get() as $b) {
            $this->sinCruces($curso->aula_id, $docenteId, $b->dia_semana, $b->hora_inicio, $b->hora_fin, $cursoId);
        }
        DB::table('curso_aula')->where('id', $cursoId)->update(['docente_id' => $docenteId]);
        Auditoria::registrar('ACTUALIZAR', 'curso_aula', $cursoId, ['docente_id' => ['antes' => $curso->docente_id, 'despues' => $docenteId]]);
    }

    public function quitarCurso(int $cursoId): void
    {
        $curso = DB::table('curso_aula')->find($cursoId) ?? abort(404);
        $usado = DB::table('evaluacion')->where('curso_aula_id', $cursoId)->exists()
            || DB::table('sesion_clase')->where('curso_aula_id', $cursoId)->exists()
            || DB::table('calificacion_periodo')->where('curso_aula_id', $cursoId)->exists();
        if ($usado) {
            throw new ErrorRegla('El curso ya tiene evaluaciones, asistencia o notas: no se puede quitar. Cambia su docente si hace falta.');
        }
        DB::table('curso_aula')->where('id', $cursoId)->delete();
        Auditoria::registrar('ELIMINAR', 'curso_aula', $cursoId, ['asignatura_id' => $curso->asignatura_id, 'aula_id' => $curso->aula_id]);
    }

    public function guardarBloque(array $d, ?int $bloqueId = null): int
    {
        $curso = DB::table('curso_aula')->find($d['curso_id']) ?? abort(404);
        $aula = $this->aula($curso->aula_id);
        $this->abierto($aula);
        $inicio = $d['hora_inicio'].':00';
        $fin = $d['hora_fin'].':00';
        if ($this->minutos($inicio, $fin) < 30) {
            throw new ErrorRegla('La clase debe durar al menos 30 minutos y terminar después de empezar.', 'hora_fin');
        }
        $this->sinCruces($aula->id, $curso->docente_id, (int) $d['dia_semana'], $inicio, $fin, null, $bloqueId);

        $fila = [
            'curso_aula_id' => $curso->id,
            'dia_semana' => $d['dia_semana'],
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'ambiente' => $d['ambiente'] ?? $aula->ambiente,
        ];
        if ($bloqueId) {
            DB::table('horario')->where('id', $bloqueId)->update($fila);
        } else {
            $bloqueId = DB::table('horario')->insertGetId($fila);
        }
        Auditoria::registrar('GUARDAR_HORARIO', 'horario', $bloqueId, $fila);

        return $bloqueId;
    }

    public function quitarBloque(int $id): void
    {
        $b = DB::table('horario')->find($id) ?? abort(404);
        $this->abierto($this->aula(DB::table('curso_aula')->where('id', $b->curso_aula_id)->value('aula_id')));
        DB::table('horario')->where('id', $id)->delete();
        Auditoria::registrar('ELIMINAR', 'horario', $id);
    }

    /**
     * RN-05: ni el aula ni el docente pueden tener dos clases a la vez.
     * $cursoIgnorado: al cambiar de docente, las clases del propio curso no cuentan como cruce del docente.
     */
    private function sinCruces(int $aulaId, int $docenteId, int $dia, string $inicio, string $fin, ?int $cursoIgnorado = null, ?int $bloqueIgnorado = null): void
    {
        $periodoId = DB::table('aula')->where('id', $aulaId)->value('periodo_id');
        $choques = DB::table('horario')
            ->join('curso_aula', 'curso_aula.id', '=', 'horario.curso_aula_id')
            ->join('aula', 'aula.id', '=', 'curso_aula.aula_id')
            ->join('asignatura', 'asignatura.id', '=', 'curso_aula.asignatura_id')
            ->join('grado', 'grado.id', '=', 'aula.grado_id')
            ->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->join('docente', 'docente.id', '=', 'curso_aula.docente_id')
            ->join('persona', 'persona.id', '=', 'docente.persona_id')
            ->where('aula.periodo_id', $periodoId)
            ->where('horario.dia_semana', $dia)
            ->where('horario.hora_inicio', '<', $fin)
            ->where('horario.hora_fin', '>', $inicio)
            ->when($bloqueIgnorado, fn ($q) => $q->where('horario.id', '!=', $bloqueIgnorado))
            ->where(fn ($q) => $q->where('curso_aula.aula_id', $aulaId)->orWhere('curso_aula.docente_id', $docenteId))
            ->get([
                'curso_aula.id as curso_id', 'curso_aula.aula_id', 'curso_aula.docente_id', 'asignatura.nombre as asignatura',
                'horario.hora_inicio', 'horario.hora_fin', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion',
                'persona.nombres', 'persona.apellidos',
            ]);

        foreach ($choques as $c) {
            $cuando = 'el '.self::DIAS[$dia].' de '.substr($c->hora_inicio, 0, 5).' a '.substr($c->hora_fin, 0, 5);
            $aula = PerfilUsuario::nombreAula($c->numero, $c->nivel, $c->seccion);
            if ($c->curso_id === $cursoIgnorado) {
                continue;
            }
            if ($c->aula_id === $aulaId) {
                throw new ErrorRegla("Cruce de horario: {$aula} ya tiene {$c->asignatura} {$cuando}.", 'hora_inicio');
            }
            $docente = explode(' ', $c->nombres)[0].' '.explode(' ', $c->apellidos)[0];
            throw new ErrorRegla("Cruce de horario: {$docente} ya dicta {$c->asignatura} en {$aula} {$cuando}.", 'hora_inicio');
        }
    }

    private function aula(int $id): object
    {
        return DB::table('aula')
            ->join('grado', 'grado.id', '=', 'aula.grado_id')
            ->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->join('periodo_academico as p', 'p.id', '=', 'aula.periodo_id')
            ->where('aula.id', $id)
            ->first(['aula.*', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion', 'p.nombre as periodo', 'p.estado as periodo_estado'])
            ?? abort(404);
    }

    private function abierto(object $aula): void
    {
        if ($aula->periodo_estado === 'CERRADO') {
            throw new ErrorRegla('El periodo está cerrado: su carga y horario ya no se modifican.');
        }
    }

    private function minutos(string $inicio, string $fin): int
    {
        [$h1, $m1] = array_map('intval', explode(':', $inicio));
        [$h2, $m2] = array_map('intval', explode(':', $fin));

        return ($h2 * 60 + $m2) - ($h1 * 60 + $m1);
    }
}
