<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Un curso (asignatura en un aula) con sus nombres, y la regla "solo el docente del curso" */
class Cursos
{
    public static function consulta(): Builder
    {
        return DB::table('curso_aula')
            ->join('asignatura', 'asignatura.id', '=', 'curso_aula.asignatura_id')
            ->join('aula', 'aula.id', '=', 'curso_aula.aula_id')
            ->join('grado', 'grado.id', '=', 'aula.grado_id')
            ->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->join('docente', 'docente.id', '=', 'curso_aula.docente_id')
            ->join('persona', 'persona.id', '=', 'docente.persona_id')
            ->orderBy('grado.orden')->orderBy('seccion.nombre')->orderBy('asignatura.nombre')
            ->select([
                'curso_aula.id', 'curso_aula.aula_id', 'curso_aula.docente_id', 'aula.periodo_id', 'asignatura.nombre as asignatura',
                'grado.numero', 'grado.nivel', 'seccion.nombre as seccion', 'persona.nombres', 'persona.apellidos',
            ]);
    }

    /** El curso, si el usuario puede verlo: el docente solo los suyos; el administrador, todos */
    public static function autorizado(int $id, Usuario $usuario): object
    {
        $curso = self::consulta()->where('curso_aula.id', $id)->first() ?? abort(404);
        if ($usuario->rol->codigo === 'DOCENTE') {
            abort_unless(self::docenteDe($usuario) === $curso->docente_id, 403);
        } else {
            abort_unless($usuario->rol->codigo === 'ADMIN', 403);
        }

        return $curso;
    }

    public static function docenteDe(Usuario $usuario): ?int
    {
        return DB::table('docente')->where('usuario_id', $usuario->id)->value('id');
    }

    public static function datos(object $c): array
    {
        $aula = PerfilUsuario::nombreAula($c->numero, $c->nivel, $c->seccion);

        return [
            'id' => $c->id,
            'nombre' => "{$c->asignatura} · {$aula}",
            'asignatura' => $c->asignatura,
            'aula' => $aula,
            'aula_id' => $c->aula_id,
            'docente' => self::nombreCorto($c->nombres, $c->apellidos),
        ];
    }

    /** "Luis Alberto", "Paredes Aranda" → "Luis Paredes" */
    public static function nombreCorto(string $nombres, string $apellidos): string
    {
        return explode(' ', $nombres)[0].' '.explode(' ', $apellidos)[0];
    }

    public static function datosBimestre(object $b): array
    {
        return ['id' => $b->id, 'nombre' => $b->nombre, 'estado' => $b->estado, 'fecha_inicio' => $b->fecha_inicio, 'fecha_fin' => $b->fecha_fin];
    }
}
