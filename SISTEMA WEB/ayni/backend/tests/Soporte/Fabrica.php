<?php

namespace Tests\Soporte;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

/** Crea filas mínimas y válidas para probar las restricciones de la BD. */
final class Fabrica
{
    private static int $n = 0;

    private static function siguiente(): int
    {
        return ++self::$n;
    }

    public static function usuario(string $rol = 'DOCENTE', array $extra = []): int
    {
        $n = self::siguiente();

        return DB::table('usuario')->insertGetId($extra + [
            'rol_id' => DB::table('rol')->where('codigo', $rol)->value('id'),
            'username' => "usuario{$n}",
            'password_hash' => 'x',
        ]);
    }

    public static function persona(array $extra = []): int
    {
        $n = self::siguiente();

        return DB::table('persona')->insertGetId($extra + [
            'numero_documento' => str_pad((string) (10_000_000 + $n), 8, '0', STR_PAD_LEFT),
            'nombres' => 'Nombre',
            'apellidos' => 'Apellido Prueba',
        ]);
    }

    public static function docente(): int
    {
        return DB::table('docente')->insertGetId(['persona_id' => self::persona(), 'usuario_id' => self::usuario()]);
    }

    public static function estudiante(): int
    {
        $n = self::siguiente();

        return DB::table('estudiante')->insertGetId([
            'persona_id' => self::persona(),
            'usuario_id' => self::usuario('ESTUDIANTE'),
            'codigo' => sprintf('E-2026-%03d', $n),
        ]);
    }

    public static function periodo(array $extra = []): int
    {
        $n = self::siguiente();

        return DB::table('periodo_academico')->insertGetId($extra + [
            'nombre' => (string) (2000 + $n),
            'fecha_inicio' => '2026-03-02',
            'fecha_fin' => '2026-12-18',
        ]);
    }

    public static function bimestre(int $periodo, array $extra = []): int
    {
        return DB::table('subperiodo')->insertGetId($extra + [
            'periodo_id' => $periodo,
            'numero' => 1,
            'nombre' => 'I Bimestre',
            'fecha_inicio' => '2026-03-02',
            'fecha_fin' => '2026-05-08',
            'fecha_cierre_notas' => '2026-05-15',
        ]);
    }

    public static function grado(string $nivel = 'SECUNDARIA', int $numero = 3): int
    {
        return DB::table('grado')->where('nivel', $nivel)->where('numero', $numero)->value('id');
    }

    public static function seccion(string $nombre = 'A'): int
    {
        return DB::table('seccion')->where('nombre', $nombre)->value('id');
    }

    public static function aula(int $periodo, array $extra = []): int
    {
        return DB::table('aula')->insertGetId($extra + [
            'periodo_id' => $periodo,
            'grado_id' => self::grado(),
            'seccion_id' => self::seccion(),
        ]);
    }

    public static function matricula(int $aula, int $periodo, ?int $estudiante = null): int
    {
        return DB::table('matricula')->insertGetId([
            'estudiante_id' => $estudiante ?? self::estudiante(),
            'aula_id' => $aula,
            'periodo_id' => $periodo,
            'fecha' => '2026-02-20',
        ]);
    }

    public static function curso(int $aula): int
    {
        $n = self::siguiente();
        $asignatura = DB::table('asignatura')->insertGetId(['codigo' => "AS{$n}", 'nombre' => "Asignatura {$n}"]);

        return DB::table('curso_aula')->insertGetId(['aula_id' => $aula, 'asignatura_id' => $asignatura, 'docente_id' => self::docente()]);
    }

    public static function evaluacion(int $curso, int $bimestre, array $extra = []): int
    {
        return DB::table('evaluacion')->insertGetId($extra + [
            'curso_aula_id' => $curso,
            'subperiodo_id' => $bimestre,
            'tipo_evaluacion_id' => DB::table('tipo_evaluacion')->value('id'),
            'titulo' => 'Práctica 1',
            'fecha_asignacion' => '2026-03-09',
            'fecha_limite' => '2026-03-13',
        ]);
    }

    /** Cuenta completa (usuario + persona + perfil) lista para iniciar sesión */
    public static function cuenta(string $rol, string $usuario, string $clave = 'Ayni#2026', ?string $correo = null, array $extra = []): int
    {
        $id = self::usuario($rol, $extra + [
            'username' => $usuario,
            'email' => $correo,
            'password_hash' => Hash::make($clave),
        ]);
        $persona = self::persona(['nombres' => 'Ana María', 'apellidos' => 'Torres Vega', 'correo' => $correo]);
        match ($rol) {
            'ADMIN' => DB::table('personal_administrativo')->insert(['persona_id' => $persona, 'usuario_id' => $id, 'cargo' => 'Administrador']),
            'DOCENTE' => DB::table('docente')->insert(['persona_id' => $persona, 'usuario_id' => $id]),
            'ESTUDIANTE' => DB::table('estudiante')->insert(['persona_id' => $persona, 'usuario_id' => $id, 'codigo' => $usuario]),
        };

        return $id;
    }

    /** Entra como administrador (Sanctum) y devuelve su id de usuario */
    public static function comoAdmin(): int
    {
        $id = self::cuenta('ADMIN', 'admin'.self::siguiente());
        app('auth')->forgetGuards();
        Sanctum::actingAs(Usuario::find($id));

        return $id;
    }

    /** Docente con nombre real; devuelve el id de docente */
    public static function docenteLlamado(string $nombres, string $apellidos): int
    {
        $persona = self::persona(['nombres' => $nombres, 'apellidos' => $apellidos]);

        return DB::table('docente')->insertGetId(['persona_id' => $persona, 'usuario_id' => self::usuario()]);
    }

    /** Escenario completo: periodo, bimestre, aula, curso, matrícula y evaluación */
    public static function escenario(): object
    {
        $periodo = self::periodo();
        $bimestre = self::bimestre($periodo);
        $aula = self::aula($periodo);
        $curso = self::curso($aula);
        $matricula = self::matricula($aula, $periodo);
        $evaluacion = self::evaluacion($curso, $bimestre);

        return (object) compact('periodo', 'bimestre', 'aula', 'curso', 'matricula', 'evaluacion');
    }
}
