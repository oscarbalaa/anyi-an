<?php

use App\Mail\CorreoAyni;
use App\Models\Usuario;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Soporte\Fabrica;

// M7 - CU-09: RF-27, RN-23

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Mail::fake();
    Carbon::setTestNow('2026-06-10');
    $this->admin = Fabrica::comoAdmin();
    $this->p = Fabrica::periodo(['nombre' => '2026', 'estado' => 'EN_CURSO']);
});

afterEach(fn () => Carbon::setTestNow());

function docenteNuevo(array $cambios = []): array
{
    return $cambios + [
        'tipo_documento' => 'DNI',
        'numero_documento' => '46123987',
        'nombres' => 'rocío  del pilar',
        'apellidos' => 'díaz mejía',
        'fecha_nacimiento' => '1988-04-12',
        'sexo' => 'F',
        'correo' => 'rdiaz@ayni.test',
        'celular' => '987 123 456',
        'especialidad' => 'Inglés',
        'titulo' => 'Lic. en Educación',
        'fecha_inicio_contrato' => '2026-03-01',
    ];
}

describe('registrar docentes (RF-27)', function () {
    it('crea la persona, el docente y su cuenta con el DNI como usuario y contraseña temporal', function () {
        $r = $this->postJson('/api/v1/admin/docentes', docenteNuevo())->assertCreated()
            ->assertJsonPath('mensaje', 'Docente registrado. Su usuario es su DNI.')
            ->assertJsonPath('correo_enviado', true)
            ->assertJsonPath('docente.nombre', 'Rocío Del Pilar Díaz Mejía')
            ->assertJsonPath('docente.usuario', '46123987');

        $usuario = Usuario::firstWhere('username', '46123987');
        expect(Hash::check($r->json('contrasena'), $usuario->password_hash))->toBeTrue()
            ->and($usuario->debe_cambiar_password)->toBeTrue()
            ->and($usuario->email)->toBe('rdiaz@ayni.test')
            ->and($usuario->creado_por)->toBe($this->admin)
            ->and(DB::table('persona')->where('numero_documento', '46123987')->value('celular'))->toBe('987123456');
        Mail::assertSent(CorreoAyni::class, fn ($m) => $m->hasTo('rdiaz@ayni.test') && $m->datos['usuario'] === '46123987');
    });

    it('puede dejarlo como coordinador de un aula del periodo en curso', function () {
        $aula = Fabrica::aula($this->p);
        $id = $this->postJson('/api/v1/admin/docentes', docenteNuevo(['coordina_aula_id' => $aula]))->assertCreated()->json('docente.id');

        expect(DB::table('coordinacion')->where(['docente_id' => $id, 'ambito' => 'AULA', 'aula_id' => $aula])->exists())->toBeTrue();
        $this->getJson("/api/v1/admin/docentes/{$id}")->assertJsonPath('docente.coordina', 'Coordinador 3.º A');
    });

    it('valida los datos', function (array $cambios, string $campo, string $mensaje) {
        $this->postJson('/api/v1/admin/docentes', docenteNuevo($cambios))->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
    })->with([
        'CE en vez de DNI' => [['tipo_documento' => 'CE', 'numero_documento' => '001234567'], 'tipo_documento', 'Los docentes se registran con su DNI.'],
        'DNI corto' => [['numero_documento' => '4612398'], 'numero_documento', 'El DNI debe tener 8 dígitos.'],
        'nombre con número' => [['nombres' => 'Rocío2'], 'nombres', 'Solo se permiten letras, espacios, apóstrofo y guion.'],
        'menor de edad (RN-23)' => [['fecha_nacimiento' => '2010-01-01'], 'fecha_nacimiento', 'La edad (16 años) no es coherente: el personal tiene de 18 a 75 años (RN-23).'],
        'fecha futura' => [['fecha_nacimiento' => '2027-01-01'], 'fecha_nacimiento', 'La fecha de nacimiento no puede ser de hoy ni futura.'],
        'contrato al revés' => [['fecha_fin_contrato' => '2026-01-01'], 'fecha_fin_contrato', 'El fin del contrato no puede ser anterior a su inicio.'],
        'especialidad con símbolos' => [['especialidad' => '<b>'], 'especialidad', 'La especialidad tiene hasta 100 caracteres: letras, números, espacios y . , - ( )'],
        'celular' => [['celular' => '812345678'], 'celular', 'El celular debe tener 9 dígitos y empezar con 9.'],
        'aula de otro periodo' => [['coordina_aula_id' => 99999], 'coordina_aula_id', 'Elige un aula del periodo en curso.'],
    ]);

    it('acepta paréntesis en el título, como en los datos de demostración', function () {
        $this->postJson('/api/v1/admin/docentes', docenteNuevo(['titulo' => 'Licenciado(a) en Educación']))->assertCreated();
    });

    it('no repite el DNI ni lo usa si ya es el usuario de otra cuenta', function () {
        $this->postJson('/api/v1/admin/docentes', docenteNuevo())->assertCreated();
        $this->postJson('/api/v1/admin/docentes', docenteNuevo(['correo' => 'otro@ayni.test']))
            ->assertStatus(422)->assertJsonPath('errores.numero_documento.0', 'Ya hay una persona registrada con ese documento.');

        Fabrica::cuenta('DOCENTE', '41234567');
        $this->postJson('/api/v1/admin/docentes', docenteNuevo(['numero_documento' => '41234567', 'correo' => null]))
            ->assertStatus(422)->assertJsonPath('errores.numero_documento.0', 'Ese DNI ya es el usuario de otra cuenta.');
    });
});

describe('lista y edición', function () {
    it('lista con búsqueda por nombre o DNI, filtro por estado y lo que coordina', function () {
        $id = $this->postJson('/api/v1/admin/docentes', docenteNuevo(['coordina_aula_id' => Fabrica::aula($this->p)]))->json('docente.id');
        $this->postJson('/api/v1/admin/docentes', docenteNuevo(['numero_documento' => '47788990', 'nombres' => 'Marta', 'apellidos' => 'Chávez León', 'correo' => null]));

        $r = $this->getJson('/api/v1/admin/docentes?buscar=diaz')->assertOk();
        expect($r->json('total'))->toBe(1)
            ->and($r->json('datos.0'))->toMatchArray(['id' => $id, 'documento' => 'DNI 46123987', 'especialidad' => 'Inglés', 'coordina' => 'Coordinador 3.º A', 'estado' => 'ACTIVO', 'cuenta' => 'ACTIVO']);
        expect($this->getJson('/api/v1/admin/docentes?buscar=47788')->json('datos.0.nombre'))->toBe('Marta Chávez León')
            ->and($this->getJson('/api/v1/admin/docentes?estado=LICENCIA')->json('total'))->toBe(0);
    });

    it('al pasar a inactivo se desactiva su cuenta y al volver se reactiva; el DNI corregido cambia el usuario', function () {
        $id = $this->postJson('/api/v1/admin/docentes', docenteNuevo())->json('docente.id');
        $usuario = DB::table('docente')->where('id', $id)->value('usuario_id');
        Usuario::find($usuario)->createToken('web');

        $this->putJson("/api/v1/admin/docentes/{$id}", docenteNuevo(['estado' => 'INACTIVO']))->assertOk()->assertJsonPath('docente.cuenta', 'INACTIVO');
        expect(DB::table('personal_access_tokens')->where('tokenable_id', $usuario)->count())->toBe(0);

        $this->putJson("/api/v1/admin/docentes/{$id}", docenteNuevo(['estado' => 'LICENCIA', 'numero_documento' => '46123988']))->assertOk()
            ->assertJsonPath('docente.cuenta', 'ACTIVO')
            ->assertJsonPath('docente.usuario', '46123988')
            ->assertJsonPath('docente.estado', 'LICENCIA');
        expect(DB::table('auditoria')->where(['accion' => 'ACTUALIZAR', 'entidad' => 'docente', 'entidad_id' => $id])->count())->toBe(2);
    });

    it('solo la administración gestiona docentes', function () {
        app('auth')->forgetGuards();
        Sanctum::actingAs(Usuario::find(Fabrica::cuenta('DOCENTE', '41234567')));
        $this->getJson('/api/v1/admin/docentes')->assertForbidden();
        $this->postJson('/api/v1/admin/docentes', docenteNuevo())->assertForbidden();
    });
});
