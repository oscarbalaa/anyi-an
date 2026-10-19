<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Fabrica;

// M9 - CU-16: RF-38, RF-39

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Storage::fake('local');
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $this->practica = DB::table('tipo_evaluacion')->where('nombre', 'Práctica')->value('id');
    Fabrica::como($this->e->profe);
});

afterEach(fn () => Carbon::setTestNow());

function nuevaEvaluacion(array $cambios = [], ?int $curso = null)
{
    $e = test()->e;

    return test()->postJson('/api/v1/docente/cursos/'.($curso ?? $e->mat).'/evaluaciones', $cambios + [
        'titulo' => 'Práctica 7 · Inecuaciones',
        'tipo_evaluacion_id' => test()->practica,
        'subperiodo_id' => $e->b2,
        'fecha_asignacion' => '2026-06-08',
        'fecha_limite' => '2026-06-12',
        'peso' => 2,
        'indicaciones' => "  Resuelve los ejercicios 1 al 12.  \n\n\n\nIncluye el procedimiento.",
        'requiere_entrega' => true,
    ]);
}

describe('permisos', function () {
    it('pide sesión y solo el docente del curso gestiona sus evaluaciones', function () {
        app('auth')->forgetGuards();
        $this->getJson("/api/v1/docente/cursos/{$this->e->mat}/evaluaciones")->assertUnauthorized();

        Fabrica::como($this->e->alumnos['Quispe Mendoza']->usuario);
        $this->getJson("/api/v1/docente/cursos/{$this->e->mat}/evaluaciones")->assertForbidden();

        Fabrica::como($this->e->otroProfe);
        $this->getJson("/api/v1/docente/cursos/{$this->e->mat}/evaluaciones")->assertForbidden();
        nuevaEvaluacion()->assertForbidden();
        expect(DB::table('evaluacion')->count())->toBe(0);
    });
});

describe('crear y listar (RF-38)', function () {
    it('crea la evaluación, limpia las indicaciones y la muestra con sus indicadores', function () {
        $id = nuevaEvaluacion()->assertCreated()->assertJsonPath('mensaje', 'Evaluación creada.')->json('id');

        expect(DB::table('evaluacion')->find($id))
            ->titulo->toBe('Práctica 7 · Inecuaciones')->peso->toBe(2)->requiere_entrega->toBe(1)
            ->indicaciones->toBe("Resuelve los ejercicios 1 al 12.\n\nIncluye el procedimiento.")
            ->and(DB::table('auditoria')->where('accion', 'CREAR')->where('entidad', 'evaluacion')->exists())->toBeTrue();

        $r = $this->getJson("/api/v1/docente/cursos/{$this->e->mat}/evaluaciones")->assertOk();
        expect($r->json('bimestre.nombre'))->toBe('II Bimestre')
            ->and($r->json('resumen'))->toBe(['evaluaciones' => 1, 'entregas' => 0, 'por_calificar' => 0])
            ->and($r->json('evaluaciones.0'))->toMatchArray(['titulo' => 'Práctica 7 · Inecuaciones', 'tipo' => 'Práctica', 'peso' => 2, 'momento' => 'EN_CURSO', 'entregas' => 0, 'estudiantes' => 3])
            ->and(collect($r->json('tipos'))->pluck('nombre'))->toContain('Práctica', 'Examen');
    });

    it('valida cada campo con su mensaje', function (array $cambios, string $campo, string $mensaje) {
        nuevaEvaluacion($cambios)->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
    })->with([
        'título corto' => [['titulo' => 'P7'], 'titulo', 'El título debe tener de 3 a 150 caracteres.'],
        'sin título' => [['titulo' => '   '], 'titulo', 'Escribe el título de la evaluación.'],
        'peso 0' => [['peso' => 0], 'peso', 'El peso es un número entero de 1 a 10.'],
        'peso decimal' => [['peso' => 1.5], 'peso', 'El peso es un número entero de 1 a 10.'],
        'peso en letras' => [['peso' => 'dos'], 'peso', 'El peso es un número entero de 1 a 10.'],
        'límite antes' => [['fecha_limite' => '2026-06-05'], 'fecha_limite', 'La fecha límite no puede ser anterior a la de asignación.'],
        'fecha inválida' => [['fecha_asignacion' => '2026-02-31'], 'fecha_asignacion', 'Ingresa una fecha de asignación válida.'],
        'indicaciones largas' => [['indicaciones' => str_repeat('a', 2001)], 'indicaciones', 'Las indicaciones no deben tener más de 2000 caracteres.'],
        'tipo inexistente' => [['tipo_evaluacion_id' => 999], 'tipo_evaluacion_id', 'Elige el tipo de evaluación.'],
        'fuera del bimestre' => [['fecha_limite' => '2026-07-30'], 'fecha_limite', 'Las fechas deben estar dentro del II Bimestre (18/05 – 24/07).'],
    ]);

    it('no crea evaluaciones en un bimestre cerrado', function () {
        DB::table('subperiodo')->where('id', $this->e->b2)->update(['estado' => 'CERRADO']);
        nuevaEvaluacion()->assertStatus(422)->assertJsonPath('mensaje', 'El II Bimestre está cerrado: sus evaluaciones y notas ya no se modifican.');
    });

    it('edita, recalcula el promedio y solo elimina si no hay entregas ni notas', function () {
        $id = nuevaEvaluacion()->json('id');
        DB::table('nota_evaluacion')->insert(['evaluacion_id' => $id, 'matricula_id' => $this->e->alumnos['Quispe Mendoza']->matricula, 'nota' => 16, 'estado_entrega' => 'ENTREGADO']);

        $this->putJson("/api/v1/docente/evaluaciones/{$id}", [
            'titulo' => 'Práctica 7', 'tipo_evaluacion_id' => $this->practica, 'subperiodo_id' => $this->e->b2,
            'fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-15', 'peso' => 3, 'requiere_entrega' => true,
        ])->assertOk();
        expect(DB::table('evaluacion')->find($id))->peso->toBe(3)->fecha_limite->toBe('2026-06-15')
            ->and((float) DB::table('calificacion_periodo')->where('matricula_id', $this->e->alumnos['Quispe Mendoza']->matricula)->value('promedio'))->toBe(16.0);

        $this->deleteJson("/api/v1/docente/evaluaciones/{$id}")->assertStatus(422)->assertJsonPath('mensaje', 'La evaluación ya tiene entregas o notas: no se puede eliminar.');
        $otra = nuevaEvaluacion(['titulo' => 'Práctica 8'])->json('id');
        $this->deleteJson("/api/v1/docente/evaluaciones/{$otra}")->assertOk();
        expect(DB::table('evaluacion')->where('id', $otra)->exists())->toBeFalse();
    });
});

describe('material de apoyo (RF-39)', function () {
    it('sube PDF, Word o imágenes, los guarda fuera de lo público y los quita', function () {
        $id = nuevaEvaluacion()->json('id');
        $archivo = $this->post("/api/v1/docente/evaluaciones/{$id}/material", ['archivo' => Fabrica::pdf('Guía de inecuaciones.pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->json('id');

        $fila = DB::table('archivo')->find($archivo);
        expect($fila->nombre_original)->toBe('Guía de inecuaciones.pdf')->and($fila->ruta)->toStartWith("material/{$id}/")
            ->and(Storage::disk('local')->exists($fila->ruta))->toBeTrue();
        expect($this->getJson("/api/v1/docente/cursos/{$this->e->mat}/evaluaciones")->json('evaluaciones.0.material.0.nombre'))->toBe('Guía de inecuaciones.pdf');

        $this->post("/api/v1/docente/evaluaciones/{$id}/material", ['archivo' => Fabrica::office('guia.docx')], ['Accept' => 'application/json'])->assertCreated();

        $this->deleteJson("/api/v1/docente/evaluaciones/{$id}/material/{$archivo}")->assertOk();
        expect(Storage::disk('local')->exists($fila->ruta))->toBeFalse()->and(DB::table('archivo')->where('id', $archivo)->exists())->toBeFalse();
    });

    it('rechaza otros formatos, archivos disfrazados y los que pesan más de 10 MB', function () {
        $id = nuevaEvaluacion()->json('id');
        $subir = fn ($archivo) => $this->post("/api/v1/docente/evaluaciones/{$id}/material", ['archivo' => $archivo], ['Accept' => 'application/json']);

        $subir(UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;'))->assertStatus(422)->assertJsonPath('errores.archivo.0', 'Sube un PDF, Word, PowerPoint, Excel, JPG o PNG.');
        $subir(UploadedFile::fake()->createWithContent('falso.pdf', 'MZ ejecutable'))->assertStatus(422)->assertJsonPath('errores.archivo.0', 'Sube un PDF, Word, PowerPoint, Excel, JPG o PNG.');
        // Un .zip renombrado a .docx tiene la firma de un zip, pero no es un documento de Word
        $subir(UploadedFile::fake()->createWithContent('guia.docx', "PK\x03\x04".str_repeat('x', 100)))->assertStatus(422);
        $subir(Fabrica::office('hoja.xlsx', 'word/'))->assertStatus(422);
        $subir(UploadedFile::fake()->create('pesado.pdf', 11 * 1024))->assertStatus(422)->assertJsonPath('errores.archivo.0', 'El archivo pesa más de 10 MB.');
        $subir(null)->assertStatus(422)->assertJsonPath('errores.archivo.0', 'Elige el archivo.');
        expect(DB::table('archivo')->count())->toBe(0);
    });
});
