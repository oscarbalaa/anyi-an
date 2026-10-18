<?php

use Carbon\Carbon;
use Database\Seeders\DemostracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

// M2 - RF-67: datos por defecto completos, coherentes y con fechas relativas a hoy

function sembrarDemo(): void
{
    static $sembrado = false;
    if (! $sembrado) {
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        $sembrado = true;
    }
    // Las demás pruebas deben volver a migrar desde cero
    RefreshDatabaseState::$migrated = false;
}

function valor(string $sql, array $parametros = []): mixed
{
    return array_values((array) DB::selectOne($sql, $parametros))[0];
}

beforeEach(fn () => sembrarDemo());

describe('cantidades', function () {
    it('crea la estructura del colegio de demostración', function () {
        expect(DB::table('personal_administrativo')->count())->toBe(2)
            ->and(DB::table('docente')->count())->toBe(15)
            ->and(DB::table('aula')->count())->toBe(12)
            ->and(DB::table('curso_aula')->count())->toBe(72)
            ->and(DB::table('horario')->count())->toBe(12 * 13)
            ->and(DB::table('estudiante')->count())->toBeGreaterThanOrEqual(140)->toBeLessThanOrEqual(170);
    });

    it('matricula a cada estudiante una sola vez, salvo 4 importados sin matrícula', function () {
        $sinMatricula = valor('SELECT COUNT(*) FROM estudiante e WHERE NOT EXISTS (SELECT 1 FROM matricula m WHERE m.estudiante_id = e.id)');
        $repetidas = valor('SELECT COUNT(*) FROM (SELECT estudiante_id FROM matricula GROUP BY estudiante_id, periodo_id HAVING COUNT(*) > 1) x');

        expect($sinMatricula)->toBe(4)->and($repetidas)->toBe(0);
    });

    it('no supera la capacidad de ninguna aula', function () {
        expect(valor('SELECT COUNT(*) FROM aula a WHERE (SELECT COUNT(*) FROM matricula m WHERE m.aula_id = a.id) > a.capacidad'))->toBe(0);
    });

    it('da a cada estudiante exactamente un apoderado principal', function () {
        expect(valor('SELECT COUNT(*) FROM estudiante e WHERE (SELECT COUNT(*) FROM apoderado a WHERE a.estudiante_id = e.id AND a.es_principal) <> 1'))->toBe(0);
    });
});

describe('calendario relativo a hoy', function () {
    it('tiene un periodo en curso que contiene la fecha de hoy', function () {
        $periodo = DB::table('periodo_academico')->where('estado', 'EN_CURSO')->sole();
        $hoy = today()->toDateString();

        expect($periodo->fecha_inicio <= $hoy && $hoy <= $periodo->fecha_fin)->toBeTrue();
    });

    it('cierra los bimestres pasados y deja abierto el de hoy', function () {
        $bimestres = DB::table('subperiodo')->orderBy('numero')->get();
        $hoy = today()->toDateString();
        $actual = $bimestres->first(fn ($b) => $b->fecha_inicio <= $hoy && $hoy <= $b->fecha_fin);

        expect($actual)->not->toBeNull()
            ->and($actual->estado)->toBe('ABIERTO')
            ->and($actual->fecha_cierre_notas >= $hoy)->toBeTrue();
        foreach ($bimestres as $b) {
            expect($b->estado)->toBe($b->fecha_cierre_notas < $hoy ? 'CERRADO' : 'ABIERTO');
        }
    });

    it('no tiene bimestres que se crucen', function () {
        $b = DB::table('subperiodo')->orderBy('numero')->get()->values();
        for ($i = 1; $i < $b->count(); $i++) {
            expect($b[$i]->fecha_inicio > $b[$i - 1]->fecha_fin)->toBeTrue();
        }
    });

    it('no registra asistencia en el futuro, en fin de semana ni en días sin clase', function () {
        $futuras = valor('SELECT COUNT(*) FROM sesion_clase WHERE fecha >= CURDATE()');
        $finDeSemana = valor('SELECT COUNT(*) FROM sesion_clase WHERE WEEKDAY(fecha) >= 5');
        $sinHorario = valor('SELECT COUNT(*) FROM sesion_clase s WHERE NOT EXISTS (SELECT 1 FROM horario h WHERE h.curso_aula_id = s.curso_aula_id AND h.dia_semana = WEEKDAY(s.fecha) + 1)');
        $fueraDeBimestre = valor('SELECT COUNT(*) FROM sesion_clase s JOIN subperiodo b ON b.id = s.subperiodo_id WHERE s.fecha NOT BETWEEN b.fecha_inicio AND b.fecha_fin');

        expect([$futuras, $finDeSemana, $sinHorario, $fueraDeBimestre])->toBe([0, 0, 0, 0]);
    });

    it('acepta otra fecha de instalación y vuelve a armar el calendario', function () {
        Carbon::setTestNow('2027-02-10 10:00:00');
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

        $periodo = DB::table('periodo_academico')->where('estado', 'EN_CURSO')->sole();
        expect($periodo->fecha_inicio <= '2027-02-10' && $periodo->fecha_fin >= '2027-02-10')->toBeTrue()
            ->and(valor('SELECT COUNT(*) FROM sesion_clase WHERE fecha >= ?', ['2027-02-10']))->toBe(0);

        // Se restaura la fecha real para las siguientes pruebas
        Carbon::setTestNow();
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    });
});

describe('horario', function () {
    it('no cruza a un docente ni a un aula en el mismo bloque', function () {
        $cruceDocente = valor('SELECT COUNT(*) FROM (SELECT c.docente_id FROM horario h JOIN curso_aula c ON c.id = h.curso_aula_id GROUP BY c.docente_id, h.dia_semana, h.hora_inicio HAVING COUNT(*) > 1) x');
        $cruceAula = valor('SELECT COUNT(*) FROM (SELECT c.aula_id FROM horario h JOIN curso_aula c ON c.id = h.curso_aula_id GROUP BY c.aula_id, h.dia_semana, h.hora_inicio HAVING COUNT(*) > 1) x');

        expect($cruceDocente)->toBe(0)->and($cruceAula)->toBe(0);
    });

    it('no repite un curso dos veces el mismo día', function () {
        expect(valor('SELECT COUNT(*) FROM (SELECT curso_aula_id FROM horario GROUP BY curso_aula_id, dia_semana HAVING COUNT(*) > 1) x'))->toBe(0);
    });
});

describe('calificaciones (RN-09)', function () {
    it('calcula el promedio ponderado de los bimestres cerrados contando 0 lo que no tiene nota', function () {
        $diferencias = valor('
            SELECT COUNT(*) FROM calificacion_periodo c
            JOIN (
                SELECT n.matricula_id, e.curso_aula_id, e.subperiodo_id,
                       ROUND(SUM(COALESCE(n.nota, 0) * e.peso) / SUM(e.peso), 1) AS esperado
                FROM nota_evaluacion n JOIN evaluacion e ON e.id = n.evaluacion_id
                GROUP BY n.matricula_id, e.curso_aula_id, e.subperiodo_id
            ) r ON r.matricula_id = c.matricula_id AND r.curso_aula_id = c.curso_aula_id AND r.subperiodo_id = c.subperiodo_id
            WHERE ABS(c.promedio - r.esperado) > 0.05');

        expect(DB::table('calificacion_periodo')->count())->toBeGreaterThan(0)
            ->and($diferencias)->toBe(0);
    });

    it('solo tiene calificaciones cerradas en bimestres cerrados', function () {
        expect(valor("SELECT COUNT(*) FROM calificacion_periodo c JOIN subperiodo b ON b.id = c.subperiodo_id WHERE b.estado <> 'CERRADO' OR c.estado <> 'CERRADA'"))->toBe(0);
    });

    it('deja actividades por entregar y por calificar en el bimestre en curso', function () {
        $porEntregar = valor("SELECT COUNT(*) FROM evaluacion e JOIN subperiodo b ON b.id = e.subperiodo_id WHERE b.estado = 'ABIERTO' AND e.fecha_limite >= CURDATE() AND e.fecha_asignacion <= CURDATE()");
        $porCalificar = valor("SELECT COUNT(*) FROM nota_evaluacion WHERE nota IS NULL AND estado_entrega IN ('ENTREGADO', 'TARDIO')");

        expect($porEntregar)->toBeGreaterThan(0)->and($porCalificar)->toBeGreaterThan(0);
    });

    it('marca como tardías solo las entregas posteriores a la fecha límite', function () {
        $mal = valor('SELECT COUNT(*) FROM entrega en JOIN evaluacion e ON e.id = en.evaluacion_id WHERE en.es_tardia <> (DATE(en.entregada_en) > e.fecha_limite)');

        expect($mal)->toBe(0);
    });
});

describe('patrones para el seguimiento', function () {
    it('tiene estudiantes con riesgo de asistencia y otros con buena asistencia', function () {
        $porcentajes = collect(DB::select("SELECT ROUND(100 * SUM(estado IN ('P', 'T', 'J')) / COUNT(*), 1) AS p FROM asistencia GROUP BY matricula_id"))->pluck('p');

        expect($porcentajes->filter(fn ($p) => $p < 85)->count())->toBeGreaterThanOrEqual(2)
            ->and($porcentajes->filter(fn ($p) => $p >= 95)->count())->toBeGreaterThan(80);
    });

    it('reproduce los casos de los mockups', function () {
        $asistencia = fn ($apellidos) => (float) valor("SELECT ROUND(100 * SUM(a.estado IN ('P', 'T', 'J')) / COUNT(*), 1) FROM asistencia a JOIN matricula m ON m.id = a.matricula_id JOIN estudiante e ON e.id = m.estudiante_id JOIN persona p ON p.id = e.persona_id WHERE p.apellidos = ?", [$apellidos]);

        expect($asistencia('Quispe Mendoza'))->toBeGreaterThan(94)
            ->and($asistencia('Sáenz Palacios'))->toBeLessThan(85)
            ->and($asistencia('Mori Lazo'))->toBeLessThan(93);
    });

    it('registra incidencias y deja casos para las alertas administrativas', function () {
        expect(DB::table('incidencia')->count())->toBeGreaterThan(10)
            ->and(DB::table('usuario')->where('estado', 'BLOQUEADO')->count())->toBe(2);
    });
});

describe('usuarios de demostración', function () {
    it('crea los accesos del bloc de credenciales', function (string $usuario, string $rol, string $clave) {
        $u = DB::table('usuario')->join('rol', 'rol.id', '=', 'usuario.rol_id')->where('username', $usuario)->select('usuario.*', 'rol.codigo')->sole();

        expect($u->codigo)->toBe($rol)
            ->and($u->estado)->toBe('ACTIVO')
            ->and((bool) $u->debe_cambiar_password)->toBeFalse()
            ->and(Hash::check($clave, $u->password_hash))->toBeTrue();
    })->with([
        'administrador' => ['cmedina', 'ADMIN', DemostracionSeeder::CLAVE_PERSONAL],
        'administrador 2' => ['promero', 'ADMIN', DemostracionSeeder::CLAVE_PERSONAL],
        'coordinador (Luis Paredes)' => ['41234567', 'DOCENTE', DemostracionSeeder::CLAVE_PERSONAL],
        'docente (Carmen Rivas)' => ['42345678', 'DOCENTE', DemostracionSeeder::CLAVE_PERSONAL],
        'estudiante secundaria (Valeria)' => ['E-2026-001', 'ESTUDIANTE', DemostracionSeeder::CLAVE_ESTUDIANTE],
        'estudiante primaria (Mateo)' => ['E-2026-002', 'ESTUDIANTE', DemostracionSeeder::CLAVE_ESTUDIANTE],
    ]);

    it('hace a Luis Paredes coordinador del 3.º A y a Carmen Rivas tutora del 3.º B', function () {
        $coordina = valor("SELECT CONCAT(g.numero, s.nombre) FROM coordinacion c JOIN docente d ON d.id = c.docente_id JOIN usuario u ON u.id = d.usuario_id JOIN aula a ON a.id = c.aula_id JOIN grado g ON g.id = a.grado_id JOIN seccion s ON s.id = a.seccion_id WHERE u.username = '41234567' AND c.ambito = 'AULA'");
        $tutora = valor("SELECT CONCAT(g.numero, s.nombre) FROM aula a JOIN docente d ON d.id = a.tutor_docente_id JOIN usuario u ON u.id = d.usuario_id JOIN grado g ON g.id = a.grado_id JOIN seccion s ON s.id = a.seccion_id WHERE u.username = '42345678'");

        expect($coordina)->toBe('3A')->and($tutora)->toBe('3B');
    });
});

describe('reglas de los datos', function () {
    it('usa códigos de estudiante con el formato E-AAAA-NNN', function () {
        expect(DB::table('estudiante')->pluck('codigo')->reject(fn ($c) => preg_match('/^E-\d{4}-\d{3}$/', $c))->all())->toBe([]);
    });

    it('usa DNI de 8 dígitos y celulares peruanos válidos', function () {
        expect(DB::table('persona')->pluck('numero_documento')->reject(fn ($d) => preg_match('/^\d{8}$/', $d))->all())->toBe([])
            ->and(DB::table('persona')->whereNotNull('celular')->pluck('celular')->reject(fn ($c) => preg_match('/^9\d{8}$/', $c))->all())->toBe([]);
    });

    it('respeta las edades por nivel (RN-23)', function () {
        $fuera = valor("
            SELECT COUNT(*) FROM matricula m
            JOIN estudiante e ON e.id = m.estudiante_id JOIN persona p ON p.id = e.persona_id
            JOIN aula a ON a.id = m.aula_id JOIN grado g ON g.id = a.grado_id
            WHERE NOT (TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, m.fecha) BETWEEN IF(g.nivel = 'PRIMARIA', 5, 11) AND IF(g.nivel = 'PRIMARIA', 14, 20))");

        expect($fuera)->toBe(0);
    });

    it('guarda los archivos de ejemplo de entregas y material', function () {
        foreach (DB::table('archivo')->pluck('ruta') as $ruta) {
            expect(Storage::disk('local')->exists($ruta))->toBeTrue();
        }
    });
});
