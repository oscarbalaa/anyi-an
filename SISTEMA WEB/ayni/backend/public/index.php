<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// El .htaccess de la raíz manda todo a backend/public. Para que Laravel vea las rutas
// como /api/... (y no /grupo02/backend/public/...) se ajusta la base de la URL.
$script = $_SERVER['SCRIPT_NAME'] ?? '';
if (str_ends_with($script, '/backend/public/index.php')) {
    $base = substr($script, 0, -strlen('/backend/public/index.php'));
    $_SERVER['SCRIPT_NAME'] = $base.'/index.php';
    $_SERVER['PHP_SELF'] = $base.'/index.php';
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
