<?php

namespace App\Servicios;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Captcha propio (D9): imagen con caracteres distorsionados, un solo uso, 5 minutos.
 * La respuesta nunca se guarda en claro.
 */
class Captcha
{
    /** @return array{id: string, imagen: string} */
    public function generar(?string $ip = null): array
    {
        $texto = $this->texto();
        $id = (string) Str::uuid();

        DB::table('captcha_desafio')->insert([
            'id' => $id,
            'respuesta_hash' => $this->huella($texto),
            'expira_en' => now()->addMinutes(config('ayni.captcha.minutos')),
            'ip' => $ip,
        ]);

        // De vez en cuando se limpian los vencidos
        if (random_int(1, 50) === 1) {
            DB::table('captcha_desafio')->where('expira_en', '<', now()->subDay())->delete();
        }

        return ['id' => $id, 'imagen' => $this->imagen($texto)];
    }

    /** RN-18: se consume al verificarlo, sea correcto o no */
    public function verificar(?string $id, ?string $respuesta): bool
    {
        if (! $id || ! $respuesta || ! Str::isUuid($id)) {
            return false;
        }

        $desafio = DB::table('captcha_desafio')->where('id', $id)->first();
        if (! $desafio || $desafio->usado_en || now()->gt($desafio->expira_en)) {
            return false;
        }

        DB::table('captcha_desafio')->where('id', $id)->update(['usado_en' => now()]);

        return hash_equals($desafio->respuesta_hash, $this->huella($respuesta));
    }

    /** Las pruebas E2E envían un secreto en vez de resolver la imagen */
    public function verificarSolicitud(Request $request): bool
    {
        $secreto = (string) config('ayni.secreto_e2e');
        if ($secreto !== '' && $this->secretoVigente($secreto) && hash_equals($secreto, (string) $request->header('X-Ayni-E2E'))) {
            return true;
        }

        return $this->verificar($request->input('captcha_id'), $request->input('captcha'));
    }

    /**
     * En local siempre. En el hosting solo durante la prueba de humo de publicar-hosting.ps1:
     * un secreto largo con vencimiento (AYNI_SECRETO_E2E_HASTA) de como máximo 2 horas.
     */
    private function secretoVigente(string $secreto): bool
    {
        if (! app()->isProduction()) {
            return true;
        }
        $hasta = (int) config('ayni.secreto_e2e_hasta');

        return strlen($secreto) >= 32 && $hasta > time() && $hasta - time() <= 7200;
    }

    private function texto(): string
    {
        $caracteres = config('ayni.captcha.caracteres');
        $texto = '';
        for ($i = 0; $i < config('ayni.captcha.longitud'); $i++) {
            $texto .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }

        return $texto;
    }

    private function huella(string $texto): string
    {
        return hash_hmac('sha256', strtoupper(trim($texto)), (string) config('app.key'));
    }

    private function imagen(string $texto): string
    {
        $fuente = config('ayni.captcha.fuente');
        if (function_exists('imagettftext') && is_file($fuente)) {
            return $this->png($texto, $fuente);
        }

        return $this->svg($texto);
    }

    private function png(string $texto, string $fuente): string
    {
        [$ancho, $alto] = [200, 64];
        $img = imagecreatetruecolor($ancho, $alto);
        imagefill($img, 0, 0, imagecolorallocate($img, 253, 247, 240));

        for ($i = 0; $i < 7; $i++) {
            $color = imagecolorallocate($img, random_int(150, 210), random_int(120, 190), random_int(110, 180));
            imagesetthickness($img, random_int(1, 2));
            imageline($img, random_int(0, $ancho), random_int(0, $alto), random_int(0, $ancho), random_int(0, $alto), $color);
        }
        for ($i = 0; $i < 350; $i++) {
            $color = imagecolorallocate($img, random_int(120, 220), random_int(100, 200), random_int(90, 190));
            imagesetpixel($img, random_int(0, $ancho - 1), random_int(0, $alto - 1), $color);
        }

        $x = 14;
        foreach (str_split($texto) as $letra) {
            $color = imagecolorallocate($img, random_int(30, 110), random_int(30, 80), random_int(30, 80));
            imagettftext($img, random_int(24, 30), random_int(-22, 22), $x, random_int(42, 52), $color, $fuente, $letra);
            $x += random_int(33, 38);
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /** Respaldo si el hosting no tiene la extensión GD */
    private function svg(string $texto): string
    {
        $letras = '';
        foreach (str_split($texto) as $i => $letra) {
            $x = 22 + $i * 35;
            $y = random_int(40, 50);
            $giro = random_int(-22, 22);
            $letras .= "<text x=\"{$x}\" y=\"{$y}\" transform=\"rotate({$giro} {$x} {$y})\" font-family=\"Verdana,sans-serif\" font-size=\"30\" font-weight=\"bold\" fill=\"#3a2a2a\">{$letra}</text>";
        }
        $lineas = '';
        for ($i = 0; $i < 6; $i++) {
            $lineas .= sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#c9a98f" stroke-width="2"/>', random_int(0, 200), random_int(0, 64), random_int(0, 200), random_int(0, 64));
        }
        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"200\" height=\"64\"><rect width=\"200\" height=\"64\" fill=\"#fdf7f0\"/>{$lineas}{$letras}</svg>";

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
