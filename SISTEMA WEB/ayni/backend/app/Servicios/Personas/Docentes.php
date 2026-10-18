<?php

namespace App\Servicios\Personas;

use App\Exceptions\ErrorRegla;
use App\Models\Usuario;
use App\Servicios\ContrasenaTemporal;
use App\Support\Auditoria;
use App\Support\PerfilUsuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** CU-09: docentes, con su cuenta creada automáticamente (RF-27). El usuario del docente es su DNI (D5) */
class Docentes
{
    public const POR_PAGINA = 10;

    public function __construct(private readonly Personas $personas) {}

    private function consulta(): Builder
    {
        return DB::table('docente as d')
            ->join('persona as p', 'p.id', '=', 'd.persona_id')
            ->join('usuario as u', 'u.id', '=', 'd.usuario_id')
            ->select([
                'd.*', 'p.tipo_documento', 'p.numero_documento', 'p.nombres', 'p.apellidos', 'p.fecha_nacimiento',
                'p.sexo', 'p.celular', 'p.correo', 'p.direccion', 'u.username', 'u.estado as cuenta', 'u.ultimo_acceso',
            ]);
    }

    public function listar(array $f): array
    {
        $q = $this->consulta();
        if ($t = trim($f['buscar'] ?? '')) {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('p.numero_documento', 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.nombres, ' ', p.apellidos)"), 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.apellidos, ' ', p.nombres)"), 'like', $like));
        }
        if ($estado = $f['estado'] ?? null) {
            $q->where('d.estado', $estado);
        }
        $pagina = $q->orderBy('p.apellidos')->orderBy('p.nombres')
            ->paginate(self::POR_PAGINA, ['*'], 'pagina', (int) ($f['pagina'] ?? 1));

        $filas = collect($pagina->items());
        $coordina = PerfilUsuario::coordinaciones($filas->pluck('id')->all());
        $cursos = $this->cursosEnCurso($filas->pluck('id')->all())->countBy('docente_id');

        return [
            'datos' => $filas->map(fn ($d) => $this->fila($d) + [
                'coordina' => $coordina[$d->id] ?? null,
                'cursos' => (int) ($cursos[$d->id] ?? 0),
            ])->all(),
            'pagina' => $pagina->currentPage(),
            'por_pagina' => $pagina->perPage(),
            'total' => $pagina->total(),
            'ultima_pagina' => $pagina->lastPage(),
            'resumen' => DB::table('docente')->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado'),
        ];
    }

    public function detalle(int $id): array
    {
        $d = $this->consulta()->where('d.id', $id)->first() ?? abort(404);
        $periodo = DB::table('periodo_academico')->where('estado', 'EN_CURSO')->value('id');
        $tutoria = DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('aula.tutor_docente_id', $id)->where('aula.periodo_id', $periodo)
            ->first(['grado.numero', 'grado.nivel', 'seccion.nombre as seccion']);

        return $this->fila($d) + [
            'persona_id' => $d->persona_id,
            'usuario_id' => $d->usuario_id,
            'tipo_documento' => $d->tipo_documento,
            'numero_documento' => $d->numero_documento,
            'nombres' => $d->nombres,
            'apellidos' => $d->apellidos,
            'fecha_nacimiento' => $d->fecha_nacimiento,
            'sexo' => $d->sexo,
            'celular' => $d->celular,
            'correo' => $d->correo,
            'direccion' => $d->direccion,
            'titulo' => $d->titulo,
            'fecha_inicio_contrato' => $d->fecha_inicio_contrato,
            'fecha_fin_contrato' => $d->fecha_fin_contrato,
            'usuario' => $d->username,
            'ultimo_acceso' => $d->ultimo_acceso,
            'coordina' => PerfilUsuario::coordinaciones([$id])[$id] ?? null,
            'tutoria' => $tutoria ? PerfilUsuario::nombreAula($tutoria->numero, $tutoria->nivel, $tutoria->seccion) : null,
            'cursos' => $this->cursosEnCurso([$id])->map(fn ($c) => $c->asignatura.' · '.PerfilUsuario::nombreAula($c->numero, $c->nivel, $c->seccion))->values(),
        ];
    }

    public function crear(array $d, int $adminId): array
    {
        $this->usuarioLibre($d['numero_documento'], null);
        $coordinar = $d['coordina_aula_id'] ?? null;
        if ($coordinar && DB::table('coordinacion')->where('ambito', 'AULA')->where('aula_id', $coordinar)->exists()) {
            throw new ErrorRegla('Esa aula ya tiene coordinador. Cámbialo desde Estructura académica.', 'coordina_aula_id');
        }

        $r = DB::transaction(function () use ($d, $adminId, $coordinar) {
            $persona = $this->personas->crearPersona($d);
            $cuenta = $this->personas->crearCuenta('DOCENTE', $d['numero_documento'], $d['correo'] ?? null, $adminId);
            $id = DB::table('docente')->insertGetId([
                'persona_id' => $persona,
                'usuario_id' => $cuenta['id'],
                'titulo' => $d['titulo'] ?? null,
                'especialidad' => $d['especialidad'] ?? null,
                'fecha_inicio_contrato' => $d['fecha_inicio_contrato'] ?? null,
                'fecha_fin_contrato' => $d['fecha_fin_contrato'] ?? null,
            ]);
            if ($coordinar) {
                DB::table('coordinacion')->insert([
                    'docente_id' => $id, 'periodo_id' => DB::table('aula')->where('id', $coordinar)->value('periodo_id'),
                    'ambito' => 'AULA', 'aula_id' => $coordinar, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            Auditoria::registrar('CREAR', 'docente', $id, ['documento' => $d['numero_documento']]);

            return ['id' => $id, 'cuenta' => $cuenta];
        });

        $usuario = Usuario::with('rol')->find($r['cuenta']['id']);

        return [
            'docente' => $this->detalle($r['id']),
            'contrasena' => $r['cuenta']['contrasena'],
            'correo_enviado' => app(ContrasenaTemporal::class)->avisar($usuario, $r['cuenta']['contrasena']),
        ];
    }

    public function actualizar(int $id, array $d, int $adminId): array
    {
        $actual = DB::table('docente')->find($id) ?? abort(404);
        $this->usuarioLibre($d['numero_documento'], $actual->usuario_id);

        DB::transaction(function () use ($id, $d, $actual, $adminId) {
            $cambios = $this->personas->actualizarPersona($actual->persona_id, $d);
            $docente = array_intersect_key($d, array_flip(['titulo', 'especialidad', 'fecha_inicio_contrato', 'fecha_fin_contrato', 'estado']));
            foreach ($docente as $campo => $valor) {
                if ($actual->{$campo} != $valor) {
                    $cambios[$campo] = ['antes' => $actual->{$campo}, 'despues' => $valor];
                }
            }
            DB::table('docente')->where('id', $id)->update($docente);

            // La cuenta sigue al docente: usuario = DNI; si deja de trabajar, no puede ingresar
            $cuenta = ['username' => $d['numero_documento'], 'email' => $d['correo'] ?? null, 'actualizado_por' => $adminId, 'updated_at' => now()];
            $estadoCuenta = DB::table('usuario')->where('id', $actual->usuario_id)->value('estado');
            if (($d['estado'] ?? null) === 'INACTIVO' && $estadoCuenta === 'ACTIVO') {
                $cuenta['estado'] = 'INACTIVO';
                DB::table('personal_access_tokens')->where('tokenable_id', $actual->usuario_id)->where('tokenable_type', Usuario::class)->delete();
            } elseif (($d['estado'] ?? null) !== 'INACTIVO' && $actual->estado === 'INACTIVO' && $estadoCuenta === 'INACTIVO') {
                $cuenta['estado'] = 'ACTIVO';
            }
            DB::table('usuario')->where('id', $actual->usuario_id)->update($cuenta);

            if ($cambios) {
                Auditoria::registrar('ACTUALIZAR', 'docente', $id, $cambios);
            }
        });

        return $this->detalle($id);
    }

    /** El DNI es el usuario: no puede ser el de otra cuenta */
    private function usuarioLibre(string $dni, ?int $usuarioId): void
    {
        $ocupado = DB::table('usuario')->where('username', $dni)->when($usuarioId, fn ($q) => $q->where('id', '!=', $usuarioId))->exists();
        if ($ocupado) {
            throw new ErrorRegla('Ese DNI ya es el usuario de otra cuenta.', 'numero_documento');
        }
    }

    private function cursosEnCurso(array $docenteIds)
    {
        $periodo = DB::table('periodo_academico')->where('estado', 'EN_CURSO')->value('id');

        return DB::table('curso_aula')
            ->join('aula', 'aula.id', '=', 'curso_aula.aula_id')
            ->join('asignatura', 'asignatura.id', '=', 'curso_aula.asignatura_id')
            ->join('grado', 'grado.id', '=', 'aula.grado_id')
            ->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->whereIn('curso_aula.docente_id', $docenteIds ?: [0])
            ->where('aula.periodo_id', $periodo)
            ->orderBy('asignatura.nombre')->orderBy('grado.orden')
            ->get(['curso_aula.docente_id', 'asignatura.nombre as asignatura', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion']);
    }

    private function fila(object $d): array
    {
        return [
            'id' => $d->id,
            'nombre' => "{$d->nombres} {$d->apellidos}",
            'documento' => "{$d->tipo_documento} {$d->numero_documento}",
            'especialidad' => $d->especialidad,
            'estado' => $d->estado,
            'cuenta' => $d->cuenta,
        ];
    }
}
