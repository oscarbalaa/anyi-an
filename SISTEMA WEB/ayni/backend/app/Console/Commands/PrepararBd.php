<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Lo usa el panel: aplica migraciones nuevas y solo siembra si la BD está vacía. */
class PrepararBd extends Command
{
    protected $signature = 'ayni:preparar-bd';

    protected $description = 'Aplica las migraciones pendientes y carga los datos por defecto si la base de datos está vacía';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);

        if (Schema::hasTable('rol') && DB::table('rol')->exists()) {
            $this->info('La base de datos ya tiene datos: no se volvieron a cargar.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--force' => true]);
        $this->info('Datos por defecto cargados.');

        return self::SUCCESS;
    }
}
