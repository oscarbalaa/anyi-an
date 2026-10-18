<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Fabrica;

// M9 - CU-17: RF-40, RF-41, RF-42, RN-12, RN-26

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Storage::fake('local');
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $tipo = fn (string $n) => DB::table('tipo_evaluacion')->where('nombre', $n)->value('id');
    // Práctica (peso 1), proyecto ya vencido (peso 2), examen en clase (peso 3) y una práctica que aún no se asigna
    $this->practica = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Práctica 7', 'tipo_evaluacion_id' => $tipo('Práctica'), 'fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-12', 'peso' => 1, 'indicaciones' => 'Resuelve los ejercicios.']);
    $this->proyecto = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Proyecto', 'tipo_evaluacion_id' => $tipo('Proyecto'), 'fecha_asignacion' => '2026-05-20', 'fecha_limite' => '2026-06-05', 'peso' => 2]);
    $this->examen = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Examen bimestral', 'tipo_evaluacion_id' => $tipo('Examen'), 'fecha_asignacion' => '2026-06-01', 'fecha_limite' => '2026-07-20', 'peso' => 3, 'requiere_entrega' => false]);
    $this->futura = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Práctica 8', 'fecha_asignacion' => '2026-06-20', 'fecha_limite' => '2026-06-25', 'peso' => 2]);
    $this->valeria = $this->e->alumnos['Quispe Mendoza'];
    Fabrica::como($this->valeria->usuario);
});

afterEach(fn () => Carbon::setTestNow());

function entregar(int $evaluacion, ?UploadedFile $archivo, string $accion = 'ENTREGAR')
{
    return test()->post("/api/v1/estudiante/actividades/{$evaluacion}/entrega", array_filter(['archivo' => $archivo, 'accion' => $accion]), ['Accept' => 'application/json']);
}

describe('mis actividades (RF-40)', function () {
    it('lista solo lo ya asignado, con el peso en % del bimestre y su estado', function () {
        $r = $this->getJson('/api/v1/estudiante/actividades')->assertOk();
        $actividades = collect($r->json('actividades'))->keyBy('titulo');

        expect($actividades->keys()->all())->toBe(['Proyecto', 'Práctica 7', 'Examen bimestral'])
            ->and($actividades['Práctica 7'])->toMatchArray(['estado' => 'PENDIENTE', 'peso_porcentaje' => 13, 'curso' => 'Matemática', 'docente' => 'Ana Torres', 'nota' => null])
            ->and($actividades['Proyecto'])->toMatchArray(['estado' => 'NO_ENTREGADO', 'peso_porcentaje' => 25])
            ->and($actividades['Examen bimestral'])->toMatchArray(['estado' => 'EN_CLASE', 'requiere_entrega' => false])
            ->and($r->json('resumen'))->toBe(['pendientes' => 1, 'por_vencer' => 1, 'completadas' => 0])
            ->and($r->json('primera.titulo'))->toBe('Práctica 7');

        $this->getJson("/api/v1/estudiante/actividades/{$this->futura}")->assertNotFound();
        $ajena = Fabrica::evaluacion(Fabrica::curso(Fabrica::aula($this->e->periodo)), $this->e->b2);
        $this->getJson("/api/v1/estudiante/actividades/{$ajena}")->assertNotFound();
    });

    it('solo los estudiantes ven sus actividades', function () {
        Fabrica::como($this->e->profe);
        $this->getJson('/api/v1/estudiante/actividades')->assertForbidden();
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/estudiante/actividades')->assertUnauthorized();
    });
});

describe('entregar (RF-41, RN-12)', function () {
    it('guarda un borrador sin entregar y luego entrega a tiempo', function () {
        entregar($this->practica, null, 'BORRADOR')->assertStatus(422)->assertJsonPath('errores.archivo.0', 'Adjunta tu archivo PDF.');
        entregar($this->practica, Fabrica::pdf('borrador.pdf'), 'BORRADOR')->assertOk()
            ->assertJsonPath('mensaje', 'Borrador guardado. Aún no lo ve tu docente: recuerda entregarlo.')
            ->assertJsonPath('estado', 'EN_PROGRESO')->assertJsonPath('entrega.archivo.nombre', 'borrador.pdf');

        // El docente aún no ve el borrador
        Fabrica::como($this->e->profe);
        $fila = collect($this->getJson("/api/v1/docente/evaluaciones/{$this->practica}/notas")->json('estudiantes'))->firstWhere('codigo', 'E-2026-002');
        expect($fila['entrega'])->toBeNull()->and($fila['estado_entrega'])->toBe('PENDIENTE');

        Fabrica::como($this->valeria->usuario);
        $borrador = DB::table('entrega')->value('archivo_id');
        $r = entregar($this->practica, Fabrica::pdf('final.pdf'))->assertOk()->assertJsonPath('mensaje', '¡Actividad entregada!');
        expect($r->json('estado'))->toBe('ENTREGADO')->and($r->json('entrega.es_tardia'))->toBeFalse()->and($r->json('puede_entregar'))->toBeFalse()
            ->and(collect($r->json('historial'))->pluck('texto')->all())->toBe(['Actividad asignada', 'Borrador guardado', 'Actividad entregada'])
            ->and(DB::table('archivo')->where('id', $borrador)->exists())->toBeFalse()
            ->and(DB::table('nota_evaluacion')->where('matricula_id', $this->valeria->matricula)->value('estado_entrega'))->toBe('ENTREGADO');

        entregar($this->practica, Fabrica::pdf())->assertStatus(422)->assertJsonPath('mensaje', 'Ya entregaste esta actividad. Si necesitas cambiarla, habla con tu docente.');
    });

    it('después de las 11:59 p. m. del día límite la entrega queda tardía', function () {
        Carbon::setTestNow('2026-06-12 23:59:00');
        entregar($this->practica, Fabrica::pdf())->assertOk()->assertJsonPath('estado', 'ENTREGADO');

        Fabrica::como($this->e->alumnos['Aranda Ruiz']->usuario);
        Carbon::setTestNow('2026-06-13 00:00:30');
        entregar($this->practica, Fabrica::pdf())->assertOk()
            ->assertJsonPath('mensaje', 'Actividad entregada fuera de plazo: queda como tardía.')
            ->assertJsonPath('estado', 'TARDIO')->assertJsonPath('entrega.es_tardia', true);
    });

    it('solo acepta PDF reales de hasta 10 MB', function () {
        entregar($this->practica, UploadedFile::fake()->createWithContent('tarea.pdf', "MZ\x90\x00 programa"))->assertStatus(422)
            ->assertJsonPath('errores.archivo.0', 'El archivo debe ser un PDF. Guarda tu trabajo como PDF y vuelve a subirlo.');
        entregar($this->practica, UploadedFile::fake()->createWithContent('tarea.docx', "PK\x03\x04 word"))->assertStatus(422);
        entregar($this->practica, UploadedFile::fake()->create('tarea.pdf', 11 * 1024, 'application/pdf'))->assertStatus(422)
            ->assertJsonPath('errores.archivo.0', 'El archivo pesa más de 10 MB.');
        entregar($this->practica, Fabrica::pdf(), 'PUBLICAR')->assertStatus(422)->assertJsonPath('errores.accion.0', 'Elige si guardas un borrador o entregas la actividad.');
        expect(DB::table('entrega')->count())->toBe(0)->and(DB::table('archivo')->count())->toBe(0);
    });

    it('no recibe archivos de un examen en clase, ni con el bimestre cerrado, ni de un retirado', function () {
        entregar($this->examen, Fabrica::pdf())->assertStatus(422)->assertJsonPath('mensaje', 'Esta evaluación se rinde en clase: no se entrega un archivo.');

        DB::table('subperiodo')->where('id', $this->e->b2)->update(['estado' => 'CERRADO']);
        entregar($this->practica, Fabrica::pdf())->assertStatus(422)->assertJsonPath('mensaje', 'El II Bimestre está cerrado: ya no se reciben entregas.');

        DB::table('subperiodo')->where('id', $this->e->b2)->update(['estado' => 'ABIERTO']);
        DB::table('matricula')->where('id', $this->valeria->matricula)->update(['estado' => 'RETIRADA']);
        entregar($this->practica, Fabrica::pdf())->assertStatus(422)->assertJsonPath('mensaje', 'Tu matrícula no está activa: no puedes entregar.');
    });
});

describe('descargas con permiso', function () {
    it('cada uno descarga solo lo que le corresponde', function () {
        entregar($this->practica, Fabrica::pdf('mi-tarea.pdf'))->assertOk();
        $archivo = DB::table('entrega')->value('archivo_id');
        $ruta = "/api/v1/archivos/{$archivo}";

        $this->get($ruta)->assertOk()->assertDownload('mi-tarea.pdf');
        Fabrica::como($this->e->alumnos['Aranda Ruiz']->usuario);
        $this->getJson($ruta)->assertForbidden();
        Fabrica::como($this->e->otroProfe);
        $this->getJson($ruta)->assertForbidden();
        Fabrica::como($this->e->profe);
        $this->get($ruta)->assertOk();
        Fabrica::como($this->e->admin);
        $this->get($ruta)->assertOk();
        app('auth')->forgetGuards();
        $this->getJson($ruta)->assertUnauthorized();
    });

    it('el material se descarga desde que la actividad está asignada y solo en el aula', function () {
        Fabrica::como($this->e->profe);
        $guia = $this->post("/api/v1/docente/evaluaciones/{$this->practica}/material", ['archivo' => Fabrica::pdf('guia.pdf')], ['Accept' => 'application/json'])->json('id');
        $oculta = $this->post("/api/v1/docente/evaluaciones/{$this->futura}/material", ['archivo' => Fabrica::pdf('oculta.pdf')], ['Accept' => 'application/json'])->json('id');

        Fabrica::como($this->valeria->usuario);
        $this->get("/api/v1/archivos/{$guia}")->assertOk();
        $this->getJson("/api/v1/archivos/{$oculta}")->assertForbidden();
        expect($this->getJson("/api/v1/estudiante/actividades/{$this->practica}")->json('material.0.nombre'))->toBe('guia.pdf');

        $otroAula = Fabrica::matricula(Fabrica::aula($this->e->periodo), $this->e->periodo);
        $usuario = DB::table('estudiante')->where('id', DB::table('matricula')->where('id', $otroAula)->value('estudiante_id'))->value('usuario_id');
        Fabrica::como($usuario);
        $this->getJson("/api/v1/archivos/{$guia}")->assertForbidden();
    });
});
