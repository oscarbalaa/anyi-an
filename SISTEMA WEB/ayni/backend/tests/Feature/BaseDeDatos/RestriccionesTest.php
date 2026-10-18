<?php

use Database\Seeders\CatalogosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Fabrica;

// M2 - La base de datos rechaza por sí misma los datos inválidos (además del backend)

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(CatalogosSeeder::class));

function rechaza(callable $accion, string $restriccion): void
{
    expect($accion)->toThrow(QueryException::class, $restriccion);
}

describe('seguridad y personas', function () {
    it('no acepta un estado de usuario inventado', function () {
        rechaza(fn () => Fabrica::usuario('ADMIN', ['estado' => 'SUSPENDIDO']), 'ck_usuario_estado');
    });

    it('no repite el nombre de usuario', function () {
        Fabrica::usuario('ADMIN', ['username' => 'cmedina']);
        rechaza(fn () => Fabrica::usuario('ADMIN', ['username' => 'cmedina']), 'Duplicate entry');
    });

    it('no repite un documento de identidad', function () {
        Fabrica::persona(['numero_documento' => '41234567']);
        rechaza(fn () => Fabrica::persona(['numero_documento' => '41234567']), 'ux_persona_documento');
    });

    it('no acepta un tipo de documento inválido ni un sexo inválido', function () {
        rechaza(fn () => Fabrica::persona(['tipo_documento' => 'LIBRETA']), 'ck_persona_tipo_documento');
        rechaza(fn () => Fabrica::persona(['sexo' => 'X']), 'ck_persona_sexo');
    });

    it('guarda ñ y tildes sin alterarlas', function () {
        $id = Fabrica::persona(['nombres' => 'Ñahui Ángela', 'apellidos' => 'Núñez Zúñiga']);

        expect(DB::table('persona')->find($id))
            ->nombres->toBe('Ñahui Ángela')
            ->apellidos->toBe('Núñez Zúñiga');
    });

    it('permite un solo apoderado principal por estudiante', function () {
        $estudiante = Fabrica::estudiante();
        $fila = fn ($principal) => ['estudiante_id' => $estudiante, 'nombres' => 'Rosa Torres', 'es_principal' => $principal];

        DB::table('apoderado')->insert($fila(true));
        DB::table('apoderado')->insert($fila(false));
        DB::table('apoderado')->insert($fila(false));
        rechaza(fn () => DB::table('apoderado')->insert($fila(true)), 'ux_apoderado_principal');
    });
});

describe('estructura académica', function () {
    it('permite un solo periodo en curso', function () {
        Fabrica::periodo(['estado' => 'EN_CURSO']);
        Fabrica::periodo(['estado' => 'CERRADO']);
        rechaza(fn () => Fabrica::periodo(['estado' => 'EN_CURSO']), 'ux_periodo_en_curso');
    });

    it('exige que el periodo termine después de empezar', function () {
        rechaza(fn () => Fabrica::periodo(['fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-03-01']), 'ck_periodo_fechas');
    });

    it('solo acepta bimestres del 1 al 4 y no repetidos', function () {
        $periodo = Fabrica::periodo();
        Fabrica::bimestre($periodo);
        rechaza(fn () => Fabrica::bimestre($periodo), 'ux_subperiodo_numero');
        rechaza(fn () => Fabrica::bimestre($periodo, ['numero' => 5]), 'ck_subperiodo_numero');
    });

    it('no acepta una fecha de cierre anterior al inicio del bimestre', function () {
        rechaza(fn () => Fabrica::bimestre(Fabrica::periodo(), ['fecha_cierre_notas' => '2026-02-01']), 'ck_subperiodo_fechas');
    });

    it('solo tiene primaria (1-6) y secundaria (1-5)', function () {
        rechaza(fn () => DB::table('grado')->insert(['nivel' => 'INICIAL', 'numero' => 1, 'nombre' => 'Inicial', 'orden' => 0]), 'ck_grado_nivel');
        rechaza(fn () => DB::table('grado')->insert(['nivel' => 'SECUNDARIA', 'numero' => 6, 'nombre' => 'Sexto', 'orden' => 12]), 'ck_grado_numero');
    });

    it('no repite grado y sección en un mismo periodo y limita la capacidad', function () {
        $periodo = Fabrica::periodo();
        Fabrica::aula($periodo);
        rechaza(fn () => Fabrica::aula($periodo), 'ux_aula_grado_seccion');
        rechaza(fn () => Fabrica::aula($periodo, ['seccion_id' => Fabrica::seccion('B'), 'capacidad' => 80]), 'ck_aula_capacidad');
    });

    it('exige que la coordinación tenga el dato de su ámbito', function () {
        $base = ['docente_id' => Fabrica::docente(), 'periodo_id' => Fabrica::periodo()];

        rechaza(fn () => DB::table('coordinacion')->insert($base + ['ambito' => 'AULA']), 'ck_coordinacion_ambito');
        rechaza(fn () => DB::table('coordinacion')->insert($base + ['ambito' => 'NIVEL', 'nivel' => 'INICIAL']), 'ck_coordinacion_ambito');
        DB::table('coordinacion')->insert($base + ['ambito' => 'NIVEL', 'nivel' => 'PRIMARIA']);
        DB::table('coordinacion')->insert($base + ['ambito' => 'INSTITUCION']);

        expect(DB::table('coordinacion')->count())->toBe(2);
    });

    it('permite una sola matrícula por estudiante y año', function () {
        $periodo = Fabrica::periodo();
        $aula = Fabrica::aula($periodo);
        $estudiante = Fabrica::estudiante();

        Fabrica::matricula($aula, $periodo, $estudiante);
        rechaza(fn () => Fabrica::matricula(Fabrica::aula($periodo, ['seccion_id' => Fabrica::seccion('B')]), $periodo, $estudiante), 'ux_matricula_estudiante_periodo');
    });

    it('no permite matricular en un aula de otro periodo', function () {
        $aula2026 = Fabrica::aula(Fabrica::periodo());
        $otroPeriodo = Fabrica::periodo();

        rechaza(fn () => Fabrica::matricula($aula2026, $otroPeriodo), 'fk_matricula_aula_periodo');
    });

    it('valida el día y las horas del horario', function () {
        $curso = Fabrica::curso(Fabrica::aula(Fabrica::periodo()));

        rechaza(fn () => DB::table('horario')->insert(['curso_aula_id' => $curso, 'dia_semana' => 8, 'hora_inicio' => '08:00', 'hora_fin' => '09:30']), 'ck_horario_dia');
        rechaza(fn () => DB::table('horario')->insert(['curso_aula_id' => $curso, 'dia_semana' => 1, 'hora_inicio' => '09:30', 'hora_fin' => '08:00']), 'ck_horario_horas');
    });
});

describe('evaluaciones, notas y asistencia', function () {
    it('acepta notas de 0 a 20 y rechaza 25 y negativas', function () {
        $e = Fabrica::escenario();
        $nota = fn ($valor) => DB::table('nota_evaluacion')->updateOrInsert(
            ['evaluacion_id' => $e->evaluacion, 'matricula_id' => $e->matricula],
            ['nota' => $valor],
        );

        $nota(20);
        $nota(0);
        $nota(15.5);
        $nota(null);
        rechaza(fn () => $nota(25), 'ck_nota_rango');
        rechaza(fn () => $nota(-1), 'ck_nota_rango');
        rechaza(fn () => $nota(20.5), 'ck_nota_rango');
    });

    it('no repite la nota de un estudiante en una evaluación', function () {
        $e = Fabrica::escenario();
        $fila = ['evaluacion_id' => $e->evaluacion, 'matricula_id' => $e->matricula, 'nota' => 14];

        DB::table('nota_evaluacion')->insert($fila);
        rechaza(fn () => DB::table('nota_evaluacion')->insert($fila), 'ux_nota_evaluacion');
    });

    it('solo acepta estados de entrega conocidos', function () {
        $e = Fabrica::escenario();
        rechaza(fn () => DB::table('nota_evaluacion')->insert(['evaluacion_id' => $e->evaluacion, 'matricula_id' => $e->matricula, 'estado_entrega' => 'PERDIDO']), 'ck_nota_estado_entrega');
    });

    it('exige peso entre 1 y 10 y fecha límite posterior a la asignación', function () {
        $e = Fabrica::escenario();
        rechaza(fn () => Fabrica::evaluacion($e->curso, $e->bimestre, ['peso' => 0]), 'ck_evaluacion_peso');
        rechaza(fn () => Fabrica::evaluacion($e->curso, $e->bimestre, ['peso' => 11]), 'ck_evaluacion_peso');
        rechaza(fn () => Fabrica::evaluacion($e->curso, $e->bimestre, ['fecha_limite' => '2026-03-01']), 'ck_evaluacion_fechas');
    });

    it('exige la fecha de una entrega marcada como entregada', function () {
        $e = Fabrica::escenario();
        rechaza(fn () => DB::table('entrega')->insert(['evaluacion_id' => $e->evaluacion, 'matricula_id' => $e->matricula, 'estado' => 'ENTREGADA']), 'ck_entrega_estado');
    });

    it('solo acepta P, F, T o J en la asistencia y una vez por clase', function () {
        $e = Fabrica::escenario();
        $sesion = DB::table('sesion_clase')->insertGetId(['curso_aula_id' => $e->curso, 'subperiodo_id' => $e->bimestre, 'fecha' => '2026-03-09']);
        $fila = ['sesion_clase_id' => $sesion, 'matricula_id' => $e->matricula, 'estado' => 'P'];

        DB::table('asistencia')->insert($fila);
        rechaza(fn () => DB::table('asistencia')->insert($fila), 'ux_asistencia');
        rechaza(fn () => DB::table('sesion_clase')->insert(['curso_aula_id' => $e->curso, 'subperiodo_id' => $e->bimestre, 'fecha' => '2026-03-09']), 'ux_sesion_clase');
        DB::table('asistencia')->where('sesion_clase_id', $sesion)->delete();
        rechaza(fn () => DB::table('asistencia')->insert(['estado' => 'X'] + $fila), 'ck_asistencia_estado');
    });

    it('solo acepta gravedad leve, moderada o grave en incidencias', function () {
        $e = Fabrica::escenario();
        rechaza(fn () => DB::table('incidencia')->insert([
            'matricula_id' => $e->matricula,
            'tipo_incidencia_id' => DB::table('tipo_incidencia')->value('id'),
            'registrado_por' => Fabrica::usuario(),
            'fecha' => '2026-03-10',
            'gravedad' => 'MUY GRAVE',
            'descripcion' => 'Prueba de gravedad',
        ]), 'ck_incidencia_gravedad');
    });

    it('limita el promedio del bimestre a 0-20 y a estados conocidos', function () {
        $e = Fabrica::escenario();
        $fila = ['matricula_id' => $e->matricula, 'curso_aula_id' => $e->curso, 'subperiodo_id' => $e->bimestre];

        rechaza(fn () => DB::table('calificacion_periodo')->insert($fila + ['promedio' => 21]), 'ck_calificacion_promedio');
        rechaza(fn () => DB::table('calificacion_periodo')->insert($fila + ['estado' => 'APROBADA']), 'ck_calificacion_estado');
    });
});

describe('alertas y configuración', function () {
    function prediccion(object $e, array $extra = []): int
    {
        return DB::table('prediccion_riesgo')->insertGetId($extra + [
            'matricula_id' => $e->matricula,
            'subperiodo_id' => $e->bimestre,
            'modelo_id' => DB::table('modelo_ml')->value('id'),
            'probabilidad' => 0.8,
            'nivel_riesgo' => 'ALTO',
            'features' => json_encode(['asistencia' => 80]),
        ]);
    }

    it('permite un solo modelo activo', function () {
        rechaza(fn () => DB::table('modelo_ml')->insert(['nombre' => 'ml', 'version' => 'v1', 'activo' => true]), 'ux_modelo_activo');
    });

    it('valida probabilidad y nivel de riesgo', function () {
        $e = Fabrica::escenario();
        rechaza(fn () => prediccion($e, ['probabilidad' => 1.5]), 'ck_prediccion_probabilidad');
        rechaza(fn () => prediccion($e, ['nivel_riesgo' => 'CRITICO']), 'ck_prediccion_nivel');
    });

    it('permite una sola alerta activa por estudiante y bimestre', function () {
        $e = Fabrica::escenario();
        $alerta = fn ($estado) => DB::table('alerta_estudiantil')->insert([
            'prediccion_id' => prediccion($e),
            'matricula_id' => $e->matricula,
            'subperiodo_id' => $e->bimestre,
            'responsable_docente_id' => Fabrica::docente(),
            'estado' => $estado,
        ]);

        $alerta('CERRADA');
        $alerta('NUEVA');
        rechaza(fn () => $alerta('EN_SEGUIMIENTO'), 'ux_alerta_activa');
        $alerta('DESCARTADA');
    });

    it('no duplica una alerta administrativa abierta', function () {
        $fila = fn ($estado) => ['tipo' => 'USUARIO_BLOQUEADO', 'severidad' => 'CRITICO', 'titulo' => 'Usuario bloqueado', 'clave' => 'usuario:7', 'estado' => $estado];

        DB::table('alerta_administrativa')->insert($fila('ABIERTA'));
        rechaza(fn () => DB::table('alerta_administrativa')->insert($fila('ABIERTA')), 'ux_alerta_admin_abierta');
        DB::table('alerta_administrativa')->insert($fila('RESUELTA'));
        rechaza(fn () => DB::table('alerta_administrativa')->insert(['tipo' => 'OTRO'] + $fila('RESUELTA')), 'ck_alerta_admin_tipo');
    });

    it('valida los umbrales del colegio y la escala literal', function () {
        rechaza(fn () => DB::table('institucion')->update(['umbral_asistencia_alto' => 95]), 'ck_institucion_asistencia');
        rechaza(fn () => DB::table('institucion')->update(['nota_aprobatoria' => 25]), 'ck_institucion');
        rechaza(fn () => DB::table('escala_literal')->insert(['codigo' => 'X', 'descripcion' => 'x', 'nota_minima' => 15, 'nota_maxima' => 10, 'orden' => 9]), 'ck_escala_rango');
    });
});

describe('configuración de la conexión', function () {
    it('trabaja en hora de Lima', function () {
        expect(DB::selectOne('SELECT @@session.time_zone AS zona')->zona)->toBe('-05:00');
    });

    it('usa utf8mb4 en todas las tablas', function () {
        $otras = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_collation NOT LIKE 'utf8mb4%'");

        expect($otras)->toBe([]);
    });

    it('documenta cada tabla con un comentario', function () {
        $sinComentario = collect(DB::select("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_comment = '' AND table_name NOT IN ('migrations', 'personal_access_tokens')"))->pluck('t');

        expect($sinComentario->all())->toBe([]);
    });
});
