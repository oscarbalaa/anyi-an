<?php

// RNF-01: el entorno local replica el hosting del curso (PHP 8.1)

it('corre sobre PHP 8.1, igual que el hosting', function () {
    expect(PHP_VERSION)->toStartWith('8.1.');
});

it('fija la plataforma de Composer en PHP 8.1.34', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    expect($composer['config']['platform']['php'])->toBe('8.1.34');
});

it('usa Laravel 10', function () {
    expect(app()->version())->toStartWith('10.');
});

it('tiene las extensiones de PHP que el sistema necesita', function (string $extension) {
    expect(extension_loaded($extension))->toBeTrue();
})->with(['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'zip', 'gd', 'xml', 'ctype', 'tokenizer']);

it('usa la zona horaria de Lima y el idioma español', function () {
    expect(config('app.timezone'))->toBe('America/Lima')
        ->and(config('app.locale'))->toBe('es')
        ->and(date_default_timezone_get())->toBe('America/Lima');
});

it('conecta a MySQL en utf8mb4 y con la hora de Lima', function () {
    $conexion = config('database.connections.mysql');

    expect($conexion['charset'])->toBe('utf8mb4')
        ->and($conexion['timezone'])->toBe('-05:00');
});

it('se niega a correr las pruebas sobre la base de datos del sistema', function () {
    expect(fn () => Tests\TestCase::verificarBaseDePruebas('ayni'))->toThrow(RuntimeException::class, 'ayni_pruebas');
    Tests\TestCase::verificarBaseDePruebas('ayni_pruebas');
    expect(config('database.connections.mysql.database'))->toBe('ayni_pruebas');
});
