<?php

namespace App\Servicios;

use App\Exceptions\ErrorRegla;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/** Archivos subidos (entregas y material de apoyo): fuera de la carpeta pública y con permiso para descargar */
class Archivos
{
    private const OOXML = ['docx', 'pptx', 'xlsx'];

    /** Extensiones con las que se guarda en disco; cualquier otra (.php, .html…) se guarda como .bin */
    private const EXTENSIONES = ['pdf', 'docx', 'pptx', 'xlsx', 'jpg', 'jpeg', 'png'];

    /** RN-26: 10 MB o lo que permita el servidor si es menos */
    public static function limiteBytes(): int
    {
        $bytes = fn (string $valor) => (int) $valor * match (strtoupper(substr(trim($valor), -1))) {
            'G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024, default => 1,
        };
        $limites = array_filter([
            config('ayni.archivos.max_mb') * 1024 * 1024,
            $bytes((string) ini_get('upload_max_filesize')),
            $bytes((string) ini_get('post_max_size')),
        ]);

        return min($limites);
    }

    public static function limiteTexto(): string
    {
        return round(self::limiteBytes() / 1024 / 1024, 1).' MB';
    }

    /** Solo PDF, comprobado por su contenido y no por la extensión */
    public function validarPdf(UploadedFile $archivo, string $campo = 'archivo'): void
    {
        $this->validarTamano($archivo, $campo);
        if (! $this->empiezaCon($archivo, '%PDF-') || $archivo->getMimeType() !== 'application/pdf') {
            throw new ErrorRegla('El archivo debe ser un PDF. Guarda tu trabajo como PDF y vuelve a subirlo.', $campo);
        }
    }

    /** Material de apoyo: PDF, Word, PowerPoint, Excel o imagen */
    public function validarMaterial(UploadedFile $archivo, string $campo = 'archivo'): void
    {
        $this->validarTamano($archivo, $campo);
        $extension = mb_strtolower($archivo->getClientOriginalExtension());
        $valido = match (true) {
            $extension === 'pdf' => $this->empiezaCon($archivo, '%PDF-'),
            in_array($extension, self::OOXML, true) => $this->esOfficeAbierto($archivo, $extension),
            in_array($extension, ['jpg', 'jpeg'], true) => $archivo->getMimeType() === 'image/jpeg',
            $extension === 'png' => $archivo->getMimeType() === 'image/png',
            default => false,
        };
        if (! $valido) {
            throw new ErrorRegla('Sube un PDF, Word, PowerPoint, Excel, JPG o PNG.', $campo);
        }
    }

    public function guardar(UploadedFile $archivo, string $carpeta, int $usuarioId): int
    {
        $extension = mb_strtolower($archivo->getClientOriginalExtension());
        $extension = in_array($extension, self::EXTENSIONES, true) ? $extension : 'bin';
        $ruta = Storage::disk('local')->putFileAs($carpeta, $archivo, Str::random(32).'.'.$extension);
        $nombre = mb_substr(preg_replace('/[\x00-\x1F\/\\\\]/u', '', $archivo->getClientOriginalName()) ?: "archivo.{$extension}", 0, 255);

        return DB::table('archivo')->insertGetId([
            'disco' => 'local',
            'ruta' => $ruta,
            'nombre_original' => $nombre,
            'tipo_mime' => $archivo->getMimeType() ?? 'application/octet-stream',
            'tamano_bytes' => $archivo->getSize(),
            'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
            'subido_por' => $usuarioId,
        ]);
    }

    public function borrar(int $archivoId): void
    {
        $archivo = DB::table('archivo')->find($archivoId);
        if ($archivo) {
            Storage::disk($archivo->disco)->delete($archivo->ruta);
            DB::table('archivo')->where('id', $archivoId)->delete();
        }
    }

    public static function datos(?object $a): ?array
    {
        return $a ? ['id' => $a->id, 'nombre' => $a->nombre_original, 'tamano' => (int) $a->tamano_bytes, 'tipo' => $a->tipo_mime] : null;
    }

    /** El administrador descarga todo; el docente, lo de sus cursos; el estudiante, su entrega y el material de su aula */
    public function descargar(int $archivoId, Usuario $usuario): StreamedResponse
    {
        $archivo = DB::table('archivo')->find($archivoId) ?? abort(404);
        abort_unless($this->puedeVer($archivo->id, $usuario), 403);
        abort_unless(Storage::disk($archivo->disco)->exists($archivo->ruta), 404);

        return Storage::disk($archivo->disco)->download($archivo->ruta, $archivo->nombre_original, [
            'Content-Type' => $archivo->tipo_mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function puedeVer(int $archivoId, Usuario $usuario): bool
    {
        $rol = $usuario->rol->codigo;
        if ($rol === 'ADMIN') {
            return true;
        }
        $material = DB::table('evaluacion_material as em')->join('evaluacion as ev', 'ev.id', '=', 'em.evaluacion_id')
            ->join('curso_aula as c', 'c.id', '=', 'ev.curso_aula_id')->where('em.archivo_id', $archivoId);
        $entrega = DB::table('entrega as en')->join('evaluacion as ev', 'ev.id', '=', 'en.evaluacion_id')
            ->join('curso_aula as c', 'c.id', '=', 'ev.curso_aula_id')->join('matricula as m', 'm.id', '=', 'en.matricula_id')
            ->join('estudiante as e', 'e.id', '=', 'm.estudiante_id')->where('en.archivo_id', $archivoId);

        if ($rol === 'DOCENTE') {
            $docente = DB::table('docente')->where('usuario_id', $usuario->id)->value('id');

            return $material->clone()->where('c.docente_id', $docente)->exists() || $entrega->clone()->where('c.docente_id', $docente)->exists();
        }

        // Estudiante: su propia entrega, o el material ya asignado en el aula donde está matriculado
        $aulas = DB::table('matricula')->join('estudiante', 'estudiante.id', '=', 'matricula.estudiante_id')
            ->where('estudiante.usuario_id', $usuario->id)->where('matricula.estado', 'ACTIVA')->pluck('matricula.aula_id');

        return $entrega->clone()->where('e.usuario_id', $usuario->id)->exists()
            || $material->clone()->whereIn('c.aula_id', $aulas)->where('ev.estado', 'PUBLICADA')->where('ev.fecha_asignacion', '<=', today()->toDateString())->exists();
    }

    private function validarTamano(UploadedFile $archivo, string $campo): void
    {
        if (! $archivo->isValid() || $archivo->getSize() > self::limiteBytes()) {
            throw new ErrorRegla('El archivo pesa más de '.self::limiteTexto().'.', $campo);
        }
    }

    /** Un .docx/.pptx/.xlsx es un zip con [Content_Types].xml y la carpeta de su programa (no basta renombrar un .zip) */
    private function esOfficeAbierto(UploadedFile $archivo, string $extension): bool
    {
        if (! $this->empiezaCon($archivo, "PK\x03\x04")) {
            return false;
        }
        $zip = new ZipArchive;
        if ($zip->open($archivo->getRealPath(), ZipArchive::RDONLY) !== true) {
            return false;
        }
        $carpeta = ['docx' => 'word/', 'pptx' => 'ppt/', 'xlsx' => 'xl/'][$extension];
        $valido = $zip->locateName('[Content_Types].xml') !== false;
        for ($i = 0; $valido && $i < $zip->numFiles; $i++) {
            if (str_starts_with((string) $zip->getNameIndex($i), $carpeta)) {
                $zip->close();

                return true;
            }
        }
        $zip->close();

        return false;
    }

    private function empiezaCon(UploadedFile $archivo, string $firma): bool
    {
        $f = fopen($archivo->getRealPath(), 'rb');
        $inicio = $f ? fread($f, strlen($firma)) : '';
        if ($f) {
            fclose($f);
        }

        return $inicio === $firma;
    }
}
