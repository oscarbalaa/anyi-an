<?php

namespace App\Servicios\Reportes;

use App\Exceptions\ErrorRegla;
use App\Models\Usuario;
use App\Servicios\Asistencia\ReportesAsistencia;
use App\Servicios\Evaluacion\Calificaciones;
use App\Support\AulasDelUsuario;
use App\Support\Calendario;
use App\Support\Cursos;
use App\Support\Excel;
use App\Support\Pdf;
use App\Support\PerfilUsuario;
use Illuminate\Support\Facades\DB;

/** CU-28: boleta de notas en PDF (RF-62), asistencia por aula (RF-63) y consolidado del bimestre (RF-64) */
class Reportes
{
    /** Aulas y bimestres para elegir el reporte */
    public function opciones(Usuario $usuario): array
    {
        $periodo = Calendario::periodoEnCurso();
        if (! $periodo) {
            return ['aulas' => [], 'bimestres' => [], 'bimestre' => null];
        }
        $bimestres = Calendario::bimestres($periodo->id);

        return [
            'aulas' => $this->aulas(AulasDelUsuario::permitidas($usuario, $periodo->id)->all()),
            'bimestres' => $bimestres->map(fn ($b) => ['id' => $b->id, 'nombre' => $b->nombre])->all(),
            'bimestre' => Calendario::bimestreVigente($bimestres)?->id,
        ];
    }

    /** @return array{0: string, 1: string} contenido del PDF y nombre del archivo */
    public function boleta(int $matriculaId): array
    {
        $m = DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->join('aula as a', 'a.id', '=', 'm.aula_id')->join('grado as g', 'g.id', '=', 'a.grado_id')->join('seccion as s', 's.id', '=', 'a.seccion_id')
            ->join('periodo_academico as pa', 'pa.id', '=', 'm.periodo_id')
            ->leftJoin('docente as td', 'td.id', '=', 'a.tutor_docente_id')->leftJoin('persona as tp', 'tp.id', '=', 'td.persona_id')
            ->where('m.id', $matriculaId)
            ->first(['m.id', 'm.aula_id', 'm.periodo_id', 'e.codigo', 'p.nombres', 'p.apellidos', 'g.numero', 'g.nivel', 's.nombre as seccion', 'pa.nombre as periodo', 'tp.nombres as t_nombres', 'tp.apellidos as t_apellidos']);
        abort_unless($m, 404);
        $colegio = DB::table('institucion')->first(['nombre', 'codigo_modular', 'ugel']);
        $bimestres = Calendario::bimestres($m->periodo_id);
        $cursos = DB::table('curso_aula as c')->join('asignatura as a', 'a.id', '=', 'c.asignatura_id')->where('c.aula_id', $m->aula_id)->orderBy('a.nombre')->pluck('a.nombre', 'c.id');
        // RN-13: en la boleta solo van los promedios publicados o cerrados
        $notas = DB::table('calificacion_periodo')->where('matricula_id', $m->id)->where('estado', '!=', 'BORRADOR')->get(['curso_aula_id', 'subperiodo_id', 'promedio'])
            ->keyBy(fn ($c) => "{$c->curso_aula_id}-{$c->subperiodo_id}");
        $asistencia = DB::table('asistencia as a')->join('sesion_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->where('a.matricula_id', $m->id)->where('s.estado', 'DEFINITIVO')
            ->groupBy('s.subperiodo_id')->selectRaw("s.subperiodo_id, COUNT(*) as clases, SUM(a.estado = 'F') as faltas")->get()->keyBy('subperiodo_id');
        $escala = Calificaciones::escala();
        $aula = PerfilUsuario::nombreAula($m->numero, $m->nivel, $m->seccion);

        $pdf = (new Pdf)->pagina();
        // Encabezado con el nombre del colegio
        $pdf->rect(0, 0, Pdf::ANCHO, 92, '5B2A6E')
            ->texto(40, 38, mb_strtoupper($colegio->nombre ?? 'Institución educativa'), 15, true, 'FFFFFF')
            ->texto(40, 58, implode(' · ', array_filter([$colegio?->codigo_modular ? "Código modular {$colegio->codigo_modular}" : null, $colegio?->ugel])), 9, false, 'EDE4F1')
            ->texto(40, 78, "Boleta de notas · Periodo {$m->periodo}", 11, true, 'FFFFFF')
            ->texto(Pdf::ANCHO - 40, 38, 'AYNI', 18, true, 'F4B860', 'der');

        // Datos del estudiante
        $pdf->rect(40, 110, Pdf::ANCHO - 80, 60, 'F7F3F9', 'E5DDEA')
            ->texto(54, 130, 'Estudiante', 8, false, '6B7280')->texto(54, 146, "{$m->apellidos}, {$m->nombres}", 12, true)
            ->texto(54, 162, "Código {$m->codigo}", 9, false, '4B5563')
            ->texto(330, 130, 'Aula', 8, false, '6B7280')->texto(330, 146, $aula.' · '.($m->nivel === 'PRIMARIA' ? 'Primaria' : 'Secundaria'), 11, true)
            ->texto(330, 162, $m->t_nombres ? 'Tutor(a): '.Cursos::nombreCorto($m->t_nombres, $m->t_apellidos) : 'Sin tutor asignado', 9, false, '4B5563');

        // Tabla: curso, un promedio por bimestre, promedio y nivel de logro
        $x = ['curso' => 40, 'final' => 425, 'literal' => 495];
        $columnas = $bimestres->values();
        $paso = $columnas->count() ? 220 / $columnas->count() : 0;
        $y = 190;
        $pdf->rect(40, $y, Pdf::ANCHO - 80, 24, '2F6F6A');
        $pdf->texto(50, $y + 16, 'Curso', 9, true, 'FFFFFF');
        foreach ($columnas as $i => $b) {
            $pdf->texto(205 + $paso * ($i + 0.5), $y + 16, explode(' ', $b->nombre)[0].' Bim.', 9, true, 'FFFFFF', 'centro');
        }
        $pdf->texto($x['final'] + 30, $y + 16, 'Promedio', 9, true, 'FFFFFF', 'centro')->texto($x['literal'] + 30, $y + 16, 'Logro', 9, true, 'FFFFFF', 'centro');
        $y += 24;

        $finales = [];
        $fila = 0;
        foreach ($cursos as $cursoId => $nombre) {
            $valores = [];
            $pdf->rect(40, $y, Pdf::ANCHO - 80, 22, $fila++ % 2 ? 'FFFFFF' : 'F9FAFB');
            $pdf->texto(50, $y + 15, $nombre, 10);
            foreach ($columnas as $i => $b) {
                $nota = Calificaciones::numero($notas->get("{$cursoId}-{$b->id}")?->promedio);
                if ($nota !== null) {
                    $valores[] = $nota;
                }
                $pdf->texto(205 + $paso * ($i + 0.5), $y + 15, self::nota($nota), 10, false, $nota !== null && $nota < 11 ? 'B42318' : '1F2937', 'centro');
            }
            $final = $valores ? round(array_sum($valores) / count($valores), 1, PHP_ROUND_HALF_UP) : null;
            if ($final !== null) {
                $finales[] = $final;
            }
            $pdf->texto($x['final'] + 30, $y + 15, self::nota($final), 10, true, $final !== null && $final < 11 ? 'B42318' : '1F2937', 'centro')
                ->texto($x['literal'] + 30, $y + 15, Calificaciones::literal($final, $escala) ?? '—', 10, true, '2F6F6A', 'centro');
            $y += 22;
        }
        $general = $finales ? round(array_sum($finales) / count($finales), 1, PHP_ROUND_HALF_UP) : null;
        $pdf->linea(40, $y, Pdf::ANCHO - 40, $y, '2F6F6A', 1)
            ->texto(50, $y + 17, 'Promedio general', 10, true)
            ->texto($x['final'] + 30, $y + 17, self::nota($general), 11, true, '1F2937', 'centro')
            ->texto($x['literal'] + 30, $y + 17, Calificaciones::literal($general, $escala) ?? '—', 11, true, '2F6F6A', 'centro');
        $y += 26;

        // Asistencia por bimestre (RN-27)
        $pdf->texto(50, $y + 17, 'Asistencia', 10, true);
        foreach ($columnas as $i => $b) {
            $a = $asistencia->get($b->id);
            $p = $a ? ReportesAsistencia::porcentaje((int) $a->clases, (int) $a->faltas) : null;
            $pdf->texto(205 + $paso * ($i + 0.5), $y + 17, $p === null ? '—' : str_replace('.', ',', (string) $p).' %', 10, false, '1F2937', 'centro');
        }
        $y += 46;

        // Escala de calificación del colegio
        $pdf->texto(40, $y, 'Escala de calificación', 9, true, '4B5563');
        $leyenda = DB::table('escala_literal')->orderByDesc('nota_minima')->get(['codigo', 'nota_minima', 'nota_maxima'])->map(fn ($e) => "{$e->codigo}: ".self::nota((float) $e->nota_minima).' a '.self::nota((float) $e->nota_maxima))->implode('    ');
        $pdf->texto(40, $y + 15, $leyenda, 9, false, '4B5563')
            ->texto(40, $y + 30, 'Solo figuran los promedios publicados por los docentes o cerrados al terminar cada bimestre. "—": aún sin publicar.', 8, false, '6B7280');

        $pdf->linea(40, Pdf::ALTO - 50, Pdf::ANCHO - 40, Pdf::ALTO - 50)
            ->texto(40, Pdf::ALTO - 34, 'Generado por AYNI el '.now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm a'), 8, false, '6B7280')
            ->texto(Pdf::ANCHO - 40, Pdf::ALTO - 34, "{$m->codigo} · {$aula}", 8, false, '6B7280', 'der');

        return [$pdf->salida(), 'boleta_'.strtolower(str_replace('-', '_', $m->codigo)).'.pdf'];
    }

    /** RF-63: asistencia del bimestre por estudiante de un aula */
    public function asistencia(int $aulaId, ?int $bimestreId, Usuario $usuario): array
    {
        [$aula, $bimestre] = $this->aulaYBimestre($aulaId, $bimestreId, $usuario);
        $alumnos = $this->alumnos($aulaId);
        $conteo = DB::table('asistencia as a')->join('sesion_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->where('s.subperiodo_id', $bimestre->id)->where('s.estado', 'DEFINITIVO')->whereIn('a.matricula_id', $alumnos->pluck('id'))
            ->groupBy('a.matricula_id')
            ->selectRaw("a.matricula_id, COUNT(*) as clases, SUM(a.estado = 'P') as p, SUM(a.estado = 'F') as f, SUM(a.estado = 'T') as t, SUM(a.estado = 'J') as j")
            ->get()->keyBy('matricula_id');

        $filas = [['Código', 'Estudiante', 'Clases', 'Presentes', 'Faltas', 'Tardanzas', 'Justificadas', '% asistencia']];
        foreach ($alumnos as $al) {
            $c = $conteo->get($al->id);
            $filas[] = [$al->codigo, "{$al->apellidos}, {$al->nombres}", (int) ($c->clases ?? 0), (int) ($c->p ?? 0), (int) ($c->f ?? 0), (int) ($c->t ?? 0), (int) ($c->j ?? 0),
                $c ? ReportesAsistencia::porcentaje((int) $c->clases, (int) $c->f) : null];
        }

        return [Excel::escribir($filas, [12, 34, 9, 11, 9, 11, 13, 14]), Excel::nombreArchivo("asistencia {$aula} {$bimestre->nombre}")];
    }

    /** RF-64: promedios de cada curso del aula en el bimestre, el promedio general y la asistencia */
    public function consolidado(int $aulaId, ?int $bimestreId, Usuario $usuario): array
    {
        [$aula, $bimestre] = $this->aulaYBimestre($aulaId, $bimestreId, $usuario);
        $alumnos = $this->alumnos($aulaId);
        $cursos = DB::table('curso_aula as c')->join('asignatura as a', 'a.id', '=', 'c.asignatura_id')->where('c.aula_id', $aulaId)->orderBy('a.nombre')->pluck('a.nombre', 'c.id');
        $notas = DB::table('calificacion_periodo')->where('subperiodo_id', $bimestre->id)->whereIn('curso_aula_id', $cursos->keys())
            ->get(['matricula_id', 'curso_aula_id', 'promedio'])->keyBy(fn ($n) => "{$n->matricula_id}-{$n->curso_aula_id}");
        $asistencia = DB::table('asistencia as a')->join('sesion_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->where('s.subperiodo_id', $bimestre->id)->where('s.estado', 'DEFINITIVO')->whereIn('a.matricula_id', $alumnos->pluck('id'))
            ->groupBy('a.matricula_id')->selectRaw("a.matricula_id, COUNT(*) as clases, SUM(a.estado = 'F') as faltas")->get()->keyBy('matricula_id');

        $filas = [array_merge(['Código', 'Estudiante'], $cursos->values()->all(), ['Promedio general', '% asistencia'])];
        foreach ($alumnos as $al) {
            $valores = $cursos->keys()->map(fn ($c) => Calificaciones::numero($notas->get("{$al->id}-{$c}")?->promedio))->all();
            $conNota = array_filter($valores, fn ($v) => $v !== null);
            $a = $asistencia->get($al->id);
            $filas[] = array_merge([$al->codigo, "{$al->apellidos}, {$al->nombres}"], $valores, [
                $conNota ? round(array_sum($conNota) / count($conNota), 1, PHP_ROUND_HALF_UP) : null,
                $a ? ReportesAsistencia::porcentaje((int) $a->clases, (int) $a->faltas) : null,
            ]);
        }

        return [Excel::escribir($filas, array_merge([12, 34], array_fill(0, $cursos->count(), 14), [16, 14])), Excel::nombreArchivo("consolidado {$aula} {$bimestre->nombre}")];
    }

    private function aulaYBimestre(int $aulaId, ?int $bimestreId, Usuario $usuario): array
    {
        $aula = DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('aula.id', $aulaId)->first(['aula.id', 'aula.periodo_id', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion']);
        abort_unless($aula, 404);
        abort_unless(AulasDelUsuario::permitidas($usuario, $aula->periodo_id)->contains($aula->id), 403);
        $bimestres = Calendario::bimestres($aula->periodo_id);
        $bimestre = $bimestreId ? $bimestres->firstWhere('id', $bimestreId) : Calendario::bimestreVigente($bimestres);
        if (! $bimestre) {
            throw new ErrorRegla('Elige un bimestre del periodo del aula.', 'bimestre_id');
        }

        return [PerfilUsuario::nombreAula($aula->numero, $aula->nivel, $aula->seccion), $bimestre];
    }

    private function alumnos(int $aulaId)
    {
        return DB::table('matricula as m')->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->where('m.aula_id', $aulaId)->where('m.estado', 'ACTIVA')->orderBy('p.apellidos')->orderBy('p.nombres')
            ->get(['m.id', 'e.codigo', 'p.nombres', 'p.apellidos']);
    }

    private function aulas(array $ids): array
    {
        return DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->whereIn('aula.id', $ids)->orderBy('grado.orden')->orderBy('seccion.nombre')
            ->get(['aula.id', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion'])
            ->map(fn ($a) => ['id' => $a->id, 'nombre' => PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion)])->all();
    }

    private static function nota(?float $n): string
    {
        return $n === null ? '—' : str_replace('.', ',', (string) round($n, 1));
    }
}
