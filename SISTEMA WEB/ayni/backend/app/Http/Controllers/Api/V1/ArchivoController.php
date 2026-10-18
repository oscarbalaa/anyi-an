<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Servicios\Archivos;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Descarga de entregas y material de apoyo, solo con permiso */
class ArchivoController extends Controller
{
    public function __invoke(Request $request, Archivos $archivos, int $archivo): StreamedResponse
    {
        return $archivos->descargar($archivo, $request->user()->load('rol'));
    }
}
