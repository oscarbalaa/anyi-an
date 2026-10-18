<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// RNF-07: errores de la API en JSON y en español

it('responde 404 en JSON para una ruta que no existe', function () {
    $this->getJson('/api/v1/no-existe')
        ->assertNotFound()
        ->assertExactJson(['mensaje' => 'El recurso solicitado no existe.']);
});

it('responde 404 en JSON aunque el cliente no pida JSON', function () {
    $this->get('/api/v1/no-existe')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('mensaje', 'El recurso solicitado no existe.');
});

it('responde 405 en JSON para un método no permitido', function () {
    $this->postJson('/api/v1/health')
        ->assertStatus(405)
        ->assertJsonPath('mensaje', 'Método no permitido para esta ruta.');
});

it('devuelve los errores de validación en español', function () {
    Route::post('/api/v1/prueba-validacion', function (Request $request) {
        $request->validate([
            'correo' => ['required'],
            'nombres' => ['required', 'max:5'],
        ]);
    });

    $this->postJson('/api/v1/prueba-validacion', ['nombres' => 'Valeria'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Los datos enviados no son válidos.')
        ->assertJsonPath('errores.correo.0', 'El correo es obligatorio.')
        ->assertJsonPath('errores.nombres.0', 'El campo Nombres no debe tener más de 5 caracteres.');
});

it('oculta el detalle de un error interno cuando no está en modo depuración', function () {
    config(['app.debug' => false]);
    Route::get('/api/v1/prueba-error', fn () => throw new RuntimeException('clave secreta'));

    $this->getJson('/api/v1/prueba-error')
        ->assertStatus(500)
        ->assertExactJson(['mensaje' => 'Ocurrió un error inesperado. Inténtalo más tarde.']);
});

it('responde 401 en JSON cuando falta la sesión', function () {
    Route::middleware('auth:sanctum')->get('/api/v1/prueba-privada', fn () => 'ok');

    $this->getJson('/api/v1/prueba-privada')
        ->assertUnauthorized()
        ->assertJsonPath('mensaje', 'Tu sesión no es válida o expiró. Inicia sesión nuevamente.');
});
