<?php

namespace App\Servicios\Personas;

use App\Exceptions\ErrorRegla;
use App\Models\Usuario;
use App\Servicios\ContrasenaTemporal;
use App\Support\Auditoria;
use App\Support\PerfilUsuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** CU-10: estudiantes y apoderados (RF-28, RF-29, RN-22, RN-24). El usuario del estudiante es su código (D5) */
class Estudiantes
{
    public const POR_PAGINA = 10;

    public const ESTADOS = ['ACTIVO', 'RETIRADO', 'TRASLADADO', 'EGRESADO'];

    public const PARENTESCOS = ['Padre', 'Madre', 'Tutor legal', 'Abuelo(a)', 'Tío(a)', 'Hermano(a)', 'Otro'];

    public function __construct(private readonly Personas $personas, private readonly Matriculas $matriculas) {}

    private function consulta(): Builder
    {
        return DB::table('estudiante as e')
            ->join('persona as p', 'p.id', '=', 'e.persona_id')
            ->join('usuario as u', 'u.id', '=', 'e.usuario_id')
            ->leftJoin('personal_administrativo as pa', 'pa.usuario_id', '=', 'u.creado_por')
            ->leftJoin('persona as pc', 'pc.id', '=', 'pa.persona_id')
            ->select([
                'e.*', 'p.tipo_documento', 'p.numero_documento', 'p.nombres', 'p.apellidos', 'p.fecha_nacimiento', 'p.sexo',
                'p.celular', 'p.correo', 'p.direccion', 'u.username', 'u.estado as cuenta', 'u.ultimo_acceso',
                'pc.nombres as creador_nombres', 'pc.apellidos as creador_apellidos',
            ]);
    }

    public function listar(array $f): array
    {
        $periodo = DB::table('periodo_academico')->where('estado', 'EN_CURSO')->value('id');
        $q = $this->consulta();
        if ($t = trim($f['buscar'] ?? '')) {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('e.codigo', 'like', $like)->orWhere('p.numero_documento', 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.nombres, ' ', p.apellidos)"), 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.apellidos, ' ', p.nombres)"), 'like', $like));
        }
        if ($estado = $f['estado'] ?? null) {
            $q->where('e.estado', $estado);
        }
        // "sin" = activos sin matrícula activa en el periodo en curso
        if ($aula = $f['aula'] ?? null) {
            $matricula = fn ($s) => $s->from('matricula')->whereColumn('matricula.estudiante_id', 'e.id')
                ->where('matricula.periodo_id', $periodo)->where('matricula.estado', 'ACTIVA');
            $aula === 'sin'
                ? $q->whereNotExists($matricula)
                : $q->whereExists(fn ($s) => $matricula($s)->where('matricula.aula_id', (int) $aula));
        }
        $pagina = $q->orderBy('p.apellidos')->orderBy('p.nombres')
            ->paginate(self::POR_PAGINA, ['*'], 'pagina', (int) ($f['pagina'] ?? 1));

        $filas = collect($pagina->items());
        $aulas = PerfilUsuario::aulas($filas->pluck('id')->all());

        return [
            'datos' => $filas->map(fn ($e) => $this->fila($e) + ['aula' => $aulas[$e->id] ?? null])->all(),
            'pagina' => $pagina->currentPage(),
            'por_pagina' => $pagina->perPage(),
            'total' => $pagina->total(),
            'ultima_pagina' => $pagina->lastPage(),
            'registrados' => DB::table('estudiante')->count(),
        ];
    }

    public function detalle(int $id): array
    {
        $e = $this->consulta()->where('e.id', $id)->first() ?? abort(404);
        $matriculas = DB::table('matricula as m')
            ->join('periodo_academico as pe', 'pe.id', '=', 'm.periodo_id')
            ->join('aula as a', 'a.id', '=', 'm.aula_id')
            ->join('grado as g', 'g.id', '=', 'a.grado_id')
            ->join('seccion as s', 's.id', '=', 'a.seccion_id')
            ->where('m.estudiante_id', $id)->orderByDesc('pe.fecha_inicio')
            ->get(['m.id', 'm.fecha', 'm.estado', 'm.motivo', 'pe.nombre as periodo', 'pe.estado as periodo_estado', 'g.numero', 'g.nivel', 's.nombre as seccion'])
            ->map(fn ($m) => [
                'id' => $m->id,
                'periodo' => $m->periodo,
                'en_curso' => $m->periodo_estado === 'EN_CURSO',
                'aula' => PerfilUsuario::nombreAula($m->numero, $m->nivel, $m->seccion),
                'fecha' => $m->fecha,
                'estado' => $m->estado,
                'motivo' => $m->motivo,
            ]);

        return $this->fila($e) + [
            'aula' => PerfilUsuario::aulas([$id])[$id] ?? null,
            'persona_id' => $e->persona_id,
            'tipo_documento' => $e->tipo_documento,
            'numero_documento' => $e->numero_documento,
            'nombres' => $e->nombres,
            'apellidos' => $e->apellidos,
            'fecha_nacimiento' => $e->fecha_nacimiento,
            'sexo' => $e->sexo,
            'celular' => $e->celular,
            'correo' => $e->correo,
            'direccion' => $e->direccion,
            'fecha_ingreso' => $e->fecha_ingreso,
            'usuario' => $e->username,
            'ultimo_acceso' => $e->ultimo_acceso,
            'matriculas' => $matriculas,
            'apoderados' => DB::table('apoderado')->where('estudiante_id', $id)->orderByDesc('es_principal')->orderBy('id')
                ->get(['id', 'nombres', 'celular', 'correo', 'parentesco', 'es_principal'])
                ->map(fn ($a) => ['es_principal' => (bool) $a->es_principal] + (array) $a),
        ];
    }

    /** RN-22: E-AAAA-NNN con el año de ingreso y el siguiente correlativo de ese año */
    public static function siguienteCodigo(int $anio): string
    {
        $ultimo = DB::table('estudiante')->where('codigo', 'like', "E-{$anio}-%")->lockForUpdate()
            ->pluck('codigo')->map(fn ($c) => (int) substr($c, 7))->max() ?? 0;
        if ($ultimo >= 999) {
            throw new ErrorRegla("Ya no quedan códigos para {$anio} (E-{$anio}-999). Escribe el código a mano.", 'codigo');
        }

        return sprintf('E-%d-%03d', $anio, $ultimo + 1);
    }

    /**
     * Registra al estudiante con su cuenta (usuario = código). Con $aulaId también lo matricula.
     * Devuelve el id y la contraseña temporal.
     */
    public function registrar(array $d, int $adminId, string $origen = 'MANUAL', bool $cuentaActiva = true): array
    {
        $ingreso = $d['fecha_ingreso'] ?? today()->toDateString();
        $codigo = $d['codigo'] ?? null;
        if ($codigo && DB::table('usuario')->where('username', $codigo)->exists()) {
            throw new ErrorRegla("El código {$codigo} ya está registrado.", 'codigo');
        }
        $codigo ??= self::siguienteCodigo((int) substr($ingreso, 0, 4));

        $persona = $this->personas->crearPersona($d);
        $cuenta = $this->personas->crearCuenta('ESTUDIANTE', $codigo, $d['correo'] ?? null, $adminId, $cuentaActiva);
        $id = DB::table('estudiante')->insertGetId([
            'persona_id' => $persona,
            'usuario_id' => $cuenta['id'],
            'codigo' => $codigo,
            'fecha_ingreso' => $ingreso,
            'origen' => $origen,
        ]);
        Auditoria::registrar('CREAR', 'estudiante', $id, ['codigo' => $codigo, 'origen' => $origen]);
        if ($d['aula_id'] ?? null) {
            $this->matriculas->matricular($id, (int) $d['aula_id'], $d['fecha_matricula'] ?? Matriculas::hoy(), $adminId);
        }

        return ['id' => $id, 'codigo' => $codigo, 'usuario_id' => $cuenta['id'], 'contrasena' => $cuenta['contrasena']];
    }

    public function crear(array $d, int $adminId): array
    {
        $r = DB::transaction(fn () => $this->registrar($d, $adminId));
        $usuario = Usuario::with('rol')->find($r['usuario_id']);

        return [
            'estudiante' => $this->detalle($r['id']),
            'contrasena' => $r['contrasena'],
            'correo_enviado' => app(ContrasenaTemporal::class)->avisar($usuario, $r['contrasena']),
        ];
    }

    public function actualizar(int $id, array $d, int $adminId): array
    {
        $actual = DB::table('estudiante')->find($id) ?? abort(404);

        DB::transaction(function () use ($id, $d, $actual, $adminId) {
            $cambios = $this->personas->actualizarPersona($actual->persona_id, $d);
            foreach (['estado', 'fecha_ingreso'] as $campo) {
                if (array_key_exists($campo, $d) && $actual->{$campo} != $d[$campo]) {
                    $cambios[$campo] = ['antes' => $actual->{$campo}, 'despues' => $d[$campo]];
                }
            }
            DB::table('estudiante')->where('id', $id)->update(array_intersect_key($d, array_flip(['estado', 'fecha_ingreso'])));

            // Un estudiante retirado, trasladado o egresado ya no ingresa
            $cuenta = ['email' => $d['correo'] ?? null, 'actualizado_por' => $adminId, 'updated_at' => now()];
            $estadoCuenta = DB::table('usuario')->where('id', $actual->usuario_id)->value('estado');
            if (($d['estado'] ?? 'ACTIVO') !== 'ACTIVO' && $estadoCuenta === 'ACTIVO') {
                $cuenta['estado'] = 'INACTIVO';
                DB::table('personal_access_tokens')->where('tokenable_id', $actual->usuario_id)->where('tokenable_type', Usuario::class)->delete();
            } elseif (($d['estado'] ?? null) === 'ACTIVO' && $actual->estado !== 'ACTIVO' && $estadoCuenta === 'INACTIVO') {
                $cuenta['estado'] = 'ACTIVO';
            }
            DB::table('usuario')->where('id', $actual->usuario_id)->update($cuenta);

            if ($cambios) {
                Auditoria::registrar('ACTUALIZAR', 'estudiante', $id, $cambios);
            }
        });

        return $this->detalle($id);
    }

    /** RN-24: a lo sumo un apoderado principal */
    public function guardarApoderado(int $estudianteId, array $d, ?int $apoderadoId = null): void
    {
        DB::transaction(function () use ($estudianteId, $d, $apoderadoId) {
            if ($d['es_principal'] ?? false) {
                DB::table('apoderado')->where('estudiante_id', $estudianteId)->update(['es_principal' => false]);
            }
            $fila = [
                'nombres' => $d['nombres'],
                'celular' => $d['celular'] ?? null,
                'correo' => $d['correo'] ?? null,
                'parentesco' => $d['parentesco'],
                'es_principal' => (bool) ($d['es_principal'] ?? false),
            ];
            if ($apoderadoId) {
                DB::table('apoderado')->where('id', $apoderadoId)->update($fila);
            } else {
                $apoderadoId = DB::table('apoderado')->insertGetId($fila + ['estudiante_id' => $estudianteId]);
            }
            Auditoria::registrar('GUARDAR_APODERADO', 'apoderado', $apoderadoId, ['estudiante_id' => $estudianteId]);
        });
    }

    public function eliminarApoderado(int $id): void
    {
        $a = DB::table('apoderado')->find($id) ?? abort(404);
        DB::table('apoderado')->where('id', $id)->delete();
        Auditoria::registrar('ELIMINAR', 'apoderado', $id, ['estudiante_id' => $a->estudiante_id]);
    }

    private function fila(object $e): array
    {
        return [
            'id' => $e->id,
            'nombre' => "{$e->nombres} {$e->apellidos}",
            'documento' => "{$e->tipo_documento} {$e->numero_documento}",
            'codigo' => $e->codigo,
            'estado' => $e->estado,
            'cuenta' => $e->cuenta,
            'origen' => $e->origen,
            'registrado_por' => $e->origen === 'EXCEL' ? 'Importación Excel' : (Personas::corto($e->creador_nombres, $e->creador_apellidos) ?? 'Datos iniciales'),
        ];
    }
}
