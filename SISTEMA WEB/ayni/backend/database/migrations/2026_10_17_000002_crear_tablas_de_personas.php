<?php

use App\Database\Migracion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migracion
{
    public function up(): void
    {
        Schema::create('persona', function (Blueprint $t) {
            $t->comment('Datos personales comunes de administrativos, docentes y estudiantes');
            $t->id();
            $t->string('tipo_documento', 15)->default('DNI')->comment('DNI, CE o PASAPORTE');
            $t->string('numero_documento', 20);
            $t->string('nombres', 100);
            $t->string('apellidos', 100);
            $t->date('fecha_nacimiento')->nullable();
            $t->char('sexo', 1)->nullable()->comment('M o F');
            $t->string('celular', 20)->nullable();
            $t->string('correo', 120)->nullable();
            $t->string('direccion', 200)->nullable();
            $this->fechas($t);
            $t->unique(['tipo_documento', 'numero_documento'], 'ux_persona_documento');
            $t->index(['apellidos', 'nombres'], 'ix_persona_nombre');
        });
        $this->check('persona', 'ck_persona_tipo_documento', "tipo_documento IN ({$this->valores(['DNI', 'CE', 'PASAPORTE'])})");
        $this->check('persona', 'ck_persona_sexo', "sexo IS NULL OR sexo IN ('M', 'F')");

        Schema::create('personal_administrativo', function (Blueprint $t) {
            $t->comment('Personal administrativo (rol ADMIN)');
            $t->id();
            $t->foreignId('persona_id')->unique()->constrained('persona');
            $t->foreignId('usuario_id')->unique()->constrained('usuario');
            $t->string('cargo', 80)->nullable();
            $t->string('estado', 10)->default('ACTIVO');
        });
        $this->check('personal_administrativo', 'ck_administrativo_estado', "estado IN ('ACTIVO', 'INACTIVO')");

        Schema::create('docente', function (Blueprint $t) {
            $t->comment('Docentes (rol DOCENTE; algunos también coordinan)');
            $t->id();
            $t->foreignId('persona_id')->unique()->constrained('persona');
            $t->foreignId('usuario_id')->unique()->constrained('usuario');
            $t->string('titulo', 120)->nullable();
            $t->string('especialidad', 100)->nullable();
            $t->date('fecha_inicio_contrato')->nullable();
            $t->date('fecha_fin_contrato')->nullable();
            $t->string('estado', 10)->default('ACTIVO')->comment('ACTIVO, INACTIVO o LICENCIA');
        });
        $this->check('docente', 'ck_docente_estado', "estado IN ({$this->valores(['ACTIVO', 'INACTIVO', 'LICENCIA'])})");
        $this->check('docente', 'ck_docente_contrato', 'fecha_fin_contrato IS NULL OR fecha_fin_contrato >= fecha_inicio_contrato');

        Schema::create('estudiante', function (Blueprint $t) {
            $t->comment('Estudiantes (rol ESTUDIANTE)');
            $t->id();
            $t->foreignId('persona_id')->unique()->constrained('persona');
            $t->foreignId('usuario_id')->unique()->constrained('usuario');
            $t->string('codigo', 30)->unique()->comment('E-AAAA-NNN; también es su usuario');
            $t->date('fecha_ingreso')->nullable();
            $t->string('estado', 15)->default('ACTIVO')->comment('ACTIVO, RETIRADO, TRASLADADO o EGRESADO');
        });
        $this->check('estudiante', 'ck_estudiante_estado', "estado IN ({$this->valores(['ACTIVO', 'RETIRADO', 'TRASLADADO', 'EGRESADO'])})");

        Schema::create('apoderado', function (Blueprint $t) {
            $t->comment('Apoderados del estudiante (sin acceso al sistema en la Unidad 1)');
            $t->id();
            $t->foreignId('estudiante_id')->constrained('estudiante')->cascadeOnDelete();
            $t->string('nombres', 150)->comment('Nombre completo');
            $t->string('celular', 20)->nullable();
            $t->string('correo', 120)->nullable();
            $t->string('parentesco', 40)->nullable()->comment('Padre, Madre, Tutor legal, Abuelo(a), Tío(a), Hermano(a), Otro');
            $t->boolean('es_principal')->default(false);
            // Solo un principal por estudiante: el índice único ignora los NULL
            $t->unsignedTinyInteger('principal_unico')->storedAs('IF(es_principal, 1, NULL)')->nullable()->comment('Calculada: 1 si es principal');
            $t->unique(['estudiante_id', 'principal_unico'], 'ux_apoderado_principal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apoderado');
        Schema::dropIfExists('estudiante');
        Schema::dropIfExists('docente');
        Schema::dropIfExists('personal_administrativo');
        Schema::dropIfExists('persona');
    }
};
