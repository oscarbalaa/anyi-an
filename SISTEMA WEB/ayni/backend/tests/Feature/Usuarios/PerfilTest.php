<?php

use App\Models\Usuario;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Soporte\Fabrica;

// M5 - CU-05: RF-14 (RF-15 se prueba en Acceso/IngresoTest)

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(CatalogosSeeder::class));

function entrarComo(int $id): void
{
    app('auth')->forgetGuards();
    Sanctum::actingAs(Usuario::find($id));
}

/** Aula 3.º A en curso con su tutor, un curso del docente y la matrícula del estudiante */
function aulaConTutor(): object
{
    $periodo = Fabrica::periodo(['estado' => 'EN_CURSO']);
    $docente = Fabrica::cuenta('DOCENTE', '41234567', correo: 'luis@ayni.test');
    $docenteId = DB::table('docente')->where('usuario_id', $docente)->value('id');
    DB::table('docente')->where('id', $docenteId)->update(['especialidad' => 'Matemática']);
    DB::table('persona')->where('id', DB::table('docente')->where('id', $docenteId)->value('persona_id'))->update(['nombres' => 'Luis', 'apellidos' => 'Paredes Aranda']);
    $aula = Fabrica::aula($periodo, ['tutor_docente_id' => $docenteId]);
    $asignatura = DB::table('asignatura')->insertGetId(['codigo' => 'MAT', 'nombre' => 'Matemática']);
    DB::table('curso_aula')->insert(['aula_id' => $aula, 'asignatura_id' => $asignatura, 'docente_id' => $docenteId]);
    DB::table('coordinacion')->insert(['docente_id' => $docenteId, 'periodo_id' => $periodo, 'ambito' => 'AULA', 'aula_id' => $aula]);

    $estudiante = Fabrica::cuenta('ESTUDIANTE', 'E-2026-001', 'piki2026');
    Fabrica::matricula($aula, $periodo, DB::table('estudiante')->where('usuario_id', $estudiante)->value('id'));

    return (object) compact('docente', 'estudiante');
}

describe('ver mi perfil (RF-14)', function () {
    it('el estudiante ve su código, su aula y su tutor', function () {
        $c = aulaConTutor();
        entrarComo($c->estudiante);

        $this->getJson('/api/v1/perfil')->assertOk()->assertJson(['perfil' => [
            'nombre_completo' => 'Ana María Torres Vega',
            'codigo' => 'E-2026-001',
            'aula' => '3.º A',
            'tutor' => 'Luis Paredes',
            'estado' => 'ACTIVO',
        ]])->assertJsonPath('perfil.documento', fn ($d) => str_starts_with($d, 'DNI '));
    });

    it('el docente ve su especialidad, sus cursos, su tutoría y su coordinación', function () {
        $c = aulaConTutor();
        entrarComo($c->docente);

        $this->getJson('/api/v1/perfil')->assertOk()->assertJson(['perfil' => [
            'especialidad' => 'Matemática',
            'cursos' => ['Matemática · 3.º A'],
            'tutoria' => '3.º A',
            'coordinacion' => 'Coordinador 3.º A',
            'correo' => 'luis@ayni.test',
        ]]);
    });

    it('el administrativo ve su cargo y su ámbito', function () {
        $admin = Fabrica::cuenta('ADMIN', 'cmedina');
        DB::table('personal_administrativo')->update(['cargo' => 'Dirección académica']);
        entrarComo($admin);

        $this->getJson('/api/v1/perfil')->assertOk()
            ->assertJsonPath('perfil.cargo', 'Dirección académica')
            ->assertJsonPath('perfil.ambito', 'Toda la institución');
    });

    it('pide sesión y contraseña definitiva', function () {
        $this->getJson('/api/v1/perfil')->assertUnauthorized();
        entrarComo(Fabrica::cuenta('DOCENTE', '41234567', extra: ['debe_cambiar_password' => true]));
        $this->getJson('/api/v1/perfil')->assertForbidden()->assertJsonPath('codigo', 'DEBE_CAMBIAR_CONTRASENA');
    });
});

describe('editar celular y correo (RF-14)', function () {
    it('guarda los datos limpios en la cuenta y en la persona, con auditoría', function () {
        $id = Fabrica::cuenta('ESTUDIANTE', 'E-2026-001', 'piki2026');
        entrarComo($id);

        $this->putJson('/api/v1/perfil', ['correo' => ' Valeria@Ayni.TEST ', 'celular' => '987 321 654'])
            ->assertOk()
            ->assertJsonPath('mensaje', 'Tus datos se actualizaron.')
            ->assertJsonPath('perfil.correo', 'valeria@ayni.test')
            ->assertJsonPath('perfil.celular', '987321654');

        expect(DB::table('usuario')->where('id', $id)->value('email'))->toBe('valeria@ayni.test')
            ->and(DB::table('persona')->where('correo', 'valeria@ayni.test')->value('celular'))->toBe('987321654')
            ->and(DB::table('auditoria')->where(['accion' => 'ACTUALIZAR_PERFIL', 'usuario_id' => $id])->exists())->toBeTrue();
    });

    it('permite dejar ambos vacíos', function () {
        entrarComo(Fabrica::cuenta('DOCENTE', '41234567', correo: 'luis@ayni.test'));
        $this->putJson('/api/v1/perfil', ['correo' => '', 'celular' => ''])->assertOk()->assertJsonPath('perfil.correo', null);
    });

    it('valida el formato y que el correo no sea de otra cuenta', function (array $datos, string $campo, string $mensaje) {
        Fabrica::cuenta('DOCENTE', '42345678', correo: 'ocupado@ayni.test');
        entrarComo(Fabrica::cuenta('DOCENTE', '41234567', correo: 'luis@ayni.test'));

        $this->putJson('/api/v1/perfil', $datos)->assertStatus(422)->assertJsonPath("errores.{$campo}.0", $mensaje);
    })->with([
        [['correo' => 'luis@ayni'], 'correo', 'El correo debe tener el formato usuario@dominio.com.'],
        [['correo' => 'ocupado@ayni.test'], 'correo', 'Ese correo ya lo usa otra cuenta.'],
        [['celular' => '812345678'], 'celular', 'El celular debe tener 9 dígitos y empezar con 9.'],
        [['celular' => '9123456789'], 'celular', 'El celular debe tener 9 dígitos y empezar con 9.'],
    ]);

    it('puede volver a guardar su propio correo', function () {
        entrarComo(Fabrica::cuenta('DOCENTE', '41234567', correo: 'luis@ayni.test'));
        $this->putJson('/api/v1/perfil', ['correo' => 'luis@ayni.test', 'celular' => null])->assertOk();
    });
});
