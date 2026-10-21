<?php

namespace App\Support;

/**
 * PDF mínimo y propio (sin librerías): páginas A4, texto con Helvetica y rectángulos de color.
 * Las coordenadas se dan en puntos desde la esquina superior izquierda. El texto va en Windows-1252,
 * que cubre las tildes, la ñ y el "·" del castellano.
 */
class Pdf
{
    public const ANCHO = 595.28;

    public const ALTO = 841.89;

    /** Anchos de Helvetica (por 1000) para los caracteres 32 a 126 */
    private const ANCHOS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var list<string> */
    private array $paginas = [];

    public function pagina(): static
    {
        $this->paginas[] = '';

        return $this;
    }

    /** $alinear: izq, centro o der (respecto de $x) */
    public function texto(float $x, float $y, string $texto, float $tamano = 10, bool $negrita = false, string $color = '1F2937', string $alinear = 'izq'): static
    {
        $ancho = self::ancho($texto, $tamano, $negrita);
        $x = match ($alinear) {
            'centro' => $x - $ancho / 2,
            'der' => $x - $ancho,
            default => $x,
        };
        $fuente = $negrita ? 'F2' : 'F1';

        return $this->agregar(sprintf('BT /%s %s Tf %s rg %s %s Td (%s) Tj ET', $fuente, self::n($tamano), self::rgb($color), self::n($x), self::n(self::ALTO - $y), self::escapar($texto)));
    }

    public function rect(float $x, float $y, float $ancho, float $alto, ?string $relleno = null, ?string $borde = null, float $grosor = 0.6): static
    {
        $partes = [];
        if ($relleno) {
            $partes[] = self::rgb($relleno).' rg';
        }
        if ($borde) {
            $partes[] = self::rgb($borde).' RG '.self::n($grosor).' w';
        }
        $partes[] = sprintf('%s %s %s %s re %s', self::n($x), self::n(self::ALTO - $y - $alto), self::n($ancho), self::n($alto), $relleno && $borde ? 'B' : ($relleno ? 'f' : 'S'));

        return $this->agregar(implode(' ', $partes));
    }

    public function linea(float $x1, float $y1, float $x2, float $y2, string $color = 'D1D5DB', float $grosor = 0.6): static
    {
        return $this->agregar(sprintf('%s RG %s w %s %s m %s %s l S', self::rgb($color), self::n($grosor), self::n($x1), self::n(self::ALTO - $y1), self::n($x2), self::n(self::ALTO - $y2)));
    }

    /** Ancho aproximado del texto en puntos (las tildes miden como su letra base) */
    public static function ancho(string $texto, float $tamano, bool $negrita = false): float
    {
        $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $total = 0;
        foreach (str_split($base) as $c) {
            $codigo = ord($c);
            $total += $codigo >= 32 && $codigo <= 126 ? self::ANCHOS[$codigo - 32] : 556;
        }

        return $total * $tamano / 1000 * ($negrita ? 1.06 : 1);
    }

    public function salida(): string
    {
        $paginas = $this->paginas ?: [''];
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $hijos = [];
        foreach ($paginas as $i => $contenido) {
            $pagina = 5 + 2 * $i;
            $hijos[] = "{$pagina} 0 R";
            $objetos[$pagina] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::n(self::ANCHO), self::n(self::ALTO), $pagina + 1);
            $objetos[$pagina + 1] = '<< /Length '.strlen($contenido)." >>\nstream\n{$contenido}\nendstream";
        }
        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $hijos).'] /Count '.count($paginas).' >>';
        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $posiciones = [];
        foreach ($objetos as $n => $cuerpo) {
            $posiciones[$n] = strlen($pdf);
            $pdf .= "{$n} 0 obj\n{$cuerpo}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($posiciones as $p) {
            $pdf .= sprintf("%010d 00000 n \n", $p);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private function agregar(string $operacion): static
    {
        if (! $this->paginas) {
            $this->pagina();
        }
        $this->paginas[count($this->paginas) - 1] .= $operacion."\n";

        return $this;
    }

    /** Texto en Windows-1252 con los paréntesis y la barra escapados */
    public static function escapar(string $texto): string
    {
        $cp1252 = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $cp1252);
    }

    private static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return implode(' ', array_map(fn ($i) => self::n(hexdec(substr($hex, $i, 2)) / 255), [0, 2, 4]));
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.') ?: '0';
    }
}
