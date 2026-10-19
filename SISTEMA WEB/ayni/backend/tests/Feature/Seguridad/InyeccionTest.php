<?php

use App\Support\Pdf;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Fabrica;
use Tests\Soporte\Leer;

// M13 - intentos de inyección SQL, XSS y de fórmulas en las planillas

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    $this->withoutMiddleware(ThrottleRequests::class);
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
});

afterEach(fn () => Carbon::setTestNow());

const INYECCIONES_SQL = [
    "' OR '1'='1",
    "' OR 1=1 -- ",
    '" OR ""="',
    '1; DROP TABLE usuario; --',
    "%' UNION SELECT username, password_hash, 1, 1 FROM usuario -- ",
    "\\' OR 1=1 #",
    "') OR SLEEP(2) -- ",
];

it('la búsqueda de cada lista trata el texto como texto: no filtra, no rompe y no borra nada', function () {
    $usuarios = DB::table('usuario')->count();
    $listas = [
        [$this->e->admin, '/api/v1/admin/usuarios'],
        [$this->e->admin, '/api/v1/admin/estudiantes'],
        [$this->e->admin, '/api/v1/admin/docentes'],
        [$this->e->admin, '/api/v1/admin/matriculas'],
        [$this->e->admin, '/api/v1/admin/matriculas/candidatos'],
        [$this->e->admin, '/api/v1/admin/alertas'],
        [$this->e->admin, '/api/v1/admin/incidencias'],
        [$this->e->profe, '/api/v1/docente/incidencias/estudiantes'],
        [$this->e->profe, '/api/v1/docente/alertas'],
    ];

    foreach ($listas as [$usuario, $ruta]) {
        Fabrica::como($usuario);
        foreach (INYECCIONES_SQL as $texto) {
            $inicio = microtime(true);
            $r = $this->getJson($ruta.'?'.http_build_query(['buscar' => $texto]));
            $etiqueta = "{$ruta} ← {$texto}";

            // 200 sin resultados, o 422 si a la lista le falta otro filtro; nunca un error del servidor
            expect($r->status())->toBeIn([200, 422], $etiqueta)
                ->and($r->getContent())->not->toContain('$2y$')
                ->and(microtime(true) - $inicio)->toBeLessThan(1.5, "{$etiqueta}: SLEEP no se ejecutó");
            if ($r->status() === 200) {
                $filas = $r->json('datos') ?? $r->json('estudiantes') ?? $r->json('candidatos') ?? [];
                expect($filas)->toBe([], $etiqueta);
            }
        }
    }

    expect(DB::table('usuario')->count())->toBe($usuarios);
});

it('los filtros numéricos y de lista rechazan texto con SQL (422)', function () {
    Fabrica::como($this->e->admin);
    foreach ([
        '/api/v1/admin/alertas?aula_id=1%20OR%201%3D1',
        '/api/v1/admin/alertas?nivel=ALTO%27%20OR%20%271%27%3D%271',
        '/api/v1/admin/usuarios?estado=ACTIVO%27--',
        '/api/v1/admin/incidencias?aula_id=1;DROP%20TABLE%20incidencia',
        '/api/v1/admin/reportes/asistencia?aula_id=1%20OR%201%3D1',
    ] as $ruta) {
        expect($this->getJson($ruta)->status())->toBe(422, $ruta);
    }
});

it('guarda el HTML como texto y lo devuelve como JSON: no hay HTML que se ejecute (XSS)', function () {
    $ataque = '<script>alert("xss")</script><img src=x onerror=alert(1)> Interrumpe la clase.';
    Fabrica::como($this->e->profe);
    $this->postJson('/api/v1/docente/incidencias', [
        'matricula_id' => $this->e->alumnos['Aranda Ruiz']->matricula,
        'tipo_incidencia_id' => DB::table('tipo_incidencia')->value('id'),
        'gravedad' => 'LEVE',
        'fecha' => '2026-06-10',
        'descripcion' => $ataque,
    ])->assertCreated();

    $r = $this->getJson('/api/v1/docente/incidencias')->assertOk();
    // Se devuelve tal cual dentro de un JSON; React lo muestra como texto (no usa dangerouslySetInnerHTML)
    expect($r->headers->get('Content-Type'))->toStartWith('application/json')
        ->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($r->json('datos.0.descripcion'))->toBe($ataque);
});

it('los nombres de personas no aceptan etiquetas ni símbolos de código', function () {
    Fabrica::como($this->e->admin);
    $r = $this->postJson('/api/v1/admin/estudiantes', ['nombres' => '<script>alert(1)</script>', 'apellidos' => "Pérez'; DROP TABLE persona; --"])
        ->assertStatus(422);

    expect($r->json('errores'))->toHaveKeys(['nombres', 'apellidos']);
});

it('un texto que empieza con "=" va a Excel como texto, no como fórmula', function () {
    $formula = '=HYPERLINK("http://malo.example","Clic aquí")';
    Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => $formula, 'fecha_asignacion' => '2026-06-01', 'fecha_limite' => '2026-06-05']);

    Fabrica::como($this->e->profe);
    $r = $this->get("/api/v1/docente/cursos/{$this->e->mat}/calificaciones/excel")->assertOk();
    $xml = Leer::xmlExcel($r);

    expect($xml)->toContain('HYPERLINK')
        ->and($xml)->not->toContain('<f>')
        ->and(Leer::excel($r)[0])->toContain($formula);
});

it('el PDF escapa los paréntesis y la barra invertida: un texto no puede cerrar la cadena e inyectar comandos', function () {
    expect(Pdf::escapar('Ana) Tj (malo\\'))->toBe('Ana\\) Tj \\(malo\\\\')
        ->and(Pdf::escapar("línea\nnueva"))->toBe(mb_convert_encoding('línea nueva', 'Windows-1252', 'UTF-8'));
});
