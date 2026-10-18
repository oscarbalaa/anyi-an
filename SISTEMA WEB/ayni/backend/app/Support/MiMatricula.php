<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/** La matrícula del estudiante con sesión en el periodo en curso (siempre por su sesión, nunca por un id que llegue) */
class MiMatricula
{
    public static function de(Usuario $usuario): ?object
    {
        $periodo = Calendario::periodoEnCurso();
        if (! $periodo) {
            return null;
        }

        return DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')
            ->join('aula as a', 'a.id', '=', 'm.aula_id')->join('grado as g', 'g.id', '=', 'a.grado_id')->join('seccion as s', 's.id', '=', 'a.seccion_id')
            ->where('e.usuario_id', $usuario->id)->where('m.periodo_id', $periodo->id)->where('m.estado', '!=', 'ANULADA')
            ->first(['m.id', 'm.aula_id', 'm.periodo_id', 'm.estado', 'a.ambiente', 'g.numero', 'g.nivel', 's.nombre as seccion']);
    }

    public static function nombreAula(object $m): string
    {
        return PerfilUsuario::nombreAula($m->numero, $m->nivel, $m->seccion);
    }
}
