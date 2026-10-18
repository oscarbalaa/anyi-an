<?php

use App\Models\Usuario;
use App\Servicios\Asistencia\ReportesAsistencia;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Soporte\Fabrica;

// M8 - CU-13, CU-14 y CU-15: RF-33 a RF-37, RN-06, RN-07, RN-27

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    // Miércoles 10 de junio de 2026, a media mañana
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->admin = Fabrica::cuenta('ADMIN', 'cmedina');
    $this->profe = Fabrica::cuenta('DOCENTE', '41234567');
    $this->otroProfe = Fabrica::cuenta('DOCENTE', '42345678');
    $docente = DB::table('docente')->where('usuario_id', $this->profe)->value('id');

    $this->p = Fabrica::periodo(['nombre' => '2026', 'estado' => 'EN_CURSO']);
    $this->b1 = Fabrica::bimestre($this->p);
    $this->b2 = Fabrica::bimestre($this->p, ['numero' => 2, 'nombre' => 'II Bimestre', 'fecha_inicio' => '2026-05-18', 'fecha_fin' => '2026-07-24', 'fecha_cierre_notas' => '2026-07-31']);
    $this->aula = Fabrica::aula($this->p, ['seccion_id' => Fabrica::seccion('B')]);
    $mat = DB::table('asignatura')->insertGetId(['codigo' => 'MAT', 'nombre' => 'Matemática']);
    $com = DB::table('asignatura')->insertGetId(['codigo' => 'COM', 'nombre' => 'Comunicación']);
    $this->curso = DB::table('curso_aula')->insertGetId(['aula_id' => $this->aula, 'asignatura_id' => $mat, 'docente_id' => $docente]);
    $this->otroCurso = DB::table('curso_aula')->insertGetId(['aula_id' => $this->aula, 'asignatura_id' => $com, 'docente_id' => DB::table('docente')->where('usuario_id', $this->otroProfe)->value('id')]);
    // Matemática: lunes y miércoles; Comunicación: lunes
    DB::table('horario')->insert([
        ['curso_aula_id' => $this->curso, 'dia_semana' => 1, 'hora_inicio' => '09:45:00', 'hora_fin' => '11:15:00'],
        ['curso_aula_id' => $this->curso, 'dia_semana' => 3, 'hora_inicio' => '08:00:00', 'hora_fin' => '09:30:00'],
        ['curso_aula_id' => $this->otroCurso, 'dia_semana' => 1, 'hora_inicio' => '08:00:00', 'hora_fin' => '09:30:00'],
    ]);

    $this->alumnos = [];
    foreach (['Aranda Ruiz', 'Quispe Mendoza', 'Ramírez Torres'] as $i => $apellidos) {
        $codigo = sprintf('E-2026-%03d', $i + 1);
        $persona = Fabrica::persona(['nombres' => 'Alumno', 'apellidos' => $apellidos]);
        $usuario = Fabrica::usuario('ESTUDIANTE', ['username' => $codigo]);
        $estudiante = DB::table('estudiante')->insertGetId(['persona_id' => $persona, 'usuario_id' => $usuario, 'codigo' => $codigo]);
        $this->alumnos[$apellidos] = ['matricula' => Fabrica::matricula($this->aula, $this->p, $estudiante), 'usuario' => $usuario];
    }
    $this->m = fn (string $apellidos) => $this->alumnos[$apellidos]['matricula'];
    $this->como = function (int $usuario) {
        app('auth')->forgetGuards();
        Sanctum::actingAs(Usuario::find($usuario));
    };
    ($this->como)($this->profe);
});

afterEach(fn () => Carbon::setTestNow());

function guardarAsistencia(int $curso, string $fecha, array $registros = [], string $estado = 'DEFINITIVO', string $rol = 'docente')
{
    return test()->putJson("/api/v1/{$rol}/asistencia/cursos/{$curso}/{$fecha}", ['estado' => $estado, 'registros' => $registros]);
}

describe('permisos', function () {
    it('pide sesión (401)', function () {
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/docente/asistencia/cursos')->assertUnauthorized();
        $this->getJson('/api/v1/estudiante/asistencia')->assertUnauthorized();
        $this->getJson('/api/v1/admin/asistencia/resumen')->assertUnauthorized();
    });

    it('cada rol entra solo a lo suyo (403)', function () {
        $this->getJson('/api/v1/admin/asistencia/resumen')->assertForbidden();
        $this->getJson('/api/v1/estudiante/asistencia')->assertForbidden();
        ($this->como)($this->alumnos['Quispe Mendoza']['usuario']);
        $this->getJson('/api/v1/docente/asistencia/cursos')->assertForbidden();
        guardarAsistencia($this->curso, '2026-06-10')->assertForbidden();
    });

    it('solo el docente del curso registra su asistencia', function () {
        guardarAsistencia($this->otroCurso, '2026-06-08')->assertForbidden();
        $this->getJson("/api/v1/docente/asistencia/cursos/{$this->otroCurso}/fechas")->assertForbidden();
        expect(DB::table('sesion_clase')->count())->toBe(0);
    });
});

describe('registro del docente (RF-33)', function () {
    it('lista sus cursos y los días de clase hasta hoy', function () {
        $cursos = $this->getJson('/api/v1/docente/asistencia/cursos')->assertOk();
        expect($cursos->json('cursos'))->toHaveCount(1)
            ->and($cursos->json('cursos.0'))->toMatchArray(['id' => $this->curso, 'nombre' => 'Matemática · 3.º B', 'dias' => [1, 3]])
            ->and($cursos->json('bimestre.nombre'))->toBe('II Bimestre');

        $fechas = collect($this->getJson("/api/v1/docente/asistencia/cursos/{$this->curso}/fechas")->assertOk()->json('fechas'));
        expect($fechas->first())->toBe(['fecha' => '2026-06-10', 'estado' => 'SIN_REGISTRO', 'hoy' => true])
            ->and($fechas->pluck('fecha')->all())->toContain('2026-06-08', '2026-05-18', '2026-05-20')
            ->and(array_intersect($fechas->pluck('fecha')->all(), ['2026-06-09', '2026-06-15', '2026-05-13']))->toBe([]);
    });

    it('muestra a todos presentes por defecto', function () {
        $r = $this->getJson("/api/v1/docente/asistencia/cursos/{$this->curso}/2026-06-10")->assertOk();

        expect($r->json('sesion'))->toMatchArray(['estado' => 'SIN_REGISTRO', 'editable' => true, 'aviso' => 'Puedes corregir este registro hasta las 11:59 p. m. de hoy.'])
            ->and(collect($r->json('estudiantes'))->pluck('estado')->unique()->all())->toBe(['P'])
            ->and($r->json('estudiantes.0.nombre'))->toBe('Aranda Ruiz, Alumno')
            ->and($r->json('resumen'))->toBe(['P' => 3, 'F' => 0, 'T' => 0, 'J' => 0]);
    });

    it('guarda solo las excepciones, avisa la falta al estudiante y deja auditoría', function () {
        $r = guardarAsistencia($this->curso, '2026-06-10', [
            ['matricula_id' => ($this->m)('Ramírez Torres'), 'estado' => 'F'],
            ['matricula_id' => ($this->m)('Aranda Ruiz'), 'estado' => 'T', 'observacion' => '  Llegó   8:20 '],
        ])->assertOk();

        expect($r->json('mensaje'))->toBe('Asistencia guardada.')
            ->and($r->json('resumen'))->toBe(['P' => 1, 'F' => 1, 'T' => 1, 'J' => 0])
            ->and($r->json('sesion.estado'))->toBe('DEFINITIVO')
            ->and(DB::table('asistencia')->where('matricula_id', ($this->m)('Aranda Ruiz'))->value('observacion'))->toBe('Llegó 8:20')
            ->and(DB::table('asistencia')->where('estado', 'P')->value('registrado_por'))->toBe($this->profe);

        $aviso = DB::table('notificacion')->sole();
        expect($aviso)->usuario_id->toBe($this->alumnos['Ramírez Torres']['usuario'])->tipo->toBe('FALTA_REGISTRADA')
            ->and($aviso->mensaje)->toBe('Se registró una falta en Matemática el miércoles 10 de junio.')
            ->and(DB::table('auditoria')->where('accion', 'REGISTRAR_ASISTENCIA')->count())->toBe(1);
    });

    it('no duplica la clase: guardar otra vez la corrige y deja quién cambió', function () {
        guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => ($this->m)('Quispe Mendoza'), 'estado' => 'F']])->assertOk();
        guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => ($this->m)('Quispe Mendoza'), 'estado' => 'J', 'observacion' => 'Cita médica con constancia']])->assertOk();

        $fila = DB::table('asistencia')->where('matricula_id', ($this->m)('Quispe Mendoza'))->sole();
        expect(DB::table('sesion_clase')->count())->toBe(1)
            ->and(DB::table('asistencia')->count())->toBe(3)
            ->and($fila)->estado->toBe('J')->modificado_por->toBe($this->profe)
            ->and(json_decode(DB::table('auditoria')->where('accion', 'CORREGIR_ASISTENCIA')->value('detalle'), true)['cambios'])
            ->toBe([(string) ($this->m)('Quispe Mendoza') => ['antes' => 'F', 'despues' => 'J']]);
    });

    it('el borrador no se notifica ni lo ve el estudiante; luego pasa a definitivo y no vuelve atrás', function () {
        guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => ($this->m)('Quispe Mendoza'), 'estado' => 'F']], 'BORRADOR')
            ->assertOk()->assertJsonPath('mensaje', 'Borrador guardado. Aún no lo ven los estudiantes.');
        expect(DB::table('notificacion')->count())->toBe(0);
        ($this->como)($this->alumnos['Quispe Mendoza']['usuario']);
        expect($this->getJson('/api/v1/estudiante/asistencia')->json('resumen.clases'))->toBe(0);

        ($this->como)($this->profe);
        guardarAsistencia($this->curso, '2026-06-10')->assertOk();
        expect(DB::table('notificacion')->count())->toBe(1);
        guardarAsistencia($this->curso, '2026-06-10', [], 'BORRADOR')->assertStatus(422)
            ->assertJsonPath('errores.estado.0', 'Esta asistencia ya es definitiva: puedes corregirla, pero no volver a borrador.');
    });
});

describe('fechas válidas (RN-06)', function () {
    it('rechaza fechas futuras, días sin clase, fuera del bimestre o inexistentes', function () {
        guardarAsistencia($this->curso, '2026-06-15')->assertStatus(422)->assertJsonPath('errores.fecha.0', 'No se registra asistencia de una fecha futura (RN-06).');
        guardarAsistencia($this->curso, '2026-06-09')->assertStatus(422)->assertJsonPath('errores.fecha.0', 'Matemática no tiene clase los martes según el horario (RN-06).');
        guardarAsistencia($this->curso, '2026-05-13')->assertStatus(422)->assertJsonPath('errores.fecha.0', 'La fecha está fuera de los bimestres del periodo (RN-06).');
        guardarAsistencia($this->curso, '2026-02-31')->assertStatus(422)->assertJsonPath('errores.fecha.0', 'La fecha no es válida.');
        $this->getJson("/api/v1/docente/asistencia/cursos/{$this->curso}/2026-06-09")->assertStatus(422);
        expect(DB::table('sesion_clase')->count())->toBe(0);
    });

    it('no se modifica la asistencia de un bimestre cerrado', function () {
        DB::table('subperiodo')->where('id', $this->b2)->update(['estado' => 'CERRADO']);
        guardarAsistencia($this->curso, '2026-06-10')->assertStatus(422)->assertJsonPath('mensaje', 'El II Bimestre está cerrado: su asistencia ya no se modifica.');
        ($this->como)($this->admin);
        guardarAsistencia($this->curso, '2026-06-08', [], 'DEFINITIVO', 'admin')->assertStatus(422);
    });
});

describe('corrección hasta la medianoche (RN-07)', function () {
    it('el docente corrige hasta las 11:59 p. m.; después solo el administrador, y queda registrado', function () {
        guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => ($this->m)('Ramírez Torres'), 'estado' => 'F']])->assertOk();

        Carbon::setTestNow('2026-06-10 23:59:00');
        guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => ($this->m)('Aranda Ruiz'), 'estado' => 'T', 'observacion' => 'Llegó 8:15']])->assertOk();

        Carbon::setTestNow('2026-06-11 00:00:01');
        $this->getJson("/api/v1/docente/asistencia/cursos/{$this->curso}/2026-06-10")->assertOk()->assertJsonPath('sesion.editable', false);
        guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => ($this->m)('Ramírez Torres'), 'estado' => 'P']])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Ya pasó la medianoche del día de la clase: solo el administrador puede corregir esta asistencia (RN-07).');

        ($this->como)($this->admin);
        guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => ($this->m)('Ramírez Torres'), 'estado' => 'J', 'observacion' => 'Presentó certificado médico']], 'DEFINITIVO', 'admin')
            ->assertOk()->assertJsonPath('mensaje', 'Asistencia corregida. El cambio queda registrado con tu nombre.')
            ->assertJsonPath('estudiantes.2.corregido_por', 'Ana Torres');
        expect(DB::table('asistencia')->where('matricula_id', ($this->m)('Ramírez Torres'))->value('modificado_por'))->toBe($this->admin)
            ->and(DB::table('auditoria')->where('accion', 'CORREGIR_ASISTENCIA')->where('usuario_id', $this->admin)->exists())->toBeTrue();
    });

    it('una clase pasada sin registro se registra una vez y queda cerrada para el docente', function () {
        $r = $this->getJson("/api/v1/docente/asistencia/cursos/{$this->curso}/2026-06-08")->assertOk();
        expect($r->json('sesion.editable'))->toBeTrue()->and($r->json('sesion.aviso'))->toContain('Clase pasada sin registrar');

        guardarAsistencia($this->curso, '2026-06-08')->assertOk()->assertJsonPath('sesion.editable', false);
        guardarAsistencia($this->curso, '2026-06-08')->assertStatus(422);
    });
});

describe('validaciones (docs/validaciones.md)', function () {
    it('rechaza estados y observaciones inválidos con mensajes por campo', function () {
        $q = ($this->m)('Quispe Mendoza');
        guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => $q, 'estado' => 'X']])->assertStatus(422)
            ->assertJsonPath('errores', ['registros.0.estado' => ['El estado de asistencia debe ser P, F, T o J.']]);
        $error = fn ($r, string $campo) => $r->assertStatus(422)->json('errores')[$campo][0] ?? null;
        expect($error(guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => $q, 'estado' => 'J', 'observacion' => '   ']]), 'registros.0.observacion'))
            ->toBe('Escribe el motivo de la justificación.')
            ->and($error(guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => $q, 'estado' => 'T', 'observacion' => str_repeat('a', 301)]]), 'registros.0.observacion'))
            ->toBe('La observación no debe tener más de 300 caracteres.')
            ->and($error(guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => $q, 'estado' => 'P'], ['matricula_id' => $q, 'estado' => 'F']]), 'registros.1.matricula_id'))
            ->toBe('Un estudiante aparece dos veces en la lista.');
        guardarAsistencia($this->curso, '2026-06-10', [], 'LISTO')->assertStatus(422)
            ->assertJsonPath('errores.estado.0', 'Elige si guardas un borrador o la asistencia definitiva.');

        $ajeno = Fabrica::matricula(Fabrica::aula($this->p), $this->p);
        guardarAsistencia($this->curso, '2026-06-10', [['matricula_id' => $ajeno, 'estado' => 'F']])->assertStatus(422)
            ->assertJsonPath('errores.registros.0', 'Hay estudiantes que no pertenecen a esta clase.');
        expect(DB::table('sesion_clase')->count())->toBe(0);
    });
});

describe('aviso de faltas en la semana (RF-37)', function () {
    it('cuenta las faltas definitivas de la semana en cualquier curso, sin la clase actual', function () {
        $q = ($this->m)('Quispe Mendoza');
        ($this->como)($this->otroProfe);
        guardarAsistencia($this->otroCurso, '2026-06-08', [['matricula_id' => $q, 'estado' => 'F']])->assertOk();
        ($this->como)($this->profe);
        guardarAsistencia($this->curso, '2026-06-08', [['matricula_id' => $q, 'estado' => 'F']])->assertOk();
        // La semana anterior no cuenta
        guardarAsistencia($this->curso, '2026-06-03', [['matricula_id' => $q, 'estado' => 'F']])->assertOk();

        $hoy = collect($this->getJson("/api/v1/docente/asistencia/cursos/{$this->curso}/2026-06-10")->json('estudiantes'))->firstWhere('matricula_id', $q);
        expect($hoy['faltas_semana'])->toBe(2);
        $lunes = collect($this->getJson("/api/v1/docente/asistencia/cursos/{$this->curso}/2026-06-08")->json('estudiantes'))->firstWhere('matricula_id', $q);
        expect($lunes['faltas_semana'])->toBe(1);
    });
});

describe('mi asistencia (RF-36, RN-27)', function () {
    it('calcula el porcentaje, los conteos por curso y por mes, y lista los registros con su hora', function () {
        $q = ($this->m)('Quispe Mendoza');
        guardarAsistencia($this->curso, '2026-05-27', [['matricula_id' => $q, 'estado' => 'F']])->assertOk();
        guardarAsistencia($this->curso, '2026-06-01', [['matricula_id' => $q, 'estado' => 'T']])->assertOk();
        guardarAsistencia($this->curso, '2026-06-03', [['matricula_id' => $q, 'estado' => 'J', 'observacion' => 'Permiso del apoderado']])->assertOk();
        guardarAsistencia($this->curso, '2026-06-10')->assertOk();
        guardarAsistencia($this->curso, '2026-06-08', [['matricula_id' => $q, 'estado' => 'F']], 'BORRADOR')->assertOk();

        ($this->como)($this->alumnos['Quispe Mendoza']['usuario']);
        $r = $this->getJson('/api/v1/estudiante/asistencia')->assertOk();

        expect($r->json('resumen'))->toBe(['clases' => 4, 'presentes' => 1, 'faltas' => 1, 'tardanzas' => 1, 'justificadas' => 1, 'porcentaje' => 75])
            ->and($r->json('aula'))->toBe('3.º B')
            ->and($r->json('bimestre.nombre'))->toBe('II Bimestre')
            ->and($r->json('meta'))->toBe(90)
            ->and(collect($r->json('por_curso'))->firstWhere('asignatura', 'Matemática'))->toMatchArray(['clases' => 4, 'faltas' => 1, 'porcentaje' => 75])
            ->and(collect($r->json('por_curso'))->firstWhere('asignatura', 'Comunicación'))->toMatchArray(['clases' => 0, 'porcentaje' => null])
            ->and(collect($r->json('por_mes'))->pluck('porcentaje', 'mes')->all())->toBe(['2026-05' => 0, '2026-06' => 100])
            ->and($r->json('registros.0'))->toBe(['fecha' => '2026-06-10', 'curso' => 'Matemática', 'hora' => '08:00', 'estado' => 'P', 'observacion' => null])
            ->and($r->json('registros.2'))->toMatchArray(['fecha' => '2026-06-01', 'hora' => '09:45', 'estado' => 'T']);

        $otro = $this->getJson("/api/v1/estudiante/asistencia?bimestre_id={$this->b1}")->assertOk();
        expect($otro->json('resumen.clases'))->toBe(0)->and($otro->json('resumen.porcentaje'))->toBeNull();
    });

    it('redondea a un decimal', function () {
        expect(ReportesAsistencia::porcentaje(3, 1))->toBe(66.7)
            ->and(ReportesAsistencia::porcentaje(8, 1))->toBe(87.5)
            ->and(ReportesAsistencia::porcentaje(0, 0))->toBeNull();
    });
});

describe('administración (RF-35)', function () {
    beforeEach(fn () => ($this->como)($this->admin));

    it('resume el % por aula y avisa qué docentes no registran', function () {
        guardarAsistencia($this->curso, '2026-06-08', [['matricula_id' => ($this->m)('Quispe Mendoza'), 'estado' => 'F']], 'DEFINITIVO', 'admin')->assertOk();

        $r = $this->getJson('/api/v1/admin/asistencia/resumen')->assertOk();
        expect(collect($r->json('aulas'))->firstWhere('id', $this->aula))->toMatchArray(['estudiantes' => 3, 'clases' => 1, 'faltas' => 1, 'porcentaje' => 66.7]);

        // Matemática: 7 clases del 18 de mayo a ayer, menos la del 8 ya registrada; Comunicación: 4 lunes
        $sinRegistro = $r->json('sin_registro');
        $matematica = $sinRegistro[0]['cursos'][0];
        expect(array_column($sinRegistro, 'total'))->toBe([6, 4])
            ->and($sinRegistro[0]['desde'])->toBe('2026-05-18')
            ->and($matematica['id'])->toBe($this->curso)
            ->and(array_intersect($matematica['fechas'], ['2026-06-08', '2026-06-10']))->toBe([]);
    });

    it('lista los cursos por aula para corregir', function () {
        $r = $this->getJson('/api/v1/admin/asistencia/cursos')->assertOk();
        expect($r->json('aulas.0.nombre'))->toBe('3.º B')
            ->and(collect($r->json('aulas.0.cursos'))->pluck('asignatura')->all())->toBe(['Comunicación', 'Matemática']);
    });

    it('borra el registro de una clase que no se dictó, con motivo', function () {
        guardarAsistencia($this->curso, '2026-06-08', [], 'DEFINITIVO', 'admin')->assertOk();
        $ruta = "/api/v1/admin/asistencia/cursos/{$this->curso}/2026-06-08";

        $this->deleteJson($ruta, ['motivo' => 'feriado'])->assertStatus(422)->assertJsonPath('errores.motivo.0', 'Explica el motivo en 10 a 300 caracteres.');
        $this->deleteJson($ruta, ['motivo' => 'No hubo clase por simulacro de sismo.'])->assertOk();
        expect(DB::table('sesion_clase')->count())->toBe(0)->and(DB::table('asistencia')->count())->toBe(0)
            ->and(DB::table('auditoria')->where('accion', 'ELIMINAR_ASISTENCIA')->exists())->toBeTrue();
        $this->deleteJson($ruta, ['motivo' => 'No hubo clase por simulacro de sismo.'])->assertNotFound();

        ($this->como)($this->profe);
        $this->deleteJson("/api/v1/docente/asistencia/cursos/{$this->curso}/2026-06-08")->assertStatus(405);
    });
});

describe('estudiantes retirados', function () {
    it('siguen en las clases donde ya figuraban, pero no en las nuevas', function () {
        guardarAsistencia($this->curso, '2026-06-08')->assertOk();
        DB::table('matricula')->where('id', ($this->m)('Aranda Ruiz'))->update(['estado' => 'RETIRADA']);

        expect($this->getJson("/api/v1/docente/asistencia/cursos/{$this->curso}/2026-06-08")->json('estudiantes'))->toHaveCount(3)
            ->and($this->getJson("/api/v1/docente/asistencia/cursos/{$this->curso}/2026-06-10")->json('estudiantes'))->toHaveCount(2);
    });
});
