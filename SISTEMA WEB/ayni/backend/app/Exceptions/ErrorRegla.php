<?php

namespace App\Exceptions;

/** Una regla de negocio impide la operación (RN-xx); responde 422 con el mensaje */
class ErrorRegla extends ErrorAcceso
{
    public function __construct(string $mensaje, ?string $campo = null)
    {
        parent::__construct($mensaje, 422, $campo ? ['errores' => [$campo => [$mensaje]]] : []);
    }
}
