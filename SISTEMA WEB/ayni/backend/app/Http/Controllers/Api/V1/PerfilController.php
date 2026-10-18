<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perfil\PerfilRequest;
use App\Support\Auditoria;
use App\Support\PerfilUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** CU-05: mi perfil (RF-14). El cambio de contraseña (RF-15) usa auth/cambiar-contrasena */
class PerfilController extends Controller
{
    private const TABLAS = ['ADMIN' => 'personal_administrativo', 'DOCENTE' => 'docente', 'ESTUDIANTE' => 'estudiante'];

    public function show(Request $request): JsonResponse
    {
        return $this->json(['perfil' => $this->perfil($request)]);
    }

    public function update(PerfilRequest $request): JsonResponse
    {
        $usuario = $request->user()->load('rol');
        $datos = $request->validated();
        $personaId = DB::table(self::TABLAS[$usuario->rol->codigo])->where('usuario_id', $usuario->id)->value('persona_id');
        $antes = DB::table('persona')->where('id', $personaId)->first(['correo', 'celular']);

        DB::transaction(function () use ($usuario, $datos, $personaId, $antes) {
            $usuario->forceFill(['email' => $datos['correo'] ?? null, 'actualizado_por' => $usuario->id])->save();
            DB::table('persona')->where('id', $personaId)->update([
                'correo' => $datos['correo'] ?? null,
                'celular' => $datos['celular'] ?? null,
                'updated_at' => now(),
            ]);
            Auditoria::registrar('ACTUALIZAR_PERFIL', 'usuario', $usuario->id, [
                'correo' => ['antes' => $antes?->correo, 'despues' => $datos['correo'] ?? null],
                'celular' => ['antes' => $antes?->celular, 'despues' => $datos['celular'] ?? null],
            ]);
        });

        return $this->json(['mensaje' => 'Tus datos se actualizaron.', 'perfil' => $this->perfil($request)]);
    }

    private function perfil(Request $request): array
    {
        $usuario = $request->user()->load('rol');
        $rol = $usuario->rol->codigo;
        $tabla = self::TABLAS[$rol];
        $fila = DB::table($tabla)
            ->join('persona', 'persona.id', '=', "{$tabla}.persona_id")
            ->where("{$tabla}.usuario_id", $usuario->id)
            ->first(["{$tabla}.*", 'persona.tipo_documento', 'persona.numero_documento', 'persona.celular', 'persona.correo as correo_persona']);

        $datos = PerfilUsuario::de($usuario) + [
            'estado' => $usuario->estado,
            'documento' => $fila ? "{$fila->tipo_documento} {$fila->numero_documento}" : null,
            'celular' => $fila?->celular,
        ];

        return $datos + match ($rol) {
            'ADMIN' => ['cargo' => $fila?->cargo, 'ambito' => 'Toda la institución'],
            'DOCENTE' => $this->datosDocente($fila),
            default => $this->datosEstudiante($fila),
        };
    }

    private function datosDocente(?object $docente): array
    {
        if (! $docente) {
            return [];
        }
        $periodo = DB::table('periodo_academico')->where('estado', 'EN_CURSO')->value('id');

        // "Matemática · 3.º A, 3.º B"
        $cursos = DB::table('curso_aula')
            ->join('asignatura', 'asignatura.id', '=', 'curso_aula.asignatura_id')
            ->join('aula', 'aula.id', '=', 'curso_aula.aula_id')
            ->join('grado', 'grado.id', '=', 'aula.grado_id')
            ->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('curso_aula.docente_id', $docente->id)
            ->where('aula.periodo_id', $periodo)
            ->orderBy('asignatura.nombre')->orderBy('grado.nivel')->orderBy('grado.numero')->orderBy('seccion.nombre')
            ->get(['asignatura.nombre as asignatura', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion'])
            ->groupBy('asignatura')
            ->map(fn ($aulas, $asignatura) => $asignatura.' · '.$aulas->map(fn ($a) => PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion))->unique()->implode(', '))
            ->values();

        $tutoria = DB::table('aula')
            ->join('grado', 'grado.id', '=', 'aula.grado_id')
            ->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('aula.tutor_docente_id', $docente->id)->where('aula.periodo_id', $periodo)
            ->first(['grado.numero', 'grado.nivel', 'seccion.nombre as seccion']);

        return [
            'especialidad' => $docente->especialidad,
            'cursos' => $cursos,
            'coordinacion' => PerfilUsuario::coordinaciones([$docente->id])[$docente->id] ?? null,
            'tutoria' => $tutoria ? PerfilUsuario::nombreAula($tutoria->numero, $tutoria->nivel, $tutoria->seccion) : null,
        ];
    }

    private function datosEstudiante(?object $estudiante): array
    {
        if (! $estudiante) {
            return [];
        }
        $periodo = DB::table('periodo_academico')->where('estado', 'EN_CURSO')->value('id');
        $tutor = DB::table('matricula')
            ->join('aula', 'aula.id', '=', 'matricula.aula_id')
            ->join('docente', 'docente.id', '=', 'aula.tutor_docente_id')
            ->join('persona', 'persona.id', '=', 'docente.persona_id')
            ->where('matricula.estudiante_id', $estudiante->id)
            ->where('matricula.periodo_id', $periodo)
            ->where('matricula.estado', 'ACTIVA')
            ->first(['persona.nombres', 'persona.apellidos']);

        return [
            'codigo' => $estudiante->codigo,
            'aula' => PerfilUsuario::aulas([$estudiante->id])[$estudiante->id] ?? null,
            'tutor' => $tutor ? explode(' ', $tutor->nombres)[0].' '.explode(' ', $tutor->apellidos)[0] : null,
        ];
    }

    private function json(array $datos): JsonResponse
    {
        return response()->json($datos, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
