<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Estructura\InstitucionRequest;
use App\Support\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** CU-06: datos del colegio y parámetros (RF-26) */
class InstitucionController extends Controller
{
    use RespondeJson;

    public function show(): JsonResponse
    {
        $institucion = DB::table('institucion')->first();
        $escala = DB::table('escala_literal')->orderBy('orden')->get(['codigo', 'descripcion', 'nota_minima', 'nota_maxima']);

        return $this->json(['institucion' => Arr::except((array) $institucion, ['created_at', 'updated_at']), 'escala' => $escala]);
    }

    public function update(InstitucionRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $escala = Arr::pull($datos, 'escala');

        DB::transaction(function () use ($datos, $escala) {
            DB::table('institucion')->update($datos + ['updated_at' => now()]);
            foreach ($escala as $rango) {
                DB::table('escala_literal')->where('codigo', $rango['codigo'])
                    ->update(['nota_minima' => $rango['nota_minima'], 'nota_maxima' => $rango['nota_maxima']]);
            }
            Auditoria::registrar('ACTUALIZAR', 'institucion', DB::table('institucion')->value('id'), $datos + ['escala' => $escala]);
        });

        return $this->json(['mensaje' => 'Datos del colegio guardados.'] + $this->show()->getData(true));
    }
}
