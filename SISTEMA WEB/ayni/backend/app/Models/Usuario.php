<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuario';

    protected $fillable = [
        'rol_id', 'username', 'email', 'password_hash', 'estado',
        'intentos_fallidos', 'bloqueado_hasta', 'debe_cambiar_password',
        'ultimo_acceso', 'creado_por', 'actualizado_por',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'bloqueado_hasta' => 'datetime',
        'ultimo_acceso' => 'datetime',
        'debe_cambiar_password' => 'boolean',
        'intentos_fallidos' => 'integer',
    ];

    // La columna se llama password_hash (script original), no password
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }
}
