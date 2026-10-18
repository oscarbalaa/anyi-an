<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/** Datos del usuario con sesión que necesita la interfaz */
class PerfilUsuario
{
    public static function de(Usuario $usuario): array
    {
        $rol = $usuario->rol->codigo;
        $tabla = ['ADMIN' => 'personal_administrativo', 'DOCENTE' => 'docente', 'ESTUDIANTE' => 'estudiante'][$rol];

        $persona = DB::table($tabla)
            ->join('persona', 'persona.id', '=', "{$tabla}.persona_id")
            ->where("{$tabla}.usuario_id", $usuario->id)
            ->select('persona.nombres', 'persona.apellidos', "{$tabla}.id as perfil_id")
            ->first();

        $nombre = $persona ? trim(explode(' ', $persona->nombres)[0].' '.explode(' ', $persona->apellidos)[0]) : $usuario->username;

        return [
            'id' => $usuario->id,
            'usuario' => $usuario->username,
            'rol' => $rol,
            'acceso' => Acceso::desdeRol($rol),
            'nombre' => $nombre,
            'nombre_completo' => $persona ? "{$persona->nombres} {$persona->apellidos}" : $usuario->username,
            'iniciales' => self::iniciales($nombre),
            'detalle' => $persona ? self::detalle($rol, $persona->perfil_id) : null,
            'correo' => $usuario->email,
            'debe_cambiar_contrasena' => (bool) $usuario->debe_cambiar_password,
            'ultimo_acceso' => $usuario->ultimo_acceso?->toIso8601String(),
        ];
    }

    private static function iniciales(string $nombre): string
    {
        $partes = preg_split('/\s+/', $nombre);

        return mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
    }

    /** Texto bajo el nombre: aula del estudiante, coordinación o cargo */
    private static function detalle(string $rol, int $perfilId): ?string
    {
        if ($rol === 'ADMIN') {
            return DB::table('personal_administrativo')->where('id', $perfilId)->value('cargo') ?? 'Administrador';
        }

        return $rol === 'ESTUDIANTE'
            ? self::aulas([$perfilId])[$perfilId] ?? 'Estudiante'
            : self::coordinaciones([$perfilId])[$perfilId] ?? 'Docente';
    }

    private static function periodoEnCurso(): ?int
    {
        return DB::table('periodo_academico')->where('estado', 'EN_CURSO')->value('id');
    }

    /** Aula de cada estudiante en el periodo en curso: [estudiante_id => "3.º B"] */
    public static function aulas(array $estudianteIds): array
    {
        if (! $estudianteIds) {
            return [];
        }

        return DB::table('matricula')
            ->join('aula', 'aula.id', '=', 'matricula.aula_id')
            ->join('grado', 'grado.id', '=', 'aula.grado_id')
            ->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->whereIn('matricula.estudiante_id', $estudianteIds)
            ->where('matricula.periodo_id', self::periodoEnCurso())
            ->where('matricula.estado', 'ACTIVA')
            ->get(['matricula.estudiante_id', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion'])
            ->mapWithKeys(fn ($a) => [$a->estudiante_id => self::nombreAula($a->numero, $a->nivel, $a->seccion)])
            ->all();
    }

    /** Coordinación de cada docente en el periodo en curso: [docente_id => "Coordinador 3.º A"] */
    public static function coordinaciones(array $docenteIds): array
    {
        if (! $docenteIds) {
            return [];
        }

        return DB::table('coordinacion')
            ->leftJoin('aula', 'aula.id', '=', 'coordinacion.aula_id')
            ->leftJoin('grado', 'grado.id', '=', 'aula.grado_id')
            ->leftJoin('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->whereIn('coordinacion.docente_id', $docenteIds)
            ->where('coordinacion.periodo_id', self::periodoEnCurso())
            ->get(['coordinacion.docente_id', 'coordinacion.ambito', 'coordinacion.nivel', 'grado.numero', 'grado.nivel as nivel_aula', 'seccion.nombre as seccion'])
            ->mapWithKeys(fn ($c) => [$c->docente_id => match ($c->ambito) {
                'AULA' => 'Coordinador '.self::nombreAula($c->numero, $c->nivel_aula, $c->seccion),
                'NIVEL' => 'Coordinación de '.mb_strtolower($c->nivel),
                'GRADO' => 'Coordinación de grado',
                default => 'Coordinación académica',
            }])
            ->all();
    }

    public static function nombreAula(int $numero, string $nivel, string $seccion): string
    {
        $texto = "{$numero}.º";

        return $nivel === 'PRIMARIA'
            ? $texto.' primaria'.($seccion === 'Única' ? '' : " {$seccion}")
            : $texto.($seccion === 'Única' ? ' secundaria' : " {$seccion}");
    }
}
