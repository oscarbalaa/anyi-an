<?php

use App\Servicios\Riesgo\Riesgo;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\DatosRiesgo;
use Tests\Soporte\Fabrica;

// M11 - CU-25: RF-55

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $this->kevin = $this->e->alumnos['Aranda Ruiz']->matricula;
    $this->tutora = DatosRiesgo::tutor($this->e, $this->e->otroProfe);
    DatosRiesgo::clases($this->e->mat, $this->e->b2, 10, [$this->kevin => 3]);
    app(Riesgo::class)->recalcular();
    $this->alerta = DB::table('alerta_estudiantil')->value('id');
    $this->url = fn (string $rol = 'docente', string $accion = '') => "/api/v1/{$rol}/alertas/{$this->alerta}".($accion ? "/{$accion}" : '');
    Fabrica::como($this->e->otroProfe);
});

afterEach(fn () => Carbon::setTestNow());

function intervencion(array $cambios = []): array
{
    return $cambios + ['tipo' => 'Reunión con el apoderado', 'descripcion' => '  Se conversó   con la madre sobre las faltas. ', 'fecha' => '2026-06-10', 'resultado' => 'Se comprometió a enviarlo todos los días.'];
}

describe('detalle y lista', function () {
    it('muestra el nivel, las reglas activadas, las recomendaciones, el encargado y el historial', function () {
        $r = $this->getJson(($this->url)())->assertOk();

        expect($r->json('estudiante'))->toMatchArray(['nombre' => 'Alumno Aranda Ruiz', 'aula' => '3.º B'])
            ->and($r->json('prediccion'))->toMatchArray(['nivel' => 'ALTO', 'probabilidad' => 60, 'modelo' => 'reglas v0'])
            ->and($r->json('prediccion.factores.0'))->toMatchArray(['variable' => 'asistencia', 'titulo' => 'Faltas frecuentes', 'valor' => '70 %'])
            ->and($r->json('prediccion.recomendaciones.0.descripcion'))->toBe('Contactar al apoderado para conversar sobre las faltas y acordar apoyos.')
            ->and($r->json('responsable.nombre'))->toBe('Ana Torres')
            ->and($r->json('permisos'))->toBe(['gestionar' => true, 'reasignar' => false])
            ->and($r->json('transiciones'))->toBe(['EN_SEGUIMIENTO', 'DESCARTADA'])
            ->and(collect($r->json('historial'))->pluck('usuario')->all())->toBe(['Sistema', 'Sistema']);
    });

    it('lista las alertas a mi cargo con su resumen y filtra', function () {
        $r = $this->getJson('/api/v1/docente/alertas')->assertOk();
        expect($r->json('total'))->toBe(1)
            ->and($r->json('datos.0'))->toMatchArray(['nivel' => 'ALTO', 'probabilidad' => 60, 'estado' => 'NUEVA', 'responsable' => 'Ana Torres', 'a_mi_cargo' => true])
            ->and($r->json('datos.0.factor_principal'))->toStartWith('Asistencia de 70 %')
            ->and($r->json('resumen'))->toMatchArray(['abiertas' => 1, 'nuevas' => 1, 'sin_intervencion' => 1]);

        expect($this->getJson('/api/v1/docente/alertas?nivel=MEDIO')->json('total'))->toBe(0)
            ->and($this->getJson('/api/v1/docente/alertas?buscar=aranda')->json('total'))->toBe(1)
            ->and($this->getJson('/api/v1/docente/alertas?estado=CERRADA')->json('total'))->toBe(0);
        $this->getJson('/api/v1/docente/alertas?estado=otro')->assertStatus(422);
    });
});

describe('estados (transiciones válidas e inválidas)', function () {
    it('nueva → en seguimiento → cerrada, con intervención y justificación', function () {
        $this->postJson(($this->url)('docente', 'estado'), ['estado' => 'EN_SEGUIMIENTO'])->assertOk()->assertJsonPath('mensaje', 'La alerta pasó a seguimiento.');

        $this->postJson(($this->url)('docente', 'estado'), ['estado' => 'CERRADA', 'justificacion' => 'Mejoró su asistencia.'])
            ->assertStatus(422)->assertJsonPath('errores.estado.0', 'Registra al menos una intervención antes de cerrar la alerta.');

        $this->postJson(($this->url)('docente', 'intervenciones'), intervencion())->assertCreated()->assertJsonPath('mensaje', 'Intervención registrada.');
        $r = $this->postJson(($this->url)('docente', 'estado'), ['estado' => 'CERRADA', 'justificacion' => 'Asistió todas las clases de las últimas dos semanas.'])->assertOk();

        expect($r->json('alerta.estado'))->toBe('CERRADA')
            ->and($r->json('transiciones'))->toBe([])
            ->and(DB::table('alerta_estudiantil')->find($this->alerta)->fecha_cierre)->not->toBeNull()
            ->and(collect($r->json('historial'))->pluck('accion')->all())->toBe(['GENERADA', 'ASIGNADA_DOCENTE_AULA', 'ESTADO_CAMBIADO', 'INTERVENCION', 'CERRADA'])
            ->and($r->json('historial.4'))->toMatchArray(['detalle' => 'En seguimiento → Cerrada. Asistió todas las clases de las últimas dos semanas.', 'usuario' => 'Ana Torres'])
            ->and(DB::table('auditoria')->where('accion', 'CAMBIAR_ESTADO_ALERTA')->count())->toBe(2);
    });

    it('rechaza las transiciones inválidas', function () {
        $this->postJson(($this->url)('docente', 'estado'), ['estado' => 'CERRADA', 'justificacion' => 'Ya está bien de nuevo.'])
            ->assertStatus(422)->assertJsonPath('errores.estado.0', 'Primero pasa la alerta a seguimiento y registra una intervención.');

        $this->postJson(($this->url)('docente', 'estado'), ['estado' => 'DESCARTADA'])->assertStatus(422)->assertJsonPath('errores.justificacion.0', 'Escribe la justificación.');
        $this->postJson(($this->url)('docente', 'estado'), ['estado' => 'DESCARTADA', 'justificacion' => 'corto'])->assertStatus(422)->assertJsonPath('errores.justificacion.0', 'La justificación debe tener de 10 a 400 caracteres.');
        $this->postJson(($this->url)('docente', 'estado'), ['estado' => 'NUEVA'])->assertStatus(422)->assertJsonPath('errores.estado.0', 'Elige el nuevo estado de la alerta.');
        $this->postJson(($this->url)('docente', 'estado'), ['estado' => 'DESCARTADA', 'justificacion' => 'Las faltas fueron por un viaje familiar avisado.'])->assertOk();

        $this->postJson(($this->url)('docente', 'estado'), ['estado' => 'EN_SEGUIMIENTO'])
            ->assertStatus(422)->assertJsonPath('errores.estado.0', 'La alerta ya está descartada: no cambia de estado.');
        $this->postJson(($this->url)('docente', 'intervenciones'), intervencion())->assertStatus(422)->assertJsonPath('mensaje', 'La alerta ya está cerrada: no admite intervenciones.');
    });

    it('la primera intervención pasa la alerta a seguimiento', function () {
        $r = $this->postJson(($this->url)('docente', 'intervenciones'), intervencion())->assertCreated();

        expect($r->json('alerta.estado'))->toBe('EN_SEGUIMIENTO')
            ->and($r->json('intervenciones.0'))->toMatchArray(['tipo' => 'Reunión con el apoderado', 'descripcion' => 'Se conversó con la madre sobre las faltas.', 'registrado_por' => 'Ana Torres']);
    });

    it('valida la intervención campo por campo', function (array $cambios, string $campo, string $mensaje) {
        $this->postJson(($this->url)('docente', 'intervenciones'), intervencion($cambios))->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
        expect(DB::table('intervencion')->count())->toBe(0);
    })->with([
        'tipo' => [['tipo' => 'Paseo'], 'tipo', 'Elige el tipo de intervención.'],
        'sin descripción' => [['descripcion' => ' '], 'descripcion', 'Describe la intervención.'],
        'descripción corta' => [['descripcion' => 'ok'], 'descripcion', 'Describe la intervención en 10 a 500 caracteres.'],
        'resultado largo' => [['resultado' => str_repeat('a', 301)], 'resultado', 'El resultado no debe tener más de 300 caracteres.'],
        'fecha futura' => [['fecha' => '2026-06-11'], 'fecha', 'La fecha debe estar entre la creación de la alerta y hoy.'],
        'antes de la alerta' => [['fecha' => '2026-06-09'], 'fecha', 'La fecha debe estar entre la creación de la alerta y hoy.'],
        'fecha inválida' => [['fecha' => '2026-02-30'], 'fecha', 'Ingresa una fecha válida.'],
    ]);
});

describe('permisos', function () {
    it('el estudiante no entra; un docente de otra aula no la ve; el profesor del curso la ve sin gestionarla', function () {
        Fabrica::como($this->e->alumnos['Aranda Ruiz']->usuario);
        $this->getJson('/api/v1/docente/alertas')->assertForbidden();

        Fabrica::como(Fabrica::cuenta('DOCENTE', '43333333'));
        $this->getJson(($this->url)())->assertForbidden();
        expect($this->getJson('/api/v1/docente/alertas?encargado=todas')->json('total'))->toBe(0);

        Fabrica::como($this->e->profe);
        expect($this->getJson('/api/v1/docente/alertas')->json('total'))->toBe(0)
            ->and($this->getJson('/api/v1/docente/alertas?encargado=todas')->json('total'))->toBe(1)
            ->and($this->getJson(($this->url)())->json('permisos.gestionar'))->toBeFalse();
        $this->postJson(($this->url)('docente', 'estado'), ['estado' => 'EN_SEGUIMIENTO'])->assertForbidden();
        $this->postJson(($this->url)('docente', 'intervenciones'), intervencion())->assertForbidden();

        $this->getJson('/api/v1/docente/alertas/999999')->assertNotFound();
    });

    it('la coordinación del aula y la administración reasignan con justificación, y avisan al nuevo encargado', function () {
        $this->postJson(($this->url)('docente', 'responsable'), ['docente_id' => 1, 'justificacion' => 'Lo ve todos los días.'])->assertForbidden();

        $coordinador = Fabrica::cuenta('DOCENTE', '44444444');
        DB::table('coordinacion')->insert(['docente_id' => DB::table('docente')->where('usuario_id', $coordinador)->value('id'), 'periodo_id' => $this->e->periodo, 'ambito' => 'AULA', 'aula_id' => $this->e->aula]);
        Fabrica::como($coordinador);
        $r = $this->getJson(($this->url)())->assertOk();
        expect($r->json('permisos'))->toBe(['gestionar' => true, 'reasignar' => true])
            ->and(collect($r->json('candidatos'))->pluck('rol')->sort()->values()->all())->toBe(['Coordinación', 'Matemática', 'Tutor(a), Comunicación']);

        $profe = DB::table('docente')->where('usuario_id', $this->e->profe)->value('id');
        $ajeno = DB::table('docente')->where('usuario_id', Fabrica::cuenta('DOCENTE', '43333333'))->value('id');
        $this->postJson(($this->url)('docente', 'responsable'), ['docente_id' => $ajeno, 'justificacion' => 'Tiene más tiempo libre.'])
            ->assertStatus(422)->assertJsonPath('errores.docente_id.0', 'Elige a un docente del estudiante: su tutor, uno de sus profesores o la coordinación.');
        $this->postJson(($this->url)('docente', 'responsable'), ['docente_id' => $profe, 'justificacion' => 'corta'])->assertStatus(422);

        $r = $this->postJson(($this->url)('docente', 'responsable'), ['docente_id' => $profe, 'justificacion' => 'El factor principal es Matemática: el profesor dicta reforzamiento.'])
            ->assertOk()->assertJsonPath('mensaje', 'Encargado actualizado.');
        expect(DB::table('alerta_estudiantil')->value('responsable_docente_id'))->toBe($profe)
            ->and(collect($r->json('historial'))->last()['accion'])->toBe('REASIGNADA')
            ->and(DB::table('notificacion')->where('usuario_id', $this->e->profe)->sole()->titulo)->toBe('Se te asignó una alerta');

        // El nuevo encargado ya la tiene a su cargo
        Fabrica::como($this->e->profe);
        expect($this->getJson('/api/v1/docente/alertas')->json('total'))->toBe(1);

        Fabrica::como($this->e->admin);
        $this->getJson(($this->url)('admin'))->assertOk()->assertJsonPath('permisos.reasignar', true);
        $this->postJson(($this->url)('admin', 'responsable'), ['docente_id' => $this->tutora, 'justificacion' => 'Vuelve con su tutora por acuerdo del equipo.'])->assertOk();
        $this->postJson(($this->url)('admin', 'responsable'), ['docente_id' => $this->tutora, 'justificacion' => 'Vuelve con su tutora por acuerdo del equipo.'])
            ->assertStatus(422)->assertJsonPath('errores.docente_id.0', 'La alerta ya está a cargo de ese docente.');
    });
});
