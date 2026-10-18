<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait RespondeJson
{
    protected function json(array $datos, int $estado = 200): JsonResponse
    {
        return response()->json($datos, $estado, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
