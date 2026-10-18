<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class SaludController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $bd = $this->estadoBaseDeDatos();

        $datos = [
            'estado' => $bd['conectada'] ? 'ok' : 'sin_base_de_datos',
            'aplicacion' => config('app.name'),
            'version' => config('ayni.version'),
            'colegio' => config('ayni.colegio'),
            'hora_servidor' => now()->toIso8601String(),
            'zona_horaria' => config('app.timezone'),
            'base_de_datos' => ['conectada' => $bd['conectada']],
        ];

        // En producción no se exponen versiones del servidor
        if (! app()->isProduction()) {
            $datos['entorno'] = app()->environment();
            $datos['php'] = PHP_VERSION;
            $datos['laravel'] = app()->version();
            $datos['base_de_datos'] += array_filter([
                'motor' => $bd['motor'] ?? null,
                'version' => $bd['version'] ?? null,
            ]);
        }

        return response()->json($datos, $bd['conectada'] ? 200 : 503, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function estadoBaseDeDatos(): array
    {
        try {
            $pdo = DB::connection()->getPdo();
            $version = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

            return [
                'conectada' => true,
                'motor' => str_contains(strtolower($version), 'mariadb') ? 'MariaDB' : 'MySQL',
                'version' => $version,
            ];
        } catch (Throwable) {
            return ['conectada' => false];
        }
    }
}
