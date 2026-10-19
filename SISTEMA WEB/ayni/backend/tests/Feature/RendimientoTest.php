<?php

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// M13 - rendimiento: cada pantalla responde en menos de 300 ms con los datos de demostración

const LIMITE_MS = 300;

function sembrarDemoRendimiento(): void
{
    static $sembrado = false;
    if (! $sembrado) {
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        $sembrado = true;
    }
    RefreshDatabaseState::$migrated = false;
}

/** Mejor de 3 llamadas, después de una de calentamiento (la primera arma la caché de rutas y configuración) */
function milisegundos(string $usuario, string $ruta): float
{
    app('auth')->forgetGuards();
    Sanctum::actingAs(Usuario::where('username', $usuario)->firstOrFail());
    test()->getJson($ruta)->assertOk();
    $tiempos = [];
    for ($i = 0; $i < 3; $i++) {
        $inicio = hrtime(true);
        test()->getJson($ruta)->assertOk();
        $tiempos[] = (hrtime(true) - $inicio) / 1e6;
    }

    return min($tiempos);
}

beforeEach(function () {
    sembrarDemoRendimiento();
    $this->withoutMiddleware(ThrottleRequests::class);
});

it('las pantallas de la administración responden en menos de 300 ms', function () {
    $aula = DB::table('aula')->value('id');
    foreach ([
        '/api/v1/admin/inicio', '/api/v1/admin/riesgo', '/api/v1/admin/alertas', '/api/v1/admin/alertas-administrativas',
        '/api/v1/admin/usuarios', '/api/v1/admin/estudiantes', '/api/v1/admin/docentes', '/api/v1/admin/matriculas',
        '/api/v1/admin/asistencia/resumen', '/api/v1/admin/incidencias', '/api/v1/admin/aulas', "/api/v1/admin/aulas/{$aula}/carga",
        '/api/v1/admin/periodos', '/api/v1/admin/estructura/opciones', '/api/v1/admin/reportes/opciones',
    ] as $ruta) {
        expect(milisegundos('cmedina', $ruta))->toBeLessThan(LIMITE_MS, $ruta);
    }
});

it('las pantallas del docente responden en menos de 300 ms', function () {
    $docente = DB::table('docente')->join('usuario', 'usuario.id', '=', 'docente.usuario_id')->where('username', '41234567')->value('docente.id');
    $curso = DB::table('curso_aula')->where('docente_id', $docente)->value('id');
    $evaluacion = DB::table('evaluacion')->where('curso_aula_id', $curso)->value('id');
    $matricula = DB::table('matricula')->join('curso_aula', 'curso_aula.aula_id', '=', 'matricula.aula_id')->where('curso_aula.id', $curso)->value('matricula.id');
    foreach ([
        '/api/v1/docente/inicio', '/api/v1/docente/estudiantes', '/api/v1/docente/asistencia/cursos', "/api/v1/docente/asistencia/cursos/{$curso}/fechas",
        "/api/v1/docente/cursos/{$curso}/evaluaciones", "/api/v1/docente/evaluaciones/{$evaluacion}/notas", "/api/v1/docente/cursos/{$curso}/calificaciones",
        '/api/v1/docente/alertas', '/api/v1/docente/incidencias', "/api/v1/docente/fichas/{$matricula}", '/api/v1/notificaciones', '/api/v1/perfil',
    ] as $ruta) {
        expect(milisegundos('41234567', $ruta))->toBeLessThan(LIMITE_MS, $ruta);
    }
});

it('las pantallas del estudiante responden en menos de 300 ms', function () {
    foreach ([
        '/api/v1/estudiante/inicio', '/api/v1/estudiante/notas', '/api/v1/estudiante/asistencia', '/api/v1/estudiante/actividades',
        '/api/v1/estudiante/horario', '/api/v1/notificaciones/resumen', '/api/v1/calendario/actual',
    ] as $ruta) {
        expect(milisegundos('E-2026-001', $ruta))->toBeLessThan(LIMITE_MS, $ruta);
    }
});

it('las búsquedas y filtros usan índices: ninguna tabla grande se recorre entera', function () {
    // Índices que usan las consultas más frecuentes (asistencia, notas, matrículas, alertas, notificaciones)
    $indices = collect(DB::select("SELECT DISTINCT TABLE_NAME AS tabla, COLUMN_NAME AS columna FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND SEQ_IN_INDEX = 1"))
        ->map(fn ($i) => "{$i->tabla}.{$i->columna}");

    foreach ([
        'asistencia.sesion_clase_id', 'asistencia.matricula_id', 'sesion_clase.curso_aula_id', 'nota_evaluacion.evaluacion_id', 'nota_evaluacion.matricula_id',
        'evaluacion.curso_aula_id', 'matricula.aula_id', 'matricula.estudiante_id', 'curso_aula.aula_id', 'curso_aula.docente_id',
        'calificacion_periodo.matricula_id', 'prediccion_riesgo.matricula_id', 'alerta_estudiantil.matricula_id', 'notificacion.usuario_id', 'incidencia.matricula_id',
    ] as $columna) {
        expect($indices)->toContain($columna);
    }
});
