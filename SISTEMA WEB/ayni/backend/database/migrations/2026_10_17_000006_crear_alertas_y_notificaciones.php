<?php

use App\Database\Migracion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migracion
{
    public function up(): void
    {
        Schema::create('modelo_ml', function (Blueprint $t) {
            $t->comment('Modelo que calcula el riesgo: reglas-v0 (Unidad 1) o ML (Unidad 2)');
            $t->id();
            $t->string('nombre', 80);
            $t->string('version', 30);
            $t->string('algoritmo', 60)->nullable();
            $t->json('metricas')->nullable();
            $t->timestamp('fecha_entrenamiento')->nullable();
            $t->boolean('activo')->default(false);
            $t->unsignedTinyInteger('activo_unico')->storedAs('IF(activo, 1, NULL)')->nullable()->comment('Calculada: un solo modelo activo');
            $t->unique(['nombre', 'version'], 'ux_modelo_version');
            $t->unique('activo_unico', 'ux_modelo_activo');
        });

        Schema::create('prediccion_riesgo', function (Blueprint $t) {
            $t->comment('Nivel de riesgo calculado para un estudiante en un bimestre');
            $t->id();
            $t->foreignId('matricula_id')->constrained('matricula');
            $t->foreignId('subperiodo_id')->constrained('subperiodo');
            $t->foreignId('modelo_id')->constrained('modelo_ml');
            $t->timestamp('fecha')->useCurrent();
            $t->decimal('probabilidad', 5, 4)->comment('0 a 1');
            $t->string('nivel_riesgo', 10)->comment('BAJO, MEDIO o ALTO');
            $t->json('features')->comment('Valores que vio el modelo');
            $t->text('resumen_texto')->nullable();
            $t->index(['matricula_id', 'fecha'], 'ix_prediccion_matricula');
            $t->index(['subperiodo_id', 'nivel_riesgo'], 'ix_prediccion_nivel');
        });
        $this->check('prediccion_riesgo', 'ck_prediccion_probabilidad', 'probabilidad BETWEEN 0 AND 1');
        $this->check('prediccion_riesgo', 'ck_prediccion_nivel', "nivel_riesgo IN ('BAJO', 'MEDIO', 'ALTO')");

        Schema::create('factor_riesgo', function (Blueprint $t) {
            $t->comment('Factores que explican la predicción (reglas activadas o valores SHAP)');
            $t->id();
            $t->foreignId('prediccion_id')->constrained('prediccion_riesgo')->cascadeOnDelete();
            $t->string('variable', 80);
            $t->string('valor', 80)->nullable();
            $t->decimal('contribucion', 8, 4)->comment('Positiva sube el riesgo; negativa lo baja');
            $t->string('descripcion', 300)->nullable();
            $t->index('prediccion_id', 'ix_factor_prediccion');
        });

        Schema::create('recomendacion', function (Blueprint $t) {
            $t->comment('Recomendaciones de intervención de una predicción');
            $t->id();
            $t->foreignId('prediccion_id')->constrained('prediccion_riesgo')->cascadeOnDelete();
            $t->string('descripcion', 400);
            $t->unsignedTinyInteger('prioridad')->default(2)->comment('1 alta, 2 media, 3 baja');
        });
        $this->check('recomendacion', 'ck_recomendacion_prioridad', 'prioridad BETWEEN 1 AND 3');

        Schema::create('alerta_estudiantil', function (Blueprint $t) {
            $t->comment('Alerta de riesgo académico con seguimiento');
            $t->id();
            $t->foreignId('prediccion_id')->unique()->constrained('prediccion_riesgo');
            $t->foreignId('matricula_id')->constrained('matricula');
            $t->foreignId('subperiodo_id')->constrained('subperiodo');
            $t->foreignId('responsable_docente_id')->constrained('docente');
            $t->string('estado', 15)->default('NUEVA')->comment('NUEVA, EN_SEGUIMIENTO, CERRADA o DESCARTADA');
            $t->string('justificacion_cambio', 400)->nullable();
            $t->timestamp('fecha_creacion')->useCurrent();
            $t->timestamp('fecha_cierre')->nullable();
            $t->unsignedTinyInteger('activa_unica')->storedAs("IF(estado IN ('NUEVA', 'EN_SEGUIMIENTO'), 1, NULL)")->nullable()->comment('Calculada: una alerta activa por estudiante y bimestre');
            $t->unique(['matricula_id', 'subperiodo_id', 'activa_unica'], 'ux_alerta_activa');
            $t->index(['responsable_docente_id', 'estado'], 'ix_alerta_responsable');
        });
        $this->check('alerta_estudiantil', 'ck_alerta_estado', "estado IN ({$this->valores(['NUEVA', 'EN_SEGUIMIENTO', 'CERRADA', 'DESCARTADA'])})");
        $this->check('alerta_estudiantil', 'ck_alerta_cierre', 'fecha_cierre IS NULL OR fecha_cierre >= fecha_creacion');

        Schema::create('intervencion', function (Blueprint $t) {
            $t->comment('Acciones realizadas para atender una alerta');
            $t->id();
            $t->foreignId('alerta_id')->constrained('alerta_estudiantil')->cascadeOnDelete();
            $t->foreignId('responsable_id')->constrained('usuario');
            $t->string('tipo', 60);
            $t->string('descripcion', 500)->nullable();
            $t->date('fecha');
            $t->string('resultado', 300)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('historial_alerta', function (Blueprint $t) {
            $t->comment('Historial de cambios de una alerta');
            $t->id();
            $t->foreignId('alerta_id')->constrained('alerta_estudiantil')->cascadeOnDelete();
            $t->string('accion', 30);
            $t->string('detalle', 400)->nullable();
            $t->foreignId('usuario_id')->nullable()->constrained('usuario')->comment('NULL = acción del sistema');
            $t->timestamp('fecha')->useCurrent();
            $t->index(['alerta_id', 'fecha'], 'ix_historial_alerta');
        });
        $this->check('historial_alerta', 'ck_historial_accion', "accion IN ({$this->valores(['GENERADA', 'ASIGNADA_DOCENTE_AULA', 'REASIGNADA', 'NIVEL_ACTUALIZADO', 'INTERVENCION', 'ESTADO_CAMBIADO', 'CERRADA'])})");

        Schema::create('alerta_administrativa', function (Blueprint $t) {
            $t->comment('Avisos operativos para el administrador');
            $t->id();
            $t->string('tipo', 40)->comment('USUARIO_BLOQUEADO, ESTUDIANTE_SIN_MATRICULA, DOCENTE_SIN_ASISTENCIA, CURSO_SIN_DOCENTE, AULA_SIN_TUTOR, NOTAS_PENDIENTES_CIERRE');
            $t->string('severidad', 10)->comment('CRITICO, ATENCION o PENDIENTE');
            $t->string('titulo', 150);
            $t->string('detalle', 300)->nullable();
            $t->string('entidad', 60)->nullable();
            $t->unsignedBigInteger('entidad_id')->nullable();
            $t->string('clave', 120)->comment('Evita duplicar la misma alerta abierta');
            $t->string('estado', 10)->default('ABIERTA')->comment('ABIERTA o RESUELTA');
            $t->unsignedTinyInteger('abierta_unica')->storedAs("IF(estado = 'ABIERTA', 1, NULL)")->nullable();
            $t->timestamp('resuelta_en')->nullable();
            $this->fechas($t);
            $t->unique(['clave', 'abierta_unica'], 'ux_alerta_admin_abierta');
        });
        $this->check('alerta_administrativa', 'ck_alerta_admin_tipo', "tipo IN ({$this->valores(['USUARIO_BLOQUEADO', 'ESTUDIANTE_SIN_MATRICULA', 'DOCENTE_SIN_ASISTENCIA', 'CURSO_SIN_DOCENTE', 'AULA_SIN_TUTOR', 'NOTAS_PENDIENTES_CIERRE'])})");
        $this->check('alerta_administrativa', 'ck_alerta_admin_severidad', "severidad IN ('CRITICO', 'ATENCION', 'PENDIENTE')");
        $this->check('alerta_administrativa', 'ck_alerta_admin_estado', "estado IN ('ABIERTA', 'RESUELTA')");

        Schema::create('notificacion', function (Blueprint $t) {
            $t->comment('Notificaciones de la campana');
            $t->id();
            $t->foreignId('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $t->string('tipo', 40)->comment('NOTA_PUBLICADA, FALTA_REGISTRADA, ACTIVIDAD_POR_VENCER, ALERTA_ASIGNADA, CIERRE_PROXIMO…');
            $t->string('titulo', 150);
            $t->string('mensaje', 400);
            $t->string('enlace', 255)->nullable()->comment('Pantalla a la que lleva');
            $t->timestamp('leida_en')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['usuario_id', 'leida_en'], 'ix_notificacion_usuario');
        });
    }

    public function down(): void
    {
        foreach (['notificacion', 'alerta_administrativa', 'historial_alerta', 'intervencion', 'alerta_estudiantil', 'recomendacion', 'factor_riesgo', 'prediccion_riesgo', 'modelo_ml'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
