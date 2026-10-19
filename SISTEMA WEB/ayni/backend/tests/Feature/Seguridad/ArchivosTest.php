<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Fabrica;

// M13 - subida de archivos maliciosos (entregas, material de apoyo e importador)

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Storage::fake('local');
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $this->practica = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-12']);
});

afterEach(fn () => Carbon::setTestNow());

/** Archivo subido de verdad: el tipo se detecta por su contenido (los de UploadedFile::fake() lo toman del nombre) */
function archivoReal(string $nombre, string $contenido): UploadedFile
{
    $ruta = tempnam(sys_get_temp_dir(), 'subida');
    file_put_contents($ruta, $contenido);

    return new UploadedFile($ruta, $nombre, null, null, true);
}

function entregarArchivo(int $evaluacion, UploadedFile $archivo)
{
    return test()->post("/api/v1/estudiante/actividades/{$evaluacion}/entrega", ['archivo' => $archivo, 'accion' => 'ENTREGAR'], ['Accept' => 'application/json']);
}

it('rechaza páginas web, scripts y ejecutables aunque se llamen .pdf', function () {
    Fabrica::como($this->e->alumnos['Aranda Ruiz']->usuario);
    foreach ([
        'tarea.pdf' => '<html><script>alert(1)</script></html>',
        'trabajo.pdf' => '<?php system($_GET["c"]); ?>',
        'informe.pdf' => "MZ\x90\x00 ejecutable de Windows",
        'dibujo.pdf' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
    ] as $nombre => $contenido) {
        entregarArchivo($this->practica, archivoReal($nombre, $contenido))
            ->assertStatus(422)->assertJsonPath('errores.archivo.0', 'El archivo debe ser un PDF. Guarda tu trabajo como PDF y vuelve a subirlo.');
    }
    expect(DB::table('archivo')->count())->toBe(0);
});

it('un PDF con código PHP dentro y extensión .php se guarda como .bin, fuera de lo público, y se descarga como adjunto', function () {
    Fabrica::como($this->e->alumnos['Aranda Ruiz']->usuario);
    $pdf = Fabrica::pdf();
    $polyglota = archivoReal('../../public/shell.php', file_get_contents($pdf->getRealPath()).'<?php system($_GET["c"]); ?>');
    entregarArchivo($this->practica, $polyglota)->assertOk();

    $archivo = DB::table('archivo')->first();
    expect($archivo->ruta)->toEndWith('.bin')
        ->and($archivo->ruta)->not->toContain('..')
        ->and($archivo->nombre_original)->not->toContain('/')
        ->and(file_exists(public_path('shell.php')))->toBeFalse();

    $r = $this->get("/api/v1/archivos/{$archivo->id}")->assertOk();
    expect($r->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($r->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('el material de apoyo no acepta HTML, SVG ni un .zip disfrazado de Word', function () {
    Fabrica::como($this->e->profe);
    $subir = fn (UploadedFile $a) => $this->post("/api/v1/docente/evaluaciones/{$this->practica}/material", ['archivo' => $a], ['Accept' => 'application/json']);

    $subir(archivoReal('guia.html', '<script>alert(1)</script>'))->assertStatus(422);
    $subir(archivoReal('guia.svg', '<svg onload="alert(1)"/>'))->assertStatus(422);
    $subir(archivoReal('foto.png', '<?php echo 1; ?>'))->assertStatus(422);
    $subir(archivoReal('guia.docx', "PK\x03\x04 zip cualquiera"))->assertStatus(422);
    $subir(Fabrica::office('guia.docx'))->assertCreated();

    expect(DB::table('archivo')->count())->toBe(1);
});

it('el importador rechaza lo que no es un Excel real', function () {
    Fabrica::como($this->e->admin);
    foreach ([
        archivoReal('estudiantes.xlsx', '<?php system("id"); ?>'),
        archivoReal('estudiantes.xlsx', "PK\x03\x04 dañado"),
        archivoReal('estudiantes.csv', "nombres,apellidos\n=cmd|' /C calc'!A0,x"),
    ] as $archivo) {
        $this->post('/api/v1/admin/estudiantes/importar/revisar', ['archivo' => $archivo, 'matricular' => '1'], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
    expect(DB::table('estudiante')->count())->toBe(3);
});
