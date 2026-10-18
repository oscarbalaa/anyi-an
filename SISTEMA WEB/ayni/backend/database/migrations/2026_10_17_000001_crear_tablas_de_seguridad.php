<?php

use App\Database\Migracion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migracion
{
    public function up(): void
    {
        Schema::create('rol', function (Blueprint $t) {
            $t->comment('Roles de acceso. El coordinador es un docente con ámbito de coordinación.');
            $t->id();
            $t->string('codigo', 20)->unique()->comment('ADMIN, DOCENTE o ESTUDIANTE');
            $t->string('nombre', 60);
        });
        $this->check('rol', 'ck_rol_codigo', "codigo IN ({$this->valores(['ADMIN', 'DOCENTE', 'ESTUDIANTE'])})");

        Schema::create('usuario', function (Blueprint $t) {
            $t->comment('Cuentas de acceso al sistema');
            $t->id();
            $t->foreignId('rol_id')->constrained('rol');
            $t->string('username', 60)->unique()->comment('Admin: nombre de usuario; docente: DNI; estudiante: código');
            $t->string('email', 120)->nullable()->unique()->comment('Correo para recuperar la contraseña');
            $t->string('password_hash')->comment('Contraseña cifrada con bcrypt');
            $t->string('estado', 15)->default('ACTIVO')->comment('ACTIVO, INACTIVO o BLOQUEADO');
            $t->unsignedTinyInteger('intentos_fallidos')->default(0)->comment('Intentos de ingreso fallidos seguidos');
            $t->timestamp('bloqueado_hasta')->nullable()->comment('Fin del bloqueo temporal; NULL con estado BLOQUEADO = hasta que el admin desbloquee');
            $t->boolean('debe_cambiar_password')->default(false)->comment('Contraseña temporal: debe cambiarla al ingresar');
            $t->timestamp('ultimo_acceso')->nullable();
            $t->foreignId('creado_por')->nullable()->constrained('usuario');
            $t->foreignId('actualizado_por')->nullable()->constrained('usuario');
            $this->fechas($t);
            $t->index('rol_id', 'ix_usuario_rol');
        });
        $this->check('usuario', 'ck_usuario_estado', "estado IN ({$this->valores(['ACTIVO', 'INACTIVO', 'BLOQUEADO'])})");

        Schema::create('auditoria', function (Blueprint $t) {
            $t->comment('Registro de quién cambió qué y cuándo');
            $t->id();
            $t->foreignId('usuario_id')->nullable()->constrained('usuario')->comment('NULL = acción del sistema');
            $t->string('accion', 40)->comment('CREAR, ACTUALIZAR, ELIMINAR, DESBLOQUEAR, CERRAR_BIMESTRE…');
            $t->string('entidad', 60)->comment('Tabla afectada');
            $t->unsignedBigInteger('entidad_id')->nullable();
            $t->json('detalle')->nullable()->comment('Valores antes/después');
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['entidad', 'entidad_id'], 'ix_auditoria_entidad');
        });

        Schema::create('recuperacion_password', function (Blueprint $t) {
            $t->comment('Enlaces de recuperación de contraseña (un solo uso, 30 minutos)');
            $t->id();
            $t->foreignId('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $t->char('token_hash', 64)->unique()->comment('SHA-256 del token enviado por correo');
            $t->timestamp('expira_en');
            $t->timestamp('usado_en')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('captcha_desafio', function (Blueprint $t) {
            $t->comment('Captchas propios del login (un solo uso, 5 minutos)');
            $t->uuid('id')->primary();
            $t->string('respuesta_hash')->comment('Respuesta cifrada; nunca se guarda en claro');
            $t->timestamp('expira_en');
            $t->timestamp('usado_en')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index('expira_en', 'ix_captcha_expira');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captcha_desafio');
        Schema::dropIfExists('recuperacion_password');
        Schema::dropIfExists('auditoria');
        Schema::dropIfExists('usuario');
        Schema::dropIfExists('rol');
    }
};
