<?php

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
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CalendarioController;
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

            // Administración de usuarios (M5)
            Route::middleware('rol:ADMIN')->prefix('admin')->name('admin.')->group(function () {
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

                Route::get('institucion', [InstitucionController::class, 'show'])->name('institucion.show');
                Route::put('institucion', [InstitucionController::class, 'update'])->name('institucion.update');
            });
        });
    });
});
