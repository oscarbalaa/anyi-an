<?php

use App\Http\Controllers\Api\V1\Admin\AlertaAdministrativaController;
use App\Http\Controllers\Api\V1\Admin\AsistenciaController as AsistenciaAdminController;
use App\Http\Controllers\Api\V1\Admin\AulaController;
use App\Http\Controllers\Api\V1\Admin\CargaController;
use App\Http\Controllers\Api\V1\Admin\CatalogoController;
use App\Http\Controllers\Api\V1\Admin\DocenteController;
use App\Http\Controllers\Api\V1\Admin\EstudianteController;
use App\Http\Controllers\Api\V1\Admin\InstitucionController;
use App\Http\Controllers\Api\V1\Admin\MatriculaController;
use App\Http\Controllers\Api\V1\Admin\OpcionesController;
use App\Http\Controllers\Api\V1\Admin\PeriodoController;
use App\Http\Controllers\Api\V1\Admin\UsuarioController;
use App\Http\Controllers\Api\V1\AlertaController;
use App\Http\Controllers\Api\V1\ArchivoController;
use App\Http\Controllers\Api\V1\AsistenciaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CalendarioController;
use App\Http\Controllers\Api\V1\Docente\EvaluacionController;
use App\Http\Controllers\Api\V1\Estudiante\AsistenciaController as MiAsistenciaController;
use App\Http\Controllers\Api\V1\Estudiante\EvaluacionController as MisEvaluacionesController;
use App\Http\Controllers\Api\V1\IncidenciaController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\PanelRiesgoController;
use App\Http\Controllers\Api\V1\PerfilController;
use App\Http\Controllers\Api\V1\SaludController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', SaludController::class)->name('salud');
    Route::get('calendario/actual', [CalendarioController::class, 'actual'])->name('calendario.actual');

    // Acceso (M4)
    Route::post('captcha', [AuthController::class, 'captcha'])->middleware('throttle:captcha')->name('captcha');
    Route::post('auth/ingresar', [AuthController::class, 'ingresar'])->middleware('throttle:ingreso')->name('auth.ingresar');
    Route::post('auth/recuperar', [AuthController::class, 'recuperar'])->middleware('throttle:recuperacion')->name('auth.recuperar');
    Route::post('auth/restablecer', [AuthController::class, 'restablecer'])->middleware('throttle:recuperacion')->name('auth.restablecer');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/yo', [AuthController::class, 'yo'])->name('auth.yo');
        Route::post('auth/salir', [AuthController::class, 'salir'])->name('auth.salir');
        Route::post('auth/cambiar-contrasena', [AuthController::class, 'cambiarContrasena'])->name('auth.cambiar');

        // Rutas de cada módulo: requieren sesión y contraseña definitiva
        Route::middleware('cambio.contrasena')->group(function () {
            Route::get('sesion/verificar', fn () => response()->json(['ok' => true]))->name('sesion.verificar');

            // Mi perfil (M5)
            Route::get('perfil', [PerfilController::class, 'show'])->name('perfil');
            Route::put('perfil', [PerfilController::class, 'update'])->name('perfil.actualizar');

            // Asistencia por clase (M8): el docente, de sus cursos; el administrador, de todos
            $fecha = '\d{4}-\d{2}-\d{2}';
            Route::middleware('rol:DOCENTE')->prefix('docente')->name('docente.')->group(function () use ($fecha) {
                Route::get('asistencia/cursos', [AsistenciaController::class, 'misCursos'])->name('asistencia.cursos');
                Route::get('asistencia/cursos/{curso}/fechas', [AsistenciaController::class, 'fechas'])->whereNumber('curso')->name('asistencia.fechas');
                Route::get('asistencia/cursos/{curso}/{fecha}', [AsistenciaController::class, 'show'])->whereNumber('curso')->where('fecha', $fecha)->name('asistencia.show');
                Route::put('asistencia/cursos/{curso}/{fecha}', [AsistenciaController::class, 'guardar'])->whereNumber('curso')->where('fecha', $fecha)->name('asistencia.guardar');

                // Evaluaciones, notas y calificaciones (M9)
                Route::get('cursos/{curso}/evaluaciones', [EvaluacionController::class, 'index'])->whereNumber('curso')->name('evaluaciones.index');
                Route::post('cursos/{curso}/evaluaciones', [EvaluacionController::class, 'store'])->whereNumber('curso')->name('evaluaciones.store');
                Route::put('evaluaciones/{evaluacion}', [EvaluacionController::class, 'update'])->whereNumber('evaluacion')->name('evaluaciones.update');
                Route::delete('evaluaciones/{evaluacion}', [EvaluacionController::class, 'destroy'])->whereNumber('evaluacion')->name('evaluaciones.destroy');
                Route::post('evaluaciones/{evaluacion}/material', [EvaluacionController::class, 'subirMaterial'])->whereNumber('evaluacion')->name('evaluaciones.material');
                Route::delete('evaluaciones/{evaluacion}/material/{archivo}', [EvaluacionController::class, 'quitarMaterial'])->whereNumber(['evaluacion', 'archivo'])->name('evaluaciones.material.destroy');
                Route::get('evaluaciones/{evaluacion}/notas', [EvaluacionController::class, 'notas'])->whereNumber('evaluacion')->name('notas.show');
                Route::put('evaluaciones/{evaluacion}/notas', [EvaluacionController::class, 'guardarNotas'])->whereNumber('evaluacion')->name('notas.guardar');
                Route::get('evaluaciones/{evaluacion}/notas/excel', [EvaluacionController::class, 'excelNotas'])->whereNumber('evaluacion')->name('notas.excel');
                Route::get('cursos/{curso}/calificaciones', [EvaluacionController::class, 'calificaciones'])->whereNumber('curso')->name('calificaciones.show');
                Route::post('cursos/{curso}/calificaciones/publicar', [EvaluacionController::class, 'publicar'])->whereNumber('curso')->name('calificaciones.publicar');
                Route::get('cursos/{curso}/calificaciones/excel', [EvaluacionController::class, 'excelCalificaciones'])->whereNumber('curso')->name('calificaciones.excel');
            });
            Route::middleware('rol:ESTUDIANTE')->prefix('estudiante')->name('estudiante.')->group(function () {
                Route::get('asistencia', MiAsistenciaController::class)->name('asistencia');
                Route::get('notas', [MisEvaluacionesController::class, 'notas'])->name('notas');
                Route::get('notas/cursos/{curso}', [MisEvaluacionesController::class, 'curso'])->whereNumber('curso')->name('notas.curso');
                Route::get('actividades', [MisEvaluacionesController::class, 'actividades'])->name('actividades');
                Route::get('actividades/{evaluacion}', [MisEvaluacionesController::class, 'actividad'])->whereNumber('evaluacion')->name('actividades.show');
                Route::post('actividades/{evaluacion}/entrega', [MisEvaluacionesController::class, 'entregar'])->whereNumber('evaluacion')->name('actividades.entregar');
                Route::get('horario', [MisEvaluacionesController::class, 'horario'])->name('horario');
            });

            // Entregas y material de apoyo: cada rol descarga solo lo que le corresponde (M9)
            Route::get('archivos/{archivo}', ArchivoController::class)->whereNumber('archivo')->name('archivos.show');

            // Notificaciones de la campana (M10): todos los roles, cada uno las suyas
            Route::get('notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
            Route::get('notificaciones/resumen', [NotificacionController::class, 'resumen'])->name('notificaciones.resumen');
            Route::post('notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');
            Route::post('notificaciones/{notificacion}/leer', [NotificacionController::class, 'leer'])->whereNumber('notificacion')->name('notificaciones.leer');

            // Incidencias (M10): el docente, de sus estudiantes; el administrador, de todos
            foreach (['docente' => 'rol:DOCENTE', 'admin' => 'rol:ADMIN'] as $prefijo => $rol) {
                Route::middleware($rol)->prefix($prefijo)->name("{$prefijo}.")->group(function () {
                    Route::get('incidencias', [IncidenciaController::class, 'index'])->name('incidencias.index');
                    Route::get('incidencias/estudiantes', [IncidenciaController::class, 'estudiantes'])->name('incidencias.estudiantes');
                    Route::post('incidencias', [IncidenciaController::class, 'store'])->name('incidencias.store');
                });
            }

            // Alertas de riesgo y paneles (M11): el docente, de sus aulas (panel solo si coordina); el administrador, todo
            foreach (['docente' => 'rol:DOCENTE', 'admin' => 'rol:ADMIN'] as $prefijo => $rol) {
                Route::middleware($rol)->prefix($prefijo)->name("{$prefijo}.")->group(function () use ($prefijo) {
                    Route::get('alertas', [AlertaController::class, 'index'])->name('alertas.index');
                    Route::get('alertas/{alerta}', [AlertaController::class, 'show'])->whereNumber('alerta')->name('alertas.show');
                    Route::post('alertas/{alerta}/estado', [AlertaController::class, 'estado'])->whereNumber('alerta')->name('alertas.estado');
                    Route::post('alertas/{alerta}/intervenciones', [AlertaController::class, 'intervencion'])->whereNumber('alerta')->name('alertas.intervenciones');
                    Route::post('alertas/{alerta}/responsable', [AlertaController::class, 'responsable'])->whereNumber('alerta')->name('alertas.responsable');
                    $panel = $prefijo === 'admin' ? 'riesgo' : 'coordinacion';
                    Route::get($panel, [PanelRiesgoController::class, 'show'])->name("{$panel}.show");
                    Route::post("{$panel}/recalcular", [PanelRiesgoController::class, 'recalcular'])->name("{$panel}.recalcular");
                });
            }
            Route::get('admin/alertas-administrativas', [AlertaAdministrativaController::class, 'index'])->middleware('rol:ADMIN')->name('admin.alertas-administrativas');

            // Administración de usuarios (M5)
            Route::middleware('rol:ADMIN')->prefix('admin')->name('admin.')->group(function () use ($fecha) {
                Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
                Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
                Route::get('usuarios/{usuario}', [UsuarioController::class, 'show'])->whereNumber('usuario')->name('usuarios.show');
                Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->whereNumber('usuario')->name('usuarios.update');
                Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'estado'])->whereNumber('usuario')->name('usuarios.estado');
                Route::post('usuarios/{usuario}/contrasena-temporal', [UsuarioController::class, 'contrasenaTemporal'])->whereNumber('usuario')->name('usuarios.temporal');

                // Estructura académica (M6)
                Route::get('estructura/opciones', OpcionesController::class)->name('estructura.opciones');

                Route::get('periodos', [PeriodoController::class, 'index'])->name('periodos.index');
                Route::post('periodos', [PeriodoController::class, 'store'])->name('periodos.store');
                Route::put('periodos/{periodo}', [PeriodoController::class, 'update'])->whereNumber('periodo')->name('periodos.update');
                Route::patch('periodos/{periodo}/estado', [PeriodoController::class, 'estado'])->whereNumber('periodo')->name('periodos.estado');
                Route::delete('periodos/{periodo}', [PeriodoController::class, 'destroy'])->whereNumber('periodo')->name('periodos.destroy');
                Route::post('periodos/{periodo}/bimestres', [PeriodoController::class, 'guardarBimestre'])->whereNumber('periodo')->name('bimestres.store');
                Route::put('bimestres/{bimestre}', [PeriodoController::class, 'actualizarBimestre'])->whereNumber('bimestre')->name('bimestres.update');
                Route::post('bimestres/{bimestre}/cerrar', [PeriodoController::class, 'cerrarBimestre'])->whereNumber('bimestre')->name('bimestres.cerrar');
                Route::delete('bimestres/{bimestre}', [PeriodoController::class, 'eliminarBimestre'])->whereNumber('bimestre')->name('bimestres.destroy');

                Route::get('aulas', [AulaController::class, 'index'])->name('aulas.index');
                Route::post('aulas', [AulaController::class, 'store'])->name('aulas.store');
                Route::put('aulas/{aula}', [AulaController::class, 'update'])->whereNumber('aula')->name('aulas.update');
                Route::delete('aulas/{aula}', [AulaController::class, 'destroy'])->whereNumber('aula')->name('aulas.destroy');
                Route::post('coordinaciones', [AulaController::class, 'crearCoordinacion'])->name('coordinaciones.store');
                Route::delete('coordinaciones/{coordinacion}', [AulaController::class, 'eliminarCoordinacion'])->whereNumber('coordinacion')->name('coordinaciones.destroy');

                Route::get('asignaturas', [CatalogoController::class, 'asignaturas'])->name('asignaturas.index');
                Route::post('asignaturas', [CatalogoController::class, 'crearAsignatura'])->name('asignaturas.store');
                Route::put('asignaturas/{asignatura}', [CatalogoController::class, 'actualizarAsignatura'])->whereNumber('asignatura')->name('asignaturas.update');
                Route::delete('asignaturas/{asignatura}', [CatalogoController::class, 'eliminarAsignatura'])->whereNumber('asignatura')->name('asignaturas.destroy');
                Route::get('tipos-evaluacion', [CatalogoController::class, 'tipos'])->name('tipos.index');
                Route::post('tipos-evaluacion', [CatalogoController::class, 'crearTipo'])->name('tipos.store');
                Route::put('tipos-evaluacion/{tipo}', [CatalogoController::class, 'actualizarTipo'])->whereNumber('tipo')->name('tipos.update');
                Route::delete('tipos-evaluacion/{tipo}', [CatalogoController::class, 'eliminarTipo'])->whereNumber('tipo')->name('tipos.destroy');

                Route::get('aulas/{aula}/carga', [CargaController::class, 'show'])->whereNumber('aula')->name('carga.show');
                Route::post('cursos', [CargaController::class, 'asignar'])->name('cursos.store');
                Route::put('cursos/{curso}', [CargaController::class, 'cambiarDocente'])->whereNumber('curso')->name('cursos.update');
                Route::delete('cursos/{curso}', [CargaController::class, 'quitar'])->whereNumber('curso')->name('cursos.destroy');
                Route::post('horarios', [CargaController::class, 'crearBloque'])->name('horarios.store');
                Route::put('horarios/{bloque}', [CargaController::class, 'actualizarBloque'])->whereNumber('bloque')->name('horarios.update');
                Route::delete('horarios/{bloque}', [CargaController::class, 'quitarBloque'])->whereNumber('bloque')->name('horarios.destroy');

                // Personas y matrículas (M7)
                Route::get('docentes', [DocenteController::class, 'index'])->name('docentes.index');
                Route::post('docentes', [DocenteController::class, 'store'])->name('docentes.store');
                Route::get('docentes/{docente}', [DocenteController::class, 'show'])->whereNumber('docente')->name('docentes.show');
                Route::put('docentes/{docente}', [DocenteController::class, 'update'])->whereNumber('docente')->name('docentes.update');

                Route::get('estudiantes', [EstudianteController::class, 'index'])->name('estudiantes.index');
                Route::post('estudiantes', [EstudianteController::class, 'store'])->name('estudiantes.store');
                Route::get('estudiantes/plantilla', [EstudianteController::class, 'plantilla'])->name('estudiantes.plantilla');
                Route::post('estudiantes/importar/revisar', [EstudianteController::class, 'revisar'])->name('estudiantes.revisar');
                Route::post('estudiantes/importar/errores', [EstudianteController::class, 'errores'])->name('estudiantes.errores');
                Route::post('estudiantes/importar', [EstudianteController::class, 'importar'])->name('estudiantes.importar');
                Route::get('estudiantes/{estudiante}', [EstudianteController::class, 'show'])->whereNumber('estudiante')->name('estudiantes.show');
                Route::put('estudiantes/{estudiante}', [EstudianteController::class, 'update'])->whereNumber('estudiante')->name('estudiantes.update');
                Route::post('estudiantes/{estudiante}/apoderados', [EstudianteController::class, 'crearApoderado'])->whereNumber('estudiante')->name('apoderados.store');
                Route::put('apoderados/{apoderado}', [EstudianteController::class, 'actualizarApoderado'])->whereNumber('apoderado')->name('apoderados.update');
                Route::delete('apoderados/{apoderado}', [EstudianteController::class, 'eliminarApoderado'])->whereNumber('apoderado')->name('apoderados.destroy');

                Route::get('matriculas', [MatriculaController::class, 'index'])->name('matriculas.index');
                Route::get('matriculas/candidatos', [MatriculaController::class, 'candidatos'])->name('matriculas.candidatos');
                Route::post('matriculas', [MatriculaController::class, 'store'])->name('matriculas.store');
                Route::post('matriculas/{matricula}/retirar', [MatriculaController::class, 'retirar'])->whereNumber('matricula')->name('matriculas.retirar');
                Route::post('matriculas/{matricula}/anular', [MatriculaController::class, 'anular'])->whereNumber('matricula')->name('matriculas.anular');

                // Asistencia (M8)
                Route::get('asistencia/resumen', [AsistenciaAdminController::class, 'resumen'])->name('asistencia.resumen');
                Route::get('asistencia/cursos', [AsistenciaAdminController::class, 'cursos'])->name('asistencia.cursos');
                Route::get('asistencia/cursos/{curso}/fechas', [AsistenciaController::class, 'fechas'])->whereNumber('curso')->name('asistencia.fechas');
                Route::get('asistencia/cursos/{curso}/{fecha}', [AsistenciaController::class, 'show'])->whereNumber('curso')->where('fecha', $fecha)->name('asistencia.show');
                Route::put('asistencia/cursos/{curso}/{fecha}', [AsistenciaController::class, 'guardar'])->whereNumber('curso')->where('fecha', $fecha)->name('asistencia.guardar');
                Route::delete('asistencia/cursos/{curso}/{fecha}', [AsistenciaAdminController::class, 'eliminar'])->whereNumber('curso')->where('fecha', $fecha)->name('asistencia.eliminar');

                Route::get('institucion', [InstitucionController::class, 'show'])->name('institucion.show');
                Route::put('institucion', [InstitucionController::class, 'update'])->name('institucion.update');
            });
        });
    });
});
