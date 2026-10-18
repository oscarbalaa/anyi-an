<?php

use App\Servicios\Personas\ImportacionEstudiantes;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Soporte\Fabrica;

// M7 - CU-11: RF-30 (importación de estudiantes desde Excel)

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Carbon::setTestNow('2026-06-10');
    $this->admin = Fabrica::comoAdmin();
    $this->p = Fabrica::periodo(['nombre' => '2026', 'estado' => 'EN_CURSO', 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-12-18']);
    $this->a3b = Fabrica::aula($this->p, ['seccion_id' => Fabrica::seccion('B')]);
    $this->p3u = Fabrica::aula($this->p, ['grado_id' => Fabrica::grado('PRIMARIA', 3), 'seccion_id' => Fabrica::seccion('Única'), 'capacidad' => 1]);
});

afterEach(fn () => Carbon::setTestNow());

/** Una fila de la plantilla con valores válidos */
function filaExcel(array $cambios = []): array
{
    return array_values(array_replace([
        'tipo_documento' => 'DNI', 'numero_documento' => '78123456', 'nombres' => 'Diego Alonso', 'apellidos' => 'Ramírez Torres',
        'fecha_nacimiento' => '14/05/2012', 'sexo' => 'M', 'nivel' => 'SECUNDARIA', 'grado' => '3', 'seccion' => 'B',
        'correo' => '', 'celular' => '', 'direccion' => '', 'codigo' => '',
    ], $cambios));
}

/** Arma un .xlsx en memoria como lo subiría el administrador */
function excel(array $filas, ?array $encabezados = null): UploadedFile
{
    $ruta = tempnam(sys_get_temp_dir(), 'ayni').'.xlsx';
    $escritor = new Writer;
    $escritor->openToFile($ruta);
    $escritor->addRow(Row::fromValues($encabezados ?? ImportacionEstudiantes::COLUMNAS));
    foreach ($filas as $fila) {
        $escritor->addRow(Row::fromValues($fila));
    }
    $escritor->close();

    return new UploadedFile($ruta, 'matricula_3B.xlsx', null, null, true);
}

function revisar(UploadedFile $archivo, bool $matricular = true)
{
    return test()->post('/api/v1/admin/estudiantes/importar/revisar', ['archivo' => $archivo, 'matricular' => $matricular ? '1' : '0'], ['Accept' => 'application/json']);
}

it('descarga la plantilla con las columnas y una fila de ejemplo', function () {
    $r = $this->get('/api/v1/admin/estudiantes/plantilla')->assertOk()->assertDownload('plantilla_estudiantes.xlsx');

    $lector = new Reader;
    $lector->open($r->baseResponse->getFile()->getPathname());
    $filas = [];
    foreach ($lector->getSheetIterator() as $hoja) {
        foreach ($hoja->getRowIterator() as $fila) {
            $filas[] = $fila->toArray();
        }
    }
    $lector->close();
    expect($filas[0])->toBe(ImportacionEstudiantes::COLUMNAS)
        ->and($filas[1][1])->toBe('78123456');
});

it('revisa cada fila y explica los errores sin guardar nada', function () {
    Fabrica::cuenta('ESTUDIANTE', 'E-2026-050', 'piki2026');
    DB::table('persona')->where('id', DB::table('persona')->max('id'))->update(['numero_documento' => '70000000']);

    $r = revisar(excel([
        filaExcel(),                                                                  // fila 2: válida
        filaExcel(['numero_documento' => '70000000']),                                // 3: DNI ya registrado
        filaExcel(['numero_documento' => '78123457', 'fecha_nacimiento' => '31/02/2012']), // 4: fecha imposible
        filaExcel(['numero_documento' => '78123458', 'seccion' => 'D']),             // 5: sección inexistente
        filaExcel(),                                                                  // 6: DNI repetido en el archivo
        filaExcel(['numero_documento' => '78123459', 'fecha_nacimiento' => '01/01/2020']), // 7: edad (RN-23)
        filaExcel(['numero_documento' => '78123460', 'correo' => 'diego@mail', 'sexo' => 'X']), // 8: dos errores
        filaExcel(['numero_documento' => '78123461', 'nivel' => 'INICIAL']),        // 9: nivel
        filaExcel(['numero_documento' => '123']),                                     // 10: no es un DNI con ceros perdidos
    ]))->assertOk();

    expect($r->json('filas'))->toBe(9)->and($r->json('validas'))->toBe(1);
    $errores = collect($r->json('errores'))->mapWithKeys(fn ($e) => [$e['fila'] => $e['mensajes']]);
    expect($errores[3])->toBe(['Ya hay una persona registrada con ese documento.'])
        ->and($errores[4])->toBe(['La fecha «31/02/2012» no es válida (usa DD/MM/AAAA).'])
        ->and($errores[5])->toBe(['La sección «D» no existe en 3.º secundaria.'])
        ->and($errores[6])->toBe(['El documento se repite en la fila 2.'])
        ->and($errores[7])->toBe(['La edad (6 años) no es coherente con secundaria (de 11 a 20 años, RN-23).'])
        ->and($errores[8])->toBe(['El sexo es M o F.', 'El correo debe tener el formato usuario@dominio.com.'])
        ->and($errores[9])->toBe(['El nivel es PRIMARIA o SECUNDARIA.'])
        ->and($errores[10])->toBe(['El DNI debe tener 8 dígitos.']);
    expect(DB::table('estudiante')->count())->toBe(1);
});

it('acepta fechas y DNI guardados como números de Excel, "Única" sin tilde y minúsculas', function () {
    $r = revisar(excel([
        filaExcel(['numero_documento' => 1234567, 'fecha_nacimiento' => new DateTimeImmutable('2017-08-20'), 'nivel' => 'primaria', 'grado' => 3, 'seccion' => 'unica', 'sexo' => 'f']),
    ]))->assertOk();
    expect($r->json('errores'))->toBe([])->and($r->json('validas'))->toBe(1);
});

it('importa solo las válidas, las matricula y entrega las contraseñas', function () {
    $r = $this->post('/api/v1/admin/estudiantes/importar', [
        'archivo' => excel([
            filaExcel(),
            filaExcel(['numero_documento' => '78123457', 'nombres' => 'Valeria', 'apellidos' => 'Quispe Mendoza', 'sexo' => 'F', 'codigo' => 'e-2022-052']),
            filaExcel(['numero_documento' => '78123458', 'seccion' => 'D']),
        ]),
        'matricular' => '1',
        'crear_usuario' => '1',
    ], ['Accept' => 'application/json'])->assertOk();

    expect($r->json('importados'))->toBe(2)
        ->and($r->json('con_error'))->toBe(1)
        ->and($r->json('mensaje'))->toBe('Se importaron 2 estudiantes.')
        ->and(collect($r->json('credenciales'))->pluck('codigo')->all())->toBe(['E-2026-001', 'E-2022-052'])
        ->and($r->json('credenciales.0.contrasena'))->toMatch('/^[a-z]{4}\d{4}$/');
    expect(DB::table('estudiante')->pluck('origen')->unique()->all())->toBe(['EXCEL'])
        ->and(DB::table('matricula')->where('aula_id', $this->a3b)->where('estado', 'ACTIVA')->count())->toBe(2)
        ->and(DB::table('usuario')->where('username', 'E-2022-052')->value('estado'))->toBe('ACTIVO')
        ->and(DB::table('auditoria')->where('accion', 'IMPORTAR')->exists())->toBeTrue();
});

it('sin "crear usuario" las cuentas quedan inactivas y no se entregan contraseñas', function () {
    $r = $this->post('/api/v1/admin/estudiantes/importar', ['archivo' => excel([filaExcel()]), 'matricular' => '0', 'crear_usuario' => '0'], ['Accept' => 'application/json'])->assertOk();
    expect($r->json('credenciales'))->toBe([])
        ->and($r->json('mensaje'))->toBe('Se importó 1 estudiante.')
        ->and(DB::table('usuario')->where('username', 'E-2026-001')->value('estado'))->toBe('INACTIVO')
        ->and(DB::table('matricula')->count())->toBe(0);
});

it('respeta la capacidad del aula al matricular (RN-04)', function () {
    $r = revisar(excel([
        filaExcel(['numero_documento' => '78000001', 'fecha_nacimiento' => '20/08/2017', 'nivel' => 'PRIMARIA', 'seccion' => 'Única']),
        filaExcel(['numero_documento' => '78000002', 'fecha_nacimiento' => '20/08/2017', 'nivel' => 'PRIMARIA', 'seccion' => 'Única']),
    ]))->assertOk();
    expect($r->json('validas'))->toBe(1)
        ->and($r->json('errores.0.mensajes.0'))->toBe('El aula 3.º primaria se llena (1/1): no entra esta fila.');
    // Sin matricular, el cupo no importa
    expect(revisar(excel([filaExcel(['numero_documento' => '78000001', 'fecha_nacimiento' => '20/08/2017', 'nivel' => 'PRIMARIA', 'seccion' => 'Única']), filaExcel(['numero_documento' => '78000002', 'fecha_nacimiento' => '20/08/2017', 'nivel' => 'PRIMARIA', 'seccion' => 'Única'])]), false)->json('validas'))->toBe(2);
});

it('descarga las filas con error con una columna que explica cada error', function () {
    $r = $this->post('/api/v1/admin/estudiantes/importar/errores', ['archivo' => excel([filaExcel(), filaExcel(['numero_documento' => '78123457', 'seccion' => 'D'])]), 'matricular' => '1'])->assertOk();

    $lector = new Reader;
    $lector->open($r->baseResponse->getFile()->getPathname());
    $filas = [];
    foreach ($lector->getSheetIterator() as $hoja) {
        foreach ($hoja->getRowIterator() as $fila) {
            $filas[] = $fila->toArray();
        }
    }
    $lector->close();
    expect($filas)->toHaveCount(2)
        ->and(end($filas[0]))->toBe('errores')
        ->and(end($filas[1]))->toBe('La sección «D» no existe en 3.º secundaria.');
});

it('rechaza archivos que no son Excel, dañados, sin columnas o vacíos', function () {
    $csv = UploadedFile::fake()->createWithContent('alumnos.csv', "tipo_documento,numero_documento\nDNI,78123456");
    revisar($csv)->assertStatus(422)->assertJsonPath('errores.archivo.0', 'Sube un archivo de Excel (.xlsx). Otros formatos como .xls o .csv no se aceptan.');

    $falso = UploadedFile::fake()->createWithContent('malicioso.xlsx', '<?php echo "hola"; ?>');
    revisar($falso)->assertStatus(422)->assertJsonPath('errores.archivo.0', 'El archivo no es un Excel (.xlsx) válido.');

    revisar(excel([filaExcel()], ['nombres', 'apellidos']))->assertStatus(422)
        ->assertJsonPath('errores.archivo.0', fn ($m) => str_starts_with($m, 'Al archivo le faltan columnas de la plantilla: tipo_documento, numero_documento'));

    revisar(excel([]))->assertStatus(422)->assertJsonPath('errores.archivo.0', 'El archivo no tiene filas de estudiantes.');
    revisar(UploadedFile::fake()->create('grande.xlsx', 6000))->assertStatus(422)->assertJsonPath('errores.archivo.0', 'El archivo pesa más de 5 MB.');
});

it('no importa si ninguna fila es válida', function () {
    $this->post('/api/v1/admin/estudiantes/importar', ['archivo' => excel([filaExcel(['seccion' => 'Z'])]), 'matricular' => '1', 'crear_usuario' => '1'], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('mensaje', 'El archivo no tiene filas válidas para importar.');
});
