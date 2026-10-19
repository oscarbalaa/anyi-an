<?php

use App\Models\Usuario;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Soporte\Fabrica;

// Límite de peticiones de la API: por cuenta, no por IP (todo un aula sale por la misma IP)

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(CatalogosSeeder::class));

function pedirComo(string $token)
{
    app('auth')->forgetGuards();

    return test()->withToken($token)->getJson('/api/v1/auth/yo');
}

it('cuenta 120 peticiones por minuto a cada cuenta, aunque compartan la IP', function () {
    $ana = Usuario::find(Fabrica::cuenta('DOCENTE', '41111111'))->createToken('web', ['docente'])->plainTextToken;
    $luis = Usuario::find(Fabrica::cuenta('DOCENTE', '42222222'))->createToken('web', ['docente'])->plainTextToken;

    for ($i = 0; $i < 120; $i++) {
        pedirComo($ana)->assertOk();
    }
    pedirComo($ana)->assertStatus(429);
    pedirComo($luis)->assertOk();
});
