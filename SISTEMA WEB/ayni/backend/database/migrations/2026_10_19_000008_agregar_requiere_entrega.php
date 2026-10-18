<?php

use App\Database\Migracion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M9: un examen en clase no pide archivo; una práctica o un proyecto, sí
return new class extends Migracion
{
    public function up(): void
    {
        Schema::table('evaluacion', function (Blueprint $t) {
            $t->boolean('requiere_entrega')->default(true)->after('peso')->comment('El estudiante sube un archivo');
        });
    }

    public function down(): void
    {
        Schema::table('evaluacion', fn (Blueprint $t) => $t->dropColumn('requiere_entrega'));
    }
};
