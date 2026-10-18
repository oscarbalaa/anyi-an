<?php

use App\Database\Migracion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migracion
{
    public function up(): void
    {
        Schema::create('tipo_evaluacion', function (Blueprint $t) {
            $t->comment('Tipos de evaluación configurables');
            $t->id();
            $t->string('nombre', 40)->unique()->comment('Práctica, Proyecto, Examen, Semanal, Parcial…');
            $t->string('estado', 10)->default('ACTIVO');
        });
        $this->check('tipo_evaluacion', 'ck_tipo_evaluacion_estado', "estado IN ('ACTIVO', 'INACTIVO')");

        Schema::create('archivo', function (Blueprint $t) {
            $t->comment('Archivos subidos (entregas y material de apoyo); se guardan fuera de la carpeta pública');
            $t->id();
            $t->string('disco', 20)->default('local');
            $t->string('ruta', 255);
            $t->string('nombre_original', 255);
            $t->string('tipo_mime', 100);
            $t->unsignedInteger('tamano_bytes');
            $t->char('hash_sha256', 64);
            $t->foreignId('subido_por')->nullable()->constrained('usuario');
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('evaluacion', function (Blueprint $t) {
            $t->comment('Evaluaciones o actividades de un curso en un bimestre');
            $t->id();
            $t->foreignId('curso_aula_id')->constrained('curso_aula');
            $t->foreignId('subperiodo_id')->constrained('subperiodo');
            $t->foreignId('tipo_evaluacion_id')->constrained('tipo_evaluacion');
            $t->string('titulo', 150);
            $t->text('indicaciones')->nullable();
            $t->date('fecha_asignacion');
            $t->date('fecha_limite')->comment('Se puede entregar hasta las 11:59 p. m. de este día');
            $t->unsignedTinyInteger('peso')->default(1)->comment('Peso relativo (1 a 10) en el promedio');
            $t->foreignId('creado_por')->nullable()->constrained('usuario');
            $this->fechas($t);
            $t->index(['curso_aula_id', 'subperiodo_id'], 'ix_evaluacion_curso');
            $t->index('subperiodo_id', 'ix_evaluacion_subperiodo');
        });
        $this->check('evaluacion', 'ck_evaluacion_peso', 'peso BETWEEN 1 AND 10');
        $this->check('evaluacion', 'ck_evaluacion_fechas', 'fecha_limite >= fecha_asignacion');

        Schema::create('evaluacion_material', function (Blueprint $t) {
            $t->comment('Material de apoyo de una evaluación');
            $t->foreignId('evaluacion_id')->constrained('evaluacion')->cascadeOnDelete();
            $t->foreignId('archivo_id')->constrained('archivo')->cascadeOnDelete();
            $t->primary(['evaluacion_id', 'archivo_id']);
        });

        Schema::create('entrega', function (Blueprint $t) {
            $t->comment('Entrega de un estudiante para una evaluación');
            $t->id();
            $t->foreignId('evaluacion_id')->constrained('evaluacion')->cascadeOnDelete();
            $t->foreignId('matricula_id')->constrained('matricula');
            $t->foreignId('archivo_id')->nullable()->constrained('archivo');
            $t->string('estado', 10)->default('BORRADOR')->comment('BORRADOR o ENTREGADA');
            $t->timestamp('entregada_en')->nullable();
            $t->boolean('es_tardia')->default(false);
            $this->fechas($t);
            $t->unique(['evaluacion_id', 'matricula_id'], 'ux_entrega');
        });
        $this->check('entrega', 'ck_entrega_estado', "estado IN ('BORRADOR', 'ENTREGADA') AND (estado = 'BORRADOR' OR entregada_en IS NOT NULL)");

        Schema::create('nota_evaluacion', function (Blueprint $t) {
            $t->comment('Nota de cada estudiante en cada evaluación');
            $t->id();
            $t->foreignId('evaluacion_id')->constrained('evaluacion')->cascadeOnDelete();
            $t->foreignId('matricula_id')->constrained('matricula');
            $t->decimal('nota', 4, 2)->nullable()->comment('0 a 20; NULL = aún sin calificar');
            $t->string('estado_entrega', 15)->default('PENDIENTE')->comment('PENDIENTE, ENTREGADO, TARDIO o NO_ENTREGADO');
            $t->string('observacion', 300)->nullable();
            $t->foreignId('registrado_por')->nullable()->constrained('usuario');
            $this->fechas($t);
            $t->unique(['evaluacion_id', 'matricula_id'], 'ux_nota_evaluacion');
            $t->index('matricula_id', 'ix_nota_matricula');
        });
        $this->check('nota_evaluacion', 'ck_nota_rango', 'nota IS NULL OR nota BETWEEN 0 AND 20');
        $this->check('nota_evaluacion', 'ck_nota_estado_entrega', "estado_entrega IN ({$this->valores(['PENDIENTE', 'ENTREGADO', 'TARDIO', 'NO_ENTREGADO'])})");

        Schema::create('calificacion_periodo', function (Blueprint $t) {
            $t->comment('Promedio del estudiante por curso y bimestre');
            $t->id();
            $t->foreignId('matricula_id')->constrained('matricula');
            $t->foreignId('curso_aula_id')->constrained('curso_aula');
            $t->foreignId('subperiodo_id')->constrained('subperiodo');
            $t->decimal('promedio', 4, 2)->nullable();
            $t->string('estado', 15)->default('BORRADOR')->comment('BORRADOR, PUBLICADA o CERRADA');
            $t->timestamp('publicado_en')->nullable();
            $t->foreignId('publicado_por')->nullable()->constrained('usuario');
            $t->timestamp('cerrado_en')->nullable();
            $this->fechas($t);
            $t->unique(['matricula_id', 'curso_aula_id', 'subperiodo_id'], 'ux_calificacion');
        });
        $this->check('calificacion_periodo', 'ck_calificacion_promedio', 'promedio IS NULL OR promedio BETWEEN 0 AND 20');
        $this->check('calificacion_periodo', 'ck_calificacion_estado', "estado IN ({$this->valores(['BORRADOR', 'PUBLICADA', 'CERRADA'])})");

        Schema::create('sesion_clase', function (Blueprint $t) {
            $t->comment('Clase dictada en una fecha (una por curso y día)');
            $t->id();
            $t->foreignId('curso_aula_id')->constrained('curso_aula');
            $t->foreignId('subperiodo_id')->constrained('subperiodo');
            $t->date('fecha');
            $t->string('tema', 200)->nullable();
            $t->string('estado', 10)->default('BORRADOR')->comment('BORRADOR o DEFINITIVO');
            $t->foreignId('registrado_por')->nullable()->constrained('usuario');
            $this->fechas($t);
            $t->unique(['curso_aula_id', 'fecha'], 'ux_sesion_clase');
        });
        $this->check('sesion_clase', 'ck_sesion_estado', "estado IN ('BORRADOR', 'DEFINITIVO')");

        Schema::create('asistencia', function (Blueprint $t) {
            $t->comment('Asistencia de cada estudiante en cada clase');
            $t->id();
            $t->foreignId('sesion_clase_id')->constrained('sesion_clase')->cascadeOnDelete();
            $t->foreignId('matricula_id')->constrained('matricula');
            $t->char('estado', 1)->comment('P presente, F falta, T tardanza, J justificada');
            $t->string('observacion', 300)->nullable();
            $t->foreignId('registrado_por')->nullable()->constrained('usuario');
            $t->foreignId('modificado_por')->nullable()->constrained('usuario')->comment('Quién corrigió después del registro');
            $this->fechas($t);
            $t->unique(['sesion_clase_id', 'matricula_id'], 'ux_asistencia');
            $t->index('matricula_id', 'ix_asistencia_matricula');
        });
        $this->check('asistencia', 'ck_asistencia_estado', "estado IN ('P', 'F', 'T', 'J')");

        Schema::create('tipo_incidencia', function (Blueprint $t) {
            $t->comment('Tipos de incidencia');
            $t->id();
            $t->string('nombre', 60)->unique();
            $t->string('descripcion', 250)->nullable();
        });

        Schema::create('incidencia', function (Blueprint $t) {
            $t->comment('Incidencias registradas a un estudiante');
            $t->id();
            $t->foreignId('matricula_id')->constrained('matricula');
            $t->foreignId('tipo_incidencia_id')->constrained('tipo_incidencia');
            $t->foreignId('registrado_por')->constrained('usuario');
            $t->date('fecha');
            $t->string('gravedad', 10)->comment('LEVE, MODERADA o GRAVE');
            $t->string('descripcion', 500);
            $this->fechas($t);
            $t->index('matricula_id', 'ix_incidencia_matricula');
        });
        $this->check('incidencia', 'ck_incidencia_gravedad', "gravedad IN ({$this->valores(['LEVE', 'MODERADA', 'GRAVE'])})");
    }

    public function down(): void
    {
        foreach (['incidencia', 'tipo_incidencia', 'asistencia', 'sesion_clase', 'calificacion_periodo', 'nota_evaluacion', 'entrega', 'evaluacion_material', 'evaluacion', 'archivo', 'tipo_evaluacion'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
