<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Respuesta de error del ingreso con su código HTTP y datos para la pantalla */
class ErrorAcceso extends RuntimeException
{
    public function __construct(string $mensaje, private readonly int $estado, private readonly array $extra = [])
    {
        parent::__construct($mensaje);
    }

    public function render(): JsonResponse
    {
        return response()->json(['mensaje' => $this->getMessage()] + $this->extra, $this->estado, [], JSON_UNESCAPED_UNICODE);
    }
}
