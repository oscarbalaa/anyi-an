<?php

namespace App\Servicios\Estructura;

use App\Exceptions\ErrorRegla;
use App\Support\Auditoria;
use App\Support\PerfilUsuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** CU-07 y CU-08: grados, secciones, aulas y coordinadores (RF-19, RF-20, RF-24, RN-03) */
class Aulas
{
    public const NIVELES = ['PRIMARIA' => 'Primaria', 'SECUNDARIA' => 'Secundaria'];

    public function listar(int $periodoId, ?string $nivel): array
    {
        $aulas = DB::table('aula')
            ->join('grado', 'grado.id', '=', 'aula.grado_id')
            ->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->leftJoin('docente', 'docente.id', '=', 'aula.tutor_docente_id')
            ->leftJoin('persona', 'persona.id', '=', 'docente.persona_id')
            ->where('aula.periodo_id', $periodoId)
            ->when($nivel, fn ($q) => $q->where('grado.nivel', $nivel))
            ->orderBy('grado.orden')->orderBy('seccion.nombre')
            ->get([
                'aula.*', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion',
                'persona.nombres as tutor_nombres', 'persona.apellidos as tutor_apellidos',
            ]);

        $ids = $aulas->pluck('id');
        $matriculados = DB::table('matricula')->whereIn('aula_id', $ids)->where('estado', 'ACTIVA')
            ->selectRaw('aula_id, COUNT(*) as total')->groupBy('aula_id')->pluck('total', 'aula_id');
        $cursos = DB::table('curso_aula')->whereIn('aula_id', $ids)
            ->selectRaw('aula_id, COUNT(*) as total')->groupBy('aula_id')->pluck('total', 'aula_id');
        $coordinaciones = $this->coordinacionesDe($periodoId);

        return $aulas->map(function ($a) use ($matriculados, $cursos, $coordinaciones) {
            $coordinador = $this->coordinadorDe($a, $coordinaciones);

            return [
                'id' => $a->id,
                'nombre' => PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion),
                'grado_id' => $a->grado_id,
                'grado' => $a->numero,
                'nivel' => $a->nivel,
                'seccion' => $a->seccion,
                'capacidad' => $a->capacidad,
                'ambiente' => $a->ambiente,
                'matriculados' => (int) ($matriculados[$a->id] ?? 0),
                'cursos' => (int) ($cursos[$a->id] ?? 0),
                'tutor' => $a->tutor_docente_id ? ['id' => $a->tutor_docente_id, 'nombre' => $this->corto($a->tutor_nombres, $a->tutor_apellidos)] : null,
                'coordinador' => $coordinador,
            ];
        })->all();
    }

    /** El coordinador más cercano: del aula, del grado, del nivel o de todo el colegio */
    private function coordinadorDe(object $aula, Collection $coordinaciones): ?array
    {
        $c = $coordinaciones->first(fn ($c) => $c->ambito === 'AULA' && $c->aula_id === $aula->id)
            ?? $coordinaciones->first(fn ($c) => $c->ambito === 'GRADO' && $c->grado_id === $aula->grado_id)
            ?? $coordinaciones->first(fn ($c) => $c->ambito === 'NIVEL' && $c->nivel === $aula->nivel)
            ?? $coordinaciones->first(fn ($c) => $c->ambito === 'INSTITUCION');

        return $c ? ['id' => $c->docente_id, 'nombre' => $c->docente, 'ambito' => $c->ambito, 'propio' => $c->ambito === 'AULA'] : null;
    }

    private function coordinacionesDe(int $periodoId): Collection
    {
        return DB::table('coordinacion')
            ->join('docente', 'docente.id', '=', 'coordinacion.docente_id')
            ->join('persona', 'persona.id', '=', 'docente.persona_id')
            ->where('coordinacion.periodo_id', $periodoId)
            ->get(['coordinacion.*', 'persona.nombres', 'persona.apellidos'])
            ->map(function ($c) {
                $c->docente = $this->corto($c->nombres, $c->apellidos);

                return $c;
            });
    }

    public function crear(array $d): int
    {
        $this->periodoAbierto($d['periodo_id']);
        $grado = DB::table('grado')->find($d['grado_id']);
        $seccion = $d['seccion'];

        // RN-03: "Única" no convive con otras secciones del mismo grado
        $existentes = DB::table('aula')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('aula.periodo_id', $d['periodo_id'])->where('aula.grado_id', $grado->id)
            ->pluck('seccion.nombre');
        $nombre = PerfilUsuario::nombreAula($grado->numero, $grado->nivel, $seccion);
        if ($existentes->contains($seccion)) {
            throw new ErrorRegla("Ya existe el aula {$nombre} en este periodo.", 'seccion');
        }
        if ($seccion === 'Única' && $existentes->isNotEmpty()) {
            throw new ErrorRegla('Este grado ya tiene secciones ('.$existentes->implode(', ').'): no puede tener además la sección Única (RN-03).', 'seccion');
        }
        if ($seccion !== 'Única' && $existentes->contains('Única')) {
            throw new ErrorRegla('Este grado tiene sección Única: no puede tener otras secciones (RN-03).', 'seccion');
        }
        $this->tutorLibre($d['tutor_docente_id'] ?? null, $d['periodo_id'], null);

        return DB::transaction(function () use ($d, $seccion) {
            $seccionId = DB::table('seccion')->where('nombre', $seccion)->value('id')
                ?? DB::table('seccion')->insertGetId(['nombre' => $seccion]);
            $id = DB::table('aula')->insertGetId([
                'periodo_id' => $d['periodo_id'],
                'grado_id' => $d['grado_id'],
                'seccion_id' => $seccionId,
                'capacidad' => $d['capacidad'],
                'tutor_docente_id' => $d['tutor_docente_id'] ?? null,
                'ambiente' => $d['ambiente'] ?? null,
            ]);
            $this->coordinadorDeAula($id, $d['periodo_id'], $d['coordinador_docente_id'] ?? null);
            Auditoria::registrar('CREAR', 'aula', $id, $d);

            return $id;
        });
    }

    public function actualizar(int $id, array $d): void
    {
        $aula = DB::table('aula')->find($id) ?? abort(404);
        $this->periodoAbierto($aula->periodo_id);
        $matriculados = DB::table('matricula')->where('aula_id', $id)->where('estado', 'ACTIVA')->count();
        if ($d['capacidad'] < $matriculados) {
            throw new ErrorRegla("El aula ya tiene {$matriculados} matriculados: la capacidad no puede ser menor.", 'capacidad');
        }
        $this->tutorLibre($d['tutor_docente_id'] ?? null, $aula->periodo_id, $id);

        DB::transaction(function () use ($id, $d, $aula) {
            DB::table('aula')->where('id', $id)->update([
                'capacidad' => $d['capacidad'],
                'tutor_docente_id' => $d['tutor_docente_id'] ?? null,
                'ambiente' => $d['ambiente'] ?? null,
            ]);
            $this->coordinadorDeAula($id, $aula->periodo_id, $d['coordinador_docente_id'] ?? null);
            Auditoria::registrar('ACTUALIZAR', 'aula', $id, $d);
        });
    }

    public function eliminar(int $id): void
    {
        $aula = DB::table('aula')->find($id) ?? abort(404);
        if (DB::table('matricula')->where('aula_id', $id)->exists() || DB::table('curso_aula')->where('aula_id', $id)->exists()) {
            throw new ErrorRegla('El aula ya tiene matrículas o cursos: no se puede eliminar.');
        }
        DB::transaction(function () use ($id, $aula) {
            DB::table('coordinacion')->where('aula_id', $id)->delete();
            DB::table('aula')->where('id', $id)->delete();
            Auditoria::registrar('ELIMINAR', 'aula', $id, ['grado_id' => $aula->grado_id, 'seccion_id' => $aula->seccion_id]);
        });
    }

    /** Un docente es tutor de un solo aula por periodo */
    private function tutorLibre(?int $docenteId, int $periodoId, ?int $aulaId): void
    {
        if (! $docenteId) {
            return;
        }
        $otra = DB::table('aula')
            ->join('grado', 'grado.id', '=', 'aula.grado_id')
            ->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('aula.periodo_id', $periodoId)->where('aula.tutor_docente_id', $docenteId)
            ->when($aulaId, fn ($q) => $q->where('aula.id', '!=', $aulaId))
            ->first(['grado.numero', 'grado.nivel', 'seccion.nombre as seccion']);
        if ($otra) {
            throw new ErrorRegla('Ese docente ya es tutor de '.PerfilUsuario::nombreAula($otra->numero, $otra->nivel, $otra->seccion).'.', 'tutor_docente_id');
        }
    }

    /** El coordinador propio del aula (ámbito AULA): se reemplaza o se quita */
    private function coordinadorDeAula(int $aulaId, int $periodoId, ?int $docenteId): void
    {
        $actual = DB::table('coordinacion')->where('ambito', 'AULA')->where('aula_id', $aulaId)->first();
        if ((int) $actual?->docente_id === (int) $docenteId) {
            return;
        }
        if ($actual) {
            DB::table('coordinacion')->where('id', $actual->id)->delete();
        }
        if ($docenteId) {
            DB::table('coordinacion')->insert([
                'docente_id' => $docenteId, 'periodo_id' => $periodoId, 'ambito' => 'AULA', 'aula_id' => $aulaId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** Coordinadores de grado, nivel o colegio (los de aula se ven en cada aula) */
    public function coordinaciones(int $periodoId): array
    {
        $grados = DB::table('grado')->get()->keyBy('id');

        return $this->coordinacionesDe($periodoId)
            ->where('ambito', '!=', 'AULA')
            ->sortBy(fn ($c) => ['INSTITUCION' => 0, 'NIVEL' => 1, 'GRADO' => 2][$c->ambito])
            ->map(fn ($c) => [
                'id' => $c->id,
                'docente' => ['id' => $c->docente_id, 'nombre' => $c->docente],
                'ambito' => $c->ambito,
                'alcance' => match ($c->ambito) {
                    'INSTITUCION' => 'Todo el colegio',
                    'NIVEL' => self::NIVELES[$c->nivel],
                    default => $grados[$c->grado_id]->numero.'.º '.mb_strtolower(self::NIVELES[$grados[$c->grado_id]->nivel]),
                },
            ])->values()->all();
    }

    public function crearCoordinacion(array $d): int
    {
        $this->periodoAbierto($d['periodo_id']);
        $repetida = DB::table('coordinacion')->where('periodo_id', $d['periodo_id'])->where('ambito', $d['ambito'])
            ->when($d['ambito'] === 'GRADO', fn ($q) => $q->where('grado_id', $d['grado_id']))
            ->when($d['ambito'] === 'NIVEL', fn ($q) => $q->where('nivel', $d['nivel']))
            ->exists();
        if ($repetida) {
            throw new ErrorRegla('Ese ámbito ya tiene coordinador. Quítalo primero para asignar otro.', 'ambito');
        }
        $id = DB::table('coordinacion')->insertGetId([
            'docente_id' => $d['docente_id'],
            'periodo_id' => $d['periodo_id'],
            'ambito' => $d['ambito'],
            'grado_id' => $d['ambito'] === 'GRADO' ? $d['grado_id'] : null,
            'nivel' => $d['ambito'] === 'NIVEL' ? $d['nivel'] : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Auditoria::registrar('CREAR', 'coordinacion', $id, $d);

        return $id;
    }

    public function eliminarCoordinacion(int $id): void
    {
        $c = DB::table('coordinacion')->find($id) ?? abort(404);
        $this->periodoAbierto($c->periodo_id);
        DB::table('coordinacion')->where('id', $id)->delete();
        Auditoria::registrar('ELIMINAR', 'coordinacion', $id, ['docente_id' => $c->docente_id, 'ambito' => $c->ambito]);
    }

    private function periodoAbierto(int $periodoId): void
    {
        if (DB::table('periodo_academico')->where('id', $periodoId)->value('estado') === 'CERRADO') {
            throw new ErrorRegla('El periodo está cerrado: su estructura ya no se modifica.');
        }
    }

    private function corto(?string $nombres, ?string $apellidos): ?string
    {
        return $nombres ? explode(' ', $nombres)[0].' '.explode(' ', (string) $apellidos)[0] : null;
    }
}
