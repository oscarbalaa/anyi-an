<?php

use App\Servicios\Notificaciones;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Fabrica;

// M10 - CU-23: RF-51

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Storage::fake('local');
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->e = Fabrica::aulaConCursos();
    $this->valeria = $this->e->alumnos['Quispe Mendoza'];
});

afterEach(fn () => Carbon::setTestNow());

describe('la campana de cada uno', function () {
    it('lista, cuenta y marca como leídas solo las propias', function () {
        Notificaciones::enviar([$this->valeria->usuario], 'NOTA_PUBLICADA', 'Notas publicadas', 'Se publicaron tus notas de Matemática.', '/estudiante/notas');
        Carbon::setTestNow('2026-06-10 11:00:00');
        Notificaciones::enviar([$this->valeria->usuario, $this->e->profe], 'FALTA_REGISTRADA', 'Falta registrada', 'Se registró una falta.', '/estudiante/asistencia');
        $ajena = DB::table('notificacion')->where('usuario_id', $this->e->profe)->value('id');

        Fabrica::como($this->valeria->usuario);
        $r = $this->getJson('/api/v1/notificaciones')->assertOk();
        expect($r->json('conteo'))->toBe(['todas' => 2, 'pendientes' => 2, 'leidas' => 0])
            ->and($r->json('datos.0'))->toMatchArray(['titulo' => 'Falta registrada', 'leida' => false, 'enlace' => '/estudiante/asistencia'])
            ->and($this->getJson('/api/v1/notificaciones/resumen')->json('pendientes'))->toBe(2);

        $this->postJson("/api/v1/notificaciones/{$ajena}/leer")->assertNotFound();
        expect(DB::table('notificacion')->find($ajena)->leida_en)->toBeNull();

        $propia = $r->json('datos.0.id');
        $this->postJson("/api/v1/notificaciones/{$propia}/leer")->assertOk();
        $this->postJson("/api/v1/notificaciones/{$propia}/leer")->assertOk();
        expect($this->getJson('/api/v1/notificaciones?filtro=leidas')->json('datos.0.id'))->toBe($propia)
            ->and($this->getJson('/api/v1/notificaciones?filtro=pendientes')->json('total'))->toBe(1);

        $this->postJson('/api/v1/notificaciones/leer-todas')->assertOk()->assertJsonPath('mensaje', 'Todas las notificaciones quedaron como leídas.');
        expect($this->getJson('/api/v1/notificaciones/resumen')->json('pendientes'))->toBe(0)
            ->and(DB::table('notificacion')->find($ajena)->leida_en)->toBeNull();
        $this->getJson('/api/v1/notificaciones?filtro=otras')->assertStatus(422);
    });

    it('pide sesión', function () {
        $this->getJson('/api/v1/notificaciones/resumen')->assertUnauthorized();
        $this->postJson('/api/v1/notificaciones/leer-todas')->assertUnauthorized();
    });
});

describe('avisos calculados al abrir la campana (sin cron)', function () {
    it('avisa una sola vez de las actividades que vencen en 2 días y no se entregaron', function () {
        $manana = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Práctica 7', 'fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-11']);
        Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Lejana', 'fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-20']);
        Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Examen', 'fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-11', 'requiere_entrega' => false]);
        $entregada = Fabrica::evaluacion($this->e->mat, $this->e->b2, ['titulo' => 'Ya entregada', 'fecha_asignacion' => '2026-06-08', 'fecha_limite' => '2026-06-10']);
        DB::table('entrega')->insert(['evaluacion_id' => $entregada, 'matricula_id' => $this->valeria->matricula, 'estado' => 'ENTREGADA', 'entregada_en' => now()]);

        Fabrica::como($this->valeria->usuario);
        $this->getJson('/api/v1/notificaciones/resumen')->assertOk()->assertJsonPath('pendientes', 1);
        $this->getJson('/api/v1/notificaciones')->assertOk();

        $aviso = DB::table('notificacion')->where('usuario_id', $this->valeria->usuario)->sole();
        expect($aviso)->tipo->toBe('ACTIVIDAD_POR_VENCER')->enlace->toBe("/estudiante/actividades?id={$manana}")
            ->and($aviso->mensaje)->toBe('Práctica 7 (Matemática) vence el jueves 11 de junio a las 11:59 p. m.');

        // A los otros estudiantes se les avisa cuando abren su campana
        expect(DB::table('notificacion')->where('usuario_id', $this->e->alumnos['Aranda Ruiz']->usuario)->exists())->toBeFalse();
    });

    it('a una semana del cierre de notas avisa al docente cuántos cursos le falta publicar', function () {
        DB::table('subperiodo')->where('id', $this->e->b2)->update(['fecha_cierre_notas' => '2026-06-15']);
        Fabrica::como($this->e->profe);
        $this->getJson('/api/v1/notificaciones/resumen')->assertJsonPath('pendientes', 1);
        $this->getJson('/api/v1/notificaciones/resumen')->assertJsonPath('pendientes', 1);

        expect(DB::table('notificacion')->where('usuario_id', $this->e->profe)->sole())
            ->tipo->toBe('CIERRE_PROXIMO')->titulo->toBe('Cierre del II Bimestre')
            ->mensaje->toBe('Las notas del II Bimestre se cierran el lunes 15 de junio. Tienes 1 curso sin publicar.');

        // Más de 7 días antes, nada
        DB::table('subperiodo')->where('id', $this->e->b2)->update(['fecha_cierre_notas' => '2026-07-31']);
        Fabrica::como($this->e->otroProfe);
        $this->getJson('/api/v1/notificaciones/resumen')->assertJsonPath('pendientes', 0);
    });
});

describe('eventos que notifican (RF-51)', function () {
    it('publicar notas avisa a cada estudiante del aula y a nadie más', function () {
        Fabrica::evaluacion($this->e->mat, $this->e->b2, ['fecha_asignacion' => '2026-05-25', 'fecha_limite' => '2026-05-29']);
        Fabrica::como($this->e->profe);
        $this->postJson("/api/v1/docente/cursos/{$this->e->mat}/calificaciones/publicar", ['bimestre_id' => $this->e->b2])->assertOk();

        $destinatarios = DB::table('notificacion')->where('tipo', 'NOTA_PUBLICADA')->pluck('usuario_id')->sort()->values()->all();
        expect($destinatarios)->toBe(collect($this->e->alumnos)->pluck('usuario')->sort()->values()->all())
            ->and(DB::table('notificacion')->where('usuario_id', $this->valeria->usuario)->value('enlace'))->toBe("/estudiante/notas?curso={$this->e->mat}&bimestre={$this->e->b2}");
    });
});
