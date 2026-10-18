<?php

namespace App\Servicios\Estructura;

use App\Models\Usuario;
use App\Support\Cursos;
use App\Support\MiMatricula;
use Illuminate\Support\Facades\DB;

/** RF-48: horario semanal del estudiante y su próxima clase */
class MiHorario
{
    public function de(Usuario $usuario): array
    {
        $m = MiMatricula::de($usuario);
        if (! $m) {
            return ['aula' => null, 'bloques' => [], 'clases' => [], 'proxima' => null];
        }

        $clases = DB::table('horario as h')->join('curso_aula as c', 'c.id', '=', 'h.curso_aula_id')
            ->join('asignatura as a', 'a.id', '=', 'c.asignatura_id')
            ->join('docente as d', 'd.id', '=', 'c.docente_id')->join('persona as p', 'p.id', '=', 'd.persona_id')
            ->where('c.aula_id', $m->aula_id)->orderBy('h.dia_semana')->orderBy('h.hora_inicio')
            ->get(['h.dia_semana', 'h.hora_inicio', 'h.hora_fin', 'h.ambiente', 'c.id as curso_id', 'a.nombre as asignatura', 'p.nombres', 'p.apellidos'])
            ->map(fn ($h) => [
                'dia' => (int) $h->dia_semana,
                'inicio' => substr($h->hora_inicio, 0, 5),
                'fin' => substr($h->hora_fin, 0, 5),
                'ambiente' => $h->ambiente ?? $m->ambiente,
                'curso_id' => $h->curso_id,
                'curso' => $h->asignatura,
                'docente' => Cursos::nombreCorto($h->nombres, $h->apellidos),
            ]);

        return [
            'aula' => MiMatricula::nombreAula($m),
            'bloques' => $clases->map(fn ($c) => ['inicio' => $c['inicio'], 'fin' => $c['fin']])->unique()->sortBy('inicio')->values()->all(),
            'clases' => $clases->all(),
            'proxima' => $this->proxima($clases->all()),
        ];
    }

    /** La siguiente clase desde ahora (hoy más tarde o en los próximos días) */
    private function proxima(array $clases): ?array
    {
        if (! $clases) {
            return null;
        }
        $ahora = now();
        for ($d = 0; $d < 7; $d++) {
            $dia = $ahora->copy()->addDays($d);
            $hora = $d === 0 ? $ahora->format('H:i') : '00:00';
            foreach ($clases as $c) {
                if ($c['dia'] === $dia->dayOfWeekIso && $c['inicio'] > $hora) {
                    return $c + ['fecha' => $dia->toDateString()];
                }
            }
        }

        return null;
    }
}
