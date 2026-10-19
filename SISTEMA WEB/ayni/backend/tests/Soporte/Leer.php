<?php

namespace Tests\Soporte;

use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use ZipArchive;

/** Lee los archivos que descarga la API para revisar su contenido */
final class Leer
{
    /** Filas de la primera hoja del .xlsx descargado */
    public static function excel(TestResponse $respuesta): array
    {
        $lector = new Reader;
        $lector->open($respuesta->baseResponse->getFile()->getPathname());
        $filas = [];
        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = $fila->toArray();
            }
            break;
        }
        $lector->close();

        return $filas;
    }

    /** XML de la primera hoja del .xlsx (para revisar el tipo de cada celda) */
    public static function xmlExcel(TestResponse $respuesta): string
    {
        $zip = new ZipArchive;
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        return $xml;
    }

    /** El PDF en el mismo texto que lleva dentro (Windows-1252) */
    public static function textoPdf(string $texto): string
    {
        return mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    }
}
