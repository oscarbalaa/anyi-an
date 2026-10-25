<?php

use App\Servicios\Riesgo\Riesgo;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\DatosRiesgo;
use Tests\Soporte\Fabrica;

// M11 - CU-24: RF-52, RF-53, RF-54 · RN-19, RN-20, RN-21

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $this->kevin = $this->e->alumnos['Aranda Ruiz']->matricula;
    $this->valeria = $this->e->alumnos['Quispe Mendoza']->matricula;
    $this->tutora = DatosRiesgo::tutor($this->e, $this->e->otroProfe);
});

afterEach(fn () => Carbon::setTestNow());

function riesgoDe(int $matricula): object
{
    return DB::table('prediccion_riesgo')->where('matricula_id', $matricula)->orderByDesc('id')->first();
}

describe('cálculo (RF-52)', function () {
    it('un estudiante con 30 % de faltas queda en riesgo alto, con su regla, sus recomendaciones y el modelo reglas v0', function () {
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 10, [$this->kevin => 3]);
        $r = app(Riesgo::class)->recalcular();

        $p = riesgoDe($this->kevin);
        expect($r)->toMatchArray(['evaluados' => 3, 'ALTO' => 1, 'BAJO' => 2, 'alertas' => 1])
            ->and($p)->nivel_riesgo->toBe('ALTO')->subperiodo_id->toBe($this->e->b2)
            ->and((float) $p->probabilidad)->toBe(0.6)
            ->and($p->resumen_texto)->toBe('Asistencia 70 % (3 de 10 faltas) · sin notas · 0 entregas pendientes')
            ->and(DB::table('modelo_ml')->where('id', $p->modelo_id)->value('nombre'))->toBe('reglas')
            ->and(DB::table('factor_riesgo')->where('prediccion_id', $p->id)->sole())->variable->toBe('asistencia')->valor->toBe('70 %')
            ->and(DB::table('recomendacion')->where('prediccion_id', $p->id)->orderBy('prioridad')->value('descripcion'))->toBe('Contactar al apoderado para conversar sobre las faltas y acordar apoyos.')
            ->and(riesgoDe($this->valeria)->nivel_riesgo)->toBe('BAJO');
    });

    it('cuenta las entregas vencidas sin entregar, el promedio con las notas ya puestas y el descenso', function () {
        DatosRiesgo::notas($this->e->mat, $this->e->b2, $this->kevin, [13, 11, 8]);
        DatosRiesgo::notas($this->e->com, $this->e->b2, $this->kevin, [10]);
        $tareas = DatosRiesgo::vencidas($this->e->mat, $this->e->b2, 4);
        // Una entregada, otra marcada como entregada por el docente y una futura: no cuentan
        DB::table('entrega')->insert(['evaluacion_id' => $tareas[0], 'matricula_id' => $this->kevin, 'estado' => 'ENTREGADA', 'entregada_en' => now()]);
        DB::table('nota_evaluacion')->insert(['evaluacion_id' => $tareas[1], 'matricula_id' => $this->kevin, 'estado_entrega' => 'TARDIO']);
        Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Futura', 'fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-20', 'requiere_entrega' => true]);
        app(Riesgo::class)->recalcular();

        $f = json_decode(riesgoDe($this->kevin)->features, true);
        // Matemática (13 + 11 + 8) / 3 = 10,7; Comunicación 10 → (10,7 + 10) / 2 = 10,35 → 10,4
        expect($f['promedio'])->toEqual(10.4)
            ->and($f['pendientes'])->toBe(2)
            ->and($f['curso_bajo'])->toEqual(['nombre' => 'Comunicación', 'promedio' => 10])
            ->and($f['descenso'])->toEqual(['curso' => 'Matemática', 'notas' => [13, 11, 8]])
            // Promedio desaprobatorio (menos de 11): riesgo alto aunque solo deba 2 tareas
            ->and(riesgoDe($this->kevin)->nivel_riesgo)->toBe('ALTO')
            ->and(DB::table('factor_riesgo')->where('prediccion_id', riesgoDe($this->kevin)->id)->pluck('variable')->all())->toBe(['promedio', 'descenso']);

        // Con 3 entregas vencidas la regla junta el promedio y las tareas
        DatosRiesgo::vencidas($this->e->com, $this->e->b2, 1);
        app(Riesgo::class)->recalcular();
        expect(riesgoDe($this->kevin)->nivel_riesgo)->toBe('ALTO')
            ->and(DB::table('factor_riesgo')->where('prediccion_id', riesgoDe($this->kevin)->id)->pluck('variable')->all())->toBe(['promedio_y_pendientes', 'descenso']);
    });

    it('solo guarda una predicción nueva cuando algo cambia', function () {
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 10, [$this->kevin => 3]);
        app(Riesgo::class)->recalcular();
        app(Riesgo::class)->recalcular();

        expect(DB::table('prediccion_riesgo')->where('matricula_id', $this->kevin)->count())->toBe(1);
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 2, [], '2026-06-01');
        app(Riesgo::class)->recalcular();
        expect(DB::table('prediccion_riesgo')->where('matricula_id', $this->kevin)->count())->toBe(2);
    });

    it('usa los umbrales que configura el colegio', function () {
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 10, [$this->kevin => 1]);
        app(Riesgo::class)->recalcular();
        expect(riesgoDe($this->kevin)->nivel_riesgo)->toBe('BAJO');

        DB::table('institucion')->update(['umbral_asistencia_alto' => 92, 'umbral_asistencia_medio' => 95]);
        app(Riesgo::class)->recalcular();
        expect(riesgoDe($this->kevin)->nivel_riesgo)->toBe('ALTO');
    });
});

describe('cuándo se calcula (sin cron)', function () {
    it('al guardar la asistencia definitiva de una clase', function () {
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 5, [$this->kevin => 2]);
        Fabrica::como($this->e->profe);
        $this->putJson("/api/v1/docente/asistencia/cursos/{$this->e->mat}/2026-06-10", ['estado' => 'DEFINITIVO', 'registros' => [['matricula_id' => $this->kevin, 'estado' => 'F']]])->assertOk();

        // 3 faltas en 6 clases: 50 %
        expect(riesgoDe($this->kevin))->nivel_riesgo->toBe('ALTO')
            ->and(json_decode(riesgoDe($this->kevin)->features, true)['clases'])->toBe(6);
    });

    it('al guardar notas', function () {
        $ev = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['fecha_asignacion' => '2026-06-01', 'fecha_limite' => '2026-06-05', 'requiere_entrega' => false]);
        Fabrica::como($this->e->profe);
        $this->putJson("/api/v1/docente/evaluaciones/{$ev}/notas", ['registros' => [['matricula_id' => $this->kevin, 'nota' => '11,5', 'estado_entrega' => 'PENDIENTE']]])->assertOk();

        expect(riesgoDe($this->kevin)->nivel_riesgo)->toBe('MEDIO');
    });

    it('una sola vez al día, en el primer ingreso', function () {
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 10, [$this->kevin => 3]);
        app(Riesgo::class)->diario();
        expect(DB::table('prediccion_riesgo')->count())->toBe(3);

        DB::table('prediccion_riesgo')->update(['fecha' => '2026-06-09 08:00:00']);
        app(Riesgo::class)->diario();
        expect(DB::table('prediccion_riesgo')->where('fecha', '>', '2026-06-10')->count())->toBe(0);
    });
});

describe('alertas (RF-54, RN-20, RN-21)', function () {
    it('crea la alerta en riesgo medio o alto, la asigna a la tutora y le avisa', function () {
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 10, [$this->kevin => 3]);
        app(Riesgo::class)->recalcular();

        $alerta = DB::table('alerta_estudiantil')->sole();
        expect($alerta)->matricula_id->toBe($this->kevin)->estado->toBe('NUEVA')->responsable_docente_id->toBe($this->tutora)
            ->and(DB::table('historial_alerta')->where('alerta_id', $alerta->id)->orderBy('id')->pluck('accion')->all())->toBe(['GENERADA', 'ASIGNADA_DOCENTE_AULA'])
            ->and(DB::table('historial_alerta')->where('accion', 'ASIGNADA_DOCENTE_AULA')->value('detalle'))->toBe('Asignada a Ana Torres por ser tutor(a) de 3.º B.')
            ->and(DB::table('notificacion')->where('usuario_id', $this->e->otroProfe)->sole())
            ->tipo->toBe('ALERTA_ASIGNADA')->enlace->toBe("/docente/alertas?id={$alerta->id}");
    });

    it('no duplica la alerta del mismo estudiante en el bimestre; si cambia el nivel, queda en el historial', function () {
        // 3 faltas en 15 clases: 80 %
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 15, [$this->kevin => 3]);
        app(Riesgo::class)->recalcular();
        expect(riesgoDe($this->kevin)->nivel_riesgo)->toBe('ALTO');

        // Más clases presentes: 3 de 20 es 85 %, riesgo medio
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 5, [], '2026-06-08');
        app(Riesgo::class)->recalcular();
        app(Riesgo::class)->recalcular();

        $alerta = DB::table('alerta_estudiantil')->sole();
        expect($alerta->prediccion_id)->toBe(riesgoDe($this->kevin)->id)
            ->and(riesgoDe($this->kevin)->nivel_riesgo)->toBe('MEDIO')
            ->and(DB::table('historial_alerta')->where('accion', 'NIVEL_ACTUALIZADO')->sole()->detalle)->toBe('De alto a medio.');
    });

    it('una alerta cerrada no vuelve a abrirse con el mismo nivel, sí si el riesgo empeora', function () {
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 25, [$this->kevin => 3]);
        app(Riesgo::class)->recalcular();
        DB::table('alerta_estudiantil')->update(['estado' => 'CERRADA', 'fecha_cierre' => now()]);

        DatosRiesgo::clases($this->e->mat, $this->e->b2, 1, [], '2026-06-22');
        app(Riesgo::class)->recalcular();
        expect(DB::table('alerta_estudiantil')->count())->toBe(1);

        DatosRiesgo::clases($this->e->com, $this->e->b2, 10, [$this->kevin => 10]);
        app(Riesgo::class)->recalcular();
        expect(DB::table('alerta_estudiantil')->count())->toBe(2)
            ->and(DB::table('alerta_estudiantil')->where('estado', 'NUEVA')->sole()->prediccion_id)->toBe(riesgoDe($this->kevin)->id);
    });

    it('sin tutor va a la coordinación del aula; sin coordinación, avisa a la administración', function () {
        DB::table('aula')->update(['tutor_docente_id' => null]);
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 10, [$this->kevin => 3]);
        app(Riesgo::class)->recalcular();
        expect(DB::table('alerta_estudiantil')->count())->toBe(0)
            ->and(DB::table('alerta_administrativa')->where('estado', 'ABIERTA')->sole())->tipo->toBe('AULA_SIN_TUTOR')->clave->toBe("aula-sin-tutor:{$this->e->aula}");

        $coordinadora = Fabrica::cuenta('DOCENTE', '45555555');
        $docente = DB::table('docente')->where('usuario_id', $coordinadora)->value('id');
        DB::table('coordinacion')->insert(['docente_id' => $docente, 'periodo_id' => $this->e->periodo, 'ambito' => 'NIVEL', 'nivel' => 'SECUNDARIA']);
        app(Riesgo::class)->recalcular();
        expect(DB::table('alerta_estudiantil')->sole()->responsable_docente_id)->toBe($docente)
            ->and(DB::table('historial_alerta')->where('accion', 'ASIGNADA_DOCENTE_AULA')->value('detalle'))->toBe('Asignada a Ana Torres por ser coordinador(a) de 3.º B, que no tiene tutor.');
    });

    it('en un bimestre cerrado solo calcula el riesgo, sin alertas', function () {
        DatosRiesgo::clases($this->e->mat, $this->e->b2, 10, [$this->kevin => 3]);
        DB::table('subperiodo')->where('id', $this->e->b2)->update(['estado' => 'CERRADO']);
        app(Riesgo::class)->recalcular(null, $this->e->b2);

        expect(riesgoDe($this->kevin)->nivel_riesgo)->toBe('ALTO')->and(DB::table('alerta_estudiantil')->count())->toBe(0);
    });
});
