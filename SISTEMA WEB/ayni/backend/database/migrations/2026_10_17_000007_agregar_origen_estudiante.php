<?php

use App\Database\Migracion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// M7: la lista de estudiantes muestra si se registró a mano o por importación de Excel (mockup admin/09)
return new class extends Migracion
{
    public function up(): void
    {
        Schema::table('estudiante', function (Blueprint $t) {
            $t->string('origen', 10)->default('MANUAL')->after('estado')->comment('MANUAL o EXCEL');
        });
        $this->check('estudiante', 'ck_estudiante_origen', "origen IN ('MANUAL', 'EXCEL')");
    }

    public function down(): void
    {
        // DROP CONSTRAINT sirve en MySQL 8 y en MariaDB (el hosting)
        DB::statement('ALTER TABLE `estudiante` DROP CONSTRAINT `ck_estudiante_origen`');
        Schema::table('estudiante', fn (Blueprint $t) => $t->dropColumn('origen'));
    }
};
