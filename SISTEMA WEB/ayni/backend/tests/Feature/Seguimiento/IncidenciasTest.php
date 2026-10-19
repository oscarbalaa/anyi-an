<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Fabrica;

// M10 - CU-22: RF-49, RF-50

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $this->ajeno = Fabrica::cuenta('DOCENTE', '43333333');
    // La tutora de 3.º B es la docente de Comunicación
    DB::table('aula')->where('id', $this->e->aula)->update(['tutor_docente_id' => DB::table('docente')->where('usuario_id', $this->e->otroProfe)->value('id')]);
    $this->conducta = DB::table('tipo_incidencia')->where('nombre', 'Conducta')->value('id');
    $this->valeria = $this->e->alumnos['Quispe Mendoza'];
    Fabrica::como($this->e->profe);
});

afterEach(fn () => Carbon::setTestNow());

function registrarIncidencia(array $cambios = [], string $rol = 'docente')
{
    return test()->postJson("/api/v1/{$rol}/incidencias", $cambios + [
        'matricula_id' => test()->valeria->matricula,
        'tipo_incidencia_id' => test()->conducta,
        'gravedad' => 'LEVE',
        'fecha' => '2026-06-09',
        'descripcion' => '  Interrumpe   la clase de forma reiterada. ',
    ]);
}

describe('registrar (RF-49)', function () {
    it('registra, avisa al estudiante y a su tutora, y deja auditoría', function () {
        $id = registrarIncidencia()->assertCreated()->assertJsonPath('mensaje', 'Incidencia registrada.')->json('id');

        expect(DB::table('incidencia')->find($id))->descripcion->toBe('Interrumpe la clase de forma reiterada.')->registrado_por->toBe($this->e->profe)
            ->and(DB::table('auditoria')->where('entidad', 'incidencia')->where('entidad_id', $id)->exists())->toBeTrue();

        $alumno = DB::table('notificacion')->where('usuario_id', $this->valeria->usuario)->sole();
        $tutora = DB::table('notificacion')->where('usuario_id', $this->e->otroProfe)->sole();
        expect($alumno)->tipo->toBe('INCIDENCIA_REGISTRADA')->mensaje->toBe('Se registró una incidencia de tipo Conducta (leve) el 9 de junio. Conversa con tu tutor si tienes dudas.')
            ->and($tutora)->titulo->toBe('Incidencia en tu tutoría')->enlace->toBe("/docente/incidencias?matricula={$this->valeria->matricula}")
            ->and(DB::table('notificacion')->where('usuario_id', $this->e->admin)->exists())->toBeFalse();
    });

    it('una incidencia grave también avisa a la administración; la tutora no se avisa a sí misma', function () {
        Fabrica::como($this->e->otroProfe);
        registrarIncidencia(['gravedad' => 'GRAVE'])->assertCreated();
        expect(DB::table('notificacion')->where('usuario_id', $this->e->admin)->value('tipo'))->toBe('INCIDENCIA_GRAVE')
            ->and(DB::table('notificacion')->where('usuario_id', $this->e->otroProfe)->exists())->toBeFalse();
    });

    it('valida cada campo con su mensaje', function (array $cambios, string $campo, string $mensaje) {
        registrarIncidencia($cambios)->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
        expect(DB::table('incidencia')->count())->toBe(0);
    })->with([
        'descripción corta' => [['descripcion' => 'mal'], 'descripcion', 'Describe lo ocurrido en 10 a 500 caracteres.'],
        'descripción larga' => [['descripcion' => str_repeat('a', 501)], 'descripcion', 'Describe lo ocurrido en 10 a 500 caracteres.'],
        'sin descripción' => [['descripcion' => '  '], 'descripcion', 'Describe lo ocurrido.'],
        'gravedad' => [['gravedad' => 'MEDIA'], 'gravedad', 'Elige la gravedad: leve, moderada o grave.'],
        'tipo' => [['tipo_incidencia_id' => 999], 'tipo_incidencia_id', 'Elige el tipo de incidencia.'],
        'fecha futura' => [['fecha' => '2026-06-11'], 'fecha', 'La fecha no puede ser futura ni anterior al inicio del periodo.'],
        'antes del periodo' => [['fecha' => '2025-12-01'], 'fecha', 'La fecha no puede ser futura ni anterior al inicio del periodo.'],
        'fecha inválida' => [['fecha' => '2026-02-31'], 'fecha', 'Ingresa una fecha válida.'],
    ]);

    it('solo para estudiantes con matrícula activa', function () {
        DB::table('matricula')->where('id', $this->valeria->matricula)->update(['estado' => 'RETIRADA']);
        registrarIncidencia()->assertStatus(422)->assertJsonPath('errores.matricula_id.0', 'Elige un estudiante con matrícula activa.');
    });
});

describe('permisos', function () {
    it('pide sesión, el estudiante no entra y el docente solo ve a sus estudiantes', function () {
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/docente/incidencias')->assertUnauthorized();

        Fabrica::como($this->valeria->usuario);
        $this->getJson('/api/v1/docente/incidencias')->assertForbidden();
        $this->getJson('/api/v1/admin/incidencias')->assertForbidden();

        Fabrica::como($this->e->profe);
        registrarIncidencia()->assertCreated();
        expect($this->getJson('/api/v1/docente/incidencias/estudiantes')->json('estudiantes'))->toHaveCount(3);

        Fabrica::como($this->ajeno);
        expect($this->getJson('/api/v1/docente/incidencias')->assertOk()->json('total'))->toBe(0)
            ->and($this->getJson('/api/v1/docente/incidencias/estudiantes')->json('estudiantes'))->toBe([]);
        registrarIncidencia()->assertForbidden();
        $this->postJson('/api/v1/admin/incidencias', [])->assertForbidden();
    });

    it('un coordinador del colegio y el administrador ven todas', function () {
        registrarIncidencia()->assertCreated();
        DB::table('coordinacion')->insert(['docente_id' => DB::table('docente')->where('usuario_id', $this->ajeno)->value('id'), 'periodo_id' => $this->e->periodo, 'ambito' => 'INSTITUCION']);
        Fabrica::como($this->ajeno);
        expect($this->getJson('/api/v1/docente/incidencias')->json('total'))->toBe(1);

        Fabrica::como($this->e->admin);
        registrarIncidencia(['gravedad' => 'MODERADA'], 'admin')->assertCreated();
        expect($this->getJson('/api/v1/admin/incidencias')->json('total'))->toBe(2);
    });
});

describe('historial y filtros (RF-50)', function () {
    it('lista con autor, aula y conteo por gravedad, y filtra', function () {
        $salud = DB::table('tipo_incidencia')->where('nombre', 'Salud')->value('id');
        registrarIncidencia();
        registrarIncidencia(['gravedad' => 'GRAVE', 'fecha' => '2026-06-08']);
        registrarIncidencia(['matricula_id' => $this->e->alumnos['Aranda Ruiz']->matricula, 'tipo_incidencia_id' => $salud, 'fecha' => '2026-06-05', 'descripcion' => 'Se retiró temprano por malestar.']);

        $r = $this->getJson('/api/v1/docente/incidencias')->assertOk();
        expect($r->json('total'))->toBe(3)
            ->and($r->json('resumen'))->toBe(['LEVE' => 2, 'MODERADA' => 0, 'GRAVE' => 1])
            ->and($r->json('datos.0'))->toMatchArray(['fecha' => '2026-06-09', 'tipo' => 'Conducta', 'aula' => '3.º B', 'registrado_por' => 'Ana Torres'])
            ->and(collect($r->json('tipos'))->pluck('nombre'))->toContain('Conducta', 'Salud')
            ->and($r->json('aulas'))->toBe([['id' => $this->e->aula, 'nombre' => '3.º B']]);

        expect($this->getJson("/api/v1/docente/incidencias?tipo_id={$salud}")->json('total'))->toBe(1)
            ->and($this->getJson('/api/v1/docente/incidencias?gravedad=GRAVE')->json('datos.0.fecha'))->toBe('2026-06-08')
            ->and($this->getJson('/api/v1/docente/incidencias?buscar=quispe')->json('total'))->toBe(2)
            ->and($this->getJson("/api/v1/docente/incidencias?matricula_id={$this->e->alumnos['Aranda Ruiz']->matricula}")->json('total'))->toBe(1);
        $this->getJson('/api/v1/docente/incidencias?gravedad=MEDIA')->assertStatus(422);
    });
});
