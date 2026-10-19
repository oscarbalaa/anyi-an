<?php

namespace Tests\Soporte;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Arma asistencia y notas a propósito para probar las reglas de riesgo (M11) */
final class DatosRiesgo
{
    /**
     * Clases definitivas del curso, una por día hábil desde $desde. Todos presentes salvo las faltas indicadas
     * (matrícula => cuántas de esas clases faltó, desde la primera).
     */
    public static function clases(int $curso, int $bimestre, int $cantidad, array $faltas = [], string $desde = '2026-05-18'): void
    {
        $aula = DB::table('curso_aula')->where('id', $curso)->value('aula_id');
        $alumnos = DB::table('matricula')->where('aula_id', $aula)->pluck('id');
        $fecha = Carbon::parse($desde);
        for ($i = 0; $i < $cantidad; $i++) {
            while ($fecha->isWeekend() || DB::table('sesion_clase')->where('curso_aula_id', $curso)->where('fecha', $fecha->toDateString())->exists()) {
                $fecha->addDay();
            }
            $sesion = DB::table('sesion_clase')->insertGetId(['curso_aula_id' => $curso, 'subperiodo_id' => $bimestre, 'fecha' => $fecha->toDateString(), 'estado' => 'DEFINITIVO']);
            DB::table('asistencia')->insert($alumnos->map(fn ($m) => [
                'sesion_clase_id' => $sesion,
                'matricula_id' => $m,
                'estado' => $i < ($faltas[$m] ?? 0) ? 'F' : 'P',
            ])->all());
            $fecha->addDay();
        }
    }

    /** Evaluaciones calificadas del curso, en orden, con la nota de la matrícula (peso 1) */
    public static function notas(int $curso, int $bimestre, int $matricula, array $notas, bool $conEntrega = false): array
    {
        $ids = [];
        foreach (array_values($notas) as $i => $nota) {
            $dia = sprintf('2026-05-%02d', 19 + $i);
            $ids[] = $ev = Fabrica::evaluacion($curso, $bimestre, ['titulo' => 'Evaluación '.($i + 1), 'fecha_asignacion' => '2026-05-18', 'fecha_limite' => $dia, 'requiere_entrega' => $conEntrega]);
            DB::table('nota_evaluacion')->insert(['evaluacion_id' => $ev, 'matricula_id' => $matricula, 'nota' => $nota, 'estado_entrega' => $conEntrega ? 'ENTREGADO' : 'PENDIENTE']);
        }

        return $ids;
    }

    /** Actividades con archivo ya vencidas que nadie entregó */
    public static function vencidas(int $curso, int $bimestre, int $cantidad): array
    {
        return array_map(fn ($i) => Fabrica::evaluacion($curso, $bimestre, ['titulo' => "Tarea {$i}", 'fecha_asignacion' => '2026-05-25', 'fecha_limite' => '2026-06-0'.$i, 'requiere_entrega' => true]), range(1, $cantidad));
    }

    public static function tutor(object $e, int $usuarioDocente): int
    {
        $docente = DB::table('docente')->where('usuario_id', $usuarioDocente)->value('id');
        DB::table('aula')->where('id', $e->aula)->update(['tutor_docente_id' => $docente]);

        return $docente;
    }
}
