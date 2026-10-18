<?php

// RF-65, RNF-01: la API responde y confirma la conexión a la BD

it('responde 200 y confirma la conexión a la base de datos', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('estado', 'ok')
        ->assertJsonPath('aplicacion', 'AYNI')
        ->assertJsonPath('base_de_datos.conectada', true)
        ->assertJsonPath('zona_horaria', 'America/Lima')
        ->assertJsonStructure(['version', 'colegio', 'hora_servidor']);
});

it('informa el motor de base de datos fuera de producción', function () {
    $this->getJson('/api/v1/health')
        ->assertJsonPath('base_de_datos.motor', fn ($motor) => in_array($motor, ['MySQL', 'MariaDB']))
        ->assertJsonStructure(['php', 'laravel', 'entorno']);
});

it('no expone versiones del servidor en producción', function () {
    app()['env'] = 'production';

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonMissingPath('php')
        ->assertJsonMissingPath('laravel')
        ->assertJsonMissingPath('base_de_datos.version');
});

it('entrega la hora del servidor con el desfase de Lima', function () {
    $hora = $this->getJson('/api/v1/health')->json('hora_servidor');

    expect($hora)->toEndWith('-05:00');
});
