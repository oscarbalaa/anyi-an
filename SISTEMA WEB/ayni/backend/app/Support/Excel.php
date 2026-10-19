<?php

namespace App\Support;

use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/** Planillas .xlsx que descarga el docente (las notas van como números, para que Excel las sume) */
class Excel
{
    public const TIPO = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** Escribe un .xlsx temporal y devuelve su ruta; la primera fila es el encabezado */
    public static function escribir(array $filas, array $anchos): string
    {
        $carpeta = storage_path('app/tmp');
        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }
        $ruta = $carpeta.'/'.Str::random(20).'.xlsx';
        $opciones = new Options;
        foreach ($anchos as $i => $ancho) {
            $opciones->setColumnWidth($ancho, $i + 1);
        }
        $escritor = new Writer($opciones);
        $escritor->openToFile($ruta);
        $encabezado = (new Style)->setFontBold()->setBackgroundColor('E2F3F1');
        foreach ($filas as $i => $fila) {
            $escritor->addRow(self::fila($fila, $i === 0 ? $encabezado : null));
        }
        $escritor->close();

        return $ruta;
    }

    /** El texto siempre va como texto: un valor que empieza con "=" no se vuelve una fórmula (inyección en planillas) */
    public static function fila(array $valores, ?Style $estilo = null): Row
    {
        return new Row(array_map(fn ($v) => is_string($v) && $v !== '' ? new StringCell($v, null) : Cell::fromValue($v ?? ''), $valores), $estilo);
    }

    /** "notas Práctica 7 · Inecuaciones 3.º B" → "notas_practica_7_inecuaciones_3_b.xlsx" */
    public static function nombreArchivo(string $texto): string
    {
        return Str::slug(str_replace(['.º', '·'], ['', ' '], $texto), '_').'.xlsx';
    }
}
