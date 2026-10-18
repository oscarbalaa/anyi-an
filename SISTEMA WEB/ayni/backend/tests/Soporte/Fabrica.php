<?php

namespace Tests\Soporte;

use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
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
            // Otro año y 3 dígitos: no cuenta para el correlativo de 2026 que generan las pruebas (RN-22)
            'codigo' => sprintf('E-1999-%03d', $n % 1000),
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

    /**
     * Aula 3.º B del periodo 2026 con Matemática (docente 41234567, lunes y miércoles) y Comunicación
     * (docente 42345678, lunes), y tres estudiantes con cuenta (E-2026-001 a 003). II Bimestre: 18/05 al 24/07.
     */
    public static function aulaConCursos(): object
    {
        $admin = self::cuenta('ADMIN', 'cmedina');
        $profe = self::cuenta('DOCENTE', '41234567');
        $otroProfe = self::cuenta('DOCENTE', '42345678');
        $periodo = self::periodo(['nombre' => '2026', 'estado' => 'EN_CURSO']);
        $b1 = self::bimestre($periodo);
        $b2 = self::bimestre($periodo, ['numero' => 2, 'nombre' => 'II Bimestre', 'fecha_inicio' => '2026-05-18', 'fecha_fin' => '2026-07-24', 'fecha_cierre_notas' => '2026-07-31']);
        $aula = self::aula($periodo, ['seccion_id' => self::seccion('B'), 'ambiente' => 'Aula 12']);
        $docente = fn (int $usuario) => DB::table('docente')->where('usuario_id', $usuario)->value('id');
        $mat = DB::table('curso_aula')->insertGetId(['aula_id' => $aula, 'asignatura_id' => DB::table('asignatura')->insertGetId(['codigo' => 'MAT', 'nombre' => 'Matemática']), 'docente_id' => $docente($profe)]);
        $com = DB::table('curso_aula')->insertGetId(['aula_id' => $aula, 'asignatura_id' => DB::table('asignatura')->insertGetId(['codigo' => 'COM', 'nombre' => 'Comunicación']), 'docente_id' => $docente($otroProfe)]);
        DB::table('horario')->insert([
            ['curso_aula_id' => $mat, 'dia_semana' => 1, 'hora_inicio' => '09:45:00', 'hora_fin' => '11:15:00', 'ambiente' => null],
            ['curso_aula_id' => $mat, 'dia_semana' => 3, 'hora_inicio' => '08:00:00', 'hora_fin' => '09:30:00', 'ambiente' => null],
            ['curso_aula_id' => $com, 'dia_semana' => 1, 'hora_inicio' => '08:00:00', 'hora_fin' => '09:30:00', 'ambiente' => 'Biblioteca'],
        ]);

        $alumnos = [];
        foreach (['Aranda Ruiz', 'Quispe Mendoza', 'Ramírez Torres'] as $i => $apellidos) {
            $codigo = sprintf('E-2026-%03d', $i + 1);
            $usuario = self::usuario('ESTUDIANTE', ['username' => $codigo]);
            $estudiante = DB::table('estudiante')->insertGetId(['persona_id' => self::persona(['nombres' => 'Alumno', 'apellidos' => $apellidos]), 'usuario_id' => $usuario, 'codigo' => $codigo]);
            $alumnos[$apellidos] = (object) ['matricula' => self::matricula($aula, $periodo, $estudiante), 'usuario' => $usuario];
        }

        return (object) compact('admin', 'profe', 'otroProfe', 'periodo', 'b1', 'b2', 'aula', 'mat', 'com', 'alumnos');
    }

    /** Inicia sesión (Sanctum) con el usuario indicado */
    public static function como(int $usuarioId): void
    {
        app('auth')->forgetGuards();
        Sanctum::actingAs(Usuario::find($usuarioId));
    }

    /** Un PDF mínimo pero real */
    public static function pdf(string $nombre = 'tarea.pdf', int $relleno = 0): UploadedFile
    {
        $contenido = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

        return UploadedFile::fake()->createWithContent($nombre, $contenido.str_repeat('%', $relleno));
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
