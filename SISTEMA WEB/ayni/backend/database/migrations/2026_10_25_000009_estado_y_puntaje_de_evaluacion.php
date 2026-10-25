<?php

use App\Database\Migracion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Revisión del grupo: la evaluación puede quedar en borrador y se califica sobre su propio puntaje
// (un examen sobre 50, una tarea sobre 10); el promedio del curso sigue en la escala de 0 a 20
return new class extends Migracion
{
    public function up(): void
    {
        Schema::table('evaluacion', function (Blueprint $t) {
            $t->string('estado', 10)->default('PUBLICADA')->after('requiere_entrega')->comment('BORRADOR (solo la ve el docente) o PUBLICADA');
            $t->decimal('puntaje_maximo', 5, 2)->default(20)->after('estado')->comment('La nota se pone de 0 a este puntaje');
        });
        $this->check('evaluacion', 'ck_evaluacion_estado', "estado IN ('BORRADOR', 'PUBLICADA')");
        $this->check('evaluacion', 'ck_evaluacion_puntaje', 'puntaje_maximo BETWEEN 1 AND 100');

        // DROP CONSTRAINT lo entienden MySQL 8 y MariaDB 10.6
        DB::statement('ALTER TABLE `nota_evaluacion` DROP CONSTRAINT `ck_nota_rango`');
        DB::statement("ALTER TABLE `nota_evaluacion` MODIFY `nota` DECIMAL(5,2) NULL COMMENT 'De 0 al puntaje máximo de la evaluación; NULL = aún sin calificar'");
        $this->check('nota_evaluacion', 'ck_nota_rango', 'nota IS NULL OR nota BETWEEN 0 AND 100');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `nota_evaluacion` DROP CONSTRAINT `ck_nota_rango`');
        DB::statement("ALTER TABLE `nota_evaluacion` MODIFY `nota` DECIMAL(4,2) NULL COMMENT '0 a 20; NULL = aún sin calificar'");
        $this->check('nota_evaluacion', 'ck_nota_rango', 'nota IS NULL OR nota BETWEEN 0 AND 20');

        DB::statement('ALTER TABLE `evaluacion` DROP CONSTRAINT `ck_evaluacion_puntaje`');
        DB::statement('ALTER TABLE `evaluacion` DROP CONSTRAINT `ck_evaluacion_estado`');
        Schema::table('evaluacion', fn (Blueprint $t) => $t->dropColumn(['estado', 'puntaje_maximo']));
    }
};
