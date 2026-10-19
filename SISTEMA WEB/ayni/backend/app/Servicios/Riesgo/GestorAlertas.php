<?php

namespace App\Servicios\Riesgo;

use App\Servicios\Notificaciones;
use App\Support\AulasDelUsuario;
use App\Support\Cursos;
use App\Support\PerfilUsuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** RN-20 y RN-21: una alerta activa por estudiante y bimestre, asignada al tutor o al coordinador */
class GestorAlertas
{
    private array $activas;

    private array $cerradas;

    private array $responsables = [];

    public function __construct(private readonly Collection $alumnos, private readonly object $bimestre)
    {
        $ids = $alumnos->keys()->all();
        $this->activas = DB::table('alerta_estudiantil')->where('subperiodo_id', $bimestre->id)->whereIn('matricula_id', $ids)
            ->whereIn('estado', ['NUEVA', 'EN_SEGUIMIENTO'])->get()->keyBy('matricula_id')->all();
        // La más grave de las ya cerradas o descartadas: solo una alerta de mayor nivel vuelve a abrir el caso
        $this->cerradas = DB::table('alerta_estudiantil as al')->join('prediccion_riesgo as p', 'p.id', '=', 'al.prediccion_id')
            ->where('al.subperiodo_id', $bimestre->id)->whereIn('al.matricula_id', $ids)->whereIn('al.estado', ['CERRADA', 'DESCARTADA'])
            ->get(['al.matricula_id', 'p.nivel_riesgo'])->groupBy('matricula_id')
            ->map(fn ($g) => $g->max(fn ($x) => MotorReglas::NIVELES[$x->nivel_riesgo]))->all();
    }

    /** Devuelve true si creó una alerta nueva */
    public function procesar(int $matricula, int $prediccion, array $r, ?string $nivelPrevio, bool $cambio): bool
    {
        $nivel = MotorReglas::NIVELES[$r['nivel']];
        if ($activa = $this->activas[$matricula] ?? null) {
            if ($cambio) {
                DB::table('alerta_estudiantil')->where('id', $activa->id)->update(['prediccion_id' => $prediccion]);
                if ($nivelPrevio && $nivelPrevio !== $r['nivel']) {
                    $detalle = "De {$this->texto($nivelPrevio)} a {$this->texto($r['nivel'])}.".($r['nivel'] === 'BAJO' ? ' El riesgo bajó: revisa si ya se puede cerrar.' : '');
                    $this->historial($activa->id, 'NIVEL_ACTUALIZADO', $detalle);
                }
            }

            return false;
        }
        if ($nivel === 0 || (isset($this->cerradas[$matricula]) && $nivel <= $this->cerradas[$matricula])) {
            return false;
        }
        // La predicción ya puede estar unida a una alerta anterior (por ejemplo, descartada en el mismo nivel)
        if (DB::table('alerta_estudiantil')->where('prediccion_id', $prediccion)->exists()) {
            return false;
        }

        $alumno = $this->alumnos[$matricula];
        [$docente, $motivo] = $this->responsable($alumno->aula_id);
        if (! $docente) {
            $this->administrativas()->aulaSinTutor($alumno->aula_id);

            return false;
        }

        try {
            $id = DB::table('alerta_estudiantil')->insertGetId([
                'prediccion_id' => $prediccion,
                'matricula_id' => $matricula,
                'subperiodo_id' => $this->bimestre->id,
                'responsable_docente_id' => $docente->id,
                'estado' => 'NUEVA',
                'fecha_creacion' => now(),
            ]);
        } catch (QueryException) {
            // Otra petición la creó en paralelo (índice ux_alerta_activa)
            return false;
        }
        $this->activas[$matricula] = (object) ['id' => $id];
        $principal = $r['factores'][0]['descripcion'] ?? '';
        $this->historial($id, 'GENERADA', "Riesgo {$this->texto($r['nivel'])} (puntaje ".round($r['probabilidad'] * 100).' %). '.$principal);
        $this->historial($id, 'ASIGNADA_DOCENTE_AULA', "Asignada a {$docente->nombre} por ser {$motivo}.");
        $nombre = "{$alumno->nombres} {$alumno->apellidos}";
        Notificaciones::enviar([$docente->usuario_id], 'ALERTA_ASIGNADA', "Alerta de riesgo {$this->texto($r['nivel'])}", "{$nombre} necesita seguimiento: {$principal}", "/docente/alertas?id={$id}");

        return true;
    }

    /** @return array{0: ?object, 1: string} */
    private function responsable(int $aulaId): array
    {
        return $this->responsables[$aulaId] ??= (function () use ($aulaId) {
            $aula = DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
                ->leftJoin('docente as d', fn ($j) => $j->on('d.id', '=', 'aula.tutor_docente_id')->where('d.estado', 'ACTIVO'))
                ->where('aula.id', $aulaId)->first(['d.id as tutor', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion']);
            $nombreAula = PerfilUsuario::nombreAula($aula->numero, $aula->nivel, $aula->seccion);
            if ($aula->tutor) {
                return [$this->docente($aula->tutor), "tutor(a) de {$nombreAula}"];
            }
            $coordinador = AulasDelUsuario::coordinadorDe($aulaId);

            return $coordinador ? [$this->docente($coordinador), "coordinador(a) de {$nombreAula}, que no tiene tutor"] : [null, ''];
        })();
    }

    private function docente(int $id): object
    {
        $d = DB::table('docente as d')->join('persona as p', 'p.id', '=', 'd.persona_id')->where('d.id', $id)->first(['d.id', 'd.usuario_id', 'p.nombres', 'p.apellidos']);
        $d->nombre = Cursos::nombreCorto($d->nombres, $d->apellidos);

        return $d;
    }

    private function historial(int $alerta, string $accion, string $detalle): void
    {
        Alertas::historial($alerta, $accion, $detalle, null);
    }

    private function texto(string $nivel): string
    {
        return mb_strtolower($nivel);
    }

    private function administrativas(): AlertasAdministrativas
    {
        return app(AlertasAdministrativas::class);
    }
}
