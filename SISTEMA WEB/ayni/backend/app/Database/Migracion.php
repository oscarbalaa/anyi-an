<?php

namespace App\Database;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Base de las migraciones de AYNI: agrega lo que el Schema de Laravel 10 no trae
 * (restricciones CHECK) y las fechas de auditoría con valor por defecto en la BD.
 */
abstract class Migracion extends Migration
{
    protected function check(string $tabla, string $nombre, string $condicion): void
    {
        DB::statement("ALTER TABLE `{$tabla}` ADD CONSTRAINT `{$nombre}` CHECK ({$condicion})");
    }

    protected function fechas(Blueprint $t): void
    {
        $t->timestamp('created_at')->useCurrent()->comment('Fecha de registro');
        $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Última modificación');
    }

    /** Lista para un CHECK ... IN (...) */
    protected function valores(array $valores): string
    {
        return implode(', ', array_map(fn ($v) => "'{$v}'", $valores));
    }
}
