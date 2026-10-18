<?php

use App\Database\Migracion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migracion
{
    public function up(): void
    {
        Schema::create('institucion', function (Blueprint $t) {
            $t->comment('Datos y parámetros del colegio (una sola fila)');
            $t->id();
            $t->string('nombre', 150);
            $t->string('codigo_modular', 20)->nullable();
            $t->string('ugel', 80)->nullable();
            $t->string('direccion', 200)->nullable();
            $t->string('telefono', 20)->nullable();
            $t->string('correo', 120)->nullable();
            $t->decimal('nota_aprobatoria', 4, 2)->default(11)->comment('Promedio mínimo para aprobar');
            $t->unsignedTinyInteger('umbral_asistencia_alto')->default(85)->comment('% de asistencia por debajo del cual el riesgo es ALTO');
            $t->unsignedTinyInteger('umbral_asistencia_medio')->default(90)->comment('% de asistencia por debajo del cual el riesgo es MEDIO');
            $t->decimal('umbral_promedio_alto', 4, 2)->default(11)->comment('Promedio por debajo del cual (con pendientes) el riesgo es ALTO');
            $t->decimal('umbral_promedio_medio', 4, 2)->default(12)->comment('Promedio por debajo del cual el riesgo es MEDIO');
            $t->unsignedTinyInteger('umbral_pendientes_alto')->default(3)->comment('Entregas pendientes que, con promedio bajo, dan riesgo ALTO');
            $t->unsignedTinyInteger('dias_cierre_bimestre')->default(7)->comment('Días después del fin del bimestre para la fecha de cierre por defecto');
            $this->fechas($t);
        });
        $this->check('institucion', 'ck_institucion_nota', 'nota_aprobatoria BETWEEN 0 AND 20');
        $this->check('institucion', 'ck_institucion_asistencia', 'umbral_asistencia_alto < umbral_asistencia_medio AND umbral_asistencia_medio <= 100');
        $this->check('institucion', 'ck_institucion_promedio', 'umbral_promedio_alto <= umbral_promedio_medio AND umbral_promedio_medio <= 20');

        Schema::create('escala_literal', function (Blueprint $t) {
            $t->comment('Equivalencia de la nota vigesimal a AD/A/B/C (solo para mostrar)');
            $t->id();
            $t->string('codigo', 3)->unique()->comment('AD, A, B o C');
            $t->string('descripcion', 80);
            $t->decimal('nota_minima', 4, 2);
            $t->decimal('nota_maxima', 4, 2);
            $t->unsignedTinyInteger('orden');
        });
        $this->check('escala_literal', 'ck_escala_rango', 'nota_minima >= 0 AND nota_maxima <= 20 AND nota_minima <= nota_maxima');
    }

    public function down(): void
    {
        Schema::dropIfExists('escala_literal');
        Schema::dropIfExists('institucion');
    }
};
