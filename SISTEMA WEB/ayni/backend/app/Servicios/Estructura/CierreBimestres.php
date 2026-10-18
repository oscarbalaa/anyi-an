<?php

namespace App\Servicios\Estructura;

use App\Support\Auditoria;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * RN-14: al llegar la fecha de cierre de notas el bimestre se cierra solo.
 * Sin cron (el hosting no lo tiene): se revisa con el uso del sistema. El M9 agrega aquí el cálculo de promedios.
 */
class CierreBimestres
{
    /** Revisa como máximo una vez por hora; con $forzar, siempre */
    public function revisar(bool $forzar = false): int
    {
        if (! $forzar && ! Cache::add('ayni:cierre-bimestres:'.now()->format('Y-m-d-H'), true, 3600)) {
            return 0;
        }

        $vencidos = DB::table('subperiodo')
            ->where('estado', 'ABIERTO')
            ->where('fecha_cierre_notas', '<', today()->toDateString())
            ->pluck('id');

        foreach ($vencidos as $id) {
            $this->cerrar($id, null);
        }

        return $vencidos->count();
    }

    /** $usuarioId null = cierre automático */
    public function cerrar(int $bimestreId, ?int $usuarioId): void
    {
        $cerrados = DB::table('subperiodo')->where('id', $bimestreId)->where('estado', 'ABIERTO')
            ->update(['estado' => 'CERRADO', 'cerrado_en' => now(), 'cerrado_por' => $usuarioId]);

        if ($cerrados && $usuarioId) {
            Auditoria::registrar('CERRAR_BIMESTRE', 'subperiodo', $bimestreId, [], $usuarioId);
        } elseif ($cerrados) {
            Auditoria::sistema('CERRAR_BIMESTRE', 'subperiodo', $bimestreId, ['automatico' => true]);
        }
    }
}
