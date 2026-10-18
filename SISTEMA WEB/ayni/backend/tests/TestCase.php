<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        self::verificarBaseDePruebas(config('database.connections.'.config('database.default').'.database'));
    }

    // Candado: las pruebas borran y recrean tablas, jamás deben tocar la base del sistema
    public static function verificarBaseDePruebas(?string $base): void
    {
        if ($base !== 'ayni_pruebas') {
            throw new RuntimeException("Las pruebas deben usar la base 'ayni_pruebas', no '{$base}'.");
        }
    }
}
