<?php

namespace App\Servicios;

use App\Exceptions\ErrorAcceso;
use App\Models\Usuario;
use App\Support\Auditoria;
use App\Support\PerfilUsuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** CU-04: gestión de cuentas por la administración (RF-11, RF-12, RF-16) */
class Usuarios
{
    public const POR_PAGINA = 10;

    public const ROLES = ['ADMIN' => 'Administrador', 'DOCENTE' => 'Docente', 'ESTUDIANTE' => 'Estudiante'];

    /** Usuario con su persona y su perfil (administrativo, docente o estudiante) */
    private function consulta(): Builder
    {
        return DB::table('usuario as u')
            ->join('rol as r', 'r.id', '=', 'u.rol_id')
            ->leftJoin('personal_administrativo as pa', 'pa.usuario_id', '=', 'u.id')
            ->leftJoin('docente as d', 'd.usuario_id', '=', 'u.id')
            ->leftJoin('estudiante as e', 'e.usuario_id', '=', 'u.id')
            ->leftJoin('persona as p', 'p.id', '=', DB::raw('COALESCE(pa.persona_id, d.persona_id, e.persona_id)'))
            ->select([
                'u.id', 'u.username', 'u.email', 'u.estado', 'u.ultimo_acceso', 'u.debe_cambiar_password',
                'u.intentos_fallidos', 'u.bloqueado_hasta', 'u.created_at', 'u.updated_at', 'u.creado_por', 'u.actualizado_por',
                'r.codigo as rol', 'p.id as persona_id', 'p.nombres', 'p.apellidos', 'p.numero_documento', 'p.celular',
                'pa.cargo', 'd.id as docente_id', 'e.id as estudiante_id',
            ]);
    }

    public function listar(array $filtros): array
    {
        $consulta = $this->consulta();

        if ($termino = trim($filtros['buscar'] ?? '')) {
            $like = '%'.addcslashes($termino, '%_\\').'%';
            // utf8mb4_unicode_ci no distingue tildes ni mayúsculas
            $consulta->where(fn ($q) => $q
                ->where('u.username', 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.nombres, ' ', p.apellidos)"), 'like', $like)
                ->orWhere(DB::raw("CONCAT(p.apellidos, ' ', p.nombres)"), 'like', $like));
        }
        if ($rol = $filtros['rol'] ?? null) {
            $consulta->where('r.codigo', $rol);
        }
        if ($estado = $filtros['estado'] ?? null) {
            $consulta->where('u.estado', $estado);
        }

        // Primero las cuentas bloqueadas: son las que piden atención
        $pagina = $consulta
            ->orderByRaw("CASE WHEN u.estado = 'BLOQUEADO' THEN 0 ELSE 1 END")
            ->orderBy('p.apellidos')->orderBy('p.nombres')->orderBy('u.username')
            ->paginate(self::POR_PAGINA, ['*'], 'pagina', (int) ($filtros['pagina'] ?? 1));

        $filas = collect($pagina->items());
        $aulas = PerfilUsuario::aulas($filas->pluck('estudiante_id')->filter()->all());
        $coordinaciones = PerfilUsuario::coordinaciones($filas->pluck('docente_id')->filter()->all());

        return [
            'datos' => $filas->map(fn ($f) => $this->fila($f, match ($f->rol) {
                'ADMIN' => $f->cargo,
                'DOCENTE' => $coordinaciones[$f->docente_id] ?? null,
                default => $aulas[$f->estudiante_id] ?? null,
            }))->all(),
            'pagina' => $pagina->currentPage(),
            'por_pagina' => $pagina->perPage(),
            'total' => $pagina->total(),
            'ultima_pagina' => $pagina->lastPage(),
            'resumen' => DB::table('usuario')->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado'),
        ];
    }

    public function detalle(int $id): array
    {
        $f = $this->consulta()->where('u.id', $id)->first();
        abort_unless($f, 404);

        $detalle = match ($f->rol) {
            'ADMIN' => $f->cargo,
            'DOCENTE' => PerfilUsuario::coordinaciones([$f->docente_id])[$f->docente_id] ?? null,
            default => PerfilUsuario::aulas([$f->estudiante_id])[$f->estudiante_id] ?? null,
        };
        $nombres = DB::table('usuario as u')
            ->leftJoin('personal_administrativo as pa', 'pa.usuario_id', '=', 'u.id')
            ->leftJoin('persona as p', 'p.id', '=', 'pa.persona_id');

        $historial = DB::table('auditoria as a')
            ->leftJoin('personal_administrativo as pa', 'pa.usuario_id', '=', 'a.usuario_id')
            ->leftJoin('persona as p', 'p.id', '=', 'pa.persona_id')
            ->where('a.entidad', 'usuario')->where('a.entidad_id', $id)
            // Los ingresos y salidas se ven en "último acceso"
            ->whereNotIn('a.accion', ['INGRESO', 'SALIDA'])
            ->orderByDesc('a.id')->limit(10)
            ->get(['a.accion', 'a.created_at', 'a.usuario_id', 'p.nombres', 'p.apellidos'])
            ->map(fn ($h) => [
                'accion' => $h->accion,
                'fecha' => $this->fecha($h->created_at),
                // Sin administrativo: la acción la hizo el propio usuario o el sistema
                'por' => $h->nombres ? $this->nombreCorto($h->nombres, $h->apellidos) : ($h->usuario_id === $id ? 'El propio usuario' : 'Sistema'),
            ]);

        $creador = fn (?int $uid) => $uid ? (clone $nombres)->where('u.id', $uid)->first(['p.nombres', 'p.apellidos', 'u.username']) : null;
        $porNombre = fn ($c) => $c ? ($c->nombres ? $this->nombreCorto($c->nombres, $c->apellidos) : $c->username) : null;

        return $this->fila($f, $detalle) + [
            'documento' => $f->numero_documento,
            'celular' => $f->celular,
            'nombres' => $f->nombres,
            'apellidos' => $f->apellidos,
            'cargo' => $f->cargo,
            'intentos_fallidos' => (int) $f->intentos_fallidos,
            'bloqueado_hasta' => $this->fecha($f->bloqueado_hasta),
            'creado_en' => $this->fecha($f->created_at),
            'creado_por' => $porNombre($creador($f->creado_por)),
            'actualizado_en' => $this->fecha($f->updated_at),
            'actualizado_por' => $porNombre($creador($f->actualizado_por)),
            'historial' => $historial,
        ];
    }

    /** RF-12: solo se crean aquí las cuentas administrativas; docentes y estudiantes se crean en el M7 */
    public function crearAdministrativo(array $datos, int $adminId): array
    {
        $clave = ContrasenaTemporal::generar('ADMIN');

        $id = DB::transaction(function () use ($datos, $adminId, $clave) {
            $id = DB::table('usuario')->insertGetId([
                'rol_id' => DB::table('rol')->where('codigo', 'ADMIN')->value('id'),
                'username' => $datos['usuario'],
                'email' => $datos['correo'] ?? null,
                'password_hash' => Hash::make($clave),
                'debe_cambiar_password' => true,
                'creado_por' => $adminId,
                'actualizado_por' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $persona = DB::table('persona')->insertGetId([
                'numero_documento' => $datos['dni'],
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'correo' => $datos['correo'] ?? null,
                'celular' => $datos['celular'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('personal_administrativo')->insert(['persona_id' => $persona, 'usuario_id' => $id, 'cargo' => $datos['cargo']]);
            Auditoria::registrar('CREAR', 'usuario', $id, ['usuario' => $datos['usuario'], 'rol' => 'ADMIN']);

            return $id;
        });

        $usuario = Usuario::with('rol')->findOrFail($id);
        $correo = app(ContrasenaTemporal::class)->avisar($usuario, $clave);

        return ['usuario' => $this->detalle($id), 'contrasena' => $clave, 'correo_enviado' => $correo];
    }

    /** Campos que cambia el administrador; los datos personales de docentes y estudiantes se editan en el M7 */
    public function actualizar(Usuario $usuario, array $datos, int $adminId): array
    {
        $antes = $this->consulta()->where('u.id', $usuario->id)->first();
        $cambios = [];
        $anotar = function (string $campo, mixed $antes, mixed $despues) use (&$cambios) {
            if ($antes !== $despues) {
                $cambios[$campo] = ['antes' => $antes, 'despues' => $despues];
            }
        };

        DB::transaction(function () use ($usuario, $datos, $adminId, $antes, $anotar, &$cambios) {
            $anotar('correo', $antes->email, $datos['correo'] ?? null);
            $anotar('celular', $antes->celular, $datos['celular'] ?? null);
            $persona = ['correo' => $datos['correo'] ?? null, 'celular' => $datos['celular'] ?? null];
            $cuenta = ['email' => $datos['correo'] ?? null];

            if ($antes->rol === 'ADMIN') {
                foreach (['nombres' => 'nombres', 'apellidos' => 'apellidos', 'dni' => 'numero_documento'] as $campo => $columna) {
                    $anotar($campo, $antes->{$columna}, $datos[$campo]);
                    $persona[$columna] = $datos[$campo];
                }
                $anotar('usuario', $antes->username, $datos['usuario']);
                $anotar('cargo', $antes->cargo, $datos['cargo']);
                $cuenta['username'] = $datos['usuario'];
                DB::table('personal_administrativo')->where('usuario_id', $usuario->id)->update(['cargo' => $datos['cargo']]);
            }

            if (! $cambios) {
                return;
            }
            $usuario->forceFill($cuenta + ['actualizado_por' => $adminId])->save();
            if ($antes->persona_id) {
                DB::table('persona')->where('id', $antes->persona_id)->update($persona + ['updated_at' => now()]);
            }
            Auditoria::registrar('ACTUALIZAR', 'usuario', $usuario->id, $cambios);
        });

        return $this->detalle($usuario->id);
    }

    /** Activar o inactivar; la cuenta bloqueada se libera con la contraseña temporal */
    public function cambiarEstado(Usuario $usuario, string $estado, int $adminId): array
    {
        if ($usuario->id === $adminId) {
            throw new ErrorAcceso('No puedes cambiar el estado de tu propia cuenta.', 422);
        }
        if ($estado === 'INACTIVO' && $usuario->rol->codigo === 'ADMIN' && $usuario->estado === 'ACTIVO') {
            $otros = DB::table('usuario')->join('rol', 'rol.id', '=', 'usuario.rol_id')
                ->where('rol.codigo', 'ADMIN')->where('usuario.estado', 'ACTIVO')->where('usuario.id', '!=', $usuario->id)->count();
            if ($otros === 0) {
                throw new ErrorAcceso('Debe quedar al menos una cuenta administrativa activa.', 422);
            }
        }
        if ($estado === 'ACTIVO' && $usuario->estado === 'BLOQUEADO') {
            throw new ErrorAcceso('La cuenta está bloqueada: usa "Desbloquear y generar contraseña temporal".', 422);
        }

        if ($usuario->estado !== $estado) {
            DB::transaction(function () use ($usuario, $estado, $adminId) {
                $anterior = $usuario->estado;
                $usuario->forceFill(['estado' => $estado, 'actualizado_por' => $adminId])->save();
                if ($estado === 'INACTIVO') {
                    $usuario->tokens()->delete();
                }
                Auditoria::registrar($estado === 'ACTIVO' ? 'ACTIVAR' : 'INACTIVAR', 'usuario', $usuario->id, ['antes' => $anterior]);
            });
        }

        return $this->detalle($usuario->id);
    }

    private function fila(object $f, ?string $detalle): array
    {
        return [
            'id' => $f->id,
            'usuario' => $f->username,
            'nombre' => $f->nombres ? "{$f->nombres} {$f->apellidos}" : $f->username,
            'rol' => $f->rol,
            'rol_nombre' => self::ROLES[$f->rol],
            'detalle' => $detalle,
            'estado' => $f->estado,
            'correo' => $f->email,
            'debe_cambiar_contrasena' => (bool) $f->debe_cambiar_password,
            'ultimo_acceso' => $this->fecha($f->ultimo_acceso),
        ];
    }

    private function nombreCorto(string $nombres, string $apellidos): string
    {
        return explode(' ', $nombres)[0].' '.explode(' ', $apellidos)[0];
    }

    private function fecha(?string $valor): ?string
    {
        return $valor ? Carbon::parse($valor)->toIso8601String() : null;
    }
}
