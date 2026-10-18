<?php

use App\Models\Usuario;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Soporte\Fabrica;

// M7 - CU-10: RF-28, RF-29, RN-22, RN-23, RN-24

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Mail::fake();
    Carbon::setTestNow('2026-06-10');
    $this->admin = Fabrica::comoAdmin();
    $this->p = Fabrica::periodo(['nombre' => '2026', 'estado' => 'EN_CURSO', 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-12-18']);
    $this->a3b = Fabrica::aula($this->p, ['seccion_id' => Fabrica::seccion('B')]);
});

afterEach(fn () => Carbon::setTestNow());

function estudianteNuevo(array $cambios = []): array
{
    return $cambios + [
        'tipo_documento' => 'DNI',
        'numero_documento' => '78123456',
        'nombres' => 'diego alonso',
        'apellidos' => 'ramírez torres',
        'fecha_nacimiento' => '2012-05-14',
        'sexo' => 'M',
        'direccion' => 'Av. Pacífico 123',
    ];
}

describe('registrar estudiantes (RF-28, RN-22)', function () {
    it('genera el código E-AAAA-NNN, crea su cuenta y lo matricula si se elige un aula', function () {
        $r = $this->postJson('/api/v1/admin/estudiantes', estudianteNuevo(['aula_id' => $this->a3b]))->assertCreated()
            ->assertJsonPath('mensaje', 'Estudiante registrado con el código E-2026-001.')
            ->assertJsonPath('estudiante.nombre', 'Diego Alonso Ramírez Torres')
            ->assertJsonPath('estudiante.aula', '3.º B')
            ->assertJsonPath('estudiante.registrado_por', 'Ana Torres')
            ->assertJsonPath('estudiante.origen', 'MANUAL');

        expect($r->json('contrasena'))->toMatch('/^[a-z]{4}\d{4}$/')
            ->and(Usuario::firstWhere('username', 'E-2026-001')->debe_cambiar_password)->toBeTrue();

        $this->postJson('/api/v1/admin/estudiantes', estudianteNuevo(['numero_documento' => '78123457']))
            ->assertCreated()->assertJsonPath('estudiante.codigo', 'E-2026-002');
    });

    it('acepta CE y pasaporte con su formato', function () {
        $this->postJson('/api/v1/admin/estudiantes', estudianteNuevo(['tipo_documento' => 'CE', 'numero_documento' => '001234567']))->assertCreated();
        $this->postJson('/api/v1/admin/estudiantes', estudianteNuevo(['tipo_documento' => 'PASAPORTE', 'numero_documento' => 'pe1234567']))
            ->assertCreated()->assertJsonPath('estudiante.documento', 'PASAPORTE PE1234567');
    });

    it('valida los datos de la persona', function (array $cambios, string $campo, string $mensaje) {
        $this->postJson('/api/v1/admin/estudiantes', estudianteNuevo($cambios))->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
    })->with([
        'DNI con letra' => [['numero_documento' => '7812345A'], 'numero_documento', 'El DNI debe tener 8 dígitos.'],
        'CE corto' => [['tipo_documento' => 'CE', 'numero_documento' => '12345'], 'numero_documento', 'El CE tiene de 9 a 12 caracteres: letras mayúsculas y números.'],
        'pasaporte con símbolo' => [['tipo_documento' => 'PASAPORTE', 'numero_documento' => 'P#12345'], 'numero_documento', 'El pasaporte tiene de 6 a 12 caracteres: letras mayúsculas y números.'],
        'tipo inválido' => [['tipo_documento' => 'LIBRETA'], 'tipo_documento', 'El tipo de documento es DNI, CE o PASAPORTE.'],
        'nombre de una letra' => [['nombres' => 'J'], 'nombres', 'Los nombres deben tener al menos 2 letras.'],
        'sin fecha' => [['fecha_nacimiento' => null], 'fecha_nacimiento', 'Ingresa la fecha de nacimiento.'],
        'fecha imposible' => [['fecha_nacimiento' => '2012-02-31'], 'fecha_nacimiento', 'La fecha de nacimiento no es válida.'],
        'sexo X' => [['sexo' => 'X'], 'sexo', 'El sexo es M o F.'],
        'dirección corta' => [['direccion' => 'Av'], 'direccion', 'La dirección tiene de 5 a 200 caracteres: letras, números, espacios y . , # - / °'],
        'dirección con etiquetas' => [['direccion' => '<script>'], 'direccion', 'La dirección tiene de 5 a 200 caracteres: letras, números, espacios y . , # - / °'],
    ]);

    it('rechaza una edad que no corresponde al nivel del aula (RN-23)', function () {
        $this->postJson('/api/v1/admin/estudiantes', estudianteNuevo(['fecha_nacimiento' => '2020-01-01', 'aula_id' => $this->a3b]))
            ->assertStatus(422)->assertJsonPath('errores.aula_id.0', 'La edad (6 años) no es coherente con secundaria (de 11 a 20 años, RN-23).');
        expect(DB::table('estudiante')->count())->toBe(0);
    });
});

describe('lista, ficha y edición', function () {
    beforeEach(function () {
        $this->diego = $this->postJson('/api/v1/admin/estudiantes', estudianteNuevo(['aula_id' => $this->a3b]))->json('estudiante.id');
        $this->camila = $this->postJson('/api/v1/admin/estudiantes', estudianteNuevo(['numero_documento' => '79456789', 'nombres' => 'Camila', 'apellidos' => 'Soto Vargas', 'sexo' => 'F']))->json('estudiante.id');
    });

    it('busca por nombre, código o DNI y filtra por aula o sin matrícula', function () {
        expect($this->getJson('/api/v1/admin/estudiantes?buscar=SOTO')->json('datos.0.id'))->toBe($this->camila)
            ->and($this->getJson('/api/v1/admin/estudiantes?buscar=E-2026-001')->json('datos.0.id'))->toBe($this->diego)
            ->and($this->getJson('/api/v1/admin/estudiantes?buscar=7945')->json('total'))->toBe(1)
            ->and($this->getJson("/api/v1/admin/estudiantes?aula={$this->a3b}")->json('datos.0.id'))->toBe($this->diego)
            ->and($this->getJson('/api/v1/admin/estudiantes?aula=sin')->json('datos.0.id'))->toBe($this->camila)
            ->and($this->getJson('/api/v1/admin/estudiantes')->json('registrados'))->toBe(2);
    });

    it('muestra la ficha con sus matrículas', function () {
        $this->getJson("/api/v1/admin/estudiantes/{$this->diego}")->assertOk()
            ->assertJsonPath('estudiante.usuario', 'E-2026-001')
            ->assertJsonPath('estudiante.matriculas.0.aula', '3.º B')
            ->assertJsonPath('estudiante.matriculas.0.estado', 'ACTIVA')
            ->assertJsonPath('estudiante.matriculas.0.en_curso', true);
    });

    it('al marcarlo como trasladado se desactiva su cuenta', function () {
        $datos = estudianteNuevo(['numero_documento' => '79456789', 'nombres' => 'Camila', 'apellidos' => 'Soto Vargas', 'sexo' => 'F', 'estado' => 'TRASLADADO', 'fecha_ingreso' => '2026-06-10', 'correo' => 'camila@ayni.test']);
        $this->putJson("/api/v1/admin/estudiantes/{$this->camila}", $datos)->assertOk()
            ->assertJsonPath('estudiante.estado', 'TRASLADADO')
            ->assertJsonPath('estudiante.cuenta', 'INACTIVO')
            ->assertJsonPath('estudiante.correo', 'camila@ayni.test');
        $this->putJson("/api/v1/admin/estudiantes/{$this->camila}", ['estado' => 'ACTIVO'] + $datos)->assertJsonPath('estudiante.cuenta', 'ACTIVO');
    });
});

describe('apoderados (RF-29, RN-24)', function () {
    beforeEach(function () {
        $this->e = $this->postJson('/api/v1/admin/estudiantes', estudianteNuevo())->json('estudiante.id');
    });

    it('agrega varios, con un solo principal', function () {
        $this->postJson("/api/v1/admin/estudiantes/{$this->e}/apoderados", ['nombres' => 'rosa torres vega', 'parentesco' => 'Madre', 'celular' => '987654321', 'es_principal' => true])->assertCreated();
        $this->postJson("/api/v1/admin/estudiantes/{$this->e}/apoderados", ['nombres' => 'Jorge Ramírez', 'parentesco' => 'Padre', 'es_principal' => true])->assertCreated();

        $apoderados = $this->getJson("/api/v1/admin/estudiantes/{$this->e}")->json('estudiante.apoderados');
        expect($apoderados)->toHaveCount(2)
            ->and($apoderados[0])->toMatchArray(['nombres' => 'Jorge Ramírez', 'es_principal' => true])
            ->and($apoderados[1])->toMatchArray(['nombres' => 'Rosa Torres Vega'])
            // false de verdad, no 0 (el front lo pintaría)
            ->and($apoderados[1]['es_principal'])->toBeFalse();
    });

    it('valida, edita y quita', function () {
        $this->postJson("/api/v1/admin/estudiantes/{$this->e}/apoderados", ['nombres' => 'Rosa1', 'parentesco' => 'Vecina', 'es_principal' => false])
            ->assertStatus(422)
            ->assertJsonPath('errores.nombres.0', 'El nombre tiene de 3 a 150 caracteres: letras, espacios, apóstrofo y guion.')
            ->assertJsonPath('errores.parentesco.0', 'Elige el parentesco.');

        $this->postJson("/api/v1/admin/estudiantes/{$this->e}/apoderados", ['nombres' => 'Rosa Torres', 'parentesco' => 'Madre', 'es_principal' => false])->assertCreated();
        $id = DB::table('apoderado')->value('id');
        $this->putJson("/api/v1/admin/apoderados/{$id}", ['nombres' => 'Rosa Torres Vega', 'parentesco' => 'Tutor legal', 'correo' => 'rosa@ayni', 'es_principal' => true])
            ->assertStatus(422)->assertJsonPath('errores.correo.0', 'El correo debe tener el formato usuario@dominio.com.');
        $this->putJson("/api/v1/admin/apoderados/{$id}", ['nombres' => 'Rosa Torres Vega', 'parentesco' => 'Tutor legal', 'es_principal' => true])->assertOk();
        $this->deleteJson("/api/v1/admin/apoderados/{$id}")->assertOk();
        expect(DB::table('apoderado')->count())->toBe(0);
    });
});
