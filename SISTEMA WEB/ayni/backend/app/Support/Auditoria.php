<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** RNF-09: quién hizo qué y cuándo */
class Auditoria
{
    public static function registrar(string $accion, string $entidad, ?int $entidadId = null, array $detalle = [], ?int $usuarioId = null): void
    {
        self::insertar($accion, $entidad, $entidadId, $detalle, $usuarioId ?? auth()->id());
    }

    /** Acción automática del sistema (sin usuario), aunque ocurra durante la petición de alguien */
    public static function sistema(string $accion, string $entidad, ?int $entidadId = null, array $detalle = []): void
    {
        self::insertar($accion, $entidad, $entidadId, $detalle, null);
    }

    private static function insertar(string $accion, string $entidad, ?int $entidadId, array $detalle, ?int $usuarioId): void
    {
        DB::table('auditoria')->insert([
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'detalle' => $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
        ]);
    }
}
