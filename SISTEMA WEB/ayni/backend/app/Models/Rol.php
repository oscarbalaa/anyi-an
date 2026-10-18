<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    public const ADMIN = 'ADMIN';

    public const DOCENTE = 'DOCENTE';

    public const ESTUDIANTE = 'ESTUDIANTE';

    protected $table = 'rol';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }
}
