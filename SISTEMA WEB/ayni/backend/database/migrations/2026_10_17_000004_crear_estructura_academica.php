<?php

use App\Database\Migracion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migracion
{
    public function up(): void
    {
        Schema::create('periodo_academico', function (Blueprint $t) {
            $t->comment('Año escolar');
            $t->id();
            $t->string('nombre', 60)->unique()->comment('Ej. 2026');
            $t->date('fecha_inicio');
            $t->date('fecha_fin');
            $t->string('estado', 15)->default('PLANIFICADO')->comment('PLANIFICADO, EN_CURSO o CERRADO');
            $t->unsignedTinyInteger('en_curso_unico')->storedAs("IF(estado = 'EN_CURSO', 1, NULL)")->nullable()->comment('Calculada: garantiza un solo periodo en curso');
            $this->fechas($t);
            $t->unique('en_curso_unico', 'ux_periodo_en_curso');
        });
        $this->check('periodo_academico', 'ck_periodo_estado', "estado IN ({$this->valores(['PLANIFICADO', 'EN_CURSO', 'CERRADO'])})");
        $this->check('periodo_academico', 'ck_periodo_fechas', 'fecha_fin > fecha_inicio');

        Schema::create('subperiodo', function (Blueprint $t) {
            $t->comment('Bimestres del periodo');
            $t->id();
            $t->foreignId('periodo_id')->constrained('periodo_academico')->cascadeOnDelete();
            $t->unsignedTinyInteger('numero')->comment('1 a 4');
            $t->string('nombre', 40)->comment('Ej. III Bimestre');
            $t->date('fecha_inicio');
            $t->date('fecha_fin');
            $t->date('fecha_cierre_notas')->comment('Al llegar esta fecha el bimestre se cierra solo');
            $t->string('estado', 10)->default('ABIERTO')->comment('ABIERTO o CERRADO');
            $t->timestamp('cerrado_en')->nullable();
            $t->foreignId('cerrado_por')->nullable()->constrained('usuario')->comment('NULL con estado CERRADO = cierre automático');
            $t->unique(['periodo_id', 'numero'], 'ux_subperiodo_numero');
        });
        $this->check('subperiodo', 'ck_subperiodo_numero', 'numero BETWEEN 1 AND 4');
        $this->check('subperiodo', 'ck_subperiodo_fechas', 'fecha_fin > fecha_inicio AND fecha_cierre_notas >= fecha_inicio');
        $this->check('subperiodo', 'ck_subperiodo_estado', "estado IN ('ABIERTO', 'CERRADO')");

        Schema::create('grado', function (Blueprint $t) {
            $t->comment('Grados de primaria (1.º-6.º) y secundaria (1.º-5.º)');
            $t->id();
            $t->string('nivel', 15)->comment('PRIMARIA o SECUNDARIA');
            $t->unsignedTinyInteger('numero');
            $t->string('nombre', 40)->comment('Ej. Tercero');
            $t->unsignedTinyInteger('orden');
            $t->unique(['nivel', 'numero'], 'ux_grado_nivel_numero');
        });
        $this->check('grado', 'ck_grado_nivel', "nivel IN ('PRIMARIA', 'SECUNDARIA')");
        $this->check('grado', 'ck_grado_numero', "numero >= 1 AND ((nivel = 'PRIMARIA' AND numero <= 6) OR (nivel = 'SECUNDARIA' AND numero <= 5))");

        Schema::create('seccion', function (Blueprint $t) {
            $t->comment('Secciones: "Única" o letras A, B, C…');
            $t->id();
            $t->string('nombre', 10)->unique();
        });

        Schema::create('aula', function (Blueprint $t) {
            $t->comment('Grado + sección en un periodo');
            $t->id();
            $t->foreignId('periodo_id')->constrained('periodo_academico');
            $t->foreignId('grado_id')->constrained('grado');
            $t->foreignId('seccion_id')->constrained('seccion');
            $t->foreignId('tutor_docente_id')->nullable()->constrained('docente')->comment('Tutor(a) del aula');
            $t->unsignedSmallInteger('capacidad')->default(30);
            $t->string('ambiente', 40)->nullable()->comment('Ej. Aula 12');
            $t->unique(['periodo_id', 'grado_id', 'seccion_id'], 'ux_aula_grado_seccion');
            $t->unique(['id', 'periodo_id'], 'ux_aula_periodo');
        });
        $this->check('aula', 'ck_aula_capacidad', 'capacidad BETWEEN 1 AND 60');

        Schema::create('coordinacion', function (Blueprint $t) {
            $t->comment('Docentes que coordinan un aula, un grado, un nivel o todo el colegio');
            $t->id();
            $t->foreignId('docente_id')->constrained('docente');
            $t->foreignId('periodo_id')->constrained('periodo_academico');
            $t->string('ambito', 15)->comment('AULA, GRADO, NIVEL o INSTITUCION');
            $t->foreignId('aula_id')->nullable()->constrained('aula');
            $t->foreignId('grado_id')->nullable()->constrained('grado');
            $t->string('nivel', 15)->nullable();
            $this->fechas($t);
            $t->index(['docente_id', 'periodo_id'], 'ix_coordinacion_docente');
        });
        $this->check('coordinacion', 'ck_coordinacion_ambito', "
            (ambito = 'AULA' AND aula_id IS NOT NULL AND grado_id IS NULL AND nivel IS NULL)
            OR (ambito = 'GRADO' AND grado_id IS NOT NULL AND aula_id IS NULL AND nivel IS NULL)
            OR (ambito = 'NIVEL' AND nivel IN ('PRIMARIA', 'SECUNDARIA') AND aula_id IS NULL AND grado_id IS NULL)
            OR (ambito = 'INSTITUCION' AND aula_id IS NULL AND grado_id IS NULL AND nivel IS NULL)");

        Schema::create('matricula', function (Blueprint $t) {
            $t->comment('Matrícula del estudiante en un aula del periodo (una por año)');
            $t->id();
            $t->foreignId('estudiante_id')->constrained('estudiante');
            $t->unsignedBigInteger('aula_id');
            $t->unsignedBigInteger('periodo_id');
            $t->date('fecha');
            $t->string('estado', 10)->default('ACTIVA')->comment('ACTIVA, RETIRADA o ANULADA');
            $t->string('motivo', 300)->nullable()->comment('Motivo del retiro o la anulación');
            $t->foreignId('registrado_por')->nullable()->constrained('usuario');
            $this->fechas($t);
            $t->unique(['estudiante_id', 'periodo_id'], 'ux_matricula_estudiante_periodo');
            $t->index('aula_id', 'ix_matricula_aula');
            // El periodo de la matrícula debe ser el del aula
            $t->foreign(['aula_id', 'periodo_id'], 'fk_matricula_aula_periodo')->references(['id', 'periodo_id'])->on('aula');
            $t->foreign('periodo_id')->references('id')->on('periodo_academico');
        });
        $this->check('matricula', 'ck_matricula_estado', "estado IN ({$this->valores(['ACTIVA', 'RETIRADA', 'ANULADA'])})");

        Schema::create('asignatura', function (Blueprint $t) {
            $t->comment('Asignaturas o cursos');
            $t->id();
            $t->string('codigo', 20)->nullable()->unique();
            $t->string('nombre', 100);
            $t->unsignedSmallInteger('horas_semanales')->nullable();
            $t->string('estado', 10)->default('ACTIVO');
        });
        $this->check('asignatura', 'ck_asignatura_horas', 'horas_semanales IS NULL OR horas_semanales BETWEEN 1 AND 20');
        $this->check('asignatura', 'ck_asignatura_estado', "estado IN ('ACTIVO', 'INACTIVO')");

        Schema::create('curso_aula', function (Blueprint $t) {
            $t->comment('Carga académica: asignatura dictada en un aula por un docente');
            $t->id();
            $t->foreignId('aula_id')->constrained('aula');
            $t->foreignId('asignatura_id')->constrained('asignatura');
            $t->foreignId('docente_id')->constrained('docente');
            $t->unique(['aula_id', 'asignatura_id'], 'ux_curso_aula');
            $t->index('docente_id', 'ix_curso_aula_docente');
        });

        Schema::create('horario', function (Blueprint $t) {
            $t->comment('Bloques semanales de cada curso');
            $t->id();
            $t->foreignId('curso_aula_id')->constrained('curso_aula')->cascadeOnDelete();
            $t->unsignedTinyInteger('dia_semana')->comment('1 = lunes … 7 = domingo');
            $t->time('hora_inicio');
            $t->time('hora_fin');
            $t->string('ambiente', 40)->nullable();
        });
        $this->check('horario', 'ck_horario_dia', 'dia_semana BETWEEN 1 AND 7');
        $this->check('horario', 'ck_horario_horas', 'hora_fin > hora_inicio');
    }

    public function down(): void
    {
        foreach (['horario', 'curso_aula', 'asignatura', 'matricula', 'coordinacion', 'aula', 'seccion', 'grado', 'subperiodo', 'periodo_academico'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
